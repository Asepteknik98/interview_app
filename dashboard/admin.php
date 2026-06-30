<?php
// dashboard/admin.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$notif = "";

// Aksi 1: Reset Ujian Siswa
if (isset($_GET['aksi']) && $_GET['aksi'] == 'reset' && isset($_GET['id'])) {
    $id_reset = intval($_GET['id']);
    if ($conn->query("DELETE FROM hasil_ujian WHERE user_id = $id_reset")) {
        $notif = "<div class='p-4 mb-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl text-xs font-bold shadow-sm flex items-center gap-2 animate-fade-in'><i class='fa-solid fa-rotate-left text-sm'></i> Akses ujian siswa berhasil di-reset!</div>";
    }
}

// Aksi 2: Hapus Akun Siswa
if (isset($_GET['aksi']) && $_GET['aksi'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = intval($_GET['id']);
    $conn->query("DELETE FROM hasil_ujian WHERE user_id = $id_hapus");
    if ($conn->query("DELETE FROM users WHERE id = $id_hapus AND role = 'siswa'")) {
        $notif = "<div class='p-4 mb-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold shadow-sm flex items-center gap-2 animate-fade-in'><i class='fa-solid fa-trash-can text-sm'></i> Data pendaftaran siswa berhasil dihapus permanen!</div>";
    }
}

// Aksi 3: Simpan Pembaruan Data Siswa
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_siswa'])) {
    $id_edit    = intval($_POST['id_siswa']);
    $nama_baru  = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $password_b = trim($_POST['password_baru']);
    
    if (!empty($nama_baru)) {
        if (!empty($password_b)) {
            $pass_encrypted = md5($password_b);
            $query_update = "UPDATE users SET nama_lengkap = '$nama_baru', password = '$pass_encrypted' WHERE id = $id_edit AND role = 'siswa'";
        } else {
            $query_update = "UPDATE users SET nama_lengkap = '$nama_baru' WHERE id = $id_edit AND role = 'siswa'";
        }
        
        if ($conn->query($query_update)) {
            $notif = "<div class='p-4 mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold shadow-sm flex items-center gap-2 animate-fade-in'><i class='fa-solid fa-user-check text-sm'></i> Profil data siswa berhasil diperbarui!</div>";
        }
    }
}

// Aksi 4: Pendaftaran Akun Registrasi Siswa Baru
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tambah_siswa'])) {
    $username     = mysqli_real_escape_string($conn, trim($_POST['username_siswa']));
    $nama_lengkap = mysqli_real_escape_string($conn, trim($_POST['nama_siswa']));
    $password     = trim($_POST['password_siswa']);

    if (!empty($username) && !empty($nama_lengkap) && !empty($password)) {
        $password_encrypted = md5($password);
        $cek_user = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($cek_user->num_rows > 0) {
            $notif = "<div class='p-4 mb-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold shadow-sm flex items-center gap-2 animate-fade-in'><i class='fa-solid fa-circle-exclamation text-sm'></i> Gagal! Username '$username' telah digunakan.</div>";
        } else {
            $query_siswa = "INSERT INTO users (username, password, nama_lengkap, role) VALUES ('$username', '$password_encrypted', '$nama_lengkap', 'siswa')";
            if ($conn->query($query_siswa)) {
                $notif = "<div class='p-4 mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold shadow-sm flex items-center gap-2 animate-fade-in'><i class='fa-solid fa-circle-check text-sm'></i> Akun siswa baru berhasil didaftarkan!</div>";
            }
        }
    }
}

// METRICS
$count_total = $conn->query("SELECT COUNT(DISTINCT id) as total FROM users WHERE role = 'siswa'")->fetch_assoc()['total'] ?? 0;
$count_sudah = $conn->query("SELECT COUNT(DISTINCT user_id) as sudah FROM hasil_ujian")->fetch_assoc()['sudah'] ?? 0;
$count_belum = max(0, $count_total - $count_sudah);
$persentase  = ($count_total > 0) ? round(($count_sudah / $count_total) * 100, 1) : 0;

$filter_status = isset($_GET['filter']) ? trim($_GET['filter']) : ''; 

$where_clause = "WHERE users.role = 'siswa'";
if ($filter_status === 'sudah') {
    $where_clause .= " AND hasil_ujian.user_id IS NOT NULL";
} elseif ($filter_status === 'belum') {
    $where_clause .= " AND hasil_ujian.user_id IS NULL";
}

$query_siswa_full = "SELECT users.id, users.username, users.nama_lengkap, hasil_ujian.kombinasi_kode, hasil_ujian.rekomendasi_jurusan, hasil_ujian.keterangan_jurusan 
                     FROM users 
                     LEFT JOIN hasil_ujian ON users.id = hasil_ujian.user_id 
                     $where_clause 
                     ORDER BY users.nama_lengkap ASC";
$siswa_result = $conn->query($query_siswa_full);

