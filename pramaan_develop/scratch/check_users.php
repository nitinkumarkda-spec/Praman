<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/Core/bootstrap.php';
\App\Core\Config::load(BASE_PATH . '/config');

$users = \App\Models\User::where();
echo "Total users in DB: " . count($users) . "\n";
foreach ($users as $u) {
    echo "ID: {$u['user_id']} | Name: {$u['name']} | Mobile: {$u['mobile']} | Email: " . ($u['email'] ?? 'NULL') . " | Role ID: {$u['role_id']} | Active: {$u['is_active']}\n";
}
