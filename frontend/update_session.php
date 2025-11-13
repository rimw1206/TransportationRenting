<?php
// frontend/public/update_session.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$newBalance = $input['newBalance'] ?? null;

if ($newBalance === null || !is_numeric($newBalance)) {
    echo json_encode(['success' => false, 'message' => 'Invalid balance']);
    exit;
}

// Cập nhật balance trong session
$_SESSION['user']['balance'] = (float)$newBalance;

echo json_encode([
    'success' => true, 
    'message' => 'Session updated',
    'newBalance' => $_SESSION['user']['balance']
]);
?>