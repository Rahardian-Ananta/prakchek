<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$fileId = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
$returnUrl = isset($_POST['return_url']) ? $_POST['return_url'] : 'dashboard.php';

if ($fileId) {
    try {
        // Fetch file to check permissions
        $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();

        if ($file) {
            $hasPermission = false;

            // Student deleting their draft file
            if ($userRole === 'student' && $file['uploader_id'] == $userId && $file['entity_type'] === 'submission') {
                $hasPermission = true;
            }
            
            // Assistant deleting their assignment or announcement file
            if ($userRole === 'assistant' && $file['uploader_id'] == $userId) {
                if ($file['entity_type'] === 'assignment' || $file['entity_type'] === 'announcement') {
                    $hasPermission = true;
                }
            }

            if ($hasPermission) {
                $year = date('Y', strtotime($file['created_at']));
                $month = date('m', strtotime($file['created_at']));
                $filePath = __DIR__ . "/uploads/{$file['entity_type']}s/{$year}/{$month}/{$file['filename']}";

                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
                $stmt->execute([$fileId]);
                
                setFlashMessage('success', 'File deleted successfully.');
            } else {
                setFlashMessage('danger', 'You do not have permission to delete this file.');
            }
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error.');
    }
}

header("Location: $returnUrl");
exit;
