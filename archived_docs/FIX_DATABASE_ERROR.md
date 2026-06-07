# Perbaikan Error "Database Error" pada Check Plagiarism

## Masalah yang Ditemukan

Ketika menekan tombol "Check Plagiarism" muncul error **"Database error"** karena:

1. **Tabel `assignments` tidak memiliki kolom yang diperlukan:**
   - `max_grade` - untuk menyimpan nilai maksimal tugas
   - `plagiarism_rules` - untuk menyimpan aturan plagiarisme dalam format JSON
   - `allowed_formats` - untuk menyimpan format file yang diizinkan

2. **File `check_plagiarism.php` (baris 21)** mencoba mengambil kolom-kolom tersebut:
   ```php
   SELECT a.title, a.max_grade, a.plagiarism_rules, c.id as class_id, c.assistant_id 
   FROM assignments a
   ```

3. **Ketika kolom tidak ada**, MySQL mengembalikan error yang ditangkap di baris 57-60:
   ```php
   } catch (PDOException $e) {
       setFlashMessage('danger', 'Database error.');
       header("Location: assignment.php?id=$assignmentId");
       exit;
   }
   ```

## Solusi

Ada 2 cara untuk memperbaiki masalah ini:

### Cara 1: Jalankan Migration (Untuk Database yang Sudah Ada)

Jika Anda sudah memiliki data di database dan tidak ingin menghapusnya:

1. Buka browser dan akses:
   ```
   http://localhost/prakchek_/fix_assignments_table.php
   ```

2. Script akan otomatis menambahkan kolom-kolom yang hilang ke tabel `assignments`

3. Setelah selesai, Anda akan melihat pesan sukses

### Cara 2: Reset Database (Untuk Database Baru/Testing)

Jika Anda tidak keberatan menghapus semua data:

1. Buka browser dan akses:
   ```
   http://localhost/prakchek_/reset_database.php
   ```

2. Database akan dibuat ulang dengan struktur yang benar (sudah diperbaiki di `install.sql`)

## Verifikasi Perbaikan

Setelah menjalankan salah satu cara di atas:

1. Login sebagai Assistant
2. Buka salah satu kelas
3. Buka salah satu assignment
4. Klik tombol **"Check Plagiarism (PDF/Word)"**
5. Seharusnya tidak ada error lagi dan halaman plagiarism checker terbuka dengan normal

## Kolom yang Ditambahkan

| Kolom | Tipe | Default | Keterangan |
|-------|------|---------|------------|
| `allowed_formats` | VARCHAR(255) | 'all' | Format file yang diizinkan (text, link, pdf, word) |
| `max_grade` | INT | 100 | Nilai maksimal untuk tugas ini |
| `plagiarism_rules` | JSON | NULL | Aturan plagiarisme dalam format JSON array |

## Contoh Data `plagiarism_rules`

```json
[
  {
    "similarity": 30,
    "penalty": 10
  },
  {
    "similarity": 60,
    "penalty": 30
  }
]
```

Artinya:
- Jika kemiripan > 30%, potong 10 poin
- Jika kemiripan > 60%, potong 30 poin

## File yang Telah Diperbaiki

1. ✅ `install.sql` - Ditambahkan 3 kolom baru di tabel assignments
2. ✅ `fix_assignments_table.php` - Script migration untuk database yang sudah ada

## Catatan Penting

- Pastikan XAMPP MySQL sudah berjalan sebelum menjalankan migration
- Backup database Anda sebelum menjalankan migration jika ada data penting
- Setelah perbaikan, fitur Check Plagiarism akan berfungsi normal
