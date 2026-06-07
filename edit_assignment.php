<?php
$pageTitle = 'Edit Assignment';
require_once 'includes/header.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($userRole !== 'assistant') {
    setFlashMessage('danger', 'Only assistants can edit assignments.');
    header('Location: dashboard.php');
    exit;
}

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if (!$assignmentId) {
    setFlashMessage('danger', 'Invalid assignment ID.');
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.*, c.assistant_id 
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

    if ($assignment['assistant_id'] != $userId) {
        setFlashMessage('danger', 'You do not have permission to edit this assignment.');
        header('Location: dashboard.php');
        exit;
    }

    // Get current attachments
    $stmtFiles = $pdo->prepare("SELECT * FROM files WHERE entity_type = 'assignment' AND entity_id = ?");
    $stmtFiles->execute([$assignmentId]);
    $existingFiles = $stmtFiles->fetchAll();

} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error.');
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadlineType = (int)($_POST['deadline_type'] ?? 1);
    $deadlineAt = $_POST['deadline_at'] ?? '';
    
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required.';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required.';
    }
    
    if (!in_array($deadlineType, [1, 2, 3])) {
        $errors[] = 'Invalid deadline type.';
    }
    
    if ($deadlineType != 1 && empty($deadlineAt)) {
        $errors[] = 'Deadline date & time is required for the selected deadline type.';
    }
    
    $formattedDeadline = null;
    if ($deadlineType != 1 && !empty($deadlineAt)) {
        $formattedDeadline = date('Y-m-d H:i:s', strtotime($deadlineAt));
    }
    
    if (empty($errors)) {
        try {
            $allowedFormats = isset($_POST['allowed_formats']) && is_array($_POST['allowed_formats']) 
                ? implode(',', $_POST['allowed_formats']) 
                : 'all';

            $maxGrade = isset($_POST['max_grade']) ? max(1, min((int)$_POST['max_grade'], 100)) : 100;
            $thresholds = $_POST['plagiarism_threshold'] ?? [];
            $penalties = $_POST['plagiarism_penalty'] ?? [];
            $rules = [];
            for ($i = 0; $i < count($thresholds); $i++) {
                $thresh = (int)$thresholds[$i];
                $pen = (int)$penalties[$i];
                if ($thresh > 0 && $pen > 0) {
                    $rules[] = [
                        'similarity' => $thresh,
                        'penalty' => $pen
                    ];
                }
            }
            usort($rules, function($a, $b) {
                return $a['similarity'] <=> $b['similarity'];
            });
            $hasDocument = strpos($allowedFormats, 'document') !== false;
            $plagiarismRulesJson = (!empty($rules) && $hasDocument) ? json_encode($rules) : null;

            $stmt = $pdo->prepare("
                UPDATE assignments 
                SET title = ?, description = ?, allowed_formats = ?, deadline_type = ?, deadline_at = ?, max_grade = ?, plagiarism_rules = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $allowedFormats, $deadlineType, $formattedDeadline, $maxGrade, $plagiarismRulesJson, $assignmentId]);
            
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
                        uploadFile($pdo, $fileData, 'assignment', $assignmentId, $userId);
                    }
                }
            }
            
            // No commit needed — PDO is in auto-commit mode by default
            setFlashMessage('success', 'Assignment updated successfully.');
            header("Location: assignment.php?id=$assignmentId");
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Prepare values for form
$formDeadlineAt = '';
if ($assignment['deadline_at']) {
    $formDeadlineAt = date('Y-m-d\TH:i', strtotime($assignment['deadline_at']));
}
$currentAllowedFormats = explode(',', $assignment['allowed_formats'] ?? 'all');
if (in_array('all', $currentAllowedFormats)) {
    $currentAllowedFormats = ['link', 'image', 'document', 'text'];
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-edit"></i> Edit Assignment</h4>
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
                                        <input type="hidden" name="return_url" value="edit_assignment.php?id=<?= $assignmentId ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $assignmentId ?>">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">Assignment Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?= isset($_POST['title']) ? e($_POST['title']) : e($assignment['title']) ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="form-label fw-bold">Instructions/Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?= isset($_POST['description']) ? e($_POST['description']) : e($assignment['description']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Deadline Type <span class="text-danger">*</span></label>
                        <?php $currentType = isset($_POST['deadline_type']) ? $_POST['deadline_type'] : $assignment['deadline_type']; ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="deadline_type" id="dt1" value="1" 
                                   <?= $currentType == 1 ? 'checked' : '' ?> onchange="toggleDeadlineField()">
                            <label class="form-check-label" for="dt1">No deadline</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="deadline_type" id="dt2" value="2"
                                   <?= $currentType == 2 ? 'checked' : '' ?> onchange="toggleDeadlineField()">
                            <label class="form-check-label" for="dt2">Soft deadline (Accepts late)</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="deadline_type" id="dt3" value="3"
                                   <?= $currentType == 3 ? 'checked' : '' ?> onchange="toggleDeadlineField()">
                            <label class="form-check-label" for="dt3">Strict deadline (No late)</label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="deadlineDateContainer" style="<?= $currentType == 1 ? 'display:none;' : '' ?>">
                        <label for="deadline_at" class="form-label fw-bold">Deadline Date & Time</label>
                        <input type="datetime-local" class="form-control" id="deadline_at" name="deadline_at" 
                               value="<?= isset($_POST['deadline_at']) ? e($_POST['deadline_at']) : $formDeadlineAt ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Allowed Submission Formats <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allowed_formats[]" value="link" id="fmt_link" <?= in_array('link', $currentAllowedFormats) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fmt_link">Link</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allowed_formats[]" value="image" id="fmt_image" <?= in_array('image', $currentAllowedFormats) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fmt_image">Gambar</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allowed_formats[]" value="document" id="fmt_doc" <?= in_array('document', $currentAllowedFormats) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fmt_doc">PDF/Word</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allowed_formats[]" value="text" id="fmt_text" <?= in_array('text', $currentAllowedFormats) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fmt_text">Text (Komentar)</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="max_grade" class="form-label fw-bold">Nilai Maksimal <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="max_grade" name="max_grade" value="<?= isset($_POST['max_grade']) ? e($_POST['max_grade']) : e($assignment['max_grade'] ?? 100) ?>" min="1" max="100" required>
                        <div class="form-text">Nilai maksimal harus berada di antara 1 dan 100.</div>
                    </div>

                    <div class="card border-warning mb-4 shadow-sm" id="plagiarism-rules-card" style="<?= in_array('document', $currentAllowedFormats) ? '' : 'display:none;' ?>">
                        <div class="card-header bg-warning text-dark fw-bold">
                            <i class="fas fa-exclamation-triangle me-1"></i> Aturan Pengurangan Nilai Plagiasi PDF/Word (Opsional)
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Aturan ini hanya berlaku untuk tugas PDF/Word. Nilai mahasiswa dipotong otomatis jika tingkat kemiripan melewati batas yang ditentukan. Bersifat berjenjang (misal: > 30% potong 20, > 60% potong 70).</p>
                            
                            <div id="rules-container">
                                <!-- Dynamic Rules inserted here -->
                            </div>
                            
                            <button type="button" class="btn btn-sm btn-outline-warning mt-2 fw-bold" id="btn-add-rule">
                                <i class="fas fa-plus-circle me-1"></i> Tambah Aturan Pengurangan
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="files" class="form-label fw-bold">Add New Attachments (Optional)</label>
                        <input class="form-control" type="file" id="files" name="files[]" multiple>
                        <div class="form-text">
                            You can attach more files for this assignment. Max size: 50MB per file.
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="assignment.php?id=<?= $assignmentId ?>" class="btn btn-outline-secondary">
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

<script>
function toggleDeadlineField() {
    const dt1 = document.getElementById('dt1').checked;
    const container = document.getElementById('deadlineDateContainer');
    const input = document.getElementById('deadline_at');
    
    if (dt1) {
        container.style.display = 'none';
        input.required = false;
    } else {
        container.style.display = 'block';
        input.required = true;
    }
}

// Plagiarism Rules Dynamic Fields
document.getElementById('btn-add-rule').addEventListener('click', function() {
    addRuleRow();
});

function addRuleRow(threshold = '', penalty = '') {
    const container = document.getElementById('rules-container');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center mb-2 rule-row animate-fade-in';
    row.innerHTML = `
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted small">Jika Kemiripan ></span>
                <input type="number" name="plagiarism_threshold[]" class="form-control" placeholder="30" min="1" max="100" required value="${threshold}">
                <span class="input-group-text bg-light text-muted small">%</span>
            </div>
        </div>
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted small">Potong Nilai sebesar</span>
                <input type="number" name="plagiarism_penalty[]" class="form-control" placeholder="20" min="1" required value="${penalty}">
                <span class="input-group-text bg-light text-muted small">poin</span>
            </div>
        </div>
        <div class="col-md-2 text-end">
            <button type="button" class="btn btn-sm btn-danger btn-remove-rule w-100"><i class="fas fa-trash-alt me-1"></i>Hapus</button>
        </div>
    `;
    container.appendChild(row);
    
    row.querySelector('.btn-remove-rule').addEventListener('click', function() {
        row.remove();
    });
}

// Populate existing rules
<?php
$existingRules = json_decode($assignment['plagiarism_rules'] ?? '[]', true);
if (!empty($existingRules)):
?>
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($existingRules as $rule): ?>
            addRuleRow(<?= (int)$rule['similarity'] ?>, <?= (int)$rule['penalty'] ?>);
        <?php endforeach; ?>
    });
<?php endif; ?>

// Toggle plagiarism rules card based on document format checkbox
function togglePlagiarismCard() {
    const docChecked = document.getElementById('fmt_doc').checked;
    const card = document.getElementById('plagiarism-rules-card');
    if (docChecked) {
        card.style.display = 'block';
    } else {
        card.style.display = 'none';
        document.getElementById('rules-container').innerHTML = '';
    }
}

document.getElementById('fmt_doc').addEventListener('change', togglePlagiarismCard);

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleDeadlineField();
    // Plagiarism card visibility already set via PHP inline style
    
    // Enforce range 1-100 on max_grade in real-time
    const maxGradeInput = document.getElementById('max_grade');
    if (maxGradeInput) {
        maxGradeInput.addEventListener('input', function () {
            const val = this.value;
            if (val === '') return; // Biarkan kosong jika user sedang menghapus
            
            const num = Number(val);
            if (num > 100) this.value = 100;
            if (num < 1) this.value = 1;
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
