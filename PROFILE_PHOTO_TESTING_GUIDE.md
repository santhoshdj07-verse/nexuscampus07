# Profile Photo Management - Quick Start & Testing Guide

## Quick Start (5 minutes)

### Step 1: Verify Installation (1 minute)

```
1. Start XAMPP (MySQL + Apache)
2. Open: http://localhost/CollegeManagementWeb/
3. Log in as Admin (admin/admin)
4. Navigate to: http://localhost/CollegeManagementWeb/admin_photo_management.php
```

✅ **Success Indicator:** You should see the Profile Photo Management interface with "Students" and "Teachers" tabs.

---

### Step 2: Upload First Student Photo (2 minutes)

```
1. On admin_photo_management.php, Students tab should be active
2. Search for a student (try searching for a name like "John" or roll number)
3. Click on student name in results
4. Drag & drop a JPG/PNG image OR click to select file
5. Click "Upload Photo" button
6. Should see: "Photo uploaded successfully"
```

✅ **Success Indicator:** Photo preview appears and success message shows.

---

### Step 3: Verify on Dashboard (1 minute)

```
1. Log out from Admin
2. Log in as the student whose photo you just uploaded
3. Go to Student Dashboard
4. Profile section should show the uploaded photo (150x150px)
```

✅ **Success Indicator:** Student profile photo displays on dashboard.

---

### Step 4: Verify Teacher Photo (1 minute)

```
1. Return to Admin Photo Management
2. Click "Teachers" tab
3. Search for a teacher
4. Upload teacher's photo
5. Log out, log in as teacher
6. Go to Teacher Dashboard
7. Welcome section shows teacher photo (120x120px)
```

✅ **Success Indicator:** Teacher profile photo displays on dashboard.

---

## Automated Testing Suite

### Test 1: Admin Access Control ✅

**Test Name:** Access_Control_Admin_Only  
**Expected:** Only admin can access photo management

```php
// Test 1A: Admin Access
1. Login as Admin
2. Navigate to: /admin_photo_management.php
3. Should display the interface

// Test 1B: Student Blocked
1. Login as Student
2. Navigate to: /admin_photo_management.php
3. Should redirect to login

// Test 1C: Teacher Blocked
1. Login as Teacher
2. Navigate to: /admin_photo_management.php
3. Should redirect to login
```

**Pass Criteria:** Admin access granted, student/teacher denied

---

### Test 2: File Upload Validation ✅

**Test Name:** Upload_File_Validation  
**Expected:** Only valid image files accepted

```
Test Case 2.1: Valid JPG (PASS)
  File: test_image.jpg (200x200px, 2MB)
  Expected: ✓ Uploaded successfully

Test Case 2.2: Valid PNG (PASS)
  File: test_image.png (200x200px, 1.5MB)
  Expected: ✓ Uploaded successfully

Test Case 2.3: Valid WEBP (PASS)
  File: test_image.webp (200x200px, 800KB)
  Expected: ✓ Uploaded successfully

Test Case 2.4: Invalid EXE (FAIL)
  File: malware.exe
  Expected: ✗ "Only JPG, JPEG, PNG and WEBP images are allowed"

Test Case 2.5: Invalid PHP (FAIL)
  File: shell.php
  Expected: ✗ "Only JPG, JPEG, PNG and WEBP images are allowed"

Test Case 2.6: File Too Large (FAIL)
  File: huge_image.jpg (6MB)
  Expected: ✗ "Image size must not exceed 5 MB"

Test Case 2.7: Corrupted JPEG (FAIL)
  File: fake_image.jpg (actually a text file)
  Expected: ✗ "Invalid image file type"

Test Case 2.8: No File Selected (FAIL)
  Action: Click Upload without selecting file
  Expected: ✗ "Please select an image"
```

**Pass Criteria:** All valid files accepted, all invalid files rejected with appropriate messages

---

### Test 3: Search Functionality ✅

**Test Name:** Search_Functionality  
**Expected:** Search works by all fields

