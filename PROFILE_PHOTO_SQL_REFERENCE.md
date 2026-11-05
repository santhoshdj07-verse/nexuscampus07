# Profile Photo Management - SQL Setup & Database Reference

## Database Schema

### Schema Changes

The following columns are automatically added to existing tables by `config/schema.php` on application startup.

#### Students Table

**New Columns:**
```sql
ALTER TABLE students ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER role;
ALTER TABLE students ADD COLUMN photo_updated_at TIMESTAMP NULL DEFAULT NULL AFTER photo_path;
```

**Column Details:**

| Column Name | Type | Null | Default | Description |
|------------|------|------|---------|-------------|
| photo_path | VARCHAR(255) | YES | NULL | Relative path to student profile photo |
| photo_updated_at | TIMESTAMP | YES | NULL | Last time photo was updated |

**Example Data:**
```sql
UPDATE students 
SET photo_path = 'uploads/profile/students/student_102_1630703200_54321.jpg',
    photo_updated_at = '2024-01-15 14:30:45'
WHERE id = 102;
```

---

#### Teachers Table

**New Columns:**
```sql
ALTER TABLE teachers ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER status;
ALTER TABLE teachers ADD COLUMN photo_updated_at TIMESTAMP NULL DEFAULT NULL AFTER photo_path;
```

**Column Details:**

| Column Name | Type | Null | Default | Description |
|------------|------|------|---------|-------------|
| photo_path | VARCHAR(255) | YES | NULL | Relative path to teacher profile photo |
| photo_updated_at | TIMESTAMP | YES | NULL | Last time photo was updated |

**Example Data:**
```sql
UPDATE teachers 
SET photo_path = 'uploads/profile/teachers/teacher_15_1630703200_54321.jpg',
    photo_updated_at = '2024-01-15 14:30:45'
WHERE id = 15;
```

---

## Activity Logging

All photo operations are logged to the existing `activity_log` table.

### Activity Log Entries for Photos

**Log Entry Structure:**
```sql
INSERT INTO activity_log 
(actor_type, actor_id, actor_name, action_type, title, description, related_table, related_id)
VALUES (
  'admin',           -- actor_type
  1,                 -- actor_id (admin ID)
  'Administrator',   -- actor_name
  'upload',          -- action_type (upload|delete|replace)
  'Upload Student Profile Photo',  -- title
  'Uploaded profile photo for Student ID: 102',  -- description
  'students',        -- related_table
  102                -- related_id (student/teacher ID)
);
```

### Example Queries

**View all photo uploads:**
```sql
SELECT * FROM activity_log 
WHERE related_table = 'students' 
AND action_type = 'upload'
ORDER BY created_at DESC;
```

**View photo operations by admin:**
```sql
SELECT * FROM activity_log 
WHERE actor_type = 'admin'
AND action_type IN ('upload', 'delete')
ORDER BY created_at DESC;
```

**Count photos uploaded today:**
```sql
SELECT COUNT(*) as photos_uploaded_today
FROM activity_log
WHERE action_type = 'upload'
AND DATE(created_at) = CURDATE()
AND related_table IN ('students', 'teachers');
```

**View photo history for specific student:**
```sql
SELECT * FROM activity_log
WHERE related_table = 'students'
AND related_id = 102
AND action_type IN ('upload', 'delete')
ORDER BY created_at DESC;
```

---

## Useful SQL Queries

### Query: Find students with photos

```sql
SELECT id, name, roll_no, department, photo_path, photo_updated_at
FROM students
WHERE photo_path IS NOT NULL
ORDER BY photo_updated_at DESC;
```

**Result:**
Shows all students who have uploaded photos with their details and when photos were last updated.

---

### Query: Find teachers with photos

```sql
SELECT id, name, teacher_code, department, designation, photo_path, photo_updated_at
FROM teachers
WHERE photo_path IS NOT NULL
ORDER BY photo_updated_at DESC;
```

**Result:**
Shows all teachers who have uploaded photos with their details.

