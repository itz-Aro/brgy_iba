<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// DB connection
$db = new Database();
$conn = $db->getConnection();
AuthMiddleware::protect(['admin', 'officials']);

// Counts
$pendingRequests = (int)$conn->query("SELECT COUNT(*) FROM requests WHERE status = 'Pending'")->fetchColumn();
$ongoingBorrowings = (int)$conn->query("SELECT COUNT(*) FROM borrowings WHERE status = 'Active'")->fetchColumn();
$itemsDue = (int)$conn->query("SELECT COUNT(*) FROM borrowings WHERE status = 'Active' AND expected_return_date <= NOW()")->fetchColumn();
$damagedEquipment = $conn->query("SELECT COUNT(*) FROM equipment WHERE `condition`='Damaged'")->fetchColumn();

// Recent requests (for feed)
$stmt = $conn->prepare("SELECT id, request_no, borrower_name, status, expected_return_date, created_at FROM requests ORDER BY created_at DESC LIMIT 7");
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent audit logs (for feed)
$logStmt = $conn->prepare("SELECT id, user_id, action, resource_type, resource_id, details, ip_address, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 7");
$logStmt->execute();
$auditLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// Merge and sort activity feed (requests + audit logs)
$activityFeed = [];
foreach ($activities as $r) $activityFeed[] = ['type'=>'request','created_at'=>$r['created_at'],'data'=>$r];
foreach ($auditLogs as $l) $activityFeed[] = ['type'=>'audit','created_at'=>$l['created_at'],'data'=>$l];
usort($activityFeed, function($a,$b){ return strtotime($b['created_at']) - strtotime($a['created_at']); });

// Fetch top 7 most borrowed equipment (sum quantities)
$topItemsStmt = $conn->prepare("
    SELECT e.id, e.name, COALESCE(SUM(bi.quantity),0) AS total_borrowed
    FROM equipment e
    LEFT JOIN borrowing_items bi 
        ON bi.equipment_id = e.id
    LEFT JOIN borrowings b 
        ON bi.borrowing_id = b.id 
       AND b.status IS NOT NULL
    GROUP BY e.id, e.name
    ORDER BY total_borrowed DESC
    LIMIT 15
");
$topItemsStmt->execute();
$topItems = $topItemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare labels and data for Chart.js
$chartLabels = [];
$chartData   = [];
foreach ($topItems as $it) {
    $chartLabels[] = $it['name'];
    $chartData[]   = (int)$it['total_borrowed'];
}

// Ensure arrays exist to prevent JS errors
$chartLabels = $chartLabels ?: [];
$chartData   = $chartData ?: [];

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role, ENT_QUOTES);
?>

<link rel="stylesheet" href="/public/css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>

<style>
/* ===== copied visual language from admin_returns.php ===== */
* { box-sizing: border-box; }
body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; margin: 0; }

.content-wrap { 
    margin-left: 250px; 
    padding: 32px; 
    max-width: 1600px;
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Top header */
.top-header { 
    margin-bottom: 32px; 
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white; 
    border-radius: 20px; 
    padding: 36px 40px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    box-shadow: 0 10px 40px rgba(99, 102, 241, 0.3);
    position: relative;
    overflow: hidden;
}
.top-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
}
.top-header .title { 
    font-size: 2rem; 
    font-weight: 800; 
    display: flex;
    align-items: center;
    gap: 16px;
    position: relative;
    z-index: 1;
}
.top-header .title i { font-size: 2.2rem; opacity: 0.95; }
.admin-area { display:flex; align-items:center; gap:20px; position:relative; z-index:1; }
.greeting { font-size: 1rem; font-weight: 600; opacity: 0.95; }
.avatar { width: 60px; height: 60px; border-radius: 50%; background: white; color: #6366f1; display:flex; justify-content:center; align-items:center; font-weight:800; font-size:1.25rem; border:4px solid rgba(255,255,255,0.3); box-shadow:0 4px 12px rgba(0,0,0,0.15); }

/* Stats */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 2px solid #f1f5f9;
    transition: all 0.3s;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.12); border-color:#6366f1; }
