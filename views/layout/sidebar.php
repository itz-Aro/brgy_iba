
<?php
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';
AuthMiddleware::protect(['admin', 'official']); // make sure roles match your DB

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user role and current page
$role = $_SESSION['user']['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']); // For active link highlighting
?>

<link rel="stylesheet" href="/brgy_iba/css/sidebar.css">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<!-- Sidebar HTML -->
<div class="sidebar">
    <img src="/brgy_iba/asset/logo.png" alt="Logo">
    <h2>Barangay Iba East</h2>

    <?php if ($role === 'admin'): ?>
        <a href="/brgy_iba/admin/dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>

        <a href="/brgy_iba/approval/requests_pending.php" class="<?= $currentPage == 'requests_pending.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-check"></i> Approvals
        </a>

        <a href="/brgy_iba/admin/admin_returns.php" class="<?= $currentPage == 'admin_returns.php' ? 'active' : '' ?>">
            <i class="fas fa-arrow-turn-up"></i> Returned Items
        </a>


        <a href="/brgy_iba/reports/report.php" class="<?= $currentPage == 'report.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> Reports
        </a>

       

    <?php elseif ($role === 'official'): ?>
        <a href="/brgy_iba/officials/dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>

        <a href="/brgy_iba/equipment/equipment.php" class="<?= $currentPage == 'equipment.php' ? 'active' : '' ?>">
            <i class="fas fa-box"></i> Manage Stocks
        </a>

        <a href="/brgy_iba/equipment/maintenance.php" class="<?= $currentPage == 'maintenance.php' ? 'active' : '' ?>">
            <i class="fas fa-tools"></i> Maintenance
        </a>
    <?php endif; ?>
    
    <a href="/brgy_iba/profile.php" class="<?= $currentPage == 'profile.php' ? 'active' : '' ?>">
        <i class="fas fa-user"></i> Profile
    </a>

    <a href="/brgy_iba/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>
