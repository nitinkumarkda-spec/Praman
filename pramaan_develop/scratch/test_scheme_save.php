<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/Core/bootstrap.php';
\App\Core\Config::load(BASE_PATH . '/config');

use App\Models\Scheme;
use App\Models\Sector;
use App\Models\Area;

echo "=== Testing Scheme Creation Without Role-User Mapping ===\n";
$areas = Area::where();
$areaId = $areas ? $areas[0]['area_id'] : null;

$id = Scheme::insert([
    'scheme_name' => 'Test Scheme Without Mapping',
    'scheme_type' => 'RESIDENTIAL',
    'area_id' => $areaId,
    'is_active' => 1,
    'created_by' => 1
]);
echo "Scheme created successfully with ID: {$id}\n";

$secId = Sector::insert([
    'scheme_id' => $id,
    'sector_name' => 'Sector A',
    'sort_order' => 1,
    'is_active' => 1,
    'created_by' => 1
]);
echo "Sector created with ID: {$secId}\n";

$found = Scheme::find($id);
echo "Verified Scheme: {$found['scheme_name']} ({$found['scheme_type']})\n";

// Clean up
Sector::delete($secId);
Scheme::delete($id);
echo "Cleaned up test scheme.\n";
echo "SUCCESS!\n";
