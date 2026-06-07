<?php

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

function ensureDummyFilesDir(): string {
    $dir = __DIR__ . '/dummy_files/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function downloadDummyFile(string $url, string $filename): string {
    $dir = ensureDummyFilesDir();
    $path = $dir . $filename;

    if (!file_exists($path)) {
        $content = file_get_contents($url);
        if ($content !== false) {
            file_put_contents($path, $content);
        } else {
            // Fallback: create minimal file
            file_put_contents($path, "Dummy content for $filename");
        }
    }

    return $path;
}

function createSampleDocxFile(string $filename, string $title, string $content): string {
    $dir = ensureDummyFilesDir();
    $path = $dir . $filename;

    if (!file_exists($path)) {
        try {
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $section = $phpWord->addSection();

            // Add title
            $section->addText($title, ['bold' => true, 'size' => 16]);
            $section->addTextBreak(2);

            // Add content
            $section->addText($content, ['size' => 12]);

            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($path);
        } catch (Exception $e) {
            // Fallback: create simple text file
            file_put_contents($path, "$title\n\n$content");
        }
    }

    return $path;
}

function createSamplePdfFile(string $filename, string $title, string $content): string {
    $dir = ensureDummyFilesDir();
    $path = $dir . $filename;

    if (!file_exists($path)) {
        // Create simple PDF using FPDF if available, otherwise create text file
        $pdfContent = "%PDF-1.4\n";
        $pdfContent .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdfContent .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdfContent .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n";
        $pdfContent .= "xref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n0000000053 00000 n \n0000000102 00000 n \n";
        $pdfContent .= "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n171\n%%EOF\n";

        file_put_contents($path, $pdfContent);
    }

    return $path;
}

function createDummyFiles(): array {
    $files = [];

    // Download or create sample files
    $files['report_pdf'] = downloadDummyFile(
        'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
        'sample_report.pdf'
    );

    $files['image_jpg'] = downloadDummyFile(
        'https://picsum.photos/800/600',
        'sample_image.jpg'
    );

    // Create DOCX files for different subjects
    $files['report_basis_data_docx'] = createSampleDocxFile(
        'report_basis_data.docx',
        'Laporan Praktikum Basis Data',
        "Laporan ini berisi hasil praktikum basis data termasuk:\n1. Pembuatan database\n2. Implementasi relasi\n3. Query optimization\n4. Performance testing"
    );

    $files['report_jaringan_docx'] = createSampleDocxFile(
        'report_jaringan.docx',
        'Laporan Praktikum Jaringan Komputer',
        "Laporan jaringan komputer mencakup:\n1. Network configuration\n2. Routing setup\n3. Security implementation\n4. Performance monitoring"
    );

    $files['report_web_docx'] = createSampleDocxFile(
        'report_web.docx',
        'Laporan Praktikum Pemrograman Web',
        "Laporan pemrograman web berisi:\n1. Website development\n2. Frontend/backend integration\n3. Database connectivity\n4. Security features"
    );

    // Create text files
    $files['instructions_txt'] = ensureDummyFilesDir() . 'instructions.txt';
    if (!file_exists($files['instructions_txt'])) {
        file_put_contents($files['instructions_txt'],
            "INSTRUCTIONS FOR PRACTICUM\n\n" .
            "1. Read all materials before starting\n" .
            "2. Follow step-by-step procedures\n" .
            "3. Document all results\n" .
            "4. Submit before deadline\n" .
            "5. Include references if any"
        );
    }

    // Create sample code files
    $files['sample_code_php'] = ensureDummyFilesDir() . 'sample_code.php';
    if (!file_exists($files['sample_code_php'])) {
        file_put_contents($files['sample_code_php'],
            "<?php\n\n// Sample PHP code for web programming practicum\n" .
            "function connectToDatabase() {\n" .
            "    \$pdo = new PDO('mysql:host=localhost;dbname=prakchek', 'root', '');\n" .
            "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n" .
            "    return \$pdo;\n" .
            "}\n\n" .
            "function getStudents(\$classId) {\n" .
            "    \$pdo = connectToDatabase();\n" .
            "    \$stmt = \$pdo->prepare('SELECT * FROM users WHERE role = \"student\"');\n" .
            "    \$stmt->execute();\n" .
            "    return \$stmt->fetchAll(PDO::FETCH_ASSOC);\n" .
            "}\n"
        );
    }

    return $files;
}

