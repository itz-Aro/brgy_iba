<?php
require_once __DIR__ . '/../config/Database.php';
$db = new Database();
$conn = $db->getConnection();

// Fetch available equipment
$equip = $conn->query("SELECT id,name,photo,available_quantity FROM equipment WHERE available_quantity > 0");
?>

<div id="equipmentStep">
    <h1>Select Equipment</h1>
    <input class="search-box" type="text" placeholder="Search item..." onkeyup="filterEquipment(this)">
    
    <form id="equipmentForm">
        <?php foreach($equip as $row): ?>
        <div class="equipment-item">
            <img src="equipment_img/<?= $row['photo'] ?>" alt="<?= $row['name'] ?>">
            <b><?= $row['name'] ?></b>
            <div class="quantity">
                <button type="button" onclick="changeQty(this,-1)">-</button>
                
                <input type="hidden" name="item[]" value="<?= $row['id'] ?>">
                <input type="number" name="qty[]" value="1" min="1">
                
                <button type="button" onclick="changeQty(this,1)">+</button>
            </div>
        </div>
        <?php endforeach; ?>
        
        <button type="submit" class="button">Submit Request ✔</button>
    </form>
</div>

<script>
// Quantity buttons
function changeQty(btn,val){
    let inp = btn.parentElement.querySelector("input[type=number]");
    let v = parseInt(inp.value)+val;
    if(v<1)v=1;
    inp.value=v;
}

// Equipment search filter
function filterEquipment(input){
    let filter = input.value.toLowerCase();
    document.querySelectorAll('.equipment-item').forEach(item=>{
        let name = item.querySelector('b').innerText.toLowerCase();
        item.style.display = name.includes(filter)?'flex':'none';
    });
}

// Submit form using fetch to save_request.php
document.getElementById('equipmentForm').addEventListener('submit', function(e){
    e.preventDefault();

    const personal = new FormData(document.getElementById('personalForm')); // from Step1
    const equipment = new FormData(this);

    const data = new FormData();
    for(let pair of personal.entries()) data.append(pair[0], pair[1]);
    for(let pair of equipment.entries()) data.append(pair[0], pair[1]);

    fetch('save_request.php', { method:'POST', body:data })
    .then(res => res.json())
    .then(resp => {
        if(resp.success){
            document.getElementById('step2').classList.add('hidden');
            document.getElementById('step3').classList.add('show');
        } else {
            alert(resp.error);
        }
    });
});
</script>
