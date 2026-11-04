<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Codes\PermissionEffect;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Permissions;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\Audit;

/** Admin → Permissions: which actions each role may use, and one user's own exceptions. */
final class PermissionController extends Controller
{
    public function save(): void
    {
        $old = Permissions::matrix();
        $new = [];
        $rolesInput = $this->request->list('roles');
        if (!$rolesInput) {
            $rolesInput = $this->request->list('panels');
        }
        $roleIds = [];
        foreach ($rolesInput as $r) {
            if (is_numeric($r)) {
                $roleIds[] = (int) $r;
            } elseif (str_starts_with((string) $r, 'role:')) {
                $roleIds[] = (int) substr((string) $r, 5);
            } else {
                $found = Role::first(['role_name' => (string) $r]);
                if ($found) {
                    $roleIds[] = (int) $found['role_id'];
                }
            }
        }
        $roleIds = array_values(array_unique(array_filter($roleIds)));
        if (!$roleIds) {
            $this->fail('Choose the role whose permissions you want to save.');
        }

        foreach ($roleIds as $roleId) {
            $codes = array_values(array_unique(array_merge(
                $this->request->list('perm_' . $roleId),
                $this->request->list('perm_role:' . $roleId)
            )));
            foreach ($codes as $code) {
                if (!Permissions::applies($code, $roleId)) {
                    $this->fail('"' . $code . '" is not a valid permission.');
                }
            }
            $new[$roleId] = array_values(array_unique(array_merge($codes, Permissions::LOCKED[$roleId] ?? [])));
        }

        $changed = 0;
        Database::transaction(static function () use ($old, $new, &$changed): void {
            foreach ($new as $roleId => $codes) {
                $added = array_values(array_diff($codes, $old[$roleId] ?? []));
                $removed = array_values(array_diff($old[$roleId] ?? [], $codes));
                if (!$added && !$removed) {
                    continue;
                }
                RolePermission::replace($roleId, $codes, Auth::id());
                Audit::log('PERMISSION', null, 'PERMISSIONS_UPDATED', ['role_id' => $roleId, 'removed' => $removed], ['role_id' => $roleId, 'added' => $added]);
                $changed++;
            }
        });
        $what = count($roleIds) === 1 ? 'the role' : $changed . ' role(s)';
        $this->done($changed ? 'Permissions of ' . $what . ' saved. They apply immediately.' : 'No change in permissions.',
            null, $changed ? 'success' : 'info');
    }

    /** One user's own exceptions: ov[code] = inherit | ALLOW | DENY. */
    public function userSave(): void
    {
        $user = User::find($this->id());
        if (!$user) {
            $this->fail('User not found.');
        }
        $roleId = (int) ($user['role_id'] ?? 1);
        $input = $this->request->input('ov');
        $new = [];
        foreach (is_array($input) ? $input : [] as $code => $effect) {
            $code = (string) $code;
            if ($effect === PermissionEffect::INHERIT || $effect === '') {
                continue;
            }
            if (!in_array($effect, PermissionEffect::ALL, true) || !Permissions::applies($code, $roleId)) {
                $this->fail('"' . $code . '" is not a valid permission.');
            }
            if (Permissions::isLocked($roleId, $code)) {
                continue;
            }
            $new[$code] = $effect;
        }
        if ($user['user_id'] === Auth::id() && ($new['user.permissions'] ?? null) === PermissionEffect::DENY) {
            $this->fail('You cannot take away your own right to change user permissions.');
        }
        ksort($new);
        $old = UserPermission::mapFor($user['user_id']);
        ksort($old);
        if ($old === $new) {
            $this->done('No change in the permissions of ' . $user['name'] . '.', null, 'info');
        }
        UserPermission::replace($user['user_id'], $new, Auth::id());
        Audit::log('USER', $user['user_id'], 'PERMISSIONS_OVERRIDDEN', ['overrides' => $old], ['overrides' => $new]);
        $role = Role::find($roleId);
        $roleName = $role['role_name'] ?? 'role';
        $this->done('Permissions of ' . $user['name'] . ' saved' . ($new ? ' — ' . count($new) . ' personal exception(s).' : ' — now exactly as the ' . $roleName . ' role.'));
    }
}
