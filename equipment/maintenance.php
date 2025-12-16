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

// Handle MARK actions
if (isset($_POST['mark_equipment'])) {
    $equipment_id = $_POST['id'];
    $action = $_POST['action'];
    $remarks = $_POST['remarks'] ?? '';
    $performed_by = $_SESSION['user']['id'] ?? 1;

    $check = $conn->prepare("
        SELECT id 
        FROM maintenance_logs 
        WHERE equipment_id = ?
        LIMIT 1
    ");
    $check->execute([$equipment_id]);

    if ($check->rowCount() > 0) {
        $stmt = $conn->prepare("
            UPDATE maintenance_logs
            SET action = ?, remarks = ?, performed_by = ?, performed_at = NOW()
            WHERE equipment_id = ?
        ");
        $stmt->execute([$action, $remarks, $performed_by, $equipment_id]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO maintenance_logs (equipment_id, action, remarks, performed_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$equipment_id, $action, $remarks, $performed_by]);
    }

    $_SESSION['success'] = "Equipment marked as $action successfully!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
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

// Handle Group Delete
if (isset($_POST['delete_group'])) {
    $equipmentIds = $_POST['delete_group'];
    $idsArray = explode(',', $equipmentIds);
    
    if (count($idsArray) > 0) {
        $placeholders = str_repeat('?,', count($idsArray) - 1) . '?';
        $stmt = $conn->prepare("DELETE FROM maintenance_logs WHERE equipment_id IN ($placeholders)");
        $stmt->execute($idsArray);
        
        $_SESSION['success'] = "Group records deleted successfully! (" . count($idsArray) . " equipment items)";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Search and Filter
$search = $_GET['search'] ?? '';
$filter_action = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

$query = "SELECT 
            e.name as equipment_name,
            COUNT(e.id) as equipment_count,
            MIN(e.code) as code,
            GROUP_CONCAT(e.id ORDER BY e.code) as equipment_ids,
            ml.action,
            GROUP_CONCAT(DISTINCT ml.remarks SEPARATOR '; ') as remarks,
            MAX(ml.performed_at) as performed_at
          FROM equipment e
          LEFT JOIN (
              SELECT ml1.*
              FROM maintenance_logs ml1
              INNER JOIN (
                  SELECT equipment_id, MAX(performed_at) AS max_date
                  FROM maintenance_logs
                  GROUP BY equipment_id
              ) ml2 ON ml1.equipment_id = ml2.equipment_id 
                   AND ml1.performed_at = ml2.max_date
          ) ml ON e.id = ml.equipment_id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (e.code LIKE ? OR e.name LIKE ? OR ml.remarks LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($filter_action)) {
    $query .= " AND ml.action = ?";
    $params[] = $filter_action;
}

if (!empty($date_from)) {
    $query .= " AND DATE(ml.performed_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(ml.performed_at) <= ?";
    $params[] = $date_to;
}

$query .= " GROUP BY e.name, ml.action";

$countQuery = "SELECT COUNT(DISTINCT e.name) as total
               FROM equipment e
               LEFT JOIN maintenance_logs ml ON e.id = ml.equipment_id
               WHERE 1=1";

$countParams = [];
if (!empty($search)) {
    $countQuery .= " AND (e.code LIKE ? OR e.name LIKE ? OR ml.remarks LIKE ?)";
    $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm]);
}
if (!empty($filter_action)) {
    $countQuery .= " AND EXISTS (
        SELECT 1 FROM maintenance_logs ml2 
        WHERE ml2.equipment_id = e.id 
        AND ml2.action = ?
    )";
    $countParams[] = $filter_action;
}

$countStmt = $conn->prepare($countQuery);
$countStmt->execute($countParams);
$total_records = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

$query .= " ORDER BY e.name ASC LIMIT $records_per_page OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$equipmentStmt = $conn->prepare("SELECT id, code, name FROM equipment ORDER BY code");
$equipmentStmt->execute();
$allEquipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$statsChecked = $conn->query("
    SELECT COUNT(DISTINCT e.id) as cnt
    FROM equipment e
    INNER JOIN maintenance_logs ml ON e.id = ml.equipment_id
    WHERE ml.action = 'Checked'
    AND ml.performed_at = (
        SELECT MAX(performed_at) 
        FROM maintenance_logs 
        WHERE equipment_id = e.id
    )
")->fetch(PDO::FETCH_ASSOC)['cnt'];

$statsRepaired = $conn->query("
    SELECT COUNT(DISTINCT e.id) as cnt
    FROM equipment e
    INNER JOIN maintenance_logs ml ON e.id = ml.equipment_id
    WHERE ml.action = 'Repaired'
    AND ml.performed_at = (
        SELECT MAX(performed_at) 
        FROM maintenance_logs 
        WHERE equipment_id = e.id
    )
")->fetch(PDO::FETCH_ASSOC)['cnt'];

$statsDamaged = $conn->query("
    SELECT COUNT(DISTINCT e.id) as cnt
    FROM equipment e
    INNER JOIN maintenance_logs ml ON e.id = ml.equipment_id
    WHERE ml.action = 'Marked Damage'
    AND ml.performed_at = (
        SELECT MAX(performed_at) 
        FROM maintenance_logs 
        WHERE equipment_id = e.id
    )
")->fetch(PDO::FETCH_ASSOC)['cnt'];

$totalEquipment = $conn->query("SELECT COUNT(*) as cnt FROM equipment")->fetch(PDO::FETCH_ASSOC)['cnt'];

$userName = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$displayRole = htmlspecialchars($_SESSION['user']['role'] ?? 'Admin');
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

#successMsg {
  background:linear-gradient(135deg, var(--success) 0%, #66bb6a 100%);
  color:white;
  padding:16px 20px;
  border-radius: var(--radius);
  margin-bottom:20px;
  font-weight:600;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(76,175,80,0.3);
  display:flex;
  align-items:center;
  gap:12px;
}

#successMsg i {
  font-size: 20px;
}

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
  background: var(--blue);
}

.stat-card.checked::before {
  background: #1565c0;
}

.stat-card.repaired::before {
  background: #1565c0;
}

.stat-card.damaged::before {
  background: #1565c0;
}

.stat-card:hover {
  box-shadow: var(--hover-shadow);
  transform: translateY(-2px);
}

.stat-left h4 {
  font-size: 16px;
  color: #000000ff;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.stat-number {
  font-size: 36px;
  font-weight: 800;
  color: black;
}

.stat-card.checked .stat-number {
  color: #000000ff;
}

.stat-card.repaired .stat-number {
  color: #000000ff;
}

.stat-card.damaged .stat-number {
  color: #000000ff;
}

.stat-icon {
  font-size: 24px;
  position: absolute;
  right: 20px;
  top: 20px;
  opacity: 0.1;
}

.controls-section { background:white; padding:24px; border-radius:var(--radius); box-shadow:var(--card-shadow); margin-bottom:24px; display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; }

.controls-left { display:flex; gap:16px; flex:1; flex-wrap:wrap; align-items:flex-end; }

.search-box { flex:1; min-width:280px; position:relative; }

.search-box input { width:100%; padding:12px 16px 12px 44px; border:2px solid #e0e7ef; border-radius:12px; font-size:15px; transition:all 0.2s; background:#f8fafc; }

.search-box input:focus { outline:none; border-color:var(--blue); background:white; box-shadow:0 0 0 4px rgba(13,71,161,0.1); }

.search-box i { position:absolute; left:14px; top:50%; transform:translateY(-50%); font-size:18px; color:#64748b; }

.filter-box { display:flex; flex-direction:column; gap:6px; }

.filter-box label { font-size:13px; font-weight:600; color:#555; }

.filter-box select { padding:12px 12px; border:2px solid #e0e7ef; border-radius:12px; font-size:15px; background:white; cursor:pointer; transition:all 0.2s; min-width:160px; }

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

.status{ font-weight:600; padding:6px 12px; border-radius:8px; display:inline-block; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; }

.status-checked{ background:#c8e6c9; color:#2e7d32; }

.status-repaired{ background:#c8e6c9; color:#2e7d32; }

.status-damaged{ background:#ffcdd2; color:#c62828; }

.status-none { background:#f5f5f5; color:#757575; }

.action-btns { display:flex; gap:8px; flex-wrap:wrap; }

.action-btn{ background:var(--blue); color:white; border:none; padding:8px 16px; border-radius:10px; font-weight:600; cursor:pointer; transition:all 0.2s; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }

.action-btn:hover{ transform:scale(1.05); box-shadow:0 4px 12px rgba(13,71,161,0.3); }

.action-btn.delete { background:var(--danger); }

.action-btn.delete:hover { background:#d32f2f; box-shadow:0 4px 12px rgba(244,67,54,0.3); }

.no-records { text-align:center; padding:60px 20px; color:#94a3b8; font-size:16px; }

.no-records i { display:block; font-size:48px; margin-bottom:16px; color:#cbd5e1; }

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal-card{
    background: #fff;
    width: 450px;
    max-width: 95%;
    border-radius: var(--radius);
    padding: 30px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.25);
}

.modal-header{
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3{
    margin: 0;
    font-weight: 700;
    color: var(--blue);
    font-size:20px;
}

.modal-header button{
    background: #ff5252;
    border: none;
    color: #fff;
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-weight:600;
    transition:0.2s;
}

.modal-header button:hover {
    background:#ff1744;
}

.modal-card label{
    font-weight: 600;
    margin-top: 16px;
    display: block;
    font-size: 14px;
    color:#333;
}

.modal-card input,
.modal-card select,
.modal-card textarea{
    width: 100%;
    padding: 10px 12px;
    margin-top: 8px;
    border-radius: 10px;
    border: 2px solid #e0e0e0;
    outline: none;
    font-size: 14px;
    transition: 0.2s;
    font-family:'Poppins', sans-serif;
    box-sizing: border-box;
}

.modal-card input:focus,
.modal-card select:focus,
.modal-card textarea:focus{
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(13,71,161,0.1);
}

.modal-actions{
    margin-top: 25px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn-save{
    background: linear-gradient(135deg, var(--success) 0%, #66bb6a 100%);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition:0.2s;
    display:flex;
    align-items:center;
    gap:8px;
}

.btn-save:hover {
    transform:scale(1.05);
    box-shadow:0 4px 12px rgba(76,175,80,0.3);
}

.btn-cancel{
    background: #f2f2f2;
    border: none;
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition:0.2s;
}

.btn-cancel:hover {
    background:#e0e0e0;
}

.pagination {
  display:flex;
  justify-content:center;
  align-items:center;
  gap:8px;
  padding:20px;
  background:#f8fafc;
  border-top:1px solid #e0e7ef;
}

.pagination a,
.pagination span {
  padding:8px 12px;
  border-radius:8px;
  text-decoration:none;
  color:var(--blue);
  font-weight:600;
  transition:all 0.2s;
}

.pagination a:hover {
  background:var(--blue);
  color:white;
}

.pagination .current {
  background:var(--blue);
  color:white;
}

.pagination .disabled {
  color:#cbd5e1;
  cursor:not-allowed;
}

@media (max-width: 768px) {
  .content-wrap { margin-left:0; padding:16px; }
  .top-header { flex-direction:column; gap:16px; text-align:center; }
  .controls-section { flex-direction:column; }
  .controls-left { width:100%; }
  .search-box { width:100%; }
  .equipment-table { font-size:12px; }
  .equipment-table th, .equipment-table td { padding:10px; }
  .stats-row { flex-direction:column; }
  .stat-card { min-width:100%; }
}
</style>

<main class="content-wrap">

  <!-- Top Header -->
  <div class="top-header">
    <div class="title">
      <i class="fas fa-tools title-icon"></i>
      Equipment Maintenance
    </div>
    <div class="admin-area">
     <div class="greeting">Hello, <?= htmlspecialchars(strtoupper($role)) ?>!</div>
      <div class="avatar">OF</div>
    </div>
  </div>

  <?php if(isset($_SESSION['success'])): ?>
    <div id="successMsg">
      <i class="fas fa-check-circle"></i>
      <?= $_SESSION['success'] ?>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <!-- STATISTICS -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-left">
        <h4><i class="fas fa-boxes"></i> Total Equipment</h4>
        <div class="stat-number"><?= $totalEquipment ?></div>
      </div>
      <!-- <div class="stat-icon"><i class="fas fa-boxes"></i></div> -->
    </div>
    <div class="stat-card checked">
      <div class="stat-left">
        <h4><i class="fas fa-check-circle"></i> Checked</h4>
        <div class="stat-number"><?= $statsChecked ?></div>
      </div>
      <!-- <div class="stat-icon"><i class="fas fa-check-circle"></i></div> -->
    </div>
    <div class="stat-card repaired">
      <div class="stat-left">
        <h4><i class="fas fa-wrench"></i> Repaired</h4>
        <div class="stat-number"><?= $statsRepaired ?></div>
      </div>
      <!-- <div class="stat-icon"><i class="fas fa-wrench"></i></div> -->
    </div>
    <div class="stat-card damaged">
      <div class="stat-left">
        <h4><i class="fas fa-exclamation-triangle"></i> Damaged</h4>
        <div class="stat-number"><?= $statsDamaged ?></div>
      </div>
      <!-- <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div> -->
    </div>
  </div>

  <!-- CONTROLS -->
  <div class="controls-section">
    <div class="controls-left">
      <form method="GET" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" name="search" placeholder="Search by code, name, or remarks..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-box">
          <label>Status</label>
          <select name="action">
            <option value="">All Status</option>
            <option value="Checked" <?= $filter_action === 'Checked' ? 'selected' : '' ?>>Checked</option>
            <option value="Repaired" <?= $filter_action === 'Repaired' ? 'selected' : '' ?>>Repaired</option>
            <option value="Marked Damage" <?= $filter_action === 'Marked Damage' ? 'selected' : '' ?>>Marked Damage</option>
          </select>
        </div>
        <div class="filter-box">
          <label>From Date</label>
          <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="filter-box">
          <label>To Date</label>
          <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
      </form>
      <?php if(!empty($search) || !empty($filter_action) || !empty($date_from) || !empty($date_to)): ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-clear"><i class="fas fa-times"></i> Clear</a>
      <?php endif; ?>
    </div>
    <button type="button" class="btn add-equipment" onclick="openMarkModal()"><i class="fas fa-plus"></i> Mark Equipment</button>
  </div>

  <!-- TABLE -->
  <div class="table-container">
    <table class="equipment-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Equipment Name</th>
          <th>Code</th>
          <th>Latest Status</th>
          <th>Remarks</th>
          <th>Last Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($records) > 0): ?>
          <?php foreach($records as $index => $row): ?>
          <tr>
            <td><?= $offset + $index + 1 ?></td>
            <td><strong><?= htmlspecialchars($row['equipment_name']) ?></strong></td>
            <td><?= htmlspecialchars($row['code']) ?></td>
            <td>
              <?php
              if($row['action']):
                $statusClass = 'status-checked';
                if($row['action'] === 'Repaired') $statusClass = 'status-repaired';
                elseif($row['action'] === 'Marked Damage') $statusClass = 'status-damaged';
              ?>
                <span class="status <?= $statusClass ?>">
                  <?= htmlspecialchars($row['action']) ?>
                </span>
              <?php else: ?>
                <span class="status status-none">No Status</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['remarks'] ?? '-') ?></td>
            <td><?= $row['performed_at'] ?: '-' ?></td>
            <td class="action-btns">
              <?php 
              $equipIds = explode(',', $row['equipment_ids']);
              if(count($equipIds) > 0 && $row['action']): 
              ?>
                <button class="action-btn delete" onclick="deleteGroup('<?= htmlspecialchars($row['equipment_name']) ?>', '<?= implode(',', $equipIds) ?>')">
                  <i class="fas fa-trash"></i> Delete
                </button>
              <?php else: ?>
                <span style="color:#94a3b8; font-size:13px;">No record</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
        <tr>
          <td colspan="7" class="no-records"><i class="fas fa-inbox"></i><br>No equipment records found.</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if($total_pages > 1): ?>
    <div class="pagination">
      <?php if($page > 1): ?>
        <a href="?page=1&search=<?= urlencode($search) ?>&action=<?= urlencode($filter_action) ?>">
          <i class="fas fa-angle-double-left"></i>
        </a>
        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($filter_action) ?>">
          <i class="fas fa-angle-left"></i>
        </a>
      <?php else: ?>
        <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
        <span class="disabled"><i class="fas fa-angle-left"></i></span>
      <?php endif; ?>

      <?php
      $start = max(1, $page - 2);
      $end = min($total_pages, $page + 2);
      
      for($i = $start; $i <= $end; $i++): ?>
        <?php if($i == $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($filter_action) ?>">
            <?= $i ?>
          </a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if($page < $total_pages): ?>
        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($filter_action) ?>">
          <i class="fas fa-angle-right"></i>
        </a>
        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($filter_action) ?>">
          <i class="fas fa-angle-double-right"></i>
        </a>
      <?php else: ?>
        <span class="disabled"><i class="fas fa-angle-right"></i></span>
        <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

</main>

<!-- Modal for Marking Equipment -->
<div id="markModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-header">
      <h3><i class="fas fa-edit"></i> Mark Equipment Status</h3>
      <button onclick="closeMarkModal()">✕</button>
    </div>
    <form method="POST">
      <label>Select Equipment *</label>
      <select name="id" required>
        <option value="">-- Choose Equipment --</option>
        <?php foreach($allEquipment as $eq): ?>
          <option value="<?= $eq['id'] ?>">
            <?= htmlspecialchars($eq['code']) ?> - <?= htmlspecialchars($eq['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Action *</label>
      <select name="action" required>
        <option value="">-- Select Action --</option>
        <option value="Checked">Checked</option>
        <option value="Repaired">Repaired</option>
        <option value="Marked Damage">Marked Damage</option>
      </select>

      <label>Remarks</label>
      <textarea name="remarks" rows="3" placeholder="Optional notes..."></textarea>

      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeMarkModal()">Cancel</button>
        <button type="submit" name="mark_equipment" class="btn-save">
          <i class="fas fa-save"></i> Save
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Success message auto-hide
setTimeout(() => {
  const msg = document.getElementById('successMsg');
  if(msg) {
    msg.style.transition = 'opacity 0.5s';
    msg.style.opacity = '0';
    setTimeout(() => msg.remove(), 500);
  }
}, 3000);

// Modal functions
function openMarkModal() {
  document.getElementById('markModal').classList.add('active');
}

function closeMarkModal() {
  document.getElementById('markModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('markModal').addEventListener('click', function(e) {
  if(e.target === this) closeMarkModal();
});

// Delete group function
function deleteGroup(equipmentName, equipmentIds) {
  if(confirm(`Are you sure you want to delete all maintenance records for "${equipmentName}"?\n\nThis will affect ${equipmentIds.split(',').length} equipment item(s).`)) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'delete_group';
    input.value = equipmentIds;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  }
}

// Prevent form resubmission on refresh
if(window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}
</script>