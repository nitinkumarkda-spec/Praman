<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Read-only counters for the panel dashboards.
 */
final class Dashboard extends Model
{
    public const PROPERTY_STATUSES = [
        'LISTED', 'UNDER_VERIFICATION', 'AUCTION_READY', 'NOT_READY',
        'SENT_FOR_AUCTION', 'SOLD', 'WITHHELD', 'ARCHIVED',
    ];

    /**
     * Super Admin counters: alias => [table key, where] or a custom sub-query.
     * Add a new card counter here; no SQL change needed.
     */
    private const ADMIN_COUNTERS = [
        'users_total'       => ['users', []],
        'users_active'      => ['users', ['is_active' => 1]],
        'users_locked'      => ['users', ['locked_until > NOW()']],
        'roles_total'       => ['roles', []],
        'roles_active'      => ['roles', ['is_active' => 1]],
        'schemes_total'     => ['schemes', []],
        'schemes_active'    => ['schemes', ['is_active' => 1]],
        'properties_total'  => ['properties', ['is_active' => 1]],
        'failed_logins_24h' => ['login_attempts', ['success' => 0, 'attempted_at > (NOW() - INTERVAL 1 DAY)']],
    ];

    private const DATA_ENTRY_COUNTERS = [
        'schemes_active'   => ['schemes', ['is_active' => 1]],
        'sectors_active'   => 'SELECT COUNT(*) FROM {scheme_sectors} s JOIN {schemes} sc ON sc.scheme_id = s.scheme_id
                                WHERE s.is_active = 1 AND sc.is_active = 1',
        'properties_total' => ['properties', ['is_active' => 1]],
        'my_properties'    => 'SELECT COUNT(*) FROM {properties} WHERE is_active = 1 AND created_by = :uid',
    ];

    private const OIC_COUNTERS = [
        'open_rounds'      => ['verification_rounds', ['round_status' => 'OPEN']],
        'pending_tasks'    => "SELECT COUNT(*) FROM {verification_tasks} t JOIN {verification_rounds} r ON r.round_id = t.round_id
                                WHERE r.round_status = 'OPEN' AND t.task_status = 'PENDING'",
        'rounds_ready'     => ['verification_rounds', ['round_status' => 'CLOSED', 'consolidated_result' => 'READY']],
        'rounds_not_ready' => ['verification_rounds', ['round_status' => 'CLOSED', 'consolidated_result' => 'NOT_READY']],
    ];

    public function adminCounts(): array
    {
        return $this->countMany(self::ADMIN_COUNTERS);
    }
    /** Users per panel, and role users per role. */
    public function usersByRole(): array
    {
        return $this->fetchAll(
            'SELECT r.role_name, r.is_active, COUNT(u.user_id) AS users_count,
                    SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) AS active_count
               FROM {roles} r LEFT JOIN {users} u ON u.role_id = r.role_id
              GROUP BY r.role_id, r.role_name, r.is_active, r.sort_order
              ORDER BY r.sort_order, r.role_name'
        );
    }

    /** property status => count (all statuses present, zero-filled). */
    public function propertyStatusCounts(?int $createdBy = null): array
    {
        $sql = 'SELECT current_status, COUNT(*) AS c FROM {properties} WHERE is_active = 1';
        $params = [];
        if ($createdBy !== null) {
            $sql .= ' AND created_by = :uid';
            $params['uid'] = $createdBy;
        }
        $rows = $this->fetchAll($sql . ' GROUP BY current_status', $params);
        $out = array_fill_keys(self::PROPERTY_STATUSES, 0);
        foreach ($rows as $row) {
            $out[$row['current_status']] = (int) $row['c'];
        }
        return $out;
    }

    public function dataEntryCounts(int $userId): array
    {
        return $this->countMany(self::DATA_ENTRY_COUNTERS, ['uid' => $userId]);
    }

    public function oicCounts(): array
    {
        return $this->countMany(self::OIC_COUNTERS);
    }

    /** Recent audit entries (optionally for one user). */
    public function recentActivity(int $limit = 8, ?int $userId = null): array
    {
        $sql = 'SELECT action, entity_type, entity_id, performed_by_name, performed_at, ip_address FROM {audit_logs}';
        $params = ['lim' => $limit];
        if ($userId !== null) {
            $sql .= ' WHERE performed_by = :uid';
            $params['uid'] = $userId;
        }
        return $this->fetchAll($sql . ' ORDER BY log_id DESC LIMIT :lim', $params);
    }
}
