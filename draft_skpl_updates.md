# DRAFT UPDATE SKPL (Spesifikasi Kebutuhan Perangkat Lunak)
# PrakCheck — Platform LMS Manajemen Praktikum

*Revisi ini menyajikan use case sistem produksi PrakCheck yang terurut secara logis dan 100% akurat berdasarkan implementasi kode yang telah berjalan.*

---

## 1. Deskripsi Umum Sistem

PrakCheck adalah sebuah platform **Learning Management System (LMS) berbasis web khusus praktikum** yang dibangun menggunakan PHP Native, MySQL, dan Bootstrap 5. Sistem ini mengelola siklus lengkap praktikum: mulai dari pendaftaran akun, pembuatan kelas, distribusi tugas, pengumpulan file oleh mahasiswa, pemeriksaan kemiripan antar dokumen (plagiarisme), hingga penilaian akhir dan ekspor laporan nilai.

Sistem memiliki dua level peran pengguna dengan hak akses yang berbeda: **Asisten Praktikum (Asprak)** dan **Mahasiswa**.

---

## 2. Aktor Sistem

| Aktor | Deskripsi |
|---|---|
| **Asisten Praktikum (Asprak)** | Pengelola utama. Bertanggung jawab atas pembuatan kelas, pengelolaan tugas dan pengumuman, penilaian, dan verifikasi plagiarisme. |
| **Mahasiswa** | Peserta aktif. Mendaftar ke sistem, bergabung ke kelas melalui kode unik, menerima pengumuman, dan mengumpulkan file tugas sesuai tenggat waktu. |

---

## 3. Daftar Use Case (Terurut & Akurat dengan Alur Sistem Produksi)

> **Catatan Urutan:** Use case di bawah ini diurutkan sesuai dengan alur kerja nyata dari awal (pendaftaran akun) hingga akhir (ekspor laporan nilai).

---

### ═══ FASE 1: MANAJEMEN AKUN ═══

### UC-01: Registrasi Akun Baru
- **Aktor:** Calon Asprak / Calon Mahasiswa
- **Deskripsi:** Pengguna mendaftarkan akun baru dengan mengisi nama lengkap, email, password, dan memilih peran (Role).
  - Jika mendaftar sebagai **Mahasiswa**: akun langsung dibuat setelah validasi data.
  - Jika mendaftar sebagai **Asisten**: pengguna wajib memasukkan *Kode Rahasia Asisten* yang hanya diketahui oleh pengelola sistem. Kode ini berfungsi sebagai gerbang keamanan agar mahasiswa tidak bisa mendaftar sebagai Asprak secara sembarangan.
- **Validasi:** Email tidak boleh terdaftar ganda, password minimal 6 karakter, format email harus valid.
- **Keamanan:** Password disimpan dalam bentuk hash kriptografis, bukan teks biasa.

### UC-02: Login ke Sistem
- **Aktor:** Semua pengguna terdaftar
- **Deskripsi:** Pengguna masuk ke sistem menggunakan kombinasi email dan password. Sistem memverifikasi kecocokan hash password. Setelah berhasil, sesi login dibuat dan pengguna diarahkan ke halaman Dashboard sesuai peran mereka.

### UC-03: Pemulihan Password (Lupa Password)
- **Aktor:** Semua pengguna terdaftar
- **Deskripsi:** Pengguna yang lupa kata sandi dapat mengajukan permintaan reset melalui formulir "Lupa Password". Sistem mengirimkan tautan reset bertanda waktu dan bersifat sekali pakai ke alamat email terdaftar pengguna melalui layanan SMTP. Pengguna kemudian dapat menetapkan password baru melalui tautan tersebut.

### UC-04: Logout dari Sistem
- **Aktor:** Semua pengguna yang sudah login
- **Deskripsi:** Menghancurkan sesi login pengguna secara aman dan mengarahkan kembali ke halaman login.

### UC-05: Pembaruan Kode Rahasia Asisten
- **Aktor:** Asprak (yang sudah login)
- **Deskripsi:** Asisten dapat mengubah "Kode Rahasia Pendaftaran Asisten" secara mandiri langsung dari panel Dashboard tanpa perlu mengedit konfigurasi server. Fitur ini tersedia apabila kode yang beredar dikhawatirkan telah bocor ke pihak yang tidak berwenang. Setiap perubahan dilindungi oleh mekanisme token CSRF.

---

### ═══ FASE 2: MANAJEMEN KELAS (ASPRAK) ═══

### UC-06: Membuat Kelas Baru
- **Aktor:** Asprak
- **Kondisi Prasyarat:** Akun Asprak sudah terdaftar dan sudah login (UC-01, UC-02).
- **Deskripsi:** Asprak mendirikan kelas baru dengan menginputkan nama kelas (contoh: *Pemrograman Web — Kelas A*). Sistem secara otomatis menghasilkan **Kode Kelas unik** berisi 8 karakter alfanumerik yang dapat dibagikan kepada mahasiswa sebagai akses masuk kelas. Seorang Asprak dapat mengelola banyak kelas secara bersamaan.
- **Kondisi Akhir:** Kelas baru muncul di Dashboard Asprak beserta Kode Kelas yang siap dibagikan.

