<?php
session_start();
$alreadyLoggedIn = false;
$role = '';
if (isset($_SESSION['user'])) {
    $alreadyLoggedIn = true;
    $role = strtolower($_SESSION['user']['role']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Logins</title>
<link rel="stylesheet" href="css\style.css">

<!-- EmailJS -->
<script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
<script>
  (function() { emailjs.init("PLC-sqoygo8kqEgvG"); })();
</script>

<style>
/* Base Styles */


/* Multi-step Modal */
.modal { position:fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.6); display:flex; justify-content:center; align-items:center; z-index:1000; }
.modal-content { background:#fff; padding:30px; border-radius:12px; width: 360px; box-shadow: 0 8px 25px rgba(0,0,0,0.2); text-align:center; animation: fadeIn 0.3s ease-in-out; }
.modal-content h2 { margin-bottom:20px; color:#333; }
.modal-content input { width:100%; padding:12px; margin:10px 0; border-radius:8px; border:1px solid #ccc; font-size:14px; transition:0.3s; }
.modal-content input:focus { border-color:#9b59b6; box-shadow:0 0 5px rgba(155,89,182,0.5); outline:none; }
.modal-content button { width:100%; padding:12px; margin:10px 0; border:none; border-radius:8px; font-size:16px; cursor:pointer; transition:0.3s; }
.modal-content button:hover { opacity:0.9; }
.btn-primary { background:#9b59b6; color:#fff; }
.btn-close { background:#f44336; color:#fff; }
.msg { min-height:20px; font-size:14px; margin-bottom:10px; }

/* Fade animation */
@keyframes fadeIn { from {opacity:0; transform: translateY(-20px);} to {opacity:1; transform: translateY(0);} }

/* Responsive */
@media(max-width:400px){ .modal-content{ width:90%; padding:20px; } }
</style>
</head>
<body>

<div class="login-box">
<?php if ($alreadyLoggedIn): ?>
    <p>You are already logged in as <strong><?php echo ucfirst($role); ?></strong>.</p>
    <a href="./logout.php" class="btn btn-danger logout-btn">Logout</a>
<?php else: ?>
    <form id="loginForm">
        <div class="login-container">
            <div class="login-logo"><img src="asset/logo.png" alt="Logo"></div>
            <div class="login-text"><h1>Login</h1><div id="msg" class="msg"></div></div>
            <div class="login-input">
                <div class="input-field">
                    <input type="text" id="username" placeholder="Username" required>
                </div>
                <div class="input-field">
                    <input type="password" id="password" placeholder="Password" required>
                </div>
            </div>
            <button type="submit">Login</button>
            <p style="text-align:center;"><a href="#" id="forgotLink">Forgot Password?</a></p>
        </div>
    </form>
<?php endif; ?>
</div>

<!-- Multi-Step Reset Modal -->
<div id="resetModal" class="modal" style="display:none;">
  <div class="modal-content">

    <!-- Step 1: Enter Email -->
    <div class="step" id="stepEmail">
      <h2>Forgot Password</h2>
      <div id="forgotMsg" class="msg"></div>
      <input type="email" id="forgotEmail" placeholder="Enter your email">
      <button id="sendCodeBtn" class="btn-primary">Send Verification Code</button>
      <button id="closeModalEmail" class="btn-close">Close</button>
    </div>

    <!-- Step 2: Enter Verification Code -->
    <div class="step" id="stepCode" style="display:none;">
      <h2>Verify Code</h2>
      <div id="verifyMsg" class="msg"></div>
      <input type="text" id="verifyCode" placeholder="Enter 6-digit code">
      <button id="verifyBtn" class="btn-primary">Verify</button>
      <button id="closeModalCode" class="btn-close">Close</button>
    </div>

    <!-- Step 3: Reset Password -->
    <div class="step" id="stepReset" style="display:none;">
      <h2>Reset Password</h2>
      <div id="resetMsg" class="msg"></div>
      <input type="password" id="newPass" placeholder="New Password">
      <input type="password" id="confirmPass" placeholder="Confirm Password">
      <button id="resetBtn" class="btn-primary">Reset Password</button>
      <button id="closeModalReset" class="btn-close">Close</button>
    </div>

  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){

    const form = document.getElementById('loginForm');
    const msg = document.getElementById('msg');
    const loginContainer = document.querySelector('.login-container');

    // Adjust container height dynamically
    function adjustContainer() {
        // Show message only if there is text
        if(msg.textContent.trim() !== '') {
            msg.style.display = 'block';
        } else {
            msg.style.display = 'none';
        }

        // Reset and measure height
        loginContainer.style.height = 'auto';
        const totalHeight = loginContainer.scrollHeight;
        loginContainer.style.height = totalHeight + 'px';
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();

        msg.textContent = '';
        adjustContainer(); // compact container initially

        if (!username || !password) {
            msg.textContent = 'Enter username and password';
            adjustContainer(); // expand container
            return;
        }

        try {
            const res = await fetch('api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await res.json();

            if (data.success) {
                const role = data.user.role.toLowerCase();
                if (role === 'admin') window.location.href = './admin/dashboard.php';
                else if (role === 'official') window.location.href = './officials/dashboard.php';
                else {
                    msg.textContent = 'Role not recognized';
                    adjustContainer();
                }
            } else {
                msg.textContent = data.error || 'Invalid credentials';
                adjustContainer();
            }
        } catch (err) {
            console.error(err);
            msg.textContent = 'Connection error. Please try again.';
            adjustContainer();
        }
    });

    // --- Forgot Password modal code remains unchanged ---
    const resetModal = document.getElementById("resetModal");
    const stepEmail = document.getElementById("stepEmail");
    const stepCode = document.getElementById("stepCode");
    const stepReset = document.getElementById("stepReset");

    const forgotEmail = document.getElementById("forgotEmail");
    const forgotMsg = document.getElementById("forgotMsg");
    const verifyCodeInput = document.getElementById("verifyCode");
    const verifyMsg = document.getElementById("verifyMsg");
    const newPass = document.getElementById("newPass");
    const confirmPass = document.getElementById("confirmPass");
    const resetMsg = document.getElementById("resetMsg");

    document.getElementById("forgotLink").onclick = () => {
        resetModal.style.display = "flex";
        stepEmail.style.display = "block";
        stepCode.style.display = "none";
        stepReset.style.display = "none";
        forgotMsg.textContent = "";
        forgotEmail.value = "";
    };

    document.getElementById("closeModalEmail").onclick = 
    document.getElementById("closeModalCode").onclick = 
    document.getElementById("closeModalReset").onclick = () => resetModal.style.display="none";

    document.getElementById("sendCodeBtn").onclick = async () => {
        const email = forgotEmail.value.trim();
        forgotMsg.style.color="red";
        if(!email){ forgotMsg.textContent="Enter your email"; return; }

        try {
            const res = await fetch("api/check_email.php", {
                method:"POST",
                headers:{"Content-Type":"application/json"},
                body: JSON.stringify({email})
            });
            const data = await res.json();
            if(!data.exists){ forgotMsg.textContent="Email not found"; return; }

            const code = Math.floor(100000 + Math.random()*900000);
            sessionStorage.setItem("reset_email", email);
            sessionStorage.setItem("reset_code", code);

            await emailjs.send("service_yuq9rnq","template_lejynq9",{verification_code:code, to_email:email});
            forgotMsg.style.color="green";
            forgotMsg.textContent="Code sent!";

            setTimeout(()=>{
                stepEmail.style.display="none";
                stepCode.style.display="block";
                verifyCodeInput.value="";
                verifyMsg.textContent="";
            },800);

        } catch(err){ console.error(err); forgotMsg.textContent="Error sending code"; }
    };

    document.getElementById("verifyBtn").onclick = () => {
        const inputCode = verifyCodeInput.value.trim();
        const sessionCode = sessionStorage.getItem("reset_code");
        verifyMsg.style.color="red";

        if(!inputCode){ verifyMsg.textContent="Enter the code"; return; }
        if(inputCode!==sessionCode){ verifyMsg.textContent="Invalid code"; return; }

        stepCode.style.display="none";
        stepReset.style.display="block";
        newPass.value=""; confirmPass.value=""; resetMsg.textContent="";
    };

    document.getElementById("resetBtn").onclick = async () => {
        resetMsg.style.color="red";
        const np = newPass.value.trim();
        const cp = confirmPass.value.trim();
        if(!np || !cp){ resetMsg.textContent="Fill both fields"; return; }
        if(np!==cp){ resetMsg.textContent="Passwords do not match"; return; }

        const email = sessionStorage.getItem("reset_email");

        try{
            const res = await fetch("api/reset_password.php",{
                method:"POST",
                headers:{"Content-Type":"application/json"},
                body: JSON.stringify({email,password:np})
            });
            const data = await res.json();
            if(data.success){
                resetMsg.style.color="green";
                resetMsg.textContent="Password reset successfully! Redirecting...";
                setTimeout(()=>window.location.href="login.php",2000);
            } else { resetMsg.textContent = data.error || "Failed to reset password"; }
        } catch(err){ console.error(err); resetMsg.textContent="Connection error"; }
    };

});

</script>
</body>
</html>
