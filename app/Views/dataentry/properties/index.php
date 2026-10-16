<?php
/** @var \App\Core\Paginator $p  @var array $schemes  @var array $sectors */
use App\Core\Lookups;

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
        <h2 class="page-title"><i class="fa-solid fa-building" aria-hidden="true"></i> Property Register</h2>
        <p class="page-subtitle">Vacant properties. Editable only while status is <strong>Listed</strong>.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/data-entry/properties/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> <span>Add Property</span></a>
    </div>
</div>

<div class="card">
    <?= partial('partials/list_toolbar', ['p' => $p, 'placeholder' => 'Search plot no., property code, scheme, sector…', 'filtersHtml' => $filtersHtml]) ?>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
            <tr>
                <?= th_sort($p, 'code', 'Code') ?>
                <?= th_sort($p, 'scheme', 'Scheme') ?>
                <?= th_sort($p, 'sector', 'Sector') ?>
                <?= th_sort($p, 'plot', 'Plot no.') ?>
                <?= th_sort($p, 'type', 'Type') ?>
                <?= th_sort($p, 'area', 'Area') ?>
                <th>Role users</th>
                <?= th_sort($p, 'status', 'Status') ?>
                <?= th_sort($p, 'created', 'Created') ?>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($p->items === []): ?>
                <?= partial('partials/empty_state', ['colspan' => 10, 'filtered' => $p->hasFilters(), 'message' => 'No properties yet']) ?>
            <?php else: foreach ($p->items as $r): ?>
                <tr>
                    <td data-label="Code"><span class="mono"><?= e($r['property_code']) ?></span></td>
                    <td data-label="Scheme"><?= e($r['scheme_name']) ?><span class="cell-sub"><?= e($r['scheme_code']) ?></span></td>
                    <td data-label="Sector"><?= e($r['sector_name']) ?></td>
                    <td data-label="Plot no."><strong><?= e($r['plot_no']) ?></strong></td>
                    <td data-label="Type"><?= e(Lookups::PROPERTY_TYPES[$r['property_type']] ?? $r['property_type']) ?></td>
                    <td data-label="Area" class="nowrap"><?= e(fmt_num($r['area_value'], 2)) ?> <?= e($r['area_unit'] === 'SQFT' ? 'sq ft' : 'sq m') ?>
                        <?php if ($r['area_unit'] === 'SQFT'): ?><span class="cell-sub">= <?= e(fmt_num($r['area_sqm'], 2)) ?> sq m</span><?php endif; ?></td>
                    <td data-label="Role users"><span class="badge badge-muted" title="<?= e($r['role_users'] ?? '') ?>"><i class="fa-solid fa-user-tie" aria-hidden="true"></i> <?= (int) $r['role_count'] ?> roles</span></td>
                    <td data-label="Status"><?= status_badge($r['current_status']) ?></td>
                    <td data-label="Created"><?= e(fmt_date($r['created_at'])) ?><span class="cell-sub"><?= e($r['created_by_name']) ?></span></td>
                    <td class="actions">
                        <?php if ($r['current_status'] === 'LISTED'): ?>
                            <a href="<?= e(url('/data-entry/properties/' . $r['property_id'] . '/edit')) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-pen" aria-hidden="true"></i> <span>Edit</span></a>
                        <?php else: ?>
                            <span class="text-muted small"><i class="fa-solid fa-lock" aria-hidden="true"></i> Locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= partial('partials/pagination', ['p' => $p]) ?>
</div>
