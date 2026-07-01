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
<!DOCTYPEDOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tes Diagnostik Minat - SMK Jaya Buana</title>
    <link rel="icon" type="image/png" href="../assets/jb.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --ink: #0f172a;
            --page: #f8fafc;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: 
                radial-gradient(circle at top left, rgba(37, 99, 235, .10), transparent 32rem),
                linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            color: #334155;
        }

        .soft-card {
            background: rgba(255, 255, 255, .92);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .07);
        }

        .answer-card {
            transition: border-color .18s ease, background .18s ease, color .18s ease, transform .18s ease, box-shadow .18s ease;
        }

        .answer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, .08);
        }

        input[type="radio"]:checked + .answer-card {
            background: #eff6ff;
            border-color: var(--primary);
            color: #1d4ed8;
            box-shadow: 0 14px 28px rgba(37, 99, 235, .12);
        }

        input[type="radio"]:checked + .answer-card .check-icon {
            opacity: 1;
            transform: scale(1);
        }

        .question-card {
            animation: fadeIn .22s ease-out both;
        }

        .question-card.hidden {
            display: none !important;
        }

        .question-dot {
            transition: all .18s ease;
        }

        .question-dot.answered {
            background: #22c55e;
            color: #fff;
            border-color: #22c55e;
        }

        .question-dot.active {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .14);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="min-h-screen font-sans antialiased">
    <nav class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur-xl">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <img src="../assets/jb.png" alt="Logo SMK Jaya Buana" class="h-9 w-9 object-contain">
                </div>
                <div class="min-w-0">
                    <h1 class="truncate text-sm font-black text-slate-900 sm:text-base">Tes Diagnostik Minat & Bakat</h1>
                    <p class="truncate text-xs font-semibold text-slate-500"><?= htmlspecialchars($nama_lengkap); ?></p>
                </div>
            </div>

            <div class="hidden min-w-[180px] md:block">
                <div class="mb-1 flex items-center justify-between text-[11px] font-black uppercase tracking-[.14em] text-slate-400">
                    <span>Progress</span>
                    <span id="navProgressPercent">0%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div id="navProgressBar" class="h-full rounded-full bg-blue-600 transition-all duration-300" style="width:0%"></div>
                </div>
            </div>

            <a href="../auth/logout.php" class="inline-flex items-center gap-2 rounded-2xl bg-red-500 px-4 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-red-600">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="hidden sm:inline">Logout</span>
            </a>
        </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
        <?php if ($sudah_ujian): 
            // Bongkar kembali string alternatif untuk ditampilkan ke interface siswa
            $data_opsi = explode(' | Opsi 3: ', $sudah_ujian['keterangan_jurusan']);
            $opsi_2 = str_replace('Opsi 2: ', '', $data_opsi[0] ?? '-');
            $opsi_3 = $data_opsi[1] ?? '-';
        ?>
            <section class="space-y-6">
                <div class="soft-card overflow-hidden">
                    <div class="grid gap-0 lg:grid-cols-[1.05fr,.95fr]">
                        <div class="bg-gradient-to-br from-blue-700 via-blue-600 to-cyan-500 p-7 text-white sm:p-10">
                            <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-black uppercase tracking-[.16em] text-blue-50">
                                <i class="fa-solid fa-circle-check"></i> Tes Selesai
                            </div>
                            <h2 class="mt-5 text-3xl font-black leading-tight sm:text-4xl">Selamat, <?= htmlspecialchars($nama_lengkap); ?>!</h2>
                            <p class="mt-4 max-w-xl text-sm font-medium leading-7 text-blue-50 sm:text-base">Terima kasih telah menyelesaikan Tes Diagnostik Minat dan Bakat. Berikut hasil rekomendasi jurusan yang paling sesuai berdasarkan jawaban Anda.</p>
                        </div>
                        <div class="flex items-center justify-center bg-white p-8">
                            <div class="relative flex h-52 w-52 items-center justify-center rounded-full bg-blue-50">
                                <div class="absolute h-40 w-40 rounded-full border-[18px] border-blue-100"></div>
                                <div class="absolute h-40 w-40 rounded-full border-[18px] border-transparent border-t-blue-600 border-r-emerald-500"></div>
                                <div class="text-center">
                                    <p class="text-xs font-black uppercase tracking-[.18em] text-slate-400">Kode</p>
                                    <p class="font-mono text-5xl font-black text-blue-700"><?= $sudah_ujian['kombinasi_kode']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-[1.2fr,.8fr]">
                    <div class="soft-card p-6 sm:p-7">
                        <div class="mb-5 flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                                <i class="fa-solid fa-award text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.16em] text-emerald-600">Jurusan Direkomendasikan</p>
                                <h3 class="text-2xl font-black text-slate-900"><?= $sudah_ujian['rekomendasi_jurusan']; ?></h3>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <p class="text-sm font-medium leading-7 text-slate-600">Rekomendasi ini dihitung dari kombinasi kode RIASEC Anda dan kedekatannya dengan matriks jurusan yang tersedia pada sistem.</p>
                            <div class="mt-4 inline-flex items-center gap-2 rounded-full bg-blue-50 px-4 py-2 text-sm font-black text-blue-700 ring-1 ring-blue-100">
                                <i class="fa-solid fa-chart-simple"></i> Kecocokan utama sistem
                            </div>
                        </div>
                    </div>

                    <div class="soft-card p-6 sm:p-7">
                        <h3 class="text-lg font-black text-slate-900">Alternatif Jurusan</h3>
                        <div class="mt-5 space-y-4">
                            <div class="flex items-start gap-3 rounded-2xl border border-amber-100 bg-amber-50 p-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-500 text-sm font-black text-white">2</span>
                                <div>
                                    <h4 class="font-black uppercase text-slate-900"><?= $opsi_2; ?></h4>
                                    <p class="mt-1 text-xs font-medium leading-5 text-slate-500">Pilihan alternatif dengan kedekatan indikator minat yang tinggi.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-600 text-sm font-black text-white">3</span>
                                <div>
                                    <h4 class="font-black uppercase text-slate-900"><?= $opsi_3; ?></h4>
                                    <p class="mt-1 text-xs font-medium leading-5 text-slate-500">Pilihan tambahan yang masih selaras dengan kompetensi dasar Anda.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="soft-card p-5">
                    <div class="flex items-start gap-3 text-sm font-medium leading-7 text-blue-800">
                        <div class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                            <i class="fa-solid fa-circle-info"></i>
                        </div>
                        <div>
                            <p><strong>Catatan:</strong> Silakan screenshot atau foto halaman hasil ini menggunakan HP Anda, kemudian tunukkan kepada panitia pendaftaran di loket PPDB SMK Jaya Buana untuk proses pemetaan kelas.</p>
                        </div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="mb-6 grid gap-5 lg:grid-cols-[1.3fr,.7fr]">
                <div class="soft-card overflow-hidden">
                    <div class="grid gap-0 md:grid-cols-[1fr_220px]">
                        <div class="p-6 sm:p-8">
                            <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-black uppercase tracking-[.16em] text-blue-700">
                                <i class="fa-solid fa-pen-to-square"></i> Tes Diagnostik
                            </div>
                            <h2 class="mt-5 text-3xl font-black leading-tight text-slate-900">Selamat Datang, <?= htmlspecialchars($nama_lengkap); ?></h2>
                            <p class="mt-4 max-w-2xl text-sm font-medium leading-7 text-slate-600">Silakan jawab seluruh pertanyaan sesuai dengan kondisi dan minat Anda. Tidak ada jawaban benar atau salah. Hasil akan digunakan sebagai rekomendasi jurusan yang paling sesuai.</p>
                        </div>
                        <div class="flex items-center justify-center bg-blue-55 p-6">
                            <div class="relative h-40 w-40">
                                <div class="absolute inset-0 rounded-[2rem] bg-blue-600 rotate-6"></div>
                                <div class="absolute inset-0 rounded-[2rem] bg-white p-5 shadow-xl">
                                    <div class="mb-4 flex items-center justify-between">
                                        <span class="h-3 w-12 rounded-full bg-blue-100"></span>
                                        <i class="fa-solid fa-check-circle text-emerald-500"></i>
                                    </div>
                                    <div class="space-y-3">
                                        <span class="block h-3 rounded-full bg-slate-100"></span>
                                        <span class="block h-3 rounded-full bg-slate-100"></span>
                                        <span class="block h-3 w-2/3 rounded-full bg-slate-100"></span>
                                    </div>
                                    <div class="mt-6 grid grid-cols-3 gap-2">
                                        <span class="h-8 rounded-xl bg-blue-100"></span>
                                        <span class="h-8 rounded-xl bg-emerald-100"></span>
                                        <span class="h-8 rounded-xl bg-amber-100"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="soft-card p-5">
                    <h3 class="text-base font-black text-slate-900">Petunjuk Singkat</h3>
                    <div class="mt-4 space-y-3">
                        <div class="flex gap-3"><i class="fa-solid fa-book-open mt-1 text-blue-600"></i><p class="text-sm font-medium leading-6 text-slate-600">Bacalah setiap pertanyaan dengan teliti.</p></div>
                        <div class="flex gap-3"><i class="fa-solid fa-user-check mt-1 text-emerald-600"></i><p class="text-sm font-medium leading-6 text-slate-600">Pilih jawaban yang paling sesuai dengan diri Anda.</p></div>
                        <div class="flex gap-3"><i class="fa-solid fa-users-slash mt-1 text-rose-500"></i><p class="text-sm font-medium leading-6 text-slate-600">Jangan meminta teman menjawab.</p></div>
                        <div class="flex gap-3"><i class="fa-solid fa-heart mt-1 text-amber-500"></i><p class="text-sm font-medium leading-6 text-slate-600">Jawablah dengan jujur.</p></div>
                        <div class="flex gap-3"><i class="fa-solid fa-list-check mt-1 text-indigo-600"></i><p class="text-sm font-medium leading-6 text-slate-600">Semua soal wajib dijawab.</p></div>
                    </div>
                </div>
            </section>

            <section class="sticky top-[73px] z-30 mb-6 rounded-3xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur-xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="mb-2 flex items-center justify-between gap-4">
                            <p class="text-xs font-black uppercase tracking-[.16em] text-slate-500">Progress Pengerjaan</p>
                            <p id="progressPercent" class="text-sm font-black text-blue-700">0%</p>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                            <div id="progressBar" class="h-full rounded-full bg-blue-600 transition-all duration-300" style="width:0%"></div>
                        </div>
                        <p id="progressText" class="mt-2 text-xs font-bold text-slate-500">0 dari 0 pertanyaan selesai.</p>
                    </div>
                    <div id="questionDots" class="flex max-w-full gap-2 overflow-x-auto pb-1"></div>
                </div>
            </section>

            <form action="" method="POST" class="space-y-5" id="assessmentForm">
                <input type="hidden" name="submit_asesmen" value="1">
                <?php while($s = $soal_res->fetch_assoc()): ?>
                    <div class="question-card soft-card p-6 sm:p-8" data-question-card data-question-number="<?= $s['nomor_urut']; ?>">
                        <div class="mb-6 flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.16em] text-blue-700">Pertanyaan <?= $s['nomor_urut']; ?></p>
                                <h3 class="mt-3 text-2xl font-black leading-snug text-slate-900"><?= htmlspecialchars($s['pertanyaan']); ?></h3>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-500">
                                <i class="fa-regular fa-circle"></i> <span class="question-status-label">Belum dijawab</span>
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-5">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="skala[<?= $s['id']; ?>]" value="<?= $i; ?>" required class="peer hidden">
                                    <div class="answer-card flex min-h-[92px] flex-col items-center justify-center rounded-2xl border border-slate-200 bg-white p-4 text-center font-black text-slate-500">
                                        <i class="check-icon fa-solid fa-circle-check mb-2 text-emerald-500 opacity-0 scale-75 transition"></i>
                                        <span class="text-2xl"><?= $i; ?></span>
                                        <span class="mt-1 text-[11px] font-bold uppercase tracking-[.08em] text-slate-400">
                                            <?= $i === 1 ? 'Tidak' : ($i === 2 ? 'Kurang' : ($i === 3 ? 'Netral' : ($i === 4 ? 'Setuju' : 'Sangat'))) ?>
                                        </span>
                                    </div>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endwhile; ?>

                <div class="soft-card p-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <button type="button" id="prevQuestion" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-100 px-5 py-4 text-sm font-black text-slate-700 transition hover:bg-slate-200">
                            <i class="fa-solid fa-arrow-left"></i> Sebelumnya
                        </button>
                        <button type="button" id="nextQuestion" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-4 text-sm font-black text-white shadow-sm transition hover:bg-blue-700 sm:col-span-2">
                            Selanjutnya <i class="fa-solid fa-arrow-right"></i>
                        </button>
                        <button type="submit" id="submitAssessment" onclick="return confirm('Apakah Anda yakin seluruh jawaban sudah benar? Jawaban tidak dapat diubah setelah dikirim.')" class="hidden inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-4 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 sm:col-span-2">
                            <i class="fa-solid fa-paper-plane"></i> Selesai Tes
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <footer class="px-4 py-6 text-center text-xs font-bold text-slate-400">&copy; 2026 PPDB Web System &bull; SMK Jaya Buana</footer>

    <script>
        const questionCards = Array.from(document.querySelectorAll('[data-question-card]'));
        const progressBar = document.getElementById('progressBar');
        const navProgressBar = document.getElementById('navProgressBar');
        const progressPercent = document.getElementById('progressPercent');
        const navProgressPercent = document.getElementById('navProgressPercent');
        const progressText = document.getElementById('progressText');
        const questionDots = document.getElementById('questionDots');
        const prevQuestion = document.getElementById('prevQuestion');
        const nextQuestion = document.getElementById('nextQuestion');
        const submitAssessment = document.getElementById('submitAssessment');
        const assessmentForm = document.getElementById('assessmentForm');
        let currentQuestion = 0;

        function answeredCount() {
            return questionCards.filter(card => card.querySelector('input[type="radio"]:checked')).length;
        }

        function renderDots() {
            if (!questionDots || questionDots.children.length) return;
            questionCards.forEach((card, index) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'question-dot flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-xs font-black text-slate-500';
                dot.innerText = card.getAttribute('data-question-number') || (index + 1);
                dot.addEventListener('click', () => {
                    currentQuestion = index;
                    showQuestion(currentQuestion);
                });
                questionDots.appendChild(dot);
            });
        }

        function updateProgress() {
            const done = answeredCount();
            const total = questionCards.length;
            const percent = total > 0 ? Math.round((done / total) * 100) : 0;
            
            if (progressBar) progressBar.style.width = `${percent}%`;
            if (navProgressBar) navProgressBar.style.width = `${percent}%`;
            if (progressPercent) progressPercent.innerText = `${percent}%`;
            if (navProgressPercent) navProgressPercent.innerText = `${percent}%`;
            if (progressText) progressText.innerText = `${done} dari ${total} pertanyaan selesai.`;

            questionCards.forEach((card, index) => {
                const answered = !!card.querySelector('input[type="radio"]:checked');
                const label = card.querySelector('.question-status-label');
                const statusIcon = card.querySelector('.fa-regular, .fa-solid');
                
                if (label) label.innerText = answered ? 'Sudah dijawab' : 'Belum dijawab';
                if (statusIcon) {
                    statusIcon.classList.toggle('fa-circle-check', answered);
                    statusIcon.classList.toggle('fa-circle', !answered);
                    statusIcon.classList.toggle('text-emerald-500', answered);
                }
                const dot = questionDots ? questionDots.children[index] : null;
                if (dot) {
                    dot.classList.toggle('answered', answered);
                    dot.classList.toggle('active', index === currentQuestion);
                }
            });
        }

        function showQuestion(index) {
            questionCards.forEach((card, idx) => {
                card.classList.toggle('hidden', idx !== index);
            });

            if (prevQuestion) {
                prevQuestion.disabled = index === 0;
                prevQuestion.classList.toggle('opacity-50', index === 0);
            }

            const isLast = index === questionCards.length - 1;
            if (nextQuestion) nextQuestion.classList.toggle('hidden', isLast);
            if (submitAssessment) submitAssessment.classList.toggle('hidden', !isLast);

            updateProgress();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        questionCards.forEach(card => {
            card.querySelectorAll('input[type="radio"]').forEach(input => {
                input.addEventListener('change', () => {
                    updateProgress();
                    if (currentQuestion < questionCards.length - 1) {
                        setTimeout(() => {
                            currentQuestion++;
                            showQuestion(currentQuestion);
                        }, 180);
                    }
                });
            });
        });

        if (prevQuestion) {
            prevQuestion.addEventListener('click', () => {
                if (currentQuestion > 0) {
                    currentQuestion--;
                    showQuestion(currentQuestion);
                }
            });
        }

        if (nextQuestion) {
            nextQuestion.addEventListener('click', () => {
                if (currentQuestion < questionCards.length - 1) {
                    currentQuestion++;
                    showQuestion(currentQuestion);
                }
            });
        }

        if (assessmentForm && submitAssessment) {
            assessmentForm.addEventListener('submit', () => {
                submitAssessment.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim Jawaban...';
                submitAssessment.disabled = true;
            });
        }

        if (questionCards.length) {
            renderDots();
            showQuestion(0);
        }
    </script>
</body>
</html>