# Panduan Pengujian Manual (Manual QA Testing) - PrakCheck

Panduan ini dibuat untuk menguji seluruh fitur yang telah diperbarui pada sistem LMS **PrakCheck**, termasuk fitur lampiran tugas, tipe pengumpulan, validasi deadline, preview dokumen, sistem deteksi plagiarisme, dan **sistem grading yang baru**.

---

## 📋 PERSIAPAN DATABASE & AKUN

### **Opsi 1: Data Komprehensif (Recommended)**
Untuk testing menyeluruh, install **Data Komprehensif** melalui `install_setup.php` yang menyediakan:
- **3 Asprak** dengan gaya mengajar berbeda
- **15 Mahasiswa** dengan 5 profil unik (active, average, perfectionist, procrastinator, struggling)
- **12 Tugas** dengan berbagai tipe (individual, group, exam, optional)
- **40+ Submission** dengan berbagai status dan grade
- **File asli** untuk testing (PDF, DOCX, JPG, TXT, PHP)

### **Akun untuk Testing Komprehensif:**
1. **Asprak 1:** `asprak1@prakchek.local` / `password123`
2. **Mahasiswa Active:** `student1@prakchek.local` / `password123`
3. **Mahasiswa Perfectionist:** `student3@prakchek.local` / `password123`
4. **Mahasiswa Struggling:** `student5@prakchek.local` / `password123`

### **Opsi 2: Data Minimal**
Untuk testing dasar, gunakan data minimal:
1. **Asprak:** `john@assistant.com` / `password123`
2. **Mahasiswa A:** `jane@student.com` / `password123`
3. **Mahasiswa B:** `bob@student.com` / `password123`

---

## 1. PENGUJIAN ALUR LOGIN & DASHBOARD
*   [ ] **Langkah**: Masuk ke halaman login (`login.php`), masukkan kredensial Asprak.
*   [ ] **Langkah**: Lakukan hal yang sama untuk akun Mahasiswa A dan B.
*   [ ] **Ekspektasi**: Pengguna berhasil masuk dan dialihkan ke `dashboard.php` sesuai dengan role masing-masing.

---

## 2. PENGUJIAN ANGGOTA KELAS (CLASS MEMBERS)
*   [ ] **Langkah**: Buka halaman kelas (`class.php?id={id_kelas}`) baik dari akun Asprak maupun Mahasiswa.
*   [ ] **Ekspektasi**: Di bagian sidebar kanan/bawah halaman kelas, muncul daftar nama seluruh anggota (mahasiswa) yang telah bergabung di kelas tersebut.

---

## 3. PENGUJIAN MEMBUAT TUGAS & LAMPIRAN (ASPRAK)
*   [ ] **Langkah**: Masuk sebagai **Asprak**, buka kelas, lalu klik **"Create Assignment"**.
*   [ ] **Langkah**: Isi judul dan instruksi tugas.
*   [ ] **Langkah**: Pada bagian **"Allowed Submission Formats"**, centang semua format (Link, Gambar, PDF/Word, Text).
*   [ ] **Langkah**: Pada bagian **"Attachments"**, unggah 1 file PDF dan 1 file Gambar (.png/.jpg). Klik **Create Assignment**.
*   [ ] **Ekspektasi**:
    *   Tugas berhasil dibuat tanpa ada error database.
    *   Buka detail tugas tersebut. Lampiran PDF dan Gambar harus muncul sebagai kartu (Card Layout).
    *   Klik tombol **"View"** pada lampiran PDF: PDF viewer modal harus terbuka dengan loading spinner, dapat di-scroll penuh, dan memiliki tombol "Open Fullscreen".
    *   Klik tombol **"View"** pada gambar: Gambar harus muncul dengan pratinjau yang tajam.

---

## 4. PENGUJIAN PENGUMPULAN TUGAS DRAFT & PREVIEW (MAHASISWA)
*   [ ] **Langkah**: Masuk sebagai **Mahasiswa A**, buka tugas yang baru saja dibuat.
*   [ ] **Langkah**: Coba kumpulkan file yang tidak sesuai format (jika Asprak membatasi format).
*   [ ] **Langkah**: Masukkan komentar teks dan unggah file PDF rancangan laporan Anda. Klik **"Save Draft"**.
*   [ ] **Ekspektasi**:
    *   Draft berhasil disimpan.
    *   Sebelum menekan "Turn In", Mahasiswa A harus bisa melihat file draft-nya di bawah bagian "Your Submission".
    *   Klik tombol **"View"** pada file draft Mahasiswa A untuk memastikan preview PDF berjalan dengan lancar bagi mahasiswa sebelum dikumpulkan.

---

