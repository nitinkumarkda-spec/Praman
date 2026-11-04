<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Codes\PropertyStatus;
use App\Codes\YesNo;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\PlotType;
use App\Models\Property;
use App\Models\Scheme;
use App\Models\Sector;
use App\Services\Audit;
use App\Services\PropertyService;
use App\Services\Uploads;
use Throwable;

/** Data Entry / Super Admin → Add Property (the whole generated grid at once) / edit one property (only while Listed). */
final class PropertyController extends Controller
{
    /** Most plots one Add Property grid may save at once. */
    private const MAX_PLOTS = 500;
    /** Where the Property screens live; a save returns to the one it came from. */
    private const LISTS = ['/data-entry/properties', '/admin/masters/properties'];

    /**
     * One function for both screens:
     *   add  — scheme_id, sector_id?, site_plan?, plots = JSON [{plot_no, plot_type_id, area_value?, unit_id, remarks?, corner}]
     *   edit — id, sector_id?, site_plan?, confirm_location = YES and that plot's fields
     *          (plot_no, plot_type_id, area_value?, unit_id, reserve_price?, remarks?, is_corner)
     * Sector, area, reserve price, description and site plan are optional.
     */
    public function save(): void
    {
        $id = $this->id() ?: null;
        $this->authorize($id ? 'property.edit' : 'property.create');
        $in = $this->request;
        $list = in_array($in->str('back'), self::LISTS, true) ? $in->str('back') : self::LISTS[0];
        $prop = null;
        if ($id) {
            $prop = Property::find($id);
            if (!$prop || !$prop['is_active']) {
                $this->fail('Property not found.');
            }
            if ($prop['status_id'] !== PropertyStatus::LISTED) {
                $this->flash('error', 'This property was sent for verification meanwhile and can no longer be edited.');
                $this->done(null, $list);
            }
            if ($in->str('confirm_location') !== YesNo::YES) {
                $this->fail('Please confirm the scheme and sector (answer "Yes").', 'confirm_location');
            }
        }
        $schemeId = $prop ? (int) $prop['scheme_id'] : $this->id('scheme_id');
        $sch = Scheme::find($schemeId);
        if (!$sch || (!$prop && !$sch['is_active'])) {
            $this->fail('Please select an active scheme.', 'scheme_id');
        }
        $sectorId = $this->id('sector_id') ?: null;
        $sector = $sectorId ? Sector::find($sectorId) : null;
        if ($sectorId && (!$sector || (int) $sector['scheme_id'] !== $schemeId || (!$sector['is_active'] && $sectorId !== ($prop['sector_id'] ?? null)))) {
            $this->fail('Please select an active sector of the selected scheme.', 'sector_id');
        }
        $where = $sch['scheme_name'] . ($sector ? ' · ' . $sector['sector_name'] : '');

        // the plots to save: the whole grid, or the one property being edited
        if ($prop) {
            // a form without the reserve price box keeps the price the property already has
            $price = $in->input('reserve_price') === null ? (string) ($prop['reserve_price'] ?? '') : $in->str('reserve_price');
            $plots = [['plot_no' => $in->str('plot_no'), 'plot_type_id' => $in->str('plot_type_id'), 'area_value' => $in->str('area_value'),
                'unit_id' => $in->str('unit_id'), 'reserve_price' => $price, 'remarks' => $in->str('remarks'),
                'corner' => $in->input('is_corner') === '1']];
        } else {
            $plots = json_decode($in->str('plots'), true);
            if (!is_array($plots) || !$plots || !array_is_list($plots)) {
                $this->fail('Generate the property grid first.');
            }
            if (count($plots) > self::MAX_PLOTS) {
                $this->fail('At most ' . self::MAX_PLOTS . ' properties can be saved at once.');
            }
        }
        // edit: the error goes next to the form field; grid: it names the plot
        $err = function (string $message, string $field, string $plot) use ($prop): never {
            $this->fail($prop ? $message : 'Plot ' . $plot . ': ' . $message, $prop ? $field : null);
        };
        $rows = [];
        $seen = [];
        foreach ($plots as $p) {
            $p = is_array($p) ? $p : [];
            $plot = strtoupper(trim((string) ($p['plot_no'] ?? '')));
            $tag = $plot !== '' ? $plot : '(blank)';
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9\/_ -]{0,29}$/', $plot)) {
                $err('Plot no. may contain only letters, digits, space, / _ - (max 30).', 'plot_no', $tag);
            }
            if (isset($seen[$plot])) {
                $err('appears twice in the grid.', 'plot_no', $tag);
            }
            $seen[$plot] = true;
            if (Property::valueExists('plot_no', $plot, $id, ['scheme_id' => $schemeId, 'sector_id' => $sector['sector_id'] ?? null])) {
                $err(($prop ? 'Plot no. ' . $plot . ' ' : '') . 'already exists in ' . $where . '.', 'plot_no', $tag);
            }
            $typeId = (int) ($p['plot_type_id'] ?? 0);
            $pt = $typeId > 0 ? PlotType::find($typeId) : null;
            if (!$pt || (!$pt['is_active'] && $typeId !== ($prop['plot_type_id'] ?? null))) {
                $err('Please select an active plot type.', 'plot_type_id', $tag);
            }
            $area = str_replace(',', '', trim((string) ($p['area_value'] ?? '')));
            $unit = null;
            if ($area !== '') {
                if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $area) || (float) $area <= 0) {
                    $err('Area must be a number greater than zero (up to 2 decimals).', 'area_value', $tag);
                }
                $unit = PropertyService::validUnit((int) ($p['unit_id'] ?? 0), $prop['unit_id'] ?? null);
                if (!$unit) {
                    $err('Please select an active area unit.', 'unit_id', $tag);
                }
            }
            $price = str_replace(',', '', trim((string) ($p['reserve_price'] ?? '')));
            if ($price !== '' && (!preg_match('/^\d{1,12}(\.\d{1,2})?$/', $price) || (float) $price <= 0)) {
                $err('Reserve price must be greater than zero (up to 2 decimals).', 'reserve_price', $tag);
            }
            $remarks = trim((string) ($p['remarks'] ?? ''));
            if (mb_strlen($remarks) > 500 || preg_match('/[<>]/', $remarks)) {
                $err('Description may be at most 500 characters, without < or >.', 'remarks', $tag);
            }
            $a = $area !== '' ? round((float) $area, 2) : null;
            $rows[] = [
                'scheme_id' => $schemeId, 'sector_id' => $sector['sector_id'] ?? null, 'plot_no' => $plot, 'plot_type_id' => $pt['plot_type_id'],
                'area_value' => $a, 'unit_id' => $unit, 'area_sqm' => $a !== null ? PropertyService::toSqm($a, $unit) : null,
                'is_corner' => empty($p['corner']) ? 0 : 1, 'reserve_price' => $price !== '' ? round((float) $price, 2) : null,
                'remarks' => $remarks !== '' ? $remarks : null,
            ];
        }
        $sitePlan = null;
        if ($f = $in->file('site_plan')) {
            $sitePlan = Uploads::checkPdf($f, 'Site plan');
            if (is_string($sitePlan)) {
                $this->fail($sitePlan, 'site_plan');
            }
        }