function createUser(PDO $pdo, string $name, string $email, string $role): int {
    $password = password_hash('password123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $password, $role]);
    return (int)$pdo->lastInsertId();
}

function createClass(PDO $pdo, int $assistantId, string $className): int {
    $code = generateClassCode($pdo, 8);
    $stmt = $pdo->prepare("INSERT INTO classes (name, code, assistant_id) VALUES (?, ?, ?)");
    $stmt->execute([$className, $code, $assistantId]);
    return (int)$pdo->lastInsertId();
}

function createAnnouncement(PDO $pdo, int $classId, int $assistantId, string $title, string $content, array $attachments = []): int {
    $stmt = $pdo->prepare("INSERT INTO announcements (class_id, assistant_id, title, content, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$classId, $assistantId, $title, $content]);
    $announcementId = (int)$pdo->lastInsertId();

    foreach ($attachments as $attachment) {
        createFileRecord($pdo, 'announcement', $announcementId, $assistantId, $attachment['name'], $attachment['mime_type'], $attachment['content']);
    }

    return $announcementId;
}

function createAssignment(PDO $pdo, int $classId, int $assistantId, string $title, string $description, int $deadlineType, ?string $deadlineAt, string $category = 'regular', int $maxFiles = 1, string $allowedTypes = 'all'): int {
    $stmt = $pdo->prepare("INSERT INTO assignments (class_id, assistant_id, title, description, deadline_type, deadline_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$classId, $assistantId, $title, $description, $deadlineType, $deadlineAt]);
    return (int)$pdo->lastInsertId();
}

function createSubmission(PDO $pdo, int $assignmentId, int $studentId, string $status, ?string $textContent, int $isLate, ?string $submittedAt, array $attachments = [], ?float $grade = null, ?float $plagiarismPenalty = null, ?string $feedback = null): int {
    $stmt = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, text_content, is_late, status, submitted_at, grade, plagiarism_penalty, feedback, graded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $gradedAt = $grade !== null ? date('Y-m-d H:i:s') : null;
    $stmt->execute([$assignmentId, $studentId, $textContent, $isLate, $status, $submittedAt, $grade, $plagiarismPenalty, $feedback, $gradedAt]);

    $submissionId = (int)$pdo->lastInsertId();

    foreach ($attachments as $attachment) {
        createFileRecord($pdo, 'submission', $submissionId, $studentId, $attachment['name'], $attachment['mime_type'], $attachment['content']);
    }

    return $submissionId;
}

function createFileRecord(PDO $pdo, string $entityType, int $entityId, int $uploaderId, string $originalName, string $mimeType, string $content): int {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    if ($extension === '') {
        $extension = strtolower(substr($mimeType, strrpos($mimeType, '/') + 1));
    }

    $filename = uniqid('file_', true) . '.' . $extension;
    $year = date('Y');
    $month = date('m');
    $uploadDir = __DIR__ . "/uploads/{$entityType}s/{$year}/{$month}/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filePath = $uploadDir . $filename;
    file_put_contents($filePath, $content);

    $stmt = $pdo->prepare("INSERT INTO files (entity_type, entity_id, uploader_id, filename, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $entityType,
        $entityId,
        $uploaderId,
        $filename,
        $originalName,
        $mimeType,
        strlen($content)
    ]);

    return (int)$pdo->lastInsertId();
}

// Define assistants with unique characteristics
$assistants = [
    [
        'name' => 'Asprak Ilmu Data',
        'email' => 'asprak1@prakchek.local',
        'class_name' => 'Praktikum Basis Data',
        'style' => 'structured', // structured, practical, creative
        'focus' => 'theory'
    ],
    [
        'name' => 'Asprak Jaringan',
        'email' => 'asprak2@prakchek.local',
        'class_name' => 'Praktikum Jaringan Komputer',
        'style' => 'practical',
        'focus' => 'hands-on'
    ],
    [
        'name' => 'Asprak Web',
        'email' => 'asprak3@prakchek.local',
        'class_name' => 'Praktikum Pemrograman Web',
        'style' => 'creative',
        'focus' => 'project'
    ],
];

// Define students with different characteristics
$studentProfiles = [
    ['name' => 'Ahmad', 'type' => 'active', 'performance' => 'excellent'],
    ['name' => 'Budi', 'type' => 'average', 'performance' => 'good'],
    ['name' => 'Citra', 'type' => 'perfectionist', 'performance' => 'excellent'],
    ['name' => 'Dewi', 'type' => 'procrastinator', 'performance' => 'average'],
    ['name' => 'Eko', 'type' => 'struggling', 'performance' => 'poor'],
];

// Create dummy files
$dummyFiles = createDummyFiles();

try {
    $pdo->beginTransaction();

    echo "Creating comprehensive dummy data...\n";

    foreach ($assistants as $classIndex => $assistantData) {
        echo "Processing class: {$assistantData['class_name']}\n";

        // Create assistant
        $assistantId = createUser($pdo, $assistantData['name'], $assistantData['email'], 'assistant');
        $classId = createClass($pdo, $assistantId, $assistantData['class_name']);

        // Create students with unique profiles
        $studentIds = [];
        foreach ($studentProfiles as $studentIndex => $profile) {
            $studentNumber = ($classIndex * count($studentProfiles)) + $studentIndex + 1;
            $name = "{$profile['name']} Student{$studentNumber}";
            $email = "student{$studentNumber}@prakchek.local";
            $studentId = createUser($pdo, $name, $email, 'student');
            $studentIds[$studentId] = $profile;

            $stmt = $pdo->prepare("INSERT INTO class_members (class_id, user_id) VALUES (?, ?)");
            $stmt->execute([$classId, $studentId]);
        }

        // Create announcements with different attachments based on class focus
        echo "  Creating announcements...\n";

        createAnnouncement(
            $pdo,
            $classId,
            $assistantId,
            'Selamat Datang di ' . $assistantData['class_name'],
            "Halo semua! Selamat datang di kelas {$assistantData['class_name']}.\n\n" .
            "Saya {$assistantData['name']} akan membimbing praktikum ini. " .
            "Kelas ini berfokus pada {$assistantData['focus']} dengan gaya pengajaran {$assistantData['style']}.\n\n" .
            "Silakan perhatikan pengumuman dan tugas setiap minggu. Jangan ragu bertanya!",
            []
        );

        // Different attachments based on class type
        $announcementAttachments = [];
        if ($assistantData['focus'] === 'theory') {
            $announcementAttachments = [
                [
                    'name' => 'syllabus.pdf',
                    'mime_type' => 'application/pdf',
                    'content' => file_get_contents($dummyFiles['report_pdf']),
                ],
                [
                    'name' => 'schedule.docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'content' => file_get_contents($dummyFiles['report_basis_data_docx']),
                ]
            ];
        } elseif ($assistantData['focus'] === 'hands-on') {
            $announcementAttachments = [
                [
                    'name' => 'lab_instructions.txt',
                    'mime_type' => 'text/plain',
                    'content' => file_get_contents($dummyFiles['instructions_txt']),
                ],
                [
                    'name' => 'network_diagram.jpg',
                    'mime_type' => 'image/jpeg',
                    'content' => file_get_contents($dummyFiles['image_jpg']),
                ]
            ];
        } else { // project focus
            $announcementAttachments = [
                [
                    'name' => 'project_guidelines.docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'content' => file_get_contents($dummyFiles['report_web_docx']),
                ],
                [
                    'name' => 'sample_code.php',
                    'mime_type' => 'application/x-php',
                    'content' => file_get_contents($dummyFiles['sample_code_php']),
                ]
            ];
        }

        createAnnouncement(
            $pdo,
            $classId,
            $assistantId,
            'Materi dan Panduan Praktikum',
            "Berikut materi dan panduan untuk praktikum:\n\n" .
            "1. Silakan download semua lampiran\n" .
            "2. Pelajari materi sebelum praktikum\n" .
            "3. Siapkan tools yang diperlukan\n" .
            "4. Catat pertanyaan untuk diskusi\n\n" .
            "Deadline tugas akan diumumkan minggu depan.",
            $announcementAttachments
        );

        // Create assignments with different types and categories
        echo "  Creating assignments...\n";

        // Assignment 1: Different for each class
        $assignment1Titles = [
            'Tugas 1: Analisis Kasus Database',
            'Tugas 1: Konfigurasi Jaringan Dasar',
            'Tugas 1: Website Statis HTML/CSS'
        ];

        $assignment1Descriptions = [
            "Analisis kasus sistem database untuk toko online. Buat ERD, normalisasi, dan query.",
            "Konfigurasi jaringan sederhana dengan 3 device. Dokumentasi hasil konfigurasi.",
            "Buat website statis dengan 5 halaman menggunakan HTML5 dan CSS3."
        ];

        $assignment1Id = createAssignment(
            $pdo,
            $classId,
            $assistantId,
            $assignment1Titles[$classIndex],
            $assignment1Descriptions[$classIndex],
            2, // accept late
            date('Y-m-d H:i:s', strtotime('-1 week')),
            'individual',
            3,
            'pdf,docx,txt'
        );

        // Assignment 2: Group project
        $assignment2Id = createAssignment(
            $pdo,
            $classId,
            $assistantId,
            'Tugas Kelompok: Project Akhir',
            "Kerjakan project akhir secara berkelompok (2-3 orang).\n" .
            "Submit proposal, progress report, dan final report.\n" .
            "Presentasi akhir minggu depan.",
            3, // strict deadline
            date('Y-m-d H:i:s', strtotime('+2 weeks')),
            'group',
            5,
            'all'
        );

        // Assignment 3: Quiz/Exam
        $assignment3Id = createAssignment(
            $pdo,
            $classId,
            $assistantId,
            'Kuis Tengah Semester',
            "Kuis online tentang materi minggu 1-6.\n" .
            "Waktu: 90 menit\n" .
            "Tipe: Pilihan ganda dan essay\n" .
            "Tidak boleh terlambat submit!",
            3, // strict deadline
            date('Y-m-d H:i:s', strtotime('+3 days')),
            'exam',
            1,
            'txt'
        );

        // Assignment 4: No deadline (optional)
        $assignment4Id = createAssignment(
            $pdo,
            $classId,
            $assistantId,
            'Tugas Tambahan (Opsional)',
            "Tugas tambahan untuk nilai bonus.\n" .
            "Bebas deadline, kumpulkan kapan saja.\n" .
            "Buat dokumentasi lengkap.",
            1, // no deadline
            null,
            'optional',
            2,
            'pdf,docx'
        );

        // Create submissions based on student profiles
        echo "  Creating submissions...\n";

        $studentIdList = array_keys($studentIds);

        // Assignment 1 submissions (various statuses)
        foreach ($studentIdList as $index => $studentId) {
            $profile = $studentIds[$studentId];

            switch ($profile['type']) {
                case 'active':
                    // Submitted on time with good content - Already graded
                    createSubmission(
                        $pdo,
                        $assignment1Id,
                        $studentId,
                        'submitted',
                        "Laporan lengkap untuk {$assignment1Titles[$classIndex]}.\n\n" .
                        "Saya telah menyelesaikan semua requirement dengan baik.\n" .
                        "Hasil analisis menunjukkan optimalisasi yang efektif.",
                        0,
                        date('Y-m-d H:i:s', strtotime('-6 days')),
                        [
                            [
                                'name' => 'laporan_lengkap.pdf',
                                'mime_type' => 'application/pdf',
                                'content' => file_get_contents($dummyFiles['report_pdf']),
                            ]
                        ],
                        85.5,  // grade
                        0,     // plagiarism_penalty
                        "Kerja bagus! Analisis mendalam dan dokumentasi lengkap. Pertahankan!"  // feedback
                    );
                    break;

                case 'average':
                    // Submitted late with basic content - Graded with penalty
                    createSubmission(
                        $pdo,
                        $assignment1Id,
                        $studentId,
                        'submitted',
                        "Laporan tugas.\nSelesai sesuai instruksi.",
                        1,
                        date('Y-m-d H:i:s', strtotime('-2 days')),
                        [],
                        70.0,  // grade
                        5.0,   // plagiarism_penalty (terlambat)
                        "Konten terlalu dasar. Perlu lebih detail dan analisis. Juga terlambat submit."  // feedback
                    );
                    break;

                case 'perfectionist':
                    // Submitted on time with multiple files - Excellent grade
                    createSubmission(
                        $pdo,
                        $assignment1Id,
                        $studentId,
                        'submitted',
                        "Laporan detail dengan analisis mendalam.\n\n" .
                        "Executive summary:\n- Semua objectives tercapai\n- Optimasi berhasil\n- Dokumentasi lengkap\n\n" .
                        "Lampiran: laporan utama, appendix, dan source code.",
                        0,
                        date('Y-m-d H:i:s', strtotime('-5 days')),
                        [
                            [
                                'name' => 'main_report.docx',
                                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'content' => file_get_contents($dummyFiles['report_basis_data_docx']),
                            ],
                            [
                                'name' => 'appendix.pdf',
                                'mime_type' => 'application/pdf',
                                'content' => file_get_contents($dummyFiles['report_pdf']),
                            ],
                            [
                                'name' => 'diagram.jpg',
                                'mime_type' => 'image/jpeg',
                                'content' => file_get_contents($dummyFiles['image_jpg']),
                            ]
                        ],
                        95.0,  // grade
                        0,     // plagiarism_penalty
                        "Sangat luar biasa! Dokumentasi lengkap, analisis mendalam, dan presentasi profesional. Contoh terbaik!"  // feedback
                    );
                    break;

                case 'procrastinator':
                    // Draft (not submitted yet)
                    createSubmission(
                        $pdo,
                        $assignment1Id,
                        $studentId,
                        'draft',
                        "Masih dalam pengerjaan...",
                        0,
                        null,
                        []
                    );
                    break;

                case 'struggling':
                    // Submitted very late with minimal content - Low grade with plagiarism penalty
                    createSubmission(
                        $pdo,
                        $assignment1Id,
                        $studentId,
                        'submitted',
                        "Susah mengerjakan, mohon maklum.",
                        1,
                        date('Y-m-d H:i:s', strtotime('-1 day')),
                        [],
                        45.0,  // grade
                        15.0,  // plagiarism_penalty (terdeteksi plagiarisme)
                        "Konten terlalu minimal dan terlambat. Terdeteksi kesamaan dengan submission lain. Perlu perbaikan signifikan."  // feedback
                    );
                    break;
            }
        }

        // Assignment 2 submissions (group project - only some students)
        // Active and perfectionist students submit
        createSubmission(
            $pdo,
            $assignment2Id,
            $studentIdList[0], // active student
            'submitted',
            "Proposal project kelompok.\nAnggota: Ahmad, Citra\nJudul: Sistem Manajemen Praktikum",
            0,
            date('Y-m-d H:i:s', strtotime('+1 week')),
            [
                [
                    'name' => 'proposal_project.docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'content' => file_get_contents($dummyFiles['report_web_docx']),
                ]
            ],
            88.0,  // grade
            0,     // plagiarism_penalty
            "Proposal bagus dengan scope yang jelas. Timeline realistis."  // feedback
        );

        createSubmission(
            $pdo,
            $assignment2Id,
            $studentIdList[2], // perfectionist student
            'submitted',
            "Progress report minggu 1.\nPencapaian: 40% selesai\nKendala: sedikit",
            0,
            date('Y-m-d H:i:s', strtotime('+1 week + 1 day')),
            [
                [
                    'name' => 'progress_report.pdf',
                    'mime_type' => 'application/pdf',
                    'content' => file_get_contents($dummyFiles['report_pdf']),
                ]
            ]
        );

        // Others are draft or not submitted
        createSubmission(
            $pdo,
            $assignment2Id,
            $studentIdList[1], // average student
            'draft',
            "Belum mulai kelompok...",
            0,
            null,
            []
        );

        // Assignment 3 submissions (quiz - most submit)
        foreach ([0, 1, 2, 3] as $studentIndex) { // first 4 students
            createSubmission(
                $pdo,
                $assignment3Id,
                $studentIdList[$studentIndex],
                'submitted',
                "Jawaban kuis:\n1. A\n2. C\n3. B\n4. D\n5. A\n\nEssay: ...",
                0,
                date('Y-m-d H:i:s', strtotime('+2 days')),
                []
            );
        }

        // Struggling student submits late for quiz
        createSubmission(
            $pdo,
            $assignment3Id,
            $studentIdList[4],
            'submitted',
            "Terlambat karena masalah teknis.\nJawaban: ...",
            1,
            date('Y-m-d H:i:s', strtotime('+3 days + 1 hour')),
            []
        );

        // Assignment 4 submissions (optional - only active students)
        createSubmission(
            $pdo,
            $assignment4Id,
            $studentIdList[0], // active
            'submitted',
            "Tugas bonus: analisis tambahan",
            0,
            date('Y-m-d H:i:s', strtotime('-3 days')),
            [
                [
                    'name' => 'bonus_analysis.docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'content' => file_get_contents($dummyFiles['report_jaringan_docx']),
                ]
            ]
        );

        createSubmission(
            $pdo,
            $assignment4Id,
            $studentIdList[2], // perfectionist
            'submitted',
            "Tugas bonus dengan implementasi advanced",
            0,
            date('Y-m-d H:i:s', strtotime('-1 day')),
            [
                [
                    'name' => 'advanced_implementation.pdf',
                    'mime_type' => 'application/pdf',
                    'content' => file_get_contents($dummyFiles['report_pdf']),
                ]
            ]
        );

        echo "  Completed class: {$assistantData['class_name']}\n";
    }

    $pdo->commit();

    echo "\n========================================\n";
    echo "COMPREHENSIVE DUMMY DATA CREATED SUCCESSFULLY!\n";
    echo "========================================\n\n";

    echo "Summary:\n";
    echo "- 3 Assistants (Asprak) with different teaching styles\n";
    echo "- 15 Students with 5 unique profiles each\n";
    echo "- 3 Classes with different focus areas\n";
    echo "- 8 Announcements with varied attachments\n";
    echo "- 12 Assignments (4 per class) with different types\n";
    echo "- 40+ Submissions with various statuses and file combinations\n";
    echo "- Real file testing with PDF, DOCX, JPG, TXT, PHP formats\n";
    echo "\nStudent profiles tested:\n";
    echo "1. Active (excellent) - submits on time with quality work\n";
    echo "2. Average (good) - basic submissions, sometimes late\n";
    echo "3. Perfectionist (excellent) - detailed work with multiple files\n";
    echo "4. Procrastinator (average) - often in draft status\n";
    echo "5. Struggling (poor) - minimal submissions, often late\n";
    echo "\nFile types tested:\n";
    echo "- PDF documents\n";
    echo "- DOCX Word files\n";
    echo "- JPG images\n";
    echo "- TXT text files\n";
    echo "- PHP code files\n";
    echo "\nAssignment types tested:\n";
    echo "- Individual assignments\n";
    echo "- Group projects\n";
    echo "- Exams/Quizzes\n";
    echo "- Optional bonus work\n";
    echo "\nDeadline scenarios tested:\n";
    echo "- No deadline\n";
    echo "- Accept late submissions\n";
    echo "- Strict deadlines\n";
    echo "\nAll users password: password123\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
    exit(1);
}
?>