<?php
/** @var \App\Core\Paginator $p  @var array<int,string> $roles */
use App\Core\Auth;
use App\Core\Lookups;

$panels = Auth::PANELS;
ob_start(); ?>
<select name="panel" class="form-select" aria-label="Panel" data-auto-submit>
    <?= Lookups::options($panels, $p->filter('panel'), 'All panels') ?>
</select>
<select name="role_id" class="form-select" aria-label="Role" data-auto-submit>
    <?= Lookups::options($roles, $p->filter('role_id'), 'All roles') ?>
</select>
<select name="status" class="form-select" aria-label="Status" data-auto-submit>
    <?= Lookups::options(['active' => 'Active', 'inactive' => 'Inactive', 'locked' => 'Locked'], $p->filter('status'), 'All status') ?>
</select>
<?php $filtersHtml = (string) ob_get_clean(); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-users" aria-hidden="true"></i> User Master</h2>
        <p class="page-subtitle">Panel users and role users (e.g. engineer-1 under Engineering). Many users can belong to one role.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/admin/users/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> <span>Add User</span></a>
    </div>
</div>

<div class="card">
    <?= partial('partials/list_toolbar', ['p' => $p, 'placeholder' => 'Search name, username, emp code, mobile, email…', 'filtersHtml' => $filtersHtml]) ?>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
            <tr>
                <?= th_sort($p, 'name', 'Name') ?>
                <?= th_sort($p, 'username', 'Username') ?>
                <?= th_sort($p, 'emp_code', 'Emp. Code') ?>
                <?= th_sort($p, 'panel', 'Panel') ?>
                <?= th_sort($p, 'role', 'Role') ?>
                <th>Contact</th>
                <?= th_sort($p, 'last_login', 'Last login') ?>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($p->items === []): ?>
                <?= partial('partials/empty_state', ['colspan' => 9, 'filtered' => $p->hasFilters(), 'message' => 'No users yet']) ?>
            <?php else: foreach ($p->items as $u): $self = (int) $u['user_id'] === Auth::id(); ?>
                <tr>
                    <td data-label="Name"><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="badge badge-gold">You</span>' : '' ?><span class="cell-sub"><?= e($u['designation']) ?></span></td>
                    <td data-label="Username"><span class="mono"><?= e($u['username']) ?></span></td>
                    <td data-label="Emp. Code"><?= e($u['emp_code']) ?></td>
                    <td data-label="Panel"><span class="badge badge-primary"><?= e($panels[$u['panel']] ?? $u['panel']) ?></span></td>
                    <td data-label="Role"><?= e($u['role_name'] ?? '—') ?></td>
                    <td data-label="Contact"><?= e($u['mobile']) ?><span class="cell-sub"><?= e($u['email']) ?></span></td>
                    <td data-label="Last login"><?= e(fmt_datetime($u['last_login_at'])) ?></td>
                    <td data-label="Status">
                        <?= active_badge($u['is_active']) ?>
                        <?php if ((int) $u['is_locked'] === 1): ?><span class="badge badge-danger"><i class="fa-solid fa-lock" aria-hidden="true"></i> Locked</span><?php endif; ?>
                        <?php if (\App\Core\Config::get('security.force_password_change') === true && (int) $u['must_change_password'] === 1): ?><span class="badge badge-warning" title="Must change password on next login"><i class="fa-solid fa-key" aria-hidden="true"></i> Temp pwd</span><?php endif; ?>
                    </td>
                    <td class="actions">
                        <div class="btn-group">
                            <?php if (!$self && $u['panel'] !== 'SUPER_ADMIN' && (int) $u['is_active'] === 1): ?>
                                <form method="post" action="<?= e(url('/admin/users/' . $u['user_id'] . '/impersonate')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return" value="<?= e($p->url()) ?>">
                                    <button type="submit" class="btn-switch" title="<?= e('Login as ' . $u['name']) ?>" aria-label="<?= e('Login as ' . $u['name']) ?>">
                                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= e(url('/admin/users/' . $u['user_id'] . '/edit')) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-pen" aria-hidden="true"></i> <span>Edit</span></a>
                            <?php if ((int) $u['is_locked'] === 1): ?>
                                <form method="post" action="<?= e(url('/admin/users/' . $u['user_id'] . '/unlock')) ?>" data-confirm="<?= e('Unlock account "' . $u['username'] . '"?') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-warning"><i class="fa-solid fa-lock-open" aria-hidden="true"></i> <span>Unlock</span></button>
                                </form>
                            <?php endif; ?>
                            <?php if (!$self): ?>
                                <form method="post" action="<?= e(url('/admin/users/' . $u['user_id'] . '/toggle')) ?>"
                                      data-confirm="<?= e((int) $u['is_active'] === 1 ? 'Deactivate "' . $u['name'] . '"? They will not be able to login.' : 'Activate "' . $u['name'] . '"?') ?>"
                                      data-confirm-variant="<?= (int) $u['is_active'] === 1 ? 'danger' : 'success' ?>">
                                    <?= csrf_field() ?>
                                    <?php if ((int) $u['is_active'] === 1): ?>
                                        <button type="submit" class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-user-slash" aria-hidden="true"></i> <span>Deactivate</span></button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-user-check" aria-hidden="true"></i> <span>Activate</span></button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= partial('partials/pagination', ['p' => $p]) ?>
</div>
