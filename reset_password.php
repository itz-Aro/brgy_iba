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
<title>Reset Password</title>
<link rel="stylesheet" href="css/style.css">
<style>
.container { max-width: 400px; margin:50px auto; padding:20px; border:1px solid #ccc; border-radius:8px; }
input, button { width:100%; padding:10px; margin:10px 0; }
</style>
</head>
<body>
<div class="container">
    <h2>Reset Password</h2>
    <input type="password" id="newPass" placeholder="New Password">
    <input type="password" id="confirmPass" placeholder="Confirm Password">
    <button id="resetBtn">Reset Password</button>
    <div id="msg" style="color:red;"></div>
</div>

<script>
document.getElementById("resetBtn").onclick = async () => {
    const newPass = document.getElementById("newPass").value.trim();
    const confirmPass = document.getElementById("confirmPass").value.trim();
    const msg = document.getElementById("msg");

    if (!newPass || !confirmPass) {
        msg.textContent = "Fill in both password fields";
        return;
    }
    if (newPass !== confirmPass) {
        msg.textContent = "Passwords do not match";
        return;
    }

    const email = "<?php echo $_SESSION['reset_email']; ?>";

    try {
        const res = await fetch("api/reset_password.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email, password: newPass })
        });
        const data = await res.json();

        if (data.success) {
            msg.style.color = "green";
            msg.textContent = "Password reset successfully! Redirecting to login...";
            setTimeout(() => window.location.href = "login.php", 2000);
        } else {
            msg.textContent = data.error || "Failed to reset password";
        }
    } catch (err) {
        console.error(err);
        msg.textContent = "Connection error. Try again.";
    }
};
</script>
</body>
</html>
