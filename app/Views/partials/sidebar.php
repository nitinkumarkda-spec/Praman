<?php
/** @var array $user */
use App\Core\Auth;
use App\Core\Config;

/**
 * Sidebar menu per panel: [label, icon, path] or ['section' => 'Heading'].
 * Each phase adds its screens here.
 */
$menus = [
    'SUPER_ADMIN' => [
        ['Dashboard', 'fa-gauge-high', '/admin/dashboard'],
        ['section' => 'Masters'],
        ['Role Master', 'fa-sitemap', '/admin/roles'],
        ['User Master', 'fa-users', '/admin/users'],
        ['section' => 'Monitoring'],
        ['Audit Log', 'fa-fingerprint', '/admin/audit-logs'],
    ],
    'DATA_ENTRY' => [
        ['Dashboard', 'fa-gauge-high', '/data-entry/dashboard'],
        ['section' => 'Masters'],
        ['Scheme Master', 'fa-map', '/data-entry/schemes'],
        ['section' => 'Property'],
        ['Property Register', 'fa-building', '/data-entry/properties'],
        ['Add Property', 'fa-circle-plus', '/data-entry/properties/create'],
    ],
    'OIC' => [
        ['Dashboard', 'fa-gauge-high', '/oic/dashboard'],
        ['section' => 'Verification'],
        ['Properties', 'fa-list-check', '/oic/properties'],
    ],
    'ROLE_USER' => [
        ['Dashboard', 'fa-gauge-high', '/role/dashboard'],
        ['section' => 'Verification'],
        ['My Tasks', 'fa-clipboard-list', '/role/tasks'],
    ],
];
$panel = (string) ($user['panel'] ?? '');
$items = $menus[$panel] ?? [];

// Highlight only the most specific matching menu entry (e.g. "Add Property" vs "Property Register").
$current = \App\Core\Request::current()->path();
$activePath = '';
foreach ($items as $item) {
    if (isset($item[2]) && ($current === $item[2] || str_starts_with($current, $item[2] . '/')) && strlen($item[2]) > strlen($activePath)) {
        $activePath = $item[2];
    }
}
?>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="sidebar-brand">
        <img src="<?= e(asset('img/kda-logo.svg')) ?>" alt="" class="brand-logo" width="40" height="40">
        <div class="brand-text">
            <strong><?= e(Config::get('app.name')) ?></strong>
            <small><?= e(Config::get('app.org')) ?></small>
        </div>
        <button type="button" class="sidebar-close btn-icon" data-sidebar-close aria-label="Close menu">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <div class="sidebar-panel">
        <span class="panel-chip"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> <?= e(Auth::PANELS[$panel] ?? '') ?> Panel</span>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($items as $item): ?>
            <?php if (isset($item['section'])): ?>
                <div class="nav-section"><?= e($item['section']) ?></div>
            <?php else: [$label, $icon, $path] = $item; ?>
                <a class="nav-link<?= $path === $activePath ? ' active' : '' ?>" href="<?= e(url($path)) ?>"<?= $path === $activePath ? ' aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= e($icon) ?> nav-icon" aria-hidden="true"></i>
                    <span><?= e($label) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="nav-section">Account</div>
        <a class="nav-link<?= nav_active('/change-password') ?>" href="<?= e(url('/change-password')) ?>">
            <i class="fa-solid fa-key nav-icon" aria-hidden="true"></i>
            <span>Change Password</span>
        </a>
        <form method="post" action="<?= e(url('/logout')) ?>" class="nav-form">
            <?= csrf_field() ?>
            <button type="submit" class="nav-link nav-button">
                <i class="fa-solid fa-right-from-bracket nav-icon" aria-hidden="true"></i>
                <span>Logout</span>
            </button>
        </form>
    </nav>
</aside>
