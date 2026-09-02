<?php

error_reporting(0);
header('Content-Type: application/json');
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

date_default_timezone_set("Asia/Kolkata");

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    ob_clean();
    echo json_encode(["success" => false, "error" => "Invalid JSON"]);
    exit;
}

$plantId   = $input["plantId"]   ?? "";
$task      = $input["task"]      ?? "";
$device    = $input["device"]    ?? "";
$deviceId  = $input["deviceId"]  ?? "";
$tag       = $input["tag"]       ?? "";
$value     = $input["value"]     ?? "";
$level     = $input["level"]     ?? "";
$channels  = $input["channels"]  ?? [];
$timestamp = $input["timestamp"] ?? date("Y-m-d H:i:s");

$telegram  = $input["telegram"] ?? null;
$logOnly   = $input["logOnly"]   ?? false;

if ($plantId === "" || $tag === "" || $level === "") {
    ob_clean();
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit;
}

$logDir = __DIR__;
$logFile = $logDir . '/alert.log';
$logLine = sprintf(
    "[%s] Plant:%s Task:%s Device:%s(%s) Tag:%s Value:%s Level:%s\n",
    $timestamp,
    $plantId,
    $task,
    $device,
    $deviceId,
    $tag,
    $value,
    $level
);
file_put_contents($logFile, $logLine, FILE_APPEND);

/* ---------- LEVEL 3 : LOG ONLY ---------- */
if ($level === "3" || $logOnly === true) {
    ob_clean();
    echo json_encode(["success" => true, "message" => "Logged only"]);
    exit;
}

if (in_array("telegram", $channels) && $telegram) {
    $apiKey = $telegram["apiKey"] ?? "";
    $chatId = $telegram["chatId"] ?? "";

    if ($apiKey && $chatId) {
        $msg =
            "🚨 *VINOBA SOLAR ALERT*\n" .
            "*Plant:* {$plantId}\n" .
            "*Task:* {$task}\n" .
            "*Device:* {$device} ({$deviceId})\n" .
            "*Tag:* {$tag}\n" .
            "*Value:* {$value}\n" .
            "*Level:* {$level}\n" .
            "*Time:* {$timestamp}";

        $url = "https://api.telegram.org/bot{$apiKey}/sendMessage";
        $payload = [
            "chat_id" => $chatId,
            "text" => $msg,
            "parse_mode" => "Markdown"
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 5
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            ob_clean();
            echo json_encode(["success" => false, "error" => $err]);
            exit;
        }
    }
}

ob_clean();
echo json_encode(["success" => true, "message" => "Alert processed"]);
?>
