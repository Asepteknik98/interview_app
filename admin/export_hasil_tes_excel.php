<?php
// admin/export_hasil_tes_excel.php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// Require PhpSpreadsheet
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
$useCsvFallback = false;
if (file_exists($vendorAutoload)) {
    require $vendorAutoload;
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Fill;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
} else {
    // Fall back to CSV output so admin can export immediately without composer
    $useCsvFallback = true;
}

// Read input params
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$q      = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build base WHERE clause (same logic as admin.php)
$where_base = "WHERE users.role = 'siswa'";
if ($filter === 'sudah') {
    $where_base .= " AND hasil_ujian.user_id IS NOT NULL";
} elseif ($filter === 'belum') {
    $where_base .= " AND hasil_ujian.user_id IS NULL";
}

// For export we only want rows that have hasil (rekomendasi_jurusan not empty)
$where_export = $where_base . " AND hasil_ujian.rekomendasi_jurusan IS NOT NULL AND hasil_ujian.rekomendasi_jurusan != ''";

$search_sql = '';
if ($q !== '') {
    $q_esc = $conn->real_escape_string($q);
    $search_sql = " AND (users.username LIKE '%$q_esc%' OR users.nama_lengkap LIKE '%$q_esc%' OR hasil_ujian.kombinasi_kode LIKE '%$q_esc%' OR hasil_ujian.rekomendasi_jurusan LIKE '%$q_esc%' OR hasil_ujian.keterangan_jurusan LIKE '%$q_esc%')";
}

// Query to fetch exported rows (matching what appears in Hasil Tes table)
$sql = "SELECT users.id, users.username, users.nama_lengkap, hasil_ujian.kombinasi_kode, hasil_ujian.rekomendasi_jurusan, hasil_ujian.keterangan_jurusan, hasil_ujian.persentase, hasil_ujian.tanggal_tes
        FROM users
        LEFT JOIN hasil_ujian ON users.id = hasil_ujian.user_id
        $where_export
        $search_sql
        ORDER BY users.nama_lengkap ASC";

$result = $conn->query($sql);
if (!$result) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Query gagal dijalankan.']);
    exit;
}

$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}

if (count($rows) === 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Tidak ada data hasil tes untuk diekspor.']);
    exit;
}

// Also compute totals for rekap
// Total students matching base filter and search (without requiring rekomendasi)
$sql_total = "SELECT COUNT(DISTINCT users.id) as total FROM users LEFT JOIN hasil_ujian ON users.id = hasil_ujian.user_id $where_base $search_sql";
$total_res = $conn->query($sql_total);
$total_students = 0;
if ($total_res) {
    $total_students = intval($total_res->fetch_assoc()['total'] ?? 0);
}

$export_count = count($rows);
$belum_count = max(0, $total_students - $export_count);

// Rekap per jurusan (from exported rows)
$rekap = [];
foreach ($rows as $rr) {
    $jur = $rr['rekomendasi_jurusan'] ?: 'Tidak Ditetapkan';
    if (!isset($rekap[$jur])) $rekap[$jur] = 0;
    $rekap[$jur]++;
}

// Create spreadsheet
if ($useCsvFallback) {
    // Output CSV with same columns and a simple header block
    header('Content-Type: text/csv; charset=utf-8');
    $filenameCsv = 'Hasil_Tes_PPDB_' . date('Y-m-d') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filenameCsv . '"');
    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    // Header block
    fputcsv($out, ['SMK JAYA BUANA']);
    fputcsv($out, ['LAPORAN HASIL DIAGNOSTIK MINAT DAN BAKAT SISWA']);
    fputcsv($out, ['PPDB Tahun Ajaran ' . date('Y')]);
    fputcsv($out, ['Tanggal Export : ' . date('d-m-Y H:i:s')]);
    fputcsv($out, ['Nama Administrator : ' . $adminName]);
    fputcsv($out, []);
    // Table header
    fputcsv($out, ['No', 'Username / NIS', 'Nama Lengkap', 'Status Tes', 'Kode RIASEC', 'Jurusan Rekomendasi', 'Jurusan Alternatif 1', 'Jurusan Alternatif 2', 'Persentase / Nilai', 'Tanggal Tes']);
    $no = 1;
    foreach ($rows as $r) {
        $o2 = '-'; $o3 = '-';
        if (!empty($r['keterangan_jurusan'])) {
            $ex = explode(' | Opsi 3: ', $r['keterangan_jurusan']);
            $o2 = str_replace('Opsi 2: ', '', $ex[0] ?? '-');
            $o3 = $ex[1] ?? '-';
        }
        fputcsv($out, [
            $no++,
            $r['username'],
            $r['nama_lengkap'],
            !empty($r['rekomendasi_jurusan']) ? 'Sudah Tes' : 'Belum Tes',
            $r['kombinasi_kode'],
            $r['rekomendasi_jurusan'],
            $o2,
            $o3,
            $r['persentase'] ?? '',
            $r['tanggal_tes'] ?? ''
        ]);
    }
    // Rekap
    fputcsv($out, []);
    fputcsv($out, ['Rekap Jurusan']);
    foreach ($rekap as $jur => $jml) {
        fputcsv($out, [$jur, $jml]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Total Siswa :', $total_students]);
    fputcsv($out, ['Sudah Tes :', $export_count]);
    fputcsv($out, ['Belum Tes :', $belum_count]);
    fclose($out);
    exit;
}

// Header area
$sheet->setCellValue('A1', 'SMK JAYA BUANA');
$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A2', 'LAPORAN HASIL DIAGNOSTIK MINAT DAN BAKAT SISWA');
$sheet->mergeCells('A2:J2');
$sheet->setCellValue('A3', 'PPDB Tahun Ajaran ' . date('Y'));
$sheet->mergeCells('A3:J3');
$sheet->setCellValue('A4', 'Tanggal Export : ' . date('d-m-Y H:i:s'));
$sheet->mergeCells('A4:J4');
$adminName = $_SESSION['admin_username'] ?? ($_SESSION['admin_nama'] ?? 'Administrator');
$sheet->setCellValue('A5', 'Nama Administrator : ' . $adminName);
$sheet->mergeCells('A5:J5');

// Table header start row
$startRow = 7;
$headers = [
    'No', 'Username / NIS', 'Nama Lengkap', 'Status Tes', 'Kode RIASEC', 'Jurusan Rekomendasi', 'Jurusan Alternatif 1', 'Jurusan Alternatif 2', 'Persentase / Nilai', 'Tanggal Tes'
];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col.$startRow, $h);
    $col++;
}

