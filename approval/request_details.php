<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

if (!isset($_GET['id'])) die("Request ID missing.");

$db = new Database();
$conn = $db->getConnection();
$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT r.*, u.fullname AS creator_name FROM requests r JOIN users u ON r.created_by = u.id WHERE r.id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request) die("Request not found.");

// ============================
// Fetch Request Items
// ============================
$stmtItems = $conn->prepare("
    SELECT ri.*, e.name AS equipment_name
    FROM request_items ri
    JOIN equipment e ON ri.equipment_id = e.id
    WHERE ri.request_id = ?
");
$stmtItems->execute([$request['id']]);
$requestItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);
?>

<link rel="stylesheet" href="/public/css/dashboard.css">

<style>
:root {
  --blue: #0d47a1;
  --accent: #1e73ff;
  --bg: #f3f6fc;
  --card-shadow: 0 6px 18px rgba(0,0,0,0.12);
  --radius: 14px;
}

.content-wrap {
  margin-left: 250px;
  padding: 26px;
  max-width: 1500px;
  background: var(--bg);
}

.top-header {
  margin-bottom: 20px;
  background: var(--blue);
  color: white;
  border-radius: var(--radius);
  padding: 28px 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: var(--card-shadow);
  background: linear-gradient(135deg, #0d47a1, #1565c0);
}

.top-header .title {
  font-size: 36px;
  font-weight: 800;
}

.admin-area {
  display: flex;
  align-items: center;
  gap: 20px;
}

.avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: white;
  color: var(--blue);
  display: flex;
  justify-content: center;
  align-items: center;
  font-weight: 700;
  font-size: 18px;
  border: 3px solid #bbd6ff;
  box-shadow: 0 3px 8px rgba(0,0,0,0.18);
}

.request-card {
  background: white;
  border-radius: var(--radius);
  padding: 28px;
  box-shadow: var(--card-shadow);
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 700px;
}

.request-card .card-header {
  display: flex;
  gap: 18px;
  align-items: center;
}

.request-card .avatar-photo {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #cfe1ff;
  background: #cfe1ff;
  display: flex;
  justify-content: center;
  align-items: center;
  font-weight: 700;
  color: var(--blue);
}

.request-card h3 {
  margin: 0;
  font-weight: 800;
  font-size: 22px;
  color: #073a6a;
}

.request-card .details {
  font-size: 14px;
  color: #657085;
}

.request-card .grid-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  font-size: 14px;
  color: #657085;
}

.request-card p {
  margin: 4px 0 12px 0;
  color: #657085;
}

.status-badge {
  padding: 6px 14px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 13px;
  display: inline-block;
  color: white;
}

.btn-action {
  padding: 10px 18px;
  font-weight: 600;
  border-radius: 10px;
  border: none;
  color: white;
  cursor: pointer;
  transition: 0.2s;
}

.btn-action:hover {
  transform: translateY(-2px);
  opacity: 0.9;
}

.btn-approve {
  background-color: #1b8b1b;
}

.decline-btn {
  background-color: #d32f2f;
  padding: 10px 18px;
  font-weight: 600;
  border-radius: 10px;
  border: none;
  color: white;
  cursor: pointer;
  transition: 0.2s;
}

.back-link {
  margin-top: 22px;
  display: inline-block;
  font-weight: 600;
  color: var(--blue);
  text-decoration: none;
  transition: 0.2s;
}

.back-link:hover {
  text-decoration: underline;
}

/* =======================
   REQUEST ITEMS STYLE
======================= */
.request-items {
  margin-top: 10px;
  background: #f9fbff;
  padding: 18px;
  border-radius: var(--radius);
  box-shadow: var(--card-shadow);
}

.request-items h4 {
  margin: 0 0 12px 0;
  color: #073a6a;
  font-weight: 700;
  font-size: 18px;
}

.request-items table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 6px;
}

.request-items th {
  background: var(--blue);
  color: white;
  padding: 10px;
  font-size: 13px;
  font-weight: 700;
  text-align: left;
  border-radius: 6px;
}

.request-items td {
  padding: 10px;
  font-size: 14px;
  color: #444;
  background: white;
  border-bottom: 1px solid #e5e9f2;
}

.request-items tr:last-child td {
  border-bottom: none;
}

/* =======================
   MODAL STYLE
======================= */
.modal-overlay {
  display: none;
  position: fixed;
  top:0;
  left:0;
  width:100%;
  height:100%;
  background: rgba(0,0,0,0.55);
  z-index: 9999;
  align-items: center;
  justify-content: center;
}

.modal-box {
  background: white;
  width: 420px;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.20);
  animation: popIn 0.25s ease-out;
}

