<?php
/** @var array $counts  @var array $statusCount  @var array $activity */
$user = auth_user();
?>
<div class="page-header">
    <div>
        <h2 class="page-title">Welcome, <?= e($user['name']) ?></h2>
        <p class="page-subtitle">Scheme, sector and property register.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/data-entry/schemes/create')) ?>" class="btn btn-light"><i class="fa-solid fa-map" aria-hidden="true"></i> <span>Create Scheme</span></a>
        <a href="<?= e(url('/data-entry/properties/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i> <span>Add Property</span></a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card stat-navy">
        <span class="stat-icon"><i class="fa-solid fa-map" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['schemes_active'] ?? 0) ?></p><p class="stat-label">Active schemes</p></div>
    </div>
    <div class="stat-card stat-gold">
        <span class="stat-icon"><i class="fa-solid fa-table-cells" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['sectors_active'] ?? 0) ?></p><p class="stat-label">Active sectors</p></div>
    </div>
    <div class="stat-card stat-blue">
        <span class="stat-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['properties_total'] ?? 0) ?></p><p class="stat-label">All properties</p></div>
    </div>
    <div class="stat-card stat-green">
        <span class="stat-icon"><i class="fa-solid fa-user-pen" aria-hidden="true"></i></span>
        <div><p class="stat-value"><?= fmt_num($counts['my_properties'] ?? 0) ?></p><p class="stat-label">Entered by me</p></div>
    </div>
</div>

<div class="grid-2">
    <?= partial('partials/status_overview', ['statusCount' => $statusCount, 'title' => 'My properties by status']) ?>
    <?= partial('partials/activity', ['activity' => $activity, 'title' => 'My recent activity']) ?>
</div>
