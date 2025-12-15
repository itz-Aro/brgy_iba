<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
AuthMiddleware::protect(['admin', 'official']);

// DB connection
$db = new Database();
$conn = $db->getConnection();

// Counts
$pendingRequests = (int)$conn->query("SELECT COUNT(*) FROM requests WHERE status = 'Pending'")->fetchColumn();
$ongoingBorrowings = (int)$conn->query("SELECT COUNT(*) FROM borrowings WHERE status = 'Active'")->fetchColumn();
$itemsDue = (int)$conn->query("SELECT COUNT(*) FROM borrowings WHERE status = 'Active' AND expected_return_date <= NOW()")->fetchColumn();
// $damageReports = (int)$conn->query("SELECT COUNT(*) FROM damage_reports")->fetchColumn();

// Recent requests
$stmt = $conn->prepare("SELECT id, request_no, borrower_name, status, expected_return_date, created_at FROM requests ORDER BY created_at DESC LIMIT 7");
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent audit logs
$logStmt = $conn->prepare("SELECT id, user_id, action, resource_type, resource_id, details, ip_address, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 7");
$logStmt->execute();
$auditLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// Merge and sort activity feed
$activityFeed = [];
foreach ($activities as $r) $activityFeed[] = ['type'=>'request','created_at'=>$r['created_at'],'data'=>$r];
foreach ($auditLogs as $l) $activityFeed[] = ['type'=>'audit','created_at'=>$l['created_at'],'data'=>$l];
usort($activityFeed,function($a,$b){ return strtotime($b['created_at'])-strtotime($a['created_at']); });

// Chart data (last 7 days)
$chartStmt = $conn->prepare("SELECT DATE(date_borrowed) as d, COUNT(*) AS cnt FROM borrowings WHERE date_borrowed >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(date_borrowed) ORDER BY DATE(date_borrowed)");
$chartStmt->execute();
$chartRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = [];
$chartData = [];
$datesIndex = [];
for ($i=6;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-{$i} days"));
    $chartLabels[]=date('m-d',strtotime($d));
    $datesIndex[$d]=count($chartData);
    $chartData[]=0;
}
foreach($chartRows as $r){
    if(isset($datesIndex[$r['d']])) $chartData[$datesIndex[$r['d']]]=(int)$r['cnt'];
}

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

/* Content beside sidebar */
.content-wrap{ margin-left:250px; padding:22px; max-width:1500px; margin-top:0px; }

.top-header{ background:var(--blue); color:white; border-radius:12px; padding:28px 32px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 6px 14px rgba(0,0,0,0.12);}
.top-header .title{ font-size:44px; font-weight:800; letter-spacing:-0.5px; }
.admin-area{ display:flex; align-items:center; gap:18px; }
.greeting{ font-size:18px; opacity:0.95; }
.avatar{ width:56px; height:56px; border-radius:50%; background:white; color:var(--blue); display:flex; justify-content:center; align-items:center; font-weight:700; font-size:18px; border:4px solid #cfe1ff; box-shadow:0 2px 6px rgba(0,0,0,0.15); }

/* Success Message Styling */
#successMsg {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    padding: 16px 20px;
    border-radius: 12px;
    margin-top: 18px;
    margin-bottom: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 12px rgba(21, 87, 36, 0.2);
    animation: slideDown 0.3s ease-out;
}

#successMsg i {
    font-size: 24px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
    margin-top: 18px;
}

/* Stat Card Base */
.stat-card {
    background: white;
    border-radius: 14px;
    padding: 22px;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 160px;
}

.stat-card::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; background:var(--blue); }
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);
}

