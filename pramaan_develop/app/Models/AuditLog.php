<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** Audit trail row (insert-only). */
final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';
    protected static string $pk = 'log_id';
    protected static array $columns = ['entity_type', 'entity_id', 'property_id', 'action', 'old_value', 'new_value', 'performed_by', 'performed_by_name',
        'performed_by_designation', 'ip_address', 'user_agent', 'performed_at'];
    protected static bool $timestamps = false;


    /**
     * Newest rows for a non-admin: rows of the given properties (null = every property row)
     * plus the user's own actions — two indexed queries joined with UNION (faster than OR).
     */
    public static function recentFor(?array $propertyIds, int $userId, int $limit): array
    {
        $limit = max(1, $limit);
        $byProperty = $propertyIds === null ? 'property_id IS NOT NULL'
            : 'property_id IN (' . ($propertyIds ? implode(',', array_map('intval', $propertyIds)) : '0') . ')';
        return static::castAll(Database::all(
            '(SELECT * FROM {audit_logs} WHERE ' . $byProperty . ' ORDER BY log_id DESC LIMIT ' . $limit . ')
             UNION
             (SELECT * FROM {audit_logs} WHERE performed_by = ? ORDER BY log_id DESC LIMIT ' . $limit . ')
             ORDER BY log_id DESC LIMIT ' . $limit,
            [$userId]
        ));
    }
}
