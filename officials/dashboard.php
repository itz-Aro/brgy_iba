<?php

require_once __DIR__ . '/../views/layout/sidebar.php';


?>

<main class="content-wrap">
    <h1>Welcome,<?= strtoupper($role) ?></h1>
    <h2>Friday, November 21</h2>
    <div style="display:flex; flex-wrap:wrap; gap:20px; margin-top:20px;">
        <div class="card" style="flex:1 1 250px;">
            <h3>Pending request</h3>
            <p>120 items</p>
        </div>

        <div class="card" style="flex:1 1 250px;">
            <h3>Ongoing Borrowings</h3>
            <p>8 requests</p>
        </div>

        <div class="card" style="flex:1 1 250px;">
            <h3>Items Due for Return</h3>
            <p>17 borrowed</p>
        </div>
    </div>
    <h2>Quick Action</h2>
    <div style="display:flex; flex-wrap:wrap; gap:20px; margin-top:20px;">
        <div class="card" style="flex:1 1 250px;">
            <h3>New Requet</h3>
        </div>

        <div class="card" style="flex:1 1 250px;">
            <h3>Approve Request</h3>
        </div>

        <div class="card" style="flex:1 1 250px;">
            <h3>Shows Reports</h3>
        </div>
    </div>
</main>
