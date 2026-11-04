<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Codes\Section;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\Role;
use App\Services\Audit;

/** Admin → Role Master. */
final class RoleController extends Controller
{
    public function save(): void
    {
        $id = $this->id() ?: null;
        $this->authorize($id ? 'role.edit' : 'role.create');
        $old = $id ? Role::find($id) : null;
        if ($id && !$old) {
            $this->fail('Role not found.');
        }
        $rules = [
            'role_name'   => ['Role name', 'required|min:2|max:100|name'],
            'description' => ['Description', 'max:255|text'],
        ];
        $sla = $this->request->input('sla_days');
        if ($sla !== null && $sla !== '') {
            $rules['sla_days'] = ['SLA days', 'integer|between:1,365'];
        }
        $d = $this->validate($rules);
        $form = $this->request->str('verification_form', Section::GENERAL);
        if (!isset(Section::FORMS[$form])) {
            $form = Section::GENERAL;
        }
        $isActive = $id === 1 ? 1 : ($this->request->input('is_active') === '1' ? 1 : 0);
        $data = [
            'role_name' => $d['role_name'],
            'verification_form' => $form,
            'role_name_hi' => $this->request->str('role_name_hi') ?: null,
            'description' => $d['description'] ?: null,
            'sla_days' => !empty($d['sla_days']) ? (int) $d['sla_days'] : 5,
            'sort_order' => $id ? ($old['sort_order'] ?? 0) : (((int) Role::max('sort_order')) + 1),
            'is_mandatory' => $this->request->input('is_mandatory') === '1' ? 1 : 0,
            'is_active' => $isActive,
        ];
        if (Role::valueExists('role_name', $data['role_name'], $id)) {
            $this->fail('A role with this name already exists.', 'role_name');
        }
        if (!$id) {
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
        if ((int) $r['role_id'] === 1) {
            $this->fail('The Admin role cannot be deactivated.');
        }
        $active = $r['is_active'] ? 0 : 1;
        Role::update($r['role_id'], ['is_active' => $active, 'updated_by' => Auth::id()]);
        Audit::log('ROLE', $r['role_id'], $active ? 'ACTIVATED' : 'DEACTIVATED', ['is_active' => 1 - $active], ['is_active' => $active]);
        $this->done('Role "' . $r['role_name'] . '" ' . ($active ? 'activated.' : 'deactivated.'));
    }
}
