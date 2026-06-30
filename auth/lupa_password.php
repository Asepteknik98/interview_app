<?php
// auth/lupa_password.php
session_start();
require_once '../config/database.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $nama_lengkap = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));

    if (!empty($username) && !empty($nama_lengkap)) {
        // Cek apakah NIS dan Nama Lengkap cocok di database (khusus role siswa)
        $query = "SELECT id FROM users WHERE username = '$username' AND LOWER(nama_lengkap) = LOWER('$nama_lengkap') AND role = 'siswa'";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Jika cocok, buat session reset sementara agar aman dan tidak bisa ditembus langsung lewat URL
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['allow_reset'] = true;
            
            // Alihkan ke halaman pembuatan password baru
            header("Location: reset_password.php");
            exit;
        } else {
            $error = "Kombinasi Username/NIS dan Nama Lengkap tidak cocok atau tidak terdaftar!";
        }
    } else {
        $error = "Semua kolom formulir wajib diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemulihan Akun - SMK Jaya Buana</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4 font-sans text-xs antialiased">

    <div class="bg-white max-w-sm w-full p-6 rounded-2xl shadow-xl border-t-4 border-orange-500 space-y-5">
        <div class="text-center">
            <h1 class="text-sm font-black tracking-wider text-slate-800 uppercase">LUPA KATA SANDI</h1>
            <p class="text-[10px] text-slate-400 mt-1">Sistem Pemulihan Mandiri Akun Siswa PPDB SMK Jaya Buana</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl font-bold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-sm"></i> <?= $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block font-bold text-slate-500 mb-1 uppercase tracking-wider text-[9px]">Masukkan Username / NIS Anda:</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="fa-solid fa-id-card"></i></span>
                    <input type="text" name="username" required placeholder="Contoh: 2026001" class="w-full pl-9 p-2.5 border rounded-xl focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs font-mono shadow-inner">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-500 mb-1 uppercase tracking-wider text-[9px]">Masukkan Nama Lengkap Sesuai Pendaftaran:</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="nama_lengkap" required placeholder="Ketik nama lengkap Anda (Huruf Besar/Kecil)" class="w-full pl-9 p-2.5 border rounded-xl focus:ring-1 focus:ring-slate-900 focus:outline-none text-xs shadow-inner">
                </div>
                <p class="text-[9px] text-slate-400 mt-1 italic">*Gunakan nama lengkap yang didaftarkan oleh panitia admin.</p>
            </div>

            <button type="submit" class="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-2.5 rounded-xl uppercase tracking-wider shadow-sm transition duration-150 flex items-center justify-center gap-2">
                Verifikasi Identitas <i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i>
            </button>
        </form>

        <div class="text-center pt-2 border-t border-slate-100">
            <a href="login.php" class="text-blue-600 hover:underline font-bold"><i class="fa-solid fa-arrow-left"></i> Kembali ke Halaman Login</a>
        </div>
    </div>

</body>
</html>