<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentication: login with rate limiting + lockout, session identity, logout.
 * The user row is re-read on every request so deactivation takes effect immediately.
 */
final class Auth
{
    public const PANELS = [
        'SUPER_ADMIN' => 'Super Admin',
        'DATA_ENTRY'  => 'Data Entry',
        'OIC'         => 'OIC Auction',
        'ROLE_USER'   => 'Role User',
    ];

    /** Home route of each panel. */
    public const HOME = [
        'SUPER_ADMIN' => '/admin/dashboard',
        'DATA_ENTRY'  => '/data-entry/dashboard',
        'OIC'         => '/oic/dashboard',
        'ROLE_USER'   => '/role/dashboard',
    ];

    private static ?array $user = null;
    private static bool $loaded = false;

    /**
     * @return array{ok: bool, message: string}
     */
    public static function attempt(string $username, string $password): array
    {
        $req = Request::current();
        $username = mb_strtolower(trim($username));
        $window = (int) Config::get('security.login_window_minutes', 15);

        // IP-wide throttle (credential stuffing across usernames)
        $stmt = Database::prepare('SELECT COUNT(*) FROM {login_attempts} WHERE ip_address = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)');
        $stmt->execute([$req->ip(), $window]);
        if ((int) $stmt->fetchColumn() >= (int) Config::get('security.ip_max_attempts', 20)) {
            self::recordAttempt($username, false);
            return ['ok' => false, 'message' => "Too many failed attempts from your network. Please try again after {$window} minutes."];
        }

        $stmt = Database::prepare('SELECT * FROM {users} WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch() ?: null;

        // Locked account (checked before password so a locked account cannot be brute-forced)
        if ($user !== null && $user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            self::recordAttempt($username, false);
            $mins = (int) ceil((strtotime((string) $user['locked_until']) - time()) / 60);
            return ['ok' => false, 'message' => "Account is temporarily locked due to repeated failed logins. Try again in {$mins} minute(s)."];
        }

        // Constant-time path: verify against a dummy hash when the user does not exist.
        $hash = $user['password_hash'] ?? '$2y$12$njqBaL.EUKw6SINTjKcbYuSwA5VIA2MKCn2rqJsAGLP6zNnpV87g2';
        $valid = password_verify($password, (string) $hash) && $user !== null;

        if (!$valid) {
            self::recordAttempt($username, false);
            if ($user !== null) {
                self::registerFailure((int) $user['user_id'], (int) $user['failed_attempts'] + 1);
            }
            return ['ok' => false, 'message' => 'Invalid username or password.'];
        }

        if ((int) $user['is_active'] !== 1) {
            self::recordAttempt($username, false);
            return ['ok' => false, 'message' => 'Your account is deactivated. Please contact the System Administrator.'];
        }

        if (!in_array($user['panel'], (array) Config::get('enabled_panels', []), true)) {
            self::recordAttempt($username, false);
            return ['ok' => false, 'message' => 'Your panel (' . (self::PANELS[$user['panel']] ?? $user['panel']) . ') is not enabled yet. Please contact the System Administrator.'];
        }

        // Transparent upgrade to the strongest available algorithm.
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $newHash = password_needs_rehash((string) $user['password_hash'], $algo) ? password_hash($password, $algo) : null;

        $sql = 'UPDATE {users} SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ?'
            . ($newHash !== null ? ', password_hash = ?' : '') . ' WHERE user_id = ?';
        $params = $newHash !== null ? [$req->ip(), $newHash, $user['user_id']] : [$req->ip(), $user['user_id']];
        Database::prepare($sql)->execute($params);

        self::recordAttempt($username, true);

        Session::regenerate();
        Csrf::rotate();
        Session::set('auth_user_id', (int) $user['user_id']);
        Session::set('auth_login_at', time());
        self::$loaded = false;

        Audit::log('USER', (int) $user['user_id'], 'LOGIN', null, ['panel' => $user['panel']]);
        return ['ok' => true, 'message' => 'Welcome, ' . $user['name'] . '!'];
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = Session::get('auth_user_id');
        if (!is_int($id)) {
            return self::$user = null;
        }
        $stmt = Database::prepare(
            'SELECT u.user_id, u.username, u.emp_code, u.name, u.designation, u.mobile, u.email, u.panel, u.role_id,
                    u.must_change_password, u.is_active, u.last_login_at, r.role_name
               FROM {users} u LEFT JOIN {roles} r ON r.role_id = u.role_id
              WHERE u.user_id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: null;
        if ($row === null || (int) $row['is_active'] !== 1 || !in_array($row['panel'], (array) Config::get('enabled_panels', []), true)) {
            Session::forget('auth_user_id');
            return self::$user = null;
        }
        return self::$user = $row;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return isset(self::user()['user_id']) ? (int) self::user()['user_id'] : null;
    }

    public static function panel(): ?string
    {
        return self::user()['panel'] ?? null;
    }

    public static function home(): string
    {
        return self::HOME[self::panel() ?? ''] ?? '/login';
    }

    public static function refresh(): void
    {
        self::$loaded = false;
    }

    // ------------------------------------------------------------------
    // Impersonation ("Login as") — Super Admin only, always reversible.
    // ------------------------------------------------------------------

    public static function isImpersonating(): bool
    {
        return is_int(Session::get('imp_admin_id'));
    }

    /** ['id' => int, 'name' => string] of the real Super Admin, or null. */
    public static function impersonator(): ?array
    {
        return self::isImpersonating()
            ? ['id' => (int) Session::get('imp_admin_id'), 'name' => (string) Session::get('imp_admin_name')]
            : null;
    }

    /** Switch the session to $target. Caller must have validated the target. */
    public static function startImpersonation(array $target, string $returnTo): void
    {
        $admin = self::user();
        Audit::log('USER', (int) $target['user_id'], 'IMPERSONATION_STARTED', null, [
            'target' => $target['username'], 'panel' => $target['panel'],
        ]);
        Session::regenerate();
        Csrf::rotate();
        Session::set('imp_admin_id', (int) $admin['user_id']);
        Session::set('imp_admin_name', (string) $admin['name']);
        Session::set('imp_return', $returnTo);
        Session::set('auth_user_id', (int) $target['user_id']);
        self::$loaded = false;
    }

    /** Return to the Super Admin account. Returns the URL to go back to. */
    public static function stopImpersonation(): string
    {
        $imp = self::impersonator();
        $return = (string) Session::get('imp_return', '/admin/users');
        $targetId = self::id();
        if ($imp === null) {
            return self::home();
        }
        // the original admin must still be an active Super Admin
        $stmt = Database::prepare("SELECT user_id FROM {users} WHERE user_id = ? AND panel = 'SUPER_ADMIN' AND is_active = 1");
        $stmt->execute([$imp['id']]);
        Session::forget('imp_admin_id');
        Session::forget('imp_admin_name');
        Session::forget('imp_return');
        if ($stmt->fetchColumn() === false) {
            self::logout();
            return '/login';
        }
        Session::regenerate();
        Csrf::rotate();
        Session::set('auth_user_id', $imp['id']);
        self::$loaded = false;
        Audit::log('USER', $targetId, 'IMPERSONATION_ENDED');
        return str_starts_with($return, '/admin/') ? $return : '/admin/users';
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('USER', self::id(), 'LOGOUT');
        }
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }

    private static function registerFailure(int $userId, int $failures): void
    {
        $max = (int) Config::get('security.login_max_attempts', 5);
        $lock = (int) Config::get('security.lockout_minutes', 15);
        if ($failures >= $max) {
            $stmt = Database::prepare('UPDATE {users} SET failed_attempts = 0, locked_until = (NOW() + INTERVAL ? MINUTE) WHERE user_id = ?');
            $stmt->execute([$lock, $userId]);
            Logger::warning('Account locked after failed logins', ['user_id' => $userId, 'ip' => Request::current()->ip()]);
            Audit::log('USER', $userId, 'ACCOUNT_LOCKED', null, ['minutes' => $lock]);
            return;
        }
        Database::prepare('UPDATE {users} SET failed_attempts = ? WHERE user_id = ?')->execute([$failures, $userId]);
    }

    private static function recordAttempt(string $username, bool $success): void
    {
        $req = Request::current();
        Database::prepare('INSERT INTO {login_attempts} (username, ip_address, success, user_agent) VALUES (?, ?, ?, ?)')
            ->execute([mb_substr($username, 0, 50), $req->ip(), $success ? 1 : 0, $req->userAgent()]);
    }
}
