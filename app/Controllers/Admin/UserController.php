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
use App\Models\User;

/**
 * User Master: Super Admin / Data Entry / OIC / Role users (mapped to a role).
 */
final class UserController extends Controller
{
    private const LABELS = [
        'name'             => 'Full name',
        'username'         => 'Username',
        'emp_code'         => 'Employee code',
        'designation'      => 'Designation',
        'mobile'           => 'Mobile',
        'email'            => 'Email',
        'panel'            => 'Panel type',
        'role_id'          => 'Role',
        'password'         => 'Password',
        'confirm_password' => 'Confirm password',
    ];

    public function index(): void
    {
        $p = Paginator::fromRequest($this->request, User::SORTABLE, 'id', 'desc', ['panel', 'role_id', 'status']);
        (new User())->paginateList($p);
        $this->view('admin/users/index', [
            'pageTitle' => 'User Master',
            'p'         => $p,
            'roles'     => (new Role())->options(),
        ]);
    }

    public function create(): void
    {
        $this->view('admin/users/form', [
            'pageTitle' => 'Add User',
            'user'      => null,
            'roles'     => (new Role())->active(),
        ]);
    }

    public function store(): void
    {
        $model = new User();
        $data = $this->collect(null, $model);
        $id = $model->create($data, (int) Auth::id());
        Audit::log('USER', $id, 'CREATED', null, $data);
        $this->flash('success', "User \"{$data['name']}\" ({$data['username']}) created successfully.");
        $this->redirect('/admin/users');
    }

    public function edit(): void
    {
        $user = $this->findOr404();
        $this->view('admin/users/form', [
            'pageTitle' => 'Edit User',
            'user'      => $user,
            'roles'     => (new Role())->active(),
            'mappings'  => (new User())->mappingCount((int) $user['user_id']),
            'isSelf'    => (int) $user['user_id'] === Auth::id(),
        ]);
    }

    public function update(): void
    {
        $user = $this->findOr404();
        $model = new User();
        $data = $this->collect($user, $model);
        $model->update((int) $user['user_id'], $data, (int) Auth::id());
        $old = array_intersect_key($user, $data);
        Audit::log('USER', (int) $user['user_id'], 'UPDATED', $old, $data);
        $this->flash('success', "User \"{$data['name']}\" updated successfully.");
        $this->redirect('/admin/users');
    }

    public function toggle(): void
    {
        $user = $this->findOr404();
        $model = new User();
        $activate = (int) $user['is_active'] !== 1;
        if (!$activate) {
            $this->guardDeactivation($user, $model);
        }
        $model->setActive((int) $user['user_id'], $activate, (int) Auth::id());
        Audit::log('USER', (int) $user['user_id'], $activate ? 'ACTIVATED' : 'DEACTIVATED');
        $this->flash('success', "User \"{$user['name']}\" " . ($activate ? 'activated.' : 'deactivated. The user can no longer login.'));
        $this->back('/admin/users');
    }

    public function resetPassword(): void
    {
        $user = $this->findOr404();
        $back = '/admin/users/' . $user['user_id'] . '/edit';
        $data = $this->validate([
            'reset_password' => 'required|password',
            'reset_confirm'  => 'required|same:reset_password',
        ], ['reset_password' => 'New password', 'reset_confirm' => 'Confirm new password'], $back);

        (new User())->resetPassword((int) $user['user_id'], (string) $data['reset_password'], (int) Auth::id());
        Audit::log('USER', (int) $user['user_id'], 'PASSWORD_RESET');
        $this->flash('success', "Password reset for \"{$user['name']}\". They must change it on next login.");
        $this->redirect($back);
    }

    /** "Login as" — open the selected user's panel; a "Back to Admin" bar returns. */
    public function impersonate(): void
    {
        $user = $this->findOr404();
        $error = match (true) {
            (int) $user['user_id'] === Auth::id()   => 'You are already logged in as yourself.',
            $user['panel'] === 'SUPER_ADMIN'         => 'Login-as is not allowed for another Super Admin account.',
            (int) $user['is_active'] !== 1           => 'This user is deactivated. Activate the account first.',
            !in_array($user['panel'], (array) \App\Core\Config::get('enabled_panels', []), true) => 'This user\'s panel is not enabled.',
            default                                  => null,
        };
        if ($error !== null) {
            $this->flash('error', $error);
            $this->back('/admin/users');
        }
        $return = (string) $this->request->input('return', '/admin/users');
        $base = \App\Core\Request::basePath();
        if ($base !== '' && str_starts_with($return, $base . '/')) {
            $return = substr($return, strlen($base)); // keep the list filters/page, as a route path
        }
        Auth::startImpersonation($user, is_string($return) && str_starts_with($return, '/admin/') ? $return : '/admin/users');
        $this->flash('info', "You are now logged in as {$user['name']} (" . (Auth::PANELS[$user['panel']] ?? $user['panel'])
            . ($user['role_name'] ? ' – ' . $user['role_name'] : '') . '). Use "Back to Admin" at the top to return.');
        $this->redirect(Auth::home());
    }