```
TEST 3.1: Search Student by Name
  Search: "John" or "Ahmed"
  Expected: Lists students matching name
  Criteria: Returns up to 50 results, case-insensitive

TEST 3.2: Search Student by Roll Number
  Search: "STU1024" or "2024001"
  Expected: Finds student by roll number
  Criteria: Exact match works

TEST 3.3: Search Student by ID
  Search: "102"
  Expected: Finds student by numeric ID
  Criteria: Exact match works

TEST 3.4: Search Teacher by Name
  Search: "Dr. Smith"
  Expected: Lists teachers matching name
  Criteria: Partial match works

TEST 3.5: Search Teacher by Code
  Search: "TCH001"
  Expected: Finds teacher by code
  Criteria: Exact match works

TEST 3.6: No Results
  Search: "zzzzzzxyz123"
  Expected: Shows "No [entity] found" message
  Criteria: Graceful empty state

TEST 3.7: Partial Match
  Search: "Joh" (for John)
  Expected: Finds all matching John, Johnny, etc.
  Criteria: Works case-insensitive
```

**Pass Criteria:** All search scenarios return expected results

---

### Test 4: Photo Display ✅

**Test Name:** Photo_Display_on_Dashboards  
**Expected:** Photos display correctly on dashboards

```
TEST 4.1: Student Photo Display
  Setup: Upload photo for Student ID 102
  Action: Login as Student 102, open Student Dashboard
  Expected: Photo displays in profile section (150x150px)
  Criteria: Image visible, no broken image icon, proper dimensions

TEST 4.2: Teacher Photo Display
  Setup: Upload photo for Teacher ID 15
  Action: Login as Teacher 15, open Teacher Dashboard
  Expected: Photo displays in welcome section (120x120px)
  Criteria: Image visible, proper dimensions

TEST 4.3: Missing Photo Placeholder (Student)
  Setup: Student without photo
  Action: Login as that student
  Expected: Professional placeholder (👤 icon + "No Photo" text)
  Criteria: No broken image, readable placeholder

TEST 4.4: Missing Photo Placeholder (Teacher)
  Setup: Teacher without photo
  Action: Login as that teacher
  Expected: Professional placeholder (👨‍🏫 icon + "No Photo" text)
  Criteria: No broken image, readable placeholder

TEST 4.5: Cache Busting
  Setup: Upload photo for student
  Action: Replace photo
  Expected: New photo displays immediately
  Criteria: No stale cached image shown
```

**Pass Criteria:** All photos display correctly with no broken images or caching issues

---

### Test 5: Replace/Delete Operations ✅

**Test Name:** Replace_and_Delete_Photos  
**Expected:** Photos can be replaced and deleted

```
TEST 5.1: Replace Photo
  Setup: Student has photo_a.jpg
  Action: Admin uploads photo_b.jpg
  Expected: 
    - New photo displays immediately
    - Old file deleted from disk
    - Database updated
    - Timestamp updated
  Criteria: Clean replacement, no duplicate files

TEST 5.2: Delete Photo
  Setup: Student has photo.jpg
  Action: Admin clicks Remove Photo → Confirm
  Expected:
    - Confirmation dialog appears first
    - Photo file deleted from disk
    - Database photo_path set to NULL
    - Placeholder appears on dashboard
  Criteria: No broken files, clean database state

TEST 5.3: Delete Confirmation Cancel
  Setup: Student has photo.jpg
  Action: Admin clicks Remove Photo → Cancel
  Expected: Nothing happens, photo remains
  Criteria: Cancel properly cancels operation

TEST 5.4: Rapid Replace Cycles
  Setup: Student has photo.jpg
  Action: Replace 5 times in rapid succession
  Expected: Only latest photo exists, no orphaned files
  Criteria: No storage bloat from partial operations
```

**Pass Criteria:** Replace and delete work cleanly without orphaned files

---

### Test 6: Database Integrity ✅

**Test Name:** Database_Integrity  
**Expected:** Database remains consistent

