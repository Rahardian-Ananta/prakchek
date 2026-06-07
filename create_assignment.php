<?php
$pageTitle = 'Create Assignment';
require_once 'includes/header.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($userRole !== 'assistant') {
    setFlashMessage('danger', 'Only assistants can create assignments.');
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
    setFlashMessage('danger', 'You do not have permission to create assignments in this class.');
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
    
    $allowedFormats = isset($_POST['allowed_formats']) && is_array($_POST['allowed_formats']) 
        ? implode(',', $_POST['allowed_formats']) 
        : 'all';
    
    // Format date properly if provided
    $formattedDeadline = null;
    if ($deadlineType != 1 && !empty($deadlineAt)) {
        $formattedDeadline = date('Y-m-d H:i:s', strtotime($deadlineAt));
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
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
                INSERT INTO assignments (class_id, assistant_id, title, description, allowed_formats, deadline_type, deadline_at, max_grade, plagiarism_rules, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$classId, $userId, $title, $description, $allowedFormats, $deadlineType, $formattedDeadline, $maxGrade, $plagiarismRulesJson]);
            $assignmentId = $pdo->lastInsertId();
            
            // Handle file uploads
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
            
            $pdo->commit();
            
            setFlashMessage('success', 'Assignment created successfully.');
            header("Location: class.php?id=$classId");
            exit;
            
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
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="fas fa-tasks"></i> Create New Assignment</h4>
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
                        <label for="title" class="form-label fw-bold">Assignment Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?= isset($_POST['title']) ? e($_POST['title']) : '' ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="form-label fw-bold">Instructions/Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?= isset($_POST['description']) ? e($_POST['description']) : '' ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Deadline Type <span class="text-danger">*</span></label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="deadline_type" id="dt1" value="1" 
                                   <?= (!isset($_POST['deadline_type']) || $_POST['deadline_type'] == 1) ? 'checked' : '' ?> onchange="toggleDeadlineField()">
                            <label class="form-check-label" for="dt1">
                                No deadline (Open forever)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="deadline_type" id="dt2" value="2"
                                   <?= (isset($_POST['deadline_type']) && $_POST['deadline_type'] == 2) ? 'checked' : '' ?> onchange="toggleDeadlineField()">
                            <label class="form-check-label" for="dt2">
                                Soft deadline (Accepts late submissions, marked as "Late")
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="deadline_type" id="dt3" value="3"
                                   <?= (isset($_POST['deadline_type']) && $_POST['deadline_type'] == 3) ? 'checked' : '' ?> onchange="toggleDeadlineField()">
                            <label class="form-check-label" for="dt3">
                                Strict deadline (Does not accept submissions after deadline)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="deadlineDateContainer" style="display: none;">
                        <label for="deadline_at" class="form-label fw-bold">Deadline Date & Time</label>
                        <input type="datetime-local" class="form-control" id="deadline_at" name="deadline_at" 
                               value="<?= isset($_POST['deadline_at']) ? e($_POST['deadline_at']) : '' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Allowed Submission Formats <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allowed_formats[]" value="link" id="fmt_link" checked>
                            <label class="form-check-label" for="fmt_link">Link</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allowed_formats[]" value="image" id="fmt_image" checked>
                            <label class="form-check-label" for="fmt_image">Gambar</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allowed_formats[]" value="document" id="fmt_doc" checked>
                            <label class="form-check-label" for="fmt_doc">PDF/Word</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allowed_formats[]" value="text" id="fmt_text" checked>
                            <label class="form-check-label" for="fmt_text">Text (Komentar)</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="max_grade" class="form-label fw-bold">Nilai Maksimal <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="max_grade" name="max_grade" value="100" min="1" max="100" required>
                        <div class="form-text">Nilai maksimal harus berada di antara 1 dan 100.</div>
                    </div>

                    <div class="card border-warning mb-4 shadow-sm" id="plagiarism-rules-card" style="display:none;">
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
                        <label for="files" class="form-label fw-bold">Attachments (Optional)</label>
                        <input class="form-control" type="file" id="files" name="files[]" multiple>
                        <div class="form-text">
                            You can attach files for this assignment. Max size: 50MB per file.
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="class.php?id=<?= $classId ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Create Assignment
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
    togglePlagiarismCard();
    
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
