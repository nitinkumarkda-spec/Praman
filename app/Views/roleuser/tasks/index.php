<?php
/** @var \App\Core\Paginator $p  @var array $schemes */
use App\Core\Lookups;

$statusTabs = [
    ''         => ['All', 'fa-list'],
    'pending'  => ['Pending', 'fa-hourglass-half'],
    'overdue'  => ['Overdue', 'fa-stopwatch'],
    'approved' => ['Approved', 'fa-check'],
    'rejected' => ['Rejected', 'fa-xmark'],
    'closed'   => ['Not required', 'fa-minus'],
];
$current = (string) $p->filter('status', '');
$today = date('Y-m-d');

ob_start(); ?>
<select name="scheme_id" class="form-select" aria-label="Scheme" data-auto-submit>
    <?= Lookups::options($schemes, $p->filter('scheme_id'), 'All schemes') ?>
</select>
<?php if ($current !== ''): ?><input type="hidden" name="status" value="<?= e($current) ?>"><?php endif; ?>
<?php $filtersHtml = (string) ob_get_clean(); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> My Tasks</h2>
        <p class="page-subtitle">Properties assigned to you for verification. Open a task to Approve or Reject it.</p>
    </div>
</div>

<nav class="tabs" aria-label="Task status">
    <?php foreach ($statusTabs as $key => [$text, $icon]): ?>
        <a href="<?= e(url('/role/tasks', array_filter(['status' => $key, 'scheme_id' => $p->filter('scheme_id')]))) ?>"
           class="tab<?= $current === $key ? ' active' : '' ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i> <?= e($text) ?>
        </a>
    <?php endforeach; ?>
</nav>

<div class="card">
    <?= partial('partials/list_toolbar', ['p' => $p, 'placeholder' => 'Search plot no., property code, scheme, sector…', 'filtersHtml' => $filtersHtml]) ?>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
            <tr>
                <th>Property</th>
                <?= th_sort($p, 'scheme', 'Scheme / Sector') ?>
                <?= th_sort($p, 'plot', 'Plot') ?>
                <th>Area</th>
                <th>Round</th>
                <?= th_sort($p, 'sent', 'Received') ?>
                <?= th_sort($p, 'due', 'Due (SLA)') ?>
                <?= th_sort($p, 'status', 'Status') ?>
                <th class="text-right">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($p->items === []): ?>
                <?= partial('partials/empty_state', ['colspan' => 9, 'filtered' => $p->hasFilters(), 'message' => 'No task assigned to you yet']) ?>
            <?php else: foreach ($p->items as $t):
                $isPending = $t['view_status'] === 'PENDING';
                $late = $isPending && $t['sla_due_date'] < $today; ?>
                <tr class="<?= $t['view_status'] === 'APPROVED' ? 'row-success' : ($t['view_status'] === 'REJECTED' ? 'row-danger' : '') ?>">
                    <td data-label="Property"><span class="mono"><?= e($t['property_code']) ?></span></td>
                    <td data-label="Scheme / Sector"><?= e($t['scheme_name']) ?><span class="cell-sub"><?= e($t['sector_name']) ?></span></td>
                    <td data-label="Plot"><strong><?= e($t['plot_no']) ?></strong><span class="cell-sub"><?= e(Lookups::PROPERTY_TYPES[$t['property_type']] ?? '') ?></span></td>
                    <td data-label="Area" class="nowrap"><?= e(fmt_num($t['area_sqm'], 2)) ?> sq m</td>
                    <td data-label="Round">R<?= (int) $t['round_no'] ?></td>
                    <td data-label="Received"><?= e(fmt_date($t['initiated_at'])) ?></td>
                    <td data-label="Due">
                        <span class="<?= $late ? 'text-danger' : '' ?>"><?= e(fmt_date($t['sla_due_date'])) ?></span>
                        <?php if ($late): ?><span class="badge badge-danger"><i class="fa-solid fa-stopwatch" aria-hidden="true"></i> Overdue</span><?php endif; ?>
                    </td>
                    <td data-label="Status">
                        <?= status_badge($t['view_status']) ?>
                        <?php if (!$isPending && $t['responded_at']): ?><span class="cell-sub"><?= e(($t['responded_by_name'] ?? '') . ' · ' . fmt_date($t['responded_at'])) ?></span><?php endif; ?>
                    </td>
                    <td class="actions">
                        <?php if ($isPending): ?>
                            <a href="<?= e(url('/role/tasks/' . $t['task_id'])) ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> <span>Verify</span></a>
                        <?php else: ?>
                            <a href="<?= e(url('/role/tasks/' . $t['task_id'])) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-eye" aria-hidden="true"></i> <span>View</span></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= partial('partials/pagination', ['p' => $p]) ?>
</div>
