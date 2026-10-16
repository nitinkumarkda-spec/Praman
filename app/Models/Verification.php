<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Audit;
use App\Core\Config;
use App\Core\Model;
use PDOException;
use Throwable;

/**
 * Verification engine: rounds (R1, R2 …), one task per role, consolidation.
 *
 *  - Send:     LISTED / NOT_READY property → new OPEN round + one task per mapped role → UNDER_VERIFICATION
 *  - Respond:  task APPROVED / REJECTED (remarks mandatory for REJECTED)
 *  - Close:    any REJECTED → round CLOSED (NOT_READY), property NOT_READY (OIC can resend → new round)
 *              all APPROVED → round CLOSED (READY), property AUCTION_READY, validity window set
 */
final class Verification extends Model
{
    public const SENDABLE = ['LISTED', 'NOT_READY'];

    /**
     * Start a new verification round for one property.
     * @return array{ok: bool, message: string, round_no?: int}
     */
    public function send(int $propertyId, int $by, ?string $reason): array
    {
        $this->beginTransaction();
        try {
            $p = $this->fetch(
                'SELECT p.property_id, p.plot_no, p.current_status, sc.scheme_name, se.sector_name
                   FROM {properties} p JOIN {schemes} sc ON sc.scheme_id = p.scheme_id JOIN {scheme_sectors} se ON se.sector_id = p.sector_id
                  WHERE p.property_id = :id AND p.is_active = 1 FOR UPDATE',
                ['id' => $propertyId]
            );
            if ($p === null) {
                $this->rollBack();
                return ['ok' => false, 'message' => "Property #{$propertyId} not found."];
            }
            $label = "Plot {$p['plot_no']} ({$p['scheme_name']} / {$p['sector_name']})";

            if (!in_array($p['current_status'], self::SENDABLE, true)) {
                $this->rollBack();
                return ['ok' => false, 'message' => "{$label}: status is " . label($p['current_status']) . ' — only Listed / Not Ready can be sent.'];
            }
            if ($this->fetchColumn("SELECT 1 FROM {verification_rounds} WHERE property_id = :id AND round_status = 'OPEN'", ['id' => $propertyId]) !== null) {
                $this->rollBack();
                return ['ok' => false, 'message' => "{$label}: a verification round is already open."];
            }
            if ($p['current_status'] === 'NOT_READY' && ($reason === null || trim($reason) === '')) {
                $this->rollBack();
                return ['ok' => false, 'message' => "{$label}: reason is required to re-send a Not Ready property."];
            }

            // Active snapshot rows, only roles that are still active and users still active in that role
            $rows = $this->fetchAll(
                "SELECT r.role_id, r.role_name, r.is_mandatory, r.sla_days, pru.user_id
                   FROM {property_role_users} pru
                   JOIN {roles} r ON r.role_id = pru.role_id AND r.is_active = 1
                   JOIN {users} u ON u.user_id = pru.user_id AND u.is_active = 1 AND u.panel = 'ROLE_USER' AND u.role_id = pru.role_id
                  WHERE pru.property_id = :id AND pru.is_active = 1
                  ORDER BY r.sort_order",
                ['id' => $propertyId]
            );
            $roles = [];
            foreach ($rows as $r) {
                $roles[(int) $r['role_id']] ??= ['name' => $r['role_name'], 'sla' => (int) $r['sla_days'], 'users' => []];
                $roles[(int) $r['role_id']]['users'][] = (int) $r['user_id'];
            }
            $missing = array_column($this->fetchAll(
                'SELECT role_name FROM {roles} WHERE is_active = 1 AND is_mandatory = 1 AND role_id NOT IN (' . ($roles === [] ? '0' : implode(',', array_map('intval', array_keys($roles)))) . ') ORDER BY sort_order'
            ), 'role_name');
            if ($missing !== []) {
                $this->rollBack();
                return ['ok' => false, 'message' => "{$label}: no active user assigned for mandatory role(s): " . implode(', ', $missing) . '.'];
            }
            if ($roles === []) {
                $this->rollBack();
                return ['ok' => false, 'message' => "{$label}: no role users assigned."];
            }

            $roundNo = (int) $this->fetchColumn('SELECT COALESCE(MAX(round_no), 0) + 1 FROM {verification_rounds} WHERE property_id = :id', ['id' => $propertyId]);
            $maxSla = max(array_column($roles, 'sla'));
            $roundId = $this->insert('verification_rounds', [
                'property_id'       => $propertyId,
                'round_no'          => $roundNo,
                'round_status'      => 'OPEN',
                'initiated_by'      => $by,
                'due_date'          => date('Y-m-d', strtotime("+{$maxSla} days")),
                'reinitiate_reason' => $roundNo > 1 ? mb_substr(trim((string) $reason), 0, 255) : null,
            ]);
            foreach ($roles as $roleId => $role) {
                $taskId = $this->insert('verification_tasks', [
                    'round_id'     => $roundId,
                    'role_id'      => $roleId,
                    'task_status'  => 'PENDING',
                    'sla_due_date' => date('Y-m-d', strtotime("+{$role['sla']} days")),
                ]);
                foreach (array_unique($role['users']) as $uid) {
                    $this->insert('verification_task_assignees', ['task_id' => $taskId, 'user_id' => $uid]);
                }
            }
            $this->run(
                "UPDATE {properties} SET current_status = 'UNDER_VERIFICATION', current_round_id = :r, updated_by = :by WHERE property_id = :id",
                ['r' => $roundId, 'by' => $by, 'id' => $propertyId]
            );
            $this->commit();

            Audit::log('ROUND', $roundId, $roundNo > 1 ? 'REINITIATED' : 'ROUND_INITIATED',
                ['status' => $p['current_status']],
                ['round_no' => $roundNo, 'roles' => array_column($roles, 'name'), 'reason' => $reason],
                $propertyId);
            return ['ok' => true, 'message' => "{$label}: sent as Round R{$roundNo} to " . count($roles) . ' role(s).', 'round_no' => $roundNo];
        } catch (PDOException $e) {
            $this->rollBack();
            if ($e->getCode() === '23000') { // unique(one open round) race
                return ['ok' => false, 'message' => "Property #{$propertyId}: a verification round is already open."];
            }
            throw $e;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /** All rounds of a property, newest first. */
    public function rounds(int $propertyId): array
    {
        return $this->fetchAll(
            'SELECT vr.*, iu.name AS initiated_by_name, cu.name AS closed_by_name
               FROM {verification_rounds} vr
               JOIN {users} iu ON iu.user_id = vr.initiated_by
               LEFT JOIN {users} cu ON cu.user_id = vr.closed_by
              WHERE vr.property_id = :id ORDER BY vr.round_no DESC',
            ['id' => $propertyId]
        );
    }

    /** Tasks of a round with assignees and responder. */
    public function tasks(int $roundId): array
    {
        return $this->fetchAll(
            "SELECT t.*, r.role_name, r.is_mandatory, ru.name AS responded_by_name,
                    (SELECT GROUP_CONCAT(CONCAT(u.name, ' (', u.designation, ')') ORDER BY u.name SEPARATOR ', ')
                       FROM {verification_task_assignees} a JOIN {users} u ON u.user_id = a.user_id WHERE a.task_id = t.task_id) AS assignees
               FROM {verification_tasks} t
               JOIN {roles} r ON r.role_id = t.role_id
               LEFT JOIN {users} ru ON ru.user_id = t.responded_by
              WHERE t.round_id = :id
              ORDER BY r.sort_order, r.role_name",
            ['id' => $roundId]
        );
    }

    /**
     * Record a role response (TEMPORARY simulate action today; the Role User panel will call the same method).
     * @return array{ok: bool, message: string}
     */
    public function respond(int $taskId, string $decision, ?string $remarks, int $by, bool $simulated): array
    {
        if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) {
            return ['ok' => false, 'message' => 'Invalid decision.'];
        }
        $remarks = $remarks !== null ? trim($remarks) : null;
        if ($decision === 'REJECTED' && ($remarks === null || $remarks === '')) {
            return ['ok' => false, 'message' => 'Rejection reason (remark) is mandatory.'];
        }

        $this->beginTransaction();
        try {
            $task = $this->fetch(
                'SELECT t.*, vr.property_id, vr.round_status, vr.round_no, r.role_name
                   FROM {verification_tasks} t
                   JOIN {verification_rounds} vr ON vr.round_id = t.round_id
                   JOIN {roles} r ON r.role_id = t.role_id
                  WHERE t.task_id = :id FOR UPDATE',
                ['id' => $taskId]
            );
            if ($task === null) {
                $this->rollBack();
                return ['ok' => false, 'message' => 'Task not found.'];
            }
            // lock the round row too, so two responses cannot close it twice
            $this->fetch('SELECT round_id FROM {verification_rounds} WHERE round_id = :id FOR UPDATE', ['id' => $task['round_id']]);
            if ($task['round_status'] !== 'OPEN') {
                $this->rollBack();
                return ['ok' => false, 'message' => 'This round is already closed.'];
            }
            if ($task['task_status'] !== 'PENDING') {
                $this->rollBack();
                return ['ok' => false, 'message' => "{$task['role_name']} has already responded (" . label($task['task_status']) . ').'];
            }

            $this->run(
                'UPDATE {verification_tasks} SET task_status = :s, remarks = :rm, responded_by = :by, responded_at = NOW(), is_simulated = :sim
                  WHERE task_id = :id',
                ['s' => $decision, 'rm' => $remarks !== '' ? $remarks : null, 'by' => $by, 'sim' => $simulated ? 1 : 0, 'id' => $taskId]
            );
            $outcome = $this->consolidate((int) $task['round_id'], (int) $task['property_id'], $by);
            $this->commit();

            Audit::log('TASK', $taskId, $simulated ? 'TASK_SIMULATED_' . $decision : 'TASK_' . $decision, null,
                ['role' => $task['role_name'], 'round_no' => (int) $task['round_no'], 'remarks' => $remarks], (int) $task['property_id']);
            if ($outcome !== null) {
                Audit::log('ROUND', (int) $task['round_id'], 'ROUND_CLOSED', null, ['result' => $outcome], (int) $task['property_id']);
            }

            $msg = "{$task['role_name']}: " . label($decision) . '.';
            if ($outcome === 'READY') {
                $msg .= ' All roles approved — property is now AUCTION READY.';
            } elseif ($outcome === 'NOT_READY') {
                $msg .= ' Round closed as NOT READY — property is back with OIC for re-verification.';
            }
            return ['ok' => true, 'message' => $msg];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Close the round when its result is known. Returns READY / NOT_READY when closed, null while still pending.
     * Must run inside the caller's transaction.
     */
    private function consolidate(int $roundId, int $propertyId, int $by): ?string
    {
        $tasks = $this->fetchAll(
            'SELECT t.task_status, t.remarks, r.role_name FROM {verification_tasks} t JOIN {roles} r ON r.role_id = t.role_id
              WHERE t.round_id = :id ORDER BY r.sort_order',
            ['id' => $roundId]
        );
        $rejected = array_filter($tasks, static fn ($t) => in_array($t['task_status'], ['REJECTED', 'NO_RESPONSE'], true));
        $pending = array_filter($tasks, static fn ($t) => $t['task_status'] === 'PENDING');

        if ($rejected !== []) {
            $reasons = implode("\n", array_map(static fn ($t) => '• ' . $t['role_name'] . ': ' . ($t['remarks'] ?? 'No response'), $rejected));
            $this->run(
                "UPDATE {verification_rounds} SET round_status = 'CLOSED', consolidated_result = 'NOT_READY', consolidation_remarks = :rm,
                        closed_by = :by, closed_at = NOW() WHERE round_id = :id",
                ['rm' => $reasons, 'by' => $by, 'id' => $roundId]
            );
            $this->run(
                "UPDATE {properties} SET current_status = 'NOT_READY', updated_by = :by WHERE property_id = :id",
                ['by' => $by, 'id' => $propertyId]
            );
            return 'NOT_READY';
        }
        if ($pending === []) {
            $days = (int) Config::get('verification.validity_days', 180);
            $from = date('Y-m-d');
            $till = date('Y-m-d', strtotime("+{$days} days"));
            $this->run(
                "UPDATE {verification_rounds} SET round_status = 'CLOSED', consolidated_result = 'READY',
                        consolidation_remarks = 'All roles approved', closed_by = :by, closed_at = NOW(),
                        valid_from = :vf, valid_till = :vt WHERE round_id = :id",
                ['by' => $by, 'vf' => $from, 'vt' => $till, 'id' => $roundId]
            );
            $this->run(
                "UPDATE {properties} SET current_status = 'AUCTION_READY', last_verified_round_id = :r, verification_valid_till = :vt, updated_by = :by
                  WHERE property_id = :id",
                ['r' => $roundId, 'vt' => $till, 'by' => $by, 'id' => $propertyId]
            );
            return 'READY';
        }
        return null;
    }
}
