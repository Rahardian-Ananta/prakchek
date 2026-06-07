<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if ($userRole !== 'assistant') {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied.";
    exit;
}

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id || !in_array($type, ['assignment', 'class'])) {
    header('HTTP/1.1 400 Bad Request');
    echo "Invalid request.";
    exit;
}

// Function to clean filename
function cleanFilename($text) {
    // Replace spaces with underscore
    $clean = preg_replace('/\s+/', '_', $text);
    // Remove special characters except underscore
    $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $clean);
    // Collapse multiple underscores to single
    $clean = preg_replace('/_+/', '_', $clean);
    // Remove leading/trailing underscores
    $clean = trim($clean, '_');
    // Limit length to 100 characters
    if (strlen($clean) > 100) {
        $clean = substr($clean, 0, 100);
    }
    return $clean;
}

// Function to generate CSV with UTF-8 BOM for Excel compatibility
function outputCSV($filename, $headers, $data) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel to recognize UTF-8 encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write headers (menggunakan delimiter titik koma ';' agar bersahabat dengan Excel Indonesia)
    fputcsv($output, $headers, ';');

    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
}

if ($type === 'assignment') {
    // Export single assignment grades
    $stmt = $pdo->prepare("
        SELECT a.*, c.name as class_name, c.assistant_id
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $assignment = $stmt->fetch();

    if (!$assignment || $assignment['assistant_id'] != $userId) {
        header('HTTP/1.1 403 Forbidden');
        echo "Access denied.";
        exit;
    }

    // Fetch all members of this class and their submissions
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, s.status, s.grade, s.plagiarism_penalty, s.feedback, s.submitted_at, s.is_late
        FROM class_members cm
        JOIN users u ON cm.user_id = u.id
        LEFT JOIN submissions s ON s.student_id = u.id AND s.assignment_id = ?
        WHERE cm.class_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$id, $assignment['class_id']]);
    $results = $stmt->fetchAll();

    $maxGrade = $assignment['max_grade'] ?? 100;
    $headers = [
        'Nama Mahasiswa',
        'Email',
        'Status',
        'Terlambat',
        'Waktu Pengumpulan',
        'Nilai Asli',
        'Potongan Plagiasi',
        'Potongan Keterlambatan',
        'Nilai Akhir (Maks: ' . $maxGrade . ')',
        'Feedback Dosen',
        'Catatan Sistem'
    ];
    $data = [];

    foreach ($results as $row) {
        $status = 'Belum Mengumpulkan';
        if ($row['status'] === 'draft') {
            $status = 'Draft';
        } elseif ($row['status'] === 'submitted') {
            $status = 'Sudah Mengumpulkan';
        }

        $isLate = ($row['is_late'] ?? 0) ? 'Ya' : 'Tidak';
        $submittedAt = $row['submitted_at'] ? date('Y-m-d H:i:s', strtotime($row['submitted_at'])) : '-';

        $gradeOriginal = '';
        $penaltyPlagiarism = 0;
        $penaltyLate = 0;
        $gradeFinal = '';
        $feedbackDosen = '';
        $catatanSistem = '';

        if ($row['status'] === 'submitted') {
            if ($row['grade'] !== null) {
                $gradeOriginal = $row['grade'];
                $totalPenalty = $row['plagiarism_penalty'] ?? 0;

                // Parse feedback to separate system message from teacher feedback
                $feedback = $row['feedback'] ?? '';

                // Check if there's a system plagiarism message
                if (preg_match('/\[Sistem Autopost Plagiasi\]:\s*(.+?)(?:\n|$)/s', $feedback, $matches)) {
                    $catatanSistem = trim($matches[1]);
                    // Remove system message from feedback
                    $feedbackDosen = trim(preg_replace('/\[Sistem Autopost Plagiasi\]:.+$/s', '', $feedback));

                    // If there's plagiarism penalty, it's from plagiarism
                    $penaltyPlagiarism = $totalPenalty;
                } else {
                    // No plagiarism message, check if late
                    $feedbackDosen = $feedback;

                    if ($isLate === 'Ya' && $totalPenalty > 0) {
                        // Late penalty
                        $penaltyLate = $totalPenalty;
                    } else {
                        // Could be plagiarism without system message (manual)
                        $penaltyPlagiarism = $totalPenalty;
                    }
                }

                $gradeFinal = max(0, $gradeOriginal - $penaltyPlagiarism - $penaltyLate);
            } else {
                $gradeOriginal = '-';
                $penaltyPlagiarism = 0;
                $penaltyLate = 0;
                $gradeFinal = 'Belum Dinilai';
                $feedbackDosen = $row['feedback'] ?? '';
            }
        } elseif ($row['status'] === 'draft') {
            // Handle Draft status - set all to 0 for consistency
            $gradeOriginal = 0;
            $penaltyPlagiarism = 0;
            $penaltyLate = 0;
            $gradeFinal = 0;
        }

        $data[] = [
            $row['name'],
            $row['email'],
            $status,
            $isLate,
            $submittedAt,
            $gradeOriginal,
            $penaltyPlagiarism,
            $penaltyLate,
            $gradeFinal,
            $feedbackDosen,
            $catatanSistem
        ];
    }

    $cleanTitle = cleanFilename($assignment['title']);
    $filename = "Nilai_Tugas_" . $cleanTitle . "_" . date('Ymd') . ".csv";
    outputCSV($filename, $headers, $data);

} elseif ($type === 'class') {
    // Export recap for the whole class
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$id]);
    $class = $stmt->fetch();

    if (!$class || $class['assistant_id'] != $userId) {
        header('HTTP/1.1 403 Forbidden');
        echo "Access denied.";
        exit;
    }

    // Fetch all assignments for this class
    $stmtAssign = $pdo->prepare("
        SELECT id, title, max_grade
        FROM assignments
        WHERE class_id = ?
        ORDER BY created_at ASC
    ");
    $stmtAssign->execute([$id]);
    $assignments = $stmtAssign->fetchAll();

    // Fetch all members
    $stmtMembers = $pdo->prepare("
        SELECT u.id, u.name, u.email
        FROM class_members cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.class_id = ?
        ORDER BY u.name ASC
    ");
    $stmtMembers->execute([$id]);
    $members = $stmtMembers->fetchAll();

    // Build headers
    $headers = ['No', 'Nama Mahasiswa', 'Email'];
    $maxGrades = [];

    foreach ($assignments as $assign) {
        $cleanAssignTitle = cleanFilename($assign['title']);
        if (strlen($cleanAssignTitle) > 30) {
            $cleanAssignTitle = substr($cleanAssignTitle, 0, 30);
        }
        $headers[] = $cleanAssignTitle . ' (' . $assign['max_grade'] . ')';
        $maxGrades[$assign['id']] = $assign['max_grade'];
    }

    $headers[] = 'Total Nilai';
    $headers[] = 'Total Maksimal';
    $headers[] = 'Rata-rata (%)';

    $data = [];
    $no = 1;

    foreach ($members as $member) {
        $row = [
            $no++,
            $member['name'],
            $member['email']
        ];

        $totalScore = 0;
        $totalMax = 0;

        foreach ($assignments as $assign) {
            $totalMax += $assign['max_grade']; // Selalu hitung max_grade dari semua tugas

            $stmtSub = $pdo->prepare("
                SELECT status, grade, plagiarism_penalty
                FROM submissions
                WHERE student_id = ? AND assignment_id = ?
            ");
            $stmtSub->execute([$member['id'], $assign['id']]);
            $sub = $stmtSub->fetch();

            $cell = '-';

            if ($sub) {
                if ($sub['status'] === 'submitted') {
                    if ($sub['grade'] !== null) {
                        $finalGrade = max(0, $sub['grade'] - ($sub['plagiarism_penalty'] ?? 0));
                        $cell = $finalGrade;
                        $totalScore += $finalGrade;
                    } else {
                        $cell = 'Belum Dinilai';
                    }
                } elseif ($sub['status'] === 'draft') {
                    $cell = 'Draft';
                }
            } else {
                $cell = 'Belum Mengumpulkan';
            }

            $row[] = $cell;
        }

        $row[] = $totalScore;
        $row[] = $totalMax;
        $avg = $totalMax > 0 ? round(($totalScore / $totalMax) * 100, 2) : 0;
        $row[] = $avg . '%';

        $data[] = $row;
    }

    $cleanClassName = cleanFilename($class['name']);
    $filename = "Rekap_Nilai_Kelas_" . $cleanClassName . "_" . date('Ymd') . ".csv";
    outputCSV($filename, $headers, $data);
}
