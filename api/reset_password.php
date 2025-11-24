<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/Database.php';
session_start();

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(["success" => false, "error" => "Email or password missing"]);
    exit;
}

// Optional: verify session
if (!isset($_SESSION['reset_email']) || $_SESSION['reset_email'] !== $email) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

try {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashed, $email]);

    // Clear session
    unset($_SESSION['reset_email']);

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
