<?php
// config/database.php

$host     = "localhost";
$username = "root";
$password = "";
$database = "db_diagnostik_smkjb";

$conn = new mysqli($host, $username, $password, $database);

// Periksa Koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
?>