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
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    font-family: 'poppins', sans-serif;
    min-height: 100vh;
}

.content-wrap {
    margin-left: 250px;
    padding: 40px 30px;
    max-width: 1400px;
}

.profile-container {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

/* HEADER SECTION */
.profile-header {
    background: linear-gradient(135deg, #0288d1 0%, #01579b 100%);
    padding: 50px 40px;
    display: flex;
    align-items: center;
    gap: 30px;
    position: relative;
    overflow: hidden;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 500px;
    height: 500px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.avatar-container {
    position: relative;
    z-index: 2;
}

.avatar-preview {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid #fff;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease;
}

.avatar-preview:hover {
    transform: scale(1.05);
}

.profile-header-info {
    color: #fff;
    z-index: 2;
    position: relative;
}

.profile-header h1 {
    font-size: 36px;
    margin-bottom: 8px;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.profile-username {
    font-size: 16px;
    opacity: 0.9;
    font-weight: 500;
}

/* MESSAGES */
.message-container {
    padding: 40px;
}

.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.4s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: linear-gradient(135deg, #bcfbb8ff 0%, #57f5b6ff 100%);
    color: #042c16ff;
    border-left: 4px solid #d7f5dcff;
}

.alert-error {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    color: #fff;
    border-left: 4px solid #fee140;
}

.alert i {
    font-size: 20px;
    flex-shrink: 0;
}

/* GRID LAYOUT */
.profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 30px;
    padding: 0 40px 40px;
}

.profile-card {
    background: #fff;
    padding: 30px;
    border-radius: 16px;
    border: 1px solid #f0f0f0;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.profile-card:hover {
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
    transform: translateY(-4px);
    border-color: #e0e0e0;
}

.profile-card h3 {
    font-size: 18px;
    color: #333;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.profile-card i {
    font-size: 20px;
    background: linear-gradient(135deg, #0288d1 0%, #01579b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.profile-card form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.profile-card input,
.profile-card textarea {
    padding: 12px 16px;
    border: 2px solid #f0f0f0;
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    transition: all 0.3s ease;
    background: #fafafa;
}

.profile-card input:focus,
.profile-card textarea:focus {
    outline: none;
    border-color: #0288d1;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(2, 136, 209, 0.1);
}

.profile-card textarea {
    resize: vertical;
    min-height: 100px;
}

.profile-card button {
    padding: 12px 20px;
    background: linear-gradient(135deg, #0288d1 0%, #01579b 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 8px;
}

.profile-card button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(2, 136, 209, 0.4);
}

.profile-card button:active {
    transform: translateY(0);
}

/* FILE INPUT STYLING */
input[type="file"] {
    padding: 12px;
    border: 2px dashed #0288d1;
    border-radius: 10px;
    cursor: pointer;
    background: linear-gradient(135deg, rgba(2, 136, 209, 0.05) 0%, rgba(1, 87, 155, 0.05) 100%);
    transition: all 0.3s ease;
}

input[type="file"]:hover {
    border-color: #01579b;
    background: linear-gradient(135deg, rgba(2, 136, 209, 0.1) 0%, rgba(1, 87, 155, 0.1) 100%);
}

input[type="file"]::file-selector-button {
    padding: 8px 16px;
    background: linear-gradient(135deg, #0288d1 0%, #01579b 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    margin-right: 10px;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .profile-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .content-wrap {
        margin-left: 0;
        padding: 20px 15px;
    }

    .profile-header {
        flex-direction: column;
        text-align: center;
        padding: 40px 20px;
    }

    .profile-header h1 {
        font-size: 28px;
    }

    .profile-grid {
        grid-template-columns: 1fr;
        padding: 0 20px 20px;
    }

    .message-container {
        padding: 20px;
    }

    .alert {
        font-size: 14px;
    }

    .profile-card {
        padding: 20px;
    }
}
</style>

<main class="content-wrap">
    <div class="profile-container">

        <!-- HEADER WITH GRADIENT BACKGROUND -->
        <div class="profile-header">
            <div class="avatar-container">
                <img 
                    src="Avatar/profile.png" 
                    class="avatar-preview" 
                    alt="User Avatar"
                >
            </div>
            <div class="profile-header-info">
                <h1><?= htmlspecialchars($user['fullname']) ?></h1>
                <p class="profile-username">@<?= htmlspecialchars($user['username']) ?></p>
            </div>
        </div>

        <!-- MESSAGES -->
        <div class="message-container">
            <?php if(isset($successMsg)) { ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($successMsg) ?></span>
                </div>
            <?php } ?>
            <?php if(isset($errorMsg)) { ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($errorMsg) ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="profile-grid">

           

            <!-- EDIT PROFILE -->
            <div class="profile-card">
                <h3><i class="fa-solid fa-user"></i> Edit Profile</h3>
                <form method="post">
                    <input type="text" name="fullname" placeholder="Full Name" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                    <input type="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($user['email']) ?>">
                    <input type="text" name="contact" placeholder="Contact Number" value="<?= htmlspecialchars($user['contact']) ?>">
                    <textarea name="address" placeholder="Street Address"><?= htmlspecialchars($user['address']) ?></textarea>
                    <button type="submit" name="update_profile">
                        <i class="fa-solid fa-check"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- CHANGE USERNAME -->
            <div class="profile-card">
                <h3><i class="fa-solid fa-user-tag"></i> Change Username</h3>
                <form method="post">
                    <input type="text" name="username" placeholder="New Username" value="<?= htmlspecialchars($user['username']) ?>" required>
                    <button type="submit" name="change_username">
                        <i class="fa-solid fa-pen-to-square"></i> Update Username
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