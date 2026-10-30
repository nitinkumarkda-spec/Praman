<?php
declare(strict_types=1);

namespace App\Controllers\DataEntry;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\PlotType;
use App\Models\Property;
use App\Models\Scheme;
use App\Models\Sector;
use App\Services\Audit;
use App\Services\PropertyService;

/** Data Entry → Add / edit property (edit only while Listed). */
final class PropertyController extends Controller
{
    public function save(): void
    {
        $id = $this->id() ?: null;
        $prop = $id ? Property::find($id) : null;
        if ($id && (!$prop || !$prop['is_active'])) {
            $this->fail('Property not found.');
        }
        if ($this->request->str('confirm_location') !== 'YES') {
            $this->fail('Please confirm the scheme and sector (answer "Yes").', 'confirm_location');
        }
        $_POST['plot_no'] = strtoupper($this->request->str('plot_no'));
        $d = $this->validate([
            'sector_id'     => ['Sector', 'required|integer'],
            'plot_no'       => ['Plot no.', 'required|plotno'],
            'property_type' => ['Plot type', 'required|max:30'],
            'area_value'    => ['Area', 'required|decimal:2|positive'],
            'area_unit'     => ['Area unit', 'required|in:SQM,SQFT'],
            'remarks'       => ['Remarks', 'max:500|text'],
        ]);
        $schemeId = $id ? (int) $prop['scheme_id'] : $this->id('scheme_id');
        $sch = Scheme::find($schemeId);
        if (!$sch || (!$id && !$sch['is_active'])) {
            $this->fail('Please select an active scheme.', 'scheme_id');
        }
        $sector = Sector::find((int) $d['sector_id']);
        if (!$sector || (int) $sector['scheme_id'] !== $schemeId || !$sector['is_active']) {
            $this->fail('Please select an active sector of the selected scheme.', 'sector_id');
        }
        $plot = $d['plot_no'];
        if (Property::valueExists('plot_no', $plot, $id, ['sector_id' => $sector['sector_id']])) {
            $this->fail('Plot no. ' . $plot . ' already exists in this sector.', 'plot_no');
        }
        $pt = PlotType::first(['code' => $d['property_type']]);
        if (!$pt || (!$pt['is_active'] && (!$prop || $prop['property_type'] !== $pt['code']))) {
            $this->fail('Please select an active plot type.', 'property_type');
        }
        $area = round((float) $d['area_value'], 2);
        $data = [
            'scheme_id' => $schemeId, 'sector_id' => $sector['sector_id'],
            'plot_no' => $plot, 'property_type' => $pt['code'], 'area_value' => $area, 'area_unit' => $d['area_unit'],
            'area_sqm' => PropertyService::toSqm($area, $d['area_unit']), 'remarks' => $d['remarks'] ?: null,
        ];

        if (!$id) {
            $missing = PropertyService::missingMandatoryRoles($schemeId);
            if ($missing) {
                $this->fail('Cannot create property: no active user is mapped in this scheme for mandatory role(s): ' . implode(', ', $missing) . '. Update the scheme\'s role mapping first.', 'scheme_id');
            }
            $res = PropertyService::create($data, Auth::id());
            Audit::log('PROPERTY', $res['property']['property_id'], 'CREATED', null, $data, $res['property']['property_id']);
            $go = $this->request->input('save_and_new') === '1' ? '/data-entry/properties/create' : '/data-entry/properties';
            $this->done('Property plot ' . $plot . ' created (status: Listed). ' . $res['snap'] . ' role user(s) assigned from the scheme mapping.', $go);
        }
        if ($prop['current_status'] !== 'LISTED') {
            $this->flash('error', 'This property was sent for verification meanwhile and can no longer be edited.');
            $this->done(null, '/data-entry/properties');
        }
        Property::update($id, $data + ['updated_by' => Auth::id()]);
        Audit::log('PROPERTY', $id, 'UPDATED', ['plot_no' => $prop['plot_no'], 'area_value' => $prop['area_value'], 'sector_id' => $prop['sector_id']], $data, $id);
        $this->done('Property plot ' . $plot . ' updated.', '/data-entry/properties');
    }
}
