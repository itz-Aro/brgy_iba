<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

// DB connection
$db = new Database();
$conn = $db->getConnection();

// Fetch equipment
$stmt = $conn->prepare("SELECT * FROM equipment ORDER BY name ASC");
$stmt->execute();
$equipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Current user role
$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);
?>

<link rel="stylesheet" href="/public/css/dashboard.css">

<style>
:root{
  --blue:#0d47a1;
  --light-gray:#efefef;
  --card-shadow:0 6px 14px rgba(15,23,42,0.12);
  --radius:14px;
}
.content-wrap{ margin-left:250px; padding:22px; max-width:1500px; margin-top:0px;}
.top-header{ margin-bottom:10px;background:var(--blue); color:white; border-radius:12px; padding:28px 32px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 6px 14px rgba(0,0,0,0.12); }
.top-header .title{ font-size:44px; font-weight:800; letter-spacing:-0.5px; }
.admin-area{ display:flex; align-items:center; gap:18px; }
.greeting{ font-size:18px; opacity:0.95; }
.avatar{ width:56px; height:56px; border-radius:50%; background:white; color:var(--blue); display:flex; justify-content:center; align-items:center; font-weight:700; font-size:18px; border:4px solid #cfe1ff; box-shadow:0 2px 6px rgba(0,0,0,0.15); }

.equipment-table{ width:100%; border-collapse:collapse; margin-top:18px; background:white; box-shadow:var(--card-shadow); border-radius:12px; overflow:hidden;}
.equipment-table th, .equipment-table td{ padding:12px 16px; text-align:left; font-size:14px; border-bottom:1px solid #f1f3f6;}
.equipment-table th{ background:#f5f7fb; font-weight:700; color:#0d47a1;}
.equipment-table td img{ width:48px; height:48px; border-radius:8px; object-fit:cover; cursor:pointer; }
.equipment-table td .status{ font-weight:600; padding:4px 10px; border-radius:8px; display:inline-block; font-size:13px; }
.status-good{ background:#c8e6c9; color:#2e7d32; }
.status-fair{ background:#fff9c4; color:#f9a825; }
.status-damaged{ background:#ffcdd2; color:#b71c1c; }

.action-btn{ background:var(--blue); color:white; border:none; padding:6px 12px; border-radius:10px; font-weight:600; cursor:pointer; margin-right:6px; transition:0.2s; }
.action-btn:hover{ opacity:0.9; transform:scale(1.05); }

.add-equipment{ display:inline-block; margin-bottom:12px; background:#1e73ff; color:white; padding:10px 16px; border-radius:12px; font-weight:700; text-decoration:none; }

/* MODAL PHOTO VIEWER */
#photoModal{
  display:none;
  position:fixed;
  top:0; left:0;
  width:100%; height:100%;
  background:rgba(0,0,0,0.7);
  justify-content:center;
  align-items:center;
  z-index:9999;
}
#photoModal img{
  max-width:90%;
  max-height:90%;
  border-radius:12px;
  box-shadow:0 0 20px #000;
}
</style>

<main class="content-wrap">
  <div class="top-header">
    <div class="title">Equipment</div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <!-- SUCCESS MESSAGE -->
  <?php if(isset($_GET['updated']) && $_GET['updated'] == 1): ?>
      <div id="successMsg" style="
          background:#c8e6c9; 
          color:#2e7d32; 
          padding:12px; 
          border-radius:10px; 
          margin-bottom:15px; 
          font-weight:600;
          transition: opacity 0.5s ease;
      ">
          ✅ Equipment updated successfully!
      </div>
  <?php endif; ?>

  <a href="add_equipment.php" class="add-equipment">+ Add Equipment</a>

  <table class="equipment-table">
    <thead>
      <tr>
        <th>Photo</th>
        <th>Code</th>
        <th>Name</th>
        <th>Category</th>
        <th>Total Qty</th>
        <th>Available Qty</th>
        <th>Condition</th>
        <th>Location</th>
        <th>Actions</th>
      </tr>
    </thead>

    <tbody>
      <?php if(!empty($equipments)): ?>
        <?php foreach($equipments as $eq): ?>
        <tr>
          <td>
            <img 
              src="equipment_img/<?= htmlspecialchars($eq['photo'] ?: 'default.png') ?>" 
              onclick="viewPhoto(this.src)"
            >
          </td>

          <td><?= htmlspecialchars($eq['code']) ?></td>
          <td><?= htmlspecialchars($eq['name']) ?></td>
          <td><?= htmlspecialchars($eq['category']) ?></td>

          <td><?= htmlspecialchars($eq['total_quantity']) ?></td>
          <td><?= htmlspecialchars($eq['available_quantity']) ?></td>

          <td>
            <span class="status 
                <?= strtolower($eq['condition']) === 'good' ? 'status-good' : 
                   (strtolower($eq['condition'])==='fair' ? 'status-fair':'status-damaged') ?>">
              <?= htmlspecialchars($eq['condition']) ?>
            </span>
          </td>

          <td><?= htmlspecialchars($eq['location']) ?></td>

          <td>
            <a href="edit_equipment.php?id=<?= $eq['id'] ?>" class="action-btn">Edit</a>
            <a href="delete_equipment.php?id=<?= $eq['id'] ?>" class="action-btn" onclick="return confirm('Delete this equipment?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="9" style="text-align:center;">No equipment found. Add a new item to get started.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>

<!-- MODAL FOR PHOTO VIEW -->
<div id="photoModal" onclick="this.style.display='none'">
    <img id="modalImg">
</div>

<!-- AUTO-HIDE SCRIPT -->
<script>
function viewPhoto(src){
    document.getElementById("modalImg").src = src;
    document.getElementById("photoModal").style.display = "flex";
}

setTimeout(() => {
    const msg = document.getElementById("successMsg");
    if (msg) {
        msg.style.opacity = "0";
        setTimeout(() => msg.remove(), 500);
    }
}, 2000);
</script>
