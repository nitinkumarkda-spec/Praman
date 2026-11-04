<?php
declare(strict_types=1);

/**
 * Table registry — the ONLY place where database table names are written.
 * Code refers to a table by its key: models use the key ($table), raw SQL uses
 * {key} placeholders (e.g. "SELECT … FROM {tasks} t JOIN {rounds} r …").
 * To rename a table, change the value here; the installer creates it with that name.
 */
return [
    // access
    'roles'            => 'roles',
    'users'            => 'users',
    'login_attempts'   => 'login_attempts',
    'panel_permissions' => 'role_permissions',
    'role_permissions' => 'role_permissions',
    'user_permissions' => 'user_permissions',
    // masters
    'areas'            => 'areas',
    'plot_types'       => 'plot_types',
    'statuses'         => 'statuses',
    'units'            => 'units',
    // schemes
    'schemes'          => 'schemes',
    'sectors'          => 'sectors',
    'scheme_users'     => 'scheme_users',
    // property register
    'properties'       => 'properties',
    // verification
    'rounds'           => 'rounds',
    'tasks'            => 'tasks',
    'task_assignees'   => 'task_assignees',
    'site_inspections' => 'site_inspections',
    'section_checks'   => 'section_checks',
    'attachments'      => 'attachments',
    // tracking
    'notifications'    => 'notifications',
    'audit_logs'       => 'audit_logs',
    // app settings
    'settings'         => 'settings',
    'slides'           => 'slides',
];
