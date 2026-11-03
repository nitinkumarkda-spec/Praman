<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Codes\Panel;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lookups;
use App\Core\Request;
use App\Models\Area;
use App\Models\PlotType;
use App\Models\Property;
use App\Models\Role;
use App\Models\Scheme;
use App\Models\SchemeUser;
use App\Models\Sector;
use App\Models\SiteInspection;
use App\Models\Status;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit;

/**
 * Every master (Area, Plot Type, Unit, Status, Sector, Scheme) through ONE action:
 *   POST /api/master   key = areas | plot-types | units | statuses | sectors | schemes
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
     *   fields:  name => [label, rules, unique, upper, lockOnEdit, number(false|true|'float')]
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
     * ]
     */
    private function masters(): array
    {
        $code = static fn ($label = 'Code') => [$label, 'required|code', true, true, true, false];
        $order = ['Display order', 'required|integer|between:0,999', false, false, false, true];
        $text = static fn ($label, $max, $req = false, $unique = false) => [$label, ($req ? 'required|min:2|' : '') . 'max:' . $max . '|text', $unique, false, false, false];
        $lastSector = static fn ($r) => $r['is_active'] && Sector::count(['scheme_id' => $r['scheme_id'], 'is_active' => 1]) <= 1
            ? 'This is the last active sector of the scheme. A scheme needs at least one active sector.' : null;

        return [
            'areas' => [
                'model' => Area::class, 'entity' => 'AREA', 'one' => 'Area', 'name' => static fn ($r) => $r['area_name'],
                'fields' => ['area_code' => $code(), 'area_name' => $text('Area name', 60, true, true),
                    'description' => $text('Description', 150), 'sort_order' => $order],
                'usage' => static fn ($r) => Scheme::count(['area_id' => $r['area_id']]),
                'block' => static function ($r) {
                    $n = Scheme::count(['area_id' => $r['area_id'], 'is_active' => 1]);
                    return $n ? 'This area has ' . $n . ' active scheme' . ($n > 1 ? 's' : '') . ' and cannot be deactivated.' : null;
                },
            ],
            'plot-types' => [
                'model' => PlotType::class, 'entity' => 'PLOT_TYPE', 'one' => 'Plot type', 'name' => static fn ($r) => $r['name'],
                'fields' => ['code' => $code(), 'name' => $text('Name', 60, true, true),
                    'description' => $text('Description', 150), 'sort_order' => $order],
                'usage' => static fn ($r) => Property::count(['property_type' => $r['code']]),
                'block' => static fn ($r) => PlotType::count(['is_active' => 1]) <= 1 ? 'At least one plot type must remain active.' : null,
            ],
            'units' => [
                'model' => Unit::class, 'entity' => 'UNIT', 'one' => 'Unit', 'name' => static fn ($r) => $r['name'], 'system' => true,
                'fields' => ['code' => $code(), 'name' => $text('Unit name', 40, true, true),
                    'symbol' => ['Symbol', 'required|max:15|text', true, false, false, false],
                    'to_sqm' => ['1 unit = sq m', 'required|decimal:8|positive', false, false, false, 'float'],
                    'description' => $text('Description', 150), 'sort_order' => $order],
                'usage' => static fn ($r) => Property::count(['area_unit' => $r['code']]) + SiteInspection::count(['area_unit' => $r['code']]),
                'block' => static fn ($r) => $r['is_system'] ? 'The base unit (sq m) cannot be deactivated.' : null,
                // stored areas were converted with the old factor
                'check' => static fn ($d, $old, $used) => !$old || abs((float) $d['to_sqm'] - (float) $old['to_sqm']) <= 1e-9 ? null
                    : ($old['is_system'] ? ['The base unit (sq m) is always 1 sq m.', 'to_sqm']
                        : ($used > 0 ? ['This unit is used by properties, so its conversion factor cannot change. Add a new unit instead.', 'to_sqm'] : null)),
            ],
            'statuses' => [
                'model' => Status::class, 'entity' => 'STATUS', 'one' => 'Property status', 'name' => static fn ($r) => $r['name'], 'system' => true,
                'fields' => ['code' => $code('Status code'), 'name' => $text('Display name', 40, true, true),
                    'color' => ['Badge colour', 'required|in:' . Lookups::keys(Lookups::STATUS_COLORS), false, false, false, false],
                    'icon' => ['Icon', 'required|in:' . Lookups::keys(Lookups::STATUS_ICONS), false, false, false, false],
                    'description' => $text('Meaning', 150), 'sort_order' => $order],
                'usage' => static fn ($r) => Property::count(['current_status' => $r['code']]),
                'block' => static fn ($r) => $r['is_system'] ? 'System statuses cannot be deactivated.' : null,
            ],
            'sectors' => [
                'model' => Sector::class, 'entity' => 'SECTOR', 'one' => 'Sector', 'name' => static fn ($r) => $r['sector_name'], 'scope' => 'scheme_id',
                'fields' => ['sector_name' => ['Sector name', 'required|max:80|text', true, false, false, false], 'sort_order' => $order],
                'create' => ['scheme_id' => ['Scheme', 'required|integer', false, false, false, true],
                    'sector_name' => ['Sector name(s)', 'required|max:400|text', true, false, false, false]],
                'split' => 'sector_name',
                'usage' => static fn ($r) => Property::count(['sector_id' => $r['sector_id']]),
                'block' => $lastSector,
            ],
            // Scheme: details + its sectors + the role users who verify its properties (Data Entry and Super Admin)
            'schemes' => [
                'model' => Scheme::class, 'entity' => 'SCHEME', 'one' => 'Scheme', 'name' => static fn ($r) => $r['scheme_name'],
                'perm' => 'scheme', 'actions' => ['save', 'toggle'], 'keepActive' => true,
                'fields' => [
                    'scheme_code'    => ['Scheme code', 'required|ucode', true, true, true, false],
                    'scheme_name'    => ['Scheme name', 'required|min:3|max:150|text', true, false, false, false],
                    'scheme_type'    => ['Scheme type', 'required|in:' . Lookups::keys(Lookups::SCHEME_TYPES), false, false, false, false],
                    'area_id'        => ['Area', 'required|integer', false, false, false, true],
                    'year_of_scheme' => ['Year of scheme', 'required|year', false, false, false, true],
                ],
                'usage' => static fn ($r) => Sector::count(['scheme_id' => $r['scheme_id']]),
                'block' => static fn ($r) => null,
                'check' => static function ($d, $old) {
                    $area = Area::find((int) $d['area_id']);
                    return !$area || (!$old && !$area['is_active']) ? ['Please select a valid active area.', 'area_id'] : null;
                },
                'prepare' => static function (Request $in, ?int $id): array {
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
                    if (count(array_filter($existing, static fn ($x) => $x['active'])) + count($fresh) < 1) {
                        return ['fail' => ['Add at least one (active) sector. A scheme cannot be saved without sectors.', null]];
                    }
                    // role-user mapping: every mandatory active role needs at least one valid user
                    $map = [];
                    foreach (Role::where(['is_active' => 1], 'sort_order') as $r) {
                        $valid = array_column(User::where(['is_active' => 1, 'panel' => Panel::ROLE_USER, 'role_id' => $r['role_id']]), 'user_id');
                        $ids = array_values(array_unique(array_map('intval', $in->list('role_users_' . $r['role_id']))));
                        if (array_diff($ids, $valid)) {
                            return ['fail' => ['Only active users of "' . $r['role_name'] . '" can be mapped to it.', null]];
                        }
                        if ($ids) {
                            $map[$r['role_id']] = $ids;
                        } elseif ($r['is_mandatory']) {
                            return ['fail' => [$valid ? 'Select at least one user for "' . $r['role_name'] . '" (mandatory role).'
                                : '"' . $r['role_name'] . '" is mandatory but has no active user. Ask the Super Admin to add one.', null]];
                        }
                    }
                    return ['existing' => $existing, 'fresh' => $fresh, 'map' => $map];
                },
                'saved' => static function (int $sid, array $x, ?int $by): void {
                    foreach ($x['existing'] as $e) {
                        Sector::update($e['s']['sector_id'], ['sector_name' => $e['name'], 'is_active' => $e['active'], 'updated_by' => $by]);
                    }
                    $order = Sector::max('sort_order', ['scheme_id' => $sid]);
                    foreach ($x['fresh'] as $n) {
                        Sector::insert(['scheme_id' => $sid, 'sector_name' => $n, 'sort_order' => ++$order, 'is_active' => 1, 'created_by' => $by]);
                    }
                    foreach (SchemeUser::where(['scheme_id' => $sid, 'is_active' => 1]) as $m) {
                        if (!in_array((int) $m['user_id'], $x['map'][$m['role_id']] ?? [], true)) {
                            SchemeUser::update($m['map_id'], ['is_active' => 0]);
                        }
                    }
                    foreach ($x['map'] as $rid => $uids) {
                        foreach ($uids as $uid) {
                            if (!SchemeUser::exists(['scheme_id' => $sid, 'role_id' => $rid, 'user_id' => $uid, 'is_active' => 1])) {
                                SchemeUser::insert(['scheme_id' => $sid, 'role_id' => $rid, 'user_id' => $uid, 'is_active' => 1, 'created_by' => $by]);
                            }
                        }
                    }
                },
                'audit' => static fn ($x) => ['sectors' => $x['fresh'], 'mapping' => $x['map']],
                'note' => static fn ($x, $isNew) => $isNew ? ' — ' . count($x['fresh']) . ' sector(s).' : ' Changes apply to new properties only.',
                'list' => static fn (Request $in) => in_array($in->str('back'), self::SCHEME_LISTS, true) ? $in->str('back')
                    : (Auth::panel() === Panel::SUPER_ADMIN ? '/admin/masters/schemes' : '/data-entry/schemes'),
            ],
        ];
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
                $_POST[$f] = strtoupper($this->request->str($f));
            }
            $rules[$f] = [$label, $rule];
        }
        $d = $this->validate($rules);
        $data = [];
        foreach ($rules as $f => $_) {
            $number = $fields[$f][5];
            $data[$f] = $d[$f] === '' ? null : ($number === 'float' ? (float) $d[$f] : ($number ? (int) $d[$f] : $d[$f]));
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
}
