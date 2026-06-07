<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if (!$fileId) {
    header("HTTP/1.0 404 Not Found");
    exit('File not found');
}

try {
    // 1. Get file info and verify authorization
    $stmt = $pdo->prepare("
        SELECT f.*, 
               a.class_id as announcement_class_id,
               s.assignment_id, asg_sub.class_id as submission_class_id,
               asg.class_id as assignment_class_id
        FROM files f
        LEFT JOIN announcements a ON f.entity_type = 'announcement' AND f.entity_id = a.id
        LEFT JOIN submissions s ON f.entity_type = 'submission' AND f.entity_id = s.id
        LEFT JOIN assignments asg_sub ON s.assignment_id = asg_sub.id
        LEFT JOIN assignments asg ON f.entity_type = 'assignment' AND f.entity_id = asg.id
        WHERE f.id = ?
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        header("HTTP/1.0 404 Not Found");
        exit('File not found');
    }

    $classId = null;
    if ($file['entity_type'] === 'announcement') {
        $classId = $file['announcement_class_id'];
    } elseif ($file['entity_type'] === 'assignment') {
        $classId = $file['assignment_class_id'];
    } elseif ($file['entity_type'] === 'submission') {
        $classId = $file['submission_class_id'];
        
        // For submissions, only the uploader or the class assistant can view it
        if ($file['uploader_id'] != $userId && !isClassAssistant($pdo, $classId, $userId)) {
            header("HTTP/1.0 403 Forbidden");
            exit('Access denied');
        }
    }

    if (!$classId) {
        header("HTTP/1.0 404 Not Found");
        exit('Associated entity not found');
    }

    // Check if user is member or assistant of the class
    if (!isClassMember($pdo, $classId, $userId) && !isClassAssistant($pdo, $classId, $userId)) {
        header("HTTP/1.0 403 Forbidden");
        exit('Access denied');
    }

    // 2. Locate file on disk
    $year = date('Y', strtotime($file['created_at']));
    $month = date('m', strtotime($file['created_at']));
    $filePath = __DIR__ . "/uploads/{$file['entity_type']}s/{$year}/{$month}/{$file['filename']}";

    if (!file_exists($filePath)) {
        header("HTTP/1.0 404 Not Found");
        exit('Physical file not found');
    }

    // 3. Stream file with range requests support
    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        header("HTTP/1.0 500 Internal Server Error");
        exit('Could not open file');
    }

    $size = filesize($filePath);
    $length = $size;
    $start = 0;
    $end = $size - 1;

    header("Content-Type: {$file['mime_type']}");
    header("Accept-Ranges: bytes");

    // Force download for some types, inline for others
    $inlineMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'video/mp4', 'video/webm'];
    $disposition = in_array($file['mime_type'], $inlineMimes) ? 'inline' : 'attachment';
    header("Content-Disposition: $disposition; filename=\"" . $file['original_name'] . "\"");

    if (isset($_SERVER['HTTP_RANGE'])) {
        $c_start = $start;
        $c_end = $end;
        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        if ($range == '-') {
            $c_start = $size - substr($range, 1);
        } else {
            $range = explode('-', $range);
            $c_start = $range[0];
            $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
        }
        
        $c_end = ($c_end > $end) ? $end : $c_end;
        
        if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }
        
        $start = $c_start;
        $end = $c_end;
        $length = $end - $start + 1;
        
        fseek($fp, $start);
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$size");
    }
    header("Content-Length: $length");

    $buffer = 1024 * 8;
    while (!feof($fp) && ($p = ftell($fp)) <= $end) {
        if ($p + $buffer > $end) {
            $buffer = $end - $p + 1;
        }
        set_time_limit(0);
        echo fread($fp, $buffer);
        flush();
    }
    fclose($fp);
    exit;

} catch (Exception $e) {
    header("HTTP/1.0 500 Internal Server Error");
    exit('Server error');
}
