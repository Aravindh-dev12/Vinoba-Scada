<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "=== SCADA DB DIAGNOSTIC ===\n\n";

require 'config.php';
if ($conn->connect_error) {
    echo "[FAIL] DB Connection: " . $conn->connect_error . "\n";
    exit;
}
echo "[OK] DB Connection to '$dbname' successful\n\n";

$tables = ['plants', 'users', 'vcb_readings', 'inverter_readings', 'inverter_strings', 'transformer_readings', 'weather_readings', 'telemetry_history'];
echo "--- Table Check ---\n";
foreach ($tables as $t) {
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    if ($r && $r->num_rows > 0) {
        $countRes = $conn->query("SELECT COUNT(*) as cnt FROM `$t`");
        $count = $countRes ? $countRes->fetch_assoc()['cnt'] : '?';
        echo "[OK] $t exists (rows: $count)\n";
    } else {
        echo "[MISSING] $t does NOT exist\n";
    }
}
echo "\n";

echo "--- vcb_readings Columns ---\n";
$r = $conn->query("SHOW COLUMNS FROM vcb_readings");
if ($r) {
    $cols = [];
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    echo "Columns: " . implode(', ', $cols) . "\n";
    if (count($cols) < 10) {
        echo "[WARNING] vcb_readings has only " . count($cols) . " columns - minimal table! Need to re-import setup_db.sql\n";
    }
} else {
    echo "[FAIL] Cannot read vcb_readings columns: " . $conn->error . "\n";
}
echo "\n";

echo "--- inverter_readings Columns ---\n";
$r = $conn->query("SHOW COLUMNS FROM inverter_readings");
if ($r) {
    $cols = [];
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    echo "Columns: " . implode(', ', $cols) . "\n";
    if (count($cols) < 10) {
        echo "[WARNING] inverter_readings has only " . count($cols) . " columns - minimal table! Need to re-import setup_db.sql\n";
    }
} else {
    echo "[FAIL] Cannot read inverter_readings columns: " . $conn->error . "\n";
}
echo "\n";

echo "--- WebSocket Test (161.97.87.75:5000) ---\n";
$sock = @fsockopen('161.97.87.75', 5000, $errno, $errstr, 5);
if (!$sock) {
    echo "[FAIL] Cannot connect to WS server: $errstr ($errno)\n";
    echo "       ws_bridge.php will NOT work from this server.\n";
} else {
    echo "[OK] TCP connection to 161.97.87.75:5000 successful\n";
    $key = base64_encode(random_bytes(16));
    $headers = "GET / HTTP/1.1\r\nHost: 161.97.87.75:5000\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n";
    fwrite($sock, $headers);
    $response = '';
    $start = time();
    while (time() - $start < 3) {
        $line = fgets($sock);
        if ($line === false) { usleep(10000); continue; }
        $response .= $line;
        if ($line === "\r\n") break;
    }
    fclose($sock);
    if (strpos($response, '101') !== false) {
        echo "[OK] WebSocket handshake successful\n";
    } else {
        echo "[FAIL] WebSocket handshake failed. Response:\n$response\n";
    }
}
echo "\n";

echo "--- Test INSERT into vcb_readings ---\n";
$stmt = $conn->prepare("INSERT INTO vcb_readings (plant_id, active_power_total, frequency, recorded_at) VALUES ('test', 0, 0, NOW())");
if (!$stmt) {
    echo "[FAIL] Prepare failed: " . $conn->error . "\n";
} else {
    if ($stmt->execute()) {
        echo "[OK] Test INSERT successful (will delete it now)\n";
        $conn->query("DELETE FROM vcb_readings WHERE plant_id='test'");
    } else {
        echo "[FAIL] Execute failed: " . $stmt->error . "\n";
    }
    $stmt->close();
}
echo "\n";

echo "--- Latest Data Timestamps ---\n";
$checkTables = ['vcb_readings', 'inverter_readings', 'transformer_readings', 'weather_readings', 'telemetry_history'];
foreach ($checkTables as $t) {
    $r = $conn->query("SELECT MAX(recorded_at) as latest, MIN(recorded_at) as earliest FROM `$t`");
    if ($r) {
        $row = $r->fetch_assoc();
        $latest = $row['latest'] ?? 'N/A';
        $earliest = $row['earliest'] ?? 'N/A';
        $now = date('Y-m-d H:i:s');
        $diff = $latest !== 'N/A' ? round((strtotime($now) - strtotime($latest)) / 60) : '?';
        echo "$t: earliest=$earliest, latest=$latest ($diff min ago)\n";
        if ($diff > 10 && $diff !== '?') {
            echo "  [WARNING] No new data in $t for $diff minutes!\n";
        }
    }
}
echo "\nCurrent server time: " . date('Y-m-d H:i:s') . "\n\n";

echo "--- Process Check ---\n";
$output = shell_exec('ps aux | grep ws_bridge');
if ($output && strpos($output, 'ws_bridge.php') !== false) {
    echo "[OK] ws_bridge.php is running\n";
} else {
    echo "[NOT RUNNING] ws_bridge.php is NOT running! Start it with: php /path/to/ws_bridge.php\n";
}
echo "\n";

echo "=== DIAGNOSTIC COMPLETE ===\n";
echo "\nNext steps:\n";
echo "1. If tables are MISSING -> import setup_db.sql in phpMyAdmin\n";
echo "2. If tables have few columns -> DROP them and re-import setup_db.sql\n";
echo "3. If WS connection FAILS -> ws_bridge.php cannot run on this server\n";
echo "4. If all OK -> run: php ws_bridge.php  (keep it running)\n";
?>
