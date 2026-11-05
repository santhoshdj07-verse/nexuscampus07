# UG/PG Workflow - Quick Reference Guide

## For Testing the Complete Workflow

### UG Student Test Path
1. **Register** → Login as student
2. **Apply** → Select "UG" program type
3. **Choose Course** → Select valid UG course (BCA, BSc, BA, B.Com, LLB, BBA, B.Tech)
4. **Admin Review** → Admin accepts application
5. **Check Notification** → Dashboard shows "Application Accepted" notification
6. **Upload Documents** → See exactly 5 UG documents:
   - 10th Marksheet
   - 12th Marksheet
   - Transfer Certificate
   - Aadhar Card
   - Migration Certificate
7. **Track Progress** → Dashboard shows "1/5", "2/5"... "5/5 Documents Submitted"
8. **Admin Verification** → Admin verifies documents one by one
9. **Completion** → All notifications received, dashboard shows "Submission Complete"

### PG Student Test Path
1. **Register** → Login as student
2. **Apply** → Select "PG" program type
3. **Choose Course** → Select valid PG course (MCA, MSc, MA, M.Com, MBA, M.Tech, LLM)
4. **Admin Review** → Admin accepts application
5. **Check Notification** → Dashboard shows "Application Accepted" notification
6. **Upload Documents** → See exactly 11 PG documents
7. **Track Progress** → Dashboard shows "1/11", "2/11"... "11/11 Documents Submitted"
8. **Admin Verification** → Admin verifies documents
9. **Completion** → Dashboard shows "Submission Complete"

## Key URLs

- **Student Application**: http://localhost/CollegeManagementWeb/student_application.php
- **Student Documents**: http://localhost/CollegeManagementWeb/student_documents.php
- **Student Dashboard**: http://localhost/CollegeManagementWeb/student_dashboard.php
- **Admin Applications**: http://localhost/CollegeManagementWeb/admin_applications.php
- **Admin Document Verification**: http://localhost/CollegeManagementWeb/document_verification.php
- **Admin Dashboard**: http://localhost/CollegeManagementWeb/dashboard.php
- **Login**: http://localhost/CollegeManagementWeb/login.php

## Database Helper Functions Reference

All functions in `config/schema.php`:

### Course Functions
```php
// Get UG courses: ["BCA", "BSc", "BA", "B.Com", "LLB", "BBA", "B.Tech"]
$ugCourses = cms_ug_courses();

// Get PG courses: ["MCA", "MSc", "MA", "M.Com", "MBA", "M.Tech", "LLM"]
$pgCourses = cms_pg_courses();

// Get courses for program type (UG/PG)
$courses = cms_get_courses("UG");

// Check if course is valid for program type
$isValid = cms_is_valid_course_for_program("UG", "BCA"); // Returns true
$isValid = cms_is_valid_course_for_program("UG", "MCA"); // Returns false
```

### Document Functions
```php
// Get required documents for program type
$ugDocs = cms_required_documents("UG");    // Returns 5 documents
$pgDocs = cms_required_documents("PG");    // Returns 11 documents
```

### Notification Functions
```php
// Create a notification
cms_create_notification(
    $conn,                    // Database connection
    $studentId,               // Student ID
    "Application Accepted",   // Title
    "Your application has been accepted...",  // Message
    "APPLICATION_ACCEPTED",   // Type
    $applicationId,           // Application ID
    null                      // Document ID (null for app notifications)
);
```

### Schema Safety Functions
```php
// Check if table exists
$tableExists = cms_table_exists($conn, "student_notifications");

// Check if column exists
$columnExists = cms_column_exists($conn, "student_applications", "program_type");

// Add column if not exists
cms_ensure_column($conn, "student_applications", "program_type", 
    "VARCHAR(10) DEFAULT 'UG'");
```

## Status Flows

### Application Status Flow
```
Pending → Accepted → Documents Submitted
       → Rejected  → Cannot submit docs
```

### Document Status Flow
```
Not Uploaded → Pending (uploaded) → Verified
                               → Rejected → Can re-upload
```

### Notification Types
```
APPLICATION_ACCEPTED    - "Your application has been accepted..."
APPLICATION_REJECTED    - "Your application has been rejected..."
DOCUMENT_VERIFIED       - "Your {document_type} has been verified successfully"
DOCUMENT_REJECTED       - "Your {document_type} was rejected. Reason: ..."
```

## Common Debug Queries

### Check student applications
```sql
SELECT id, student_id, application_number, program_type, course_name, 
       application_status, verified_at, verified_by 
FROM student_applications 
WHERE student_id = 1;
```

### Check documents for application
```sql
SELECT id, application_id, student_id, document_type, 
       verification_status, uploaded_at, verified_at 
FROM student_documents 
WHERE application_id = 5;
```

### Check notifications for student
```sql
SELECT id, student_id, title, notification_type, is_read, created_at 
FROM student_notifications 
WHERE student_id = 1 
ORDER BY created_at DESC;
```

### Count pending documents by type
```sql
SELECT COUNT(*) as count 
FROM student_documents 
WHERE verification_status = 'Pending';
```

### Check admission processing status
```sql
SELECT id, student_id, application_id, admission_status, created_at 
FROM admission_processing 
WHERE student_id = 1;
```

## Common Issues & Solutions

### Issue: Course dropdown not showing
**Solution**: Ensure JavaScript is enabled. Check browser console for errors. Verify student_application.php loaded successfully.

### Issue: Document upload locked
**Solution**: Admin must accept application first. Check student_applications table for verified_at timestamp.

### Issue: Notifications not showing
**Solution**: Check student_notifications table exists. Verify cms_create_notification() is called in admin_applications.php and document_verification.php.

### Issue: Document count shows wrong number
**Solution**: Check program_type field in student_applications table. Verify cms_required_documents() returns correct count.

### Issue: Re-upload button disabled
**Solution**: Document status must be "Pending" or "Rejected". Verified documents cannot be re-uploaded.

## Performance Tips

1. **Limit notifications displayed**: Currently fetches last 10 - modify LIMIT if needed
2. **Cache course lists**: cms_ug_courses() and cms_pg_courses() return static arrays
3. **Index queries**: Add indexes on:
   - student_applications(student_id)
   - student_applications(application_status)
   - student_documents(student_id)
   - student_documents(verification_status)
   - student_notifications(student_id)

## Security Checklist

✅ All queries use prepared statements
✅ Session-based authentication on all pages
✅ Role-based access control (admin/student)
✅ Document submission locked until application accepted
✅ File upload validation (MIME type, extension, size)
✅ Unique filenames prevent collisions
✅ Admin remarks sanitized in notifications
✅ Course selection validated server-side
✅ Application status validation before document submission

## File Locations

- Schema: `/config/schema.php`
- Database: `/config/database.php`
- Student Pages: `/student_*.php`
- Admin Pages: `/admin_*.php`, `/document_verification.php`, `/dashboard.php`
- Uploads: `/uploads/student_documents/`
- Session: Uses PHP session cookie
