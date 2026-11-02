<?php
declare(strict_types=1);

namespace App\Models;

use App\Codes\PermissionEffect;
use App\Core\Database;
use App\Core\Model;
use App\Core\Permissions;

/** One user's exception to the permissions of the panel: ALLOW (extra) or DENY (taken away). */
final class UserPermission extends Model
{
    public const EFFECTS = PermissionEffect::ALL;

    protected static string $table = 'user_permissions';
    protected static string $pk = 'override_id';
    protected static array $columns = ['user_id', 'permission', 'effect', 'created_by'];
    protected static bool $timestamps = false;
    protected static bool $createdOnly = true;

    /** @return array<string, string> code => ALLOW|DENY */
    public static function mapFor(int $userId): array
    {
        return array_column(static::select(['permission', 'effect'], ['user_id' => $userId]), 'effect', 'permission');
    }

    /** Replace all overrides of one user with $map (code => ALLOW|DENY). */
    public static function replace(int $userId, array $map, ?int $by): void
    {
        Database::transaction(static function () use ($userId, $map, $by): void {
            static::deleteWhere(['user_id' => $userId]);
            foreach ($map as $code => $effect) {
                static::insert(['user_id' => $userId, 'permission' => $code, 'effect' => $effect, 'created_by' => $by]);
            }
        });
        Permissions::forget();
    }
}
