<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

// DB connection
$db = new Database();
$conn = $db->getConnection();

// Current user role
$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);
?>

<link rel="stylesheet" href="/public/css/dashboard.css">
<style>
/* Dashboard Variables */
:root{
  --blue:#0d47a1;
  --light-gray:#efefef;
  --card-shadow:0 6px 14px rgba(15,23,42,0.12);
  --radius:14px;
}
.content-wrap{ margin-left:250px; padding:22px; max-width:1500px; margin-top:0px; }
.top-header{ margin-bottom:10px;background:var(--blue); color:white; border-radius:12px; padding:28px 32px; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--card-shadow); }
.top-header .title{ font-size:44px; font-weight:800; letter-spacing:-0.5px; }
.admin-area{ display:flex; align-items:center; gap:18px; }
.greeting{ font-size:18px; opacity:0.95; }
.avatar{ width:56px; height:56px; border-radius:50%; background:white; color:var(--blue); display:flex; justify-content:center; align-items:center; font-weight:700; font-size:18px; border:4px solid #cfe1ff; box-shadow:0 2px 6px rgba(0,0,0,0.15); }

/* KPI Cards */
.kpi-cards{ display:flex; gap:18px; margin-bottom:20px; flex-wrap:wrap; }
.kpi-card{ flex:1; min-width:180px; background:white; padding:20px; border-radius:var(--radius); box-shadow:var(--card-shadow); display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; }
.kpi-card h3{ font-size:32px; margin:0; color:var(--blue); }
.kpi-card p{ font-size:14px; color:#555; margin-top:4px; }

/* Tabs */
.tabs{ display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap; }
.tab-btn{ padding:10px 18px; border-radius:12px; border:none; cursor:pointer; font-weight:600; background:#f5f7fb; color:var(--blue); transition:0.2s; }
.tab-btn.active{ background:var(--blue); color:white; }
.tab-content{ background:white; padding:16px; border-radius:var(--radius); box-shadow:var(--card-shadow); }

/* Tables */
.report-table{ width:100%; border-collapse:collapse; margin-top:12px; font-size:14px; }
.report-table th, .report-table td{ padding:12px; border-bottom:1px solid #f1f3f6; text-align:left; }
.report-table th{ background:#f5f7fb; font-weight:700; color:var(--blue); }
.report-table td .status{ font-weight:600; padding:4px 10px; border-radius:8px; display:inline-block; font-size:13px; }
.status-good{ background:#c8e6c9; color:#2e7d32; }
.status-fair{ background:#fff9c4; color:#f9a825; }
.status-damaged{ background:#ffcdd2; color:#b71c1c; }

/* Chart placeholders */
.chart-container{ width:100%; height:300px; margin-top:20px; background:#f5f7fb; border-radius:var(--radius); display:flex; justify-content:center; align-items:center; color:#888; font-weight:600; }

/* Export Buttons */
.export-btn{ background:var(--blue); color:white; border:none; padding:8px 14px; border-radius:10px; font-weight:600; cursor:pointer; margin-right:6px; transition:0.2s; }
.export-btn:hover{ opacity:0.9; transform:scale(1.05); }

/* Date Filter */
.date-filter{ display:flex; gap:8px; align-items:center; margin-bottom:16px; }
.date-filter input[type="date"]{ padding:6px 10px; border-radius:8px; border:1px solid #ccc; }
.generate-btn{ background:#1e73ff; color:white; border:none; padding:6px 12px; border-radius:8px; cursor:pointer; font-weight:600; }
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
      <h3>120</h3>
      <p>Total Equipment</p>
    </div>
    <div class="kpi-card">
      <h3>98</h3>
      <p>Available</p>
    </div>
    <div class="kpi-card">
      <h3>22</h3>
      <p>Borrowed</p>
    </div>
    <div class="kpi-card">
      <h3>5</h3>
      <p>Damaged</p>
    </div>
    <div class="kpi-card">
      <h3>8</h3>
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
        <!-- PHP loop for inventory data -->
      </tbody>
    </table>
  </div>

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
        <!-- PHP loop for borrowings -->
      </tbody>
    </table>
  </div>

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
        <!-- PHP loop for condition -->
      </tbody>
    </table>
  </div>

  <div id="usage" class="tab-content" style="display:none;">
    <div class="chart-container">[Bar Chart Placeholder]</div>
    <div class="chart-container">[Pie Chart Placeholder]</div>
    <div class="chart-container">[Line Chart Placeholder]</div>
  </div>

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
        <!-- PHP loop for user activity -->
      </tbody>
    </table>
  </div>

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
        <!-- PHP loop for alerts -->
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
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tc => tc.style.display = 'none');
    document.getElementById(tab.dataset.tab).style.display = 'block';
  });
});
</script>
