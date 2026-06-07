<?php
$pageTitle = 'Plagiarism Check & Auto Penalty';
require_once 'includes/header.php';
require_once 'vendor/autoload.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if ($userRole !== 'assistant' || !$assignmentId) {
    setFlashMessage('danger', 'Invalid request or unauthorized.');
    header('Location: dashboard.php');
    exit;
}

try {
    // Check if the user is the assistant for this class
    $stmt = $pdo->prepare("
        SELECT a.title, a.max_grade, a.plagiarism_rules, c.id as class_id, c.assistant_id 
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ?
    ");
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch();

    if (!$assignment || $assignment['assistant_id'] != $userId) {
        setFlashMessage('danger', 'Unauthorized access.');
        header('Location: dashboard.php');
        exit;
    }

    $classId = $assignment['class_id'];
    $assignmentTitle = $assignment['title'];
    $maxGrade = (int)($assignment['max_grade'] ?? 100);
    $rules = json_decode($assignment['plagiarism_rules'] ?? '[]', true);

    // Fetch all submitted files for this assignment (only PDF and Word)
    $stmt = $pdo->prepare("
        SELECT f.id as file_id, f.filename, f.original_name, f.mime_type, f.created_at,
               s.student_id, u.name as student_name
        FROM files f
        JOIN submissions s ON f.entity_type = 'submission' AND f.entity_id = s.id
        JOIN users u ON s.student_id = u.id
        WHERE s.assignment_id = ? AND s.status = 'submitted'
        AND f.mime_type IN (
            'application/pdf', 
            'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        )
    ");
    $stmt->execute([$assignmentId]);
    $allFiles = $stmt->fetchAll();

} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error.');
    header("Location: assignment.php?id=$assignmentId");
    exit;
}

// Function to extract text
function extractText($filePath, $mimeType) {
    try {
        if ($mimeType === 'application/pdf') {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        } elseif (
            $mimeType === 'application/msword' || 
            $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ) {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . ' ';
                    } elseif (method_exists($element, 'getElements')) {
                        foreach ($element->getElements() as $innerElement) {
                            if (method_exists($innerElement, 'getText')) {
                                $text .= $innerElement->getText() . ' ';
                            }
                        }
                    }
                }
            }
            return $text;
        }
    } catch (Exception $e) {
        return '';
    }
    return '';
}

// Function to calculate similarity (Jaccard Index with shingling)
function calculateSimilarity($text1, $text2) {
    $text1 = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $text1));
    $text2 = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $text2));
    
    // Normalize newlines and tabs to single spaces
    $text1 = preg_replace('/\s+/', ' ', $text1);
    $text2 = preg_replace('/\s+/', ' ', $text2);
    
    $words1 = array_filter(explode(' ', $text1));
    $words2 = array_filter(explode(' ', $text2));
    
    if (empty($words1) || empty($words2)) {
        return 0;
    }
    
    $set1 = [];
    $set2 = [];
    
    // Create 3-grams
    $words1 = array_values($words1);
    for ($i = 0; $i < count($words1) - 2; $i++) {
        $set1[] = $words1[$i] . ' ' . $words1[$i+1] . ' ' . $words1[$i+2];
    }
    
    $words2 = array_values($words2);
    for ($i = 0; $i < count($words2) - 2; $i++) {
        $set2[] = $words2[$i] . ' ' . $words2[$i+1] . ' ' . $words2[$i+2];
    }
    
    if (empty($set1) || empty($set2)) {
        // Fallback to words if too short for trigrams
        $set1 = array_unique($words1);
        $set2 = array_unique($words2);
    } else {
        $set1 = array_unique($set1);
        $set2 = array_unique($set2);
    }
    
    $intersection = count(array_intersect($set1, $set2));
    $union = count(array_unique(array_merge($set1, $set2)));
    
    if ($union == 0) return 0;
    
    return round(($intersection / $union) * 100, 2);
}

// Process files
$studentFiles = [];
foreach ($allFiles as $file) {
    $year = date('Y', strtotime($file['created_at']));
    $month = date('m', strtotime($file['created_at']));
    $filePath = __DIR__ . "/uploads/submissions/{$year}/{$month}/{$file['filename']}";
    
    if (file_exists($filePath)) {
        $text = extractText($filePath, $file['mime_type']);
        if (trim($text) !== '') {
            $studentFiles[$file['student_id']]['name'] = $file['student_name'];
            $studentFiles[$file['student_id']]['files'][] = [
                'name' => $file['original_name'],
                'text' => $text
            ];
        }
    }
}

