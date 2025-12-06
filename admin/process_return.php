<?php
session_start();
require_once __DIR__ . '/../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

$borrowing_item_id = $_POST['borrowing_item_id'];
$condition_in = $_POST['condition_in'];

// ✅ Update borrowing item condition
$conn->prepare("
    UPDATE borrowing_items 
    SET condition_in = ? 
    WHERE id = ?
")->execute([$condition_in, $borrowing_item_id]);

// ✅ Get borrowing + equipment info
$stmt = $conn->prepare("
    SELECT bi.equipment_id, bi.quantity, b.id AS borrowing_id
    FROM borrowing_items bi
    JOIN borrowings b ON bi.borrowing_id = b.id
    WHERE bi.id = ?
");
$stmt->execute([$borrowing_item_id]);
$data = $stmt->fetch();

// ✅ Set borrowing as Returned
$conn->prepare("
    UPDATE borrowings 
    SET status='Returned', actual_return_date = NOW() 
    WHERE id = ?
")->execute([$data['borrowing_id']]);

// ✅ AUTO RESTORE INVENTORY (GOOD / FAIR)
if ($condition_in !== 'Damaged') {
    $conn->prepare("
        UPDATE equipment 
        SET available_quantity = available_quantity + ?
        WHERE id = ?
    ")->execute([$data['quantity'], $data['equipment_id']]);
}

// ✅ AUTO MAINTENANCE LOG IF DAMAGED ✅✅✅
if ($condition_in === 'Damaged') {
    $conn->prepare("
        INSERT INTO maintenance_logs (equipment_id, action, remarks, performed_by)
        VALUES (?, 'Marked Damaged', 'Auto logged during return', ?)
    ")->execute([
        $data['equipment_id'],
        $_SESSION['user']['id']
    ]);
}

// ✅ UPLOAD PHOTO
if (!empty($_FILES['return_photo']['name'])) {
    $filename = time() . '_' . $_FILES['return_photo']['name'];
    move_uploaded_file($_FILES['return_photo']['tmp_name'], "../uploads/returns/" . $filename);

    $conn->prepare("
        INSERT INTO return_photos (borrowing_item_id, filename)
        VALUES (?, ?)
    ")->execute([$borrowing_item_id, $filename]);
}

header("Location: admin_returns.php");
