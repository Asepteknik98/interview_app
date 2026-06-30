<?php
// index.php
session_start();

// Jika sudah login, arahkan ke dashboard masing-masing
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: dashboard/admin.php");
        exit;
    } else if ($_SESSION['role'] == 'siswa') {
        header("Location: dashboard/siswa.php");
        exit;
    }
} else {
    // Jika belum login, lempar ke halaman loginauth
    header("Location: auth/login.php");
    exit;
}
?>