@keyframes popIn {
  from { transform: scale(0.85); opacity:0; }
  to { transform: scale(1); opacity:1; }
}

.modal-box h3 {
  margin-top: 0;
  color: #073a6a;
  font-size: 20px;
  font-weight: 700;
  text-align: center;
}

.modal-box textarea {
  width: 100%;
  height: 100px;
  padding: 12px;
  border:1px solid #ccc;
  border-radius: 8px;
  font-size: 14px;
  resize: none;
}

.modal-actions {
  margin-top: 15px;
  display:flex;
  justify-content:flex-end;
  gap:10px;
}

.cancel-btn {
  padding: 10px 14px;
  background:#657085;
  color:white;
  border:none;
  border-radius:6px;
  cursor:pointer;
}

.submit-btn {
  padding: 10px 14px;
  background:#d32f2f;
  color:white;
  border:none;
  border-radius:6px;
  cursor:pointer;
}
</style>

<main class="content-wrap">

  <div class="top-header">
    <div class="title">Request Details</div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <div class="request-card">
    <div class="card-header">
      <?php if($request['borrower_id_photo']): ?>
        <img class="avatar-photo" src="../uploads/<?= htmlspecialchars($request['borrower_id_photo']) ?>" alt="ID Photo">
      <?php else: ?>
        <div class="avatar-photo">N/A</div>
      <?php endif; ?>

      <div>
        <h3><?= htmlspecialchars($request['borrower_name']) ?></h3>
        <div class="details">Request #: <?= htmlspecialchars($request['request_no']) ?></div>
        <div class="details">Created By: <?= htmlspecialchars($request['creator_name']) ?></div>
      </div>
    </div>

    <div class="grid-info">
      <div>Date Needed: <?= htmlspecialchars($request['date_needed']) ?></div>
      <div>Expected Return: <?= htmlspecialchars($request['expected_return_date']) ?></div>
      <div>Borrower Contact: <?= htmlspecialchars($request['borrower_contact']) ?></div>
      <div>Borrower Address: <?= htmlspecialchars($request['borrower_address']) ?></div>
    </div>

    <!-- Request Items -->
    <div class="request-items">
      <h4>Requested Items</h4>
      <?php if(count($requestItems)>0): ?>
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
                <td><?= htmlspecialchars($item['equipment_name']) ?></td>
                <td><?= htmlspecialchars($item['quantity']) ?></td>
                <td><?= htmlspecialchars($item['unit_condition']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:#657085; font-size:14px;">No items requested.</p>
      <?php endif; ?>
    </div>

    <div style="font-size:14px; color:#073a6a; font-weight:600;">Remarks:</div>
    <p><?= htmlspecialchars($request['remarks']) ?: '-' ?></p>

    <?php if(!empty($request['notes_declined'])): ?>
      <div style="font-size:14px; color:#d32f2f; font-weight:600; margin-top:8px;">Notes Declined:</div>
      <p><?= htmlspecialchars($request['notes_declined']) ?></p>
    <?php endif; ?>


    <?php if($request['status'] === 'Pending'): ?>
    <div style="display:flex; gap:12px;">
      <form action="request_action.php" method="post">
        <input type="hidden" name="id" value="<?= $request['id'] ?>">
        <input type="hidden" name="action" value="approve">
        <button type="submit" class="btn-action btn-approve">Approve</button>
      </form>

      <!-- Open modal button -->
      <button type="button" class="decline-btn" onclick="openNotesModal()">Not Available</button>
    </div>
    <?php else: ?>
      <div>
        Status: <span class="status-badge" style="background: <?= $request['status']=='Approved'?'#1b8b1b':'#d32f2f' ?>;">
          <?= $request['status'] ?>
        </span>
      </div>
    <?php endif; ?>

  </div>

  <a href="requests_pending.php" class="back-link">◀ Back to Pending Requests</a>

</main>

<!-- =====================
     DECLINE NOTES MODAL
===================== -->
<div id="notesModal" class="modal-overlay">
  <div class="modal-box">
    <h3>Reason Not Available</h3>
    <form method="POST" action="request_action.php">
      <input type="hidden" name="id" value="<?= $request['id'] ?>">
      <input type="hidden" name="action" value="decline">
      <textarea name="notes" placeholder="Type reason here..." required></textarea>
      <div class="modal-actions">
        <button type="button" class="cancel-btn" onclick="closeNotesModal()">Cancel</button>
        <button type="submit" class="submit-btn">Submit</button>
      </div>
    </form>
  </div>
</div>

<script>
function openNotesModal() {
    document.getElementById('notesModal').style.display = 'flex';
}
function closeNotesModal() {
    document.getElementById('notesModal').style.display = 'none';
}
</script>
