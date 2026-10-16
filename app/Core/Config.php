<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Read-only access to config/*.php using dot notation: Config::get('app.name'), Config::get('tables.prefix').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(string $configDir): void
    {
        self::$items['config'] = require $configDir . '/config.php';
        self::$items['database'] = require $configDir . '/database.php';
        self::$items['tables'] = require $configDir . '/tables.php';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        // "database.*" / "tables.*" live in their own files, everything else in config.php
        $file = in_array($segments[0], ['database', 'tables'], true) ? array_shift($segments) : 'config';
        $value = self::$items[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
