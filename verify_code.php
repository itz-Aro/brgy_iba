<?php
session_start();

// Only allow access if reset_email exists
if (!isset($_SESSION['reset_email'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Code</title>
<link rel="stylesheet" href="css/style.css">
<style>
.container { max-width: 400px; margin:50px auto; padding:20px; border:1px solid #ccc; border-radius:8px; }
input, button { width:100%; padding:10px; margin:10px 0; }
</style>
</head>
<body>
<div class="container">
    <h2>Enter Verification Code</h2>
    <input type="text" id="code" placeholder="6-digit code">
    <button id="verifyBtn">Verify</button>
    <div id="msg" style="color:red;"></div>
</div>

<script>
document.getElementById("verifyBtn").onclick = () => {
    const inputCode = document.getElementById("code").value.trim();
    const msg = document.getElementById("msg");

    const sessionCode = sessionStorage.getItem("reset_code");

    if (!inputCode) {
        msg.textContent = "Enter the code";
        return;
    }
    if (inputCode !== sessionCode) {
        msg.textContent = "Invalid code";
        return;
    }

    // Code correct → redirect to reset_password page
    window.location.href = "reset_password.php";
};
</script>
</body>
</html>