// MATRIKS REF
$matriks_riasec = [
    ["no" => 1, "jurusan" => "Teknik Komputer Jaringan (TKJ)", "kode" => "IRC, ICR, RIC, IRC, CRI", "ket" => "Mengutak-atik komputer, jaringan, memecahkan masalah teknis, teliti, dan suka teknologi."],
    ["no" => 2, "jurusan" => "Teknik Sepeda Motor", "kode" => "RCI, RIC, RCI, CRI, IRC", "ket" => "Membongkar pasang motor, servis, kerja di bengkel, praktis, dan suka kendaraan roda dua."],
    ["no" => 3, "jurusan" => "Teknik Pengelasan", "kode" => "RCI, RIC, RC, RE, CRI", "ket" => "Kuat fisik, suka las, fabrikasi logam, tahan panas & kotor, dan teliti dalam hasil las."],
    ["no" => 4, "jurusan" => "Teknik Bodi Kendaraan Ringan", "kode" => "RAC, RAI, RCI, ARC, RIC", "ket" => "Memperbaiki body mobil, mengecat, desain tampilan, kerja tangan + ada unsur seni."],
    ["no" => 5, "jurusan" => "Teknik Instalasi Tenaga Listrik (TITL)", "kode" => "RIC, IRC, RCI, ICR, CRI", "ket" => "Menginstalasi listrik gedung, panel, rangkaian, safety, dan troubleshooting masalah listrik."],
    ["no" => 6, "jurusan" => "Teknik Tata & Pendingin Udara", "kode" => "RIC, IRC, RCI, ICR, CRI", "ket" => "Memasang AC, sistem pendingin, kombinasi mekanik + listrik, dan suka troubleshooting."],
    ["no" => 7, "jurusan" => "Desain Produksi Busana", "kode" => "ARC, ARI, ARE, RAC, AIC", "ket" => "Kreatif, suka mendesain baju, membuat pola, menjahit, dan tertarik dunia fashion."],
    ["no" => 8, "jurusan" => "Teknik Kimia Industri", "kode" => "ICR, IRC, ICR, CIR, RIC", "ket" => "Menyukai kimia, praktik di laboratorium, perhitungan, quality control, dan mengikuti prosedur."],
    ["no" => 9, "jurusan" => "Desain Teknik Furnitur", "kode" => "ARC, ARI, ACR, RAC, AIC", "ket" => "Kreatif, mendesain mebel, gambar teknik 3D, woodworking, dan suka estetika interior."],
    ["no" => 10, "jurusan" => "Teknik Pemesinan", "kode" => "RIC, RCI, IRC, ICR, CRI", "ket" => "Mengoperasikan mesin bubut, milling, CNC, presisi tinggi, dan manufaktur."]
];

