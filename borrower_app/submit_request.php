<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $address = $_POST['address'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $items = json_decode($_POST['items'] ?? '[]', true);

    if(empty($fullname) || empty($contact) || empty($start_date) || empty($end_date) || empty($items)){
        echo json_encode(['status'=>'error','message'=>'All required fields must be filled.']);
        exit;
    }

    $conn->beginTransaction();

    // Generate request number
    $request_no = 'REQ-' . date('YmdHis');

    // Insert into requests table
    $stmt = $conn->prepare("INSERT INTO requests (request_no, created_by, borrower_name, borrower_email, borrower_contact, borrower_address, date_needed, expected_return_date, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $request_no,
        1, // replace with logged-in user ID if available
        $fullname,
        $email,
        $contact,
        $address,
        $start_date,
        $end_date,
        $notes
    ]);

    $request_id = $conn->lastInsertId();

    // Insert request_items and reduce equipment stock
    $stmtItem = $conn->prepare("INSERT INTO request_items (request_id, equipment_id, quantity) VALUES (?, ?, ?)");
    $stmtUpdate = $conn->prepare("UPDATE equipment SET available_quantity = available_quantity - ? WHERE id = ?");
    foreach($items as $item){
        // Check stock
        $stmtCheck = $conn->prepare("SELECT available_quantity FROM equipment WHERE id = ?");
        $stmtCheck->execute([$item['id']]);
        $equip = $stmtCheck->fetch();
        if(!$equip || $equip['available_quantity'] < $item['qty']){
            throw new Exception("Not enough stock for equipment ID {$item['id']}");
        }

        $stmtItem->execute([$request_id, $item['id'], $item['qty']]);
        $stmtUpdate->execute([$item['qty'], $item['id']]);
    }

    $conn->commit();

    echo json_encode(['status'=>'success']);
} catch(Exception $e){
    $conn->rollBack();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>
