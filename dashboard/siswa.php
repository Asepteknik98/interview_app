<?php
// dashboard/siswa.php
session_start();
require_once '../config/database.php';

// 1. PASTIKAN PROTEKSI MEMBACA SESSION YANG BARU
if (!isset($_SESSION['siswa_role']) || $_SESSION['siswa_role'] !== 'siswa') {
    header("Location: ../auth/login.php");
    exit;
}

// 2. SOLUSI ERROR LINE 16: Ambil data dari variabel session yang baru
// Jika kodingan lama Bapak di baris 16 menggunakan $_SESSION['id'], ganti menjadi $_SESSION['siswa_id']
$id_siswa     = $_SESSION['siswa_id']; 
$nama_lengkap = $_SESSION['siswa_nama'];

// --- TIPS AMAN ---
// Jika di bagian bawah kodingan siswa.php Bapak masih ada yang menggunakan variabel $_SESSION['id'],
// agar tidak ada yang terlewat, kita buatkan "jembatan" salinan di bawah ini:
$_SESSION['id']   = $_SESSION['siswa_id'];
$_SESSION['nama'] = $_SESSION['siswa_nama'];

$user_id = $_SESSION['id'];
$cek_hasil = $conn->query("SELECT * FROM hasil_ujian WHERE user_id = '$user_id' LIMIT 1");
$sudah_ujian = ($cek_hasil->num_rows > 0) ? $cek_hasil->fetch_assoc() : false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_asesmen']) && !$sudah_ujian) {
    $jawaban_siswa = $_POST['skala'] ?? [];
    $skor_riasec = ['R' => 0, 'I' => 0, 'A' => 0, 'S' => 0, 'E' => 0, 'C' => 0];

    // 1. Hitung total skor dari 18 pertanyaan riil
    $all_soal = $conn->query("SELECT id, kategori_riasec FROM soal_diagnostik");
    while ($s = $all_soal->fetch_assoc()) {
        $poin = isset($jawaban_siswa[$s['id']]) ? intval($jawaban_siswa[$s['id']]) : 0;
        $skor_riasec[$s['kategori_riasec']] += $poin;
    }

    // 2. Ambil 3 Huruf Tertinggi (Contoh Hasil: ISC)
    arsort($skor_riasec);
    $top_3_keys = array_slice(array_keys($skor_riasec), 0, 3);
    $kode_user = implode('', $top_3_keys); 

    // Pecah huruf user menjadi array: ['I', 'S', 'C']
    $array_huruf_user = str_split($kode_user);

    // 3. Logika Scoring Baru: Hitung Kedekatan Karakter Secara Mutlak dengan Gambar Panduan
    $matriks_jurusan = [];
    $get_jurusan = $conn->query("SELECT * FROM master_jurusan");
    
    while ($j = $get_jurusan->fetch_assoc()) {
        // Bersihkan spasi dan pecah 5 kode RIASEC bawaan jurusan di database
        $clean_list = str_replace(' ', '', $j['kode_riasec_list']);
        $kode_jurusan_arr = explode(',', $clean_list);
        
        $bobot_tertinggi_jurusan = 0;

        foreach ($kode_jurusan_arr as $kj) {
            $skor_cocok = 0;
            $array_huruf_jurusan = str_split($kj);

            // ATURAN 1: Cocok Sempurna 3 Huruf Posisi Sama (Skor Tertinggi)
            if ($kj === $kode_user) {
                $skor_cocok += 200;
            }
            // ATURAN 2: Dua Huruf Pertama Cocok Persis (Sesuai Panduan No. 2 pada Gambar)
            elseif (substr($kode_user, 0, 2) === substr($kj, 0, 2)) {
                $skor_cocok += 100;
            }
            // ATURAN 3: Satu Huruf Pertama (Minat Terkuat) Cocok Persis
            elseif (substr($kode_user, 0, 1) === substr($kj, 0, 1)) {
                $skor_cocok += 50;
            }

            // ATURAN 4: Hitung jumlah huruf yang beririsan tanpa melihat urutan (Sangat penting untuk kasus seperti ISC)
            $irisan = array_intersect($array_huruf_user, $array_huruf_jurusan);
            $skor_cocok += count($irisan) * 10; // Menambah bobot kemiripan elemen kepribadian

            // Ambil nilai loop terbaik untuk jurusan ini
            if ($skor_cocok > $bobot_tertinggi_jurusan) {
                $bobot_tertinggi_jurusan = $skor_cocok;
            }
        }

        $matriks_jurusan[] = [
            'nama' => $j['nama_jurusan'],
            'ket' => $j['keterangan'],
            'total_bobot' => $bobot_tertinggi_jurusan
        ];
    }

    // Urutkan peringkat jurusan berdasarkan total bobot dari yang paling relevan
    usort($matriks_jurusan, function($a, $b) {
        return $b['total_bobot'] <=> $a['total_bobot'];
    });

    // Ambil 3 Jurusan Teratas Hasil Perhitungan Karakter Mutlak
    $rekomendasi_1 = isset($matriks_jurusan[0]) ? $matriks_jurusan[0]['nama'] : 'Teknik Komputer Jaringan (TKJ)';
    $rekomendasi_2 = isset($matriks_jurusan[1]) ? $matriks_jurusan[1]['nama'] : 'Teknik Kimia Industri';
    $rekomendasi_3 = isset($matriks_jurusan[2]) ? $matriks_jurusan[2]['nama'] : 'Teknik Instalasi Tenaga Listrik (TITL)';

    // Masukkan Opsi 2 dan Opsi 3 ke dalam string pemisah agar struktur database aman
    $alternatif_gabungan = "Opsi 2: " . $rekomendasi_2 . " | Opsi 3: " . $rekomendasi_3;

    // Simpan hasil riil tanpa ada istilah "General Vocational" lagi
    $stmt = $conn->prepare("INSERT INTO hasil_ujian (user_id, kombinasi_kode, rekomendasi_jurusan, keterangan_jurusan) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $kode_user, $rekomendasi_1, $alternatif_gabungan);
    $stmt->execute();
                  
    header("Location: siswa.php"); 
    exit;
}

