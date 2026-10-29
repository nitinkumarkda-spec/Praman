<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** Users who may answer a task. */
final class TaskAssignee extends Model
{
    protected static string $table = 'task_assignees';
    protected static string $pk = 'assignee_id';
    protected static array $columns = ['task_id', 'user_id'];
    protected static bool $timestamps = false;
    protected static bool $createdOnly = true;


    /** Properties a user has (had) a task on — what a role user may see. */
    public static function propertyIdsFor(int $userId): array
    {
        return array_map('intval', array_column(Database::all(
            'SELECT DISTINCT r.property_id FROM {task_assignees} a JOIN {tasks} t ON t.task_id = a.task_id JOIN {rounds} r ON r.round_id = t.round_id WHERE a.user_id = ?',
            [$userId]
        ), 'property_id'));
    }

    public static function userOnProperty(int $userId, int $propertyId): bool
    {
        return Database::value(
            'SELECT 1 FROM {task_assignees} a JOIN {tasks} t ON t.task_id = a.task_id JOIN {rounds} r ON r.round_id = t.round_id
             WHERE a.user_id = ? AND r.property_id = ? LIMIT 1',
            [$userId, $propertyId]
        ) !== null;
    }
}
