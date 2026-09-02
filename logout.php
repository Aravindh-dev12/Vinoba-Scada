<?php
session_start();
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Logging out...</title>
<noscript><meta http-equiv="refresh" content="0; url=index.php"></noscript>
</head>
<body>
<script>
    localStorage.removeItem('userRole');
    localStorage.removeItem('vs_token');
    localStorage.removeItem('vs_user');
    sessionStorage.removeItem('userRole');
    sessionStorage.removeItem('vs_token');
    sessionStorage.removeItem('vs_user');
    window.location.replace('index.php');
</script>
<p style="text-align:center;margin-top:40px;font-family:sans-serif;color:#666;">Logging out...</p>
</body>
</html>
