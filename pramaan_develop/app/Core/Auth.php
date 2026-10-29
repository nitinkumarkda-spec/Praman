<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Role;
use App\Models\User;

/**
 * Logged-in user (server session), including Super Admin "login as user".
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            $u = $id ? User::find((int) $id) : null;
            if ($id && (!$u || !(int) $u['is_active'])) {
                self::logout();
                $u = null;
            }
            if ($u) {
                $role = $u['role_id'] ? Role::find($u['role_id']) : null;
                $u['role_name'] = $role['role_name'] ?? null;
            }
            self::$user = $u;
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::user()['user_id'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function panel(): ?string
    {
        return self::user()['panel'] ?? null;
    }

    /** The Super Admin who opened this panel via "Login as", or null. */
    public static function impersonator(): ?array
    {
        $id = Session::get('impersonator_id');
        return $id ? User::find((int) $id) : null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['user_id']);
        Session::forget('impersonator_id');
        Csrf::rotate();
        self::refresh();
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        Session::forget('impersonator_id');
        Session::regenerate();
        Csrf::rotate();
        self::$user = null;
        self::$loaded = true;
    }

    public static function impersonate(array $target, array $admin): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $target['user_id']);
        Session::set('impersonator_id', (int) $admin['user_id']);
        self::refresh();
    }

    public static function stopImpersonation(): void
    {
        $adminId = Session::get('impersonator_id');
        Session::regenerate();
        Session::set('user_id', (int) $adminId);
        Session::forget('impersonator_id');
        self::refresh();
    }

    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }

    /** Who is acting, for the audit trail: name gets the admin's name while impersonating. */
    public static function actor(): array
    {
        $u = self::user();
        if (!$u) {
            return ['user_id' => null, 'name' => 'System', 'designation' => null, 'impersonator_id' => null];
        }
        $imp = self::impersonator();
        return [
            'user_id'         => (int) $u['user_id'],
            'name'            => $imp ? mb_substr($u['name'] . ' [by Super Admin: ' . $imp['name'] . ']', 0, 120) : $u['name'],
            'designation'     => $u['designation'],
            'impersonator_id' => $imp ? (int) $imp['user_id'] : null,
        ];
    }
}