---

### Query: Find students WITHOUT photos

```sql
SELECT id, name, roll_no, department, semester
FROM students
WHERE photo_path IS NULL
ORDER BY name;
```

**Result:**
Useful for bulk photo upload identification.

---

### Query: Photo upload statistics

```sql
SELECT 
  COUNT(*) as total_students,
  SUM(CASE WHEN photo_path IS NOT NULL THEN 1 ELSE 0 END) as students_with_photos,
  ROUND(SUM(CASE WHEN photo_path IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as photo_completion_percentage
FROM students;
```

**Result:**
```
total_students | students_with_photos | photo_completion_percentage
100            | 75                   | 75.00
```

---

### Query: Photo updates in last week

```sql
SELECT 
  u.actor_name,
  COUNT(*) as photo_operations,
  GROUP_CONCAT(al.action_type) as operation_types
FROM activity_log al
LEFT JOIN activity_log u ON al.actor_id = u.actor_id
WHERE al.related_table IN ('students', 'teachers')
AND al.action_type IN ('upload', 'delete')
AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY al.actor_id, u.actor_name
ORDER BY COUNT(*) DESC;
```

**Result:**
Shows which admins have been most active with photo uploads.

---

### Query: Find broken photo references

```sql
SELECT 
  'student' as entity_type,
  s.id,
  s.name,
  s.photo_path
FROM students s
WHERE photo_path IS NOT NULL
  AND photo_path NOT LIKE 'uploads/profile/students/%'

UNION

SELECT 
  'teacher' as entity_type,
  t.id,
  t.name,
  t.photo_path
FROM teachers t
WHERE photo_path IS NOT NULL
  AND photo_path NOT LIKE 'uploads/profile/teachers/%';
```

**Result:**
Identifies any misplaced or incorrectly pathed photo references.

---

### Query: Photo storage analysis

```sql
SELECT 
  'students' as entity_type,
  COUNT(*) as total_entities,
  SUM(CASE WHEN photo_path IS NOT NULL THEN 1 ELSE 0 END) as entities_with_photos,
  MIN(photo_updated_at) as oldest_photo,
  MAX(photo_updated_at) as newest_photo

UNION

SELECT 
  'teachers' as entity_type,
  COUNT(*) as total_entities,
  SUM(CASE WHEN photo_path IS NOT NULL THEN 1 ELSE 0 END) as entities_with_photos,
  MIN(photo_updated_at) as oldest_photo,
  MAX(photo_updated_at) as newest_photo
FROM teachers;
```

**Result:**
Complete overview of photo storage status.

---

## Data Maintenance Queries

### Clear all photos (CAREFUL!)

```sql
UPDATE students SET photo_path = NULL, photo_updated_at = NULL;
UPDATE teachers SET photo_path = NULL, photo_updated_at = NULL;
```

---

### Clear photos not updated in 1 year

```sql
UPDATE students 
SET photo_path = NULL, photo_updated_at = NULL
WHERE photo_path IS NOT NULL
AND photo_updated_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

---

### Verify photo_path format

```sql
SELECT 
  id, 
  name,
  photo_path,
  CASE 
    WHEN photo_path LIKE 'uploads/profile/students/student_%' THEN 'Valid'
    WHEN photo_path IS NULL THEN 'No photo'
    ELSE 'Invalid format'
  END as path_status
FROM students
WHERE photo_path IS NOT NULL;
```

---

## Backup & Restore

### Backup student photos metadata

```sql
SELECT id, name, roll_no, photo_path, photo_updated_at
INTO OUTFILE '/tmp/student_photos_backup.csv'
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
FROM students
WHERE photo_path IS NOT NULL;
```

---

### Backup teacher photos metadata

```sql
SELECT id, name, teacher_code, photo_path, photo_updated_at
INTO OUTFILE '/tmp/teacher_photos_backup.csv'
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
FROM teachers
WHERE photo_path IS NOT NULL;
```

---

## Performance Optimization

### Add index for photo queries (Optional)

```sql
ALTER TABLE students ADD INDEX idx_photo_path (photo_path);
ALTER TABLE teachers ADD INDEX idx_photo_path (photo_path);
```

This speeds up queries searching for NULL or non-NULL photo paths.

---

## Monitoring & Diagnostics

### Check for orphaned photo references

Photos stored in database but files don't exist (requires manual file system check):

```sql
SELECT 
  'student' as type,
  s.id,
  s.name,
  s.photo_path
