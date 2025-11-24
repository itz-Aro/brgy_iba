<?php
require_once __DIR__ . '/../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

// Get selected date from query param
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// 3-day labels and counts ending with selectedDate
$chartLabels = [];
$chartData = [];
$datesIndex = [];

for ($i = 2; $i >= 0; $i--) {  // last 3 days
    $d = date('Y-m-d', strtotime("$selectedDate -{$i} days"));
    $chartLabels[] = date('m-d', strtotime($d));
    $datesIndex[$d] = count($chartData);
    $chartData[] = 0;
}

// Fetch borrowings from last 3 days up to selectedDate
$stmt = $conn->prepare("
    SELECT DATE(date_borrowed) AS borrow_date, COUNT(*) AS total
    FROM borrowings
    WHERE date_borrowed BETWEEN DATE_SUB(:selectedDate, INTERVAL 2 DAY) AND :selectedDate
    GROUP BY DATE(date_borrowed)
    ORDER BY DATE(date_borrowed)
");
$stmt->execute(['selectedDate' => $selectedDate]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    if (isset($datesIndex[$row['borrow_date']])) {
        $chartData[$datesIndex[$row['borrow_date']]] = (int)$row['total'];
    }
}

echo json_encode([
    'labels' => $chartLabels,
    'data' => $chartData
]);
