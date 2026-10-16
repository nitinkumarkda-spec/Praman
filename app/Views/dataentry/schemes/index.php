<?php
/** @var \App\Core\Paginator $p */
use App\Core\Lookups;

ob_start(); ?>
<select name="type" class="form-select" aria-label="Scheme type" data-auto-submit>
    <?= Lookups::options(Lookups::SCHEME_TYPES, $p->filter('type'), 'All types') ?>
</select>
<select name="status" class="form-select" aria-label="Status" data-auto-submit>
    <?= Lookups::options(['active' => 'Active', 'inactive' => 'Inactive'], $p->filter('status'), 'All status') ?>
</select>
<?php $filtersHtml = (string) ob_get_clean(); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-map" aria-hidden="true"></i> Scheme Master</h2>
        <p class="page-subtitle">Schemes with their sectors and the role users who will verify properties in them.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/data-entry/schemes/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> <span>Create Scheme</span></a>
    </div>
</div>

<div class="card">
    <?= partial('partials/list_toolbar', ['p' => $p, 'placeholder' => 'Search scheme name, code or zone…', 'filtersHtml' => $filtersHtml]) ?>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
            <tr>
                <?= th_sort($p, 'code', 'Code') ?>
                <?= th_sort($p, 'name', 'Scheme') ?>
                <?= th_sort($p, 'type', 'Type') ?>
                <?= th_sort($p, 'zone', 'Zone') ?>
                <?= th_sort($p, 'year', 'Year') ?>
                <?= th_sort($p, 'sectors', 'Sectors') ?>
                <th>Role mapping</th>
                <?= th_sort($p, 'properties', 'Properties') ?>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($p->items === []): ?>
                <?= partial('partials/empty_state', ['colspan' => 10, 'filtered' => $p->hasFilters(), 'message' => 'No schemes yet — create the first one']) ?>
            <?php else: foreach ($p->items as $s): ?>
                <tr>
                    <td data-label="Code"><span class="mono"><?= e($s['scheme_code']) ?></span></td>
                    <td data-label="Scheme"><strong><?= e($s['scheme_name']) ?></strong></td>
                    <td data-label="Type"><?= e(Lookups::SCHEME_TYPES[$s['scheme_type']] ?? $s['scheme_type']) ?></td>
                    <td data-label="Zone"><?= e($s['zone']) ?></td>
                    <td data-label="Year"><?= (int) $s['year_of_scheme'] ?></td>
                    <td data-label="Sectors">
                        <span class="badge badge-info"><?= (int) $s['sector_count'] ?></span>
                        <span class="cell-sub truncate" title="<?= e($s['sector_names'] ?? '') ?>"><?= e($s['sector_names'] ?? '—') ?></span>
                    </td>
                    <td data-label="Role mapping"><?= (int) $s['mapped_roles'] ?> roles<span class="cell-sub"><?= (int) $s['mapped_users'] ?> users</span></td>
                    <td data-label="Properties"><?= (int) $s['property_count'] ?></td>
                    <td data-label="Status"><?= active_badge($s['is_active']) ?></td>
                    <td class="actions">
                        <div class="btn-group">
                            <a href="<?= e(url('/data-entry/schemes/' . $s['scheme_id'] . '/edit')) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-pen" aria-hidden="true"></i> <span>Edit</span></a>
                            <form method="post" action="<?= e(url('/data-entry/schemes/' . $s['scheme_id'] . '/toggle')) ?>"
                                  data-confirm="<?= e((int) $s['is_active'] === 1 ? 'Deactivate scheme "' . $s['scheme_name'] . '"? No new property can be added in it.' : 'Activate scheme "' . $s['scheme_name'] . '"?') ?>"
                                  data-confirm-variant="<?= (int) $s['is_active'] === 1 ? 'danger' : 'success' ?>">
                                <?= csrf_field() ?>
                                <?php if ((int) $s['is_active'] === 1): ?>
                                    <button type="submit" class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-toggle-off" aria-hidden="true"></i> <span>Deactivate</span></button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-toggle-on" aria-hidden="true"></i> <span>Activate</span></button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= partial('partials/pagination', ['p' => $p]) ?>
</div>
