<?php
/**
 * Application shell (same markup as html/index.html). The screens are drawn
 * by public/assets/js from the JSON state below.
 *
 * @var array $state
 */
// same cascade order as html/index.html (components.css refines some responsive rules)
$css = ['fonts.css', 'loader.css', 'fontawesome.min.css', 'style.css', 'animations.css', 'responsive.css', 'components.css', 'landing.css', 'landing-photo.css'];
$js = [
    'loader.js', 'vendor/chart.umd.min.js', 'db.js', 'validation.js', 'app.js', 'core.js', 'forms.js', 'charts.js', 'tables.js', 'files.js',
    'pages/auth.js', 'pages/landing.js', 'pages/admin.js', 'pages/insights.js', 'pages/dataentry.js', 'pages/masters.js',
    'pages/oic.js', 'pages/role.js', 'pages/dashboards.js', 'pages/notifications.js', 'pages/timeline.js',
];
$json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
?>
<!doctype html>
<?php $boot = empty($state['session']); // loader only when nobody is signed in (refresh of a dashboard opens it straight away) ?>
<html lang="en"<?= $boot ? ' class="pl-lock"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0f2b52">
    <meta name="format-detection" content="telephone=no">
    <meta name="app-base" content="<?= e(base_path()) ?>">
    <title><?= e(\App\Services\Settings::get('app_name') . ' | ' . \App\Services\Settings::get('app_tagline')) ?></title>
    <!-- favicon = KDA emblem; reused for every logo: landing header/footer and the dashboard sidebar -->
    <link rel="icon" type="image/png" id="app-icon" href="<?= e(asset('img/kda-emblem.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('img/kda-emblem.png')) ?>">
    <!-- fonts + icons are local files (assets/fonts, assets/webfonts) — preload the ones every screen needs -->
<?php foreach (['fonts/inter/inter-400-800-latin.woff2', 'fonts/poppins/poppins-700-latin.woff2', 'webfonts/fa-solid-900.woff2'] as $p): ?>
    <link rel="preload" href="<?= e(base_path() . '/assets/' . $p) ?>" as="font" type="font/woff2" crossorigin>
<?php endforeach; ?>
<?php foreach ($css as $f): ?>
    <link rel="stylesheet" href="<?= e(asset('css/' . $f)) ?>">
<?php endforeach; ?>
</head>
<body class="auth-body">
<?= \App\Core\View::render('partials/loader', ['hidden' => !$boot]) ?>

<!-- Every page (landing + all 4 panels) is rendered here by js/core.js -->
<div id="app-root">
    <noscript><p class="noscript"><?= e(\App\Services\Settings::get('app_name')) ?> needs JavaScript enabled.</p></noscript>
</div>

<div id="modal-root"></div>
<div class="toast-container" id="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- initial data for the signed-in user (JSON, not executable) -->
<script type="application/json" id="praman-state"><?= $json ?></script>
<?php foreach ($js as $f): ?>
<script src="<?= e(asset('js/' . $f)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
