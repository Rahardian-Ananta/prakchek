<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

try {
    if ($userRole === 'assistant') {
        // Get classes created by assistant
        $stmt = $pdo->prepare("
            SELECT c.*, COUNT(DISTINCT cm.user_id) as member_count
            FROM classes c
            LEFT JOIN class_members cm ON c.id = cm.class_id
            WHERE c.assistant_id = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$userId]);
        $classes = $stmt->fetchAll();
    } else {
        // Get classes joined by student
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as assistant_name, cm.joined_at
            FROM classes c
            INNER JOIN class_members cm ON c.id = cm.class_id
            INNER JOIN users u ON c.assistant_id = u.id
            WHERE cm.user_id = ?
            ORDER BY cm.joined_at DESC
        ");
        $stmt->execute([$userId]);
        $classes = $stmt->fetchAll();
    }
if ($userRole === 'assistant' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_secret_code') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlashMessage('danger', 'Invalid CSRF token.');
    } else {
        $newSecret = trim($_POST['assistant_secret'] ?? '');
        if (empty($newSecret)) {
            setFlashMessage('danger', 'Secret code cannot be empty.');
        } else {
            $content = "<?php\n// config/secret.php\n// Konfigurasi Kode Rahasia Asisten - Bisa diubah oleh Asisten dari Dashboard\nreturn [\n    'assistant_secret' => '" . addslashes($newSecret) . "'\n];\n";
            if (file_put_contents(__DIR__ . '/config/secret.php', $content)) {
                setFlashMessage('success', 'Kode rahasia asisten berhasil diperbarui!');
                header('Location: dashboard.php');
                exit;
            } else {
                setFlashMessage('danger', 'Gagal memperbarui file konfigurasi.');
            }
        }
    }
}

} catch (PDOException $e) {
    $classes = [];
}
?>

<div class="row">
    <div class="col-12">
        <?php if ($userRole === 'assistant'): ?>
            <?php
            $secretConfig = @include __DIR__ . '/config/secret.php';
            $correctSecret = is_array($secretConfig) && isset($secretConfig['assistant_secret']) 
                ? $secretConfig['assistant_secret'] 
                : 'PRAKCHEK_ASPRAK_2026';
            ?>
            <div class="card border-danger shadow-sm mb-4">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-shield-alt me-1"></i> Keamanan Pendaftaran Asisten (Asprak)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">Mahasiswa yang mendaftar sebagai Asisten memerlukan kode ini. Jika kode bocor, segera ubah kodenya di bawah ini.</p>
                    <form method="POST" action="" class="row g-3 align-items-center">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                        <input type="hidden" name="action" value="update_secret_code">
                        <div class="col-auto">
                            <label class="col-form-label fw-bold">Kode Rahasia Asisten:</label>
                        </div>
                        <div class="col-auto">
                            <input type="text" name="assistant_secret" class="form-control form-control-sm font-monospace fs-6 fw-bold" value="<?= e($correctSecret) ?>" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-save me-1"></i> Perbarui Kode</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-home"></i> Dashboard
            </h2>
            <?php if ($userRole === 'assistant'): ?>
            <a href="/prakchek_/create_class.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Create New Class
            </a>
            <?php else: ?>
            <a href="/prakchek_/join_class.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Join Class
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <?php if ($userRole === 'assistant'): ?>
                        <i class="fas fa-chalkboard"></i> My Classes
                    <?php else: ?>
                        <i class="fas fa-book"></i> Enrolled Classes
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($classes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">
                        <?php if ($userRole === 'assistant'): ?>
                            You haven't created any classes yet.
                        <?php else: ?>
                            You haven't joined any classes yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($userRole === 'assistant'): ?>
                    <a href="/prakchek_/create_class.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Create Your First Class
                    </a>
                    <?php else: ?>
                    <a href="/prakchek_/join_class.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Join a Class
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($classes as $class): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 class-card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="/prakchek_/class.php?id=<?= $class['id'] ?>" class="text-decoration-none">
                                        <?= e($class['name']) ?>
                                    </a>
                                </h5>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <i class="fas fa-key"></i> Code: <strong><?= e($class['code']) ?></strong>
                                    </small>
                                </p>
                                <?php if ($userRole === 'assistant'): ?>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <i class="fas fa-users"></i> <?= $class['member_count'] ?> member(s)
                                    </small>
                                </p>
                                <?php else: ?>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <i class="fas fa-chalkboard-teacher"></i> <?= e($class['assistant_name']) ?>
                                    </small>
                                </p>
                                <?php endif; ?>
                                <a href="/prakchek_/class.php?id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-arrow-right"></i> View Class
                                </a>
                            </div>
                            <div class="card-footer text-muted">
                                <small>
                                    <i class="far fa-clock"></i>
                                    <?php if ($userRole === 'assistant'): ?>
                                        Created <?= date('M d, Y', strtotime($class['created_at'])) ?>
                                    <?php else: ?>
                                        Joined <?= date('M d, Y', strtotime($class['joined_at'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
