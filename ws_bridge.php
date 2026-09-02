<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php'; 


class SimpleWSClient {
    private $socket;
    private $host;
    private $port;
    private $path;

    public function __construct($host, $port = 80, $path = '/') {
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
    }

    public function connect() {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);
        if (!$this->socket) {
            echo "[WS] Connection failed: $errstr ($errno)\n";
            return false;
        }
        stream_set_timeout($this->socket, 60);
        stream_set_blocking($this->socket, false);

        $key = base64_encode(random_bytes(16));
        $headers = "GET {$this->path} HTTP/1.1\r\n";
        $headers .= "Host: {$this->host}:{$this->port}\r\n";
        $headers .= "Upgrade: websocket\r\n";
        $headers .= "Connection: Upgrade\r\n";
        $headers .= "Sec-WebSocket-Key: $key\r\n";
        $headers .= "Sec-WebSocket-Version: 13\r\n\r\n";

        fwrite($this->socket, $headers);

        $response = '';
        $start = time();
        while (time() - $start < 5) {
            $line = fgets($this->socket);
            if ($line === false) { usleep(10000); continue; }
            $response .= $line;
            if ($line === "\r\n") break;
        }

        if (strpos($response, '101') === false) {
            echo "[WS] Handshake failed. Response:\n$response\n";
            return false;
        }
        echo "[WS] Connected to {$this->host}:{$this->port}\n";
        return true;
    }

    public function sendText($payload) {
        $len = strlen($payload);
        $frame = chr(0x81);
        $mask = random_bytes(4);

        if ($len <= 125) {
            $frame .= chr(0x80 | $len);
        } elseif ($len <= 65535) {
            $frame .= chr(0x80 | 126);
            $frame .= pack('n', $len);
        } else {
            $frame .= chr(0x80 | 127);
            $frame .= pack('NN', 0, $len);
        }

        $frame .= $mask;
        for ($i = 0; $i < $len; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }
        fwrite($this->socket, $frame);
    }

    public function readFrame() {
        $data = $this->readExactly(2);
        if ($data === null || strlen($data) < 2) return null;

        $byte1 = ord($data[0]);
        $byte2 = ord($data[1]);
        $opcode = $byte1 & 0x0F;
        $masked = ($byte2 >> 7) & 0x01;
        $len = $byte2 & 0x7F;

        if ($len === 126) {
            $ext = $this->readExactly(2);
            if ($ext === null) return null;
            $len = unpack('n', $ext)[1];
        } elseif ($len === 127) {
            $ext = $this->readExactly(8);
            if ($ext === null) return null;
            $u = unpack('N2', $ext);
            $len = ($u[1] << 32) | $u[2];
        }

        if ($masked) {
            $mask = $this->readExactly(4);
            if ($mask === null) return null;
        }

        $payload = '';
        if ($len > 0) {
            $payload = $this->readExactly($len);
            if ($payload === null) return null;
        }

        if (!empty($mask)) {
            for ($i = 0; $i < $len; $i++) {
                $payload[$i] = $payload[$i] ^ $mask[$i % 4];
            }
        }

        if ($opcode === 0x08) return ['opcode' => 'close', 'payload' => $payload];
        if ($opcode === 0x09) {
            $this->sendPong($payload);
            return ['opcode' => 'ping', 'payload' => ''];
        }
        if ($opcode === 0x0A) return ['opcode' => 'pong', 'payload' => ''];

        return ['opcode' => 'text', 'payload' => $payload];
    }

    private function readExactly($n) {
        $buffer = '';
        while (strlen($buffer) < $n) {
            $chunk = @fread($this->socket, $n - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                if (feof($this->socket)) return null;
                usleep(1000);
                continue;
            }
            $buffer .= $chunk;
        }
        return $buffer;
    }

    private function sendPong($payload) {
        $len = strlen($payload);
        $frame = chr(0x8A);
        $mask = random_bytes(4);
        if ($len <= 125) {
            $frame .= chr(0x80 | $len) . $mask;
        } else {
            $frame .= chr(0x80 | 126) . pack('n', $len) . $mask;
        }
        for ($i = 0; $i < $len; $i++) $frame .= $payload[$i] ^ $mask[$i % 4];
        fwrite($this->socket, $frame);
    }

    public function close() {
        if ($this->socket) { fclose($this->socket); $this->socket = null; }
    }
}

