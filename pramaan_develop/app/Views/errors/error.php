<?php
/**
 * Server error page (direct URL that is not part of the app, 404 / 405 / 500).
 * @var int $status @var string $title @var string $message
 */
$icons = [403 => 'fa-hand', 404 => 'fa-map-location-dot', 500 => 'fa-triangle-exclamation'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> | <?= e(\App\Services\Settings::get('app_name')) ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/kda-emblem.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/fontawesome.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/animations.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
</head>
<body class="auth-body">
<main class="auth-wrap">
    <div class="error-card anim-fade-up">
        <i class="fa-solid <?= e($icons[$status] ?? 'fa-circle-exclamation') ?> error-icon fa-bounce" aria-hidden="true"></i>
        <p class="error-code"><?= (int) $status ?></p>
        <h1 class="error-title"><?= e($title) ?></h1>
        <p class="error-message"><?= e($message) ?></p>
        <a href="<?= e(base_path() . '/') ?>" class="btn btn-primary"><i class="fa-solid fa-house" aria-hidden="true"></i> <span>Go to <?= e(\App\Services\Settings::get('app_name')) ?></span></a>
    </div>
</main>
</body>
</html>
