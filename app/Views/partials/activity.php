<?php
/** @var array $activity  @var string|null $title */
$icons = [
    'LOGIN' => 'fa-right-to-bracket', 'LOGOUT' => 'fa-right-from-bracket', 'PASSWORD_CHANGED' => 'fa-key',
    'ACCOUNT_LOCKED' => 'fa-user-lock', 'ACCESS_DENIED' => 'fa-hand', 'CREATED' => 'fa-plus',
    'UPDATED' => 'fa-pen', 'DEACTIVATED' => 'fa-toggle-off', 'ACTIVATED' => 'fa-toggle-on',
];
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> <?= e($title ?? 'Recent activity') ?></h3>
        <span class="text-muted small">Latest <?= count($activity) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if ($activity === []): ?>
            <div class="empty-state compact">
                <i class="fa-solid fa-inbox empty-icon" aria-hidden="true"></i>
                <p class="empty-title">No activity yet</p>
            </div>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($activity as $a): ?>
                    <li>
                        <span class="activity-icon"><i class="fa-solid <?= e($icons[$a['action']] ?? 'fa-circle-dot') ?>" aria-hidden="true"></i></span>
                        <div class="activity-body">
                            <strong><?= e(label($a['action'])) ?></strong>
                            <span class="text-muted"><?= e(label($a['entity_type'])) ?><?= $a['entity_id'] !== null ? ' #' . e($a['entity_id']) : '' ?></span>
                            <small class="text-muted"><?= e($a['performed_by_name'] ?? 'System') ?> &middot; <?= e(fmt_datetime($a['performed_at'])) ?> &middot; <?= e($a['ip_address']) ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
