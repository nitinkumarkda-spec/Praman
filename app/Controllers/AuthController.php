<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', ['pageTitle' => 'Login'], 'layouts/auth');
    }

    public function login(): void
    {
        $data = $this->validate([
            'username' => 'required|max:50|alpha_dash',
            'password' => 'required|max:72',
        ], ['username' => 'Username', 'password' => 'Password'], '/login');

        $result = Auth::attempt((string) $data['username'], (string) $data['password']);
        if (!$result['ok']) {
            Session::flash('old', ['username' => $data['username']]);
            Session::flash('error', $result['message']);
            $this->redirect('/login');
        }
        $this->flash('success', $result['message']);
        $this->redirect(Auth::home());
    }

    public function logout(): void
    {
        Auth::logout();
        Session::start();
        Csrf::rotate();
        Session::flash('success', 'You have been logged out securely.');
        $this->redirect('/login');
    }

    /** End "Login as" and go back to the Super Admin account. */
    public function stopImpersonation(): void
    {
        if (!Auth::isImpersonating()) {
            $this->redirect(Auth::home());
        }
        $name = (string) (Auth::user()['name'] ?? '');
        $to = Auth::stopImpersonation();
        $this->flash('success', $to === '/login' ? 'Your admin account is no longer active. Please login again.' : "Back to Super Admin. You left {$name}'s panel.");
        $this->redirect($to);
    }

    public function showChangePassword(): void
    {
        $this->view('auth/change_password', [
            'pageTitle' => 'Change Password',
            'forced'    => \App\Core\Config::get('security.force_password_change') === true && (int) (Auth::user()['must_change_password'] ?? 0) === 1,
        ]);
    }

    public function changePassword(): void
    {
        if (Auth::isImpersonating()) {
            $this->flash('error', 'Password cannot be changed while you are logged in as another user. Use User Master → Reset password instead.');
            $this->redirect('/change-password');
        }
        $user = Auth::user();
        $data = $this->validate([
            'current_password' => 'required|max:72',
            'new_password'     => 'required|password',
            'confirm_password' => 'required|same:new_password',
        ], [
            'current_password' => 'Current password',
            'new_password'     => 'New password',
            'confirm_password' => 'Confirm password',
        ], '/change-password');

        $model = new User();
        $row = $model->find((int) $user['user_id']);
        $v = Validator::make([], []);
        if ($row === null || !password_verify((string) $data['current_password'], (string) $row['password_hash'])) {
            $v->addError('current_password', 'Current password is incorrect.');
        } elseif (hash_equals((string) $data['current_password'], (string) $data['new_password'])) {
            $v->addError('new_password', 'New password must be different from the current password.');
        } elseif (stripos((string) $data['new_password'], (string) $user['username']) !== false) {
            $v->addError('new_password', 'New password must not contain your username.');
        }
        if ($v->fails()) {
            $this->failValidation($v->errors(), '/change-password');
        }

        $model->changePassword((int) $user['user_id'], (string) $data['new_password']);
        Session::regenerate();
        Csrf::rotate();
        Auth::refresh();
        Audit::log('USER', (int) $user['user_id'], 'PASSWORD_CHANGED');

        $this->flash('success', 'Password changed successfully.');
        $this->redirect(Auth::home());
    }
}
