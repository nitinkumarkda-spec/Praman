<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Codes\PermissionEffect;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lookups;
use App\Core\Permissions;
use App\Models\PanelPermission;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\Audit;

/** Super Admin → Permissions: which actions each panel may use, and one user's own exceptions. */
final class PermissionController extends Controller
{
    public function save(): void
    {
        $old = Permissions::matrix();
        $new = [];
        // only the panels this form shows (one role, or all of them in the matrix) are changed
        $panels = array_values(array_intersect(array_keys(Lookups::PANELS), $this->request->list('panels')));
        if (!$panels) {
            $this->fail('Choose the role whose permissions you want to save.');
        }
        foreach ($panels as $panel) {
            $codes = array_values(array_unique($this->request->list('perm_' . $panel)));
            foreach ($codes as $code) {
                if (!Permissions::applies($code, $panel)) {
                    $this->fail('"' . $code . '" cannot be given to the ' . Lookups::PANELS[$panel] . ' panel.');
                }
            }
            $new[$panel] = array_values(array_unique(array_merge($codes, Permissions::LOCKED[$panel] ?? [])));
        }

        $changed = 0;
        Database::transaction(static function () use ($old, $new, &$changed): void {
            foreach ($new as $panel => $codes) {
                $added = array_values(array_diff($codes, $old[$panel]));
                $removed = array_values(array_diff($old[$panel], $codes));
                if (!$added && !$removed) {
                    continue;
                }
                PanelPermission::replace($panel, $codes, Auth::id());
                Audit::log('PERMISSION', null, 'PERMISSIONS_UPDATED', ['panel' => $panel, 'removed' => $removed], ['panel' => $panel, 'added' => $added]);
                $changed++;
            }
        });
        $what = count($panels) === 1 ? 'the ' . Lookups::PANELS[$panels[0]] . ' role' : $changed . ' role(s)';
        $this->done($changed ? 'Permissions of ' . $what . ' saved. They apply on the next screen its users open.' : 'No change in permissions.',
            null, $changed ? 'success' : 'info');
    }

    /** One user's own exceptions: ov[code] = inherit | ALLOW | DENY. */
    public function userSave(): void
    {
        $user = User::find($this->id());
        if (!$user) {
            $this->fail('User not found.');
        }
        $panel = $user['panel'];
        $input = $this->request->input('ov');
        $new = [];
        foreach (is_array($input) ? $input : [] as $code => $effect) {
            $code = (string) $code;
            if ($effect === PermissionEffect::INHERIT || $effect === '') {
                continue;
            }
            if (!in_array($effect, PermissionEffect::ALL, true) || !Permissions::applies($code, $panel)) {
                $this->fail('"' . $code . '" cannot be set for a ' . Lookups::PANELS[$panel] . ' user.');
            }
            if (Permissions::isLocked($panel, $code)) {
                continue; // always on for this panel
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
        $this->done('Permissions of ' . $user['name'] . ' saved' . ($new ? ' — ' . count($new) . ' personal exception(s).' : ' — now exactly as the ' . Lookups::PANELS[$panel] . ' panel.'));
    }
}
