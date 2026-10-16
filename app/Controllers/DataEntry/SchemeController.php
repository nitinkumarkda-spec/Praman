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
use App\Models\Scheme;

/**
 * Scheme Master: scheme details + multiple sectors + role → user mapping, in one form.
 */
final class SchemeController extends Controller
{
    private const LABELS = [
        'scheme_name'    => 'Scheme name',
        'scheme_code'    => 'Scheme code',
        'scheme_type'    => 'Scheme type',
        'zone'           => 'Zone',
        'year_of_scheme' => 'Year of scheme',
    ];

    public function index(): void
    {
        $p = Paginator::fromRequest($this->request, Scheme::SORTABLE, 'id', 'desc', ['type', 'status']);
        (new Scheme())->paginateList($p);
        $this->view('dataentry/schemes/index', ['pageTitle' => 'Scheme Master', 'p' => $p]);
    }

    public function create(): void
    {
        $this->view('dataentry/schemes/form', [
            'pageTitle'   => 'Create Scheme',
            'scheme'      => null,
            'sectors'     => [],
            'mapping'     => [],
            'roleOptions' => (new Scheme())->roleUserOptions(),
        ]);
    }

    public function store(): void
    {
        $model = new Scheme();
        [$data, , $newSectors, $mapping] = $this->collect(null, $model);
        $id = $model->create($data, $newSectors, $mapping, (int) Auth::id());
        Audit::log('SCHEME', $id, 'CREATED', null, $data + ['sectors' => $newSectors, 'role_users' => $mapping]);
        $this->flash('success', "Scheme \"{$data['scheme_name']}\" created with " . count($newSectors) . ' sector(s).');
        $this->redirect('/data-entry/schemes');
    }

    public function edit(): void
    {
        $scheme = $this->findOr404();
        $model = new Scheme();
        $this->view('dataentry/schemes/form', [
            'pageTitle'   => 'Edit Scheme',
            'scheme'      => $scheme,
            'sectors'     => $model->sectors((int) $scheme['scheme_id']),
            'mapping'     => $model->mapping((int) $scheme['scheme_id']),
            'roleOptions' => $model->roleUserOptions(),
        ]);
    }

    public function update(): void
    {
        $scheme = $this->findOr404();
        $model = new Scheme();
        $id = (int) $scheme['scheme_id'];
        $oldMapping = $model->mapping($id);
        [$data, $existing, $newSectors, $mapping] = $this->collect($scheme, $model);
        $model->update($id, $data, $existing, $newSectors, $mapping, (int) Auth::id());
        Audit::log('SCHEME', $id, 'UPDATED',
            array_intersect_key($scheme, $data) + ['role_users' => $oldMapping],
            $data + ['sectors_changed' => $existing, 'sectors_added' => $newSectors, 'role_users' => $mapping]);
        $this->flash('success', "Scheme \"{$data['scheme_name']}\" updated. Changes apply to new properties only.");
        $this->redirect('/data-entry/schemes');
    }

    public function toggle(): void
    {
        $scheme = $this->findOr404();
        $active = (int) $scheme['is_active'] !== 1;
        (new Scheme())->setActive((int) $scheme['scheme_id'], $active, (int) Auth::id());
        Audit::log('SCHEME', (int) $scheme['scheme_id'], $active ? 'ACTIVATED' : 'DEACTIVATED');
        $this->flash('success', "Scheme \"{$scheme['scheme_name']}\" " . ($active ? 'activated.' : 'deactivated. No new property can be added in it.'));
        $this->back('/data-entry/schemes');
    }

    /** AJAX: active sectors of a scheme that is open for property entry. */
    public function sectors(): void
    {
        $id = $this->idParam();
        $model = new Scheme();
        $eligible = array_filter($model->forPropertyEntry(), static fn ($s) => (int) $s['scheme_id'] === $id);
        if ($eligible === []) {
            $this->json(['ok' => false, 'message' => 'This scheme is not available for property entry (inactive or no sectors).'], 404);
        }
        $sectors = array_map(
            static fn ($s) => ['id' => (int) $s['sector_id'], 'name' => $s['sector_name']],
            $model->sectors($id, true)
        );
        $this->json(['ok' => true, 'sectors' => $sectors]);
    }

