<?php
class AuthMiddleware {
    public static function protect($allowedRoles = []) {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Function to show error message page
        $showErrorPage = function($message) {
            echo '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Access Restricted</title>
                <style>
                    body {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                        font-family: Poppins, sans-serif;
                        background-color: #f8f9fa;
                    }
                    .container {
                        text-align: center;
                        background-color: #fff;
                        padding: 40px;
                        border-radius: 12px;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    }
                    h1 {
                        color: #dc3545;
                        margin-bottom: 20px;
                    }
                    button {
                        font-family: Poppins, sans-serif;
                        padding: 12px 24px;
                        font-size: 16px;
                        background: linear-gradient(135deg, #2d7af5, #083d92);
                        color: #fff;
                        border: none;
                        border-radius: 8px;
                        cursor: pointer;
                        transition: 0.3s;
                    }
                    button:hover {
                        background-color: #0056b3;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>' . htmlspecialchars($message) . '</h1>
                    <button onclick="window.location.href=\'../login.php\'">Continue</button>
                </div>
            </body>
            </html>
            ';
            exit;
        };

        // Check if user is logged in
        if (!isset($_SESSION['user'])) {
            $showErrorPage("Login Required");
        }

        // Check if user's role is allowed
        $role = strtolower($_SESSION['user']['role']);
        if (!in_array($role, $allowedRoles)) {
            $showErrorPage("Access Denied");
        }
    }
}
?>
