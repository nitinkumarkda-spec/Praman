<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Model;
use App\Core\Lookups;
use App\Core\Session;
use App\Models\Slide;
use App\Models\Attachment;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyUser;
use App\Models\Role;
use App\Models\Round;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;

/**
 * Builds the data a signed-in user may see. The UI renders every screen from
 * this snapshot; each panel only gets the rows it needs and never any secret
 * (password hashes, stored file names, other users' notifications …).
 */
final class StateService
{
    /** Public user columns (everyone). */
    private const USER_PUBLIC = ['user_id', 'name', 'designation', 'emp_code', 'panel', 'role_id', 'is_active'];
    /** Extra columns for the signed-in user and for Super Admin. */
    private const USER_ADMIN = ['username', 'mobile', 'email', 'must_change_password', 'failed_attempts', 'locked_until',
        'last_login_at', 'last_login_ip', 'password_changed_at', 'created_at'];

    public static function build(): array
    {
        $u = Auth::user();
        $state = [
            'now'     => Clock::now(),
            'csrf'    => Csrf::token(),
            'session' => null,
            'flashes' => Session::takeFlashes(),
            'app'     => self::app($u),
            'tables'  => [],
        ];
        if (!$u) {
            // landing page: number of active verification sections only
            $state['tables']['roles'] = array_map(static fn ($r) => ['role_id' => $r['role_id'], 'is_active' => $r['is_active'], 'sort_order' => $r['sort_order']],
                Role::where(['is_active' => 1]));
            return $state;
        }
        $uid = (int) $u['user_id'];
        $panel = $u['panel'];
        if ($panel === 'ROLE_USER') {
            Verification::overdueReminders($uid);
        } elseif ($panel === 'OIC') {
            Verification::reverifyReminders($uid);
        }
        $imp = Auth::impersonator();
        $state['session'] = ['user_id' => $uid, 'impersonator_id' => $imp ? (int) $imp['user_id'] : null];

        $t = &$state['tables'];
        $t['roles'] = Role::where([], 'sort_order');
        foreach (['zones' => 'Zone', 'wards' => 'Ward', 'assemblies' => 'Assembly', 'plot_types' => 'PlotType', 'statuses' => 'Status',
                     'schemes' => 'Scheme', 'sectors' => 'Sector'] as $key => $model) {
            $t[$key] = ('App\\Models\\' . $model)::where();
        }
        $t['users'] = self::users($panel === 'SUPER_ADMIN', $uid, $imp ? (int) $imp['user_id'] : null);

        if ($panel === 'ROLE_USER') {
            $propertyIds = TaskAssignee::propertyIdsFor($uid);
            $t['scheme_users'] = [];
        } else {
            $propertyIds = null; // all
            $t['scheme_users'] = \App\Models\SchemeUser::where();
        }

        $t['properties'] = $propertyIds === null ? Property::where() : Property::where(['property_id' => $propertyIds]);
        $t['property_users'] = $propertyIds === null ? PropertyUser::where() : PropertyUser::where(['property_id' => $propertyIds]);
        $t['rounds'] = $propertyIds === null ? Round::where() : Round::where(['property_id' => $propertyIds]);
        $roundIds = array_column($t['rounds'], 'round_id');
        $t['tasks'] = self::tasks($propertyIds === null ? null : $roundIds);
        $taskIds = array_column($t['tasks'], 'task_id');
        $t['task_assignees'] = $propertyIds === null ? TaskAssignee::where() : TaskAssignee::where(['task_id' => $taskIds]);
        $t['attachments'] = array_map(static fn ($a) => array_diff_key($a, ['stored_name' => 1, 'sha256' => 1]),
            $propertyIds === null ? Attachment::where() : Attachment::where(['property_id' => $propertyIds]));
        $t['audit_logs'] = self::auditLogs($panel === 'SUPER_ADMIN', $uid, $propertyIds);
        $t['login_attempts'] = $panel === 'SUPER_ADMIN' ? self::loginAttempts() : [];
        $t['notifications'] = Notification::latestFor($uid, (int) config('state.notification_limit', 300));
        return $state;
    }

    /** Settings, landing slides and code lists for the screens. */
    private static function app(?array $u): array
    {
        $admin = $u && $u['panel'] === 'SUPER_ADMIN';
        $app = [
            'settings' => Settings::typed(!$admin),
            'lookups'  => Lookups::all(),
            'slides'   => $admin ? Slide::where([], 'sort_order') : Slide::where(['is_active' => 1], 'sort_order'),
        ];
        if ($admin) {
            $app['settings_meta'] = Settings::meta();
            $app['settings_groups'] = Settings::GROUPS;
        }
        return $app;
    }

    private static function users(bool $admin, int $self, ?int $impersonator): array
    {
        $rows = User::select(array_merge(self::USER_PUBLIC, self::USER_ADMIN), [], 'user_id');
        return array_map(static function ($r) use ($admin, $self, $impersonator) {
            if (!$admin && $r['user_id'] !== $self && $r['user_id'] !== $impersonator) {
                $r = array_intersect_key($r, array_flip(self::USER_PUBLIC));
            }
            return $r;
        }, $rows);
    }

    /** Tasks with the Engineering site inspection / Sale vacancy check attached. */
    private static function tasks(?array $roundIds): array
    {
        $tasks = $roundIds === null ? Task::where() : Task::where(['round_id' => $roundIds]);
        if (!$tasks) {
            return [];
        }
        $ids = array_column($tasks, 'task_id');
        $site = array_column(\App\Models\SiteInspection::where(['task_id' => $ids]), null, 'task_id');
        $sale = array_column(\App\Models\SaleCheck::where(['task_id' => $ids]), null, 'task_id');
        foreach ($tasks as &$x) {
            $x['site_inspection'] = $site[$x['task_id']] ?? null;
            $x['sale_check'] = $sale[$x['task_id']] ?? null;
        }
        return $tasks;
    }

    private static function auditLogs(bool $admin, int $uid, ?array $propertyIds): array
    {
        $limit = (int) config('state.audit_limit', 3000);
        $rows = $admin ? \App\Models\AuditLog::where([], 'log_id DESC', $limit) : \App\Models\AuditLog::recentFor($propertyIds, $uid, $limit);
        return array_map(static function ($r) use ($admin) {
            $r['old_value'] = $r['old_value'] !== null ? json_decode((string) $r['old_value'], true) : null;
            $r['new_value'] = $r['new_value'] !== null ? json_decode((string) $r['new_value'], true) : null;
            if (!$admin) {
                unset($r['user_agent'], $r['ip_address']);
                $r['ip_address'] = '';
            }
            return $r;
        }, $rows);
    }

    private static function loginAttempts(): array
    {
        return \App\Models\LoginAttempt::select(['attempt_id', 'username', 'success', 'attempted_at'], ['attempted_at >=' => now(-90 * 1440)], 'attempt_id DESC', 5000);
    }
}
