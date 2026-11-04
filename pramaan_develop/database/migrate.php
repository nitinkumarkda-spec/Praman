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
             ['site_inspections', 'longitude', 'DECIMAL(11,8) NULL AFTER latitude'],
             ['properties', 'sector_key', 'INT UNSIGNED AS (IFNULL(sector_id, 0)) STORED AFTER sector_id'],
             ['properties', 'is_corner', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER area_sqm']] as [$key, $col, $def]) {
    if (!$pdo->query('SHOW COLUMNS FROM ' . Table::q($key) . ' LIKE ' . $pdo->quote($col))->fetch()) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' ADD COLUMN `' . $col . '` ' . $def);
        echo '  + added ' . Table::name($key) . ".{$col}\n";
    }
}

// Unit Master: area units used to be ENUM('SQM','SQFT'); now they are codes of the units table
\App\Models\Unit::seedDefaults();
foreach ([['properties', 'area_unit', 'VARCHAR(20) NULL'], ['site_inspections', 'area_unit', 'VARCHAR(20) NOT NULL'],
             ['site_inspections', 'recorded_unit', 'VARCHAR(20) NOT NULL']] as [$key, $col, $def]) {
    $c = $pdo->query('SHOW COLUMNS FROM ' . Table::q($key) . ' LIKE ' . $pdo->quote($col))->fetch(PDO::FETCH_ASSOC);
    if ($c && str_starts_with(strtolower((string) $c['Type']), 'enum')) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' MODIFY `' . $col . '` ' . $def);
        echo '  ~ ' . Table::name($key) . ".{$col} now refers to the Unit Master\n";
    }
}
// Add Property grid: sector and area are optional; a site check of a plot without a recorded area has nothing to compare
$nullable = $pdo->prepare('SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
foreach ([['properties', 'sector_id', 'INT UNSIGNED NULL'], ['properties', 'area_value', 'DECIMAL(12,2) NULL'],
             ['properties', 'area_unit', 'VARCHAR(20) NULL'], ['properties', 'area_sqm', 'DECIMAL(14,4) NULL'],
             ['site_inspections', 'recorded_value', 'DECIMAL(12,2) NULL'], ['site_inspections', 'recorded_unit', 'VARCHAR(20) NULL'],
             ['site_inspections', 'variance', "ENUM('SAME','LESS','MORE') NULL"]] as [$key, $col, $def]) {
    $nullable->execute([Table::name($key), $col]);
    if ($nullable->fetchColumn() === 'NO') {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' MODIFY `' . $col . '` ' . $def);
        echo '  ~ ' . Table::name($key) . ".{$col} may now be empty
";
    }
}
// checks whose rule changed: [table key, name, new definition, text the new rule contains]
$check = $pdo->prepare('SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
foreach ([['properties', 'ck_properties_area', 'CHECK (area_value IS NULL OR area_value > 0)', 'is null']] as [$key, $name, $def, $has]) {
    $check->execute([Table::name($key), $name]);
    $clause = $check->fetchColumn();
    if ($clause !== false && !str_contains(strtolower((string) $clause), $has)) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' DROP CONSTRAINT ' . $name . ', ADD CONSTRAINT ' . $name . ' ' . $def);
        echo "  ~ {$name}
";
    }
}
$fk = $pdo->prepare('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
// No codes: masters are linked by id, schemes have no code / year, properties no property code.
// Every step runs only while the old column is still there, so the migration can run again.
$hasCol = static fn (string $key, string $col): bool => (bool) $pdo->query('SHOW COLUMNS FROM ' . Table::q($key) . ' LIKE ' . $pdo->quote($col))->fetch();
$dropFk = static function (string $key, string $name) use ($pdo, $fk): void {
    $fk->execute([Table::name($key), $name]);
    if ($fk->fetch()) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' DROP FOREIGN KEY `' . $name . '`');
    }
};
$dropIndex = static function (string $key, string $name) use ($pdo): void {
    if ($pdo->query('SHOW INDEX FROM ' . Table::q($key) . ' WHERE Key_name = ' . $pdo->quote($name))->fetch()) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' DROP INDEX `' . $name . '`');
    }
};
$dropCheck = static function (string $key, string $name) use ($pdo, $fk): void {
    $fk->execute([Table::name($key), $name]);
    if ($fk->fetch()) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' DROP CONSTRAINT `' . $name . '`');
    }
};
if ($hasCol('properties', 'area_unit')) {
    $dropCheck('properties', 'ck_properties_area_unit'); // added again below on unit_id
}
// the workflow's statuses get their fixed ids (App\Codes\PropertyStatus) before the codes go
if ($hasCol('statuses', 'code')) {
    $want = \App\Codes\PropertyStatus::IDS;
    $have = $pdo->query('SELECT code, status_id FROM ' . Table::q('statuses'))->fetchAll(PDO::FETCH_KEY_PAIR);
    if (array_intersect_key($have, $want) != array_intersect_key($want, $have)) {
        $pdo->exec('UPDATE ' . Table::q('statuses') . ' SET status_id = status_id + 100000');
        $next = max(array_merge([0], array_values($want))) + 1;
        foreach ($pdo->query('SELECT code FROM ' . Table::q('statuses') . ' ORDER BY status_id')->fetchAll(PDO::FETCH_COLUMN) as $code) {
            $id = $want[$code] ?? $next++;
            $pdo->prepare('UPDATE ' . Table::q('statuses') . ' SET status_id = ? WHERE code = ?')->execute([$id, $code]);
        }
        $pdo->exec('ALTER TABLE ' . Table::q('statuses') . ' AUTO_INCREMENT = ' . $next);
        echo "  ~ statuses: workflow statuses now have their fixed ids\n";
    }
}
// [table key, old code column, new id column, its definition, master table key, master id, old FK, index on the old column]
foreach ([['properties', 'property_type', 'plot_type_id', 'INT UNSIGNED NULL AFTER plot_no', 'plot_types', 'plot_type_id', 'fk_properties_type', null],
             ['properties', 'area_unit', 'unit_id', 'INT UNSIGNED NULL AFTER area_value', 'units', 'unit_id', 'fk_properties_unit', null],
             ['properties', 'current_status', 'status_id', 'INT UNSIGNED NULL AFTER reserve_price', 'statuses', 'status_id', 'fk_properties_status', 'ix_properties_status'],
             ['site_inspections', 'area_unit', 'unit_id', 'INT UNSIGNED NULL AFTER area_value', 'units', 'unit_id', 'fk_site_inspections_unit', null],
             ['site_inspections', 'recorded_unit', 'recorded_unit_id', 'INT UNSIGNED NULL AFTER recorded_value', 'units', 'unit_id', 'fk_site_inspections_recorded_unit', null]]
         as [$key, $old, $new, $def, $master, $mid, $oldFk, $oldIndex]) {
    if (!$hasCol($key, $old)) {
        continue;
    }
    if (!$hasCol($key, $new)) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' ADD COLUMN `' . $new . '` ' . $def);
    }
    $pdo->exec('UPDATE ' . Table::q($key) . ' x JOIN ' . Table::q($master) . ' m ON m.code = x.`' . $old . '` SET x.`' . $new . '` = m.`' . $mid . '`');
    $lost = (int) $pdo->query('SELECT COUNT(*) FROM ' . Table::q($key) . ' WHERE `' . $old . '` IS NOT NULL AND `' . $new . '` IS NULL')->fetchColumn();
    if ($lost) {
        exit('  ! ' . Table::name($key) . ".{$old}: {$lost} row(s) have a code that is not in " . Table::name($master) . " — fix them, then run again.\n");
    }
    $dropFk($key, $oldFk);
    if ($oldIndex) {
        $dropIndex($key, $oldIndex);
    }
    $pdo->exec('ALTER TABLE ' . Table::q($key) . ' DROP COLUMN `' . $old . '`');
    echo '  ~ ' . Table::name($key) . ".{$old} → {$new}\n";
}
foreach ([['properties', 'plot_type_id', 'INT UNSIGNED NOT NULL'], ['properties', 'status_id', 'INT UNSIGNED NOT NULL DEFAULT 1'],
             ['site_inspections', 'unit_id', 'INT UNSIGNED NOT NULL']] as [$key, $col, $def]) {
    $nullable->execute([Table::name($key), $col]);
    if ($nullable->fetchColumn() === 'YES') {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' MODIFY `' . $col . '` ' . $def);
    }
}
// sections with their own verification form were found by their role code
if (!$hasCol('roles', 'verification_form')) {
    $pdo->exec('ALTER TABLE ' . Table::q('roles') . " ADD COLUMN verification_form ENUM('GENERAL','SALE','AUCTION','SITE') NOT NULL DEFAULT 'GENERAL' AFTER role_name");
    if ($hasCol('roles', 'role_code')) {
        $pdo->exec('UPDATE ' . Table::q('roles') . " SET verification_form = CASE role_code WHEN 'SALE_CELL' THEN 'SALE' WHEN 'AUCTION_CELL' THEN 'AUCTION' WHEN 'ENGINEERING_CELL' THEN 'SITE' ELSE 'GENERAL' END");
    }
    echo "  + roles.verification_form\n";
}
$dropCheck('schemes', 'ck_schemes_year');
// [table key, column, its unique index]
foreach ([['roles', 'role_code', 'uq_roles_code'], ['areas', 'area_code', 'uq_areas_code'], ['plot_types', 'code', 'uq_plot_types_code'],
             ['statuses', 'code', 'uq_statuses_code'], ['units', 'code', 'uq_units_code'], ['schemes', 'scheme_code', 'uq_schemes_code'],
             ['schemes', 'year_of_scheme', null], ['properties', 'property_code', 'uq_properties_code']] as [$key, $col, $index]) {
    if ($hasCol($key, $col)) {
        if ($index) {
            $dropIndex($key, $index);
        }
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' DROP COLUMN `' . $col . '`');
        echo '  - removed ' . Table::name($key) . ".{$col}\n";
    }
}