.stat-left { display:flex; flex-direction:column; gap:6px; }
.stat-left h4 { margin:0; font-size:14px; font-weight:700; color:#334155; }
.stat-number { font-size:34px; font-weight:800; color:#0f172a; }

/* Activity + layout */
.main-grid { display:grid; grid-template-columns: 1fr 380px; gap:18px; align-items:start; }
.activity-box { background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08); display:flex; flex-direction:column; }
.activity-header { background: linear-gradient(135deg,#6366f1,#8b5cf6); color:white; padding:16px; font-weight:800; font-size:18px; border-radius:12px 12px 0 0; }
.activity-list { list-style:none; padding:0; margin:0; height:360px; overflow-y:auto; }
.activity-item { display:flex; gap:12px; align-items:center; padding:14px 18px; border-bottom:1px solid #f1f3f6; cursor:default; }
.activity-item.clickable { cursor:pointer; }
.activity-avatar { width:44px; height:44px; border-radius:8px; background:#f5f7fb; display:flex; justify-content:center; align-items:center; font-weight:700; color:#0d47a1; }
.activity-text { flex:1; font-weight:600; }
.activity-meta { text-align:right; min-width:140px; font-size:13px; color:#6b7787; }
.label-success{ color:#059669; font-weight:700; }
.label-danger{ color:#dc2626; font-weight:700; }
.label-pending{ color:#d97706; font-weight:700; }

/* Graph Card */
.graph-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.08);
  overflow: hidden;
  display:flex;
  flex-direction:column;
  height:468px;
}
.graph-header {
  background: linear-gradient(135deg,#6366f1,#8b5cf6);
  color:white;
  padding:16px 18px;
  font-weight:800;
  display:flex;
  justify-content:space-between;
  align-items:center;
}
.graph-body { padding:18px; position:relative; display:flex; flex-direction:column; justify-content:center; gap:12px; }

/* Modal (reuse admin_returns style) */
.modal-overlay {
    display:none;
    position:fixed;
    top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.7);
    justify-content:center; align-items:center; z-index:9999;
}
.modal-box {
    background:white;
    width:100%;
    max-width:820px;
    padding:0;
    border-radius:14px;
    box-shadow:0 5px 30px rgba(0,0,0,0.30);
    animation: popIn 0.25s ease-out;
    margin:auto;
    max-height:90vh;
    overflow-y:auto;
}
@keyframes popIn { from { transform: scale(0.95); opacity:0 } to { transform: scale(1); opacity:1 } }
.modal-header {
  background: linear-gradient(135deg,#6366f1,#8b5cf6);
  color:white;
  padding:20px 22px;
  border-radius:14px 14px 0 0;
  display:flex; justify-content:space-between; align-items:center;
}
.modal-header h2 { margin:0; font-size:20px; font-weight:800; }
.close-modal { background:none; border:none; color:white; font-size:22px; cursor:pointer; padding:0 6px; border-radius:6px; }

/* small responsive */
@media (max-width: 1024px) {
  .content-wrap { margin-left:0; padding:20px; }
  .main-grid { grid-template-columns: 1fr; }
  .activity-meta { min-width:90px; }
}
/* Pagination buttons */
.activity-footer {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 8px;
  padding: 12px 0;
  background: #f5f7fb;
  border-top: 1px solid #e0e0e0;
  border-radius: 0 0 12px 12px;
}

.page-btn {
  background: white;
  color: var(--blue);
  border: 1px solid #cfe1ff;
  padding: 8px 14px;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  transition: all 0.2s ease;
}

.page-btn:hover:not(.active):not(:disabled) {
  background: #e3f2fd;
  border-color: #90caf9;
  color: #0d47a1;
}

.page-btn.active {
  background: linear-gradient(135deg, #0d47a1, #1e73ff);
  color: white;
  border: none;
  box-shadow: 0 4px 12px rgba(13,71,161,0.25);
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}



/*CHARTTTYERN*/

.view-large {
  display: inline-block;
  text-align: center;
  margin-top: 20px;
  margin-bottom:-20px;
  font-weight: 700;
  font-size: 13px;
  color: #0d47a1;
  text-decoration: none;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid #cfe1ff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  transition: all 0.25s ease;
}

.view-large:hover {
  background: linear-gradient(135deg, #0d47a1, #1e73ff);
  color: white;
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(13,71,161,0.25);
  text-decoration: none;
}


</style>

<main class="content-wrap">

  <div class="top-header">
    <div class="title">
      <i class="fa-solid fa-gauge-high"></i>
      Dashboard
    </div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= htmlspecialchars(strtoupper($displayRole), ENT_QUOTES) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="stats-container">
    <div class="stat-card" aria-hidden="true" role="region">
      <div class="stat-left">
        <h4>Pending Requests</h4>
        <div class="stat-number"><?= $pendingRequests ?></div>
      </div>
      <div style="font-size:28px;color:#6366f1;"><i class="fa-solid fa-clock"></i></div>
    </div>

    <div class="stat-card">
      <div class="stat-left">
        <h4>Ongoing Borrowings</h4>
        <div class="stat-number"><?= $ongoingBorrowings ?></div>
      </div>
      <div style="font-size:28px;color:#6366f1;"><i class="fas fa-people-carry"></i></div>
    </div>

    <div class="stat-card">
      <div class="stat-left">
        <h4>Items Due for Return</h4>
        <div class="stat-number"><?= $itemsDue ?></div>
      </div>
      <div style="font-size:28px;color:#6366f1;"><i class="fa-solid fa-calendar-days"></i></div>
    </div>

    <div class="stat-card">
      <div class="stat-left">
        <h4>Total Damage Reports</h4>
        <div class="stat-number"><?= $damagedEquipment ?></div>
      </div>
      <div style="font-size:28px;color:#6366f1;"><i class="fa-solid fa-triangle-exclamation"></i></div>
    </div>
  </div>

  <div class="main-grid">

    <!-- Activity Overview -->
    <div class="left-column">
      <div class="activity-box" role="region" aria-labelledby="activityTitle">
        <div class="activity-header" id="activityTitle">Activity Overview</div>

        <ul class="activity-list" id="activityList" aria-live="polite">
          <?php if(!empty($activityFeed)): ?>
            <?php foreach($activityFeed as $item):
              $dateDisplay = date("h:i / m-d-y", strtotime($item['created_at']));
              if($item['type'] === 'request'):
                $r = $item['data'];
            ?>
              <li class="activity-item clickable" data-id="<?= (int)$r['id'] ?>" data-type="request">
                <div class="activity-avatar">R</div>
                <div class="activity-text">
                  <?php
                    $msg = $r['status']==='Approved' ? 'Approved request #' . $r['request_no'] :
                           ($r['status']==='Pending' ? 'Sent borrow request #' . $r['request_no'] :
                           'Request #' . $r['request_no'] . ' was declined');
                    $label = $r['status']==='Approved' ? 'label-success' : ($r['status']==='Declined' ? 'label-danger' : 'label-pending');
                  ?>
                  <div class="<?= $label ?>"><?= htmlspecialchars($r['borrower_name']) ?> · <?= htmlspecialchars($msg) ?></div>
                  <div style="font-size:13px;color:#657085;margin-top:3px;">
                    <?= $r['expected_return_date'] ? 'Return: ' . htmlspecialchars($r['expected_return_date']) : '' ?>
                  </div>
                </div>
                <div class="activity-meta"><?= $dateDisplay ?></div>
              </li>
            <?php else:
                $a = $item['data'];
            ?>
              <li class="activity-item" aria-hidden="true">
                <div class="activity-avatar">A</div>
                <div class="activity-text">
                  <div style="font-weight:700;">User #<?= htmlspecialchars($a['user_id']) ?> · <?= htmlspecialchars($a['action']) ?></div>
                  <div style="font-size:13px;color:#657085;margin-top:3px;">
                    <?= htmlspecialchars($a['details'] ?: '') ?>
                  </div>
                </div>
                <div class="activity-meta"><?= $dateDisplay ?></div>
              </li>
            <?php endif; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <li style="padding:18px; color:#64748b;">No recent activity.</li>
          <?php endif; ?>
        </ul>

        <div class="activity-footer" style="display:flex; justify-content:center; gap:8px; padding:12px 0; background:#f5f7fb; border-top:1px solid #e0e0e0;">
          <button class="page-btn active" data-page="1">1</button>
          <button class="page-btn" data-page="2">2</button>
          <button class="page-btn" data-page="3">3</button>
        </div>

      </div>
    </div>

    <!-- Graph -->
    <div class="right-column">
      <div class="graph-card">
        <div class="graph-header">
          <div>
            <div style="font-size:18px;">Most Used Equipment</div>
            <small>Based on total borrowed quantity</small>
          </div>
          <i class="fa-solid fa-chart-bar" style="font-size:18px;opacity:0.9;"></i>
        </div>

        <div class="graph-body">
          <canvas id="borrowChart" width="300" height="240" aria-label="Most used equipment chart"></canvas>
          <a class="view-large" href="#">View Large Graph ▸</a>
        </div>
      </div>
    </div>

  </div>

</main>

<!-- Modal container for request details -->
<div id="activityModalOverlay" class="modal-overlay" aria-hidden="true">
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-header">
      <h2 id="modalTitle">Request Details</h2>
      <button class="close-modal" onclick="closeActivityModal()" aria-label="Close">&times;</button>
    </div>
    <div id="activityModalContent" style="padding:20px;">
      <!-- request_details_modal.php content will be injected here -->
      <div style="text-align:center;color:#64748b;">Loading...</div>
    </div>
  </div>
</div>

<script>
const topLabels = <?= json_encode($chartLabels) ?>;
const topData   = <?= json_encode($chartData) ?>;

const ctx = document.getElementById('borrowChart').getContext('2d');

if (!topLabels.length) {
  ctx.canvas.parentElement.innerHTML = `
    <div style="text-align:center;padding:40px 20px;color:#64748b;">
      <i class="fa-solid fa-box-open" style="font-size:40px;margin-bottom:10px;opacity:0.6;"></i>
      <div style="font-weight:700;">No Borrowing Data Yet</div>
      <div style="font-size:13px;">Equipment usage will appear here once items are borrowed.</div>
    </div>`;
} else {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: topLabels,
      datasets: [{
        label: 'Total Borrowed',
        data: topData,
        backgroundColor: '#1e73ff',
        borderRadius: 8,
        barThickness: 28
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      animation: { duration: 800, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0d47a1',
          titleFont: { weight: 'bold' },
          padding: 10,
          callbacks: {
            label: ctx => ' Borrowed: ' + ctx.parsed.x
          }
        },
        datalabels: {
          anchor: 'end',
          align: 'right',
          color: '#0d47a1',
          font: { weight: '600', size: 12 },
          formatter: value => value
        }
      },
      scales: {
        x: { beginAtZero: true, ticks: { precision: 0, color: '#334155', font: { weight: '600' } }, grid: { color: 'rgba(0,0,0,0.05)' } },
        y: { ticks: { color: '#0f172a', font: { weight: '600' } }, grid: { display: false } }
      }
    },
    plugins: [ChartDataLabels]
  });
}

// AJAX pagination loader (if you keep fetch_activities.php)
const activityListElem = document.getElementById('activityList');
const activityFooterContainer = document.querySelector('.activity-footer');

// render pagination with maxButtons centered around current page
function buildPaginationHTML(currentPage, totalPages, maxButtons = 5) {
  let html = '';
  // Prev button
  const prevDisabled = currentPage <= 1 ? 'disabled' : '';
  html += `<button class="page-btn" data-page="${Math.max(1, currentPage-1)}" ${prevDisabled}>Prev</button>`;

  // calculate start/end
  const half = Math.floor(maxButtons / 2);
  let start = Math.max(1, currentPage - half);
  let end = start + maxButtons - 1;
  if (end > totalPages) {
    end = totalPages;
    start = Math.max(1, end - maxButtons + 1);
  }

  if (start > 1) {
    html += `<button class="page-btn" data-page="1">1</button>`;
    if (start > 2) html += `<span style="padding:0 8px;color:#94a3b8;">…</span>`;
  }

  for (let p = start; p <= end; p++) {
    const active = p === currentPage ? 'active' : '';
    html += `<button class="page-btn ${active}" data-page="${p}">${p}</button>`;
  }

  if (end < totalPages) {
    if (end < totalPages - 1) html += `<span style="padding:0 8px;color:#94a3b8;">…</span>`;
    html += `<button class="page-btn" data-page="${totalPages}">${totalPages}</button>`;
  }

  // Next button
  const nextDisabled = currentPage >= totalPages ? 'disabled' : '';
  html += `<button class="page-btn" data-page="${Math.min(totalPages, currentPage+1)}" ${nextDisabled}>Next</button>`;

  return html;
}

function attachPageButtonHandlers() {
  document.querySelectorAll('.page-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const page = parseInt(btn.dataset.page, 10);
      if (!isFinite(page)) return;
      loadActivities(page);
    });
  });
}

function loadActivities(page = 1, limit = 7) {
  // show loading state
  activityListElem.innerHTML = '<li style="padding:20px; text-align:center; color:#64748b;">Loading...</li>';
  fetch(`/brgy_iba/fetch_activities.php?page=${encodeURIComponent(page)}&limit=${encodeURIComponent(limit)}`)
    .then(res => res.json())
    .then(data => {
      if (!data || !data.success) {
        activityListElem.innerHTML = `<li style="padding:18px;color:#d32f2f;">Failed to load activities.</li>`;
        return;
      }
      activityListElem.innerHTML = data.html || '<li style="padding:18px;color:#64748b;">No recent activity.</li>';

      // build pagination UI and handlers
      const paginationHTML = buildPaginationHTML(data.page, data.totalPages, 5);
      // Use the footer container for placement (it might be innerHTML earlier)
      // Find or create an inner container to place pagination buttons.
      // The server-side markup had .activity-footer; use that element if present.
      const footer = document.querySelector('.activity-footer') || activityFooterContainer;
      if (footer) {
        footer.innerHTML = paginationHTML;
        attachPageButtonHandlers();
      }

      // (re)attach click handler for activity items (requests)
      // We use event delegation on activityListElem (already present). If you replaced the markup,
      // ensure the click delegation below is still wired in your overall script.
    })
    .catch(err => {
      console.error('Failed loading activities', err);
      activityListElem.innerHTML = `<li style="padding:18px;color:#d32f2f;">Failed to load activities.</li>`;
    });
}

// initial load
loadActivities(1);

// event delegation to handle clicks on request items (open modal)
activityListElem.addEventListener('click', function(e){
  const li = e.target.closest('.activity-item');
  if (!li) return;
  const type = li.dataset.type || '';
  if (type !== 'request') return;
  const id = li.dataset.id;
  if (!id) return;
  openActivityModal(id);
});

// initial load (keeps server-side list already present; uncomment to enable AJAX pagination)
// loadActivities();

// Event delegation: only handle clicks on items that are requests
activityList.addEventListener('click', function(e){
  const li = e.target.closest('.activity-item');
  if(!li) return;
  const type = li.dataset.type || '';
  if(type !== 'request') return;
  const id = li.dataset.id;
  if(!id) return;
  openActivityModal(id);
});

// Open modal and fetch request details
function openActivityModal(requestId){
  const overlay = document.getElementById('activityModalOverlay');
  const content = document.getElementById('activityModalContent');
  content.innerHTML = '<p style="text-align:center;padding:20px;color:#6b7280;">Loading...</p>';
  overlay.style.display = 'flex';
  overlay.setAttribute('aria-hidden','false');

  const url = '/brgy_iba/approval/request_details_modal.php?id=' + encodeURIComponent(requestId);
  fetch(url)
    .then(res => res.text())
    .then(html => {
      content.innerHTML = html;
    })
    .catch(err => {
      content.innerHTML = '<p style="color:red;text-align:center;padding:20px;">Failed to load details.</p>';
      console.error('Error fetching request modal:', err);
    });
}

function closeActivityModal(){
  const overlay = document.getElementById('activityModalOverlay');
  if(!overlay) return;
  overlay.style.display = 'none';
  overlay.setAttribute('aria-hidden','true');
  document.getElementById('activityModalContent').innerHTML = '';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e){
  if(e.key === 'Escape') closeActivityModal();
});
</script>
