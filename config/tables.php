<?php
declare(strict_types=1);

/**
 * Table registry: logical key => real table name.
 *
 * Code never hard-codes a table name. Use the key instead:
 *   - inside SQL:      'SELECT * FROM {users} u JOIN {roles} r ...'
 *   - in PHP:          tbl('users')  /  Table::name('users')
 *   - generic helpers: $this->countRows('users', ['is_active' => 1])
 *
 * To rename a table or add a prefix, change it here only.
 */
return [
    'prefix' => '',

    'tables' => [
        'roles'                       => 'roles',
        'users'                       => 'users',
        'schemes'                     => 'schemes',
        'scheme_sectors'              => 'scheme_sectors',
        'scheme_role_users'           => 'scheme_role_users',
        'properties'                  => 'properties',
        'property_role_users'         => 'property_role_users',
        'verification_rounds'         => 'verification_rounds',
        'verification_tasks'          => 'verification_tasks',
        'verification_task_assignees' => 'verification_task_assignees',
        'audit_logs'                  => 'audit_logs',
        'login_attempts'              => 'login_attempts',
    ],
];