// Role-user mapping is bound when sending properties for verification

        $by = (int) Auth::id();
        $stored = [];
        try {
            Database::transaction(static function () use ($id, $rows, $by, $sitePlan, &$stored): void {
                if ($id) {
                    Property::update($id, $rows[0] + ['updated_by' => $by]);
                    if ($sitePlan) {
                        $stored[] = Uploads::store($sitePlan, ['property_id' => $id, 'context' => PropertyService::SITE_PLAN, 'by' => $by])['path'];
                    }
                    return;
                }
                $plan = $sitePlan;
                foreach ($rows as $data) {
                    $pid = PropertyService::create($data, $by)['property']['property_id'];
                    if ($plan) {
                        // one site plan for the whole grid: every property keeps its own copy
                        $path = Uploads::store($plan, ['property_id' => $pid, 'context' => PropertyService::SITE_PLAN, 'by' => $by])['path'];
                        $stored[] = $path;
                        $plan = ['tmp' => $path, 'stored' => true] + $plan;
                    }
                    Audit::log('PROPERTY', $pid, 'CREATED', null, $data + ($plan ? ['site_plan' => $plan['name']] : []), $pid);
                }
            });
        } catch (Throwable $e) {
            Uploads::discard($stored);
            throw $e;
        }
        if ($prop) {
            Audit::log('PROPERTY', $id, 'UPDATED', array_intersect_key($prop, $rows[0]), $rows[0] + ($sitePlan ? ['site_plan' => $sitePlan['name']] : []), $id);
            $this->done('Property plot ' . $rows[0]['plot_no'] . ' updated.', $list);
        }
        $n = count($rows);
        $this->done($n . ' propert' . ($n === 1 ? 'y' : 'ies') . ' created in ' . $where . ' (status: Listed).', $list);
    }
}
