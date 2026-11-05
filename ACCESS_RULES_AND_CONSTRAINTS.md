# Project Access Rules and Constraints

This file captures the actual access logic, role rules, and validation constraints currently implemented in the project.

## 1) Entry and Access Flow

### Login screen
- The single access point is `login.php`.
- If an admin session already exists, the app redirects to `dashboard.php`.
- If a student session already exists, the app redirects to `student_dashboard.php`.
- Teacher login is also handled in the same screen; there is no separate teacher login page in normal flow.

### Redirect behavior
- `teacher_login.php` has been redirected to `login.php`.
- `student_register.php` redirects to `login.php`.
- `index.php` redirects to `login.php`.
- `create_admin.php` redirects to `login.php`.

### Role rules
- Student login checks `students` by `roll_no` and password.
- Admin login checks `admins` by `username` and password.
- Teacher login checks `teachers` by `email` and password.
- Each role sets a separate session variable:
  - admin: `admin_id`, `admin_username`, `admin_name`, `role = "admin"`
  - student: `student_id`, `student_roll_no`, `student_name`, `student_department`, `student_semester`, `role = "student"`
  - teacher: `teacher_id`, `teacher_login_id`, `teacher_name`, `teacher_email`, `teacher_department`, `teacher_code`, `role = "teacher"`

---

## 2) Admin Rules

### Admin checks
- Admin login is based on the `admins` table.
- Admin username must match an existing record.
- Admin password is verified with `password_verify()`.
- If invalid, the page shows: "Invalid username or password."

### Admin-only creation
- Teacher accounts are not user-registerable.
- Teacher creation is controlled by admin-only logic in `teachers.php`.
- The page enforces:
  - admin must already be logged in
  - redirect to `admin_login.php` if not logged in

### Admin-only teacher creation constraints
When creating a teacher from `teachers.php`:
- `teacher_code` is required and must be unique
- `name` is required
- `email` is required and must be unique
- `password` is required and must be at least 6 characters
- `department` is required
- `status` is automatically set to `ACTIVE`
- duplicate teacher code or email is rejected

### Teacher status rules
- Teachers can be toggled between `ACTIVE` and `INACTIVE` by admin.
- A teacher with status not equal to `ACTIVE` cannot log in.

---

## 3) Student Rules

### Student registration rules
Student registration on the unified login page allows student self-registration with these validations:

- Full name is required
- Name must contain only letters and spaces
- Name length cannot exceed 20 characters
- Roll number format must match a pattern like `26mcaa45`
- Valid departments:
  - `msc`
  - `mca`
  - `bca`
  - `bsc`
  - `law`
  - `cse`
- Valid semester values:
  - `I`, `II`, `III`, `IV`, `V`, `VI`, `VII`, `VIII`
- Phone must contain exactly 10 digits
- CGPA must be in format like `8.93`
- CGPA must be between 0 and 10
- Password must be at least 6 characters
- Confirm password must match
- Duplicate roll number is rejected

### Student login rules
- Student login uses roll number and password.
- If the username/password is wrong, login fails.
- On success, student is redirected to `student_dashboard.php`.

### Student dashboard gating
Student attendance is not shown unless the following are true:
- The student has an `admission_processing` record
- `admission_status` is `Approved`

If admission is not approved, the attendance summary is hidden and replaced with a pending message. A profile photo is not required to view attendance.

---

## 4) Teacher Rules

### Teacher registration rules
Teacher accounts are not self-registered.
- Only admin can create them.
- Teacher code must be unique.
- Teacher email must be unique.
- Password minimum is 6 characters.

### Teacher login rules
- Teacher login uses `email` + password.
- The teacher record must exist in `teachers`.
- The teacher status must be `ACTIVE`.
- Password is verified using `password_verify()`.
- On success, the teacher is redirected to `teacher_dashboard.php`.

### Teacher dashboard requirements
Teacher dashboard loads teacher data and shows:
- teacher name
- teacher department
- teacher code
- teacher photo if uploaded
- assigned student count
- marks pending
- marks entered
- exam count

---

## 5) Database Constraints for Teacher and Attendance Modules

The schema includes the following relevant constraints:

### `teachers`
- `id` primary key
- `teacher_code` unique
- `email` unique
- `status` default = `ACTIVE`
- indexed by `status` and `department`

### `class_sessions`
- `id` primary key
- `teacher_id` required
- `class_date` required
- `period_label` required
- `start_time` required
- `end_time` required
- `subject_name` default = `General Studies`
- unique key: `(teacher_id, class_date, period_label)`
- foreign key to `teachers(id)`

### `class_attendance`
- `id` primary key
- `session_id` required
- `student_id` required
- `status` default = `present`
- unique key: `(session_id, student_id)`
- foreign key to `class_sessions(id)` and `students(id)`

### Allowed attendance values
The app uses these values in practice:
- `present`
- `late`
- `absent`

---

## 6) Attendance Workflow

### Teacher timetable mark setup
The app creates a default daily class schedule with three periods:
- Period 1: 09:00 to 09:50
- Period 2: 10:10 to 11:00
- Period 3: 11:20 to 12:10

These are inserted for the teacher/date combination unless already present.

### Attendance marking rules
- Teacher opens a class session for a chosen date.
- The system loads approved students for the teacher department.
- Each student is marked as `present`, `late`, or `absent`.
- Attendance is stored with unique pairing `(session_id, student_id)`.
- Saving attendance updates the record using `ON DUPLICATE KEY UPDATE`.

### Student visibility of attendance
Student attendance summary is calculated from `class_attendance` joined to `class_sessions`.
The percentage is based on the count of `present` sessions divided by total marked sessions.

The app expects a minimum attendance of 85%.

---

## 7) Security and Project Constraints

### Important constraints in the current app
- No public admin self-registration flow exists.
- No public teacher self-registration flow exists.
- Teacher login is not open to unapproved users.
- Student dashboard attendance remains hidden until approval and profile photo upload.
- Student login is by roll number, not by email.
- Admin login is by username, not by email.
- Teacher login is by email, not by teacher code.
- Passwords are stored using `password_hash()` and validated with `password_verify()`.

### Practical business rule summary
- Students may register themselves.
- Admins must be created externally or seeded in the database.
- Teachers must be created by admin.
- Teacher account must be `ACTIVE` to access teacher features.
- Attendance is driven by teacher-marked class sessions.
- Student attendance is displayed only after full approval and photo upload.

---

## 8) Main Files to Review

- `login.php` — unified access page and all login/registration validation
- `teachers.php` — admin-only teacher creation and status toggling
- `teacher_dashboard.php` — teacher main dashboard
- `teacher_attendance.php` — teacher attendance marking
- `student_dashboard.php` — student dashboard and attendance display
- `config/schema.php` — database tables and constraints
- `includes/admin_sidebar.php` — admin navigation
- `includes/teacher_sidebar.php` — teacher navigation

---

## 9) Quick Summary

The system currently enforces these access rules:

- Students self-register and login themselves.
- Admins log in only via admin credentials.
- Teachers are created only by admins.
- Teachers must be `ACTIVE` to log in.
- Attendance follows teacher-marked sessions and is shown only after student admission approval + photo upload.
- The attendance minimum threshold is 85%.

This is the current project rule set implemented in code.
