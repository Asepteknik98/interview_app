<?php
// dashboard/hapus_soal.php
session_start();
require_once '../config/database.php';

// Validasi Hak Akses Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Tangkap ID dari parameter URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Jalankan query hapus data
    $query_delete = "DELETE FROM soal_diagnostik WHERE id = $id";
    $conn->query($query_delete);
}

// Kembalikan halaman ke dashboard utama admin setelah proses selesai
header("Location: admin.php");
exit;
?>