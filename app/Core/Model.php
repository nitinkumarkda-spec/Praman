<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use PDOStatement;

/**
 * PDO base model. Every query goes through prepared statements.
 *
 * Table names are never hard-coded: SQL uses {key} placeholders from config/tables.php
 * (e.g. 'SELECT * FROM {users} u'), and the generic helpers take the same keys.
 */
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare(Table::resolve($sql));
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    protected function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    protected function fetchColumn(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** INSERT using a column => value map. Column names come from code, never from user input. */
    protected function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            Table::name($table),
            implode(', ', $cols),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols))
        );
        $this->run($sql, $data);
        return (int) $this->db->lastInsertId();
    }

    /** UPDATE by a single key column. Returns affected rows. */
    protected function updateWhere(string $table, array $data, string $keyColumn, int $keyValue): int
    {
        $sets = implode(', ', array_map(static fn ($c) => $c . ' = :' . $c, array_keys($data)));
        $data['__key'] = $keyValue;
        return $this->run(sprintf('UPDATE %s SET %s WHERE %s = :__key', Table::name($table), $sets, $keyColumn), $data)->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /** Case-insensitive uniqueness check (table/column names come from code only). */
    public function valueExists(string $table, string $column, string $value, string $idColumn, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM ' . Table::name($table) . " WHERE {$column} = :v";
        $params = ['v' => $value];
        if ($exceptId !== null) {
            $sql .= " AND {$idColumn} <> :id";
            $params['id'] = $exceptId;
        }
        return $this->fetchColumn($sql . ' LIMIT 1', $params) !== null;
    }

    /* ------------------------------------------------------------------
     * Generic table helpers. $table is a key from config/tables.php.
     *
     * $where format (all conditions are ANDed):
     *   'col' => value          col = :value   (null => IS NULL, array => IN (...))
     *   'col >=' => value       operators: = <> != < <= > >= LIKE, NOT LIKE
     *   'raw SQL condition'     int key; code-only text, e.g. 'locked_until > NOW()'
     * Column names and raw conditions must come from code, never from user input.
     * ------------------------------------------------------------------ */

    /** COUNT(*) of a table with optional conditions. */
    public function countRows(string $table, array $where = []): int
    {
        $params = [];
        $sql = 'SELECT COUNT(*) FROM ' . Table::name($table) . $this->buildWhere($where, $params);
        return (int) $this->fetchColumn($sql, $params);
    }

    /** True when at least one row matches. */
    public function rowExists(string $table, array $where): bool
    {
        $params = [];
        $sql = 'SELECT 1 FROM ' . Table::name($table) . $this->buildWhere($where, $params) . ' LIMIT 1';
        return $this->fetchColumn($sql, $params) !== null;
    }

    /** First matching row (or null). */
    public function findRow(string $table, array $where, string $columns = '*'): ?array
    {
        $params = [];
        $sql = 'SELECT ' . $columns . ' FROM ' . Table::name($table) . $this->buildWhere($where, $params) . ' LIMIT 1';
        return $this->fetch($sql, $params);
    }

    /** All matching rows, with optional ORDER BY and LIMIT. */
    public function findRows(string $table, array $where = [], string $orderBy = '', ?int $limit = null, string $columns = '*'): array
    {
        $params = [];
        $sql = 'SELECT ' . $columns . ' FROM ' . Table::name($table) . $this->buildWhere($where, $params);
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        if ($limit !== null) {
            $sql .= ' LIMIT :__lim';
            $params['__lim'] = $limit;
        }
        return $this->fetchAll($sql, $params);
    }

    /**
     * Several counters in ONE query. Each definition is either
     *   alias => [tableKey, $where]                     simple count
     *   alias => 'SELECT COUNT(*) FROM {t} JOIN ...'    custom sub-query (code only)
     * Extra named params for custom sub-queries go in $params.
     * Returns alias => int.
     */
    public function countMany(array $definitions, array $params = []): array
    {
        $parts = [];
        foreach ($definitions as $alias => $def) {
            self::assertIdent((string) $alias);
            $sub = is_string($def)
                ? $def
                : 'SELECT COUNT(*) FROM ' . Table::name((string) $def[0]) . $this->buildWhere($def[1] ?? [], $params);
            $parts[] = '(' . $sub . ') AS ' . $alias;
        }
        if ($parts === []) {
            return [];
        }
        $row = $this->fetch('SELECT ' . implode(', ', $parts), $params) ?? [];
        return array_map('intval', $row);
    }

    /** Builds " WHERE ..." (or '') from a $where map, adding bound values to $params. */
    protected function buildWhere(array $where, array &$params): string
    {
        $conds = [];
        foreach ($where as $key => $value) {
            if (is_int($key)) {
                $conds[] = '(' . $value . ')';
                continue;
            }
            $parts = preg_split('/\s+/', trim($key), 2);
            $col = $parts[0];
            $op = strtoupper($parts[1] ?? '=');
            self::assertIdent($col);
            if (!in_array($op, ['=', '<>', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'], true)) {
                throw new InvalidArgumentException("Unsupported operator: {$op}");
            }
            if ($value === null) {
                $conds[] = $col . ($op === '=' ? ' IS NULL' : ' IS NOT NULL');
                continue;
            }
            if (is_array($value)) {
                if ($value === []) {
                    $conds[] = $op === '=' ? '0 = 1' : '1 = 1';
                    continue;
                }
                $names = [];
                foreach (array_values($value) as $v) {
                    $names[] = $name = ':w' . count($params);
                    $params[substr($name, 1)] = $v;
                }
                $conds[] = $col . ($op === '=' ? ' IN (' : ' NOT IN (') . implode(', ', $names) . ')';
                continue;
            }
            $name = 'w' . count($params);
            $params[$name] = $value;
            $conds[] = $col . ' ' . $op . ' :' . $name;
        }
        return $conds === [] ? '' : ' WHERE ' . implode(' AND ', $conds);
    }

    /** Column/alias names: letters, digits, underscore, optional "alias." prefix. */
    private static function assertIdent(string $name): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid identifier: {$name}");
        }
    }

    /** Escape LIKE wildcards in user search text. */
    protected static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }

    /**
     * Runs COUNT + paged SELECT for a Paginator.
     * $select: "SELECT a, b"   $from: "FROM t JOIN ... WHERE ..." (with named params)
     */
    protected function paginate(Paginator $p, string $select, string $from, array $params = []): Paginator
    {
        $total = (int) $this->fetchColumn('SELECT COUNT(*) ' . $from, $params);
        $p->setTotal($total);
        $sql = $select . ' ' . $from . ' ORDER BY ' . $p->orderBy() . ' LIMIT :__limit OFFSET :__offset';
        $params['__limit'] = $p->perPage;
        $params['__offset'] = $p->offset();
        $p->setItems($this->fetchAll($sql, $params));
        return $p;
    }
}
