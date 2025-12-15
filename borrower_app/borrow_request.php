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
<link rel="stylesheet" href="/brgy_iba/css/borrower_request.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
        <input
        type="text"
        name="contact"
        placeholder="09XXXXXXXXX"
        inputmode="numeric"
        pattern="^[0-9]{11}$"
        maxlength="11"
        required
        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
        />
        <small style="color:#083d97;font-size:0.75rem;">
        Must be exactly 11 digits (numbers only)
        </small>

        <label>Complete Address</label>
        <input type="text" name="address" placeholder="Street, Barangay, City" required>

        <label>Borrowing Period</label>
        <div class="row">
            <div>
                <input type="date" name="start_date" id="start_date" required>
                <small style="color:#083d97;font-size:0.75rem;">Start Date</small>
            </div>
            <div>
                <input type="date" name="end_date" id="end_date" required>
                <small style="color:#083d97;font-size:0.75rem;">End Date</small>
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

document.addEventListener("DOMContentLoaded", () => {
    const startDate = document.getElementById("start_date");
    const endDate   = document.getElementById("end_date");

    const today = new Date().toISOString().split("T")[0];

    // Block past dates
    startDate.min = today;
    endDate.min   = today;

    // When start date changes
    startDate.addEventListener("change", () => {
        endDate.min = startDate.value;

        if (endDate.value && endDate.value < startDate.value) {
            endDate.value = "";
        }
    });
});
</script>

</body>
</html>