<?php
$pageTitle = 'Login';
require_once 'includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /prakchek_/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    loginUser($user['id'], $user['name'], $user['role']);
                    header('Location: /prakchek_/dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid email or password';
                }
            } catch (PDOException $e) {
                $error = 'Database error occurred';
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="fas fa-graduation-cap fa-3x text-primary"></i>
                    <h3 class="mt-3">PrakCheck</h3>
                    <p class="text-muted">Login to your account</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label for="password" class="form-label mb-0">Password</label>
                            <a href="forgot_password.php" class="text-decoration-none small">Lupa password?</a>
                        </div>
                        <input type="password" class="form-control mt-1" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>

                <div class="text-center mt-3">
                    <p class="text-muted">Don't have an account? <a href="/prakchek_/register.php">Register here</a></p>
                </div>

                <hr class="my-4">

                <div class="text-center">
                    <small class="text-muted">
                        <strong>Demo Accounts:</strong><br>
                        Assistant: john@assistant.com<br>
                        Student: jane@student.com<br>
                        Password: password123
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
