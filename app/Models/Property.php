<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Lookups;
use App\Core\Model;
use App\Core\Paginator;
use Throwable;

/**
 * Property Register + per-property role/user snapshot.
 */
final class Property extends Model
{
    public const SORTABLE = [
        'id'      => 'p.property_id',
        'code'    => 'p.property_code',
        'scheme'  => 'sc.scheme_name',
        'sector'  => 'se.sector_name',
        'plot'    => 'p.plot_no',
        'type'    => 'p.property_type',
        'area'    => 'p.area_sqm',
        'status'  => 'p.current_status',
        'created' => 'p.created_at',
    ];

    /** Shared list query for Data Entry and OIC. */
    public function paginateList(Paginator $p): Paginator
    {
        $from = ' FROM {properties} p
                  JOIN {schemes} sc ON sc.scheme_id = p.scheme_id
                  JOIN {scheme_sectors} se ON se.sector_id = p.sector_id
                  JOIN {users} cu ON cu.user_id = p.created_by
                  LEFT JOIN {verification_rounds} vr ON vr.round_id = p.current_round_id
                 WHERE p.is_active = 1';
        $params = [];
        if ($p->search !== '') {
            $from .= ' AND (p.plot_no LIKE :q1 OR p.property_code LIKE :q2 OR sc.scheme_name LIKE :q3 OR se.sector_name LIKE :q4)';
            $like = self::like($p->search);
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $scheme = $p->filter('scheme_id');
        if ($scheme !== null && ctype_digit($scheme)) {
            $from .= ' AND p.scheme_id = :scheme';
            $params['scheme'] = (int) $scheme;
        }
        $sector = $p->filter('sector_id');
        if ($sector !== null && ctype_digit($sector)) {
            $from .= ' AND p.sector_id = :sector';
            $params['sector'] = (int) $sector;
        }
        $status = $p->filter('status');
        if ($status !== null && array_key_exists($status, Lookups::PROPERTY_STATUSES)) {
            $from .= ' AND p.current_status = :status';
            $params['status'] = $status;
        }
        $type = $p->filter('type');
        if ($type !== null && array_key_exists($type, Lookups::PROPERTY_TYPES)) {
            $from .= ' AND p.property_type = :ptype';
            $params['ptype'] = $type;
        }
        return $this->paginate(
            $p,
            "SELECT p.*, sc.scheme_name, sc.scheme_code, se.sector_name, cu.name AS created_by_name,
                    vr.round_no, vr.round_status, vr.consolidated_result,
                    (SELECT GROUP_CONCAT(CONCAT(r.role_name, ': ', u.name) ORDER BY r.sort_order, u.name SEPARATOR ' | ')
                       FROM {property_role_users} pru
                       JOIN {roles} r ON r.role_id = pru.role_id
                       JOIN {users} u ON u.user_id = pru.user_id
                      WHERE pru.property_id = p.property_id AND pru.is_active = 1) AS role_users,
                    (SELECT COUNT(DISTINCT pru.role_id) FROM {property_role_users} pru
                      WHERE pru.property_id = p.property_id AND pru.is_active = 1) AS role_count,
                    (SELECT COUNT(*) FROM {verification_tasks} t WHERE t.round_id = p.current_round_id AND t.task_status = 'APPROVED') AS tasks_approved,
                    (SELECT COUNT(*) FROM {verification_tasks} t WHERE t.round_id = p.current_round_id) AS tasks_total",
            $from,
            $params
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch(
            'SELECT p.*, sc.scheme_name, sc.scheme_code, sc.scheme_type, sc.zone, se.sector_name, se.is_active AS sector_active,
                    cu.name AS created_by_name, uu.name AS updated_by_name
               FROM {properties} p
               JOIN {schemes} sc ON sc.scheme_id = p.scheme_id
               JOIN {scheme_sectors} se ON se.sector_id = p.sector_id
               JOIN {users} cu ON cu.user_id = p.created_by
               LEFT JOIN {users} uu ON uu.user_id = p.updated_by
              WHERE p.property_id = :id AND p.is_active = 1',
            ['id' => $id]
        );
    }

    public function duplicateExists(int $schemeId, int $sectorId, string $plotNo, string $type, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM {properties} WHERE scheme_id = :s AND sector_id = :se AND plot_no = :p AND property_type = :t';
        $params = ['s' => $schemeId, 'se' => $sectorId, 'p' => $plotNo, 't' => $type];
        if ($exceptId !== null) {
            $sql .= ' AND property_id <> :id';
            $params['id'] = $exceptId;
        }
        return $this->fetchColumn($sql . ' LIMIT 1', $params) !== null;
    }

    /** Sector belongs to scheme and is active. */
    public function sectorValid(int $schemeId, int $sectorId): bool
    {
        return $this->fetchColumn(
            'SELECT 1 FROM {scheme_sectors} WHERE sector_id = :se AND scheme_id = :s AND is_active = 1',
            ['se' => $sectorId, 's' => $schemeId]
        ) !== null;
    }

    /** Mandatory active roles that have NO active user mapped in the scheme (blocks property creation). */
    public function missingMandatoryRoles(int $schemeId): array
    {
        return array_column($this->fetchAll(
            "SELECT r.role_name FROM {roles} r
              WHERE r.is_active = 1 AND r.is_mandatory = 1
                AND NOT EXISTS (SELECT 1 FROM {scheme_role_users} m JOIN {users} u ON u.user_id = m.user_id
                                 WHERE m.scheme_id = :s AND m.role_id = r.role_id AND m.is_active = 1
                                   AND u.is_active = 1 AND u.panel = 'ROLE_USER' AND u.role_id = r.role_id)
              ORDER BY r.sort_order",
            ['s' => $schemeId]
        ), 'role_name');
    }

    /** Create the property and snapshot the scheme's role → user mapping. Returns [id, snapshotCount]. */
    public function create(array $d, int $by): array
    {
        $this->beginTransaction();
        try {
            $id = $this->insert('properties', [
                'scheme_id'     => (int) $d['scheme_id'],
                'sector_id'     => (int) $d['sector_id'],
                'plot_no'       => $d['plot_no'],
                'property_type' => $d['property_type'],
                'area_value'    => $d['area_value'],
                'area_unit'     => $d['area_unit'],
                'area_sqm'      => $d['area_sqm'],
                'remarks'       => $d['remarks'] ?: null,
                'current_status'=> 'LISTED',
                'created_by'    => $by,
            ]);
            $this->run('UPDATE {properties} SET property_code = :c WHERE property_id = :id', [
                'c'  => 'PRM-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'id' => $id,
            ]);
            $snap = $this->run(
                "INSERT INTO {property_role_users} (property_id, role_id, user_id, created_by)
                 SELECT :pid, m.role_id, m.user_id, :by
                   FROM {scheme_role_users} m
                   JOIN {roles} r ON r.role_id = m.role_id AND r.is_active = 1
                   JOIN {users} u ON u.user_id = m.user_id AND u.is_active = 1 AND u.panel = 'ROLE_USER' AND u.role_id = m.role_id
                  WHERE m.scheme_id = :sid AND m.is_active = 1",
                ['pid' => $id, 'by' => $by, 'sid' => (int) $d['scheme_id']]
            )->rowCount();
            $this->commit();
            return [$id, $snap];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /** Edit allowed only while LISTED. Returns false if the status changed meanwhile. */
    public function update(int $id, array $d, int $by): bool
    {
        return $this->run(
            "UPDATE {properties} SET sector_id = :se, plot_no = :p, property_type = :t, area_value = :av, area_unit = :au,
                    area_sqm = :sqm, remarks = :rm, updated_by = :by
              WHERE property_id = :id AND current_status = 'LISTED'",
            [
                'se' => (int) $d['sector_id'], 'p' => $d['plot_no'], 't' => $d['property_type'],
                'av' => $d['area_value'], 'au' => $d['area_unit'], 'sqm' => $d['area_sqm'],
                'rm' => $d['remarks'] ?: null, 'by' => $by, 'id' => $id,
            ]
        )->rowCount() > 0 || $this->fetchColumn("SELECT current_status FROM {properties} WHERE property_id = :id", ['id' => $id]) === 'LISTED';
    }

    /** Snapshot rows: role → users of one property. */
    public function roleUsers(int $propertyId): array
    {
        return $this->fetchAll(
            'SELECT r.role_id, r.role_name, r.is_mandatory, r.is_active AS role_active, u.user_id, u.name, u.designation, u.is_active AS user_active
               FROM {property_role_users} pru
               JOIN {roles} r ON r.role_id = pru.role_id
               JOIN {users} u ON u.user_id = pru.user_id
              WHERE pru.property_id = :id AND pru.is_active = 1
              ORDER BY r.sort_order, r.role_name, u.name',
            ['id' => $propertyId]
        );
    }
}
