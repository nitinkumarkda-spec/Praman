<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Password;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\Audit;
use App\Services\Notify;

/**
 * Sign in / sign out / change password.
 */
final class AuthController extends Controller
{
    public const HOME = [
        'SUPER_ADMIN' => '/admin/dashboard', 'DATA_ENTRY' => '/data-entry/dashboard',
        'OIC' => '/oic/dashboard', 'ROLE_USER' => '/role/dashboard',
    ];

    public function login(): void
    {
        $username = strtolower($this->request->str('username'));
        $password = (string) $this->request->input('password', '');
        if ($username === '' || !is_string($this->request->input('password'))) {
            $this->fail('Enter your username and password.', 'username');
        }
        if (mb_strlen($username) > 50 || strlen($password) > 72 || !preg_match('/^[a-z0-9_.-]+$/', $username)) {
            $this->fail('Invalid username or password.', 'password');
        }
        $ip = $this->request->ip();
        $window = (int) config('security.login_window_minutes', 15);
        $ipFails = LoginAttempt::count(['ip_address' => $ip, 'success' => 0, 'attempted_at >' => now(-$window)]);
        if ($ipFails >= (int) config('security.ip_max_attempts', 20)) {
            $this->record($username, $ip, false);
            $this->fail('Too many failed logins from this network. Please wait ' . $window . ' minutes and try again.', 'password', 429);
        }

        $u = User::first(['username' => $username]);
        // unknown user: verify against a dummy hash of a random secret, so timing does not reveal valid usernames
        $hash = $u['password_hash'] ?? '$argon2id$v=19$m=65536,t=3,p=1$UkZDL2QxN2JXdTRTZHM0Uw$efTS3olo4mdxitZhrjDUKb3vDZUtBhCPYwFgtFBfVIo';
        $valid = Password::verify($password, $hash); // always run: same timing for unknown users

        if ($u && $u['locked_until'] && $u['locked_until'] > now()) {
            $this->record($username, $ip, false);
            $mins = max(1, (int) ceil((strtotime($u['locked_until']) - time()) / 60));
            $this->fail('Account is temporarily locked due to repeated failed logins. Try again in ' . $mins . ' minute(s).', 'password', 423);
        }
        if (!$u || !$valid) {
            $this->record($username, $ip, false);
            if ($u) {
                $this->countFailure($u);
            }
            $this->fail('Invalid username or password.', 'password');
        }
        if (!$u['is_active']) {
            $this->record($username, $ip, false);
            $this->fail('Your account is deactivated. Please contact the System Administrator.', 'password', 403);
        }

        $patch = ['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now(), 'last_login_ip' => $ip];
        if (Password::needsRehash($u['password_hash'])) {
            $patch['password_hash'] = Password::hash($password);
        }
        User::update($u['user_id'], $patch);
        $this->record($username, $ip, true);
        Auth::login($u);
        Audit::log('AUTH', $u['user_id'], 'LOGIN', null, ['message' => 'Login successful'], null, ['user_id' => $u['user_id'], 'name' => $u['name'], 'designation' => $u['designation']]);
        $this->done('Welcome, ' . $u['name'] . '.', self::HOME[$u['panel']]);
    }

    public function logout(): void
    {
        $u = Auth::user();
        if ($u) {
            Audit::log('AUTH', $u['user_id'], 'LOGOUT', null, ['message' => 'User logged out'], null, ['user_id' => $u['user_id'], 'name' => $u['name'], 'designation' => $u['designation']]);
        }
        Auth::logout();
        $this->done('You have been logged out securely.', '/login');
    }

    public function changePassword(): void
    {
        if (Auth::impersonator()) {
            $this->fail('Password cannot be changed while you are logged in as another user. Use User Master → Reset password instead.');
        }
        $d = $this->validate([
            'new_password'     => ['New password', 'required|password'],
            'confirm_password' => ['Confirm new password', 'required'],
        ]);
        if ($d['new_password'] !== $d['confirm_password']) {
            $this->fail('Confirm password must match the new password.', 'confirm_password');
        }
        $u = User::find(Auth::id());
        if (Password::verify($d['new_password'], $u['password_hash'])) {
            $this->fail('New password must be different from the current password.', 'new_password');
        }
        if (str_contains(strtolower($d['new_password']), strtolower($u['username']))) {
            $this->fail('New password must not contain your username.', 'new_password');
        }
        User::update($u['user_id'], ['password_hash' => Password::hash($d['new_password']), 'password_changed_at' => now(), 'must_change_password' => 0]);
        \App\Core\Session::regenerate();
        Audit::log('USER', $u['user_id'], 'PASSWORD_CHANGED', null, null);
        $this->done('Password changed successfully.', self::HOME[$u['panel']]);
    }

    private function record(string $username, string $ip, bool $ok): void
    {
        LoginAttempt::insert(['username' => mb_substr($username, 0, 50), 'ip_address' => $ip, 'success' => $ok ? 1 : 0, 'attempted_at' => now()]);
    }

    private function countFailure(array $u): void
    {
        $max = (int) config('security.login_max_attempts', 5);
        $lock = (int) config('security.lockout_minutes', 15);
        $f = (int) $u['failed_attempts'] + 1;
        $actor = ['user_id' => $u['user_id'], 'name' => $u['name'], 'designation' => $u['designation']];
        if ($f >= $max) {
            User::update($u['user_id'], ['failed_attempts' => 0, 'locked_until' => now($lock)]);
            Audit::log('USER', $u['user_id'], 'ACCOUNT_LOCKED', null, ['minutes' => $lock], null, $actor);
            Notify::send(Notify::panelUsers('SUPER_ADMIN'), [
                'type' => 'ACCOUNT', 'tone' => 'danger', 'icon' => 'fa-user-lock', 'title' => 'Account locked — ' . $u['username'],
                'message' => $u['name'] . ' was locked for ' . $lock . ' minutes after ' . $max . ' failed logins.', 'link' => '/admin/users?status=locked',
            ]);
        } else {
            User::update($u['user_id'], ['failed_attempts' => $f]);
            Notify::send(Notify::panelUsers('SUPER_ADMIN'), [
                'type' => 'ACCOUNT', 'tone' => 'warning', 'icon' => 'fa-user-shield', 'title' => 'Failed login attempt — ' . $u['username'],
                'message' => 'A wrong password was entered for ' . $u['username'] . '. The account locks after ' . $max . ' failed attempts.',
                'link' => '/admin/audit-logs?area=AUTH&quick=area:AUTH', 'ref' => 'FAILED-' . $u['user_id'] . '-' . today(),
            ]);
        }
    }
}
