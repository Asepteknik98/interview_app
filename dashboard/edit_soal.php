<?php
// dashboard/edit_soal.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$query_find = $conn->query("SELECT * FROM soal_diagnostik WHERE id = $id LIMIT 1");
if ($query_find->num_rows == 0) {
    header("Location: admin.php");
    exit;
}

$soal = $query_find->fetch_assoc();
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pertanyaan = mysqli_real_escape_string($conn, $_POST['pertanyaan']);
    $query_update = "UPDATE soal_diagnostik SET pertanyaan = '$pertanyaan' WHERE id = $id";

    if ($conn->query($query_update)) {
        header("Location: admin.php");
        exit;
    } else {
        $message = "<div class='p-3 bg-red-100 text-red-700 rounded-lg text-xs mb-4'>Gagal merubah data!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pertanyaan Asesmen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col justify-between text-xs">
    <nav class="bg-slate-900 text-white px-6 py-4 shadow font-bold">EDIT BUTIR PERNYATAAN ARTISTIC</nav>
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow border border-gray-200 p-6 w-full max-w-md space-y-4">
            <h2 class="font-bold text-gray-700 border-b pb-2 uppercase">Ubah Kalimat Indikator</h2>
            <?= $message; ?>
            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block font-medium text-gray-600 mb-1">Pernyataan Soal Ke-<?= $soal['nomor_urut']; ?>:</label>
                    <textarea name="pertanyaan" required rows="4" class="w-full p-2 border rounded-lg focus:outline-none text-sm"><?= htmlspecialchars($soal['pertanyaan']); ?></textarea>
                </div>
                <div class="flex gap-2">
                    <a href="admin.php" class="w-1/3 bg-gray-200 text-center font-bold py-2 rounded-lg">Batal</a>
                    <button type="submit" class="w-2/3 bg-amber-500 text-white font-bold py-2 rounded-lg uppercase">Simpan</button>
                </div>
            </form>
        </div>
    </main>
    <footer class="bg-white text-center py-3 text-gray-400 border-t">&copy; 2026 SMK Jaya Buana</footer>
</body>
</html>