<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

if (!isset($_SESSION['user'])) header("Location: ../login.php");

$db = new Database();
$conn = $db->getConnection();

// Fetch requests by status
$stmt = $conn->prepare("SELECT r.*, u.fullname AS creator_name 
                        FROM requests r 
                        JOIN users u ON r.created_by = u.id 
                        ORDER BY r.created_at DESC");
$stmt->execute();
$allRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorize requests
$pending = array_filter($allRequests, fn($r) => $r['status'] === 'Pending');
$approved = array_filter($allRequests, fn($r) => $r['status'] === 'Approved');
$declined = array_filter($allRequests, fn($r) => $r['status'] === 'Declined');

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);
?>

<link rel="stylesheet" href="/public/css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ======================
   Copy style from admin_returns.php
====================== */
* { box-sizing: border-box; }
body { font-family: 'Poppins', sans-serif; background:#f1f5f9; margin:0; }

.content-wrap { 
    margin-left: 250px; 
    padding: 32px; 
    max-width: 1600px;
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.top-header { 
    margin-bottom: 32px; 
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white; 
    border-radius: 20px; 
    padding: 36px 40px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    box-shadow: 0 10px 40px rgba(99, 102, 241, 0.3);
    position: relative;
    overflow: hidden;
}

.top-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.top-header .title { font-size: 2rem; font-weight: 800; position: relative; z-index: 1; display:flex; gap:10px; }
.top-header .title i { font-size:2.2rem; opacity:0.9; }
.admin-area { display:flex; align-items:center; gap:20px; position:relative; z-index:1; }
.greeting { font-size:1rem; font-weight:600; opacity:0.95; }
.avatar { width:60px; height:60px; border-radius:50%; background:white; color:#6366f1; display:flex; justify-content:center; align-items:center; font-weight:800; font-size:1.25rem; border:4px solid rgba(255,255,255,0.3); box-shadow:0 4px 12px rgba(0,0,0,0.15); }

.stats-container { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:24px; margin-bottom:32px; }
.stat-card { background:white; border-radius:16px; padding:28px; box-shadow:0 4px 12px rgba(0,0,0,0.08); border:2px solid #f1f5f9; transition: all 0.3s; }
.stat-card:hover { transform:translateY(-4px); box-shadow:0 12px 28px rgba(0,0,0,0.12); border-color:#6366f1; }
.stat-card .stat-icon { width:56px; height:56px; border-radius:14px; display:flex; justify-content:center; align-items:center; font-size:1.5rem; margin-bottom:16px; }
.stat-card.pending .stat-icon { background:linear-gradient(135deg,#fef3c7,#fde68a); color:#d97706; }
.stat-card.approved .stat-icon { background:linear-gradient(135deg,#d1fae5,#a7f3d0); color:#059669; }
.stat-card.declined .stat-icon { background:linear-gradient(135deg,#fee2e2,#fecaca); color:#dc2626; }
.stat-card .stat-value { font-size:2rem; font-weight:800; color:#1e293b; margin-bottom:4px; }
.stat-card .stat-label { font-size:0.875rem; color:#64748b; font-weight:600; }

.tabs { 
    display: flex; 
    gap: 12px; 
    margin-bottom: 24px;
    background: white;
    padding: 8px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.tab-btn { 
    padding: 14px 28px; 
    border: none; 
    cursor: pointer; 
    font-weight: 700; 
    border-radius: 12px; 
    background: transparent;
    color: #64748b;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tab-btn:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.tab-btn.active { 
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.tab-content { 
    display: none;
    animation: slideIn 0.4s ease-in-out;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateX(-10px); }
    to { opacity: 1; transform: translateX(0); }
}

.tab-content.active { 
    display: block; 
}
.table-card { background:white; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.08); overflow:hidden; border:2px solid #f1f5f9; margin-bottom:24px; }
.table-card table { width:100%; border-collapse:collapse; }
.table-card th { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white; padding:18px 20px; text-align:left; font-weight:700; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.5px; }
.table-card td { padding:18px 20px; border-bottom:1px solid #f1f5f9; vertical-align:middle; font-size:0.9rem; color:#334155; }
.table-card tbody tr:hover { background:#f8fafc; }

.badge-pending { background: linear-gradient(135deg,#fef3c7,#fde68a); color:#d97706; padding:6px 14px; border-radius:8px; font-weight:700; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; }
.badge-approved { background: linear-gradient(135deg,#d1fae5,#a7f3d0); color:#059669; padding:6px 14px; border-radius:8px; font-weight:700; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; }
.badge-declined { background: linear-gradient(135deg,#fee2e2,#fecaca); color:#dc2626; padding:6px 14px; border-radius:8px; font-weight:700; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px; }

/* Modal reuse from admin_returns.php */
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); justify-content:center; align-items:center; z-index:9999; }
.modal-details-box { background:white; width:100%; max-width:800px; padding:0; border-radius:14px; box-shadow:0 5px 30px rgba(0,0,0,0.3); animation: popIn 0.25s ease-out; margin:auto; max-height:90vh; overflow-y:auto; }
@keyframes popIn { from { transform: scale(0.85); opacity:0; } to { transform: scale(1); opacity:1; } }
.modal-header { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white; padding:24px 28px; border-radius:14px 14px 0 0; display:flex; justify-content:space-between; align-items:center; }
.modal-header h2 { margin:0; font-size:24px; font-weight:800; }
.close-modal { background:none; border:none; color:white; font-size:28px; cursor:pointer; line-height:1; padding:0; width:32px; height:32px; display:flex; justify-content:center; align-items:center; border-radius:50%; transition:0.2s; }
.close-modal:hover { background: rgba(255,255,255,0.2); }
.modal-content { padding:28px; }
/* ✅ Decline Notes Modal */
.notes-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
}

.notes-modal-box {
  background: #fff;
  padding: 22px;
  border-radius: 14px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 12px 30px rgba(0,0,0,0.3);
}

.notes-modal-box h3 {
  margin-bottom: 10px;
  font-size: 18px;
  color: #073a6a;
  font-weight: 700;
}

.notes-modal-box textarea {
  width: 100%;
  height: 120px;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #ccc;
  font-size: 14px;
  resize: none;
}

.notes-modal-actions {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.cancel-btn {
  background: #9e9e9e;
  color: #fff;
  padding: 8px 16px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
}

.submit-btn {
  background: #d32f2f;
  color: #fff;
  padding: 8px 16px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
}


</style>

<main class="content-wrap">

  <div class="top-header">
    <div class="title"><i class="fa-solid fa-file-lines"></i> Requests Management</div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <!-- Statistics -->
  <div class="stats-container">
    <div class="stat-card pending">
      <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
      <div class="stat-value"><?= count($pending) ?></div>
      <div class="stat-label">Pending Requests</div>
    </div>
    <div class="stat-card approved">
      <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div class="stat-value"><?= count($approved) ?></div>
      <div class="stat-label">Approved Requests</div>
    </div>
    <div class="stat-card declined">
      <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div class="stat-value"><?= count($declined) ?></div>
      <div class="stat-label">Declined Requests</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn active" data-tab="pending"><i class="fa-solid fa-clock"></i> Pending</button>
    <button class="tab-btn" data-tab="approved"><i class="fa-solid fa-circle-check"></i> Approved</button>
    <button class="tab-btn" data-tab="declined"><i class="fa-solid fa-triangle-exclamation"></i> Declined</button>
  </div>

  <!-- Table: Pending -->
  <div class="tab-content active" id="pending">
    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>Request #</th>
            <th>Borrower</th>
            <th>Date Needed</th>
            <th>Expected Return</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if($pending): ?>
            <?php foreach($pending as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['request_no']) ?></td>
                <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                <td><?= htmlspecialchars($r['date_needed']) ?></td>
                <td><?= htmlspecialchars($r['expected_return_date']) ?></td>
                <td><span class="badge-pending"><?= htmlspecialchars($r['status']) ?></span></td>
                <td><button class="confirm-btn" onclick="openDetailsModal(<?= $r['id'] ?>)">View</button></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center; color:#64748b;">No Pending Requests</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Table: Approved -->
  <div class="tab-content" id="approved">
    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>Request #</th>
            <th>Borrower</th>
            <th>Date Needed</th>
            <th>Expected Return</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if($approved): ?>
            <?php foreach($approved as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['request_no']) ?></td>
                <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                <td><?= htmlspecialchars($r['date_needed']) ?></td>
                <td><?= htmlspecialchars($r['expected_return_date']) ?></td>
                <td><span class="badge-approved"><?= htmlspecialchars($r['status']) ?></span></td>
                <td><button class="confirm-btn" onclick="openDetailsModal(<?= $r['id'] ?>)">View</button></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center; color:#64748b;">No Approved Requests</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Table: Declined -->
  <div class="tab-content" id="declined">
    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>Request #</th>
            <th>Borrower</th>
            <th>Date Needed</th>
            <th>Expected Return</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if($declined): ?>
            <?php foreach($declined as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['request_no']) ?></td>
                <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                <td><?= htmlspecialchars($r['date_needed']) ?></td>
                <td><?= htmlspecialchars($r['expected_return_date']) ?></td>
                <td><span class="badge-declined"><?= htmlspecialchars($r['status']) ?></span></td>
                <td><button class="confirm-btn" onclick="openDetailsModal(<?= $r['id'] ?>)">View</button></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center; color:#64748b;">No Declined Requests</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<!-- Details Modal -->
<div id="detailsModal" class="modal-overlay">
  <div class="modal-details-box">
    <div class="modal-header">
      <h2>Request Details</h2>
      <button class="close-modal" onclick="closeDetailsModal()">&times;</button>
    </div>
    <div class="modal-content" id="detailsContent">
      <p style="text-align:center; color:#64748b;">Loading...</p>
    </div>
  </div>
</div>
<!-- ✅ DECLINE NOTES MODAL -->
<div id="notesModal" class="notes-modal-overlay" style="display:none;">
  <div class="notes-modal-box">

    <h3>Reason — Not Available</h3>

    <form action="request_action.php" method="POST">
      <input type="hidden" name="id" id="declineRequestId">
      <input type="hidden" name="action" value="decline">

      <textarea 
        name="notes" 
        id="notesTextarea"
        required 
        placeholder="Enter reason why the request is not available..."
      ></textarea>

      <div class="notes-modal-actions">
        <button type="button" class="cancel-btn" onclick="closeNotesModal()">Cancel</button>
        <button type="submit" class="submit-btn">Submit</button>
      </div>
    </form>

  </div>
</div>
<script>
function openNotesModal(requestId) {
  document.getElementById('declineRequestId').value = requestId;
  document.getElementById('notesModal').style.display = 'flex';
  document.getElementById('notesTextarea').focus();
}

function closeNotesModal() {
  document.getElementById('notesModal').style.display = 'none';
}
</script>

<script>

// Tab functionality
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

// Modal functions
function openDetailsModal(id){
    document.getElementById('detailsModal').style.display = 'flex';
    fetch('get_request_details.php?id=' + id)
        .then(res => res.text())
        .then(html => { document.getElementById('detailsContent').innerHTML = html; });
}
function closeDetailsModal(){ document.getElementById('detailsModal').style.display='none'; }
</script>
