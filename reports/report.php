<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);

// Get date filters
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

// Build date filter condition
$dateCondition = '';
if ($fromDate && $toDate) {
    $dateCondition = " AND DATE(created_at) BETWEEN '$fromDate' AND '$toDate'";
}

// ----------------------------
// KPI Cards with date filtering
// ----------------------------
$totalEquipment = $conn->query("SELECT COUNT(*) FROM equipment WHERE 1=1 $dateCondition")->fetchColumn();
$availableEquipment = $conn->query("SELECT SUM(available_quantity) FROM equipment WHERE 1=1 $dateCondition")->fetchColumn();
$borrowedEquipment = $conn->query("SELECT SUM(quantity) FROM borrowing_items bi
    JOIN borrowings b ON bi.borrowing_id = b.id
    WHERE b.status='Active' " . ($fromDate && $toDate ? "AND DATE(b.date_borrowed) BETWEEN '$fromDate' AND '$toDate'" : ""))->fetchColumn();
$damagedEquipment = $conn->query("SELECT COUNT(*) FROM equipment WHERE `condition`='Damaged' " . $dateCondition)->fetchColumn();
$pendingRequests = $conn->query("SELECT COUNT(*) FROM requests WHERE status='Pending' " . $dateCondition)->fetchColumn();
?>

<style>
:root{
  --blue:#0d47a1;
  --light-blue:#1976d2;
  --dark-blue:#003c8f;
  --light-gray:#efefef;
  --card-shadow:0 6px 14px rgba(15,23,42,0.12);
  --radius:14px;
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
.top-header .title{ font-size:44px; font-weight:800; letter-spacing:-0.5px; }
.admin-area{ display:flex; align-items:center; gap:18px; }
.greeting{ font-size:18px; opacity:0.95; }
.avatar{
  width:56px; height:56px;
  border-radius:50%;
  background:white;
  color:var(--blue);
  display:flex;
  justify-content:center;
  align-items:center;
  font-weight:700; font-size:18px;
  border:4px solid #cfe1ff;
  box-shadow:0 2px 6px rgba(0,0,0,0.15);
}

.kpi-cards{ display:flex; gap:18px; margin-bottom:20px; flex-wrap:wrap; }
.kpi-card{
  flex:1;
  min-width:180px;
  background:white;
  padding:20px;
  border-radius:var(--radius);
  box-shadow:var(--card-shadow);
  display:flex; flex-direction:column;
  justify-content:center; align-items:center;
  text-align:center;
  transition:0.3s ease;
}
.kpi-card:hover{ transform:translateY(-5px); box-shadow:0 8px 20px rgba(15,23,42,0.18); }
.kpi-card h3{ font-size:32px; margin:0; color:var(--blue); font-weight:800; }
.kpi-card p{ font-size:14px; color:#555; margin-top:4px; font-weight:600; }

/* Enhanced Date Filter */
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

.tabs{ display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap; }
.tab-btn{
  padding:12px 20px;
  border-radius:12px;
  border:none;
  cursor:pointer;
  font-weight:600;
  background:#f5f7fb;
  color:var(--blue);
  transition:0.3s;
  font-size:14px;
}
.tab-btn:hover{ background:#e8ecf4; }
.tab-btn.active{ 
  background:linear-gradient(135deg, var(--blue) 0%, var(--light-blue) 100%); 
  color:white;
  box-shadow:0 4px 12px rgba(13,71,161,0.25);
}
.tab-content{
  background:white;
  padding:20px;
  border-radius:var(--radius);
  box-shadow:var(--card-shadow);
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
.search-box svg{ color:#888; }
.search-box input{
  border:none;
  background:transparent;
  outline:none;
  width:100%;
  font-size:14px;
  padding:4px 8px;
  font-family:'Segoe UI', sans-serif;
}
.action-buttons{ display:flex; gap:8px; }
.export-btn, .print-btn{
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
.export-btn:hover, .print-btn:hover{ 
  transform:scale(1.05);
  box-shadow:0 4px 12px rgba(13,71,161,0.3);
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
.status-good{ background:#c8e6c9; color:#2e7d32; }
.status-fair{ background:#fff9c4; color:#f57f17; }
.status-damaged{ background:#ffcdd2; color:#c62828; }

/* Print Styles */
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
.print-preview.active{ display:flex; justify-content:center; align-items:flex-start; }
.print-container{
  background:white;
  width:210mm;
  min-height:297mm;
  padding:20mm;
  box-shadow:0 0 40px rgba(0,0,0,0.5);
  position:relative;
  border-radius:8px;
}
.print-header{
  text-align:center;
  margin-bottom:30px;
  border-bottom:4px solid var(--blue);
  padding-bottom:20px;
}
.print-header h1{
  color:var(--blue);
  font-size:32px;
  margin:0 0 8px 0;
  font-weight:800;
  letter-spacing:-0.5px;
}
.print-header h2{
  color:#555;
  font-size:22px;
  margin:8px 0;
  font-weight:600;
}
.print-header .meta{
  color:#777;
  font-size:14px;
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
.print-table tr:nth-child(even){ background:#f9f9f9; }
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

@media print{
  body *{ visibility:hidden; }
  .print-container, .print-container *{ visibility:visible; }
  .print-container{ 
    position:absolute; 
    left:0; top:0; 
    width:100%;
    box-shadow:none;
    border-radius:0;
  }
  .print-close, .print-actions{ display:none; }
}

.no-data{
  text-align:center;
  padding:40px;
  color:#888;
  font-size:14px;
}
</style>

<main class="content-wrap">
  <div class="top-header">
    <div class="title">Reports</div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <div class="kpi-cards">
    <div class="kpi-card">
      <h3><?= $totalEquipment ?></h3>
      <p>Total Equipment</p>
    </div>
    <div class="kpi-card">
      <h3><?= $availableEquipment ?></h3>
      <p>Available</p>
    </div>
    <div class="kpi-card">
      <h3><?= $borrowedEquipment ?></h3>
      <p>Borrowed</p>
    </div>
    <div class="kpi-card">
      <h3><?= $damagedEquipment ?></h3>
      <p>Damaged</p>
    </div>
    <div class="kpi-card">
      <h3><?= $pendingRequests ?></h3>
      <p>Pending Requests</p>
    </div>
  </div>

  <!-- Enhanced Date Filter -->
  <div class="date-filter-card">
    <div class="date-filter-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      Date Range Filter
    </div>
    <form method="GET" action="" class="date-filter">
      <div class="date-input-group">
        <label>From Date</label>
        <input type="date" name="from_date" id="fromDate" value="<?= $fromDate ?>">
      </div>
      <div class="date-input-group">
        <label>To Date</label>
        <input type="date" name="to_date" id="toDate" value="<?= $toDate ?>">
      </div>
      <button type="submit" class="filter-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
        Apply Filter
      </button>
      <?php if($fromDate || $toDate): ?>
      <button type="button" class="clear-btn" onclick="window.location.href='?'">
        Clear Filter
      </button>
      <?php endif; ?>
    </form>
  </div>

  <div class="tabs">
    <button class="tab-btn active" data-tab="inventory">📦 Inventory Report</button>
    <button class="tab-btn" data-tab="borrowings">📋 Borrowing Report</button>
    <button class="tab-btn" data-tab="condition">🔧 Condition Report</button>
  </div>

  <!-- Inventory Tab -->
  <div id="inventory" class="tab-content">
    <div class="actions-bar">
      <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>
        <input type="text" placeholder="Search inventory..." onkeyup="searchTable('inventory')">
      </div>
      <div class="action-buttons">
        <button class="print-btn" onclick="printReport('inventory')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          Print Preview
        </button>
      </div>
    </div>
    <table class="report-table" id="inventoryTable">
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
          <th>Last Updated</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $stmt = $conn->query("SELECT * FROM equipment WHERE 1=1 $dateCondition ORDER BY name ASC");
        if($stmt->rowCount() > 0):
          while($eq = $stmt->fetch(PDO::FETCH_ASSOC)):
        ?>
        <tr>
          <td><img src="<?= $eq['photo'] ?: '/public/imgs/default.png' ?>" style="width:48px; height:48px; border-radius:8px; object-fit:cover;"></td>
          <td><?= htmlspecialchars($eq['code']) ?></td>
          <td><?= htmlspecialchars($eq['name']) ?></td>
          <td><?= htmlspecialchars($eq['category']) ?></td>
          <td><?= htmlspecialchars($eq['total_quantity']) ?></td>
          <td><?= htmlspecialchars($eq['available_quantity']) ?></td>
          <td>
            <span class="status <?= strtolower($eq['condition'])=='good'?'status-good':(strtolower($eq['condition'])=='fair'?'status-fair':'status-damaged') ?>">
              <?= htmlspecialchars($eq['condition']) ?>
            </span>
          </td>
          <td><?= htmlspecialchars($eq['location']) ?></td>
          <td><?= $eq['updated_at'] ?: $eq['created_at'] ?></td>
        </tr>
        <?php 
          endwhile;
        else:
        ?>
        <tr><td colspan="9" class="no-data">No equipment found for the selected date range</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Borrowings Tab -->
  <div id="borrowings" class="tab-content" style="display:none;">
    <div class="actions-bar">
      <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>
        <input type="text" placeholder="Search borrowings..." onkeyup="searchTable('borrowings')">
      </div>
      <div class="action-buttons">
        <button class="print-btn" onclick="printReport('borrowings')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          Print Preview
        </button>
      </div>
    </div>
    <table class="report-table" id="borrowingsTable">
      <thead>
        <tr>
          <th>Borrowing #</th>
          <th>Borrower</th>
          <th>Equipment</th>
          <th>Quantity</th>
          <th>Date Borrowed</th>
          <th>Expected Return</th>
          <th>Actual Return</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $borrowDateFilter = '';
        if($fromDate && $toDate) {
          $borrowDateFilter = "AND DATE(b.date_borrowed) BETWEEN '$fromDate' AND '$toDate'";
        }
        $stmt = $conn->query("SELECT b.id, b.borrowing_no, b.borrower_name, b.date_borrowed, b.expected_return_date, b.actual_return_date, b.status,
                                    GROUP_CONCAT(CONCAT(e.name,' (',bi.quantity,')') SEPARATOR ', ') AS items
                             FROM borrowings b
                             JOIN borrowing_items bi ON b.id = bi.borrowing_id
                             JOIN equipment e ON bi.equipment_id = e.id
                             WHERE 1=1 $borrowDateFilter
                             GROUP BY b.id
                             ORDER BY b.date_borrowed DESC");
        if($stmt->rowCount() > 0):
          while($b = $stmt->fetch(PDO::FETCH_ASSOC)):
        ?>
        <tr>
          <td><?= htmlspecialchars($b['borrowing_no']) ?></td>
          <td><?= htmlspecialchars($b['borrower_name']) ?></td>
          <td><?= htmlspecialchars($b['items']) ?></td>
          <td><?= $conn->query("SELECT SUM(quantity) FROM borrowing_items WHERE borrowing_id=".$b['id'])->fetchColumn() ?></td>
          <td><?= $b['date_borrowed'] ?></td>
          <td><?= $b['expected_return_date'] ?></td>
          <td><?= $b['actual_return_date'] ?: '-' ?></td>
          <td><span class="status status-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span></td>
        </tr>
        <?php 
          endwhile;
        else:
        ?>
        <tr><td colspan="8" class="no-data">No borrowings found for the selected date range</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Condition Tab -->
  <div id="condition" class="tab-content" style="display:none;">
    <div class="actions-bar">
      <div class="search-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>
        <input type="text" placeholder="Search conditions..." onkeyup="searchTable('condition')">
      </div>
      <div class="action-buttons">
        <button class="print-btn" onclick="printReport('condition')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
          </svg>
          Print Preview
        </button>
      </div>
    </div>
    <table class="report-table" id="conditionTable">
      <thead>
        <tr>
          <th>Equipment Code</th>
          <th>Equipment Name</th>
          <th>Condition</th>
          <th>Last Maintenance</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $stmt = $conn->query("SELECT e.code, e.name AS equipment_name, e.`condition`, ml.performed_at, ml.remarks 
                              FROM equipment e 
                              LEFT JOIN maintenance_logs ml ON e.id = ml.equipment_id
                              WHERE 1=1 $dateCondition
                              ORDER BY e.name ASC");
        if($stmt->rowCount() > 0):
          while($c = $stmt->fetch(PDO::FETCH_ASSOC)):
        ?>
        <tr>
          <td><?= htmlspecialchars($c['code']) ?></td>
          <td><?= htmlspecialchars($c['equipment_name']) ?></td>
          <td>
            <span class="status <?= strtolower($c['condition'])=='good'?'status-good':(strtolower($c['condition'])=='fair'?'status-fair':'status-damaged') ?>">
              <?= htmlspecialchars($c['condition']) ?>
            </span>
          </td>
          <td><?= $c['performed_at'] ?: '-' ?></td>
          <td><?= htmlspecialchars($c['remarks'] ?: '-') ?></td>
        </tr>
        <?php 
          endwhile;
        else:
        ?>
        <tr><td colspan="5" class="no-data">No equipment found for the selected date range</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<!-- Print Preview Modal -->
<div class="print-preview" id="printPreview">
  <div style="width:210mm;">
    <div class="print-actions">
      <button class="btn-print-now" onclick="window.print()">🖨️ Print Now</button>
      <button class="print-close" onclick="closePrintPreview()">✕ Close</button>
    </div>
    <div class="print-container" id="printContainer">
      <!-- Content will be dynamically inserted here -->
    </div>
  </div>
</div>

<script>
// Tab switching
const tabs = document.querySelectorAll('.tab-btn');
tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    tab.classList.add('active');
    document.querySelectorAll('.tab-content').forEach(tc => tc.style.display='none');
    document.getElementById(tab.dataset.tab).style.display='block';
  });
});

// Search functionality
function searchTable(tabName) {
  const input = event.target.value.toLowerCase();
  const table = document.getElementById(tabName + 'Table');
  const rows = table.getElementsByTagName('tr');
  
  for (let i = 1; i < rows.length; i++) {
    const row = rows[i];
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(input) ? '' : 'none';
  }
}

// Print Report
function printReport(reportType) {
  const reportTitles = {
    'inventory': 'Equipment Inventory Report',
    'borrowings': 'Equipment Borrowing Report',
    'condition': 'Equipment Condition Report'
  };
  
  const table = document.getElementById(reportType + 'Table');
  const clonedTable = table.cloneNode(true);
  clonedTable.classList.remove('report-table');
  clonedTable.classList.add('print-table');
  
  // Remove photo column for inventory
  if(reportType === 'inventory') {
    const rows = clonedTable.rows;
    for(let i = 0; i < rows.length; i++) {
      rows[i].deleteCell(0);
    }
  }
  
  const today = new Date().toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'});
  const dateRange = '<?= $fromDate && $toDate ? "From: ".date("F d, Y", strtotime($fromDate))." To: ".date("F d, Y", strtotime($toDate)) : "All Dates" ?>';
  
  const printContent = `
    <button class="print-close" onclick="closePrintPreview()">✕ Close</button>
    <div class="print-header">
      <h1>Equipment’s Request Record and Monitoring System Report</h1>
      <h2>${reportTitles[reportType]}</h2>
      <div class="meta">
        <div class="meta-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          ${dateRange}
        </div>
        <div class="meta-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          Generated by: <?= strtoupper($displayRole) ?>
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
      </div>
      <div style="text-align:center; margin-top:30px; color:#888; font-size:11px; border-top:1px solid #ddd; padding-top:15px;">
        <strong>This is an official document generated by the Equipment Management System</strong><br>
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
</script>