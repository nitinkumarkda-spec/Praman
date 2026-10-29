<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Role;
use App\Models\Scheme;
use App\Models\SchemeUser;
use App\Models\Sector;
use App\Models\User;
use App\Models\Zone;
use App\Services\Audit;

/**
 * Scheme Master (Data Entry and Super Admin): details, sectors and the role-user mapping.
 */
final class SchemeController extends Controller
{
    private const BACKS = ['/data-entry/schemes', '/admin/masters/schemes'];

    public function save(): void
    {
        $id = $this->id() ?: null;
        $scheme = $id ? Scheme::find($id) : null;
        if ($id && !$scheme) {
            $this->fail('Scheme not found.');
        }
        $rules = [
            'scheme_name'    => ['Scheme name', 'required|min:3|max:150|text'],
            'scheme_type'    => ['Scheme type', 'required|in:' . \App\Core\Lookups::keys(\App\Core\Lookups::SCHEME_TYPES)],
            'zone_id'        => ['Zone', 'required|integer'],
            'year_of_scheme' => ['Year of scheme', 'required|year'],
        ];
        if (!$id) {
            $_POST['scheme_code'] = strtoupper($this->request->str('scheme_code'));
            $rules['scheme_code'] = ['Scheme code', 'required|ucode'];
        }
        $d = $this->validate($rules);
        if ($this->request->input('sectors_done') !== '1') {
            $this->fail('Tick “All sectors added — Done” after adding all sectors.', 'sectors_done');
        }
        $data = ['scheme_name' => $d['scheme_name'], 'scheme_type' => $d['scheme_type'], 'zone_id' => (int) $d['zone_id'], 'year_of_scheme' => (int) $d['year_of_scheme']];
        $zone = Zone::find($data['zone_id']);
        if (!$zone || (!$zone['is_active'] && (!$scheme || (int) $scheme['zone_id'] !== $data['zone_id']))) {
            $this->fail('Please select an active zone.', 'zone_id');
        }
        if (Scheme::valueExists('scheme_name', $data['scheme_name'], $id)) {
            $this->fail('A scheme with this name already exists.', 'scheme_name');
        }
        if (!$id) {
            $data['scheme_code'] = $d['scheme_code'];
            if (Scheme::valueExists('scheme_code', $data['scheme_code'])) {
                $this->fail('This scheme code is already in use.', 'scheme_code');
            }
        }

        // sectors: existing (rename / deactivate) + new rows
        $existing = [];
        if ($id) {
            foreach (Sector::where(['scheme_id' => $id], 'sort_order') as $s) {
                $existing[] = ['s' => $s, 'name' => $this->request->str('sec_name_' . $s['sector_id']), 'active' => $this->request->input('sec_active_' . $s['sector_id']) === '1' ? 1 : 0];
            }
        }
        $fresh = array_values(array_filter($this->request->list('sectors_new'), 'strlen'));
        $seen = [];
        foreach (array_merge(array_column($existing, 'name'), $fresh) as $n) {
            if ($n === '' || mb_strlen($n) > 80) {
                $this->fail('Sector name is required (max 80 characters).');
            }
            if (preg_match('/[<>]/', $n)) {
                $this->fail('Sector "' . mb_substr($n, 0, 30) . '": < and > are not allowed.');
            }
            $k = mb_strtolower($n);
            if (isset($seen[$k])) {
                $this->fail('Sector name "' . $n . '" is repeated. Each sector name must be unique in a scheme.');
            }
            $seen[$k] = true;
        }
        if (count(array_filter($existing, static fn ($x) => $x['active'])) + count($fresh) < 1) {
            $this->fail('Add at least one (active) sector. A scheme cannot be saved without sectors.');
        }

        // role-user mapping: every mandatory active role needs at least one valid user
        $map = [];
        foreach (Role::where(['is_active' => 1], 'sort_order') as $r) {
            $valid = array_column(User::where(['is_active' => 1, 'panel' => 'ROLE_USER', 'role_id' => $r['role_id']]), 'user_id');
            $ids = array_values(array_unique(array_map('intval', $this->request->list('role_users_' . $r['role_id']))));
            if (array_diff($ids, $valid)) {
                $this->fail('Only active users of "' . $r['role_name'] . '" can be mapped to it.');
            }
            if ($ids) {
                $map[$r['role_id']] = $ids;
            } elseif ($r['is_mandatory']) {
                $this->fail($valid
                    ? 'Select at least one user for "' . $r['role_name'] . '" (mandatory role).'
                    : '"' . $r['role_name'] . '" is mandatory but has no active user. Ask the Super Admin to add one.');
            }
        }

        $by = Auth::id();
        $sid = Database::transaction(function () use ($id, $data, $existing, $fresh, $map, $by): int {
            if (!$id) {
                $sid = Scheme::insert($data + ['is_active' => 1, 'created_by' => $by]);
            } else {
                $sid = $id;
                Scheme::update($id, $data + ['updated_by' => $by]);
                foreach ($existing as $x) {
                    Sector::update($x['s']['sector_id'], ['sector_name' => $x['name'], 'is_active' => $x['active'], 'updated_by' => $by]);
                }
            }
            $order = Sector::max('sort_order', ['scheme_id' => $sid]);
            foreach ($fresh as $n) {
                Sector::insert(['scheme_id' => $sid, 'sector_name' => $n, 'sort_order' => ++$order, 'is_active' => 1, 'created_by' => $by]);
            }
            foreach (SchemeUser::where(['scheme_id' => $sid, 'is_active' => 1]) as $m) {
                if (!in_array((int) $m['user_id'], $map[$m['role_id']] ?? [], true)) {
                    SchemeUser::update($m['map_id'], ['is_active' => 0]);
                }
            }
            foreach ($map as $rid => $uids) {
                foreach ($uids as $uid) {
                    if (!SchemeUser::exists(['scheme_id' => $sid, 'role_id' => $rid, 'user_id' => $uid, 'is_active' => 1])) {
                        SchemeUser::insert(['scheme_id' => $sid, 'role_id' => $rid, 'user_id' => $uid, 'is_active' => 1, 'created_by' => $by]);
                    }
                }
            }
            return $sid;
        });

        $back = in_array($this->request->str('back'), self::BACKS, true) ? $this->request->str('back')
            : (Auth::panel() === 'SUPER_ADMIN' ? '/admin/masters/schemes' : '/data-entry/schemes');
        if (!$id) {
            Audit::log('SCHEME', $sid, 'CREATED', null, $data + ['sectors' => $fresh, 'mapping' => $map]);
            $this->done('Scheme "' . $data['scheme_name'] . '" created with ' . count($fresh) . ' sector(s).', $back);
        }
        Audit::log('SCHEME', $sid, 'UPDATED', ['scheme_name' => $scheme['scheme_name'], 'zone_id' => $scheme['zone_id']], $data + ['new_sectors' => $fresh, 'mapping' => $map]);
        $this->done('Scheme "' . $data['scheme_name'] . '" updated. Changes apply to new properties only.', $back);
    }

    public function toggle(): void
    {
        $s = Scheme::find($this->id());
        if (!$s) {
            $this->fail('Scheme not found.');
        }
        $active = $s['is_active'] ? 0 : 1;
        Scheme::update($s['scheme_id'], ['is_active' => $active, 'updated_by' => Auth::id()]);
        Audit::log('SCHEME', $s['scheme_id'], $active ? 'ACTIVATED' : 'DEACTIVATED', ['is_active' => 1 - $active], ['is_active' => $active]);
        $this->done('Scheme "' . $s['scheme_name'] . '" ' . ($active ? 'activated.' : 'deactivated. No new property can be added in it.'));
    }
}
