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

if (!in_array($action, ['approve', 'decline'])) {
    die("Invalid action.");
}

// FETCH request details for both approve and decline
$stmt = $conn->prepare("
    SELECT id, request_no, borrower_name, borrower_contact, borrower_email, borrower_address, expected_return_date
    FROM requests
    WHERE id = :id
");
$stmt->execute([':id' => $id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die("Request not found.");
}

// Check if email exists and is valid
$hasEmail = !empty($request['borrower_email']) && filter_var($request['borrower_email'], FILTER_VALIDATE_EMAIL);
if (!$hasEmail) {
    $_SESSION['email_warning'] = "No valid email address found for borrower: " . htmlspecialchars($request['borrower_name']);
}

/* ============================ APPROVE ============================ */
if ($action === "approve") {

    $unavailableItems = [];
    if (!empty($_POST['unavailable_items'])) {
        $decoded = json_decode($_POST['unavailable_items'], true);
        $unavailableItems = is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    // Update request status
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

    // Generate borrowing number
    $borrowing_no = "BRW-" . time();

    // Insert into borrowings
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

    $borrowing_id = $conn->lastInsertId();

    // Insert borrowing items
    $stmtItems = $conn->prepare("
        SELECT id, equipment_id, quantity, unit_condition 
        FROM request_items 
        WHERE request_id = :request_id
    ");
    $stmtItems->execute([':request_id' => $id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $stmtInsert = $conn->prepare("
        INSERT INTO borrowing_items 
        (borrowing_id, equipment_id, quantity, condition_out)
        VALUES (:borrowing_id, :equipment_id, :quantity, :condition_out)
    ");

    foreach ($items as $item) {
        $itemId = intval($item['id']);
        if (in_array($itemId, $unavailableItems)) continue;

        $stmtInsert->execute([
            ':borrowing_id' => $borrowing_id,
            ':equipment_id' => $item['equipment_id'],
            ':quantity' => $item['quantity'],
            ':condition_out' => $item['unit_condition']
        ]);
    }

    if (!empty($unavailableItems)) {
        $notesUnavailable = "Partial approval: " . count($unavailableItems) . " item(s) unavailable";
        $stmt = $conn->prepare("UPDATE requests SET notes_declined = :notes WHERE id = :id");
        $stmt->execute([':notes'=>$notesUnavailable, ':id'=>$id]);
    }

    // Build approved & unavailable item names
    $approvedNames = [];
    $unavailableNames = [];

    $stmtNames = $conn->prepare("
        SELECT ri.id, e.name 
        FROM request_items ri
        JOIN equipment e ON ri.equipment_id = e.id
        WHERE ri.request_id = :rid
    ");
    $stmtNames->execute([':rid' => $id]);
    $allItems = $stmtNames->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allItems as $i) {
        $itemId = intval($i['id']);
        if (in_array($itemId, $unavailableItems)) {
            $unavailableNames[] = $i['name'];
        } else {
            $approvedNames[] = $i['name'];
        }
    }

    $_SESSION['email_payload'] = [
        'type' => 'approve',
        'borrower_name' => $request['borrower_name'],
        'request_no' => $request['request_no'],
        'approved_items' => !empty($approvedNames) ? implode(', ', $approvedNames) : 'None',
        'unavailable_items' => !empty($unavailableNames) ? implode(', ', $unavailableNames) : 'None',
        'expected_return' => $request['expected_return_date'],
        'email' => $request['borrower_email'] ?? '',
        'has_email' => $hasEmail
    ];

}

/* ============================ DECLINE ============================ */
elseif ($action === "decline") {

    if (!isset($_POST['notes']) || trim($_POST['notes']) === "") {
        die("Notes required when declining.");
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

    $_SESSION['email_payload'] = [
        'type' => 'decline',
        'borrower_name' => $request['borrower_name'],
        'request_no' => $request['request_no'],
        'reason' => $notes,
        'email' => !empty($request['borrower_email']) ? $request['borrower_email'] : $request['borrower_contact'],
        'has_email' => $hasEmail
    ];
}

// Give EmailJS time to send before redirecting
sleep(2);

header("Location: requests_pending.php");
exit;