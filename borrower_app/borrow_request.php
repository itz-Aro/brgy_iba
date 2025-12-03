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

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }

body {
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background: linear-gradient(135deg,#59a5fc,#7dd7f0,#ffffff);
    padding:20px;
}

.form-container {
    background:white;
    width:100%;
    max-width:520px;
    padding:2rem;
    border-radius:14px;
    box-shadow:0 18px 35px rgba(0,0,0,0.18);
    max-height:90vh;
    overflow-y:auto;
    animation:fade 0.4s ease-in-out;
}

@keyframes fade { 
    from {opacity:0; transform:translateY(15px);} 
    to {opacity:1; transform:translateY(0);} 
}

img.logo {
    width:150px;
    border-radius:50%;
    display:block;
    margin:0 auto 10px;
}

h1 {
    text-align:center;
    font-size:1.4rem;
    margin-bottom:1rem;
    color:#1a237e;
    font-weight:700;
}

label { font-size:0.9rem; margin:12px 0 4px; display:block; }

input,textarea {
    width:100%;
    padding:10px;
    border-radius:6px;
    border:1.4px solid #b6c5e0;
    font-size:0.95rem;
    transition:.2s;
}

input:focus,textarea:focus {
    border-color:#1e65ff; box-shadow:0 0 4px rgba(30,101,255,.4);
}

.row { display:flex; gap:10px; }

.button {
    width:100%; padding:12px; margin-top:18px;
    background:#1e65ff; color:white; font-size:1rem;
    border:none; border-radius:8px; font-weight:600;
    transition:.2s; cursor:pointer;
}

.button:hover { background:#004fe0; transform:scale(1.03); }

.hidden { display:none; }

/* EQUIPMENT */
.search-box {
    padding:10px;
    border-radius:6px;
    border:1.4px solid #ccc;
    width:100%;
    margin-bottom:14px;
}

.equipment-item {
    background:#f7f9ff;
    border-radius:10px;
    padding:12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:12px;
    border:1px solid #e2e6fb;
    transition:.2s box-shadow;
}

.equipment-item:hover {
    box-shadow:0 4px 14px rgba(0,0,0,0.07);
}

.equipment-item img {
    width:60px;
    height:60px;
    border-radius:6px;
    object-fit:cover;
    border:2px solid #074adc;
}

.quantity {
    display:flex;
    align-items:center;
    gap:4px;
}

.quantity button {
    background:#1e65ff;
    color:white;
    border:none;
    padding:6px 10px;
    border-radius:6px;
    cursor:pointer;
}

.quantity input {
    width:55px;
    text-align:center;
    font-size:1rem;
    border:1.4px solid #839ad8;
    border-radius:6px;
}

/* STEP 3 MODAL */
#step3 {
    position: fixed;
    top:0; left:0; width:100%; height:100%;
    display:flex;
    justify-content:center;
    align-items:center;
    background: rgba(0,0,0,0.55);
    visibility:hidden;
    opacity:0;
    transition:0.3s;
}

#step3.show {
    visibility:visible;
    opacity:1;
}

#step3 .success {
    background:white;
    padding:2rem;
    border-radius:12px;
    text-align:center;
    width:90%;
    max-width:400px;
}

#step3 i {
    font-size:4rem;
    margin-bottom:12px;
    color:#00c853;
}
#themeLoader {
    position: fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(255,255,255,.92);
    backdrop-filter: blur(3px);
    display:flex;
    justify-content:center;
    align-items:center;
    flex-direction:column;
    visibility:hidden; opacity:0;
    transition:.25s ease-in-out;
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
    width:75px;
    height:75px;
    border:6px solid #1e65ff;
    border-top-color:transparent;
    border-radius:50%;
    animation:spin 0.8s linear infinite;
}

#themeLoader p {
    margin-top:12px;
    font-size:16px;
    font-weight:600;
    color:#073a6a;
}

@keyframes spin {
    from{transform:rotate(0deg);}
    to{transform:rotate(360deg);}
}

</style>
</head>
<body>

