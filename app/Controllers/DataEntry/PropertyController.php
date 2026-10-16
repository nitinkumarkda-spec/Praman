<?php
declare(strict_types=1);

namespace App\Controllers\DataEntry;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lookups;
use App\Core\Paginator;
use App\Core\Validator;
use App\Models\Property;
use App\Models\Scheme;

/**
 * Property Register: Scheme → Sector (AJAX) → confirmation → plot details.
 */
final class PropertyController extends Controller
{
    private const LABELS = [
        'scheme_id'     => 'Scheme',
        'sector_id'     => 'Sector',
        'plot_no'       => 'Plot no.',
        'property_type' => 'Plot type',
        'area_value'    => 'Area',
        'area_unit'     => 'Area unit',
        'remarks'       => 'Remarks',
        'confirm_location' => 'Confirmation',
    ];

    public function index(): void
    {
        $p = Paginator::fromRequest($this->request, Property::SORTABLE, 'id', 'desc', ['scheme_id', 'sector_id', 'status', 'type']);
        (new Property())->paginateList($p);
        $scheme = new Scheme();
        $schemeId = $p->filter('scheme_id');
        $this->view('dataentry/properties/index', [
            'pageTitle' => 'Property Register',
            'p'         => $p,
            'schemes'   => $scheme->options(),
            'sectors'   => ($schemeId !== null && ctype_digit($schemeId)) ? $scheme->sectorOptions((int) $schemeId) : [],
        ]);
    }

    public function create(): void
    {
        $this->view('dataentry/properties/form', [
            'pageTitle' => 'Add Property',
            'property'  => null,
            'schemes'   => (new Scheme())->forPropertyEntry(),
            'sectors'   => [],
        ]);
    }

    public function store(): void
    {
        $model = new Property();
        $data = $this->collect(null, $model);
        [$id, $snap] = $model->create($data, (int) Auth::id());
        Audit::log('PROPERTY', $id, 'CREATED', null, $data + ['role_user_snapshot' => $snap], $id);
        $this->flash('success', "Property plot {$data['plot_no']} created (status: Listed). {$snap} role user(s) assigned from the scheme mapping.");
        $this->redirect($this->request->input('save_and_new') === '1' ? '/data-entry/properties/create' : '/data-entry/properties');
    }

    public function edit(): void
    {
        $property = $this->findOr404();
        if ($property['current_status'] !== 'LISTED') {
            $this->flash('warning', 'Only properties in "Listed" status can be edited. This one is ' . label($property['current_status']) . '.');
            $this->redirect('/data-entry/properties');
        }
        $this->view('dataentry/properties/form', [
            'pageTitle' => 'Edit Property',
            'property'  => $property,
            'schemes'   => [],
            'sectors'   => (new Scheme())->sectors((int) $property['scheme_id'], true),
            'roleUsers' => (new Property())->roleUsers((int) $property['property_id']),
        ]);
    }

    public function update(): void
    {
        $property = $this->findOr404();
        if ($property['current_status'] !== 'LISTED') {
            $this->flash('error', 'This property is no longer "Listed" and cannot be edited.');
            $this->redirect('/data-entry/properties');
        }
        $model = new Property();
        $data = $this->collect($property, $model);
        if (!$model->update((int) $property['property_id'], $data, (int) Auth::id())) {
            $this->flash('error', 'This property was sent for verification meanwhile and can no longer be edited.');
            $this->redirect('/data-entry/properties');
        }
        Audit::log('PROPERTY', (int) $property['property_id'], 'UPDATED', array_intersect_key($property, $data), $data, (int) $property['property_id']);
        $this->flash('success', "Property plot {$data['plot_no']} updated.");
        $this->redirect('/data-entry/properties');
    }

    private function collect(?array $property, Property $model): array
    {
        $isEdit = $property !== null;
        $back = $isEdit ? '/data-entry/properties/' . $property['property_id'] . '/edit' : '/data-entry/properties/create';
        if ($isEdit) {
            $_POST['scheme_id'] = (string) $property['scheme_id']; // scheme is fixed after creation
        }

        $v = Validator::make($this->request->all(), [
            'scheme_id'        => 'required|integer',
            'sector_id'        => 'required|integer',
            'plot_no'          => 'required|plotno',
            'property_type'    => 'required|in:' . implode(',', array_keys(Lookups::PROPERTY_TYPES)),
            'area_value'       => 'required|decimal:2|positive|between:0.01,99999999',
            'area_unit'        => 'required|in:' . implode(',', array_keys(Lookups::AREA_UNITS)),
            'remarks'          => 'nullable|max:500|text',
            'confirm_location' => 'required|in:YES,NO',
        ], self::LABELS);

        $d = [];
        foreach (['scheme_id', 'sector_id', 'plot_no', 'property_type', 'area_value', 'area_unit', 'remarks'] as $k) {
            $d[$k] = trim((string) $this->request->input($k, ''));
        }
        $d['plot_no'] = strtoupper($d['plot_no']);

        if (!$v->fails()) {
            $schemeId = (int) $d['scheme_id'];
            $sectorId = (int) $d['sector_id'];
            if (!$isEdit) {
                $eligible = array_column((new Scheme())->forPropertyEntry(), 'scheme_id');
                if (!in_array($schemeId, array_map('intval', $eligible), true)) {
                    $v->addError('scheme_id', 'This scheme is not available (inactive or has no sectors).');
                }
                $missing = $model->missingMandatoryRoles($schemeId);
                if ($missing !== []) {
                    $v->addError('scheme_id', 'Scheme role mapping incomplete — no active user mapped for: ' . implode(', ', $missing) . '. Edit the scheme first.');
                }
            }
            if (!$v->fails() && !$model->sectorValid($schemeId, $sectorId)) {
                $v->addError('sector_id', 'Please select an active sector of the selected scheme.');
            }
            if ($this->request->input('confirm_location') !== 'YES') {
                $v->addError('confirm_location', 'You answered "No" — the property was not saved. Choose the correct scheme / sector and answer "Yes".');
            }
            if (!$v->fails() && $model->duplicateExists($schemeId, $sectorId, $d['plot_no'], $d['property_type'], $isEdit ? (int) $property['property_id'] : null)) {
                $v->addError('plot_no', 'Plot ' . $d['plot_no'] . ' (' . (Lookups::PROPERTY_TYPES[$d['property_type']] ?? '') . ') already exists in this scheme and sector.');
            }
        }
        if ($v->fails()) {
            $this->failValidation($v->errors(), $back);
        }

        $d['area_value'] = number_format((float) $d['area_value'], 2, '.', '');
        $d['area_sqm'] = number_format(Lookups::toSqm((float) $d['area_value'], $d['area_unit']), 4, '.', '');
        return $d;
    }

    private function findOr404(): array
    {
        $property = (new Property())->find($this->idParam());
        if ($property === null) {
            throw new HttpException(404, 'Property not found.');
        }
        return $property;
    }
}
