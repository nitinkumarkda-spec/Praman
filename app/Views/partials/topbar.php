<?php
/** @var array $user  @var string $pageTitle */
$initials = '';
foreach (array_slice(preg_split('/\s+/', trim((string) ($user['name'] ?? ''))) ?: [], 0, 2) as $part) {
    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
?>
<header class="topbar">
    <button type="button" class="btn-icon sidebar-toggle" data-sidebar-toggle aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <h1 class="topbar-title"><?= e($pageTitle) ?></h1>

    <div class="topbar-right">
        <?= partial('partials/impersonation_bar') ?>
        <span class="topbar-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e(date('d M Y')) ?></span>
        <div class="user-menu" data-dropdown>
            <button type="button" class="user-trigger" data-dropdown-toggle aria-haspopup="true" aria-expanded="false">
                <span class="avatar" aria-hidden="true"><?= e($initials) ?></span>
                <span class="user-meta">
                    <strong><?= e($user['name'] ?? '') ?></strong>
                    <small><?= e($user['designation'] ?? '') ?></small>
                </span>
                <i class="fa-solid fa-chevron-down user-caret" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu" role="menu">
                <div class="dropdown-header">
                    <strong><?= e($user['username'] ?? '') ?></strong>
                    <small>Emp. Code: <?= e($user['emp_code'] ?? '') ?></small>
                    <small>Last login: <?= e(fmt_datetime($user['last_login_at'] ?? null)) ?></small>
                </div>
                <a class="dropdown-item" role="menuitem" href="<?= e(url('/change-password')) ?>">
                    <i class="fa-solid fa-key" aria-hidden="true"></i> Change Password
                </a>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="dropdown-item text-danger" role="menuitem">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
