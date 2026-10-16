<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Hardened PHP session: strict mode, httponly, SameSite=Strict, idle + absolute timeout,
 * user-agent binding, flash messages.
 */
final class Session
{
    private const FLASH_NEW = '_flash_new';
    private const FLASH_OLD = '_flash_old';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $savePath = (string) Config::get('session.save_path');
        if ($savePath !== '') {
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0700, true);
            }
            session_save_path($savePath);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) Config::get('session.absolute_timeout', 28800));

        session_name((string) Config::get('session.name', 'PRAMAN_SID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => (Request::basePath() ?: '') . '/',
            'secure'   => Request::current()->isSecure(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();

        self::enforceTimeouts();
        self::ageFlash();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** New session id, keeps data. Call on login / privilege change. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => 'Strict',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Flash value available on the NEXT request only. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH_NEW][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::FLASH_OLD][$key] ?? $default;
    }

    private static function ageFlash(): void
    {
        $_SESSION[self::FLASH_OLD] = $_SESSION[self::FLASH_NEW] ?? [];
        $_SESSION[self::FLASH_NEW] = [];
    }

    private static function enforceTimeouts(): void
    {
        $now = time();
        $ua = hash('sha256', Request::current()->userAgent());

        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = $now;
            $_SESSION['_ua'] = $ua;
        }

        $idle = (int) Config::get('session.idle_timeout', 1800);
        $absolute = (int) Config::get('session.absolute_timeout', 28800);
        $expired = (isset($_SESSION['_last']) && ($now - (int) $_SESSION['_last']) > $idle)
            || (($now - (int) $_SESSION['_created']) > $absolute)
            || (isset($_SESSION['_ua']) && !hash_equals((string) $_SESSION['_ua'], $ua));

        if ($expired && isset($_SESSION['auth_user_id'])) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_created'] = $now;
            $_SESSION['_ua'] = $ua;
            $_SESSION[self::FLASH_NEW]['warning'] = 'Session expired due to inactivity. Please login again.';
        }
        $_SESSION['_last'] = $now;
    }
}
