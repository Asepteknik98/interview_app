<?php
// dashboard/cetak_siswa.php
session_start();
require_once '../config/database.php';

// PERBAIKAN: Menggunakan session admin_role yang baru agar akses tidak ditolak
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    die("Akses ditolak. Anda harus login sebagai Admin untuk mencetak dokumen ini.");
}

$id_siswa = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data siswa dan hasil ujiannya
$query = "SELECT users.username, users.nama_lengkap, hasil_ujian.kombinasi_kode, hasil_ujian.rekomendasi_jurusan, hasil_ujian.keterangan_jurusan 
          FROM users 
          LEFT JOIN hasil_ujian ON users.id = hasil_ujian.user_id 
          WHERE users.id = $id_siswa AND users.role = 'siswa'";
$result = $conn->query($query);
$data = $result->fetch_assoc();

if (!$data) {
    die("Data siswa tidak ditemukan.");
}

// Pecah data opsi jurusan alternatif
$opsi2 = "-"; $opsi3 = "-";
if (!empty($data['keterangan_jurusan'])) {
    $ex = explode(' | Opsi 3: ', $data['keterangan_jurusan']);
    $opsi2 = str_replace('Opsi 2: ', '', $ex[0] ?? '-');
    $opsi3 = $ex[1] ?? '-';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil Asesmen_<?= $data['username']; ?></title>
    <link rel="icon" type="image/png" href="../assets/jb.png">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        .kop-surat {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .kop-surat h2 {
            margin: 0;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .kop-surat p {
            margin: 4px 0 0 0;
            font-size: 11px;
            color: #666;
        }

        .tabel-info {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }

        .tabel-info td {
            padding: 6px 4px;
            vertical-align: top;
        }

        .box-hasil {
            border: 2px dashed #1e3a8a;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .box-hasil h3 {
            margin-top: 0;
            color: #1e3a8a;
            font-size: 13px;
            text-transform: uppercase;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .jurusan-utama {
            font-size: 14px;
            font-weight: bold;
            color: #059669;
            margin: 10px 0;
        }

        .btn-print {
            background: #1e3a8a;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 20px;
        }

        @media print {
            .btn-print {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Cetak / Simpan PDF</button>

    <div class="kop-surat">
        <h2>PANITIA PENERIMAAN PESERTA DIDIK BARU (PPDB)</h2>
        <h2>SMK JAYA BUANA</h2>
        <p>Jl. Raya Tengger - Kemuning, Kec. Kresek, Kabupaten Tangerang, Banten</p>
    </div>

    <h3 style="text-align: center; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 25px;">LEMBAR HASIL DIAGNOSTIK MINAT & BAKAT (RIASEC)</h3>

    <table class="tabel-info" width="100%">
        <tr>
            <td width="25%"><strong>NOMOR URUT / NIS</strong></td>
            <td width="2%">:</td>
            <td><?= htmlspecialchars($data['username']); ?></td>
        </tr>
        <tr>
            <td><strong>NAMA LENGKAP CALON SISWA</strong></td>
            <td>:</td>
            <td style="text-transform: uppercase; font-weight: bold;"><?= htmlspecialchars($data['nama_lengkap']); ?></td>
        </tr>
        <tr>
            <td><strong>STATUS ASESMEN</strong></td>
            <td>:</td>
            <td><?= !empty($data['rekomendasi_jurusan']) ? 'TELAH SELESAI' : 'BELUM MENGERJAKAN TES'; ?></td>
        </tr>
    </table>

    <?php if(!empty($data['rekomendasi_jurusan'])): ?>
        <div class="box-hasil">
            <h3>Kombinasi Tipologi Kepribadian</h3>
            <div style="font-size: 16px; font-weight: black; font-family: monospace; color: #ea580c; margin: 5px 0;">
                KODE RIASEC: <?= $data['kombinasi_kode']; ?>
            </div>
            
            <h3 style="margin-top: 20px;">Rekomendasi Kluster Program Keahlian</h3>
            <div class="jurusan-utama">PRIORITAS 1 (UTAMA): <?= $data['rekomendasi_jurusan']; ?></div>
            <div style="font-weight: bold; color: #475569;">PRIORITAS 2 (ALTERNATIF): <?= $opsi2; ?></div>
            <div style="font-weight: bold; color: #475569; margin-top: 3px;">PRIORITAS 3 (ALTERNATIF): <?= $opsi3; ?></div>
        </div>
        
        <p style="margin-top: 30px; font-size: 11px; color: #666; font-style: italic;">
            * Dokumen ini merupakan hasil kalkulasi otomatis sistem aplikasi PPDB SMK Jaya Buana berdasarkan instrumen kuesioner RIASEC yang diisi secara mandiri oleh calon siswa. Digunakan sebagai berkas pendukung prasyarat wawancara pemetaan jurusan.
        </p>
    <?php else: ?>
        <div style="text-align: center; border: 1px solid #ccc; padding: 20px; color: #999; font-style: italic; border-radius: 8px;">
            Siswa yang bersangkutan belum melakukan pengisian kuesioner minat bakat online. Hasil rekomendasi pemetaan kluster belum dapat dicetak.
        </div>
    <?php endif; ?>

    <table width="100%" style="margin-top: 60px;">
        <tr>
            <td width="60%"></td>
            <td style="text-align: center;">
                Tangerang, <?= date('d F Y'); ?><br>
                Tim Penguji / Pewawancara,<br><br><br><br>

                <span style="display:inline-block; border-bottom:1px solid #000; padding:0 20px;">
                    ROFIQ OKVIANTO, M.Pd
                </span>
            </td>
        </tr>
    </table>

</body>
</html>