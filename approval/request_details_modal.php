<?php
// request_details_modal.php
require_once __DIR__ . '/../config/Database.php';

if(!isset($_GET['id'])) {
    echo "<p style='color:red; text-align:center;'>Request ID missing.</p>";
    exit;
}

$id = intval($_GET['id']);
$db = new Database();
$conn = $db->getConnection();

// Fetch request
$stmt = $conn->prepare("
    SELECT r.*, u.fullname AS creator_name
    FROM requests r
    JOIN users u ON r.created_by = u.id
    WHERE r.id = :id
");
$stmt->bindParam(':id', $id);
$stmt->execute();
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$request){
    echo "<p style='color:red; text-align:center;'>Request not found.</p>";
    exit;
}

// Fetch request items
$stmtItems = $conn->prepare("
    SELECT ri.*, e.name AS equipment_name
    FROM request_items ri
    JOIN equipment e ON ri.equipment_id = e.id
    WHERE ri.request_id = ?
");
$stmtItems->execute([$request['id']]);
$requestItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Function to get status badge color
function getStatusBadge($status) {
    switch(strtolower($status)) {
        case 'approved': return '<span class="badge badge-approved">Approved</span>';
        case 'declined': return '<span class="badge badge-declined">Declined</span>';
        default: return '<span class="badge badge-pending">Pending</span>';
    }
}
?>

<style>
/* Card container */
.request-card {
    max-width: 750px;
    margin: 20px auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    overflow: hidden;
}

/* Header */
.request-card h2 {
    background: linear-gradient(90deg, #073a6a, #1f618d);
    color: #fff;
    margin: 0;
    padding: 20px;
    text-align: center;
    font-size: 1.6em;
}

/* Status badge */
.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 12px;
    font-weight: 600;
    color: #fff;
    margin-left: 10px;
    font-size: 0.9em;
}
.badge-approved { background-color: #27ae60; }
.badge-declined { background-color: #c0392b; }
.badge-pending { background-color: #f39c12; }

/* Grid layout for request info */
.request-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    padding: 20px;
}

.request-info p {
    margin: 0;
    line-height: 1.4;
}

.request-info strong {
    color: #073a6a;
}

/* Requested items table */
.request-items {
    width: 100%;
    border-collapse: collapse;
    margin: 0 20px 20px 20px;
}

.request-items th, .request-items td {
    padding: 10px;
    text-align: left;
}

.request-items th {
    background-color: #073a6a;
    color: #fff;
    font-weight: 600;
}

.request-items tbody tr:nth-child(even) {
    background-color: #f7f7f7;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 0.85em;
    color: #fff;
    margin-left: 5px;
}
.badge-quantity { background-color: #27ae60; }
.badge-condition { background-color: #2980b9; }

/* Remarks and notes */
.remarks, .notes-declined {
    padding: 15px 20px;
    border-top: 1px solid #eee;
}

.notes-declined { 
    color: #c0392b; 
    font-weight: 600;
}

@media (max-width: 600px) {
    .request-info {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="request-card">
    <h2>
        Request Details
        <?= getStatusBadge($request['status']) ?>
    </h2>

    <div class="request-info">
        <p><strong>Borrower:</strong> <?= htmlspecialchars($request['borrower_name']) ?></p>
        <p><strong>Request #:</strong> <?= htmlspecialchars($request['request_no']) ?></p>
        <p><strong>Created By:</strong> <?= htmlspecialchars($request['creator_name']) ?></p>
        <p><strong>Contact:</strong> <?= htmlspecialchars($request['borrower_contact']) ?></p>
        <p><strong>Date Needed:</strong> <?= htmlspecialchars($request['date_needed']) ?></p>
        <p><strong>Expected Return:</strong> <?= htmlspecialchars($request['expected_return_date']) ?></p>
        <p style="grid-column: span 2;"><strong>Address:</strong> <?= htmlspecialchars($request['borrower_address']) ?></p>
    </div>

    <div>
        <strong style="padding: 0 20px;">Requested Items:</strong>
        <?php if(count($requestItems) > 0): ?>
            <table class="request-items">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Condition</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($requestItems as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['equipment_name']) ?></td>
                        <td><span class="badge badge-quantity"><?= htmlspecialchars($item['quantity']) ?></span></td>
                        <td><span class="badge badge-condition"><?= htmlspecialchars($item['unit_condition']) ?></span></td>
                        <td><?= getStatusBadge($item['status'] ?? $request['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#666; margin:10px 20px;">No items requested.</p>
        <?php endif; ?>
    </div>

    <div class="remarks">
        <strong>Remarks:</strong>
        <p><?= htmlspecialchars($request['remarks'] ?: '-') ?></p>
    </div>

    <?php if(!empty($request['notes_declined'])): ?>
    <div class="notes-declined">
        <strong>Notes Declined:</strong>
        <p><?= htmlspecialchars($request['notes_declined']) ?></p>
    </div>
    <?php endif; ?>
</div>
