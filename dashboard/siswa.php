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

$debug_riasec = null;
if (!empty($_SESSION['debug_riasec'])) {
    $debug_riasec = $_SESSION['debug_riasec'];
    unset($_SESSION['debug_riasec']);
}

function getBiasAdjustment(array $sorted_skor, string $jurusan_name): float {
    arsort($sorted_skor);
    $keys = array_keys($sorted_skor);
    $values = array_values($sorted_skor);
    $primary = $keys[0] ?? '';
    $secondary = $keys[1] ?? '';
    $tertiary = $keys[2] ?? '';
    $top_value = $values[0] ?? 0;
    $second_value = $values[1] ?? 0;
    $third_value = $values[2] ?? 0;
    $bonus = 0.0;

    $rc_ratio = $top_value > 0 ? $second_value / $top_value : 0;
    $cc_ratio = $second_value > 0 ? $third_value / $second_value : 0;

    if ($primary === 'R' && $secondary === 'I' && $tertiary === 'C') {
        if ($third_value < $second_value * 0.7) {
            if (in_array($jurusan_name, ['Teknik Sepeda Motor (TSM)', 'Teknik Pengelasan', 'Teknik Pemesinan (TPM)'], true)) {
                $bonus = 5.0;
            }
            if ($jurusan_name === 'Teknik Komputer Jaringan (TKJ)') {
                $bonus = -4.0;
            }
        } else {
            if ($jurusan_name === 'Teknik Komputer Jaringan (TKJ)') {
                $bonus = 2.0;
            }
            if ($jurusan_name === 'Teknik Kimia Industri') {
                $bonus = 2.0;
            }
        }
    }

    if ($primary === 'R' && $secondary === 'C') {
        if (in_array($jurusan_name, ['Teknik Instalasi Tenaga Listrik (TITL)', 'Teknik Tata & Pendingin Udara (TTPU)'], true)) {
            $bonus = 5.0;
        }
        if ($jurusan_name === 'Teknik Komputer Jaringan (TKJ)') {
            $bonus = -3.0;
        }
    }

    if (($primary === 'A' && $secondary === 'R') || ($primary === 'R' && $secondary === 'A')) {
        if (in_array($jurusan_name, ['Desain Produksi Busana', 'Desain Teknik Furnitur'], true)) {
            $bonus = 5.0;
        }
        if (in_array($jurusan_name, ['Teknik Komputer Jaringan (TKJ)', 'Teknik Kimia Industri'], true)) {
            $bonus = -2.0;
        }
    }

    if (($primary === 'I' && $secondary === 'C') || ($primary === 'C' && $secondary === 'I')) {
        if (in_array($jurusan_name, ['Teknik Komputer Jaringan (TKJ)', 'Teknik Kimia Industri'], true)) {
            $bonus = 5.0;
        }
    }

    if ($jurusan_name === 'Teknik Komputer Jaringan (TKJ)' && !in_array('I', [$primary, $secondary], true)) {
        $bonus -= 3.0;
    }

    if ($jurusan_name === 'Teknik Komputer Jaringan (TKJ)' && !in_array('C', [$primary, $secondary], true)) {
        $bonus -= 2.0;
    }

    return $bonus;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_asesmen']) && !$sudah_ujian) {
    $jawaban_siswa = $_POST['skala'] ?? [];
    $skor_riasec = ['R' => 0, 'I' => 0, 'A' => 0, 'E' => 0, 'C' => 0];
    $debug_log = [
        'skor_per_kategori' => [],
        'kode_user' => '',
        'nama_jurusan' => [],
    ];

    // 1. Hitung total skor dari semua pertanyaan RIASEC (tanpa S)
    $all_soal = $conn->query("SELECT id, kategori_riasec FROM soal_diagnostik");
    while ($s = $all_soal->fetch_assoc()) {
        $poin = isset($jawaban_siswa[$s['id']]) ? intval($jawaban_siswa[$s['id']]) : 0;
        if (isset($skor_riasec[$s['kategori_riasec']])) {
            $skor_riasec[$s['kategori_riasec']] += $poin;
        }
    }

    // 2. Kode siswa dibuat dari 3 kategori tertinggi tanpa S
    $sorted_skor = $skor_riasec;
    arsort($sorted_skor);
    $top_3_keys = array_slice(array_keys($sorted_skor), 0, 3);
    $kode_user = implode('', $top_3_keys);
    $user_letters = str_split($kode_user);
    $debug_log['skor_per_kategori'] = $skor_riasec;
    $debug_log['kode_user'] = $kode_user;

    // 3. Hitung skor untuk setiap jurusan berdasarkan semua kombinasi kode yang tersedia
    $jurusan_candidates = [];
    $max_original_sum = 0;
    $get_jurusan = $conn->query("SELECT id, nama_jurusan, kode_riasec_list, keterangan FROM master_jurusan");

    while ($j = $get_jurusan->fetch_assoc()) {
        $clean_list = str_replace(' ', '', $j['kode_riasec_list']);
        $kode_jurusan_arr = array_filter(array_map('trim', explode(',', $clean_list)), function($item) {
            return $item !== '';
        });

        $total_codes = count($kode_jurusan_arr);
        foreach ($kode_jurusan_arr as $index => $kode_jurusan) {
            $jurusan_letters = str_split($kode_jurusan);
            $temp_letters = $jurusan_letters;
            $common_letters = 0;

            foreach ($user_letters as $letter) {
                $pos = array_search($letter, $temp_letters);
                if ($pos !== false) {
                    $common_letters++;
                    unset($temp_letters[$pos]);
                }
            }

            $position_same = 0;
            foreach ($jurusan_letters as $idx => $letter) {
                if (isset($user_letters[$idx]) && $user_letters[$idx] === $letter) {
                    $position_same++;
                }
            }

            $same_order = ($kode_jurusan === $kode_user) ? 1 : 0;
            $raw_original_sum = 0;
            foreach ($jurusan_letters as $letter) {
                if (isset($skor_riasec[$letter])) {
                    $raw_original_sum += $skor_riasec[$letter];
                }
            }

            $max_original_sum = max($max_original_sum, $raw_original_sum);
            $jurusan_candidates[$j['id']]['nama'] = $j['nama_jurusan'];
            $jurusan_candidates[$j['id']]['keterangan'] = $j['keterangan'];
            $jurusan_candidates[$j['id']]['codes'][] = [
                'kode' => $kode_jurusan,
                'common_letters' => $common_letters,
                'position_same' => $position_same,
                'same_order' => $same_order,
                'raw_original_sum' => $raw_original_sum,
                'priority' => $total_codes > 0 ? ($total_codes - $index) / $total_codes : 1.0,
            ];
        }
    }

    $matriks_jurusan = [];
    foreach ($jurusan_candidates as $jurusan_id => $jurusan_data) {
        $best_score = -1;
        $best_code = null;
        $best_detail = null;

        foreach ($jurusan_data['codes'] as $code_data) {
            $user_len = max(1, count($user_letters));
            $letter_component = ($code_data['common_letters'] / $user_len) * 35;
            $position_component = ($code_data['position_same'] / $user_len) * 25;
            $order_bonus = $code_data['same_order'] ? 10.0 : 0.0;
            $original_component = ($max_original_sum > 0) ? ($code_data['raw_original_sum'] / $max_original_sum) * 25 : 0;
            $priority_boost = $code_data['priority'] * 5.0;
            $final_score = $letter_component + $position_component + $original_component + $order_bonus + $priority_boost;

            if ($final_score > $best_score) {
                $best_score = $final_score;
                $best_code = $code_data['kode'];
                $best_detail = [
                    'letter_component' => round($letter_component, 2),
                    'position_component' => round($position_component, 2),
                    'order_bonus' => round($order_bonus, 2),
                    'original_component' => round($original_component, 2),
                    'priority_boost' => round($priority_boost, 2),
                    'final_score' => round($final_score, 2),
                    'common_letters' => $code_data['common_letters'],
                    'position_same' => $code_data['position_same'],
                    'same_order' => $code_data['same_order'],
                    'raw_original_sum' => $code_data['raw_original_sum'],
                ];
            }
        }

        $bias_bonus = getBiasAdjustment($sorted_skor, $jurusan_data['nama']);
        $final_best_score = $best_score + $bias_bonus;
        $matriks_jurusan[] = [
            'nama' => $jurusan_data['nama'],
            'ket' => $jurusan_data['keterangan'],
            'kode_terbaik' => $best_code,
            'best_score' => $final_best_score,
            'bias_bonus' => round($bias_bonus, 2),
            'detail' => $best_detail,
        ];
    }

    usort($matriks_jurusan, function($a, $b) {
        return $b['best_score'] <=> $a['best_score'];
    });

    $rekomendasi_1 = isset($matriks_jurusan[0]) ? $matriks_jurusan[0]['nama'] : 'Teknik Komputer Jaringan (TKJ)';
    $rekomendasi_2 = isset($matriks_jurusan[1]) ? $matriks_jurusan[1]['nama'] : 'Teknik Kimia Industri';
    $rekomendasi_3 = isset($matriks_jurusan[2]) ? $matriks_jurusan[2]['nama'] : 'Teknik Instalasi Tenaga Listrik (TITL)';

    $alternatif_gabungan = "Opsi 2: " . $rekomendasi_2 . " | Opsi 3: " . $rekomendasi_3;

    $debug_log['nama_jurusan'] = array_map(function($item) {
        return [
            'nama' => $item['nama'],
            'best_code' => $item['kode_terbaik'],
            'best_score' => round($item['best_score'], 2),
            'detail' => $item['detail'],
        ];
    }, $matriks_jurusan);
    $_SESSION['debug_riasec'] = $debug_log;

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
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .14);
        }

        @media (max-width: 880px) {
            .soft-card {
                padding: 0.75rem;
                border-radius: 1.15rem;
                box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
            }

            .question-card {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
                border: 1px solid #e2e8f0;
            }

            .answer-card {
                min-height: 5.8rem;
                padding: 0.75rem;
            }

            .answer-card span {
                font-size: 0.92rem;
            }

            .question-status-label {
                font-size: 0.7rem;
            }

            .question-dot {
                width: 2.1rem;
                height: 2.1rem;
                font-size: 0.7rem;
            }

            .soft-card h2,
            .soft-card h3,
            .soft-card h4 {
                line-height: 1.2;
            }

            .soft-card p,
            .soft-card .text-sm,
            .soft-card .text-xs {
                font-size: 0.86rem;
            }

            .bg-blue-55 {
                background-color: #eff6ff;
            }

            .hidden-on-mobile {
                display: none !important;
            }
        }

        @media (max-width: 640px) {
            body {
                background: #fafafa;
            }

            nav {
                padding-top: 0.65rem;
                padding-bottom: 0.65rem;
            }

            .soft-card {
                padding: 0.65rem;
                border-radius: 1rem;
                box-shadow: none;
            }

            .question-card {
                padding: 0.65rem;
                margin-bottom: 0.65rem;
                border: 1px solid #e5e7eb;
            }

            .question-card h3 {
                font-size: 1.1rem;
            }

            .question-card .mb-6 {
                margin-bottom: 0.8rem;
            }

            .answer-card {
                min-height: 5rem;
                padding: 0.6rem;
                border-radius: 16px;
            }

            .answer-card span {
                font-size: 0.86rem;
            }

            .answer-row {
                display: flex;
                gap: 0.45rem;
                overflow-x: auto;
                padding-bottom: 0.4rem;
                margin: 0 -0.4rem;
                padding-left: 0.4rem;
            }

            .answer-row label {
                min-width: 80px;
                flex: 0 0 auto;
            }

            .answer-row::-webkit-scrollbar {
                height: 5px;
            }

            .answer-row::-webkit-scrollbar-thumb {
                background: rgba(37, 99, 235, 0.35);
                border-radius: 999px;
            }

            .answer-dot {
                width: 1.9rem;
                height: 1.9rem;
                font-size: .64rem;
            }

            .question-status-label {
                display: none;
            }

            .progress-sticky {
                position: sticky;
                top: 0;
                z-index: 30;
            }

            .grid.gap-5 {
                gap: 0.9rem;
            }

            .grid.gap-0 {
                gap: 0;
            }

            .text-3xl {
                font-size: 1.55rem;
            }
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
                        <?php if (!empty($debug_riasec)): ?>
                            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700 hidden sm:block">
                                <div class="mb-3 font-black uppercase tracking-[.18em] text-slate-500">Debug Perhitungan Skor</div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-2xl bg-slate-50 p-3">
                                        <div class="font-black text-slate-800">Skor Kategori</div>
                                        <ul class="mt-2 space-y-1 text-xs text-slate-600">
                                            <?php foreach ($debug_riasec['skor_per_kategori'] as $letter => $score): ?>
                                                <li><?= htmlspecialchars($letter); ?>: <?= intval($score); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-3">
                                        <div class="font-black text-slate-800">Kode Siswa</div>
                                        <div class="mt-2 text-lg font-black text-slate-900"><?= htmlspecialchars($debug_riasec['kode_user']); ?></div>
                                    </div>
                                </div>
                                <div class="mt-4 rounded-2xl bg-slate-50 p-3">
                                    <div class="font-black text-slate-800">Skor Jurusan Teratas</div>
                                    <div class="mt-3 space-y-3 text-xs text-slate-600">
                                        <?php foreach (array_slice($debug_riasec['nama_jurusan'], 0, 4) as $jur): ?>
                                            <div class="rounded-2xl border border-slate-200 bg-white p-3">
                                                <div class="font-bold text-slate-900"><?= htmlspecialchars($jur['nama']); ?> (<?= htmlspecialchars($jur['best_code']); ?>)</div>
                                                <div class="mt-1">Skor: <?= htmlspecialchars($jur['best_score']); ?> <span class="text-slate-400">(bias: <?= htmlspecialchars($jur['bias_bonus']); ?>)</span></div>
                                                <div class="mt-1 text-[11px] text-slate-500">Huruf cocok: <?= htmlspecialchars($jur['detail']['common_letters']); ?>, posisi benar: <?= htmlspecialchars($jur['detail']['position_same']); ?>, urutan sama: <?= htmlspecialchars($jur['detail']['same_order']); ?>, RIASEC asli: <?= htmlspecialchars($jur['detail']['raw_original_sum']); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
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
                        <div class="hidden md:flex items-center justify-center bg-blue-55 p-6">
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

            <section class="sticky top-[73px] z-30 mb-6 rounded-3xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur-xl progress-sticky">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="mb-2 flex items-center justify-between gap-4">
                            <p class="text-xs font-black uppercase tracking-[.16em] text-slate-500">Progress</p>
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

                        <div class="answer-row grid gap-3 sm:grid-cols-5">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <label class="cursor-pointer min-w-[90px]">
                                    <input type="radio" name="skala[<?= $s['id']; ?>]" value="<?= $i; ?>" required class="peer hidden">
                                    <div class="answer-card flex min-h-[78px] flex-col items-center justify-center rounded-2xl border border-slate-200 bg-white p-3 text-center font-black text-slate-500">
                                        <i class="check-icon fa-solid fa-circle-check mb-1 text-emerald-500 opacity-0 scale-75 transition"></i>
                                        <span class="text-xl"><?= $i; ?></span>
                                        <span class="mt-1 text-[10px] font-bold uppercase tracking-[.08em] text-slate-400">
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