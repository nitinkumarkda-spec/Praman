<?php
/** @var array $task  @var array $history */
use App\Core\Lookups;

$canRespond = $task['view_status'] === 'PENDING';
$late = $canRespond && $task['sla_due_date'] < date('Y-m-d');
$decision = (string) old('decision', '');
?>
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Plot <?= e($task['plot_no']) ?> <?= status_badge($task['view_status']) ?></h2>
        <p class="page-subtitle"><?= e($task['property_code'] . ' · ' . $task['scheme_name'] . ' · ' . $task['sector_name'] . ' · Round R' . $task['round_no'] . ' · ' . $task['role_name']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/role/tasks', ['status' => 'pending'])) ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <span>Back to my tasks</span></a>
    </div>
</div>

<?php if ($task['view_status'] === 'NOT_REQUIRED'): ?>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>This round was already closed (another section rejected the property), so your response is no longer required. If the OIC re-sends it, you will get a new task.</span></div>
<?php elseif ($late): ?>
    <div class="alert alert-warning"><i class="fa-solid fa-stopwatch fa-shake" aria-hidden="true"></i>
        <span>SLA due date <?= e(fmt_date($task['sla_due_date'])) ?> has passed. Please respond as soon as possible.</span></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-building" aria-hidden="true"></i> Property details</h3></div>
        <div class="card-body">
            <dl class="detail-list">
                <dt>Property code</dt><dd class="mono"><?= e($task['property_code']) ?></dd>
                <dt>Scheme</dt><dd><?= e($task['scheme_name'] . ' (' . $task['scheme_code'] . ')') ?></dd>
                <dt>Scheme type</dt><dd><?= e(Lookups::SCHEME_TYPES[$task['scheme_type']] ?? $task['scheme_type']) ?></dd>
                <dt>Zone</dt><dd><?= e($task['zone']) ?></dd>
                <dt>Sector</dt><dd><?= e($task['sector_name']) ?></dd>
                <dt>Plot no.</dt><dd><strong><?= e($task['plot_no']) ?></strong></dd>
                <dt>Plot type</dt><dd><?= e(Lookups::PROPERTY_TYPES[$task['property_type']] ?? $task['property_type']) ?></dd>
                <dt>Area</dt><dd><?= e(fmt_num($task['area_value'], 2) . ' ' . (Lookups::AREA_UNITS[$task['area_unit']] ?? '')) ?>
                    <?php if ($task['area_unit'] === 'SQFT'): ?> <span class="text-muted">(= <?= e(fmt_num($task['area_sqm'], 2)) ?> sq m)</span><?php endif; ?></dd>
                <?php if ($task['property_remarks']): ?><dt>Remarks</dt><dd><?= e($task['property_remarks']) ?></dd><?php endif; ?>
            </dl>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Verification task</h3></div>
        <div class="card-body">
            <dl class="detail-list">
                <dt>Section</dt><dd><?= e($task['role_name']) ?></dd>
                <dt>Round</dt><dd>R<?= (int) $task['round_no'] ?> <?= status_badge($task['round_status']) ?></dd>
                <dt>Sent by</dt><dd><?= e($task['initiated_by_name'] . ' (' . $task['initiated_by_designation'] . ')') ?></dd>
                <dt>Received on</dt><dd><?= e(fmt_datetime($task['initiated_at'])) ?></dd>
                <dt>Due (SLA)</dt><dd class="<?= $late ? 'text-danger' : '' ?>"><?= e(fmt_date($task['sla_due_date'])) ?></dd>
                <dt>Assigned to</dt><dd><?= e($task['assignees'] ?? '—') ?> <small class="text-muted">(any one may respond)</small></dd>
                <?php if ($task['reinitiate_reason']): ?><dt>Re-verification reason</dt><dd><?= e($task['reinitiate_reason']) ?></dd><?php endif; ?>
                <?php if (!$canRespond && $task['responded_at']): ?>
                    <dt>Response</dt><dd><?= status_badge($task['task_status']) ?></dd>
                    <dt>Responded by</dt><dd><?= e(($task['responded_by_name'] ?? '') . ' · ' . fmt_datetime($task['responded_at'])) ?>
                        <?php if ((int) $task['is_simulated'] === 1): ?> <span class="badge badge-gold">simulated by OIC</span><?php endif; ?></dd>
                    <dt>Remark</dt><dd><?= e($task['remarks'] ?? '—') ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>
</div>

<?php if ($canRespond): ?>
    <div class="card response-card" id="respond">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-gavel" aria-hidden="true"></i> Your decision</h3>
            <span class="text-muted small">Once submitted, the response cannot be changed.</span></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('/role/tasks/' . $task['task_id'] . '/respond')) ?>" novalidate data-validate data-response-form
                  data-confirm="Submit your decision? It cannot be changed afterwards." data-confirm-ok="Yes, submit">
                <?= csrf_field() ?>
                <div class="form-group">
                    <p class="form-label">Is property ko auction ke liye <?= e($task['role_name']) ?> ki taraf se clear karte hain? <span class="required">*</span></p>
                    <div class="decision-row">
                        <label class="decision decision-approve">
                            <input type="radio" name="decision" value="APPROVED"<?= $decision === 'APPROVED' ? ' checked' : '' ?> data-rules="required" data-label="Approve or Reject">
                            <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> <strong>Approve</strong><small>Record is clear — no objection</small></span>
                        </label>
                        <label class="decision decision-reject">
                            <input type="radio" name="decision" value="REJECTED"<?= $decision === 'REJECTED' ? ' checked' : '' ?>>
                            <span><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> <strong>Reject</strong><small>Objection found — reason required</small></span>
                        </label>
                    </div>
                    <div class="invalid-feedback"><?= e(error('decision')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="remarks">Remark <span class="required" data-remark-required<?= $decision === 'REJECTED' ? '' : ' hidden' ?>>* (reason for rejection)</span></label>
                    <textarea id="remarks" name="remarks" class="form-control<?= invalid('remarks') ?>" rows="3" maxlength="1000"
                              placeholder="e.g. Atikraman — temporary tin shed 8 sq m on front side / Record clear, file no. 123"
                              data-rules="max:1000|text" data-label="Remark"><?= e(old('remarks')) ?></textarea>
                    <div class="invalid-feedback"><?= e(error('remarks')) ?></div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" data-loading-text="Submitting…"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span>Submit decision</span></button>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> <?= e($task['role_name']) ?> — earlier rounds of this property</h3></div>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead><tr><th>Round</th><th>Response</th><th>Remark</th><th>By / Date</th><th>Round result</th></tr></thead>
            <tbody>
            <?php if ($history === []): ?>
                <?= partial('partials/empty_state', ['colspan' => 5, 'message' => 'This is the first round for this property']) ?>
            <?php else: foreach ($history as $h): ?>
                <tr>
                    <td data-label="Round">R<?= (int) $h['round_no'] ?></td>
                    <td data-label="Response"><?= status_badge($h['task_status']) ?></td>
                    <td data-label="Remark"><?= e($h['remarks'] ?? '—') ?></td>
                    <td data-label="By / Date"><?= e(($h['responded_by_name'] ?? '—') . ' · ' . fmt_date($h['responded_at'])) ?></td>
                    <td data-label="Result"><?= $h['consolidated_result'] ? status_badge($h['consolidated_result']) : '—' ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
