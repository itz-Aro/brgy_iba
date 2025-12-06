<?php
// /brgy_iba/fetch_activities.php
session_start();
require_once __DIR__ . '/config/Database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $conn = $db->getConnection();

    $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 7; // default 7 items per page
    $offset = ($page - 1) * $limit;

    // Count total rows (requests + audit_logs) safely
    $totalStmt = $conn->query("SELECT (SELECT COUNT(*) FROM requests) + (SELECT COUNT(*) FROM audit_logs) AS total");
    $total = (int)$totalStmt->fetchColumn();
    $totalPages = ($total > 0) ? (int)ceil($total / $limit) : 1;

    // Union query: normalize columns for rendering
    $sql = "
        SELECT id, request_no AS ref_no, borrower_name AS title, status AS extra_status, expected_return_date, created_at, 'request' AS type
        FROM requests
        UNION ALL
        SELECT id, NULL AS ref_no, CONCAT('User #', user_id) AS title, action AS extra_status, NULL AS expected_return_date, created_at, 'audit' AS type
        FROM audit_logs
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);
    // bind as integers
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build HTML for the activity list items
    ob_start();
    if (empty($rows)) {
        echo '<li style="padding:18px;color:#64748b;">No recent activity.</li>';
    } else {
        foreach ($rows as $row) {
            $createdAt = !empty($row['created_at']) ? date("h:i / m-d-y", strtotime($row['created_at'])) : '';
            if ($row['type'] === 'request') {
                $rId = (int)$row['id'];
                $refNo = htmlspecialchars($row['ref_no'] ?? '', ENT_QUOTES);
                $borrower = htmlspecialchars($row['title'] ?? 'Unknown', ENT_QUOTES);
                $status = htmlspecialchars($row['extra_status'] ?? '', ENT_QUOTES);
                $expected = htmlspecialchars($row['expected_return_date'] ?? '', ENT_QUOTES);
                // choose label class
                $labelClass = $status === 'Approved' ? 'label-success' : ($status === 'Declined' ? 'label-danger' : 'label-pending');

                // Message text
                $msg = $status === 'Approved' ? "Approved request #{$refNo}" : ($status === 'Pending' ? "Sent borrow request #{$refNo}" : "Request #{$refNo} was {$status}");
                ?>
                <li class="activity-item clickable" data-id="<?= $rId ?>" data-type="request" style="user-select:none;">
                  <div class="activity-avatar">R</div>
                  <div class="activity-text">
                    <div class="<?= $labelClass ?>"><?= $borrower ?> · <?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
                    <div style="font-size:13px;color:#657085;margin-top:3px;"><?= $expected ? 'Return: ' . $expected : '' ?></div>
                  </div>
                  <div class="activity-meta"><?= $createdAt ?></div>
                </li>
                <?php
            } else {
                // audit
                $aId = (int)$row['id'];
                $title = htmlspecialchars($row['title'] ?? 'Audit', ENT_QUOTES);
                $action = htmlspecialchars($row['extra_status'] ?? '', ENT_QUOTES);
                ?>
                <li class="activity-item" style="user-select:none;">
                  <div class="activity-avatar">A</div>
                  <div class="activity-text">
                    <div style="font-weight:700;"><?= $title ?> · <?= $action ?></div>
                    <div style="font-size:13px;color:#657085;margin-top:3px;"><?= '' ?></div>
                  </div>
                  <div class="activity-meta"><?= $createdAt ?></div>
                </li>
                <?php
            }
        }
    }
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html,
        'total' => $total,
        'totalPages' => max(1, $totalPages),
        'page' => $page,
        'limit' => $limit
    ]);
    exit;
} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $ex->getMessage()]);
    exit;
}
