<?php
require_once __DIR__ . '/../config/Database.php';
$db = new Database();
$conn = $db->getConnection();
$equip = $conn->query("SELECT id,name,photo,available_quantity FROM equipment ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brgy Iba East Equipment Request</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { 
    margin:0; 
    padding:0; 
    box-sizing:border-box; 
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background: linear-gradient(135deg, #96a7f5ff 0%, #4166fdff 100%);
    padding:20px;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
    background-size: 50px 50px;
    animation: float 20s linear infinite;
    pointer-events: none;
}

@keyframes float {
    0% { transform: translate(0, 0); }
    100% { transform: translate(50px, 50px); }
}

.form-container {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    width:100%;
    max-width:580px;
    padding:2.5rem;
    border-radius:24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.5) inset;
    max-height:90vh;
    overflow-y:auto;
    animation:slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    z-index: 10;
}

@keyframes slideUp { 
    from {opacity:0; transform:translateY(40px) scale(0.95);} 
    to {opacity:1; transform:translateY(0) scale(1);} 
}

.form-container::-webkit-scrollbar {
    width: 8px;
}

.form-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.form-container::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 10px;
}

img.logo {
    width:120px;
    height: 120px;
    border-radius:50%;
    display:block;
    margin:0 auto 20px;
    border: 5px solid #667eea;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    object-fit: cover;
}

h1 {
    text-align:center;
    font-size:1.75rem;
    margin-bottom:0.5rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight:700;
    letter-spacing: -0.5px;
}

.subtitle {
    text-align: center;
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 2rem;
    font-weight: 500;
}

label { 
    font-size:0.875rem; 
    margin:16px 0 8px; 
    display:block; 
    color: #334155;
    font-weight: 600;
    letter-spacing: 0.2px;
}

input,textarea {
    width:100%;
    padding:14px 16px;
    border-radius:12px;
    border:2px solid #e2e8f0;
    font-size:0.95rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: #f8fafc;
    font-family: 'Inter', sans-serif;
}

input:focus,textarea:focus {
    border-color:#667eea; 
    box-shadow:0 0 0 4px rgba(102, 126, 234, 0.1);
    background: white;
    outline: none;
}

input::placeholder, textarea::placeholder {
    color: #94a3b8;
}

.row { 
    display:grid; 
    grid-template-columns: 1fr 1fr;
    gap:12px; 
}

.button {
    width:100%; 
    padding:16px; 
    margin-top:24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color:white; 
    font-size:1rem;
    border:none; 
    border-radius:12px; 
    font-weight:600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor:pointer;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.button:hover { 
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
}

.button:active {
    transform: translateY(0);
}

.hidden { display:none; }

/* EQUIPMENT SECTION */
.search-box {
    padding:14px 16px 14px 44px;
    border-radius:12px;
    border:2px solid #e2e8f0;
    width:100%;
    margin-bottom:20px;
    font-size: 0.95rem;
    background: #f8fafc url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%2394a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>') no-repeat 14px center;
    transition: all 0.3s;
}

.search-box:focus {
    border-color: #667eea;
    background-color: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    outline: none;
}

.equipment-grid {
    display: grid;
    gap: 16px;
    margin-bottom: 20px;
}

.equipment-item {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius:16px;
    padding:16px;
    display:flex;
    align-items:center;
    gap: 16px;
    border:2px solid #e2e8f0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.equipment-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
    opacity: 0;
    transition: opacity 0.3s;
}

.equipment-item:hover {
    border-color: #667eea;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
    transform: translateY(-2px);
}

.equipment-item:hover::before {
    opacity: 1;
}

.equipment-item img {
    width:70px;
    height:70px;
    border-radius:12px;
    object-fit:cover;
    border:3px solid #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    position: relative;
    z-index: 1;
}

.equipment-info {
    flex: 1;
    position: relative;
    z-index: 1;
}

.equipment-name {
    font-weight: 600;
    font-size: 1rem;
    color: #1e293b;
    margin-bottom: 4px;
}

.equipment-status {
    font-size: 0.85rem;
    font-weight: 500;
}

.status-available {
    color: #059669;
}

.status-unavailable {
    color: #dc2626;
}

.quantity {
    display:flex;
    align-items:center;
    gap:8px;
    position: relative;
    z-index: 1;
}

.quantity button {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color:white;
    border:none;
    width: 36px;
    height: 36px;
    border-radius:10px;
    cursor:pointer;
    font-size: 1.1rem;
    font-weight: 600;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.quantity button:hover:not(:disabled) {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.quantity button:disabled {
    background: #cbd5e1;
    cursor: not-allowed;
    box-shadow: none;
}

.quantity input {
    width:60px;
    height: 36px;
    text-align:center;
    font-size:1rem;
    font-weight: 600;
    border:2px solid #cbd5e1;
    border-radius:10px;
    background: white;
    padding: 0;
}

/* STEP 3 MODAL */
#step3 {
    position: fixed;
    top:0; left:0; width:100%; height:100%;
    display:flex;
    justify-content:center;
    align-items:center;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(8px);
    visibility:hidden;
    opacity:0;
    transition:0.4s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
}

#step3.show {
    visibility:visible;
    opacity:1;
}

#step3 .success {
    background:white;
    padding:3rem 2rem;
    border-radius:24px;
    text-align:center;
    width:90%;
    max-width:440px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlide 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalSlide {
    from { opacity: 0; transform: scale(0.9) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

#step3 .icon-wrapper {
    width: 100px;
    height: 100px;
    margin: 0 auto 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
}

#step3 i {
    font-size:3rem;
    color:white;
}

#step3 h2 {
    font-size: 1.75rem;
    margin-bottom: 12px;
    color: #1e293b;
    font-weight: 700;
}

