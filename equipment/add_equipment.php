<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

// DB connection
$db = new Database();
$conn = $db->getConnection();

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);

// ⭐ AUTO GENERATE NEXT CODE
$getCode = $conn->query("SELECT code FROM equipment ORDER BY id DESC LIMIT 1");
$last = $getCode->fetch(PDO::FETCH_ASSOC);

if ($last) {
    // Remove non-numeric characters then increment
    $num = (int) preg_replace('/[^0-9]/', '', $last['code']);
    $nextNum = str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    $generatedCode = "EQ-" . $nextNum;
} else {
    // If no equipment yet
    $generatedCode = "EQ-001";
}

// Handle form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $total_quantity = $_POST['total_quantity'] ?? 0;
    $available_quantity = $_POST['available_quantity'] ?? $total_quantity;
    $condition = $_POST['condition'] ?? 'Good';
    $location = $_POST['location'] ?? '';
    $created_by = $_SESSION['user']['id'] ?? null;

    // Handle photo upload
    $photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo = uniqid() . '.' . $ext;

        $uploadPath = __DIR__ . '/equipment_img/' . $photo;

        if (!is_dir(__DIR__ . '/equipment_img')) {
            mkdir(__DIR__ . '/equipment_img', 0777, true);
        }

        move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath);
    }

    // Insert to database
    $stmt = $conn->prepare("INSERT INTO equipment 
        (code,name,description,category,total_quantity,available_quantity,`condition`,location,photo,created_by) 
        VALUES (?,?,?,?,?,?,?,?,?,?)");

    $res = $stmt->execute([$code,$name,$description,$category,$total_quantity,$available_quantity,$condition,$location,$photo,$created_by]);

    if ($res) {
        header("Location: equipment.php?updated=1");
        exit();
    } else {
        $error = "Failed to add equipment. Please try again.";
    }
}
?>

<link rel="stylesheet" href="/public/css/dashboard.css">
<style>
.content-wrap{ margin-left:430px; padding:22px; max-width:900px; margin-top:0px;}
.top-header{ background:#0d47a1; color:white; border-radius:12px; padding:28px 32px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 6px 14px rgba(0,0,0,0.12);}
.top-header .title{ font-size:44px; font-weight:800; letter-spacing:-0.5px; }
.admin-area{ display:flex; align-items:center; gap:18px; }
.greeting{ font-size:18px; opacity:0.95; }
.avatar{ width:56px; height:56px; border-radius:50%; background:white; color:#0d47a1; display:flex; justify-content:center; align-items:center; font-weight:700; font-size:18px; border:4px solid #cfe1ff; box-shadow:0 2px 6px rgba(0,0,0,0.15); }

.form-card{ background:white; padding:24px; border-radius:12px; box-shadow:0 6px 14px rgba(15,23,42,0.12);}
.form-card h2{ margin-top:0; color:#0d47a1; margin-bottom:18px;}
.form-group{ margin-bottom:16px; display:flex; flex-direction:column; }
.form-group label{ font-weight:600; margin-bottom:6px; }
.form-group input[type="text"], .form-group input[type="number"], .form-group textarea, .form-group select{ padding:10px 12px; border-radius:10px; border:1px solid #ccc; font-size:14px; width:100%; }
.form-group input[type="file"]{ font-size:14px; }
.submit-btn{ background:#1e73ff; color:white; border:none; padding:12px 18px; font-weight:700; border-radius:12px; cursor:pointer; transition:0.2s; }
.submit-btn:hover{ opacity:0.9; transform:scale(1.02); }
.alert-error{ background:#ffcdd2; color:#b71c1c; padding:12px; border-radius:10px; margin-bottom:12px; }
.cancel-btn{ display:inline-block; margin-left:10px; padding:10px 16px; background:#ccc; color:#333; border-radius:10px; text-decoration:none; font-weight:600; }
</style>

<main class="content-wrap">
  <div class="top-header">
    <div class="title">Add Equipment</div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= strtoupper($displayRole) ?>!</div>
      <div class="avatar">AD</div>
    </div>
  </div>

  <div class="form-card">
    <h2>New Equipment Details</h2>

    <?php if($error): ?>
      <div class="alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">

      <!-- ⭐ AUTO GENERATED CODE FIELD -->
      <div class="form-group">
        <!-- <label for="code">Equipment Code *</label> -->
        <input type="text" name="code" id="code" value="<?= $generatedCode ?>" readonly>
      </div>

      <div class="form-group">
        <label for="name">Name *</label>
        <input type="text" name="name" id="name" required placeholder="E.g. Equipment">
      </div>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea name="description" id="description" rows="3" placeholder="Optional description"></textarea>
      </div>

      <div class="form-group">
        <label for="category">Category</label>
        <select name="category" id="category" required>
          <option value="" disabled selected>Select Category</option>
          <option value="Furniture">Furniture</option>
          <option value="Electronics">Electronics</option>
          <option value="Sports Equipment">Sports Equipment</option>
          <option value="Laboratory Equipment">Laboratory Equipment</option>
          <option value="IT Equipment">IT Equipment</option>
          <option value="Office Supplies">Office Supplies</option>
          <option value="Tools">Tools</option>
          <option value="Others">Others</option>
        </select>
      </div>

      <div class="form-group">
        <label for="total_quantity">Total Quantity *</label>
        <input type="number" name="total_quantity" id="total_quantity" required min="0" value="0">
      </div>

      <div class="form-group">
        <label for="condition">Condition</label>
        <select name="condition" id="condition">
          <option value="Good" selected>Good</option>
          <option value="Fair">Fair</option>
          <option value="Damaged">Damaged</option>
        </select>
      </div>

      <div class="form-group">
        <label for="location">Location</label>
        <select name="location" id="location" required>
          <option value="Barangay Iba, Taal, Batangas" selected>Barangay Iba, Taal, Batangas</option>
        </select>
      </div>

      <div class="form-group">
        <label for="photo">Photo</label>
        <input type="file" name="photo" id="photo" accept="image/*">
      </div>

      <button type="submit" class="submit-btn">Add Equipment</button>
      <a href="equipment.php" class="cancel-btn">Cancel</a>

    </form>
  </div>
</main>
