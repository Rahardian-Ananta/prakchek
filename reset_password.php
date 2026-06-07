<?php
$pageTitle = 'Reset Password';
require_once 'includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$tokenValid = false;

$email = isset($_GET['email']) ? trim($_GET['email']) : (isset($_POST['email']) ? trim($_POST['email']) : '');
$token = isset($_GET['token']) ? trim($_GET['token']) : (isset($_POST['token']) ? trim($_POST['token']) : '');

if (empty($email) || empty($token)) {
    $error = 'Link reset password tidak lengkap atau tidak valid.';
} else {
    try {
        // Hash the incoming raw token to compare with the stored SHA-256 hash
        $tokenHash = hash('sha256', $token);

        // Verify if token exists and hasn't expired yet
        $stmt = $pdo->prepare("
            SELECT * FROM password_resets 
            WHERE email = ? AND token_hash = ? AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$email, $tokenHash]);
        $resetRecord = $stmt->fetch();

        if (!$resetRecord) {
            $error = 'Tautan reset password tidak valid, sudah digunakan, atau sudah kedaluwarsa (berlaku maksimal 1 jam).';
        } else {
            $tokenValid = true;
        }
    } catch (PDOException $e) {
        $error = 'Database error occurred.';
    }
}

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($password) || empty($confirmPassword)) {
            $error = 'Semua field wajib diisi.';
        } elseif (strlen($password) < 6) {
            $error = 'Password harus minimal 6 karakter.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Update the user's password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashedPassword, $email]);

                // 2. Delete all reset records for this email (invalidating them all)
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);

                $pdo->commit();
                
                setFlashMessage('success', 'Password Anda berhasil diperbarui! Silakan masuk dengan password baru Anda.');
                header('Location: login.php');
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Gagal memperbarui password di database.';
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow border-0">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="fas fa-lock-open fa-3x text-success"></i>
                    <h3 class="mt-3">Reset Password</h3>
                    <p class="text-muted">Masukkan password baru untuk akun Anda</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger small" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($tokenValid): ?>
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <input type="hidden" name="email" value="<?= e($email) ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="mb-3">
                            <label for="password" class="form-label fw-bold">Password Baru</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-key text-muted"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="Minimal 6 karakter" required minlength="6">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-bold">Konfirmasi Password Baru</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-key text-muted"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0" id="confirm_password" name="confirm_password" placeholder="Ulangi password baru" required minlength="6">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                            <i class="fas fa-save me-1"></i> Simpan Password Baru
                        </button>
                    </form>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <p class="mb-0 text-muted"><a href="login.php" class="text-primary fw-bold text-decoration-none">Kembali ke halaman Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
