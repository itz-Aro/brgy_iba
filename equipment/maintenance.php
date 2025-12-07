<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';

// Database configuration
$host = 'localhost';
$db_name = 'barangay_inventory';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM maintenance_logs WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "Record deleted successfully!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch all records
$stmt = $conn->prepare("SELECT * FROM maintenance_logs ORDER BY performed_at DESC");
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
:root{
  --blue:#0d47a1;
  --light-gray:#efefef;
  --card-shadow:0 6px 14px rgba(15,23,42,0.12);
  --radius:14px;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: var(--light-gray);
}

.content-wrap{
  margin-left:250px;
  padding:22px;
  max-width:1500px;
  margin-top:0;
}

.top-header{
  margin-bottom:20px;
  background:var(--blue);
  color:white;
  border-radius:12px;
  padding:28px 32px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  box-shadow:0 6px 14px rgba(0,0,0,0.12);
}

.top-header .title{
  font-size:36px;
  font-weight:800;
  letter-spacing:-0.5px;
}

.admin-area{
  display:flex;
  align-items:center;
  gap:18px;
}

.greeting{
  font-size:18px;
  opacity:0.95;
}

.avatar{
  width:56px;
  height:56px;
  border-radius:50%;
  background:white;
  color:var(--blue);
  display:flex;
  justify-content:center;
  align-items:center;
  font-weight:700;
  font-size:18px;
  border:4px solid #cfe1ff;
  box-shadow:0 2px 6px rgba(0,0,0,0.15);
}

#successMsg {
  background:#c8e6c9;
  color:#2e7d32;
  padding:12px;
  border-radius: var(--radius);
  margin-bottom:15px;
  font-weight:600;
  transition: opacity 0.5s ease;
}

table {
  width:100%;
  border-collapse:collapse;
  margin-top:18px;
  background:white;
  box-shadow:var(--card-shadow);
  border-radius:var(--radius);
  overflow:hidden;
}

table th, table td {
  padding:12px 16px;
  text-align:left;
  border-bottom:1px solid #f1f3f6;
}

table th {
  background-color:#f5f7fb;
  font-weight:700;
  color:var(--blue);
}

table td a {
  background: var(--blue);
  color:white;
  padding:6px 12px;
  border-radius:10px;
  text-decoration:none;
  font-weight:600;
  transition:0.2s;
}

table td a:hover {
  opacity:0.9;
  transform: scale(1.05);
}

.no-records {
  text-align:center;
  padding:12px;
  color:#666;
}
</style>

<main class="content-wrap">
    <div class="top-header">
        <div class="title">Maintenance Logs</div>
        <div class="admin-area">
            <div class="greeting">Hello, <?= htmlspecialchars($_SESSION['user']['name'] ?? 'User') ?>!</div>
            <div class="avatar"><?= strtoupper(substr($_SESSION['user']['name'] ?? 'U',0,1)) ?></div>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div id="successMsg"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <h3>All Records</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Equipment ID</th>
                <th>Action</th>
                <th>Remarks</th>
                <th>Performed By</th>
                <th>Performed At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($records) > 0): ?>
                <?php foreach($records as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['equipment_id']) ?></td>
                        <td><?= htmlspecialchars($row['action']) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td><?= htmlspecialchars($row['performed_by']) ?></td>
                        <td><?= htmlspecialchars($row['performed_at']) ?></td>
                        <td>
                            <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this record?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="no-records">No records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>

<script>
setTimeout(() => {
    const msg = document.getElementById("successMsg");
    if (msg) {
        msg.style.opacity = "0";
        setTimeout(() => msg.remove(), 500);
    }
}, 2000);
</script>