FROM students s
WHERE photo_path IS NOT NULL

UNION

SELECT 
  'teacher' as type,
  t.id,
  t.name,
  t.photo_path
FROM teachers t
WHERE photo_path IS NOT NULL
ORDER BY type, id;
```

Then manually verify files exist in `uploads/profile/` directories.

---

### Activity Log Summary

```sql
SELECT 
  related_table,
  action_type,
  COUNT(*) as count,
  MAX(created_at) as last_operation
FROM activity_log
WHERE related_table IN ('students', 'teachers')
GROUP BY related_table, action_type;
```

---

## Testing Queries

### Test: Verify columns exist

```sql
SHOW COLUMNS FROM students WHERE Field IN ('photo_path', 'photo_updated_at');
SHOW COLUMNS FROM teachers WHERE Field IN ('photo_path', 'photo_updated_at');
```

Should show 2 rows for each table.

---

### Test: Insert sample photo path

```sql
UPDATE students 
SET photo_path = 'uploads/profile/students/student_101_test.jpg',
    photo_updated_at = NOW()
WHERE id = 1;

-- Verify
SELECT id, name, photo_path, photo_updated_at FROM students WHERE id = 1;
```

---

### Test: Clear sample photo path

```sql
UPDATE students 
SET photo_path = NULL,
    photo_updated_at = NULL
WHERE id = 1;

-- Verify
SELECT id, name, photo_path, photo_updated_at FROM students WHERE id = 1;
```

---

## Troubleshooting SQL Issues

### Issue: Columns not appearing after update

**Solution:**
1. Clear browser cache
2. Hard refresh page (Ctrl+Shift+R)
3. Check config/schema.php is being included in database.php
4. Verify database user has ALTER TABLE permissions

---

### Issue: Photo path not updating

**Verify:**
```sql
-- Check last update
SELECT * FROM students WHERE id = 102;

-- Check if update query works manually
UPDATE students 
SET photo_path = 'uploads/profile/students/student_102_test.jpg',
    photo_updated_at = NOW()
WHERE id = 102;

-- Verify update
SELECT photo_path, photo_updated_at FROM students WHERE id = 102;
```

---

### Issue: Activity log not recording

**Verify activity_log table:**
```sql
SHOW TABLES LIKE 'activity_log';

-- Check structure
DESC activity_log;

-- Check entries
SELECT * FROM activity_log WHERE related_table IN ('students', 'teachers') ORDER BY created_at DESC LIMIT 5;
```

---

## Migration Guide (If Existing Data)

If you have existing systems with photos in different format:

```sql
-- Assuming old photos are stored with old path format
-- Old format: 'documents/profile_pics/student_101.jpg'
-- New format: 'uploads/profile/students/student_101_timestamp_random.jpg'

UPDATE students 
SET photo_path = CONCAT('uploads/profile/students/student_', id, '_migrated.jpg'),
    photo_updated_at = NOW()
WHERE photo_path IS NOT NULL
AND photo_path LIKE 'documents/profile_pics/%';

-- Verify migration
SELECT COUNT(*) as migrated_photos 
FROM students 
WHERE photo_path LIKE 'uploads/profile/students/%';
```

---

## References

- Database Configuration: `config/database.php`
- Schema Setup: `config/schema.php`
- Photo Utilities: `config/photo_utils.php`
- Activity Log Table: Created by `config/schema.php`

**Note:** This guide assumes MySQL 5.7+ compatibility. Syntax may vary slightly with older versions.
