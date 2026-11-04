<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\Permissions;

/** One permission given to a role (Admin → Permissions). */
final class RolePermission extends Model
{
    protected static string $table = 'role_permissions';
    protected static string $pk = 'grant_id';
    protected static array $columns = ['role_id', 'permission', 'created_by'];
    protected static bool $timestamps = false;
    protected static bool $createdOnly = true;

    /** @return string[] */
    public static function codesFor(int $roleId): array
    {
        return static::pluck('permission', ['role_id' => $roleId], 'permission');
    }

    /** Replace the codes of one role. */
    public static function replace(int $roleId, array $codes, ?int $by): void
    {
        Database::transaction(static function () use ($roleId, $codes, $by): void {
            static::deleteWhere(['role_id' => $roleId]);
            foreach ($codes as $code) {
                static::insert(['role_id' => $roleId, 'permission' => $code, 'created_by' => $by]);
            }
        });
        Permissions::forget();
    }
}

