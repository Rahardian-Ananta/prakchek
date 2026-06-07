<?php
/**
 * install_setup.php
 * -------------------------------------------------
 * One-click installer untuk PrakCheck LMS.
 * Membaca file `install.sql` dan mengeksekusinya lewat PDO.
 * -------------------------------------------------
 * Cara pakai:
 * 1. Buka http://localhost/prakchek_/install_setup.php
 * 2. Isi host, nama user, password (atau biarkan default).
 * 3. Pilih tipe data: Minimal atau Komprehensif
 * 4. Klik "Setup Database". Semua tabel & data akan dibuat otomatis.
 * 5. Setelah sukses, hapus file ini atau ubah permission agar tidak dapat diakses lagi.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Konfigurasi default (bisa di-override lewat form)
$defaultHost = 'localhost';
$defaultUser = 'root';
$defaultPass = '';
$defaultDb   = 'prakchek';

// ---------------------------------------------------------------------
// Tampilkan formulir jika belum disubmit
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>PrakCheck - Setup Database</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            .data-type-card {
                cursor: pointer;
                transition: all 0.3s;
                border: 2px solid transparent;
            }
            .data-type-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            }
            .data-type-card.selected {
                border-color: #0d6efd;
                background-color: #f8f9fa;
            }
            .feature-list {
                font-size: 0.9rem;
            }
            .feature-list li {
                margin-bottom: 5px;
            }
        </style>
    </head>
    <body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-tools me-2"></i> Instalasi PrakCheck LMS</h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info">
                            <strong>Penting:</strong> Skrip ini akan membuat database baru bernama <code>prakchek</code> dan menghapus data lama jika ada.
                        </div>

                        <form method="post" class="row g-3" id="installForm">
                            <div class="col-md-12 mb-3">
                                <h5><i class="fas fa-database me-2"></i> Konfigurasi Database</h5>
                                <hr>
                            </div>

                            <div class="col-md-12 mb-2">
                                <label class="form-label fw-bold">Hostname MySQL</label>
                                <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($defaultHost) ?>" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold">Username MySQL</label>
                                <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($defaultUser) ?>" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold">Password MySQL</label>
                                <input type="password" name="db_pass" class="form-control" placeholder="Kosongkan jika root tanpa password">
                            </div>

                            <div class="col-md-12 mb-3 mt-4">
                                <h5><i class="fas fa-users me-2"></i> Tipe Data Dummy</h5>
                                <hr>
                                <p class="text-muted">Pilih tipe data yang akan di-generate untuk testing:</p>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card data-type-card h-100" onclick="selectDataType('minimal')" id="cardMinimal">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-primary text-white rounded-circle p-2 me-3">
                                                        <i class="fas fa-file-alt fa-lg"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="card-title mb-0">Data Minimal</h5>
                                                        <small class="text-muted">Untuk testing dasar</small>
                                                    </div>
                                                </div>
                                                <ul class="feature-list">
                                                    <li>3 pengguna (1 asprak, 2 mahasiswa)</li>
                                                    <li>1 kelas dengan 1 pengumuman</li>
                                                    <li>Data sample dari install.sql</li>
                                                    <li>Cocok untuk quick testing</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="card data-type-card h-100 selected" onclick="selectDataType('comprehensive')" id="cardComprehensive">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-success text-white rounded-circle p-2 me-3">
                                                        <i class="fas fa-chart-bar fa-lg"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="card-title mb-0">Data Komprehensif</h5>
                                                        <small class="text-muted">Untuk testing menyeluruh</small>
                                                    </div>
                                                </div>
                                                <ul class="feature-list">
                                                    <li>60 pengguna (10 asprak, 50 mahasiswa)</li>
                                                    <li>10 kelas dengan 20 pengumuman</li>
                                                    <li>40 tugas dengan 300+ submission</li>
                                                    <li>File dummy asli (PDF, DOCX, JPG, TXT, PHP, SQL, ZIP)</li>
                                                    <li>5 profil mahasiswa berbeda</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="data_type" id="dataType" value="comprehensive">
                            </div>

                            <div class="col-12 mt-4 text-center">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="fas fa-database me-2"></i> Install / Setup Database
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectDataType(type) {
            document.getElementById('dataType').value = type;

            // Update card styles
            const cardMinimal = document.getElementById('cardMinimal');
            const cardComprehensive = document.getElementById('cardComprehensive');

            if (type === 'minimal') {
                cardMinimal.classList.add('selected');
                cardComprehensive.classList.remove('selected');
            } else {
                cardMinimal.classList.remove('selected');
                cardComprehensive.classList.add('selected');
            }
        }

        // Form submission handler
        document.getElementById('installForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memproses...';
            submitBtn.disabled = true;
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}

// ---------------------------------------------------------------------
// Proses instalasi
$dbHost = trim($_POST['db_host'] ?? $defaultHost);
$dbUser = trim($_POST['db_user'] ?? $defaultUser);
$dbPass = $_POST['db_pass'] ?? $defaultPass;
$dataType = $_POST['data_type'] ?? 'comprehensive';

// Membuat koneksi PDO ke MySQL (tanpa memilih database dulu, karena DB mungkin belum ada)
$dsn = "mysql:host=$dbHost;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die("
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body class='p-5'>
        <div class='alert alert-danger'>
            <h4 class='alert-heading'>❌ Gagal Terkoneksi ke MySQL</h4>
            <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
            <hr>
            <a href='install_setup.php' class='btn btn-outline-danger'>Kembali</a>
        </div>
    </body>
    </html>
    ");
}

// Baca file install.sql
$sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'install.sql';
if (!file_exists($sqlFile)) {
    die("<h3 class='text-danger text-center mt-5'>❌ File install.sql tidak ditemukan di root direktori.</h3>");
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    die("<h3 class='text-danger text-center mt-5'>❌ Tidak dapat membaca install.sql.</h3>");
}

// Hapus sample data dari install.sql jika memilih data komprehensif
if ($dataType === 'comprehensive') {
    $marker = '-- Sample data for testing';
    if (($pos = strpos($sql, $marker)) !== false) {
        $sql = substr($sql, 0, $pos);
    }
}

$success = false;
$errorMsg = '';
$dummyDataResult = '';

try {
    // Eksekusi seluruh blok SQL sekaligus
    $pdo->exec($sql);
    $success = true;

    // Jika memilih data komprehensif, jalankan add_data_dummy.php
    if ($dataType === 'comprehensive' && $success) {
        // Pilih database yang baru dibuat
        $pdo->exec("USE prakchek");

        // Include dan jalankan add_data_dummy_2.php
        $dummyScript = __DIR__ . DIRECTORY_SEPARATOR . 'add_data_dummy_2.php';
        if (file_exists($dummyScript)) {
            ob_start();
            try {
                // Simpan PDO instance untuk digunakan oleh add_data_dummy.php
                $GLOBALS['pdo'] = $pdo;

                // Include file
                require_once $dummyScript;

                $dummyOutput = ob_get_clean();
                $dummyDataResult = "<div class='alert alert-success mt-3'>
                    <h5><i class='fas fa-check-circle me-2'></i> Data Dummy Komprehensif Berhasil Dibuat!</h5>
                    <pre class='mt-2' style='max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px;'>" .
                    htmlspecialchars($dummyOutput) .
                    "</pre>
                </div>";
            } catch (Exception $e) {
                $dummyOutput = ob_get_clean();
                $dummyDataResult = "<div class='alert alert-warning mt-3'>
                    <h5><i class='fas fa-exclamation-triangle me-2'></i> Data Dummy Dibuat dengan Peringatan</h5>
                    <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
                    <pre class='mt-2' style='max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px;'>" .
                    htmlspecialchars($dummyOutput) .
                    "</pre>
                </div>";
            }
        } else {
            $dummyDataResult = "<div class='alert alert-warning mt-3'>
                <h5><i class='fas fa-exclamation-triangle me-2'></i> File add_data_dummy.php tidak ditemukan</h5>
                <p>Data komprehensif tidak dapat dibuat. Hanya struktur database yang diinstall.</p>
            </div>";
        }
    }

} catch (PDOException $e) {
    $success = false;
    $errorMsg = $e->getMessage();
}

// ---------------------------------------------------------------------
// Tampilkan hasil
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PrakCheck - Hasil Instalasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .login-credentials {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            border-radius: 5px;
        }
        .login-credentials h6 {
            color: #0d6efd;
        }
        .summary-box {
            background: #e8f4fd;
            border: 1px solid #b6d4fe;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .summary-box h5 {
            color: #0a58ca;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0 text-center"><i class="fas fa-clipboard-list me-2"></i> Hasil Instalasi PrakCheck</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (!$success): ?>
                        <div class="alert alert-danger">
                            <h4><i class="fas fa-times-circle me-2"></i> Instalasi Gagal!</h4>
                            <hr>
                            <p>Query error: <code><?= htmlspecialchars($errorMsg) ?></code></p>
                        </div>
                        <div class="text-center mt-4">
                            <a href="install_setup.php" class="btn btn-warning"><i class="fas fa-redo me-2"></i> Coba Lagi</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <h4><i class="fas fa-check-circle me-2"></i> Instalasi Berhasil Sempurna!</h4>
                            <p class="mb-0">Database <strong>prakchek</strong> telah dibuat beserta seluruh tabel.</p>
                            <?php if ($dataType === 'comprehensive'): ?>
                                <p class="mb-0 mt-2"><strong>Tipe Data:</strong> Komprehensif (dummy data lengkap untuk testing)</p>
                            <?php else: ?>
                                <p class="mb-0 mt-2"><strong>Tipe Data:</strong> Minimal (data sample dasar)</p>
                            <?php endif; ?>
                        </div>

                        <?php if ($dataType === 'comprehensive'): ?>
                            <div class="summary-box">
                                <h5><i class="fas fa-chart-pie me-2"></i> Data Dummy Komprehensif</h5>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-user-tie text-primary me-2"></i> <strong>3 Asprak</strong> dengan gaya mengajar berbeda</li>
                                            <li><i class="fas fa-users text-primary me-2"></i> <strong>15 Mahasiswa</strong> dengan 5 profil unik</li>
                                            <li><i class="fas fa-chalkboard-teacher text-primary me-2"></i> <strong>3 Kelas</strong> dengan fokus berbeda</li>
                                            <li><i class="fas fa-bullhorn text-primary me-2"></i> <strong>8 Pengumuman</strong> dengan lampiran</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-tasks text-primary me-2"></i> <strong>12 Tugas</strong> (4 per kelas)</li>
                                            <li><i class="fas fa-file-upload text-primary me-2"></i> <strong>40+ Submission</strong> dengan berbagai status</li>
                                            <li><i class="fas fa-file-alt text-primary me-2"></i> <strong>File asli</strong> (PDF, DOCX, JPG, TXT, PHP)</li>
                                            <li><i class="fas fa-vial text-primary me-2"></i> <strong>Testing komprehensif</strong> semua fitur</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="login-credentials mt-4">
                                <h6><i class="fas fa-key me-2"></i> Credentials untuk Testing:</h6>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <p><strong>Asprak (Admin):</strong><br>
                                        Email: <code>asprak.basisdata@prakchek.local</code><br>
                                        Password: <code>password123</code></p>
                                        <small class="text-muted">(atau asprak.rpl, asprak.web, dll)</small>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Mahasiswa:</strong><br>
                                        Email: <code>student01@prakchek.local</code><br>
                                        Password: <code>password123</code></p>
                                        <small class="text-muted">(student01 sampai student50 tersedia)</small>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="login-credentials mt-4">
                                <h6><i class="fas fa-key me-2"></i> Credentials untuk Testing:</h6>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <p><strong>Asprak (Admin):</strong><br>
                                        Email: <code>john@assistant.com</code><br>
                                        Password: <code>password123</code></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Mahasiswa:</strong><br>
                                        Email: <code>jane@student.com</code><br>
                                        Password: <code>password123</code></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php echo $dummyDataResult; ?>

                        <div class="alert alert-warning mt-4">
                            <strong><i class="fas fa-exclamation-triangle me-2"></i> Peringatan Keamanan:</strong><br>
                            Sangat disarankan untuk <strong>menghapus file <code>install_setup.php</code></strong> dari server produksi Anda agar tidak dijalankan ulang oleh orang lain!
                        </div>

                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-primary btn-lg px-5 me-3">
                                <i class="fas fa-sign-in-alt me-2"></i> Buka Halaman Login
                            </a>
                            <?php if ($dataType === 'minimal'): ?>
                                <a href="add_data_dummy.php" class="btn btn-outline-success btn-lg px-5">
                                    <i class="fas fa-plus-circle me-2"></i> Tambah Data Dummy Lengkap
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>