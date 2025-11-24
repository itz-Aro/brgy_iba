<?php
session_start();
require_once __DIR__ . '/../config/Database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: /public/login.php");
    exit;
}

// Get equipment ID from URL
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: equipment.php");
    exit;
}

try {
    // DB connection
    $db = new Database();
    $conn = $db->getConnection();

    // Optional: fetch photo to delete file from server
    $stmt = $conn->prepare("SELECT photo FROM equipment WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($equipment) {
        // Delete the record
        $stmt = $conn->prepare("DELETE FROM equipment WHERE id = :id");
        $stmt->execute(['id' => $id]);

        // Optionally, delete photo file if exists and not default
        if ($equipment['photo'] && file_exists(__DIR__ . '/../' . $equipment['photo']) && $equipment['photo'] != 'public/imgs/default.png') {
            unlink(__DIR__ . '/../' . $equipment['photo']);
        }
    }

    header("Location: equipment.php?msg=deleted");
    exit;

} catch (PDOException $e) {
    echo "Error deleting equipment: " . $e->getMessage();
}