#step3 p {
    color: #64748b;
    font-size: 1rem;
    margin-bottom: 28px;
    line-height: 1.6;
}

#themeLoader {
    position: fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(255,255,255,.95);
    backdrop-filter: blur(10px);
    display:flex;
    justify-content:center;
    align-items:center;
    flex-direction:column;
    visibility:hidden; opacity:0;
    transition:.3s ease-in-out;
    z-index:3000;
}

#themeLoader.show {
    visibility:visible;
    opacity:1;
}

.loader-wrapper {
    display:flex;
    flex-direction:column;
    align-items:center;
}

.loader-circle {
    width:80px;
    height:80px;
    border:6px solid #e2e8f0;
    border-top-color:#667eea;
    border-right-color:#764ba2;
    border-radius:50%;
    animation:spin 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
}

#themeLoader p {
    margin-top:20px;
    font-size:1.1rem;
    font-weight:600;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

@keyframes spin {
    from{transform:rotate(0deg);}
    to{transform:rotate(360deg);}
}

.progress-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.progress-step {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #94a3b8;
    position: relative;
    transition: all 0.3s;
}

.progress-step.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.progress-line {
    width: 60px;
    height: 3px;
    background: #e2e8f0;
    border-radius: 2px;
}

@media (max-width: 600px) {
    .form-container {
        padding: 2rem 1.5rem;
    }
    
    h1 {
        font-size: 1.5rem;
    }
    
    .row {
        grid-template-columns: 1fr;
    }
}

</style>
</head>
<body>

<!-- STEP 1 -->
<div class="form-container" id="step1">
    <img src="../asset/logo.png" class="logo">
    <h1>Equipment Request Form</h1>
    <p class="subtitle">Barangay Iba East</p>

    <div class="progress-indicator">
        <div class="progress-step active">1</div>
        <div class="progress-line"></div>
        <div class="progress-step">2</div>
    </div>

    <form id="personalForm">
        <label>Full Name</label>
        <input type="text" name="fullname" placeholder="Enter your full name" required>

        <label>Email Address</label>
        <input type="email" name="email" placeholder="your.email@example.com" required>

        <label>Contact Number</label>
        <input type="text" name="contact" placeholder="+63 XXX XXX XXXX" required>

        <label>Complete Address</label>
        <input type="text" name="address" placeholder="Street, Barangay, City" required>

        <label>Borrowing Period</label>
        <div class="row">
            <div>
                <input type="date" name="start_date" required>
                <small style="color: #64748b; font-size: 0.75rem;">Start Date</small>
            </div>
            <div>
                <input type="date" name="end_date" required>
                <small style="color: #64748b; font-size: 0.75rem;">End Date</small>
            </div>
        </div>

        <label>Additional Remarks (Optional)</label>
        <textarea name="notes" rows="3" placeholder="Any special instructions or notes..."></textarea>

        <button type="button" class="button" onclick="nextStep()">
            Continue
            <i class="fa-solid fa-arrow-right"></i>
        </button>
    </form>
</div>

