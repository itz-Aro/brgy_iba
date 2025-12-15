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

<link rel="stylesheet" href="/brgy_iba/css/request_pending.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<!-- <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"> -->


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
  </a>
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
