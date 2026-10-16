<?php
/** @var array $counts  @var array $byRole  @var array $statusCount  @var array $activity */
$user = auth_user();
?>
<div class="page-header">
    <div>
        <h2 class="page-title">Welcome, <?= e($user['name']) ?></h2>
        <p class="page-subtitle">System overview — users, roles, schemes and properties.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/admin/roles')) ?>" class="btn btn-light"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> <span>Role Master</span></a>
        <a href="<?= e(url('/admin/users/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> <span>Add User</span></a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card stat-navy">
        <span class="stat-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['users_total'] ?? 0) ?></p><p class="stat-label">Users</p>
            <small class="stat-sub"><?= fmt_num($counts['users_active'] ?? 0) ?> active</small></div>
    </div>
    <div class="stat-card stat-gold">
        <span class="stat-icon"><i class="fa-solid fa-sitemap" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['roles_total'] ?? 0) ?></p><p class="stat-label">Roles / Sections</p>
            <small class="stat-sub"><?= fmt_num($counts['roles_active'] ?? 0) ?> active</small></div>
    </div>
    <div class="stat-card stat-blue">
        <span class="stat-icon"><i class="fa-solid fa-map" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['schemes_total'] ?? 0) ?></p><p class="stat-label">Schemes</p>
            <small class="stat-sub"><?= fmt_num($counts['schemes_active'] ?? 0) ?> active</small></div>
    </div>
    <div class="stat-card stat-green">
        <span class="stat-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['properties_total'] ?? 0) ?></p><p class="stat-label">Properties</p>
            <small class="stat-sub">registered</small></div>
    </div>
    <div class="stat-card stat-red">
        <span class="stat-icon"><i class="fa-solid fa-user-lock" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['users_locked'] ?? 0) ?></p><p class="stat-label">Locked accounts</p>
            <small class="stat-sub"><?= fmt_num($counts['failed_logins_24h'] ?? 0) ?> failed logins (24h)</small></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Users per role</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-wrap">
                <table class="table table-stack">
                    <thead><tr><th>Role / Section</th><th class="text-right">Users</th><th class="text-right">Active</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($byRole === []): ?>
                        <?= partial('partials/empty_state', ['colspan' => 4, 'message' => 'No roles configured']) ?>
                    <?php else: foreach ($byRole as $r): ?>
                        <tr>
                            <td data-label="Role"><?= e($r['role_name']) ?></td>
                            <td data-label="Users" class="text-right"><?= fmt_num($r['users_count']) ?></td>
                            <td data-label="Active" class="text-right"><?= fmt_num($r['active_count']) ?></td>
                            <td data-label="Status"><?= active_badge($r['is_active']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?= partial('partials/status_overview', ['statusCount' => $statusCount]) ?>
</div>

<?= partial('partials/activity', ['activity' => $activity, 'title' => 'Recent system activity']) ?>
