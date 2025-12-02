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
/* --- GENERAL --- */
* { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
body {
    min-height:100vh;
    display:flex; justify-content:center; align-items:center;
    background: linear-gradient(135deg,#59a5fc,#7dd7f0,#ffffff);
    padding:20px;
}
.form-container {
    background:white; width:100%; max-width:520px;
    padding:2rem; border-radius:14px;
    box-shadow:0 18px 35px rgba(0,0,0,0.18);
    max-height:90vh; overflow-y:auto;
    animation:fade 0.4s ease-in-out;
    position: relative;
}
@keyframes fade { from{opacity:0;transform:translateY(15px);} to{opacity:1;transform:translateY(0);} }

img.logo {
    width:150px;
    border-radius:50%;
    display:block;
    margin:0 auto 10px; 
}
.logo-box {
    width:100%;
    display:flex;
    justify-content:center;
    margin-bottom:10px;
}

h1 { text-align:center; font-size:1.4rem; margin-bottom:1rem; color:#1a237e; font-weight:700; }
label { font-size:0.9rem; margin:12px 0 4px; display:block; }
input,textarea { width:100%; padding:10px; border-radius:6px; border:1.4px solid #b6c5e0; font-size:0.95rem; transition:.2s; }
input:focus,textarea:focus { outline:none; border-color:#1e65ff; box-shadow:0 0 4px rgba(30,101,255,.4); }

.row { display:flex; gap:10px; }

.button {
    width:100%; padding:12px; margin-top:18px;
    background:#1e65ff; color:white; font-size:1rem;
    border:none; border-radius:8px; font-weight:600;
    transition:.2s; cursor:pointer;
}
.button:hover { background:#004fe0; transform:scale(1.03); }

.hidden { display:none; }

/* --- EQUIPMENT ITEMS --- */
.search-box { padding:10px; border-radius:6px; border:1.4px solid #ccc; width:100%; margin-bottom:14px; }

.equipment-item {
    background:#f7f9ff; border-radius:10px; padding:12px; display:flex; align-items:center;
    justify-content:space-between; margin-bottom:12px; border:1px solid #e2e6fb;
    transition:.2s box-shadow;
}
.equipment-item:hover { box-shadow:0 4px 14px rgba(0,0,0,0.07); }
.equipment-item img { width:60px; height:60px; border-radius:6px; object-fit:cover; border:2px solid #074adc; }
.quantity { display:flex; align-items:center; gap:4px; }
.quantity input { width:55px; text-align:center; font-size:1rem; border:1.4px solid #839ad8; border-radius:6px; }
.quantity button { background:#1e65ff; color:white; border:none; padding:6px 10px; border-radius:6px; cursor:pointer; font-size:14px; }

/* --- SUCCESS MODAL --- */
#step3 {
    position: fixed; top:0; left:0; width:100%; height:100%;
    display:flex; justify-content:center; align-items:center;
    background: rgba(0,0,0,0.5); z-index:1000;
    visibility:hidden; opacity:0; transition:0.3s;
}
#step3.show { visibility:visible; opacity:1; }
#step3 .success {
    background:white; padding:2rem; border-radius:12px; text-align:center;
    width:90%; max-width:400px;
}
#step3 i { font-size:4.5rem; margin-bottom:12px; color:#00c853; }
#step3 h2 { font-size:1.5rem; margin-bottom:6px; color:#1a1a1a; }
#step3 p { color:#555; font-size:0.95rem; }
</style>
</head>
<body>

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

<div class="form-container hidden" id="step2">
    <h1>Select Equipment</h1>
    <input class="search-box" type="text" placeholder="Search item..." onkeyup="filterEquipment(this)">

    <form id="equipmentForm">
        <?php foreach($equip as $row):
            $isAvailable = $row['available_quantity'] > 0;
        ?>
        <div class="equipment-item" data-name="<?= strtolower($row['name']) ?>">
            <img src="equipment_img/<?= $row['photo'] ?>" alt="<?= $row['name'] ?>">
            <div style="flex:1; margin-left:10px;">
                <b><?= $row['name'] ?></b>
                <p style="font-size:0.85rem; color:<?= $isAvailable ? '#555' : '#d32f2f' ?>">
                    <?= $isAvailable ? "Available: {$row['available_quantity']}" : "No Stock" ?>
                </p>
            </div>
            <div class="quantity">
                <button type="button" onclick="changeQty(this,-0)" <?= !$isAvailable ? 'disabled' : '' ?>>-</button>
                <input type="hidden" name="item[]" value="<?= $row['id'] ?>">
                <input type="number" name="qty[]" value="0" min="0" max="<?= $row['available_quantity'] ?>" <?= !$isAvailable ? 'disabled' : '' ?>>
                <button type="button" onclick="changeQty(this,0)" <?= !$isAvailable ? 'disabled' : '' ?>>+</button>
            </div>
        </div>
        <?php endforeach; ?>

        <button type="button" class="button" onclick="submitForm()">Submit Request ✔</button>
    </form>
</div>

<div class="form-container hidden" id="step3">
    <div class="success">
        <i class="fa-solid fa-circle-check"></i>
        <h2>Request Submitted!</h2>
        <p>You will receive SMS/Email updates regarding approval. Thank you!</p>
        <button class="button" onclick="resetForm()">Done</button>
    </div>
</div>


<script>
function nextStep(){
    // Validate Step 1 fields
    const step1Form = document.getElementById('personalForm');
    if(!step1Form.checkValidity()){
        step1Form.reportValidity();
        return;
    }
    document.getElementById('step1').classList.add('hidden');
    document.getElementById('step2').classList.remove('hidden');
}

function changeQty(btn,val){
    const input = btn.parentElement.querySelector("input[type='number']");
    let value = parseInt(input.value) + val;
    const max = parseInt(input.getAttribute('max')) || 999;
    if(value < 1) value = 1;
    if(value > max) value = max;
    input.value = value;
}

function filterEquipment(input){
    const query = input.value.toLowerCase();
    document.querySelectorAll('.equipment-item').forEach(item=>{
        const name = item.dataset.name;
        item.style.display = name.includes(query) ? 'flex' : 'none';
    });
}

function submitForm() {
    // Validate equipment selection
    const quantities = document.querySelectorAll('#equipmentForm input[type="number"]');
    let hasSelection = false;
    quantities.forEach(q => { if(parseInt(q.value) > 0) hasSelection = true; });
    if(!hasSelection){
        alert("Please select at least one equipment.");
        return;
    }

    // Collect Step 1 data
    const step1Form = document.getElementById('personalForm');
    const formData = new FormData(step1Form);

    // Collect Step 2 data
    const step2Form = document.getElementById('equipmentForm');
    const items = [];
    step2Form.querySelectorAll('.equipment-item').forEach(item=>{
        const qtyInput = item.querySelector('input[type="number"]');
        const idInput = item.querySelector('input[type="hidden"]');
        if(parseInt(qtyInput.value) > 0){
            items.push({id: idInput.value, qty: qtyInput.value});
        }
    });
    formData.append('items', JSON.stringify(items));

    // AJAX POST
    fetch('submit_request.php', { method:'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success'){
            // Hide Step 2
            document.getElementById('step2').classList.add('hidden');

            // Show Step 3 modal
            document.getElementById('step3').classList.add('show');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred.');
    });
}

function resetForm(){
    document.getElementById('step1').classList.remove('hidden');
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step3').classList.remove('show');
    document.getElementById('personalForm').reset();
    document.getElementById('equipmentForm').reset();
}

</script>
</body>
</html>