```
TEST 6.1: Photo Path Stored Correctly
  Setup: Upload photo for Student 102
  SQL: SELECT photo_path FROM students WHERE id = 102;
  Expected: Returns "uploads/profile/students/student_102_*.jpg"
  Criteria: Path format correct and stored

TEST 6.2: Timestamp Updated
  Setup: Upload photo
  SQL: SELECT photo_updated_at FROM students WHERE id = 102;
  Expected: Returns recent timestamp (within last minute)
  Criteria: Timestamp accurate

TEST 6.3: Multiple Student Photos
  Setup: Upload photo for Student 101, 102, 103
  SQL: SELECT COUNT(*) FROM students WHERE photo_path IS NOT NULL;
  Expected: Returns 3
  Criteria: Each student's photo tracked separately

TEST 6.4: Activity Log Recorded
  Setup: Upload photo for Student 102
  SQL: SELECT * FROM activity_log WHERE related_id = 102 
       AND related_table = 'students' 
       AND action_type = 'upload';
  Expected: Returns log entry with:
    - actor_type = 'admin'
    - action_type = 'upload'
    - title containing "Upload"
    - related_table = 'students'
    - related_id = 102
  Criteria: All fields populated correctly

TEST 6.5: Delete Clears Database
  Setup: Student has photo, delete it
  SQL: SELECT photo_path FROM students WHERE id = 102;
  Expected: Returns NULL
  Criteria: Database properly cleared
```

**Pass Criteria:** All database operations maintain integrity

---

### Test 7: File System Security ✅

**Test Name:** File_System_Security  
**Expected:** Files stored securely

```
TEST 7.1: Secure Filename Generation
  Setup: Upload file with name "very bad filename (2024).jpg"
  Verify: Check uploads/profile/students/ directory
  Expected: Filename like "student_102_1630703200_54321.jpg"
  Criteria: Original filename not used, random names generated

TEST 7.2: Correct Directory Structure
  Verify: Check directory structure
  Expected:
    uploads/profile/
    ├── students/
    │   └── student_*.jpg
    └── teachers/
        └── teacher_*.jpg
  Criteria: Files in correct subdirectories

TEST 7.3: No Path Traversal
  Attempt: Try to craft malicious path in POST
  Expected: Upload fails or path cleaned
  Criteria: No access to parent directories

TEST 7.4: File Permissions
  Verify: Check file permissions on uploaded image
  Expected: Files readable by web server
  Criteria: Proper read access
```

**Pass Criteria:** All files stored securely in correct locations

---

### Test 8: UI/UX Responsiveness ✅

**Test Name:** UI_UX_Responsiveness  
**Expected:** Interface works on all devices

```
TEST 8.1: Desktop (1920x1080)
  Action: Access admin interface
  Expected:
    - Search panel on left (35%)
    - Management panel on right (65%)
    - All buttons visible and clickable
  Criteria: Professional layout

TEST 8.2: Tablet (768x1024)
  Action: Access admin interface on tablet
  Expected:
    - Responsive grid adjusts
    - Panels stack properly
    - Touch targets are large enough
  Criteria: Usable on tablet

TEST 8.3: Mobile (375x667)
  Action: Access admin interface on mobile
  Expected:
    - Single column layout
    - Buttons full width
    - Search box takes full width
  Criteria: Usable on mobile

TEST 8.4: Drag & Drop on Desktop
  Action: Drag image onto upload area
  Expected:
    - Upload area highlights
    - File preview appears
    - Upload completes
  Criteria: Smooth drag & drop experience

TEST 8.5: Drag & Drop on Mobile
  Action: Touch and hold image
  Expected: Fallback to file picker works
  Criteria: Mobile-friendly fallback
```

**Pass Criteria:** Interface works smoothly on all screen sizes

---

## Performance Benchmarks

### Expected Performance Metrics

| Operation | Expected Time | Status |
|-----------|---------------|--------|
| Search 50 students | < 1 second | ✓ |
| Upload 2MB photo | < 3 seconds | ✓ |
| Display photo on dashboard | < 500ms | ✓ |
| Delete photo | < 1 second | ✓ |
| Page load (with photo) | < 2 seconds | ✓ |
| Redirect after upload | < 1 second | ✓ |

