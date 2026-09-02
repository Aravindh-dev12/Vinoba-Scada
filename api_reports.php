<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require 'config.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');

$headers = getallheaders();
$auth = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
$userRole = ''; $userPlant = '';
if ($auth && preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
    $token = $conn->real_escape_string($m[1]);
    $res = $conn->query("SELECT role, plant_id FROM users WHERE auth_token = '$token' LIMIT 1");
    if ($res && $res->num_rows > 0) { $u = $res->fetch_assoc(); $userRole = $u['role']; $userPlant = $u['plant_id']; }
}
if (empty($userRole)) {
    $urlToken = isset($_GET['token']) ? $conn->real_escape_string($_GET['token']) : '';
    if ($urlToken) {
        $res = $conn->query("SELECT role, plant_id FROM users WHERE auth_token = '$urlToken' LIMIT 1");
        if ($res && $res->num_rows > 0) { $u = $res->fetch_assoc(); $userRole = $u['role']; $userPlant = $u['plant_id']; }
    }
}

$tab = isset($_GET['tab']) ? $conn->real_escape_string($_GET['tab']) : 'inv_vcb';
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : 'daily';
$date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : date('Y-m-d');
$plant = isset($_GET['plant']) ? trim($conn->real_escape_string($_GET['plant'])) : 'all';
$chartMode = isset($_GET['chart']) ? true : false;
if ($plant === '') $plant = 'all';

if ($userRole && $userRole !== 'admin') { if ($plant !== $userPlant) $plant = $userPlant; }
$plantClause = ($plant !== 'all' && $plant !== '') ? " AND plant_id = '$plant'" : "";

try {
class SimpleWSClient {
    private $socket;
    public function connect($host, $port) {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, 3);
        if (!$this->socket) return false;
        stream_set_timeout($this->socket, 10);
        stream_set_blocking($this->socket, false);
        $key = base64_encode(random_bytes(16));
        $headers = "GET / HTTP/1.1\r\nHost: {$host}:{$port}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n";
        fwrite($this->socket, $headers);
        $response = ''; $start = time();
        while (time() - $start < 3) {
            $line = fgets($this->socket);
            if ($line === false) { usleep(10000); continue; }
            $response .= $line;
            if ($line === "\r\n") break;
        }
        return strpos($response, '101') !== false;
    }
    public function sendText($payload) {
        $len = strlen($payload); $frame = chr(0x81); $mask = random_bytes(4);
        if ($len <= 125) $frame .= chr(0x80 | $len);
        elseif ($len <= 65535) { $frame .= chr(0x80 | 126) . pack('n', $len); }
        else { $frame .= chr(0x80 | 127) . pack('NN', 0, $len); }
        $frame .= $mask;
        for ($i = 0; $i < $len; $i++) $frame .= $payload[$i] ^ $mask[$i % 4];
        fwrite($this->socket, $frame);
    }
    public function readFrame() {
        $data = $this->readExactly(2);
        if (!$data || strlen($data) < 2) return null;
        $byte1 = ord($data[0]); $byte2 = ord($data[1]);
        $opcode = $byte1 & 0x0F; $len = $byte2 & 0x7F;
        if ($len === 126) { $ext = $this->readExactly(2); if (!$ext) return null; $len = unpack('n', $ext)[1]; }
        elseif ($len === 127) { $ext = $this->readExactly(8); if (!$ext) return null; $u = unpack('N2', $ext); $len = ($u[1] << 32) | $u[2]; }
        $masked = ($byte2 >> 7) & 0x01;
        if ($masked) { $mask = $this->readExactly(4); if (!$mask) return null; }
        $payload = ''; if ($len > 0) { $payload = $this->readExactly($len); if (!$payload) return null; }
        if (!empty($mask)) for ($i = 0; $i < $len; $i++) $payload[$i] = $payload[$i] ^ $mask[$i % 4];
        if ($opcode === 0x08) return ['opcode' => 'close', 'payload' => $payload];
        if ($opcode === 0x09) { $this->sendPong($payload); return ['opcode' => 'ping', 'payload' => '']; }
        if ($opcode === 0x0A) return ['opcode' => 'pong', 'payload' => ''];
        return ['opcode' => 'text', 'payload' => $payload];
    }
    private function readExactly($n) {
        $buffer = ''; $start = time();
        while (strlen($buffer) < $n && time() - $start < 2) {
            $chunk = @fread($this->socket, $n - strlen($buffer));
            if ($chunk === false || $chunk === '') { if (feof($this->socket)) return null; usleep(1000); continue; }
            $buffer .= $chunk;
        }
        return strlen($buffer) === $n ? $buffer : null;
    }
    private function sendPong($payload) {
        $len = strlen($payload); $frame = chr(0x8A); $mask = random_bytes(4);
        if ($len <= 125) $frame .= chr(0x80 | $len) . $mask;
        else { $frame .= chr(0x80 | 126) . pack('n', $len) . $mask; }
        for ($i = 0; $i < $len; $i++) $frame .= $payload[$i] ^ $mask[$i % 4];
        fwrite($this->socket, $frame);
    }
    public function close() { if ($this->socket) { fclose($this->socket); $this->socket = null; } }
}

