<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

$bridgePath = __DIR__ . '/ws_bridge.php';
$logPath = __DIR__ . '/ws_bridge.log';

$running = false;
$output = @shell_exec('ps aux 2>/dev/null | grep ws_bridge.php | grep -v grep');
if ($output && strpos($output, 'ws_bridge.php') !== false) {
    $running = true;
}

echo "=== WS Bridge Keepalive ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "Bridge path: $bridgePath\n";

if ($running) {
    echo "[OK] ws_bridge.php is already running\n";
    echo trim($output) . "\n";
} else {
    echo "[INFO] ws_bridge.php is NOT running. Starting it now...\n";
    
    $started = false;
    
    $cmd = "RUN_SECONDS=240 nohup php $bridgePath > $logPath 2>&1 &";
    @shell_exec($cmd);
    usleep(500000);
    
    $output = @shell_exec('ps aux 2>/dev/null | grep ws_bridge.php | grep -v grep');
    if ($output && strpos($output, 'ws_bridge.php') !== false) {
        $started = true;
        echo "[OK] Started via nohup\n";
    }
    
    if (!$started) {
        @exec("RUN_SECONDS=240 php $bridgePath > $logPath 2>&1 &", $out, $ret);
        usleep(500000);
        $output = @shell_exec('ps aux 2>/dev/null | grep ws_bridge.php | grep -v grep');
        if ($output && strpos($output, 'ws_bridge.php') !== false) {
            $started = true;
            echo "[OK] Started via exec\n";
        }
    }
    
    if (!$started) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logPath, 'a'],
            2 => ['file', $logPath, 'a']
        ];
        $proc = @proc_open("RUN_SECONDS=240 php $bridgePath", $descriptors, $pipes);
        if ($proc) {
            // Don't wait - close immediately to let it run in background
            if (is_resource($pipes[0])) fclose($pipes[0]);
            // Don't close proc or it kills the child
            $started = true;
            echo "[OK] Started via proc_open (PID: " . proc_get_status($proc)['pid'] . ")\n";
        }
    }
    
    if (!$started) {
        echo "[FAIL] Could not start ws_bridge.php\n";
        echo "       Your hosting may not allow background processes.\n";
        echo "       Alternative: Set up a cron job to call this URL every 5 min:\n";
        echo "       * /5 * * * * curl -s https://vinobasolar.scadahub.in/keepalive.php\n";
    }
}

if (file_exists($logPath)) {
    echo "\n--- Last 5 log lines ---\n";
    $lines = array_slice(file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -20);
    foreach ($lines as $l) echo "$l\n";
}

echo "\n=== Done ===\n";
?>
