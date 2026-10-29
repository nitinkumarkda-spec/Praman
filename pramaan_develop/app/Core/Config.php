<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Loads config/*.php once and serves values by dot notation.
 */
final class Config
{
    private static array $items = [];

    public static function load(string $dir): void
    {
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $data = require $file;
            self::$items[$key] = is_array($data) ? $data : [];
        }
        // config/config.php sections are top-level keys (app, session, security …)
        if (isset(self::$items['config'])) {
            self::$items = array_replace(self::$items['config'], self::$items);
            unset(self::$items['config']);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $ref = &self::$items;
        foreach (explode('.', $key) as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }
}
