<?php
$pageTitle = 'Create Class';
require_once 'includes/header.php';

requireRole('assistant');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $className = trim($_POST['class_name'] ?? '');

        if (empty($className)) {
            $error = 'Please enter a class name';
        } else {
            try {
                // Generate unique class code
                $classCode = generateClassCode($pdo);

                // Insert new class
                $stmt = $pdo->prepare("INSERT INTO classes (name, code, assistant_id) VALUES (?, ?, ?)");
                $stmt->execute([$className, $classCode, getCurrentUserId()]);

                setFlashMessage('success', "Class created successfully! Class code: $classCode");
                header('Location: /prakchek_/dashboard.php');
                exit;
            } catch (Exception $e) {
                $error = 'Failed to create class. Please try again.';
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Create New Class</h5>
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
                        <label for="class_name" class="form-label">Class Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="class_name" name="class_name" required autofocus placeholder="e.g., Web Programming 101" value="<?= e($_POST['class_name'] ?? '') ?>">
                        <small class="text-muted">Enter a descriptive name for your class</small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> A unique class code will be automatically generated for students to join.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Create Class
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
