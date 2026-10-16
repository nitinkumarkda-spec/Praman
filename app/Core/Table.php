<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Resolves logical table keys (config/tables.php) to real table names.
 * Unknown keys throw, so a typo can never reach the database as raw SQL.
 */
final class Table
{
    private const IDENT = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** @var array<string, string>|null key => prefixed real name */
    private static ?array $map = null;

    /** Real (prefixed) table name for a registry key. */
    public static function name(string $key): string
    {
        $map = self::map();
        if (!isset($map[$key])) {
            throw new InvalidArgumentException("Unknown table key: {$key}");
        }
        return $map[$key];
    }

    public static function has(string $key): bool
    {
        return isset(self::map()[$key]);
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::map();
    }

    /** Replace every {key} placeholder in a SQL string with its real table name. */
    public static function resolve(string $sql): string
    {
        if (!str_contains($sql, '{')) {
            return $sql;
        }
        return (string) preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static fn (array $m): string => self::name($m[1]),
            $sql
        );
    }

    /** @return array<string, string> */
    private static function map(): array
    {
        if (self::$map === null) {
            $prefix = (string) Config::get('tables.prefix', '');
            $map = [];
            foreach ((array) Config::get('tables.tables', []) as $key => $name) {
                $real = $prefix . $name;
                if (preg_match(self::IDENT, (string) $key) !== 1 || preg_match(self::IDENT, $real) !== 1) {
                    throw new InvalidArgumentException("Invalid table entry: {$key} => {$real}");
                }
                $map[(string) $key] = $real;
            }
            self::$map = $map;
        }
        return self::$map;
    }
}