function insertVcb($conn, $unit, $d) {
    if (!$d['values']) return;
    $v = $d['values'];
    $vt = isset($d['virtualTags']['vcb-today']) ? floatval($d['virtualTags']['vcb-today']['value']) : 0;

    $stmt = $conn->prepare("INSERT INTO vcb_readings
        (plant_id, active_power_total, active_power_r, active_power_y, active_power_b,
         frequency, voltage_rn, voltage_yn, voltage_bn, voltage_ry, voltage_yb, voltage_br,
         current_r, current_y, current_b, pf_q1, pf_q2, pf_q3,
         voltage_thd_r, voltage_thd_y, voltage_thd_b,
         active_total_export, active_total_import,
         reactive_import_q1q2, reactive_export_q3q4, today_energy)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) { echo "[DB ERROR] VCB prepare failed: " . $conn->error . "\n"; return; }
    $ap_total = floatval($v['3 Phase Active Power'] ?? 0);
    $ap_r     = floatval($v['Active Power R'] ?? 0);
    $ap_y     = floatval($v['Active Power Y'] ?? 0);
    $ap_b     = floatval($v['Active Power B'] ?? 0);
    $freq     = floatval($v['Frequency (Hz)'] ?? 0);
    $v_rn     = floatval($v['R Phase-N Voltage'] ?? 0);
    $v_yn     = floatval($v['Y Phase-N Voltage'] ?? 0);
    $v_bn     = floatval($v['B Phase-N Voltage'] ?? 0);
    $v_ry     = floatval($v['V12 (RY)'] ?? 0);
    $v_yb     = floatval($v['V23 (YB)'] ?? 0);
    $v_br     = floatval($v['V31 (BR)'] ?? 0);
    $i_r      = floatval($v['L1 (R)'] ?? 0);
    $i_y      = floatval($v['L2 (Y)'] ?? 0);
    $i_b      = floatval($v['L3 (B)'] ?? 0);
    $pf1      = floatval($v['Q1 PF'] ?? 0);
    $pf2      = floatval($v['Q2 PF'] ?? 0);
    $pf3      = floatval($v['Q3 PF'] ?? 0);
    $thd_r    = floatval($v['Voltage THD R'] ?? 0);
    $thd_y    = floatval($v['Voltage THD Y'] ?? 0);
    $thd_b    = floatval($v['Voltage THD B'] ?? 0);
    $exp      = floatval($v['Active Total Export'] ?? 0);
    $imp      = floatval($v['Active Total Import'] ?? 0);
    $r_imp    = floatval($v['Reactive Import (Q1+Q2)'] ?? 0);
    $r_exp    = floatval($v['Reactive Export (Q3+Q4)'] ?? 0);
    $stmt->bind_param('sddddddddddddddddddddddddd',
        $unit, $ap_total, $ap_r, $ap_y, $ap_b,
        $freq, $v_rn, $v_yn, $v_bn, $v_ry, $v_yb, $v_br,
        $i_r, $i_y, $i_b, $pf1, $pf2, $pf3,
        $thd_r, $thd_y, $thd_b,
        $exp, $imp, $r_imp, $r_exp, $vt
    );
    if (!$stmt->execute()) { echo "[DB ERROR] VCB execute failed: " . $stmt->error . "\n"; }
    else { echo "[DB] VCB inserted for $unit\n"; }
    $stmt->close();
}

