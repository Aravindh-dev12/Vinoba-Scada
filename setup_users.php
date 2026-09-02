<?php
require 'config.php';
header('Content-Type: text/plain');

$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS plant_id VARCHAR(50) DEFAULT ''",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_token VARCHAR(128) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user'"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "OK: $q\n";
    } else {
        echo "SKIP/ERR: " . $conn->error . "\n";
    }
}

// $users = [
//     ['admin@nuclei.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin', ''],
//     ['veliyani@vinoba.com', password_hash('veliyani123', PASSWORD_DEFAULT), 'user', 'vinoba-velliyanai'],
//     ['makkal@makkal.com', password_hash('makkal123', PASSWORD_DEFAULT), 'user', 'makkalpower'],
//     ['anushyam@anushyam.com', password_hash('anushyam123', PASSWORD_DEFAULT), 'user', 'anushyam']
// ];

foreach ($users as $u) {
    $email = $u[0];
    $check = $conn->query("SELECT id FROM users WHERE email = '$email' LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $id = $check->fetch_assoc()['id'];
        $conn->query("UPDATE users SET password = '{$u[1]}', role = '{$u[2]}', plant_id = '{$u[3]}' WHERE id = $id");
        echo "Updated: $email\n";
    } else {
        $conn->query("INSERT INTO users (email, password, role, plant_id) VALUES ('{$u[0]}', '{$u[1]}', '{$u[2]}', '{$u[3]}')");
        echo "Inserted: $email\n";
    }
}

echo "\nDone. Default users are ready.\n";
?>
