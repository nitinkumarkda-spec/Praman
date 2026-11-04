<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/bootstrap.php';
\App\Core\Config::load(BASE_PATH . '/config');

$users = \App\Core\Database::all('SELECT user_id, username, emp_code, name, designation, mobile, email, role_id, is_active FROM users');
foreach ($users as $u) {
    echo json_encode($u) . "\n";
}