/* Card Color Variants */
.card-pending::before {
    background: linear-gradient(90deg, #073a6a, #0e4a88);
}

.card-ongoing::before {
    background: linear-gradient(90deg, #073a6a, #0e4a88);
}

.card-due::before {
    background: linear-gradient(90deg, #073a6a, #0e4a88);
}

.card-damage::before {
   background: linear-gradient(90deg, #073a6a, #0e4a88);
}

/* Stat Left Section */
.stat-left {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.stat-left h4 {
    font-size: 15px;
    font-weight: 700;
    color: #334155;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-left h4 i {
    font-size: 16px;
}

.card-pending h4 {
    color: #000000ff;
}

.card-ongoing h4 {
    color: #000000ff;
}

.card-due h4 {
    color: #000000ff;
}

.card-damage h4 {
     color: #000000ff;
}

.stat-number {
    font-size: 48px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
}

/* Stat Icon */
.stat-icon {
    font-size: 52px;
    position: absolute;
    right: 20px;
    top: 20px;
    opacity: 0.08;
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon {
    opacity: 0.15;
    transform: scale(1.1) rotate(5deg);
}

/* View Button */
.view-btn {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    color: #475569;
    border: 2px solid #e2e8f0;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    align-self: flex-start;
    margin-top: auto;
}

.view-btn:hover {
    transform: translateX(2px);
}

.card-pending .view-btn:hover {
    background: linear-gradient(135deg, #073a6a 0%, #0e4a88 100%);
    color: white;
    border-color: #073a6a;
}

.card-ongoing .view-btn:hover {
    background: linear-gradient(135deg, #2e0077 0%, #3b17bd 100%);
    color: white;
    border-color: #2e0077;
}

.card-due .view-btn:hover {
    background: linear-gradient(135deg, #1e73ff 0%, #3aa0ff 100%);
    color: white;
    border-color: #1e73ff;
}

.card-damage .view-btn:hover {
    background: linear-gradient(135deg, #b71c1c 0%, #d32f2f 100%);
    color: white;
    border-color: #b71c1c;
}

/* Main grid */
.main-grid{ display:grid; grid-template-columns:1fr 380px; gap:18px; margin-top:22px; align-items:start; }

/* Activity box */
.activity-box{ background:white; border-radius:12px; overflow:hidden; box-shadow:var(--card-shadow); display:flex; flex-direction:column; width: 148%;}
.activity-header{ background:var(--blue); color:white; padding:18px; font-weight:800; font-size:20px; border-radius:12px 12px 0 0; }
.activity-list{ list-style:none; padding:0; margin:0; height: 345px;}
.activity-item{ display:flex; gap:12px; align-items:center; padding:14px 18px; border-bottom:1px solid #f1f3f6; }
.activity-item:last-child{ border-bottom:none; }
.activity-avatar{ width:44px; height:44px; border-radius:8px; background:#f5f7fb; display:flex; justify-content:center; align-items:center; font-weight:700; color:#0d47a1; margin-right:6px; }
.activity-text{ flex:1; font-weight:600; }
.activity-meta{ text-align:right; min-width:140px; font-size:13px; color:#6b7787; }
.label-success{ color:#1b8b1b; font-weight:700; }
.label-danger{ color:#d32f2f; font-weight:700; }
.label-pending{ color:#f9a825; font-weight:700; }

/* PAGINATION STYLING */
.activity-footer {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding: 12px 0;
    background-color: #f5f7fb;
    border-top: 1px solid #e0e0e0;
    border-radius: 0 0 12px 12px;
}

.page-btn {
    background-color: white;
    color: #0d47a1;
    border: 1px solid #cfe1ff;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
}

.page-btn:hover {
    background-color: #41a10dff;
    color: white;
    transform: scale(1.05);
}

.page-btn.active {
    background-color: #0d47a1;
    color: white;
    border-color: #0d47a1;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .stat-number {
        font-size: 40px;
    }
    
    .stat-icon {
        font-size: 44px;
    }
}

@media(max-width:980px){ .main-grid{ grid-template-columns:1fr; } }
</style>

<main class="content-wrap">

  <div class="top-header">
    <div class="title">Dashboard</div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= htmlspecialchars(strtoupper($displayRole)) ?>!</div>
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

  <div class="stats-row">
    <div class="stat-card card-pending">
      <div class="stat-left">
        <h4>
          <i class="fas fa-clock"></i>
          Pending Request
        </h4>
        <div class="stat-number"><?= $pendingRequests ?></div>
      </div>
      <div class="stat-icon"></div>
      <!-- <button class="view-btn">View Details ▸</button> -->
    </div>

    <div class="stat-card card-ongoing">
      <div class="stat-left">
        <h4>
          <i class="fas fa-hand-holding"></i>
          Ongoing Borrowings
        </h4>
        <div class="stat-number"><?= $ongoingBorrowings ?></div>
      </div>
      <div class="stat-icon"></div>
      <!-- <button class="view-btn">View Details ▸</button> -->
    </div>

    <div class="stat-card card-due">
      <div class="stat-left">
        <h4>
          <i class="fas fa-calendar-times"></i>
          Items Due for Return
        </h4>
        <div class="stat-number"><?= $itemsDue ?></div>
      </div>
      <div class="stat-icon"></div>
      <!-- <button class="view-btn">View Details ▸</button> -->
    </div>

    <div class="stat-card card-damage">
      <div class="stat-left">
        <h4>
          <i class="fas fa-exclamation-triangle"></i>
          Total Damage Reports
        </h4>
        <div class="stat-number">4</div>
      </div>
      <div class="stat-icon"></div>
      <!-- <button class="view-btn">View Details ▸</button> --> 
    </div>
  </div>

  <div class="main-grid">

   <!-- Activity Overview -->
<div class="left-column">
  <div class="activity-box">
    <div class="activity-header">Activity Overview</div>
    <ul class="activity-list" id="activityList" style="max-height:420px; overflow-y:auto;">
      <?php if(!empty($activityFeed)): ?>
        <?php foreach($activityFeed as $item):
          $dateDisplay = date("h:i / m-d-y", strtotime($item['created_at']));
        ?>
        <li class="activity-item">
          <div class="activity-avatar"><?= ($item['type']==='request')?'R':'A' ?></div>
          <div class="activity-icon" aria-hidden>
            <?php
              if($item['type']==='request'){
                $st = $item['data']['status'];
                echo $st==='Approved' ? '✅' : ($st==='Pending' ? '❓' : ($st==='Declined' ? '❌' : '⏸'));
              } else { echo '📝'; }
            ?>
          </div>
          <div class="activity-text">
            <?php if($item['type']==='request'):
              $r = $item['data'];
              $msg = $r['status']==='Approved' ? 'Approved request #' . $r['request_no'] :
                     ($r['status']==='Pending' ? 'Sent borrow request #' . $r['request_no'] :
                     ($r['status']==='Declined' ? 'Request #' . $r['request_no'] . ' was declined' : $r['status'] . ' request #' . $r['request_no']));
              $label = $r['status']==='Approved' ? 'label-success' : ($r['status']==='Declined' ? 'label-danger' : 'label-pending');
            ?>
              <div class="<?= $label ?>"><?= htmlspecialchars($r['borrower_name']) ?> · <?= $msg ?></div>
              <div style="font-size:13px;color:#657085;margin-top:3px;">
                <?= $r['expected_return_date'] ? 'Return: ' . htmlspecialchars($r['expected_return_date']) : '' ?>
              </div>
            <?php else:
              $a = $item['data'];
            ?>
              <div class="label-info">
                User #<?= htmlspecialchars($a['user_id']) ?> · <?= htmlspecialchars($a['action']) ?> on <?= htmlspecialchars($a['resource_type']) ?> #<?= htmlspecialchars($a['resource_id']) ?>
              </div>
              <div style="font-size:13px;color:#657085;margin-top:3px;">
                <?= htmlspecialchars($a['details'] ?: '') ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="activity-meta"><?= $dateDisplay ?></div>
        </li>
        <?php endforeach; ?>
      <?php else: ?>
        <li style="padding:18px;">No recent activity.</li>
      <?php endif; ?>
    </ul>

    <div class="activity-footer">
    <button class="page-btn active" data-page="1">1</button>
    <button class="page-btn" data-page="2">2</button>
    <button class="page-btn" data-page="3">3</button>
    </div>

  </div>
</div>

  </div>
</main>

<script>
const activityList = document.getElementById('activityList');
const activityFooter = document.querySelector('.activity-footer');

function loadActivities(page=1){
    fetch(`/brgy_iba/fetch_activities.php?page=${page}`)
        .then(res => res.json())
        .then(data => {
            activityList.innerHTML = data.html;

            // Render pagination
            let paginationHTML = '';
            for(let i=1;i<=data.totalPages;i++){
                paginationHTML += `<button class="page-btn ${i===page?'active':''}" data-page="${i}">${i}</button>`;
            }
            activityFooter.innerHTML = paginationHTML;

            // Add click event to buttons
            document.querySelectorAll('.page-btn').forEach(btn=>{
                btn.addEventListener('click', ()=> loadActivities(parseInt(btn.dataset.page)));
            });
        })
        .catch(err=>console.error(err));
}

// Initial load
loadActivities();
</script>