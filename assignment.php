<?php
require_once 'includes/header.php';
requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$assignmentId) {
    setFlashMessage('danger', 'Invalid assignment ID.');
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_submission') {
    if ($userRole !== 'assistant') {
        setFlashMessage('danger', 'Unauthorized action.');
    } else {
        $subId = (int)$_POST['submission_id'];
        $rawGrade = $_POST['grade'] ?? '';
        $feedback = trim($_POST['feedback'] ?? '');
        
        // Fetch max_grade for this assignment to validate cap
        $stmtMg = $pdo->prepare("SELECT max_grade FROM assignments WHERE id = ?");
        $stmtMg->execute([$assignmentId]);
        $maxGradeCap = (int)($stmtMg->fetchColumn() ?? 100);
        
        $grade = null;
        if ($rawGrade !== '') {
            $grade = max(0, min((int)$rawGrade, $maxGradeCap)); // Clamp 0 to max_grade
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE submissions 
                SET grade = ?, feedback = ?
                WHERE id = ?
            ");
            $stmt->execute([$grade, $feedback, $subId]);
            setFlashMessage('success', 'Nilai berhasil diperbarui!' . ($grade !== null && $grade < (int)$rawGrade ? ' (Nilai dipotong ke maksimum ' . $maxGradeCap . ')' : ''));
            header("Location: assignment.php?id=$assignmentId");
            exit;
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Gagal menyimpan penilaian.');
        }
    }
}

try {
    // Get assignment details
    $stmt = $pdo->prepare("
        SELECT a.*, c.name as class_name, c.id as class_id, c.assistant_id 
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ?
    ");
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        setFlashMessage('danger', 'Assignment not found.');
        header('Location: dashboard.php');
        exit;
    }

    $classId = $assignment['class_id'];
    $isAssistant = isClassAssistant($pdo, $classId, $userId);
    $isMember = isClassMember($pdo, $classId, $userId);

    if (!$isAssistant && !$isMember) {
        setFlashMessage('danger', 'You do not have permission to view this assignment.');
        header('Location: dashboard.php');
        exit;
    }

    $submissionLogic = canSubmitAssignment($assignment['deadline_type'], $assignment['deadline_at']);
    $pageTitle = $assignment['title'];

    // Get assignment attachments
    $stmtFiles = $pdo->prepare("SELECT * FROM files WHERE entity_type = 'assignment' AND entity_id = ?");
    $stmtFiles->execute([$assignmentId]);
    $assignmentFiles = $stmtFiles->fetchAll();
    
    $allowedFormats = explode(',', $assignment['allowed_formats'] ?? 'all');
    if (in_array('all', $allowedFormats)) {
        $allowedFormats = ['link', 'image', 'document', 'text'];
    }

    // Variables for Student
    $submission = null;
    $submissionFiles = [];
    
    // Variables for Assistant
    $allSubmissions = [];

    if ($userRole === 'student') {
        // Fetch current submission for this student
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?");
        $stmt->execute([$assignmentId, $userId]);
        $submission = $stmt->fetch();

        if ($submission) {
            $stmtFiles = $pdo->prepare("SELECT * FROM files WHERE entity_type = 'submission' AND entity_id = ?");
            $stmtFiles->execute([$submission['id']]);
            $submissionFiles = $stmtFiles->fetchAll();
        }
    } else {
        // Assistant: Fetch all submissions and class members
        // To simplify, we fetch all students in the class and left join their submissions
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.name as student_name, u.email,
                   s.id as submission_id, s.status, s.is_late, s.submitted_at, s.text_content,
                   s.grade, s.plagiarism_penalty, s.feedback
            FROM class_members cm
            JOIN users u ON cm.user_id = u.id
            LEFT JOIN submissions s ON s.student_id = u.id AND s.assignment_id = ?
            WHERE cm.class_id = ?
            ORDER BY u.name ASC
        ");
        $stmt->execute([$assignmentId, $classId]);
        $allSubmissions = $stmt->fetchAll();
        
        // We'll also fetch all submission files to group them easily
        // NOTE: array_filter preserves original keys which breaks PDO positional params.
        // array_values() resets keys to 0-based sequential.
        $submissionIds = array_values(array_filter(array_column($allSubmissions, 'submission_id')));
        $allSubmissionFiles = [];
        if (!empty($submissionIds)) {
            $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
            $stmtFiles = $pdo->prepare("SELECT * FROM files WHERE entity_type = 'submission' AND entity_id IN ($placeholders)");
            $stmtFiles->execute($submissionIds);
            foreach ($stmtFiles->fetchAll() as $file) {
                $allSubmissionFiles[$file['entity_id']][] = $file;
            }
        }
    }

} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    header('Location: dashboard.php');
    exit;
}
?>

