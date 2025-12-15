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

    // Check if a maintenance record already exists
    $check = $conn->prepare("
        SELECT id 
        FROM maintenance_logs 
        WHERE equipment_id = ?
        LIMIT 1
    ");
    $check->execute([$equipment_id]);

    if ($check->rowCount() > 0) {
        // Update existing log
        $stmt = $conn->prepare("
            UPDATE maintenance_logs
            SET action = ?, remarks = ?, performed_by = ?, performed_at = NOW()
            WHERE equipment_id = ?
        ");
        $stmt->execute([
            $action,
            $remarks,
            $performed_by,
            $equipment_id
        ]);

    } else {
        // Insert first log if none exists
        $stmt = $conn->prepare("
            INSERT INTO maintenance_logs (equipment_id, action, remarks, performed_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $equipment_id,
            $action,
            $remarks,
            $performed_by
        ]);
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

// Search and Filter
$search = $_GET['search'] ?? '';
$filter_action = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'performed_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Modified query to JOIN with equipment table and get only latest record per equipment
$query = "SELECT ml.*, e.code, e.name, e.category
          FROM maintenance_logs ml
          INNER JOIN equipment e ON ml.equipment_id = e.id
          INNER JOIN (
                SELECT equipment_id, MAX(performed_at) AS max_date
                FROM maintenance_logs
                GROUP BY equipment_id
          ) latest 
          ON ml.equipment_id = latest.equipment_id 
          AND ml.performed_at = latest.max_date
          WHERE 1=1";
$params = [];


if (!empty($search)) {
    $countQuery .= " AND (e.code LIKE ? OR e.name LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

if (!empty($filter_action)) {
    $countQuery .= " AND EXISTS (
        SELECT 1 FROM maintenance_logs ml2
        WHERE ml2.equipment_id = e.id AND ml2.action = ?
    )";
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

// Count total records
$countQuery = "SELECT COUNT(*) as total
               FROM (
                   SELECT equipment_id, MAX(performed_at)
                   FROM maintenance_logs
                   GROUP BY equipment_id
               ) x
               INNER JOIN equipment e ON x.equipment_id = e.id
               WHERE 1=1";

if (!empty($search)) {
    $countQuery .= " AND (e.code LIKE ? OR e.name LIKE ? OR ml.remarks LIKE ?)";
}
if (!empty($filter_action)) {
    $countQuery .= " AND ml.action = ?";
}
if (!empty($date_from)) {
    $countQuery .= " AND DATE(ml.performed_at) >= ?";
}
if (!empty($date_to)) {
    $countQuery .= " AND DATE(ml.performed_at) <= ?";
}

$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$total_records = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Main query with sorting and pagination
$allowed_sorts = ['code', 'name', 'action', 'performed_at'];
$sort_by = in_array($sort_by, $allowed_sorts) ? $sort_by : 'performed_at';
$sort_order = $sort_order === 'ASC' ? 'ASC' : 'DESC';

$query .= " ORDER BY $sort_by $sort_order LIMIT $records_per_page OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter
$actionsStmt = $conn->prepare("SELECT DISTINCT action FROM maintenance_logs ORDER BY action");
$actionsStmt->execute();
$actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Get all equipment for marking
$equipmentStmt = $conn->prepare("SELECT id, code, name FROM equipment ORDER BY code");
$equipmentStmt->execute();
$allEquipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$statsQuery = "SELECT * FROM maintenance_logs";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$allRecords = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

$userName = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --blue:#0d47a1;
  --blue-light:#1976d2;
  --blue-dark:#003c8f;
  --light-gray:#f5f7fa;
  --card-shadow:0 6px 20px rgba(15,23,42,0.08);
  --hover-shadow:0 8px 24px rgba(15,23,42,0.12);
  --radius:14px;
  --success:#4caf50;
  --danger:#f44336;
  --warning:#ff9800;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

  background-color: var(--light-gray);
  color: #2c3e50;
}

.content-wrap{
  margin-left:250px;
  padding:28px;
  max-width:1600px;
  margin-top:0;
  min-height:100vh;
}

.top-header{
  margin-bottom:28px;
 background: var(--blue);   
  color:white;
  border-radius:16px;
  padding:32px 40px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  box-shadow:0 8px 20px rgba(13,71,161,0.2);
}

.top-header .title{
  font-size:38px;
  font-weight:800;
  letter-spacing:-0.8px;
  display:flex;
  align-items:center;
  gap:12px;
}

.title-icon {
  font-size: 32px;
}

.admin-area{
  display:flex;
  align-items:center;
  gap:20px;
}

.greeting{
  font-size:18px;
  opacity:0.95;
  font-weight:500;
}

.avatar{
  width:58px;
  height:58px;
  border-radius:50%;
  background:white;
  color:var(--blue);
  display:flex;
  justify-content:center;
  align-items:center;
  font-weight:700;
  font-size:20px;
  border:4px solid rgba(255,255,255,0.3);
  box-shadow:0 4px 12px rgba(0,0,0,0.15);
  transition: transform 0.2s;
}

.avatar:hover {
  transform: scale(1.05);
}

#successMsg {
  background:linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
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
  font-size: 24px;
}

.controls-section {
  background:white;
  padding:24px;
  border-radius:var(--radius);
  box-shadow:var(--card-shadow);
  margin-bottom:24px;
}

.controls-row {
  display:flex;
  gap:16px;
  flex-wrap:wrap;
  align-items:center;
  margin-bottom:16px;
}

.controls-row:last-child {
  margin-bottom:0;
}

.search-box {
  flex:1;
  min-width:280px;
  position:relative;
}

.search-box input {
  width:100%;
  padding:12px 16px 12px 44px;
  border:2px solid #e0e7ef;
  border-radius:12px;
  font-size:15px;
  transition:all 0.2s;
  background:#f8fafc;
}

.search-box input:focus {
  outline:none;
  border-color:var(--blue);
  background:white;
  box-shadow:0 0 0 4px rgba(13,71,161,0.1);
}

.search-box i {
  position:absolute;
  left:14px;
  top:50%;
  transform:translateY(-50%);
  font-size:16px;
  color:#64748b;
}

.filter-group {
  display:flex;
  gap:12px;
  align-items:center;
  flex-wrap:wrap;
}

.filter-box {
  position:relative;
}

.filter-box label {
  position:absolute;
  top:-8px;
  left:12px;
  background:white;
  padding:0 4px;
  font-size:11px;
  font-weight:600;
  color:#64748b;
  text-transform:uppercase;
}

.filter-box select,
.filter-box input[type="date"] {
  padding:12px 16px;
  border:2px solid #e0e7ef;
  border-radius:12px;
  font-size:15px;
  background:white;
  cursor:pointer;
  transition:all 0.2s;
  min-width:160px;
}

.filter-box select:focus,
.filter-box input[type="date"]:focus {
  outline:none;
  border-color:var(--blue);
  box-shadow:0 0 0 4px rgba(13,71,161,0.1);
}

.btn {
  padding:12px 20px;
  border-radius:12px;
  font-weight:600;
  cursor:pointer;
  transition:all 0.2s;
  font-size:15px;
  border:none;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  gap:8px;
}

.btn-primary {
  background:var(--blue);
  color:white;
}

.btn-primary:hover {
  background:var(--blue-dark);
  transform:translateY(-1px);
  box-shadow:0 4px 12px rgba(13,71,161,0.3);
}

.btn-success {
  background:var(--success);
  color:white;
}

.btn-success:hover {
  background:#388e3c;
  transform:translateY(-1px);
  box-shadow:0 4px 12px rgba(76,175,80,0.3);
}

.btn-clear {
  background:#e0e7ef;
  color:#334155;
}

.btn-clear:hover {
  background:#cbd5e1;
}

.btn-print {
  background:#fff3e0;
  color:#e65100;
  border:2px solid #ff9800;
}

.btn-print:hover {
  background:#ff9800;
  color:white;
  transform:translateY(-2px);
  box-shadow:0 4px 12px rgba(255,152,0,0.3);
}

@media print {
  body * {
    visibility: hidden;
  }
  
  .print-area,
  .print-area * {
    visibility: visible;
  }
  
  .print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  
  .no-print {
    display: none !important;
  }
  
  .table-container {
    box-shadow: none;
  }
  
  table th {
    background-color: #f0f0f0 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}

.stats-row {
  display:flex;
  gap:20px;
  margin-bottom:24px;
  flex-wrap:wrap;
}

.stat-card {
  background:white;
  padding:24px;
  border-radius:var(--radius);
  box-shadow:var(--card-shadow);
  flex:1;
  min-width:200px;
  transition:all 0.2s;
  position:relative;
  overflow:hidden;
}

.stat-card::before {
  content:'';
  position:absolute;
  top:0;
  left:0;
  width:4px;
  height:100%;
  background:var(--blue);
}

.stat-card:hover {
  box-shadow:var(--hover-shadow);
  transform:translateY(-2px);
}

.stat-label {
  font-size:14px;
  color:black;
  font-weight:600;
  text-transform:uppercase;
  letter-spacing:0.5px;
  margin-bottom:8px;
  display:flex;
  align-items:center;
  gap:6px;
}

.stat-value {
  font-size:32px;
  font-weight:800;
  color:black;
}

.table-container {
  background:white;
  border-radius:var(--radius);
  box-shadow:var(--card-shadow);
  overflow:hidden;
}

.table-header {
  padding:20px 24px;
  background:#f8fafc;
  border-bottom:2px solid #e0e7ef;
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.table-header h3 {
  font-size:20px;
  color:var(--blue);
  font-weight:700;
  display:flex;
  align-items:center;
  gap:8px;
}

.showing-info {
  font-size:14px;
  color:#64748b;
}

table {
  width:100%;
  border-collapse:collapse;
}

table th, table td {
  padding:16px 20px;
  text-align:left;
  border-bottom:1px solid #f1f3f6;
}

table th {
  background-color:#f8fafc;
  font-weight:700;
  color:var(--blue);
  font-size:13px;
  text-transform:uppercase;
  letter-spacing:0.5px;
  cursor:pointer;
  user-select:none;
  position:relative;
}

table th:hover {
  background-color:#e3f2fd;
}

table th i {
  margin-left:4px;
  font-size:10px;
  opacity:0.5;
}

table th.active i {
  opacity:1;
}

table tbody tr {
  transition:all 0.15s;
}

table tbody tr:hover {
  background-color:#f8fafc;
}

.badge {
  display:inline-block;
  padding:6px 12px;
  border-radius:8px;
  font-size:12px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:0.3px;
}

.badge-checked {
  background:#e3f2fd;
  color:#1565c0;
}

.badge-repaired {
  background:#e8f5e9;
  color:#2e7d32;
}

.badge-damaged {
  background:#ffebee;
  color:#c62828;
}

.action-btns {
  display:flex;
  gap:8px;
}

.btn-delete {
  background:var(--danger);
  color:white;
  padding:8px 16px;
  border-radius:10px;
  text-decoration:none;
  font-weight:600;
  transition:all 0.2s;
  font-size:13px;
  display:inline-flex;
  align-items:center;
  gap:6px;
}

.btn-delete:hover {
  background:#d32f2f;
  transform:scale(1.05);
  box-shadow:0 4px 12px rgba(244,67,54,0.3);
}

.no-records {
  text-align:center;
  padding:60px 20px;
  color:#94a3b8;
  font-size:16px;
}

.no-records i {
  display:block;
  font-size:48px;
  margin-bottom:16px;
  color:#cbd5e1;
}

.pagination {
  display:flex;
  justify-content:center;
  align-items:center;
  gap:8px;
  padding:20px;
  background:#f8fafc;
  border-top:2px solid #e0e7ef;
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

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: linear-gradient(120deg, rgba(82, 114, 163, 0.6), rgba(0,0,0,.75));
    z-index: 9999;
    align-items: center;
    justify-content: center;
    animation: fadeIn .35s ease-in-out;
}

.modal-card {
    background: #fff;
    width: 450px;
    max-width: 95%;
    border-radius: 18px;
    padding: 30px;
    box-shadow: 0 25px 60px rgba(0,0,0,.35);
    animation: slideUp .35s ease;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 0;
    font-weight: 700;
    color: #0d47a1;
}

.modal-header button {
    background: #ff5252;
    border: none;
    color: #fff;
    padding: 5px 10px;
    border-radius: 8px;
    cursor: pointer;
}

.modal-card label {
    font-weight: 600;
    margin-top: 12px;
    display: block;
    font-size: 14px;
}

.modal-card input,
.modal-card select,
.modal-card textarea {
    width: 100%;
    padding: 10px 12px;
    margin-top: 6px;
    border-radius: 10px;
    border: 1px solid #ddd;
    outline: none;
    font-size: 14px;
    transition: 0.2s;
}

.modal-card input:focus,
.modal-card select:focus,
.modal-card textarea:focus {
    border-color: #0d47a1;
    box-shadow: 0 0 0 2px rgba(13,71,161,.12);
}

.modal-actions {
    margin-top: 25px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-save {
    background: linear-gradient(45deg, #1565c0, #1e88e5);
    border: none;
    color: white;
    padding: 10px 17px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
}

.btn-cancel {
    background: #f2f2f2;
    border: none;
    padding: 10px 17px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(40px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@media (max-width: 768px) {
  .content-wrap {
    margin-left:0;
    padding:16px;
  }
  
  .top-header {
    flex-direction:column;
    gap:16px;
    text-align:center;
  }
  
  .controls-row {
    flex-direction:column;
  }
  
  .search-box {
    width:100%;
  }
  
  table {
    font-size:12px;
  }
  
  table th,
  table td {
    padding:10px;
  }
}
</style>

<main class="content-wrap">
    <div class="top-header">
        <div class="title">
            <i class="fas fa-tools title-icon"></i>
            Equipment Maintenance Status
        </div>
        <div class="admin-area">
          <div class="greeting">Hello, <?= htmlspecialchars(strtoupper($role)) ?>!</div>
      <div class="avatar">AD</div>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div id="successMsg">
            <i class="fas fa-check-circle"></i>
            <?= $_SESSION['success'] ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">
                <i class="fas fa-clipboard-list"></i>
                Total Equipment
            </div>
            <div class="stat-value"><?= $total_records ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">
                <i class="fas fa-check-circle"></i>
                Checked
            </div>
            <div class="stat-value">
                <?php
                $checked = array_filter($records, fn($r) => $r['action'] === 'Checked');
                echo count($checked);
                ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">
                <i class="fas fa-wrench"></i>
                Repaired
            </div>
            <div class="stat-value">
                <?php
                $repaired = array_filter($records, fn($r) => $r['action'] === 'Repaired');
                echo count($repaired);
                ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">
                <i class="fas fa-exclamation-triangle"></i>
                Damaged
            </div>
            <div class="stat-value">
                <?php
                $damaged = array_filter($records, fn($r) => $r['action'] === 'Marked Damage');
                echo count($damaged);
                ?>
            </div>
        </div>
    </div>

    <div class="controls-section">
        <form method="GET">
            <div class="controls-row">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by equipment code, name, or remarks..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="filter-group">
                    <div class="filter-box">
                        <label>Status</label>
                        <select name="action">
                            <option value="">All Status</option>
                            <option value="Checked" <?= $filter_action === 'Checked' ? 'selected' : '' ?>>Checked</option>
                            <option value="Repaired" <?= $filter_action === 'Repaired' ? 'selected' : '' ?>>Repaired</option>
                            <option value="Marked Damage" <?= $filter_action === 'Marked Damage' ? 'selected' : '' ?>>Marked Damage</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if(!empty($search) || !empty($filter_action)): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-clear">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-success no-print" onclick="openMarkModal()">
                        <i class="fas fa-plus"></i> Mark Equipment
                    </button>
                    
                    <button type="button" class="btn btn-print no-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Preview
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-container print-area">
        <div class="table-header">
            <h3>
                <i class="fas fa-list"></i>
                Equipment Status List
            </h3>
            <div class="showing-info">
                Showing <?= $offset + 1 ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> equipment
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Equipment Code</th>
                    <th>Name</th>
                    <th>Action</th>
                    <th>Remarks</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($records) > 0): ?>
                    <?php foreach($records as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['code']) ?></strong></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td>
                                <?php
                                $badgeClass = 'badge-checked';
                                if($row['action'] === 'Repaired') $badgeClass = 'badge-repaired';
                                elseif($row['action'] === 'Marked Damage') $badgeClass = 'badge-damaged';
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($row['action']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['remarks']) ?></td>
                            <td class="no-print">
                                <div class="action-btns">
                                    <a href="?delete=<?= $row['id'] ?>&page=<?= $page ?>"
                                       class="btn-delete"
                                       onclick="return confirm('Are you sure you want to delete this record?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="no-records">
                            <i class="fas fa-clipboard"></i>
                            <strong>No records found.</strong><br>
                            <?php if(!empty($search) || !empty($filter_action)): ?>
                                Try adjusting your search or filter criteria.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if($total_pages > 1): ?>
        <div class="pagination no-print">
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
            <h3>Mark Equipment Status</h3>
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
}, 4000);

// Modal functions
function openMarkModal() {
    document.getElementById('markModal').style.display = 'flex';
}

function closeMarkModal() {
    document.getElementById('markModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('markModal').addEventListener('click', function(e) {
    if(e.target === this) closeMarkModal();
});

// Prevent form resubmission on refresh
if(window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>