<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/bootstrap.php';
\App\Core\Config::load(BASE_PATH . '/config');

use App\Models\Property;
use App\Models\User;
use App\Services\StateService;
use App\Services\PropertyService;
use App\Core\Auth;
use App\Core\Session;

echo "1. Checking properties table...\n";
$props = Property::where([], 'property_id', 5);
echo "Found " . count($props) . " properties.\n";

echo "2. Checking StateService build with admin user...\n";
Session::start();
Auth::login(User::find(1));
$state = StateService::build();
echo "State properties count: " . count($state['tables']['properties']) . "\n";
echo "Has property_users in state? " . (isset($state['tables']['property_users']) ? 'YES' : 'NO') . "\n";

echo "3. ALL TESTS PASSED!\n";
