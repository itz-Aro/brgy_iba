<?php
session_start();
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['return_photo']) || $_FILES['return_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['return_photo'];
$itemId = $_POST['item_id'] ?? 0;

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images are allowed.']);
    exit;
}

// Validate file size (5MB max)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB.']);
    exit;
}

try {
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/returns/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'return_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        exit;
    }

    // Save to database
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        INSERT INTO return_photos (filename, uploaded_at)
        VALUES (?, NOW())
    ");
    $stmt->execute([$filename]);

    $photoId = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $photoId,
        'filename' => $filename,
        'message' => 'Photo uploaded successfully'
    ]);

} catch (Exception $e) {
    // Delete file if database insert fails
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>