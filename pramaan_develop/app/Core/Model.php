<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Base model (table gateway) — every common query lives here, written once.
 *
 *   $table     key from config/tables.php (the real name is resolved there)
 *   $columns   writable columns (whitelist: no user input ever becomes an identifier)
 *
 * Conditions ($where) are column => value:
 *   ['is_active' => 1]                 col = ?
 *   ['role_id' => [1, 2]]              col IN (?, ?)      ([] matches nothing)
 *   ['locked_until' => null]           col IS NULL
 *   ['deleted_at !=' => null]          col IS NOT NULL
 *   ['user_id <>' => 5]                operators: = != <> < <= > >= LIKE
 * Order: "sort_order, name DESC" (known columns only).
 *
 * Queries that need joins live as named methods in the model they belong to,
 * using {table_key} placeholders (Database resolves them through Table::sql()).
 */
abstract class Model
{
    /** Table key (config/tables.php). */
    protected static string $table;
    /** Primary key column. */
    protected static string $pk;
    /** Writable columns. */
    protected static array $columns = [];
    /** created_at / updated_at maintained by the model. */
    protected static bool $timestamps = true;
    /** Table has created_at only (no updated_at). */
    protected static bool $createdOnly = false;
    /** Integer columns cast on read (ids, flags, counters). */
    protected static array $ints = [];
    /** Decimal columns cast to float on read. */
    protected static array $floats = [];

    private const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE'];

    /** Real table name. */
    public static function table(): string
    {
        return Table::name(static::$table);
    }

    /** Quoted table name for SQL. */
    protected static function q(): string
    {
        return Table::q(static::$table);
    }

    public static function pk(): string
    {
        return static::$pk;
    }

    /* ======================= reads ======================= */

