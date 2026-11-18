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

// renamed columns: [table key, old name, new name, new definition]
foreach ([['schemes', 'area_registration_no', 'rera_registration_no', 'VARCHAR(30) NULL']] as [$key, $old, $new, $def]) {
    $q = static fn (string $c) => $pdo->query('SHOW COLUMNS FROM ' . Table::q($key) . ' LIKE ' . $pdo->quote($c))->fetch();
    if ($q($old) && !$q($new)) {
        $pdo->exec('ALTER TABLE ' . Table::q($key) . ' CHANGE `' . $old . '` `' . $new . '` ' . $def);
        echo '  ~ ' . Table::name($key) . ".{$old} → {$new}\n";
    }
}

// columns added after the first release: [table key, column, definition]
foreach ([['notifications', 'deleted_at', 'DATETIME NULL AFTER read_at'],
             ['site_inspections', 'length_value', 'DECIMAL(10,2) NULL AFTER task_id'],
             ['site_inspections', 'width_value', 'DECIMAL(10,2) NULL AFTER length_value'],
             ['site_inspections', 'encroachment_type', "ENUM('PARTIAL','COMPLETE') NULL AFTER encroachment"],
             ['site_inspections', 'road_width', 'SMALLINT UNSIGNED NULL AFTER encroachment_details'],
             ['site_inspections', 'road_width_unit', "ENUM('FT','M') NULL AFTER road_width"],
             ['site_inspections', 'is_corner', 'TINYINT(1) NULL AFTER road_width_unit'],
             ['site_inspections', 'latitude', 'DECIMAL(10,8) NULL AFTER is_corner'],
             ['site_inspections', 'longitude', 'DECIMAL(11,8) NULL AFTER latitude'],
             ['properties', 'sector_key', 'INT UNSIGNED AS (IFNULL(sector_id, 0)) STORED AFTER sector_id'],
             ['properties', 'is_corner', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER area_sqm'],
             ['developer_types', 'default_developer', 'VARCHAR(150) NULL AFTER name'],
             ['schemes', 'developer_type_id', 'INT UNSIGNED NULL AFTER area_id'],
             ['schemes', 'developer_name', 'VARCHAR(150) NULL AFTER developer_type_id'],
             ['schemes', 'scheme_landmark', 'VARCHAR(255) NULL AFTER scheme_name'],
             ['schemes', 'plot_type_id', 'INT UNSIGNED NULL AFTER scheme_landmark'],
             ['schemes', 'approve_date', 'DATE NULL AFTER developer_name'],
             ['schemes', 'rera_registration_no', 'VARCHAR(30) NULL AFTER approve_date'],
             ['schemes', 'reserve_price', 'DECIMAL(14,2) NULL AFTER plot_type_id'],
             ['attachments', 'scheme_id', 'INT UNSIGNED NULL AFTER property_id'],
             ['properties', 'sub_plot_type_id', 'INT UNSIGNED NULL AFTER plot_no'], // plot_no always exists (plot_type_id comes later on very old databases)
             ['properties', 'facing', "ENUM('EAST','NORTH','NORTH_EAST','NORTH_WEST','SOUTH','SOUTH_EAST','SOUTH_WEST','WEST') NULL AFTER sub_plot_type_id"],
             ['properties', 'road_type_id', 'INT UNSIGNED NULL AFTER facing'],
             ['properties', 'latitude', 'DECIMAL(10,8) NULL AFTER is_corner'],
             ['properties', 'longitude', 'DECIMAL(11,8) NULL AFTER latitude']] as [$key, $col, $def]) {
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
             ['site_inspections', 'variance', "ENUM('SAME','LESS','MORE') NULL"],
             ['attachments', 'property_id', 'INT UNSIGNED NULL']] as [$key, $col, $def]) { // a scheme's site plan has no property
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
             ['properties', 'current_status', 'status_id', 'INT UNSIGNED NULL AFTER remarks', 'statuses', 'status_id', 'fk_properties_status', 'ix_properties_status'],
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
// Scheme Type Master: schemes.scheme_type used to be a fixed ENUM. Its values (and any value a scheme holds)
// become rows of scheme_types, the schemes are linked by scheme_type_id and the ENUM column goes.
if ($hasCol('schemes', 'scheme_type')) {
    $col = $pdo->query('SHOW COLUMNS FROM ' . Table::q('schemes') . " LIKE 'scheme_type'")->fetch(PDO::FETCH_ASSOC);
    preg_match_all("/'((?:[^']|'')*)'/", (string) $col['Type'], $m);
    $codes = array_values(array_unique(array_merge(array_map(static fn ($v) => str_replace("''", "'", $v), $m[1]),
        $pdo->query('SELECT DISTINCT scheme_type FROM ' . Table::q('schemes') . ' WHERE scheme_type IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN))));
    $label = static fn (string $code): string => ucwords(strtolower(str_replace('_', ' ', $code)));
    $find = $pdo->prepare('SELECT scheme_type_id FROM ' . Table::q('scheme_types') . ' WHERE name = ?');
    $add = $pdo->prepare('INSERT INTO ' . Table::q('scheme_types') . ' (name, sort_order, is_active, is_system) VALUES (?, ?, 1, 0)');
    $ids = [];
    foreach ($codes as $i => $code) {
        $find->execute([$label($code)]);
        $id = $find->fetchColumn();
        if ($id === false) {
            $add->execute([$label($code), $i + 1]);
            $id = $pdo->lastInsertId();
        }
        $ids[$code] = (int) $id;
    }
    if (!$hasCol('schemes', 'scheme_type_id')) {
        $pdo->exec('ALTER TABLE ' . Table::q('schemes') . ' ADD COLUMN scheme_type_id INT UNSIGNED NULL AFTER scheme_name');
    }
    $set = $pdo->prepare('UPDATE ' . Table::q('schemes') . ' SET scheme_type_id = ? WHERE scheme_type = ?');
    foreach ($ids as $code => $id) {
        $set->execute([$id, $code]);
    }
    $pdo->exec('ALTER TABLE ' . Table::q('schemes') . ' DROP COLUMN scheme_type, MODIFY scheme_type_id INT UNSIGNED NOT NULL');
    echo '  ~ schemes.scheme_type → scheme_type_id (' . count($ids) . " scheme types)\n";
}
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
             ['schemes', 'fk_schemes_scheme_type', 'FOREIGN KEY (scheme_type_id) REFERENCES ' . Table::q('scheme_types') . ' (scheme_type_id) ON DELETE RESTRICT'],
             ['schemes', 'fk_schemes_plot_type', 'FOREIGN KEY (plot_type_id) REFERENCES ' . Table::q('plot_types') . ' (plot_type_id) ON DELETE RESTRICT'],
             ['schemes', 'fk_schemes_developer_type', 'FOREIGN KEY (developer_type_id) REFERENCES ' . Table::q('developer_types') . ' (developer_type_id) ON DELETE RESTRICT'],
             ['schemes', 'ck_schemes_price', 'CHECK (reserve_price IS NULL OR reserve_price > 0)'],
             ['properties', 'fk_properties_sub_type', 'FOREIGN KEY (sub_plot_type_id) REFERENCES ' . Table::q('sub_plot_types') . ' (sub_plot_type_id) ON DELETE RESTRICT'],
             ['properties', 'fk_properties_road', 'FOREIGN KEY (road_type_id) REFERENCES ' . Table::q('road_types') . ' (road_type_id) ON DELETE RESTRICT'],
             ['properties', 'ck_properties_geo', 'CHECK ((latitude IS NULL) = (longitude IS NULL))'],
             ['attachments', 'fk_attachments_scheme', 'FOREIGN KEY (scheme_id) REFERENCES ' . Table::q('schemes') . ' (scheme_id) ON DELETE RESTRICT'],
             ['attachments', 'ck_attachments_owner', 'CHECK (property_id IS NOT NULL OR scheme_id IS NOT NULL)'],
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
// Facing is a fixed list (properties.facing), no longer a master: facing_id → facing, then the facings table and its permissions go
if ($hasCol('properties', 'facing_id')) {
    $names = $pdo->query("SHOW TABLES LIKE 'facings'")->fetch()
        ? $pdo->query('SELECT facing_id, name FROM `facings`')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
    $codeOf = array_flip(array_map('strtolower', \App\Core\Lookups::FACINGS)); // "north-east" => NORTH_EAST
    $set = $pdo->prepare('UPDATE ' . Table::q('properties') . ' SET facing = ? WHERE facing_id = ?');
    $unknown = [];
    foreach ($pdo->query('SELECT DISTINCT facing_id FROM ' . Table::q('properties') . ' WHERE facing_id IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $fid) {
        $code = $codeOf[strtolower((string) ($names[$fid] ?? ''))] ?? null;
        if ($code) {
            $set->execute([$code, $fid]);
        } else {
            $unknown[] = $names[$fid] ?? '#' . $fid;
        }
    }
    if ($unknown) {
        echo '  ! properties.facing_id kept: "' . implode('", "', $unknown) . "\" is not in the fixed facing list\n";
    } else {
        $dropFk('properties', 'fk_properties_facing');
        $pdo->exec('ALTER TABLE ' . Table::q('properties') . ' DROP COLUMN facing_id');
        echo "  ~ properties.facing_id → facing (fixed list)\n";
    }
}
if (!$hasCol('properties', 'facing_id') && $pdo->query("SHOW TABLES LIKE 'facings'")->fetch()) {
    $pdo->exec('DROP TABLE `facings`');
    $pdo->exec("DELETE FROM " . Table::q('role_permissions') . " WHERE permission LIKE 'facing.%'");
    $pdo->exec("DELETE FROM " . Table::q('user_permissions') . " WHERE permission LIKE 'facing.%'");
    echo "  - removed the Facing master (table facings and its permissions)\n";
}
// reserve price moved from the property to its scheme (Scheme form)
if ($hasCol('properties', 'reserve_price')) {
    if ((int) $pdo->query('SELECT COUNT(*) FROM ' . Table::q('properties') . ' WHERE reserve_price IS NOT NULL')->fetchColumn() === 0) {
        $dropCheck('properties', 'ck_properties_price');
        $pdo->exec('ALTER TABLE ' . Table::q('properties') . ' DROP COLUMN reserve_price');
        echo "  - removed properties.reserve_price (now on the scheme)\n";
    } else {
        echo "  ! properties.reserve_price kept: it holds data — move it to the schemes, then run again\n";
    }
}
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
