<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lookups;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Area;
use App\Models\DeveloperType;
use App\Models\PlotType;
use App\Models\Property;
use App\Models\Role;
use App\Models\Scheme;
use App\Models\RoadType;
use App\Models\SchemeType;
use App\Models\SubPlotType;
use App\Models\SchemeUser;
use App\Models\Sector;
use App\Models\SiteInspection;
use App\Models\Status;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit;
use App\Services\PropertyService;
use App\Services\Uploads;

/**
 * Every master (Area, Plot Type, Unit, Status, Sector, Scheme) through ONE action:
 *   POST /api/master   key = areas | plot-types | developer-types | scheme-types | units | statuses | sectors | schemes
 *                      do  = save | toggle | delete
 * What differs between masters is data in masters() below, not separate functions.
 * Same rules as the screens in public/assets/js/pages/masters.js and dataentry.js (Scheme).
 */
final class MasterController extends Controller
{
    /** Where the Scheme screens live (Data Entry and Super Admin); a save returns to the one it came from. */
    private const SCHEME_LISTS = ['/data-entry/schemes', '/admin/masters/schemes'];

    /**
     * key => [
     *   model, entity (audit), one (label), name(row),
     *   fields:  name => [label, rules, unique, case(false|true = ALL CAPITALS|'words' = First Letter Of Every Word), lockOnEdit,
     *                     type(false|true = int|'float'|'date' = dd/mm/yyyy → Y-m-d)]
     *   create:  fields when adding (default: fields), split: field holding "a, b, c" to add several rows
     *   scope:   column the unique check is limited to (sector name is unique inside its scheme)
     *   system:  rows with is_system = 1 are always active and cannot be deleted
     *   usage(row): records using the row (delete only at 0)
     *   block(row): reason the row may not be switched off / removed, or null
     *   check(data, old, usage): [message, field] when a save is not allowed, or null
     *   perm:    permission prefix (default "master" → master.create / .edit / .toggle / .delete)
     *   actions: what may be done (default save, toggle, delete)
     *   keepActive: the form has no Active box — new rows start active, edits leave it as it is
     *   prepare(request, id): extra input saved with the row, or ['fail' => [message, field]]
     *   saved(id, extra, by): writes that extra input in the same transaction as the row
     *   audit(extra): extra values for the audit trail;  note(extra, isNew): text added to the message
     *   list(request): screen to return to (default /admin/masters/<key>)
     *   ordered: the list is shown by sort_order, so a new row gets the next sort_order (goes to the end)
     * ]
     */
    private function masters(): array
    {
        $text = static fn ($label, $max, $req = false, $unique = false) => [$label, ($req ? 'required|min:2|' : '') . 'max:' . $max . '|text', $unique, false, false, false];

        return [
            'plot-types' => [
                'model' => PlotType::class, 'entity' => 'PLOT_TYPE', 'one' => 'Plot type', 'name' => static fn ($r) => $r['name'],
                'fields' => ['name' => $text('Name', 60, true, true),
                    'description' => $text('Description', 150)],
                'usage' => static fn ($r) => Property::count(['plot_type_id' => $r['plot_type_id']]) + Scheme::count(['plot_type_id' => $r['plot_type_id']]),
                'block' => static fn ($r) => PlotType::count(['is_active' => 1]) <= 1 ? 'At least one plot type must remain active.' : null,
            ],
            'developer-types' => [
                'model' => DeveloperType::class, 'entity' => 'DEVELOPER_TYPE', 'one' => 'Developer type', 'name' => static fn ($r) => $r['name'],
                'perm' => 'developer_type', // own module in the Permissions screen (developer_type.view / create / edit / toggle / delete)
                'fields' => ['name' => $text('Name', 60, true, true),
                    'default_developer' => $text('Default developer', 150),
                    'description' => $text('Description', 150)],
                'usage' => static fn ($r) => Scheme::count(['developer_type_id' => $r['developer_type_id']]),
                'block' => static fn ($r) => DeveloperType::count(['is_active' => 1]) <= 1 ? 'At least one developer type must remain active.' : null,
            ],
            'scheme-types' => [
                'model' => SchemeType::class, 'entity' => 'SCHEME_TYPE', 'one' => 'Scheme type', 'name' => static fn ($r) => $r['name'],
                'perm' => 'scheme_type', // own module in the Permissions screen (scheme_type.view / create / edit / toggle / delete)
                'fields' => ['name' => $text('Name', 60, true, true),
                    'description' => $text('Description', 150)],
                'usage' => static fn ($r) => Scheme::count(['scheme_type_id' => $r['scheme_type_id']]),
                'block' => static fn ($r) => SchemeType::count(['is_active' => 1]) <= 1 ? 'At least one scheme type must remain active.' : null,
            ],
            'sub-plot-types' => [
                'model' => SubPlotType::class, 'entity' => 'SUB_PLOT_TYPE', 'one' => 'Sub plot type', 'name' => static fn ($r) => $r['name'],
                'perm' => 'sub_plot_type', // own module in the Permissions screen (sub_plot_type.view / create / edit / toggle / delete)
                // name saved with the first letter of every word in capitals ("water works" → "Water Works"; EWS, MIG-A stay as typed)
                'fields' => ['name' => ['Name', 'required|min:2|max:60|text', true, 'words', false, false],
                    'description' => $text('Description', 150)],
                'usage' => static fn ($r) => Property::count(['sub_plot_type_id' => $r['sub_plot_type_id']]),
                'block' => static fn ($r) => SubPlotType::count(['is_active' => 1]) <= 1 ? 'At least one sub plot type must remain active.' : null,
            ],
            'road-types' => [
                'model' => RoadType::class, 'entity' => 'ROAD_TYPE', 'one' => 'Road type', 'name' => static fn ($r) => $r['name'],
                'perm' => 'road_type', // own module in the Permissions screen (road_type.view / create / edit / toggle / delete)
                'ordered' => true,     // listed by sort_order (9, 18 … Meter); a new road type goes to the end
                'fields' => ['name' => ['Name', 'required|min:2|max:60|text', true, 'words', false, false], // "9 meter" → "9 Meter"
                    'description' => $text('Description', 150)],
                'usage' => static fn ($r) => Property::count(['road_type_id' => $r['road_type_id']]),
                'block' => static fn ($r) => RoadType::count(['is_active' => 1]) <= 1 ? 'At least one road type must remain active.' : null,
            ],
            'units' => [
                'model' => Unit::class, 'entity' => 'UNIT', 'one' => 'Unit', 'name' => static fn ($r) => $r['name'], 'system' => true,
                'fields' => ['name' => $text('Unit name', 40, true, true),
                    'symbol' => ['Symbol', 'required|max:15|text', true, false, false, false],
                    'to_sqm' => ['1 unit = sq m', 'required|decimal:8|positive', false, false, false, 'float'],
                    'description' => $text('Description', 150)],
                'usage' => static fn ($r) => Property::count(['unit_id' => $r['unit_id']]) + SiteInspection::count(['unit_id' => $r['unit_id']]),
                'block' => static fn ($r) => $r['is_system'] ? 'The base unit (sq m) cannot be deactivated.' : null,
                // stored areas were converted with the old factor
                'check' => static fn ($d, $old, $used) => !$old || abs((float) $d['to_sqm'] - (float) $old['to_sqm']) <= 1e-9 ? null
                    : ($old['is_system'] ? ['The base unit (sq m) is always 1 sq m.', 'to_sqm']
                        : ($used > 0 ? ['This unit is used by properties, so its conversion factor cannot change. Add a new unit instead.', 'to_sqm'] : null)),
            ],
            'statuses' => [
                'model' => Status::class, 'entity' => 'STATUS', 'one' => 'Property status', 'name' => static fn ($r) => $r['name'], 'system' => true,
                'fields' => ['name' => $text('Display name', 40, true, true),
                    'color' => ['Badge colour', 'required|in:' . Lookups::keys(Lookups::STATUS_COLORS), false, false, false, false],
                    'icon' => ['Icon', 'required|in:' . Lookups::keys(Lookups::STATUS_ICONS), false, false, false, false],
                    'description' => $text('Meaning', 150)],
                'usage' => static fn ($r) => Property::count(['status_id' => $r['status_id']]),
                'block' => static fn ($r) => $r['is_system'] ? 'System statuses cannot be deactivated.' : null,
            ],
            'sectors' => [
                'model' => Sector::class, 'entity' => 'SECTOR', 'one' => 'Sector', 'name' => static fn ($r) => $r['sector_name'], 'scope' => 'scheme_id',
                'fields' => ['sector_name' => ['Sector name', 'required|max:80|text', true, false, false, false]],
                'create' => ['scheme_id' => ['Scheme', 'required|integer', false, false, false, true],
                    'sector_name' => ['Sector name(s)', 'required|max:400|text', true, false, false, false]],
                'split' => 'sector_name',
                'usage' => static fn ($r) => Property::count(['sector_id' => $r['sector_id']]),
                'block' => static fn ($r) => null, // sectors are optional: a scheme may have none
            ],
            // Scheme: details + its sectors + the role users who verify its properties (Data Entry and Super Admin)
            'schemes' => [
                'model' => Scheme::class, 'entity' => 'SCHEME', 'one' => 'Scheme', 'name' => static fn ($r) => $r['scheme_name'],
                'perm' => 'scheme', 'actions' => ['save', 'toggle'], 'keepActive' => true,
                // same order as the Scheme form, so the first error found is the first one on screen
                'fields' => [
                    'developer_type_id'    => ['Developer type', 'required|integer', false, false, false, true],
                    'developer_name'       => ['Developer', 'required|min:2|max:150|text', false, false, false, false],
                    'scheme_type_id'       => ['Scheme type', 'required|integer', false, false, false, true],
                    'plot_type_id'         => ['Plot type', 'required|integer', false, false, false, true],
                    'scheme_name'          => ['Scheme name', 'required|min:3|max:150|text', true, false, false, false],
                    'scheme_landmark'      => ['Scheme landmark', 'max:255|text', false, false, false, false],        // optional
                    'approve_date'         => ['Approve date', 'dmy', false, false, false, 'date'],                    // optional
                    // optional; saved in capitals, CAPITAL letters / digits / "/ _ -" (2–30), and no two schemes may share one
                    'rera_registration_no' => ['Rera registration number', 'ucode', true, true, false, false],
                    'reserve_price'        => ['Reserve price', 'decimal:2|positive', false, false, false, 'float'],    // optional, in rupees
                    // site plan PDF (optional): checked in prepare, saved as the scheme's attachment in saved
                ],
                'usage' => static fn ($r) => Sector::count(['scheme_id' => $r['scheme_id']]),
                'check' => static function (array $d, ?array $old): ?array {
                    // types come from their masters: an active row, or the inactive one the scheme already has
                    if (!($t = self::pick(DeveloperType::class, 'developer_type_id', $d, $old))) {
                        return ['Please select an active developer type.', 'developer_type_id'];
                    }
                    // a developer type with a default developer always uses that name
                    $default = trim((string) ($t['default_developer'] ?? ''));
                    if ($default !== '' && $d['developer_name'] !== $default) {
                        return ['Developer for "' . $t['name'] . '" must be "' . $default . '".', 'developer_name'];
                    }
                    if (!self::pick(SchemeType::class, 'scheme_type_id', $d, $old)) {
                        return ['Please select an active scheme type.', 'scheme_type_id'];
                    }
                    if (!self::pick(PlotType::class, 'plot_type_id', $d, $old)) {
                        return ['Please select an active plot type.', 'plot_type_id'];
                    }
                    $approved = $d['approve_date']; // optional: null when left empty
                    if ($approved !== null && $approved > today()) {
                        return ['Approve date cannot be in the future.', 'approve_date'];
                    }
                    return $approved !== null && $approved < '1950-01-01' ? ['Approve date must be after 01/01/1950.', 'approve_date'] : null;
                },
                'block' => static fn ($r) => null,
                'prepare' => static function (Request $in, ?int $id): array {
                    // site plan (optional): one PDF up to 5 MB; a new upload becomes the current one, older ones stay as history
                    $plan = null;
                    if ($f = $in->file('site_plan')) {
                        $plan = Uploads::checkPdf($f, 'Site plan');
                        if (is_string($plan)) {
                            return ['fail' => [$plan, 'site_plan']];
                        }
                    }
                    // sectors: existing (rename / deactivate) + new rows
                    $existing = [];
                    foreach ($id ? Sector::where(['scheme_id' => $id], 'sort_order') : [] as $s) {
                        $existing[] = ['s' => $s, 'name' => $in->str('sec_name_' . $s['sector_id']), 'active' => $in->input('sec_active_' . $s['sector_id']) === '1' ? 1 : 0];
                    }
                    $fresh = array_values(array_filter($in->list('sectors_new'), 'strlen'));
                    $seen = [];
                    foreach (array_merge(array_column($existing, 'name'), $fresh) as $n) {
                        if ($n === '' || mb_strlen($n) > 80) {
                            return ['fail' => ['Sector name is required (max 80 characters).', null]];
                        }
                        if (preg_match('/[<>]/', $n)) {
                            return ['fail' => ['Sector "' . mb_substr($n, 0, 30) . '": < and > are not allowed.', null]];
                        }
                        if (isset($seen[mb_strtolower($n)])) {
                            return ['fail' => ['Sector name "' . $n . '" is repeated. Each sector name must be unique in a scheme.', null]];
                        }
                        $seen[mb_strtolower($n)] = true;
                    }
                    return ['existing' => $existing, 'fresh' => $fresh, 'plan' => $plan];
                },
                'saved' => static function (int $sid, array $x, ?int $by): void {
                    foreach ($x['existing'] as $e) {
                        Sector::update($e['s']['sector_id'], ['sector_name' => $e['name'], 'is_active' => $e['active'], 'updated_by' => $by]);
                    }
                    $order = Sector::max('sort_order', ['scheme_id' => $sid]);
                    foreach ($x['fresh'] as $n) {
                        Sector::insert(['scheme_id' => $sid, 'sector_name' => $n, 'sort_order' => ++$order, 'is_active' => 1, 'created_by' => $by]);
                    }
                    // the file is moved last, so nothing after it in this transaction can fail and leave it orphaned
                    if ($x['plan']) {
                        Uploads::store($x['plan'], ['scheme_id' => $sid, 'context' => PropertyService::SITE_PLAN, 'by' => $by]);
                    }
                },
                'audit' => static fn ($x) => ['sectors' => $x['fresh']] + ($x['plan'] ? ['site_plan' => $x['plan']['name']] : []),
                'note' => static fn ($x, $isNew) => $isNew ? ' — ' . count($x['fresh']) . ' sector(s).' : ' Changes apply to new properties only.',
                'list' => static fn (Request $in) => in_array($in->str('back'), self::SCHEME_LISTS, true) ? $in->str('back')
                    : (Auth::can('master.view') ? '/admin/masters/schemes' : '/data-entry/schemes'),
            ],
        ];
    }

