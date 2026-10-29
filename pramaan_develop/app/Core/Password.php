<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Password policy + hashing (Argon2id when available, else bcrypt).
 */
final class Password
{
    public static function hash(string $plain): string
    {
        return password_hash($plain, self::algo(), self::options());
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algo(), self::options());
    }

    /** Null when the password meets the policy, else the message. */
    public static function problem(string $v): ?string
    {
        $min = (int) Config::get('security.password_min_length', 10);
        if (mb_strlen($v) < $min || strlen($v) > 72) {
            return 'Password must be ' . $min . '–72 characters long.';
        }
        if (!preg_match('/[A-Z]/', $v) || !preg_match('/[a-z]/', $v) || !preg_match('/\d/', $v) || !preg_match('/[^A-Za-z0-9]/', $v)) {
            return 'Password needs an uppercase letter, a lowercase letter, a digit and a special character.';
        }
        return null;
    }

    private static function algo(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    private static function options(): array
    {
        return defined('PASSWORD_ARGON2ID')
            ? ['memory_cost' => 1 << 16, 'time_cost' => 3, 'threads' => 1]
            : ['cost' => 12];
    }
}
