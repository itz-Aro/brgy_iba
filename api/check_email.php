<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/Database.php';
$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));
$email = $data->email ?? '';

if (!$email) {
    echo json_encode(["exists" => false]);
    exit;
}

// Check if email exists
$query = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$query->execute([$email]);
$exists = $query->rowCount() > 0;

if ($exists) {
    $_SESSION['reset_email'] = $email; // store in session
}

echo json_encode(["exists" => $exists]);
