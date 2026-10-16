<?php
use App\Core\Session;

/**
 * Flash messages: rendered as alerts (work without JS); app.js also mirrors them as toasts
 * and auto-dismisses success/info alerts.
 */
$icons = [
    'success' => 'fa-circle-check',
    'error'   => 'fa-circle-exclamation',
    'warning' => 'fa-triangle-exclamation',
    'info'    => 'fa-circle-info',
];
foreach ($icons as $type => $icon):
    $msg = Session::getFlash($type);
    if (!is_string($msg) || $msg === '') {
        continue;
    }
?>
    <div class="alert alert-<?= e($type) ?> anim-slide-down" role="<?= $type === 'error' ? 'alert' : 'status' ?>" data-flash="<?= e($type) ?>">
        <i class="fa-solid <?= e($icon) ?><?= $type === 'error' || $type === 'warning' ? ' fa-beat' : '' ?>" aria-hidden="true"></i>
        <span><?= e($msg) ?></span>
        <button type="button" class="alert-close" data-dismiss-alert aria-label="Dismiss">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
<?php endforeach; ?>
