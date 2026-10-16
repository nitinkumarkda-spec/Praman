<?php
/** @var string $content  @var string|null $pageTitle */
use App\Core\Config;

$user = auth_user();
$appName = (string) Config::get('app.name');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(url('/')) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($pageTitle ?? 'Dashboard') . ' | ' . $appName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/fontawesome.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/animations.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
</head>
<body class="app-body">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="app">
    <?= partial('partials/sidebar', ['user' => $user]) ?>
    <div class="sidebar-overlay" data-sidebar-close></div>

    <div class="main">
        <?= partial('partials/topbar', ['user' => $user, 'pageTitle' => $pageTitle ?? '']) ?>

        <main id="main-content" class="content" tabindex="-1">
            <?= partial('partials/flash') ?>
            <?= $content ?>
        </main>

        <footer class="app-footer">
            <span>&copy; <?= e(date('Y')) ?> <?= e(Config::get('app.org')) ?> &middot; <?= e($appName) ?></span>
            <span class="text-muted">Internal use only</span>
        </footer>
    </div>
</div>

<?= partial('partials/confirm_modal') ?>
<div class="toast-container" id="toast-container" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e(asset('js/ajax.js')) ?>"></script>
<script src="<?= e(asset('js/validation.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/forms.js')) ?>"></script>
</body>
</html>
