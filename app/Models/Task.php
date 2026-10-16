<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

/**
 * Verification tasks as seen by a Role User — only tasks the user is assigned to.
 *
 * view_status:
 *   PENDING       task pending and round still OPEN (user must act)
 *   APPROVED / REJECTED / NO_RESPONSE
 *   NOT_REQUIRED  task still pending but the round was already closed (another role rejected)
 */
final class Task extends Model
{
    public const SORTABLE = [
        'id'     => 't.task_id',
        'due'    => 't.sla_due_date',
        'sent'   => 'vr.initiated_at',
        'plot'   => 'p.plot_no',
        'scheme' => 'sc.scheme_name',
        'status' => 'view_status',
    ];

    private const VIEW_STATUS = "CASE WHEN t.task_status = 'PENDING' AND vr.round_status <> 'OPEN' THEN 'NOT_REQUIRED' ELSE t.task_status END";

    public function paginateForUser(Paginator $p, int $userId): Paginator
    {
        $from = ' FROM {verification_tasks} t
                  JOIN {verification_task_assignees} a ON a.task_id = t.task_id AND a.user_id = :uid
                  JOIN {verification_rounds} vr ON vr.round_id = t.round_id
                  JOIN {properties} p ON p.property_id = vr.property_id
                  JOIN {schemes} sc ON sc.scheme_id = p.scheme_id
                  JOIN {scheme_sectors} se ON se.sector_id = p.sector_id
                  JOIN {roles} r ON r.role_id = t.role_id
                  LEFT JOIN {users} ru ON ru.user_id = t.responded_by
                 WHERE (t.task_status = \'PENDING\' OR t.responded_by = :me)';
        // A task answered by another assignee of the same section disappears from this user's lists.
        $params = ['uid' => $userId, 'me' => $userId];

        if ($p->search !== '') {
            $from .= ' AND (p.plot_no LIKE :q1 OR p.property_code LIKE :q2 OR sc.scheme_name LIKE :q3 OR se.sector_name LIKE :q4)';
            $like = self::like($p->search);
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        switch ($p->filter('status')) {
            case 'pending':
                $from .= " AND t.task_status = 'PENDING' AND vr.round_status = 'OPEN'";
                break;
            case 'overdue':
                $from .= " AND t.task_status = 'PENDING' AND vr.round_status = 'OPEN' AND t.sla_due_date < CURDATE()";
                break;
            case 'approved':
                $from .= " AND t.task_status = 'APPROVED'";
                break;
            case 'rejected':
                $from .= " AND t.task_status = 'REJECTED'";
                break;
            case 'closed':
                $from .= " AND t.task_status = 'PENDING' AND vr.round_status <> 'OPEN'";
                break;
        }
        $scheme = $p->filter('scheme_id');
        if ($scheme !== null && ctype_digit($scheme)) {
            $from .= ' AND p.scheme_id = :scheme';
            $params['scheme'] = (int) $scheme;
        }

        return $this->paginate(
            $p,
            'SELECT t.task_id, t.task_status, t.remarks, t.responded_at, t.sla_due_date, t.is_simulated,
                    ' . self::VIEW_STATUS . ' AS view_status,
                    vr.round_no, vr.round_status, vr.initiated_at,
                    p.property_id, p.property_code, p.plot_no, p.property_type, p.area_value, p.area_unit, p.area_sqm,
                    sc.scheme_name, sc.scheme_code, se.sector_name, r.role_name, ru.name AS responded_by_name',
            $from,
            $params
        );
    }

    /** Dashboard counters for one user. */
    public function countsForUser(int $userId): array
    {
        return $this->fetch(
            "SELECT
               SUM(t.task_status = 'PENDING' AND vr.round_status = 'OPEN') AS pending,
               SUM(t.task_status = 'PENDING' AND vr.round_status = 'OPEN' AND t.sla_due_date < CURDATE()) AS overdue,
               SUM(t.task_status = 'APPROVED') AS approved,
               SUM(t.task_status = 'REJECTED') AS rejected,
               COUNT(*) AS total
               FROM {verification_tasks} t
               JOIN {verification_task_assignees} a ON a.task_id = t.task_id AND a.user_id = :uid
               JOIN {verification_rounds} vr ON vr.round_id = t.round_id
              WHERE (t.task_status = 'PENDING' OR t.responded_by = :me)",
            ['uid' => $userId, 'me' => $userId]
        ) ?? [];
    }

    /** Most urgent open tasks for the dashboard. */
    public function urgentForUser(int $userId, int $limit = 5): array
    {
        return $this->fetchAll(
            "SELECT t.task_id, t.sla_due_date, vr.round_no, vr.initiated_at, p.property_code, p.plot_no, p.property_type,
                    sc.scheme_name, se.sector_name
               FROM {verification_tasks} t
               JOIN {verification_task_assignees} a ON a.task_id = t.task_id AND a.user_id = :uid
               JOIN {verification_rounds} vr ON vr.round_id = t.round_id AND vr.round_status = 'OPEN'
               JOIN {properties} p ON p.property_id = vr.property_id
               JOIN {schemes} sc ON sc.scheme_id = p.scheme_id
               JOIN {scheme_sectors} se ON se.sector_id = p.sector_id
              WHERE t.task_status = 'PENDING'
              ORDER BY t.sla_due_date ASC, t.task_id ASC
              LIMIT :lim",
            ['uid' => $userId, 'lim' => $limit]
        );
    }

    /** One task — null if it does not exist OR the user is not assigned to it. */
    public function findForUser(int $taskId, int $userId): ?array
    {
        return $this->fetch(
            'SELECT t.*, ' . self::VIEW_STATUS . ' AS view_status,
                    vr.round_id, vr.round_no, vr.round_status, vr.consolidated_result, vr.initiated_at, vr.due_date, vr.reinitiate_reason,
                    iu.name AS initiated_by_name, iu.designation AS initiated_by_designation,
                    p.property_id, p.property_code, p.plot_no, p.property_type, p.area_value, p.area_unit, p.area_sqm, p.remarks AS property_remarks,
                    p.current_status, sc.scheme_name, sc.scheme_code, sc.scheme_type, sc.zone, se.sector_name,
                    r.role_name, r.role_code, ru.name AS responded_by_name, ru.designation AS responded_by_designation,
                    (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR \', \') FROM {verification_task_assignees} a2
                       JOIN {users} u ON u.user_id = a2.user_id WHERE a2.task_id = t.task_id) AS assignees
               FROM {verification_tasks} t
               JOIN {verification_task_assignees} a ON a.task_id = t.task_id AND a.user_id = :uid
               JOIN {verification_rounds} vr ON vr.round_id = t.round_id
               JOIN {users} iu ON iu.user_id = vr.initiated_by
               JOIN {properties} p ON p.property_id = vr.property_id
               JOIN {schemes} sc ON sc.scheme_id = p.scheme_id
               JOIN {scheme_sectors} se ON se.sector_id = p.sector_id
               JOIN {roles} r ON r.role_id = t.role_id
               LEFT JOIN {users} ru ON ru.user_id = t.responded_by
              WHERE t.task_id = :id',
            ['uid' => $userId, 'id' => $taskId]
        );
    }

    /** Same role's responses in earlier rounds of the same property (history for the checker). */
    public function previousResponses(int $propertyId, int $roleId, int $currentRoundNo): array
    {
        return $this->fetchAll(
            'SELECT vr.round_no, vr.consolidated_result, t.task_status, t.remarks, t.responded_at, t.is_simulated, ru.name AS responded_by_name
               FROM {verification_tasks} t
               JOIN {verification_rounds} vr ON vr.round_id = t.round_id
               LEFT JOIN {users} ru ON ru.user_id = t.responded_by
              WHERE vr.property_id = :pid AND t.role_id = :rid AND vr.round_no < :rn
              ORDER BY vr.round_no DESC',
            ['pid' => $propertyId, 'rid' => $roleId, 'rn' => $currentRoundNo]
        );
    }
}
