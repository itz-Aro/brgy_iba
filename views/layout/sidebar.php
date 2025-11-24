<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['user']['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']); // For active link highlighting
?>

<!-- Sidebar CSS -->
<style>
/* Reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* SIDEBAR */
.sidebar {
    width: 250px;
    height: 100vh;
    background: linear-gradient(180deg, #1E3A8A, #3B82F6);
    padding: 30px 15px;
    color: white;
    position: fixed;
    left: 0;
    top: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    overflow-y: auto;
    box-shadow: 2px 0 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.sidebar img {
    height: 120px;
    width: 120px;
    margin-bottom: 15px;
    border-radius: 50%;
    object-fit: cover;
}

.sidebar h2 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 35px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    letter-spacing: 1px;
}

.sidebar a {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 12px 18px;
    text-decoration: none;
    color: white;
    background: rgba(255,255,255,0.08);
    margin-bottom: 12px;
    border-radius: 12px;
    font-weight: 500;
    transition: 0.3s;
}

.sidebar a i {
    margin-right: 12px;
    font-size: 18px;
}

.sidebar a:hover {
    background-color: rgba(255,255,255,0.25);
    transform: translateX(5px);
}

.sidebar a.active {
    background-color: rgba(255,255,255,0.35);
}

/* Responsive */
@media (max-width: 900px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        flex-direction: row;
        padding: 15px;
        justify-content: space-around;
    }
}
</style>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<!-- Sidebar HTML -->
<div class="sidebar">
    <img src="\brgy_iba\asset\logo.png" alt="Logo">
    <h2>Barangay Iba East</h2>

    <?php if ($role === 'admin'): ?>
        <a href="/brgy_iba/admin/dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>

        <a href="/barangay-inventory/admin/requests.php" class="<?= $currentPage == 'requests.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-check"></i> Approvals
        </a>
        <a href="\brgy_iba\reports\report.php" class="<?= $currentPage == 'reports.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> Reports
        </a>

    <?php elseif ($role === 'official'): ?>
        <a href="\brgy_iba\officials\dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>

        <a href="/brgy_iba/equipment/equipment.php" class="<?= $currentPage == 'equipment.php' ? 'active' : '' ?>">
            <i class="fas fa-box"></i> Manage Stocks
        </a>
    <?php endif; ?>
    
    <a href="/brgy_iba/profile.php" class="<?= $currentPage == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i> Profile
        </a>

    <a href="/brgy_iba/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>
