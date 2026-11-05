<?php
define('BASE_PATH', __DIR__);
require_once __DIR__ . '/app/Core/bootstrap.php';
App\Core\Config::load(__DIR__ . '/config');
$db = App\Core\Database::pdo();

$roles = [8, 9, 10, 11]; // Sale, Auction, General, Engineer
foreach ($roles as $rid) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission = 'property.view'");
    $stmt->execute([$rid]);
    if ($stmt->fetchColumn() == 0) {
        $ins = $db->prepare("INSERT INTO role_permissions (role_id, permission, created_by, created_at) VALUES (?, 'property.view', 1, NOW())");
        $ins->execute([$rid]);
        echo "Granted property.view to role_id $rid\n";
    } else {
        echo "Role $rid already has property.view\n";
    }
}

echo "\n--- Current role_permissions for verification roles ---\n";
$stmt = $db->query("SELECT rp.*, r.role_name FROM role_permissions rp JOIN roles r ON r.role_id = rp.role_id WHERE rp.role_id IN (8,9,10,11)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
