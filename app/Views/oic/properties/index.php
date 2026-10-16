<?php
/** @var \App\Core\Paginator $p  @var array $schemes  @var array $sectors */
use App\Core\Lookups;

$sendable = ['LISTED', 'NOT_READY'];
ob_start(); ?>
<select name="scheme_id" class="form-select" aria-label="Scheme" data-auto-submit data-reset-on-change="sector_id">
    <?= Lookups::options($schemes, $p->filter('scheme_id'), 'All schemes') ?>
</select>
<select name="sector_id" class="form-select" aria-label="Sector" data-auto-submit<?= $sectors === [] ? ' disabled' : '' ?>>
    <?= Lookups::options($sectors, $p->filter('sector_id'), $sectors === [] ? 'Select scheme first' : 'All sectors') ?>
</select>
<select name="type" class="form-select" aria-label="Plot type" data-auto-submit>
    <?= Lookups::options(Lookups::PROPERTY_TYPES, $p->filter('type'), 'All types') ?>
</select>
<select name="status" class="form-select" aria-label="Status" data-auto-submit>
    <?= Lookups::options(Lookups::PROPERTY_STATUSES, $p->filter('status'), 'All status') ?>
</select>
<?php $filtersHtml = (string) ob_get_clean(); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Properties – Verification</h2>
        <p class="page-subtitle">Send <strong>Listed</strong> properties for verification to all mapped role users. <strong>Not Ready</strong> ones can be re-sent (new round) with a reason.</p>
    </div>
</div>

<div class="card">
    <?= partial('partials/list_toolbar', ['p' => $p, 'placeholder' => 'Search plot no., code, scheme, sector…', 'filtersHtml' => $filtersHtml]) ?>

    <form method="post" action="<?= e(url('/oic/properties/send')) ?>" id="bulk-send-form"
          data-confirm="Send the selected properties for verification? A new round will be created for each and all mapped role users will get a task."
          data-confirm-ok="Yes, send" data-bulk-form data-validate novalidate>
        <?= csrf_field() ?>
        <div class="bulk-bar">
            <span class="bulk-count"><i class="fa-solid fa-square-check" aria-hidden="true"></i> <strong data-bulk-count="ids[]">0</strong> selected</span>
            <input type="text" name="reason" class="form-control form-control-sm bulk-reason" maxlength="255"
                   placeholder="Re-verification reason (required for Not Ready properties)" aria-label="Re-verification reason" data-bulk-reason>
            <button type="submit" class="btn btn-primary btn-sm" data-bulk-requires="ids[]" disabled data-loading-text="Sending…">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span>Send selected for verification</span>
            </button>
        </div>

        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                <tr>
                    <th class="cell-check"><input type="checkbox" data-check-all="ids[]" aria-label="Select all sendable properties"></th>
                    <?= th_sort($p, 'code', 'Code') ?>
                    <?= th_sort($p, 'scheme', 'Scheme') ?>
                    <?= th_sort($p, 'sector', 'Sector') ?>
                    <?= th_sort($p, 'plot', 'Plot') ?>
                    <?= th_sort($p, 'type', 'Type') ?>
                    <?= th_sort($p, 'area', 'Area (sq m)') ?>
                    <th>Assigned role users</th>
                    <?= th_sort($p, 'status', 'Status') ?>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($p->items === []): ?>
                    <?= partial('partials/empty_state', ['colspan' => 10, 'filtered' => $p->hasFilters(), 'message' => 'No properties yet — Data Entry adds them']) ?>
                <?php else: foreach ($p->items as $r):
                    $canSend = in_array($r['current_status'], $sendable, true);
                    $rowClass = $r['current_status'] === 'AUCTION_READY' ? 'row-success' : ($r['current_status'] === 'NOT_READY' ? 'row-danger' : ''); ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="cell-check" data-label="Select">
                            <?php if ($canSend): ?>
                                <input type="checkbox" name="ids[]" value="<?= (int) $r['property_id'] ?>" aria-label="Select plot <?= e($r['plot_no']) ?>"
                                       data-status="<?= e($r['current_status']) ?>">
                            <?php else: ?>
                                <input type="checkbox" disabled aria-label="Not sendable">
                            <?php endif; ?>
                        </td>
                        <td data-label="Code"><a href="<?= e(url('/oic/properties/' . $r['property_id'])) ?>" class="mono"><?= e($r['property_code']) ?></a></td>
                        <td data-label="Scheme"><?= e($r['scheme_name']) ?></td>
                        <td data-label="Sector"><?= e($r['sector_name']) ?></td>
                        <td data-label="Plot"><strong><?= e($r['plot_no']) ?></strong></td>
                        <td data-label="Type"><?= e(Lookups::PROPERTY_TYPES[$r['property_type']] ?? $r['property_type']) ?></td>
                        <td data-label="Area"><?= e(fmt_num($r['area_sqm'], 2)) ?></td>
                        <td data-label="Role users">
                            <span class="badge badge-muted" title="<?= e($r['role_users'] ?? '') ?>"><i class="fa-solid fa-user-tie" aria-hidden="true"></i> <?= (int) $r['role_count'] ?> roles</span>
                            <span class="cell-sub truncate" title="<?= e($r['role_users'] ?? '') ?>"><?= e($r['role_users'] ?? '—') ?></span>
                        </td>
                        <td data-label="Status">
                            <?= status_badge($r['current_status']) ?>
                            <?php if ($r['round_no'] !== null): ?>
                                <span class="cell-sub">R<?= (int) $r['round_no'] ?><?= $r['current_status'] === 'UNDER_VERIFICATION' ? ' · ' . (int) $r['tasks_approved'] . '/' . (int) $r['tasks_total'] . ' approved' : '' ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <div class="btn-group">
                                <a href="<?= e(url('/oic/properties/' . $r['property_id'])) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-eye" aria-hidden="true"></i> <span>Status</span></a>
                                <?php if ($r['current_status'] === 'LISTED'): ?>
                                    <button type="submit" form="send-<?= (int) $r['property_id'] ?>" class="btn btn-primary btn-sm" data-loading-text="Sending…">
                                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span>Send</span>
                                    </button>
                                <?php elseif ($r['current_status'] === 'NOT_READY'): ?>
                                    <a href="<?= e(url('/oic/properties/' . $r['property_id'])) ?>#resend" class="btn btn-warning btn-sm"><i class="fa-solid fa-rotate" aria-hidden="true"></i> <span>Re-send</span></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php foreach ($p->items as $r): if ($r['current_status'] !== 'LISTED') { continue; } ?>
        <form method="post" action="<?= e(url('/oic/properties/send')) ?>" id="send-<?= (int) $r['property_id'] ?>" hidden
              data-confirm="<?= e('Send plot ' . $r['plot_no'] . ' (' . $r['scheme_name'] . ' / ' . $r['sector_name'] . ') for verification to ' . (int) $r['role_count'] . ' role(s)?') ?>"
              data-confirm-ok="Yes, send">
            <?= csrf_field() ?>
            <input type="hidden" name="ids[]" value="<?= (int) $r['property_id'] ?>">
        </form>
    <?php endforeach; ?>

    <?= partial('partials/pagination', ['p' => $p]) ?>
</div>
