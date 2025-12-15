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
        $stmt->execute([
            $action,
            $remarks,
            $performed_by,
            $equipment_id
        ]);

    } else {
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
$sort_by = $_GET['sort_by'] ?? 'code';
$sort_order = $_GET['sort_order'] ?? 'ASC';

// Pagination
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

$actionsStmt = $conn->prepare("SELECT DISTINCT action FROM maintenance_logs ORDER BY action");
$actionsStmt->execute();
$actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

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
    WHERE ml.action = 'Marked Damaged'
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --blue:#0d47a1;
  --light-blue:#1976d2;
  --dark-blue:#003c8f;
  --light-gray:#efefef;
  --card-shadow:0 6px 14px rgba(15,23,42,0.12);
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
  font-family: 'Segoe UI', sans-serif;
  background-color: var(--light-gray);
  color: #2c3e50;
}

.content-wrap{
  margin-left:250px;
  padding:22px;
  max-width:1500px;
  margin-top:0px;
  font-family: 'Segoe UI', sans-serif;
}

.top-header{
  margin-bottom:10px;
  background:linear-gradient(135deg, var(--blue) 0%, var(--light-blue) 100%);
  color:white;
  border-radius:12px;
  padding:28px 32px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  box-shadow:var(--card-shadow);
}

.top-header .title{ 
  font-size:44px; 
  font-weight:800; 
  letter-spacing:-0.5px;
  display:flex;
  align-items:center;
  gap:12px;
}

.title-icon {
  font-size: 38px;
}

.admin-area{ 
  display:flex; 
  align-items:center; 
  gap:18px; 
}

.greeting{ 
  font-size:18px; 
  opacity:0.95;
  font-weight:500;
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
  transition: transform 0.2s;
}

.avatar:hover {
  transform: scale(1.05);
}

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

.kpi-cards{ 
  display:flex; 
  gap:18px; 
  margin-bottom:20px; 
  flex-wrap:wrap; 
}

.kpi-card{
  flex:1;
  min-width:180px;
  background:white;
  padding:20px;
  border-radius:var(--radius);
  box-shadow:var(--card-shadow);
  display:flex; 
  flex-direction:column;
  justify-content:center; 
  align-items:center;
  text-align:center;
  transition:0.3s ease;
}

.kpi-card:hover{ 
  transform:translateY(-5px); 
  box-shadow:0 8px 20px rgba(15,23,42,0.18); 
}

.kpi-card h3{ 
  font-size:32px; 
  margin:0; 
  color:var(--blue); 
  font-weight:800; 
}

.kpi-card p{ 
  font-size:14px; 
  color:#555; 
  margin-top:4px; 
  font-weight:600; 
}

.kpi-card i {
  font-size:24px;
  color:var(--blue);
  margin-bottom:8px;
}

/* Date Filter */
.date-filter-card{
  background:white;
  padding:20px;
  border-radius:var(--radius);
  box-shadow:var(--card-shadow);
  margin-bottom:20px;
}

.date-filter-title{
  font-size:16px;
  font-weight:700;
  color:var(--blue);
  margin-bottom:15px;
  display:flex;
  align-items:center;
  gap:8px;
}

.date-filter{ 
  display:flex; 
  gap:12px; 
  align-items:center; 
  flex-wrap:wrap; 
}

.date-input-group{
  display:flex;
  flex-direction:column;
  gap:6px;
}

.date-input-group label{
  font-size:13px;
  font-weight:600;
  color:#555;
}

.date-filter input[type="date"]{ 
  padding:10px 14px; 
  border-radius:10px; 
  border:2px solid #e0e0e0;
  font-size:14px;
  font-family:'Segoe UI', sans-serif;
  transition:0.2s;
  min-width:160px;
}

.date-filter input[type="date"]:focus{
  outline:none;
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(13,71,161,0.1);
}

.filter-btn{
  background:linear-gradient(135deg, var(--blue) 0%, var(--light-blue) 100%);
  color:white;
  border:none;
  padding:10px 24px;
  border-radius:10px;
  cursor:pointer;
  font-weight:600;
  font-size:14px;
  transition:0.2s;
  display:flex;
  align-items:center;
  gap:8px;
  margin-top:auto;
}

.filter-btn:hover{ 
  transform:scale(1.05); 
  box-shadow:0 4px 12px rgba(13,71,161,0.3);
}

.clear-btn{
  background:#e0e0e0;
  color:#555;
  border:none;
  padding:10px 20px;
  border-radius:10px;
  cursor:pointer;
  font-weight:600;
  font-size:14px;
  transition:0.2s;
  margin-top:auto;
}

.clear-btn:hover{
  background:#d0d0d0;
}

/* Search and Actions Bar */
.actions-bar{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:20px;
  gap:12px;
  flex-wrap:wrap;
}