// CHART PREP
$labels_chart = [];
$data_chart = [];
$query_distribusi = $conn->query("SELECT rekomendasi_jurusan, COUNT(*) as jumlah FROM hasil_ujian WHERE rekomendasi_jurusan IS NOT NULL AND rekomendasi_jurusan != '' GROUP BY rekomendasi_jurusan ORDER BY jumlah DESC");
while ($res_dist = $query_distribusi->fetch_assoc()) {
    $labels_chart[] = $res_dist['rekomendasi_jurusan'];
    $data_chart[] = intval($res_dist['jumlah']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator PPDB - SMK Jaya Buana</title>
    <link rel="icon" type="image/png" href="../assets/jb.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2563EB;
            --sidebar: #0F172A;
            --page: #F8FAFC;
            --success: #22C55E;
            --warning: #F59E0B;
            --danger: #EF4444;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: var(--page);
            color: #334155;
        }

        .animate-fade-in {
            animation: fadeIn .28s ease-out both;
        }

        .section-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .055);
        }

        .metric-card {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            box-shadow: 0 18px 38px rgba(15, 23, 42, .10);
            transition: transform .22s ease, box-shadow .22s ease;
        }

        .metric-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 24px 55px rgba(15, 23, 42, .16);
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: .75rem;
            border-radius: 14px;
            padding: .75rem .9rem;
            color: #cbd5e1;
            font-size: .82rem;
            font-weight: 700;
            transition: all .18s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(37, 99, 235, .16);
            color: #fff;
        }

        .nav-link i {
            width: 18px;
            text-align: center;
        }

        .form-input {
            width: 100%;
            border-radius: 14px;
            border: 1px solid #dbe3ef;
            background: #f8fafc;
            padding: .78rem .9rem;
            font-size: .84rem;
            outline: none;
            transition: all .18s ease;
        }

        .form-input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
        }

        .btn-primary,
        .btn-soft,
        .btn-danger,
        .btn-success {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            border-radius: 14px;
            font-weight: 800;
            transition: all .18s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            box-shadow: 0 14px 26px rgba(37, 99, 235, .24);
        }

        .btn-soft {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-soft:hover {
            background: #e2e8f0;
        }

        .btn-success {
            background: #16a34a;
            color: #fff;
        }

        .btn-success:hover {
            background: #15803d;
        }

        .btn-danger {
            background: #ef4444;
            color: #fff;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .table-row-item {
            transition: background-color .18s ease, transform .18s ease, box-shadow .18s ease;
        }

        .table-row-item:hover {
            background: #eff6ff;
            box-shadow: inset 3px 0 0 var(--primary);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1023px) {
            #sidebar {
                transform: translateX(-100%);
            }

            #sidebar.open {
                transform: translateX(0);
            }
        }

        @media print {
            .no-print,
            nav,
            aside,
            form,
            button,
            a,
            th:last-child,
            td:last-child,
            #modalEdit,
            .chart-container-wrapper,
            .pagination-container,
            .dashboard-hero,
            .section-card:not(.printable-table) {
                display: none !important;
            }

            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                font-size: 11px !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            main {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .bg-white {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            tr {
                display: table-row !important;
                page-break-inside: avoid !important;
            }

            th,
            td {
                border: 1px solid #000 !important;
                padding: 6px !important;
            }
        }
    </style>
</head>
<body class="min-h-screen font-sans antialiased">
    <div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-slate-950/50 backdrop-blur-sm no-print lg:hidden"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-[#0F172A] text-white transition-transform duration-300 no-print lg:translate-x-0">
        <div class="flex h-full flex-col">
            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-lg">
                    <img src="../assets/jb.png" alt="Logo SMK Jaya Buana" class="h-9 w-9 object-contain">
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-black leading-tight">PPDB Admin</p>
                    <p class="truncate text-xs font-medium text-slate-400">SMK Jaya Buana</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 px-4 py-5">
                <a href="#dashboard" class="nav-link active"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
                <a href="#data-siswa" class="nav-link"><i class="fa-solid fa-users"></i> Data Siswa</a>
                <a href="#registrasi" class="nav-link"><i class="fa-solid fa-user-plus"></i> Registrasi</a>
                <a href="#hasil-tes" class="nav-link"><i class="fa-solid fa-clipboard-check"></i> Hasil Tes</a>
                <a href="#statistik" class="nav-link"><i class="fa-solid fa-chart-pie"></i> Statistik</a>
                <a href="#matriks" class="nav-link"><i class="fa-solid fa-table-cells-large"></i> Matriks RIASEC</a>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="mb-3 rounded-2xl bg-white/5 p-4">
                    <p class="text-[11px] font-bold uppercase tracking-[.18em] text-slate-400">Login sebagai</p>
                    <div class="mt-2 inline-flex items-center gap-2 rounded-full bg-blue-500/15 px-3 py-1 text-xs font-black text-blue-200">
                        <i class="fa-solid fa-shield-halved"></i> Administrator
                    </div>
                </div>
                <a href="../auth/logout.php" onclick="return confirm('Keluar dari dashboard?')" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-red-500 px-4 py-3 text-sm font-black text-white transition hover:bg-red-600">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </aside>

    <div class="min-h-screen lg:pl-72">
        <nav class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur-xl no-print">
            <div class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" id="sidebarToggle" class="flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 lg:hidden">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-black text-slate-900 sm:text-xl">Dashboard Administrator</h1>
                        <p class="hidden text-sm font-medium text-slate-500 sm:block">Sistem Diagnostik Minat & Bakat PPDB</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right md:block">
                        <div id="clock" class="text-sm font-black text-slate-800"></div>
                        <div id="today" class="text-xs font-semibold text-slate-400"></div>
                    </div>
                    <span class="hidden rounded-full bg-blue-50 px-3 py-1.5 text-xs font-black text-blue-700 ring-1 ring-blue-100 sm:inline-flex">Administrator</span>
                    <a href="../auth/logout.php" onclick="return confirm('Keluar dari dashboard?')" class="btn-danger px-4 py-2.5 text-xs shadow-sm">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span class="hidden sm:inline">Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <main class="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
            <section id="dashboard" class="space-y-6">
                <div class="dashboard-hero overflow-hidden rounded-[28px] bg-gradient-to-br from-blue-700 via-blue-600 to-cyan-500 p-6 text-white shadow-xl sm:p-8">
                    <div class="grid gap-8 lg:grid-cols-[1.6fr_.9fr] lg:items-center">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-black uppercase tracking-[.18em] text-blue-50">
                                <i class="fa-solid fa-chart-line"></i> Administrator Dashboard
                            </div>
                            <h2 class="mt-5 text-3xl font-black leading-tight sm:text-4xl">Ringkasan Diagnostik PPDB</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-7 text-blue-50 sm:text-base">Pantau progres tes, distribusi hasil, dan aktivitas administrasi tanpa perlu membuka seluruh data sekaligus.</p>
                            <div class="mt-7">
                                <div class="mb-2 flex items-center justify-between text-sm font-bold">
                                    <span>Progress Pengisian Tes</span>
                                    <span><?= $persentase ?>%</span>
                                </div>
                                <div class="h-3 overflow-hidden rounded-full bg-white/25">
                                    <div class="h-full rounded-full bg-emerald-400 transition-all duration-700" style="width:<?= $persentase ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-3xl bg-white/12 p-5 ring-1 ring-white/20">
                            <div class="flex items-center gap-4">
                                <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-white text-blue-700 shadow-lg">
                                    <i class="fa-solid fa-graduation-cap text-3xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-blue-50">Total peserta</p>
                                    <p class="text-4xl font-black"><?= number_format($count_total) ?></p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                                <a href="admin.php?filter=sudah#data-siswa" class="rounded-2xl bg-white/12 p-4 transition hover:bg-white/20">
                                    <p class="font-black"><?= number_format($count_sudah) ?></p>
                                    <p class="text-blue-50">Sudah tes</p>
                                </a>
                                <a href="admin.php?filter=belum#data-siswa" class="rounded-2xl bg-white/12 p-4 transition hover:bg-white/20">
                                    <p class="font-black"><?= number_format($count_belum) ?></p>
                                    <p class="text-blue-50">Belum tes</p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?= $notif; ?>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <a href="admin.php?filter=#data-siswa" class="metric-card bg-gradient-to-br from-blue-600 to-blue-800 p-5 text-white">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.16em] text-blue-100">Total Siswa</p>
                                <h3 class="mt-3 text-4xl font-black"><?= number_format($count_total) ?></h3>
                            </div>
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20"><i class="fa-solid fa-users text-2xl"></i></div>
                        </div>
                        <p class="mt-5 text-sm font-medium text-blue-50">Semua akun siswa terdaftar.</p>
                    </a>
                    <a href="admin.php?filter=sudah#data-siswa" class="metric-card bg-gradient-to-br from-emerald-500 to-green-700 p-5 text-white">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.16em] text-emerald-50">Sudah Tes</p>
                                <h3 class="mt-3 text-4xl font-black"><?= number_format($count_sudah) ?></h3>
                            </div>
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20"><i class="fa-solid fa-circle-check text-2xl"></i></div>
                        </div>
                        <p class="mt-5 text-sm font-medium text-emerald-50"><?= $persentase ?>% peserta selesai.</p>
                    </a>
                    <a href="admin.php?filter=belum#data-siswa" class="metric-card bg-gradient-to-br from-amber-400 to-orange-500 p-5 text-white">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.16em] text-amber-50">Belum Tes</p>
                                <h3 class="mt-3 text-4xl font-black"><?= number_format($count_belum) ?></h3>
                            </div>
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20"><i class="fa-solid fa-hourglass-half text-2xl"></i></div>
                        </div>
                        <p class="mt-5 text-sm font-medium text-amber-50">Menunggu pengerjaan tes.</p>
                    </a>
                    <div class="metric-card bg-gradient-to-br from-slate-800 to-slate-950 p-5 text-white">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.16em] text-slate-300">Progress</p>
                                <h3 class="mt-3 text-4xl font-black"><?= $persentase ?>%</h3>
                            </div>
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15"><i class="fa-solid fa-chart-line text-2xl"></i></div>
                        </div>
                        <div class="mt-5 h-2 rounded-full bg-white/15"><div class="h-2 rounded-full bg-blue-400" style="width:<?= $persentase ?>%"></div></div>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <div class="section-card p-5 xl:col-span-2">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-black text-slate-900">Aktivitas Terbaru</h3>
                                <p class="text-sm font-medium text-slate-500">Akses cepat untuk pekerjaan rutin administrator.</p>
                            </div>
                            <a href="#data-siswa" class="btn-soft px-3 py-2 text-xs">Lihat Data</a>
                        </div>
                        <div class="grid gap-3 md:grid-cols-3">
                            <a href="#registrasi" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-blue-200 hover:bg-blue-50">
                                <i class="fa-solid fa-user-plus text-lg text-blue-600"></i>
                                <p class="mt-3 font-black text-slate-800">Tambah siswa</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Registrasi akun peserta baru.</p>
                            </a>
                            <a href="export_excel.php" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-200 hover:bg-emerald-50">
                                <i class="fa-solid fa-file-excel text-lg text-emerald-600"></i>
                                <p class="mt-3 font-black text-slate-800">Export Excel</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Unduh data administrasi.</p>
                            </a>
                            <button onclick="window.print()" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left transition hover:border-slate-300 hover:bg-white">
                                <i class="fa-solid fa-print text-lg text-slate-700"></i>
                                <p class="mt-3 font-black text-slate-800">Cetak rekap</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Print laporan admin.</p>
                            </button>
                        </div>
                    </div>
                    <div class="section-card p-5">
                        <h3 class="text-base font-black text-slate-900">Status Sistem</h3>
                        <div class="mt-5 space-y-4">
                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4">
                                <span class="text-sm font-bold text-slate-600">Aplikasi</span>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">Aktif</span>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4">
                                <span class="text-sm font-bold text-slate-600">Versi</span>
                                <span class="text-sm font-black text-slate-800">2026</span>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4">
                                <span class="text-sm font-bold text-slate-600">Developer</span>
                                <span class="text-sm font-black text-slate-800">PPDB Web System</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="registrasi" class="section-card p-5 sm:p-6 no-print">
                <div class="mb-6 flex flex-col gap-3 border-b border-slate-200 pb-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Registrasi Siswa</h2>
                        <p class="mt-1 text-sm font-medium text-slate-500">Tambah akun peserta dan gunakan NIS sebagai kredensial awal bila diperlukan.</p>
                    </div>
                    <div class="rounded-2xl bg-blue-50 px-4 py-3 text-xs font-bold leading-5 text-blue-700">
                        <i class="fa-solid fa-circle-info mr-1"></i> Reset password tersedia melalui tombol edit pada tabel siswa.
                    </div>
                </div>

                <form action="" method="POST" class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
                    <input type="hidden" name="tambah_siswa" value="1">
                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-[.14em] text-slate-500">Username / NIS</label>
                        <input type="text" name="username_siswa" required placeholder="Contoh: 2026001" class="form-input">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-[.14em] text-slate-500">Nama Lengkap</label>
                        <input type="text" name="nama_siswa" required placeholder="Masukkan nama lengkap siswa" class="form-input">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-black uppercase tracking-[.14em] text-slate-500">Kata Sandi Akun</label>
                        <input type="password" name="password_siswa" required placeholder="Buat sandi akses masuk" class="form-input">
                    </div>
                    <button type="submit" class="btn-primary px-5 py-3.5 text-sm">
                        <i class="fa-solid fa-user-plus"></i> Daftarkan
                    </button>
                </form>
            </section>

            <section id="data-siswa" class="section-card printable-table overflow-hidden">
                <div class="border-b border-slate-200 p-5 sm:p-6">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <h2 class="text-xl font-black text-slate-900">Data Siswa</h2>
                            <p class="mt-1 text-sm font-medium text-slate-500">Search, filter, export, aksi akun, hasil tes, dan pagination dalam satu area kerja.</p>
                            <p class="mt-2 text-xs font-bold text-slate-400">Baris aktif: <strong id="filterCounter" class="text-slate-800"><?= $siswa_result->num_rows; ?></strong></p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center no-print">
                            <div class="relative min-w-0 sm:w-72">
                                <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" id="liveSearchInput" placeholder="Cari nama atau NIS..." class="form-input pl-11">
                            </div>
                            <div class="flex gap-2">
                                <a href="admin.php?filter=#data-siswa" class="btn-soft px-4 py-3 text-xs <?= $filter_status === '' ? 'ring-2 ring-blue-200 text-blue-700' : '' ?>">Semua</a>
                                <a href="admin.php?filter=sudah#data-siswa" class="btn-soft px-4 py-3 text-xs <?= $filter_status === 'sudah' ? 'ring-2 ring-emerald-200 text-emerald-700' : '' ?>">Sudah</a>
                                <a href="admin.php?filter=belum#data-siswa" class="btn-soft px-4 py-3 text-xs <?= $filter_status === 'belum' ? 'ring-2 ring-amber-200 text-amber-700' : '' ?>">Belum</a>
                            </div>
                            <a href="export_excel.php" class="btn-success px-4 py-3 text-xs"><i class="fa-solid fa-file-excel"></i> Export</a>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left" id="siswaDataTable">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] uppercase tracking-[.12em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4 font-black">Identitas Calon Siswa</th>
                                <th class="px-5 py-4 text-center font-black">Status</th>
                                <th class="px-5 py-4 text-center font-black">Kode</th>
                                <th class="px-5 py-4 font-black">Prioritas Rekomendasi Jurusan</th>
                                <th class="px-5 py-4 text-center font-black no-print">Tindakan Admin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if($siswa_result && $siswa_result->num_rows > 0): ?>
                                <?php while($row = $siswa_result->fetch_assoc()): 
                                    $o2 = "-"; $o3 = "-";
                                    if(!empty($row['keterangan_jurusan'])) {
                                        $ex = explode(' | Opsi 3: ', $row['keterangan_jurusan']);
                                        $o2 = str_replace('Opsi 2: ', '', $ex[0] ?? '-');
                                        $o3 = $ex[1] ?? '-';
                                    }
                                ?>
                                    <tr class="table-row-item odd:bg-white even:bg-slate-50/55" 
                                        data-nama="<?= strtolower(htmlspecialchars($row['nama_lengkap'])); ?>" 
                                        data-nis="<?= strtolower(htmlspecialchars($row['username'])); ?>">
                                        <td class="px-5 py-4">
                                            <span class="block font-black text-slate-900"><?= htmlspecialchars($row['nama_lengkap']); ?></span>
                                            <div class="mt-1 flex items-center gap-2">
                                                <span class="font-mono text-xs font-bold text-slate-500"><i class="fa-solid fa-id-card mr-1 text-slate-400"></i><?= $row['username']; ?></span>
                                                <button onclick="salinAkun('<?= $row['username']; ?>')" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-blue-50 hover:text-blue-600 no-print" title="Salin Akun untuk Share WA">
                                                    <i class="fa-regular fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-black <?= !empty($row['rekomendasi_jurusan']) ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-100' ?>">
                                                <i class="fa-solid <?= !empty($row['rekomendasi_jurusan']) ? 'fa-circle-check' : 'fa-clock' ?>"></i>
                                                <?= !empty($row['rekomendasi_jurusan']) ? 'Sudah Tes' : 'Belum Tes'; ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-center font-mono">
                                            <?= !empty($row['kombinasi_kode']) ? "<span class='inline-flex rounded-xl bg-orange-50 px-3 py-1.5 text-xs font-black text-orange-600 ring-1 ring-orange-100'>".$row['kombinasi_kode']."</span>" : "<span class='text-slate-300 italic'>-</span>"; ?>
                                        </td>
                                        <td class="px-5 py-4">
                                            <?php if(!empty($row['rekomendasi_jurusan'])): ?>
                                                <div class="font-black uppercase text-slate-900"><i class="fa-solid fa-circle-check mr-1.5 text-xs text-emerald-500"></i>P1: <span class="text-blue-700"><?= $row['rekomendasi_jurusan']; ?></span></div>
                                                <div class="mt-1 text-xs font-bold uppercase leading-5 text-slate-500">P2: <span class="text-slate-700"><?= $o2; ?></span> &bull; P3: <span class="text-slate-700"><?= $o3; ?></span></div>
                                            <?php else: ?>
                                                <span class="text-sm italic text-slate-400">Menunggu pengerjaan tes...</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-4 text-center no-print">
                                            <div class="flex justify-center gap-2">
                                                <?php if(!empty($row['rekomendasi_jurusan'])): ?>
                                                    <a href="cetak_siswa.php?id=<?= $row['id']; ?>" target="_blank" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-blue-600 shadow-sm transition hover:bg-blue-50" title="Print Lembar Hasil"><i class="fa-solid fa-print"></i></a>
                                                    <a href="admin.php?aksi=reset&id=<?= $row['id']; ?>&filter=<?= $filter_status; ?>" onclick="return confirm('Apakah Anda yakin ingin me-reset hasil tes ini?')" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50" title="Reset Ujian"><i class="fa-solid fa-rotate-left"></i></a>
                                                <?php endif; ?>
                                                <button onclick="bukaModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['username']); ?>', '<?= htmlspecialchars($row['nama_lengkap']); ?>')" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-amber-600 shadow-sm transition hover:bg-amber-50" title="Edit Akun"><i class="fa-solid fa-user-pen"></i></button>
                                                <a href="admin.php?aksi=hapus&id=<?= $row['id']; ?>&filter=<?= $filter_status; ?>" onclick="return confirm('Hapus permanen akun pendaftar?')" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-rose-600 shadow-sm transition hover:bg-rose-50" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr id="noDataRow"><td colspan="5" class="p-10 text-center font-medium italic text-slate-400">Data pendaftar kosong.</td></tr>
                            <?php endif; ?>
                            <tr id="liveSearchEmptyRow" class="hidden"><td colspan="5" class="p-10 text-center font-medium italic text-slate-400">Data siswa yang Anda cari tidak ditemukan.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div id="jsPaginationContainer" class="pagination-container flex flex-col gap-3 border-t border-slate-200 px-5 py-4 text-xs font-bold text-slate-500 sm:flex-row sm:items-center sm:justify-between no-print">
                    <span id="jsPaginationInfo">Menampilkan halaman 1</span>
                    <div class="flex flex-wrap gap-2" id="jsPaginationButtons"></div>
                </div>
            </section>

            <section id="hasil-tes" class="grid gap-6 xl:grid-cols-3">
                <div class="section-card p-5 sm:p-6">
                    <h2 class="text-xl font-black text-slate-900">Hasil Tes</h2>
                    <p class="mt-2 text-sm font-medium leading-6 text-slate-500">Gunakan filter cepat untuk melihat siswa yang sudah tes, mencetak hasil per siswa, atau melakukan reset hasil tes dari tabel data.</p>
                    <div class="mt-5 grid gap-3">
                        <a href="admin.php?filter=sudah#data-siswa" class="btn-primary px-4 py-3 text-sm"><i class="fa-solid fa-clipboard-check"></i> Daftar Sudah Tes</a>
                        <a href="admin.php?filter=belum#data-siswa" class="btn-soft px-4 py-3 text-sm"><i class="fa-solid fa-hourglass-half"></i> Daftar Belum Tes</a>
                        <button onclick="window.print()" class="btn-soft px-4 py-3 text-sm no-print"><i class="fa-solid fa-print"></i> Cetak Rekap Admin</button>
                    </div>
                </div>
                <div class="section-card p-5 sm:p-6 xl:col-span-2">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-100">
                            <p class="text-xs font-black uppercase tracking-[.16em] text-emerald-700">Sudah Tes</p>
                            <p class="mt-3 text-3xl font-black text-emerald-700"><?= number_format($count_sudah) ?></p>
                        </div>
                        <div class="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-100">
                            <p class="text-xs font-black uppercase tracking-[.16em] text-amber-700">Belum Tes</p>
                            <p class="mt-3 text-3xl font-black text-amber-700"><?= number_format($count_belum) ?></p>
                        </div>
                        <div class="rounded-2xl bg-blue-50 p-5 ring-1 ring-blue-100">
                            <p class="text-xs font-black uppercase tracking-[.16em] text-blue-700">Status Filter</p>
                            <p class="mt-3 text-2xl font-black text-blue-700"><?= $filter_status === 'sudah' ? 'Sudah' : ($filter_status === 'belum' ? 'Belum' : 'Semua') ?></p>
                        </div>
                    </div>
                    <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-medium leading-6 text-slate-600">
                        Filter status tersedia melalui tombol cepat, sedangkan pencarian instan pada tabel tetap menggunakan nama siswa atau NIS.
                    </div>
                </div>
            </section>

            <section id="statistik" class="grid gap-6 xl:grid-cols-12">
                <div class="section-card chart-container-wrapper p-5 sm:p-6 xl:col-span-5">
                    <div class="mb-5">
                        <h2 class="text-xl font-black text-slate-900">Donut Chart</h2>
                        <p class="mt-1 text-sm font-medium text-slate-500">Distribusi rekomendasi jurusan berdasarkan hasil tes.</p>
                    </div>
                    <div class="relative min-h-[320px]">
                        <?php if(!empty($data_chart)): ?>
                            <canvas id="riasecDonutChart"></canvas>
                        <?php else: ?>
                            <div class="flex min-h-[260px] items-center justify-center rounded-2xl bg-slate-50 text-sm font-medium italic text-slate-400">Belum ada data grafik dari kuesioner.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-card chart-container-wrapper p-5 sm:p-6 xl:col-span-7">
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-black text-slate-900">Bar Chart</h2>
                            <p class="mt-1 text-sm font-medium text-slate-500">Ranking jurusan dan statistik peserta.</p>
                        </div>
                        <button onclick="window.print()" class="btn-soft px-4 py-3 text-xs no-print"><i class="fa-solid fa-print"></i> Cetak</button>
                    </div>
                    <div class="relative min-h-[320px]">
                        <?php if(!empty($data_chart)): ?>
                            <canvas id="riasecBarChart"></canvas>
                        <?php else: ?>
                            <div class="flex min-h-[260px] items-center justify-center rounded-2xl bg-slate-50 text-sm font-medium italic text-slate-400">Belum ada data statistik.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-card p-5 sm:p-6 xl:col-span-12">
                    <h3 class="text-lg font-black text-slate-900">Ranking Jurusan</h3>
                    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <?php if(!empty($labels_chart)): ?>
                            <?php foreach($labels_chart as $idx => $label): ?>
                                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-800"><?= htmlspecialchars($label); ?></p>
                                        <p class="mt-1 text-xs font-bold text-slate-400">Peringkat <?= $idx + 1; ?></p>
                                    </div>
                                    <span class="rounded-full bg-blue-50 px-3 py-1 text-sm font-black text-blue-700"><?= number_format($data_chart[$idx]); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-sm font-medium italic text-slate-400">Belum ada ranking jurusan.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section id="matriks" class="section-card overflow-hidden">
                <div class="border-b border-slate-200 p-5 sm:p-6">
                    <h2 class="text-xl font-black text-slate-900">Matriks RIASEC</h2>
                    <p class="mt-1 text-sm font-medium text-slate-500">Referensi pemetaan kompetensi dan penjelasan jurusan untuk validasi hasil rekomendasi.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-[.12em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4 text-center font-black">No</th>
                                <th class="px-5 py-4 font-black">Program Keahlian / Jurusan</th>
                                <th class="px-5 py-4 text-center font-black">5 Kode RIASEC Cocok</th>
                                <th class="px-5 py-4 font-black">Keterangan Profil Kompetensi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach($matriks_riasec as $m): ?>
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-4 text-center font-black text-slate-400"><?= $m['no']; ?></td>
                                    <td class="px-5 py-4 font-black text-slate-900"><?= $m['jurusan']; ?></td>
                                    <td class="px-5 py-4 text-center font-mono"><span class="rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-600 ring-1 ring-slate-200"><?= $m['kode']; ?></span></td>
                                    <td class="px-5 py-4 leading-6 text-slate-600"><?= $m['ket']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white px-4 py-5 text-center text-xs font-bold text-slate-400">&copy; 2026 PPDB Web System &bull; SMK Jaya Buana</footer>
    </div>

    <div id="modalEdit" class="fixed inset-0 z-[70] flex hidden items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl animate-fade-in">
            <div class="mb-5 flex items-center justify-between border-b border-slate-200 pb-4">
                <div>
                    <h4 class="text-base font-black text-slate-900"><i class="fa-solid fa-user-gear mr-2 text-blue-600"></i>Sunting Akun Siswa</h4>
                    <p class="mt-1 text-xs font-medium text-slate-500">Perbarui nama atau reset password akun.</p>
                </div>
                <button onclick="tutupModal()" class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-slate-200" title="Tutup modal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="edit_siswa" value="1">
                <input type="hidden" name="id_siswa" id="modal_id">
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-[.14em] text-slate-500">Username / NIS</label>
                    <input type="text" id="modal_username" disabled class="form-input font-mono text-slate-500">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-[.14em] text-slate-500">Nama Lengkap Siswa</label>
                    <input type="text" name="nama_lengkap" id="modal_nama" required class="form-input">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-black uppercase tracking-[.14em] text-slate-500">Reset Password Baru</label>
                    <input type="password" name="password_baru" placeholder="Kosongkan jika sandi tidak diganti" class="form-input">
                </div>
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <button type="button" onclick="tutupModal()" class="btn-soft px-4 py-3 text-sm">Batal</button>
                    <button type="submit" class="btn-primary px-4 py-3 text-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function bukaModal(id, username, nama) {
        document.getElementById('modal_id').value = id;
        document.getElementById('modal_username').value = username;
        document.getElementById('modal_nama').value = nama;
        document.getElementById('modalEdit').classList.remove('hidden');
    }
    function tutupModal() { 
        document.getElementById('modalEdit').classList.add('hidden'); 
    }

    // NEW PREMIUM FUNCTION: QUICK COPY FOR WHATSAPP
    function salinAkun(username) {
        const textToCopy = `Akun Login Ujian PPDB SMK Jaya Buana:\nUsername: ${username}\nPassword: ${username}\n\nSilakan masuk melalui tautan website resmi PPDB.`;
        navigator.clipboard.writeText(textToCopy).then(() => {
            alert(`Berhasil menyalin kredensial siswa (${username})! Siap dipaste ke WhatsApp.`);
        }).catch(err => {
            console.error('Gagal menyalin text: ', err);
        });
    }

    // CONFIG INTEGRATED ENGINE PAGINATION
    const rowsPerPage = 10;
    let currentPage = 1;
    let filteredRows = [];

    const searchInput = document.getElementById('liveSearchInput');
    const allRows = Array.from(document.querySelectorAll('.table-row-item'));

    function initPagination() {
        const keyword = searchInput ? searchInput.value.toLowerCase().trim() : '';
        filteredRows = allRows.filter(row => {
            const nama = row.getAttribute('data-nama');
            const nis = row.getAttribute('data-nis');
            return nama.includes(keyword) || nis.includes(keyword);
        });

        currentPage = 1;
        renderTablePage();
    }

    function renderTablePage() {
        const totalRows = filteredRows.length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;

        allRows.forEach(row => {
            row.classList.add('hidden');
            row.classList.remove('animate-fade-in'); // Reset animasi kelas pemicu
        });

        filteredRows.forEach((row, index) => {
            if (index >= startIndex && index < endIndex) {
                row.classList.remove('hidden');
                row.classList.add('animate-fade-in'); // Tambahkan efek pudar mulus saat slide berganti
            }
        });

        const infoSpan = document.getElementById('jsPaginationInfo');
        if (infoSpan) {
            if (totalRows > 0) {
                infoSpan.innerText = `Menampilkan ${startIndex + 1}-${Math.min(endIndex, totalRows)} dari ${totalRows} baris data`;
            } else {
                infoSpan.innerText = "Tidak ada data yang ditampilkan";
            }
        }

        const emptyRow = document.getElementById('liveSearchEmptyRow');
        if (emptyRow) {
            if (totalRows === 0 && allRows.length > 0) {
                emptyRow.classList.remove('hidden');
            } else {
                emptyRow.classList.add('hidden');
            }
        }

        const counter = document.getElementById('filterCounter');
        if (counter) counter.innerText = totalRows;

        renderPaginationButtons(totalPages);
    }

    function renderPaginationButtons(totalPages) {
        const buttonContainer = document.getElementById('jsPaginationButtons');
        if (!buttonContainer) return;
        buttonContainer.innerHTML = '';

        if (totalPages <= 1) {
            document.getElementById('jsPaginationContainer').classList.add('hidden');
            return;
        }
        document.getElementById('jsPaginationContainer').classList.remove('hidden');

        if (currentPage > 1) {
            const prevBtn = document.createElement('button');
            prevBtn.className = "rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-600 transition hover:bg-slate-200";
            prevBtn.innerHTML = "&laquo; Prev";
            prevBtn.onclick = () => { currentPage--; renderTablePage(); };
            buttonContainer.appendChild(prevBtn);
        }

        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `rounded-xl px-3 py-2 text-xs font-black transition ${i === currentPage ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`;
            pageBtn.innerText = i;
            pageBtn.onclick = () => { currentPage = i; renderTablePage(); };
            buttonContainer.appendChild(pageBtn);
        }

        if (currentPage < totalPages) {
            const nextBtn = document.createElement('button');
            nextBtn.className = "rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-600 transition hover:bg-slate-200";
            nextBtn.innerHTML = "Next &raquo;";
            nextBtn.onclick = () => { currentPage++; renderTablePage(); };
            buttonContainer.appendChild(nextBtn);
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', initPagination);
    }

    document.addEventListener("DOMContentLoaded", initPagination);

    // ENGINE CHART.JS
    <?php if(!empty($data_chart)): ?>
    const chartColors = [
        '#2563EB', '#06B6D4', '#22C55E', '#F59E0B',
        '#EF4444', '#8B5CF6', '#EC4899', '#64748B', '#14B8A6', '#F97316'
    ];

    const ctx = document.getElementById('riasecDonutChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($labels_chart); ?>,
            datasets: [{
                data: <?= json_encode($data_chart); ?>,
                backgroundColor: chartColors,
                borderWidth: 3,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: { boxWidth: 10, font: { size: 11, weight: 'bold' }, color: '#64748b' }
                }
            },
            cutout: '68%',
            animation: { animateScale: true, animateRotate: true }
        }
    });

    const barCtx = document.getElementById('riasecBarChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_chart); ?>,
            datasets: [{
                label: 'Jumlah Peserta',
                data: <?= json_encode($data_chart); ?>,
                backgroundColor: '#2563EB',
                borderRadius: 10,
                maxBarThickness: 42
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { ticks: { color: '#64748b', font: { size: 10, weight: 'bold' } }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: '#e2e8f0' } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
    <?php endif; ?>

    function updateClock(){
        const now=new Date();
        document.getElementById("clock").innerHTML=
        now.toLocaleTimeString('id-ID');

        document.getElementById("today").innerHTML=
        now.toLocaleDateString('id-ID',{
        weekday:'long',
        day:'numeric',
        month:'long',
        year:'numeric'
        });
    }

    updateClock();
    setInterval(updateClock,1000);

    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const navLinks = document.querySelectorAll('.nav-link');

    function closeSidebar() {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.add('hidden');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('hidden');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navLinks.forEach(item => item.classList.remove('active'));
            link.classList.add('active');
            closeSidebar();
        });
    });
    </script>
</body>
</html>
