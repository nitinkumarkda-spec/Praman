<?php
/**
 * @var array $property  @var array $roleUsers  @var array $rounds  @var bool $simulateEnabled
 */
use App\Core\Lookups;

$current = $rounds[0] ?? null;
$status = $property['current_status'];
$rejectedTasks = $current && $current['consolidated_result'] === 'NOT_READY'
    ? array_filter($current['tasks'], static fn ($t) => $t['task_status'] === 'REJECTED' || $t['task_status'] === 'NO_RESPONSE')
    : [];
?>
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Plot <?= e($property['plot_no']) ?> <?= status_badge($status) ?></h2>
        <p class="page-subtitle"><?= e($property['property_code'] . ' · ' . $property['scheme_name'] . ' · ' . $property['sector_name']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/oic/properties')) ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <span>Back to list</span></a>
        <?php if ($status === 'LISTED'): ?>
            <form method="post" action="<?= e(url('/oic/properties/send')) ?>" data-confirm="Send this property for verification to all mapped role users?" data-confirm-ok="Yes, send">
                <?= csrf_field() ?>
                <input type="hidden" name="ids[]" value="<?= (int) $property['property_id'] ?>">
                <button type="submit" class="btn btn-primary" data-loading-text="Sending…"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span>Send for verification</span></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($status === 'AUCTION_READY'): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span><strong>Ready for Auction.</strong> All roles approved in round R<?= (int) $current['round_no'] ?>. Valid till <?= e(fmt_date($property['verification_valid_till'])) ?>.</span></div>
<?php elseif ($status === 'NOT_READY'): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-xmark fa-beat" aria-hidden="true"></i>
        <span><strong>Not Ready — round R<?= (int) $current['round_no'] ?> rejected.</strong> Reasons:
            <ul class="reason-list">
                <?php foreach ($rejectedTasks as $t): ?>
                    <li><strong><?= e($t['role_name']) ?>:</strong> <?= e($t['remarks'] ?? 'No response') ?> <small>(<?= e($t['responded_by_name'] ?? '') ?>, <?= e(fmt_datetime($t['responded_at'])) ?>)</small></li>
                <?php endforeach; ?>
            </ul>
        </span></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-building" aria-hidden="true"></i> Property details</h3></div>
        <div class="card-body">
            <dl class="detail-list">
                <dt>Property code</dt><dd class="mono"><?= e($property['property_code']) ?></dd>
                <dt>Scheme</dt><dd><?= e($property['scheme_name'] . ' (' . $property['scheme_code'] . ')') ?></dd>
                <dt>Zone</dt><dd><?= e($property['zone']) ?></dd>
                <dt>Sector</dt><dd><?= e($property['sector_name']) ?></dd>
                <dt>Plot no.</dt><dd><?= e($property['plot_no']) ?></dd>
                <dt>Plot type</dt><dd><?= e(Lookups::PROPERTY_TYPES[$property['property_type']] ?? $property['property_type']) ?></dd>
                <dt>Area</dt><dd><?= e(fmt_num($property['area_value'], 2) . ' ' . (Lookups::AREA_UNITS[$property['area_unit']] ?? '')) ?>
                    <?php if ($property['area_unit'] === 'SQFT'): ?> <span class="text-muted">(= <?= e(fmt_num($property['area_sqm'], 2)) ?> sq m)</span><?php endif; ?></dd>
                <dt>Created</dt><dd><?= e(fmt_datetime($property['created_at']) . ' · ' . $property['created_by_name']) ?></dd>
                <?php if ($property['remarks']): ?><dt>Remarks</dt><dd><?= e($property['remarks']) ?></dd><?php endif; ?>
            </dl>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-user-tie" aria-hidden="true"></i> Assigned role users</h3>
            <span class="text-muted small">Snapshot taken when the property was created</span></div>
        <div class="card-body p-0">
            <div class="table-wrap">
                <table class="table table-stack">
                    <thead><tr><th>Role</th><th>User</th><th>Designation</th></tr></thead>
                    <tbody>
                    <?php if ($roleUsers === []): ?>
                        <?= partial('partials/empty_state', ['colspan' => 3, 'message' => 'No role users assigned']) ?>
                    <?php else: foreach ($roleUsers as $ru): ?>
                        <tr>
                            <td data-label="Role"><?= e($ru['role_name']) ?><?= (int) $ru['role_active'] === 1 ? '' : ' <span class="badge badge-muted">role inactive</span>' ?></td>
                            <td data-label="User"><?= e($ru['name']) ?><?= (int) $ru['user_active'] === 1 ? '' : ' <span class="badge badge-danger">inactive</span>' ?></td>
                            <td data-label="Designation"><?= e($ru['designation']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($status === 'NOT_READY'): ?>
    <div class="card" id="resend">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Send again for verification (new round R<?= (int) $current['round_no'] + 1 ?>)</h3></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('/oic/properties/send')) ?>" novalidate data-validate
                  data-confirm="Start a new verification round? The old round stays in history." data-confirm-ok="Yes, re-send" data-confirm-variant="warning">
                <?= csrf_field() ?>
                <input type="hidden" name="ids[]" value="<?= (int) $property['property_id'] ?>">
                <div class="form-group">
                    <label class="form-label" for="reason">Reason for re-verification <span class="required">*</span></label>
                    <input type="text" id="reason" name="reason" class="form-control" maxlength="255" placeholder="e.g. Encroachment removed, record corrected"
                           data-rules="required|min:5|max:255|text" data-label="Reason">
                    <div class="invalid-feedback"></div>
                </div>
                <button type="submit" class="btn btn-warning" data-loading-text="Sending…"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span>Re-send for verification</span></button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($rounds === []): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fa-solid fa-hourglass-start empty-icon" aria-hidden="true"></i>
                <p class="empty-title">Not sent for verification yet</p>
                <p class="text-muted">Use “Send for verification” to create round R1.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($rounds as $i => $round):
    $isOpen = $round['round_status'] === 'OPEN';
    $approved = count(array_filter($round['tasks'], static fn ($t) => $t['task_status'] === 'APPROVED')); ?>
    <details class="card round-card"<?= $i === 0 ? ' open' : '' ?>>
        <summary class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Round R<?= (int) $round['round_no'] ?>
                <?= status_badge($round['round_status']) ?>
                <?php if ($round['consolidated_result']): ?><?= status_badge($round['consolidated_result']) ?><?php endif; ?>
            </h3>
            <span class="text-muted small">
                <?= $approved ?>/<?= count($round['tasks']) ?> approved ·
                Sent <?= e(fmt_datetime($round['initiated_at'])) ?> by <?= e($round['initiated_by_name']) ?>
                <?= $round['closed_at'] ? ' · Closed ' . e(fmt_datetime($round['closed_at'])) : ' · Due ' . e(fmt_date($round['due_date'])) ?>
            </span>
        </summary>
        <?php if ($round['reinitiate_reason']): ?>
            <p class="round-note"><i class="fa-solid fa-quote-left" aria-hidden="true"></i> Re-verification reason: <?= e($round['reinitiate_reason']) ?></p>
        <?php endif; ?>
        <?php if ($isOpen && $simulateEnabled): ?>
            <div class="simulate-note"><i class="fa-solid fa-flask" aria-hidden="true"></i>
                <strong>TEMPORARY — testing only:</strong> “Simulate” records a role response on behalf of the role user until the Role User panel is built.</div>
        <?php endif; ?>
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                <tr><th>Role</th><th>Assigned user(s)</th><th>Status</th><th>Remark</th><th>Date</th><?= $isOpen && $simulateEnabled ? '<th class="text-right">Simulate response</th>' : '' ?></tr>
                </thead>
                <tbody>
                <?php foreach ($round['tasks'] as $t):
                    $pendingClosed = $t['task_status'] === 'PENDING' && !$isOpen; ?>
                    <tr class="<?= $t['task_status'] === 'APPROVED' ? 'row-success' : ($t['task_status'] === 'REJECTED' ? 'row-danger' : '') ?>">
                        <td data-label="Role"><strong><?= e($t['role_name']) ?></strong><?= (int) $t['is_mandatory'] === 1 ? '' : ' <span class="badge badge-muted">optional</span>' ?></td>
                        <td data-label="Assigned"><?= e($t['assignees'] ?? '—') ?></td>
                        <td data-label="Status">
                            <?php if ($pendingClosed): ?>
                                <span class="badge badge-muted"><i class="fa-solid fa-minus" aria-hidden="true"></i> Not required (round closed)</span>
                            <?php else: ?>
                                <?= status_badge($t['task_status']) ?>
                                <?php if ((int) $t['is_simulated'] === 1): ?><span class="badge badge-gold" title="Recorded via temporary simulate action"><i class="fa-solid fa-flask" aria-hidden="true"></i> simulated</span><?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Remark"><?= e($t['remarks'] ?? '—') ?></td>
                        <td data-label="Date"><?= $t['responded_at'] ? e(fmt_datetime($t['responded_at'])) . '<span class="cell-sub">' . e($t['responded_by_name'] ?? '') . '</span>' : '<span class="text-muted">SLA ' . e(fmt_date($t['sla_due_date'])) . '</span>' ?></td>
                        <?php if ($isOpen && $simulateEnabled): ?>
                            <td class="actions">
                                <?php if ($t['task_status'] === 'PENDING'): ?>
                                    <form method="post" action="<?= e(url('/oic/tasks/' . $t['task_id'] . '/simulate')) ?>" class="simulate-form" data-simulate-form
                                          data-confirm="<?= e('Record a SIMULATED response for ' . $t['role_name'] . '?') ?>" data-confirm-variant="warning">
                                        <?= csrf_field() ?>
                                        <input type="text" name="remarks" class="form-control form-control-sm" maxlength="1000" placeholder="Remark (required for reject)" aria-label="Remark">
                                        <button type="submit" name="decision" value="APPROVED" class="btn btn-success btn-xs"><i class="fa-solid fa-check" aria-hidden="true"></i> <span>Approve</span></button>
                                        <button type="submit" name="decision" value="REJECTED" class="btn btn-danger btn-xs"><i class="fa-solid fa-xmark" aria-hidden="true"></i> <span>Reject</span></button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">Responded</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($round['consolidation_remarks'] && $round['consolidated_result'] === 'NOT_READY'): ?>
            <div class="card-footer"><strong>Result:</strong> <?= nl2br(e($round['consolidation_remarks'])) ?></div>
        <?php elseif ($round['consolidated_result'] === 'READY'): ?>
            <div class="card-footer"><strong>Result:</strong> All roles approved · valid <?= e(fmt_date($round['valid_from'])) ?> – <?= e(fmt_date($round['valid_till'])) ?></div>
        <?php endif; ?>
    </details>
<?php endforeach; ?>