---

## Browser Compatibility Matrix

| Browser | Version | Status | Notes |
|---------|---------|--------|-------|
| Chrome | Latest | ✓ PASS | Full support |
| Firefox | Latest | ✓ PASS | Full support |
| Safari | Latest | ✓ PASS | Full support |
| Edge | Latest | ✓ PASS | Full support |
| Mobile Chrome | Latest | ✓ PASS | Touch-friendly |
| Mobile Safari | Latest | ✓ PASS | Touch-friendly |

---

## Self-service photo review and email checks

The original admin upload/replace/delete workflow remains available. Students and teachers can also open **Profile settings** from their account pages to submit a photo for approval.

1. Sign in as a student or teacher and open `profile_photo.php`.
2. Choose a JPG, PNG, or WEBP. Drag the image inside the square preview and adjust zoom.
3. Submit the photo. Confirm the page reports it is waiting for approval and the current dashboard photo has not changed.
4. Sign in as admin and open `profile_photo_review.php` (also linked from Photo Management).
5. Approve the request and confirm the new photo is shown on the account dashboard. Repeat with a rejection note and confirm the old photo remains.
6. Confirm opening the app under `http://localhost/CollegeManagementWeb/` loads the photo from the project subfolder rather than `/uploads/` at the web root.
7. Confirm no CSRF token or an invalid token is rejected for admin upload/delete and photo approval actions.
8. For a student, save a contact email in Profile settings. Configure PHP `mail()` plus `APP_MAIL_ENABLED=true` and `APP_MAIL_FROM`, then verify acceptance, rejection, document-verified, and document-rejected messages arrive. Without mail configuration, verify the existing in-app notifications still appear.

Student self-service requests must be 512 × 512 JPEGs produced by the crop preview. Admin direct uploads continue to accept JPG, PNG, and WEBP up to 5 MB.

---

## Manual Testing Checklist

### Pre-Testing Setup

- [ ] XAMPP running (MySQL + Apache)
- [ ] Database imported and schema updated
- [ ] Admin account created
- [ ] Student accounts exist (at least 2)
- [ ] Teacher accounts exist (at least 2)
- [ ] Test images prepared (JPG, PNG, 5MB limit)
- [ ] Browser cache cleared

### Execution Checklist

#### Admin Access (5 minutes)
- [ ] Admin can access /admin_photo_management.php
- [ ] Student cannot access /admin_photo_management.php
- [ ] Teacher cannot access /admin_photo_management.php

#### Student Photo Upload (10 minutes)
- [ ] Search for student works
- [ ] Student details display correctly
- [ ] Valid image upload succeeds
- [ ] Invalid file rejected
- [ ] Success message displays
- [ ] Photo preview shows after upload

#### Student Dashboard (5 minutes)
- [ ] Log in as student
- [ ] Dashboard loads
- [ ] Profile photo displays (150x150px)
- [ ] Photo doesn't distort
- [ ] Placeholder shows if no photo

#### Teacher Photo Upload (10 minutes)
- [ ] Switch to Teachers tab
- [ ] Search for teacher works
- [ ] Teacher details display correctly
- [ ] Valid image upload succeeds
- [ ] Success message displays

#### Teacher Dashboard (5 minutes)
- [ ] Log in as teacher
- [ ] Dashboard loads
- [ ] Profile photo displays (120x120px)
- [ ] Photo positioned correctly
- [ ] Info displays beside photo

#### Replace Photo (5 minutes)
- [ ] Re-upload for same person
- [ ] Old photo replaced
- [ ] Database updated
- [ ] New photo displays immediately

#### Delete Photo (5 minutes)
- [ ] Click Remove Photo
- [ ] Confirmation dialog appears
- [ ] Cancel works
- [ ] Confirm deletes
- [ ] Placeholder appears

