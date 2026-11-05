# Database Verification & Setup Commands

## Quick Database Status Check

Run these queries in phpMyAdmin or MySQL client to verify the implementation is complete:

### 1. Check if notifications table exists
```sql
DESCRIBE student_notifications;
```

Expected columns:
- id (INT, PRIMARY KEY)
- student_id (INT)
- title (VARCHAR 255)
- message (TEXT)
- notification_type (VARCHAR 50)
- application_id (INT)
- document_id (INT)
- is_read (TINYINT 1)
- created_at (TIMESTAMP)

### 2. Check student_applications table
```sql
DESCRIBE student_applications;
```

Should have columns:
- id, student_id, application_number, program_type, course_name, 
  application_status, admin_remarks, verified_at, verified_by, 
  qualification, institution_name, percentage, address, city, state, pincode

### 3. Check student_documents table
```sql
DESCRIBE student_documents;
```

Should have columns:
- id, student_id, application_id, document_type, file_name, file_path,
  verification_status, admin_remarks, uploaded_at, verified_at

## Manual Table Creation (if needed)

If any tables are missing, run these SQL commands:

### Create notifications table
```sql
CREATE TABLE IF NOT EXISTS student_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT,
    notification_type VARCHAR(50),
    application_id INT,
    document_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES student_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES student_documents(id) ON DELETE SET NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_created_at (created_at),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Add program_type to student_applications (if missing)
```sql
ALTER TABLE student_applications 
ADD COLUMN IF NOT EXISTS program_type VARCHAR(10) DEFAULT 'UG';
```

### Add application_id to student_documents (if missing)
```sql
ALTER TABLE student_documents 
ADD COLUMN IF NOT EXISTS application_id INT 
ADD FOREIGN KEY (application_id) REFERENCES student_applications(id) ON DELETE CASCADE;
```

## Verification Queries

### Count records in new table
```sql
SELECT COUNT(*) FROM student_notifications;
```

### Check pending applications
```sql
SELECT COUNT(*) as pending_count 
FROM student_applications 
WHERE application_status = 'Pending';
```

### Check accepted applications
```sql
SELECT COUNT(*) as accepted_count 
FROM student_applications 
WHERE application_status = 'Accepted';
```

### Check documents needing verification
```sql
SELECT COUNT(*) as pending_docs 
FROM student_documents 
WHERE verification_status = 'Pending';
```

### Check verified documents
```sql
SELECT COUNT(*) as verified_docs 
FROM student_documents 
WHERE verification_status = 'Verified';
```

### Check application program type distribution
```sql
SELECT program_type, COUNT(*) as count 
FROM student_applications 
GROUP BY program_type;
```

### Check documents per application
```sql
SELECT application_id, COUNT(*) as doc_count 
FROM student_documents 
GROUP BY application_id;
```

### Check notifications by type
```sql
SELECT notification_type, COUNT(*) as count 
FROM student_notifications 
GROUP BY notification_type;
```

## Sample Test Data

Insert test notifications manually:
```sql
INSERT INTO student_notifications 
(student_id, title, message, notification_type, application_id, is_read)
VALUES
(1, 'Application Accepted', 'Your admission application has been accepted.', 'APPLICATION_ACCEPTED', 1, 0),
(1, 'Document Verified', 'Your 10th Marksheet has been verified successfully.', 'DOCUMENT_VERIFIED', 1, 0),
(2, 'Application Rejected', 'Your application has been rejected.', 'APPLICATION_REJECTED', 2, 0);
```

## Export/Backup Commands

Backup the database:
```bash
mysqldump -u root -p college_management > backup_college_management.sql
```

Restore from backup:
```bash
mysql -u root -p college_management < backup_college_management.sql
```

## Performance Optimization

Add indexes for faster queries:
```sql
CREATE INDEX idx_student_app_status ON student_applications(student_id, application_status);
CREATE INDEX idx_doc_verification ON student_documents(student_id, verification_status);
CREATE INDEX idx_notification_student_date ON student_notifications(student_id, created_at DESC);
```

## Database Statistics

Check database size:
```sql
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'college_management'
ORDER BY size_mb DESC;
```

Check table row counts:
```sql
SELECT 
    table_name,
    table_rows
FROM information_schema.TABLES
WHERE table_schema = 'college_management'
ORDER BY table_rows DESC;
```

## Connection Test

Test database connectivity from PHP:
```php
<?php
require_once "config/database.php";

if ($conn) {
    echo "✓ Database connection successful";
    
    // Test notifications table
    $result = $conn->query("SELECT COUNT(*) as count FROM student_notifications");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<br>✓ Notifications table exists with " . $row["count"] . " records";
    } else {
        echo "<br>✗ Notifications table query failed";
    }
} else {
    echo "✗ Database connection failed";
}
?>
```

## Cleanup (if needed)

Delete all notifications:
```sql
DELETE FROM student_notifications;
```

Reset application statuses:
```sql
UPDATE student_applications SET application_status = 'Pending', verified_at = NULL, verified_by = NULL;
```

Reset document statuses:
```sql
UPDATE student_documents SET verification_status = 'Pending', verified_at = NULL, admin_remarks = '';
```

Clear upload directory:
```bash
# From Windows command line
del C:\xampp\htdocs\CollegeManagementWeb\uploads\student_documents\*.*

# From bash/Linux
rm /var/www/html/CollegeManagementWeb/uploads/student_documents/*
```

## Monitoring Queries

Track notification creation:
```sql
SELECT 
    DATE(created_at) as date,
    notification_type,
    COUNT(*) as count
FROM student_notifications
GROUP BY DATE(created_at), notification_type
ORDER BY created_at DESC;
```

Track document uploads over time:
```sql
SELECT 
    DATE(uploaded_at) as date,
    COUNT(*) as uploads
FROM student_documents
GROUP BY DATE(uploaded_at)
ORDER BY uploaded_at DESC;
```

Track application submissions:
```sql
SELECT 
    DATE(created_at) as date,
    COUNT(*) as submissions
FROM student_applications
GROUP BY DATE(created_at)
ORDER BY created_at DESC;
```

## Troubleshooting Queries

Find students with incomplete document submissions:
```sql
SELECT 
    s.id,
    s.name,
    sa.program_type,
    COUNT(sd.id) as docs_uploaded,
    CASE 
        WHEN sa.program_type = 'UG' THEN 5
        WHEN sa.program_type = 'PG' THEN 11
        ELSE 0
    END as docs_required
FROM students s
LEFT JOIN student_applications sa ON s.id = sa.student_id
LEFT JOIN student_documents sd ON sa.id = sd.application_id
WHERE sa.application_status = 'Accepted'
GROUP BY s.id, sa.program_type
HAVING docs_uploaded < docs_required;
```

Find pending notifications by student:
```sql
SELECT 
    student_id,
    COUNT(*) as unread_count,
    MAX(created_at) as latest_notification
FROM student_notifications
WHERE is_read = 0
GROUP BY student_id
ORDER BY latest_notification DESC;
```

Find applications awaiting verification:
```sql
SELECT 
    sa.id,
    sa.application_number,
    s.name,
    COUNT(sd.id) as docs_uploaded,
    SUM(CASE WHEN sd.verification_status = 'Verified' THEN 1 ELSE 0 END) as docs_verified
FROM student_applications sa
JOIN students s ON sa.student_id = s.id
LEFT JOIN student_documents sd ON sa.id = sd.application_id
WHERE sa.application_status = 'Accepted'
GROUP BY sa.id
HAVING docs_verified < docs_uploaded;
```
