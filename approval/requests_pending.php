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
        <div class="stat-left">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-value"><?= count($pending) ?></div>
        </div>
        <div class="stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
    </div>

    <div class="stat-card approved">
        <div class="stat-left">
            <div class="stat-label">Approved Requests</div>
            <div class="stat-value"><?= count($approved) ?></div>
        </div>
        <div class="stat-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>

    <div class="stat-card declined">
        <div class="stat-left">
            <div class="stat-label">Declined Requests</div>
            <div class="stat-value"><?= count($declined) ?></div>
        </div>
        <div class="stat-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
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

<!-- Decline Notes Modal -->
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

<!-- EmailJS Library -->
<script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>

<!-- Notification Styles -->
<style>
  .notification {
    position: fixed;
    top: 20px;
    right: 20px;
    max-width: 400px;
    padding: 16px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    font-family: 'Poppins', Arial, sans-serif;
    animation: slideIn 0.3s ease-out;
    font-size: 14px;
  }

  @keyframes slideIn {
    from {
      transform: translateX(400px);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }

  .notification-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
  }

  .notification-close {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    font-size: 18px;
    padding: 0;
    opacity: 0.7;
    transition: opacity 0.2s;
    flex-shrink: 0;
    margin-top: -2px;
  }

  .notification-close:hover {
    opacity: 1;
  }

  .notification-success {
    background: #e8f5e9;
    color: #1b5e20;
    border-left: 4px solid #4caf50;
  }

  .notification-error {
    background: #ffebee;
    color: #b71c1c;
    border-left: 4px solid #f44336;
  }

  .notification-warning {
    background: #fff8e1;
    color: #e65100;
    border-left: 4px solid #ffc107;
  }

  .notification-info {
    background: #e3f2fd;
    color: #0d47a1;
    border-left: 4px solid #2196f3;
  }
</style>

<script>
  // Initialize EmailJS
  (function(){ 
    emailjs.init("6PxG_ry1LNFpHMxWR"); 
  })();

  // Email sending functions
  function sendApprovalEmail(data) {
    // Format approved items as plain text with line breaks
    const approvedText = data.approved_items === 'None' 
      ? 'None' 
      : data.approved_items.split(', ').map(item => `• ${item}`).join('\n');
    
    // Format unavailable items as plain text with line breaks
    const unavailableText = data.unavailable_items === 'None' 
      ? 'None' 
      : data.unavailable_items.split(', ').map(item => `• ${item}`).join('\n');

    return emailjs.send(
      "service_rb1euzf",
      "template_approval02",
      {
        borrower_name: data.borrower_name,
        request_no: data.request_no,
        approved_items: approvedText,
        unavailable_items: unavailableText,
        expected_return: data.expected_return,
        to_email: data.email
      }
    );
  }

  function sendDeclineEmail(data) {
    return emailjs.send(
      "service_rb1euzf",
      "template_declined01",
      {
        borrower_name: data.borrower_name,
        request_no: data.request_no,
        decline_reason: data.reason,
        to_email: data.email
      }
    );
  }

  // Send email on page load if payload exists
  (async function(){
    <?php if (!empty($_SESSION['email_payload'])): ?>
      const payload = <?= json_encode($_SESSION['email_payload']) ?>;
      
      // Check if email exists
      if (!payload.has_email || !payload.email || payload.email.trim() === '') {
        showNotification('⚠️ Warning: No valid email address found for ' + payload.borrower_name + '. Request processed but email not sent.', 'warning');
        console.warn('No email address provided for borrower: ' + payload.borrower_name);
        return;
      }
      
      try {
        let result;
        
        if (payload.type === 'decline') {
          result = await sendDeclineEmail({
            borrower_name: payload.borrower_name,
            request_no: payload.request_no,
            reason: payload.reason,
            email: payload.email
          });
        } else if (payload.type === 'approve') {
          result = await sendApprovalEmail({
            borrower_name: payload.borrower_name,
            request_no: payload.request_no,
            approved_items: payload.approved_items,
            unavailable_items: payload.unavailable_items,
            expected_return: payload.expected_return,
            email: payload.email
          });
        }
        
        console.log('✅ Email sent successfully:', result);
        showNotification('✅ Email notification sent successfully to ' + payload.email, 'success');
        
      } catch (err) {
        console.error('❌ Email sending failed:', err);
        showNotification('❌ Email notification failed: ' + err.message + '. But request was processed.', 'error');
      }
    <?php endif; ?>
  })();

  // Notification function
  function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'notification notification-' + type;
    notification.innerHTML = `
      <div class="notification-content">
        <span>${message}</span>
        <button class="notification-close" onclick="this.parentElement.parentElement.remove()">✕</button>
      </div>
    `;

    // Add to page
    document.body.insertBefore(notification, document.body.firstChild);

    // Auto remove after 5 seconds
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove();
      }
    }, 5000);
  }

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
  function openDetailsModal(id) {
    document.getElementById('detailsModal').style.display = 'flex';
    fetch('get_request_details.php?id=' + id)
      .then(res => res.text())
      .then(html => { document.getElementById('detailsContent').innerHTML = html; });
  }

  function closeDetailsModal() { 
    document.getElementById('detailsModal').style.display = 'none'; 
  }

  function openNotesModal(requestId) {
    document.getElementById('declineRequestId').value = requestId;
    document.getElementById('notesModal').style.display = 'flex';
    document.getElementById('notesTextarea').focus();
  }

  function closeNotesModal() {
    document.getElementById('notesModal').style.display = 'none';
  }

  // Global function for approve button
  function submitApproval() {
    const checkedBoxes = Array.from(document.querySelectorAll('.item-checkbox:checked'))
        .map(cb => cb.value);

    // Debug: log what's being sent
    console.log('Checked checkboxes:', checkedBoxes);
    console.log('JSON String to send:', JSON.stringify(checkedBoxes));

    // Set the hidden input value
    const hiddenInput = document.getElementById('unavailableItemsInput');
    if (!hiddenInput) {
      console.error('Hidden input not found!');
      return;
    }
    
    hiddenInput.value = JSON.stringify(checkedBoxes);
    
    // Verify it was set
    console.log('Hidden input value:', hiddenInput.value);

    // Submit the form
    const form = document.getElementById('approveForm');
    if (!form) {
      console.error('Approve form not found!');
      return;
    }
    form.submit();
  }
</script>

<?php
unset($_SESSION['email_payload']);
?>