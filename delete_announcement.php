<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($userRole !== 'assistant') {
    setFlashMessage('danger', 'Only assistants can delete announcements.');
    header('Location: dashboard.php');
    exit;
}

$announcementId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$announcementId) {
    setFlashMessage('danger', 'Invalid announcement ID.');
    header('Location: dashboard.php');
    exit;
}

try {
    // Check if the announcement exists and belongs to a class managed by this assistant
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
        setFlashMessage('danger', 'You do not have permission to delete this announcement.');
        header('Location: dashboard.php');
        exit;
    }

    $classId = $announcement['class_id'];

    // Begin transaction for cascaded deletions
    $pdo->beginTransaction();

    // Phase 2: Fetch and delete attached files from disk and 'files' table here
    $stmtFiles = $pdo->prepare("SELECT * FROM files WHERE entity_type = 'announcement' AND entity_id = ?");
    $stmtFiles->execute([$announcementId]);
    $files = $stmtFiles->fetchAll();

    foreach ($files as $file) {
        $year = date('Y', strtotime($file['created_at']));
        $month = date('m', strtotime($file['created_at']));
        $filePath = __DIR__ . "/uploads/announcements/{$year}/{$month}/{$file['filename']}";

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    $stmtDeleteFiles = $pdo->prepare("DELETE FROM files WHERE entity_type = 'announcement' AND entity_id = ?");
    $stmtDeleteFiles->execute([$announcementId]);

    // Delete the announcement itself
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$announcementId]);

    $pdo->commit();

    setFlashMessage('success', 'Announcement deleted successfully.');
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