function fetchLiveData($plant) {
    $ws = new SimpleWSClient();
    if (!$ws->connect('161.97.87.75', 5000)) {
        return ['error' => 'WS connect failed'];
    }
    $ws->sendText(json_encode(['type' => 'subscribe', 'unit_id' => $plant]));

    $frames = []; $start = time();
    while (time() - $start < 8) {
        $frame = $ws->readFrame();
        if ($frame && isset($frame['payload']) && $frame['opcode'] === 'text') {
            $j = json_decode($frame['payload'], true);
            if ($j && isset($j['unit_id']) && $j['unit_id'] === $plant) $frames[] = $j;
        }
        usleep(2000);
    }
    $ws->close();

    if (empty($frames)) {
        $ws2 = new SimpleWSClient();
        if ($ws2->connect('161.97.87.75', 5000)) {
            $ws2->sendText(json_encode(['type' => 'subscribe', 'unit_id' => $plant]));
            $start = time();
            while (time() - $start < 5) {
                $frame = $ws2->readFrame();
                if ($frame && isset($frame['payload']) && $frame['opcode'] === 'text') {
                    $j = json_decode($frame['payload'], true);
                    if ($j && isset($j['unit_id']) && $j['unit_id'] === $plant) $frames[] = $j;
                }
                usleep(2000);
            }
            $ws2->close();
        }
    }

    $latest = ['inv1'=>[],'inv2'=>[],'vcb'=>[],'trafo'=>[]];
    $invRaw = []; // key = actual device name, value = parsed data
    foreach ($frames as $f) {
        $task = strtolower($f['task'] ?? '');
        $dev = strtolower($f['device'] ?? '');
        $v = $f['values'] ?? [];
        if ($task === 'inverter' || strpos($dev, 'inverter') !== false) {
            $actualName = $f['device'] ?? 'Unknown Inverter';
            if (!isset($invRaw[$actualName])) $invRaw[$actualName] = [];
            foreach ($v as $vk => $vv) {
                $vkl = strtolower($vk);
                if (preg_match('/active.*power|ac.*power|power.*ac|a\.c\..*power/', $vkl) && !preg_match('/reactive|apparent|3\.phase/', $vkl)) {
                    $invRaw[$actualName]['kw'] = floatval($vv);
                }
                if (preg_match('/daily.*generation|daily.*gen/', $vkl)) $invRaw[$actualName]['kwh'] = floatval($vv);
                if (preg_match('/internal.*temp|ambient.*temp|control.*temp/', $vkl)) $invRaw[$actualName]['temp'] = floatval($vv);
            }
        }
        if ($task === 'vcb' || $dev === 'vcb') {
            foreach ($v as $vk => $vv) {
                $vkl = strtolower($vk);
                if (strpos($vkl, 'active power') !== false && (strpos($vkl, 'total') !== false || strpos($vkl, '3 phase') !== false)) $latest['vcb']['kw'] = floatval($vv);
                if (strpos($vkl, 'export') !== false) $latest['vcb']['kwh_exp'] = floatval($vv);
            }
        }
        if ($task === 'transformer') {
            foreach ($v as $vk => $vv) {
                $vkl = strtolower($vk);
                if (strpos($vkl, 'oil') !== false) $latest['trafo']['oil'] = floatval($vv);
                if (strpos($vkl, 'winding') !== false) $latest['trafo']['winding'] = floatval($vv);
            }
        }
    }
    // Sort inverter device names alphabetically and map to inv1/inv2 by order
    $invNames = array_keys($invRaw);
    sort($invNames);
    if (isset($invNames[0]) && isset($invRaw[$invNames[0]])) $latest['inv1'] = $invRaw[$invNames[0]];
    if (isset($invNames[1]) && isset($invRaw[$invNames[1]])) $latest['inv2'] = $invRaw[$invNames[1]];
    return ['success' => true, 'frames' => count($frames), 'latest' => $latest, 'inv_names' => $invNames, 'sample' => $frames[0] ?? null];
}

