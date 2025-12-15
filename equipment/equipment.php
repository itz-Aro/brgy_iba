<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

// DB connection
$db = new Database();
$conn = $db->getConnection();

// Search and Filter
$search = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_condition = $_GET['condition'] ?? '';

$query = "SELECT * FROM equipment WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (code LIKE ? OR name LIKE ? OR location LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($filter_category)) {
    $query .= " AND category = ?";
    $params[] = $filter_category;
}

if (!empty($filter_condition)) {
    $query .= " AND `condition` = ?";
    $params[] = $filter_condition;
}

$query .= " ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$equipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories
$categoriesStmt = $conn->prepare("SELECT DISTINCT category FROM equipment ORDER BY category");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

// Current user info
$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);
$userName = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
?>

<link rel="stylesheet" href="/public/css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<style>
:root{
  --blue:#0d47a1;
  --blue-light:#0f62e8ff;
  --blue-dark:#083d97;  
  --light-gray:#f5f7fa;
  --card-shadow:0 6px 20px rgba(15,23,42,0.08);
  --hover-shadow:0 8px 24px rgba(15,23,42,0.12);
  --radius:14px;
  --success:#4caf50;
  --danger:#f44336;
  --warning:#ff9800;
  --info:#2196f3;
}
body { font-family:'Poppins',sans-serif; background-color: var(--light-gray); color:#2c3e50; }
.content-wrap{ margin-left:250px; padding:28px; max-width:1600px; margin-top:0; min-height:100vh; }
.top-header{ margin-bottom:28px; background: var(--blue); color:white; border-radius:16px; padding:32px 40px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 8px 20px rgba(13,71,161,0.2);}
.top-header .title{ font-size:38px; font-weight:bold; letter-spacing:-0.8px; display:flex; align-items:center; gap:12px; }
.title-icon { font-size:32px; }
.admin-area{ display:flex; align-items:center; gap:20px; }
.greeting{ font-size:18px; opacity:0.95; font-weight:500; }
.avatar{ width:58px; height:58px; border-radius:50%; background:white; color:var(--blue); display:flex; justify-content:center; align-items:center; font-weight:700; font-size:20px; border:4px solid rgba(255,255,255,0.3); box-shadow:0 4px 12px rgba(0,0,0,0.15); transition: transform 0.2s; }
.avatar:hover { transform: scale(1.05); }

.stats-row {
  display: flex;
  gap: 20px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.stat-card {
  background: white;
  padding: 24px;
  border-radius: var(--radius);
  box-shadow: var(--card-shadow);
  flex: 1;
  min-width: 200px;
  transition: all 0.2s;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: var(--blue); /* default, can override per card */
}

.stat-card.checked::before {
  background: #1565c0; /* Blue for Checked */
}

.stat-card.repaired::before {
  background: #2e7d32; /* Green for Repaired */
}

.stat-card.damaged::before {
  background: #c62828; /* Red for Damaged */
}

.stat-card:hover {
  box-shadow: var(--hover-shadow);
  transform: translateY(-2px);
}

.stat-left h4 {
  font-size: 16px;
  color: #000000ff; /* Label color */
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.stat-number {
  font-size: 36px;
  font-weight: 800;
  color: black; /* default, can override per card */
}

.stat-card.checked .stat-number {
  color: #1565c0; /* Blue number for Checked */
}

.stat-card.repaired .stat-number {
  color: #2e7d32; /* Green number for Repaired */
}

.stat-card.damaged .stat-number {
  color: #c62828; /* Red number for Damaged */
}

.stat-icon {
  font-size: 24px;
  position: absolute;
  right: 20px;
  top: 20px;
  opacity: 0.1;
}

.controls-section { background:white; padding:24px; border-radius:var(--radius); box-shadow:var(--card-shadow); margin-bottom:24px; display:flex; gap:16px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
.controls-left { display:flex; gap:16px; flex:1; flex-wrap:wrap; }
.search-box { flex:1; min-width:280px; position:relative; }
.search-box input { width:100%; padding:12px 16px 12px 44px; border:2px solid #e0e7ef; border-radius:12px; font-size:15px; transition:all 0.2s; background:#f8fafc; }
.search-box input:focus { outline:none; border-color:var(--blue); background:white; box-shadow:0 0 0 4px rgba(13,71,161,0.1); }
.search-box i { position:absolute; left:14px; top:50%; transform:translateY(-50%); font-size:18px; color:#64748b; }
.filter-box select { padding:12px 5px; border:2px solid #e0e7ef; border-radius:12px; font-size:15px; background:white; cursor:pointer; transition:all 0.2s; min-width:160px; }
.filter-box select:focus { outline:none; border-color:var(--blue); box-shadow:0 0 0 4px rgba(13,71,161,0.1); }
.btn { font-family:'Poppins',sans-serif; padding:12px 20px; border-radius:12px; font-weight:600; cursor:pointer; transition:all 0.2s; font-size:15px; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
.btn-primary { background:var(--blue); color:white; }
.btn-primary:hover { background:var(--blue-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(13,71,161,0.3); }
.btn-clear { background:#e0e7ef; color:#334155; }
.btn-clear:hover { background:#cbd5e1; }
.add-equipment { background:linear-gradient(135deg, var(--success) 0%, #66bb6a 100%); color:white; box-shadow:0 4px 12px rgba(76,175,80,0.3); }
.add-equipment:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(76,175,80,0.4); }

.table-container { background:white; border-radius:var(--radius); box-shadow:var(--card-shadow); overflow:hidden; margin-bottom:24px; }
.equipment-table{ width:100%; border-collapse:collapse; }
.equipment-table th, .equipment-table td{ padding:16px 20px; text-align:left; font-size:14px; border-bottom:1px solid #f1f3f6; }
.equipment-table th{ background:#083d97; font-weight:700; color:white; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; }
.equipment-table tbody tr { transition:all 0.15s; }
.equipment-table tbody tr:hover { background-color:#f8fafc; }
.equipment-table td img{ width:56px; height:56px; border-radius:10px; object-fit:cover; cursor:pointer; border:2px solid #e0e7ef; transition:all 0.2s; }
.equipment-table td img:hover { transform:scale(1.1); box-shadow:0 4px 12px rgba(0,0,0,0.2); }

.status{ font-weight:600; padding:6px 12px; border-radius:8px; display:inline-block; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; }
.status-good{ background:#c8e6c9; color:#2e7d32; }
.status-fair{ background:#fff9c4; color:#f57f17; }
.status-damaged{ background:#ffcdd2; color:#c62828; }

.action-btns { display:flex; gap:8px; flex-wrap:wrap; }
.action-btn{ background:var(--blue); color:white; border:none; padding:8px 16px; border-radius:10px; font-weight:600; cursor:pointer; transition:all 0.2s; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; width:70%; gap:6px; }
.action-btn:hover{ transform:scale(1.05); box-shadow:0 4px 12px rgba(13,71,161,0.3); }
.action-btn.delete { background:var(--danger); }
.action-btn.delete:hover { background:#d32f2f; box-shadow:0 4px 12px rgba(244,67,54,0.3); }

.no-records { text-align:center; padding:60px 20px; color:#94a3b8; font-size:16px; }
.no-records i { display:block; font-size:48px; margin-bottom:16px; color:#cbd5e1; }

#photoModal{ display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); justify-content:center; align-items:center; z-index:9999; cursor:pointer; }
#photoModal img{ max-width:90%; max-height:90%; border-radius:12px; box-shadow:0 0 40px rgba(0,0,0,0.5); }
.modal-close { position:absolute; top:20px; right:20px; background:white; color:#333; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:bold; cursor:pointer; transition:all 0.2s; }
.modal-close:hover { transform:rotate(90deg); background:var(--danger); color:white; }

@media (max-width: 768px) {
  .content-wrap { margin-left:0; padding:16px; }
  .top-header { flex-direction:column; gap:16px; text-align:center; }
  .controls-section { flex-direction:column; }
  .controls-left { width:100%; }
  .search-box { width:100%; }
  .equipment-table { font-size:12px; }
  .equipment-table th, .equipment-table td { padding:10px; }
}
</style>

<main class="content-wrap">

  <!-- Top Header -->
  <div class="top-header">
    <div class="title">
      <i class="fas fa-box title-icon"></i>
      Equipment Management
    </div>
    <div class="admin-area">
          <div class="greeting">Hello, <?= htmlspecialchars(strtoupper($role)) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <!-- STATISTICS -->
  <div class="stats-row">
    <div class="stat-card card-pending">
      <div class="stat-left">
        <h4><i class="fas fa-boxes"></i> Total Equipment</h4>
        <div class="stat-number"><?= count($equipments) ?></div>
      </div>
      <div class="stat-icon"></div>
    </div>
    <div class="stat-card card-ongoing">
      <div class="stat-left">
        <h4><i class="fas fa-cubes"></i> Total Quantity</h4>
        <div class="stat-number"><?= array_sum(array_column($equipments,'total_quantity')) ?></div>
      </div>
      <div class="stat-icon"></div>
    </div>
    <div class="stat-card card-due">
      <div class="stat-left">
        <h4><i class="fas fa-check-circle"></i> Available</h4>
        <div class="stat-number"><?= array_sum(array_column($equipments,'available_quantity')) ?></div>
      </div>
      <div class="stat-icon"></div>
    </div>
    <div class="stat-card card-damage">
      <div class="stat-left">
        <h4><i class="fas fa-folder-open"></i> Categories</h4>
        <div class="stat-number"><?= count($categories) ?></div>
      </div>
      <div class="stat-icon"></div>
    </div>
  </div>

  <!-- CONTROLS -->
  <div class="controls-section">
    <div class="controls-left">
      <form method="GET" style="display:contents;">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" name="search" placeholder="Search by code, name, or location..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-box">
          <select name="category">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-box">
          <select name="condition">
            <option value="">All Conditions</option>
            <option value="Good" <?= $filter_condition === 'Good' ? 'selected' : '' ?>>Good</option>
            <option value="Fair" <?= $filter_condition === 'Fair' ? 'selected' : '' ?>>Fair</option>
            <option value="Damaged" <?= $filter_condition === 'Damaged' ? 'selected' : '' ?>>Damaged</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
      </form>
      <?php if(!empty($search) || !empty($filter_category) || !empty($filter_condition)): ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-clear"><i class="fas fa-times"></i> Clear</a>
      <?php endif; ?>
    </div>
    <a href="add_equipment.php" class="btn add-equipment"><i class="fas fa-plus"></i> Add Equipment</a>
  </div>

  <!-- TABLE -->
  <div class="table-container">
    <table class="equipment-table">
      <thead>
        <tr>
          <th>Photo</th>
          <th>Code</th>
          <th>Name</th>
          <th>Category</th>
          <th>Total Qty</th>
          <th>Available</th>
          <th>Condition</th>
          <th>Location</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!empty($equipments)): ?>
          <?php foreach($equipments as $eq): ?>
          <tr>
            <td><img src="equipment_img/<?= htmlspecialchars($eq['photo'] ?: 'default.png') ?>" onclick="viewPhoto(this.src)" alt="<?= htmlspecialchars($eq['name']) ?>"></td>
            <td><?= htmlspecialchars($eq['code']) ?></td>
            <td><?= htmlspecialchars($eq['name']) ?></td>
            <td><?= htmlspecialchars($eq['category']) ?></td>
            <td><?= htmlspecialchars($eq['total_quantity']) ?></td>
            <td><?= htmlspecialchars($eq['available_quantity']) ?></td>
            <td><span class="status <?= strtolower($eq['condition'])==='good'?'status-good':(strtolower($eq['condition'])==='fair'?'status-fair':'status-damaged') ?>"><?= htmlspecialchars($eq['condition']) ?></span></td>
            <td><?= htmlspecialchars($eq['location']) ?></td>
            <td class="action-btns">
              <a href="edit_equipment.php?id=<?= $eq['id'] ?>" class="action-btn"><i class="fas fa-edit"></i> Edit</a>
              <a href="delete_equipment.php?id=<?= $eq['id'] ?>" class="action-btn delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="9" class="no-records"><i class="fas fa-box-open"></i><br>No equipment found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  
</main>

<!-- PHOTO MODAL -->
<div id="photoModal" onclick="this.style.display='none'">
  <div class="modal-close">×</div>
  <img id="modalImg" alt="Equipment Photo">
</div>

<script>
function viewPhoto(src){
  document.getElementById("modalImg").src = src;
  document.getElementById("photoModal").style.display = "flex";
}
setTimeout(()=>{
  const msg = document.getElementById("successMsg");
  if(msg){ msg.style.opacity="0"; setTimeout(()=>msg.remove(),500); }
}, 3000);
</script>