$soal_res = $conn->query("SELECT * FROM soal_diagnostik ORDER BY nomor_urut ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Diagnostik Minat - SMK Jaya Buana</title>
    <link rel="icon" type="image/png" href="../assets/jb.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans min-h-screen text-xs text-gray-700 pb-10">

    <nav class="bg-blue-900 text-white p-4 flex justify-between items-center shadow-md">
        <span class="font-bold uppercase tracking-wider text-[11px]"><i class="fa-solid fa-graduation-cap text-orange-400 mr-1"></i> PPDB SMK JAYA BUANA</span>
        <a href="../auth/logout.php" class="bg-red-600 hover:bg-red-700 px-3 py-1 font-bold rounded text-[10px] transition">Keluar</a>
    </nav>

    <main class="max-w-xl mx-auto mt-6 px-4">
        <?php if ($sudah_ujian): 
            // Bongkar kembali string alternatif untuk ditampilkan ke interface siswa
            $data_opsi = explode(' | Opsi 3: ', $sudah_ujian['keterangan_jurusan']);
            $opsi_2 = str_replace('Opsi 2: ', '', $data_opsi[0] ?? '-');
            $opsi_3 = $data_opsi[1] ?? '-';
        ?>
            <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200 space-y-5">
                <div class="text-center border-b pb-3">
                    <div class="w-12 h-12 bg-green-50 text-green-600 rounded-full flex items-center justify-center text-xl mx-auto mb-2 border border-green-200 shadow-sm">
                        <i class="fa-solid fa-square-poll-vertical"></i>
                    </div>
                    <h2 class="text-sm font-black text-gray-800 uppercase tracking-wide">Hasil Pemetaan Minat Selesai</h2>
                    <p class="text-gray-400 text-[10px] mt-0.5">Berdasarkan kalkulasi matriks kecocokan 5 kode RIASEC, berikut 3 jurusan terbaik Anda:</p>
                </div>
                
                <div class="flex items-center gap-4 bg-gradient-to-r from-orange-50 to-amber-50 p-4 rounded-xl border border-orange-200 shadow-sm">
                    <div class="bg-white p-2.5 rounded-xl border border-orange-200 text-center shrink-0 shadow-inner">
                        <span class="text-[8px] text-gray-400 block font-bold uppercase leading-none mb-1">KODE</span>
                        <span class="text-2xl font-black text-orange-600 font-mono tracking-tighter"><?= $sudah_ujian['kombinasi_kode']; ?></span>
                    </div>
                    <div>
                        <span class="px-2 py-0.5 bg-blue-700 text-white rounded font-bold text-[8px] uppercase tracking-wider shadow-sm">Rekomendasi Utama (Prioritas 1)</span>
                        <h3 class="text-xs font-black text-slate-900 mt-1.5 uppercase tracking-wide leading-tight"><?= $sudah_ujian['rekomendasi_jurusan']; ?></h3>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3.5 shadow-inner">
                    <span class="font-bold text-gray-400 uppercase tracking-wider text-[9px] block border-b pb-1.5"><i class="fa-solid fa-list-ol mr-1"></i> Pilihan Jurusan Alternatif Terdekat</span>
                    
                    <div class="flex items-start gap-3">
                        <span class="w-5 h-5 bg-amber-500 text-white rounded-full flex items-center justify-center font-bold text-[10px] shrink-0 shadow-sm">2</span>
                        <div class="space-y-0.5">
                            <h4 class="font-black text-gray-800 text-xs uppercase tracking-wide"><?= $opsi_2; ?></h4>
                            <p class="text-gray-400 text-[10px] leading-tight">Memiliki kecocokan indikator dan rumpun keahlian sekunder tinggi sesuai instrumen.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 pt-2.5 border-t border-gray-200">
                        <span class="w-5 h-5 bg-slate-500 text-white rounded-full flex items-center justify-center font-bold text-[10px] shrink-0 shadow-sm">3</span>
                        <div class="space-y-0.5">
                            <h4 class="font-black text-gray-800 text-xs uppercase tracking-wide"><?= $opsi_3; ?></h4>
                            <p class="text-gray-400 text-[10px] leading-tight">Memiliki keselarasan kompetensi dasar yang direkomendasikan sistem aplikasi.</p>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-blue-50 border border-blue-100 rounded-xl text-[10px] text-blue-800 leading-relaxed shadow-sm">
                    <i class="fa-solid fa-circle-info mr-1"></i> <strong>Catatan Kelulusan:</strong> Silakan screenshot atau foto halaman hasil ini menggunakan HP Anda, kemudian tunjukkan kepada panitia pendaftaran di loket PPDB SMK Jaya Buana untuk proses pemetaan kelas.
                </div>
            </div>
            
        <?php else: ?>
            <div class="bg-white p-4 rounded-xl shadow border mb-4 border-l-4 border-blue-900 shadow-sm">
                <h3 class="font-bold text-gray-800 text-xs">Petunjuk Pengisian</h3>
                <p class="text-gray-400 leading-normal">Pilih angka 1 (Sangat Tidak Setuju) sampai 5 (Sangat Setuju) yang paling sesuai dengan minat bakat dirimu.</p>
            </div>

            <form action="" method="POST" class="space-y-3.5">
                <input type="hidden" name="submit_asesmen" value="1">
                <?php while($s = $soal_res->fetch_assoc()): ?>
                    <div class="bg-white p-4 rounded-xl shadow border border-gray-100 space-y-3">
                        <p class="font-bold text-gray-800 leading-relaxed">
                            <span class="text-blue-700 font-mono"><?= $s['nomor_urut']; ?>.</span> <?= htmlspecialchars($s['pertanyaan']); ?>
                        </p>
                        <div class="grid grid-cols-5 gap-2 text-center">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <label class="cursor-pointer flex flex-col">
                                    <input type="radio" name="skala[<?= $s['id']; ?>]" value="<?= $i; ?>" required class="peer hidden">
                                    <div class="w-full text-center py-2 rounded-lg border border-gray-200 peer-checked:bg-blue-900 peer-checked:text-white peer-checked:border-blue-900 font-black text-gray-400 text-xs bg-gray-50/50 transition duration-150 hover:bg-gray-100"><?= $i; ?></div>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
                <button type="submit" onclick="return confirm('Apakah Anda yakin semua jawaban sudah terisi dengan benar?')" class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold py-3.5 rounded-xl shadow uppercase tracking-wider text-xs transition duration-150 mt-4">Simpan & Kirim Jawaban Asesmen</button>
            </form>
        <?php endif; ?>
    </main>

    <footer class="text-center py-4 text-[10px] text-gray-400 mt-8">&copy; 2026 Admin Panel PPDB SMK Jaya Buana</footer>
</body>
</html>