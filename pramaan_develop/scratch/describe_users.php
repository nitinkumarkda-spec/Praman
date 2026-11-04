<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/bootstrap.php';
\App\Core\Config::load(BASE_PATH . '/config');

$cols = \App\Core\Database::all('DESCRIBE users');
foreach ($cols as $c) {
    echo $c['Field'] . " | " . $c['Type'] . " | " . $c['Null'] . " | " . $c['Key'] . "\n";
}
echo "\n--- INDEXES ---\n";
$indexes = \App\Core\Database::all('SHOW INDEX FROM users');
foreach ($indexes as $i) {
    echo $i['Key_name'] . " | " . $i['Column_name'] . " | " . ($i['Non_unique'] ? 'NON-UNIQUE' : 'UNIQUE') . "\n";
}
