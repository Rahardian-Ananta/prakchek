# DRAFT UPDATE SKPL (Spesifikasi Kebutuhan Perangkat Lunak) PrakCheck
*Dokumen ini adalah rancangan sementara untuk menyelaraskan SKPL lama dengan fitur aktual (100% by code) yang telah terimplementasi di PrakCheck.*

## 1. Pembaruan Deskripsi Umum Sistem
PrakCheck bukan sekadar sistem pengumpulan tugas biasa, melainkan platform **Learning Management System (LMS) khusus praktikum** yang dirancang dengan kapabilitas *In-Browser Preview* untuk mempercepat proses koreksi oleh asisten, serta dilengkapi dengan sistem cerdas pendeteksi kemiripan (plagiarisme) antar pengumpulan tugas mahasiswa.

## 2. Pembaruan Aktor (User Roles)
1. **Asisten Praktikum (Asprak):** Bertindak sebagai pengajar utama di dalam sistem. Memiliki kendali penuh atas kelas, pengumuman, modul tugas, koreksi, dan verifikasi plagiarisme.
2. **Mahasiswa:** Aktor pasif-aktif yang tergabung dalam kelas berdasarkan aspek/golongan, menerima pengumuman, dan mengunggah tugas sesuai tenggat waktu.

## 3. Daftar Use Case (100% Akurat dengan Kode Saat Ini)

### UC-01: Instalasi & Pembuatan Skenario Data (One-Click Installer)
- **Aktor:** Administrator / Asisten
- **Deskripsi:** Menjalankan `install_setup.php` untuk memformat ulang database (`install.sql`) dan secara otomatis membibitkan (seed) skenario pengujian komprehensif menggunakan `add_data_dummy_2.php`.
- **Kondisi Akhir:** Tercipta 10 akun Asprak, 50 akun Mahasiswa, 40 modul tugas, dan 300+ pengumpulan tugas dengan 5 profil behavior mahasiswa yang bervariasi (aktif, telat, plagiat, dsb). Mahasiswa secara otomatis tergabung ke tabel `class_members`.

### UC-02: Autentikasi & Keamanan Akun
- **Aktor:** Semua
- **Deskripsi:** Login dengan proteksi *password_hash* standar industri. Termasuk fitur "Lupa Password" yang memicu pengiriman token OTP/Link via Brevo SMTP Integration (`config_mail.php`).

### UC-03: Manajemen Kelas & Pengumuman
- **Aktor:** Asprak
- **Deskripsi:** Pembuatan kelas (men-generate kode akses), mengelola anggota kelas, dan menyiarkan pengumuman dengan kemampuan untuk melampirkan file *attachment* pendukung.

### UC-04: Manajemen Modul Tugas (Assignment)
- **Aktor:** Asprak
- **Deskripsi:** Membuat instruksi tugas dengan 3 opsi tipe tenggat waktu:
  1. *No Deadline* (bebas kapan saja).
  2. *Soft Deadline* (Menerima keterlambatan/late submission dengan penanda).
  3. *Strict Deadline* (Sistem mengunci pengunggahan secara otomatis).
- **Aturan File:** Asprak dapat membatasi ekstensi file yang diizinkan (.pdf, .docx, .zip) serta jumlah maksimum file (hingga 5 file per tugas).

### UC-05: Pengumpulan Tugas (Submission)
- **Aktor:** Mahasiswa
- **Deskripsi:** Mengunggah file tugas menggunakan antarmuka *Drag & Drop* yang elegan. Menyimpan tugas sebagai *Draft* (belum dinilai) sebelum dilakukan *Final Submit*. 

