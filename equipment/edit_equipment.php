<?php
session_start();
require_once __DIR__ . '/../views/layout/sidebar.php';
require_once __DIR__ . '/../config/Database.php';

// DB connection
$db = new Database();
$conn = $db->getConnection();

// Check if ID is missing
if (!isset($_GET['id'])) {
    die("Missing equipment ID.");
}

$id = $_GET['id'];
$success = '';
$error = '';

// Fetch the equipment
$stmt = $conn->prepare("SELECT * FROM equipment WHERE id = ?");
$stmt->execute([$id]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    die("Equipment not found.");
}

// Handle form update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = $_POST['code'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $total_quantity = $_POST['total_quantity'];
    $available_quantity = $_POST['available_quantity'];
    $condition = $_POST['condition'];
    $location = $_POST['location'];

   // PHOTO UPLOAD
$photo = $equipment['photo']; // keep old photo if no file is uploaded

if (!empty($_FILES['photo']['name'])) {
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $photo = uniqid() . '.' . $ext; 

    $uploadPath = __DIR__ . '/equipment_img/' . $photo;

    // Create folder if not exists
    if (!is_dir(__DIR__ . '/equipment_img')) {
        mkdir(__DIR__ . '/equipment_img', 0777, true);
    }

    move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath);

    // Store only filename in DB
    $photo = "equipment_img/" . $photo;
}


   $stmt = $conn->prepare("
    UPDATE equipment SET
        code = ?, name = ?, description = ?, category = ?, 
        total_quantity = ?, available_quantity = ?, 
        `condition` = ?, location = ?, photo = ?
    WHERE id = ?
");

$res = $stmt->execute([
    $code, $name, $description, $category,
    $total_quantity, $available_quantity,
    $condition, $location, $photo, $id
]);



 if ($res) {
    
        $success = "Equipment updated successfully!";
        header("Location: equipment.php?updated=1");
        // refresh data
        $stmt = $conn->prepare("SELECT * FROM equipment WHERE id = ?");
        $stmt->execute([$id]);
        $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "Failed to update equipment.";
    }

}

?>

<link rel="stylesheet" href="/public/css/dashboard.css">

<style>
.content-wrap{ margin-left:430px; padding:22px; max-width:900px; }
.top-header{ background:#0d47a1; color:white; border-radius:12px; padding:24px 30px;
 display:flex; justify-content:space-between; align-items:center; }
.title{ font-size:36px; font-weight:800; }

.form-card{ background:white; padding:24px; margin-top:20px;
 border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.12); }
.form-card h2{ margin:0 0 20px; color:#0d47a1; }

.form-group{ margin-bottom:16px; display:flex; flex-direction:column; }
.form-group input, .form-group textarea, .form-group select{
 padding:10px; border-radius:8px; border:1px solid #ccc; font-size:14px;
}

.submit-btn{ background:#1e73ff; color:white; padding:12px; border:none;
 font-weight:700; border-radius:10px; cursor:pointer; margin-top:10px; }
.alert-success{ background:#c8e6c9; color:#2e7d32; padding:12px; border-radius:10px; }
.alert-error{ background:#ffcdd2; color:#b71c1c; padding:12px; border-radius:10px; }
</style>

<main class="content-wrap">

    <div class="top-header">
        <div class="title">Edit Equipment</div>
    </div>

    <div class="form-card">
        <h2>Update Details</h2>

        <?php if($success): ?>
            <div class="alert-success"><?= $success ?></div>
        <?php elseif($error): ?>
            <div class="alert-error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label>Equipment Code *</label>
                <input type="text" name="code" required value="<?= $equipment['code'] ?>">
            </div>

            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" required value="<?= $equipment['name'] ?>">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?= $equipment['description'] ?></textarea>
            </div>

            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" value="<?= $equipment['category'] ?>">
            </div>

            <div class="form-group">
                <label>Total Quantity *</label>
                <input type="number" name="total_quantity" min="0" required value="<?= $equipment['total_quantity'] ?>">
            </div>

            <div class="form-group">
                <label>Available Quantity *</label>
                <input type="number" name="available_quantity" min="0" required value="<?= $equipment['available_quantity'] ?>">
            </div>

            <div class="form-group">
                <label>Condition</label>
                <select name="condition">
                    <option value="Good"    <?= $equipment['condition'] == 'Good' ? 'selected' : '' ?>>Good</option>
                    <option value="Fair"    <?= $equipment['condition'] == 'Fair' ? 'selected' : '' ?>>Fair</option>
                    <option value="Damaged" <?= $equipment['condition'] == 'Damaged' ? 'selected' : '' ?>>Damaged</option>
                </select>
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?= $equipment['location'] ?>">
            </div>

            <div class="form-group">
                <label>Photo</label>
                <input type="file" name="photo" accept="image/*">

                <?php if (!empty($equipment['photo'])): ?>
                    <br><img src="/public/<?= $equipment['photo'] ?>" 
                             width="120" style="margin-top:10px; border-radius:8px;">
                <?php endif; ?>
            </div>

            <button class="submit-btn">Update Equipment</button>
             <a href="equipment.php" class="cancel-btn">Cancel</a>
        </form>
    </div>
</main>
