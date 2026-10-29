<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Table;
use PDO;
use Throwable;

/**
 * database/schema.sql helpers for the installer and the migration:
 * table names come from config/tables.php and the database name from .env (DB_DATABASE),
 * and the indexes of an existing database are brought in line with the schema.
 */
final class SchemaTool
{
    /** schema.sql split into statements, comments removed, names resolved. */
    public static function statements(): array
    {
        $sql = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
        $sql = (string) preg_replace('/(^|\s)--\s.*$/m', '$1', $sql);
        $db = '`' . str_replace('`', '', (string) Config::get('database.name')) . '`';
        $sql = (string) preg_replace('/\b(DATABASE(?: IF EXISTS)?|USE)\s+pramaan_db\b/', '$1 ' . $db, $sql);
        $map = Table::all();
        $sql = (string) preg_replace_callback('/\b(CREATE TABLE|REFERENCES|ALTER TABLE)\s+([a-z_]+)\b/', static function ($m) use ($map) {
            return $m[1] . ' ' . (isset($map[$m[2]]) ? '`' . $map[$m[2]] . '`' : $m[2]);
        }, $sql);
        return array_values(array_filter(array_map('trim', explode(';', $sql))));
    }

    /** CREATE TABLE statements by table key. */
    public static function tables(): array
    {
        $out = [];
        $keys = array_flip(Table::all());
        foreach (self::statements() as $st) {
            if (preg_match('/^CREATE TABLE `?([a-z_]+)`?\s*\(/', $st, $m)) {
                $out[$keys[$m[1]] ?? $m[1]] = $st;
            }
        }
        return $out;
    }

    /**
     * Add missing indexes and rebuild changed ones (same name, different columns).
     * Drop + add run in one ALTER, so an index a foreign key needs is never missing.
     * @return string[] what was done
     */
    public static function syncIndexes(PDO $pdo): array
    {
        $log = [];
        foreach (self::tables() as $key => $create) {
            $table = Table::name($key);
            preg_match_all('/^\s*(UNIQUE )?KEY (\w+) \(([^)]+)\)/m', $create, $m, PREG_SET_ORDER);
            $have = [];
            foreach ($pdo->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $have[$r['Key_name']][(int) $r['Seq_in_index']] = $r['Column_name'];
            }
            foreach ($m as [, $unique, $name, $cols]) {
                $want = array_map(static fn ($c) => trim($c, " `"), explode(',', $cols));
                $add = 'ADD ' . ($unique ? 'UNIQUE ' : '') . 'INDEX `' . $name . '` (`' . implode('`, `', $want) . '`)';
                if (!isset($have[$name])) {
                    $sql = 'ALTER TABLE `' . $table . '` ' . $add;
                    $what = "+ {$table}.{$name} (" . implode(', ', $want) . ')';
                } else {
                    ksort($have[$name]);
                    if (array_values($have[$name]) === $want) {
                        continue;
                    }
                    $sql = 'ALTER TABLE `' . $table . '` DROP INDEX `' . $name . '`, ' . $add;
                    $what = "~ {$table}.{$name} (" . implode(', ', $want) . ')';
                }
                try {
                    $pdo->exec($sql);
                    $log[] = $what;
                } catch (Throwable $e) {
                    $log[] = "! {$table}.{$name}: " . $e->getMessage();
                }
            }
        }
        return $log;
    }
}