// Compare files & calculate highest match for each student (Cross-Comparison)
$studentIds = array_keys($studentFiles);
$numStudents = count($studentIds);

$studentHighestSim = [];
foreach ($studentIds as $sId) {
    $studentHighestSim[$sId] = [
        'student_name' => $studentFiles[$sId]['name'],
        'max_similarity' => 0,
        'partner_name' => '-',
        'file_name' => '-',
        'partner_file_name' => '-'
    ];
}

for ($i = 0; $i < $numStudents; $i++) {
    for ($j = $i + 1; $j < $numStudents; $j++) {
        $studentA = $studentIds[$i];
        $studentB = $studentIds[$j];
        
        $filesA = $studentFiles[$studentA]['files'];
        $filesB = $studentFiles[$studentB]['files'];
        
        $maxSimilarity = 0;
        $bestMatch = null;
        
        foreach ($filesA as $fileA) {
            foreach ($filesB as $fileB) {
                $similarity = calculateSimilarity($fileA['text'], $fileB['text']);
                if ($similarity > $maxSimilarity) {
                    $maxSimilarity = $similarity;
                    $bestMatch = [
                        'fileA' => $fileA['name'],
                        'fileB' => $fileB['name']
                    ];
                }
            }
        }
        
        if ($maxSimilarity > 0) {
            // Update Student A highest match
            if ($maxSimilarity > $studentHighestSim[$studentA]['max_similarity']) {
                $studentHighestSim[$studentA]['max_similarity'] = $maxSimilarity;
                $studentHighestSim[$studentA]['partner_name'] = $studentFiles[$studentB]['name'];
                $studentHighestSim[$studentA]['file_name'] = $bestMatch['fileA'];
                $studentHighestSim[$studentA]['partner_file_name'] = $bestMatch['fileB'];
            }
            // Update Student B highest match
            if ($maxSimilarity > $studentHighestSim[$studentB]['max_similarity']) {
                $studentHighestSim[$studentB]['max_similarity'] = $maxSimilarity;
                $studentHighestSim[$studentB]['partner_name'] = $studentFiles[$studentA]['name'];
                $studentHighestSim[$studentB]['file_name'] = $bestMatch['fileB'];
                $studentHighestSim[$studentB]['partner_file_name'] = $bestMatch['fileA'];
            }
        }
    }
}

// Calculate Penalties and Grades
$calculatedSubmissions = [];
foreach ($studentHighestSim as $sId => $simInfo) {
    $maxSim = $simInfo['max_similarity'];
    $penalty = 0;
    
    // Find matching plagiarism rule
    if (!empty($rules)) {
        foreach ($rules as $rule) {
            if ($maxSim > $rule['similarity']) {
                $penalty = max($penalty, (int)$rule['penalty']);
            }
        }
    }
    
    $suggestedGrade = max(0, $maxGrade - $penalty);
    
    $calculatedSubmissions[$sId] = array_merge($simInfo, [
        'penalty' => $penalty,
        'suggested_grade' => $suggestedGrade
    ]);
}

// Sort by highest similarity descending
uasort($calculatedSubmissions, function($a, $b) {
    return $b['max_similarity'] <=> $a['max_similarity'];
});

