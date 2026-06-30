<?php
// auth/reset_password.php
session_start();
require_once '../config/database.php';

// Proteksi ketat: Jika belum lolos verifikasi di halaman lupa_password.php, tendang kembali
if (!isset($_SESSION['allow_reset']) || $_SESSION['allow_reset'] !== true || !isset($_SESSION['reset_user_id'])) {
    header("Location: lupa_password.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password_baru = trim($_POST['password_baru']);
    $konfirmasi_password = trim($_POST['konfirmasi_password']);

    if (!empty($password_baru) && !empty($konfirmasi_password)) {
        if ($password_baru === $konfirmasi_password) {
            $user_id = $_SESSION['reset_user_id'];
            $password_encrypted = md5($password_baru); // Sinkronisasi enkripsi MD5 lama milik Bapak

            // Update password baru ke database
            $query_update = "UPDATE users SET password = '$password_encrypted' WHERE id = $user_id AND role = 'siswa'";
            
            if ($conn->query($query_update)) {
                $success = "Kata sandi Anda berhasil diperbarui! Silakan kembali login menggunakan sandi baru.";
                
                // Hancurkan session reset agar tidak bisa disalahgunakan lagi
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['allow_reset']);
            } else {
                $error = "Gagal memperbarui database. Silakan coba lagi.";
            }
        } else {
            $error = "Konfirmasi kata sandi tidak cocok! Pastikan kedua kolom sama.";
        }
    } else {
        $error = "Semua kolom wajib diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Sandi Baru - SMK Jaya Buana</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4 font-sans text-xs antialiased">

    <div class="bg-white max-w-sm w-full p-6 rounded-2xl shadow-xl border-t-4 border-emerald-500 space-y-5">
        <div class="text-center">
            <h1 class="text-sm font-black tracking-wider text-slate-800 uppercase">ATUR ULANG KATA SANDI</h1>
            <p class="text-[10px] text-slate-400 mt-1">Silakan masukkan kombinasi kata sandi baru Anda</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl font-bold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-sm"></i> <?= $error; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($success)): ?>
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl font-bold space-y-3">
                <div class="flex items-center gap-2 text-xs">
                    <i class="fa-solid fa-circle-check text-base text-emerald-600"></i> <?= $success; ?>
                </div>
                <a href="login.php" class="block text-center bg-emerald-600 hover:bg-emerald-700 text-white py-2 rounded-xl text-[11px] font-black tracking-wider shadow transition">MASUK KE HALAMAN LOGIN</a>
            </div>
        <?php endif; ?>

        <?php if(empty($success)): ?>
            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block font-bold text-slate-500 mb-1 uppercase tracking-wider text-[9px]">Kata Sandi Baru:</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password_baru" required placeholder="Buat kata sandi baru" class="w-full pl-9 p-2.5 border rounded-xl focus:ring-1 focus:ring-emerald-500 focus:outline-none text-xs shadow-inner">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-500 mb-1 uppercase tracking-wider text-[9px]">Ulangi Kata Sandi Baru:</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="fa-solid fa-circle-check"></i></span>
                        <input type="password" name="konfirmasi_password" required placeholder="Ulangi kata sandi baru" class="w-full pl-9 p-2.5 border rounded-xl focus:ring-1 focus:ring-emerald-500 focus:outline-none text-xs shadow-inner">
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl uppercase tracking-wider shadow-sm transition duration-150 flex items-center justify-center gap-2">
                    Simpan & Perbarui Sandi <i class="fa-solid fa-key text-[10px]"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>