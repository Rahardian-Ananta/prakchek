<?php
require 'config/database.php';
require 'includes/auth.php';
require 'includes/functions.php';

// Mock session to simulate the user
$_SESSION['user_id'] = 55;
$_SESSION['user_role'] = 'assistant';

$fileId = 137;
$userId = 55;
$userRole = 'assistant';

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

    $classId = null;
    if ($file['entity_type'] === 'announcement') {
        $classId = $file['announcement_class_id'];
    } elseif ($file['entity_type'] === 'assignment') {
        $classId = $file['assignment_class_id'];
    } elseif ($file['entity_type'] === 'submission') {
        $classId = $file['submission_class_id'];
        
        // For submissions, only the uploader or the class assistant can view it
        if ($file['uploader_id'] != $userId && !isClassAssistant($pdo, $classId, $userId)) {
            echo "Access denied (Submission check) - Uploader: {$file['uploader_id']}, User: {$userId}, Class: {$classId}\n";
            exit;
        }
    }

    if (!$classId) {
        echo "Access denied (No class ID)\n";
        exit;
    }

    if (!isClassMember($pdo, $classId, $userId) && !isClassAssistant($pdo, $classId, $userId)) {
        echo "Access denied (Member/Assistant check)\n";
        exit;
    }

    echo "Access GRANTED!\n";
