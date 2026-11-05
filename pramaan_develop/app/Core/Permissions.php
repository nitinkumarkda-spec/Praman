<?php
declare(strict_types=1);

namespace App\Core;

use App\Codes\PermissionEffect;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\UserPermission;

/**
 * Permission catalog — every action the server checks ("can:user.create" on a route,
 * Auth::can() in code, P.Auth.can() in the UI).
 *
 * Permissions are assigned dynamically to ROLES in the Role & Permission Master.
 * The Admin role (role_id = 1) has full access to all modules.
 */
final class Permissions
{
    public const MODULES = [
        'role' => ['Role Master', 'fa-sitemap', [
            'role.view'   => ['View roles'],
            'role.create' => ['Add role'],
            'role.edit'   => ['Edit role'],
            'role.toggle' => ['Activate / deactivate role'],
        ]],
        'user' => ['User Master', 'fa-users', [
            'user.view'           => ['View users'],
            'user.create'         => ['Add user'],
            'user.edit'           => ['Edit user'],
            'user.toggle'         => ['Activate / deactivate user'],
            'user.unlock'         => ['Unlock account'],
            'user.reset_password' => ['Reset password'],
            'user.impersonate'    => ['Login as user'],
            'user.permissions'    => ['Give / take away permissions of one user'],
        ]],
        'master' => ['Masters (Sector, Plot Type, Unit, Status)', 'fa-map-location-dot', [
            'master.view'   => ['View masters'],
            'master.create' => ['Add master record'],
            'master.edit'   => ['Edit master record'],
            'master.toggle' => ['Activate / deactivate master record'],
            'master.delete' => ['Delete master record'],
        ]],
        'scheme' => ['Schemes (Scheme Management)', 'fa-map', [
            'scheme.view'   => ['View schemes'],
            'scheme.create' => ['Add scheme'],
            'scheme.edit'   => ['Edit scheme'],
            'scheme.toggle' => ['Activate / deactivate scheme'],
        ]],
        'property' => ['Property Register', 'fa-building', [
            'property.view'   => ['View properties'],
            'property.create' => ['Add property'],
            'property.edit'   => ['Edit property'],
        ]],
        'verification' => ['Verification', 'fa-list-check', [
            'verification.view' => ['View properties for verification'],
            'verification.send' => ['Send / re-send for verification'],
        ]],
        'audit' => ['Activity & Audit', 'fa-chart-line', [
            'audit.view'        => ['View activity & audit'],
            'direction.publish' => ['Publish directions'],
        ]],
        'settings' => ['App Settings', 'fa-sliders', [
            'settings.view' => ['View settings'],
            'settings.edit' => ['Change settings & slides'],
        ]],
        'permission' => ['Permissions', 'fa-user-shield', [
            'permission.view' => ['View permissions'],
            'permission.edit' => ['Change permissions'],
        ]],
        'report' => ['Reports & Analytics', 'fa-chart-pie', [
            'report.view'   => ['View reports and analytics'],
            'report.export' => ['Export and print reports'],
        ]],
    ];

    /** Always granted to Admin (role_id = 1) so administrators can never lock everyone out. */
    public const LOCKED = [
        1 => ['permission.view', 'permission.edit', 'role.view', 'role.edit', 'user.view', 'user.edit', 'report.view', 'report.export']
    ];

    /** @var array<int, string[]> role_id => granted codes (this request) */
    private static array $granted = [];
    /** @var array<int, string[]> user_id => effective codes (this request) */
    private static array $users = [];

    /** @return array<string, array{0:string}> code => [label] */
    public static function all(): array
    {
        return array_merge(...array_map(static fn ($m) => $m[2], array_values(self::MODULES)));
    }

    /** May $code be given to a role? (Any valid code in catalog can be given to any role) */
    public static function applies(string $code, int|string $role = 1): bool
    {
        return isset(self::all()[$code]);
    }

    public static function isLocked(int|string $roleId, string $code): bool
    {
        $rid = (int) $roleId;
        return in_array($code, self::LOCKED[$rid] ?? [], true);
    }

    /** Codes a role may use: for Admin (role_id 1), all codes; for other roles, stored grants. */
    public static function forRole(int $roleId): array
    {
        if (!isset(self::$granted[$roleId])) {
            if ($roleId === 1) {
                $codes = array_keys(self::all());
            } else {
                $codes = RolePermission::codesFor($roleId);
            }
            self::$granted[$roleId] = array_values(array_unique(array_merge($codes, self::LOCKED[$roleId] ?? [])));
        }
        return self::$granted[$roleId];
    }

    /** Compatibility helper for older callers passing panel string or role id. */
    public static function forPanel(string|int $target): array
    {
        if (is_numeric($target)) {
            return self::forRole((int) $target);
        }
        return self::forRole(1);
    }

    /**
     * What one user may do: the role's codes + the user's ALLOW overrides − the DENY overrides.
     */
    public static function forUser(int $userId, int|string $roleId): array
    {
        $rid = (int) $roleId;
        if (!isset(self::$users[$userId])) {
            $codes = array_flip(self::forRole($rid));
            foreach (UserPermission::mapFor($userId) as $code => $effect) {
                if ($rid === 1 && self::isLocked($rid, $code)) {
                    continue;
                }
                if ($effect === PermissionEffect::ALLOW) {
                    $codes[$code] = true;
                } else {
                    unset($codes[$code]);
                }
            }
            self::$users[$userId] = array_keys($codes);
        }
        return self::$users[$userId];
    }

    /** role_id => granted codes, for the Permissions screen. */
    public static function matrix(): array
    {
        $out = [];
        $roles = Role::where(['is_active' => 1], 'sort_order');
        foreach ($roles as $r) {
            $rid = (int) $r['role_id'];
            $out[$rid] = self::forRole($rid);
        }
        return $out;
    }

    /** Every code for defaults (Admin role gets all of them). */
    public static function defaults(): array
    {
        return [1 => array_keys(self::all())];
    }

    /** Catalog for the UI: [{key, label, icon, items:[{code, label}]}]. */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::MODULES as $key => [$label, $icon, $items]) {
            $out[] = [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'items' => array_map(
                    static fn ($code, $x) => ['code' => $code, 'label' => is_array($x) ? $x[0] : $x],
                    array_keys($items),
                    $items
                )
            ];
        }
        return $out;
    }

    public static function forget(): void
    {
        self::$granted = [];
        self::$users = [];
    }
}
