<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Role;
use App\Services\Audit;

/** Super Admin → Role Master. */
final class RoleController extends Controller
{
    public function save(): void
    {
        $id = $this->id() ?: null;
        $old = $id ? Role::find($id) : null;
        if ($id && !$old) {
            $this->fail('Role not found.');
        }
        $rules = [
            'role_name'    => ['Role name', 'required|min:3|max:100|name'],
            'role_name_hi' => ['Role name (Hindi)', 'max:100|text'],
            'sla_days'     => ['SLA days', 'required|integer|between:1,365'],
            'sort_order'   => ['Display order', 'required|integer|between:0,999'],
            'description'  => ['Description', 'max:255|text'],
        ];
        if (!$id) {
            $_POST['role_code'] = strtoupper($this->request->str('role_code'));
            $rules['role_code'] = ['Role code', 'required|code'];
        }
        $d = $this->validate($rules);
        $data = [
            'role_name' => $d['role_name'], 'role_name_hi' => $d['role_name_hi'] ?: null, 'description' => $d['description'] ?: null,
            'sla_days' => (int) $d['sla_days'], 'sort_order' => (int) $d['sort_order'],
            'is_mandatory' => $this->request->input('is_mandatory') === '1' ? 1 : 0, 'is_active' => $this->request->input('is_active') === '1' ? 1 : 0,
        ];
        if (Role::valueExists('role_name', $data['role_name'], $id)) {
            $this->fail('A role with this name already exists.', 'role_name');
        }
        if (!$id) {
            $data['role_code'] = $d['role_code'];
            if (Role::valueExists('role_code', $data['role_code'])) {
                $this->fail('This role code is already in use.', 'role_code');
            }
            $newId = Role::insert($data + ['created_by' => Auth::id()]);
            Audit::log('ROLE', $newId, 'CREATED', null, $data);
            $this->done('Role "' . $data['role_name'] . '" created successfully.', '/admin/roles');
        }
        Role::update($id, $data + ['updated_by' => Auth::id()]);
        Audit::log('ROLE', $id, 'UPDATED', array_intersect_key($old, $data), $data);
        $this->done('Role "' . $data['role_name'] . '" updated successfully.', '/admin/roles');
    }

    public function toggle(): void
    {
        $r = Role::find($this->id());
        if (!$r) {
            $this->fail('Role not found.');
        }
        $active = $r['is_active'] ? 0 : 1;
        Role::update($r['role_id'], ['is_active' => $active, 'updated_by' => Auth::id()]);
        Audit::log('ROLE', $r['role_id'], $active ? 'ACTIVATED' : 'DEACTIVATED', ['is_active' => 1 - $active], ['is_active' => $active]);
        $this->done('Role "' . $r['role_name'] . '" ' . ($active ? 'activated.' : 'deactivated. It will not be used for new schemes / verification rounds.'));
    }
}