    /**
     * Validate everything; on any error go back with messages.
     * @return array{0: array, 1: array<int, array{name: string, active: int}>, 2: string[], 3: array<int, int[]>}
     */
    private function collect(?array $scheme, Scheme $model): array
    {
        $isEdit = $scheme !== null;
        $back = $isEdit ? '/data-entry/schemes/' . $scheme['scheme_id'] . '/edit' : '/data-entry/schemes/create';
        $_POST['scheme_code'] = strtoupper(trim((string) ($isEdit ? $scheme['scheme_code'] : $this->request->input('scheme_code', ''))));

        $data = Validator::make($this->request->all(), [
            'scheme_name'    => 'required|min:3|max:150|text',
            'scheme_code'    => 'required|ucode',
            'scheme_type'    => 'required|in:' . implode(',', array_keys(Lookups::SCHEME_TYPES)),
            // Zone 1–4 dropdown; an existing legacy value is accepted only if unchanged on edit
            'zone'           => 'required|in:' . implode(',', array_unique(array_merge(
                array_keys(Lookups::ZONES),
                $isEdit ? [(string) $scheme['zone']] : []
            ))),
            'year_of_scheme' => 'required|year',
        ], self::LABELS);
        $v = $data;
        $values = [];
        foreach (array_keys(self::LABELS) as $k) {
            $values[$k] = trim((string) $this->request->input($k, ''));
        }

        $schemeId = $isEdit ? (int) $scheme['scheme_id'] : null;
        if (!$v->fails()) {
            if ($model->valueExists('schemes', 'scheme_name', $values['scheme_name'], 'scheme_id', $schemeId)) {
                $v->addError('scheme_name', 'A scheme with this name already exists.');
            }
            if (!$isEdit && $model->valueExists('schemes', 'scheme_code', $values['scheme_code'], 'scheme_id')) {
                $v->addError('scheme_code', 'This scheme code is already in use.');
            }
        }

        // ---------- Sectors ----------
        $existing = [];
        $seen = [];
        $activeCount = 0;
        if ($isEdit) {
            $posted = $this->request->inputArray('sectors_existing');
            foreach ($model->sectors((int) $scheme['scheme_id']) as $row) {
                $sid = (int) $row['sector_id'];
                $p = is_array($posted[$sid] ?? null) ? $posted[$sid] : [];
                $name = trim((string) ($p['name'] ?? $row['sector_name']));
                $active = ($p['active'] ?? '0') === '1' ? 1 : 0;
                if ($name === '' || mb_strlen($name) > 80 || preg_match('/[<>]/', $name) === 1) {
                    $v->addError('sectors', "Sector \"{$row['sector_name']}\": name is required (max 80 characters, no < >).");
                }
                $key = mb_strtolower($name);
                if (isset($seen[$key])) {
                    $v->addError('sectors', "Sector name \"{$name}\" is repeated. Each sector name must be unique in a scheme.");
                }
                $seen[$key] = true;
                $activeCount += $active;
                $existing[$sid] = ['name' => $name, 'active' => $active];
            }
        }
        $newSectors = [];
        foreach ($this->request->inputArray('sectors_new') as $raw) {
            $name = trim(is_string($raw) ? $raw : '');
            if ($name === '') {
                continue; // empty repeater rows are ignored
            }
            if (mb_strlen($name) > 80 || preg_match('/[<>]/', $name) === 1) {
                $v->addError('sectors', "Sector \"" . mb_substr($name, 0, 30) . "\": max 80 characters, no < >.");
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                $v->addError('sectors', "Sector name \"{$name}\" is repeated. Each sector name must be unique in a scheme.");
                continue;
            }
            $seen[$key] = true;
            $newSectors[] = $name;
            $activeCount++;
        }
        if ($activeCount < 1) {
            $v->addError('sectors', 'Add at least one (active) sector. A scheme cannot be saved without sectors.');
        }
        if (count($newSectors) + count($existing) > 200) {
            $v->addError('sectors', 'A scheme may have at most 200 sectors.');
        }
        if ($this->request->input('sectors_done') !== '1') {
            $v->addError('sectors_done', 'Please tick "Sector add karna complete / Done" after adding all sectors.');
        }

        // ---------- Role → user mapping ----------
        $options = $model->roleUserOptions();
        $postedMap = $this->request->inputArray('role_users');
        $mapping = [];
        foreach ($options as $roleId => $opt) {
            $allowed = array_map(static fn ($u) => (int) $u['user_id'], $opt['users']);
            $picked = is_array($postedMap[$roleId] ?? null) ? $postedMap[$roleId] : [];
            $ids = [];
            foreach ($picked as $uid) {
                if (is_string($uid) && ctype_digit($uid) && in_array((int) $uid, $allowed, true)) {
                    $ids[(int) $uid] = (int) $uid;
                }
            }
            if ($ids !== []) {
                $mapping[$roleId] = array_values($ids);
            } elseif ((int) $opt['role']['is_mandatory'] === 1) {
                $v->addError('role_users_' . $roleId, $allowed === []
                    ? "No active user exists in \"{$opt['role']['role_name']}\". Ask the Super Admin to add one."
                    : "Select at least one user for \"{$opt['role']['role_name']}\" (mandatory role).");
            }
        }

        if ($v->fails()) {
            $this->failValidation($v->errors(), $back);
        }
        return [$values, $existing, $newSectors, $mapping];
    }

    private function findOr404(): array
    {
        $scheme = (new Scheme())->find($this->idParam());
        if ($scheme === null) {
            throw new HttpException(404, 'Scheme not found.');
        }
        return $scheme;
    }
}