## 5. PENGUJIAN LOGIKA DEADLINE & TURN IN
### A. Soft Deadline (Tipe 2)
*   [ ] **Langkah**: Buat tugas baru dengan **Soft Deadline** yang diatur 5 menit yang lalu dari waktu sekarang.
*   [ ] **Langkah**: Masuk sebagai **Mahasiswa A**, lakukan pengumpulan draft, lalu klik **"Turn In"**.
*   [ ] **Ekspektasi**: Pengumpulan berhasil dikirim, namun muncul badge/status merah **"Late" (Terlambat)** pada detail tugas mahasiswa dan panel penilaian Asprak.

### B. Strict Deadline (Tipe 3)
*   [ ] **Langkah**: Buat tugas baru dengan **Strict Deadline** yang diatur 5 menit yang lalu dari waktu sekarang.
*   [ ] **Langkah**: Masuk sebagai **Mahasiswa A**, buka halaman tugas tersebut.
*   [ ] **Ekspektasi**: Tombol "Save Draft" dan "Turn In" dinonaktifkan atau disembunyikan. Muncul pesan peringatan bahwa batas waktu pengumpulan telah berakhir.

---

## 6. PENGUJIAN DETEKSI PLAGIARISME (CROSS-COMPARISON)
*   [ ] **Langkah**: Pastikan **Mahasiswa A** mengunggah file laporan PDF/Word yang berisi teks tertentu (misal: tulisan tentang "Algoritma Jaccard Index"). Klik **"Turn In"**.
*   [ ] **Langkah**: Masuk sebagai **Mahasiswa B**, unggah file PDF/Word yang memiliki paragraf yang sangat mirip atau persis sama dengan Mahasiswa A. Klik **"Turn In"**.
*   [ ] **Langkah**: Masuk sebagai **Asprak**, buka halaman detail tugas tersebut.
*   [ ] **Langkah**: Di panel kanan "Submissions Summary", klik tombol berwarna oranye **"Check Plagiarism"**.
*   [ ] **Ekspektasi**:
    *   Sistem akan memproses perbandingan menyilang teks secara otomatis.
    *   Muncul tabel laporan plagiarisme yang menunjukkan nama **Mahasiswa A** vs **Mahasiswa B**.
    *   Menampilkan nama file masing-masing yang dibandingkan.
    *   Menampilkan skor persentase kesamaan teks.
    *   Baris tabel akan otomatis berwarna merah/kuning jika persentase kemiripan tinggi.

---

## 7. PENGUJIAN SISTEM GRADING & FEEDBACK
*   [ ] **Langkah**: Masuk sebagai **Asprak**, buka halaman detail tugas yang sudah ada submission.
*   [ ] **Langkah**: Klik pada submission mahasiswa untuk melihat detail.
*   [ ] **Langkah**: Berikan nilai (grade) antara 0-100, tambahkan plagiarism penalty jika perlu, dan tulis feedback.
*   [ ] **Ekspektasi**:
    *   Grade berhasil disimpan dan ditampilkan di halaman assignment.
    *   Plagiarism penalty mengurangi nilai akhir.
    *   Feedback tampil di halaman mahasiswa.
    *   Timestamp `graded_at` tercatat.
*   [ ] **Langkah**: Masuk sebagai **Mahasiswa**, buka halaman tugas yang sudah di-grade.
*   [ ] **Ekspektasi**: Mahasiswa dapat melihat grade, penalty, dan feedback dari asprak.

## 8. PENGUJIAN EXPORT GRADES
*   [ ] **Langkah**: Masuk sebagai **Asprak**, buka halaman kelas.
*   [ ] **Langkah**: Klik tombol **"Export Grades"**.
*   [ ] **Ekspektasi**:
    *   File CSV berhasil di-download.
    *   File berisi kolom: Nama, Email, Status, Grade, Plagiarism Penalty, Feedback, Submitted At.
    *   Data grade dari submission yang sudah di-grade tersedia.
    *   Format CSV dapat dibuka di Excel/Google Sheets.

## 9. PENGUJIAN EDIT & HAPUS TUGAS / PENGUMUMAN
*   [ ] **Langkah**: Masuk sebagai **Asprak**, edit tugas yang telah dibuat. Coba hapus salah satu file lampiran bawaan dengan menekan ikon tong sampah pada lampiran saat mode edit.
*   [ ] **Ekspektasi**: File lampiran berhasil terhapus seketika dan terhapus dari penyimpanan fisik server.
*   [ ] **Langkah**: Hapus seluruh tugas melalui tombol **"Delete Assignment"**.
*   [ ] **Ekspektasi**: Tugas terhapus bersamaan dengan seluruh file fisik tugas dan file pengumpulan mahasiswa yang bersangkutan (tidak menyisakan file sampah di server).
