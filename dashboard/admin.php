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
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(4px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in {
        animation: fadeIn 0.3s ease-out forwards;
    }

    @media print {

        .no-print,
        nav,
        form,
        button,
        a,
        th:last-child,
        td:last-child,
        #modalEdit,
        .chart-container-wrapper,
        .pagination-container {
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
<body class="bg-[#f8fafc] min-h-screen flex flex-col font-sans text-xs text-slate-700 antialiased">

    <nav class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 shadow-sm">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">

        <div class="flex items-center gap-4">

            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-900 to-sky-600 flex items-center justify-center shadow-lg overflow-hidden">
                <img src="../assets/jb.png" class="w-10 h-10 object-contain">
            </div>

            <div>

                <h1 class="text-xl font-black text-slate-800 tracking-wide">
                    Dashboard Administrator
                </h1>

                <p class="text-sm text-slate-500">
                    Sistem Diagnostik Minat & Bakat PPDB SMK Jaya Buana
                </p>

            </div>

        </div>

        <div class="flex items-center gap-5">

            <div class="text-right">

                <p class="text-xs text-slate-400">
                    Login sebagai
                </p>

                <div class="flex items-center gap-2 justify-end">

                    <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">
                        Administrator
                    </span>

                </div>

            </div>

            <div class="text-right">

                <div id="clock" class="font-bold text-slate-700 text-sm"></div>

                <div id="today" class="text-xs text-slate-400"></div>

            </div>

            <a href="../auth/logout.php"
                onclick="return confirm('Keluar dari dashboard?')"
                class="bg-red-500 hover:bg-red-600 transition text-white px-4 py-2 rounded-xl font-semibold shadow">

                <i class="fa-solid fa-right-from-bracket mr-2"></i>

                Logout

            </a>

        </div>

    </div>
</nav>

    <main class="p-4 sm:p-6 max-w-7xl w-full mx-auto flex-grow space-y-5">
        
        <?= $notif; ?>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="admin.php?filter=" class="bg-white p-4 rounded-2xl shadow-[0_2px_8px_-3px_rgba(0,0,0,0.05)] border transition-all duration-300 flex items-center justify-between hover:translate-y-[-2px] hover:shadow-md cursor-pointer <?= empty($filter_status) ? 'border-blue-500 ring-4 ring-blue-50/50' : 'border-slate-100' ?>">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Pendaftar</span>
                    <span class="text-xl font-black text-slate-800 mt-1 block font-mono"><?= number_format($count_total); ?></span>
                </div>
                <div class="w-10 h-10 bg-blue-50/60 text-blue-600 rounded-xl flex items-center justify-center text-sm border border-blue-100/50"><i class="fa-solid fa-users"></i></div>
            </a>
            
            <a href="admin.php?filter=sudah" class="bg-white p-4 rounded-2xl shadow-[0_2px_8px_-3px_rgba(0,0,0,0.05)] border transition-all duration-300 flex items-center justify-between hover:translate-y-[-2px] hover:shadow-md cursor-pointer <?= $filter_status === 'sudah' ? 'border-emerald-500 ring-4 ring-emerald-50/50' : 'border-slate-100' ?>">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Sudah Selesai Tes</span>
                    <span class="text-xl font-black text-emerald-600 mt-1 block font-mono"><?= number_format($count_sudah); ?></span>
                </div>
                <div class="w-10 h-10 bg-emerald-50/60 text-emerald-600 rounded-xl flex items-center justify-center text-sm border border-emerald-100/50"><i class="fa-solid fa-circle-check"></i></div>
            </a>
            
            <a href="admin.php?filter=belum" class="bg-white p-4 rounded-2xl shadow-[0_2px_8px_-3px_rgba(0,0,0,0.05)] border transition-all duration-300 flex items-center justify-between hover:translate-y-[-2px] hover:shadow-md cursor-pointer <?= $filter_status === 'belum' ? 'border-amber-500 ring-4 ring-amber-50/50' : 'border-slate-100' ?>">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Menunggu Antrean</span>
                    <span class="text-xl font-black text-amber-500 mt-1 block font-mono"><?= number_format($count_belum); ?></span>
                </div>
                <div class="w-10 h-10 bg-amber-50/60 text-amber-500 rounded-xl flex items-center justify-center text-sm border border-amber-100/50"><i class="fa-solid fa-clock-rotate-left"></i></div>
            </a>
            
            <div class="bg-white p-4 rounded-2xl shadow-[0_2px_8px_-3px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between select-none">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Progres Pengisian</span>
                    <span class="text-xl font-black text-indigo-600 mt-1 block font-mono"><?= $persentase; ?>%</span>
                </div>
                <div class="w-10 h-10 bg-indigo-50/60 text-indigo-600 rounded-xl flex items-center justify-center text-sm border border-indigo-100/50"><i class="fa-solid fa-chart-pie"></i></div>
            </div>
        </div>

        <div id="modalEdit" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
            <div class="bg-white rounded-2xl shadow-xl p-5 max-w-sm w-full space-y-4 border border-slate-100 animate-fade-in">
                <div class="flex justify-between items-center border-b pb-2.5">
                    <h4 class="font-bold text-slate-800 text-xs uppercase flex items-center gap-1.5"><i class="fa-solid fa-user-gear text-blue-900"></i> Sunting Akun Calon Siswa</h4>
                    <button onclick="tutupModal()" class="text-slate-400 hover:text-slate-600 text-base font-bold">&times;</button>
                </div>
                <form action="" method="POST" class="space-y-3">
                    <input type="hidden" name="edit_siswa" value="1">
                    <input type="hidden" name="id_siswa" id="modal_id">
                    <div>
                        <label class="block font-bold text-slate-400 mb-1">USERNAME / NIS (Permanen):</label>
                        <input type="text" id="modal_username" disabled class="w-full p-2 border bg-slate-50 rounded-lg text-slate-500 font-mono focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">NAMA LENGKAP SISWA:</label>
                        <input type="text" name="nama_lengkap" id="modal_nama" required class="w-full p-2 border rounded-lg focus:ring-1 focus:ring-blue-900 focus:outline-none text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">RESET PASSWORD BARU (Opsional):</label>
                        <input type="password" name="password_baru" placeholder="Kosongkan jika sandi tidak diganti" class="w-full p-2 border rounded-lg focus:ring-1 focus:ring-blue-900 focus:outline-none text-[11px]">
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" onclick="tutupModal()" class="w-1/2 bg-slate-100 hover:bg-slate-200 text-slate-700 py-2 rounded-lg font-bold transition">Batal</button>
                        <button type="submit" class="w-1/2 bg-blue-900 hover:bg-blue-800 text-white py-2 rounded-lg font-bold transition">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 items-start">
            
            <div class="space-y-5">
                <div class="bg-white p-5 rounded-2xl shadow-[0_2px_8px_-3px_rgba(0,0,0,0.05)] border border-slate-100 space-y-4 no-print">
                    <h3 class="font-black text-slate-800 uppercase border-b pb-2.5 flex items-center gap-1.5 text-[11px]">
                        <i class="fa-solid fa-user-plus text-slate-900"></i> Registrasi Siswa Baru
                    </h3>
                    <form action="" method="POST" class="space-y-3.5">
                        <input type="hidden" name="tambah_siswa" value="1">
                        <div>
                            <label class="block font-bold text-slate-400 mb-1 uppercase tracking-wider text-[9px]">Username / NIS:</label>
                            <input type="text" name="username_siswa" required placeholder="Contoh: 2026001" class="w-full p-2.5 border border-slate-200 rounded-xl focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs bg-slate-50/50">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-400 mb-1 uppercase tracking-wider text-[9px]">Nama Lengkap:</label>
                            <input type="text" name="nama_siswa" required placeholder="Masukkan nama lengkap siswa" class="w-full p-2.5 border border-slate-200 rounded-xl focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs bg-slate-50/50">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-400 mb-1 uppercase tracking-wider text-[9px]">Kata Sandi Akun:</label>
                            <input type="password" name="password_siswa" required placeholder="Buat sandi akses masuk" class="w-full p-2.5 border border-slate-200 rounded-xl focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs bg-slate-50/50">
                        </div>
                        <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl uppercase tracking-wider shadow-sm transition duration-150 text-[10px]">
                            Daftarkan Akun Siswa
                        </button>
                    </form>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-[0_2px_8px_-3px_rgba(0,0,0,0.05)] border border-slate-100 space-y-4 chart-container-wrapper">
                    <h3 class="font-black text-slate-800 uppercase border-b pb-2.5 flex items-center gap-1.5 text-[11px]">
                        <i class="fa-solid fa-chart-pie text-indigo-600 animate-pulse"></i> Visual Analisis Minat
                    </h3>
                    <div class="relative w-full aspect-square flex items-center justify-center py-2">
                        <?php if(!empty($data_chart)): ?>
                            <canvas id="riasecDonutChart"></canvas>
                        <?php else: ?>
                            <p class="text-slate-400 italic text-[10px] text-center py-6">Belum ada data grafik dari kuesioner.</p>
                        <?php endif; ?>
                    </div>
                    <button onclick="window.print()" class="w-full mt-2 bg-slate-800 hover:bg-slate-700 text-white font-bold py-2.5 rounded-xl uppercase tracking-wider text-[9px] transition flex items-center justify-center gap-1.5 shadow-sm no-print">
                        <i class="fa-solid fa-print text-xs"></i> Cetak Rekap Lap. Admin
                    </button>
                </div>
            </div>

            <div class="lg:col-span-3 bg-white p-5 rounded-2xl shadow-[0_2px_8px_-3px_rgba(0,0,0,0.05)] border border-slate-100 space-y-4">
                <div class="sm:flex justify-between items-center border-b border-slate-100 pb-3.5 gap-4 space-y-3 sm:space-y-0">
                    <div>
                        <h3 class="font-black text-slate-800 uppercase flex items-center gap-1.5 text-[11px]">
                            <i class="fa-solid fa-folder-open text-orange-500"></i> Data Hasil Analisis Siswa Pendaftar
                        </h3>
                        <p class="text-[10px] text-slate-400 mt-0.5">Sistem Pencarian Instan: <strong id="filterCounter" class="text-slate-700 font-bold"><?= $siswa_result->num_rows; ?></strong> baris aktif</p>
                    </div>
                    
                    <div class="flex items-center gap-2 w-full sm:w-auto justify-end no-print">
                        <div class="relative w-52">
                            <input type="text" id="liveSearchInput" placeholder="Cari Nama / NIS instan..." class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-900 text-xs shadow-inner bg-slate-50/30">
                            <i class="fa-solid fa-search text-slate-400 absolute left-3 top-2.5 text-[10px]"></i>
                        </div>
                        <a href="export_excel.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl font-bold transition flex items-center gap-1 shadow-sm text-[10px]"><i class="fa-solid fa-file-excel text-xs"></i> <span>Export</span></a>
                    </div>
                </div>
                
                <div class="overflow-x-auto bg-white">
                    <table class="w-full text-left border-separate border-spacing-y-2" id="siswaDataTable">
                        <thead>
                            <tr class="text-slate-400 font-bold text-[9px] uppercase tracking-wider">
                                <th class="pb-2 pl-4">Identitas Calon Siswa</th>
                                <th class="pb-2 text-center">Status</th>
                                <th class="pb-2 text-center">Kode</th>
                                <th class="pb-2">Prioritas Rekomendasi Jurusan Sesuai Matriks</th>
                                <th class="pb-2 text-center no-print pr-4">Tindakan Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($siswa_result && $siswa_result->num_rows > 0): ?>
                                <?php while($row = $siswa_result->fetch_assoc()): 
                                    $o2 = "-"; $o3 = "-";
                                    if(!empty($row['keterangan_jurusan'])) {
                                        $ex = explode(' | Opsi 3: ', $row['keterangan_jurusan']);
                                        $o2 = str_replace('Opsi 2: ', '', $ex[0] ?? '-');
                                        $o3 = $ex[1] ?? '-';
                                    }
                                ?>
                                    <tr class="table-row-item bg-[#f8fafc]/40 hover:bg-slate-50 transition-all duration-200" 
                                        data-nama="<?= strtolower(htmlspecialchars($row['nama_lengkap'])); ?>" 
                                        data-nis="<?= strtolower(htmlspecialchars($row['username'])); ?>">
                                        
                                        <td class="p-3 pl-4 rounded-l-xl border-y border-l border-slate-100">
                                            <span class="font-bold text-slate-800 block text-xs tracking-wide"><?= htmlspecialchars($row['nama_lengkap']); ?></span>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="text-slate-400 font-mono text-[9px]"><i class="fa-solid fa-id-card text-[8px] mr-0.5"></i> <?= $row['username']; ?></span>
                                                <button onclick="salinAkun('<?= $row['username']; ?>')" class="text-slate-400 hover:text-blue-600 transition p-0.5" title="Salin Akun untuk Share WA">
                                                    <i class="fa-regular fa-copy text-[9px]"></i>
                                                </button>
                                            </div>
                                        </td>
                                        
                                        <td class="p-3 text-center border-y border-slate-100 whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 font-bold rounded-full text-[9px] <?= !empty($row['rekomendasi_jurusan']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-700 border border-amber-100' ?>">
                                                <?= !empty($row['rekomendasi_jurusan']) ? 'Sudah Tes' : 'Belum Tes'; ?>
                                            </span>
                                        </td>
                                        
                                        <td class="p-3 text-center font-mono border-y border-slate-100">
                                            <?= !empty($row['kombinasi_kode']) ? "<span class='px-1.5 py-0.5 bg-orange-50 text-orange-600 font-black rounded-lg border border-orange-100 text-[10px]'>".$row['kombinasi_kode']."</span>" : "<span class='text-slate-300 italic'>-</span>"; ?>
                                        </td>
                                        
                                        <td class="p-3 space-y-0.5 border-y border-slate-100">
                                            <?php if(!empty($row['rekomendasi_jurusan'])): ?>
                                                <div class="font-bold text-slate-800 text-xs uppercase"><i class="fa-solid fa-circle-check text-emerald-500 mr-1 text-[8px]"></i>P1: <span class="text-blue-900"><?= $row['rekomendasi_jurusan']; ?></span></div>
                                                <div class="text-slate-400 text-[9px] font-medium uppercase tracking-tight pl-3.5">P2: <span class="text-slate-600 font-bold"><?= $o2; ?></span> &bull; P3: <span class="text-slate-600 font-bold"><?= $o3; ?></span></div>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic text-[10px] pl-1">Menunggu pengerjaan tes...</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="p-3 text-center no-print rounded-r-xl border-y border-r border-slate-100 pr-4">
                                            <div class="flex justify-center gap-1">
                                                <?php if(!empty($row['rekomendasi_jurusan'])): ?>
                                                    <a href="cetak_siswa.php?id=<?= $row['id']; ?>" target="_blank" class="bg-white hover:bg-blue-50 border border-slate-200 text-blue-600 p-1.5 rounded-xl transition shadow-sm" title="Print Lembar Hasil"><i class="fa-solid fa-print text-[9px]"></i></a>
                                                    <a href="admin.php?aksi=reset&id=<?= $row['id']; ?>&filter=<?= $filter_status; ?>" onclick="return confirm('Apakah Anda yakin ingin me-reset hasil tes ini?')" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-500 p-1.5 rounded-xl transition" title="Reset Ujian"><i class="fa-solid fa-rotate-left text-[9px]"></i></a>
                                                <?php endif; ?>
                                                <button onclick="bukaModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['username']); ?>', '<?= htmlspecialchars($row['nama_lengkap']); ?>')" class="bg-white hover:bg-amber-50 border border-slate-200 text-amber-600 p-1.5 rounded-xl transition shadow-sm" title="Edit Akun"><i class="fa-solid fa-user-pen text-[9px]"></i></button>
                                                <a href="admin.php?aksi=hapus&id=<?= $row['id']; ?>&filter=<?= $filter_status; ?>" onclick="return confirm('Hapus permanen akun pendaftar?')" class="bg-white hover:bg-rose-50 border border-slate-200 text-rose-600 p-1.5 rounded-xl transition shadow-sm" title="Hapus"><i class="fa-solid fa-trash text-[9px]"></i></a>
                                            </div>
                                        </td>
                                        
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr id="noDataRow"><td colspan="5" class="p-6 text-center text-slate-400 italic">Data pendaftar kosong.</td></tr>
                            <?php endif; ?>
                            <tr id="liveSearchEmptyRow" class="hidden"><td colspan="5" class="p-6 text-center text-slate-400 italic bg-slate-50/50 rounded-xl">Data siswa yang Anda cari tidak ditemukan.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div id="jsPaginationContainer" class="flex justify-between items-center pt-3 text-[10px] no-print pagination-container">
                    <span id="jsPaginationInfo" class="text-slate-400 font-medium">Menampilkan halaman 1</span>
                    <div class="flex gap-1" id="jsPaginationButtons"></div>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-[0_2px_8px_-3px_rgba(0,0,0,0.05)] border border-slate-100 space-y-4">
            <div>
                <h3 class="font-black text-slate-800 uppercase flex items-center gap-1.5 text-[11px]">
                    <i class="fa-solid fa-layer-group text-slate-900"></i> Matriks Referensi Pemetaan Kompetensi RIASEC
                </h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Gunakan daftar tabel acuan di bawah ini untuk validasi kesesuaian minat bakat siswa dengan program keahlian</p>
            </div>
            <div class="overflow-x-auto border border-slate-100 rounded-xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 font-bold border-b border-slate-100 text-[9px] uppercase tracking-wider">
                            <th class="p-2.5 w-12 text-center">No</th>
                            <th class="p-2.5 w-56">Program Keahlian / Jurusan</th>
                            <th class="p-2.5 w-52 text-center">5 Kode RIASEC Cocok</th>
                            <th class="p-2.5">Keterangan Profil Kompetensi (Untuk Calon Siswa)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/80 text-[11px]">
                        <?php foreach($matriks_riasec as $m): ?>
                            <tr class="hover:bg-slate-50/40 transition">
                                <td class="p-2.5 text-center font-bold text-slate-400"><?= $m['no']; ?></td>
                                <td class="p-2.5 font-bold text-slate-800"><?= $m['jurusan']; ?></td>
                                <td class="p-2.5 text-center font-mono"><span class="px-2 py-0.5 bg-slate-100 text-slate-600 font-bold rounded border border-slate-200 text-[10px]"><?= $m['kode']; ?></span></td>
                                <td class="p-2.5 text-slate-500 leading-relaxed"><?= $m['ket']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="bg-white border-t py-4 text-center text-[10px] text-slate-400 font-medium">&copy; 2026 PPDB Web System &bull; SMK Jaya Buana</footer>

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
            prevBtn.className = "px-2.5 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg font-bold text-slate-600 transition text-[9px]";
            prevBtn.innerHTML = "&laquo; Prev";
            prevBtn.onclick = () => { currentPage--; renderTablePage(); };
            buttonContainer.appendChild(prevBtn);
        }

        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `px-2.5 py-1 rounded-lg font-bold transition text-[9px] ${i === currentPage ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`;
            pageBtn.innerText = i;
            pageBtn.onclick = () => { currentPage = i; renderTablePage(); };
            buttonContainer.appendChild(pageBtn);
        }

        if (currentPage < totalPages) {
            const nextBtn = document.createElement('button');
            nextBtn.className = "px-2.5 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg font-bold text-slate-600 transition text-[9px]";
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
    const ctx = document.getElementById('riasecDonutChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($labels_chart); ?>,
            datasets: [{
                data: <?= json_encode($data_chart); ?>,
                backgroundColor: [
                    '#4f46e5', '#06b6d4', '#10b981', '#f59e0b', 
                    '#ec4899', '#8b5cf6', '#ef4444', '#64748b'
                ],
                borderWidth: 2,
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
                    labels: { boxWidth: 8, font: { size: 9, weight: 'bold' }, color: '#64748b' }
                }
            },
            cutout: '70%',
            animation: { animateScale: true, animateRotate: true }
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>