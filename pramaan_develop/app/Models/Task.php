<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** One section's task in a round. */
final class Task extends Model
{
    protected static string $table = 'tasks';
    protected static string $pk = 'task_id';
    protected static array $columns = ['round_id', 'role_id', 'task_status', 'sla_due_date', 'remarks', 'responded_by', 'responded_at'];


    /** Tasks of a round with their role name, in section order (consolidation). */
    public static function withRoles(int $roundId): array
    {
        return static::castAll(Database::all(
            'SELECT t.*, r.role_name, r.sort_order FROM {tasks} t JOIN {roles} r ON r.role_id = t.role_id WHERE t.round_id = ? ORDER BY r.sort_order',
            [$roundId]
        ));
    }

    /** Pending tasks of open rounds assigned to a user whose SLA date has passed (reminders). */
    public static function overdueFor(int $userId, string $today): array
    {
        return static::castAll(Database::all(
            "SELECT t.task_id, t.sla_due_date, p.plot_no FROM {task_assignees} a
             JOIN {tasks} t ON t.task_id = a.task_id JOIN {rounds} r ON r.round_id = t.round_id JOIN {properties} p ON p.property_id = r.property_id
             WHERE a.user_id = ? AND t.task_status = 'PENDING' AND r.round_status = 'OPEN' AND t.sla_due_date < ?",
            [$userId, $today]
        ));
    }
}