---

### ═══ FASE 3: PENDAFTARAN MAHASISWA KE KELAS ═══

### UC-07: Bergabung ke Kelas (Self-Enrollment)
- **Aktor:** Mahasiswa
- **Kondisi Prasyarat:** Mahasiswa sudah login (UC-02). Asprak sudah membuat kelas dan membagikan Kode Kelas (UC-06).
- **Deskripsi:** Mahasiswa bergabung ke kelas secara mandiri dengan memasukkan Kode Kelas yang diterima dari Asprak. Sistem memvalidasi kode, kemudian mendaftarkan mahasiswa ke dalam kelas tersebut.
- **Kondisi Akhir:** Kelas muncul di daftar kelas mahasiswa di Dashboard. Mahasiswa selanjutnya dapat melihat pengumuman dan tugas dari kelas tersebut.

---

### ═══ FASE 4: PENGELOLAAN KONTEN KELAS (ASPRAK) ═══

### UC-08: Memposting Pengumuman Kelas
- **Aktor:** Asprak
- **Kondisi Prasyarat:** Kelas sudah ada dan Asprak adalah pemiliknya (UC-06).
- **Deskripsi:** Asprak memposting pengumuman berisi judul dan konten teks yang dapat dilihat oleh seluruh anggota kelas. Asprak dapat melampirkan file pendukung seperti modul praktikum atau slide presentasi. Pengumuman dapat diedit dan dihapus kapan saja; penghapusan juga membersihkan file lampiran secara permanen dari server.

### UC-09: Membuat Modul Tugas (Assignment)
- **Aktor:** Asprak
- **Kondisi Prasyarat:** Kelas sudah ada dan Asprak adalah pemiliknya (UC-06).
- **Deskripsi:** Asprak membuat tugas baru untuk sebuah kelas dengan mengisi detail berikut:
  - **Judul & deskripsi instruksi** pengerjaan tugas.
  - **Tipe Tenggat Waktu**, pilih salah satu:
    - *No Deadline* — Mahasiswa dapat mengumpulkan kapan saja tanpa batas.
    - *Soft Deadline* — Pengumpulan setelah tenggat masih diterima, namun ditandai sebagai "Terlambat".
    - *Strict Deadline* — Sistem menutup akses pengumpulan secara otomatis tepat saat tenggat berakhir.
  - **Batasan format file** yang diizinkan (misal: hanya `.pdf` dan `.docx`) dan **jumlah maksimum file** per pengumpulan.
  - **Aturan penalti plagiarisme otomatis**: Asprak dapat mendefinisikan pasangan ambang batas kemiripan (%) dan poin penalti yang akan diterapkan secara otomatis ketika pemeriksaan plagiarisme dijalankan.
- **Kondisi Akhir:** Modul tugas baru muncul di halaman kelas dan dapat diakses oleh semua mahasiswa anggota kelas.

---

### ═══ FASE 5: PENGUMPULAN TUGAS (MAHASISWA) ═══

### UC-10: Mengumpulkan Tugas
- **Aktor:** Mahasiswa
- **Kondisi Prasyarat:** Mahasiswa sudah terdaftar di kelas (UC-07). Modul tugas sudah dibuat oleh Asprak (UC-09). Tenggat waktu belum berakhir (untuk Strict Deadline).
- **Deskripsi:** Mahasiswa mengunggah file tugas melalui antarmuka seret-dan-jatuhkan (*drag & drop*). Pengumpulan berlangsung dalam dua tahap:
  1. **Tahap Draft:** File berhasil terunggah namun belum dikumpulkan secara resmi. Pada tahap ini, mahasiswa masih bebas menghapus, mengganti, atau menambahkan file baru.
  2. **Tahap Final Submit:** Mahasiswa mengkonfirmasi pengumpulan akhir. Status berubah menjadi "Dikumpulkan", file terkunci dari perubahan lebih lanjut, dan sistem mencatat waktu pengumpulan secara akurat untuk keperluan validasi keterlambatan.

---

### ═══ FASE 6: KOREKSI & PENILAIAN (ASPRAK) ═══

