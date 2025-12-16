<?php
session_start();
require_once __DIR__ . '../config/Database.php';

if (!isset($_SESSION['user'])) {
    header("Location: /public/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$userId = $_SESSION['user']['id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ===============================
// 1. Update profile info
// ===============================
if (isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);

    $stmt = $conn->prepare("UPDATE users SET fullname=?, email=?, contact=?, address=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$fullname, $email, $contact, $address, $userId]);

    $user['fullname'] = $fullname;
    $user['email'] = $email;
    $user['contact'] = $contact;
    $user['address'] = $address;
    $_SESSION['user']['fullname'] = $fullname;

    $successMsg = "Profile updated successfully!";
}

// ===============================
// 2. Change avatar
// ===============================
if (isset($_POST['change_avatar']) && isset($_FILES['avatar'])) {
    $avatar = $_FILES['avatar'];
    $ext = strtolower(pathinfo($avatar['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($ext, $allowed)) {

        // FILENAME
        $avatarName = 'avatar_' . $userId . '_' . time() . '.' . $ext;

        // CORRECT UPLOAD DIRECTORY
        $uploadDir = __DIR__ . '/../Avatar/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // TARGET FULL PATH
        $target = $uploadDir . $avatarName;

        if (move_uploaded_file($avatar['tmp_name'], $target)) {
            $conn->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$avatarName, $userId]);
            $user['avatar'] = $avatarName;
            $successMsg = "Avatar updated successfully!";
        } else {
            $errorMsg = "Failed to upload avatar.";
        }
    } else {
        $errorMsg = "Invalid file type. Only JPG, PNG, GIF allowed.";
    }
}

// ===============================
// 3. Change username
// ===============================
if (isset($_POST['change_username'])) {
    $newUsername = trim($_POST['username']);

    $check = $conn->prepare("SELECT id FROM users WHERE username=? AND id<>?");
    $check->execute([$newUsername, $userId]);

    if ($check->rowCount() > 0) {
        $errorMsg = "Username already taken!";
    } else {
        $conn->prepare("UPDATE users SET username=?, updated_at=NOW() WHERE id=?")
             ->execute([$newUsername, $userId]);

        $user['username'] = $newUsername;
        $_SESSION['user']['username'] = $newUsername;

        $successMsg = "Username updated!";
    }
}

// ===============================
// 4. Change password
// ===============================
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $errorMsg = "Current password is incorrect!";
    } elseif ($new !== $confirm) {
        $errorMsg = "New passwords do not match!";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $conn->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?")
             ->execute([$hash, $userId]);

        $successMsg = "Password changed successfully!";
    }
}

?>

<?php require_once __DIR__ . '../views/layout/sidebar.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.profile-container {
    max-width: 900px;
    margin: 30px auto;
    padding: 25px;
    background: #fdfdfd;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
}

.avatar-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #0d47a1;
}

.profile-header h2 {
    font-size: 28px;
    color: #0d47a1;
    margin: 0;
}

.success { color: #1b8b1b; margin-bottom: 15px; font-weight: bold; }
.error { color: #d32f2f; margin-bottom: 15px; font-weight: bold; }

.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.profile-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.profile-card h3 {
    margin-bottom: 15px;
    color: #0d47a1;
}

.profile-card input,
.profile-card textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-family:'Poppins', sans-serif;
}

.profile-card button {
    padding: 10px 18px;
    background: #0d47a1;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.2s;
}

.profile-card button:hover {
    background: #084096;
}

.upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

@media (max-width: 768px) {
    .profile-grid { grid-template-columns: 1fr; }
}

.content-wrap {
    margin-left:250px;
    padding:22px;
    max-width:1500px;
}
</style>

<main class="content-wrap">
    <div class="profile-container">

        <!-- HEADER WITH DEFAULT AVATAR FALLBACK -->
        <div class="profile-header">
            <img 
                src="Avatar/profile.png" 
                class="avatar-preview" 
                alt="Avatar"
            >
            <h2><?= htmlspecialchars($user['fullname']) ?></h2>
        </div>

        <!-- MESSAGES -->
        <?php if(isset($successMsg)) echo "<p class='success'>$successMsg</p>"; ?>
        <?php if(isset($errorMsg)) echo "<p class='error'>$errorMsg</p>"; ?>

        <div class="profile-grid">

            <!-- CHANGE AVATAR -->
            <div class="profile-card">
                <h3><i class="fa-solid fa-image"></i> Change Avatar</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="avatar" accept="image/*">
                    <button type="submit" name="change_avatar" class="upload-btn">
                        <i class="fa-solid fa-upload"></i> Upload
                    </button>
                </form>
            </div>

            <!-- EDIT PROFILE -->
            <div class="profile-card">
                <h3><i class="fa-solid fa-user"></i> Edit Profile</h3>
                <form method="post">
                    <input type="text" name="fullname" placeholder="Full Name" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                    <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($user['email']) ?>">
                    <input type="text" name="contact" placeholder="Contact" value="<?= htmlspecialchars($user['contact']) ?>">
                    <textarea name="address" placeholder="Address"><?= htmlspecialchars($user['address']) ?></textarea>
                    <button type="submit" name="update_profile"><i class="fa-solid fa-pen"></i> Update Profile</button>
                </form>
            </div>

            <!-- CHANGE USERNAME -->
            <div class="profile-card">
                <h3><i class="fa-solid fa-user-tag"></i> Change Username</h3>
                <form method="post">
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                    <button type="submit" name="change_username">
                        <i class="fa-solid fa-pen"></i> Change Username
                    </button>
                </form>
            </div>

            <!-- CHANGE PASSWORD -->
            <div class="profile-card">
                <h3><i class="fa-solid fa-lock"></i> Change Password</h3>
                <form method="post">
                    <input type="password" name="current_password" placeholder="Current Password" required>
                    <input type="password" name="new_password" placeholder="New Password" required>
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                    <button type="submit" name="change_password">
                        <i class="fa-solid fa-key"></i> Change Password
                    </button>
                </form>
            </div>

        </div>
    </div>
</main>
