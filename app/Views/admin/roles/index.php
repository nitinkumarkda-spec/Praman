<?php
/** @var \App\Core\Paginator $p */
$status = $p->filter('status', '');
ob_start(); ?>
<select name="status" class="form-select" aria-label="Status" data-auto-submit>
    <option value="">All status</option>
    <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
    <option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>>Inactive</option>
</select>
<?php $filtersHtml = (string) ob_get_clean(); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Role Master</h2>
        <p class="page-subtitle">Each role is one verification section (Engineering, Sale, Legal …). Users are mapped to roles in the User Master.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/admin/roles/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> <span>Add Role</span></a>
    </div>
</div>

<div class="card">
    <?= partial('partials/list_toolbar', ['p' => $p, 'placeholder' => 'Search role name or code…', 'filtersHtml' => $filtersHtml]) ?>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
            <tr>
                <?= th_sort($p, 'order', '#') ?>
                <?= th_sort($p, 'name', 'Role / Section') ?>
                <?= th_sort($p, 'code', 'Code') ?>
                <?= th_sort($p, 'sla', 'SLA (days)') ?>
                <th>Mandatory</th>
                <?= th_sort($p, 'users', 'Users') ?>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($p->items === []): ?>
                <?= partial('partials/empty_state', ['colspan' => 8, 'filtered' => $p->hasFilters(), 'message' => 'No roles yet']) ?>
            <?php else: foreach ($p->items as $r): ?>
                <tr>
                    <td data-label="Order"><?= (int) $r['sort_order'] ?></td>
                    <td data-label="Role">
                        <strong><?= e($r['role_name']) ?></strong>
                        <?php if ($r['role_name_hi']): ?><span class="cell-sub"><?= e($r['role_name_hi']) ?></span><?php endif; ?>
                    </td>
                    <td data-label="Code"><span class="mono"><?= e($r['role_code']) ?></span></td>
                    <td data-label="SLA"><?= (int) $r['sla_days'] ?></td>
                    <td data-label="Mandatory"><?= (int) $r['is_mandatory'] === 1 ? '<span class="badge badge-gold"><i class="fa-solid fa-star" aria-hidden="true"></i> Mandatory</span>' : '<span class="badge badge-muted">Optional</span>' ?></td>
                    <td data-label="Users">
                        <a href="<?= e(url('/admin/users', ['role_id' => $r['role_id']])) ?>"><?= (int) $r['active_users'] ?> active</a>
                        <span class="cell-sub"><?= (int) $r['users_count'] ?> total</span>
                    </td>
                    <td data-label="Status"><?= active_badge($r['is_active']) ?></td>
                    <td class="actions">
                        <div class="btn-group">
                            <a href="<?= e(url('/admin/roles/' . $r['role_id'] . '/edit')) ?>" class="btn btn-light btn-sm" title="Edit"><i class="fa-solid fa-pen" aria-hidden="true"></i> <span>Edit</span></a>
                            <form method="post" action="<?= e(url('/admin/roles/' . $r['role_id'] . '/toggle')) ?>"
                                  data-confirm="<?= e((int) $r['is_active'] === 1
                                      ? 'Deactivate role "' . $r['role_name'] . '"? It will not be used for new schemes and verification rounds.'
                                      : 'Activate role "' . $r['role_name'] . '"?') ?>"
                                  data-confirm-variant="<?= (int) $r['is_active'] === 1 ? 'danger' : 'success' ?>">
                                <?= csrf_field() ?>
                                <?php if ((int) $r['is_active'] === 1): ?>
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
