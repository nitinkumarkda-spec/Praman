<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Single shared PDO connection. Real prepared statements only (emulation off).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                (string) Config::get('database.host'),
                (int) Config::get('database.port'),
                (string) Config::get('database.database'),
                (string) Config::get('database.charset')
            );
            self::$pdo = new PDO($dsn, (string) Config::get('database.username'), (string) Config::get('database.password'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ]);
            $tz = (string) Config::get('database.timezone', '+05:30');
            if (preg_match('/^[+-]\d{2}:\d{2}$/', $tz) === 1) {
                self::$pdo->exec("SET time_zone = '" . $tz . "'");
            }
            $collation = (string) Config::get('database.collation');
            if (preg_match('/^[a-z0-9_]+$/', $collation) === 1) {
                self::$pdo->exec('SET NAMES ' . (string) Config::get('database.charset') . ' COLLATE ' . $collation);
            }
        }
        return self::$pdo;
    }

    /** Prepare SQL after resolving {table} placeholders (see config/tables.php). */
    public static function prepare(string $sql): PDOStatement
    {
        return self::connection()->prepare(Table::resolve($sql));
    }
}
