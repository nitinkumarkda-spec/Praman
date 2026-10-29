<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Hardened PHP session: strict mode, HttpOnly + SameSite=Strict cookie,
 * idle / absolute timeout and user-agent binding.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $c = Config::get('session');
        $secure = Request::current()->isSecure();
        if (!is_dir($c['save_path'])) {
            mkdir($c['save_path'], 0700, true);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) $c['absolute_timeout']);
        session_save_path($c['save_path']);
        session_name($c['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => (Request::current()->basePath() ?: '') . '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();

        $t = time();
        $fp = hash('sha256', Request::current()->userAgent());
        if (isset($_SESSION['_started'])) {
            $expired = ($t - (int) ($_SESSION['_last'] ?? 0)) > (int) $c['idle_timeout']
                || ($t - (int) $_SESSION['_started']) > (int) $c['absolute_timeout'];
            if ($expired || !hash_equals((string) ($_SESSION['_fp'] ?? ''), $fp)) {
                $hadUser = isset($_SESSION['user_id']);
                self::destroy();
                session_start();
                if ($hadUser) {
                    $_SESSION['_flash'][] = ['type' => 'warning', 'message' => 'Your session expired. Please login again.'];
                }
            }
        }
        $_SESSION['_started'] ??= $t;
        $_SESSION['_fp'] ??= $fp;
        $_SESSION['_last'] = $t;
    }

    /** New session id (login, logout, privilege change) — keeps data. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_started'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Pull and clear the queued flash messages. */
    public static function takeFlashes(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
            session_destroy();
        }
    }
}
