<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

if (!isset($_SESSION['user'])) header("Location: ../login.php");

$db = new Database();
$conn = $db->getConnection();

// Determine status filter
$status = $_GET['status'] ?? 'Pending'; // default to Pending

$stmt = $conn->prepare("SELECT r.*, u.fullname AS creator_name 
                        FROM requests r 
                        JOIN users u ON r.created_by = u.id 
                        WHERE r.status = :status 
                        ORDER BY r.created_at DESC");
$stmt->bindParam(':status', $status);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);
?>

<link rel="stylesheet" href="/public/css/dashboard.css">
<style>
.content-wrap {
  margin-left: 250px;
  padding: 26px;
  max-width: 1500px;
  background: #f3f6fc;
}

.top-header {
  margin-bottom: 20px;
  background: #0d47a1;
  color: white;
  border-radius: 14px;
  padding: 28px 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 6px 18px rgba(0,0,0,0.12);
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
  color: #0d47a1;
  display: flex;
  justify-content: center;
  align-items: center;
  font-weight: 700;
  font-size: 18px;
  border: 3px solid #bbd6ff;
  box-shadow: 0 3px 8px rgba(0,0,0,0.18);
}

/* Filter buttons */
.filter-buttons {
  margin: 20px 0;
  display: flex;
  gap: 12px;
}

.filter-buttons a {
  padding: 10px 18px;
  border-radius: 10px;
  font-weight: 600;
  color: white;
  text-decoration: none;
  transition: 0.2s;
}

.filter-buttons a.active {
  background: #1e73ff;
}

.filter-buttons a.pending { background: #f9a825; }
.filter-buttons a.approved { background: #1b8b1b; }
.filter-buttons a.not-available { background: #d32f2f; }

.filter-buttons a:hover {
  opacity: 0.9;
  transform: translateY(-2px);
}

/* Request card */
.request-card {
  background: white;
  border-radius: 14px;
  padding: 18px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.12);
  display: flex;
  gap: 14px;
  align-items: center;
  margin-bottom: 16px;
  transition: transform 0.2s, box-shadow 0.2s;
}

.request-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 24px rgba(0,0,0,0.15);
}

.request-card .avatar-photo {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #cfe1ff;
  background: #cfe1ff;
  display: flex;
  justify-content: center;
  align-items: center;
  font-weight: 700;
  color: #0d47a1;
}

.request-card .info {
  flex: 1;
}

.request-card .info h4 {
  margin: 0 0 4px 0;
  font-size: 18px;
  font-weight: 700;
  color: #073a6a;
}

.request-card .info .details {
  font-size: 13px;
  color: #657085;
}

.status-badge {
  padding: 5px 12px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 12px;
  display: inline-block;
  color: white;
}

.status-pending { background: #f9a825; color: #000; }
.status-approved { background: #1b8b1b; }
.status-not-available { background: #d32f2f; }

.view-btn {
  background: #1e73ff;
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.2s;
}

.view-btn:hover {
  transform: translateY(-2px);
  opacity: 0.9;
}
</style>

<main class="content-wrap">

  <div class="top-header">
    <div class="title">Requests</div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <!-- Filter Buttons -->
  <div class="filter-buttons">
    <a href="?status=Pending" class="pending <?= $status=='Pending'?'active':'' ?>">Pending</a>
    <a href="?status=Approved" class="approved <?= $status=='Approved'?'active':'' ?>">Approved</a>
    <a href="?status=Not Available" class="not-available <?= $status=='Not Available'?'active':'' ?>">Not Available</a>
  </div>

  <!-- Request Cards -->
  <?php if($requests): ?>
    <?php foreach($requests as $req): ?>
    <div class="request-card">
      <?php if($req['borrower_id_photo']): ?>
        <img class="avatar-photo" src="../uploads/<?= htmlspecialchars($req['borrower_id_photo']) ?>" alt="ID Photo">
      <?php else: ?>
        <div class="avatar-photo">N/A</div>
      <?php endif; ?>
      <div class="info">
        <h4><?= htmlspecialchars($req['borrower_name']) ?></h4>
        <div class="details">Request #: <?= htmlspecialchars($req['request_no']) ?></div>
        <div class="details">Date Needed: <?= htmlspecialchars($req['date_needed']) ?></div>
        <div class="details">Expected Return: <?= htmlspecialchars($req['expected_return_date']) ?></div>
        <span class="status-badge <?= strtolower(str_replace(' ', '-', $req['status'])) ?>">
          <?= htmlspecialchars($req['status']) ?>
        </span>
      </div>
      <a href="request_details.php?id=<?= $req['id'] ?>">
        <button class="view-btn">See Details ▸</button>
      </a>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="color:#657085; font-weight:600;">No requests for this status.</div>
  <?php endif; ?>

</main>
