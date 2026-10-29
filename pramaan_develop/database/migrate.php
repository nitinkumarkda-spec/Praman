<?php
declare(strict_types=1);

/**
 * PRAMAAN — upgrade an existing database without losing data (command line only):
 *   C:\xampp\php\php.exe database\migrate.php
 * 1. creates any table from schema.sql that is missing,
 * 2. adds columns introduced after the first release,
 * 3. adds missing indexes and rebuilds changed ones (same as schema.sql),
 * 4. fills the default App Settings and landing slides (existing values are kept).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Table;
use App\Services\SchemaTool;
use App\Services\Seeder;

Config::load(BASE_PATH . '/config');
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Kolkata'));
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$pdo = Database::pdo();

echo "PRAMAAN migration\n";
$have = array_map('strtolower', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
foreach (SchemaTool::tables() as $key => $create) {
    if (!in_array(strtolower(Table::name($key)), $have, true)) {
        $pdo->exec((string) preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $create));
        echo '  + created table ' . Table::name($key) . "\n";
    }
}

// columns added after the first release: [table key, column, definition]
foreach ([['notifications', 'deleted_at', 'DATETIME NULL AFTER read_at']] as [$key, $col, $def]) {
    if (!$pdo->query('SHOW COLUMNS FROM ' . Table::q($key) . ' LIKE ' . $pdo->quote($col))->fetch()) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' ADD COLUMN `' . $col . '` ' . $def);
        echo '  + added ' . Table::name($key) . ".{$col}\n";
    }
}

foreach (SchemaTool::syncIndexes($pdo) as $line) {
    echo "  {$line}\n";
}

// indexes replaced by wider ones: [table key, index]
foreach ([['properties', 'ix_properties_valid']] as [$key, $index]) {
    if ($pdo->query('SHOW INDEX FROM ' . Table::q($key) . ' WHERE Key_name = ' . $pdo->quote($index))->fetch()) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' DROP INDEX `' . $index . '`');
        echo '  - removed ' . Table::name($key) . ".{$index}\n";
    }
}

Seeder::seedConfig();
@unlink(BASE_PATH . '/storage/cache/housekeeping.txt');
echo "Migration done.\n";