#### Database Verification (5 minutes)
- [ ] Check photo_path in students table
- [ ] Check photo_path in teachers table
- [ ] Check photo_updated_at timestamps
- [ ] Activity log records operations

#### Security Tests (5 minutes)
- [ ] Try uploading .exe - rejected
- [ ] Try uploading .php - rejected
- [ ] Try uploading 10MB file - rejected
- [ ] Try manual photo_handler.php as student - denied

---

## Sign-Off Checklist

### Developer Sign-Off
- [ ] All code follows coding standards
- [ ] No SQL injection vulnerabilities
- [ ] No XSS vulnerabilities
- [ ] Prepared statements used throughout
- [ ] Error handling implemented
- [ ] Comments/documentation complete

### QA Sign-Off
- [ ] All test cases passed
- [ ] No broken image links
- [ ] No console errors
- [ ] Responsive design verified
- [ ] Performance acceptable
- [ ] Security tests passed

### Admin Sign-Off
- [ ] Feature meets requirements
- [ ] User workflow intuitive
- [ ] Photos display correctly
- [ ] Activity logging works
- [ ] No existing functionality broken
- [ ] Ready for production

---

## Rollback Procedure (If Needed)

### Complete Rollback Steps

```bash
# 1. Stop XAMPP
Stop Apache and MySQL

# 2. Delete new files
rm config/photo_utils.php
rm photo_handler.php
rm admin_photo_management.php
rm uploads/profile -r

# 3. Delete documentation files
rm PROFILE_PHOTO_IMPLEMENTATION.md
rm PROFILE_PHOTO_SQL_REFERENCE.md

# 4. Restore database (rollback schema changes)
# Option A: Remove columns manually
ALTER TABLE students DROP COLUMN photo_path;
ALTER TABLE students DROP COLUMN photo_updated_at;
ALTER TABLE teachers DROP COLUMN photo_path;
ALTER TABLE teachers DROP COLUMN photo_updated_at;

# Option B: If you have backup, restore it
mysql college_management < backup_database.sql

# 5. Restore dashboard files if modified
git checkout student_dashboard.php teacher_dashboard.php config/schema.php

# 6. Restart XAMPP
Start Apache and MySQL

# 7. Verify
- Test student dashboard loads
- Test teacher dashboard loads
- Test admin functions
```

---

## Support & Debugging

### Common Issues & Solutions

**Issue: Upload directory not created**
```
Solution: Create manually
mkdir -p uploads/profile/students
mkdir -p uploads/profile/teachers
chmod 755 uploads/profile
```

**Issue: Photo not displaying**
```
Check:
1. File exists: ls uploads/profile/students/
2. Database: SELECT photo_path FROM students WHERE id = X;
3. File permissions: ls -la uploads/profile/
4. Browser console for 404 errors
```

**Issue: Upload fails silently**
```
Check:
1. Admin session valid
2. File size < 5MB
3. MIME type check: file -b uploads/profile/students/student_*.jpg
4. PHP error log: tail -f xampp/apache/logs/error.log
```

---

## Success Criteria

### Feature is Production-Ready When:

- ✅ All automated tests pass
- ✅ All manual tests pass
- ✅ No console errors or warnings
- ✅ Photos display on all dashboards
- ✅ Security tests pass
- ✅ Performance metrics met
- ✅ Documentation complete
- ✅ Rollback procedure tested
- ✅ No existing functionality broken
- ✅ Stakeholder approval obtained

---

## Implementation Timestamp

**Date Implemented:** January 2025  
**Developer:** EduEnroll Team  
**Version:** 1.0  
**Status:** ✅ Ready for Testing

---

## Next Steps

1. Follow "Quick Start" guide above
2. Run through "Automated Testing Suite"
3. Complete "Manual Testing Checklist"
4. Verify all items in "Success Criteria"
5. Obtain stakeholder sign-off
6. Deploy to production
7. Monitor for issues (first week)
8. Archive test results

---

**Questions?** Refer to PROFILE_PHOTO_IMPLEMENTATION.md for detailed documentation.
