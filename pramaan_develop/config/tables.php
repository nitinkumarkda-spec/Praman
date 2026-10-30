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
    // masters
    'plot_types'       => 'plot_types',
    'statuses'         => 'statuses',
    // schemes
    'schemes'          => 'schemes',
    'sectors'          => 'sectors',
    'scheme_users'     => 'scheme_users',
    // property register
    'properties'       => 'properties',
    'property_users'   => 'property_users',
    // verification
    'rounds'           => 'rounds',
    'tasks'            => 'tasks',
    'task_assignees'   => 'task_assignees',
    'site_inspections' => 'site_inspections',
    'sale_checks'      => 'sale_checks',
    'attachments'      => 'attachments',
    // tracking
    'notifications'    => 'notifications',
    'audit_logs'       => 'audit_logs',
    // app settings
    'settings'         => 'settings',
    'slides'           => 'slides',
];
