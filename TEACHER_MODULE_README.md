# Teacher & Marks Management Module - Implementation Guide

This document provides a complete guide for the Smart College Management System's Teacher and Marks Management Module.

## Overview

The Teacher Module is an extension to the existing Smart College Management System that enables:
- Secure teacher authentication separate from admin/student login
- Mark entry and management for admitted students
- Academic performance tracking and reporting
- Student GPA/CGPA calculations
- Published marks viewing by students
- Teacher account management by admins

## Architecture & Security

### Role Separation
- **Admin**: Manages teachers, subjects, exam schedules, system-wide reporting
- **Teacher**: Enters and publishes marks, views student lists, generates department reports
- **Student**: Views own marks only after teacher publishes, sees calculated GPA/CGPA

### Access Control
- Teachers can only enter marks for students in their own department
- Students can only view marks after admission completion (verified in `cms_is_student_admitted()`)
- All queries use prepared statements to prevent SQL injection
- Passwords are hashed with `password_hash()` and verified with `password_verify()`

### Database Relationships
```
students ←→ student_marks ←→ subjects
             ↓              ↓
          teachers      departments
             ↓
       exam_schedule
             ↓
        mark_change_history (audit trail)
```

## Database Schema

### New Tables Created

#### 1. teachers
```sql
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    designation VARCHAR(100),
    status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
    phone VARCHAR(20),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 2. subjects
```sql
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_code VARCHAR(50) UNIQUE NOT NULL,
    subject_name VARCHAR(200) NOT NULL,
    program_type ENUM('UG', 'PG') NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    credits INT DEFAULT 4,
    status ENUM('ACTIVE', 'INACTIVE') DEFAULT 'ACTIVE',
    created_at TIMESTAMP
);
```

#### 3. student_marks
```sql
CREATE TABLE student_marks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    semester VARCHAR(20) NOT NULL,
    academic_year VARCHAR(10) DEFAULT '2026-2027',
    midterm_marks DECIMAL(5,2),
    end_sem_marks DECIMAL(5,2),
    total_marks DECIMAL(5,2),
    percentage DECIMAL(5,2),
    grade VARCHAR(2),
    grade_point DECIMAL(3,2),
    pass_status ENUM('PASS', 'FAIL') DEFAULT 'FAIL',
    publish_status ENUM('NOT_ENTERED', 'DRAFT', 'PUBLISHED') DEFAULT 'NOT_ENTERED',
    teacher_id INT,
    entered_at TIMESTAMP,
    published_at TIMESTAMP,
    updated_at TIMESTAMP,
    created_at TIMESTAMP,
    UNIQUE KEY unique_mark (student_id, subject_id, semester, academic_year),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);
```

#### 4. mark_change_history
```sql
CREATE TABLE mark_change_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mark_record_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    old_midterm DECIMAL(5,2),
    new_midterm DECIMAL(5,2),
    old_end_sem DECIMAL(5,2),
    new_end_sem DECIMAL(5,2),
    change_reason VARCHAR(500),
    changed_at TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);
```

#### 5. grading_rules
```sql
CREATE TABLE grading_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    min_percentage DECIMAL(5,2) NOT NULL,
    max_percentage DECIMAL(5,2) NOT NULL,
    grade VARCHAR(2) NOT NULL,
    grade_point DECIMAL(3,2) NOT NULL,
    pass_fail ENUM('PASS', 'FAIL') NOT NULL,
    created_at TIMESTAMP
);
-- Pre-populated with 7 rows:
-- O: 90-100, 4.0, PASS
-- A+: 80-89.99, 3.7, PASS
-- A: 70-79.99, 3.3, PASS
-- B+: 60-69.99, 3.0, PASS
-- B: 50-59.99, 2.7, PASS
-- C: 40-49.99, 2.0, PASS
-- F: 0-39.99, 0.0, FAIL
```

#### 6. exam_schedule
```sql
CREATE TABLE exam_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT NOT NULL,
    semester VARCHAR(20) NOT NULL,
    exam_type ENUM('Midterm', 'End Semester') NOT NULL,
    exam_date DATE NOT NULL,
    max_marks INT DEFAULT 100,
    academic_year VARCHAR(10) DEFAULT '2026-2027',
    status ENUM('SCHEDULED', 'COMPLETED', 'CANCELLED') DEFAULT 'SCHEDULED',
    created_at TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);
