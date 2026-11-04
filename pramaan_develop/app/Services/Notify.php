<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Models\Notification;
use App\Models\User;

/**
 * In-app notifications — one row per recipient.
 * type: TASK | VERIFICATION | PROPERTY | ACCOUNT | SYSTEM, tone: info | success | warning | danger.
 * A notification with a "ref" is sent at most once per user (unique key).
 */
final class Notify
{
    /** @param int|int[]|null $userIds */
    public static function send(int|array|null $userIds, array $n): void
    {
        $ids = array_unique(array_filter(array_map('intval', (array) $userIds)));
        foreach ($ids as $uid) {
            Notification::insertIgnore([
                'user_id' => $uid, 'type' => $n['type'] ?? 'SYSTEM', 'title' => mb_substr((string) $n['title'], 0, 200),
                'message' => mb_substr((string) ($n['message'] ?? ''), 0, 600), 'link' => $n['link'] ?? null, 'icon' => $n['icon'] ?? 'fa-bell',
                'tone' => $n['tone'] ?? 'info', 'ref' => $n['ref'] ?? null, 'is_read' => 0, 'created_at' => Clock::now(),
            ]);
        }
    }

    public static function exists(int $userId, string $ref): bool
    {
        return Notification::exists(['user_id' => $userId, 'ref' => $ref]);
    }

    /** Active users of admin role (role_id = 1). */
    public static function adminUsers(): array
    {
        return array_map('intval', User::pluck('user_id', ['role_id' => 1, 'is_active' => 1]) ?: User::pluck('user_id', ['is_active' => 1]));
    }

    /** Active users whose assigned role has any of the given permissions. */
    public static function permissionUsers(string ...$perms): array
    {
        $roleIds = \App\Models\RolePermission::pluck('role_id', ['permission' => $perms]);
        if ($roleIds) {
            return array_map('intval', User::pluck('user_id', ['role_id' => array_values(array_unique($roleIds)), 'is_active' => 1]));
        }
        return [];
    }

    /** Compatibility adapter for legacy callers passing panel or role name. */
    public static function panelUsers(string $panel): array
    {
        if ($panel === 'SUPER_ADMIN' || $panel === 'ADMIN') {
            return self::adminUsers();
        }
        $perm = match ($panel) {
            'OIC' => 'verification.view',
            'DATA_ENTRY' => 'scheme.create',
            default => 'role.view',
        };
        return self::permissionUsers($perm);
    }
}
