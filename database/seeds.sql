USE university_portal;

INSERT INTO students (username, student_id, password_hash, first_name, last_name, program, faculty, level, admission_year, email, phone, status)
VALUES
('ama.mensah', 'STU2026001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ama', 'Mensah', 'BSc Computer Science', 'Faculty of Physical Sciences', '300', '2023', 'ama.mensah@example.edu', '+233240000001', 'Active'),
('kwesi.owusu', 'STU2026002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kwesi', 'Owusu', 'BSc Civil Engineering', 'Faculty of Engineering', '200', '2024', 'kwesi.owusu@example.edu', '+233240000002', 'Active')
ON DUPLICATE KEY UPDATE
username = VALUES(username),
first_name = VALUES(first_name),
last_name = VALUES(last_name),
program = VALUES(program),
faculty = VALUES(faculty),
level = VALUES(level),
email = VALUES(email),
phone = VALUES(phone),
status = VALUES(status);

INSERT INTO admins (username, password_hash, full_name)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Portal Administrator')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO academic_terms (academic_year, semester, is_current)
VALUES
('2024/2025', 1, 0),
('2024/2025', 2, 0),
('2025/2026', 1, 1)
ON DUPLICATE KEY UPDATE is_current = VALUES(is_current);

INSERT INTO courses (course_code, title, credit_hours)
VALUES
('CS 301', 'Data Structures and Algorithms', 3),
('CS 305', 'Database Systems', 3),
('MATH 251', 'Probability and Statistics', 3),
('ENGR 201', 'Engineering Mechanics', 3),
('CIV 203', 'Fluid Mechanics', 3)
ON DUPLICATE KEY UPDATE
title = VALUES(title),
credit_hours = VALUES(credit_hours);

INSERT INTO results (student_id, term_id, course_id, grade)
SELECT s.id, t.id, c.id, x.grade
FROM (
    SELECT 'STU2026001' AS student_id, '2025/2026' AS academic_year, 1 AS semester, 'CS 301' AS course_code, 'A' AS grade
    UNION ALL SELECT 'STU2026001', '2025/2026', 1, 'CS 305', 'B+'
    UNION ALL SELECT 'STU2026001', '2025/2026', 1, 'MATH 251', 'A'
    UNION ALL SELECT 'STU2026001', '2024/2025', 2, 'CS 301', 'B'
    UNION ALL SELECT 'STU2026001', '2024/2025', 2, 'CS 305', 'B+'
    UNION ALL SELECT 'STU2026002', '2025/2026', 1, 'ENGR 201', 'B'
    UNION ALL SELECT 'STU2026002', '2025/2026', 1, 'CIV 203', 'C+'
) x
INNER JOIN students s ON s.student_id = x.student_id
INNER JOIN academic_terms t ON t.academic_year = x.academic_year AND t.semester = x.semester
INNER JOIN courses c ON c.course_code = x.course_code
ON DUPLICATE KEY UPDATE grade = VALUES(grade);