function insertInverter($conn, $unit, $d) {
    if (!$d['values']) return;
    $v = $d['values'];
    $dev = $conn->real_escape_string($d['device'] ?? 'Unknown');

    $pwr = 0;
    foreach ($v as $pk => $pv) {
        $pkl = strtolower($pk);
        if (preg_match('/active.*power|ac.*power|power.*ac|a\.c\..*power/', $pkl) && !preg_match('/reactive|apparent|3.phase/', $pkl)) {
            $pwr = floatval($pv); break;
        }
    }

    $strings = [];
    $byNum = [];
    foreach (array_keys($v) as $k) {
        if (preg_match('/(\d+)/', $k, $m)) {
            $n = intval($m[1]);
            if (!isset($byNum[$n])) $byNum[$n] = [];
            $byNum[$n][] = $k;
        }
    }
    $activeStr = 0; $totalStr = 0;
    foreach ($byNum as $n => $group) {
        $currKey = ''; $voltKey = '';
        foreach ($group as $k) {
            $kl = strtolower($k);
            if (preg_match('/phase|phasa|ph_|r.phase|y.phase|b.phase|a.phase|c.phase|3.phase|three.phase/', $kl)) continue;
            if (preg_match('/inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|reactive.*curr|mppt.*curr|dc.*curr/', $kl)) continue;
            if (preg_match('/freq|temperature|temp|ambient|cosphi|pf.*_/', $kl)) continue;
            if (!$currKey && preg_match('/\b(curr|current|amp|i)\b/', $kl) && !preg_match('/\b(volt|voltage|temp|freq)\b/', $kl)) {
                $currKey = $k;
            }
            if (!$voltKey && preg_match('/\b(volt|voltage|v)\b/', $kl) && !preg_match('/\b(curr|current|amp|i)\b/', $kl)) {
                $voltKey = $k;
            }
        }
        if ($currKey) {
            $curr = floatval($v[$currKey]) ?? 0;
            $volt = $voltKey ? (floatval($v[$voltKey]) ?? 0) : 0;
            $strings[] = ['n' => $n, 'curr' => $curr, 'volt' => $volt, 'active' => ($curr > 0.5) ? 1 : 0];
            $totalStr++;
            if ($curr > 0.5) $activeStr++;
        }
    }

    $stmt = $conn->prepare("INSERT INTO inverter_readings
        (plant_id, device_name, ac_active_power, ac_reactive_power, power_factor,
         ac_voltage_ab, ac_voltage_bc, ac_voltage_ca, ac_frequency,
         phase_current_a, phase_current_b, phase_current_c,
         inverter_efficiency, internal_temp,
         daily_generation, total_generation, daily_co2_reduction, total_co2_reduction,
         daily_working_hours, total_working_hours,
         active_strings, total_strings, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) { echo "[DB ERROR] Inverter prepare failed: " . $conn->error . "\n"; return; }
    $status = ($pwr > 0.01) ? 'online' : 'offline';
    $reactivePwr  = floatval($v['a.c. reactive power'] ?? 0);
    $pf           = floatval($v['Power Factor'] ?? 0);
    $voltAB       = floatval($v['a.c. voltage AB'] ?? 0);
    $voltBC       = floatval($v['a.c. voltage BC'] ?? 0);
    $voltCA       = floatval($v['a.c. voltage CA'] ?? 0);
    $freq         = floatval($v['a.c. frequency'] ?? 0);
    $currA        = floatval($v['A phase current'] ?? 0);
    $currB        = floatval($v['B phase current'] ?? 0);
    $currC        = floatval($v['C phase current'] ?? 0);
    $eff          = floatval($v['inverter efficiency'] ?? 0);
    $intTemp      = floatval($v['internal ambient temperature'] ?? 0);
    $dailyGen     = floatval($v['daily generation'] ?? 0);
    $totalGen     = floatval($v['total generation'] ?? 0);
    $dailyCO2     = floatval($v['daily CO2 reduction'] ?? 0);
    $totalCO2     = floatval($v['total CO2 reduction'] ?? 0);
    $dailyHrs     = floatval($v['daily working hours'] ?? 0);
    $totalHrs     = floatval($v['total working hours'] ?? 0);
    $stmt->bind_param('ssdddddddddddddddddddiss',
        $unit, $dev, $pwr,
        $reactivePwr, $pf,
        $voltAB, $voltBC, $voltCA, $freq,
        $currA, $currB, $currC,
        $eff, $intTemp,
        $dailyGen, $totalGen,
        $dailyCO2, $totalCO2,
        $dailyHrs, $totalHrs,
        $activeStr, $totalStr, $status
    );
    if (!$stmt->execute()) { echo "[DB ERROR] Inverter execute failed: " . $stmt->error . "\n"; }
    else { echo "[DB] Inverter $dev inserted for $unit (strings: $totalStr)\n"; }
    $stmt->close();

    foreach ($strings as $s) {
        $st = $conn->prepare("INSERT INTO inverter_strings (plant_id, inverter_name, string_number, current, voltage, is_active) VALUES (?,?,?,?,?,?)");
        if (!$st) { echo "[DB ERROR] String prepare failed: " . $conn->error . "\n"; continue; }
        $sN = $s['n']; $sCurr = $s['curr']; $sVolt = $s['volt']; $sActive = $s['active'];
        $st->bind_param('ssiddd', $unit, $dev, $sN, $sCurr, $sVolt, $sActive);
        if (!$st->execute()) { echo "[DB ERROR] String execute failed: " . $st->error . "\n"; }
        $st->close();
    }
}

function insertTransformer($conn, $unit, $d) {
    if (!$d['values']) return;
    $v = $d['values'];
    $dev = $conn->real_escape_string($d['device'] ?? 'Transformer');
    $oil = isset($v['oil-temp']) ? floatval($v['oil-temp']) : null;
    $wind = isset($v['winding-temp']) ? floatval($v['winding-temp']) : null;
    $status = (($oil !== null && $oil > 80) || ($wind !== null && $wind > 100)) ? 'warning' : 'normal';

    $stmt = $conn->prepare("INSERT INTO transformer_readings (plant_id, device_name, oil_temp, winding_temp, status) VALUES (?,?,?,?,?)");
    if (!$stmt) { echo "[DB ERROR] Transformer prepare failed: " . $conn->error . "\n"; return; }
    $stmt->bind_param('ssdds', $unit, $dev, $oil, $wind, $status);
    if (!$stmt->execute()) { echo "[DB ERROR] Transformer execute failed: " . $stmt->error . "\n"; }
    else { echo "[DB] Transformer $dev inserted for $unit\n"; }
    $stmt->close();
}

function insertWeather($conn, $unit, $d) {
    if (!$d['values']) return;
    $v = $d['values'];
    $rad = floatval($v['raw data'] ?? 0);
    $ptemp = floatval($v['pannel temperature'] ?? 0);
    $wind = floatval($v['windspeed'] ?? 0);

    $stmt = $conn->prepare("INSERT INTO weather_readings (plant_id, radiation, panel_temp, wind_speed) VALUES (?,?,?,?)");
    if (!$stmt) { echo "[DB ERROR] Weather prepare failed: " . $conn->error . "\n"; return; }
    $stmt->bind_param('sddd', $unit, $rad, $ptemp, $wind);
    if (!$stmt->execute()) { echo "[DB ERROR] Weather execute failed: " . $stmt->error . "\n"; }
    else { echo "[DB] Weather inserted for $unit\n"; }
    $stmt->close();
}

$lastTelemetryInsert = [];

function insertTelemetry($conn, $unit, $type, $value) {
    global $lastTelemetryInsert;
    $key = $unit . '|' . $type;
    $now = time();
    if (isset($lastTelemetryInsert[$key]) && ($now - $lastTelemetryInsert[$key]) < 3600) {
        return;
    }
    $lastTelemetryInsert[$key] = $now;
    $stmt = $conn->prepare("INSERT INTO telemetry_history (plant_id, metric_type, metric_value) VALUES (?,?,?)");
    if (!$stmt) { echo "[DB ERROR] Telemetry prepare failed: " . $conn->error . "\n"; return; }
    $stmt->bind_param('ssd', $unit, $type, $value);
    if (!$stmt->execute()) { echo "[DB ERROR] Telemetry execute failed: " . $stmt->error . "\n"; }
    else { echo "[DB] Telemetry $type for $unit inserted (hourly)\n"; }
    $stmt->close();
}


$plants = ['vinoba-velliyanai', 'makkalpower', 'anushyam'];

// Time-limited mode: if RUN_SECONDS is set, run for that long then exit (for cron-based execution)
$runSeconds = 0;
if (getenv('RUN_SECONDS')) $runSeconds = intval(getenv('RUN_SECONDS'));
if (isset($_GET['run_seconds'])) $runSeconds = intval($_GET['run_seconds']);
if ($runSeconds > 0) {
    echo "[MAIN] Time-limited mode: will run for {$runSeconds}s then exit\n";
}
$startTime = time();

$ws = new SimpleWSClient('161.97.87.75', 5000, '/');

while (true) {
    if ($runSeconds > 0 && (time() - $startTime) >= $runSeconds) {
        echo "[MAIN] Time limit reached ({$runSeconds}s). Exiting cleanly.\n";
        $ws->close();
        exit(0);
    }
    if (!$ws->connect()) {
        echo "[MAIN] Retry in 5s...\n";
        sleep(5);
        $ws = new SimpleWSClient('161.97.87.75', 5000, '/');
        continue;
    }

    foreach ($plants as $p) {
        $ws->sendText(json_encode(['type' => 'subscribe', 'unit_id' => $p]));
        echo "[WS] Subscribed to $p\n";
    }

    while (true) {
        if ($runSeconds > 0 && (time() - $startTime) >= $runSeconds) {
            echo "[MAIN] Time limit reached ({$runSeconds}s). Exiting cleanly.\n";
            $ws->close();
            exit(0);
        }
        $frame = $ws->readFrame();
        if ($frame === null) {
            echo "[WS] Connection lost. Reconnecting...\n";
            break;
        }
        if ($frame['opcode'] === 'close') {
            echo "[WS] Server closed connection.\n";
            break;
        }
        if ($frame['opcode'] !== 'text' || empty($frame['payload'])) {
            continue;
        }

        $json = json_decode($frame['payload'], true);
        if (!$json || !isset($json['unit_id'])) continue;
        $unit = $json['unit_id'];
        $task = isset($json['task']) ? strtolower(strval($json['task'])) : '';
        $device = isset($json['device']) ? strtolower(strval($json['device'])) : '';

        $vcbKeys = ["R Phase-N Voltage", "L1 (R)", "Frequency (Hz)"];
        $hasVCBKeys = isset($json['values']) && count(array_intersect_key($json['values'], array_flip($vcbKeys))) > 0;
        $hasVCBPower = isset($json['values']['3 Phase Active Power']) && $hasVCBKeys;
        if ($task === 'vcb' || $device === 'vcb' || $hasVCBPower) {
            insertVcb($conn, $unit, $json);
            if (isset($json['values']['3 Phase Active Power'])) {
                insertTelemetry($conn, $unit, 'vcb_power', floatval($json['values']['3 Phase Active Power']));
            }
            continue;
        }

        if ($task === 'transformer') {
            insertTransformer($conn, $unit, $json);
            if (isset($json['values']['oil-temp'])) {
                insertTelemetry($conn, $unit, 'oil_temp', floatval($json['values']['oil-temp']));
            }
            if (isset($json['values']['winding-temp'])) {
                insertTelemetry($conn, $unit, 'winding_temp', floatval($json['values']['winding-temp']));
            }
            continue;
        }


        if (isset($json['values'])) {
            $keys = array_keys($json['values']);
            $kl = array_map('strtolower', $keys);
            if (in_array('raw data', $kl) || in_array('pannel temperature', $kl) || in_array('windspeed', $kl)) {
                insertWeather($conn, $unit, $json);
                if (isset($json['values']['raw data'])) {
                    insertTelemetry($conn, $unit, 'radiation', floatval($json['values']['raw data']));
                }
                continue;
            }
        }

        if ($json['values']) {
            $keys = array_keys($json['values']);
            $hasInvPower = false;
            $hasNumberedCurrents = false;
            foreach ($keys as $pk) {
                $pkl = strtolower($pk);
                if (preg_match('/power/', $pkl) && preg_match('/active|ac/', $pkl) && !preg_match('/reactive|apparent/', $pkl)) {
                    $hasInvPower = true;
                }
                if (preg_match('/\d/', $pk) && preg_match('/curr|current|amp/i', $pk) && !preg_match('/phase|3.phase|reactive|apparent|freq|temp/i', strtolower($pk))) {
                    $hasNumberedCurrents = true;
                }
            }
            $isInv = ($task === 'inverter') || $hasInvPower || $hasNumberedCurrents;
            if ($isInv && strpos($device, 'vcb') === false) {
                insertInverter($conn, $unit, $json);
                $pwr = 0;
                foreach ($json['values'] as $pk => $pv) {
                    $pkl = strtolower($pk);
                    if (preg_match('/active.*power|ac.*power|power.*ac|a\.c\..*power/', $pkl) && !preg_match('/reactive|apparent|3.phase/', $pkl)) {
                        $pwr = floatval($pv); break;
                    }
                }
                if ($pwr > 0) {
                    insertTelemetry($conn, $unit, 'inverter_power', $pwr);
                }
            }
        }
    }

    $ws->close();
    sleep(5);
}
?>
