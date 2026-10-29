<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\LoginAttempt;
use App\Controllers\AuthController;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Password;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use App\Services\Notify;

/** Super Admin → User Master (+ "Login as" another user). */
final class UserController extends Controller
{

    public function save(): void
    {
        $id = $this->id() ?: null;
        $old = $id ? User::find($id) : null;
        if ($id && !$old) {
            $this->fail('User not found.');
        }
        $isSelf = $id === Auth::id();
        $_POST['username'] = strtolower($this->request->str('username'));
        $_POST['emp_code'] = strtoupper($this->request->str('emp_code'));
        $_POST['email'] = strtolower($this->request->str('email'));
        $rules = [
            'name'        => ['Full name', 'required|min:3|max:120|name'],
            'designation' => ['Designation', 'required|min:2|max:100|text'],
            'username'    => ['Username', 'required|min:3|max:50|alpha_dash'],
            'emp_code'    => ['Employee code', 'required|ucode'],
            'mobile'      => ['Mobile', 'required|mobile'],
            'email'       => ['Email', 'required|max:150|email'],
            'panel'       => ['Panel type', 'required|in:' . \App\Core\Lookups::keys(\App\Core\Lookups::PANELS)],
        ];
        if (!$id) {
            $rules['password'] = ['Password', 'required|password'];
        }
        $d = $this->validate($rules);
        if (!$id && (string) $this->request->input('confirm_password') !== $d['password']) {
            $this->fail('Confirm password must match the password.', 'confirm_password');
        }
        $panel = $isSelf ? $old['panel'] : $d['panel'];
        $roleId = null;
        if ($panel === 'ROLE_USER') {
            $roleId = $this->id('role_id');
            $role = $roleId ? Role::find($roleId) : null;
            if (!$role || (!$role['is_active'] && (int) ($old['role_id'] ?? 0) !== $roleId)) {
                $this->fail('Please select an active role / section.', 'role_id');
            }
        }
        $data = [
            'name' => $d['name'], 'designation' => $d['designation'], 'username' => $d['username'], 'emp_code' => $d['emp_code'],
            'mobile' => $d['mobile'], 'email' => $d['email'], 'panel' => $panel, 'role_id' => $roleId,
            'is_active' => $isSelf ? 1 : ($this->request->input('is_active') === '1' ? 1 : 0),
        ];
        foreach ([['username', 'This username is already taken.'], ['emp_code', 'This employee code is already in use.'],
                     ['email', 'This email is already registered.'], ['mobile', 'This mobile number is already registered.']] as [$col, $msg]) {
            if (User::valueExists($col, $data[$col], $id)) {
                $this->fail($msg, $col);
            }
        }
        if ($id && $old['panel'] === 'SUPER_ADMIN' && ($data['panel'] !== 'SUPER_ADMIN' || !$data['is_active']) && $this->activeAdmins($id) === 0) {
            $this->fail('At least one active Super Admin must remain.');
        }
        if (!$id) {
            $newId = User::insert($data + ['password_hash' => Password::hash($d['password']), 'must_change_password' => 0, 'created_by' => Auth::id()]);
            Audit::log('USER', $newId, 'CREATED', null, $data);
            Notify::send($newId, [
                'type' => 'ACCOUNT', 'tone' => 'success', 'icon' => 'fa-hand-sparkles', 'title' => 'Welcome to ' . \App\Services\Settings::get('app_name'),
                'message' => 'Your account was created by ' . Auth::user()['name'] . '. Please change your password after first login.', 'link' => '/change-password',
            ]);
            $this->done('User "' . $data['name'] . '" (' . $data['username'] . ') created successfully.', '/admin/users');
        }
        User::update($id, $data + ['updated_by' => Auth::id()]);
        Audit::log('USER', $id, 'UPDATED', ['name' => $old['name'], 'panel' => $old['panel'], 'role_id' => $old['role_id'], 'is_active' => $old['is_active']], $data);
        $this->done('User "' . $data['name'] . '" updated successfully.', '/admin/users');
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
        if (!$active && $u['panel'] === 'SUPER_ADMIN' && $this->activeAdmins($u['user_id']) === 0) {
            $this->fail('At least one active Super Admin must remain.');
        }
        User::update($u['user_id'], ['is_active' => $active, 'updated_by' => Auth::id()]);
        Audit::log('USER', $u['user_id'], $active ? 'ACTIVATED' : 'DEACTIVATED', ['is_active' => 1 - $active], ['is_active' => $active]);
        $this->done('User "' . $u['name'] . '" ' . ($active ? 'activated.' : 'deactivated. The user can no longer login.'));
    }

