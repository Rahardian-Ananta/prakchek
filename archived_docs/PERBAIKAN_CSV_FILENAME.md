# ✅ PERBAIKAN CSV FILENAME - Export Grades

**Tanggal:** 25 Mei 2026  
**Status:** ✅ BERHASIL DIPERBAIKI

---

## 📋 Masalah yang Ditemukan

Nama file CSV yang di-export memiliki **double underscore** yang tidak rapi:

```
❌ Nilai_Tugas_Tugas_1__Analisis_Kasus_Database_20260525.csv
                      ^^
                (double underscore)
```

### Penyebab:
File `export_grades.php` menggunakan regex sederhana yang mengubah **semua karakter non-alphanumeric** (termasuk spasi dan tanda baca) menjadi underscore:

```php
// Kode lama (baris 94):
preg_replace('/[^a-zA-Z0-9_]/', '_', $assignment['title'])
```

Ketika judul tugas adalah `"Tugas 1: Analisis Kasus Database"`:
- Spasi → `_`
- Titik dua `:` → `_`
- Hasilnya: `Tugas_1__Analisis_Kasus_Database` (double underscore)

---

## 🛠️ Solusi yang Diterapkan

### Algoritma Pembersihan Nama File (4 Langkah):

```php
// 1. Ubah semua spasi menjadi underscore
$cleanTitle = preg_replace('/\s+/', '_', $title);

// 2. Hapus semua karakter khusus (bukan huruf/angka/underscore)
$cleanTitle = preg_replace('/[^a-zA-Z0-9_]/', '', $cleanTitle);

// 3. Collapse multiple underscores menjadi satu
$cleanTitle = preg_replace('/_+/', '_', $cleanTitle);

// 4. Hapus underscore di awal/akhir
$cleanTitle = trim($cleanTitle, '_');
```

### Hasil:
```
✅ Nilai_Tugas_Tugas_1_Analisis_Kasus_Database_20260525.csv
                      ^
              (single underscore, rapi!)
```

---

## ✅ Test Results

| Input Title | Output Filename | Status |
|-------------|-----------------|--------|
| `Tugas 1: Analisis Kasus Database` | `Nilai_Tugas_Tugas_1_Analisis_Kasus_Database_20260525.csv` | ✅ PASS |
| `Tugas 2 - Pemrograman Web` | `Nilai_Tugas_Tugas_2_Pemrograman_Web_20260525.csv` | ✅ PASS |
| `Quiz #1 (Mid-Term)` | `Nilai_Tugas_Quiz_1_MidTerm_20260525.csv` | ✅ PASS |
| `Final Project: E-Commerce` | `Nilai_Tugas_Final_Project_ECommerce_20260525.csv` | ✅ PASS |
| `Tugas    dengan    spasi    banyak` | `Nilai_Tugas_Tugas_dengan_spasi_banyak_20260525.csv` | ✅ PASS |
| `Tugas!!!@@@###$$$` | `Nilai_Tugas_Tugas_20260525.csv` | ✅ PASS |

**Semua test PASSED!** ✅

---

## 📁 File yang Dimodifikasi

| File | Baris | Perubahan |
|------|-------|-----------|
| `export_grades.php` | 94-99 | Perbaikan algoritma cleaning untuk export assignment |
| `export_grades.php` | 179-184 | Perbaikan algoritma cleaning untuk export class recap |

---

## 🎯 Fitur yang Terpengaruh

### 1. Export Nilai Per Tugas
**URL:** `export_grades.php?type=assignment&id=X`

**Sebelum:**
```
Nilai_Tugas_Tugas_1__Analisis_Kasus_Database_20260525.csv
```

**Sesudah:**
```
Nilai_Tugas_Tugas_1_Analisis_Kasus_Database_20260525.csv
```

### 2. Export Rekap Nilai Kelas
**URL:** `export_grades.php?type=class&id=X`

**Sebelum:**
```
Rekap_Nilai_Kelas_Basis__Data__2024_20260525.csv
```

**Sesudah:**
```
Rekap_Nilai_Kelas_Basis_Data_2024_20260525.csv
```

---

## 📊 Perbandingan Sebelum vs Sesudah

### Contoh 1: Judul dengan Tanda Baca
```
Input: "Tugas 1: Analisis Kasus Database"

❌ Sebelum: Nilai_Tugas_Tugas_1__Analisis_Kasus_Database_20260525.csv
✅ Sesudah: Nilai_Tugas_Tugas_1_Analisis_Kasus_Database_20260525.csv
```

### Contoh 2: Judul dengan Spasi Banyak
```
Input: "Tugas    dengan    spasi    banyak"

❌ Sebelum: Nilai_Tugas_Tugas____dengan____spasi____banyak_20260525.csv
✅ Sesudah: Nilai_Tugas_Tugas_dengan_spasi_banyak_20260525.csv
```

### Contoh 3: Judul dengan Karakter Khusus
```
Input: "Quiz #1 (Mid-Term)"

❌ Sebelum: Nilai_Tugas_Quiz__1___Mid_Term__20260525.csv
✅ Sesudah: Nilai_Tugas_Quiz_1_MidTerm_20260525.csv
```

---

## 🔍 Detail Teknis

### Regex yang Digunakan:

1. **`/\s+/`** - Match satu atau lebih whitespace (spasi, tab, newline)
2. **`/[^a-zA-Z0-9_]/`** - Match karakter yang BUKAN huruf, angka, atau underscore
3. **`/_+/`** - Match satu atau lebih underscore berturut-turut
4. **`trim($str, '_')`** - Hapus underscore di awal dan akhir string

### Urutan Penting:
Urutan operasi sangat penting! Jika dibalik, hasilnya bisa berbeda:

```php
// ✅ BENAR (urutan seperti di kode):
"Tugas 1: Test" 
→ "Tugas_1:_Test"      (spasi → underscore)
→ "Tugas_1_Test"       (hapus :)
→ "Tugas_1_Test"       (collapse __)
→ "Tugas_1_Test"       (trim)

// ❌ SALAH (jika hapus karakter khusus dulu):
"Tugas 1: Test"
→ "Tugas 1 Test"       (hapus :)
→ "Tugas_1_Test"       (spasi → underscore)
→ "Tugas_1_Test"       (collapse)
→ "Tugas_1_Test"       (trim)
// Kebetulan sama, tapi tidak konsisten untuk semua kasus
```

---

## ✅ Status Akhir

**MASALAH TERSELESAIKAN** ✅

Nama file CSV export sekarang:
- ✅ Tidak ada double underscore
- ✅ Lebih rapi dan konsisten
- ✅ Mudah dibaca
- ✅ Compatible dengan semua OS (Windows, Mac, Linux)

---

## 📝 Cara Testing

1. Login sebagai Assistant
2. Buka salah satu assignment
3. Klik tombol **"Export Nilai (CSV)"**
4. Periksa nama file yang terdownload
5. Seharusnya tidak ada double underscore lagi

Atau jalankan test script:
```
http://localhost/prakchek_/test_csv_filename.php
```

---

**Diperbaiki oleh:** Claude (Kiro AI)  
**Tanggal:** 25 Mei 2026, 10:02 WIB
