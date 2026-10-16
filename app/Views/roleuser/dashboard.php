<?php
/** @var array $counts  @var array $urgent  @var array $activity */
use App\Core\Lookups;

$user = auth_user();
$today = date('Y-m-d');
?>
<div class="page-header">
    <div>
        <h2 class="page-title">Welcome, <?= e($user['name']) ?></h2>
        <p class="page-subtitle"><?= e(($user['role_name'] ?? '') . ' · ' . $user['designation']) ?> — properties sent to you for verification.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/role/tasks', ['status' => 'pending'])) ?>" class="btn btn-primary"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> <span>Pending tasks</span></a>
    </div>
</div>

<div class="stat-grid">
    <a class="stat-card stat-gold stat-link" href="<?= e(url('/role/tasks', ['status' => 'pending'])) ?>">
        <span class="stat-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['pending'] ?? 0) ?></p><p class="stat-label">Pending</p></div>
    </a>
    <a class="stat-card stat-red stat-link" href="<?= e(url('/role/tasks', ['status' => 'overdue'])) ?>">
        <span class="stat-icon"><i class="fa-solid fa-stopwatch<?= (int) ($counts['overdue'] ?? 0) > 0 ? ' fa-shake' : '' ?>" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['overdue'] ?? 0) ?></p><p class="stat-label">Overdue (SLA)</p></div>
    </a>
    <a class="stat-card stat-green stat-link" href="<?= e(url('/role/tasks', ['status' => 'approved'])) ?>">
        <span class="stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['approved'] ?? 0) ?></p><p class="stat-label">Approved by me</p></div>
    </a>
    <a class="stat-card stat-navy stat-link" href="<?= e(url('/role/tasks', ['status' => 'rejected'])) ?>">
        <span class="stat-icon"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['rejected'] ?? 0) ?></p><p class="stat-label">Rejected by me</p></div>
    </a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-fire" aria-hidden="true"></i> Most urgent</h3>
            <a href="<?= e(url('/role/tasks', ['status' => 'pending'])) ?>" class="small">View all <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        <div class="card-body p-0">
            <?php if ($urgent === []): ?>
                <div class="empty-state compact">
                    <i class="fa-solid fa-mug-hot empty-icon" aria-hidden="true"></i>
                    <p class="empty-title">No pending task</p>
                    <p class="text-muted">New properties sent by the OIC will appear here.</p>
                </div>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($urgent as $t): $late = $t['sla_due_date'] < $today; ?>
                        <li>
                            <span class="activity-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                            <div class="activity-body">
                                <a href="<?= e(url('/role/tasks/' . $t['task_id'])) ?>"><strong>Plot <?= e($t['plot_no']) ?></strong> · <?= e(Lookups::PROPERTY_TYPES[$t['property_type']] ?? '') ?></a>
                                <span class="text-muted"><?= e($t['scheme_name'] . ' / ' . $t['sector_name']) ?> · R<?= (int) $t['round_no'] ?></span>
                                <small class="<?= $late ? 'text-danger' : 'text-muted' ?>"><i class="fa-regular fa-clock" aria-hidden="true"></i> Due <?= e(fmt_date($t['sla_due_date'])) ?><?= $late ? ' — overdue' : '' ?></small>
                            </div>
                            <a href="<?= e(url('/role/tasks/' . $t['task_id'])) ?>" class="btn btn-primary btn-sm ml-auto"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> <span>Verify</span></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?= partial('partials/activity', ['activity' => $activity, 'title' => 'My recent activity']) ?>
</div>