<!-- STEP 1 -->
<div class="form-container" id="step1">
    <img src="../asset/logo.png" class="logo">
    <h1>BRGY. IBA EAST EQUIPMENT REQUEST</h1>

    <form id="personalForm">
        <label>Full Name</label>
        <input type="text" name="fullname" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Contact Number</label>
        <input type="text" name="contact" required>

        <label>Address</label>
        <input type="text" name="address" required>

        <label>Borrowing Date</label>
        <div class="row">
            <input type="date" name="start_date" required>
            <input type="date" name="end_date" required>
        </div>

        <label>Remarks:</label>
        <textarea name="notes" rows="2"></textarea>

        <button type="button" class="button" onclick="nextStep()">Next ➜</button>
    </form>
</div>

<!-- STEP 2 -->
<div class="form-container hidden" id="step2">
    <h1>Select Equipment</h1>
    <input class="search-box" type="text" placeholder="Search item..." onkeyup="filterEquipment(this)">

    <form id="equipmentForm">
        <?php foreach($equip as $row): 
            $isAvailable = $row['available_quantity'] > 0;
        ?>
        <div class="equipment-item" data-name="<?= strtolower($row['name']) ?>">
            <img src="../equipment/equipment_img/<?= $row['photo'] ?>" alt="<?= $row['name'] ?>">
            
            <div style="flex:1; margin-left:10px;">
                <b><?= $row['name'] ?></b>
                <p style="font-size:0.85rem; color:<?= $isAvailable ? '#555' : '#d32f2f' ?>">
                    <?= $isAvailable ? "Available: {$row['available_quantity']}" : "No Stock" ?>
                </p>
            </div>

            <div class="quantity">
                <button type="button" onclick="changeQty(this,-1)" <?= !$isAvailable ? 'disabled' : '' ?>>-</button>
                <input type="hidden" name="item[]" value="<?= $row['id'] ?>">
                <input type="number" name="qty[]" value="0" min="0" max="<?= $row['available_quantity'] ?>" <?= !$isAvailable ? 'disabled' : '' ?>>
                <button type="button" onclick="changeQty(this,1)" <?= !$isAvailable ? 'disabled' : '' ?>>+</button>
            </div>
        </div>
        <?php endforeach; ?>

        <button type="button" class="button" onclick="submitForm()">Submit Request ✔</button>
    </form>
</div>

<!-- STEP 3 -->
<div id="step3">
    <!-- GLOBAL LOADING SCREEN -->
    <div id="themeLoader">
        <div class="loader-wrapper">
            <div class="loader-circle"></div>
            <p>Processing your request...</p>
        </div>
    </div>

    <div class="success">
        <i class="fa-solid fa-circle-check"></i>
        <h2>Request Submitted!</h2>
        <p>You will receive SMS/Email updates regarding approval.</p>
        <button class="button" onclick="resetForm()">Done</button>
    </div>
</div>

<script>
function nextStep(){
    const step1 = document.getElementById('personalForm');

    if(!step1.checkValidity()){
        step1.reportValidity();
        return;
    }

    // Show barangay themed loader
    document.getElementById("themeLoader").classList.add("show");

    // Smooth transition delay
    setTimeout(() => {
        document.getElementById('step1').classList.add('hidden');
        document.getElementById('step2').classList.remove('hidden');

        // Hide loader after everything is displayed
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
    // Validate quantity > 0
    const quantities = document.querySelectorAll('#equipmentForm input[type="number"]');
    let hasSelection = false;
    quantities.forEach(q => { if(parseInt(q.value) > 0) hasSelection = true; });

    if(!hasSelection){
        alert("Please select at least one equipment.");
        return;
    }

    // Show loading theme
    document.getElementById("themeLoader").classList.add("show");

    // Prepare data
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

    // AJAX request
    fetch('submit_request.php', { method:'POST', body: formData })
    .then(res => res.json())
    .then(data => {

        setTimeout(() => {
            // Hide loading
            document.getElementById("themeLoader").classList.remove("show");


            if(data.status === 'success'){
                document.getElementById('step2').classList.add('hidden');
                document.getElementById('step3').classList.add('show');
            } else {
                alert('Error: ' + data.message);
            }

        }, 1200); // smooth loading delay
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred.');
        document.getElementById("themeLoader").classList.remove("show");

    });
}


function resetForm(){
    document.getElementById('step3').classList.remove('show');

    // Show loader while refreshing
    document.getElementById("themeLoader").classList.add("show");

    setTimeout(() => {
        location.reload();
    }, 1200);
}


</script>

</body>
</html>
