# ✅ PERBAIKAN LENGKAP - Export CSV Siap Pakai

**Tanggal:** 25 Mei 2026  
**Status:** ✅ SELESAI - SIAP PAKAI

---

## 📋 Ringkasan Perbaikan

File `export_grades.php` telah diperbaiki secara menyeluruh untuk menghasilkan CSV yang:
- ✅ Nama file rapi tanpa double underscore
- ✅ Kompatibel dengan Excel (UTF-8 BOM)
- ✅ Kolom lebih lengkap dan informatif
- ✅ Kode lebih terstruktur dan maintainable

---

## 🎯 Perbaikan yang Diterapkan

### 1. **Fungsi cleanFilename() - Pembersihan Nama File**

```php
function cleanFilename($text) {
    // 1. Replace spaces with underscore
    $clean = preg_replace('/\s+/', '_', $text);
    
    // 2. Remove special characters except underscore
    $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $clean);
    
    // 3. Collapse multiple underscores to single
    $clean = preg_replace('/_+/', '_', $clean);
    
    // 4. Remove leading/trailing underscores
    $clean = trim($clean, '_');
    
    // 5. Limit length to 100 characters
    if (strlen($clean) > 100) {
        $clean = substr($clean, 0, 100);
    }
    
    return $clean;
}
```

**Hasil:**
```
Input:  "Tugas 1: Analisis Kasus Database"
Output: "Tugas_1_Analisis_Kasus_Database"

Input:  "Quiz #1 (Mid-Term)"
Output: "Quiz_1_MidTerm"
```

### 2. **UTF-8 BOM untuk Kompatibilitas Excel**

```php
function outputCSV($filename, $headers, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}
```

**Manfaat:**
- Excel akan otomatis mengenali encoding UTF-8
- Karakter Indonesia (é, ñ, dll) tampil dengan benar
- Tidak perlu import manual di Excel

### 3. **Kolom Baru di Export Assignment**

| Kolom Lama | Kolom Baru | Keterangan |
|------------|------------|------------|
| Nama Mahasiswa | Nama Mahasiswa | Sama |
| Email | Email | Sama |
| Status | Status | Sama |
| - | **Terlambat** | ✅ BARU - Ya/Tidak |
| Waktu Pengumpulan | Waktu Pengumpulan | Sama |
| Nilai | **Nilai Asli** | ✅ BARU - Sebelum potongan |
| Potongan Plagiasi | Potongan Plagiasi | Sama |
| - | **Nilai Akhir** | ✅ BARU - Setelah potongan |
| Feedback | Feedback | Sama |

**Contoh Data:**
```csv
Nama,Email,Status,Terlambat,Waktu,Nilai Asli,Potongan,Nilai Akhir,Feedback
Budi,budi@mail.com,Sudah Mengumpulkan,Ya,2026-05-20 10:30:00,85,10,75,Bagus tapi terlambat
```

### 4. **Kolom Baru di Export Class Recap**

| Kolom Lama | Kolom Baru | Keterangan |
|------------|------------|------------|
| - | **No** | ✅ BARU - Nomor urut |
| Nama Mahasiswa | Nama Mahasiswa | Sama |
| Email | Email | Sama |
| [Tugas 1] | [Tugas 1] | Sama |
| [Tugas 2] | [Tugas 2] | Sama |
| Total Nilai | Total Nilai | Sama |
| - | **Total Maksimal** | ✅ BARU - Total max semua tugas |
| Rata-rata (%) | Rata-rata (%) | Sama |

**Contoh Data:**
```csv
No,Nama,Email,Tugas1(100),Tugas2(100),Total,Total Maksimal,Rata-rata
1,Budi,budi@mail.com,85,90,175,200,87.5%
2,Ani,ani@mail.com,90,95,185,200,92.5%
```

---

## 📊 Perbandingan Sebelum vs Sesudah

### Export Assignment (Per Tugas)

