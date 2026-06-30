<?php
// dashboard/cetak_sertifikat.php
session_start();
require_once '../config/database.php';

// Proteksi Akses Admin
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    die("Akses ditolak.");
}

// Menangkap parameter dari URL
$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$data_cetak = [];

if ($aksi === 'kolektif') {
    // Ambil SEMUA siswa yang SUDAH MENGERJAKAN TES
    $query = "SELECT users.username, users.nama_lengkap, hasil_ujian.kombinasi_kode, hasil_ujian.rekomendasi_jurusan, hasil_ujian.keterangan_jurusan 
              FROM users 
              INNER JOIN hasil_ujian ON users.id = hasil_ujian.user_id 
              WHERE users.role = 'siswa' 
              ORDER BY users.nama_lengkap ASC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $data_cetak[] = $row;
    }
    
    if (empty($data_cetak)) {
        die("<script>alert('Belum ada data siswa yang sudah mengikuti tes untuk dicetak massal.'); window.close();</script>");
    }
} elseif ($user_id > 0) {
    // Ambil data SATU SISWA spesifik (Cetak Per Individu)
    $query = "SELECT users.username, users.nama_lengkap, hasil_ujian.kombinasi_kode, hasil_ujian.rekomendasi_jurusan, hasil_ujian.keterangan_jurusan 
              FROM users 
              INNER JOIN hasil_ujian ON users.id = hasil_ujian.user_id 
              WHERE users.id = $user_id AND users.role = 'siswa'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $data_cetak[] = $result->fetch_assoc();
    } else {
        die("<script>alert('Data ujian siswa tidak ditemukan atau siswa belum mengikuti tes.'); window.close();</script>");
    }
} else {
    die("Parameter cetak tidak valid.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Hasil Asesmen PPDB - SMK JAYA BUANA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            color: #000000;
        }
        .halaman-sertifikat {
            width: 210mm;
            min-height: 297mm; 
            padding: 20mm;
            margin: 0 auto;
            box-sizing: border-box;
            background: white;
            position: relative;
        }
        /* CSS Pemotong Halaman Otomatis saat cetak massal */
        .page-break {
            page-break-after: always;
        }
        .kop-surat {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .kop-surat h2 {
            margin: 0;
            font-size: 18pt;
            letter-spacing: 1px;
        }
        .kop-surat h3 {
            margin: 5px 0;
            font-size: 14pt;
        }
        .kop-surat p {
            margin: 0;
            font-size: 10pt;
            color: #555;
        }
        .judul-dokumen {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            text-decoration: underline;
            margin-bottom: 25px;
            text-transform: uppercase;
        }
        .tabel-biodata {
            width: 100%;
            margin-bottom: 30px;
            font-size: 11pt;
        }
        .tabel-biodata td {
            padding: 5px 0;
            vertical-align: top;
        }
        .kotak-hasil {
            border: 2px solid #000;
            padding: 20px;
            margin-bottom: 30px;
            background-color: #fafafa;
        }
        .kotak-hasil h4 {
            margin: 0 0 10px 0;
            font-size: 12pt;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .rekomendasi-utama {
            font-size: 14pt;
            font-weight: bold;
            color: #1e3a8a;
            margin: 10px 0;
        }
        .tanda-tangan-section {
            margin-top: 50px;
            width: 100%;
        }
        .tanda-tangan-box {
            float: right;
            text-align: center;
            width: 250px;
            font-size: 11pt;
        }
        .space-ttd {
            height: 80px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .halaman-sertifikat {
                margin: 0;
                padding: 10mm;
                width: 100%;
                min-height: auto;
            }
        }
        .btn-floating-print {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: #1e293b;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 11pt;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            border: none;
            cursor: pointer;
            z-index: 9999;
        }
    </style>
</head>
<body>

    <button onclick="window.print();" class="btn-floating-print no-print">
        Cetak / Simpan PDF
    </button>

    <?php 
    $total_data = count($data_cetak);
    foreach ($data_cetak as $index => $siswa): 
        $opsi2 = "-"; $opsi3 = "-";
        if (!empty($siswa['keterangan_jurusan'])) {
            $ex = explode(' | Opsi 3: ', $siswa['keterangan_jurusan']);
            $opsi2 = str_replace('Opsi 2: ', '', $ex[0] ?? '-');
            $opsi3 = $ex[1] ?? '-';
        }
        
        $style_page_break = ($index + 1 < $total_data) ? 'page-break' : '';
    ?>
    
    <div class="halaman-sertifikat <?= $style_page_break; ?>">
        <div class="kop-surat">
            <h2>SMK JAYA BUANA</h2>
            <h3>TEKNIK KOMPUTER DAN JARINGAN (TKJ)</h3>
            <p>Alamat Admin: Tangerang, Banten, Indonesia</p>
        </div>

        <div class="judul-dokumen">
            Hasil Asesmen Diagnostik Minat Bakat (RIASEC)
        </div>

        <table class="tabel-biodata">
            <tr>
                <td style="width: 30%;">Nama Lengkap</td>
                <td style="width: 3%;">:</td>
                <td style="font-weight: bold; text-transform: uppercase;"><?= htmlspecialchars($siswa['nama_lengkap']); ?></td>
            </tr>
            <tr>
                <td>Username / NIS</td>
                <td>:</td>
                <td><?= htmlspecialchars($siswa['username']); ?></td>
            </tr>
        </table>

        <div class="kotak-hasil">
            <h4>1. Hasil Kode Kategori Kompetensi</h4>
            <div style="font-size: 20pt; font-weight: bold; letter-spacing: 2px; color: #b45309;">
                <?= !empty($siswa['kombinasi_kode']) ? htmlspecialchars($siswa['kombinasi_kode']) : '-'; ?>
            </div>
        </div>

        <div class="kotak-hasil">
            <h4>2. Rekomendasi Penjurusan Kompetensi Keahlian</h4>
            <span style="font-size: 10pt; color: #666; font-weight: bold;">PILIHAN 1 (REKOMENDASI UTAMA):</span>
            <div class="rekomendasi-utama"><?= !empty($siswa['rekomendasi_jurusan']) ? htmlspecialchars($siswa['rekomendasi_jurusan']) : 'Belum Mengikuti Ujian'; ?></div>

            <div style="margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 10px;">
                <table style="width: 100%; font-size: 11pt;">
                    <tr>
                        <td style="width: 50%;"><strong>Pilihan 2 (Alternatif):</strong><br><?= htmlspecialchars($opsi2); ?></td>
                        <td style="width: 50%;"><strong>Pilihan 3 (Alternatif):</strong><br><?= htmlspecialchars($opsi3); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="tanda-tangan-section">
            <div class="tanda-tangan-box">
                <p>Tangerang, <?= date('d F Y'); ?></p>
                <p>Panitia PPDB SMK Jaya Buana,</p>
                <div class="space-ttd"></div>
                <p>__________________________</p>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

    <?php endforeach; ?>

    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>