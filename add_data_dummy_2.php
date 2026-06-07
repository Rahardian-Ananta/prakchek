<?php

/**
 * ============================================================
 * COMPREHENSIVE DUMMY DATA GENERATOR — LMS PrakChek
 * ============================================================
 * Versi : 2.0
 * Deskripsi : 10 Kelas, 30 Aspek Penilaian, 50 Mahasiswa,
 *             Submissions dengan 5 skenario profil perilaku.
 * ============================================================
 */

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

// ============================================================
// UTILITY: DUMMY FILE HELPERS
// ============================================================

function ensureDummyFilesDir(): string {
    $dir = __DIR__ . '/dummy_files/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function downloadDummyFile(string $url, string $filename): string {
    $dir  = ensureDummyFilesDir();
    $path = $dir . $filename;
    if (!file_exists($path)) {
        $content = file_get_contents($url);
        file_put_contents($path, $content !== false ? $content : "Dummy content for $filename");
    }
    return $path;
}

function createSampleDocxFile(string $filename, string $title, string $content): string {
    $dir  = ensureDummyFilesDir();
    $path = $dir . $filename;
    if (!file_exists($path)) {
        try {
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $section = $phpWord->addSection();
            $section->addText($title,   ['bold' => true, 'size' => 16]);
            $section->addTextBreak(2);
            $section->addText($content, ['size' => 12]);
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($path);
        } catch (Exception $e) {
            file_put_contents($path, "$title\n\n$content");
        }
    }
    return $path;
}

function createSamplePdfFile(string $filename): string {
    $dir  = ensureDummyFilesDir();
    $path = $dir . $filename;
    if (!file_exists($path)) {
        $pdf  = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n";
        $pdf .= "xref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n";
        $pdf .= "0000000053 00000 n \n0000000102 00000 n \n";
        $pdf .= "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n171\n%%EOF\n";
        file_put_contents($path, $pdf);
    }
    return $path;
}

function createDummyFiles(): array {
    $f = [];

    $f['report_pdf'] = downloadDummyFile(
        'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
        'sample_report.pdf'
    );
    $f['image_jpg'] = downloadDummyFile(
        'https://picsum.photos/800/600',
        'sample_image.jpg'
    );

    // DOCX per topik
    foreach ([
        ['basis_data',     'Laporan Basis Data',       "ERD, DDL, normalisasi, dan query optimization."],
        ['rpl',            'Laporan RPL',               "Analisis kebutuhan, Class Diagram, dan Testing."],
        ['kecerdasan_ai',  'Laporan Kecerdasan Buatan', "Agen cerdas, pencarian heuristik, jaringan saraf."],
        ['web',            'Laporan Pemrograman Web',   "HTML5, CSS3, PHP backend, dan database."],
        ['sisop',          'Laporan Sistem Operasi',    "Scheduling, manajemen memori, deadlock."],
        ['keamanan',       'Laporan Keamanan Informasi',"Enkripsi, firewall, analisis risiko."],
        ['jaringan',       'Laporan Jaringan Komputer', "Routing, VLAN, monitoring jaringan."],
        ['algoritma',      'Laporan Analisis Algoritma',"Big-O, sorting, graph traversal."],
        ['imk',            'Laporan IMK',               "Usability testing, wireframe, prototype."],
        ['komputasi',      'Laporan Kecerdasan Komputasi',"Fuzzy, ANN, GA, dan optimasi."],
    ] as [$key, $title, $body]) {
        $f["docx_{$key}"] = createSampleDocxFile("report_{$key}.docx", $title, $body);
    }

    // TXT dan kode
    $f['instructions_txt'] = ensureDummyFilesDir() . 'instructions.txt';
    if (!file_exists($f['instructions_txt'])) {
        file_put_contents($f['instructions_txt'],
            "PETUNJUK PRAKTIKUM\n\n1. Baca seluruh modul\n2. Ikuti prosedur langkah demi langkah\n" .
            "3. Dokumentasikan semua hasil\n4. Submit sebelum deadline\n5. Cantumkan referensi"
        );
    }

    $f['sample_php'] = ensureDummyFilesDir() . 'sample_code.php';
    if (!file_exists($f['sample_php'])) {
        file_put_contents($f['sample_php'],
            "<?php\n// Sample PHP code\nfunction connectDB() {\n" .
            "    return new PDO('mysql:host=localhost;dbname=prakchek', 'root', '');\n}\n"
        );
    }

    $f['sample_sql'] = ensureDummyFilesDir() . 'schema.sql';
    if (!file_exists($f['sample_sql'])) {
        file_put_contents($f['sample_sql'],
            "-- Schema dummy\nCREATE TABLE users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100));\n"
        );
    }

    $f['sample_zip'] = ensureDummyFilesDir() . 'project_source.zip';
    if (!file_exists($f['sample_zip'])) {
        file_put_contents($f['sample_zip'], "PK\x05\x06" . str_repeat("\x00", 18)); // minimal zip
    }

    return $f;
}

// ============================================================
// CRUD HELPERS (dipertahankan dari script lama)
// ============================================================

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

function createAspect(PDO $pdo, int $classId, string $name): int {
    $stmt = $pdo->prepare("INSERT INTO aspects (class_id, name) VALUES (?, ?)");
    $stmt->execute([$classId, $name]);
    return (int)$pdo->lastInsertId();
}