function ensureTables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS inverter_readings (
        id INT AUTO_INCREMENT PRIMARY KEY, plant_id VARCHAR(50), device_name VARCHAR(100),
        ac_active_power DECIMAL(10,2) DEFAULT 0, daily_generation DECIMAL(12,2) DEFAULT 0, internal_temp DECIMAL(5,1) DEFAULT 0,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_inv_plant (plant_id), INDEX idx_inv_time (recorded_at)
    ) ENGINE=InnoDB");
    $conn->query("CREATE TABLE IF NOT EXISTS vcb_readings (
        id INT AUTO_INCREMENT PRIMARY KEY, plant_id VARCHAR(50),
        active_power_total DECIMAL(10,2) DEFAULT 0, active_total_export DECIMAL(12,2) DEFAULT 0,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_vcb_plant (plant_id), INDEX idx_vcb_time (recorded_at)
    ) ENGINE=InnoDB");
    $conn->query("CREATE TABLE IF NOT EXISTS transformer_readings (
        id INT AUTO_INCREMENT PRIMARY KEY, plant_id VARCHAR(50), device_name VARCHAR(100),
        oil_temp DECIMAL(5,1) DEFAULT NULL, winding_temp DECIMAL(5,1) DEFAULT NULL,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_trafo_plant (plant_id), INDEX idx_trafo_time (recorded_at)
    ) ENGINE=InnoDB");
}

