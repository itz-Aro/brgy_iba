<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

if (!$username || !$password) {
    echo json_encode(['success' => false, 'error' => 'Please enter username and password']);
    exit;
}

try {
    $query = "SELECT u.id, u.username, u.password, u.fullname, r.name AS role
          FROM users u
          JOIN roles r ON u.role_id = r.id
          WHERE BINARY u.username = :username
          LIMIT 1";


    $stmt = $conn->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ❌ Username not found
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Username not found']);
        exit;
    }

    // ❌ Password incorrect
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Incorrect password']);
        exit;
    }

    // ✅ Login successful
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'fullname' => $user['fullname'],
        'role' => strtolower($user['role'])
    ];

    echo json_encode(['success' => true, 'user' => $_SESSION['user']]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
