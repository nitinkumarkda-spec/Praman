<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Resolves table keys from config/tables.php to real table names.
 *   Table::name('tasks')               → tasks
 *   Table::q('tasks')                  → `tasks`
 *   Table::sql('SELECT … FROM {tasks}') → SELECT … FROM `tasks`
 */
final class Table
{
    private static ?array $map = null;

    public static function all(): array
    {
        if (self::$map === null) {
            $map = Config::get('tables');
            if (!is_array($map) || !$map) {
                $map = require BASE_PATH . '/config/tables.php';
            }
            foreach ($map as $key => $name) {
                if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) $name)) {
                    throw new InvalidArgumentException('Invalid table name for "' . $key . '" in config/tables.php');
                }
            }
            self::$map = $map;
        }
        return self::$map;
    }

    public static function name(string $key): string
    {
        $map = self::all();
        if (!isset($map[$key])) {
            throw new InvalidArgumentException('Unknown table key: ' . $key . ' (add it to config/tables.php)');
        }
        return $map[$key];
    }

    public static function q(string $key): string
    {
        return '`' . self::name($key) . '`';
    }

    /** Replace every {key} placeholder with the quoted table name. */
    public static function sql(string $sql): string
    {
        if (!str_contains($sql, '{')) {
            return $sql;
        }
        return (string) preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', static fn ($m) => self::q($m[1]), $sql);
    }
}
