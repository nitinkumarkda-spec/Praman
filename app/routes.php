<?php
declare(strict_types=1);

/**
 * Route table. Every POST is CSRF-checked automatically by the Router.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Admin\AuditController;
use App\Controllers\Admin\DashboardController as AdminDashboard;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\UserController;
use App\Controllers\AuthController;
use App\Controllers\DataEntry\DashboardController as DataEntryDashboard;
use App\Controllers\DataEntry\PropertyController as DataEntryProperty;
use App\Controllers\DataEntry\SchemeController;
use App\Controllers\HomeController;
use App\Controllers\Oic\DashboardController as OicDashboard;
use App\Controllers\Oic\PropertyController as OicProperty;
use App\Controllers\Oic\TaskController as OicTask;
use App\Controllers\RoleUser\DashboardController as RoleUserDashboard;
use App\Controllers\RoleUser\TaskController as RoleUserTask;

// ---- Public / auth -----------------------------------------------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/change-password', [AuthController::class, 'showChangePassword'], ['auth']);
$router->post('/impersonate/stop', [AuthController::class, 'stopImpersonation'], ['auth']);   // "Back to Admin"
$router->post('/change-password', [AuthController::class, 'changePassword'], ['auth']);

// ---- A) Super Admin ----------------------------------------------------
$router->group('/admin', ['auth', 'panel:SUPER_ADMIN'], static function ($r): void {
    $r->get('/dashboard', [AdminDashboard::class, 'index']);

    $r->get('/roles', [RoleController::class, 'index']);
    $r->get('/roles/create', [RoleController::class, 'create']);
    $r->post('/roles', [RoleController::class, 'store']);
    $r->get('/roles/{id}/edit', [RoleController::class, 'edit']);
    $r->post('/roles/{id}', [RoleController::class, 'update']);
    $r->post('/roles/{id}/toggle', [RoleController::class, 'toggle']);

    $r->get('/users', [UserController::class, 'index']);
    $r->get('/users/create', [UserController::class, 'create']);
    $r->post('/users', [UserController::class, 'store']);
    $r->get('/users/{id}/edit', [UserController::class, 'edit']);
    $r->post('/users/{id}', [UserController::class, 'update']);
    $r->post('/users/{id}/toggle', [UserController::class, 'toggle']);
    $r->post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    $r->post('/users/{id}/unlock', [UserController::class, 'unlock']);
    $r->post('/users/{id}/impersonate', [UserController::class, 'impersonate']);   // "Login as"

    $r->get('/audit-logs', [AuditController::class, 'index']);
});

// ---- B) Data Entry -----------------------------------------------------
$router->group('/data-entry', ['auth', 'panel:DATA_ENTRY'], static function ($r): void {
    $r->get('/dashboard', [DataEntryDashboard::class, 'index']);

    $r->get('/schemes', [SchemeController::class, 'index']);
    $r->get('/schemes/create', [SchemeController::class, 'create']);
    $r->post('/schemes', [SchemeController::class, 'store']);
    $r->get('/schemes/{id}/edit', [SchemeController::class, 'edit']);
    $r->get('/schemes/{id}/sectors', [SchemeController::class, 'sectors']);   // AJAX (JSON)
    $r->post('/schemes/{id}', [SchemeController::class, 'update']);
    $r->post('/schemes/{id}/toggle', [SchemeController::class, 'toggle']);

    $r->get('/properties', [DataEntryProperty::class, 'index']);
    $r->get('/properties/create', [DataEntryProperty::class, 'create']);
    $r->post('/properties', [DataEntryProperty::class, 'store']);
    $r->get('/properties/{id}/edit', [DataEntryProperty::class, 'edit']);
    $r->post('/properties/{id}', [DataEntryProperty::class, 'update']);
});

// ---- C) OIC Auction ----------------------------------------------------
$router->group('/oic', ['auth', 'panel:OIC'], static function ($r): void {
    $r->get('/dashboard', [OicDashboard::class, 'index']);

    $r->get('/properties', [OicProperty::class, 'index']);
    $r->post('/properties/send', [OicProperty::class, 'send']);
    $r->get('/properties/{id}', [OicProperty::class, 'show']);

    // TEMPORARY testing aid — only works when config verification.simulate_enabled = true (now false: Role User panel is live)
    $r->post('/tasks/{id}/simulate', [OicTask::class, 'simulate']);
});

// ---- D) Role User (section verifiers: Engineering, Legal, Sale …) -------
$router->group('/role', ['auth', 'panel:ROLE_USER'], static function ($r): void {
    $r->get('/dashboard', [RoleUserDashboard::class, 'index']);
    $r->get('/tasks', [RoleUserTask::class, 'index']);
    $r->get('/tasks/{id}', [RoleUserTask::class, 'show']);
    $r->post('/tasks/{id}/respond', [RoleUserTask::class, 'respond']);
});
