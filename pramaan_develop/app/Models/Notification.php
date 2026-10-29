<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** In-app notification (one row per recipient). */
final class Notification extends Model
{
    protected static string $table = 'notifications';
    protected static string $pk = 'notification_id';
    protected static array $columns = ['user_id', 'type', 'title', 'message', 'link', 'icon', 'tone', 'ref', 'is_read', 'read_at', 'deleted_at'];
    protected static bool $timestamps = false;
    protected static bool $createdOnly = true;


    /** Sample data: everything older than $before counts as already read. */
    public static function markReadBefore(string $before): int
    {
        return Database::run('UPDATE {notifications} SET is_read = 1, read_at = created_at WHERE created_at < ?', [$before])->rowCount();
    }

    /** Newest visible notifications of a user. */
    public static function latestFor(int $userId, int $limit): array
    {
        return static::where(['user_id' => $userId, 'deleted_at' => null], 'notification_id DESC', $limit);
    }
}
