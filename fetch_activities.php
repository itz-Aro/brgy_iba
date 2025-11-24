<?php
require_once __DIR__ . '../config/Database.php';
session_start();

$db = new Database();
$conn = $db->getConnection();

$perPage = 7;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $perPage;

// Get total counts
$totalRequests = (int)$conn->query("SELECT COUNT(*) FROM requests")->fetchColumn();
$totalAudits = (int)$conn->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$totalActivities = $totalRequests + $totalAudits;
$totalPages = ceil($totalActivities / $perPage);

// Fetch recent requests
$stmt = $conn->prepare("
    SELECT id, request_no, borrower_name, status, expected_return_date, created_at
    FROM requests
    ORDER BY created_at DESC
    LIMIT $start, $perPage
");
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent audit logs
$logStmt = $conn->prepare("
    SELECT id, user_id, action, resource_type, resource_id, details, ip_address, created_at
    FROM audit_logs
    ORDER BY created_at DESC
    LIMIT $start, $perPage
");
$logStmt->execute();
$auditLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// Merge and sort
$activityFeed = [];
foreach ($activities as $r) { $activityFeed[] = ['type'=>'request','created_at'=>$r['created_at'],'data'=>$r]; }
foreach ($auditLogs as $l) { $activityFeed[] = ['type'=>'audit','created_at'=>$l['created_at'],'data'=>$l]; }
usort($activityFeed, fn($a,$b)=> strtotime($b['created_at']) - strtotime($a['created_at']));

// Generate HTML
ob_start();
if(!empty($activityFeed)):
    foreach($activityFeed as $item):
        $dateDisplay = date("h:i / m-d-y", strtotime($item['created_at']));
?>
<li class="activity-item">
    <div class="activity-avatar"><?= ($item['type']==='request') ? 'R':'A' ?></div>
    <div class="activity-icon"><?= $item['type']==='request' ? ($item['data']['status']==='Approved'?'✅':($item['data']['status']==='Pending'?'❓':($item['data']['status']==='Declined'?'❌':'⏸'))) : '📝' ?></div>
    <div class="activity-text">
        <?php if($item['type']==='request'):
            $r = $item['data'];
            $msg = $r['status']==='Approved' ? 'Approved request #'.$r['request_no'] :
                   ($r['status']==='Pending' ? 'Sent borrow request #'.$r['request_no'] :
                   ($r['status']==='Declined' ? 'Request #'.$r['request_no'].' was declined' : $r['status'].' request #'.$r['request_no']));
            $label = $r['status']==='Approved'?'label-success':($r['status']==='Declined'?'label-danger':'label-pending');
        ?>
        <div class="<?= $label ?>"><?= htmlspecialchars($r['borrower_name']) ?> · <?= $msg ?></div>
        <div style="font-size:13px;color:#657085;margin-top:3px;">
            <?= $r['expected_return_date'] ? 'Return: '.htmlspecialchars($r['expected_return_date']) : '' ?>
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
<?php
    endforeach;
else:
    echo '<li style="padding:18px;">No recent activity.</li>';
endif;

$html = ob_get_clean();

// Return JSON
echo json_encode(['html'=>$html, 'totalPages'=>$totalPages]);