```

## File Structure

### Authentication & Core Pages
- **teacher_login.php** - Teacher login form (separate from admin/student)
- **includes/teacher_sidebar.php** - Navigation sidebar for teacher pages

### Teacher Pages (Authenticated)
- **teacher_dashboard.php** - Home page with statistics
- **teacher_students.php** - List of students in teacher's department
- **teacher_marks.php** - Mark entry, editing, and publication
- **teacher_examinations.php** - View exam schedule
- **teacher_reports.php** - Department performance reports
- **teacher_profile.php** - Teacher profile and password change

### Student Pages (Modified)
- **student_marks.php** - NEW - Student viewing published marks
- **student_dashboard.php** - UPDATED - Added academic performance section

### Admin Pages (Modified)
- **teachers.php** - NEW - Teacher account management

## Key Functions in config/schema.php

### `cms_is_student_admitted($conn, $studentId)`
Returns boolean: true if student has completed full admission workflow
- Requires: Application Accepted + Documents Verified + Admission Approved
- Used for: Gating access to marks viewing and academic records

### `cms_get_grade_for_percentage($conn, $percentage)`
Returns array: `['grade', 'grade_point', 'pass_fail']` based on percentage
- Queries grading_rules table
- Called during mark entry/calculation

### `cms_calculate_semester_gpa($conn, $studentId, $semester, $academicYear = '2026-2027')`
Returns float: GPA for semester (credit-weighted average)
- Formula: SUM(grade_point * credits) / SUM(credits)
- Uses published marks only

### `cms_calculate_overall_cgpa($conn, $studentId, $academicYear = '2026-2027')`
Returns float: CGPA across all semesters
- Uses average of all semester GPAs
- Only includes semesters with published marks

## User Workflows

### 1. Admin: Create Teacher Account
1. Navigate to `teachers.php`
2. Fill in form: Teacher Code, Name, Email, Password, Department, Designation
3. Click "CREATE TEACHER ACCOUNT"
4. Teacher account created with status ACTIVE

### 2. Teacher: Enter Marks
1. Login at `teacher_login.php` with email/password
2. Go to Dashboard → "My Students" or "Enter Marks"
3. Select student from dropdown
4. Select semester (UI shows 4 for PG, 6 for UG)
5. Subjects auto-populate based on student's program
6. Enter Midterm (0-30) and End Semester (0-70) marks
7. Click "Save" to save as DRAFT (not visible to student yet)
8. Or click "Save & Publish" to make visible to student immediately
9. System automatically calculates: Total, %, Grade, Grade Point, Pass/Fail
10. On update: Old values logged in mark_change_history

### 3. Student: View Marks
1. Login at `login.php?role=student` with roll_no/password
2. Dashboard shows "Academic Performance" section (only if admitted)
3. Shows CGPA, semesters completed, current semester
4. Click "VIEW DETAILED MARKS" to go to `student_marks.php`
5. Shows all published marks organized by semester
6. Displays: Subject, Midterm, End Sem, Total, %, Grade, Points, Credits, Status
7. Shows Semester GPA and Overall CGPA

### 4. Teacher: View Reports
1. Navigate to "Reports" from sidebar
2. See department statistics:
   - Total Students
   - Total Subjects
   - Average Percentage
   - Pass Percentage
3. Semester-wise performance breakdown

## Mark Entry Validation

### Midterm Marks
- Range: 0-30 (or max_marks from exam_schedule)
- Rejected if negative or > maximum

### End Semester Marks
- Range: 0-70 (or max_marks from exam_schedule)
- Rejected if negative or > maximum

### Total Calculation
- Total = Midterm + End Semester
- Percentage = (Total / max_marks) * 100
- Grade: Fetched from grading_rules based on percentage
- Pass Status: PASS if grade_point > 0, FAIL otherwise

## Mark Publication Workflow

### Draft Status (NOT_ENTERED / DRAFT)
- Marks are saved in database
- NOT visible to students
- Can be edited by teacher multiple times
- No notification sent

### Published Status (PUBLISHED)
- Marks visible to students in `student_marks.php`
- Can still be edited by teacher (creates history entry)
- Notification created for student
- published_at timestamp recorded

## GPA/CGPA Calculation

### Semester GPA (Credit-Weighted)
```
GPA = SUM(grade_point × credits) / SUM(credits)
Example: 
- Subject 1: Grade A+ (3.7) × 4 credits = 14.8
- Subject 2: Grade B (2.7) × 4 credits = 10.8
- Total = 25.6 / 8 = 3.2 GPA
```

### Overall CGPA
```
CGPA = Average of all semester GPAs
(Calculated only for semesters with published marks)
```

### Grade Points Mapping
- O (90-100%): 4.0
- A+ (80-89.99%): 3.7
- A (70-79.99%): 3.3
- B+ (60-69.99%): 3.0
- B (50-59.99%): 2.7
- C (40-49.99%): 2.0
- F (0-39.99%): 0.0

## Responsive Design

All pages are responsive with breakpoints:
- Desktop: Full-width with 255px sidebar (teacher pages)
- Tablet (≤1024px): Grid layouts adjust (4 cols → 2 cols)
- Mobile (≤800px): Single column, sidebar becomes full-width

## Styling & Theme

- Background: Dark theme (#070b17) with radial gradient overlays
- Accent Colors: Blue (#2563eb) and Cyan (#06b6d4)
- Cards: rgba(13,19,36,.86) with border: 1px solid rgba(148,163,184,.14)
- Text: #f8fafc (primary), #94a3b8 (secondary)
- Matches existing Smart Campus design

## Important Constants

### Academic Year
- Currently hardcoded: "2026-2027"
- Configurable in helper functions (last parameter)
- Change in: cms_calculate_semester_gpa(), cms_calculate_overall_cgpa()

### Semesters by Program
- UG: 6 semesters
- PG: 4 semesters
- Determined from student_applications.program_type

### Max Marks (Dynamic)
- Determined from exam_schedule.max_marks if available
- Defaults: Midterm=30, End Sem=70 (Total=100)

## Testing Checklist

```
[ ] Create teacher account via teachers.php
[ ] Teacher login at teacher_login.php
[ ] Teacher sees dashboard with statistics
[ ] Teacher views student list (only department members)
[ ] Teacher enters marks for a student (saves as DRAFT)
[ ] Marks not visible to student yet
[ ] Teacher publishes marks
[ ] Student sees published marks in student_marks.php
[ ] Student sees GPA/CGPA calculations
[ ] Non-admitted student sees locked message
[ ] GPA calculations are correct (credit-weighted)
[ ] UG shows 6 semesters, PG shows 4
[ ] Deactivating teacher works
[ ] Mark change history logs old/new values
[ ] Reports show correct statistics
```

## Database Migration

To apply these changes to existing system:

1. Run all CREATE TABLE statements from config/schema.php
2. Seed grading_rules table with 7 grade rows
3. Add test teacher accounts via teachers.php
4. Add test subjects to subjects table
5. Add test exam schedules

Tables created by: `cms_table_exists()` and auto-created during first page load if missing

## Troubleshooting

### Marks not calculating
- Verify grading_rules table has 7 rows
- Check exam_schedule.max_marks is set correctly
- Verify student_applications.program_type is UG or PG

### Student can't see marks
- Verify mark record has publish_status = 'PUBLISHED'
- Verify student is admitted: cms_is_student_admitted() returns true
- Check admission_processing.admission_status = 'Approved'

### Teacher can't access student
- Verify teacher and student are in same department
- Check teacher status is ACTIVE

### GPA is 0.0
- Verify grades were properly assigned
- Check grading_rules table exists and has correct values
- Verify credits are set in subjects table

## Future Enhancements

1. Email notifications when marks are published
2. Subject assignment table to limit teacher's subjects
3. Configurable academic year selector
4. Bulk mark import/export (CSV)
5. Advanced analytics and trend reports
6. Mobile app support
7. Retake management for failed subjects
8. Dean/HOD dashboard with approval workflow

---

**Module Version**: 1.0  
**Last Updated**: 2024  
**Compatibility**: PHP 7.0+, MySQL 5.7+  
**Tested With**: xampp MySQL InnoDB
