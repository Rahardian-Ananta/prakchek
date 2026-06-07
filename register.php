<?php
$pageTitle = 'Register';
require_once 'includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /prakchek_/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? '';

        $secretConfig = @include __DIR__ . '/config/secret.php';
        $correctSecret = is_array($secretConfig) && isset($secretConfig['assistant_secret']) 
            ? $secretConfig['assistant_secret'] 
            : 'PRAKCHEK_ASPRAK_2026';

        if (empty($name) || empty($email) || empty($password) || empty($confirmPassword) || empty($role)) {
            $error = 'Please fill in all fields';
        } elseif (!in_array($role, ['assistant', 'student'])) {
            $error = 'Invalid role selected';
        } elseif ($role === 'assistant' && ($_POST['assistant_secret'] ?? '') !== $correctSecret) {
            $error = 'Invalid Assistant Secret Code. Contact administrator.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);

                if ($stmt->fetchColumn() > 0) {
                    $error = 'Email already registered';
                } else {
                    // Insert new user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hashedPassword, $role]);

                    $success = 'Registration successful! You can now login.';
                }
            } catch (PDOException $e) {
                $error = 'Database error occurred';
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="fas fa-user-plus fa-3x text-primary"></i>
                    <h3 class="mt-3">Create Account</h3>
                    <p class="text-muted">Register for PrakCheck LMS</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= e($success) ?>
                    <a href="/prakchek_/login.php" class="alert-link">Click here to login</a>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" required autofocus value="<?= e($_POST['name'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Register as</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="role" id="role_assistant" value="assistant" <?= ($_POST['role'] ?? '') === 'assistant' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="role_assistant">
                                    <i class="fas fa-chalkboard-teacher"></i> Assistant
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="role" id="role_student" value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="role_student">
                                    <i class="fas fa-user-graduate"></i> Student
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 d-none" id="assistant_secret_container">
                        <label for="assistant_secret" class="form-label fw-bold text-danger">Assistant Secret Code</label>
                        <input type="password" class="form-control border-danger" id="assistant_secret" name="assistant_secret" placeholder="Masukkan kode rahasia asisten">
                        <small class="text-muted">Diperlukan untuk mendaftar sebagai asisten kelas.</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </form>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const roleAssistant = document.getElementById('role_assistant');
                    const roleStudent = document.getElementById('role_student');
                    const secretContainer = document.getElementById('assistant_secret_container');
                    const secretInput = document.getElementById('assistant_secret');

                    function toggleSecretCode() {
                        if (roleAssistant.checked) {
                            secretContainer.classList.remove('d-none');
                            secretInput.setAttribute('required', 'required');
                        } else {
                            secretContainer.classList.add('d-none');
                            secretInput.removeAttribute('required');
                            secretInput.value = '';
                        }
                    }

                    roleAssistant.addEventListener('change', toggleSecretCode);
                    roleStudent.addEventListener('change', toggleSecretCode);

                    // Initial check
                    toggleSecretCode();
                });
                </script>

                <div class="text-center mt-3">
                    <p class="text-muted">Already have an account? <a href="/prakchek_/login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
