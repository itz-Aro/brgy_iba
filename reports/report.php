<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);

// ----------------------------
// KPI Cards
// ----------------------------

// Total equipment
$totalEquipment = $conn->query("SELECT COUNT(*) FROM equipment")->fetchColumn();

// Available equipment
$availableEquipment = $conn->query("SELECT SUM(available_quantity) FROM equipment")->fetchColumn();

// Borrowed equipment
$borrowedEquipment = $conn->query("SELECT SUM(quantity) FROM borrowing_items bi
    JOIN borrowings b ON bi.borrowing_id = b.id
    WHERE b.status='Active'")->fetchColumn();

// Damaged equipment
$damagedEquipment = $conn->query("SELECT COUNT(*) FROM equipment WHERE `condition`='Damaged'")->fetchColumn();

// Pending requests
$pendingRequests = $conn->query("SELECT COUNT(*) FROM requests WHERE status='Pending'")->fetchColumn();
?>


<style>
/* (keep your previous styles as-is) */
/* dashboard.css */

/* Root variables */
:root{
  --blue:#0d47a1;
  --light-gray:#efefef;
  --card-shadow:0 6px 14px rgba(15,23,42,0.12);
  --radius:14px;
}

/* Main content */
.content-wrap{
  margin-left:250px;
  padding:22px;
  max-width:1500px;
  margin-top:0px;
  font-family: 'Segoe UI', sans-serif;
}

/* Top Header */
.top-header{
  margin-bottom:10px;
  background:var(--blue);
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

/* KPI Cards */
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
}
.kpi-card h3{ font-size:32px; margin:0; color:var(--blue); }
.kpi-card p{ font-size:14px; color:#555; margin-top:4px; }

/* Tabs */
.tabs{ display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap; }
.tab-btn{
  padding:10px 18px;
  border-radius:12px;
  border:none;
  cursor:pointer;
  font-weight:600;
  background:#f5f7fb;
  color:var(--blue);
  transition:0.2s;
}
.tab-btn.active{ background:var(--blue); color:white; }
.tab-content{
  background:white;
  padding:16px;
  border-radius:var(--radius);
  box-shadow:var(--card-shadow);
}

/* Tables */
.report-table{ width:100%; border-collapse:collapse; margin-top:12px; font-size:14px; }
.report-table th, .report-table td{ padding:12px; border-bottom:1px solid #f1f3f6; text-align:left; }
.report-table th{ background:#f5f7fb; font-weight:700; color:var(--blue); }
.report-table td .status{
  font-weight:600;
  padding:4px 10px;
  border-radius:8px;
  display:inline-block;
  font-size:13px;
}
.status-good{ background:#c8e6c9; color:#2e7d32; }
.status-fair{ background:#fff9c4; color:#f9a825; }
.status-damaged{ background:#ffcdd2; color:#b71c1c; }

/* Chart placeholders */
.chart-container{
  width:100%;
  height:300px;
  margin-top:20px;
  background:#f5f7fb;
  border-radius:var(--radius);
  display:flex;
  justify-content:center;
  align-items:center;
  color:#888;
  font-weight:600;
}

/* Export Buttons */
.export-btn{
  background:var(--blue);
  color:white;
  border:none;
  padding:8px 14px;
  border-radius:10px;
  font-weight:600;
  cursor:pointer;
  margin-right:6px;
  transition:0.2s;
}
.export-btn:hover{ opacity:0.9; transform:scale(1.05); }

/* Date Filter */
.date-filter{ display:flex; gap:8px; align-items:center; margin-bottom:16px; }
.date-filter input[type="date"]{ padding:6px 10px; border-radius:8px; border:1px solid #ccc; }
.generate-btn{
  background:#1e73ff;
  color:white;
  border:none;
  padding:6px 12px;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
}
.generate-btn:hover{ opacity:0.9; }

</style>

<main class="content-wrap">
  <div class="top-header">
    <div class="title">Reports</div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <!-- KPI Cards -->
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

  <!-- Date Filter -->
  <div class="date-filter">
    <label>From: <input type="date" name="from_date"></label>
    <label>To: <input type="date" name="to_date"></label>
    <button class="generate-btn">Generate Report</button>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn active" data-tab="inventory">Inventory</button>
    <button class="tab-btn" data-tab="borrowings">Borrowings</button>
    <button class="tab-btn" data-tab="condition">Condition</button>
    <button class="tab-btn" data-tab="usage">Usage Stats</button>
    <button class="tab-btn" data-tab="users">User Activity</button>
    <button class="tab-btn" data-tab="alerts">Alerts</button>
  </div>

  <!-- Tab Contents -->

  <!-- Inventory Tab -->
  <div id="inventory" class="tab-content">
    <button class="export-btn">Export PDF</button>
    <button class="export-btn">Export Excel</button>
    <table class="report-table">
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
        $stmt = $conn->query("SELECT * FROM equipment ORDER BY name ASC");
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
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Borrowings Tab -->
  <div id="borrowings" class="tab-content" style="display:none;">
    <button class="export-btn">Export PDF</button>
    <button class="export-btn">Export Excel</button>
    <table class="report-table">
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
        $stmt = $conn->query("SELECT b.id, b.borrowing_no, b.borrower_name, b.date_borrowed, b.expected_return_date, b.actual_return_date, b.status,
                                    GROUP_CONCAT(CONCAT(e.name,' (',bi.quantity,')')) AS items
                             FROM borrowings b
                             JOIN borrowing_items bi ON b.id = bi.borrowing_id
                             JOIN equipment e ON bi.equipment_id = e.id
                             GROUP BY b.id
                             ORDER BY b.date_borrowed DESC");
        while($b = $stmt->fetch(PDO::FETCH_ASSOC)):
        ?>
        <tr>
          <td><?= htmlspecialchars($b['borrowing_no']) ?></td>
          <td><?= htmlspecialchars($b['borrower_name']) ?></td>
          <td><?= htmlspecialchars($b['items']) ?></td>
          <td><?= /* sum quantities */ $conn->query("SELECT SUM(quantity) FROM borrowing_items WHERE borrowing_id=".$b['id'])->fetchColumn() ?></td>
          <td><?= $b['date_borrowed'] ?></td>
          <td><?= $b['expected_return_date'] ?></td>
          <td><?= $b['actual_return_date'] ?: '-' ?></td>
          <td><?= $b['status'] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Condition Tab -->
  <div id="condition" class="tab-content" style="display:none;">
    <table class="report-table">
      <thead>
        <tr>
          <th>Equipment</th>
          <th>Condition</th>
          <th>Last Maintenance</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $stmt = $conn->query("SELECT e.name AS equipment_name, e.`condition`, ml.performed_at, ml.remarks 
                              FROM equipment e 
                              LEFT JOIN maintenance_logs ml ON e.id = ml.equipment_id
                              ORDER BY e.name ASC");
        while($c = $stmt->fetch(PDO::FETCH_ASSOC)):
        ?>
        <tr>
          <td><?= htmlspecialchars($c['equipment_name']) ?></td>
          <td>
            <span class="status <?= strtolower($c['condition'])=='good'?'status-good':(strtolower($c['condition'])=='fair'?'status-fair':'status-damaged') ?>">
              <?= htmlspecialchars($c['condition']) ?>
            </span>
          </td>
          <td><?= $c['performed_at'] ?: '-' ?></td>
          <td><?= htmlspecialchars($c['remarks'] ?: '-') ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Usage Tab -->
  <div id="usage" class="tab-content" style="display:none;">
    <div class="chart-container">[Bar Chart Placeholder]</div>
    <div class="chart-container">[Pie Chart Placeholder]</div>
    <div class="chart-container">[Line Chart Placeholder]</div>
  </div>

  <!-- Users Tab -->
  <div id="users" class="tab-content" style="display:none;">
    <table class="report-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>Action</th>
          <th>Equipment</th>
          <th>Date & Time</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $stmt = $conn->query("SELECT a.*, u.username, r.name AS role, e.name AS equipment
                              FROM audit_logs a
                              LEFT JOIN users u ON a.user_id = u.id
                              LEFT JOIN roles r ON u.role_id = r.id
                              LEFT JOIN equipment e ON a.resource_type='equipment' AND a.resource_id=e.id
                              ORDER BY a.id DESC LIMIT 100");
        while($u = $stmt->fetch(PDO::FETCH_ASSOC)):
        ?>
        <tr>
          <td><?= htmlspecialchars($u['username'] ?: '-') ?></td>
          <td><?= htmlspecialchars($u['role'] ?: '-') ?></td>
          <td><?= htmlspecialchars($u['action']) ?></td>
          <td><?= htmlspecialchars($u['equipment'] ?: '-') ?></td>
          <td><?= $u['ip_address'].' | '.$u['created_at'] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Alerts Tab -->
  <div id="alerts" class="tab-content" style="display:none;">
    <table class="report-table">
      <thead>
        <tr>
          <th>Issue</th>
          <th>Equipment</th>
          <th>Details</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $stmt = $conn->query("SELECT ml.action AS issue, e.name AS equipment, ml.remarks AS details, ml.performed_at AS date
                              FROM maintenance_logs ml
                              JOIN equipment e ON ml.equipment_id=e.id
                              ORDER BY ml.performed_at DESC LIMIT 100");
        while($a = $stmt->fetch(PDO::FETCH_ASSOC)):
        ?>
        <tr>
          <td><?= htmlspecialchars($a['issue']) ?></td>
          <td><?= htmlspecialchars($a['equipment']) ?></td>
          <td><?= htmlspecialchars($a['details']) ?></td>
          <td><?= $a['date'] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</main>

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
</script>
