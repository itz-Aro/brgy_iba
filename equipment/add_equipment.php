<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

// DB connection
$db = new Database();
$conn = $db->getConnection();

$role = $_SESSION['user']['role'] ?? 'Admin';
$displayRole = htmlspecialchars($role);
$userName = htmlspecialchars($_SESSION['user']['name'] ?? 'User');

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
$success = '';
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
        header("Location: equipment.php?added=1");
        exit();
    } else {
        $error = "Failed to add equipment. Please try again.";
    }
}
?>

<link rel="stylesheet" href="/public/css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<style>
:root{
  --blue:#0d47a1;
  --blue-light:#1976d2;
  --blue-dark:#083d97;
  --light-gray:#f5f7fa;
  --card-shadow:0 6px 20px rgba(15,23,42,0.08);
  --hover-shadow:0 8px 24px rgba(15,23,42,0.12);
  --radius:14px;
  --success:#4caf50;
  --danger:#f44336;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Poppins', sans-serif;
  background-color: var(--light-gray);
  color: #2c3e50;
}

.content-wrap{ 
  margin-left:410px; 
  padding:28px; 
  max-width:900px; 
  margin-top:0;
  min-height:100vh;
}

.top-header{ 
  background-color:var(--blue); 
  color:white; 
  border-radius:16px; 
  padding:32px 40px; 
  display:flex; 
  justify-content:space-between; 
  align-items:center; 
  box-shadow:0 8px 20px rgba(13,71,161,0.2);
  margin-bottom:28px;
}

.top-header .title{ 
  font-size:38px; 
  font-weight:800; 
  letter-spacing:-0.8px;
  display:flex;
  align-items:center;
  gap:12px;
}

.title-icon {
  font-size: 32px;
}

.admin-area{ 
  display:flex; 
  align-items:center; 
  gap:20px; 
}

.greeting{ 
  font-size:18px; 
  opacity:0.95;
  font-weight:500;
}

.avatar{ 
  width:58px; 
  height:58px; 
  border-radius:50%; 
  background:white; 
  color:var(--blue); 
  display:flex; 
  justify-content:center; 
  align-items:center; 
  font-weight:700; 
  font-size:20px; 
  border:4px solid rgba(255,255,255,0.3); 
  box-shadow:0 4px 12px rgba(0,0,0,0.15);
  transition: transform 0.2s;
}

.avatar:hover {
  transform: scale(1.05);
}

.form-card{ 
  background:white; 
  padding:32px; 
  border-radius:var(--radius); 
  box-shadow:var(--card-shadow);
  transition:all 0.2s;
}

.form-card:hover {
  box-shadow:var(--hover-shadow);
}

.form-card h2{ 
  margin-top:0; 
  color:var(--blue); 
  margin-bottom:24px;
  font-size:28px;
  font-weight:800;
  display:flex;
  align-items:center;
  gap:12px;
}

.form-card h2 i {
  font-size:26px;
}

.section-divider {
  border:none;
  border-top:2px solid #e0e7ef;
  margin:24px 0;
}

.section-title {
  font-size:16px;
  font-weight:700;
  color:var(--blue);
  margin-bottom:16px;
  text-transform:uppercase;
  letter-spacing:0.5px;
  display:flex;
  align-items:center;
  gap:8px;
}

.section-title i {
  font-size:18px;
}

.form-row {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:20px;
  margin-bottom:20px;
}

