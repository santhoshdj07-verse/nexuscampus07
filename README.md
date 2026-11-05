# NEXUSCAMPUS

## College Management Web

A PHP-based college management system for handling admissions, student records, document verification, and administrative workflows.

## Project structure

- `config/database.php` – database connection settings
- `config/schema.php` – schema bootstrap and table setup
- `includes/` – shared UI partials
- `uploads/student_documents/` – uploaded student files
- `uploads/profile/` – profile photos and pending photo reviews
- Root PHP files – application pages and actions

## Local setup

1. Start Apache/MySQL with XAMPP.
2. Create a MySQL database named `college_management`.
3. Update database credentials if needed in `config/database.php`.
4. Run the project from the workspace root in a browser, for example via XAMPP or:

```bash
php -S localhost:8000
```

## Notes

- The application currently uses a direct MySQL connection via the root user.
- For production use, move credentials into environment variables or a safer configuration strategy.
- Uploaded files are stored under `uploads/student_documents/` and `uploads/profile/`.
- Students and teachers can submit a cropped profile photo from **Profile settings**. Admins approve or reject requests in **Profile photo approvals**; the existing admin upload/replace/remove screen remains available.

## Profile photos and status emails

Students can save a contact email and submit a photo from `profile_photo.php`; teachers can submit a photo from the same page. The built-in cropper creates a square 512 × 512 JPEG. Submitted photos stay private to the review workflow and do not replace the current profile image until an admin approves them.

Status emails for application acceptance/rejection and document verification/rejection are optional. The existing in-app notifications continue to work when email is off. To enable email, configure PHP's `mail()` transport (for XAMPP, use the SMTP settings in `php.ini`) and set these environment variables for the Apache process:

```text
APP_MAIL_ENABLED=true
APP_MAIL_FROM=admissions@example.edu
```

Leave `APP_MAIL_ENABLED` unset or false until the mail transport is configured. Students must save a valid contact email in Profile settings to receive these messages. Email handoff depends on the configured PHP mail transport; verify delivery with a real mailbox before deployment.

## Student record and planning workflows

Admins can open **Student Record Workflows** from the sidebar to see how the project manages student review lists, orders pending application review, organizes accepted-student records, finds and sorts students for admission decisions (CLO2), explores saved student connections, and plans teacher support (CLO3). These features read accepted students, active teachers, pending application numbers, and saved relationships from the database. The page explains the implementation techniques within each workflow; its demonstrations are read-only and do not alter student, application, or teacher records. Pending applicants' names are not exposed.

For a purpose-first explanation and test steps for the CLO2 and CLO3 features, see [DataStructures_Working.pdf](./DataStructures_Working.pdf).

To add a separate, repeatable sample cohort (10 demo applicants: 5 accepted and 5 pending, plus 3 demo teachers), set a local password of at least 12 characters and run the CLI seed script. Existing accounts are preserved:

```powershell
$env:COLLEGE_DEMO_PASSWORD = "Choose-a-local-demo-password"
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\CollegeManagementWeb\seed_course_demo.php"
Remove-Item Env:COLLEGE_DEMO_PASSWORD
```

## Full project guide and test plan

See [CollegeManagementWeb_Full_Project_Test_Guide.pdf](./CollegeManagementWeb_Full_Project_Test_Guide.pdf) for local startup, role-based login, registration, student application/document flow, admin and teacher modules, data-structures demonstrations, end-to-end test steps, expected results, and troubleshooting.
