<?php
$host = "localhost";
$username = "root"; 
$password = "Arun@811001";
$dbname = "vinoba-velliyanai-scada";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    $isApi = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'json') !== false;
    if ($isApi || (defined('JSON_RESPONSE') && JSON_RESPONSE)) {
        header('Content-Type: application/json');
        die(json_encode(["status" => "error", "message" => "DB connection failed"]));
    }
    $dbError = "Database connection failed. Please check config.php credentials.";
}
?>