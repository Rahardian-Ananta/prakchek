<?php
require_once 'includes/header.php'; // Includes session_start and DB config

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($userRole !== 'assistant') {
    setFlashMessage('danger', 'Only assistants can delete assignments.');
    header('Location: dashboard.php');
    exit;
}

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
        setFlashMessage('danger', 'You do not have permission to delete this assignment.');
        header('Location: dashboard.php');
        exit;
    }

    $classId = $assignment['class_id'];

    $pdo->beginTransaction();

    // Delete assignment attachment files from disk and database
    $stmtAsgFiles = $pdo->prepare("SELECT * FROM files WHERE entity_type = 'assignment' AND entity_id = ?");
    $stmtAsgFiles->execute([$assignmentId]);
    foreach ($stmtAsgFiles->fetchAll() as $file) {
        $year = date('Y', strtotime($file['created_at']));
        $month = date('m', strtotime($file['created_at']));
        $filePath = __DIR__ . "/uploads/assignments/{$year}/{$month}/{$file['filename']}";
        if (file_exists($filePath)) unlink($filePath);
    }
    $pdo->prepare("DELETE FROM files WHERE entity_type = 'assignment' AND entity_id = ?")->execute([$assignmentId]);

    // Fetch all submissions for this assignment to delete associated files
    $stmtSubs = $pdo->prepare("SELECT id FROM submissions WHERE assignment_id = ?");
    $stmtSubs->execute([$assignmentId]);
    $submissionIds = $stmtSubs->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($submissionIds)) {
        $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
        
        // Delete physical submission files
        $stmtFiles = $pdo->prepare("SELECT * FROM files WHERE entity_type = 'submission' AND entity_id IN ($placeholders)");
        $stmtFiles->execute($submissionIds);
        
        foreach ($stmtFiles->fetchAll() as $file) {
            $year = date('Y', strtotime($file['created_at']));
            $month = date('m', strtotime($file['created_at']));
            $filePath = __DIR__ . "/uploads/submissions/{$year}/{$month}/{$file['filename']}";
            if (file_exists($filePath)) unlink($filePath);
        }
        
        // Delete records from files table
        $stmtDelFiles = $pdo->prepare("DELETE FROM files WHERE entity_type = 'submission' AND entity_id IN ($placeholders)");
        $stmtDelFiles->execute($submissionIds);
    }

    // Delete submissions
    $stmtDelSubs = $pdo->prepare("DELETE FROM submissions WHERE assignment_id = ?");
    $stmtDelSubs->execute([$assignmentId]);

    // Delete assignment
    $stmtDelAsg = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
    $stmtDelAsg->execute([$assignmentId]);

    $pdo->commit();

    setFlashMessage('success', 'Assignment deleted successfully.');
    header("Location: class.php?id=$classId");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    header('Location: dashboard.php');
    exit;
}
