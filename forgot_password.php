<?php
$pageTitle = 'Forgot Password';
require_once 'includes/header.php';
require_once 'includes/MailHelper.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = '';
$devLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $message = 'Invalid CSRF token';
        $messageType = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $message = 'Please enter your email address.';
            $messageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email format.';
            $messageType = 'danger';
        } else {
            // Always show the same generic success message to prevent user enumeration
            $message = 'Jika email Anda terdaftar di sistem kami, tautan untuk mereset kata sandi telah dikirim ke email Anda.';
            $messageType = 'success';

            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Generate secure token
                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    
                    // Expiry time (1 hour from now)
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Save token to database
                    $stmt = $pdo->prepare("
                        INSERT INTO password_resets (email, token_hash, expires_at)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$email, $tokenHash, $expiresAt]);

                    // Generate full reset URL dynamically
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    
                    // Determine subfolder path
                    $requestUri = $_SERVER['REQUEST_URI'];
                    $scriptName = $_SERVER['SCRIPT_NAME'];
                    $subfolder = str_replace(basename($scriptName), '', $scriptName);
                    
                    $resetLink = "{$protocol}://{$host}{$subfolder}reset_password.php?token={$rawToken}&email=" . urlencode($email);

                    // Send email using MailHelper
                    MailHelper::sendResetEmail($email, $user['name'], $resetLink);
                }
            } catch (PDOException $e) {
                // Quietly log error on dev, but don't crash
            }
        }
    }
}

// Retrieve local development flash link if exists
$devLink = '';
$devStatus = '';
if (isset($_SESSION['dev_reset_link'])) {
    $devLink = $_SESSION['dev_reset_link'];
    $devStatus = $_SESSION['dev_reset_status'] ?? 'sent_smtp';
    unset($_SESSION['dev_reset_link']);
    unset($_SESSION['dev_reset_status']);
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow border-0">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="fas fa-key fa-3x text-primary"></i>
                    <h3 class="mt-3">Lupa Password</h3>
                    <p class="text-muted">Masukkan email terdaftar untuk menerima link reset password</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> small" role="alert">
                        <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                        <?= e($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($devLink): ?>
                    <div class="alert alert-warning border-warning border-dashed p-3 mb-4 small">
                        <h6 class="alert-heading text-warning fw-bold mb-2"><i class="fas fa-bug me-1"></i> Mode Pengembang (XAMPP Lokal):</h6>
                        <?php if ($devStatus === 'sent_smtp'): ?>
                            <p class="mb-2 text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Email riil BERHASIL terkirim via Brevo SMTP!</p>
                            <p class="mb-2 text-dark small" style="opacity:0.8;">Kami tetap menampilkan tombol di bawah ini untuk memudahkan Anda melakukan testing lokal secara instan tanpa perlu membuka email asli Anda.</p>
                        <?php elseif ($devStatus === 'smtp_failed'): ?>
                            <p class="mb-2 text-danger fw-bold"><i class="fas fa-times-circle me-1"></i> SMTP Brevo GAGAL mengirim email nyata.</p>
                            <p class="mb-2 text-dark small" style="opacity:0.8;">Sistem otomatis mengaktifkan mode log lokal. Silakan klik tombol di bawah untuk testing.</p>
                        <?php else: ?>
                            <p class="mb-2 text-dark small" style="opacity:0.8;">Menggunakan mode log lokal karena key masih berupa dummy.</p>
                        <?php endif; ?>
                        <a href="<?= $devLink ?>" class="btn btn-warning btn-sm text-dark fw-bold w-100 mt-2"><i class="fas fa-external-link-alt me-1"></i> Klik Di Sini untuk Reset Password</a>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfField() ?>

                    <div class="mb-4">
                        <label for="email" class="form-label fw-bold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" placeholder="name@domain.com" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                        <i class="fas fa-paper-plane me-1"></i> Kirim Link Reset
                    </button>
                </form>

                <div class="text-center mt-4">
                    <p class="mb-0 text-muted">Ingat password Anda? <a href="login.php" class="text-primary fw-bold text-decoration-none">Login kembali</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