.form-group{ 
  margin-bottom:20px; 
  display:flex; 
  flex-direction:column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label{ 
  font-weight:600; 
  margin-bottom:8px;
  color:#334155;
  font-size:14px;
  display:flex;
  align-items:center;
  gap:6px;
}

.form-group label i {
  color:var(--blue);
}

.required::after {
  content:"*";
  color:var(--danger);
  margin-left:4px;
}

.form-group input[type="text"], 
.form-group input[type="number"], 
.form-group textarea, 
.form-group select{ 
  padding:12px 16px; 
  border-radius:10px; 
  border:2px solid #e0e7ef; 
  font-size:15px; 
  width:100%;
  transition:all 0.2s;
  background:#f8fafc;
}

.form-group input:focus, 
.form-group textarea:focus, 
.form-group select:focus{ 
  outline:none;
  border-color:var(--blue);
  background:white;
  box-shadow:0 0 0 4px rgba(13,71,161,0.1);
}

.form-group input[readonly] {
  background:#e0e7ef;
  color:#64748b;
  cursor:not-allowed;
  font-weight:700;
}

.form-group textarea {
  resize:vertical;
  min-height:100px;
  font-family:inherit;
}

.file-input-wrapper {
  position:relative;
  overflow:hidden;
  display:inline-block;
  width:100%;
}

.file-input-wrapper input[type=file] {
  position:absolute;
  left:-9999px;
}

.file-input-label {
  display:flex;
  align-items:center;
  gap:12px;
  padding:12px 16px;
  background:#f8fafc;
  border:2px dashed #cbd5e1;
  border-radius:10px;
  cursor:pointer;
  transition:all 0.2s;
  font-size:15px;
  color:#64748b;
}

.file-input-label:hover {
  border-color:var(--blue);
  background:white;
}

.file-input-label i {
  font-size:20px;
}

.file-name {
  margin-top:8px;
  font-size:13px;
  color:var(--blue);
  font-weight:600;
  display:flex;
  align-items:center;
  gap:6px;
}

.image-preview {
  margin-top:12px;
  display:none;
}

.image-preview img {
  max-width:200px;
  max-height:200px;
  border-radius:10px;
  border:2px solid #e0e7ef;
  object-fit:cover;
}

.form-actions {
  display:flex;
  gap:12px;
  margin-top:32px;
  padding-top:24px;
  border-top:2px solid #e0e7ef;
}

.submit-btn{ 
  background:linear-gradient(135deg, var(--success) 0%, #66bb6a 100%); 
  color:white; 
  border:none; 
  padding:14px 28px; 
  font-weight:700; 
  border-radius:12px; 
  cursor:pointer; 
  transition:all 0.2s;
  font-size:16px;
  display:inline-flex;
  align-items:center;
  gap:8px;
  box-shadow:0 4px 12px rgba(76,175,80,0.3);
}

.submit-btn:hover{ 
  transform:translateY(-2px);
  box-shadow:0 6px 16px rgba(76,175,80,0.4);
}

.cancel-btn{ 
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:14px 28px; 
  background:#e0e7ef; 
  color:#334155; 
  border-radius:12px; 
  text-decoration:none; 
  font-weight:700;
  transition:all 0.2s;
  font-size:16px;
}

.cancel-btn:hover {
  background:#cbd5e1;
  transform:translateY(-1px);
}

.alert-error{ 
  background:linear-gradient(135deg, #ffcdd2 0%, #ef9a9a 100%); 
  color:#b71c1c; 
  padding:16px 20px; 
  border-radius:12px; 
  margin-bottom:20px;
  font-weight:600;
  display:flex;
  align-items:center;
  gap:12px;
  box-shadow:0 4px 12px rgba(183,28,28,0.2);
}

.alert-error i {
  font-size:24px;
}

.info-box {
  background:#e3f2fd;
  border-left:4px solid var(--blue);
  padding:16px;
  border-radius:8px;
  margin-bottom:20px;
  font-size:14px;
  color:#1565c0;
}

.info-box strong {
  display:flex;
  align-items:center;
  gap:8px;
  margin-bottom:4px;
}

.info-box strong i {
  font-size:16px;
}

.helper-text {
  font-size:12px;
  color:#64748b;
  margin-top:4px;
  font-style:italic;
  display:flex;
  align-items:center;
  gap:4px;
}

.helper-text i {
  font-size:10px;
}

@media (max-width: 768px) {
  .content-wrap {
    margin-left:0;
    padding:16px;
  }
  
  .top-header {
    flex-direction:column;
    gap:16px;
    text-align:center;
  }
  
  .form-row {
    grid-template-columns:1fr;
  }
  
  .form-card {
    padding:20px;
  }
}
</style>

<main class="content-wrap">
  <div class="top-header">
    <div class="title">
      <i class="fas fa-plus-circle title-icon"></i>
      Add Equipment
    </div>
    <div class="admin-area">
      <div class="greeting">Hello, <?= $userName ?>!</div>
      <div class="avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
    </div>
  </div>

  <div class="form-card">
    <h2>
      <i class="fas fa-box"></i>
      New Equipment Details
    </h2>

    <?php if($error): ?>
      <div class="alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?= $error ?>
      </div>
    <?php endif; ?>

    <div class="info-box">
      <strong>
        <i class="fas fa-barcode"></i>
        Auto-Generated Code: <?= $generatedCode ?>
      </strong>
      This equipment code is automatically generated and cannot be changed.
    </div>

    <form action="" method="POST" enctype="multipart/form-data" id="equipmentForm">

      <!-- BASIC INFORMATION -->
      <div class="section-title">
        <i class="fas fa-info-circle"></i>
        Basic Information
      </div>

      <!-- ⭐ AUTO GENERATED CODE FIELD (HIDDEN) -->
      <input type="hidden" name="code" value="<?= $generatedCode ?>">

      <div class="form-row">
        <div class="form-group">
          <label for="name" class="required">
            <i class="fas fa-tag"></i>
            Equipment Name
          </label>
          <input type="text" name="name" id="name" required 
                 placeholder="e.g., Laptop, Projector, Desk">
          <div class="helper-text">
            <i class="fas fa-circle"></i>
            Enter the name of the equipment
          </div>
        </div>

        <div class="form-group">
          <label for="category" class="required">
            <i class="fas fa-folder-open"></i>
            Category
          </label>
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
      </div>

      <div class="form-group full-width">
        <label for="description">
          <i class="fas fa-align-left"></i>
          Description
        </label>
        <textarea name="description" id="description" rows="3" 
                  placeholder="Enter additional details about the equipment (optional)"></textarea>
        <div class="helper-text">
          <i class="fas fa-circle"></i>
          Provide any additional information that might be helpful
        </div>
      </div>

      <hr class="section-divider">

      <!-- QUANTITY & CONDITION -->
      <div class="section-title">
        <i class="fas fa-calculator"></i>
        Quantity & Condition
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="total_quantity" class="required">
            <i class="fas fa-boxes"></i>
            Total Quantity
          </label>
          <input type="number" name="total_quantity" id="total_quantity" 
                 required min="1" value="1">
          <div class="helper-text">
            <i class="fas fa-circle"></i>
            How many units do you have?
          </div>
        </div>

        <div class="form-group">
          <label for="condition" class="required">
            <i class="fas fa-clipboard-check"></i>
            Condition
          </label>
          <select name="condition" id="condition">
            <option value="Good" selected>Good</option>
            <option value="Fair">Fair</option>
            <option value="Damaged">Damaged</option>
          </select>
        </div>
      </div>

      <hr class="section-divider">

      <!-- LOCATION -->
      <div class="section-title">
        <i class="fas fa-map-marker-alt"></i>
        Location
      </div>

      <div class="form-group">
        <label for="location" class="required">
          <i class="fas fa-warehouse"></i>
          Storage Location
        </label>
        <select name="location" id="location" required>
          <option value="Barangay Iba, Taal, Batangas" selected>Barangay Iba, Taal, Batangas</option>
        </select>
        <div class="helper-text">
          <i class="fas fa-circle"></i>
          Where is this equipment stored?
        </div>
      </div>

      <hr class="section-divider">

      <!-- PHOTO UPLOAD -->
      <div class="section-title">
        <i class="fas fa-camera"></i>
        Equipment Photo
      </div>

      <div class="form-group">
        <label for="photo">
          <i class="fas fa-image"></i>
          Upload Photo (Optional)
        </label>
        <div class="file-input-wrapper">
          <input type="file" name="photo" id="photo" accept="image/*" onchange="previewImage(this)">
          <label for="photo" class="file-input-label">
            <i class="fas fa-cloud-upload-alt"></i>
            <span id="fileLabel">Choose a photo or drag and drop</span>
          </label>
        </div>
        <div class="file-name" id="fileName"></div>
        <div class="image-preview" id="imagePreview">
          <img id="previewImg" src="" alt="Preview">
        </div>
        <div class="helper-text">
          <i class="fas fa-circle"></i>
          Supported formats: JPG, PNG, GIF (Max 5MB)
        </div>
      </div>

      <!-- FORM ACTIONS -->
      <div class="form-actions">
        <button type="submit" class="submit-btn">
          <i class="fas fa-check"></i>
          Add Equipment
        </button>
        <a href="equipment.php" class="cancel-btn">
          <i class="fas fa-times"></i>
          Cancel
        </a>
      </div>

    </form>
  </div>
</main>

<script>
// Image preview
function previewImage(input) {
  const fileNameEl = document.getElementById('fileName');
  const fileLabel = document.getElementById('fileLabel');
  const preview = document.getElementById('imagePreview');
  const previewImg = document.getElementById('previewImg');
  
  if (input.files && input.files[0]) {
    const file = input.files[0];
    fileNameEl.innerHTML = '<i class="fas fa-file-image"></i> ' + file.name;
    fileLabel.textContent = 'Photo selected';
    
    const reader = new FileReader();
    reader.onload = function(e) {
      previewImg.src = e.target.result;
      preview.style.display = 'block';
    }
    reader.readAsDataURL(file);
  }
}

// Form validation
document.getElementById('equipmentForm').addEventListener('submit', function(e) {
  const totalQty = parseInt(document.getElementById('total_quantity').value);
  
  if (totalQty < 1) {
    e.preventDefault();
    alert('Total quantity must be at least 1');
    return false;
  }
});
</script>