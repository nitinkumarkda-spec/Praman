<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Models\Property;
use App\Models\PropertyUser;
use App\Models\Role;
use App\Models\Round;
use App\Models\Scheme;
use App\Models\Sector;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;

/**
 * Verification engine: send a property to every mapped section (round),
 * record each section's decision, close the round as READY / NOT READY,
 * expire Auction Ready validity, and SLA / re-verification reminders.
 */
final class Verification
{
    public const SENDABLE = ['LISTED', 'NOT_READY', 'EXPIRED'];

    public static function validityDays(): int
    {
        return (int) config('verification.validity_days', 180);
    }

    /** Last date of the re-verification window: Auction Ready valid till this date or earlier is "due". */
    public static function horizon(): string
    {
        return today((int) config('verification.reverify_alert_days', 15));
    }

    public static function isDue(array $p): bool
    {
        return $p['current_status'] === 'AUCTION_READY' && !empty($p['valid_till']) && $p['valid_till'] <= self::horizon();
    }

    /** Can OIC start a new round now? Listed / Not Ready / Expired, or Auction Ready inside the window. */
    public static function canSend(array $p): bool
    {
        return in_array($p['current_status'], self::SENDABLE, true) || self::isDue($p);
    }

    /** Auction Ready past its validity -> EXPIRED (safe to repeat). */
    public static function expireDue(): int
    {
        $n = 0;
        foreach (Property::readyValidTill('<', today()) as $p) {
            Database::transaction(static function () use ($p): void {
                Property::update($p['property_id'], ['current_status' => 'EXPIRED']);
                Audit::log('PROPERTY', $p['property_id'], 'VALIDITY_EXPIRED', ['status' => 'AUCTION_READY'],
                    ['status' => 'EXPIRED', 'valid_till' => $p['valid_till'], 'round_id' => $p['ready_round_id']], $p['property_id'], Audit::system());
                Notify::send(Notify::panelUsers('OIC'), [
                    'type' => 'VERIFICATION', 'tone' => 'danger', 'icon' => 'fa-calendar-xmark', 'ref' => 'EXPIRED-' . $p['property_id'] . '-' . $p['valid_till'],
                    'title' => 'Validity expired — Plot ' . $p['plot_no'],
                    'message' => 'Auction readiness ended on ' . dmy($p['valid_till']) . '. Send it for periodic re-verification before the next auction.',
                    'link' => '/oic/properties/' . $p['property_id'],
                ]);
            });
            $n++;
        }
        return $n;
    }

