<?php
// admin/import_siswa_excel.php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    http_response_code(403);
    echo "Akses ditolak.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard/admin.php');
    exit;
}

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    echo "File tidak diunggah atau terjadi kesalahan upload.";
    echo '<p><a href="../dashboard/admin.php">Kembali</a></p>';
    exit;
}

$upload = $_FILES['import_file'];
$filename = $upload['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$tmp = $upload['tmp_name'];

$rows = [];

$vendor = __DIR__ . '/../vendor/autoload.php';
$usePhpSpreadsheet = false;
if (file_exists($vendor)) {
    require $vendor;
    $usePhpSpreadsheet = class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory');
}

if ($usePhpSpreadsheet && in_array($ext, ['xlsx','xls'])) {
    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true);
        foreach ($data as $i => $line) {
            // Expect columns in order A= username, B=nama_lengkap, C=password(optional)
            $u = trim($line['A'] ?? '');
            $n = trim($line['B'] ?? '');
            $p = trim($line['C'] ?? '');
            if ($u !== '' && $n !== '') $rows[] = [$u, $n, $p];
        }
    } catch (Exception $e) {
        echo "Gagal membaca file Excel: " . $e->getMessage();
        echo '<p><a href="../dashboard/admin.php">Kembali</a></p>';
        exit;
    }
} else {
    // Fallback: parse CSV
    if ($ext !== 'csv') {
        // try to handle as csv even if extension differs
    }
    $fh = fopen($tmp, 'r');
    if (!$fh) {
        echo "Gagal membuka file.";
        echo '<p><a href="../dashboard/admin.php">Kembali</a></p>';
        exit;
    }
    while (($data = fgetcsv($fh, 0, ',')) !== false) {
        // skip empty lines
        if (count($data) < 2) continue;
        $u = trim($data[0]);
        $n = trim($data[1]);
        $p = isset($data[2]) ? trim($data[2]) : '';
        if ($u !== '' && $n !== '') $rows[] = [$u, $n, $p];
    }
    fclose($fh);
}

if (count($rows) === 0) {
    echo "Tidak ditemukan baris valid untuk diimpor.";
    echo '<p><a href="../dashboard/admin.php">Kembali</a></p>';
    exit;
}

$imported = 0;
$skipped = 0;
$errors = [];

foreach ($rows as $r) {
    list($username, $nama, $password) = $r;
    // normalize
    $username = $conn->real_escape_string($username);
    $nama = $conn->real_escape_string($nama);
    if ($password === '') $password = $username;
    $password_encrypted = md5($password);

    // check duplicate
    $cek = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($cek && $cek->num_rows > 0) {
        $skipped++;
        continue;
    }

    $query = "INSERT INTO users (username, password, nama_lengkap, role) VALUES ('$username', '$password_encrypted', '$nama', 'siswa')";
    if ($conn->query($query)) {
        $imported++;
    } else {
        $errors[] = "Gagal import $username: " . $conn->error;
    }
}

// Summary output
// Determine if request is AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

$summary = [
    'processed' => count($rows),
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => $errors
];

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'summary' => $summary]);
    exit;
} else {
    echo '<div style="max-width:800px;margin:40px auto;font-family:Arial,Helvetica,sans-serif">';
    echo '<h2>Hasil Import Siswa</h2>';
    echo '<p>Jumlah baris diproses: ' . $summary['processed'] . '</p>';
    echo '<p>Berhasil diimpor: ' . $summary['imported'] . '</p>';
    echo '<p>Dilewati (duplikat): ' . $summary['skipped'] . '</p>';
    if (!empty($summary['errors'])) {
        echo '<h3>Errors</h3><ul>';
        foreach ($summary['errors'] as $err) echo '<li>' . htmlspecialchars($err) . '</li>';
        echo '</ul>';
    }
    echo '<p><a href="../dashboard/admin.php">Kembali ke Dashboard</a></p>';
    echo '</div>';
}
