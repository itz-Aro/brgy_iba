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

/* ============================
   ✅ APPROVE REQUEST
=============================== */
if ($action === "approve") {

    // 1. GET REQUEST DETAILS
    $stmt = $conn->prepare("
        SELECT borrower_name, borrower_contact, borrower_address, expected_return_date 
        FROM requests 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        die("Request not found.");
    }

    // 2. UPDATE REQUEST STATUS
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

    // 3. GENERATE BORROWING NUMBER
    $borrowing_no = "BRW-" . time();

    // 4. INSERT INTO BORROWINGS TABLE
    $stmt = $conn->prepare("
        INSERT INTO borrowings 
        (borrowing_no, request_id, borrower_name, borrower_contact, borrower_address, approved_by, expected_return_date)
        VALUES 
        (:borrowing_no, :request_id, :borrower_name, :borrower_contact, :borrower_address, :approved_by, :expected_return_date)
    ");
    $stmt->execute([
        ':borrowing_no' => $borrowing_no,
        ':request_id' => $id,
        ':borrower_name' => $request['borrower_name'],
        ':borrower_contact' => $request['borrower_contact'],
        ':borrower_address' => $request['borrower_address'],
        ':approved_by' => $user_id,
        ':expected_return_date' => $request['expected_return_date']
    ]);

    // 5. GET NEW BORROWING ID
    $borrowing_id = $conn->lastInsertId();

    // 6. GET REQUEST ITEMS
    $stmt = $conn->prepare("
        SELECT equipment_id, quantity, unit_condition 
        FROM request_items 
        WHERE request_id = :request_id
    ");
    $stmt->execute([':request_id' => $id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. INSERT INTO BORROWING ITEMS
    $stmt = $conn->prepare("
        INSERT INTO borrowing_items 
        (borrowing_id, equipment_id, quantity, condition_out)
        VALUES
        (:borrowing_id, :equipment_id, :quantity, :condition_out)
    ");

    foreach ($items as $item) {
        $stmt->execute([
            ':borrowing_id' => $borrowing_id,
            ':equipment_id' => $item['equipment_id'],
            ':quantity' => $item['quantity'],
            ':condition_out' => $item['unit_condition']
        ]);
    }
}


/* ============================
   ✅ DECLINE REQUEST
=============================== */
elseif ($action === "decline") {

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

// ✅ REDIRECT BACK
header("Location: requests_pending.php");
exit;
