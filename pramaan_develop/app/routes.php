<?php
declare(strict_types=1);

/**
 * Route table. Every POST goes through "csrf"; panel access through "panel:…".
 * The screen URLs themselves (#/admin/users …) are drawn in the browser from
 * GET / — the server decides what data and which actions each panel gets.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\Admin\InsightController;
use App\Controllers\Admin\MasterController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\UserController;
use App\Controllers\AttachmentController;
use App\Controllers\AuthController;
use App\Controllers\DataEntry\PropertyController;
use App\Controllers\HomeController;
use App\Controllers\NotificationController;
use App\Controllers\Oic\VerificationController;
use App\Controllers\Role\TaskController;
use App\Controllers\SchemeController;
use App\Controllers\SystemController;

$router->get('/', [HomeController::class, 'index']);
$router->get('/api/state', [HomeController::class, 'state']);

$router->group(['csrf'], static function ($r): void {
    $r->post('/api/login', [AuthController::class, 'login'], ['guest']);

    $r->group(['auth'], static function ($r): void {
        $r->post('/api/logout', [AuthController::class, 'logout']);
        $r->post('/api/change-password', [AuthController::class, 'changePassword']);
        $r->post('/api/impersonate-stop', [UserController::class, 'stopImpersonation']);
        $r->post('/api/access-denied', [SystemController::class, 'accessDenied']);

        $r->post('/api/notif-read', [NotificationController::class, 'read']);
        $r->post('/api/notif-read-all', [NotificationController::class, 'readAll']);
        $r->post('/api/notif-delete', [NotificationController::class, 'delete']);
        $r->post('/api/notif-delete-read', [NotificationController::class, 'deleteRead']);

        // Super Admin
        $r->group(['panel:SUPER_ADMIN'], static function ($r): void {
            $r->post('/api/role-save', [RoleController::class, 'save']);
            $r->post('/api/role-toggle', [RoleController::class, 'toggle']);
            $r->post('/api/user-save', [UserController::class, 'save']);
            $r->post('/api/user-toggle', [UserController::class, 'toggle']);
            $r->post('/api/user-unlock', [UserController::class, 'unlock']);
            $r->post('/api/user-reset-password', [UserController::class, 'resetPassword']);
            $r->post('/api/impersonate', [UserController::class, 'impersonate']);
            $r->post('/api/master-save', [MasterController::class, 'save']);
            $r->post('/api/master-toggle', [MasterController::class, 'toggle']);
            $r->post('/api/master-delete', [MasterController::class, 'delete']);
            $r->post('/api/sector-save', [MasterController::class, 'sectorSave']);
            $r->post('/api/sector-toggle', [MasterController::class, 'sectorToggle']);
            $r->post('/api/sector-delete', [MasterController::class, 'sectorDelete']);
            $r->post('/api/publish-direction', [InsightController::class, 'publishDirection']);
            $r->post('/api/settings-save', [SettingsController::class, 'save']);
            $r->post('/api/slides-save', [SettingsController::class, 'slidesSave']);
            $r->post('/api/slide-add', [SettingsController::class, 'slideAdd']);
            $r->post('/api/slide-delete', [SettingsController::class, 'slideDelete']);
        });

        // Scheme Master: Data Entry + Super Admin
        $r->post('/api/scheme-save', [SchemeController::class, 'save'], ['panel:DATA_ENTRY,SUPER_ADMIN']);
        $r->post('/api/scheme-toggle', [SchemeController::class, 'toggle'], ['panel:DATA_ENTRY,SUPER_ADMIN']);

        $r->post('/api/property-save', [PropertyController::class, 'save'], ['panel:DATA_ENTRY']);
        $r->post('/api/oic-send', [VerificationController::class, 'send'], ['panel:OIC']);
        $r->post('/api/task-respond', [TaskController::class, 'respond'], ['panel:ROLE_USER']);
    });
});

$router->get('/api/attachments/{id}', [AttachmentController::class, 'show'], ['auth']);