.search-box{
  display:flex;
  align-items:center;
  background:#f5f7fb;
  border:2px solid #e8ecf4;
  border-radius:12px;
  padding:10px 16px;
  flex:1;
  min-width:250px;
  max-width:400px;
  transition:0.2s;
}

.search-box:focus-within{
  border-color:var(--blue);
  background:white;
  box-shadow:0 0 0 3px rgba(13,71,161,0.1);
}

.search-box svg{ 
  color:#888; 
}

.search-box input{
  border:none;
  background:transparent;
  outline:none;
  width:100%;
  font-size:14px;
  padding:4px 8px;
  font-family:'Segoe UI', sans-serif;
}

.action-buttons{ 
  display:flex; 
  gap:8px; 
  flex-wrap:wrap;
}

.btn-primary, .btn-success, .btn-print, .btn-clear {
  background:linear-gradient(135deg, var(--blue) 0%, var(--light-blue) 100%);
  color:white;
  border:none;
  padding:10px 20px;
  border-radius:10px;
  font-weight:600;
  cursor:pointer;
  transition:0.3s;
  display:flex;
  align-items:center;
  gap:8px;
  font-size:14px;
}

.btn-success {
  background:linear-gradient(135deg, var(--success) 0%, #66bb6a 100%);
}

.btn-print {
  background:#fff3e0;
  color:#e65100;
  border:2px solid #ff9800;
}

.btn-clear {
  background:#e0e0e0;
  color:#555;
}

.btn-primary:hover, .btn-success:hover, .btn-clear:hover { 
  transform:scale(1.05);
  box-shadow:0 4px 12px rgba(13,71,161,0.3);
}

.btn-print:hover {
  background:#ff9800;
  color:white;
}

.report-table{ 
  width:100%; 
  border-collapse:collapse; 
  margin-top:12px; 
  font-size:14px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05);
  border-radius:10px;
  overflow:hidden;
}

.report-table th, .report-table td{ 
  padding:14px; 
  border-bottom:1px solid #f1f3f6; 
  text-align:left; 
}

.report-table th{ 
  background:linear-gradient(135deg, var(--blue) 0%, var(--light-blue) 100%);
  font-weight:700; 
  color:white;
  text-transform:uppercase;
  font-size:13px;
  letter-spacing:0.5px;
}

.report-table tbody tr{
  transition:0.2s;
}

.report-table tbody tr:hover{
  background:#f8f9ff;
  transform:scale(1.01);
}

.report-table td .status{
  font-weight:600;
  padding:6px 12px;
  border-radius:8px;
  display:inline-block;
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:0.5px;
}

.status-checked{ 
  background:#c8e6c9; 
  color:#2e7d32; 
}

.status-repaired{ 
  background:#c8e6c9; 
  color:#2e7d32; 
}

.status-marked{ 
  background:#ffcdd2; 
  color:#c62828; 
}

.status-none {
  background:#f5f5f5;
  color:#757575;
}

.no-data{
  text-align:center;
  padding:40px;
  color:#888;
  font-size:14px;
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
  border:none;
  cursor:pointer;
}

