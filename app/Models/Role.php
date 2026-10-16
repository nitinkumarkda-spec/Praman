<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

/**
 * Role Master — each role is one verification section (Engineering, Legal, ...).
 */
final class Role extends Model
{
    public const SORTABLE = [
        'id'    => 'r.role_id',
        'name'  => 'r.role_name',
        'code'  => 'r.role_code',
        'sla'   => 'r.sla_days',
        'order' => 'r.sort_order',
        'users' => 'users_count',
    ];

    public function paginateList(Paginator $p): Paginator
    {
        $from = ' FROM {roles} r
                  LEFT JOIN (SELECT role_id, COUNT(*) AS c, SUM(is_active) AS a FROM {users} WHERE role_id IS NOT NULL GROUP BY role_id) uc
                         ON uc.role_id = r.role_id
                 WHERE 1 = 1';
        $params = [];
        if ($p->search !== '') {
            $from .= ' AND (r.role_name LIKE :q1 OR r.role_code LIKE :q2 OR r.role_name_hi LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = self::like($p->search);
        }
        $status = $p->filter('status');
        if ($status === 'active' || $status === 'inactive') {
            $from .= ' AND r.is_active = :st';
            $params['st'] = $status === 'active' ? 1 : 0;
        }
        return $this->paginate(
            $p,
            'SELECT r.*, COALESCE(uc.c, 0) AS users_count, COALESCE(uc.a, 0) AS active_users',
            $from,
            $params
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch('SELECT * FROM {roles} WHERE role_id = :id', ['id' => $id]);
    }

    /** Active roles ordered for forms. */
    public function active(): array
    {
        return $this->fetchAll('SELECT * FROM {roles} WHERE is_active = 1 ORDER BY sort_order, role_name');
    }

    /** All roles as id => name (filters). */
    public function options(): array
    {
        $out = [];
        foreach ($this->fetchAll('SELECT role_id, role_name, is_active FROM {roles} ORDER BY sort_order, role_name') as $r) {
            $out[(int) $r['role_id']] = $r['role_name'] . ((int) $r['is_active'] === 1 ? '' : ' (inactive)');
        }
        return $out;
    }

    public function create(array $d, int $by): int
    {
        return $this->insert('roles', [
            'role_code'    => $d['role_code'],
            'role_name'    => $d['role_name'],
            'role_name_hi' => $d['role_name_hi'] ?: null,
            'description'  => $d['description'] ?: null,
            'sla_days'     => (int) $d['sla_days'],
            'is_mandatory' => (int) $d['is_mandatory'],
            'sort_order'   => (int) $d['sort_order'],
            'is_active'    => (int) $d['is_active'],
            'created_by'   => $by,
        ]);
    }

    public function update(int $id, array $d, int $by): void
    {
        $this->updateWhere('roles', [
            'role_name'    => $d['role_name'],
            'role_name_hi' => $d['role_name_hi'] ?: null,
            'description'  => $d['description'] ?: null,
            'sla_days'     => (int) $d['sla_days'],
            'is_mandatory' => (int) $d['is_mandatory'],
            'sort_order'   => (int) $d['sort_order'],
            'is_active'    => (int) $d['is_active'],
            'updated_by'   => $by,
        ], 'role_id', $id);
    }

    public function setActive(int $id, bool $active, int $by): void
    {
        $this->updateWhere('roles', ['is_active' => $active ? 1 : 0, 'updated_by' => $by], 'role_id', $id);
    }

    /** Number of active scheme mappings that use this role (for warnings). */
    public function schemeUsage(int $id): int
    {
        return (int) $this->fetchColumn(
            'SELECT COUNT(DISTINCT scheme_id) FROM {scheme_role_users} WHERE role_id = :id AND is_active = 1',
            ['id' => $id]
        );
    }
}
