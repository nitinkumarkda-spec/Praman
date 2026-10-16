<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Validator;
use App\Models\Role;

/**
 * Role Master (each role = one verification section).
 */
final class RoleController extends Controller
{
    private const LABELS = [
        'role_name'    => 'Role name',
        'role_code'    => 'Role code',
        'role_name_hi' => 'Role name (Hindi)',
        'description'  => 'Description',
        'sla_days'     => 'SLA days',
        'sort_order'   => 'Display order',
    ];

    public function index(): void
    {
        $p = Paginator::fromRequest($this->request, Role::SORTABLE, 'order', 'asc', ['status']);
        (new Role())->paginateList($p);
        $this->view('admin/roles/index', ['pageTitle' => 'Role Master', 'p' => $p]);
    }

    public function create(): void
    {
        $this->view('admin/roles/form', ['pageTitle' => 'Add Role', 'role' => null]);
    }

    public function store(): void
    {
        $model = new Role();
        $data = $this->collect(null, $model);
        $id = $model->create($data, (int) Auth::id());
        Audit::log('ROLE', $id, 'CREATED', null, $data);
        $this->flash('success', "Role \"{$data['role_name']}\" created successfully.");
        $this->redirect('/admin/roles');
    }

    public function edit(): void
    {
        $role = $this->findOr404();
        $this->view('admin/roles/form', [
            'pageTitle' => 'Edit Role',
            'role'      => $role,
            'usage'     => (new Role())->schemeUsage((int) $role['role_id']),
        ]);
    }

    public function update(): void
    {
        $role = $this->findOr404();
        $model = new Role();
        $data = $this->collect($role, $model);
        $model->update((int) $role['role_id'], $data, (int) Auth::id());
        Audit::log('ROLE', (int) $role['role_id'], 'UPDATED', $role, $data);
        $this->flash('success', "Role \"{$data['role_name']}\" updated successfully.");
        $this->redirect('/admin/roles');
    }

    public function toggle(): void
    {
        $role = $this->findOr404();
        $active = (int) $role['is_active'] !== 1;
        (new Role())->setActive((int) $role['role_id'], $active, (int) Auth::id());
        Audit::log('ROLE', (int) $role['role_id'], $active ? 'ACTIVATED' : 'DEACTIVATED', ['is_active' => (int) $role['is_active']], ['is_active' => (int) $active]);
        $this->flash('success', "Role \"{$role['role_name']}\" " . ($active ? 'activated.' : 'deactivated. It will not be used for new schemes / verification rounds.'));
        $this->back('/admin/roles');
    }

    /** Validate + normalise the form. On edit the code is immutable. */
    private function collect(?array $existing, Role $model): array
    {
        $_POST['role_code'] = strtoupper(trim((string) ($existing['role_code'] ?? $this->request->input('role_code', ''))));
        $back = $existing === null ? '/admin/roles/create' : '/admin/roles/' . $existing['role_id'] . '/edit';

        $data = $this->validate([
            'role_name'    => 'required|min:3|max:100|name',
            'role_code'    => 'required|code',
            'role_name_hi' => 'nullable|max:100|text',
            'description'  => 'nullable|max:255|text',
            'sla_days'     => 'required|integer|between:1,365',
            'sort_order'   => 'required|integer|between:0,999',
        ], self::LABELS, $back);

        $v = Validator::make([], []);
        $id = $existing !== null ? (int) $existing['role_id'] : null;
        if ($model->valueExists('roles', 'role_name', (string) $data['role_name'], 'role_id', $id)) {
            $v->addError('role_name', 'A role with this name already exists.');
        }
        if ($existing === null && $model->valueExists('roles', 'role_code', (string) $data['role_code'], 'role_id')) {
            $v->addError('role_code', 'This role code is already in use.');
        }
        if ($v->fails()) {
            $this->failValidation($v->errors(), $back);
        }

        $data['is_mandatory'] = $this->request->input('is_mandatory') === '1' ? 1 : 0;
        $data['is_active'] = $this->request->input('is_active') === '1' ? 1 : 0;
        return $data;
    }

    private function findOr404(): array
    {
        $role = (new Role())->find($this->idParam());
        if ($role === null) {
            throw new HttpException(404, 'Role not found.');
        }
        return $role;
    }
}