    /** @return array{ok:bool,message:string,round_no?:int} */
    public static function send(int $propertyId, int $by, ?string $reason): array
    {
        return Database::transaction(static function () use ($propertyId, $by, $reason): array {
            // lock the property row so two OIC clicks cannot open two rounds
            $p = Property::findForUpdate($propertyId);
            if (!$p || !$p['is_active']) {
                return ['ok' => false, 'message' => 'Property #' . $propertyId . ' not found.'];
            }
            $sc = Scheme::find($p['scheme_id']);
            $se = Sector::find($p['sector_id']);
            $lbl = 'Plot ' . $p['plot_no'] . ' (' . $sc['scheme_name'] . ' / ' . $se['sector_name'] . ')';
            $reason = $reason !== null ? trim($reason) : null;

            if (!self::canSend($p)) {
                return ['ok' => false, 'message' => $lbl . ': status is ' . label($p['current_status']) . ' — only Listed / Not Ready / Validity Expired (or Auction Ready due for re-verification) can be sent.'];
            }
            if (Round::exists(['property_id' => $p['property_id'], 'round_status' => 'OPEN'])) {
                return ['ok' => false, 'message' => $lbl . ': a verification round is already open.'];
            }
            if ($p['current_status'] === 'NOT_READY' && ($reason === null || $reason === '')) {
                return ['ok' => false, 'message' => $lbl . ': reason is required to re-send a Not Ready property.'];
            }
            if (($reason === null || $reason === '') && $p['current_status'] !== 'LISTED') {
                $reason = 'Periodic re-verification — validity ' . ($p['current_status'] === 'EXPIRED' ? 'expired' : 'ends') . ' on ' . dmy($p['valid_till']);
            }

            // active snapshot rows; role active, user active and still in that role
            $roles = [];
            foreach (PropertyUser::where(['property_id' => $p['property_id'], 'is_active' => 1]) as $pru) {
                $r = Role::find($pru['role_id']);
                $u = User::find($pru['user_id']);
                if (!$r || !$r['is_active'] || !$u || !$u['is_active'] || $u['panel'] !== 'ROLE_USER' || (int) $u['role_id'] !== (int) $r['role_id']) {
                    continue;
                }
                $roles[$r['role_id']] ??= ['role' => $r, 'users' => []];
                if (!in_array($u['user_id'], $roles[$r['role_id']]['users'], true)) {
                    $roles[$r['role_id']]['users'][] = $u['user_id'];
                }
            }
            $missing = [];
            foreach (Role::where(['is_active' => 1, 'is_mandatory' => 1], 'sort_order') as $r) {
                if (!isset($roles[$r['role_id']])) {
                    $missing[] = $r['role_name'];
                }
            }
            if ($missing) {
                return ['ok' => false, 'message' => $lbl . ': no active user assigned for mandatory role(s): ' . implode(', ', $missing) . '.'];
            }
            if (!$roles) {
                return ['ok' => false, 'message' => $lbl . ': no role users assigned.'];
            }
            $list = array_values($roles);
            usort($list, static fn ($a, $b) => $a['role']['sort_order'] <=> $b['role']['sort_order']);

            $roundNo = Round::nextOrder(['property_id' => $p['property_id']], 'round_no');
            $maxSla = max(array_map(static fn ($x) => (int) $x['role']['sla_days'], $list));
            $roundId = Round::insert([
                'property_id' => $p['property_id'], 'round_no' => $roundNo, 'round_status' => 'OPEN', 'initiated_by' => $by,
                'initiated_at' => Clock::now(), 'due_date' => today($maxSla),
                'resend_reason' => $roundNo > 1 ? mb_substr((string) $reason, 0, 255) : null,
            ]);
            $tasks = [];
            foreach ($list as $x) {
                $due = today((int) $x['role']['sla_days']);
                $taskId = Task::insert(['round_id' => $roundId, 'role_id' => $x['role']['role_id'], 'task_status' => 'PENDING', 'sla_due_date' => $due]);
                foreach ($x['users'] as $uid) {
                    TaskAssignee::insert(['task_id' => $taskId, 'user_id' => $uid]);
                }
                $tasks[] = ['task_id' => $taskId, 'role' => $x['role'], 'users' => $x['users'], 'due' => $due];
            }
            $prevStatus = $p['current_status'];
            Property::update($p['property_id'], ['current_status' => 'UNDER_VERIFICATION', 'current_round_id' => $roundId, 'updated_by' => $by]);

            foreach ($tasks as $t) {
                Notify::send($t['users'], [
                    'type' => 'TASK', 'tone' => $roundNo > 1 ? 'warning' : 'info', 'icon' => 'fa-clipboard-list',
                    'title' => ($roundNo > 1 ? 'Re-verification task' : 'New verification task') . ' — Plot ' . $p['plot_no'],
                    'message' => $sc['scheme_name'] . ' / ' . $se['sector_name'] . ' · Round R' . $roundNo . ' · ' . $t['role']['role_name'] . ' · due ' . dmy($t['due'])
                        . ($roundNo > 1 && $reason ? ' · Reason: ' . $reason : ''),
                    'link' => '/role/tasks/' . $t['task_id'],
                ]);
            }
            if ((int) $p['created_by'] !== $by) {
                Notify::send($p['created_by'], [
                    'type' => 'VERIFICATION', 'icon' => 'fa-paper-plane', 'title' => 'Plot ' . $p['plot_no'] . ' sent for verification',
                    'message' => 'Round R' . $roundNo . ' sent to ' . count($list) . ' section(s) by OIC.', 'link' => '/data-entry/properties',
                ]);
            }
            Audit::log('ROUND', $roundId, $roundNo > 1 ? 'REINITIATED' : 'ROUND_INITIATED', ['status' => $prevStatus],
                ['round_no' => $roundNo, 'roles' => array_map(static fn ($x) => $x['role']['role_name'], $list), 'reason' => $reason ?: null], $p['property_id']);

            return ['ok' => true, 'message' => $lbl . ': sent as Round R' . $roundNo . ' to ' . count($list) . ' role(s).', 'round_no' => $roundNo];
        });
    }

