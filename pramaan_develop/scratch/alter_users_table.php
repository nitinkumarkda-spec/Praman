<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/bootstrap.php';
\App\Core\Config::load(BASE_PATH . '/config');

$pdo = \App\Core\Database::pdo();

echo "Running ALTER TABLE users...\n";

// Check if uq_users_username index exists
$indexes = \App\Core\Database::all('SHOW INDEX FROM users');
$indexNames = array_column($indexes, 'Key_name');

if (in_array('uq_users_username', $indexNames, true)) {
    $pdo->exec('ALTER TABLE users DROP INDEX uq_users_username');
    echo "  - Dropped index uq_users_username\n";
}

if (in_array('uq_users_emp_code', $indexNames, true)) {
    $pdo->exec('ALTER TABLE users DROP INDEX uq_users_emp_code');
    echo "  - Dropped index uq_users_emp_code\n";
}

// Check columns
$cols = \App\Core\Database::all('DESCRIBE users');
$colNames = array_column($cols, 'Field');

if (in_array('username', $colNames, true)) {
    $pdo->exec('ALTER TABLE users DROP COLUMN username');
    echo "  - Dropped column username\n";
}

if (in_array('emp_code', $colNames, true)) {
    $pdo->exec('ALTER TABLE users DROP COLUMN emp_code');
    echo "  - Dropped column emp_code\n";
}

if (in_array('designation', $colNames, true)) {
    $pdo->exec('ALTER TABLE users DROP COLUMN designation');
    echo "  - Dropped column designation\n";
}

// Make email nullable
$pdo->exec('ALTER TABLE users MODIFY COLUMN email VARCHAR(150) NULL DEFAULT NULL');
echo "  - Modified email column to VARCHAR(150) NULL DEFAULT NULL\n";

echo "\n--- VERIFICATION OF USERS TABLE ---\n";
$newCols = \App\Core\Database::all('DESCRIBE users');
foreach ($newCols as $c) {
    echo $c['Field'] . " | " . $c['Type'] . " | " . $c['Null'] . " | " . $c['Key'] . "\n";
}

