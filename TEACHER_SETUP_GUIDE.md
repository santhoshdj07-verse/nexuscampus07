# Teacher Module - Quick Setup & Sample Data

This guide will help you quickly set up and test the Teacher & Marks Management Module.

## Step 1: Verify Database Tables

All tables are automatically created by the schema functions if they don't exist. To manually verify:

```sql
-- Check if tables exist
SHOW TABLES LIKE 'teachers';
SHOW TABLES LIKE 'subjects';
SHOW TABLES LIKE 'student_marks';
SHOW TABLES LIKE 'grading_rules';
SHOW TABLES LIKE 'exam_schedule';
SHOW TABLES LIKE 'mark_change_history';
```

## Step 2: Seed Grading Rules (Manual - If Not Auto-Created)

```sql
INSERT INTO grading_rules (min_percentage, max_percentage, grade, grade_point, pass_fail, created_at) VALUES
(90, 100, 'O', 4.0, 'PASS', NOW()),
(80, 89.99, 'A+', 3.7, 'PASS', NOW()),
(70, 79.99, 'A', 3.3, 'PASS', NOW()),
(60, 69.99, 'B+', 3.0, 'PASS', NOW()),
(50, 59.99, 'B', 2.7, 'PASS', NOW()),
(40, 49.99, 'C', 2.0, 'PASS', NOW()),
(0, 39.99, 'F', 0.0, 'FAIL', NOW());
```

## Step 3: Create Sample Teacher Accounts

### Via Web Interface (Recommended)
1. Login as admin at `admin_login.php`
2. Navigate to `teachers.php` (or add link in admin sidebar)
3. Fill the "Add New Teacher" form
4. Create teachers:
   - **Teacher 1**: Code=T001, Name=Dr. Rajesh Kumar, Email=rajesh@college.edu, Dept=Computer Science, Password=teacher@123
   - **Teacher 2**: Code=T002, Name=Prof. Priya Singh, Email=priya@college.edu, Dept=Information Technology, Password=teacher@123
   - **Teacher 3**: Code=T003, Name=Dr. Amit Patel, Email=amit@college.edu, Dept=Electronics, Password=teacher@123

### Via SQL (Direct)
```sql
-- Insert sample teachers
INSERT INTO teachers (teacher_code, name, email, password, department, designation, status, phone, created_at, updated_at) VALUES
('T001', 'Dr. Rajesh Kumar', 'rajesh@college.edu', '$2y$10$YourHashedPassword1', 'Computer Science', 'Associate Professor', 'ACTIVE', '9876543210', NOW(), NOW()),
('T002', 'Prof. Priya Singh', 'priya@college.edu', '$2y$10$YourHashedPassword2', 'Information Technology', 'Assistant Professor', 'ACTIVE', '9876543211', NOW(), NOW()),
('T003', 'Dr. Amit Patel', 'amit@college.edu', '$2y$10$YourHashedPassword3', 'Electronics', 'Assistant Professor', 'ACTIVE', '9876543212', NOW(), NOW());

-- Note: Generate hashes in PHP: password_hash('teacher@123', PASSWORD_DEFAULT)
```

## Step 4: Create Sample Subjects

```sql
-- Computer Science UG Subjects (Semester 1)
INSERT INTO subjects (subject_code, subject_name, program_type, course_name, department, semester, credits, status, created_at) VALUES
('CS101', 'Programming Fundamentals', 'UG', 'B.Tech Computer Science', 'Computer Science', 'Semester 1', 4, 'ACTIVE', NOW()),
('CS102', 'Data Structures', 'UG', 'B.Tech Computer Science', 'Computer Science', 'Semester 1', 4, 'ACTIVE', NOW()),
('CS103', 'Web Development Basics', 'UG', 'B.Tech Computer Science', 'Computer Science', 'Semester 1', 4, 'ACTIVE', NOW()),
('CS104', 'Database Management', 'UG', 'B.Tech Computer Science', 'Computer Science', 'Semester 1', 3, 'ACTIVE', NOW());

-- Information Technology UG Subjects (Semester 1)
INSERT INTO subjects (subject_code, subject_name, program_type, course_name, department, semester, credits, status, created_at) VALUES
('IT101', 'IT Fundamentals', 'UG', 'B.Tech Information Technology', 'Information Technology', 'Semester 1', 4, 'ACTIVE', NOW()),
('IT102', 'Network Basics', 'UG', 'B.Tech Information Technology', 'Information Technology', 'Semester 1', 4, 'ACTIVE', NOW()),
('IT103', 'System Administration', 'UG', 'B.Tech Information Technology', 'Information Technology', 'Semester 1', 4, 'ACTIVE', NOW());

-- Electronics UG Subjects (Semester 1)
INSERT INTO subjects (subject_code, subject_name, program_type, course_name, department, semester, credits, status, created_at) VALUES
('EC101', 'Circuit Analysis', 'UG', 'B.Tech Electronics', 'Electronics', 'Semester 1', 4, 'ACTIVE', NOW()),
('EC102', 'Digital Electronics', 'UG', 'B.Tech Electronics', 'Electronics', 'Semester 1', 4, 'ACTIVE', NOW()),
('EC103', 'Signals and Systems', 'UG', 'B.Tech Electronics', 'Electronics', 'Semester 1', 4, 'ACTIVE', NOW());
```

