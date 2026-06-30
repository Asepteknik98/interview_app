<?php
// auth/login.php
session_start();
require_once '../config/database.php';

$error_message = ""; // Disamakan agar sinkron dengan variabel tampilan HTML di bawah

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = md5(trim($_POST['password'])); // Menyesuaikan dengan enkripsi MD5 lama Bapak

    if (!empty($username) && !empty($password)) {
        $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // MEMISAHKAN "LACI" SESSION BERDASARKAN ROLE AGAR TIDAK SALING TABRAK
            if ($user['role'] === 'admin') {
                $_SESSION['admin_id']        = $user['id'];
                $_SESSION['admin_username']  = $user['username'];
                $_SESSION['admin_nama']      = $user['nama_lengkap'];
                $_SESSION['admin_role']      = 'admin'; // Kunci khusus admin
                
                header("Location: ../dashboard/admin.php");
                exit;
            } else {
                $_SESSION['siswa_id']        = $user['id'];
                $_SESSION['siswa_username']  = $user['username'];
                $_SESSION['siswa_nama']      = $user['nama_lengkap'];
                $_SESSION['siswa_role']      = 'siswa'; // Kunci khusus siswa
                
                header("Location: ../dashboard/siswa.php");
                exit;
            }
        } else {
            $error_message = "Username atau Password salah!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Diagnostik SMK JAYA BUANA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="../assets/jb.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body
    class="min-h-screen flex flex-col justify-between font-sans"
    style="
        background: url('../assets/fotojb.jpeg') center center / cover no-repeat;
        min-height: 100vh;
    ">

    <div class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8 w-full max-w-md text-center">
            
            <div class="flex justify-center items-center gap-6 mb-6">
                <div class="flex justify-center items-center gap-6 mb-6">

                <img src="../assets/jb.png"
                    alt="Logo SMK"
                    class="w-16 h-16 object-contain">

                <div class="h-10 w-px bg-gray-300"></div>

                <img src="../assets/yayasan.png"
                    alt="Logo Diagnostik"
                    class="w-16 h-16 object-contain">

                </div>
                <!-- <div class="h-8 w-[1px] bg-gray-300"></div>
                <div class="w-14 h-14 bg-orange-500 rounded flex flex-col items-center justify-center text-[10px] text-white font-bold leading-tight p-1">
                    <span>DIAG</span>
                    <span>NOSTIK</span>
                </div> -->
            </div>

            <h2 class="text-xl font-bold text-slate-800 tracking-wide uppercase mb-6">
                LOGIN DIAGNOSTIK SMK JAYA BUANA
            </h2>

            <?php if (!empty($error_message)): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm text-left flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= $error_message; ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4 text-left">
                
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Username / NIS</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fa-regular fa-user"></i>
                        </span>
                        <input type="text" name="username" placeholder="Masukkan Username / NIS" required
                            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="password" placeholder="Masukkan Password" required
                            class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-gray-400 hover:text-gray-600" onclick="togglePassword()">
                            <i class="fa-regular fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2 text-center">Pilih Role Login:</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="border-2 border-blue-500 bg-blue-50 rounded-lg p-3 flex items-center justify-center gap-2 cursor-pointer hover:bg-blue-100 transition" id="label-siswa">
                            <input type="radio" name="role" value="siswa" checked class="accent-blue-600" onclick="switchRole('siswa')">
                            <span class="text-sm font-bold text-blue-700 uppercase flex items-center gap-1">
                                <i class="fa-solid fa-graduation-cap"></i> SISWA
                            </span>
                        </label>
                        <label class="border-2 border-gray-200 bg-white rounded-lg p-3 flex items-center justify-center gap-2 cursor-pointer hover:bg-gray-50 transition" id="label-admin">
                            <input type="radio" name="role" value="admin" class="accent-orange-600" onclick="switchRole('admin')">
                            <span class="text-sm font-bold text-gray-600 uppercase flex items-center gap-1" id="text-admin">
                                <i class="fa-solid fa-user-gear"></i> ADMIN
                            </span>
                        </label>
                    </div>
                </div>

                <button type="submit" id="btn-submit"
                    class="w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 rounded-lg shadow-md transition duration-200 text-sm tracking-wide uppercase mt-2">
                    MASUK
                </button>
            </form>

            <div class="mt-4">
                <a href="lupa_password.php" class="text-sm text-blue-600 hover:underline font-medium">Lupa Password?</a>
            </div>
        </div>
    </div>

    <footer class="text-center py-4 bg-white border-t border-gray-200 text-xs text-gray-500 space-y-1">
        <p>Butuh Bantuan? 
            <a href="https://wa.me/6289661578210" target="_blank" class="text-green-600 hover:underline font-bold mr-2">
                <i class="fa-brands fa-whatsapp"></i> WhatsApp
            </a> | 
            <a href="mailto:asepsetiadi478@gmail.com" class="text-blue-600 hover:underline font-bold ml-2">
                <i class="fa-solid fa-envelope"></i> Email
            </a>
        </p>
    </footer>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function switchRole(role) {
            const labelSiswa = document.getElementById('label-siswa');
            const labelAdmin = document.getElementById('label-admin');
            const textAdmin = document.getElementById('text-admin');
            const btnSubmit = document.getElementById('btn-submit');

            if (role === 'siswa') {
                labelSiswa.className = "border-2 border-blue-500 bg-blue-50 rounded-lg p-3 flex items-center justify-center gap-2 cursor-pointer transition";
                labelAdmin.className = "border-2 border-gray-200 bg-white rounded-lg p-3 flex items-center justify-center gap-2 cursor-pointer transition";
                textAdmin.className = "text-sm font-bold text-gray-600 uppercase flex items-center gap-1";
                btnSubmit.className = "w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 rounded-lg shadow-md transition duration-200 text-sm tracking-wide uppercase mt-2";
            } else {
                labelSiswa.className = "border-2 border-gray-200 bg-white rounded-lg p-3 flex items-center justify-center gap-2 cursor-pointer transition";
                labelAdmin.className = "border-2 border-orange-500 bg-orange-50 rounded-lg p-3 flex items-center justify-center gap-2 cursor-pointer transition";
                textAdmin.className = "text-sm font-bold text-orange-700 uppercase flex items-center gap-1";
                btnSubmit.className = "w-full bg-orange-600 hover:bg-orange-500 text-white font-semibold py-3 rounded-lg shadow-md transition duration-200 text-sm tracking-wide uppercase mt-2";
            }
        }
    </script>
</body>
</html>