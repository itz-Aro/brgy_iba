<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brgy Iba East Equipment Request</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }

        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            background: #ccc; 
            padding: 20px;
        }

        .form-container { 
            background: #fff; 
            padding: 2rem; 
            border-radius: 8px; 
            width: 500px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            
            /* NEW: Make container scrollable */
            max-height: 90vh;
            overflow-y: auto;
        }

        /* OPTIONAL: nice scrollbar */
        .form-container::-webkit-scrollbar { width: 6px; }
        .form-container::-webkit-scrollbar-track { background: #f1f1f1; }
        .form-container::-webkit-scrollbar-thumb { background: #bbb; border-radius: 10px; }
        .form-container::-webkit-scrollbar-thumb:hover { background: #999; }

        .logo { text-align: center; margin-bottom: 1rem; }
        .logo img { width: 80px; }
        h1 { text-align: center; margin-bottom: 1rem; font-size: 1.2rem; }
        label { display: block; margin-top: 1rem; margin-bottom: 0.3rem; font-weight: 400; }
        input[type="text"], input[type="email"], input[type="number"], input[type="date"], textarea { 
            width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; 
        }
        .row { display: flex; gap: 0.5rem; }
        .row input { flex: 1; }
        .button { width: 100%; padding: 0.7rem; background: #0066ff; color: #fff; border: none; border-radius: 4px; margin-top: 1rem; cursor: pointer; font-weight: 600; }
        .button:hover { background: #0052cc; }
        .hidden { display: none; }

        .equipment-item { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .equipment-item img { width: 60px; border-radius: 4px; margin-right: 1rem; border:2px solid black; }
        .quantity { display: flex; align-items: center; gap: 0.3rem; }
        .quantity button { padding: 0.2rem 0.5rem; border: 1px solid #ccc; background: #f0f0f0; cursor: pointer; }

        .success { text-align: center; padding: 2rem; }
        .success img { width: 60px; margin-bottom: 1rem; }

    </style>
</head>
<body>

<div class="form-container" id="step1">
    <div class="logo">
        <img src="asset/logo.png" alt="Logo">
    </div>
    <h1>BRGY. IBA EAST EQUIPMENT REQUEST FORM</h1>
    
    <form id="personalForm">
        <label>Full Name</label>
        <input type="text" name="fullname" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Contact Number</label>
        <input type="text" name="contact" required>

        <label>Address</label>
        <input type="text" name="address" required>

        <label>Select Borrowing Date Range</label>
        <div class="row">
            <input type="date" name="start_date" required>
            <input type="date" name="end_date" required>
        </div>

        <label>Notes (Optional)</label>
        <textarea name="notes" rows="2"></textarea>

        <button type="button" class="button" onclick="nextStep()">Proceed</button>
    </form>
</div>

<div class="form-container hidden" id="step2">
    <h1>Select Equipment and Quantity</h1>
    <input type="text" placeholder="Search..." style="width:100%; padding:0.5rem; margin-bottom:1rem; border:1px solid #ccc; border-radius:4px;">

    <form id="equipmentForm">
        <div class="equipment-item">
            <img src="\brgy_iba\equipment\equipment_img\chair.png" alt="Chair">
            <span>Chair</span>
            <div class="quantity">
                <button type="button" onclick="changeQty(this,-1)">-</button>
                <input type="number" name="chair" value="1" min="1" style="width:50px;">
                <button type="button" onclick="changeQty(this,1)">+</button>
            </div>
        </div>

        <div class="equipment-item">
            <img src="\brgy_iba\equipment\equipment_img\table.png" alt="Table">
            <span>Table</span>
            <div class="quantity">
                <button type="button" onclick="changeQty(this,-1)">-</button>
                <input type="number" name="table" value="1" min="1" style="width:50px;">
                <button type="button" onclick="changeQty(this,1)">+</button>
            </div>
        </div>

        <div class="equipment-item">
            <img src="https://via.placeholder.com/60" alt="Tent">
            <span>Tent</span>
            <div class="quantity">
                <button type="button" onclick="changeQty(this,-1)">-</button>
                <input type="number" name="tent" value="1" min="1" style="width:50px;">
                <button type="button" onclick="changeQty(this,1)">+</button>
            </div>
        </div>

        <button type="button" class="button" onclick="submitForm()">Submit</button>
    </form>
</div>

<div class="form-container hidden" id="step3">
    <div class="success">
        <img src="https://via.placeholder.com/60?text=%E2%9C%93" alt="Success">
        <h2>Request Submitted!</h2>
        <p>We’ll notify you through Email or SMS regarding your request. Thank you!</p>
        <button class="button" onclick="resetForm()">Okay</button>
    </div>
</div>

<script>
function nextStep() {
    document.getElementById('step1').classList.add('hidden');
    document.getElementById('step2').classList.remove('hidden');
}

function changeQty(btn, delta) {
    const input = btn.parentElement.querySelector('input');
    let value = parseInt(input.value);
    value += delta;
    if (value < 1) value = 1;
    input.value = value;
}

function submitForm() {
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step3').classList.remove('hidden');
}

function resetForm() {
    document.getElementById('step3').classList.add('hidden');
    document.getElementById('step1').classList.remove('hidden');
    document.getElementById('personalForm').reset();
    document.getElementById('equipmentForm').reset();
}
</script>

</body>
</html>
