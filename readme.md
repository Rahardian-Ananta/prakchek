# 🎓 PrakCheck LMS

PrakCheck adalah sistem *Learning Management System* (LMS) sederhana yang dirancang khusus untuk mengelola pengumpulan tugas praktikum, mengecek plagiarisme dokumen (PDF/Word), dan memberikan penilaian secara terpusat antara Asisten Praktikum (Asprak) dan Mahasiswa.

---

## 🛠️ Persyaratan Sistem
Sebelum memulai, pastikan komputer Anda sudah terinstal aplikasi berikut:
- **XAMPP** atau **MAMP** (berisi server Apache dan MySQL).
- Web Browser modern (Chrome, Edge, atau Firefox).

---

## 🚀 Panduan Instalasi (Untuk Pemula)

Ikuti langkah-langkah di bawah ini secara berurutan jika Anda baru pertama kali menjalankan proyek ini di komputer Anda:

### Langkah 1: Persiapan Folder
1. Pastikan folder proyek ini bernama **`prakchek_`**.
2. Pindahkan seluruh folder `prakchek_` ini ke dalam direktori server lokal Anda:
   - Jika menggunakan **XAMPP (Windows)**: Pindahkan ke `C:\xampp\htdocs\`
   - Jika menggunakan **XAMPP/MAMP (Mac)**: Pindahkan ke `/Applications/XAMPP/htdocs/` atau `/Applications/MAMP/htdocs/`

### Langkah 2: Menyalakan Server
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache** dan modul **MySQL**. Pastikan keduanya berubah warna menjadi hijau.

### Langkah 3: Setup Database (Otomatis & Mudah)
Anda tidak perlu pusing membuat database secara manual di PHPMyAdmin. Cukup ikuti cara ini:
1. Buka browser Anda (Google Chrome / Firefox).
2. Ketikkan URL berikut di kolom pencarian alamat web: 
   👉 **`http://localhost/prakchek_/install_setup.php`**
3. Anda akan melihat halaman "Instalasi PrakCheck LMS".
4. Biarkan *Username* terisi `root` dan *Password* dikosongkan (kecuali XAMPP Anda memiliki password khusus).
5. **Pilih Tipe Data:**
   - **Data Minimal:** Untuk testing dasar (3 pengguna, 1 kelas)
   - **Data Komprehensif (Recommended):** Untuk testing menyeluruh (18 pengguna, 3 kelas, 12 tugas, 40+ submission)
6. Klik tombol hijau besar **"Install / Setup Database"**.
7. Selesai! Sistem akan otomatis membuatkan database dan mengisi data sesuai pilihan Anda.

> ⚠️ **PENTING UNTUK KEAMANAN:** 
> Setelah setup berhasil dan muncul tulisan hijau "Instalasi Berhasil Sempurna", segera **HAPUS** file `install_setup.php` dari folder proyek Anda agar tidak diakses orang lain dan mereset data Anda!

---

## 🧑‍💻 Akun Demo (Bawaan)

Jika Anda sudah menyelesaikan Instalasi di atas, Anda bisa langsung melakukan Login menggunakan akun percobaan (demo) yang sudah tersedia di database:

### **Data Minimal (Default):**
**Akun Asisten Praktikum (Asprak):**
- **Email:** `john@assistant.com`
- **Password:** `password123`

**Akun Mahasiswa:**
- **Email:** `jane@student.com`
- **Password:** `password123`

### **Data Komprehensif (Testing Menyeluruh):**
**Akun Asisten Praktikum (Asprak):**
- **Email:** `asprak1@prakchek.local`
- **Password:** `password123`
- **Juga tersedia:** `asprak2@prakchek.local`, `asprak3@prakchek.local`

**Akun Mahasiswa:**
- **Email:** `student1@prakchek.local`
- **Password:** `password123`
- **Juga tersedia:** `student2` sampai `student15@prakchek.local`

Untuk mencoba mendaftarkan Asisten Praktikum baru, Anda akan dimintai "Kode Rahasia". Secara bawaan, kodenya adalah:
- **Kode Rahasia Asisten:** `PRAKCHEK_ASPRAK_2026` 
*(Anda bisa mengganti kode ini kapan saja langsung dari dalam halaman Dashboard Asisten Anda).*

---

## ⚙️ Pengaturan Lanjutan (Jika Dibutuhkan)

Jika Anda ingin mengubah konfigurasi inti di masa depan, berikut adalah file-file penting yang mengatur sistem:

1. **`config/database.php`**
   Tempat mengatur koneksi database ke server MySQL.
2. **`includes/functions.php`**
   Tempat mengatur API *Brevo SMTP* untuk pengiriman email Lupa Password (baris fungsi `sendResetEmail`).
3. **`config/secret.php`**
   Tempat tersimpannya sandi rahasia asisten (walaupun direkomendasikan untuk mengubahnya langsung lewat layar Dashboard/UI).

---

## ✅ Dokumen Pengujian (QA Testing)
Untuk memastikan seluruh sistem berjalan lancar tanpa error, silakan jalankan dokumen Uji Kualitas Interaktif (QA) yang telah disediakan.
Buka di browser: 👉 **`http://localhost/prakchek_/qa_testing.html`**

Selamat mengelola praktikum dengan lebih mudah! 🎉
