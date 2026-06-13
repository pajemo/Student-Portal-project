# University Student Portal (PHP + MySQL)

A university student login portal with:
- Student authentication (Student ID + password)
 - Student authentication (Username + Student ID + password)
- Current results view and past results history
- Student profile/account page
- Admin manual result entry
- Admin CSV result import
- Student password reset request flow
- Admin password reset for students
- PDF transcript export
- Activity log audit trail

## 1. Requirements
- XAMPP (Apache + MySQL + PHP 8+)

## 2. Database Setup
1. Start Apache and MySQL from XAMPP.
2. Open phpMyAdmin or MySQL CLI.
3. Run [database/schema.sql](database/schema.sql).
4. Run [database/seeds.sql](database/seeds.sql).

## 3. App Config
- Copy [.env.example](.env.example) to [.env](.env) if needed.
- Default local config uses:
  - DB host: 127.0.0.1
  - DB name: university_portal
  - DB user: root
  - DB pass: (empty)

## 4. Launch
Open this URL in browser:
- http://localhost/admin/public/login.php

## 5. Demo Credentials
- Student login:
  - Username: ama.mensah
  - Student ID: STU2026001
  - Password: password
- Admin login:
  - Username: admin
  - Password: password

## 6. CSV Import Format
Use header order exactly:
student_id,academic_year,semester,course_code,course_title,credit_hours,grade

You can test with [database/sample_import.csv](database/sample_import.csv).

## 7. Notes
- Login attempts are rate-limited after repeated failures.
- CSRF token checks are enforced on form submissions.
- Result rows upsert by (student, term, course).
- GPA/CGPA uses the configurable grading policy in [src/Config/GradingPolicy.php](src/Config/GradingPolicy.php). Set `GRADE_POLICY_JSON` in [.env](.env) if your institution uses different grade points.
