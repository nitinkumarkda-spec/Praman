<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Password;
use App\Models\LoginAttempt;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use App\Services\Notify;

/** Admin → User Master (+ "Login as" another user). */
final class UserController extends Controller
{
    public function save(): void
    {
        $id = $this->id() ?: null;
        $this->authorize($id ? 'user.edit' : 'user.create');
        $old = $id ? User::find($id) : null;
        if ($id && !$old) {
            $this->fail('User not found.');
        }
        $isSelf = $id === Auth::id();
        $emailInput = trim(strtolower($this->request->str('email')));
        $_POST['email'] = $emailInput;
        $rules = [
            'name'   => ['Full name', 'required|min:3|max:120|name'],
            'mobile' => ['Mobile', 'required|mobile'],
        ];
        if ($emailInput !== '') {
            $rules['email'] = ['Email', 'max:150|email'];
        }
        if (!$id) {
            $rules['password'] = ['Password', 'required|password'];
        }
        $d = $this->validate($rules);

        $roleId = (int) ($this->request->input('role_id') ?: ($old['role_id'] ?? 1));
        $role = Role::find($roleId);
        if (!$role || (!$role['is_active'] && (int) ($old['role_id'] ?? 0) !== $roleId)) {
            $this->fail('Please select an active role.', 'role_id');
        }
        $email = $emailInput !== '' ? $d['email'] : null;
        $data = [
            'name'      => $d['name'],
            'mobile'    => $d['mobile'],
            'email'     => $email,
            'role_id'   => $roleId,
            'is_active' => $isSelf ? 1 : ($this->request->input('is_active') === '1' ? 1 : 0),
        ];

        if (User::valueExists('mobile', $data['mobile'], $id)) {
            $this->fail('This mobile number is already registered.', 'mobile');
        }
        if ($email !== null && User::valueExists('email', $email, $id)) {
            $this->fail('This email is already registered.', 'email');
        }

        if ($id && (int) ($old['role_id'] ?? 0) === 1 && ($roleId !== 1 || !$data['is_active']) && $this->activeAdmins($id) === 0) {
            $this->fail('At least one active Admin must remain.');
        }
        if (!$id) {
            $newId = User::insert($data + ['password_hash' => Password::hash($d['password']), 'must_change_password' => 0, 'created_by' => Auth::id()]);
            Audit::log('USER', $newId, 'CREATED', null, $data);
            Notify::send($newId, [
                'type' => 'ACCOUNT', 'tone' => 'success', 'icon' => 'fa-hand-sparkles', 'title' => 'Welcome to ' . \App\Services\Settings::get('app_name'),
                'message' => 'Your account was created by ' . Auth::user()['name'] . '. Please change your password after first login.', 'link' => '/change-password',
            ]);
            $this->done('User "' . $data['name'] . '" (' . $data['mobile'] . ') created successfully.', '/admin/users');
        }
        User::update($id, $data + ['updated_by' => Auth::id()]);
        Audit::log('USER', $id, 'UPDATED', ['name' => $old['name'], 'role_id' => $old['role_id'], 'is_active' => $old['is_active']], $data);
        $cleared = (int) ($old['role_id'] ?? 0) !== $roleId ? \App\Models\UserPermission::deleteWhere(['user_id' => $id]) : 0;
        $this->done('User "' . $data['name'] . '" updated successfully.' . ($cleared ? ' Personal permission exceptions of the old role were removed.' : ''), '/admin/users');
    }

    public function toggle(): void
    {
        $u = User::find($this->id());
        if (!$u) {
            $this->fail('User not found.');
        }
        if ($u['user_id'] === Auth::id()) {
            $this->fail('You cannot deactivate your own account.');
        }
        $active = $u['is_active'] ? 0 : 1;
        if (!$active && (int) ($u['role_id'] ?? 0) === 1 && $this->activeAdmins($u['user_id']) === 0) {
            $this->fail('At least one active Admin must remain.');
        }
        User::update($u['user_id'], ['is_active' => $active, 'updated_by' => Auth::id()]);
        Audit::log('USER', $u['user_id'], $active ? 'ACTIVATED' : 'DEACTIVATED', ['is_active' => 1 - $active], ['is_active' => $active]);
        $this->done('User "' . $u['name'] . '" ' . ($active ? 'activated.' : 'deactivated.'));
    }

    public function unlock(): void
    {
        $u = User::find($this->id());
        if (!$u) {
            $this->fail('User not found.');
        }
        User::update($u['user_id'], ['failed_attempts' => 0, 'locked_until' => null, 'updated_by' => Auth::id()]);
        Audit::log('USER', $u['user_id'], 'UNLOCKED', ['locked_until' => $u['locked_until']], ['locked_until' => null]);
        $this->done('Account of "' . $u['name'] . '" unlocked.');
    }

    public function resetPassword(): void
    {
        $u = User::find($this->id());
        if (!$u) {
            $this->fail('User not found.');
        }
        $d = $this->validate([
            'new_password' => ['New password', 'required|password'],
        ]);
        if ((string) $this->request->input('confirm_new_password') !== $d['new_password']) {
            $this->fail('Confirm password must match the new password.', 'confirm_new_password');
        }
        User::update($u['user_id'], [
            'password_hash' => Password::hash($d['new_password']),
            'failed_attempts' => 0,
            'locked_until' => null,
            'password_changed_at' => now(),
            'must_change_password' => 1,
            'updated_by' => Auth::id(),
        ]);
        Audit::log('USER', $u['user_id'], 'PASSWORD_RESET', null, ['must_change_password' => 1]);
        Notify::send($u['user_id'], [
            'type' => 'ACCOUNT', 'tone' => 'warning', 'icon' => 'fa-key', 'title' => 'Password reset by Admin',
            'message' => 'Your password was reset by ' . Auth::user()['name'] . '. You will be asked to choose a new password when you login.', 'link' => '/change-password',
        ]);
        $this->done('Password for "' . $u['name'] . '" reset successfully.');
    }

    public function impersonate(): void
    {
        $u = User::find($this->id());
        if (!$u || !$u['is_active'] || (int) ($u['role_id'] ?? 0) === 1 || Auth::impersonator()) {
            $this->fail('Cannot login as this user.');
        }
        $role = $u['role_id'] ? Role::find($u['role_id']) : null;
        Auth::impersonate($u, Auth::user());
        Audit::log('USER', $u['user_id'], 'IMPERSONATED', null, ['user' => $u['name'] . ' (' . $u['mobile'] . ')']);
        $this->done('You are now logged in as ' . $u['name'] . ' (' . ($role ? $role['role_name'] : 'User') . '). Use "Back to Admin" to return.',
            '/admin/dashboard', 'info');
    }

    public function stopImpersonation(): void
    {
        $admin = Auth::impersonator();
        if (!$admin) {
            $this->fail('You are not currently logged in as another user.');
        }
        $target = Auth::user();
        Auth::stopImpersonation();
        Audit::log('USER', $target['user_id'] ?? null, 'IMPERSONATION_STOPPED', null, ['admin' => $admin['name'] . ' (' . $admin['mobile'] . ')']);
        $this->done('Returned to your Admin account (' . $admin['name'] . ').', '/admin/users');
    }

    private function activeAdmins(?int $excludeId = null): int
    {
        $rows = User::where(['role_id' => 1, 'is_active' => 1]);
        if ($excludeId) {
            $rows = array_filter($rows, static fn ($u) => (int) $u['user_id'] !== $excludeId);
        }
        return count($rows);
    }
}