<!-- STEP 2 -->
<div class="form-container hidden" id="step2">
    <h1>Select Equipment</h1>
    <p class="subtitle">Choose items and quantity</p>

    <div class="progress-indicator">
        <div class="progress-step">1</div>
        <div class="progress-line"></div>
        <div class="progress-step active">2</div>
    </div>

    <input class="search-box" type="text" placeholder="Search for equipment..." onkeyup="filterEquipment(this)">

    <form id="equipmentForm">
        <div class="equipment-grid">
            <?php foreach($equip as $row): 
                $isAvailable = $row['available_quantity'] > 0;
            ?>
            <div class="equipment-item" data-name="<?= strtolower($row['name']) ?>">
                <img src="../equipment/equipment_img/<?= $row['photo'] ?>" alt="<?= $row['name'] ?>">
                
                <div class="equipment-info">
                    <div class="equipment-name"><?= $row['name'] ?></div>
                    <div class="equipment-status <?= $isAvailable ? 'status-available' : 'status-unavailable' ?>">
                        <?= $isAvailable ? "<i class='fa-solid fa-circle-check'></i> Available: {$row['available_quantity']}" : "<i class='fa-solid fa-circle-xmark'></i> Out of Stock" ?>
                    </div>
                </div>

                <div class="quantity">
                    <button type="button" onclick="changeQty(this,-1)" <?= !$isAvailable ? 'disabled' : '' ?>>−</button>
                    <input type="hidden" name="item[]" value="<?= $row['id'] ?>">
                    <input type="number" name="qty[]" value="0" min="0" max="<?= $row['available_quantity'] ?>" <?= !$isAvailable ? 'disabled' : '' ?>>
                    <button type="button" onclick="changeQty(this,1)" <?= !$isAvailable ? 'disabled' : '' ?>>+</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="button" onclick="submitForm()">
            <i class="fa-solid fa-paper-plane"></i>
            Submit Request
        </button>
    </form>
</div>

<!-- STEP 3 -->
<div id="step3">
    <div class="success">
        <div class="icon-wrapper">
            <i class="fa-solid fa-check"></i>
        </div>
        <h2>Request Submitted!</h2>
        <p>You will receive SMS/Email updates regarding the approval of your equipment request.</p>
        <button class="button" onclick="resetForm()">
            <i class="fa-solid fa-rotate-right"></i>
            Submit Another Request
        </button>
    </div>
</div>

<!-- LOADING SCREEN -->
<div id="themeLoader">
    <div class="loader-wrapper">
        <div class="loader-circle"></div>
        <p>Processing your request...</p>
    </div>
</div>

<script>
function nextStep(){
    const step1 = document.getElementById('personalForm');

    if(!step1.checkValidity()){
        step1.reportValidity();
        return;
    }

    document.getElementById("themeLoader").classList.add("show");

    setTimeout(() => {
        document.getElementById('step1').classList.add('hidden');
        document.getElementById('step2').classList.remove('hidden');

        setTimeout(() => {
            document.getElementById("themeLoader").classList.remove("show");
        }, 400);
    }, 600);
}

function changeQty(btn,val){
    const input = btn.parentElement.querySelector("input[type='number']");
    let value = parseInt(input.value) + val;
    const max = parseInt(input.getAttribute('max'));

    if(value < 0) value = 0;
    if(value > max) value = max;

    input.value = value;
}

function filterEquipment(input){
    const query = input.value.toLowerCase();
    document.querySelectorAll('.equipment-item').forEach(item=>{
        item.style.display = item.dataset.name.includes(query) ? 'flex' : 'none';
    });
}

function submitForm() {
    const quantities = document.querySelectorAll('#equipmentForm input[type="number"]');
    let hasSelection = false;
    quantities.forEach(q => { if(parseInt(q.value) > 0) hasSelection = true; });

    if(!hasSelection){
        alert("Please select at least one equipment.");
        return;
    }

    document.getElementById("themeLoader").classList.add("show");

    const formData = new FormData(document.getElementById('personalForm'));
    const items = [];

    document.querySelectorAll('.equipment-item').forEach(box => {
        const qty = box.querySelector('input[type="number"]').value;
        const id = box.querySelector('input[type="hidden"]').value;
        if(parseInt(qty) > 0){
            items.push({id:id, qty:qty});
        }
    });

    formData.append('items', JSON.stringify(items));

    fetch('submit_request.php', { method:'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        setTimeout(() => {
            document.getElementById("themeLoader").classList.remove("show");

            if(data.status === 'success'){
                document.getElementById('step2').classList.add('hidden');
                document.getElementById('step3').classList.add('show');
            } else {
                alert('Error: ' + data.message);
            }
        }, 1200);
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred.');
        document.getElementById("themeLoader").classList.remove("show");
    });
}

function resetForm(){
    document.getElementById('step3').classList.remove('show');
    document.getElementById("themeLoader").classList.add("show");

    setTimeout(() => {
        location.reload();
    }, 1200);
}
</script>

</body>
</html>