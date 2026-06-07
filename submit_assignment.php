<?php
require_once 'includes/header.php'; // For session and DB

// Clean output buffer AFTER header.php has set up session
requireLogin();
ob_clean();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($userRole !== 'student') {
    setFlashMessage('danger', 'Only students can submit assignments.');
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$assignmentId = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
$action = $_POST['action'] ?? '';

if (!$assignmentId) {
    setFlashMessage('danger', 'Invalid assignment ID.');
    header('Location: dashboard.php');
    exit;
}

try {
    // 1. Fetch Assignment & Verify Access
    $stmt = $pdo->prepare("
        SELECT a.*, c.id as class_id 
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

    if (!isClassMember($pdo, $assignment['class_id'], $userId)) {
        setFlashMessage('danger', 'You are not a member of this class.');
        header('Location: dashboard.php');
        exit;
    }

    // 2. Fetch or create submission record
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?");
    $stmt->execute([$assignmentId, $userId]);
    $submission = $stmt->fetch();

    if (!$submission) {
        $stmt = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, text_content, status) VALUES (?, ?, '', 'draft')");
        $stmt->execute([$assignmentId, $userId]);
        $submissionId = $pdo->lastInsertId();
        
        // Refetch to have full array
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
        $stmt->execute([$submissionId]);
        $submission = $stmt->fetch();
    } else {
        $submissionId = $submission['id'];
    }

    $submissionLogic = canSubmitAssignment($assignment['deadline_type'], $assignment['deadline_at']);
    $isLate = $submissionLogic['isLate'] ? 1 : 0;

    // 3. Handle Actions
    
    // ACTION: DELETE FILE (From Draft)
    if ($action === 'delete_file') {
        if ($submission['status'] !== 'draft') {
            setFlashMessage('danger', 'Cannot delete files after submission. Unsubmit first.');
            header("Location: assignment.php?id=$assignmentId");
            exit;
        }
        
        $fileId = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
        if ($fileId && deleteSpecificFile($pdo, $fileId, $userId)) {
            setFlashMessage('success', 'File removed.');
        } else {
            setFlashMessage('danger', 'Failed to remove file.');
        }
        header("Location: assignment.php?id=$assignmentId");
        exit;
    }
    
    // ACTION: SAVE DRAFT
    if ($action === 'save_draft') {
        if (!$submissionLogic['allowed']) {
            setFlashMessage('danger', 'Submission is closed for this assignment.');
            header("Location: assignment.php?id=$assignmentId");
            exit;
        }
        
        if ($submission['status'] !== 'draft') {
            setFlashMessage('danger', 'Cannot modify submitted assignment. Unsubmit first.');
            header("Location: assignment.php?id=$assignmentId");
            exit;
        }

        $textContent = $_POST['text_content'] ?? $submission['text_content'];
        
        // Update text content
        $stmt = $pdo->prepare("UPDATE submissions SET text_content = ? WHERE id = ?");
        $stmt->execute([$textContent, $submissionId]);

        // Process file uploads
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
                    uploadFile($pdo, $fileData, 'submission', $submissionId, $userId);
                }
            }
        }
        
        setFlashMessage('success', 'Draft saved successfully.');
        header("Location: assignment.php?id=$assignmentId");
        exit;
    }
    
    // ACTION: SUBMIT
    if ($action === 'submit') {
        if (!$submissionLogic['allowed']) {
            setFlashMessage('danger', 'Deadline has passed. Submission is closed.');
            header("Location: assignment.php?id=$assignmentId");
            exit;
        }
        
        // Fetch submission files to validate there's actually content
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM files WHERE entity_type = 'submission' AND entity_id = ?");
        $stmtCheck->execute([$submissionId]);
        $fileCount = $stmtCheck->fetchColumn();
        $hasText = !empty(trim($submission['text_content'] ?? ''));
        
        if ($fileCount === 0 && !$hasText) {
            setFlashMessage('danger', 'Please add at least a file or text content before turning in.');
            header("Location: assignment.php?id=$assignmentId");
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE submissions 
            SET status = 'submitted', submitted_at = NOW(), is_late = ? 
            WHERE id = ?
        ");
        $stmt->execute([$isLate, $submissionId]);
        
        setFlashMessage('success', 'Assignment turned in successfully.');
        header("Location: assignment.php?id=$assignmentId");
        exit;
    }
    
    // ACTION: UNSUBMIT
    if ($action === 'unsubmit') {
        // Block unsubmit if already graded
        if ($submission['grade'] !== null) {
            setFlashMessage('danger', 'Tugas sudah dinilai oleh asisten dan tidak bisa dibatalkan.');
            header("Location: assignment.php?id=$assignmentId");
            exit;
        }
        
        if (!$submissionLogic['allowed']) {
            setFlashMessage('danger', 'Deadline has passed. Unsubmit is no longer allowed.');
            header("Location: assignment.php?id=$assignmentId");
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE submissions 
            SET status = 'draft'
            WHERE id = ?
        ");
        $stmt->execute([$submissionId]);
        
        setFlashMessage('info', 'Assignment unsubmitted. You can now make changes.');
        header("Location: assignment.php?id=$assignmentId");
        exit;
    }

} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error.');
    header("Location: dashboard.php");
    exit;
}
