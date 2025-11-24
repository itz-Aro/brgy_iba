<?php
session_start();
class AuthMiddleware {
    public static function protect($allowedRoles = []) {
        if (!isset($_SESSION['user'])) {
            header('Location: /login.php');
            exit;
        }
        $role = strtolower($_SESSION['user']['role']);
        if (!in_array($role, $allowedRoles)) {
            echo "<h2>ACCESS DENIED</h2>";
            exit;
        }
    }
}
?>