// Handle Form Post - Apply Auto Penalties
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_penalties'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlashMessage('danger', 'Invalid CSRF Token.');
    } else {
        try {
            $pdo->beginTransaction();
            
            foreach ($calculatedSubmissions as $sId => $subData) {
                $stmt = $pdo->prepare("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?");
                $stmt->execute([$assignmentId, $sId]);
                $subId = $stmt->fetchColumn();
                
                if ($subId) {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE submissions 
                        SET plagiarism_penalty = ?, feedback = CONCAT(COALESCE(feedback, ''), ?)
                        WHERE id = ?
                    ");
                    
                    $feedbackNotice = "\n\n[Sistem Autopost Plagiasi]: Pengurangan nilai otomatis sebesar -" . $subData['penalty'] . " diterapkan karena tingkat kemiripan tertinggi " . $subData['max_similarity'] . "% dideteksi dengan " . $subData['partner_name'] . ".";
                    
                    $stmtUpdate->execute([
                        $subData['penalty'], 
                        $feedbackNotice,
                        $subId
                    ]);
                }
            }
            
            $pdo->commit();
            setFlashMessage('success', 'Penalti plagiasi otomatis berhasil diterapkan ke semua tugas mahasiswa!');
            header("Location: assignment.php?id=$assignmentId");
            exit;
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlashMessage('danger', 'Gagal menerapkan penalti: ' . $e->getMessage());
        }
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-search"></i> Laporan Plagiasi & Penilaian Otomatis</h4>
                <a href="assignment.php?id=<?= $assignmentId ?>" class="btn btn-sm btn-light">Kembali ke Tugas</a>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-7">
                        <h4 class="text-primary fw-bold mb-1"><?= e($assignmentTitle) ?></h4>
                        <p class="text-muted small">Perbandingan silang dinamis (Cross-Comparison) untuk seluruh file PDF dan Word yang dikumpulkan mahasiswa. Hasil diurutkan dari kemiripan tertinggi.</p>
                        
                        <div class="alert alert-info py-2 px-3 small border-0 bg-light-blue shadow-none mb-0">
                            <strong>Nilai Maksimal Tugas:</strong> <span class="badge bg-secondary"><?= $maxGrade ?> poin</span>
                        </div>
                    </div>
                    
                    <div class="col-md-5">
                        <div class="card border-warning bg-light-yellow p-3">
                            <h6 class="text-warning fw-bold mb-2"><i class="fas fa-gavel me-1"></i> Aturan Pengurangan Nilai Aktif:</h6>
                            <?php if (empty($rules)): ?>
                                <p class="text-muted small mb-0">Belum ada aturan pengurangan plagiasi aktif untuk tugas ini. <a href="edit_assignment.php?id=<?= $assignmentId ?>" class="fw-bold">Set Aturan Sekarang</a></p>
                            <?php else: ?>
                                <ul class="list-unstyled mb-0 small">
                                    <?php foreach ($rules as $rule): ?>
                                        <li class="mb-1 text-dark">
                                            <i class="fas fa-check-circle text-warning me-1"></i>
                                            Kemiripan <strong>> <?= $rule['similarity'] ?>%</strong>: Potong <strong>-<?= $rule['penalty'] ?></strong> poin.
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($studentFiles)): ?>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Tidak ada berkas PDF atau Word yang telah dikumpulkan mahasiswa.</div>
                <?php elseif (count($studentFiles) < 2): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Dibutuhkan minimal 2 pengumpulan berkas Word/PDF dari mahasiswa berbeda untuk melakukan perbandingan plagiarisme.</div>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-secondary"><i class="fas fa-trophy me-1"></i> Peringkat Kemiripan Mahasiswa</h5>
                        
                        <?php if (!empty($rules)): ?>
                            <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menerapkan pengurangan nilai otomatis ke semua tugas mahasiswa berdasarkan tingkat plagiasi di bawah ini?');">
                                <?= csrfField() ?>
                                <button type="submit" name="apply_penalties" class="btn btn-warning fw-bold text-dark shadow-sm">
                                    <i class="fas fa-check-double me-1"></i> Terapkan Pemotongan Nilai Otomatis
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <div class="table-responsive shadow-sm rounded">
                        <table class="table table-hover align-middle border-0 mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nama Siswa</th>
                                    <th>Tingkat Kemiripan Tertinggi</th>
                                    <th>Partner Plagiasi</th>
                                    <th>Berkas yang Cocok</th>
                                    <th class="text-center">Potongan Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($calculatedSubmissions as $sId => $sub): ?>
                                    <?php 
                                        $maxSim = $sub['max_similarity'];
                                        $rowClass = '';
                                        $badgeClass = 'bg-success';
                                        
                                        if ($maxSim > 80) {
                                            $rowClass = 'table-danger';
                                            $badgeClass = 'bg-danger';
                                        } elseif ($maxSim > 50) {
                                            $rowClass = 'table-warning';
                                            $badgeClass = 'bg-warning text-dark';
                                        } elseif ($maxSim > 30) {
                                            $badgeClass = 'bg-info text-dark';
                                        }
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="fw-bold"><?= e($sub['student_name']) ?></td>
                                        <td>
                                            <span class="badge <?= $badgeClass ?> px-3 py-2 font-monospace fs-6 shadow-sm">
                                                <?= $maxSim ?>%
                                            </span>
                                        </td>
                                        <td class="fw-bold text-secondary"><?= e($sub['partner_name']) ?></td>
                                        <td>
                                            <small class="text-muted d-block">
                                                <strong>Tugas A:</strong> <?= e($sub['file_name']) ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <strong>Tugas B:</strong> <?= e($sub['partner_file_name']) ?>
                                            </small>
                                        </td>
                                        <td class="text-center text-danger fw-bold fs-6">
                                            <?= $sub['penalty'] > 0 ? '-' . $sub['penalty'] : '0' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
