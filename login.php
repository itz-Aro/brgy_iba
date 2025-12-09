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

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Poppins', sans-serif;
    /* background: linear-gradient(135deg, #b9c4f8ff 0%, #083d92 100%); */
    /* background-color: #083d92; */
    background: url('asset/login-bg.png') center center / cover no-repeat;
    min-height: 100vh;
    display: flex;
    justify-content: start;
    align-items: center;
    /* padding: 20px; */
}

.login-box {
    /* background: rgba(143, 35, 35, 0.95); */
    backdrop-filter: blur(10px);
    /* border-radius: 20px; */
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;

   /* ✅ SAME AS WIDTH */
    display: flex;
    align-items: center;
    justify-content: center;
    animation: slideUp 0.5s ease-out;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.login-container {
    width: 480px;
    /* height: 460px; */
    height:100vh;
    padding: 28px 60px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.login-logo {
    text-align: center;
    margin-bottom: -10px;
}

.login-logo img {
    width: 100px;
    height: 100px;
    object-fit: contain;
}

.login-text h1 {
    text-align: center;
    font-size: 25px;
    font-weight: 700;
    margin-bottom: 6px;
    background: linear-gradient(135deg, #0f62e8ff 0%, #083d92 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.msg {
    font-size: 12px;
    text-align: center;
    margin: 8px 0;
    padding: 6px;
    border-radius: 8px;
    min-height: 18px;
}

.msg:not(:empty) {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
}

.login-input {
    /* margin:-10px; */
}

.input-field {
    margin-bottom: 14px;
    width: 100%;
}

.input-field input {
    
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    border: 2px solid #e0e0e0;
    font-size: 14px;
    background: #f8f9fa;
}

.input-field input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
}

.input-field input::placeholder {
    color: #999;
}

button[type="submit"], .btn {
    width: 100%;
    padding: 10px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

button[type="submit"] {
    width: 100%;
    padding: 12px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    background: linear-gradient(135deg, #2d7af5, #083d92);
    color: white;
    margin-top: 4px;
}


button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

button[type="submit"]:active {
    transform: translateY(0);
}

.login-container p {
    text-align: center;
    margin-top: -10px;
    font-size: 15px;
}


.login-container a {
    color: #667eea;
    text-decoration: none;
}

.login-container a {
    color: #667eea;
    text-decoration: none;
}

/* Logged In State */
.logged-in-state {
    text-align: center;
}

.logged-in-state p {
    font-size: 16px;
    margin-bottom: 15px;
}

.logged-in-state strong {
    color: #667eea;
    font-size: 20px;
}

.btn-danger {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    width: 100%;
    padding: 12px;
    border-radius: 10px;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(245, 87, 108, 0.6);
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    padding: 40px;
    border-radius: 20px;
    width: 90%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    animation: modalSlideUp 0.4s ease-out;
}

@keyframes modalSlideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.9); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-content h2 {
    margin-bottom: 25px;
    color: #333;
    font-size: 28px;
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.modal-content input {
    width: 100%;
    padding: 14px 18px;
    margin: 12px 0;
    border-radius: 10px;
    border: 2px solid #e0e0e0;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.modal-content input:focus {
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    outline: none;
}

.modal-content button {
    width: 100%;
    padding: 14px;
    margin: 10px 0;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #ced6f8ff 0%, #2b05d4ff 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
}

.btn-close {
    background: #e0e0e0;
    color: #666;
}

.btn-close:hover {
    background: #d0d0d0;
}

.modal .msg {
    min-height: 24px;
    font-size: 14px;
    margin-bottom: 15px;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
}

.modal .msg:not(:empty) {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
}

/* Success message styling */
.modal .msg[style*="green"] {
    background: #e8f5e9 !important;
    color: #2e7d32 !important;
    border: 1px solid #a5d6a7 !important;
}

/* Responsive Design */
@media (max-width: 480px) {
    .login-container, .logged-in-state {
        padding: 40px 30px;
    }
    
    .modal-content {
        padding: 30px 25px;
    }
    
    .login-text h1 {
        font-size: 28px;
    }
}
</style>
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