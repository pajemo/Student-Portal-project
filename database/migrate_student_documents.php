<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;

$pdo = Database::connection();

$tableExists = (bool) $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'student_documents' LIMIT 1")->fetchColumn();
if (!$tableExists) {
    echo "[OK] student_documents table is not present.\n";
    exit(0);
}

$rowCount = (int) $pdo->query('SELECT COUNT(*) FROM student_documents')->fetchColumn();
if ($rowCount > 0) {
    fwrite(STDERR, "[ERROR] student_documents already has data. Back it up before running this migration.\n");
    exit(1);
}

$pdo->exec('DROP TABLE IF EXISTS student_documents');
$pdo->exec("CREATE TABLE student_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    student_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester TINYINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_documents_admin FOREIGN KEY (admin_id) REFERENCES admins(id),
    CONSTRAINT fk_student_documents_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uq_student_document (student_id, academic_year, semester),
    INDEX idx_student_documents_student (student_id),
    INDEX idx_student_documents_created_at (created_at)
)");

echo "[OK] student_documents table rebuilt for semester-based PDF uploads.\n";