    public static function find(int|string|null $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }
        $row = Database::first('SELECT * FROM ' . static::q() . ' WHERE `' . static::$pk . '` = ? LIMIT 1', [is_numeric($id) ? (int) $id : $id]);
        return $row ? static::cast($row) : null;
    }

    /** Lock one row until the transaction ends (SELECT … FOR UPDATE). */
    public static function findForUpdate(int $id): ?array
    {
        $row = Database::first('SELECT * FROM ' . static::q() . ' WHERE `' . static::$pk . '` = ? LIMIT 1 FOR UPDATE', [$id]);
        return $row ? static::cast($row) : null;
    }

    public static function where(array $where = [], string $order = '', ?int $limit = null): array
    {
        return static::select(['*'], $where, $order, $limit);
    }

    /** Only some columns: select(['user_id', 'name'], ['is_active' => 1], 'name'). */
    public static function select(array $cols, array $where = [], string $order = '', ?int $limit = null): array
    {
        if ($cols !== ['*']) {
            array_walk($cols, static fn ($c) => static::assertColumn($c));
        }
        [$sql, $params] = static::whereSql($where);
        $list = $cols === ['*'] ? '*' : '`' . implode('`, `', $cols) . '`';
        $rows = Database::all('SELECT ' . $list . ' FROM ' . static::q() . $sql . static::tail($order, $limit), $params);
        return array_map([static::class, 'cast'], $rows);
    }

    public static function first(array $where, string $order = ''): ?array
    {
        return static::where($where, $order, 1)[0] ?? null;
    }

    /** One column as a list: pluck('user_id', ['panel' => 'OIC']). */
    public static function pluck(string $col, array $where = [], string $order = ''): array
    {
        return array_column(static::select([$col], $where, $order), $col);
    }

    public static function count(array $where = []): int
    {
        [$sql, $params] = static::whereSql($where);
        return (int) Database::value('SELECT COUNT(*) FROM ' . static::q() . $sql, $params);
    }

    public static function exists(array $where): bool
    {
        [$sql, $params] = static::whereSql($where);
        return Database::value('SELECT 1 FROM ' . static::q() . $sql . ' LIMIT 1', $params) !== null;
    }

    /** Largest value of a numeric column (0 when no row). */
    public static function max(string $col, array $where = []): int
    {
        static::assertColumn($col);
        [$sql, $params] = static::whereSql($where);
        return (int) Database::value('SELECT COALESCE(MAX(`' . $col . '`), 0) FROM ' . static::q() . $sql, $params);
    }

    /** Next display order / number: max + 1. */
    public static function nextOrder(array $where = [], string $col = 'sort_order'): int
    {
        return static::max($col, $where) + 1;
    }

    /** Case-insensitive "value already used" check (except one row). */
    public static function valueExists(string $column, string $value, ?int $exceptId = null, array $scope = []): bool
    {
        static::assertColumn($column);
        [$sql, $params] = static::whereSql($scope);
        $sql .= ($sql ? ' AND ' : ' WHERE ') . 'LOWER(`' . $column . '`) = LOWER(?)';
        $params[] = trim($value);
        if ($exceptId !== null) {
            $sql .= ' AND `' . static::$pk . '` <> ?';
            $params[] = $exceptId;
        }
        return Database::value('SELECT 1 FROM ' . static::q() . $sql . ' LIMIT 1', $params) !== null;
    }

    /* ======================= writes ======================= */

    public static function insert(array $data): int
    {
        return static::doInsert('INSERT', $data);
    }

    /** Insert, silently skipping a duplicate of a unique key. Returns the new id or 0. */
    public static function insertIgnore(array $data): int
    {
        return static::doInsert('INSERT IGNORE', $data);
    }

    /** Insert, or update $updateCols when the unique / primary key already exists. */
    public static function upsert(array $data, array $updateCols): void
    {
        $data = static::stamp(static::only($data));
        $cols = array_keys($data);
        $set = implode(', ', array_map(static function ($c) {
            static::assertColumn($c);
            return '`' . $c . '` = VALUES(`' . $c . '`)';
        }, $updateCols));
        Database::run('INSERT INTO ' . static::q() . ' (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . $set, array_values($data));
    }

    public static function update(int|string $id, array $data): void
    {
        static::updateWhere($data, [static::$pk => $id]);
    }

    /** Update every row matching $where; returns the number of changed rows. */
    public static function updateWhere(array $data, array $where): int
    {
        $data = static::only($data);
        if (static::$timestamps && !array_key_exists('updated_at', $data)) {
            $data['updated_at'] = Clock::now();
        }
        if (!$data) {
            return 0;
        }
        [$sql, $params] = static::whereSql($where);
        $set = implode(', ', array_map(static fn ($c) => '`' . $c . '` = ?', array_keys($data)));
        return Database::run('UPDATE ' . static::q() . ' SET ' . $set . $sql, [...array_values($data), ...$params])->rowCount();
    }

    public static function delete(int|string $id): void
    {
        static::deleteWhere([static::$pk => $id]);
    }

    /** Delete every row matching $where (an empty $where is refused). */
    public static function deleteWhere(array $where): int
    {
        if (!$where) {
            throw new InvalidArgumentException('deleteWhere() needs a condition: ' . static::table());
        }
        [$sql, $params] = static::whereSql($where);
        return Database::run('DELETE FROM ' . static::q() . $sql, $params)->rowCount();
    }

    /* ======================= helpers ======================= */

    /** Columns that are numbers in every table. */
    private const INTS = ['sort_order', 'sla_days', 'round_no', 'ward_no', 'ac_no', 'year_of_scheme', 'failed_attempts',
        'size', 'success', 'must_change_password', 'entity_id'];
    private const FLOATS = ['area_value', 'area_sqm', 'recorded_value', 'difference', 'difference_pct'];

    /** Cast numeric columns so JSON gets real numbers (the UI compares with ===). */
    public static function cast(array $row): array
    {
        foreach ($row as $k => $v) {
            if ($v === null || !is_scalar($v)) {
                continue;
            }
            if ($k === static::$pk || str_ends_with($k, '_id') || str_ends_with($k, '_by') || str_starts_with($k, 'is_')
                || in_array($k, self::INTS, true) || in_array($k, static::$ints, true)) {
                $row[$k] = is_numeric($v) ? (int) $v : $v;
            } elseif (in_array($k, self::FLOATS, true) || in_array($k, static::$floats, true)) {
                $row[$k] = (float) $v;
            }
        }
        return $row;
    }

    /** Cast a list of rows from a custom query. */
    protected static function castAll(array $rows): array
    {
        return array_map([static::class, 'cast'], $rows);
    }

    private static function doInsert(string $verb, array $data): int
    {
        $data = static::stamp(static::only($data));
        $cols = array_keys($data);
        $st = Database::run($verb . ' INTO ' . static::q() . ' (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
        return $st->rowCount() ? Database::lastId() : 0;
    }

    private static function stamp(array $data): array
    {
        if ((static::$timestamps || static::$createdOnly) && !isset($data['created_at'])) {
            $data['created_at'] = Clock::now();
        }
        return $data;
    }

    protected static function only(array $data): array
    {
        $allowed = array_merge(static::$columns, ['created_at', 'updated_at']);
        foreach (array_keys($data) as $k) {
            if (!in_array($k, $allowed, true) && $k !== static::$pk) {
                throw new InvalidArgumentException('Column not writable: ' . static::table() . '.' . $k);
            }
        }
        return $data;
    }

    protected static function assertColumn(string $column): void
    {
        if ($column !== static::$pk && !in_array($column, static::$columns, true) && !in_array($column, ['created_at', 'updated_at'], true)) {
            throw new InvalidArgumentException('Unknown column: ' . static::table() . '.' . $column);
        }
    }

    private static function tail(string $order, ?int $limit): string
    {
        return ($order !== '' ? ' ORDER BY ' . static::orderSql($order) : '') . ($limit !== null ? ' LIMIT ' . max(0, $limit) : '');
    }

    /** @return array{0:string,1:array} */
    protected static function whereSql(array $where): array
    {
        if (!$where) {
            return ['', []];
        }
        $parts = [];
        $params = [];
        foreach ($where as $key => $val) {
            [$col, $op] = array_pad(preg_split('/\s+/', trim((string) $key), 2), 2, '=');
            $op = strtoupper($op);
            static::assertColumn($col);
            if (!in_array($op, self::OPERATORS, true)) {
                throw new InvalidArgumentException('Unknown operator: ' . $op);
            }
            if (is_array($val)) {
                if (!$val) {
                    $parts[] = $op === '=' ? '1 = 0' : '1 = 1';
                    continue;
                }
                $parts[] = '`' . $col . '` ' . ($op === '=' ? 'IN' : 'NOT IN') . ' (' . implode(',', array_fill(0, count($val), '?')) . ')';
                array_push($params, ...array_values($val));
            } elseif ($val === null) {
                $parts[] = '`' . $col . '` ' . ($op === '=' ? 'IS NULL' : 'IS NOT NULL');
            } else {
                $parts[] = '`' . $col . '` ' . $op . ' ?';
                $params[] = $val;
            }
        }
        return [' WHERE ' . implode(' AND ', $parts), $params];
    }

    /** "sort_order, name DESC" — only known columns allowed. */
    protected static function orderSql(string $order): string
    {
        $out = [];
        foreach (explode(',', $order) as $part) {
            $bits = preg_split('/\s+/', trim($part));
            static::assertColumn($bits[0]);
            $dir = strtoupper($bits[1] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $out[] = '`' . $bits[0] . '` ' . $dir;
        }
        return implode(', ', $out);
    }
}
