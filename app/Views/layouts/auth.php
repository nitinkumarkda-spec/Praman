<?php
/** @var string $content  @var string|null $pageTitle */
use App\Core\Config;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(url('/')) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($pageTitle ?? $title ?? 'Login') . ' | ' . Config::get('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/fontawesome.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/animations.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
</head>
<body class="auth-body">
<main class="auth-wrap">
    <?= $content ?>
</main>
<div class="toast-container" id="toast-container" aria-live="polite" aria-atomic="true"></div>
<script src="<?= e(asset('js/ajax.js')) ?>"></script>
<script src="<?= e(asset('js/validation.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
