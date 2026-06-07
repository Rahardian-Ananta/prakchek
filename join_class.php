<?php
$pageTitle = 'Join Class';
require_once 'includes/header.php';

requireRole('student');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $classCode = strtoupper(trim($_POST['class_code'] ?? ''));

        if (empty($classCode)) {
            $error = 'Please enter a class code';
        } else {
            try {
                // Check if class exists
                $stmt = $pdo->prepare("SELECT id, name FROM classes WHERE code = ?");
                $stmt->execute([$classCode]);
                $class = $stmt->fetch();

                if (!$class) {
                    $error = 'Invalid class code';
                } else {
                    // Check if already a member
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_members WHERE class_id = ? AND user_id = ?");
                    $stmt->execute([$class['id'], getCurrentUserId()]);

                    if ($stmt->fetchColumn() > 0) {
                        $error = 'You are already a member of this class';
                    } else {
                        // Join the class
                        $stmt = $pdo->prepare("INSERT INTO class_members (class_id, user_id) VALUES (?, ?)");
                        $stmt->execute([$class['id'], getCurrentUserId()]);

                        setFlashMessage('success', "Successfully joined class: {$class['name']}");
                        header('Location: /prakchek_/dashboard.php');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                $error = 'Failed to join class. Please try again.';
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sign-in-alt"></i> Join Class</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="class_code" class="form-label">Class Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg text-uppercase" id="class_code" name="class_code" required autofocus placeholder="Enter class code" value="<?= e($_POST['class_code'] ?? '') ?>" maxlength="10" style="letter-spacing: 2px;">
                        <small class="text-muted">Ask your assistant for the class code</small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Enter the class code provided by your assistant to join the class.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Join Class
                        </button>
                        <a href="/prakchek_/dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
