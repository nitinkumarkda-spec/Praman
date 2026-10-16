<?php
/** @var int $status  @var string $title  @var string $message */
use App\Core\Auth;

$icon = match ($status) {
    403 => 'fa-hand',
    404 => 'fa-map-location-dot',
    419 => 'fa-hourglass-end',
    429 => 'fa-gauge-high',
    500 => 'fa-triangle-exclamation',
    default => 'fa-circle-exclamation',
};
$home = Auth::check() ? Auth::home() : '/login';
?>
<div class="error-card anim-fade-up">
    <i class="fa-solid <?= e($icon) ?> error-icon fa-bounce" aria-hidden="true"></i>
    <p class="error-code"><?= (int) $status ?></p>
    <h1 class="error-title"><?= e($title) ?></h1>
    <p class="error-message"><?= e($message) ?></p>
    <a href="<?= e(url($home)) ?>" class="btn btn-primary">
        <i class="fa-solid fa-house" aria-hidden="true"></i> <span>Go to <?= Auth::check() ? 'Dashboard' : 'Login' ?></span>
    </a>
</div>
