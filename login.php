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
<title>Barangay Login Portal</title>
<link rel="stylesheet" href="css/style.css">

<!-- EmailJS -->
<script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
<script>
(function() { emailjs.init("PLC-sqoygo8kqEgvG"); })();
</script>
</head>
<body>

<div class="login-box">
    <?php if ($alreadyLoggedIn): ?>
        <div class="logged-in-state">
            <p>You are already logged in as <strong><?php echo ucfirst($role); ?></strong></p>
            <a href="./logout.php" class="btn btn-danger">Logout</a>
        </div>
    <?php else: ?>
    <form id="loginForm">
        <div class="login-container">
            <div class="login-logo"><img src="asset/logo.png" alt="Barangay Logo"></div>
            <div class="login-text">
                <h1>Welcome Back</h1>
                <div id="msg" class="msg"></div>
            </div>

            <div class="login-input">
                <div class="input-field">
                    <input type="text" id="username" placeholder="Username" required>
                </div>
                <div class="input-field">
                    <input type="password" id="password" placeholder="Password" required>
                </div>
            </div>
            <button type="submit">Sign In</button>
            <p>
                <a href="#" id="forgotLink">Forgot Password?</a>
            </p>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- Multi-Step Reset Modal -->
<div id="resetModal" class="modal" style="display:none;">
  <div class="modal-content">

    <!-- Step 1: Enter Email -->
    <div class="step" id="stepEmail">
      <h2>Reset Password</h2>
      <div id="forgotMsg" class="msg"></div>
      <input type="email" id="forgotEmail" placeholder="Enter your email address">
      <button id="sendCodeBtn" class="btn-primary">Send Verification Code</button>
      <button id="closeModalEmail" class="btn-close">Cancel</button>
    </div>

    <!-- Step 2: Enter Verification Code -->
    <div class="step" id="stepCode" style="display:none;">
      <h2>Verify Code</h2>
      <div id="verifyMsg" class="msg"></div>
      <input type="text" id="verifyCode" placeholder="Enter 6-digit code" maxlength="6">
      <button id="verifyBtn" class="btn-primary">Verify Code</button>
      <button id="closeModalCode" class="btn-close">Cancel</button>
    </div>

    <!-- Step 3: Reset Password -->
    <div class="step" id="stepReset" style="display:none;">
      <h2>New Password</h2>
      <div id="resetMsg" class="msg"></div>
      <input type="password" id="newPass" placeholder="New Password">
      <input type="password" id="confirmPass" placeholder="Confirm New Password">
      <button id="resetBtn" class="btn-primary">Update Password</button>
      <button id="closeModalReset" class="btn-close">Cancel</button>
    </div>

  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const form = document.getElementById('loginForm');
    const msg = document.getElementById('msg');
    const loginContainer = document.querySelector('.login-container');

    function adjustContainer() {
        if(msg && msg.textContent.trim() !== '') { msg.style.display = 'block'; }
        else if(msg) { msg.style.display = 'none'; }
    }

    // Login form submit
    if(form) {
        form.addEventListener('submit', async function(e){
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            msg.textContent = ''; adjustContainer();

            if(!username || !password){ 
                msg.textContent='Please enter username and password'; 
                adjustContainer(); 
                return; 
            }

            try {
                const res = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const data = await res.json();

                if(data.success){
                    const role = data.user.role.toLowerCase();
                    if(role==='admin') window.location.href='./admin/dashboard.php';
                    else if(role==='official') window.location.href='./officials/dashboard.php';
                    else { msg.textContent='Role not recognized'; adjustContainer(); }
                } else {
                    msg.textContent = data.error || 'Invalid username or password';
                    adjustContainer();
                }
            } catch(err){
                console.error(err);
                msg.textContent = 'Connection error. Please try again.';
                adjustContainer();
            }
        });
    }

    // --- Forgot Password Modal Logic ---
    (function(){
        const resetModal = document.getElementById("resetModal");
        const stepEmail  = document.getElementById("stepEmail");
        const stepCode   = document.getElementById("stepCode");
        const stepReset  = document.getElementById("stepReset");

        const forgotEmail     = document.getElementById("forgotEmail");
        const forgotMsg       = document.getElementById("forgotMsg");
        const verifyCodeInput = document.getElementById("verifyCode");
        const verifyMsg       = document.getElementById("verifyMsg");
        const newPass         = document.getElementById("newPass");
        const confirmPass     = document.getElementById("confirmPass");
        const resetMsg        = document.getElementById("resetMsg");

        const forgotLink = document.getElementById("forgotLink");
        const closeModalEmail = document.getElementById("closeModalEmail");
        const closeModalCode  = document.getElementById("closeModalCode");
        const closeModalReset = document.getElementById("closeModalReset");
        const sendCodeBtn = document.getElementById("sendCodeBtn");
        const verifyBtn   = document.getElementById("verifyBtn");
        const resetBtn    = document.getElementById("resetBtn");

        if (!resetModal || !forgotLink) {
            console.warn("Forgot password elements not found");
            return;
        }

        function showForgotMsg(el, text = '', color = 'red', duration = 4000) {
            if(!el) return;
            el.textContent = text;
            el.style.color = color;
            if (text) {
                el.style.display = 'block';
                if (duration > 0) {
                    setTimeout(() => {
                        if (el.textContent === text) el.textContent = '';
                    }, duration);
                }
            } else {
                el.style.display = 'none';
            }
        }

        function resetForgotModalState() {
            stepEmail.style.display = 'block';
            stepCode.style.display  = 'none';
            stepReset.style.display = 'none';

            showForgotMsg(forgotMsg, '');
            showForgotMsg(verifyMsg, '');
            showForgotMsg(resetMsg, '');
            if (forgotEmail) forgotEmail.value = '';
            if (verifyCodeInput) verifyCodeInput.value = '';
            if (newPass) newPass.value = '';
            if (confirmPass) confirmPass.value = '';

            sessionStorage.removeItem('reset_code');
            sessionStorage.removeItem('reset_email');
        }

        forgotLink.addEventListener("click", function (e) {
            e.preventDefault();
            resetForgotModalState();
            resetModal.style.display = "flex";
            setTimeout(() => { if (forgotEmail) forgotEmail.focus(); }, 100);
        });

        [closeModalEmail, closeModalCode, closeModalReset].forEach(btn => {
            if (!btn) return;
            btn.addEventListener("click", function (e) {
                if(e) e.preventDefault();
                resetModal.style.display = "none";
                resetForgotModalState();
            });
        });

        sendCodeBtn.addEventListener("click", async function (e) {
            e.preventDefault();
            const email = (forgotEmail && forgotEmail.value || '').trim();
            showForgotMsg(forgotMsg, '', 'red');

            if (!email) {
                showForgotMsg(forgotMsg, 'Please enter your email address', 'red');
                return;
            }

            try {
                const res = await fetch("api/check_email.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ email })
                });

                if (!res.ok) throw new Error('Network error');
                const data = await res.json();

                if (!data.exists) {
                    showForgotMsg(forgotMsg, 'Email not found in our system', 'red');
                    return;
                }

                const code = String(Math.floor(100000 + Math.random() * 900000));
                sessionStorage.setItem('reset_code', code);
                sessionStorage.setItem('reset_email', email);

                await emailjs.send("service_yuq9rnq", "template_lejynq9", {
                    verification_code: code,
                    to_email: email
                });

                showForgotMsg(forgotMsg, 'Verification code sent! Check your email.', 'green');

                setTimeout(() => {
                    stepEmail.style.display = 'none';
                    stepCode.style.display = 'block';
                    if (verifyCodeInput) verifyCodeInput.focus();
                }, 800);

            } catch (err) {
                console.error('sendCode error', err);
                showForgotMsg(forgotMsg, 'Error sending code. Please try again.', 'red');
            }
        });

        verifyBtn.addEventListener("click", function (e) {
            e.preventDefault();
            const inputCode = (verifyCodeInput && verifyCodeInput.value || '').trim();
            const savedCode = sessionStorage.getItem('reset_code');

            showForgotMsg(verifyMsg, '', 'red');

            if (!inputCode) {
                showForgotMsg(verifyMsg, 'Please enter the verification code', 'red');
                return;
            }
            if (!savedCode) {
                showForgotMsg(verifyMsg, 'Session expired. Please request a new code.', 'red');
                return;
            }
            if (inputCode !== savedCode) {
                showForgotMsg(verifyMsg, 'Invalid verification code', 'red');
                return;
            }

            stepCode.style.display = 'none';
            stepReset.style.display = 'block';
            setTimeout(()=> { if (newPass) newPass.focus(); }, 100);
        });

        resetBtn.addEventListener("click", async function (e) {
            e.preventDefault();
            const np = (newPass && newPass.value || '').trim();
            const cp = (confirmPass && confirmPass.value || '').trim();
            showForgotMsg(resetMsg, '', 'red');

            if (!np || !cp) {
                showForgotMsg(resetMsg, 'Please fill in both password fields', 'red');
                return;
            }
            if (np !== cp) {
                showForgotMsg(resetMsg, 'Passwords do not match', 'red');
                return;
            }
            if (np.length < 6) {
                showForgotMsg(resetMsg, 'Password must be at least 6 characters', 'red');
                return;
            }

            const email = sessionStorage.getItem('reset_email');
            if (!email) {
                showForgotMsg(resetMsg, 'Session expired. Please start over.', 'red');
                return;
            }

            try {
                const res = await fetch("api/reset_password.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ email, password: np })
                });

                if (!res.ok) throw new Error('Network error');
                const data = await res.json();

                if (data.success) {
                    showForgotMsg(resetMsg, 'Password reset successful! Redirecting...', 'green', 3000);
                    setTimeout(() => {
                        resetModal.style.display = 'none';
                        resetForgotModalState();
                        window.location.href = 'login.php';
                    }, 1500);
                } else {
                    showForgotMsg(resetMsg, data.error || 'Failed to reset password', 'red');
                }
            } catch (err) {
                console.error('resetPassword error', err);
                showForgotMsg(resetMsg, 'Connection error. Please try again.', 'red');
            }
        });

    })();
});

</script>
</body>
</html>