// constraints added after the first release: [table key, name, definition]
foreach ([['properties', 'fk_properties_type', 'FOREIGN KEY (plot_type_id) REFERENCES ' . Table::q('plot_types') . ' (plot_type_id) ON DELETE RESTRICT'],
             ['properties', 'fk_properties_unit', 'FOREIGN KEY (unit_id) REFERENCES ' . Table::q('units') . ' (unit_id) ON DELETE RESTRICT'],
             ['properties', 'fk_properties_status', 'FOREIGN KEY (status_id) REFERENCES ' . Table::q('statuses') . ' (status_id) ON DELETE RESTRICT'],
             ['properties', 'ck_properties_price', 'CHECK (reserve_price IS NULL OR reserve_price > 0)'],
             ['properties', 'ck_properties_area_unit', 'CHECK ((area_value IS NULL) = (unit_id IS NULL) AND (area_value IS NULL) = (area_sqm IS NULL))'],
             ['site_inspections', 'fk_site_inspections_unit', 'FOREIGN KEY (unit_id) REFERENCES ' . Table::q('units') . ' (unit_id) ON DELETE RESTRICT'],
             ['site_inspections', 'fk_site_inspections_recorded_unit', 'FOREIGN KEY (recorded_unit_id) REFERENCES ' . Table::q('units') . ' (unit_id) ON DELETE RESTRICT']] as [$key, $name, $def]) {
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
