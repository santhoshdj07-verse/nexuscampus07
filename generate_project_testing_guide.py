from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    PageBreak,
    Paragraph,
    Preformatted,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


ROOT = r"C:\xampp\htdocs\CollegeManagementWeb"
OUTPUT = ROOT + r"\CollegeManagementWeb_Full_Project_Test_Guide.pdf"
NAVY = colors.HexColor("#163A70")
INK = colors.HexColor("#172033")
MUTED = colors.HexColor("#526174")
PALE = colors.HexColor("#EFF5FC")
LINE = colors.HexColor("#CBD5E1")
GREEN = colors.HexColor("#E8F6EF")
GOLD = colors.HexColor("#FFF5D9")


def styles():
    base = getSampleStyleSheet()
    base.add(ParagraphStyle(
        name="CoverTitle", parent=base["Title"], fontName="Helvetica-Bold",
        fontSize=27, leading=33, textColor=NAVY, alignment=TA_CENTER, spaceAfter=10,
    ))
    base.add(ParagraphStyle(
        name="CoverSub", parent=base["Normal"], fontSize=12, leading=17,
        textColor=MUTED, alignment=TA_CENTER, spaceAfter=8,
    ))
    base.add(ParagraphStyle(
        name="Section", parent=base["Heading1"], fontSize=16, leading=19,
        textColor=NAVY, spaceBefore=3, spaceAfter=8, keepWithNext=True,
    ))
    base.add(ParagraphStyle(
        name="Subsection", parent=base["Heading2"], fontSize=11, leading=14,
        textColor=INK, spaceBefore=6, spaceAfter=4, keepWithNext=True,
    ))
    base.add(ParagraphStyle(
        name="Body9", parent=base["BodyText"], fontSize=9, leading=13,
        textColor=INK, spaceAfter=5,
    ))
    base.add(ParagraphStyle(
        name="Small", parent=base["BodyText"], fontSize=8, leading=11,
        textColor=MUTED, spaceAfter=4,
    ))
    base.add(ParagraphStyle(
        name="Callout", parent=base["BodyText"], fontSize=8.5, leading=12,
        textColor=INK, backColor=PALE, borderColor=LINE, borderWidth=.5,
        borderPadding=7, spaceBefore=4, spaceAfter=7,
    ))
    base.add(ParagraphStyle(
        name="Warning", parent=base["BodyText"], fontSize=8.5, leading=12,
        textColor=INK, backColor=GOLD, borderColor=colors.HexColor("#E8CC7A"),
        borderWidth=.5, borderPadding=7, spaceBefore=4, spaceAfter=7,
    ))
    base.add(ParagraphStyle(
        name="Good", parent=base["BodyText"], fontSize=8.5, leading=12,
        textColor=INK, backColor=GREEN, borderColor=colors.HexColor("#9BC9AD"),
        borderWidth=.5, borderPadding=7, spaceBefore=4, spaceAfter=7,
    ))
    base.add(ParagraphStyle(
        name="CodeBlock", fontName="Courier", fontSize=7.5, leading=10,
        textColor=INK, backColor=PALE, borderColor=LINE, borderWidth=.5,
        borderPadding=6, leftIndent=4, rightIndent=4, spaceBefore=3, spaceAfter=7,
    ))
    base.add(ParagraphStyle(
        name="TableCell", parent=base["BodyText"], fontSize=7.5, leading=10,
        textColor=INK,
    ))
    base.add(ParagraphStyle(
        name="TableHead", parent=base["BodyText"], fontName="Helvetica-Bold",
        fontSize=7.5, leading=10, textColor=colors.white,
    ))
    return base


def p(text, style):
    return Paragraph(text, style)


def bullet(text, s):
    return Paragraph("&bull;&nbsp;&nbsp;" + text, s)


def code(text, s):
    return Preformatted(text.strip("\n"), s)


