# Dummy Data Summary - Comprehensive Testing Version

## Overview

Skrip `add_data_dummy.php` yang diperbarui menciptakan data dummy yang komprehensif untuk testing berbagai skenario dalam sistem PrakChek. Data ini dirancang untuk menguji:

1. **Berbagai tipe pengguna** dengan karakteristik unik
2. **Multiple assignment types** dengan requirement berbeda
3. **Variasi status submission** yang mencerminkan kondisi nyata
4. **Testing file upload** dengan format berbeda
5. **Scenario-based testing** untuk fitur utama sistem

## Struktur Data yang Diciptakan

### 1. Asisten Praktikum (3)
Setiap asisten memiliki karakteristik mengajar yang berbeda:

| Asisten | Email | Kelas | Gaya Mengajar | Fokus |
|---------|-------|-------|---------------|--------|
| Asprak Ilmu Data | `asprak1@prakchek.local` | Praktikum Basis Data | Structured | Theory |
| Asprak Jaringan | `asprak2@prakchek.local` | Praktikum Jaringan Komputer | Practical | Hands-on |
| Asprak Web | `asprak3@prakchek.local` | Praktikum Pemrograman Web | Creative | Project |

### 2. Mahasiswa (15)
5 mahasiswa per kelas dengan **profil unik**:

| Profil | Karakteristik | Performa | Perilaku Submission |
|--------|---------------|----------|---------------------|
| **Active** | Rajin, tepat waktu | Excellent | Submit on time dengan kualitas tinggi |
| **Average** | Biasa saja | Good | Basic submission, kadang terlambat |
| **Perfectionist** | Detail-oriented | Excellent | Multiple files, dokumentasi lengkap |
| **Procrastinator** | Menunda-nunda | Average | Sering draft, jarang submit tepat waktu |
| **Struggling** | Kesulitan belajar | Poor | Minimal submission, sering terlambat |

**Daftar Mahasiswa:**
- Kelas 1: `Ahmad Student1`, `Budi Student2`, `Citra Student3`, `Dewi Student4`, `Eko Student5`
- Kelas 2: `Ahmad Student6`, `Budi Student7`, `Citra Student8`, `Dewi Student9`, `Eko Student10`
- Kelas 3: `Ahmad Student11`, `Budi Student12`, `Citra Student13`, `Dewi Student14`, `Eko Student15`

**Email:** `student1@prakchek.local` sampai `student15@prakchek.local`
**Password semua pengguna:** `password123`

### 3. Kelas (3)
Setiap kelas memiliki fokus dan materi yang berbeda:

1. **Praktikum Basis Data** - Fokus teori, structured approach
2. **Praktikum Jaringan Komputer** - Fokus hands-on, practical approach  
3. **Praktikum Pemrograman Web** - Fokus project, creative approach

### 4. Pengumuman (8 total)
Setiap kelas memiliki 2-3 pengumuman dengan lampiran berbeda:

**Pengumuman 1:** Welcome message tanpa lampiran
**Pengumuman 2:** Materi dengan lampiran sesuai fokus kelas:
- Basis Data: PDF syllabus + DOCX schedule
- Jaringan: TXT instructions + JPG diagram
- Web: DOCX guidelines + PHP sample code

### 5. Tugas (12 total - 4 per kelas)
Setiap kelas memiliki 4 jenis tugas berbeda:

#### Tugas 1: Individual Assignment
- **Tipe:** `accept late` (deadline_type = 2)
- **Deadline:** 1 minggu yang lalu
- **Kategori:** Individual
- **Max files:** 3
- **Allowed types:** PDF, DOCX, TXT
- **Deskripsi berbeda per kelas**

#### Tugas 2: Group Project
- **Tipe:** `strict` (deadline_type = 3)
- **Deadline:** 2 minggu ke depan
- **Kategori:** Group
- **Max files:** 5
- **Allowed types:** All
- **Deskripsi:** Project akhir berkelompok

#### Tugas 3: Quiz/Exam
- **Tipe:** `strict` (deadline_type = 3)
- **Deadline:** 3 hari ke depan
- **Kategori:** Exam
- **Max files:** 1
- **Allowed types:** TXT
- **Deskripsi:** Kuis tengah semester

#### Tugas 4: Optional Bonus
- **Tipe:** `no deadline` (deadline_type = 1)
- **Deadline:** None
- **Kategori:** Optional
- **Max files:** 2
- **Allowed types:** PDF, DOCX
- **Deskripsi:** Tugas tambahan untuk nilai bonus

### 6. Submissions (40+ total)
Kombinasi submission yang menguji berbagai skenario:

#### Untuk Tugas 1 (Individual):
- **Active student:** Submitted on time dengan PDF attachment
- **Average student:** Submitted late tanpa attachment
- **Perfectionist:** Submitted on time dengan 3 attachments (DOCX, PDF, JPG)
- **Procrastinator:** Masih draft
- **Struggling:** Submitted very late dengan konten minimal