## Step 5: Create Sample Exam Schedule

```sql
-- Get subject IDs first:
-- SELECT id FROM subjects WHERE subject_code = 'CS101'; -- Should return 1
-- Then create exam schedules

INSERT INTO exam_schedule (subject_id, semester, exam_type, exam_date, max_marks, academic_year, status, created_at) VALUES
(1, 'Semester 1', 'Midterm', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 30, '2026-2027', 'SCHEDULED', NOW()),
(1, 'Semester 1', 'End Semester', DATE_ADD(CURDATE(), INTERVAL 35 DAY), 70, '2026-2027', 'SCHEDULED', NOW()),
(2, 'Semester 1', 'Midterm', DATE_ADD(CURDATE(), INTERVAL 8 DAY), 30, '2026-2027', 'SCHEDULED', NOW()),
(2, 'Semester 1', 'End Semester', DATE_ADD(CURDATE(), INTERVAL 36 DAY), 70, '2026-2027', 'SCHEDULED', NOW()),
(3, 'Semester 1', 'Midterm', DATE_ADD(CURDATE(), INTERVAL 9 DAY), 30, '2026-2027', 'SCHEDULED', NOW()),
(3, 'Semester 1', 'End Semester', DATE_ADD(CURDATE(), INTERVAL 37 DAY), 70, '2026-2027', 'SCHEDULED', NOW());
```

## Step 6: Ensure Student is Admitted

For testing marks viewing, a student must have:
1. ✓ Application with status 'Accepted'
2. ✓ All required documents verified
3. ✓ Admission processing with status 'Approved' or 'Completed'

Check if existing student is admitted:
```sql
-- Find a student and check admission status
SELECT s.id, s.name, s.roll_no, sa.application_status, 
       sd.verification_status, ap.admission_status
FROM students s
LEFT JOIN student_applications sa ON s.id = sa.student_id
LEFT JOIN student_documents sd ON s.id = sd.student_id
LEFT JOIN admission_processing ap ON s.id = ap.student_id
WHERE s.department = 'Computer Science'
LIMIT 1;
```

If not admitted yet, you'll need to:
1. Create an application
2. Verify documents
3. Process admission

See IMPLEMENTATION_SUMMARY.md for full admission workflow.

## Step 7: Test the System

### Test 1: Teacher Login
1. Open browser: `http://localhost/CollegeManagementWeb/teacher_login.php`
2. Email: `rajesh@college.edu`
3. Password: `teacher@123`
4. Expected: Redirects to `teacher_dashboard.php`

### Test 2: Teacher Enters Marks
1. From dashboard: Click "My Students" or "Enter Marks"
2. Select student: Any admitted student in Computer Science department
3. Select semester: Semester 1
4. Subjects should auto-load: CS101, CS102, CS103, CS104
5. Enter marks:
   - CS101: Midterm=25, End Sem=65
   - CS102: Midterm=28, End Sem=70
6. Click "Save & Publish"
7. Expected: Marks saved and published status set

### Test 3: Student Views Marks
1. Login as the student: `http://localhost/CollegeManagementWeb/login.php?role=student`
2. Go to "Student Dashboard"
3. Should see "Academic Performance" section (if admitted)
4. Click "VIEW DETAILED MARKS"
5. Should see Semester 1 with marks you just entered
6. Semester GPA and Overall CGPA calculated automatically

