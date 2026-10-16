<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;
use Throwable;

/**
 * Scheme Master with its sectors and scheme-level role → user mapping.
 */
final class Scheme extends Model
{
    public const SORTABLE = [
        'id'         => 's.scheme_id',
        'name'       => 's.scheme_name',
        'code'       => 's.scheme_code',
        'type'       => 's.scheme_type',
        'zone'       => 's.zone',
        'year'       => 's.year_of_scheme',
        'sectors'    => 'sector_count',
        'properties' => 'property_count',
    ];

    public function paginateList(Paginator $p): Paginator
    {
        $from = ' FROM {schemes} s
                  LEFT JOIN (SELECT scheme_id, COUNT(*) AS c FROM {scheme_sectors} WHERE is_active = 1 GROUP BY scheme_id) sc ON sc.scheme_id = s.scheme_id
                  LEFT JOIN (SELECT scheme_id, COUNT(*) AS c FROM {properties} WHERE is_active = 1 GROUP BY scheme_id) pc ON pc.scheme_id = s.scheme_id
                  LEFT JOIN (SELECT scheme_id, COUNT(DISTINCT role_id) AS roles, COUNT(*) AS users FROM {scheme_role_users} WHERE is_active = 1 GROUP BY scheme_id) mc
                         ON mc.scheme_id = s.scheme_id
                 WHERE 1 = 1';
        $params = [];
        if ($p->search !== '') {
            $from .= ' AND (s.scheme_name LIKE :q1 OR s.scheme_code LIKE :q2 OR s.zone LIKE :q3)';
            $like = self::like($p->search);
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $type = $p->filter('type');
        if ($type !== null && array_key_exists($type, \App\Core\Lookups::SCHEME_TYPES)) {
            $from .= ' AND s.scheme_type = :type';
            $params['type'] = $type;
        }
        $status = $p->filter('status');
        if ($status === 'active' || $status === 'inactive') {
            $from .= ' AND s.is_active = :st';
            $params['st'] = $status === 'active' ? 1 : 0;
        }
        return $this->paginate(
            $p,
            'SELECT s.*, COALESCE(sc.c, 0) AS sector_count, COALESCE(pc.c, 0) AS property_count,
                    COALESCE(mc.roles, 0) AS mapped_roles, COALESCE(mc.users, 0) AS mapped_users,
                    (SELECT GROUP_CONCAT(ss.sector_name ORDER BY ss.sort_order, ss.sector_name SEPARATOR \', \')
                       FROM {scheme_sectors} ss WHERE ss.scheme_id = s.scheme_id AND ss.is_active = 1) AS sector_names',
            $from,
            $params
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch('SELECT * FROM {schemes} WHERE scheme_id = :id', ['id' => $id]);
    }

    public function sectors(int $schemeId, bool $activeOnly = false): array
    {
        return $this->fetchAll(
            'SELECT s.*, (SELECT COUNT(*) FROM {properties} p WHERE p.sector_id = s.sector_id AND p.is_active = 1) AS property_count
               FROM {scheme_sectors} s WHERE s.scheme_id = :id' . ($activeOnly ? ' AND s.is_active = 1' : '') . '
              ORDER BY s.sort_order, s.sector_name',
            ['id' => $schemeId]
        );
    }

    /** role_id => [user_id, ...] of active mappings. */
    public function mapping(int $schemeId): array
    {
        $out = [];
        foreach ($this->fetchAll('SELECT role_id, user_id FROM {scheme_role_users} WHERE scheme_id = :id AND is_active = 1', ['id' => $schemeId]) as $r) {
            $out[(int) $r['role_id']][] = (int) $r['user_id'];
        }
        return $out;
    }

    /** Mapping with names for display: [{role_name, users: "A, B"}] */
    public function mappingSummary(int $schemeId): array
    {
        return $this->fetchAll(
            'SELECT r.role_id, r.role_name, r.is_mandatory, r.is_active AS role_active,
                    GROUP_CONCAT(CONCAT(u.name, IF(u.is_active = 1, \'\', \' (inactive)\')) ORDER BY u.name SEPARATOR \', \') AS users
               FROM {roles} r
               LEFT JOIN {scheme_role_users} m ON m.role_id = r.role_id AND m.scheme_id = :id AND m.is_active = 1
               LEFT JOIN {users} u ON u.user_id = m.user_id
              WHERE r.is_active = 1 OR m.id IS NOT NULL
              GROUP BY r.role_id, r.role_name, r.is_mandatory, r.is_active, r.sort_order
              ORDER BY r.sort_order, r.role_name',
            ['id' => $schemeId]
        );
    }

    /**
     * Active roles each with their active role-users — the options of the mapping form.
     * @return array<int, array{role: array, users: array}>
     */
    public function roleUserOptions(): array
    {
        $roles = $this->fetchAll('SELECT * FROM {roles} WHERE is_active = 1 ORDER BY sort_order, role_name');
        $users = $this->fetchAll(
            "SELECT user_id, name, designation, emp_code, role_id FROM {users}
              WHERE panel = 'ROLE_USER' AND is_active = 1 AND role_id IS NOT NULL ORDER BY name"
        );
        $out = [];
        foreach ($roles as $r) {
            $out[(int) $r['role_id']] = ['role' => $r, 'users' => []];
        }
        foreach ($users as $u) {
            if (isset($out[(int) $u['role_id']])) {
                $out[(int) $u['role_id']]['users'][] = $u;
            }
        }
        return $out;
    }

    /** Schemes usable for property entry: active, sectors marked done, ≥ 1 active sector. */
    public function forPropertyEntry(): array
    {
        return $this->fetchAll(
            'SELECT s.scheme_id, s.scheme_code, s.scheme_name, s.scheme_type
               FROM {schemes} s
              WHERE s.is_active = 1 AND s.sectors_complete = 1
                AND EXISTS (SELECT 1 FROM {scheme_sectors} ss WHERE ss.scheme_id = s.scheme_id AND ss.is_active = 1)
              ORDER BY s.scheme_name'
        );
    }

    /** All schemes as id => "name (code)" for list filters. */
    public function options(): array
    {
        $out = [];
        foreach ($this->fetchAll('SELECT scheme_id, scheme_name, scheme_code FROM {schemes} ORDER BY scheme_name') as $s) {
            $out[(int) $s['scheme_id']] = $s['scheme_name'] . ' (' . $s['scheme_code'] . ')';
        }
        return $out;
    }

    /** Sectors as id => name for list filters (one scheme). */
    public function sectorOptions(int $schemeId): array
    {
        $out = [];
        foreach ($this->sectors($schemeId) as $s) {
            $out[(int) $s['sector_id']] = $s['sector_name'] . ((int) $s['is_active'] === 1 ? '' : ' (inactive)');
        }
        return $out;
    }

    /**
     * @param string[] $newSectors
     * @param array<int, int[]> $mapping role_id => user_ids
     */
    public function create(array $d, array $newSectors, array $mapping, int $by): int
    {
        $this->beginTransaction();
        try {
            $id = $this->insert('schemes', [
                'scheme_code'      => $d['scheme_code'],
                'scheme_name'      => $d['scheme_name'],
                'scheme_type'      => $d['scheme_type'],
                'zone'             => $d['zone'],
                'year_of_scheme'   => (int) $d['year_of_scheme'],
                'sectors_complete' => 1,
                'is_active'        => 1,
                'created_by'       => $by,
            ]);
            $order = 0;
            foreach ($newSectors as $name) {
                $this->insert('scheme_sectors', ['scheme_id' => $id, 'sector_name' => $name, 'sort_order' => ++$order, 'created_by' => $by]);
            }
            $this->syncMapping($id, $mapping, $by);
            $this->commit();
            return $id;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<int, array{name: string, active: int}> $existingSectors sector_id => data
     * @param string[] $newSectors
     * @param array<int, int[]> $mapping
     */
    public function update(int $id, array $d, array $existingSectors, array $newSectors, array $mapping, int $by): void
    {
        $this->beginTransaction();
        try {
            $this->updateWhere('schemes', [
                'scheme_name'      => $d['scheme_name'],
                'scheme_type'      => $d['scheme_type'],
                'zone'             => $d['zone'],
                'year_of_scheme'   => (int) $d['year_of_scheme'],
                'sectors_complete' => 1,
                'updated_by'       => $by,
            ], 'scheme_id', $id);

            foreach ($existingSectors as $sectorId => $s) {
                $this->run(
                    'UPDATE {scheme_sectors} SET sector_name = :n, is_active = :a, updated_by = :by WHERE sector_id = :sid AND scheme_id = :scheme',
                    ['n' => $s['name'], 'a' => $s['active'], 'by' => $by, 'sid' => $sectorId, 'scheme' => $id]
                );
            }
            $order = (int) $this->fetchColumn('SELECT COALESCE(MAX(sort_order), 0) FROM {scheme_sectors} WHERE scheme_id = :id', ['id' => $id]);
            foreach ($newSectors as $name) {
                $this->insert('scheme_sectors', ['scheme_id' => $id, 'sector_name' => $name, 'sort_order' => ++$order, 'created_by' => $by]);
            }
            $this->syncMapping($id, $mapping, $by);
            $this->commit();
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function setActive(int $id, bool $active, int $by): void
    {
        $this->updateWhere('schemes', ['is_active' => $active ? 1 : 0, 'updated_by' => $by], 'scheme_id', $id);
    }

    /** Deactivate mappings not selected any more; insert / re-activate the selected ones. */
    private function syncMapping(int $schemeId, array $mapping, int $by): void
    {
        $this->run('UPDATE {scheme_role_users} SET is_active = 0, updated_by = :by WHERE scheme_id = :id AND is_active = 1', ['by' => $by, 'id' => $schemeId]);
        foreach ($mapping as $roleId => $userIds) {
            foreach ($userIds as $userId) {
                $this->run(
                    'INSERT INTO {scheme_role_users} (scheme_id, role_id, user_id, is_active, created_by)
                     VALUES (:s, :r, :u, 1, :by)
                     ON DUPLICATE KEY UPDATE is_active = 1, updated_by = :by2',
                    ['s' => $schemeId, 'r' => (int) $roleId, 'u' => (int) $userId, 'by' => $by, 'by2' => $by]
                );
            }
        }
    }
}
