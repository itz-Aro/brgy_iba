<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

AuthMiddleware::protect(['admin','officials']);

$db = new Database();
$conn = $db->getConnection();
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/brgy_iba/uploads/returns/';

/* ==========================
   HANDLE CONFIRM RETURN
   ========================== */
if (isset($_POST['confirm_return'])) {
    $item_id     = (int)$_POST['item_id'];
    $equipmentId = (int)$_POST['equipment_id'];
    $condition   = $_POST['condition_in'];

    // 1) update borrowing_items.condition_in
    $updateItem = $conn->prepare("UPDATE borrowing_items SET condition_in = ? WHERE id = ?");
    $updateItem->execute([$condition, $item_id]);

    // 2) get quantity (must fetch separately; do NOT chain execute()->fetchColumn())
    $qStmt = $conn->prepare("SELECT quantity FROM borrowing_items WHERE id = ?");
    $qStmt->execute([$item_id]);
    $qty = (int)$qStmt->fetchColumn();

    // 3) update parent borrowing status to Returned and set actual_return_date
    $markBorrowing = $conn->prepare("
        UPDATE borrowings 
        SET status = 'Returned', actual_return_date = NOW()
        WHERE id = (
            SELECT borrowing_id FROM borrowing_items WHERE id = ?
        )
    ");
    $markBorrowing->execute([$item_id]);

    // 4) restore inventory if not damaged
    if ($condition !== 'Damaged' && $qty > 0) {
        $updEq = $conn->prepare("UPDATE equipment SET available_quantity = available_quantity + ? WHERE id = ?");
        $updEq->execute([$qty, $equipmentId]);
    }

    
    // 5) auto log maintenance if damaged (WITH MODAL REMARKS + RETURNED_FROM)
if ($condition === 'Damaged') {

    $customRemarks = trim($_POST['damage_remarks'] ?? '');

    // ✅ Get borrower name
    $borrowerStmt = $conn->prepare("
        SELECT b.borrower_name 
        FROM borrowings b
        JOIN borrowing_items bi ON b.id = bi.borrowing_id
        WHERE bi.id = ?
        LIMIT 1
    ");
    $borrowerStmt->execute([$item_id]);
    $borrowerName = $borrowerStmt->fetchColumn();

    // ✅ Fallback protection
    if (!$borrowerName) {
        $borrowerName = "Unknown Borrower";
    }

    // ✅ Final clean description
    $finalRemarks = !empty($customRemarks) ? $customRemarks : 'No description provided';

    // ✅ Correct insert using your new column structure
    $insLog = $conn->prepare("
        INSERT INTO maintenance_logs 
        (equipment_id, action, returned_from, remarks, performed_by)
        VALUES (?, 'Marked Damaged', ?, ?, ?)
    ");

    $insLog->execute([
        $equipmentId,              // equipment_id
        $borrowerName,             // returned_from ✅
        $finalRemarks,             // remarks ✅
        $_SESSION['user']['id'] ?? null   // performed_by ✅
    ]);
}



    // 6) handle optional return photo upload
    if (!empty($_FILES['return_photo']['name']) && $_FILES['return_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadsDir = __DIR__ . '/../uploads/returns/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $originalName = basename($_FILES['return_photo']['name']);
        $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $originalName);
        $dest = $uploadsDir . $safeName;

        if (move_uploaded_file($_FILES['return_photo']['tmp_name'], $dest)) {
            $insPhoto = $conn->prepare("INSERT INTO return_photos (borrowing_item_id, filename) VALUES (?, ?)");
            $insPhoto->execute([$item_id, $safeName]);
        }
    }

    // Redirect to avoid form resubmission
    header("Location: admin_returns.php");
    exit;
}

/* ==========================
   FETCH ITEMS DUE (Active borrowings)
   ========================== */
$stmtDue = $conn->prepare("
SELECT 
    bi.id AS item_id,
    bi.quantity,
    bi.condition_out,
    bi.condition_in,
    b.id AS borrowing_id,
    b.borrowing_no,
    b.borrower_name,
    b.expected_return_date,
    e.id AS equipment_id,
    e.name AS equipment_name
FROM borrowing_items bi
JOIN borrowings b ON bi.borrowing_id = b.id
JOIN equipment e ON bi.equipment_id = e.id
WHERE b.status = 'Active'
ORDER BY b.expected_return_date ASC
");
$stmtDue->execute();
$dueItems = $stmtDue->fetchAll(PDO::FETCH_ASSOC);

/* ==========================
   FETCH ITEMS MARKED RETURNED
   ========================== */
$stmtReturned = $conn->prepare("
SELECT 
    bi.id AS item_id,
    bi.quantity,
    bi.condition_out,
    bi.condition_in,
    b.borrowing_no,
    b.borrower_name,
    b.actual_return_date,
    e.id AS equipment_id,
    e.name AS equipment_name
FROM borrowing_items bi
JOIN borrowings b ON bi.borrowing_id = b.id
JOIN equipment e ON bi.equipment_id = e.id
WHERE b.status = 'Returned'
ORDER BY b.actual_return_date DESC
");
$stmtReturned->execute();
$returnedItems = $stmtReturned->fetchAll(PDO::FETCH_ASSOC);

/* ==========================
   FETCH ITEMS MARKED DAMAGED WITH PHOTO + REMARKS
   ========================== */
$stmtDamaged = $conn->prepare("
SELECT 
    bi.id AS item_id,
    bi.quantity,
    bi.condition_out,
    bi.condition_in,
    b.borrowing_no,
    b.borrower_name,
    b.actual_return_date,
    e.id AS equipment_id,
    e.name AS equipment_name,
    rp.filename AS return_photo,
    ml.remarks AS damage_remarks,
    ml.returned_from
FROM borrowing_items bi
JOIN borrowings b ON bi.borrowing_id = b.id
JOIN equipment e ON bi.equipment_id = e.id
LEFT JOIN return_photos rp ON rp.borrowing_item_id = bi.id
LEFT JOIN (
    SELECT m1.*
    FROM maintenance_logs m1
    INNER JOIN (
        SELECT equipment_id, MAX(id) AS max_id
        FROM maintenance_logs
        WHERE action = 'Marked Damaged'
        GROUP BY equipment_id
    ) m2 ON m1.id = m2.max_id
) ml ON ml.equipment_id = bi.equipment_id
WHERE bi.condition_in = 'Damaged'
ORDER BY b.actual_return_date DESC
");

$stmtDamaged->execute();
$damagedItems = $stmtDamaged->fetchAll(PDO::FETCH_ASSOC);



$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);
?>

<link rel="stylesheet" href="/public/css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
* { box-sizing: border-box; }

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #f1f5f9;
    margin: 0;
}

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
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
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

.top-header .title i {
    font-size: 2.2rem;
    opacity: 0.9;
}

.admin-area { 
    display: flex; 
    align-items: center; 
    gap: 20px;
    position: relative;
    z-index: 1;
}

.greeting {
    font-size: 1rem;
    font-weight: 600;
    opacity: 0.95;
}

.avatar { 
    width: 60px; 
    height: 60px; 
    border-radius: 50%; 
    background: white;
    color: #6366f1; 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    font-weight: 800; 
    font-size: 1.25rem; 
    border: 4px solid rgba(255,255,255,0.3);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 2px solid #f1f5f9;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.12);
    border-color: #6366f1;
}

.stat-card .stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 16px;
}

.stat-card.due .stat-icon {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #d97706;
}

.stat-card.returned .stat-icon {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #059669;
}

.stat-card.damaged .stat-icon {
    background:linear-gradient(135deg,#fee2e2,#fecaca); 
    color:#dc2626; 
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}

.stat-card .stat-label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 600;
}

.tabs { 
    display: flex; 
    gap: 12px; 
    margin-bottom: 24px;
    background: white;
    padding: 8px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.tab-btn { 
    padding: 14px 28px; 
    border: none; 
    cursor: pointer; 
    font-weight: 700; 
    border-radius: 12px; 
    background: transparent;
    color: #64748b;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tab-btn:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.tab-btn.active { 
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.tab-content { 
    display: none;
    animation: slideIn 0.4s ease-in-out;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateX(-10px); }
    to { opacity: 1; transform: translateX(0); }
}

.tab-content.active { 
    display: block; 
}

.table-card { 
    background: white; 
    border-radius: 20px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    border: 2px solid #f1f5f9;
}

.table-card table { 
    width: 100%; 
    border-collapse: collapse; 
}

.table-card th { 
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; 
    padding: 18px 20px; 
    text-align: left;
    font-weight: 700;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-card td { 
    padding: 18px 20px; 
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    font-size: 0.9rem;
    color: #334155;
}

.table-card tbody tr {
    transition: all 0.2s;
}

.table-card tbody tr:hover {
    background: #f8fafc;
}

.table-card tbody tr:last-child td {
    border-bottom: none;
}

.badge-good { 
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #059669;
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.85rem;
}

.badge-damage { 
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #dc2626;
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.85rem;
}

.badge-warning {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #d97706;
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.85rem;
}

.action-form {
    background: #f8fafc;
    padding: 16px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
}

.select-condition { 
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 2px solid #e2e8f0;
    font-size: 0.9rem;
    font-weight: 600;
    background: white;
    color: #334155;
    cursor: pointer;
    transition: all 0.3s;
}

.select-condition:focus {
    border-color: #6366f1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.file-input-wrapper {
    margin-top: 12px;
    position: relative;
}

.file-input-wrapper input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    font-size: 0.85rem;
    background: white;
    cursor: pointer;
    transition: all 0.3s;
}

.file-input-wrapper input[type="file"]:hover {
    border-color: #6366f1;
    background: #f8fafc;
}

.confirm-btn { 
    width: 100%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.9rem;
    margin-top: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.confirm-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

.confirm-btn:active {
    transform: translateY(0);
}

.small-note { 
    font-size: 0.75rem; 
    color: #64748b; 
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.small-note i {
    color: #94a3b8;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

.empty-state i {
    font-size: 4rem;
    color: #cbd5e1;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 1.25rem;
    color: #334155;
    margin-bottom: 8px;
    font-weight: 700;
}

.empty-state p {
    font-size: 0.95rem;
    color: #64748b;
}

/* Overdue indicator */
.overdue-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #dc2626;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-left: 8px;
}

@media (max-width: 1024px) {
    .content-wrap {
        margin-left: 0;
        padding: 20px;
    }
    
    .top-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
    }
}

/*RETURN IMAGESSSS*/

</style>

<main class="content-wrap">

<div class="top-header">
    <div class="title">
        <i class="fa-solid fa-box-archive"></i>
        Returns Management
    </div>
    <div class="admin-area">
        <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
        <div class="avatar">AD</div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-container">
    <div class="stat-card due">
        <div class="stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="stat-value"><?= count($dueItems) ?></div>
        <div class="stat-label">Items Due for Return</div>
    </div>
    <div class="stat-card returned">
        <div class="stat-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="stat-value"><?= count($returnedItems) ?></div>
        <div class="stat-label">Successfully Returned</div>
    </div>
     <div class="stat-card damaged">
        <div class="stat-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-value"><?= count($damagedItems) ?></div>
        <div class="stat-label">Marked Damaged Returned</div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <button class="tab-btn active" data-tab="due">
        <i class="fa-solid fa-clock"></i>
        Items Due for Return
    </button>
    <button class="tab-btn" data-tab="returned">
        <i class="fa-solid fa-circle-check"></i>
        Returned Items
    </button>
    <button class="tab-btn" data-tab="damaged">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Returned Damaged
    </button>
</div>

<!-- Items Due Table -->
<div class="tab-content active" id="due">
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Borrow No</th>
                    <th>Borrower</th>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>Condition Out</th>
                    <th>Expected Return</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($dueItems)): ?>
                <?php foreach($dueItems as $r): 
                    $expectedDate = new DateTime($r['expected_return_date']);
                    $today = new DateTime();
                    $isOverdue = $expectedDate < $today;
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['borrowing_no']) ?></strong></td>
                        <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                        <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                        <td><strong><?= (int)$r['quantity'] ?></strong></td>
                        <td>
                            <span class="badge-good">
                                <i class="fa-solid fa-circle"></i>
                                <?= htmlspecialchars($r['condition_out']) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($r['expected_return_date']) ?>
                            <?php if($isOverdue): ?>
                                <span class="overdue-badge">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    Overdue
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" enctype="multipart/form-data" class="action-form return-form">
                                <input type="hidden" name="item_id" value="<?= $r['item_id'] ?>">
                                <input type="hidden" name="equipment_id" value="<?= $r['equipment_id'] ?>">
                                
                                <select name="condition_in" class="select-condition" required>
                                    <option value="">Select Condition</option>
                                    <option value="Good">✓ Good</option>
                                    <option value="Fair">⚠ Fair</option>
                                    <option value="Damaged">✗ Damaged</option>
                                </select>
                                
                                <div class="file-input-wrapper">
                                    <input type="file" name="return_photo" accept="image/*">
                                </div>
                                
                                <button name="confirm_return" class="confirm-btn">
                                    <i class="fa-solid fa-check"></i>
                                    Confirm Return
                                </button>
                                
                                <div class="small-note">
                                    <i class="fa-solid fa-info-circle"></i>
                                    Photo upload is optional but recommended
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <h3>No Items Due</h3>
                            <p>All equipment has been returned or no active borrowings</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Returned Items Table -->
<div class="tab-content" id="returned">
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Borrow No</th>
                    <th>Borrower</th>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>Condition Out</th>
                    <th>Condition In</th>
                    <th>Return Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($returnedItems)): ?>
                <?php foreach($returnedItems as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['borrowing_no']) ?></strong></td>
                        <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                        <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                        <td><strong><?= (int)$r['quantity'] ?></strong></td>
                        <td>
                            <span class="badge-good">
                                <i class="fa-solid fa-circle"></i>
                                <?= htmlspecialchars($r['condition_out']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?= $r['condition_in'] === 'Damaged' ? 'badge-damage' : ($r['condition_in'] === 'Fair' ? 'badge-warning' : 'badge-good') ?>">
                                <i class="fa-solid fa-<?= $r['condition_in'] === 'Damaged' ? 'triangle-exclamation' : ($r['condition_in'] === 'Fair' ? 'circle-exclamation' : 'circle-check') ?>"></i>
                                <?= htmlspecialchars($r['condition_in']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['actual_return_date']) ?></td>
                        <td>
                            <span class="badge-good">
                                <i class="fa-solid fa-check-double"></i>
                                Returned
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fa-solid fa-box-open"></i>
                            <h3>No Returns Yet</h3>
                            <p>Returned items will appear here</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Damaged Items Table -->
<div class="tab-content" id="damaged">
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Borrow No</th>
                    <th>Borrower</th>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>Condition Out</th>
                    <th>Condition In</th>
                    <th>Return Date</th>
                    <th>Remarks</th> 
                    <th>Photo</th>
                </tr>
            </thead>

            <tbody>
            <?php if (!empty($damagedItems)): ?>
                <?php foreach($damagedItems as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['borrowing_no']) ?></strong></td>
                        <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                        <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                        <td><strong><?= (int)$r['quantity'] ?></strong></td>
                        <td>
                            <span class="badge-good">
                                <i class="fa-solid fa-circle"></i>
                                <?= htmlspecialchars($r['condition_out']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-damage">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <?= htmlspecialchars($r['condition_in']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['actual_return_date']) ?></td>
                        <td>
                            <?php if(!empty($r['damage_remarks'])): ?>
                                <span style="font-size:0.85rem; color:#1e293b;">
                                    <?= htmlspecialchars($r['damage_remarks']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#64748b; font-size:0.85rem;">No Remarks</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($r['return_photo']) && file_exists(__DIR__ . '/../uploads/returns/' . $r['return_photo'])): ?>
                                <img 
                                    src="<?= $baseUrl . htmlspecialchars($r['return_photo']) ?>"  
                                    alt="Return Photo" 
                                    style="width:50px; height:50px; object-fit:cover; border-radius:8px; border:1px solid #ccc; cursor:pointer;"
                                    class="clickable-photo"
                                    data-src="<?= $baseUrl . htmlspecialchars($r['return_photo']) ?>" 
                                >
                            <?php else: ?>
                                <span style="color:#64748b; font-size:0.85rem;">No Photo</span>
                            <?php endif; ?>
                        </td>

                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fa-solid fa-box-open"></i>
                            <h3>No Damaged Returns</h3>
                            <p>Damaged returned items will appear here</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Image Modal -->
<div id="imageModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); justify-content:center; align-items:center; z-index:9999;">
    <span id="closeModal" style="position:absolute; top:20px; right:30px; font-size:2rem; color:white; cursor:pointer;">&times;</span>
    <img id="modalImage" src="" alt="Return Photo" style="max-width:90%; max-height:90%; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.5);">
</div>


<!-- Damaged Remarks Modal -->
<div id="damageRemarksModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
  <div style="background:white; padding:24px; border-radius:16px; width:100%; max-width:420px; box-shadow:0 10px 40px rgba(0,0,0,0.3); animation:fadeIn .2s ease;">
    
    <h3 style="margin-bottom:12px; color:#dc2626;">
      <i class="fa-solid fa-triangle-exclamation"></i> Damaged Item Description
    </h3>

    <p style="font-size:0.9rem; color:#64748b; margin-bottom:12px;">
      Please provide a description for the damage:
    </p>

    <form method="POST" id="damageRemarksForm">
      <input type="hidden" name="item_id" id="damage_item_id">
      <input type="hidden" name="equipment_id" id="damage_equipment_id">
      <input type="hidden" name="condition_in" value="Damaged">
      <input type="hidden" name="confirm_return" value="1">

      <textarea name="damage_remarks" required
        style="width:100%; height:100px; padding:10px; border-radius:8px; border:2px solid #e2e8f0; resize:none;"></textarea>

      <div style="display:flex; gap:10px; margin-top:16px;">
        <button type="button" onclick="closeDamageModal()" 
          style="flex:1; padding:10px; border-radius:8px; border:none; background:#e5e7eb;">
          Cancel
        </button>

        <button type="submit" 
          style="flex:1; padding:10px; border-radius:8px; border:none; background:#dc2626; color:white;">
          Save Damage
        </button>
      </div>
    </form>

  </div>
</div>

</main>

<script>
// Tab switching with smooth animation
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});
// Modal logic for images
const modal = document.getElementById('imageModal');
const modalImg = document.getElementById('modalImage');
const closeModal = document.getElementById('closeModal');

document.querySelectorAll('.clickable-photo').forEach(img => {
    img.addEventListener('click', () => {
        modal.style.display = 'flex';
        modalImg.src = img.dataset.src; // Set full image
    });
});

closeModal.addEventListener('click', () => {
    modal.style.display = 'none';
});

modal.addEventListener('click', (e) => {
    if(e.target === modal) modal.style.display = 'none';
});


//SCRIPT FOR DAMAGED REMARKED
document.querySelectorAll('.return-form').forEach(form => {
  form.addEventListener('submit', function(e) {

    const condition = this.querySelector('select[name="condition_in"]').value;

    if (condition === 'Damaged') {
      e.preventDefault(); // STOP normal submit

      // Get hidden values
      const itemId = this.querySelector('input[name="item_id"]').value;
      const equipmentId = this.querySelector('input[name="equipment_id"]').value;

      // Pass to modal
      document.getElementById('damage_item_id').value = itemId;
      document.getElementById('damage_equipment_id').value = equipmentId;

      // Show modal
      document.getElementById('damageRemarksModal').style.display = 'flex';
    }
  });
});

function closeDamageModal() {
  document.getElementById('damageRemarksModal').style.display = 'none';
}
</script>