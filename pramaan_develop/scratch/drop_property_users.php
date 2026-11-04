<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/Core/bootstrap.php';
\App\Core\Config::load(BASE_PATH . '/config');

use App\Core\Database;

echo "=== Dropping property_users table from live database ===\n";
Database::run("DROP TABLE IF EXISTS `property_users`");
echo "Table property_users dropped successfully.\n";