#### ❌ Sebelum:
```
Nama File: Nilai_Tugas_Tugas_1__Analisis_Kasus_Database_20260525.csv
                              ^^
                        (double underscore)

Kolom: 7 kolom
- Nama, Email, Status, Waktu, Nilai, Potongan, Feedback

Masalah:
- Double underscore di nama file
- Tidak ada info keterlambatan
- Tidak jelas nilai asli vs nilai akhir
- Excel tidak bisa baca UTF-8 dengan benar
```

#### ✅ Sesudah:
```
Nama File: Nilai_Tugas_Tugas_1_Analisis_Kasus_Database_20260525.csv
                              ^
                      (single underscore)

Kolom: 9 kolom
- Nama, Email, Status, Terlambat, Waktu, Nilai Asli, Potongan, Nilai Akhir, Feedback

Perbaikan:
- Single underscore (rapi)
- Ada kolom "Terlambat" (Ya/Tidak)
- Jelas: Nilai Asli vs Nilai Akhir
- UTF-8 BOM untuk Excel
```

### Export Class Recap (Rekap Kelas)

#### ❌ Sebelum:
```
Nama File: Rekap_Nilai_Kelas_Basis__Data__2024_20260525.csv
                                  ^^    ^^
                          (double underscore)

Kolom: Nama, Email, [Tugas1], [Tugas2], ..., Total, Rata-rata

Masalah:
- Double underscore di nama file
- Tidak ada nomor urut
- Tidak ada total maksimal (sulit hitung persentase manual)
```

#### ✅ Sesudah:
```
Nama File: Rekap_Nilai_Kelas_Basis_Data_2024_20260525.csv
                                  ^    ^
                          (single underscore)

Kolom: No, Nama, Email, [Tugas1], [Tugas2], ..., Total, Total Maksimal, Rata-rata

Perbaikan:
- Single underscore (rapi)
- Ada nomor urut
- Ada total maksimal (mudah verifikasi perhitungan)
```

---

## ✅ Test Results

### Test 1: Fungsi cleanFilename()
| Input | Output | Status |
|-------|--------|--------|
| `Tugas 1: Analisis Kasus Database` | `Tugas_1_Analisis_Kasus_Database` | ✅ PASS |
| `Quiz #1 (Mid-Term)` | `Quiz_1_MidTerm` | ✅ PASS |
| `Final Project: E-Commerce` | `Final_Project_ECommerce` | ✅ PASS |
| `Tugas    dengan    spasi    banyak` | `Tugas_dengan_spasi_banyak` | ✅ PASS |
| `Tugas!!!@@@###$$$` | `Tugas` | ✅ PASS |
| `Basis Data 2024` | `Basis_Data_2024` | ✅ PASS |

**Semua test PASSED!** ✅

### Test 2: Format Nama File
| Tipe | Cek | Status |
|------|-----|--------|
| Assignment | Tidak ada double underscore | ✅ PASS |
| Assignment | Format tanggal YYYYMMDD | ✅ PASS |
| Assignment | Ekstensi .csv | ✅ PASS |
| Class | Tidak ada double underscore | ✅ PASS |
| Class | Format tanggal YYYYMMDD | ✅ PASS |
| Class | Ekstensi .csv | ✅ PASS |

**Semua test PASSED!** ✅

---

## 🎯 Fitur Lengkap

### 1. Export Nilai Per Tugas
**URL:** `export_grades.php?type=assignment&id=X`

**Kolom yang Di-export:**
1. Nama Mahasiswa
2. Email
3. Status (Belum Mengumpulkan / Draft / Sudah Mengumpulkan)
4. **Terlambat** (Ya / Tidak)
5. Waktu Pengumpulan
6. **Nilai Asli** (sebelum potongan)
7. Potongan Plagiasi
8. **Nilai Akhir** (setelah potongan)
9. Feedback

**Nama File:**
```
Nilai_Tugas_[JudulTugas]_[YYYYMMDD].csv
```

### 2. Export Rekap Nilai Kelas
**URL:** `export_grades.php?type=class&id=X`

**Kolom yang Di-export:**
1. **No** (nomor urut)
2. Nama Mahasiswa
3. Email
4. [Tugas 1] (max: X)
5. [Tugas 2] (max: X)
6. ... (semua tugas)
7. Total Nilai
8. **Total Maksimal**
9. Rata-rata (%)

