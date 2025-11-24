<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

// DB connection
$db = new Database();
$conn = $db->getConnection();

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);

// Handle form submission
$success = '';
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
        $photo = 'uploads/' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../public/' . $photo);
    }

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO equipment (code,name,description,category,total_quantity,available_quantity,condition,location,photo,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $res = $stmt->execute([$code,$name,$description,$category,$total_quantity,$available_quantity,$condition,$location,$photo,$created_by]);

    if($res) $success = "Equipment successfully added!";
    else $error = "Failed to add equipment. Please try again.";
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
.alert-success{ background:#c8e6c9; color:#2e7d32; padding:12px; border-radius:10px; margin-bottom:12px; }
.alert-error{ background:#ffcdd2; color:#b71c1c; padding:12px; border-radius:10px; margin-bottom:12px; }
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

    <?php if($success): ?>
      <div class="alert-success"><?= $success ?></div>
    <?php elseif($error): ?>
      <div class="alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label for="code">Equipment Code *</label>
        <input type="text" name="code" id="code" required placeholder="E.g. CH-001">
      </div>

      <div class="form-group">
        <label for="name">Name *</label>
        <input type="text" name="name" id="name" required placeholder="E.g. Chair">
      </div>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea name="description" id="description" rows="3" placeholder="Optional description"></textarea>
      </div>

      <div class="form-group">
        <label for="category">Category</label>
        <input type="text" name="category" id="category" placeholder="E.g. Furniture">
      </div>

      <div class="form-group">
        <label for="total_quantity">Total Quantity *</label>
        <input type="number" name="total_quantity" id="total_quantity" required min="0" value="0">
      </div>

      <div class="form-group">
        <label for="available_quantity">Available Quantity</label>
        <input type="number" name="available_quantity" id="available_quantity" min="0" value="0">
        <small>Leave blank to match total quantity</small>
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
        <input type="text" name="location" id="location" placeholder="E.g. Barangay Hall Storage">
      </div>

      <div class="form-group">
        <label for="photo">Photo</label>
        <input type="file" name="photo" id="photo" accept="image/*">
      </div>

      <button type="submit" class="submit-btn">Add Equipment</button>
    </form>
  </div>
</main>
