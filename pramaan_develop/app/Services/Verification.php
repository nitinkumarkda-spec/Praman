<?php
declare(strict_types=1);

namespace App\Services;

use App\Codes\PropertyStatus;
use App\Codes\RoundStatus;
use App\Codes\TaskStatus;
use App\Core\Clock;
use App\Core\Database;
use App\Models\Property;
use App\Models\Role;
use App\Models\Round;
use App\Models\Scheme;
use App\Models\SchemeUser;
use App\Models\Sector;
use App\Models\Status;
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
    public const SENDABLE = PropertyStatus::SENDABLE;

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
        return $p['status_id'] === PropertyStatus::AUCTION_READY && !empty($p['valid_till']) && $p['valid_till'] <= self::horizon();
    }

    /** Can OIC start a new round now? Listed / Not Ready / Expired, or Auction Ready inside the window. */
    public static function canSend(array $p): bool
    {
        return in_array($p['status_id'], self::SENDABLE, true) || self::isDue($p);
    }

    /** Auction Ready past its validity -> EXPIRED (safe to repeat). */
    public static function expireDue(): int
    {
        $n = 0;
        foreach (Property::readyValidTill('<', today()) as $p) {
            Database::transaction(static function () use ($p): void {
                Property::update($p['property_id'], ['status_id' => PropertyStatus::EXPIRED]);
                Audit::log('PROPERTY', $p['property_id'], 'VALIDITY_EXPIRED', ['status' => PropertyStatus::AUCTION_READY],
                    ['status' => PropertyStatus::EXPIRED, 'valid_till' => $p['valid_till'], 'round_id' => $p['ready_round_id']], $p['property_id'], Audit::system());
                Notify::send(Notify::permissionUsers('verification.view'), [
                    'type' => 'VERIFICATION', 'tone' => 'danger', 'icon' => 'fa-calendar-xmark', 'ref' => 'EXPIRED-' . $p['property_id'] . '-' . $p['valid_till'],
                    'title' => 'Validity expired — Plot ' . $p['plot_no'],
                    'message' => 'Auction readiness ended on ' . dmy($p['valid_till']) . '. Send it for periodic re-verification before the next auction.',
                    'link' => '/admin/masters/properties/' . $p['property_id'],
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
            $se = $p['sector_id'] ? Sector::find($p['sector_id']) : null; // sector is optional
            $where = $sc['scheme_name'] . ($se ? ' / ' . $se['sector_name'] : '');
            $lbl = 'Plot ' . $p['plot_no'] . ' (' . $where . ')';
            $reason = $reason !== null ? trim($reason) : null;

            if (!self::canSend($p)) {
                return ['ok' => false, 'message' => $lbl . ': status is ' . (Status::find($p['status_id'])['name'] ?? '—') . ' — only Listed / Not Ready / Validity Expired (or Auction Ready due for re-verification) can be sent.'];
            }
            if (Round::exists(['property_id' => $p['property_id'], 'round_status' => RoundStatus::OPEN])) {
                return ['ok' => false, 'message' => $lbl . ': a verification round is already open.'];
            }
            if ($p['status_id'] === PropertyStatus::NOT_READY && ($reason === null || $reason === '')) {
                return ['ok' => false, 'message' => $lbl . ': reason is required to re-send a Not Ready property.'];
            }
            if (($reason === null || $reason === '') && $p['status_id'] !== PropertyStatus::LISTED) {
                $reason = 'Periodic re-verification — validity ' . ($p['status_id'] === PropertyStatus::EXPIRED ? 'expired' : 'ends') . ' on ' . dmy($p['valid_till']);
            }

            // Sequential Workflow: Active verification roles with status_id > 0, ordered by sort_order ASC
            $roles = array_values(array_filter(
                Role::where(['is_active' => 1], 'sort_order'),
                static fn ($r) => !empty($r['status_id']) && (int) $r['status_id'] > 0
            ));
            usort($roles, static fn ($a, $b) => (int) $a['sort_order'] <=> (int) $b['sort_order']);

            if (!$roles) {
                return ['ok' => false, 'message' => $lbl . ': no active verification roles with assigned status found.'];
            }

            // Step 1 role (lowest sort_order)
            $firstRole = $roles[0];
            $firstRoleId = (int) $firstRole['role_id'];
            $firstStatusId = (int) $firstRole['status_id'];

            // Get users for Step 1 role mapped to this scheme (fallback to active users of this role)
            $mappedUsers = array_column(
                SchemeUser::where(['scheme_id' => $p['scheme_id'], 'role_id' => $firstRoleId, 'is_active' => 1]),
                'user_id'
            );
            if (empty($mappedUsers)) {
                $mappedUsers = array_column(
                    User::where(['is_active' => 1, 'role_id' => $firstRoleId]),
                    'user_id'
                );
            }
            if (empty($mappedUsers)) {
                return ['ok' => false, 'message' => $lbl . ': no active user assigned for "' . $firstRole['role_name'] . '". Please assign users in Scheme Master.'];
            }

            $roundNo = Round::nextOrder(['property_id' => $p['property_id']], 'round_no');
            $roundId = Round::insert([
                'property_id' => $p['property_id'], 'round_no' => $roundNo, 'round_status' => RoundStatus::OPEN, 'initiated_by' => $by,
                'initiated_at' => Clock::now(), 'due_date' => today(30),
                'resend_reason' => $roundNo > 1 ? mb_substr((string) $reason, 0, 255) : null,
            ]);

            // Create Task for the FIRST role ONLY
            $taskId = Task::insert([
                'round_id' => $roundId,
                'role_id' => $firstRoleId,
                'task_status' => TaskStatus::PENDING,
                'sla_due_date' => today(30),
            ]);
            foreach ($mappedUsers as $uid) {
                TaskAssignee::insert(['task_id' => $taskId, 'user_id' => (int) $uid]);
            }

            // Update property status directly to the first role's status (e.g. Forward to Sale)
            $prevStatus = $p['status_id'];
            Property::update($p['property_id'], [
                'status_id' => $firstStatusId,
                'current_round_id' => $roundId,
                'updated_by' => $by,
            ]);

            $statusObj = Status::find($firstStatusId);
            $statusName = $statusObj ? $statusObj['name'] : $firstRole['role_name'];

            Notify::send($mappedUsers, [
                'type' => 'TASK', 'tone' => $roundNo > 1 ? 'warning' : 'info', 'icon' => 'fa-clipboard-list',
                'title' => ($roundNo > 1 ? 'Re-verification task' : 'New verification task') . ' — Plot ' . $p['plot_no'],
                'message' => $where . ' · Step 1: ' . $firstRole['role_name'] . ' (Status: ' . $statusName . ')'
                    . ($roundNo > 1 && $reason ? ' · Reason: ' . $reason : ''),
                'link' => '/admin/masters/properties/' . $p['property_id'],
            ]);

            if ((int) $p['created_by'] !== $by) {
                Notify::send($p['created_by'], [
                    'type' => 'VERIFICATION', 'icon' => 'fa-paper-plane', 'title' => 'Plot ' . $p['plot_no'] . ' sent for verification',
                    'message' => 'Round R' . $roundNo . ' initiated — forwarded to ' . $firstRole['role_name'] . ' (Status: ' . $statusName . ').',
                    'link' => '/data-entry/properties',
                ]);
            }

            Audit::log('ROUND', $roundId, $roundNo > 1 ? 'REINITIATED' : 'ROUND_INITIATED', ['status' => $prevStatus],
                ['round_no' => $roundNo, 'role' => $firstRole['role_name'], 'status' => $statusName, 'reason' => $reason ?: null], $p['property_id']);

            return ['ok' => true, 'message' => $lbl . ': sent to ' . $firstRole['role_name'] . ' (Status: ' . $statusName . ').', 'round_no' => $roundNo];
        });
    }

    /**
     * A section's decision. $extra: ['attachments' => [file names]] for the audit row.
     * Updates status step-by-step in ascending order of sort_order.
     * @return array{ok:bool,message:string,outcome?:?string}
     */
    public static function respond(int $taskId, string $decision, ?string $remarks, int $by, array $extra = []): array
    {
        if (!in_array($decision, TaskStatus::DECISIONS, true)) {
            return ['ok' => false, 'message' => 'Invalid decision.'];
        }
        $remarks = $remarks === null ? null : trim($remarks);
        if ($decision === TaskStatus::REJECTED && !$remarks) {
            return ['ok' => false, 'message' => 'Rejection reason (remark) is mandatory.'];
        }
        return Database::transaction(static function () use ($taskId, $decision, $remarks, $by, $extra): array {
            $t = Task::findForUpdate($taskId);
            if (!$t) {
                return ['ok' => false, 'message' => 'Task not found.'];
            }
            $round = Round::find($t['round_id']);
            $role = Role::find($t['role_id']);
            if ($round['round_status'] !== RoundStatus::OPEN) {
                return ['ok' => false, 'message' => 'This round is already closed.'];
            }
            if ($t['task_status'] !== TaskStatus::PENDING) {
                return ['ok' => false, 'message' => $role['role_name'] . ' has already responded (' . label($t['task_status']) . ').'];
            }

            // 1. Mark current task with decision
            Task::update($t['task_id'], [
                'task_status' => $decision,
                'remarks' => $remarks ?: null,
                'responded_by' => $by,
                'responded_at' => Clock::now(),
            ]);

            $prop = Property::find($round['property_id']);
            $sc = Scheme::find($prop['scheme_id']);
            $se = $prop['sector_id'] ? Sector::find($prop['sector_id']) : null;
            $where = ($sc ? $sc['scheme_name'] : '') . ($se ? ' / ' . $se['sector_name'] : '');

            Audit::log('TASK', $t['task_id'], 'TASK_' . $decision, null,
                array_merge(['role' => $role['role_name'], 'round_no' => $round['round_no'], 'remarks' => $remarks],
                    !empty($extra['attachments']) ? ['attachments' => $extra['attachments']] : []), $round['property_id']);

            // 2. IF REJECTED: Mark property Not Ready and close round
            if ($decision === TaskStatus::REJECTED) {
                Round::update($round['round_id'], [
                    'round_status' => RoundStatus::CLOSED,
                    'result' => RoundStatus::NOT_READY,
                    'result_note' => '• ' . $role['role_name'] . ': ' . ($remarks ?: 'Objection / Rejection'),
                    'closed_by' => $by,
                    'closed_at' => Clock::now(),
                ]);
                Property::update($prop['property_id'], [
                    'status_id' => PropertyStatus::NOT_READY,
                    'updated_by' => $by,
                ]);

                Audit::log('ROUND', $round['round_id'], 'ROUND_CLOSED', null, ['result' => RoundStatus::NOT_READY], $round['property_id']);

                Notify::send(Notify::permissionUsers('verification.send'), [
                    'type' => 'VERIFICATION', 'tone' => 'danger', 'icon' => 'fa-circle-xmark',
                    'title' => $role['role_name'] . ' rejected Plot ' . $prop['plot_no'],
                    'message' => 'Round R' . $round['round_no'] . ': "' . $remarks . '". Property is NOT READY — re-send after correction.',
                    'link' => '/admin/masters/properties/' . $prop['property_id'],
                ]);
                if ((int) $prop['created_by'] !== $by) {
                    Notify::send($prop['created_by'], [
                        'type' => 'VERIFICATION', 'tone' => 'danger', 'icon' => 'fa-circle-xmark',
                        'title' => 'Plot ' . $prop['plot_no'] . ' is Not Ready',
                        'message' => $role['role_name'] . ': ' . $remarks,
                        'link' => '/data-entry/properties',
                    ]);
                }

                return [
                    'ok' => true,
                    'message' => $role['role_name'] . ': Objection recorded. Property status updated to NOT READY.',
                    'outcome' => RoundStatus::NOT_READY,
                ];
            }

            // 3. IF APPROVED: Find NEXT role in ascending sort_order hierarchy
            $roles = array_values(array_filter(
                Role::where(['is_active' => 1], 'sort_order'),
                static fn ($r) => !empty($r['status_id']) && (int) $r['status_id'] > 0
            ));
            usort($roles, static fn ($a, $b) => (int) $a['sort_order'] <=> (int) $b['sort_order']);

            $currentRoleIndex = null;
            foreach ($roles as $idx => $er) {
                if ((int) $er['role_id'] === (int) $t['role_id']) {
                    $currentRoleIndex = $idx;
                    break;
                }
            }

            $targetStatusId = !empty($extra['status_id']) ? (int) $extra['status_id'] : null;
            $nextRole = null;
            if ($targetStatusId !== null) {
                foreach ($roles as $r) {
                    if ((int) $r['status_id'] === $targetStatusId) {
                        $nextRole = $r;
                        break;
                    }
                }
                if ($nextRole === null) {
                    // Selected status is not mapped to an active role (e.g. Allocated, Vacant, etc.)
                    $statusObj = Status::find($targetStatusId);
                    $statusName = $statusObj ? $statusObj['name'] : 'Status #' . $targetStatusId;

                    Property::update($prop['property_id'], [
                        'status_id' => $targetStatusId,
                        'updated_by' => $by,
                    ]);
                    Round::update($round['round_id'], [
                        'round_status' => RoundStatus::CLOSED,
                        'result' => RoundStatus::READY,
                        'result_note' => 'Status updated to "' . $statusName . '". Verification round completed.',
                        'closed_by' => $by,
                        'closed_at' => Clock::now(),
                    ]);

                    Audit::log('ROUND', $round['round_id'], 'STATUS_UPDATED', null,
                        ['status_id' => $targetStatusId, 'status' => $statusName, 'by_role' => $role['role_name']], $prop['property_id']);

                    return [
                        'ok' => true,
                        'message' => 'Property status updated to "' . $statusName . '" successfully.',
                        'outcome' => RoundStatus::READY,
                    ];
                }
            } else {
                $nextRole = ($currentRoleIndex !== null && isset($roles[$currentRoleIndex + 1]))
                    ? $roles[$currentRoleIndex + 1]
                    : null;
            }

            // CASE 3A: Next role exists -> Shift forward to next role
            if ($nextRole !== null) {
                $nextRoleId = (int) $nextRole['role_id'];
                $nextStatusId = (int) $nextRole['status_id'];

                $nextUsers = array_column(
                    SchemeUser::where(['scheme_id' => $prop['scheme_id'], 'role_id' => $nextRoleId, 'is_active' => 1]),
                    'user_id'
                );
                if (empty($nextUsers)) {
                    $nextUsers = array_column(
                        User::where(['is_active' => 1, 'role_id' => $nextRoleId]),
                        'user_id'
                    );
                }

                $nextTaskId = Task::insert([
                    'round_id' => $round['round_id'],
                    'role_id' => $nextRoleId,
                    'task_status' => TaskStatus::PENDING,
                    'sla_due_date' => today(30),
                ]);
                foreach ($nextUsers as $uid) {
                    TaskAssignee::insert([
                        'task_id' => $nextTaskId,
                        'user_id' => (int) $uid,
                    ]);
                }

                // Update property status to next role's status_id
                Property::update($prop['property_id'], [
                    'status_id' => $nextStatusId,
                    'updated_by' => $by,
                ]);

                $nextStatusObj = Status::find($nextStatusId);
                $nextStatusName = $nextStatusObj ? $nextStatusObj['name'] : $nextRole['role_name'];

                Notify::send($nextUsers, [
                    'type' => 'TASK', 'tone' => 'info', 'icon' => 'fa-clipboard-list',
                    'title' => 'Verification task — Plot ' . $prop['plot_no'],
                    'message' => $where . ' · Forwarded from ' . $role['role_name'] . ' to ' . $nextRole['role_name'] . ' (Status: ' . $nextStatusName . ')',
                    'link' => '/admin/masters/properties/' . $prop['property_id'],
                ]);

                Notify::send(Notify::permissionUsers('verification.send'), [
                    'type' => 'VERIFICATION', 'icon' => 'fa-arrow-right',
                    'title' => 'Plot ' . $prop['plot_no'] . ' cleared by ' . $role['role_name'],
                    'message' => 'Status updated to "' . $nextStatusName . '" and forwarded to ' . $nextRole['role_name'] . '.',
                    'link' => '/admin/masters/properties/' . $prop['property_id'],
                ]);

                Audit::log('ROUND', $round['round_id'], 'TASK_FORWARDED', null,
                    ['from_role' => $role['role_name'], 'to_role' => $nextRole['role_name'], 'status_id' => $nextStatusId], $prop['property_id']);

                return [
                    'ok' => true,
                    'message' => $role['role_name'] . ': Approved. Property status updated to "' . $nextStatusName . '" and forwarded to ' . $nextRole['role_name'] . '.',
                    'outcome' => null,
                ];
            }

            // CASE 3B: All roles in hierarchy approved -> AUCTION READY!
            $till = today(self::validityDays());
            Round::update($round['round_id'], [
                'round_status' => RoundStatus::CLOSED,
                'result' => RoundStatus::READY,
                'result_note' => 'All roles approved',
                'closed_by' => $by,
                'closed_at' => Clock::now(),
                'valid_from' => today(),
                'valid_till' => $till,
            ]);
            Property::update($prop['property_id'], [
                'status_id' => PropertyStatus::AUCTION_READY,
                'ready_round_id' => $round['round_id'],
                'valid_till' => $till,
                'updated_by' => $by,
            ]);

            Audit::log('ROUND', $round['round_id'], 'ROUND_CLOSED', null, ['result' => RoundStatus::READY], $prop['property_id']);

            Notify::send(array_merge(Notify::permissionUsers('verification.send'), [$prop['created_by']]), [
                'type' => 'VERIFICATION', 'tone' => 'success', 'icon' => 'fa-gavel',
                'title' => 'Plot ' . $prop['plot_no'] . ' is AUCTION READY',
                'message' => 'All verification sections (' . implode(' → ', array_column($roles, 'role_name')) . ') approved round R' . $round['round_no'] . '. Valid for ' . self::validityDays() . ' days.',
                'link' => '/admin/masters/properties/' . $prop['property_id'],
            ]);

            return [
                'ok' => true,
                'message' => $role['role_name'] . ': Approved. All verification stages cleared — Property is now AUCTION READY!',
                'outcome' => RoundStatus::READY,
            ];
        });
    }

    /** Close the round when any section rejected (NOT_READY) or all approved (READY). */
    public static function consolidate(int $roundId, int $propertyId, int $by): ?string
    {
        $tasks = Task::withRoles($roundId);
        $rejected = array_filter($tasks, static fn ($t) => in_array($t['task_status'], TaskStatus::BLOCKING, true));
        $pending = array_filter($tasks, static fn ($t) => $t['task_status'] === TaskStatus::PENDING);
        if ($rejected) {
            Round::update($roundId, [
                'round_status' => RoundStatus::CLOSED, 'result' => RoundStatus::NOT_READY,
                'result_note' => implode("\n", array_map(static fn ($t) => '• ' . $t['role_name'] . ': ' . ($t['remarks'] ?: 'No response'), $rejected)),
                'closed_by' => $by, 'closed_at' => Clock::now(),
            ]);
            Property::update($propertyId, ['status_id' => PropertyStatus::NOT_READY, 'updated_by' => $by]);
            return RoundStatus::NOT_READY;
        }
        if (!$pending) {
            $till = today(self::validityDays());
            Round::update($roundId, [
                'round_status' => RoundStatus::CLOSED, 'result' => RoundStatus::READY, 'result_note' => 'All roles approved',
                'closed_by' => $by, 'closed_at' => Clock::now(), 'valid_from' => today(), 'valid_till' => $till,
            ]);
            Property::update($propertyId, ['status_id' => PropertyStatus::AUCTION_READY, 'ready_round_id' => $roundId, 'valid_till' => $till, 'updated_by' => $by]);
            return RoundStatus::READY;
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
                'link' => '/admin/masters/properties/' . $x['property_id'],
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
                'link' => '/admin/masters/properties/' . $p['property_id'],
            ]);
        }
    }
}
