<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Synchroniser-token CSRF protection. One token per session, rotated on login/logout.
 * Forms send it as "_csrf"; fetch() sends it as the X-CSRF-Token header.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function rotate(): void
    {
        Session::set(self::KEY, bin2hex(random_bytes(32)));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(Request $request): bool
    {
        $sent = $request->input('_csrf') ?? $request->header('X-CSRF-Token') ?? '';
        $token = Session::get(self::KEY);
        return is_string($token) && $sent !== '' && hash_equals($token, $sent);
    }
}