    /**
     * The master row chosen in $d[$pk] when it may be used: active, or the inactive one $old already has
     * (so an old record still saves). Null when it is missing or switched off.
     */
    private static function pick(string $model, string $pk, array $d, ?array $old): ?array
    {
        $row = $model::find((int) $d[$pk]);
        return $row && ($row['is_active'] || (int) ($old[$pk] ?? 0) === (int) $row[$pk]) ? $row : null;
    }

    /**
     * First letter of every word in capitals; the other letters stay as typed, so short forms keep their case
     * ("retail shop (kiosk)" → "Retail Shop (Kiosk)", "MIG-a" → "MIG-A", "EWS" stays "EWS"). Same rule as data-capitalize in forms.js.
     */
    private static function capitalizeWords(string $v): string
    {
        return (string) preg_replace_callback('/(^|[\s(\/-])(\p{Ll})/u', static fn ($m) => $m[1] . mb_strtoupper($m[2]), $v);
    }

    /** The one action for every master: save (add / edit), toggle (active ↔ inactive) and delete. */
    public function handle(): void
    {
        $key = $this->request->str('key');
        $do = $this->request->str('do');
        $m = $this->masters()[$key] ?? null;
        if (!$m || !in_array($do, $m['actions'] ?? ['save', 'toggle', 'delete'], true)) {
            $this->fail('Unknown master or action.');
        }
        ['model' => $model, 'entity' => $entity, 'one' => $one, 'name' => $name] = $m;
        $id = $this->id() ?: null;
        $this->authorize(($m['perm'] ?? 'master') . '.' . ($do === 'save' ? ($id ? 'edit' : 'create') : $do));
        $row = $id ? $model::find($id) : null;
        if ($id && !$row) {
            $this->fail($one . ' not found.');
        }
        if (!$id && $do !== 'save') {
            $this->fail('Record not found.');
        }
        $list = isset($m['list']) ? $m['list']($this->request) : '/admin/masters/' . $key;
        $system = !empty($m['system']) && $row && $row['is_system'];
        $block = $row ? $m['block']($row) : null;

        // ---------- switch on / off ----------
        if ($do === 'toggle') {
            $active = $row['is_active'] ? 0 : 1;
            if (!$active && $block) {
                $this->fail($block);
            }
            $model::update($id, ['is_active' => $active, 'updated_by' => Auth::id()]);
            Audit::log($entity, $id, $active ? 'ACTIVATED' : 'DEACTIVATED', ['is_active' => 1 - $active], ['is_active' => $active]);
            $this->done($one . ' "' . $name($row) . '" ' . ($active ? 'activated.' : 'deactivated. It is hidden from new entries.'));
        }

        // ---------- delete (only when nothing uses it) ----------
        if ($do === 'delete') {
            if ($system || $m['usage']($row) > 0) {
                $this->fail('This ' . strtolower($one) . ' is in use and cannot be deleted. Deactivate it instead.');
            }
            if ($row['is_active'] && $block) {
                $this->fail($block);
            }
            $model::delete($id);
            Audit::log($entity, $id, 'DELETED', array_intersect_key($row, $m['fields']), null);
            $this->done($one . ' "' . $name($row) . '" deleted.');
        }

        // ---------- save: validate the fields of this master ----------
        $fields = !$id && isset($m['create']) ? $m['create'] : $m['fields'];
        $rules = [];
        foreach ($fields as $f => [$label, $rule, , $upper, $lockOnEdit]) {
            if ($id && $lockOnEdit) {
                continue;
            }
            if ($upper) {
                $_POST[$f] = $upper === 'words' ? self::capitalizeWords($this->request->str($f)) : strtoupper($this->request->str($f));
            }
            $rules[$f] = [$label, $rule];
        }
        $d = $this->validate($rules);
        $data = [];
        foreach ($rules as $f => $_) {
            $number = $fields[$f][5];
            $data[$f] = $d[$f] === '' ? null : match (true) {
                $number === 'float' => (float) $d[$f],
                $number === 'date'  => Validator::dmy($d[$f]),
                (bool) $number      => (int) $d[$f],
                default             => $d[$f],
            };
        }
        $scope = [];
        if (isset($m['scope'])) {
            $parent = $row ? $row[$m['scope']] : (int) $data[$m['scope']];
            if (!$row && !Scheme::find($parent)) {
                $this->fail('Please select a scheme.', $m['scope']);
            }
            $scope = [$m['scope'] => $parent];
        }
        // one value, or several "a, b, c" when adding to a master that allows it
        $splitField = !$id ? ($m['split'] ?? null) : null;
        $values = $splitField ? array_values(array_filter(array_map('trim', explode(',', (string) $data[$splitField])), 'strlen')) : [null];
        $seen = [];
        foreach ($values as $v) {
            $candidate = $splitField ? [$splitField => $v] + $data : $data;
            foreach ($fields as $f => [$label, $rule, $unique]) {
                if (!$unique || !isset($candidate[$f]) || $candidate[$f] === '' || ($id && $fields[$f][4])) {
                    continue;
                }
                $max = $splitField === $f ? (int) (preg_match('/max:(\d+)/', $m['fields'][$f][1], $mm) ? $mm[1] : 0) : 0;
                if ($max && mb_strlen((string) $candidate[$f]) > $max) {
                    $this->fail($one . ' "' . mb_substr((string) $candidate[$f], 0, 30) . '…" is longer than ' . $max . ' characters.', $f);
                }
                $lower = mb_strtolower((string) $candidate[$f]);
                if (isset($seen[$f][$lower]) || $model::valueExists($f, (string) $candidate[$f], $id, $scope)) {
                    $this->fail($label . ' "' . $candidate[$f] . '" already exists' . ($scope ? ' in this scheme' : '') . '.', $f);
                }
                $seen[$f][$lower] = true;
            }
        }
        if (isset($m['check']) && ($bad = $m['check']($data, $row, $row ? $m['usage']($row) : 0))) {
            $this->fail($bad[0], $bad[1]);
        }
        // input saved together with the row (Scheme: its sectors and role-user mapping)
        $extra = isset($m['prepare']) ? $m['prepare']($this->request, $id) : [];
        if (isset($extra['fail'])) {
            $this->fail($extra['fail'][0], $extra['fail'][1]);
        }
        if (!empty($m['keepActive'])) {
            $data += $id ? [] : ['is_active' => 1];
        } else {
            $data['is_active'] = $system ? 1 : ($id || !$splitField ? ($this->request->input('is_active') === '1' ? 1 : 0) : 1);
        }
        if ($id && $row['is_active'] && isset($data['is_active']) && !$data['is_active'] && $block) {
            $this->fail($block);
        }
        $by = Auth::id();
        $auditExtra = isset($m['audit']) ? $m['audit']($extra) : [];
        $note = isset($m['note']) ? $m['note']($extra, !$id) : '';

        if ($id) {
            Database::transaction(static function () use ($model, $id, $data, $m, $extra, $by): void {
                $model::update($id, $data + ['updated_by' => $by]);
                if (isset($m['saved'])) {
                    $m['saved']($id, $extra, $by);
                }
            });
            Audit::log($entity, $id, 'UPDATED', array_intersect_key($row, $data), $data + $auditExtra);
            $this->done($one . ' "' . $name($model::find($id)) . '" updated successfully.' . $note, $list);
        }
        $created = Database::transaction(static function () use ($model, $entity, $data, $values, $splitField, $scope, $m, $extra, $by, $auditExtra): array {
            $out = [];
            $next = $scope ? $model::max('sort_order', $scope) : null;
            foreach ($values as $v) {
                $new = $splitField ? [$splitField => $v, 'sort_order' => ++$next] + $data : $data;
                $new += isset($m['system']) ? ['is_system' => 0] : [];
                $new += !empty($m['ordered']) ? ['sort_order' => $model::max('sort_order') + 1] : [];
                $newId = $model::insert($new + ['created_by' => $by]);
                if (isset($m['saved'])) {
                    $m['saved']($newId, $extra, $by);
                }
                Audit::log($entity, $newId, 'CREATED', null, $new + $auditExtra);
                $out[] = $model::find($newId);
            }
            return $out;
        });
        $this->done(count($created) === 1 ? $one . ' "' . $name($created[0]) . '" created successfully.' . $note
            : count($created) . ' ' . strtolower($one) . 's added.', $list);
    }

