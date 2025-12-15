<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

AuthMiddleware::protect(['admin', 'officials']);

$db = new Database();
$conn = $db->getConnection();

// Validate input
if (!isset($_POST['borrowing_item_id']) || !isset($_POST['condition_in'])) {
    $_SESSION['error'] = 'Missing required fields.';
    header("Location: admin_returns.php");
    exit;
}

$borrowing_item_id = (int)$_POST['borrowing_item_id'];
$condition_in = $_POST['condition_in'];
$damage_remarks = trim($_POST['damage_remarks'] ?? '');

// Validate condition
if (!in_array($condition_in, ['Good', 'Fair', 'Damaged'])) {
    $_SESSION['error'] = 'Invalid condition value.';
    header("Location: admin_returns.php");
    exit;
}

// Damaged items require remarks
if ($condition_in === 'Damaged' && empty($damage_remarks)) {
    $_SESSION['error'] = 'Damage remarks are required for damaged items.';
    header("Location: admin_returns.php");
    exit;
}

try {
    $conn->beginTransaction();

    // Get borrowing item details
    $stmt = $conn->prepare("
        SELECT bi.equipment_id, bi.quantity, bi.borrowing_id, bi.condition_out,
               b.borrowing_no, b.borrower_name,
               e.name AS equipment_name
        FROM borrowing_items bi
        JOIN borrowings b ON bi.borrowing_id = b.id
        JOIN equipment e ON bi.equipment_id = e.id
        WHERE bi.id = ? AND bi.status = 'Borrowed'
    ");
    $stmt->execute([$borrowing_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Borrowing item not found or already returned.');
    }

    $returnPhotoId = null;

    // Handle photo upload for damaged items
    if ($condition_in === 'Damaged' && !empty($_FILES['return_photo']['name'])) {
        $file = $_FILES['return_photo'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error.');
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPG, PNG, WEBP allowed.');
        }

        // Max 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File too large. Max 5MB.');
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'return_' . time() . '_' . uniqid() . '.' . $extension;
        
        // Upload directory
        $uploadDir = __DIR__ . '/../uploads/returns/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $destination = $uploadDir . $filename;

        // Move file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Failed to upload photo.');
        }

        // Insert photo record
        $photoStmt = $conn->prepare("
            INSERT INTO return_photos (borrowing_item_id, filename, uploaded_at)
            VALUES (?, ?, NOW())
        ");
        $photoStmt->execute([$borrowing_item_id, $filename]);
        $returnPhotoId = $conn->lastInsertId();
    }

    // Require photo for damaged items
    if ($condition_in === 'Damaged' && !$returnPhotoId) {
        throw new Exception('Photo is required for damaged items.');
    }

    // Determine status
    $status = ($condition_in === 'Damaged') ? 'Damaged' : 'Returned';

    // Insert into returned_items table
    $insertReturn = $conn->prepare("
        INSERT INTO returned_items (
            borrowing_item_id,
            borrowing_id,
            borrowing_no,
            borrower_name,
            equipment_id,
            equipment_name,
            quantity,
            condition_out,
            condition_in,
            damage_remarks,
            return_photo_id,
            return_date,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $insertReturn->execute([
        $borrowing_item_id,
        $item['borrowing_id'],
        $item['borrowing_no'],
        $item['borrower_name'],
        $item['equipment_id'],
        $item['equipment_name'],
        $item['quantity'],
        $item['condition_out'] ?? 'Good',
        $condition_in,
        $damage_remarks ?: null,
        $returnPhotoId,
        $status
    ]);

    // Update borrowing_items status
    $updateItem = $conn->prepare("
        UPDATE borrowing_items
        SET status = 'Returned', condition_in = ?
        WHERE id = ?
    ");
    $updateItem->execute([$condition_in, $borrowing_item_id]);

    // Restore inventory (for all conditions including damaged)
    $updateEquipment = $conn->prepare("
        UPDATE equipment
        SET available_quantity = available_quantity + ?
        WHERE id = ?
    ");
    $updateEquipment->execute([$item['quantity'], $item['equipment_id']]);

    // Auto-log maintenance for damaged items
    if ($condition_in === 'Damaged') {
        $maintenanceStmt = $conn->prepare("
            INSERT INTO maintenance_logs (equipment_id, action, remarks, performed_by, created_at)
            VALUES (?, 'Marked Damaged', ?, ?, NOW())
        ");
        $maintenanceStmt->execute([
            $item['equipment_id'],
            'Auto-logged during return: ' . $damage_remarks,
            $_SESSION['user']['id']
        ]);
    }

    // Check if all items in this borrowing are returned
    $checkAll = $conn->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) as returned
        FROM borrowing_items
        WHERE borrowing_id = ?
    ");
    $checkAll->execute([$item['borrowing_id']]);
    $counts = $checkAll->fetch(PDO::FETCH_ASSOC);

    // If all items returned, update borrowing status
    if ($counts['total'] == $counts['returned']) {
        $updateBorrowing = $conn->prepare("
            UPDATE borrowings
            SET status = 'Returned', actual_return_date = NOW()
            WHERE id = ?
        ");
        $updateBorrowing->execute([$item['borrowing_id']]);
    }

    $conn->commit();
    
    $_SESSION['return_success'] = [
        'borrowing_no' => $item['borrowing_no'],
        'equipment_name' => $item['equipment_name'],
        'borrower_name' => $item['borrower_name'],
        'quantity' => $item['quantity'],
        'condition' => $condition_in
    ];

    header("Location: admin_returns.php");
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    
    // Clean up uploaded file if exists
    if (isset($destination) && file_exists($destination)) {
        unlink($destination);
    }
    
    $_SESSION['error'] = 'Return failed: ' . $e->getMessage();
    header("Location: admin_returns.php");
    exit;
}
?>