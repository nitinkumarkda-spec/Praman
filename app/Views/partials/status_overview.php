<?php
/** @var array<string,int> $statusCount  @var string|null $title */
$total = array_sum($statusCount);
$colors = [
    'LISTED' => 'bar-info', 'UNDER_VERIFICATION' => 'bar-warning', 'AUCTION_READY' => 'bar-success',
    'NOT_READY' => 'bar-danger', 'SENT_FOR_AUCTION' => 'bar-primary', 'SOLD' => 'bar-dark',
    'WITHHELD' => 'bar-muted', 'ARCHIVED' => 'bar-muted',
];
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> <?= e($title ?? 'Property status') ?></h3>
        <span class="badge badge-muted"><?= fmt_num($total) ?> total</span>
    </div>
    <div class="card-body">
        <?php if ($total === 0): ?>
            <div class="empty-state compact">
                <i class="fa-solid fa-building-circle-exclamation empty-icon" aria-hidden="true"></i>
                <p class="empty-title">No properties yet</p>
                <p class="text-muted">Status distribution will appear once properties are registered.</p>
            </div>
        <?php else: ?>
            <ul class="bar-list">
                <?php foreach ($statusCount as $status => $count):
                    $pct = $total > 0 ? round($count * 100 / $total, 1) : 0; ?>
                    <li>
                        <div class="bar-label">
                            <span><?= e(label($status)) ?></span>
                            <strong><?= fmt_num($count) ?> <small class="text-muted">(<?= e((string) $pct) ?>%)</small></strong>
                        </div>
                        <div class="bar" role="img" aria-label="<?= e(label($status) . ': ' . $count) ?>">
                            <span class="bar-fill <?= e($colors[$status] ?? 'bar-muted') ?>" data-pct="<?= e((string) $pct) ?>"></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
