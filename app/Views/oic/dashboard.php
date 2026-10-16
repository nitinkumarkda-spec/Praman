<?php
/** @var array $counts  @var array $statusCount  @var array $activity */
$user = auth_user();
?>
<div class="page-header">
    <div>
        <h2 class="page-title">Welcome, <?= e($user['name']) ?></h2>
        <p class="page-subtitle">Verification rounds and auction readiness.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/oic/properties', ['status' => 'LISTED'])) ?>" class="btn btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span>Send for verification</span></a>
        <a href="<?= e(url('/oic/properties', ['status' => 'NOT_READY'])) ?>" class="btn btn-light"><i class="fa-solid fa-rotate" aria-hidden="true"></i> <span>Not ready properties</span></a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card stat-blue">
        <span class="stat-icon"><i class="fa-solid fa-list" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($statusCount['LISTED'] ?? 0) ?></p><p class="stat-label">Listed (to send)</p></div>
    </div>
    <div class="stat-card stat-gold">
        <span class="stat-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($statusCount['UNDER_VERIFICATION'] ?? 0) ?></p><p class="stat-label">Under verification</p>
            <small class="stat-sub"><?= fmt_num($counts['pending_tasks'] ?? 0) ?> pending role tasks</small></div>
    </div>
    <div class="stat-card stat-green">
        <span class="stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($statusCount['AUCTION_READY'] ?? 0) ?></p><p class="stat-label">Auction ready</p></div>
    </div>
    <div class="stat-card stat-red">
        <span class="stat-icon"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($statusCount['NOT_READY'] ?? 0) ?></p><p class="stat-label">Not ready</p></div>
    </div>
    <div class="stat-card stat-navy">
        <span class="stat-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['open_rounds'] ?? 0) ?></p><p class="stat-label">Open rounds</p>
            <small class="stat-sub"><?= fmt_num($counts['rounds_ready'] ?? 0) ?> ready / <?= fmt_num($counts['rounds_not_ready'] ?? 0) ?> not ready closed</small></div>
    </div>
</div>

<div class="grid-2">
    <?= partial('partials/status_overview', ['statusCount' => $statusCount]) ?>
    <?= partial('partials/activity', ['activity' => $activity, 'title' => 'My recent activity']) ?>
</div>