function storeLiveData($conn, $plant, $l, $invNames = []) {
    $now = date('Y-m-d H:i:s');
    if (!empty($l['inv1'])) {
        $stmt = $conn->prepare("INSERT INTO inverter_readings (plant_id, device_name, ac_active_power, daily_generation, internal_temp, recorded_at) VALUES (?,?,?,?,?,?)");
        if ($stmt) {
            $dev = $invNames[0] ?? 'Inverter 1';
            $kw = (float)($l['inv1']['kw'] ?? 0);
            $kwh = (float)($l['inv1']['kwh'] ?? 0);
            $temp = (float)($l['inv1']['temp'] ?? 0);
            $stmt->bind_param('ssddds', $plant, $dev, $kw, $kwh, $temp, $now);
            if (!$stmt->execute()) error_log("[api_reports] inv1 insert failed: " . $stmt->error);
            $stmt->close();
        } else { error_log("[api_reports] inv1 prepare failed: " . $conn->error); }
    }
    if (!empty($l['inv2'])) {
        $stmt = $conn->prepare("INSERT INTO inverter_readings (plant_id, device_name, ac_active_power, daily_generation, internal_temp, recorded_at) VALUES (?,?,?,?,?,?)");
        if ($stmt) {
            $dev = $invNames[1] ?? 'Inverter 2';
            $kw = (float)($l['inv2']['kw'] ?? 0);
            $kwh = (float)($l['inv2']['kwh'] ?? 0);
            $temp = (float)($l['inv2']['temp'] ?? 0);
            $stmt->bind_param('ssddds', $plant, $dev, $kw, $kwh, $temp, $now);
            if (!$stmt->execute()) error_log("[api_reports] inv2 insert failed: " . $stmt->error);
            $stmt->close();
        } else { error_log("[api_reports] inv2 prepare failed: " . $conn->error); }
    }
    if (!empty($l['vcb'])) {
        $stmt = $conn->prepare("INSERT INTO vcb_readings (plant_id, active_power_total, active_total_export, recorded_at) VALUES (?,?,?,?)");
        if ($stmt) {
            $kw = (float)($l['vcb']['kw'] ?? 0);
            $kwhExp = (float)($l['vcb']['kwh_exp'] ?? 0);
            $stmt->bind_param('sdds', $plant, $kw, $kwhExp, $now);
            if (!$stmt->execute()) error_log("[api_reports] vcb insert failed: " . $stmt->error);
            $stmt->close();
        } else { error_log("[api_reports] vcb prepare failed: " . $conn->error); }
    }
    if (!empty($l['trafo'])) {
        $stmt = $conn->prepare("INSERT INTO transformer_readings (plant_id, device_name, oil_temp, winding_temp, recorded_at) VALUES (?,?,?,?,?)");
        if ($stmt) {
            $dev = 'Transformer';
            $oil = (float)($l['trafo']['oil'] ?? 0);
            $winding = (float)($l['trafo']['winding'] ?? 0);
            $stmt->bind_param('ssdds', $plant, $dev, $oil, $winding, $now);
            if (!$stmt->execute()) error_log("[api_reports] trafo insert failed: " . $stmt->error);
            $stmt->close();
        } else { error_log("[api_reports] trafo prepare failed: " . $conn->error); }
    }
}

function buildBuckets($type, $date, $hourly = false) {
    $buckets = [];
    $interval = $hourly ? 3600 : 900;
    if ($type === 'daily') {
        $start = strtotime($date . ($hourly ? " 05:00:00" : " 05:30:00"));
        $defaultEnd = strtotime($date . ($hourly ? " 19:00:00" : " 19:30:00"));
        $today = date('Y-m-d');
        if ($date === $today) {
            $now = time();
            $nowRounded = ceil($now / $interval) * $interval;
            $end = min($nowRounded, $defaultEnd);
            if ($end < $start) $end = $start;
        } else {
            $end = $defaultEnd;
        }
        while ($start <= $end) {
            $t = date('H:i', $start);
            $buckets[$t] = ['time_label' => $t, 'inv1_kwh'=>0,'inv1_kw'=>0,'inv1_temp'=>0,'inv2_kwh'=>0,'inv2_kw'=>0,'inv2_temp'=>0,'vcb_kwh'=>0,'vcb_kw'=>0,'ot'=>0,'wt1'=>0,'wt2'=>0];
            $start += $interval;
        }
    } else {
        $days = date('t', strtotime($date.'-01'));
        for ($i=1; $i<=$days; $i++) {
            $label = str_pad($i,2,'0',STR_PAD_LEFT).'-'.date('m-Y',strtotime($date.'-01'));
            $buckets[$label] = ['time_label'=>$label,'inv1_kwh'=>0,'inv1_kw'=>0,'inv1_temp'=>0,'inv2_kwh'=>0,'inv2_kw'=>0,'inv2_temp'=>0,'vcb_kwh'=>0,'vcb_kw'=>0,'ot'=>0,'wt1'=>0,'wt2'=>0];
        }
    }
    return $buckets;
}
function to15min($t) { $p=explode(':',$t); return $p[0].':'.str_pad(floor($p[1]/15)*15,2,'0',STR_PAD_LEFT); }
function toHour($t) { $p=explode(':',$t); return $p[0].':00'; }

