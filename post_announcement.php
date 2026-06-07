<?php
$pageTitle = 'Post Announcement';
require_once 'includes/header.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($userRole !== 'assistant') {
    setFlashMessage('danger', 'Only assistants can post announcements.');
    header('Location: dashboard.php');
    exit;
}

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : (isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0);

if (!$classId) {
    setFlashMessage('danger', 'Invalid class ID.');
    header('Location: dashboard.php');
    exit;
}

// Check if assistant owns this class
if (!isClassAssistant($pdo, $classId, $userId)) {
    setFlashMessage('danger', 'You do not have permission to post announcements in this class.');
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token (assume simple implementation or skip for basic phase)
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required.';
    }
    
    if (empty($content)) {
        $errors[] = 'Content is required.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO announcements (class_id, assistant_id, title, content, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$classId, $userId, $title, $content]);
            $announcementId = $pdo->lastInsertId();
            
            // Phase 2: Handle file uploads
            if (!empty($_FILES['files']['name'][0])) {
                $fileCount = count($_FILES['files']['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                        $fileData = [
                            'name' => $_FILES['files']['name'][$i],
                            'type' => $_FILES['files']['type'][$i],
                            'tmp_name' => $_FILES['files']['tmp_name'][$i],
                            'error' => $_FILES['files']['error'][$i],
                            'size' => $_FILES['files']['size'][$i]
                        ];
                        
                        $uploadResult = uploadFile($pdo, $fileData, 'announcement', $announcementId, $userId);
                        if (!$uploadResult) {
                            // Non-fatal error, but you might want to notify
                            $errors[] = "Failed to upload file: " . $_FILES['files']['name'][$i];
                        }
                    }
                }
            }
            
            if (empty($errors)) {
                $pdo->commit();
                setFlashMessage('success', 'Announcement posted successfully.');
                header("Location: class.php?id=$classId");
                exit;
            } else {
                $pdo->rollBack();
                // We show errors on the form
            }
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-bullhorn"></i> Post New Announcement</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?= isset($_POST['title']) ? e($_POST['title']) : '' ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="content" class="form-label fw-bold">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" name="content" rows="6" required><?= isset($_POST['content']) ? e($_POST['content']) : '' ?></textarea>
                    </div>
                    
                    <!-- File upload section -->
                    <div class="mb-4">
                        <label for="files" class="form-label fw-bold">Attachments (Optional)</label>
                        <input class="form-control" type="file" id="files" name="files[]" multiple>
                        <div class="form-text">
                            Allowed files: Images (jpg, png, webp), Video (mp4, webm), PDF, Word. Max size: 50MB per file.
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="class.php?id=<?= $classId ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Post Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