def table(rows, widths, s, repeat=1):
    converted = []
    for row_index, row in enumerate(rows):
        converted.append([
            p(str(value), s["TableHead"] if row_index == 0 else s["TableCell"])
            for value in row
        ])
    result = Table(converted, colWidths=widths, repeatRows=repeat, hAlign="LEFT")
    result.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("GRID", (0, 0), (-1, -1), .4, LINE),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, PALE]),
    ]))
    return result


def footer(pdf, doc):
    pdf.saveState()
    w, h = A4
    pdf.setFillColor(NAVY)
    pdf.rect(0, h - 8 * mm, w, 8 * mm, fill=1, stroke=0)
    pdf.setStrokeColor(LINE)
    pdf.line(18 * mm, 14 * mm, w - 18 * mm, 14 * mm)
    pdf.setFont("Helvetica", 7.5)
    pdf.setFillColor(MUTED)
    pdf.drawString(18 * mm, 9 * mm, "CollegeManagementWeb | Full Project Test Guide")
    pdf.drawRightString(w - 18 * mm, 9 * mm, "Page %d" % doc.page)
    pdf.restoreState()


def add_checklist(story, items, s):
    for item in items:
        story.append(bullet(item, s["Body9"]))


def build():
    s = styles()
    doc = SimpleDocTemplate(
        OUTPUT, pagesize=A4, rightMargin=18 * mm, leftMargin=18 * mm,
        topMargin=16 * mm, bottomMargin=19 * mm,
        title="CollegeManagementWeb Full Project Guide and Test Plan",
        author="CollegeManagementWeb",
        subject="Role-based setup, feature guide, and end-to-end test instructions",
    )
    story = []

    # Cover and project map
    story.extend([
        Spacer(1, 28 * mm),
        p("COLLEGE MANAGEMENT WEB", s["CoverTitle"]),
        p("Full Project Guide &amp; Test Plan", s["CoverSub"]),
        Spacer(1, 4 * mm),
        p("From first launch and account registration to admissions, academics, reports, and student record workflows.", s["CoverSub"]),
        Spacer(1, 12 * mm),
        table([
            ["Portal", "Main responsibilities"],
            ["Student", "Register/sign in, submit admission application, upload documents, track notifications and academic results."],
            ["Admin", "Review applications/documents, process admissions, manage records, view network/search/reports/audit."],
            ["Teacher", "View department students, enter/publish marks, manage attendance/exams, review reports/profile."],
            ["Shared services", "MySQL database, role sessions, file uploads, notifications, activity/audit records."],
        ], [32 * mm, 138 * mm], s),
        Spacer(1, 9 * mm),
        p("<b>How to use this guide:</b> Start with Section 1, then follow the student-to-admin workflow in order. "
          "Sections for teachers and data structures can be tested independently. Each test gives an action and an expected result.", s["Callout"]),
        p("Prepared for the current project files and local demo data. The project uses PHP, MySQL/MariaDB, and XAMPP.", s["Small"]),
        PageBreak(),
    ])

    # Setup / access
    story.extend([
        p("1. Start the project and confirm the database", s["Section"]),
        p("Local XAMPP setup", s["Subsection"]),
    ])
    add_checklist(story, [
        "Open XAMPP Control Panel and start <b>Apache</b> and <b>MySQL</b>.",
        "Open <font name='Courier'>http://localhost/CollegeManagementWeb/</font>. The root index redirects to the login page.",
        "The app connects using <font name='Courier'>config/database.php</font>. Defaults are database <font name='Courier'>college_management</font>, host <font name='Courier'>127.0.0.1</font>, user <font name='Courier'>root</font>, blank password, and candidate ports 3306/3307/3308. Environment variables DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, and DB_PORT override defaults.",
        "On request, <font name='Courier'>config/schema.php</font> creates or upgrades the app tables. Confirm MySQL is running before opening the app.",
        "If Apache is on a non-default port, include it in the URL, for example <font name='Courier'>http://localhost:8080/CollegeManagementWeb/</font>.",
    ], s)
    story.extend([
        p("Current database snapshot", s["Subsection"]),
        table([
            ["Table/data", "Rows at guide creation", "Notes"],
            ["students", "14", "Includes existing accounts and the 10 DS26 demo accounts."],
            ["student_applications", "11", "10 DS26 demo applications: 5 Accepted and 5 Pending; one existing application."],
            ["teachers", "4", "3 DS-TEACH demo teachers plus one pre-existing teacher."],
            ["subjects / exam_schedule", "0 / 0", "Teacher marks/exam pages need academic setup records before meaningful end-to-end tests."],
            ["student_documents", "0", "Upload documents during testing; admin verification is a separate step."],
            ["student_connections", "7", "Includes sample relationships; the lab filters graph to approved students."],
        ], [43 * mm, 29 * mm, 98 * mm], s),
        p("The counts are a snapshot, not a promise that your database will always match. Your local records may change as you test.", s["Small"]),
        p("Demo sign-ins", s["Subsection"]),
        table([
            ["Role", "Login identifier", "Demo password"],
            ["Student", "Accepted: DS26-001 to DS26-005. Pending: DS26-006 to DS26-010. Use the roll number.", "Use the local COLLEGE_DEMO_PASSWORD value set during seeding."],
            ["Teacher", "sahana.mehta@example.test; anil.kumar@example.test; leena.joseph@example.test", "Use the local COLLEGE_DEMO_PASSWORD value set during seeding."],
            ["Admin", "Use the admin username and password already configured in your database.", "Not printed here; use your private admin credential."],
        ], [25 * mm, 104 * mm, 41 * mm], s),
                p("Demo-password handling: The seed script requires a local COLLEGE_DEMO_PASSWORD value. Do not store or share it. "
                    "The login form authenticates teachers by email, not teacher code.", s["Warning"]),
        PageBreak(),
    ])

    # Login and register
    story.extend([
        p("2. Login, registration, and role access", s["Section"]),
        p("One entry page serves all roles", s["Body9"]),
        p("On <font name='Courier'>login.php</font>, choose Student, Admin, or Teacher sign-in. "
          "Student login uses a roll number; admin login uses a username; teacher login uses an email. "
          "Passwords are verified against stored hashes. A successful login creates a role session and redirects to that role's dashboard.", s["Body9"]),
        p("Student registration test", s["Subsection"]),
    ])
    add_checklist(story, [
        "On the login screen, open the student registration form.",
        "Use a fresh roll number in the required pattern <font name='Courier'>26mcaa45</font> (2 digits + 4 letters + 2 digits). Do not use a roll number already in the database.",
        "Enter a name of letters/spaces up to 20 characters; choose an allowed department (MSC, MCA, BCA, BSC, LAW, CSE), semester I-VIII, 10-digit phone, CGPA formatted like 8.93 in the 0-10 range, and matching password fields (minimum 6 characters).",
        "Submit. Expected: registration success message. Sign in with the new roll number and password; expected destination is Student Dashboard.",
    ], s)
    story.extend([
        p("Negative registration tests", s["Subsection"]),
        table([
            ["Try", "Expected result"],
            ["Reuse the same roll number", "Duplicate registration is rejected."],
            ["Use an invalid roll format or phone not exactly 10 digits", "Validation message; no new account."],
            ["Use mismatched confirmation or a password shorter than 6", "Validation message; no new account."],
            ["Open a protected dashboard while logged out", "Redirect to the corresponding login page."],
        ], [73 * mm, 97 * mm], s),
        p("Important distinction: Registration creates a student account in the students table; it does not itself mean an admission application has been accepted. "
          "The student must submit an application, then the admin reviews it. Demo DS26 accounts were seeded directly for testing and do not match the public registration form's roll-number pattern.", s["Warning"]),
        PageBreak(),
    ])

    # Student journey
    story.extend([
        p("3. Student portal: application through progress", s["Section"]),
        p("Student Dashboard is the student's starting page. It summarizes profile, application/admission readiness, documents, notifications, attendance/academic status, and links to the relevant pages. "
          "Admission Timeline shows the student's workflow status.", s["Body9"]),
        p("End-to-end student test", s["Subsection"]),
    ])
    add_checklist(story, [
        "Sign in with DS26-006 and the demo password. Open Student Dashboard and confirm the account sees its own Pending application state.",
        "Open Student Application. Complete the application fields (program type UG/PG, course, previous qualification/institution/percentage, address, city/state/pincode) and submit. Verify the application appears in the student's application history/timeline.",
        "Before admin accepts the application, open Student Documents. Expected: document upload is locked with an explanation; do not expect pending students to submit documents.",
        "Sign out and sign in as admin; accept the application (see Section 4). Sign back in as the student and confirm the updated status/notification.",
        "After acceptance and verification, upload required documents (PDF/JPG/JPEG/PNG; maximum 5 MB per file) under the correct document type. Confirm each file appears in the document list.",
        "Sign in as admin and verify/reject a submitted document. Sign back in as student and check notification/status.",
        "After all required uploaded documents are verified, admin can complete admission. Confirm timeline/dashboard reflects the admission number/status.",
        "When teachers publish marks for the student's subject, open Student Marks and confirm published academic results display.",
    ], s)
    story.extend([
        p("Student pages and what to check", s["Subsection"]),
        table([
            ["Page", "Purpose / test"],
            ["student_dashboard.php", "Status cards, next action, document readiness, marks, attendance, notifications and profile photo."],
            ["student_application.php", "Submit/update the admission application; course choices depend on program rules."],
            ["student_documents.php", "Upload required documents after application acceptance/verification; test valid file and over-limit file."],
            ["admission_timeline.php", "Read progress stages and current state."],
            ["notification_center.php", "Open notices, mark one read, then mark all read; unread count should update."],
            ["student_profile.php", "Review the student's own profile information."],
            ["student_marks.php", "View marks after a teacher has published them; drafts should not be treated as published results."],
            ["student_network.php", "Admin feature; not a student self-service graph."],
        ], [47 * mm, 123 * mm], s),
        p("Document upload is gated by an Accepted application with verification timestamp. The document verifier handles the admin decision; upload does not complete admission automatically.", s["Callout"]),
        PageBreak(),
    ])

    # Admin
    story.extend([
        p("4. Admin portal: review and manage", s["Section"]),
        p("Sign in with the existing admin account. The admin dashboard is the navigation and workflow hub; sidebar entries open the modules below.", s["Body9"]),
        table([
            ["Module", "How it works / test action"],
            ["Applications - admin_applications.php", "Filter/search student applications. Accept, reject, or reset an application. Accepting creates an admission-processing row and notifies the student. Test with a Pending demo application."],
            ["Document Verification - document_verification.php", "Review documents for accepted applicants. Verify or reject with optional remarks. Confirm student notification and status."],
            ["Admission Processing - admission_processing.php", "Move an eligible accepted application to Processing, Completed, or Rejected. Completion is blocked until at least one document exists and every submitted document is Verified."],
            ["Students - students.php / edit_student.php", "List, add, edit, and delete student records. Test add/edit with a unique valid roll number. Take care: delete is destructive."],
            ["Teachers - teachers.php", "Create teacher accounts, see roster, and toggle ACTIVE/INACTIVE. Login with an inactive teacher should be denied."],
            ["Departments - department.php", "Review department information and related academic data available in this project."],
            ["Search & Sort - search_sort.php", "Search by roll/name and sort by name or CGPA. This project version displays accepted-application students only."],
            ["Student Network - student_network.php", "Create/remove valid relationships; students must exist, differ from each other, and share department or semester. Graph edges are persisted."],
            ["Reports - reports.php", "Inspect available reports and filters; compare displayed totals to the records in the current database."],
            ["Activity Log - activity_log.php", "Review logged application and administrative activity after completing test actions."],
            ["Admin Photo Management - admin_photo_management.php", "Find a student/teacher and upload/replace/delete a profile image. Allowed image formats JPG/JPEG/PNG/WEBP, max 5 MB."],
            ["Student Record Workflows - data_structures_showcase.php", "Database-fed student review and academic-planning tools; details and tests in Section 8."],
        ], [51 * mm, 119 * mm], s),
        p("Application review test", s["Subsection"]),
    ])
    add_checklist(story, [
        "Open Applications and locate DS26-006 (Pending). Use Accept. Expected: status Accepted, a student notification, and an admission-processing record.",
        "Repeat with DS26-007 using Reject, then confirm Rejected state. Use reset-to-pending only if you need to repeat this test.",
        "Return to student login after acceptance and verify its application state changed.",
        "Test duplicate/invalid selection controls without deleting any real records.",
    ], s)
    story.extend([
        p("Avoid delete tests on production/important records. Use only the DS26 demo cohort for destructive actions and make a database backup first.", s["Warning"]),
        PageBreak(),
    ])

    # Docs & admission
    story.extend([
        p("5. Documents and admission completion rules", s["Section"]),
        p("Admission is a sequence of separate states. Application acceptance is not the same as completed admission.", s["Body9"]),
        table([
            ["Stage", "Owner", "Expected transition"],
            ["Application submitted", "Student", "Application is recorded; admin sees it for review."],
            ["Accepted / Rejected", "Admin", "Accepted opens the document workflow; rejection stores the decision and notification."],
            ["Documents uploaded", "Student", "Required document types are associated with the student's application."],
            ["Documents Verified / Rejected", "Admin", "Each file is reviewed and its status/remarks are saved."],
            ["Admission Processing", "Admin", "An accepted application can move to Processing."],
            ["Completed", "Admin", "Allowed only when at least one document is present and all documents for the application are verified; an admission number is generated."],
        ], [40 * mm, 28 * mm, 102 * mm], s),
        p("Test a successful completion", s["Subsection"]),
    ])
    add_checklist(story, [
        "Use an accepted applicant such as DS26-001. Upload at least one allowed document with size below 5 MB.",
        "In Document Verification, locate the uploaded file and mark it Verified.",
        "In Admission Processing, choose Processing first if desired, then Completed. Expected: generated admission number and Completed status.",
        "Try to complete another accepted application without documents. Expected: completion is blocked with a clear requirement message.",
        "Try a file above 5 MB or an unsupported type. Expected: upload is rejected and no usable document row is created.",
        "Check the student's notification center and admission timeline after status changes.",
    ], s)
    story.extend([
        p("The 5 accepted DS26 samples are application-approved but currently have no sample documents. The 5 pending DS26 applicants are not eligible for document upload or admission processing until admin acceptance. "
          "Testing upload/verification is a manual action in the portal.", s["Warning"]),
        p("Document security basics to verify", s["Subsection"]),
    ])
    add_checklist(story, [
        "Use safe test documents, never upload confidential identity documents into a shared demo system.",
        "Uploaded files are placed under uploads/student_documents; confirm the file appears only against the correct student/application.",
        "Do not use arbitrary executable files as test payloads. A harmless unsupported text file is enough to check rejection.",
    ], s)
    story.append(PageBreak())

    # Teacher module
    story.extend([
        p("6. Teacher portal: classes and academic work", s["Section"]),
        p("Teacher accounts sign in by email. Only ACTIVE accounts can authenticate. Teacher pages are scoped to the signed-in teacher and department; use the sample emails from Section 1.", s["Body9"]),
        table([
            ["Page", "Testing path"],
            ["teacher_dashboard.php", "Check teacher identity and summary cards, then use the action tiles."],
            ["teacher_students.php", "View department/assigned student cards and open a student's academic context."],
            ["teacher_marks.php", "Select a department student, semester, and subject. Enter valid midterm/end-semester marks. Save as draft, then publish; verify published marks in the student portal."],
            ["teacher_attendance.php", "Select date/session, set each student's status (present/late/absent), save, refresh, and confirm values persist."],
            ["teacher_examinations.php", "Review scheduled exams relevant to the teacher's department/subjects."],
            ["teacher_reports.php", "Review available academic performance reports and filters."],
            ["teacher_profile.php", "View/update the current teacher profile as supported by the form."],
        ], [46 * mm, 124 * mm], s),
        p("Prerequisite in the current database", s["Subsection"]),
        p("At guide creation, there are no rows in subjects or exam_schedule. Consequently, Marks Entry and Examination pages may show empty selectors or no exams until academic setup data is present. "
          "The sample cohort seed creates teachers and student/application data, not subjects, exams, marks, or attendance. Do not interpret an empty academic list as a login failure.", s["Warning"]),
        p("Teacher test when academic rows exist", s["Subsection"]),
    ])
    add_checklist(story, [
        "Use an active teacher account whose department matches a configured subject and approved/admitted students.",
        "Enter marks within the displayed allowed maximum. The page uses a midterm/end-semester split and saves a draft or publishes.",
        "Test out-of-range marks; expected: validation error and unchanged stored marks.",
        "Mark attendance for an available scheduled session, save, reload, and check persistence.",
        "Log in as the student; drafts should not be visible as published results, while published results should appear.",
        "Set a teacher account inactive from Teachers, log out, then test login. Expected: account-deactivated message.",
    ], s)
    story.extend([
        p("Do not fabricate academic records just to make the screens look full. Configure actual test subjects/schedules through your institution's chosen setup process, then use the portal's normal flows.", s["Callout"]),
        PageBreak(),
    ])

    # Admin reports / profile photos
    story.extend([
        p("7. Dashboard, reports, photos, and notifications", s["Section"]),
        p("Admin dashboard", s["Subsection"]),
        p("Use dashboard.php to check counts and the Pending Action Center. After accepting an application, uploading a document, or completing admission, refresh and confirm the relevant cards/counts move consistently. The dashboard summarizes records; it is not the source of the workflow action.", s["Body9"]),
        p("Reports and activity", s["Subsection"]),
    ])
    add_checklist(story, [
        "Open Reports and try each visible report tab/filter. Search a known demo roll number where supported and compare report data with the student record.",
        "After testing application acceptance/rejection, inspect Activity Log for corresponding admin actions and timestamps.",
        "Open Notification Center as a student, mark one notice read, then mark all read. Confirm unread indicator/count changes.",
    ], s)
    story.extend([
        p("Profile photo test", s["Subsection"]),
    ])
    add_checklist(story, [
        "Admin opens admin_photo_management.php and searches a demo student or teacher.",
        "Upload a non-sensitive JPG, PNG, or WEBP image under 5 MB. Verify preview and success state.",
        "Log in as that user and verify photo appears on the dashboard. Replace it, then delete it and confirm the placeholder returns.",
        "Try a file larger than 5 MB and an unsupported type; confirm rejection. The feature is admin-only.",
    ], s)
    story.extend([
        p("Sample cohort sign-ins for end-to-end testing", s["Subsection"]),
        table([
            ["Accepted accounts", "Pending accounts"],
            ["DS26-001 Aarav Nair - CSE / Sem 1 / CGPA 8.71<br/>DS26-002 Diya Sharma - CSE / Sem 2 / CGPA 9.14<br/>DS26-003 Ishan Rao - CSE / Sem 3 / CGPA 7.82<br/>DS26-004 Mira Das - CSE / Sem 4 / CGPA 8.46<br/>DS26-005 Neel Menon - CSE / Sem 1 / CGPA 9.02",
             "DS26-006 Riya Shah - CSE / Sem 1 / CGPA 7.54<br/>DS26-007 Kabir Sen - CSE / Sem 2 / CGPA 8.11<br/>DS26-008 Tara Iyer - CSE / Sem 3 / CGPA 7.96<br/>DS26-009 Dev Patel - CSE / Sem 4 / CGPA 8.32<br/>DS26-010 Anya Roy - CSE / Sem 1 / CGPA 8.04"],
        ], [85 * mm, 85 * mm], s),
        Spacer(1, 5),
        table([
            ["Demo teacher", "Login email", "Department / designation"],
            ["Sahana Mehta", "sahana.mehta@example.test", "CSE / Assistant Professor"],
            ["Anil Kumar", "anil.kumar@example.test", "IT / Associate Professor"],
            ["Leena Joseph", "leena.joseph@example.test", "MCA / Assistant Professor"],
        ], [38 * mm, 78 * mm, 54 * mm], s),
        p("The seeded student/teacher accounts use the local COLLEGE_DEMO_PASSWORD value set when running seed_course_demo.php. The guide does not contain that value. Teacher authentication uses email.", s["Warning"]),
        PageBreak(),
    ])

    # DSA lab
    story.extend([
        p("8. Student record workflows and academic planning", s["Section"]),
        p("Open Student Record Workflows from the admin sidebar. This page queries database records: accepted students, pending application tokens, active teachers, and saved student_connections edges. Student names/details for Pending applications are intentionally not passed to the page.", s["Body9"]),
        table([
            ["Feature", "How to test / expected result"],
            ["Linked list", "Use approved-student selector to insert, unlink, and traverse. Unlink changes the in-page list only; it never deletes the DB student."],
            ["Stack", "Push an approved student several times, then Pop. Last pushed item is removed first."],
            ["Queue", "Enqueue pending application-number tokens, then Dequeue next. Front token leaves first. Status in the admissions table is not changed."],
            ["Expression stacks", "Evaluate (8 + 4) * 3 / 2. Expected result 18; test 1 / 0 for a guarded error."],
            ["Binary tree", "Tree is built from accepted students. Inspect inorder, preorder, postorder roll sequences; no database write."],
            ["BST", "Search an exact accepted roll number such as DS26-003. The page displays found/missing and its comparison path."],
            ["Phase 3: Find and organize approved students for admission decisions (CLO2)", "Accepted student snapshot only: partial lookup checks each name/roll in turn; exact lookup orders a copy and repeatedly checks the middle roll number, narrowing to one half. Sorting compares roll/name as text or CGPA numerically; insertion ordering shifts into an ordered prefix, while merge ordering splits and merges sorted copies. Results and comparison counts are displayed without database writes."],
            ["Graph/BFS", "Adjacency list and matrix use saved student_connections restricted to accepted vertices. Choose BFS start; only connected component is visited."],
            ["Phase 4: Plan teacher support for accepted students (CLO3)", "The page combines Accepted student records with active teachers. Advising assigns one student at a time to the least-loaded same-department teacher when possible. Tutoring fills a student-by-budget score table and traces it back to choose the highest-need set within 1-50 hours. Adviser planning recursively tries teachers, enforcing ceiling caseload and unique semesters per teacher; conflicts undo a tentative assignment and try the next teacher. Proposals are read-only and not saved."],
        ], [35 * mm, 135 * mm], s),
        p("These algorithms run against the data loaded for the page and are not a replacement for persistent records. The database-backed relationship editor remains Student Network. "
          "Search & Sort links to the existing project search workflow.", s["Callout"]),
        PageBreak(),
    ])

    # full workflow checklist
    story.extend([
        p("9. Complete end-to-end test run", s["Section"]),
        p("Use demo records only. Keep the accepted/pending test distinction intact; do not complete or reject all sample applications if you still need them for demonstrations.", s["Warning"]),
        table([
            ["#", "Action", "Expected checkpoint"],
            ["1", "Start MySQL and Apache; open project URL.", "Login page appears without DB connection error."],
            ["2", "Sign in as a demo pending student DS26-006.", "Student dashboard opens; own Pending application is visible."],
            ["3", "Open documents while application is Pending.", "Upload is locked."],
            ["4", "Sign out; sign in as admin; accept DS26-006.", "Application Accepted, notification created, processing row available."],
            ["5", "Sign in as DS26-006; upload allowed file under 5 MB.", "Document is listed as pending verification."],
            ["6", "Admin verifies the document.", "Document status changes and student is notified."],
            ["7", "Admin completes admission.", "Admission number is generated; completion is blocked unless all uploaded docs are verified."],
            ["8", "Open admin Search & Sort, Student Network, and Student Record Workflows.", "Student names appear only after an Accepted application; graph/search use accepted cohort."],
            ["9", "Use a demo teacher sign-in.", "Teacher dashboard opens. Academic functions need configured subjects/exams."],
            ["10", "Test invalid login, duplicate registration, invalid file, invalid marks if academic data exists.", "Validation feedback appears; invalid operation does not persist."],
            ["11", "Sign out and revisit protected page URL.", "Page redirects to login."],
        ], [10 * mm, 75 * mm, 85 * mm], s),
        p("Resetting the demo state", s["Subsection"]),
        p("There is no one-click reset script because deleting or resetting admission, document, attendance, or academic records can destroy useful work. Before the demo, record the application IDs/statuses and use admin actions to restore only the specific DS26 test records you changed. Never run broad DELETE statements against the database.", s["Body9"]),
        PageBreak(),
    ])

    # Test checklist/troubleshoot/files
    story.extend([
        p("10. Troubleshooting, safety, and project map", s["Section"]),
        table([
            ["Symptom", "Check"],
            ["Database unavailable", "Start MySQL; check DB_HOST/DB_PORT/DB_NAME and credentials in config/database.php; try phpMyAdmin connection."],
            ["Login rejected", "Use role-specific identifier: student roll number, admin username, or teacher email. Demo password is case-sensitive."],
            ["Pending person not in DSA/search/network", "Expected: student-based features require an Accepted application. Review status in Applications."],
            ["Documents are locked", "Application must be Accepted and verified; student needs an active application."],
            ["Cannot complete admission", "At least one document must exist and all documents for that application must be Verified."],
            ["Teacher marks/exam selector empty", "Current database has no subjects and no exam schedules. Configure academic setup data first."],
            ["Teacher cannot see student", "Check matching department and admission eligibility/status for that feature."],
            ["Photo/document rejected", "Check supported extension, actual file type, and 5 MB limit; check upload folder permissions."],
        ], [56 * mm, 114 * mm], s),
        p("Safety before testing", s["Subsection"]),
    ])
    add_checklist(story, [
        "Back up the database before testing deletes, status changes, or file removals.",
        "Use the DS26 cohort and harmless files, not real student personal documents.",
        "Do not share the demo password publicly; replace it for any non-local use.",
        "Only test actions using role accounts you are authorized to use.",
    ], s)
    story.extend([
        p("Where the main implementation lives", s["Subsection"]),
        table([
            ["Area", "Files"],
            ["Entry/auth", "index.php, login.php, logout.php, logout_teacher.php, config/database.php, config/schema.php"],
            ["Student journey", "student_dashboard.php, student_application.php, student_documents.php, admission_timeline.php, notification_center.php, student_profile.php, student_marks.php"],
            ["Admin workflow", "dashboard.php, admin_applications.php, document_verification.php, admission_processing.php, students.php, teachers.php, department.php"],
            ["Search/network/insight", "search_sort.php, student_network.php, reports.php, activity_log.php, data_structures_showcase.php"],
            ["Teacher portal", "teacher_dashboard.php, teacher_students.php, teacher_marks.php, teacher_attendance.php, teacher_examinations.php, teacher_reports.php, teacher_profile.php"],
            ["Photos / local demo seed", "admin_photo_management.php, photo_handler.php, config/photo_utils.php, seed_course_demo.php"],
        ], [48 * mm, 122 * mm], s),
        p("This guide explains the visible flows and the state checks that matter for testing. Use the source files and the live database as the final authority if UI wording or counts change.", s["Good"]),
    ])

    doc.build(story, onFirstPage=footer, onLaterPages=footer)
    print("Generated: " + OUTPUT)


if __name__ == "__main__":
    build()