$live = ['success' => false, 'latest' => [], 'frames' => 0, 'error' => null, 'inv_names' => []];
$hasLive = false;

if ($plant !== 'all' && $plant !== '') {
    $live = fetchLiveData($plant);
    $hasLive = (isset($live['success']) && !empty($live['latest']['inv1']));
    if ($hasLive) {
        storeLiveData($conn, $plant, $live['latest'], $live['inv_names'] ?? []);
    }
}

// Determine inverter device names: from live WS data, or from DB
$invNames = $live['inv_names'] ?? [];
if (empty($invNames)) {
    $dnRes = $conn->query("SELECT DISTINCT device_name FROM inverter_readings WHERE 1=1 $plantClause AND device_name NOT LIKE 'VCB%' AND device_name NOT LIKE 'Transformer%' ORDER BY device_name ASC");
    if ($dnRes) while ($dnRow = $dnRes->fetch_assoc()) $invNames[] = $dnRow['device_name'];
}
if (empty($invNames)) { $invNames = ['Inverter 1', 'Inverter 2']; }
$inv1Name = $conn->real_escape_string($invNames[0] ?? 'Inverter 1');
$inv2Name = $conn->real_escape_string($invNames[1] ?? 'Inverter 2');

$l = $live['latest'] ?? [];
$timeBuckets = buildBuckets($type, $date, $chartMode);

$isToday = ($date === date('Y-m-d'));
// Historical readings always populate their own time buckets. Live readings are
// added only to the current bucket below, never copied into future/past rows.
{
    $q = "SELECT DATE_FORMAT(recorded_at,'%H:%i') as bTime, daily_generation as kwh, ac_active_power as kw, internal_temp as temp FROM inverter_readings WHERE DATE(recorded_at)='$date' AND device_name = '$inv1Name' $plantClause ORDER BY recorded_at ASC";
    $res = $conn->query($q);
    $bucketFn = $chartMode ? 'toHour' : 'to15min';
    if ($res) while ($row=$res->fetch_assoc()) { $bt=$bucketFn($row['bTime']); if(isset($timeBuckets[$bt])){ $timeBuckets[$bt]['inv1_kwh']=(float)$row['kwh']; $timeBuckets[$bt]['inv1_kw']=(float)$row['kw']; $timeBuckets[$bt]['inv1_temp']=(float)$row['temp']; } }

    $q = "SELECT DATE_FORMAT(recorded_at,'%H:%i') as bTime, daily_generation as kwh, ac_active_power as kw, internal_temp as temp FROM inverter_readings WHERE DATE(recorded_at)='$date' AND device_name = '$inv2Name' $plantClause ORDER BY recorded_at ASC";
    $res = $conn->query($q);
    if ($res) while ($row=$res->fetch_assoc()) { $bt=$bucketFn($row['bTime']); if(isset($timeBuckets[$bt])){ $timeBuckets[$bt]['inv2_kwh']=(float)$row['kwh']; $timeBuckets[$bt]['inv2_kw']=(float)$row['kw']; $timeBuckets[$bt]['inv2_temp']=(float)$row['temp']; } }

    $q = "SELECT DATE_FORMAT(recorded_at,'%H:%i') as bTime, active_power_total as kw, active_total_export as kwh_exp FROM vcb_readings WHERE DATE(recorded_at)='$date' $plantClause ORDER BY recorded_at ASC";
    $res = $conn->query($q);
    $baseExport = 0;
    $minRes = $conn->query("SELECT active_total_export FROM vcb_readings WHERE DATE(recorded_at)='$date' $plantClause ORDER BY recorded_at ASC LIMIT 1");
    if ($minRes && $minRes->num_rows>0) { $r=$minRes->fetch_assoc(); $baseExport=(float)$r['active_total_export']; }
    if ($res) while ($row=$res->fetch_assoc()) { $bt=$bucketFn($row['bTime']); if(isset($timeBuckets[$bt])){ $timeBuckets[$bt]['vcb_kw']=(float)$row['kw']; $timeBuckets[$bt]['vcb_kwh']=((float)$row['kwh_exp']-$baseExport)/1000; } }

    $q = "SELECT DATE_FORMAT(recorded_at,'%H:%i') as bTime, oil_temp, winding_temp FROM transformer_readings WHERE DATE(recorded_at)='$date' $plantClause ORDER BY recorded_at ASC";
    $res = $conn->query($q);
    if ($res) while ($row=$res->fetch_assoc()) { $bt=$bucketFn($row['bTime']); if(isset($timeBuckets[$bt])){ if($row['oil_temp']!==null)$timeBuckets[$bt]['ot']=(float)$row['oil_temp']; if($row['winding_temp']!==null)$timeBuckets[$bt]['wt1']=(float)$row['winding_temp']; } }
}

