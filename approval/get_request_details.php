<?php
session_start();
require_once __DIR__ . '/../config/Database.php';

if (!isset($_GET['id'])) {
    echo '<p style="text-align: center; color: #d32f2f;">Request ID missing.</p>';
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$id = intval($_GET['id']);

// Fetch request details
$stmt = $conn->prepare("SELECT r.*, u.fullname AS creator_name FROM requests r JOIN users u ON r.created_by = u.id WHERE r.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo '<p style="text-align: center; color: #d32f2f;">Request not found.</p>';
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
?>

<?php

require_once __DIR__ . '/../config/Database.php';

if (!isset($_GET['id'])) {
    echo '<p style="text-align: center; color: #d32f2f;">Request ID missing.</p>';
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$id = intval($_GET['id']);

// Fetch request details
$stmt = $conn->prepare("SELECT r.*, u.fullname AS creator_name 
                        FROM requests r 
                        JOIN users u ON r.created_by = u.id 
                        WHERE r.id = :id");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo '<p style="text-align: center; color: #d32f2f;">Request not found.</p>';
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

// Helper to output safe field
function esc($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES);
}
?>

<!-- Embedded style matches the Requests / Returns UI -->
<style>
/* Card & modal content styling (keeps consistent with main UI) */
.request-detail-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: 'Poppins', sans-serif;
  color: #334155;
}

/* Header */
.card-header {
  display:flex;
  gap:18px;
  align-items:center;
  padding-bottom:16px;
  border-bottom:2px solid #e5e9f2;
}
.avatar-photo-large {
  width:72px;
  height:72px;
  border-radius:50%;
  object-fit:cover;
  border:3px solid #cfe1ff;
  background:#cfe1ff;
  display:flex;
  justify-content:center;
  align-items:center;
  font-weight:700;
  color:#0d47a1;
  font-size:24px;
}
.card-header h3 {
  margin:0;
  font-weight:800;
  font-size:22px;
  color:#073a6a;
}
.card-header .details {
  font-size:14px;
  color:#657085;
  margin-top:4px;
}

/* Grid info */
.grid-info {
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap:14px;
  font-size:14px;
  color:#657085;
  padding:16px;
  background:#f9fbff;
  border-radius:10px;
}
.grid-info div { display:flex; flex-direction:column; gap:4px; }
.grid-info strong { color:#073a6a; font-weight:600; }

/* Request items table */
.request-items {
  background:#f9fbff;
  padding:18px;
  border-radius:10px;
}
.request-items h4 {
  margin:0 0 12px 0;
  color:#073a6a;
  font-weight:700;
  font-size:18px;
}
.request-items table { width:100%; border-collapse:collapse; margin-top:6px; }
.request-items th {
  background: #0d47a1;
  color:white;
  padding:10px;
  font-size:13px;
  font-weight:700;
  text-align:left;
}
.request-items th:first-child { border-radius:6px 0 0 6px; }
.request-items th:last-child { border-radius:0 6px 6px 0; }
.request-items td {
  padding:10px;
  font-size:14px;
  color:#444;
  background:white;
  border-bottom:1px solid #e5e9f2;
}
.request-items tr:last-child td { border-bottom:none; }

/* Remarks */
.remarks-section {
  padding:16px;
  background:#f9fbff;
  border-radius:10px;
}
.remarks-section .label {
  font-size:14px;
  color:#073a6a;
  font-weight:600;
  margin-bottom:8px;
}
.remarks-section p { margin:0; color:#657085; font-size:14px; }

/* Declined notes */
.declined-notes {
  padding:16px;
  background:#ffebee;
  border-radius:10px;
  border-left:4px solid #d32f2f;
}
.declined-notes .label { font-size:14px; color:#d32f2f; font-weight:600; margin-bottom:8px; }
.declined-notes p { margin:0; color:#c62828; font-size:14px; }

/* Actions */
.modal-actions {
  display:flex;
  gap:12px;
  padding-top:16px;
  border-top:2px solid #e5e9f2;
}
.btn-action {
  padding:12px 20px;
  font-weight:600;
  border-radius:10px;
  border:none;
  color:white;
  cursor:pointer;
  transition:0.2s;
  font-size:14px;
}
.btn-action:hover { transform:translateY(-2px); opacity:0.95; }
.btn-approve { background-color:#1b8b1b; }
.decline-btn { background-color:#d32f2f; padding:12px 20px; font-weight:600; border-radius:10px; border:none; color:white; cursor:pointer; transition:0.2s; font-size:14px; }

/* Small responsive tweaks inside modal content */
@media (max-width:640px) {
  .grid-info { grid-template-columns: 1fr; }
  .card-header { gap:12px; }
  .avatar-photo-large { width:64px; height:64px; }
}
</style>

<!-- Request detail markup (styled) -->
<div class="request-detail-card" role="document" aria-labelledby="request-title-<?= esc($request['id']) ?>">

    <div class="card-header">
        <?php if (!empty($request['borrower_id_photo']) && file_exists(__DIR__ . '/../uploads/' . $request['borrower_id_photo'])): ?>
            <img class="avatar-photo-large" src="../uploads/<?= esc($request['borrower_id_photo']) ?>" alt="Borrower ID Photo">
        <?php else: ?>
            <div class="avatar-photo-large"><?= strtoupper(substr($request['borrower_name'] ?? 'N/A', 0, 2)) ?></div>
        <?php endif; ?>

        <div>
            <h3 id="request-title-<?= esc($request['id']) ?>"><?= esc($request['borrower_name']) ?></h3>
            <div class="details">Request #: <?= esc($request['request_no']) ?></div>
            <div class="details">Created By: <?= esc($request['creator_name']) ?></div>
        </div>
    </div>

    <div class="grid-info">
        <div>
            <strong>Date Needed</strong>
            <span><?= esc($request['date_needed']) ?: '-' ?></span>
        </div>
        <div>
            <strong>Expected Return</strong>
            <span><?= esc($request['expected_return_date']) ?: '-' ?></span>
        </div>
        <div>
            <strong>Borrower Contact</strong>
            <span><?= esc($request['borrower_contact']) ?: '-' ?></span>
        </div>
        <div>
            <strong>Borrower Address</strong>
            <span><?= esc($request['borrower_address']) ?: '-' ?></span>
        </div>
    </div>

    <!-- Request Items -->
    <div class="request-items" aria-live="polite">
        <h4>Requested Items</h4>
        <?php if (count($requestItems) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Quantity</th>
                        <th>Condition</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($requestItems as $item): ?>
                        <tr>
                            <td><?= esc($item['equipment_name']) ?></td>
                            <td><?= esc($item['quantity']) ?></td>
                            <td><?= esc($item['unit_condition']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#657085; font-size:14px;">No items requested.</p>
        <?php endif; ?>
    </div>

    <!-- Remarks -->
    <div class="remarks-section">
        <div class="label">Remarks:</div>
        <p><?= esc($request['remarks']) ?: '-' ?></p>
    </div>

    <!-- Declined Notes (if exists) -->
    <?php if (!empty($request['notes_declined'])): ?>
        <div class="declined-notes" aria-live="polite">
            <div class="label">Notes Declined:</div>
            <p><?= esc($request['notes_declined']) ?></p>
        </div>
    <?php endif; ?>

    <!-- Action Buttons (only for Pending) -->
    <?php if ($request['status'] === 'Pending'): ?>
        <div class="modal-actions">
            <form action="request_action.php" method="post" style="margin: 0;">
                <input type="hidden" name="id" value="<?= esc($request['id']) ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn-action btn-approve">Approve</button>
            </form>

            <!-- Note: openNotesModal() is defined in parent page -->
            <button type="button" class="decline-btn" onclick="openNotesModal(<?= esc($request['id']) ?>)">Not Available</button>
        </div>
    <?php else: ?>
        <div class="modal-actions">
            <span class="status-badge" style="background: <?= $request['status']=='Approved' ? '#1b8b1b' : '#d32f2f' ?>; padding: 8px 16px; font-size: 14px; color: #fff;">
                Status: <?= esc($request['status']) ?>
            </span>
        </div>
    <?php endif; ?>

</div>
