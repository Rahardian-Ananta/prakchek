<?php
require_once 'includes/header.php';
requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

$classId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$classId) {
    setFlashMessage('danger', 'Invalid class ID.');
    header('Location: dashboard.php');
    exit;
}

// Check authorization
$isAssistant = isClassAssistant($pdo, $classId, $userId);
$isMember = isClassMember($pdo, $classId, $userId);

if (!$isAssistant && !$isMember) {
    setFlashMessage('danger', 'You do not have permission to view this class.');
    header('Location: dashboard.php');
    exit;
}

// Get class details
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as assistant_name 
        FROM classes c
        JOIN users u ON c.assistant_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$classId]);
    $class = $stmt->fetch();

    if (!$class) {
        setFlashMessage('danger', 'Class not found.');
        header('Location: dashboard.php');
        exit;
    }

    // Get announcements
    $stmt = $pdo->prepare("
        SELECT * FROM announcements 
        WHERE class_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$classId]);
    $announcements = $stmt->fetchAll();
    
    // Fetch all files for these announcements
    $announcementFiles = [];
    if (!empty($announcements)) {
        $announcementIds = array_column($announcements, 'id');
        $placeholders = implode(',', array_fill(0, count($announcementIds), '?'));
        
        $stmtFiles = $pdo->prepare("
            SELECT * FROM files 
            WHERE entity_type = 'announcement' AND entity_id IN ($placeholders)
        ");
        $stmtFiles->execute($announcementIds);
        $files = $stmtFiles->fetchAll();
        
        foreach ($files as $file) {
            $announcementFiles[$file['entity_id']][] = $file;
        }
    }

    // Get assignments
    $stmt = $pdo->prepare("
        SELECT * FROM assignments 
        WHERE class_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$classId]);
    $assignments = $stmt->fetchAll();

    // Get class members (students)
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, cm.joined_at 
        FROM class_members cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.class_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$classId]);
    $classMembers = $stmt->fetchAll();

} catch (PDOException $e) {
    setFlashMessage('danger', 'An error occurred while fetching class details.');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = $class['name'];
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-primary text-white">
            <div class="card-body py-5">
                <h1 class="display-4"><?= e($class['name']) ?></h1>
                <p class="lead mb-0">
                    <i class="fas fa-chalkboard-teacher"></i> <?= e($class['assistant_name']) ?>
                </p>
                <?php if ($isAssistant): ?>
                    <div class="mt-3">
                        <span class="badge bg-light text-dark fs-6">
                            Class Code: <strong><?= e($class['code']) ?></strong>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Content: Announcements -->
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0"><i class="fas fa-bullhorn"></i> Announcements</h3>
            <?php if ($isAssistant): ?>
                <a href="post_announcement.php?class_id=<?= $class['id'] ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Post Announcement
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="card mb-3">
                <div class="card-body text-center text-muted py-5">
                    <i class="fas fa-comment-slash fa-3x mb-3"></i>
                    <p>No announcements yet.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="card-title"><?= e($announcement['title']) ?></h5>
                                <small class="text-muted">
                                    <?= date('M d, Y H:i', strtotime($announcement['created_at'])) ?>
                                </small>
                            </div>
                            <?php if ($isAssistant): ?>
                            <div>
                                <a href="edit_announcement.php?id=<?= $announcement['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_announcement.php?id=<?= $announcement['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this announcement?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <p class="card-text mt-2"><?= nl2br(e($announcement['content'])) ?></p>
                        <!-- File attachments -->
                        <?php if (isset($announcementFiles[$announcement['id']])): ?>
                            <div class="mt-3">
                                <h6><i class="fas fa-paperclip"></i> Attachments:</h6>
                                <div class="row g-2">
                                    <?php foreach ($announcementFiles[$announcement['id']] as $file): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card h-100 bg-light border-0">
                                                <div class="card-body p-2 d-flex align-items-center">
                                                    <div class="me-2 text-primary fs-3">
                                                        <i class="fas <?= getFileIcon($file['mime_type']) ?>"></i>
                                                    </div>
                                                    <div class="overflow-hidden">
                                                        <div class="text-truncate" title="<?= e($file['original_name']) ?>">
                                                            <strong><?= e($file['original_name']) ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                                            <small class="text-muted"><?= formatFileSize($file['file_size']) ?></small>
                                                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" 
                                                                    onclick="renderFilePreview(<?= $file['id'] ?>, '<?= e($file['mime_type']) ?>', '<?= e($file['original_name']) ?>')">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Sidebar: Assignments, etc. -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tasks"></i> Assignments</h5>
                <?php if ($isAssistant): ?>
                    <div>
                        <a href="export_grades.php?type=class&id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-success me-1" title="Export Rekap Nilai Kelas (CSV)">
                            <i class="fas fa-file-excel"></i>
                        </a>
                        <a href="create_assignment.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-success" title="Buat Tugas Baru">
                            <i class="fas fa-plus"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($assignments)): ?>
                    <li class="list-group-item text-muted text-center py-4">
                        <i class="fas fa-clipboard-list fa-2x mb-2"></i><br>
                        No assignments yet.
                    </li>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment): ?>
                        <li class="list-group-item">
                            <a href="assignment.php?id=<?= $assignment['id'] ?>" class="text-decoration-none fw-bold">
                                <?= e($assignment['title']) ?>
                            </a>
                            <div class="text-muted small mt-1">
                                <?php if ($assignment['deadline_type'] == 1): ?>
                                    <span class="badge bg-secondary">No Deadline</span>
                                <?php else: ?>
                                    <i class="fas fa-clock"></i> Due: <?= date('M d, Y H:i', strtotime($assignment['deadline_at'])) ?>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users"></i> Class Members (<?= count($classMembers) ?>)</h5>
            </div>
            <ul class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                <?php if (empty($classMembers)): ?>
                    <li class="list-group-item text-muted text-center py-3">
                        No students have joined yet.
                    </li>
                <?php else: ?>
                    <?php foreach ($classMembers as $member): ?>
                        <li class="list-group-item d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <i class="fas fa-user-circle fa-2x text-secondary"></i>
                            </div>
                            <div>
                                <h6 class="mb-0"><?= e($member['name']) ?></h6>
                                <small class="text-muted"><?= e($member['email']) ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- File Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel">File Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center" id="previewModalBody">
        <!-- Content injected by JS -->
      </div>
      <div class="modal-footer">
        <a href="#" id="downloadBtn" class="btn btn-primary" download>
            <i class="fas fa-download"></i> Download Full File
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/preview.js"></script>
<?php require_once 'includes/footer.php'; ?>