if (!$chartMode && $hasLive && $isToday) {
    $currentBucket = to15min(date('H:i'));
    if (isset($timeBuckets[$currentBucket])) {
        if (!empty($l['inv1'])) {
            $timeBuckets[$currentBucket]['inv1_kwh'] = $l['inv1']['kwh'] ?? 0;
            $timeBuckets[$currentBucket]['inv1_kw'] = $l['inv1']['kw'] ?? 0;
            $timeBuckets[$currentBucket]['inv1_temp'] = $l['inv1']['temp'] ?? 0;
        }
        if (!empty($l['inv2'])) {
            $timeBuckets[$currentBucket]['inv2_kwh'] = $l['inv2']['kwh'] ?? 0;
            $timeBuckets[$currentBucket]['inv2_kw'] = $l['inv2']['kw'] ?? 0;
            $timeBuckets[$currentBucket]['inv2_temp'] = $l['inv2']['temp'] ?? 0;
        }
        if (!empty($l['vcb'])) {
            $timeBuckets[$currentBucket]['vcb_kw'] = $l['vcb']['kw'] ?? 0;
            $timeBuckets[$currentBucket]['vcb_kwh'] = ($l['vcb']['kwh_exp'] ?? 0) / 1000;
        }
        if (!empty($l['trafo'])) {
            $timeBuckets[$currentBucket]['ot'] = $l['trafo']['oil'] ?? 0;
            $timeBuckets[$currentBucket]['wt1'] = $l['trafo']['winding'] ?? 0;
        }
    }
}