<div class="row">
    <!-- Assignment Details -->
    <div class="col-md-8 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="class.php?id=<?= $classId ?>"><?= e($assignment['class_name']) ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Assignment</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-start">
                    <h2 class="text-primary mb-3"><i class="fas fa-file-alt"></i> <?= e($assignment['title']) ?></h2>
                    <?php if ($isAssistant): ?>
                        <div>
                            <a href="edit_assignment.php?id=<?= $assignmentId ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i> Edit</a>
                            <a href="delete_assignment.php?id=<?= $assignmentId ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this assignment?');"><i class="fas fa-trash"></i> Delete</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <span class="badge bg-info text-dark me-2">
                        Created: <?= date('M d, Y', strtotime($assignment['created_at'])) ?>
                    </span>
                    <?php if ($assignment['deadline_type'] == 1): ?>
                        <span class="badge bg-secondary"><i class="fas fa-infinity"></i> No Deadline</span>
                    <?php else: ?>
                        <?php 
                        $isPast = strtotime($assignment['deadline_at']) < time();
                        $badgeClass = $isPast ? 'bg-danger' : 'bg-warning text-dark';
                        ?>
                        <span class="badge <?= $badgeClass ?>">
                            <i class="fas fa-clock"></i> Due: <?= date('M d, Y H:i', strtotime($assignment['deadline_at'])) ?>
                            <?= $assignment['deadline_type'] == 3 ? '(Strict)' : '' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="p-3 bg-light rounded border mb-3">
                    <?= nl2br(e($assignment['description'])) ?>
                </div>

                <?php if (!empty($assignmentFiles)): ?>
                    <h6><i class="fas fa-paperclip"></i> Attachments:</h6>
                    <div class="row g-2">
                        <?php foreach ($assignmentFiles as $file): ?>
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
                <?php endif; ?>
                
                <div class="mt-3">
                    <strong>Allowed Formats:</strong>
                    <?php
                    $formatBadges = [];
                    if (in_array('link', $allowedFormats)) $formatBadges[] = '<span class="badge bg-secondary">Link</span>';
                    if (in_array('image', $allowedFormats)) $formatBadges[] = '<span class="badge bg-secondary">Image</span>';
                    if (in_array('document', $allowedFormats)) $formatBadges[] = '<span class="badge bg-secondary">Document (PDF/Word)</span>';
                    if (in_array('text', $allowedFormats)) $formatBadges[] = '<span class="badge bg-secondary">Text</span>';
                    echo implode(' ', $formatBadges);
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Panel: Student Submission Form OR Assistant Stats -->
    <div class="col-md-4">
        <?php if ($userRole === 'student'): ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Your Work</h5>
                </div>
                <div class="card-body">
                    <?php
                    $status = $submission ? $submission['status'] : 'not_started';
                    $statusLabel = 'Not Started';
                    $statusColor = 'secondary';
                    if ($status === 'draft') { $statusLabel = 'Draft'; $statusColor = 'warning'; }
                    elseif ($status === 'submitted') {
                        if ($submission['is_late']) {
                            $statusLabel = 'Late';
                            $statusColor = 'danger';
                        } else {
                            $statusLabel = 'Submitted';
                            $statusColor = 'success';
                        }
                    }
                    ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold">Status:</span>
                        <span class="badge bg-<?= $statusColor ?> fs-6"><?= $statusLabel ?></span>
                    </div>

                    <?php if ($submission && $submission['status'] === 'submitted'): ?>
                        <hr>
                        <div class="mb-3">
                            <span class="fw-bold d-block mb-1 small text-muted">Nilai Akhir:</span>
                            <?php if ($submission['grade'] !== null): ?>
                                <h3 class="text-success fw-bold font-monospace mb-0">
                                    <?= $submission['grade'] ?> <span class="fs-6 text-muted">/ <?= $assignment['max_grade'] ?? 100 ?></span>
                                </h3>
                                <?php if ($submission['plagiarism_penalty'] > 0): ?>
                                    <div class="text-danger small fw-bold mt-1">
                                        <i class="fas fa-exclamation-triangle"></i> Terkena potongan plagiasi: -<?= $submission['plagiarism_penalty'] ?> poin
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted italic small"><i class="fas fa-hourglass-half me-1"></i> Belum dinilai asisten</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($submission['feedback'])): ?>
                            <div class="mb-3">
                                <span class="fw-bold d-block mb-1 small text-muted">Catatan/Feedback Asisten:</span>
                                <div class="bg-light p-2 rounded small border text-dark font-monospace" style="white-space: pre-wrap;"><?= e($submission['feedback']) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($status === 'submitted'): ?>
                        <!-- View Submitted Work -->
                        <div class="mb-3">
                            <?php if (!empty($submissionFiles)): ?>
                                <h6>Attached Files:</h6>
                                <div class="row g-2 mb-3">
                                    <?php foreach ($submissionFiles as $file): ?>
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
                            <?php endif; ?>
                            
                            <?php if (!empty($submission['text_content'])): ?>
                                <h6>Comment/Text:</h6>
                                <div class="bg-light p-2 rounded small border text-muted">
                                    <?= nl2br(e($submission['text_content'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($submission['grade'] !== null): ?>
                            <div class="alert alert-warning py-2 small mb-0 text-center">
                                <i class="fas fa-lock me-1"></i> <strong>Tugas sudah dinilai.</strong><br>Tidak dapat dibatalkan pengumpulannya.
                            </div>
                        <?php elseif ($submissionLogic['allowed']): ?>
                            <form action="submit_assignment.php" method="POST">
                                <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                                <input type="hidden" name="action" value="unsubmit">
                                <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Unsubmit to make changes?');">
                                    Unsubmit
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info py-2 small mb-0 text-center">Deadline has passed. Unsubmit is disabled.</div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <!-- Form for Draft / Not Started -->
                        
                        <!-- Show current Draft files -->
                        <?php if (!empty($submissionFiles)): ?>
                            <div class="row g-2 mb-3">
                                <?php foreach ($submissionFiles as $file): ?>
                                    <div class="col-12">
                                        <div class="card bg-light border-0">
                                            <div class="card-body p-2 d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center overflow-hidden">
                                                    <div class="me-2 text-primary fs-3">
                                                        <i class="fas <?= getFileIcon($file['mime_type']) ?>"></i>
                                                    </div>
                                                    <div class="text-truncate" style="max-width: 150px;">
                                                        <strong><?= e($file['original_name']) ?></strong><br>
                                                        <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" 
                                                                onclick="renderFilePreview(<?= $file['id'] ?>, '<?= e($file['mime_type']) ?>', '<?= e($file['original_name']) ?>')">
                                                            <i class="fas fa-eye"></i> Preview
                                                        </button>
                                                    </div>
                                                </div>
                                                <form action="submit_assignment.php" method="POST" class="m-0">
                                                    <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                                                    <input type="hidden" name="action" value="delete_file">
                                                    <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove file"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($submissionLogic['allowed']): ?>
                            <form action="submit_assignment.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                                <input type="hidden" name="action" value="save_draft">
                                
                                <?php if (in_array('link', $allowedFormats) || in_array('image', $allowedFormats) || in_array('document', $allowedFormats)): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Add File(s)</label>
                                    <?php 
                                    $accepts = [];
                                    if (in_array('image', $allowedFormats)) $accepts[] = 'image/*';
                                    if (in_array('document', $allowedFormats)) {
                                        $accepts[] = '.pdf';
                                        $accepts[] = '.doc';
                                        $accepts[] = '.docx';
                                    }
                                    $acceptAttr = !empty($accepts) ? 'accept="' . implode(',', $accepts) . '"' : '';
                                    ?>
                                    <input type="file" name="files[]" class="form-control form-control-sm" multiple <?= $acceptAttr ?>>
                                    <small class="text-muted">Max size: 50MB</small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (in_array('text', $allowedFormats) || in_array('link', $allowedFormats)): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Text Content (Optional / Link)</label>
                                    <textarea name="text_content" class="form-control form-control-sm" rows="3"><?= $submission ? e($submission['text_content']) : '' ?></textarea>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Save Draft / Add Files</button>
                                </div>
                            </form>
                            
                            <hr>
                            
                            <!-- Final Submit -->
                            <form action="submit_assignment.php" method="POST">
                                <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                                <input type="hidden" name="action" value="submit">
                                <button type="submit" class="btn btn-success w-100" <?= empty($submission) && empty($_FILES) ? 'disabled' : '' ?> onclick="return confirm('Are you sure you want to turn in your work?');">
                                    Turn In
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger py-2 small mb-0 text-center">
                                <i class="fas fa-ban"></i> Submission is closed for this assignment.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Assistant Summary Panel -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Submissions Summary</h5>
                </div>
                <div class="card-body text-center">
                    <?php
                    $submittedCount = count(array_filter($allSubmissions, fn($s) => $s['status'] === 'submitted'));
                    $totalStudents = count($allSubmissions);
                    ?>
                    <h2 class="display-4 text-primary"><?= $submittedCount ?> <span class="fs-4 text-muted">/ <?= $totalStudents ?></span></h2>
                    <p class="text-muted mb-0">Turned In</p>
                </div>
            </div>
            <div class="d-grid gap-2">
                <!-- Action to download all files (future enhancement) -->
                <button class="btn btn-outline-primary"><i class="fas fa-download"></i> Download All (WIP)</button>
                <?php if (in_array('document', $allowedFormats)): ?>
                    <a href="check_plagiarism.php?assignment_id=<?= $assignmentId ?>" class="btn btn-outline-warning text-dark"><i class="fas fa-search"></i> Check Plagiarism (PDF/Word)</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($userRole === 'assistant'): ?>
<?php $csrfToken = generateCsrfToken(); ?>
<!-- Submissions List for Assistant -->
<div class="row mt-2">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users"></i> Student Submissions</h5>
                <a href="export_grades.php?type=assignment&id=<?= $assignmentId ?>" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-file-excel"></i> Export Nilai (CSV)
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student Name</th>
                                <th>Status</th>
                                <th>Submitted At</th>
                                <th>Files</th>
                                <th>Nilai</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allSubmissions as $sub): ?>
                                <?php
                                $statusBadge = '<span class="badge bg-secondary">Not Started</span>';
                                if ($sub['status'] === 'draft') $statusBadge = '<span class="badge bg-warning">Draft</span>';
                                elseif ($sub['status'] === 'submitted') {
                                    $statusBadge = '<span class="badge bg-success">Submitted</span>';
                                    if ($sub['is_late']) $statusBadge .= ' <span class="badge bg-danger">Late</span>';
                                }
                                $files = isset($allSubmissionFiles[$sub['submission_id']]) ? $allSubmissionFiles[$sub['submission_id']] : [];
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= e($sub['student_name']) ?></div>
                                        <div class="small text-muted"><?= e($sub['email']) ?></div>
                                    </td>
                                    <td><?= $statusBadge ?></td>
                                    <td>
                                        <?php if ($sub['status'] === 'submitted' && $sub['submitted_at']): ?>
                                            <?= date('M d, Y H:i', strtotime($sub['submitted_at'])) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($files) && $sub['status'] === 'submitted'): ?>
                                            <?= count($files) ?> file(s)
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($sub['status'] === 'submitted'): ?>
                                            <?php if ($sub['grade'] !== null): ?>
                                                <span class="badge bg-success font-monospace fs-6"><?= $sub['grade'] ?></span>
                                                <?php if ($sub['plagiarism_penalty'] > 0): ?>
                                                    <span class="badge bg-danger ms-1" title="Potongan Plagiasi">-<?= $sub['plagiarism_penalty'] ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Belum Dinilai</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($sub['status'] === 'submitted'): ?>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#gradeModal<?= $sub['user_id'] ?>"><i class="fas fa-edit me-1"></i>View / Grade</button>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grading Modals (rendered outside table to avoid DOM nesting issues) -->
<?php foreach ($allSubmissions as $sub): ?>
    <?php if ($sub['status'] === 'submitted'): ?>
        <?php $files = isset($allSubmissionFiles[$sub['submission_id']]) ? $allSubmissionFiles[$sub['submission_id']] : []; ?>
        <div class="modal fade" id="gradeModal<?= $sub['user_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-user-graduate me-2"></i>Submission: <?= e($sub['student_name']) ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Text Content -->
                        <?php if (!empty($sub['text_content'])): ?>
                            <h6 class="fw-bold"><i class="fas fa-align-left me-1"></i> Text / Link Content:</h6>
                            <div class="bg-light p-3 rounded mb-3 border" style="white-space: pre-wrap; word-break: break-all;">
                                <?= nl2br(e($sub['text_content'])) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Files -->
                        <?php if (!empty($files)): ?>
                            <h6 class="fw-bold"><i class="fas fa-paperclip me-1"></i> Uploaded Files:</h6>
                            <div class="row g-2 mb-3">
                                <?php foreach ($files as $file): ?>
                                    <div class="col-md-6">
                                        <div class="card h-100 bg-light border-0">
                                            <div class="card-body p-2 d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center overflow-hidden">
                                                    <div class="me-2 text-primary fs-3">
                                                        <i class="fas <?= getFileIcon($file['mime_type']) ?>"></i>
                                                    </div>
                                                    <div class="text-truncate" style="max-width: 150px;">
                                                        <strong><?= e($file['original_name']) ?></strong><br>
                                                        <small class="text-muted"><?= formatFileSize($file['file_size']) ?></small>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-dismiss="modal"
                                                            onclick="setTimeout(() => renderFilePreview(<?= $file['id'] ?>, '<?= e($file['mime_type']) ?>', '<?= e($file['original_name']) ?>'), 400)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <a href="serve_file.php?id=<?= $file['id'] ?>" class="btn btn-sm btn-outline-secondary" download><i class="fas fa-download"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($sub['text_content']) && empty($files)): ?>
                            <div class="alert alert-secondary text-center py-3">
                                <i class="fas fa-info-circle me-1"></i> Mahasiswa submit tanpa text content maupun file.
                            </div>
                        <?php endif; ?>

                        <!-- Grading Section -->
                        <hr class="my-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="fas fa-graduation-cap me-1"></i> Penilaian Asisten</h6>
                        
                        <form method="POST" action="assignment.php?id=<?= $assignmentId ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="grade_submission">
                            <input type="hidden" name="submission_id" value="<?= $sub['submission_id'] ?>">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-dark">Nilai (0 - <?= $assignment['max_grade'] ?? 100 ?>)</label>
                                    <input type="number" name="grade" class="form-control font-monospace fs-5" 
                                           value="<?= $sub['grade'] ?>" min="0" max="<?= $assignment['max_grade'] ?? 100 ?>"
                                           placeholder="0">
                                    <?php if ($sub['plagiarism_penalty'] > 0): ?>
                                        <div class="form-text text-danger small fw-bold mt-1">
                                            <i class="fas fa-exclamation-triangle"></i> Potongan Plagiasi Aktif: -<?= $sub['plagiarism_penalty'] ?>
                                        </div>
                                    <?php endif; ?>
                                   
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-bold small text-dark">Catatan / Feedback</label>
                                    <textarea name="feedback" class="form-control" rows="3" placeholder="Masukkan saran atau alasan pengurangan nilai..."><?= e($sub['feedback'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan Penilaian</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

<!-- File Preview Modal (Shared) -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel">File Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center" id="previewModalBody">
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

<!-- PDF.js library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
</script>
<script src="assets/js/preview.js"></script>
<script>
// Enforce max grade cap in real-time on all grade inputs
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="grade"]').forEach(function(input) {
        function enforceMax() {
            const max = parseInt(input.getAttribute('max')) || 100;
            const min = parseInt(input.getAttribute('min')) || 0;
            let val = parseInt(input.value);
            if (isNaN(val)) return;
            if (val > max) input.value = max;
            if (val < min) input.value = min;
        }
        input.addEventListener('input', enforceMax);
        input.addEventListener('change', enforceMax);
        input.addEventListener('blur', enforceMax);
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
