<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Codes\PropertyStatus;
use App\Codes\YesNo;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lookups;
use App\Models\PlotType;
use App\Models\Property;
use App\Models\RoadType;
use App\Models\SubPlotType;
use App\Models\Scheme;
use App\Models\Sector;
use App\Services\Audit;
use App\Services\PropertyService;

/** Data Entry / Super Admin → Add Property (the whole generated grid at once) / edit one property (only while Listed). */
final class PropertyController extends Controller
{
    /** Most plots one Add Property grid may save at once. */
    private const MAX_PLOTS = 500;
    /** Where the Property screens live; a save returns to the one it came from. */
    private const LISTS = ['/data-entry/properties', '/admin/masters/properties'];
    /** Required master dropdowns of every plot: field => [model, label in messages] (facing is a fixed list: Lookups::FACINGS) */
    private const MASTERS = [
        'sub_plot_type_id' => [SubPlotType::class, 'sub plot type'],
        'road_type_id'     => [RoadType::class, 'road type'],
    ];

    /**
     * One function for both screens:
     *   add  — scheme_id, sector_id?, plots = JSON [{plot_no, plot_type_id, sub_plot_type_id, facing (code), road_type_id,
     *          area_value?, unit_id, latitude?, longitude?, remarks?, corner}]
     *   edit — id, sector_id? and that plot's fields (same names; is_corner instead of corner)
     * Sector, area, latitude / longitude and description are optional. Reserve price and site plan belong to the scheme (Scheme form).
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
            $plots = [['plot_no' => $in->str('plot_no'), 'plot_type_id' => $in->str('plot_type_id'), 'area_value' => $in->str('area_value'),
                'unit_id' => $in->str('unit_id'), 'remarks' => $in->str('remarks'), 'corner' => $in->input('is_corner') === '1']
                + array_map(static fn ($k) => $in->str($k), array_combine(array_keys(self::MASTERS), array_keys(self::MASTERS)))
                + ['facing' => $in->str('facing'), 'latitude' => $in->str('latitude'), 'longitude' => $in->str('longitude')]];
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
            // facing: required, one of the fixed list
            $facing = (string) ($p['facing'] ?? '');
            if (!isset(Lookups::FACINGS[$facing])) {
                $err('Please select the facing.', 'facing', $tag);
            }
            // sub plot type, road type: required, from their masters (an inactive one only when the property already has it)
            $picked = ['facing' => $facing];
            foreach (self::MASTERS as $key => [$model, $label]) {
                $mid = (int) ($p[$key] ?? 0);
                $row = $mid > 0 ? $model::find($mid) : null;
                if (!$row || (!$row['is_active'] && $mid !== ($prop[$key] ?? null))) {
                    $err('Please select an active ' . $label . '.', $key, $tag);
                }
                $picked[$key] = $mid;
            }
            // latitude / longitude: optional, but both together
            $lat = trim((string) ($p['latitude'] ?? ''));
            $lng = trim((string) ($p['longitude'] ?? ''));
            if (($lat === '') !== ($lng === '')) {
                $err('Enter both latitude and longitude, or leave both empty.', $lat === '' ? 'latitude' : 'longitude', $tag);
            }
            $latV = $lat !== '' ? PropertyService::coordinate($lat, 2, 90) : null;
            $lngV = $lng !== '' ? PropertyService::coordinate($lng, 3, 180) : null;
            if ($lat !== '' && $latV === null) {
                $err('Latitude must be between -90 and 90 with at most 8 decimals.', 'latitude', $tag);
            }
            if ($lng !== '' && $lngV === null) {
                $err('Longitude must be between -180 and 180 with at most 8 decimals.', 'longitude', $tag);
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
            $remarks = trim((string) ($p['remarks'] ?? ''));
            if (mb_strlen($remarks) > 500 || preg_match('/[<>]/', $remarks)) {
                $err('Description may be at most 500 characters, without < or >.', 'remarks', $tag);
            }
            $a = $area !== '' ? round((float) $area, 2) : null;
            $rows[] = [
                'scheme_id' => $schemeId, 'sector_id' => $sector['sector_id'] ?? null, 'plot_no' => $plot, 'plot_type_id' => $pt['plot_type_id'],
                'area_value' => $a, 'unit_id' => $unit, 'area_sqm' => $a !== null ? PropertyService::toSqm($a, $unit) : null,
                'is_corner' => empty($p['corner']) ? 0 : 1, 'latitude' => $latV, 'longitude' => $lngV, 'remarks' => $remarks !== '' ? $remarks : null,
            ] + $picked;
        }
        // Role-user mapping is bound when sending properties for verification
        $by = (int) Auth::id();
        Database::transaction(static function () use ($id, $rows, $by): void {
            if ($id) {
                Property::update($id, $rows[0] + ['updated_by' => $by]);
                return;
            }
            foreach ($rows as $data) {
                $pid = PropertyService::create($data, $by)['property']['property_id'];
                Audit::log('PROPERTY', $pid, 'CREATED', null, $data, $pid);
            }
        });
        if ($prop) {
            Audit::log('PROPERTY', $id, 'UPDATED', array_intersect_key($prop, $rows[0]), $rows[0], $id);
            $this->done('Property plot ' . $rows[0]['plot_no'] . ' updated.', $list);
        }
        $n = count($rows);
        $this->done($n . ' propert' . ($n === 1 ? 'y' : 'ies') . ' created in ' . $where . ' (status: Listed).', $list);
    }
}
