<?php
declare(strict_types=1);

/**
 * MySQL / MariaDB connection — values from .env (DB_*).
 * Production: use a least-privilege account (see the footer of database/schema.sql).
 */
return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'name' => env('DB_DATABASE', 'pramaan_db'),
    'user' => env('DB_USERNAME', 'root'),
    'pass' => (string) env('DB_PASSWORD', ''),
];
