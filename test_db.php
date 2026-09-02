<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "=== DB Connection Test ===\n\n";

// Test 1: localhost (TCP)
echo "Test 1: localhost (TCP)\n";
try {
    $c = new mysqli('localhost', 'root', 'Arun@811001', 'vinoba-velliyanai-scada');
    if ($c->connect_error) echo "FAIL: " . $c->connect_error . "\n";
    else { echo "OK - connected!\n"; $c->close(); }
} catch (Exception $e) { echo "FAIL: " . $e->getMessage() . "\n"; }

// Test 2: 127.0.0.1 (TCP explicit)
echo "\nTest 2: 127.0.0.1 (TCP)\n";
try {
    $c = new mysqli('127.0.0.1', 'root', 'Arun@811001', 'vinoba-velliyanai-scada');
    if ($c->connect_error) echo "FAIL: " . $c->connect_error . "\n";
    else { echo "OK - connected!\n"; $c->close(); }
} catch (Exception $e) { echo "FAIL: " . $e->getMessage() . "\n"; }

// Test 3: empty password
echo "\nTest 3: localhost with empty password\n";
try {
    $c = new mysqli('localhost', 'root', '', 'vinoba-velliyanai-scada');
    if ($c->connect_error) echo "FAIL: " . $c->connect_error . "\n";
    else { echo "OK - connected!\n"; $c->close(); }
} catch (Exception $e) { echo "FAIL: " . $e->getMessage() . "\n"; }

// Test 4: show config.php contents
echo "\n--- config.php contents ---\n";
echo file_get_contents(__DIR__ . '/config.php');

// Test 5: Check if mysql.sock path
echo "\n--- MySQL socket info ---\n";
echo "mysql.default_socket: " . ini_get('mysql.default_socket') . "\n";
echo "mysqli.default_socket: " . ini_get('mysqli.default_socket') . "\n";

// Test 6: phpMyAdmin config check
echo "\n--- Checking phpMyAdmin config ---\n";
$pmaPaths = [
    '/etc/phpmyadmin/config.inc.php',
    '/usr/share/phpmyadmin/config.inc.php',
    '/var/www/html/phpmyadmin/config.inc.php',
    __DIR__ . '/../phpmyadmin/config.inc.php',
];
foreach ($pmaPaths as $p) {
    if (file_exists($p)) {
        echo "Found: $p\n";
        $content = file_get_contents($p);
        if (preg_match('/\$cfg\[\'Servers\'\]\[\$i\]\[\'host\'\]\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            echo "  host: " . $m[1] . "\n";
        }
        if (preg_match('/\$cfg\[\'Servers\'\]\[\$i\]\[\'user\'\]\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            echo "  user: " . $m[1] . "\n";
        }
        if (preg_match('/\$cfg\[\'Servers\'\]\[\$i\]\[\'password\'\]\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            echo "  password: " . $m[1] . "\n";
        }
        if (preg_match('/\$cfg\[\'Servers\'\]\[\$i\]\[\'socket\'\]\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            echo "  socket: " . $m[1] . "\n";
        }
        break;
    }
}
?>