### UC-06: In-Browser File Preview Engine
- **Aktor:** Asprak & Mahasiswa
- **Deskripsi:** Melihat secara langsung isi file yang diunggah tanpa harus men-download. Format yang didukung (100% tervalidasi di `assets/js/preview.js` & `serve_file.php`):
  - **Dokumen:** `.pdf` (via PDF.js) dan `.docx` / `.doc` (via Mammoth.js secara lokal tanpa server Microsoft).
  - **Multimedia:** Gambar (`.jpg`, `.png`, `.webp`) dan Video (`.mp4`, `.webm`).
  - **Source Code / Teks:** `.txt`, `.php`, `.sql`, `.js`, `.css`, `.json`, `.md` (di-render rapi pada *monokai code block* lengkap dengan proteksi *escape XSS*).

### UC-07: Deteksi Kemiripan & Plagiarisme Lintas Dokumen
- **Aktor:** Asprak
- **Deskripsi:** Mengeksekusi modul `check_plagiarism.php` untuk membandingkan satu file tugas (.pdf / .docx) mahasiswa terhadap *seluruh* file mahasiswa lain dalam satu modul tugas (satu *assignment_id*).
- **Proses:** Sistem mengekstraksi teks dokumen, mengubahnya menjadi *n-gram/trigram*, dan menghitung persentase kemiripan *Intersection over Union*.
- **Aksi Penalti:** Asisten dapat mengaplikasikan denda nilai (*plagiarism penalty*) secara terpisah dari *grade* murni (Nilai Akhir = Grade - Penalty).

### UC-08: Penilaian (Grading) & Rekapitulasi Ekspor
- **Aktor:** Asprak
- **Deskripsi:** Memberikan nilai akhir (maks 100) dan *feedback* naratif. Mampu mengekspor rekapan nilai seluruh kelas menjadi file CSV (`export_grades.php`), di mana perhitungan akumulasi (bobot persentase) diproses secara adil tanpa mengikutsertakan kebocoran *denominator* pada mahasiswa yang tidak mengumpulkan tugas.

### UC-09: Pendaftaran Kelas Mandiri (Self-Enrollment)
- **Aktor:** Mahasiswa
- **Deskripsi:** Mahasiswa dapat bergabung ke dalam kelas secara mandiri melalui menu `Join Class` dengan memasukkan Kode Kelas (*Class Code*) unik (berisi 8 karakter alfanumerik) yang dibagikan oleh Asisten.

### UC-10: Rotasi Kode Rahasia Asisten (UI Security Management)
- **Aktor:** Asprak
- **Deskripsi:** Asisten dapat secara mandiri memperbarui "Kode Rahasia Pendaftaran Asisten" langsung dari halaman Dashboard tanpa perlu menyentuh source code (`config/secret.php`). Ini digunakan sebagai pengamanan berlapis agar mahasiswa tidak bisa mendaftar sebagai asisten.

### UC-11: Kategorisasi Mahasiswa via Aspek Penilaian
- **Aktor:** Asprak
- **Deskripsi:** Asisten dapat memecah mahasiswa di dalam satu kelas yang sama ke dalam beberapa "Aspek Penilaian" (*Assessment Aspects*), memungkinkan pengelompokan koreksi yang lebih terstruktur.

---

## 4. Pembaruan Logika Bisnis & Constraint Database

1. **Akses Data Ketat (Authorization):**
   - File hanya bisa dibuka (di-request via HTTP) jika yang meminta adalah **Uploader asli (Mahasiswa tersebut)** atau **Asisten dari kelas yang menaungi file tersebut** (dicek di `isClassAssistant` & `isClassMember`).
2. **Sinkronisasi Waktu (Timezone Fix):**
   - Database PDO dipaksa menggunakan `SET time_zone = '+07:00'` (WIB) untuk mencegah *delay/offset* perhitungan *deadline* pada aplikasi.
3. **Pembersihan File Otomatis:**
   - Jika suatu pengumuman, tugas, atau *submission* dihapus, *physical file* pada folder `uploads/` akan diverifikasi dan dihapus menggunakan `unlink()`.

---
*Silakan salin draf ini untuk memperbarui dokumen SKPL resmi Anda.*
