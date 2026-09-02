<?php
$token = isset($_GET['token']) ? $_GET['token'] : '';
$user = null;

if ($token) {
    require 'config.php';
    $safeToken = $conn->real_escape_string($token);
    $res = $conn->query("SELECT * FROM users WHERE auth_token = '$safeToken' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
    }
}

if (!$user) {
    header('Location: index.php');
    exit;
}

$currentPlant = isset($_GET['plant']) ? $_GET['plant'] : '';
// Only non-admin users are restricted to their assigned plant
if ($user['role'] !== 'admin' && !empty($user['plant_id'])) {
    if (empty($currentPlant)) {
        $query = $_GET;
        $query['plant'] = $user['plant_id'];
        $currentPage = basename($_SERVER['PHP_SELF']);
        header('Location: ' . $currentPage . '?' . http_build_query($query));
        exit;
    }
    if ($currentPlant !== $user['plant_id']) {
        $redirect = 'home.php?plant=' . urlencode($user['plant_id']) . '&token=' . urlencode($token);
        header('Location: ' . $redirect);
        exit;
    }
}
?>
