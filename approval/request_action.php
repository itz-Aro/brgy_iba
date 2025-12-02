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

// Validate action
if (!in_array($action, ['approve', 'decline'])) {
    die("Invalid action.");
}

if ($action === "approve") {
    // APPROVE REQUEST
    $stmt = $conn->prepare("
        UPDATE requests 
        SET status = 'Approved',
            approver_id = :approver_id,
            approved_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':approver_id' => $user_id,
        ':id' => $id
    ]);

} elseif ($action === "decline") {
    // DECLINE REQUEST WITH NOTES DECLINED
    if (!isset($_POST['notes']) || trim($_POST['notes']) === "") {
        die("Notes are required when declining.");
    }

    $notes = trim($_POST['notes']);

    $stmt = $conn->prepare("
        UPDATE requests 
        SET status = 'Declined',
            notes_declined = :notes,
            approver_id = :approver_id,
            approved_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':notes' => $notes,
        ':approver_id' => $user_id,
        ':id' => $id
    ]);
}

// Redirect back to pending requests
header("Location: requests_pending.php");
exit;