**Nama File:**
```
Rekap_Nilai_Kelas_[NamaKelas]_[YYYYMMDD].csv
```

---

## 🔧 Detail Teknis

### Algoritma cleanFilename()

**5 Langkah Pembersihan:**

1. **Spasi → Underscore**
   ```
   "Tugas 1: Test" → "Tugas_1:_Test"
   ```

2. **Hapus Karakter Khusus**
   ```
   "Tugas_1:_Test" → "Tugas_1_Test"
   ```

3. **Collapse Multiple Underscores**
   ```
   "Tugas__1___Test" → "Tugas_1_Test"
   ```

4. **Trim Underscore**
   ```
   "_Tugas_Test_" → "Tugas_Test"
   ```

5. **Limit Panjang (100 char)**
   ```
   "VeryLongTitle..." → "VeryLongTitle...[100 chars]"
   ```

### UTF-8 BOM

**Byte Order Mark (BOM):**
```php
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
```

**Hexadecimal:** `EF BB BF`

**Fungsi:**
- Memberitahu Excel bahwa file ini UTF-8
- Karakter Indonesia tampil dengan benar
- Tidak perlu "Import Data" manual

---

## 📝 Cara Menggunakan

### Export Nilai Per Tugas:
1. Login sebagai **Assistant**
2. Buka **Dashboard** → Pilih kelas
3. Klik salah satu **Assignment**
4. Scroll ke bawah, klik tombol **"Export Nilai (CSV)"**
5. File akan terdownload otomatis

### Export Rekap Nilai Kelas:
1. Login sebagai **Assistant**
2. Buka **Dashboard** → Pilih kelas
3. Di halaman kelas, klik ikon **Export** atau tombol **"Export Rekap Nilai"**
4. File akan terdownload otomatis

### Membuka di Excel:
1. Double-click file CSV
2. Excel akan otomatis membuka dengan encoding yang benar
3. Semua karakter Indonesia tampil sempurna
4. Tidak perlu import manual

---

## 🧪 Testing Manual

Untuk memverifikasi perbaikan:

1. **Test Nama File:**
   - Buat assignment dengan judul: `"Tugas 1: Analisis Kasus Database"`
   - Export CSV
   - Nama file harus: `Nilai_Tugas_Tugas_1_Analisis_Kasus_Database_20260525.csv`
   - ✅ Tidak ada double underscore

2. **Test Kolom Baru:**
   - Export assignment yang ada mahasiswa terlambat
   - Buka CSV, cek kolom "Terlambat" ada isinya (Ya/Tidak)
   - Cek kolom "Nilai Asli" dan "Nilai Akhir" berbeda jika ada potongan plagiasi

3. **Test UTF-8:**
   - Buat mahasiswa dengan nama: `José María`
   - Export CSV
   - Buka di Excel
   - ✅ Nama harus tampil: `José María` (bukan `JosÃ© MarÃ­a`)

4. **Test Rekap Kelas:**
   - Export rekap kelas
   - Cek ada kolom "No" di awal
   - Cek ada kolom "Total Maksimal" sebelum "Rata-rata"

---

## ✅ Status Akhir

**PERBAIKAN SELESAI** ✅

File `export_grades.php` sekarang:
- ✅ Siap pakai tanpa kesalahan
- ✅ Nama file rapi dan profesional
- ✅ Kompatibel dengan Excel
- ✅ Kolom lebih lengkap dan informatif
- ✅ Kode terstruktur dan mudah di-maintain

---

## 📁 File yang Dibuat/Dimodifikasi

| File | Status | Keterangan |
|------|--------|------------|
| `export_grades.php` | ✅ Diperbaiki | File utama export CSV |
| `test_export_csv.php` | ✅ Dibuat | Test verification script |
| `PERBAIKAN_EXPORT_CSV_LENGKAP.md` | ✅ Dibuat | Dokumentasi lengkap (file ini) |

---

**Diperbaiki oleh:** Claude (Kiro AI)  
**Tanggal:** 25 Mei 2026, 10:06 WIB  
**Versi:** 2.0 - Production Ready