function linkAspectStudent(PDO $pdo, int $aspectId, int $studentId): void {
    $stmt = $pdo->prepare("INSERT IGNORE INTO aspect_students (aspect_id, student_id) VALUES (?, ?)");
    $stmt->execute([$aspectId, $studentId]);
}

function createAnnouncement(PDO $pdo, int $classId, int $assistantId, string $title, string $content, array $attachments = []): int {
    $stmt = $pdo->prepare("INSERT INTO announcements (class_id, assistant_id, title, content, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$classId, $assistantId, $title, $content]);
    $id = (int)$pdo->lastInsertId();
    foreach ($attachments as $a) {
        createFileRecord($pdo, 'announcement', $id, $assistantId, $a['name'], $a['mime_type'], $a['content']);
    }
    return $id;
}

function createAssignment(
    PDO $pdo, int $classId, int $assistantId,
    string $title, string $description,
    int $deadlineType, ?string $deadlineAt,
    string $category = 'individual', int $maxFiles = 1, string $allowedTypes = 'all'
): int {
    $stmt = $pdo->prepare(
        "INSERT INTO assignments (class_id, assistant_id, title, description, deadline_type, deadline_at, category, max_files, allowed_types, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$classId, $assistantId, $title, $description, $deadlineType, $deadlineAt, $category, $maxFiles, $allowedTypes]);
    return (int)$pdo->lastInsertId();
}

function createSubmission(
    PDO $pdo, int $assignmentId, int $studentId,
    string $status, ?string $textContent,
    int $isLate, ?string $submittedAt,
    array $attachments = [],
    ?float $grade = null, ?float $plagiarismPenalty = null, ?string $feedback = null
): int {
    $gradedAt = $grade !== null ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare(
        "INSERT INTO submissions (assignment_id, student_id, text_content, is_late, status, submitted_at, grade, plagiarism_penalty, feedback, graded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$assignmentId, $studentId, $textContent, $isLate, $status, $submittedAt, $grade, $plagiarismPenalty, $feedback, $gradedAt]);
    $subId = (int)$pdo->lastInsertId();
    foreach ($attachments as $a) {
        createFileRecord($pdo, 'submission', $subId, $studentId, $a['name'], $a['mime_type'], $a['content']);
    }
    return $subId;
}

function createFileRecord(PDO $pdo, string $entityType, int $entityId, int $uploaderId, string $originalName, string $mimeType, string $content): int {
    $ext      = pathinfo($originalName, PATHINFO_EXTENSION) ?: strtolower(substr($mimeType, strrpos($mimeType, '/') + 1));
    $filename = uniqid('file_', true) . '.' . $ext;
    $year     = date('Y'); $month = date('m');
    $dir      = __DIR__ . "/uploads/{$entityType}s/{$year}/{$month}/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($dir . $filename, $content);

    $stmt = $pdo->prepare(
        "INSERT INTO files (entity_type, entity_id, uploader_id, filename, original_name, mime_type, file_size)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$entityType, $entityId, $uploaderId, $filename, $originalName, $mimeType, strlen($content)]);
    return (int)$pdo->lastInsertId();
}

// ============================================================
// DATA DEFINITIONS — 10 KELAS + 30 ASPEK (3 per kelas)
// ============================================================

$classDefinitions = [
    [
        'assistant_name'  => 'Asprak Basis Data',
        'assistant_email' => 'asprak.basisdata@prakchek.local',
        'class_name'      => 'Basis Data 1',
        'style'           => 'structured',
        'focus'           => 'theory',
        'docx_key'        => 'docx_basis_data',
        'aspects'         => ['Desain ERD', 'Query DDL & DML', 'Normalisasi Database'],
        'assignments'     => [
            ['title' => 'Rancang ERD Sistem Perpustakaan',  'desc' => "Buat ERD lengkap untuk sistem perpustakaan: entitas, atribut, dan relasi.\nSertakan kardinalitas dan kamus data.",                              'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project Akhir: Sistem Informasi', 'desc' => "Bangun sistem informasi berbasis database. Proposal + ER Diagram + implementasi SQL lengkap.\nPresentasi akhir semester.",               'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'Kuis: Normalisasi 1NF–3NF',       'desc' => "Kuis tertulis tentang normalisasi. Waktu 60 menit.\nJawab semua soal, tidak boleh terlambat.",                                          'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Tugas Opsional: Query Lanjut',    'desc' => "Eksplorasi JOIN, subquery, dan stored procedure. Bebas deadline, kumpulkan kapan saja untuk nilai bonus.",                               'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
    [
        'assistant_name'  => 'Asprak RPL',
        'assistant_email' => 'asprak.rpl@prakchek.local',
        'class_name'      => 'Rekayasa Perangkat Lunak B',
        'style'           => 'practical',
        'focus'           => 'hands-on',
        'docx_key'        => 'docx_rpl',
        'aspects'         => ['Analisis Kebutuhan', 'Class Diagram UML', 'Testing & QA'],
        'assignments'     => [
            ['title' => 'Spesifikasi Kebutuhan Perangkat Lunak', 'desc' => "Tulis SRS mengikuti standar IEEE 830. Sertakan use case dan diagram konteks.",                                                       'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project: Aplikasi Desktop Sederhana',  'desc' => "Bangun aplikasi CRUD sederhana menggunakan Java/Python. Sertakan diagram, source code, dan dokumentasi.",                            'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'Ujian Tengah Semester RPL',            'desc' => "UTS mencakup SDLC, model proses, dan analisis sistem. Waktu 90 menit. Strict deadline.",                                             'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Opsional: Review Paper Agile',         'desc' => "Buat review paper metodologi Agile/Scrum. Nilai bonus. Tidak ada deadline.",                                                         'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
    [
        'assistant_name'  => 'Asprak Kecerdasan Komputasi',
        'assistant_email' => 'asprak.kompkomputasi@prakchek.local',
        'class_name'      => 'Kecerdasan Komputasi A',
        'style'           => 'analytical',
        'focus'           => 'theory',
        'docx_key'        => 'docx_komputasi',
        'aspects'         => ['Fuzzy Logic', 'Artificial Neural Network', 'Genetic Algorithm'],
        'assignments'     => [
            ['title' => 'Implementasi Fuzzy Inference System',  'desc' => "Implementasikan FIS Mamdani untuk studi kasus penilaian kinerja karyawan. Sertakan grafik membership function.",                     'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project Akhir: Model Prediksi ANN',   'desc' => "Bangun model ANN untuk prediksi data. Training, validasi, dan evaluasi akurasi. Laporan lengkap.",                                    'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'Kuis: Teori Optimasi & GA',           'desc' => "Kuis tentang genetic algorithm dan particle swarm optimization. 45 menit. Strict.",                                                   'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Opsional: Eksplorasi Deep Learning',  'desc' => "Implementasi CNN/RNN sederhana menggunakan TensorFlow/PyTorch. Nilai tambahan.",                                                       'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
    [
        'assistant_name'  => 'Asprak Web',
        'assistant_email' => 'asprak.web@prakchek.local',
        'class_name'      => 'Pemrograman Web',
        'style'           => 'creative',
        'focus'           => 'project',
        'docx_key'        => 'docx_web',
        'aspects'         => ['Frontend HTML/CSS/JS', 'Backend PHP & MySQL', 'Keamanan Aplikasi Web'],
        'assignments'     => [
            ['title' => 'Buat Landing Page Responsif',          'desc' => "Desain dan kembangkan landing page responsif menggunakan HTML5, CSS3, dan JavaScript murni.\nDokumentasikan semua fitur.",            'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project: Aplikasi Web Full-Stack',     'desc' => "Bangun aplikasi web dengan CRUD, autentikasi, dan database MySQL. Deploy ke server lokal.\nSertakan source code dan dokumentasi.",    'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'UTS: Analisis Kode & Debugging',      'desc' => "Ujian praktikum: debug kode yang diberikan, tambahkan fitur keamanan dasar. 90 menit.",                                               'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Opsional: REST API dengan PHP',        'desc' => "Buat REST API sederhana menggunakan PHP native. Dokumentasi endpoint dengan Postman.",                                               'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
    [
        'assistant_name'  => 'Asprak Sistem Operasi',
        'assistant_email' => 'asprak.sisop@prakchek.local',
        'class_name'      => 'Sistem Operasi',
        'style'           => 'structured',
        'focus'           => 'theory',
        'docx_key'        => 'docx_sisop',
        'aspects'         => ['Scheduling Process', 'Manajemen Memori', 'Deadlock & Sinkronisasi'],
        'assignments'     => [
            ['title' => 'Simulasi Algoritma Penjadwalan CPU',   'desc' => "Implementasikan dan bandingkan FCFS, SJF, dan Round Robin. Hitung rata-rata waiting time.\nBuat laporan perbandingan.",              'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project Akhir: Mini Shell Linux',      'desc' => "Buat mini shell sederhana menggunakan C yang mendukung perintah dasar, piping, dan redirection.",                                    'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'Kuis: Deadlock & Memori Virtual',      'desc' => "Kuis teori tentang kondisi deadlock, algoritma banker, dan paging. 60 menit. Strict deadline.",                                      'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Opsional: Analisis Kernel Linux',      'desc' => "Review dan analisis modul kernel Linux. Tuliskan cara kerja sistem file ext4. Nilai bonus.",                                          'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
    [
        'assistant_name'  => 'Asprak Keamanan Informasi',
        'assistant_email' => 'asprak.keamanan@prakchek.local',
        'class_name'      => 'Keamanan Informasi',
        'style'           => 'practical',
        'focus'           => 'hands-on',
        'docx_key'        => 'docx_keamanan',
        'aspects'         => ['Kriptografi', 'Analisis Risiko', 'Ethical Hacking Dasar'],
        'assignments'     => [
            ['title' => 'Implementasi Enkripsi AES & RSA',      'desc' => "Implementasikan AES-256 dan RSA untuk mengenkripsi file teks. Bandingkan performa keduanya.\nSertakan source code dan analisis.",     'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project Akhir: Audit Keamanan Sistem', 'desc' => "Lakukan vulnerability assessment pada sistem yang ditentukan. Gunakan tools standar (Nmap, Nessus).\nLaporan executive summary.",     'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'Ujian: Analisis Malware & Forensik',   'desc' => "Ujian praktis: analisis sampel malware di sandbox, identifikasi IOC. 90 menit. Strict.",                                             'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Opsional: CTF Challenge Writeup',      'desc' => "Selesaikan minimal 3 tantangan CTF dan tulis writeup lengkap. Nilai bonus ekstra.",                                                   'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
    [
        'assistant_name'  => 'Asprak Jaringan',
        'assistant_email' => 'asprak.jaringan@prakchek.local',
        'class_name'      => 'Jaringan Komputer A',
        'style'           => 'practical',
        'focus'           => 'hands-on',
        'docx_key'        => 'docx_jaringan',
        'aspects'         => ['Konfigurasi Routing', 'VLAN & Subnetting', 'Monitoring & Troubleshooting'],
        'assignments'     => [
            ['title' => 'Konfigurasi Routing Statis & Dinamis', 'desc' => "Konfigurasi topologi jaringan dengan 3 router menggunakan RIP dan OSPF di Cisco Packet Tracer.\nSertakan file .pkt dan laporan.",   'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project Akhir: Desain Jaringan Kampus','desc' => "Desain jaringan kampus kecil: VLAN, inter-VLAN routing, DHCP, dan keamanan. Simulasikan di Packet Tracer.",                          'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'Kuis: Subnetting & Protocol',         'desc' => "Kuis hitung subnetting CIDR dan pilihan ganda tentang protokol TCP/IP. 45 menit. Strict.",                                            'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Opsional: Analisis Paket Wireshark',  'desc' => "Capture dan analisis paket network menggunakan Wireshark. Identifikasi protokol yang digunakan. Nilai bonus.",                        'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
    [
        'assistant_name'  => 'Asprak Algoritma',
        'assistant_email' => 'asprak.algoritma@prakchek.local',
        'class_name'      => 'Analisis Algoritma',
        'style'           => 'analytical',
        'focus'           => 'theory',
        'docx_key'        => 'docx_algoritma',
        'aspects'         => ['Kompleksitas Waktu & Ruang', 'Dynamic Programming', 'Graph & Greedy'],
        'assignments'     => [
            ['title' => 'Analisis Kompleksitas Sorting',        'desc' => "Implementasikan Bubble, Merge, dan Quick Sort. Hitung kompleksitas best/worst/average case.\nBuat grafik perbandingan runtime.",       'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project Akhir: Penyelesaian TSP',      'desc' => "Selesaikan Travelling Salesman Problem menggunakan DP dan Greedy. Bandingkan akurasi dan runtime.",                                   'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'Kuis: Big-O & Recurrence Relation',   'desc' => "Kuis tentang notasi asimptotik, master theorem, dan analisis rekursi. 60 menit. Tidak boleh terlambat.",                              'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Opsional: Implementasi Dijkstra',      'desc' => "Implementasikan Dijkstra dan A* untuk shortest path. Visualisasikan hasilnya. Nilai bonus.",                                          'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
    [
        'assistant_name'  => 'Asprak IMK',
        'assistant_email' => 'asprak.imk@prakchek.local',
        'class_name'      => 'Interaksi Manusia Komputer',
        'style'           => 'creative',
        'focus'           => 'project',
        'docx_key'        => 'docx_imk',
        'aspects'         => ['Prototyping & Wireframe', 'Usability Testing', 'Desain Aksesibilitas'],
        'assignments'     => [
            ['title' => 'Wireframe Aplikasi Mobile',            'desc' => "Buat wireframe low-fidelity dan high-fidelity untuk aplikasi mobile pilihan Anda menggunakan Figma/Adobe XD.\nSertakan user flow.",   'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project Akhir: Prototype Interaktif',  'desc' => "Bangun prototype interaktif dan lakukan usability testing dengan minimal 5 responden. Laporan lengkap dengan SUS score.",              'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'Kuis: Prinsip Desain & Heuristik',    'desc' => "Kuis tentang 10 heuristik Nielsen dan prinsip Gestalt. 45 menit. Strict deadline.",                                                   'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Opsional: Audit Aksesibilitas WCAG',  'desc' => "Lakukan audit aksesibilitas website berdasarkan standar WCAG 2.1. Nilai bonus.",                                                       'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
    [
        'assistant_name'  => 'Asprak AI',
        'assistant_email' => 'asprak.ai@prakchek.local',
        'class_name'      => 'Kecerdasan Buatan',
        'style'           => 'analytical',
        'focus'           => 'theory',
        'docx_key'        => 'docx_kecerdasan_ai',
        'aspects'         => ['Agen & Pencarian Heuristik', 'Machine Learning Dasar', 'Natural Language Processing'],
        'assignments'     => [
            ['title' => 'Implementasi Algoritma A* Search',     'desc' => "Implementasikan A* untuk pathfinding pada peta grid. Bandingkan dengan BFS dan DFS.\nAnalisis kompleksitas dan optimasi heuristik.",  'cat' => 'individual', 'dt' => 2, 'week' => -1, 'max' => 3, 'types' => 'pdf,docx,zip'],
            ['title' => 'Project Akhir: Chatbot NLP Sederhana', 'desc' => "Bangun chatbot sederhana menggunakan rule-based dan ML. Training dengan dataset lokal.\nEvaluasi dengan confusion matrix.",            'cat' => 'group',      'dt' => 3, 'week' =>  2, 'max' => 5, 'types' => 'zip,rar,pdf'],
            ['title' => 'Kuis: Teori AI & ML',                 'desc' => "Kuis teori: Turing test, supervised vs unsupervised, bias-variance tradeoff. 60 menit. Strict.",                                      'cat' => 'exam',       'dt' => 3, 'week' =>  0, 'max' => 1, 'types' => 'txt,pdf'],
            ['title' => 'Opsional: Kaggle Mini Challenge',      'desc' => "Ikuti kompetisi Kaggle pilihan dan submit notebook analisis. Skor leaderboard dinilai. Nilai bonus.",                                  'cat' => 'optional',   'dt' => 1, 'week' =>  0, 'max' => 2, 'types' => 'pdf,docx'],
        ],
    ],
];

// ============================================================
// DATA DEFINITIONS — 50 MAHASISWA (5 profil × 10 orang)
// ============================================================

$studentBaseNames = [
    'active'         => ['Ahmad','Aulia','Arif','Andini','Agus','Adi','Ayu','Anisa','Aris','Amira'],
    'average'        => ['Budi','Bagas','Bella','Bagus','Bintang','Bunga','Bayu','Berlian','Bima','Bela'],
    'perfectionist'  => ['Citra','Cahya','Candra','Cindy','Cahyani','Chandra','Cristal','Cintia','Candra','Cahyo'],
    'procrastinator' => ['Dewi','Dani','Dika','Dinda','Donny','Desi','Dion','Diana','Damar','Dinda'],
    'struggling'     => ['Eko','Elsa','Eris','Enda','Erlita','Erwin','Elly','Enrico','Enggar','Elda'],
];

// Variasi feedback per profil
$feedbackPool = [
    'active' => [
        "Kerja bagus! Analisis sangat mendalam dan dokumentasi lengkap. Pertahankan prestasi ini!",
        "Luar biasa! Semua requirement terpenuhi dengan sangat baik. Sangat memuaskan!",
        "Excellent! Laporan terstruktur dengan baik, argumen kuat. Teruskan!",
        "Sangat bagus! Tepat waktu dan konten berkualitas tinggi. Jadi contoh teman-teman.",
        "Outstanding! Pemahaman mendalam terhadap materi. Nilai sempurna layak kamu dapatkan.",
    ],
    'average' => [
        "Konten cukup, namun perlu analisis yang lebih mendalam. Deadline hampir terlewat.",
        "Tugas memenuhi syarat minimum. Saran: perbanyak referensi dan perdalaman materi.",
        "Ada usaha yang cukup, tapi masih banyak ruang untuk perbaikan pada aspek analitis.",
        "Laporan dasar sudah ada. Perlu lebih sistematis dan tidak terlambat mengumpulkan.",
        "Cukup baik, namun konsistensi perlu ditingkatkan. Usahakan lebih tepat waktu.",
    ],
    'perfectionist' => [
        "Sempurna! Laporan sangat komprehensif, sumber kode lengkap, dan presentasi profesional. Contoh terbaik!",
        "Luar biasa! Semua lampiran lengkap, analisis mendalam, dan format sangat rapi.",
        "Nilai terbaik. Dokumentasi sangat detail, diagram akurat, dan penjelasan sangat jelas.",
        "Sangat memuaskan! Kamu melampaui ekspektasi. Semua file tersusun dengan sangat baik.",
        "Top performer! Laporan dan source code sangat berkualitas. Patut diacungi jempol.",
    ],
    'struggling' => [
        "Terlambat signifikan dan konten sangat minim. Terdeteksi kesamaan tinggi dengan submission lain (plagiarisme). Perlu konsultasi segera.",
        "Nilai sangat rendah karena keterlambatan ekstrem dan indikasi menyontek. Harap bertanggung jawab.",
        "Konten tidak memenuhi standar minimum. Terdeteksi copy-paste. Peringatan keras diberikan.",
        "Kumpul sangat terlambat dan hasil tidak memuaskan. Plagiarisme terdeteksi. Harap bicara dengan dosen.",
        "Usaha ada tapi sangat terlambat dan terindikasi plagiasi. Perlu bimbingan intensif.",
    ],
];

// ============================================================
// MAIN SCRIPT EXECUTION
// ============================================================

$dummyFiles = createDummyFiles();

try {
    $pdo->beginTransaction();
    echo "Generating comprehensive dummy data...\n\n";

    // ---- Step 1: Create 50 unique students ----
    echo "[1/3] Creating 50 unique students...\n";
    $allStudents = []; // ['id' => X, 'type' => '...']
    $counter = 1;
    foreach ($studentBaseNames as $type => $names) {
        foreach ($names as $name) {
            $email = 'student' . str_pad($counter, 2, '0', STR_PAD_LEFT) . '@prakchek.local';
            $id = createUser($pdo, "{$name} Mahasiswa", $email, 'student');
            $allStudents[] = ['id' => $id, 'type' => $type];
            $counter++;
        }
    }
    // Shuffle for random distribution
    shuffle($allStudents);
    echo "   50 students created.\n\n";

    // ---- Step 2: Create 10 classes with aspects & assignments ----
    echo "[2/3] Creating 10 classes, 30 aspects, assignments, and submissions...\n";

    // Split students for distribution: each aspect needs 10 students
    // Total 30 aspects × 10 students = 300 aspect-student links
    // We have 50 students, so each student appears in ~6 aspects on average
    $studentIdPool = array_column($allStudents, 'id');
    $studentTypeMap = array_column($allStudents, 'type', 'id');

    foreach ($classDefinitions as $ci => $cls) {
        echo "  Processing [{$cls['class_name']}]...\n";

        // Create assistant & class
        $assistantId = createUser($pdo, $cls['assistant_name'], $cls['assistant_email'], 'assistant');
        $classId     = createClass($pdo, $assistantId, $cls['class_name']);

        // ---- Announcements (2 per class = 20 total) ----
        createAnnouncement($pdo, $classId, $assistantId,
            "Selamat Datang di {$cls['class_name']}",
            "Halo mahasiswa! Saya {$cls['assistant_name']}, pembimbing praktikum {$cls['class_name']}.\n\n" .
            "Kelas ini menggunakan pendekatan {$cls['style']} dengan fokus pada {$cls['focus']}.\n" .
            "Perhatikan setiap pengumuman dan kumpulkan tugas tepat waktu. Semangat!",
            []
        );

        $announcementAttachments = match ($cls['focus']) {
            'theory'   => [
                ['name' => 'silabus.pdf',          'mime_type' => 'application/pdf',                                                              'content' => file_get_contents($dummyFiles['report_pdf'])],
                ['name' => 'jadwal_kuliah.docx',   'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',      'content' => file_get_contents($dummyFiles[$cls['docx_key']])],
            ],
            'hands-on' => [
                ['name' => 'panduan_lab.txt',      'mime_type' => 'text/plain',                                                                   'content' => file_get_contents($dummyFiles['instructions_txt'])],
                ['name' => 'topologi.jpg',         'mime_type' => 'image/jpeg',                                                                   'content' => file_get_contents($dummyFiles['image_jpg'])],
            ],
            default    => [
                ['name' => 'panduan_project.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',      'content' => file_get_contents($dummyFiles[$cls['docx_key']])],
                ['name' => 'contoh_kode.php',      'mime_type' => 'application/x-php',                                                            'content' => file_get_contents($dummyFiles['sample_php'])],
            ],
        };

        createAnnouncement($pdo, $classId, $assistantId,
            "Materi & Panduan {$cls['class_name']}",
            "Berikut materi dan panduan untuk semester ini:\n" .
            "1. Download semua lampiran\n2. Pelajari sebelum pertemuan\n3. Siapkan tools yang diperlukan\n4. Catat pertanyaan",
            $announcementAttachments
        );

        // ---- Aspects (3 per class = 30 total) ----
        $aspectIds = [];
        foreach ($cls['aspects'] as $aspectName) {
            $aspectIds[] = createAspect($pdo, $classId, $aspectName);
        }

        // ---- Distribute 10 students per aspect ----
        // Rotate the student pool so each class gets different students
        $rotatedPool = array_slice($studentIdPool, ($ci * 5) % count($studentIdPool));
        $rotatedPool = array_merge($rotatedPool, array_slice($studentIdPool, 0, ($ci * 5) % count($studentIdPool)));

        $aspectStudentMap = []; // aspectId => [studentIds]
        foreach ($aspectIds as $ai => $aspectId) {
            // Pick 10 students starting from offset, wrapping around
            $offset    = ($ai * 10 + $ci * 3) % count($rotatedPool);
            $picked    = [];
            for ($s = 0; $s < 10; $s++) {
                $picked[] = $rotatedPool[($offset + $s) % count($rotatedPool)];
            }
            // Ensure uniqueness within this aspect
            $picked = array_unique($picked);
            while (count($picked) < 10) {
                $extra = $rotatedPool[array_rand($rotatedPool)];
                if (!in_array($extra, $picked)) $picked[] = $extra;
            }
            $picked = array_slice($picked, 0, 10);
            $aspectStudentMap[$aspectId] = $picked;
            foreach ($picked as $sid) {
                linkAspectStudent($pdo, $aspectId, $sid);
            }
        }

        // Collect class students (union of all aspect students)
        $classStudentIds = array_unique(array_merge(...array_values($aspectStudentMap)));

        // ---- Assignments (4 per class = 40 total) ----
        $assignmentIds = [];
        foreach ($cls['assignments'] as $ai => $asgn) {
            $deadlineAt = match ($asgn['dt']) {
                1 => null,
                2 => date('Y-m-d H:i:s', strtotime("{$asgn['week']} week")),
                3 => date('Y-m-d H:i:s', strtotime('+3 days')),
            };

            $assignmentIds[] = createAssignment(
                $pdo, $classId, $assistantId,
                $asgn['title'], $asgn['desc'],
                $asgn['dt'], $deadlineAt,
                $asgn['cat'], $asgn['max'], $asgn['types']
            );
        }

        [$asgn1Id, $asgn2Id, $asgn3Id, $asgn4Id] = $assignmentIds;

        // ---- Submissions per student per assignment ----
        foreach ($classStudentIds as $sid) {
            $profile = $studentTypeMap[$sid] ?? 'average';

            // Helper: file set per submission type
            $pdfFile    = ['name' => 'laporan.pdf',       'mime_type' => 'application/pdf',                                                         'content' => file_get_contents($dummyFiles['report_pdf'])];
            $docxFile   = ['name' => 'laporan.docx',      'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',   'content' => file_get_contents($dummyFiles[$cls['docx_key']])];
            $zipFile    = ['name' => 'source_code.zip',   'mime_type' => 'application/zip',                                                           'content' => file_get_contents($dummyFiles['sample_zip'])];
            $phpFile    = ['name' => 'code.php',          'mime_type' => 'application/x-php',                                                         'content' => file_get_contents($dummyFiles['sample_php'])];
            $sqlFile    = ['name' => 'schema.sql',        'mime_type' => 'text/plain',                                                                 'content' => file_get_contents($dummyFiles['sample_sql'])];
            $imgFile    = ['name' => 'diagram.jpg',       'mime_type' => 'image/jpeg',                                                                 'content' => file_get_contents($dummyFiles['image_jpg'])];

            $fb = $feedbackPool[$profile] ?? $feedbackPool['average'];
            $feedbackText = $fb[array_rand($fb)];

            // === Assignment 1: Individual / regular (pdf,docx,zip, max 3) ===
            switch ($profile) {
                case 'active':
                    createSubmission($pdo, $asgn1Id, $sid, 'submitted',
                        "Laporan lengkap {$asgn['title']}.\nSemua requirement telah terpenuhi dengan baik.",
                        0, date('Y-m-d H:i:s', strtotime('-6 days')),
                        [$pdfFile, $docxFile],
                        rand(80, 95) + 0.0, 0, $feedbackText
                    );
                    break;
                case 'average':
                    $isLate = rand(0, 1);
                    createSubmission($pdo, $asgn1Id, $sid, 'submitted',
                        "Laporan tugas selesai. Dikerjakan sesuai instruksi.",
                        $isLate, date('Y-m-d H:i:s', strtotime($isLate ? '+1 day' : '-3 days')),
                        [$pdfFile],
                        rand(65, 75) + 0.0, 0, $feedbackText
                    );
                    break;
                case 'perfectionist':
                    createSubmission($pdo, $asgn1Id, $sid, 'submitted',
                        "Laporan detail & komprehensif.\nExecutive summary: semua objectives tercapai.\nLampiran: laporan, appendix, source code, diagram.",
                        0, date('Y-m-d H:i:s', strtotime('-5 days')),
                        [$pdfFile, $docxFile, $zipFile],
                        rand(95, 100) + 0.0, 0, $feedbackText
                    );
                    break;
                case 'procrastinator':
                    createSubmission($pdo, $asgn1Id, $sid, 'draft',
                        "Masih dalam pengerjaan...", 0, null, []
                    );
                    break;
                case 'struggling':
                    createSubmission($pdo, $asgn1Id, $sid, 'submitted',
                        "Mohon maklum, susah mengerjakannya.",
                        1, date('Y-m-d H:i:s', strtotime('-1 day')),
                        [],
                        rand(40, 55) + 0.0,
                        rand(10, 30) + 0.0,
                        $feedbackText
                    );
                    break;
            }

            // === Assignment 2: Group project (zip,rar,pdf, max 5) ===
            switch ($profile) {
                case 'active':
                    createSubmission($pdo, $asgn2Id, $sid, 'submitted',
                        "Proposal project kelompok.\nJudul: Sistem Informasi {$cls['class_name']}.\nAnggota: 3 orang.",
                        0, date('Y-m-d H:i:s', strtotime('+1 week')),
                        [$pdfFile, $docxFile, $zipFile],
                        rand(80, 90) + 0.0, 0,
                        "Proposal terstruktur dengan baik. Scope jelas dan timeline realistis."
                    );
                    break;
                case 'perfectionist':
                    createSubmission($pdo, $asgn2Id, $sid, 'submitted',
                        "Progress report lengkap.\nPencapaian: 60% selesai. Semua milestone on-track.",
                        0, date('Y-m-d H:i:s', strtotime('+1 week + 1 day')),
                        [$pdfFile, $docxFile, $zipFile, $phpFile, $sqlFile],
                        rand(93, 100) + 0.0, 0,
                        "Luar biasa! Dokumentasi sangat lengkap dan progress sangat baik."
                    );
                    break;
                case 'average':
                    createSubmission($pdo, $asgn2Id, $sid, 'draft',
                        "Belum mulai kelompok...", 0, null, []
                    );
                    break;
                case 'procrastinator':
                    // Not submitted at all — skip (unsubmitted)
                    break;
                case 'struggling':
                    createSubmission($pdo, $asgn2Id, $sid, 'draft',
                        "Belum bisa kerjakan.", 0, null, []
                    );
                    break;
            }

            // === Assignment 3: Exam/Quiz (txt,pdf, max 1, STRICT) ===
            switch ($profile) {
                case 'active':
                    createSubmission($pdo, $asgn3Id, $sid, 'submitted',
                        "Jawaban kuis:\n1. B\n2. A\n3. C\n4. D\n5. A\nEssay: [jawaban lengkap]",
                        0, date('Y-m-d H:i:s', strtotime('+2 days')),
                        [$pdfFile],
                        rand(82, 93) + 0.0, 0, "Jawaban akurat dan tepat waktu. Bagus!"
                    );
                    break;
                case 'average':
                    createSubmission($pdo, $asgn3Id, $sid, 'submitted',
                        "Jawaban kuis: 1. A 2. C 3. B 4. B 5. D",
                        0, date('Y-m-d H:i:s', strtotime('+2 days + 3 hours')),
                        [],
                        rand(65, 72) + 0.0, 0, "Sebagian besar benar. Perlu lebih teliti."
                    );
                    break;
                case 'perfectionist':
                    createSubmission($pdo, $asgn3Id, $sid, 'submitted',
                        "Jawaban kuis lengkap.\n1. B\n2. A\n3. C\n4. D\n5. A\nEssay: [analisis mendalam disertai referensi]",
                        0, date('Y-m-d H:i:s', strtotime('+1 day')),
                        [$pdfFile],
                        rand(96, 100) + 0.0, 0, "Sempurna! Semua jawaban benar dan essay sangat mendalam."
                    );
                    break;
                case 'procrastinator':
                    // Strict deadline — stays as draft or unsubmitted
                    createSubmission($pdo, $asgn3Id, $sid, 'draft',
                        "Mau kerjakan tapi lupa...", 0, null, []
                    );
                    break;
                case 'struggling':
                    // Submitted very late (violated strict)
                    createSubmission($pdo, $asgn3Id, $sid, 'submitted',
                        "Terlambat karena masalah internet. Jawaban seadanya.",
                        1, date('Y-m-d H:i:s', strtotime('+4 days')),
                        [],
                        rand(40, 52) + 0.0,
                        rand(10, 25) + 0.0,
                        "Sangat terlambat pada ujian strict. Terindikasi menyontek. Nilai sangat dikurangi."
                    );
                    break;
            }

            // === Assignment 4: Optional (pdf,docx, max 2, no deadline) ===
            switch ($profile) {
                case 'active':
                    createSubmission($pdo, $asgn4Id, $sid, 'submitted',
                        "Tugas bonus: analisis tambahan mendalam.",
                        0, date('Y-m-d H:i:s', strtotime('-3 days')),
                        [$docxFile, $pdfFile],
                        rand(82, 90) + 0.0, 0, "Kerja keras diapresiasi! Analisis bonus sangat berguna."
                    );
                    break;
                case 'perfectionist':
                    createSubmission($pdo, $asgn4Id, $sid, 'submitted',
                        "Implementasi advanced untuk tugas bonus.",
                        0, date('Y-m-d H:i:s', strtotime('-1 day')),
                        [$pdfFile, $docxFile],
                        rand(96, 100) + 0.0, 0, "Implementasi melampaui ekspektasi. Sangat memuaskan!"
                    );
                    break;
                // average, procrastinator, struggling: skip optional
                default:
                    break;
            }
        }

        echo "    [{$cls['class_name']}] done.\n";
    }

    $pdo->commit();

    // ============================================================
    // SUMMARY OUTPUT
    // ============================================================
    echo "\n";
    echo "======================================================\n";
    echo " COMPREHENSIVE DUMMY DATA CREATED SUCCESSFULLY!\n";
    echo "======================================================\n\n";
    echo "Summary:\n";
    echo "- 10 Assistants (Asprak) dengan keahlian masing-masing\n";
    echo "- 50 Mahasiswa Unik dengan 5 variasi profil perilaku\n";
    echo "- 10 Kelas Perkuliahan (Basis Data, RPL, AI, dll.)\n";
    echo "- 30 Total Aspek Penilaian (Tiap aspek memegang 10 mahasiswa)\n";
    echo "- 20 Pengumuman Kelas dengan lampiran file dinamis\n";
    echo "- 40 Tugas Aktif (Kuis, Kelompok, Individu, Opsional)\n";
    echo "- 300+ Submissions dengan berbagai skenario\n";
    echo "  (Tepat waktu, Telat, Plagiasi, Draft, Unsubmitted)\n";
    echo "\nProfil mahasiswa yang diuji:\n";
    echo "1. Active       — Tepat waktu, nilai 80–95, file lengkap, feedback pujian\n";
    echo "2. Average      — Kadang tepat, nilai 65–75, feedback saran perbaikan\n";
    echo "3. Perfectionist— Selalu tepat, upload file maksimal, nilai 95–100\n";
    echo "4. Procrastinator— Draft / tidak submit, terutama di deadline strict\n";
    echo "5. Struggling   — Terlambat ekstrem, nilai 40–55, plagiarism_penalty 10–30\n";
    echo "\nSkenario file yang diuji:\n";
    echo "- Kuis/Ujian   : txt, pdf — max_files = 1\n";
    echo "- Tugas Reguler: pdf, docx, zip — max_files = 3\n";
    echo "- Project Akhir: zip, rar, pdf — max_files = 5\n";
    echo "- Opsional     : pdf, docx — max_files = 2\n";
    echo "\nSemua password user: password123\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
