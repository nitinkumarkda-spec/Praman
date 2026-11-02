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
foreach ([['notifications', 'deleted_at', 'DATETIME NULL AFTER read_at'],
             ['properties', 'reserve_price', 'DECIMAL(14,2) NULL AFTER remarks'],
             ['site_inspections', 'length_value', 'DECIMAL(10,2) NULL AFTER task_id'],
             ['site_inspections', 'width_value', 'DECIMAL(10,2) NULL AFTER length_value'],
             ['site_inspections', 'encroachment_type', "ENUM('PARTIAL','COMPLETE') NULL AFTER encroachment"],
             ['site_inspections', 'road_width', 'SMALLINT UNSIGNED NULL AFTER encroachment_details'],
             ['site_inspections', 'road_width_unit', "ENUM('FT','M') NULL AFTER road_width"],
             ['site_inspections', 'is_corner', 'TINYINT(1) NULL AFTER road_width_unit'],
             ['site_inspections', 'latitude', 'DECIMAL(10,8) NULL AFTER is_corner'],
             ['site_inspections', 'longitude', 'DECIMAL(11,8) NULL AFTER latitude']] as [$key, $col, $def]) {
    if (!$pdo->query('SHOW COLUMNS FROM ' . Table::q($key) . ' LIKE ' . $pdo->quote($col))->fetch()) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' ADD COLUMN `' . $col . '` ' . $def);
        echo '  + added ' . Table::name($key) . ".{$col}\n";
    }
}

// Unit Master: area units used to be ENUM('SQM','SQFT'); now they are codes of the units table
\App\Models\Unit::seedDefaults();
foreach ([['properties', 'area_unit', "VARCHAR(20) NOT NULL DEFAULT 'SQM'"], ['site_inspections', 'area_unit', 'VARCHAR(20) NOT NULL'],
             ['site_inspections', 'recorded_unit', 'VARCHAR(20) NOT NULL']] as [$key, $col, $def]) {
    $c = $pdo->query('SHOW COLUMNS FROM ' . Table::q($key) . ' LIKE ' . $pdo->quote($col))->fetch(PDO::FETCH_ASSOC);
    if ($c && str_starts_with(strtolower((string) $c['Type']), 'enum')) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' MODIFY `' . $col . '` ' . $def);
        echo '  ~ ' . Table::name($key) . ".{$col} now refers to the Unit Master\n";
    }
}
$fk = $pdo->prepare('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
// constraints added after the first release: [table key, name, definition]
foreach ([['properties', 'fk_properties_unit', 'FOREIGN KEY (area_unit) REFERENCES ' . Table::q('units') . ' (code) ON UPDATE CASCADE ON DELETE RESTRICT'],
             ['properties', 'ck_properties_price', 'CHECK (reserve_price IS NULL OR reserve_price > 0)'],
             ['site_inspections', 'fk_site_inspections_unit', 'FOREIGN KEY (area_unit) REFERENCES ' . Table::q('units') . ' (code) ON UPDATE CASCADE ON DELETE RESTRICT'],
             ['site_inspections', 'fk_site_inspections_recorded_unit', 'FOREIGN KEY (recorded_unit) REFERENCES ' . Table::q('units') . ' (code) ON UPDATE CASCADE ON DELETE RESTRICT']] as [$key, $name, $def]) {
    $fk->execute([Table::name($key), $name]);
    if (!$fk->fetch()) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' ADD CONSTRAINT ' . $name . ' ' . $def);
        echo "  + {$name}\n";
    }
}

// encroachment details are limited to 300 characters (kept as it is if an older record is longer)
$c = $pdo->query('SHOW COLUMNS FROM ' . Table::q('site_inspections') . " LIKE 'encroachment_details'")->fetch(PDO::FETCH_ASSOC);
if ($c && strtolower((string) $c['Type']) !== 'varchar(300)') {
    if ((int) $pdo->query('SELECT COALESCE(MAX(CHAR_LENGTH(encroachment_details)), 0) FROM ' . Table::q('site_inspections'))->fetchColumn() <= 300) {
        $pdo->exec('ALTER TABLE ' . Table::q('site_inspections') . ' MODIFY encroachment_details VARCHAR(300) NULL');
        echo "  ~ site_inspections.encroachment_details is VARCHAR(300)\n";
    } else {
        echo "  ! site_inspections.encroachment_details kept: an older record is longer than 300 characters\n";
    }
}

// removed features: dropped only while they hold no data, otherwise reported and kept
$c = array_column($pdo->query('SHOW COLUMNS FROM ' . Table::q('section_checks'))->fetchAll(PDO::FETCH_ASSOC), 'Field');
if (array_intersect(['is_mutated', 'mutation_name', 'mutation_date'], $c)) {
    if ((int) $pdo->query('SELECT COUNT(*) FROM ' . Table::q('section_checks') . ' WHERE is_mutated IS NOT NULL OR mutation_name IS NOT NULL OR mutation_date IS NOT NULL')->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE ' . Table::q('section_checks') . ' DROP COLUMN is_mutated, DROP COLUMN mutation_name, DROP COLUMN mutation_date');
        echo "  - removed section_checks mutation columns\n";
    } else {
        echo "  ! section_checks mutation columns kept: they hold data\n";
    }
}
if ($pdo->query("SHOW TABLES LIKE 'sale_checks'")->fetch()) {
    if ((int) $pdo->query('SELECT COUNT(*) FROM `sale_checks`')->fetchColumn() === 0) {
        $pdo->exec('DROP TABLE `sale_checks`');
        echo "  - removed table sale_checks\n";
    } else {
        echo "  ! table sale_checks kept: it holds data\n";
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
