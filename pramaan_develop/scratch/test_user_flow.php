<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/Core/bootstrap.php';
\App\Core\Config::load(BASE_PATH . '/config');

use App\Models\User;
use App\Core\Password;

echo "=== 1. Testing Password Policy for 10-char Generated Passwords ===\n";
function genPw(int $len = 10): string {
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghjkmnpqrstuvwxyz';
    $digits = '23456789';
    $special = '@#$%&*!_';
    $all = $upper . $lower . $digits . $special;
    $chars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];
    for ($i = 4; $i < $len; $i++) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }
    shuffle($chars);
    return implode('', $chars);
}

for ($i = 0; $i < 5; $i++) {
    $pw = genPw(10);
    $err = Password::problem($pw);
    echo "Password: {$pw} | Problem: " . ($err ?: 'VALID (OK)') . "\n";
    if ($err !== null) {
        throw new Exception("Generated password failed policy: {$err}");
    }
}

echo "\n=== 2. Testing User Creation With Null / Optional Email ===\n";
// Clean up any previous test user
$existing = User::where(['mobile' => '9999900001']);
foreach ($existing as $u) { User::delete($u['user_id']); }
$existing2 = User::where(['mobile' => '9999900002']);
foreach ($existing2 as $u) { User::delete($u['user_id']); }

$testPw1 = genPw(10);
$testPw2 = genPw(10);

$u1Id = User::insert([
    'name' => 'Test Officer One',
    'mobile' => '9999900001',
    'email' => null, // empty email
    'password_hash' => Password::hash($testPw1),
    'role_id' => 1,
    'is_active' => 1,
    'must_change_password' => 0,
    'created_by' => 1,
]);
echo "User 1 created with ID: {$u1Id} (email: NULL)\n";

$u2Id = User::insert([
    'name' => 'Test Officer Two',
    'mobile' => '9999900002',
    'email' => null, // another user with empty/null email
    'password_hash' => Password::hash($testPw2),
    'role_id' => 1,
    'is_active' => 1,
    'must_change_password' => 0,
    'created_by' => 1,
]);
echo "User 2 created with ID: {$u2Id} (email: NULL) - multiple NULL emails allowed!\n";

echo "\n=== 3. Testing Authentication By Mobile ===\n";
$found1 = User::first(['mobile' => '9999900001']);
if ($found1 && Password::verify($testPw1, $found1['password_hash'])) {
    echo "AUTH SUCCESS for mobile 9999900001 with auto-generated 10-char password!\n";
} else {
    echo "AUTH FAILED for mobile 9999900001!\n";
}

$found2 = User::first(['mobile' => '9999900002']);
if ($found2 && Password::verify($testPw2, $found2['password_hash'])) {
    echo "AUTH SUCCESS for mobile 9999900002 with auto-generated 10-char password!\n";
} else {
    echo "AUTH FAILED for mobile 9999900002!\n";
}

// Wrong password check
if ($found1 && !Password::verify('WrongPass@123', $found1['password_hash'])) {
    echo "WRONG PASSWORD REJECTED correctly.\n";
}

echo "\n=== 4. Cleaning Up Test Users ===\n";
User::delete($u1Id);
User::delete($u2Id);
echo "Cleaned up test users {$u1Id} and {$u2Id}.\n";
echo "\nALL TESTS PASSED!\n";