    /**
     * A section's decision. $extra: ['attachments' => [file names]] for the audit row.
     * @return array{ok:bool,message:string,outcome?:?string}
     */
    public static function respond(int $taskId, string $decision, ?string $remarks, int $by, array $extra = []): array
    {
        if ($decision !== 'APPROVED' && $decision !== 'REJECTED') {
            return ['ok' => false, 'message' => 'Invalid decision.'];
        }
        $remarks = $remarks === null ? null : trim($remarks);
        if ($decision === 'REJECTED' && !$remarks) {
            return ['ok' => false, 'message' => 'Rejection reason (remark) is mandatory.'];
        }
        return Database::transaction(static function () use ($taskId, $decision, $remarks, $by, $extra): array {
            $t = Task::findForUpdate($taskId);
            if (!$t) {
                return ['ok' => false, 'message' => 'Task not found.'];
            }
            $round = Round::find($t['round_id']);
            $role = Role::find($t['role_id']);
            if ($round['round_status'] !== 'OPEN') {
                return ['ok' => false, 'message' => 'This round is already closed.'];
            }
            if ($t['task_status'] !== 'PENDING') {
                return ['ok' => false, 'message' => $role['role_name'] . ' has already responded (' . label($t['task_status']) . ').'];
            }
            Task::update($t['task_id'], ['task_status' => $decision, 'remarks' => $remarks ?: null, 'responded_by' => $by, 'responded_at' => Clock::now()]);
            $outcome = self::consolidate($round['round_id'], $round['property_id'], $by);
            Audit::log('TASK', $t['task_id'], 'TASK_' . $decision, null,
                array_merge(['role' => $role['role_name'], 'round_no' => $round['round_no'], 'remarks' => $remarks],
                    !empty($extra['attachments']) ? ['attachments' => $extra['attachments']] : []), $round['property_id']);
            if ($outcome) {
                Audit::log('ROUND', $round['round_id'], 'ROUND_CLOSED', null, ['result' => $outcome], $round['property_id']);
            }

            $prop = Property::find($round['property_id']);
            if ($decision === 'REJECTED') {
                Notify::send(Notify::panelUsers('OIC'), [
                    'type' => 'VERIFICATION', 'tone' => 'danger', 'icon' => 'fa-circle-xmark', 'title' => $role['role_name'] . ' rejected Plot ' . $prop['plot_no'],
                    'message' => 'Round R' . $round['round_no'] . ': "' . $remarks . '". Property is NOT READY — re-send after correction.', 'link' => '/oic/properties/' . $prop['property_id'],
                ]);
                Notify::send($prop['created_by'], [
                    'type' => 'VERIFICATION', 'tone' => 'danger', 'icon' => 'fa-circle-xmark', 'title' => 'Plot ' . $prop['plot_no'] . ' is Not Ready',
                    'message' => $role['role_name'] . ': ' . $remarks, 'link' => '/data-entry/properties',
                ]);
                foreach (Task::where(['round_id' => $round['round_id'], 'task_status' => 'PENDING']) as $o) {
                    Notify::send(array_column(TaskAssignee::where(['task_id' => $o['task_id']]), 'user_id'), [
                        'type' => 'TASK', 'icon' => 'fa-minus', 'title' => 'Response not required — Plot ' . $prop['plot_no'],
                        'message' => 'Round R' . $round['round_no'] . ' was closed because ' . $role['role_name'] . ' rejected it.', 'link' => '/role/tasks/' . $o['task_id'],
                    ]);
                }
            } elseif ($outcome === 'READY') {
                Notify::send(array_merge(Notify::panelUsers('OIC'), [$prop['created_by']]), [
                    'type' => 'VERIFICATION', 'tone' => 'success', 'icon' => 'fa-gavel', 'title' => 'Plot ' . $prop['plot_no'] . ' is AUCTION READY',
                    'message' => 'All sections approved round R' . $round['round_no'] . '. Valid for ' . self::validityDays() . ' days.', 'link' => '/oic/properties/' . $prop['property_id'],
                ]);
            }
            $msg = $role['role_name'] . ': ' . label($decision) . '.';
            if ($outcome === 'READY') {
                $msg .= ' All roles approved — property is now AUCTION READY.';
            } elseif ($outcome === 'NOT_READY') {
                $msg .= ' Round closed as NOT READY — property is back with OIC for re-verification.';
            }
            return ['ok' => true, 'message' => $msg, 'outcome' => $outcome];
        });
    }