### Test 4: Mark Calculation Verification
For the marks entered above:
- CS101: Total=90, %=90, Grade=O, Points=4.0
- CS102: Total=98, %=98, Grade=O, Points=4.0
- Semester GPA = (4.0×4 + 4.0×4) / 8 = 4.0
- Both subjects: Pass Status = PASS

## Step 8: Troubleshooting

### Teacher can't login
- Verify teacher status is 'ACTIVE' in database
- Check password was hashed correctly
- Verify email matches exactly

### Student can't view marks
- Verify student is admitted (check cms_is_student_admitted)
- Verify marks have publish_status = 'PUBLISHED'
- Check both student and subject are in same department

### Marks not calculating
- Verify grading_rules table has 7 rows
- Check student_applications.program_type is UG or PG
- Verify exam_schedule.max_marks is set

### Subjects not appearing
- Verify subjects exist in database for that semester
- Check program_type matches (UG/PG)
- Verify course_name matches student_applications.course_name
- Verify department matches

## Sample Test Data Script

Create `sample_data.php` in root directory:

```php
<?php
require_once "config/database.php";

// Check and seed grading rules
$check = $conn->prepare("SELECT COUNT(*) as cnt FROM grading_rules");
$check->execute();
$result = $check->get_result();
$row = $result->fetch_assoc();

if ($row["cnt"] < 7) {
    $grades = [
        [90, 100, 'O', 4.0, 'PASS'],
        [80, 89.99, 'A+', 3.7, 'PASS'],
        [70, 79.99, 'A', 3.3, 'PASS'],
        [60, 69.99, 'B+', 3.0, 'PASS'],
        [50, 59.99, 'B', 2.7, 'PASS'],
        [40, 49.99, 'C', 2.0, 'PASS'],
        [0, 39.99, 'F', 0.0, 'FAIL']
    ];
    
    foreach ($grades as $g) {
        $stmt = $conn->prepare("INSERT INTO grading_rules (min_percentage, max_percentage, grade, grade_point, pass_fail, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("dddss", $g[0], $g[1], $g[2], $g[3], $g[4]);
        $stmt->execute();
    }
    
    echo "✓ Grading rules seeded<br>";
}

echo "✓ Sample data check complete";
?>
```

Run: `http://localhost/CollegeManagementWeb/sample_data.php`

## Admin Sidebar Integration (Optional)

Add link to `includes/admin_sidebar.php`:

```html
<a href="teachers.php" class="nav-link">
    <span class="nav-icon">👨‍🏫</span>
    <span>Manage Teachers</span>
</a>
```

## Key Database Queries for Reference

```sql
-- Count marks entered by teacher
SELECT COUNT(*) FROM student_marks WHERE teacher_id = 1 AND publish_status != 'NOT_ENTERED';

-- Get all published marks for a student
SELECT * FROM student_marks WHERE student_id = 1 AND publish_status = 'PUBLISHED' ORDER BY semester ASC;

-- Get average marks by subject
SELECT subject_id, AVG(percentage) as avg_pct FROM student_marks 
WHERE publish_status = 'PUBLISHED' GROUP BY subject_id;

-- Check mark change history
SELECT * FROM mark_change_history WHERE student_id = 1 ORDER BY changed_at DESC;

-- Count active teachers by department
SELECT department, COUNT(*) as count FROM teachers WHERE status = 'ACTIVE' GROUP BY department;
```

## Access URLs

| Page | URL | Role | Purpose |
|------|-----|------|---------|
| Teacher Login | `/teacher_login.php` | Public | Teacher authentication |
| Teacher Dashboard | `/teacher_dashboard.php` | Teacher | Home page & statistics |
| My Students | `/teacher_students.php` | Teacher | List students in department |
| Enter Marks | `/teacher_marks.php` | Teacher | Mark entry form |
| Examinations | `/teacher_examinations.php` | Teacher | View exam schedule |
| Reports | `/teacher_reports.php` | Teacher | Department reports |
| My Profile | `/teacher_profile.php` | Teacher | Profile & password change |
| My Marks | `/student_marks.php` | Student | View published marks |
| Student Dashboard | `/student_dashboard.php` | Student | Academic section added |
| Teacher Management | `/teachers.php` | Admin | Manage teacher accounts |

---

**Next Steps**:
1. Create sample teachers via teachers.php
2. Add sample subjects to subjects table
3. Create exam schedules
4. Ensure test student is admitted
5. Run through all test cases above

For detailed documentation, see `TEACHER_MODULE_README.md`
