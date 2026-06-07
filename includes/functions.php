<?php
/**
 * Helper Functions
 */

// Generate unique class code
function generateClassCode(PDO $pdo, int $length = 8): string {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxAttempts = 10;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Check if code is unique
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE code = ?");
        $stmt->execute([$code]);

        if ($stmt->fetchColumn() == 0) {
            return $code;
        }
    }

    throw new Exception("Failed to generate unique class code");
}

// Check permission to submit assignment
function canSubmitAssignment(int $deadlineType, ?string $deadlineAt): array {
    $result = ['allowed' => true, 'isLate' => false];

    if ($deadlineType == 1) {
        // No deadline - always allowed, never late
        return $result;
    }

    if ($deadlineAt === null) {
        return $result;
    }

    $now = new DateTime();
    $deadline = new DateTime($deadlineAt);

    if ($deadlineType == 2) {
        // Accept late submissions
        $result['allowed'] = true;
        $result['isLate'] = $now > $deadline;
    } elseif ($deadlineType == 3) {
        // Strict deadline
        $result['allowed'] = $now <= $deadline;
        $result['isLate'] = false;
    }

    return $result;
}

// Format file size
function formatFileSize(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
}

// Get file icon based on MIME type
function getFileIcon(string $mimeType): string {
    $iconMap = [
        'image/jpeg' => 'fa-file-image',
        'image/png' => 'fa-file-image',
        'image/gif' => 'fa-file-image',
        'image/webp' => 'fa-file-image',
        'video/mp4' => 'fa-file-video',
        'video/webm' => 'fa-file-video',
        'application/pdf' => 'fa-file-pdf',
        'application/msword' => 'fa-file-word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fa-file-word',
    ];

    return $iconMap[$mimeType] ?? 'fa-file';
}

// Upload file
function uploadFile(PDO $pdo, array $file, string $entityType, int $entityId, int $uploaderId): int|false {
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Validate file size (max 50MB)
    $maxSize = 50 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return false;
    }

    // Validate MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if (!in_array($mimeType, $allowedMimes)) {
        return false;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('file_', true) . '.' . $extension;

    // Create directory structure: uploads/{entityType}s/{YYYY}/{MM}/
    $year = date('Y');
    $month = date('m');
    $uploadDir = __DIR__ . "/../uploads/{$entityType}s/{$year}/{$month}/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filePath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return false;
    }

    // Insert into database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO files (entity_type, entity_id, uploader_id, filename, original_name, mime_type, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $entityType,
            $entityId,
            $uploaderId,
            $filename,
            $file['name'],
            $mimeType,
            $file['size']
        ]);

        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        // Delete file if database insert fails
        unlink($filePath);
        return false;
    }
}

// Delete specific file
function deleteSpecificFile(PDO $pdo, int $fileId, int $userId): bool {
    try {
        // Get file info
        $stmt = $pdo->prepare("
            SELECT f.*, s.status, s.student_id
            FROM files f
            LEFT JOIN submissions s ON f.entity_type = 'submission' AND f.entity_id = s.id
            WHERE f.id = ?
        ");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();

        if (!$file) {
            return false;
        }

        // Check if user owns the file
        if ($file['uploader_id'] != $userId) {
            return false;
        }

        // Check if submission is in draft status or user is the student
        if ($file['entity_type'] === 'submission') {
            if ($file['status'] !== 'draft' || $file['student_id'] != $userId) {
                return false;
            }
        }

        // Delete physical file
        $year = date('Y', strtotime($file['created_at']));
        $month = date('m', strtotime($file['created_at']));
        $filePath = __DIR__ . "/../uploads/{$file['entity_type']}s/{$year}/{$month}/{$file['filename']}";

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$fileId]);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Check if user is member of class
function isClassMember(PDO $pdo, int $classId, int $userId): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM class_members
        WHERE class_id = ? AND user_id = ?
    ");
    $stmt->execute([$classId, $userId]);
    return $stmt->fetchColumn() > 0;
}

// Check if user is assistant of class
function isClassAssistant(PDO $pdo, int $classId, int $userId): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM classes
        WHERE id = ? AND assistant_id = ?
    ");
    $stmt->execute([$classId, $userId]);
    return $stmt->fetchColumn() > 0;
}

// Set flash message
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Get and clear flash message
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Sanitize output
function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