.btn-delete:hover {
  background:#d32f2f;
  transform:scale(1.05);
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

.modal-overlay.active {
    display: flex;
}

.modal-card{
    background: #fff;
    width: 450px;
    max-width: 95%;
    border-radius: 18px;
    padding: 30px;
    box-shadow: 0 25px 60px rgba(0,0,0,.35);
    animation: slideUp .35s ease;
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
    font-family:'Segoe UI', sans-serif;
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

/* Print Preview Styles */
.print-preview{
  display:none;
  position:fixed;
  top:0; left:0; right:0; bottom:0;
  background:rgba(0,0,0,0.85);
  z-index:9999;
  overflow:auto;
  padding:20px;
  backdrop-filter:blur(4px);
}

.print-preview.active{ 
  display:flex; 
  justify-content:center; 
  align-items:flex-start; 
}

.print-container{
  background:white;
  width:210mm;
  min-height:297mm;
  padding:20mm;
  box-shadow:0 0 40px rgba(0,0,0,0.5);
  position:relative;
  border-radius:8px;
  margin-top:20px;
}

.print-header{
  text-align:center;
  margin-bottom:30px;
  border-bottom:4px solid var(--blue);
  padding-bottom:20px;
}

.print-header h1{
  color:var(--blue);
  font-size:28px;
  margin:0 0 8px 0;
  font-weight:800;
  letter-spacing:-0.5px;
}

.print-header h2{
  color:#555;
  font-size:18px;
  margin:8px 0;
  font-weight:600;
}

.print-header .meta{
  color:#777;
  font-size:12px;
  margin-top:12px;
  display:flex;
  justify-content:center;
  gap:20px;
  flex-wrap:wrap;
}

.meta-item{
  display:flex;
  align-items:center;
  gap:6px;
}

.print-table{
  width:100%;
  border-collapse:collapse;
  margin:20px 0;
  font-size:11px;
}

.print-table th, .print-table td{
  border:1px solid #ddd;
  padding:10px;
  text-align:left;
}

.print-table th{
  background:var(--blue);
  color:white;
  font-weight:700;
  text-transform:uppercase;
  font-size:10px;
  letter-spacing:0.5px;
}

.print-table tr:nth-child(even){ 
  background:#f9f9f9; 
}

.print-footer{
  margin-top:60px;
  padding-top:20px;
  border-top:2px solid #ddd;
}

.signature-section{
  display:flex;
  justify-content:space-between;
  margin-top:80px;
}

.signature-box{
  text-align:center;
  width:45%;
}

.signature-line{
  border-top:2px solid #333;
  margin-top:60px;
  padding-top:10px;
  font-weight:700;
  font-size:13px;
}

.signature-label{
  color:#888;
  font-size:11px;
  margin-top:4px;
}

.print-close{
  position:absolute;
  top:15px;
  right:15px;
  background:#e53935;
  color:white;
  border:none;
  padding:10px 20px;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
  transition:0.2s;
}

.print-close:hover{
  background:#c62828;
  transform:scale(1.05);
}

.print-actions{
  display:flex;
  gap:12px;
  margin-bottom:20px;
  justify-content:center;
}

.print-actions button{
  padding:12px 28px;
  border:none;
  border-radius:10px;
  font-weight:600;
  cursor:pointer;
  font-size:14px;
  transition:0.2s;
}

.btn-print-now{
  background:#2e7d32;
  color:white;
}

.btn-print-now:hover{
  background:#1b5e20;
  transform:scale(1.05);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(40px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@media print {
  body *{ visibility:hidden; }
  .print-container, .print-container *{ visibility:visible; }
  .print-container{ 
    position:absolute; 
    left:0; top:0; 
    width:100%;
    box-shadow:none;
    border-radius:0;
    margin:0;
    padding:20mm;
  }
  .print-close, .print-actions{ display:none; }
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
  
  .actions-bar {
    flex-direction:column;
  }
  
  .search-box {
    width:100%;
    max-width:100%;
  }
  
  .action-buttons {
    width:100%;
  }
  
  .report-table {
    font-size:12px;
  }
  
  .report-table th,
  .report-table td {
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
      <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
      <div class="avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
    </div>
  </div>

  <?php if(isset($_SESSION['success'])): ?>
    <div id="successMsg">
      <i class="fas fa-check-circle"></i>
      <?= $_SESSION['success'] ?>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <!-- KPI Cards with Icons -->
  <div class="kpi-cards">
    <div class="kpi-card">
      <i class="fas fa-boxes"></i>
      <h3><?= $totalEquipment ?></h3>
      <p>Total Equipment</p>
    </div>
    <div class="kpi-card">
      <i class="fas fa-check-circle"></i>
      <h3><?= $statsChecked ?></h3>
      <p>Checked</p>
    </div>
    <div class="kpi-card">
      <i class="fas fa-wrench"></i>
      <h3><?= $statsRepaired ?></h3>
      <p>Repaired</p>
    </div>
    <div class="kpi-card">
      <i class="fas fa-exclamation-triangle"></i>
      <h3><?= $statsDamaged ?></h3>
      <p>Damaged</p>
    </div>
  </div>

  <!-- Date Filter -->
  <div class="date-filter-card">
    <div class="date-filter-title">
      <i class="fas fa-calendar-alt"></i>
      Date Range Filter
    </div>
    <form method="GET" class="date-filter">
      <div class="date-input-group">
        <label>From Date</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
      </div>
      <div class="date-input-group">
        <label>To Date</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
      </div>
      <button type="submit" class="filter-btn">
        <i class="fas fa-filter"></i>
        Apply Filter
      </button>
      <?php if($date_from || $date_to): ?>
      <button type="button" class="clear-btn" onclick="window.location.href='?'">
        Clear Filter
      </button>
      <?php endif; ?>
    </form>
  </div>

  <!-- Search and Actions -->
  <div class="actions-bar">
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; flex:1;">
      <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>
        <input type="text" name="search" placeholder="Search by code, name, or remarks..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="action" style="padding:10px 14px; border-radius:10px; border:2px solid #e0e0e0; min-width:140px;">
        <option value="">All Status</option>
        <option value="Checked" <?= $filter_action === 'Checked' ? 'selected' : '' ?>>Checked</option>
        <option value="Repaired" <?= $filter_action === 'Repaired' ? 'selected' : '' ?>>Repaired</option>
        <option value="Marked Damage" <?= $filter_action === 'Marked Damage' ? 'selected' : '' ?>>Marked Damage</option>
      </select>
      <button type="submit" class="btn-primary">
        <i class="fas fa-search"></i> Search
      </button>
      <?php if(!empty($search) || !empty($filter_action)): ?>
      <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-clear">
        <i class="fas fa-times"></i> Clear
      </a>
      <?php endif; ?>
    </form>
    <div class="action-buttons">
      <button type="button" class="btn-success no-print" onclick="openMarkModal()">
        <i class="fas fa-plus"></i> Mark Equipment
      </button>
      <button type="button" class="btn-print no-print" onclick="openPrintPreview()">
        <i class="fas fa-print"></i> Print Preview
      </button>
    </div>
  </div>

  <!-- Equipment Table -->
  <div style="background:white; padding:20px; border-radius:var(--radius); box-shadow:var(--card-shadow);">
    <table class="report-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Equipment Name</th>
          <th>Code</th>
          <th>Latest Status</th>
          <th>Remarks</th>
          <th>Last Updated</th>
          <th class="no-print">Actions</th>
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
                elseif($row['action'] === 'Marked Damage') $statusClass = 'status-marked';
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
            <td class="no-print">
              <div class="action-btns">
                <?php 
                $equipIds = explode(',', $row['equipment_ids']);
                if(count($equipIds) > 0 && $row['action']): 
                ?>
                  <button class="btn-delete" onclick="deleteGroup('<?= htmlspecialchars($row['equipment_name']) ?>', '<?= implode(',', $equipIds) ?>')">
                    <i class="fas fa-trash"></i> Delete
                  </button>
                <?php else: ?>
                  <span style="color:#94a3b8; font-size:13px;">No record</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
        <tr>
          <td colspan="7" class="no-data">
            <i class="fas fa-inbox"></i>
            <strong>No equipment found.</strong><br>
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

<!-- Print Preview Modal -->
<div class="print-preview" id="printPreview">
  <div style="width:210mm;">
    <div class="print-actions">
      <button class="btn-print-now" onclick="window.print()">
        <i class="fas fa-print"></i> Print Now
      </button>
      <button class="print-close" onclick="closePrintPreview()">✕ Close</button>
    </div>
    <div class="print-container" id="printContainer">
      <!-- Content will be dynamically inserted here -->
    </div>
  </div>
</div>

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
  document.getElementById('markModal').classList.add('active');
}

function closeMarkModal() {
  document.getElementById('markModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('markModal').addEventListener('click', function(e) {
  if(e.target === this) closeMarkModal();
});

// Print Preview Function
function openPrintPreview() {
  const table = document.querySelector('.report-table');
  const clonedTable = table.cloneNode(true);
  clonedTable.classList.remove('report-table');
  clonedTable.classList.add('print-table');
  
  // Remove actions column
  const headerCells = clonedTable.querySelectorAll('th');
  const lastHeaderIndex = headerCells.length - 1;
  headerCells[lastHeaderIndex].remove();
  
  const rows = clonedTable.querySelectorAll('tbody tr');
  rows.forEach(row => {
    const cells = row.querySelectorAll('td');
    if(cells.length > 0) {
      cells[cells.length - 1].remove();
    }
  });
  
  const today = new Date().toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'});
  
  const printContent = `
    <button class="print-close" onclick="closePrintPreview()">✕ Close</button>
    <div class="print-header">
      <h1>Equipment Management System</h1>
      <h2>Equipment Maintenance Status Report</h2>
      <div class="meta">
        <div class="meta-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          <strong>Date:</strong> ${today}
        </div>
        <div class="meta-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <strong>Generated by:</strong> <?= strtoupper($displayRole) ?>
        </div>
      </div>
    </div>
    
    ${clonedTable.outerHTML}
    
    <div class="print-footer">
      <div class="signature-section">
        <div class="signature-box">
          <div class="signature-line">
            Prepared By
            <div class="signature-label"><?= strtoupper($displayRole) ?></div>
          </div>
        </div>
        <div class="signature-box">
          <div class="signature-line">
            Approved By
            <div class="signature-label">System Administrator</div>
          </div>
        </div>
      </div>
      <div style="text-align:center; margin-top:40px; color:#888; font-size:11px; border-top:1px solid #ddd; padding-top:15px;">
        <strong>This is an official document generated by the Equipment Management System</strong><br>
        Confidential - For Internal Use Only<br>
        Document generated on: ${new Date().toLocaleString('en-US', {
          year: 'numeric', 
          month: 'long', 
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          hour12: true
        })}
      </div>
    </div>
  `;
  
  document.getElementById('printContainer').innerHTML = printContent;
  document.getElementById('printPreview').classList.add('active');
}

function closePrintPreview() {
  document.getElementById('printPreview').classList.remove('active');
}

// Close print preview on ESC key
document.addEventListener('keydown', function(e) {
  if(e.key === 'Escape') {
    closePrintPreview();
  }
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