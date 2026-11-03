<?php
declare(strict_types=1);

/**
 * Route table. Every POST goes through "csrf"; actions through "can:…" — the permissions
 * Super Admin gives each panel (app/Core/Permissions.php, Super Admin → Permissions).
 * Save routes list both create and edit; the controller checks the one that applies.
 * The screen URLs themselves (/admin/users …) all serve the same page and are drawn
 * in the browser — the server decides what data and which actions each panel gets.
 * Handlers are names: "Role@save" = App\Controllers\RoleController::save() (found by the Router).
 *
 * @var App\Core\Router $router
 */

$router->get('/', 'Home@index');
$router->get('/api/state', 'Home@state');
$router->pages(['admin', 'data-entry', 'oic', 'role', 'notifications', 'change-password', 'login'], 'Home@index');

$router->group(['csrf'], static function ($r): void {
    $r->post('/api/login', 'Auth@login', ['guest']);

    $r->group(['auth'], static function ($r): void {
        $r->post('/api/logout', 'Auth@logout');
        $r->post('/api/change-password', 'Auth@changePassword');
        $r->post('/api/impersonate-stop', 'User@stopImpersonation');
        $r->post('/api/access-denied', 'System@accessDenied');

        $r->post('/api/notif-read', 'Notification@read');
        $r->post('/api/notif-read-all', 'Notification@readAll');
        $r->post('/api/notif-delete', 'Notification@delete');
        $r->post('/api/notif-delete-read', 'Notification@deleteRead');

        $r->post('/api/role-save', 'Role@save', ['can:role.create,role.edit']);
        $r->post('/api/role-toggle', 'Role@toggle', ['can:role.toggle']);

        $r->post('/api/user-save', 'User@save', ['can:user.create,user.edit']);
        $r->post('/api/user-toggle', 'User@toggle', ['can:user.toggle']);
        $r->post('/api/user-unlock', 'User@unlock', ['can:user.unlock']);
        $r->post('/api/user-reset-password', 'User@resetPassword', ['can:user.reset_password']);
        $r->post('/api/impersonate', 'User@impersonate', ['can:user.impersonate']);
        $r->post('/api/user-permission-save', 'Permission@userSave', ['can:user.permissions']);

        // every master (key = areas | plot-types | units | statuses | sectors | schemes), every action (do = save | toggle | delete);
        // the controller checks the exact permission (master.* or, for schemes, scheme.*)
        $r->post('/api/master', 'Master@handle', ['can:master.create,master.edit,master.toggle,master.delete,scheme.create,scheme.edit,scheme.toggle']);

        $r->post('/api/publish-direction', 'Insight@publishDirection', ['can:direction.publish']);
        $r->post('/api/settings-save', 'Settings@save', ['can:settings.edit']);
        $r->post('/api/slides-save', 'Settings@slidesSave', ['can:settings.edit']);
        $r->post('/api/slide-add', 'Settings@slideAdd', ['can:settings.edit']);
        $r->post('/api/slide-delete', 'Settings@slideDelete', ['can:settings.edit']);
        $r->post('/api/permission-save', 'Permission@save', ['can:permission.edit']);

        $r->post('/api/property-save', 'Property@save', ['can:property.create,property.edit']);
        $r->post('/api/oic-send', 'Verification@send', ['can:verification.send']);
        $r->post('/api/task-respond', 'Task@respond', ['can:task.respond']);
    });
});

$router->get('/api/attachments/{id}', 'Attachment@show', ['auth']);
