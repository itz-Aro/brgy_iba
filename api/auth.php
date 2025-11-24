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
              WHERE u.username = :username
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Log failed login (non-existent username)
        $logStmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, resource_type, resource_id, details, ip_address)
            VALUES (NULL, 'failed_login', 'user', NULL, :details, :ip)
        ");
        $logStmt->execute([
            ':details' => "Attempted login with non-existent username: $username",
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);

        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        // Log failed login (wrong password)
        $logStmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, resource_type, resource_id, details, ip_address)
            VALUES (:user_id, 'failed_login', 'user', :user_id, :details, :ip)
        ");
        $logStmt->execute([
            ':user_id' => $user['id'],
            ':details' => 'Incorrect password attempt',
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);

        echo json_encode(['success' => false, 'error' => 'Incorrect password']);
        exit;
    }

    // Successful login
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'fullname' => $user['fullname'],
        'role' => strtolower($user['role'])
    ];

    // Log successful login
    $logStmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, resource_type, resource_id, details, ip_address)
        VALUES (:user_id, 'login', 'user', :user_id, :details, :ip)
    ");
    $logStmt->execute([
        ':user_id' => $user['id'],
        ':details' => 'User logged in successfully',
        ':ip' => $_SERVER['REMOTE_ADDR']
    ]);

    echo json_encode(['success' => true, 'user' => $_SESSION['user']]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
