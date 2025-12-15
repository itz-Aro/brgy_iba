<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

AuthMiddleware::protect(['admin','officials']);

$db = new Database();
$conn = $db->getConnection();
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . 'brgy_iba/uploads/returns/';

if (isset($_POST['confirm_return'])) {

    $item_id       = (int)($_POST['item_id'] ?? 0);
    $equipment_id  = (int)($_POST['equipment_id'] ?? 0);
    $condition     = $_POST['condition_in'] ?? '';
    $damageRemarks = trim($_POST['damage_remarks'] ?? '');
    $returnPhotoId = null;

    if (!$item_id || !$equipment_id || !$condition) {
        $_SESSION['error'] = 'Invalid return data.';
        header("Location: admin_returns.php");
        exit;
    }

    // Photo ID from upload
    if (!empty($_POST['return_photo_id']) && is_numeric($_POST['return_photo_id'])) {
        $returnPhotoId = (int)$_POST['return_photo_id'];
    }

    // Damaged items must have photo and remarks
    if ($condition === 'Damaged') {
        if (empty($damageRemarks)) {
            $_SESSION['error'] = 'Damage remarks are required for damaged items.';
            header("Location: admin_returns.php");
            exit;
        }
        if (empty($returnPhotoId)) {
            $_SESSION['error'] = 'Return photo is required for damaged items.';
            header("Location: admin_returns.php");
            exit;
        }
    }

    try {
        $conn->beginTransaction();

        // Fetch borrowing info
        $stmt = $conn->prepare("
            SELECT bi.borrowing_id, bi.quantity, bi.condition_out, b.borrowing_no, b.borrower_name, e.name AS equipment_name
            FROM borrowing_items bi
            JOIN borrowings b ON bi.borrowing_id = b.id
            JOIN equipment e ON bi.equipment_id = e.id
            WHERE bi.id = ?
        ");
        $stmt->execute([$item_id]);
        $borrow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$borrow) throw new Exception('Borrowing item not found.');

        // Insert into returned_items
        $insertReturn = $conn->prepare("
            INSERT INTO returned_items (
                borrowing_item_id,
                borrowing_id,
                borrowing_no,
                borrower_name,
                equipment_id,
                equipment_name,
                quantity,
                condition_out,
                condition_in,
                damage_remarks,
                return_photo_id,
                return_date,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $status = ($condition === 'Damaged') ? 'Damaged' : 'Returned';
        $insertReturn->execute([
            $item_id,
            $borrow['borrowing_id'],
            $borrow['borrowing_no'],
            $borrow['borrower_name'],
            $equipment_id,
            $borrow['equipment_name'],
            $borrow['quantity'],
            $borrow['condition_out'] ?? 'Good',
            $condition,
            $damageRemarks ?: null,
            $returnPhotoId,
            $status
        ]);

        // Update borrowing item status
        $updBorrowItem = $conn->prepare("
            UPDATE borrowing_items
            SET status = ?, condition_in = ?
            WHERE id = ?
        ");
        $updBorrowItem->execute([$status, $condition, $item_id]);

        // Update equipment quantity
        $updEquip = $conn->prepare("
            UPDATE equipment
            SET available_quantity = available_quantity + ?
            WHERE id = ?
        ");
        $updEquip->execute([$borrow['quantity'], $equipment_id]);

        // Check if all items returned for this borrowing
        $checkAll = $conn->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status IN ('Returned','Damaged') THEN 1 ELSE 0 END) as returned
            FROM borrowing_items
            WHERE borrowing_id = ?
        ");
        $checkAll->execute([$borrow['borrowing_id']]);
        $counts = $checkAll->fetch(PDO::FETCH_ASSOC);

        if ($counts['total'] == $counts['returned']) {
            $updBorrowing = $conn->prepare("
                UPDATE borrowings
                SET status = 'Returned', actual_return_date = NOW()
                WHERE id = ?
            ");
            $updBorrowing->execute([$borrow['borrowing_id']]);
        }

        $conn->commit();

        $_SESSION['return_success'] = [
            'borrowing_no' => $borrow['borrowing_no'],
            'equipment_name' => $borrow['equipment_name'],
            'borrower_name' => $borrow['borrower_name'],
            'quantity' => $borrow['quantity'],
            'condition' => $condition
        ];

        header("Location: admin_returns.php");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'Return failed: ' . $e->getMessage();
        header("Location: admin_returns.php");
        exit;
    }
}


// ==========================
// FETCH ITEMS DUE
// ==========================
$dueItems = $conn->query("
    SELECT bi.id AS item_id, bi.quantity, bi.condition_out, bi.condition_in,
           b.id AS borrowing_id, b.borrowing_no, b.borrower_name, b.expected_return_date,
           e.id AS equipment_id, e.name AS equipment_name
    FROM borrowing_items bi
    JOIN borrowings b ON bi.borrowing_id = b.id
    JOIN equipment e ON bi.equipment_id = e.id
    WHERE b.status = 'Active' 
      
    ORDER BY b.expected_return_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// FETCH RETURNED ITEMS (excluding damaged)
$returnedItems = $conn->query("
    SELECT ri.id AS item_id, ri.quantity, ri.condition_out, ri.condition_in,
           ri.borrowing_no, ri.borrower_name, ri.return_date AS actual_return_date,
           ri.equipment_id, ri.equipment_name
    FROM returned_items ri
    WHERE ri.status = 'Returned' AND ri.condition_in != 'Damaged'
    ORDER BY ri.return_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// FETCH DAMAGED ITEMS
$damagedItems = $conn->query("
    SELECT ri.id AS item_id, ri.quantity, ri.condition_in, ri.borrowing_no,
           ri.borrower_name, ri.return_date AS actual_return_date,
           ri.equipment_id, ri.equipment_name,
           ri.damage_remarks AS remarks,
           rp.filename AS return_photo
    FROM returned_items ri
    LEFT JOIN return_photos rp ON rp.id = ri.return_photo_id
    WHERE ri.status = 'Damaged' OR ri.condition_in = 'Damaged'
    ORDER BY ri.return_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);

// Get success data if exists
$returnSuccess = $_SESSION['return_success'] ?? null;
if ($returnSuccess) {
    unset($_SESSION['return_success']);
}

// Get error if exists
$errorMessage = $_SESSION['error'] ?? null;
if ($errorMessage) {
    unset($_SESSION['error']);
}
?>


<link rel="stylesheet" href="/brgy_iba/css/admin_returns.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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

<!-- Error Toast -->
<?php if ($errorMessage): ?>
<div id="errorToast" style="position:fixed; top:80px; right:20px; background:#dc2626; color:white; padding:16px 24px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.3); z-index:99999; display:flex; align-items:center; gap:12px; animation: slideIn 0.3s ease;">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= htmlspecialchars($errorMessage) ?></span>
    <button onclick="this.parentElement.remove()" style="background:none; border:none; color:white; font-size:1.2rem; cursor:pointer; margin-left:8px;">&times;</button>
</div>
<script>
setTimeout(() => {
    const toast = document.getElementById('errorToast');
    if (toast) {
        toast.style.transition = 'opacity 0.3s ease';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }
}, 5000);
</script>
<?php endif; ?>

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
                    <tr data-item-id="<?= $r['item_id'] ?>">
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
                                <input type="hidden" name="return_photo_id">
                                <input type="hidden" name="damage_remarks">

                                <select name="condition_in" class="select-condition" required>
                                    <option value="">Select Condition</option>
                                    <option value="Good">✓ Good</option>
                                    <option value="Fair">⚠ Fair</option>
                                    <option value="Damaged">✗ Damaged</option>
                                </select>

                                <div class="file-input-wrapper" style="margin-top:8px;">
                                    <input type="file" name="return_photo" accept="image/*" style="font-size:0.85rem;">
                                    <small style="color:#64748b; display:block; margin-top:4px;">Required for damaged items</small>
                                </div>

                                <button type="submit" name="confirm_return" class="confirm-btn">
                                    <i class="fa-solid fa-check"></i>
                                    Confirm Return
                                </button>
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
                            <span class="<?= $r['condition_in'] === 'Fair' ? 'badge-warning' : 'badge-good' ?>">
                                <i class="fa-solid fa-<?= $r['condition_in'] === 'Fair' ? 'circle-exclamation' : 'circle-check' ?>"></i>
                                <?= htmlspecialchars($r['condition_in']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['actual_return_date'] ?? 'N/A') ?></td>
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
                            <span class="badge-damage">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <?= htmlspecialchars($r['condition_in']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['actual_return_date']) ?></td>
                        <td>
                            <?php if(!empty($r['remarks'])): ?>
                                <span style="font-size:0.85rem; color:#1e293b;">
                                    <?= htmlspecialchars($r['remarks']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#64748b; font-size:0.85rem;">No Remarks</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $photoPath = __DIR__ . '/../uploads/returns/' . $r['return_photo'];
                            ?>

                            <?php if (!empty($r['return_photo']) && file_exists($photoPath)): ?>
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

<!-- Success Modal -->
<div id="successModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:white; padding:32px; border-radius:16px; max-width:480px; width:100%; text-align:center; box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <div style="width:80px; height:80px; background:linear-gradient(135deg, #10b981, #059669); border-radius:50%; margin:0 auto 24px; display:flex; align-items:center; justify-content:center; animation:scaleIn 0.5s ease;">
            <i class="fa-solid fa-check" style="color:white; font-size:40px;"></i>
        </div>
        
        <h2 style="color:#059669; margin-bottom:12px; font-size:1.75rem;">Return Confirmed Successfully!</h2>
        
        <p style="color:#64748b; font-size:1rem; margin-bottom:24px;">The equipment has been returned and inventory updated.</p>
        
        <div style="background:#f1f5f9; padding:16px; border-radius:12px; margin-bottom:24px; text-align:left;">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="color:#64748b; font-size:0.9rem;">Borrow No:</span>
                <strong style="color:#1e293b;" id="modal-borrow-no"></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="color:#64748b; font-size:0.9rem;">Equipment:</span>
                <strong style="color:#1e293b;" id="modal-equipment"></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="color:#64748b; font-size:0.9rem;">Borrower:</span>
                <strong style="color:#1e293b;" id="modal-borrower"></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="color:#64748b; font-size:0.9rem;">Quantity:</span>
                <strong style="color:#1e293b;" id="modal-quantity"></strong>
            </div>
            <div style="display:flex; justify-content:space-between;">
                <span style="color:#64748b; font-size:0.9rem;">Condition:</span>
                <strong id="modal-condition"></strong>
            </div>
        </div>
        
        <button onclick="closeSuccessModal()" style="width:100%; padding:12px; border-radius:10px; border:none; background:linear-gradient(135deg, #10b981, #059669); color:white; font-size:1rem; font-weight:600; cursor:pointer; transition:transform 0.2s;">
            <i class="fa-solid fa-circle-check"></i> Done
        </button>
    </div>
</div>

<!-- Damaged Remarks Modal -->
<div id="damageRemarksModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:white; padding:24px; border-radius:16px; max-width:420px; width:100%; box-shadow:0 8px 32px rgba(0,0,0,0.3);">
        <h3 style="color:#dc2626; margin-bottom:8px;">
            <i class="fa-solid fa-triangle-exclamation"></i> Damaged Item Description
        </h3>
        <p style="font-size:0.9rem; color:#64748b; margin-bottom:12px;">
            Please provide a detailed description of the damage:
        </p>
        <textarea id="damageRemarksText" required style="width:100%; height:100px; padding:10px; border-radius:8px; border:2px solid #e2e8f0; resize:none; font-family:inherit;" placeholder="Describe the damage..."></textarea>
        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="button" onclick="closeDamageModal()" style="flex:1; padding:10px; border-radius:8px; border:none; background:#e5e7eb; cursor:pointer; font-weight:500;">
                Cancel
            </button>
            <button type="button" onclick="saveDamageAndSubmit()" style="flex:1; padding:10px; border-radius:8px; border:none; background:#dc2626; color:white; cursor:pointer; font-weight:500;">
                <i class="fa-solid fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<style>
@keyframes scaleIn {
    from { transform: scale(0); }
    to { transform: scale(1); }
}
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

</main>

<script>
// ================================
// TAB SWITCHING
// ================================
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

// ================================
// IMAGE MODAL
// ================================
const modal = document.getElementById('imageModal');
const modalImg = document.getElementById('modalImage');
const closeModalBtn = document.getElementById('closeModal');

document.querySelectorAll('.clickable-photo').forEach(img => {
    img.addEventListener('click', () => {
        modal.style.display = 'flex';
        modalImg.src = img.dataset.src;
    });
});

closeModalBtn?.addEventListener('click', () => modal.style.display = 'none');
modal?.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

// ================================
// SUCCESS MODAL
// ================================
function showSuccessModal(data) {
    document.getElementById('modal-borrow-no').textContent = data.borrowing_no;
    document.getElementById('modal-equipment').textContent = data.equipment_name;
    document.getElementById('modal-borrower').textContent = data.borrower_name;
    document.getElementById('modal-quantity').textContent = data.quantity;

    const cond = document.getElementById('modal-condition');
    cond.textContent = data.condition;
    cond.style.color =
        data.condition === 'Damaged' ? '#dc2626' :
        data.condition === 'Fair' ? '#f59e0b' : '#10b981';

    document.getElementById('successModal').style.display = 'flex';
}

function closeSuccessModal() {
    document.getElementById('successModal').style.display = 'none';
    location.reload(); // Refresh to update tables
}

<?php if ($returnSuccess): ?>
showSuccessModal(<?= json_encode($returnSuccess) ?>);
<?php endif; ?>

// ================================
// RETURN HANDLING
// ================================
let activeForm = null;

function resetButton(btn, html) {
    btn.disabled = false;
    btn.innerHTML = html;
}

function closeDamageModal() {
    document.getElementById('damageRemarksModal').style.display = 'none';
    document.getElementById('damageRemarksText').value = '';
    if (activeForm) {
        const submitBtn = activeForm.querySelector('button[type="submit"]');
        resetButton(submitBtn, '<i class="fa-solid fa-check"></i> Confirm Return');
    }
    activeForm = null;
}

document.querySelectorAll('.return-form').forEach(form => {
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const condition = form.querySelector('select[name="condition_in"]').value;
        const photoInput = form.querySelector('input[name="return_photo"]');
        const itemId = form.querySelector('input[name="item_id"]').value;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        if (!condition) {
            alert('Please select a condition.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        // ================================
        // GOOD / FAIR → DIRECT SUBMIT
        // ================================
        if (condition !== 'Damaged') {
            form.submit();
            return;
        }

        // ================================
        // DAMAGED → REQUIRE PHOTO & REMARKS
        // ================================
        if (!photoInput.files.length) {
            alert('Photo is REQUIRED for damaged items.');
            resetButton(submitBtn, originalText);
            return;
        }

        try {
            // Upload photo first
            const fd = new FormData();
            fd.append('return_photo', photoInput.files[0]);
            fd.append('item_id', itemId);

            const res = await fetch('/brgy_iba/admin/upload_return_photo.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });

            if (!res.ok) {
                throw new Error('Upload request failed with status ' + res.status);
            }

            const data = await res.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Upload failed');
            }

            // Save photo ID
            form.querySelector('input[name="return_photo_id"]').value = data.id;

            // Show damage remarks modal
            activeForm = form;
            document.getElementById('damageRemarksModal').style.display = 'flex';

        } catch (err) {
            console.error('Upload error:', err);
            alert('Failed to upload photo: ' + err.message);
            resetButton(submitBtn, originalText);
        }
    });
});

// ================================
// DAMAGE REMARKS MODAL
// ================================
function saveDamageAndSubmit() {
    if (!activeForm) {
        alert('No active form found.');
        return;
    }

    const remarks = document.getElementById('damageRemarksText').value.trim();
    if (!remarks) {
        alert('Damage remarks are required.');
        return;
    }

    // Save remarks to form
    activeForm.querySelector('input[name="damage_remarks"]').value = remarks;

    // Get the row for animation
    const row = activeForm.closest('tr');

    // Close modal
    document.getElementById('damageRemarksModal').style.display = 'none';
    document.getElementById('damageRemarksText').value = '';

    // Animate row removal
    if (row) {
        row.style.transition = 'opacity 0.3s ease';
        row.style.opacity = '0';
        setTimeout(() => row.remove(), 300);
    }

    // Submit the form
    activeForm.submit();
    activeForm = null;
}

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeDamageModal();
    }
});
</script>