<?php
declare(strict_types=1);

/**
 * PRAMAAN installer (command line only):
 *   C:\xampp\php\php.exe database\install.php
 *
 * 1. drops the old databases (the database in .env and the earlier praman_db),
 * 2. creates the database from database/schema.sql
 *    (table names from config/tables.php, database name from .env DB_DATABASE),
 * 3. loads the sample data (every user's password: Praman@123).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/bootstrap.php';

use App\Core\Config;
use App\Core\Table;
use App\Services\SchemaTool;
use App\Services\Seeder;

Config::load(BASE_PATH . '/config');
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$c = Config::get('database');
$server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], (int) $c['port']), $c['user'], $c['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "PRAMAAN installer\n";
foreach (array_unique(['praman_db', $c['name']]) as $old) {
    $server->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '', $old) . '`');
    echo "  - dropped database {$old} (if it existed)\n";
}

$count = 0;
foreach (SchemaTool::statements() as $stmt) {
    $server->exec($stmt);
    $count++;
}
echo "  - created database {$c['name']} ({$count} statements)\n";

$t = microtime(true);
Seeder::run();
printf("  - sample data loaded in %.1f s\n", microtime(true) - $t);

$pdo = App\Core\Database::pdo();
foreach (Table::all() as $table) {
    printf("    %-18s %5d rows\n", $table, (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn());
}
echo "Done. Open http://localhost/pramaan_kda/pramaan_develop/ — login e.g. superadmin / " . Seeder::PASSWORD . "\n";
