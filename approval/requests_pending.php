<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

if (!isset($_SESSION['user'])) header("Location: ../login.php");

$db = new Database();
$conn = $db->getConnection();

// Fetch pending requests
$stmt = $conn->prepare("SELECT r.*, u.fullname AS creator_name FROM requests r JOIN users u ON r.created_by = u.id WHERE r.status = 'Pending' ORDER BY r.created_at DESC");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

body {
  background: var(--bg);
}

.content-wrap {
  margin-left: 250px;
  padding: 26px;
  max-width: 1500px;
}

/* Header */
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

/* Stats row */
.stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}

/* Request Card */
.stat-card {
  flex: 1 1 300px;
  background: white;
  border-radius: var(--radius);
  padding: 18px;
  box-shadow: var(--card-shadow);
  transition: transform 0.2s, box-shadow 0.2s;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 24px rgba(0,0,0,0.18);
  cursor: pointer;
}

.stat-card .card-header {
  display: flex;
  gap: 14px;
  align-items: center;
}

.stat-card .avatar-photo {
  flex-shrink: 0;
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
  color: var(--blue);
}

.stat-card h4 {
  margin: 0 0 4px 0;
  font-size: 18px;
  font-weight: 700;
  color: #073a6a;
}

.stat-card .details {
  font-size: 13px;
  color: #657085;
  margin-bottom: 6px;
}

.stat-card .status-badge {
  padding: 5px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  color: #f9a825;
  background: #fff8e1;
  border: 1px solid #f9a825;
  display: inline-block;
}

/* View Details Button */
.view-btn {
  margin-top: 14px;
  background: var(--accent);
  color: white;
  border: none;
  padding: 8px 14px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 14px;
  text-align: center;
  cursor: pointer;
  transition: 0.2s;
  width: fit-content;
}

.view-btn:hover {
  transform: translateY(-2px);
  opacity: 0.9;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<main class="content-wrap">

  <div class="top-header">
    <div class="title">Pending Requests</div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <div class="stats-row">
    <?php if($requests): ?>
      <?php foreach($requests as $req): ?>
      <div class="stat-card">
        <div class="card-header">
          <?php if($req['borrower_id_photo']): ?>
            <img class="avatar-photo" src="../uploads/<?= htmlspecialchars($req['borrower_id_photo']) ?>" alt="ID Photo">
          <?php else: ?>
            <div class="avatar-photo">N/A</div>
          <?php endif; ?>
          <div>
            <h4><?= htmlspecialchars($req['borrower_name']) ?></h4>
            <div class="details">Request #: <?= htmlspecialchars($req['request_no']) ?></div>
            <div class="details">Date Needed: <?= htmlspecialchars($req['date_needed']) ?></div>
            <div class="details">Expected Return: <?= htmlspecialchars($req['expected_return_date']) ?></div>
            <div class="status-badge">Pending</div>
          </div>
        </div>
        <a href="request_details.php?id=<?= $req['id'] ?>">
          <button class="view-btn">See Details ▸</button>
        </a>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="color:#657085; font-weight:600;">No pending requests.</div>
    <?php endif; ?>
  </div>

</main>