    /** Close the round when any section rejected (NOT_READY) or all approved (READY). */
    public static function consolidate(int $roundId, int $propertyId, int $by): ?string
    {
        $tasks = Task::withRoles($roundId);
        $rejected = array_filter($tasks, static fn ($t) => in_array($t['task_status'], ['REJECTED', 'NO_RESPONSE'], true));
        $pending = array_filter($tasks, static fn ($t) => $t['task_status'] === 'PENDING');
        if ($rejected) {
            Round::update($roundId, [
                'round_status' => 'CLOSED', 'result' => 'NOT_READY',
                'result_note' => implode("\n", array_map(static fn ($t) => '• ' . $t['role_name'] . ': ' . ($t['remarks'] ?: 'No response'), $rejected)),
                'closed_by' => $by, 'closed_at' => Clock::now(),
            ]);
            Property::update($propertyId, ['current_status' => 'NOT_READY', 'updated_by' => $by]);
            return 'NOT_READY';
        }
        if (!$pending) {
            $till = today(self::validityDays());
            Round::update($roundId, [
                'round_status' => 'CLOSED', 'result' => 'READY', 'result_note' => 'All roles approved',
                'closed_by' => $by, 'closed_at' => Clock::now(), 'valid_from' => today(), 'valid_till' => $till,
            ]);
            Property::update($propertyId, ['current_status' => 'AUCTION_READY', 'ready_round_id' => $roundId, 'valid_till' => $till, 'updated_by' => $by]);
            return 'READY';
        }
        return null;
    }

    /** SLA reminders for a role user: one OVERDUE notification per overdue task. */
    public static function overdueReminders(int $userId): void
    {
        foreach (Task::overdueFor($userId, today()) as $x) {
            Notify::send($userId, [
                'type' => 'TASK', 'tone' => 'danger', 'icon' => 'fa-stopwatch', 'ref' => 'OVERDUE-' . $x['task_id'],
                'title' => 'SLA overdue — Plot ' . $x['plot_no'],
                'message' => 'Your verification response was due on ' . dmy($x['sla_due_date']) . '. Please respond as soon as possible.',
                'link' => '/role/tasks/' . $x['task_id'],
            ]);
        }
    }

    /** OIC reminder: one REVERIFY notification per property entering the re-verification window. */
    public static function reverifyReminders(int $userId): void
    {
        foreach (Property::readyValidTill('<=', self::horizon()) as $p) {
            Notify::send($userId, [
                'type' => 'VERIFICATION', 'tone' => 'warning', 'icon' => 'fa-calendar-day', 'ref' => 'REVERIFY-' . $p['property_id'] . '-' . $p['valid_till'],
                'title' => 'Re-verification due — Plot ' . $p['plot_no'],
                'message' => 'Auction readiness ends on ' . dmy($p['valid_till']) . '. Auction it before then or start re-verification.',
                'link' => '/oic/properties/' . $p['property_id'],
            ]);
        }
    }
}
