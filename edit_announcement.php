<?php
$pageTitle = 'Edit Announcement';
require_once 'includes/header.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($userRole !== 'assistant') {
    setFlashMessage('danger', 'Only assistants can edit announcements.');
    header('Location: dashboard.php');
    exit;
}

$announcementId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if (!$announcementId) {
    setFlashMessage('danger', 'Invalid announcement ID.');
    header('Location: dashboard.php');
    exit;
}

// Fetch announcement and verify ownership
try {
    $stmt = $pdo->prepare("
        SELECT a.*, c.assistant_id 
        FROM announcements a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ?
    ");
    $stmt->execute([$announcementId]);
    $announcement = $stmt->fetch();

    if (!$announcement) {
        setFlashMessage('danger', 'Announcement not found.');
        header('Location: dashboard.php');
        exit;
    }

    if ($announcement['assistant_id'] != $userId) {
        setFlashMessage('danger', 'You do not have permission to edit this announcement.');
        header('Location: dashboard.php');
        exit;
    }

    // Get current attachments
    $stmtFiles = $pdo->prepare("SELECT * FROM files WHERE entity_type = 'announcement' AND entity_id = ?");
    $stmtFiles->execute([$announcementId]);
    $existingFiles = $stmtFiles->fetchAll();

} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error.');
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $stmt = $pdo->prepare("
                UPDATE announcements 
                SET title = ?, content = ? 
                WHERE id = ?
            ");
            $stmt->execute([$title, $content, $announcementId]);
            
            // Handle new file uploads
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
                        uploadFile($pdo, $fileData, 'announcement', $announcementId, $userId);
                    }
                }
            }
            
            setFlashMessage('success', 'Announcement updated successfully.');
            header("Location: class.php?id=" . $announcement['class_id']);
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-edit"></i> Edit Announcement</h4>
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

                <div class="mb-4">
                    <h6>Current Attachments</h6>
                    <?php if (empty($existingFiles)): ?>
                        <p class="text-muted small">No attachments.</p>
                    <?php else: ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($existingFiles as $file): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="text-truncate">
                                        <i class="fas <?= getFileIcon($file['mime_type']) ?> me-2 text-primary"></i>
                                        <?= e($file['original_name']) ?>
                                    </div>
                                    <form action="delete_file_handler.php" method="POST" class="m-0" onsubmit="return confirm('Remove this file?');">
                                        <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                        <input type="hidden" name="return_url" value="edit_announcement.php?id=<?= $announcementId ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $announcementId ?>">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?= isset($_POST['title']) ? e($_POST['title']) : e($announcement['title']) ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="content" class="form-label fw-bold">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" name="content" rows="6" required><?= isset($_POST['content']) ? e($_POST['content']) : e($announcement['content']) ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="files" class="form-label fw-bold">Add New Attachments (Optional)</label>
                        <input class="form-control" type="file" id="files" name="files[]" multiple>
                        <div class="form-text">
                            You can attach more files for this announcement. Max size: 50MB per file.
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="class.php?id=<?= $announcement['class_id'] ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