if ($type === 'monthly') {
    $days = date('t', strtotime($date.'-01'));
    for ($i=1; $i<=$days; $i++) {
        $d = date('Y-m',strtotime($date.'-01')).'-'.str_pad($i,2,'0',STR_PAD_LEFT);
        $label = str_pad($i,2,'0',STR_PAD_LEFT).'-'.date('m-Y',strtotime($date.'-01'));
        // Sum each plant's/device's daily maximum. A plain MAX would silently
        // return only one plant when the admin selects All Plants.
        $r1=$conn->query("SELECT COALESCE(SUM(kwh),0) as kwh, COALESCE(SUM(kw),0) as kw FROM (SELECT plant_id, device_name, MAX(daily_generation) kwh, MAX(ac_active_power) kw FROM inverter_readings WHERE DATE(recorded_at)='$d' AND device_name = '$inv1Name' $plantClause GROUP BY plant_id, device_name) daily_inv");
        if ($r1&&$r1->num_rows>0){$row=$r1->fetch_assoc(); $timeBuckets[$label]['inv1_kwh']=(float)$row['kwh']; $timeBuckets[$label]['inv1_kw']=(float)$row['kw'];}
        $r2=$conn->query("SELECT COALESCE(SUM(kwh),0) as kwh, COALESCE(SUM(kw),0) as kw FROM (SELECT plant_id, device_name, MAX(daily_generation) kwh, MAX(ac_active_power) kw FROM inverter_readings WHERE DATE(recorded_at)='$d' AND device_name = '$inv2Name' $plantClause GROUP BY plant_id, device_name) daily_inv");
        if ($r2&&$r2->num_rows>0){$row=$r2->fetch_assoc(); $timeBuckets[$label]['inv2_kwh']=(float)$row['kwh']; $timeBuckets[$label]['inv2_kw']=(float)$row['kw'];}
        // The VCB export register is cumulative, so daily energy is max-min.
        // Calculate that independently per plant before combining all plants.
        $rv=$conn->query("SELECT COALESCE(SUM(kwh_exp),0) as kwh_exp FROM (SELECT plant_id, GREATEST(MAX(active_total_export)-MIN(active_total_export),0) kwh_exp FROM vcb_readings WHERE DATE(recorded_at)='$d' $plantClause GROUP BY plant_id) daily_vcb");
        if ($rv&&$rv->num_rows>0){$row=$rv->fetch_assoc(); $timeBuckets[$label]['vcb_kwh']=((float)$row['kwh_exp'])/1000;}
        $rt=$conn->query("SELECT oil_temp, winding_temp FROM transformer_readings WHERE DATE(recorded_at)='$d' $plantClause ORDER BY recorded_at DESC LIMIT 1");
        if ($rt&&$rt->num_rows>0){$row=$rt->fetch_assoc(); if($row['oil_temp']!==null)$timeBuckets[$label]['ot']=(float)$row['oil_temp']; if($row['winding_temp']!==null)$timeBuckets[$label]['wt1']=(float)$row['winding_temp'];}
    }
}

$operatingTimes = [
    'inverter' => ['start' => null, 'end' => null],
    'vcb' => ['start' => null, 'end' => null],
    'transformer' => ['start' => null, 'end' => null]
];
if ($type === 'daily') {
    $timeQueries = [
        'inverter' => "SELECT DATE_FORMAT(MIN(recorded_at),'%H:%i') AS start_time, DATE_FORMAT(MAX(recorded_at),'%H:%i') AS end_time FROM inverter_readings WHERE DATE(recorded_at)='$date' AND ac_active_power > 0 $plantClause",
        'vcb' => "SELECT DATE_FORMAT(MIN(recorded_at),'%H:%i') AS start_time, DATE_FORMAT(MAX(recorded_at),'%H:%i') AS end_time FROM vcb_readings WHERE DATE(recorded_at)='$date' AND active_power_total > 0 $plantClause",
        'transformer' => "SELECT DATE_FORMAT(MIN(recorded_at),'%H:%i') AS start_time, DATE_FORMAT(MAX(recorded_at),'%H:%i') AS end_time FROM transformer_readings WHERE DATE(recorded_at)='$date' $plantClause"
    ];
    foreach ($timeQueries as $deviceType => $query) {
        $timeResult = $conn->query($query);
        if ($timeResult && $timeResult->num_rows > 0) {
            $timeRow = $timeResult->fetch_assoc();
            $operatingTimes[$deviceType] = [
                'start' => $timeRow['start_time'] ?: null,
                'end' => $timeRow['end_time'] ?: null
            ];
        }
    }
}

$response = [
    "success" => true,
    "meta" => [
        "tab" => $tab, "type" => $type, "date" => $date, "plant" => $plant,
        "source" => $hasLive ? "websocket_live" : "db_cache",
        "ws_frames" => $live['frames'] ?? 0,
        "ws_error" => $live['error'] ?? null,
        "inv_names" => $invNames,
        "operating_times" => $operatingTimes,
        "generated_at" => date('Y-m-d H:i:s')
    ],
    "data" => array_values($timeBuckets)
];

echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Server error: " . $e->getMessage(),
        "data" => []
    ]);
}
?>
