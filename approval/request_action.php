<?php
session_start();
require_once __DIR__ . '/../config/Database.php';

if (!isset($_POST['id'], $_POST['action'])) {
    die("Invalid request.");
}

$db = new Database();
$conn = $db->getConnection();

$id = intval($_POST['id']);
$action = $_POST['action'];
$user_id = $_SESSION['user']['id'] ?? 0;

if (!in_array($action, ['approve', 'decline'])) {
    die("Invalid action.");
}

$status = $action === 'approve' ? 'Approved' : 'Declined';

$stmt = $conn->prepare("UPDATE requests 
                        SET status = :status, approver_id = :approver_id, approved_at = NOW()
                        WHERE id = :id");
$stmt->execute([
    ':status' => $status,
    ':approver_id' => $user_id,
    ':id' => $id
]);

header("Location: requests_pending.php");
exit;
