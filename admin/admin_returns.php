<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

AuthMiddleware::protect(['admin','officials']);

$db = new Database();
$conn = $db->getConnection();
$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/brgy_iba/uploads/returns/';

if (isset($_POST['confirm_return'])) {

    $item_id        = (int)($_POST['item_id'] ?? 0);
    $equipment_id   = (int)($_POST['equipment_id'] ?? 0);
    $condition      = $_POST['condition_in'] ?? '';
    $damageRemarks  = trim($_POST['damage_remarks'] ?? '');
    $returnPhotoId  = null;

    if (!$item_id || !$equipment_id || !$condition) {
        $_SESSION['error'] = 'Invalid return data.';
        header("Location: admin_returns.php");
        exit;
    }

    /**
     * =====================================================
     * 1️⃣ GET return_photo_id (preferred: uploaded earlier)
     * =====================================================
     */
    if (!empty($_POST['return_photo_id']) && is_numeric($_POST['return_photo_id'])) {
        $returnPhotoId = (int)$_POST['return_photo_id'];
    }

    /**
     * =====================================================
     * 2️⃣ FALLBACK: inline upload (optional but safe)
     * =====================================================
     */
    if (
        $returnPhotoId === null &&
        !empty($_FILES['return_photo']['name']) &&
        $_FILES['return_photo']['error'] === UPLOAD_ERR_OK
    ) {
        $uploadsDir = __DIR__ . '/../uploads/returns/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['return_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];

        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = 'Invalid image type.';
            header("Location: admin_returns.php");
            exit;
        }

        $safeName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $uploadsDir . $safeName;

        if (!move_uploaded_file($_FILES['return_photo']['tmp_name'], $dest)) {
            $_SESSION['error'] = 'Failed to save uploaded photo.';
            header("Location: admin_returns.php");
            exit;
        }

        $insPhoto = $conn->prepare("
            INSERT INTO return_photos (borrowing_item_id, filename)
            VALUES (?, ?)
        ");
        $insPhoto->execute([$item_id, $safeName]);

        $returnPhotoId = (int)$conn->lastInsertId();
    }

    /**
     * =====================================================
     * 3️⃣ ENFORCE PHOTO REQUIRED WHEN DAMAGED
     * =====================================================
     */
    if ($condition === 'Damaged' && empty($returnPhotoId)) {
        $_SESSION['error'] = 'Return photo is REQUIRED for damaged items.';
        header("Location: admin_returns.php");
        exit;
    }

    /**
     * =====================================================
     * 4️⃣ SAVE RETURN TRANSACTION (DB TRANSACTION SAFE)
     * =====================================================
     */
    try {
        $conn->beginTransaction();

        // Get borrowing info
        $stmt = $conn->prepare("
            SELECT bi.borrowing_id, bi.quantity, b.borrowing_no, u.fullname, e.name AS equipment_name
            FROM borrowing_items bi
            JOIN borrowings b ON bi.borrowing_id = b.id
            JOIN users u ON b.user_id = u.id
            JOIN equipment e ON bi.equipment_id = e.id
            WHERE bi.id = ?
        ");
        $stmt->execute([$item_id]);
        $borrow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$borrow) {
            throw new Exception('Borrowing item not found.');
        }

        // Insert returned item
        $insertReturn = $conn->prepare("
            INSERT INTO returned_items (
                borrowing_item_id,
                borrowing_id,
                borrowing_no,
                borrower_name,
                equipment_id,
                equipment_name,
                quantity,
                condition_in,
                damage_remarks,
                return_photo_id,
                returned_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $insertReturn->execute([
            $item_id,
            $borrow['borrowing_id'],
            $borrow['borrowing_no'],
            $borrow['fullname'],
            $equipment_id,
            $borrow['equipment_name'],
            $borrow['quantity'],
            $condition,
            $damageRemarks,
            $returnPhotoId
        ]);

        // Update borrowing item status
        $updBorrowItem = $conn->prepare("
            UPDATE borrowing_items
            SET status = 'Returned'
            WHERE id = ?
        ");
        $updBorrowItem->execute([$item_id]);

        // Update equipment quantities
        $updEquip = $conn->prepare("
            UPDATE equipment
            SET available_quantity = available_quantity + ?
            WHERE id = ?
        ");
        $updEquip->execute([$borrow['quantity'], $equipment_id]);

        $conn->commit();

        $_SESSION['success'] = 'Item successfully returned.';
        header("Location: admin_returns.php");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'Return failed: ' . $e->getMessage();
        header("Location: admin_returns.php");
        exit;
    }
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
   FETCH ITEMS MARKED DAMAGED WITH PHOTO + REMARKS (MATCH REMARKS BY BORROWER NAME)
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
FROM returned_items bi
JOIN borrowings b ON bi.borrowing_id = b.id
JOIN equipment e ON bi.equipment_id = e.id
LEFT JOIN return_photos rp ON rp.borrowing_item_id = bi.id

-- join latest 'Marked Damaged' maintenance log per borrower name
LEFT JOIN (
    SELECT m1.*
    FROM maintenance_logs m1
    INNER JOIN (
        SELECT returned_from, MAX(id) AS max_id
        FROM maintenance_logs
        WHERE action = 'Marked Damaged'
        GROUP BY returned_from
    ) m2 ON m1.id = m2.max_id
) ml ON ml.returned_from = b.borrower_name

WHERE bi.condition_in = 'Damaged'
ORDER BY b.actual_return_date DESC
");

$stmtDamaged->execute();
$damagedItems = $stmtDamaged->fetchAll(PDO::FETCH_ASSOC);




$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);
?>

<link rel="stylesheet" href="/brgy_iba/css/admin_returns.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>


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

                                <!-- REQUIRED hidden fields -->
                                <input type="hidden" name="return_photo_id">
                                <input type="hidden" name="damage_remarks">

                                <select name="condition_in" class="select-condition" required>
                                    <option value="">Select Condition</option>
                                    <option value="Good">✓ Good</option>
                                    <option value="Fair">⚠ Fair</option>
                                    <option value="Damaged">✗ Damaged</option>
                                </select>

                                <div class="file-input-wrapper">
                                    <input type="file" name="return_photo" accept="image/*">
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
<div id="damageRemarksModal"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6);
            z-index:99999; justify-content:center; align-items:center;">

  <div style="background:white; padding:24px; border-radius:16px; max-width:420px; width:100%;">

    <h3 style="color:#dc2626;">
      <i class="fa-solid fa-triangle-exclamation"></i> Damaged Item Description
    </h3>

    <p style="font-size:0.9rem; color:#64748b; margin-bottom:12px;">
      Please provide a description for the damage:
    </p>

    <textarea id="damageRemarksText" required
      style="width:100%; height:100px; padding:10px; border-radius:8px;
             border:2px solid #e2e8f0; resize:none;"></textarea>

    <div style="display:flex; gap:10px; margin-top:16px;">
      <button type="button" onclick="closeDamageModal()"
        style="flex:1; padding:10px; border-radius:8px; border:none; background:#e5e7eb;">
        Cancel
      </button>

      <!-- IMPORTANT: type="button" -->
      <button type="button" onclick="saveDamageAndSubmit()"
        style="flex:1; padding:10px; border-radius:8px; border:none;
               background:#dc2626; color:white;">
        Save Damage
      </button>
    </div>

  </div>
</div>


</main>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

// Image modal
const modal = document.getElementById('imageModal');
const modalImg = document.getElementById('modalImage');
const closeModal = document.getElementById('closeModal');

document.querySelectorAll('.clickable-photo').forEach(img => {
    img.addEventListener('click', () => {
        modal.style.display = 'flex';
        modalImg.src = img.dataset.src;
    });
});

closeModal.addEventListener('click', () => modal.style.display = 'none');
modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

// Damaged remarks + upload flow
let currentReturnForm = null;

document.querySelectorAll('.return-form').forEach(form => {
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const condition = this.querySelector('select[name="condition_in"]').value;
    const photoInput = this.querySelector('input[name="return_photo"]');
    const itemId = this.querySelector('input[name="item_id"]').value;

    // GOOD / FAIR → normal submit
    if (condition !== 'Damaged') {
      this.submit();
      return;
    }

    // DAMAGED → photo REQUIRED
    if (!photoInput.files.length) {
      alert('Photo is REQUIRED for damaged items.');
      return;
    }

    // Upload photo FIRST to separate endpoint
    const fd = new FormData();
    fd.append('return_photo', photoInput.files[0]);
    fd.append('item_id', itemId);

    try {
      const res = await fetch('/brgy_iba/admin/upload_return_photo.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });

      const data = await res.json();

      if (!data || !data.success) {
        alert(data?.error || 'Photo upload failed.');
        return;
      }

      // Save photo ID in ORIGINAL form
      this.querySelector('input[name="return_photo_id"]').value = data.id;

      // store reference so modal buttons can operate on the right form
      currentReturnForm = this;

      // open modal to capture damage remarks
      document.getElementById('damageRemarksModal').style.display = 'flex';
    } catch (err) {
      console.error('Upload error', err);
      alert('Photo upload error. Check console for details.');
    }
  });
});

function saveDamageAndSubmit() {
  if (!currentReturnForm) return;

  // Ensure hidden damage_remarks input exists
  let remarksInput = currentReturnForm.querySelector('input[name="damage_remarks"]');
  if (!remarksInput) {
    remarksInput = document.createElement('input');
    remarksInput.type = 'hidden';
    remarksInput.name = 'damage_remarks';
    currentReturnForm.appendChild(remarksInput);
  }
  remarksInput.value = document.getElementById('damageRemarksText').value.trim();

  // Ensure return_photo_id exists
  const photoIdInput = currentReturnForm.querySelector('input[name="return_photo_id"]');
  if (!photoIdInput || !photoIdInput.value) {
    alert('Photo upload is not complete. Please wait until the upload finishes before saving.');
    console.warn('Missing return_photo_id on form', photoIdInput);
    return;
  }

  // Ensure hidden confirm_return exists (so server sees it)
  let confirmInput = currentReturnForm.querySelector('input[name="confirm_return"]');
  if (!confirmInput) {
    confirmInput = document.createElement('input');
    confirmInput.type = 'hidden';
    confirmInput.name = 'confirm_return';
    confirmInput.value = '1';
    currentReturnForm.appendChild(confirmInput);
  } else {
    confirmInput.value = confirmInput.value || '1';
  }

  document.getElementById('damageRemarksModal').style.display = 'none';
  currentReturnForm.submit();
}

function closeDamageModal() {
  document.getElementById('damageRemarksModal').style.display = 'none';
  currentReturnForm = null;
}
</script>
