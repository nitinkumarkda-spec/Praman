<?php
declare(strict_types=1);

/**
 * Database connection. XAMPP default = root with empty password.
 * Production: use the least-privilege 'praman_app' user (see database/praman_db.sql footer).
 */
return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'praman_db',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
    'collation'=> 'utf8mb4_unicode_ci',
    'timezone' => '+05:30',
];