### UC-11: Melihat Pratinjau File Tugas (In-Browser Preview)
- **Aktor:** Asprak & Mahasiswa
- **Deskripsi:** Pengguna dapat melihat isi file yang diunggah langsung di dalam browser tanpa harus mengunduhnya terlebih dahulu. Format file yang didukung:
  - **Dokumen PDF** — dirender langsung oleh browser.
  - **Dokumen Word (.docx/.doc)** — dikonversi menjadi HTML dan ditampilkan langsung di halaman.
  - **File Gambar** — `.jpg`, `.png`, `.gif`, `.webp` ditampilkan secara langsung.
  - **File Video** — `.mp4`, `.webm` diputar menggunakan pemutar video HTML5 bawaan.
  - **File Teks & Kode Sumber** — `.txt`, `.php`, `.sql`, `.js`, `.css`, `.json`, `.md`, `.csv` ditampilkan dalam blok kode bertema gelap yang rapi.
- **Keamanan:** Sebuah file hanya dapat diakses oleh **pemilik file itu sendiri (mahasiswa yang mengunggah)** atau **Asprak dari kelas yang menaungi file tersebut**. Permintaan dari pengguna yang tidak terotorisasi akan ditolak.

### UC-12: Pemeriksaan & Deteksi Plagiarisme Lintas Dokumen
- **Aktor:** Asprak
- **Kondisi Prasyarat:** Minimal ada dua mahasiswa yang sudah mengumpulkan tugas pada modul yang sama dengan format file PDF atau Word.
- **Deskripsi:** Asprak menjalankan modul deteksi plagiarisme untuk suatu modul tugas. Proses yang terjadi:
  1. Sistem mengekstrak teks dari seluruh dokumen PDF dan Word yang dikumpulkan.
  2. Teks dipecah menjadi pecahan-pecahan kecil (*N-gram/trigram*).
  3. Tingkat kemiripan antara setiap pasang dokumen dihitung menggunakan metode *Intersection over Union*.
  4. Laporan matriks kemiripan antar mahasiswa ditampilkan kepada Asprak.
- **Aksi Lanjutan:** Asprak dapat menerapkan **Penalti Plagiarisme** secara non-destruktif. Nilai asli tidak diubah; sistem menyimpan nilai penalti secara terpisah. **Nilai Akhir = Nilai Asli − Total Penalti**.

### UC-13: Penilaian Tugas Mahasiswa (Grading)
- **Aktor:** Asprak
- **Kondisi Prasyarat:** Mahasiswa sudah melakukan Final Submit (UC-10).
- **Deskripsi:** Asprak membuka detail pengumpulan tugas setiap mahasiswa, memberikan nilai numerik (0–100) dan catatan feedback naratif berbasis teks. Nilai dan feedback dapat diperbarui kembali oleh Asprak selama proses penilaian masih berlangsung.

---

### ═══ FASE 7: PELAPORAN & EKSPOR ═══

### UC-14: Ekspor Rekap Nilai ke Berkas CSV
- **Aktor:** Asprak
- **Deskripsi:** Asprak mengunduh rekapitulasi nilai dalam format berkas `.csv` yang dapat dibuka dengan Microsoft Excel. Berkas menggunakan delimiter titik koma (`;`) dan penyandian UTF-8 agar kompatibel dengan pengaturan regional Indonesia. Terdapat dua mode ekspor:
  - **Per Tugas:** Menampilkan detail nilai satu modul tugas, mencakup kolom: Nilai Asli, Potongan Plagiarisme, Potongan Keterlambatan, Nilai Akhir, dan Feedback Asisten.
  - **Rekapitulasi Kelas:** Menampilkan semua mahasiswa dalam satu kelas dengan nilai setiap tugas dalam kolom terpisah; siap digunakan sebagai laporan nilai akhir semester.

---

## 4. Kebutuhan Non-Fungsional (NFR)

| No. | Aspek | Keterangan |
|---|---|---|
| NFR-01 | **Keamanan CSRF** | Seluruh formulir yang mengubah data dilindungi oleh token CSRF dinamis berbasis sesi. |
| NFR-02 | **Kontrol Akses Berbasis Peran (RBAC)** | Setiap halaman dan aksi terproteksi; Asprak tidak bisa mengakses data kelas milik Asprak lain. |
| NFR-03 | **Otorisasi Kepemilikan File** | File hanya dapat diakses oleh pemilik aslinya atau Asprak kelas yang bersangkutan; permintaan tidak sah ditolak (HTTP 403). |
| NFR-04 | **Akurasi Waktu (Timezone)** | Sistem dikonfigurasi pada Zona Waktu WIB (UTC+7) untuk memastikan kalkulasi tenggat waktu selalu akurat. |
| NFR-05 | **Pembersihan Penyimpanan Otomatis** | Penghapusan data juga memicu penghapusan file fisik dari server untuk mencegah penumpukan sampah (*storage leak*). |
| NFR-06 | **Pengelolaan Notifikasi Aman** | Notifikasi sistem (sukses/gagal) disampaikan melalui mekanisme sesi PHP, bukan melalui parameter URL yang rentan dimanipulasi. |

---

*Dokumen ini merepresentasikan use case sistem PrakCheck dalam kapasitas produksi dan siap digunakan sebagai referensi pembaruan SKPL resmi.*
