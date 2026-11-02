<?php
declare(strict_types=1);

namespace App\Core;

use App\Codes\Panel;
use App\Codes\PermissionEffect;
use App\Models\PanelPermission;
use App\Models\UserPermission;

/**
 * Permission catalog — every action the server checks ("can:user.create" on a route,
 * Auth::can() in code, P.Auth.can() in the UI). The panels stay fixed; which of these
 * each panel may use is stored in panel_permissions and edited by Super Admin.
 *
 *   module => [label, icon, [code => [label, panels that can be given it]]]
 */
final class Permissions
{
    private const SA = [Panel::SUPER_ADMIN];

    public const MODULES = [
        'role' => ['Role Master', 'fa-sitemap', [
            'role.view'   => ['View roles', self::SA],
            'role.create' => ['Add role', self::SA],
            'role.edit'   => ['Edit role', self::SA],
            'role.toggle' => ['Activate / deactivate role', self::SA],
        ]],
        'user' => ['User Master', 'fa-users', [
            'user.view'           => ['View users', self::SA],
            'user.create'         => ['Add user', self::SA],
            'user.edit'           => ['Edit user', self::SA],
            'user.toggle'         => ['Activate / deactivate user', self::SA],
            'user.unlock'         => ['Unlock account', self::SA],
            'user.reset_password' => ['Reset password', self::SA],
            'user.impersonate'    => ['Login as user', self::SA],
            'user.permissions'    => ['Give / take away permissions of one user', self::SA],
        ]],
        'master' => ['Masters (Area, Sector, Plot Type, Unit, Status)', 'fa-map-location-dot', [
            'master.view'   => ['View masters', self::SA],
            'master.create' => ['Add master record', self::SA],
            'master.edit'   => ['Edit master record', self::SA],
            'master.toggle' => ['Activate / deactivate master record', self::SA],
            'master.delete' => ['Delete master record', self::SA],
        ]],
        'scheme' => ['Scheme Master', 'fa-map', [
            'scheme.view'   => ['View schemes', [Panel::SUPER_ADMIN, Panel::DATA_ENTRY]],
            'scheme.create' => ['Add scheme', [Panel::SUPER_ADMIN, Panel::DATA_ENTRY]],
            'scheme.edit'   => ['Edit scheme', [Panel::SUPER_ADMIN, Panel::DATA_ENTRY]],
            'scheme.toggle' => ['Activate / deactivate scheme', [Panel::SUPER_ADMIN, Panel::DATA_ENTRY]],
        ]],
        'property' => ['Property Register', 'fa-building', [
            'property.view'   => ['View properties', [Panel::DATA_ENTRY]],
            'property.create' => ['Add property', [Panel::DATA_ENTRY]],
            'property.edit'   => ['Edit property', [Panel::DATA_ENTRY]],
        ]],
        'verification' => ['Verification (OIC)', 'fa-list-check', [
            'verification.view' => ['View properties for verification', [Panel::OIC]],
            'verification.send' => ['Send / re-send for verification', [Panel::OIC]],
        ]],
        'task' => ['My Tasks (section users)', 'fa-clipboard-list', [
            'task.view'    => ['View assigned tasks', [Panel::ROLE_USER]],
            'task.respond' => ['Submit verification response', [Panel::ROLE_USER]],
        ]],
        'audit' => ['Activity & Audit', 'fa-chart-line', [
            'audit.view'        => ['View activity & audit', self::SA],
            'direction.publish' => ['Publish directions', self::SA],
        ]],
        'settings' => ['App Settings', 'fa-sliders', [
            'settings.view' => ['View settings', self::SA],
            'settings.edit' => ['Change settings & slides', self::SA],
        ]],
        'permission' => ['Permissions', 'fa-user-shield', [
            'permission.view' => ['View permissions', self::SA],
            'permission.edit' => ['Change permissions', self::SA],
        ]],
    ];

    /** Always granted, so Super Admin can never lock everyone out of this screen. */
    public const LOCKED = [Panel::SUPER_ADMIN => ['permission.view', 'permission.edit']];

    /** @var array<string, string[]> panel => granted codes (this request) */
    private static array $granted = [];
    /** @var array<int, string[]> user id => effective codes (this request) */
    private static array $users = [];

    /** @return array<string, array{0:string,1:string[]}> code => [label, panels] */
    public static function all(): array
    {
        return array_merge(...array_map(static fn ($m) => $m[2], array_values(self::MODULES)));
    }

    /** May $code be given to $panel at all? */
    public static function applies(string $code, string $panel): bool
    {
        return in_array($panel, self::all()[$code][1] ?? [], true);
    }

    public static function isLocked(string $panel, string $code): bool
    {
        return in_array($code, self::LOCKED[$panel] ?? [], true);
    }

    /** Codes $panel may use: stored grants that still apply + the locked ones. */
    public static function forPanel(string $panel): array
    {
        if (!isset(self::$granted[$panel])) {
            $codes = array_filter(PanelPermission::codesFor($panel), static fn ($c) => self::applies($c, $panel));
            self::$granted[$panel] = array_values(array_unique(array_merge($codes, self::LOCKED[$panel] ?? [])));
        }
        return self::$granted[$panel];
    }

    /**
     * What one user may do: the panel's codes + the user's ALLOW overrides − the DENY overrides.
     * Overrides only count for codes the panel can have, and never remove a locked code.
     */
    public static function forUser(int $userId, string $panel): array
    {
        if (!isset(self::$users[$userId])) {
            $codes = array_flip(self::forPanel($panel));
            foreach (UserPermission::mapFor($userId) as $code => $effect) {
                if (!self::applies($code, $panel) || self::isLocked($panel, $code)) {
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

    /** panel => granted codes, for the Permissions screen. */
    public static function matrix(): array
    {
        $out = [];
        foreach (array_keys(Lookups::PANELS) as $panel) {
            $out[$panel] = self::forPanel($panel);
        }
        return $out;
    }

    /** Every code each panel can be given (the first install grants all of them). */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::all() as $code => [, $panels]) {
            foreach ($panels as $panel) {
                $out[$panel][] = $code;
            }
        }
        return $out;
    }

    /** Catalog for the UI: [{key, label, icon, items:[{code, label, panels}]}]. */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::MODULES as $key => [$label, $icon, $items]) {
            $out[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'items' => array_map(
                static fn ($code, $x) => ['code' => $code, 'label' => $x[0], 'panels' => $x[1]], array_keys($items), $items)];
        }
        return $out;
    }

    public static function forget(): void
    {
        self::$granted = [];
        self::$users = [];
    }
}