    public function unlock(): void
    {
        $u = User::find($this->id());
        if (!$u) {
            $this->fail('User not found.');
        }
        User::update($u['user_id'], ['locked_until' => null, 'failed_attempts' => 0]);
        LoginAttempt::deleteWhere(['username' => $u['username'], 'success' => 0]);
        Audit::log('USER', $u['user_id'], 'UNLOCKED', null, null);
        $this->done('Account "' . $u['username'] . '" unlocked.');
    }

    public function resetPassword(): void
    {
        $u = User::find($this->id());
        if (!$u) {
            $this->fail('User not found.');
        }
        $d = $this->validate(['reset_password' => ['New temporary password', 'required|password']]);
        if ((string) $this->request->input('reset_confirm') !== $d['reset_password']) {
            $this->fail('Confirm password must match the new password.', 'reset_confirm');
        }
        User::update($u['user_id'], [
            'password_hash' => Password::hash($d['reset_password']), 'locked_until' => null, 'failed_attempts' => 0,
            'password_changed_at' => now(), 'must_change_password' => 1,
        ]);
        Audit::log('USER', $u['user_id'], 'PASSWORD_RESET', null, null);
        Notify::send($u['user_id'], [
            'type' => 'ACCOUNT', 'tone' => 'warning', 'icon' => 'fa-key', 'title' => 'Your password was reset',
            'message' => 'Super Admin set a temporary password for your account. Change it from Change Password.', 'link' => '/change-password',
        ]);
        $this->done('Password reset for "' . $u['name'] . '". They must change it on next login.', '/admin/users');
    }

    public function impersonate(): void
    {
        $u = User::find($this->id());
        $admin = Auth::user();
        if (!$u || !$u['is_active'] || $u['panel'] === 'SUPER_ADMIN' || Auth::impersonator()) {
            $this->fail('You cannot login as this user.');
        }
        Audit::log('USER', $u['user_id'], 'IMPERSONATION_STARTED', null, ['target' => $u['username']]);
        Auth::impersonate($u, $admin);
        $role = $u['role_id'] ? Role::find($u['role_id']) : null;
        $this->done('You are now logged in as ' . $u['name'] . ' (' . \App\Core\Lookups::PANELS[$u['panel']] . ($role ? ' · ' . $role['role_name'] : '') . '). Use "Back to Admin" to return.',
            AuthController::HOME[$u['panel']], 'info');
    }

    /** Any panel: return from "Login as" to the Super Admin's own panel. */
    public function stopImpersonation(): void
    {
        $imp = Auth::impersonator();
        $left = Auth::user();
        if (!$imp) {
            $this->fail('You are not logged in as another user.');
        }
        Auth::stopImpersonation();
        Audit::log('USER', $left['user_id'] ?? null, 'IMPERSONATION_ENDED', null, null, null, ['user_id' => $imp['user_id'], 'name' => $imp['name'], 'designation' => $imp['designation']]);
        $this->done('Back to Super Admin. You left ' . ($left['name'] ?? 'the user') . "'s panel.", '/admin/users');
    }

    private function activeAdmins(int $exceptId): int
    {
        return User::count(['panel' => 'SUPER_ADMIN', 'is_active' => 1, 'user_id <>' => $exceptId]);
    }
}
