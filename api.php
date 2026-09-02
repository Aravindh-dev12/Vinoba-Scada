<?php

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/*
 * Log PHP errors instead of printing HTML errors into the JSON response.
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function sendJson(int $statusCode, array $response): void
{
    http_response_code($statusCode);

    echo json_encode(
        $response,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

function getRequestData(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput !== false && trim($rawInput) !== '') {
        $jsonData = json_decode($rawInput, true);

        if (is_array($jsonData)) {
            return $jsonData;
        }
    }

    // Supports FormData and normal HTML forms too.
    return $_POST;
}

function getBearerToken(): string
{
    $authorization = '';

    if (function_exists('getallheaders')) {
        $headers = getallheaders();

        $authorization =
            $headers['Authorization']
            ?? $headers['authorization']
            ?? '';
    }

    if ($authorization === '') {
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    }

    if (preg_match('/Bearer\s+(\S+)/i', $authorization, $matches)) {
        return $matches[1];
    }

    return '';
}

function getUserByToken(mysqli $conn, string $token): ?array
{
    $statement = $conn->prepare(
        'SELECT id, email, role, plant_id, auth_token
         FROM users
         WHERE auth_token = ?
         LIMIT 1'
    );

    $statement->bind_param('s', $token);
    $statement->execute();
    $statement->store_result();

    if ($statement->num_rows === 0) {
        $statement->close();
        return null;
    }

    $statement->bind_result(
        $id,
        $email,
        $role,
        $plantId,
        $authToken
    );

    $statement->fetch();
    $statement->close();

    return [
        'id' => $id,
        'email' => $email,
        'role' => $role,
        'plant_id' => $plantId,
        'auth_token' => $authToken
    ];
}

try {
    /*
     * __DIR__ makes sure PHP loads config.php from the same folder
     * as api.php.
     */
    require_once __DIR__ . '/config.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException(
            'config.php did not create a valid $conn MySQLi connection.'
        );
    }

    if ($conn->connect_errno) {
        throw new RuntimeException(
            'Database connection failed: ' . $conn->connect_error
        );
    }

    $conn->set_charset('utf8mb4');

    $action = $_GET['action'] ?? '';
    $data = getRequestData();

    /*
     * LOGIN
     */
    if ($action === 'login') {
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            sendJson(400, [
                'status' => 'error',
                'message' => 'Email and password are required.'
            ]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJson(422, [
                'status' => 'error',
                'message' => 'Enter a valid email address.'
            ]);
        }

        /*
         * Avoid get_result(), because some PHP servers do not have
         * the mysqlnd extension. Missing mysqlnd can cause your 500 error.
         */
        $statement = $conn->prepare(
            'SELECT id, email, password, role, plant_id
             FROM users
             WHERE email = ?
             LIMIT 1'
        );

        $statement->bind_param('s', $email);
        $statement->execute();
        $statement->store_result();

        if ($statement->num_rows === 0) {
            $statement->close();

            sendJson(401, [
                'status' => 'error',
                'message' => 'Invalid email or password.'
            ]);
        }

        $statement->bind_result(
            $userId,
            $userEmail,
            $storedPassword,
            $userRole,
            $plantId
        );

        $statement->fetch();
        $statement->close();

        /*
         * Supports hashed passwords and temporarily supports old
         * plaintext passwords.
         */
        $passwordInformation = password_get_info($storedPassword);

        $isHashedPassword =
            ($passwordInformation['algoName'] ?? 'unknown') !== 'unknown';

        if ($isHashedPassword) {
            $passwordIsCorrect = password_verify(
                $password,
                $storedPassword
            );
        } else {
            $passwordIsCorrect = hash_equals(
                (string) $storedPassword,
                $password
            );
        }

        if (!$passwordIsCorrect) {
            sendJson(401, [
                'status' => 'error',
                'message' => 'Invalid email or password.'
            ]);
        }

        /*
         * Convert an old plaintext password into a secure hash.
         */
        if (!$isHashedPassword) {
            $newPasswordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $passwordUpdate = $conn->prepare(
                'UPDATE users SET password = ? WHERE id = ?'
            );

            $passwordUpdate->bind_param(
                'si',
                $newPasswordHash,
                $userId
            );

            $passwordUpdate->execute();
            $passwordUpdate->close();
        }

        $token = bin2hex(random_bytes(32));

        $tokenUpdate = $conn->prepare(
            'UPDATE users SET auth_token = ? WHERE id = ?'
        );

        $tokenUpdate->bind_param(
            'si',
            $token,
            $userId
        );

        $tokenUpdate->execute();
        $tokenUpdate->close();

        sendJson(200, [
            'status' => 'success',
            'token' => $token,
            'user' => [
                'email' => $userEmail,
                'role' => $userRole,
                'plant_id' => $plantId
            ]
        ]);
    }

    /*
     * GET CURRENT USER
     */
    if ($action === 'get_user') {
        $token = getBearerToken();

        if ($token === '') {
            sendJson(401, [
                'status' => 'error',
                'message' => 'Authorization token is required.'
            ]);
        }

        $user = getUserByToken($conn, $token);

        if ($user === null) {
            sendJson(401, [
                'status' => 'error',
                'message' => 'Invalid or expired session.'
            ]);
        }

        sendJson(200, [
            'status' => 'success',
            'user' => [
                'email' => $user['email'],
                'role' => $user['role'],
                'plant_id' => $user['plant_id']
            ]
        ]);
    }

    /*
     * ADD USER
     */
    if ($action === 'add_user') {
        $token = getBearerToken();
        $currentUser = $token !== ''
            ? getUserByToken($conn, $token)
            : null;

        if ($currentUser === null || $currentUser['role'] !== 'admin') {
            sendJson(403, [
                'status' => 'error',
                'message' => 'Administrator access is required.'
            ]);
        }

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $plantId = trim((string) ($data['plant_id'] ?? ''));
        $role = trim((string) ($data['role'] ?? 'user'));

        if ($email === '' || $password === '') {
            sendJson(400, [
                'status' => 'error',
                'message' => 'Email and password are required.'
            ]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJson(422, [
                'status' => 'error',
                'message' => 'Enter a valid email address.'
            ]);
        }

        if (strlen($password) < 8) {
            sendJson(422, [
                'status' => 'error',
                'message' => 'Password must contain at least 8 characters.'
            ]);
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            sendJson(422, [
                'status' => 'error',
                'message' => 'Invalid user role.'
            ]);
        }

        $checkStatement = $conn->prepare(
            'SELECT id FROM users WHERE email = ? LIMIT 1'
        );

        $checkStatement->bind_param('s', $email);
        $checkStatement->execute();
        $checkStatement->store_result();

        if ($checkStatement->num_rows > 0) {
            $checkStatement->close();

            sendJson(409, [
                'status' => 'error',
                'message' => 'An account with this email already exists.'
            ]);
        }

        $checkStatement->close();

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $insertStatement = $conn->prepare(
            'INSERT INTO users (email, password, role, plant_id)
             VALUES (?, ?, ?, ?)'
        );

        $insertStatement->bind_param(
            'ssss',
            $email,
            $passwordHash,
            $role,
            $plantId
        );

        $insertStatement->execute();
        $insertStatement->close();

        sendJson(201, [
            'status' => 'success',
            'message' => 'User created successfully.'
        ]);
    }

    sendJson(404, [
        'status' => 'error',
        'message' => 'Unknown API action.'
    ]);
} catch (Throwable $error) {
    error_log(
        sprintf(
            'API error: %s in %s on line %d',
            $error->getMessage(),
            $error->getFile(),
            $error->getLine()
        )
    );

    sendJson(500, [
        'status' => 'error',
        'message' => 'An internal server error occurred. Check the PHP error log.'
    ]);
}