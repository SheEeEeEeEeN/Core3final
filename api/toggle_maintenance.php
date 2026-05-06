<?php
// api/toggle_maintenance.php
include('../connection.php');
include('../session.php');
requireRoles(['super admin', 'super_admin']);

header('Content-Type: application/json');

$flagFile = __DIR__ . '/../maintenance.flag';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'enable') {
        file_put_contents($flagFile, time());
        echo json_encode(['success' => true, 'status' => 'enabled']);
        exit;
    } elseif ($action === 'disable') {
        if (file_exists($flagFile)) {
            unlink($flagFile);
        }
        echo json_encode(['success' => true, 'status' => 'disabled']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
