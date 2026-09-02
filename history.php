<?php
// history.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require 'config.php';

// Get the requested plant from the URL
$plant = isset($_GET['plant']) ? $_GET['plant'] : null;

if (!$plant) {
    echo json_encode(['success' => false, 'message' => 'No plant specified']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT recorded_at, active_power_total FROM vcb_readings WHERE plant_id = ? ORDER BY recorded_at DESC LIMIT 288");
    $stmt->bind_param('s', $plant);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    // Reverse so oldest is on the left, newest on the right
    $rows = array_reverse($rows);

    $times = [];
    $power = [];

    foreach ($rows as $row) {
        $timeStr = date('H:i', strtotime($row['recorded_at']));
        $activePower = isset($row['active_power_total']) ? (float)$row['active_power_total'] : 0;

        $times[] = $timeStr;
        $power[] = round($activePower, 1);
    }

    echo json_encode([
        'success' => true,
        'times' => $times,
        'power' => $power
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>