// Style header
$headerRange = 'A' . $startRow . ':J' . $startRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getRowDimension($startRow)->setRowHeight(22);

// Fill data
$rowIndex = $startRow + 1;
$no = 1;
foreach ($rows as $r) {
    $o2 = '-'; $o3 = '-';
    if (!empty($r['keterangan_jurusan'])) {
        $ex = explode(' | Opsi 3: ', $r['keterangan_jurusan']);
        $o2 = str_replace('Opsi 2: ', '', $ex[0] ?? '-');
        $o3 = $ex[1] ?? '-';
    }
    $sheet->setCellValue('A'.$rowIndex, $no);
    $sheet->setCellValue('B'.$rowIndex, $r['username']);
    $sheet->setCellValue('C'.$rowIndex, $r['nama_lengkap']);
    $sheet->setCellValue('D'.$rowIndex, !empty($r['rekomendasi_jurusan']) ? 'Sudah Tes' : 'Belum Tes');
    $sheet->setCellValue('E'.$rowIndex, $r['kombinasi_kode']);
    $sheet->setCellValue('F'.$rowIndex, $r['rekomendasi_jurusan']);
    $sheet->setCellValue('G'.$rowIndex, $o2);
    $sheet->setCellValue('H'.$rowIndex, $o3);
    $sheet->setCellValue('I'.$rowIndex, $r['persentase'] ?? '');
    $sheet->setCellValue('J'.$rowIndex, $r['tanggal_tes'] ?? '');

    // borders for row
    $sheet->getStyle("A{$rowIndex}:J{$rowIndex}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$rowIndex}:J{$rowIndex}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

    $no++;
    $rowIndex++;
}

// Auto width
foreach (range('A','J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Wrap text for some columns
$sheet->getStyle('C'.($startRow+1).':C'.$rowIndex)->getAlignment()->setWrapText(true);
$sheet->getStyle('F'.($startRow+1).':H'.$rowIndex)->getAlignment()->setWrapText(true);

// Freeze header and set autofilter
$sheet->freezePane('A'.($startRow+1));
$sheet->setAutoFilter($headerRange);

// Rekapitulasi below table
$rekapStart = $rowIndex + 2;
$sheet->setCellValue('A'.$rekapStart, 'Rekap Jurusan');
$sheet->getStyle('A'.$rekapStart)->getFont()->setBold(true);

$k = $rekapStart + 1;
foreach ($rekap as $jur => $jml) {
    $sheet->setCellValue('A'.$k, $jur);
    $sheet->setCellValue('B'.$k, $jml);
    $sheet->getStyle('A'.$k.':B'.$k)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $k++;
}

// Totals
$k += 1;
$sheet->setCellValue('A'.$k, 'Total Siswa :');
$sheet->setCellValue('B'.$k, $total_students);
$sheet->setCellValue('A'.($k+1), 'Sudah Tes :');
$sheet->setCellValue('B'.($k+1), $export_count);
$sheet->setCellValue('A'.($k+2), 'Belum Tes :');
$sheet->setCellValue('B'.($k+2), $belum_count);

// Final styling for totals
$sheet->getStyle('A'.($k).':B'.($k+2))->getFont()->setBold(true);

// Prepare download
$filename = 'Hasil_Tes_PPDB_' . date('Y-m-d') . '.xlsx';

// Output file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Filename: ' . $filename);

$writer = new Xlsx($spreadsheet);
// Ensure UTF-8
// PhpSpreadsheet handles UTF-8 strings; ensure output buffering off
ob_end_clean();
$writer->save('php://output');
exit;
