<?php
// dashboard/export_excel.php
session_start();
require_once '../config/database.php';

// PERBAIKAN SINKRONISASI: Menggunakan $_SESSION['admin_role'] agar sinkron dengan file login terbaru
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    die("Akses ditolak.");
}

// Set header agar browser mengunduh format berkas spreadsheet CSV/Excel
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=REKAP_HASIL_ASESMEN_PPDB_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');

// Cetak BOM UTF-8 agar karakter spasi, simbol, dan baris terbaca rapi saat dibuka di Microsoft Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header Kolom Excel
fputcsv($output, ['NO', 'USERNAME / NIS', 'NAMA LENGKAP CALON SISWA', 'KODE RIASEC', 'REKOMENDASI UTAMA (P1)', 'JURUSAN ALTERNATIF (P2)', 'JURUSAN ALTERNATIF (P3)']);

$query = "SELECT users.username, users.nama_lengkap, hasil_ujian.kombinasi_kode, hasil_ujian.rekomendasi_jurusan, hasil_ujian.keterangan_jurusan 
          FROM users 
          LEFT JOIN hasil_ujian ON users.id = hasil_ujian.user_id 
          WHERE users.role = 'siswa' 
          ORDER BY users.nama_lengkap ASC";
$result = $conn->query($query);

$no = 1;
while ($row = $result->fetch_assoc()) {
    $opsi2 = "-"; $opsi3 = "-";
    if (!empty($row['keterangan_jurusan'])) {
        $ex = explode(' | Opsi 3: ', $row['keterangan_jurusan']);
        $opsi2 = str_replace('Opsi 2: ', '', $ex[0] ?? '-');
        $opsi3 = $ex[1] ?? '-';
    }

    fputcsv($output, [
        $no++,
        $row['username'],
        $row['nama_lengkap'],
        $row['kombinasi_kode'] ?? '-',
        $row['rekomendasi_jurusan'] ?? 'Belum Ujian',
        $opsi2,
        $opsi3
    ]);
}
fclose($output);
exit;