#### Untuk Tugas 2 (Group Project):
- **Active & Perfectionist:** Submitted proposal dan progress report
- **Average student:** Masih draft
- **Lainnya:** Belum submit

#### Untuk Tugas 3 (Quiz):
- **4 siswa pertama:** Submitted tepat waktu
- **Struggling student:** Submitted terlambat (karena masalah teknis)

#### Untuk Tugas 4 (Optional):
- **Active & Perfectionist:** Submitted untuk nilai bonus
- **Lainnya:** Tidak submit (opsional)

### 7. File Dummy
File asli untuk testing upload/download:

| File | Format | Ukuran | Penggunaan |
|------|--------|--------|------------|
| `sample_report.pdf` | PDF | ~13KB | Laporan umum |
| `sample_image.jpg` | JPG | ~72KB | Diagram/gambar |
| `report_basis_data.docx` | DOCX | - | Laporan basis data |
| `report_jaringan.docx` | DOCX | - | Laporan jaringan |
| `report_web.docx` | DOCX | - | Laporan web programming |
| `instructions.txt` | TXT | - | Panduan praktikum |
| `sample_code.php` | PHP | - | Contoh kode |

## Skenario Testing yang Dicover

### 1. Testing User Roles
- [x] Asprak membuat kelas, pengumuman, tugas
- [x] Mahasiswa join kelas, submit tugas
- [x] Perbedaan perilaku berdasarkan profil

### 2. Testing Assignment Types
- [x] Individual vs Group assignments
- [x] Strict vs Accept-late vs No-deadline
- [x] Different file requirements and limits
- [x] Category-based filtering

### 3. Testing Submission Status
- [x] Submitted on time
- [x] Submitted late
- [x] Draft status
- [x] No submission
- [x] Multiple file submissions

### 4. Testing File Handling
- [x] Upload berbagai format (PDF, DOCX, JPG, TXT, PHP)
- [x] Multiple file upload
- [x] File size handling
- [x] Download functionality
- [x] File type validation

### 5. Testing Deadline Scenarios
- [x] Tugas dengan deadline ketat
- [x] Tugas yang menerima terlambat
- [x] Tugas tanpa deadline
- [x] Grace period handling
- [x] Late submission marking

### 6. Testing Plagiarism Check
- [x] File dengan konten berbeda untuk testing similarity
- [x] Text content untuk text-based plagiarism check
- [x] Multiple submissions dengan konten serupa

### 7. Testing Grade Management
- [x] Different performance levels
- [x] Bonus assignment completion
- [x] Late penalty scenarios
- [x] Group vs individual grading

## Cara Menggunakan

1. **Reset database:** Jalankan `reset_database.php`
2. **Create dummy data:** Jalankan `add_data_dummy.php`
3. **Login credentials:**
   - Asprak: `asprak1@prakchek.local` / `password123`
   - Mahasiswa: `student1@prakchek.local` / `password123`
4. **Testing scenarios:** Gunakan kombinasi user di atas untuk test berbagai fitur

## Catatan Testing

### Untuk Testing Plagiarism:
- File `report_basis_data.docx`, `report_jaringan.docx`, `report_web.docx` memiliki konten berbeda
- Cocok untuk testing similarity detection antar submission
- Text content dalam submission juga bervariasi

### Untuk Testing UI/UX:
- Different student profiles create varied dashboard views
- Multiple assignment types test filtering and sorting
- Various submission statuses test status indicators

### Untuk Testing Performance:
- 40+ submissions test pagination and loading
- Multiple file attachments test upload/download performance
- Different file sizes test handling capabilities

## Struktur Database yang Terisi

| Table | Jumlah Entri | Keterangan |
|-------|--------------|------------|
| `users` | 18 | 3 asprak + 15 mahasiswa |
| `classes` | 3 | Satu per asprak |
| `class_members` | 15 | 5 mahasiswa per kelas |
| `announcements` | 8 | 2-3 per kelas dengan lampiran |
| `assignments` | 12 | 4 jenis tugas per kelas |
| `submissions` | 40+ | Variasi status dan attachment |
| `files` | 30+ | Lampiran pengumuman dan submission |

## Update Terakhir
- **Tanggal:** 2026-05-24
- **Versi:** 2.0 (Comprehensive Testing)
- **Fitur:** Added student profiles, varied assignment types, real file testing
- **Tujuan:** Comprehensive testing of all system features with realistic scenarios

## Troubleshooting
1. Jika file dummy tidak terdownload: Script akan create fallback files
2. Jika database error: Pastikan `reset_database.php` dijalankan terlebih dahulu
3. Jika permission issues: Pastikan folder `uploads/` dan `dummy_files/` writable

Data ini siap untuk testing menyeluruh semua fitur PrakChek dengan skenario yang mendekati kondisi penggunaan nyata.