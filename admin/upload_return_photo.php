<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Set JSON header first
header('Content-Type: application/json');

// Check authentication
try {
    AuthMiddleware::protect(['admin', 'officials']);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['return_photo'])) {
        throw new Exception('No file uploaded. Please select a photo.');
    }

    $file = $_FILES['return_photo'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
        throw new Exception($errorMsg);
    }

    $itemId = $_POST['item_id'] ?? 0;

    // Validate file exists
    if (!file_exists($file['tmp_name'])) {
        throw new Exception('Uploaded file not found.');
    }

    // Validate file type using mime_content_type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $mimeType = mime_content_type($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed. Detected: ' . $mimeType);
    }

    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size exceeds 5MB limit. File size: ' . round($file['size'] / 1024 / 1024, 2) . 'MB');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/returns/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory: ' . $uploadDir);
        }
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory is not writable: ' . $uploadDir);
    }

    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (empty($extension)) {
        // Get extension from mime type
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        ];
        $extension = $mimeToExt[$mimeType] ?? 'jpg';
    }

    $filename = 'return_' . time() . '_' . uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to move uploaded file to: ' . $uploadPath);
    }

    // Verify file was saved
    if (!file_exists($uploadPath)) {
        throw new Exception('File was not saved successfully.');
    }

    // Insert into database
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        INSERT INTO return_photos (filename, upload_date) 
        VALUES (?, NOW())
    ");
    
    if (!$stmt->execute([$filename])) {
        // If database insert fails, delete the uploaded file
        unlink($uploadPath);
        throw new Exception('Failed to save photo information to database.');
    }

    $photoId = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $photoId,
        'filename' => $filename,
        'message' => 'Photo uploaded successfully',
        'file_size' => $file['size'],
        'mime_type' => $mimeType
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'files_received' => isset($_FILES['return_photo']),
            'file_error' => $_FILES['return_photo']['error'] ?? 'N/A',
            'file_size' => $_FILES['return_photo']['size'] ?? 'N/A',
            'file_tmp' => $_FILES['return_photo']['tmp_name'] ?? 'N/A',
            'upload_dir' => __DIR__ . '/../uploads/returns/',
            'post_data' => array_keys($_POST)
        ]
    ]);
}
?>