    /** Assign role users to a scheme (modal popup from Schemes list). */
    public function schemeUsers(): void
    {
        $this->authorize('scheme.edit');

        $schemeId = $this->id('scheme_id');
        $scheme = Scheme::find($schemeId);
        if (!$scheme) {
            $this->fail('Scheme not found.', 'scheme_id');
        }

        // Only active roles that have an assigned status
        $roles = array_values(array_filter(
            Role::where(['is_active' => 1], 'sort_order'),
            static fn ($r) => !empty($r['status_id'])
        ));
        $roleIdsWithStatus = array_map('intval', array_column($roles, 'role_id'));
        $map = [];

        foreach ($roles as $r) {
            $roleId = (int) $r['role_id'];
            $validUsers = array_column(User::where(['is_active' => 1, 'role_id' => $roleId]), 'user_id');
            $validUsers = array_map('intval', $validUsers);

            $inputList = $this->request->list('role_users_' . $roleId);
            $selectedIds = array_values(array_unique(array_filter(array_map('intval', $inputList))));

            // Validate that all selected user IDs are valid active users of this role
            if (array_diff($selectedIds, $validUsers)) {
                $this->fail('Only active users of "' . $r['role_name'] . '" can be assigned to it.');
            }

            if (!empty($selectedIds)) {
                $map[$roleId] = $selectedIds;
            }
        }

        $by = Auth::id();
        Database::transaction(static function () use ($schemeId, $map, $roleIdsWithStatus, $by): void {
            // Deactivate existing assignments for eligible roles that are no longer selected
            foreach (SchemeUser::where(['scheme_id' => $schemeId, 'is_active' => 1]) as $m) {
                $roleId = (int) $m['role_id'];
                $userId = (int) $m['user_id'];
                if (in_array($roleId, $roleIdsWithStatus, true) && !in_array($userId, $map[$roleId] ?? [], true)) {
                    SchemeUser::update($m['map_id'], ['is_active' => 0]);
                }
            }

            // Insert new assignments or reactivate previously deactivated ones
            foreach ($map as $roleId => $userIds) {
                foreach ($userIds as $userId) {
                    $existing = SchemeUser::first(['scheme_id' => $schemeId, 'role_id' => $roleId, 'user_id' => $userId]);
                    if ($existing) {
                        if (!$existing['is_active']) {
                            SchemeUser::update($existing['map_id'], ['is_active' => 1]);
                        }
                    } else {
                        SchemeUser::insert([
                            'scheme_id'  => $schemeId,
                            'role_id'    => $roleId,
                            'user_id'    => $userId,
                            'is_active'  => 1,
                            'created_by' => $by,
                        ]);
                    }
                }
            }
        });

        Audit::log('SCHEME', $schemeId, 'MAPPED_USERS', null, ['mapping' => $map]);
        $this->done('Role users assigned successfully for scheme "' . $scheme['scheme_name'] . '".');
    }
}