    public function unlock(): void
    {
        $user = $this->findOr404();
        (new User())->unlock((int) $user['user_id'], (int) Auth::id());
        Audit::log('USER', (int) $user['user_id'], 'UNLOCKED');
        $this->flash('success', "Account \"{$user['username']}\" unlocked.");
        $this->back('/admin/users');
    }

    private function collect(?array $existing, User $model): array
    {
        $_POST['username'] = mb_strtolower(trim((string) $this->request->input('username', '')));
        $_POST['emp_code'] = strtoupper(trim((string) $this->request->input('emp_code', '')));
        $_POST['email'] = mb_strtolower(trim((string) $this->request->input('email', '')));
        $back = $existing === null ? '/admin/users/create' : '/admin/users/' . $existing['user_id'] . '/edit';

        $rules = [
            'name'        => 'required|min:3|max:120|name',
            'username'    => 'required|min:3|max:50|alpha_dash',
            'emp_code'    => 'required|ucode',
            'designation' => 'required|min:2|max:100|text',
            'mobile'      => 'required|mobile',
            'email'       => 'required|max:150|email',
            'panel'       => 'required|in:SUPER_ADMIN,DATA_ENTRY,OIC,ROLE_USER',
            'role_id'     => 'nullable|integer',
        ];
        if ($existing === null) {
            $rules['password'] = 'required|password';
            $rules['confirm_password'] = 'required|same:password';
        }
        $data = $this->validate($rules, self::LABELS, $back);

        $v = Validator::make([], []);
        $id = $existing !== null ? (int) $existing['user_id'] : null;
        foreach (['username' => 'username', 'emp_code' => 'employee code', 'email' => 'email'] as $field => $text) {
            if ($model->valueExists('users', $field, (string) $data[$field], 'user_id', $id)) {
                $v->addError($field, "Another user already has this {$text}.");
            }
        }

        if ($data['panel'] === 'ROLE_USER') {
            $role = ($data['role_id'] !== null && $data['role_id'] !== '') ? (new Role())->find((int) $data['role_id']) : null;
            $keepsOldRole = $existing !== null && $role !== null && (int) $existing['role_id'] === (int) $role['role_id'];
            if ($role === null || ((int) $role['is_active'] !== 1 && !$keepsOldRole)) {
                $v->addError('role_id', 'Please select an active role for a Role User.');
            }
        } else {
            $data['role_id'] = null;
        }

        $isActive = $this->request->input('is_active') === '1' ? 1 : 0;
        if ($existing !== null && (int) $existing['user_id'] === Auth::id()) {
            if ($data['panel'] !== $existing['panel']) {
                $v->addError('panel', 'You cannot change your own panel.');
            }
            $isActive = 1; // cannot deactivate yourself
        }
        if ($existing !== null && $existing['panel'] === 'SUPER_ADMIN' && (int) $existing['is_active'] === 1
            && ($data['panel'] !== 'SUPER_ADMIN' || $isActive === 0) && $model->activeSuperAdminCount() <= 1) {
            $v->addError('panel', 'At least one active Super Admin must remain.');
        }
        if ($existing === null && stripos((string) ($data['password'] ?? ''), (string) $data['username']) !== false) {
            $v->addError('password', 'Password must not contain the username.');
        }
        if ($v->fails()) {
            $this->failValidation($v->errors(), $back);
        }

        $data['is_active'] = $isActive;
        $data['must_change_password'] = $existing === null && $this->request->input('must_change_password') !== '1' ? 0 : 1;
        unset($data['confirm_password']);
        return $data;
    }

    private function guardDeactivation(array $user, User $model): void
    {
        if ((int) $user['user_id'] === Auth::id()) {
            $this->flash('error', 'You cannot deactivate your own account.');
            $this->back('/admin/users');
        }
        if ($user['panel'] === 'SUPER_ADMIN' && $model->activeSuperAdminCount() <= 1) {
            $this->flash('error', 'At least one active Super Admin must remain.');
            $this->back('/admin/users');
        }
    }

    private function findOr404(): array
    {
        $user = (new User())->find($this->idParam());
        if ($user === null) {
            throw new HttpException(404, 'User not found.');
        }
        return $user;
    }
}
