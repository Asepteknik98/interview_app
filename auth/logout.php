<?php
// auth/logout.php
session_start();

// Hapus semua session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Alihkan kembali ke root index (akan diarahkan otomatis ke login)
header("Location: ../index.php");
exit;
?>