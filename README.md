# Sistem Rekomendasi Jurusan SMK Berbasis RIASEC

Aplikasi web untuk membantu siswa menentukan jurusan SMK yang sesuai berdasarkan minat dan bakat menggunakan model **RIASEC (Realistic, Investigative, Artistic, Sosial, Enterprising, Conventional)**.

## Fitur

- Login Admin & Siswa
- Tes Minat Bakat RIASEC
- Rekomendasi Jurusan Otomatis
- Dashboard Admin
- Manajemen Soal & Jurusan
- Riwayat Hasil Tes
- Export PDF & Excel
- Responsive (Desktop & Mobile)

## Teknologi

- PHP Native
- MySQL
- Bootstrap 5
- JavaScript
- Chart.js

## Cara Kerja

1. Siswa mengerjakan tes minat.
2. Sistem menghitung skor R, I, A, S, E, dan C.
3. Skor diurutkan untuk mendapatkan kode dominan siswa.
4. Sistem membandingkan profil siswa dengan profil setiap jurusan.
5. Jurusan dengan skor kecocokan tertinggi menjadi rekomendasi utama.

## Struktur Folder

```text
admin/
assets/
config/
database/
export/
includes/
siswa/
README.md
```

## Instalasi

1. Clone project.
2. Import database ke MySQL.
3. Atur koneksi database pada file konfigurasi.
4. Jalankan melalui XAMPP/Laragon.

## Catatan Developer

- Logika rekomendasi berada pada folder `proses/` atau file perhitungan hasil.
- Jurusan dan bobot dapat dimodifikasi sesuai kebutuhan sekolah.
- Soal RIASEC dapat ditambah atau dikurangi melalui panel admin.
- Sistem dirancang agar mudah dikembangkan dan dijadikan referensi penelitian atau proyek serupa.

## Developer

**Asep Setiadi, S.Kom**  
