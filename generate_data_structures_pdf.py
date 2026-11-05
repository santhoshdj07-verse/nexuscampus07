from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


OUTPUT_PATH = r"c:\xampp\htdocs\CollegeManagementWeb\DataStructures_Working.pdf"
NAVY = colors.HexColor("#1E3A8A")
INK = colors.HexColor("#172033")
BLUE = colors.HexColor("#DBEAFE")


def make_styles():
    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle(
        name="CoverTitle", parent=styles["Title"], fontName="Helvetica-Bold",
        fontSize=24, leading=30, textColor=NAVY, alignment=TA_CENTER, spaceAfter=10,
    ))
    styles.add(ParagraphStyle(
        name="SectionTitle", parent=styles["Heading1"], fontSize=17, leading=21,
        textColor=NAVY, spaceAfter=9,
    ))
    styles.add(ParagraphStyle(
        name="SubTitle", parent=styles["Heading2"], fontSize=11, leading=14,
        textColor=INK, spaceBefore=7, spaceAfter=4,
    ))
    styles.add(ParagraphStyle(
        name="BodySmall", parent=styles["BodyText"], fontSize=9, leading=13,
        textColor=INK, spaceAfter=6,
    ))
    styles.add(ParagraphStyle(
        name="Note", parent=styles["BodyText"], fontSize=8.5, leading=12,
        textColor=colors.HexColor("#334155"), backColor=BLUE,
        borderPadding=7, spaceBefore=5, spaceAfter=8,
    ))
    return styles


def paragraph(text, style):
    return Paragraph(text, style)


def add_footer(pdf, doc):
    pdf.saveState()
    pdf.setStrokeColor(colors.HexColor("#CBD5E1"))
    pdf.line(20 * mm, 15 * mm, 190 * mm, 15 * mm)
    pdf.setFont("Helvetica", 8)
    pdf.setFillColor(colors.HexColor("#64748B"))
    pdf.drawString(20 * mm, 10 * mm, "CollegeManagementWeb - Student Workflow Implementation")
    pdf.drawRightString(190 * mm, 10 * mm, "Page %d" % doc.page)
    pdf.restoreState()


def make_table(rows, widths):
    table = Table(rows, colWidths=widths, repeatRows=1, hAlign="LEFT")
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTNAME", (0, 1), (-1, -1), "Helvetica"),
        ("FONTSIZE", (0, 0), (-1, -1), 8),
        ("LEADING", (0, 0), (-1, -1), 11),
        ("BACKGROUND", (0, 1), (-1, -1), colors.white),
        ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#CBD5E1")),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    return table


def build_pdf(path=OUTPUT_PATH):
    styles = make_styles()
    doc = SimpleDocTemplate(
        path, pagesize=A4, rightMargin=20 * mm, leftMargin=20 * mm,
        topMargin=20 * mm, bottomMargin=21 * mm,
        title="CollegeManagementWeb Student Workflow Implementation",
        author="CollegeManagementWeb",
    )
    story = [
        Spacer(1, 24 * mm),
        paragraph("STUDENT RECORDS<br/>AND ACADEMIC PLANNING", styles["CoverTitle"]),
        paragraph("How the project uses its live data workflows", styles["Heading2"]),
        Spacer(1, 8 * mm),
        paragraph(
            "This guide explains why the admin Data Structures page exists and how it uses the "
            "CollegeManagementWeb database. Section headings describe the college task; implementation "
            "techniques are explained in the text rather than used as feature headings.",
            styles["BodySmall"],
        ),
        make_table([
            ["Project purpose", "Implementation in the current project"],
            ["Review and organize student records", "Accepted students load from student_applications and students; temporary list and tree views are built from that snapshot."],
            ["Phase 3: Find and organize students for admission decisions (CLO2)", "Partial and exact roll-number lookup plus sorting by roll number, name, or CGPA, with displayed comparison counts."],
            ["Phase 4: Plan teacher support for accepted students (CLO3)", "Read-only proposals use accepted students and active teachers for advising balance, tutoring-hour allocation, and valid adviser assignments."],
            ["Explore relationships between students", "Saved student_connections become a list and matrix view; staff can explore reachable students from a selected start."],
        ], [63 * mm, 107 * mm]),
        Spacer(1, 6 * mm),
        paragraph(
            "<b>Data safety:</b> Students are included in these views only when an application has status "
            "<code>Accepted</code>. Pending applicants appear only as application-number tokens in the "
            "review queue. Search, sorting, trees, and planning use in-memory snapshots and do not change "
            "student or application records. Planning results are not saved as assignments.",
            styles["Note"],
        ),
        PageBreak(),
        paragraph("How student review works in the project", styles["SectionTitle"]),
        paragraph(
            "The admin page requires an admin login and reads accepted student roll number, name, "
            "department, semester, and CGPA from the database. It separately reads active teachers, "
            "pending application numbers, and saved student connections. The list, stack, queue, and "
            "tree controls demonstrate possible review workflows without writing to the source records.",
            styles["BodySmall"],
        ),
        make_table([
            ["What staff need to do", "How the page supports it"],
            ["Adjust a temporary list of accepted students", "Insert, unlink, and traverse in-page student nodes; the student table is not deleted or changed."],
            ["Review the newest selected item first", "A linked-node stack follows last-in, first-out order."],
            ["Process pending applications in submission order", "A linked-node queue uses application-number tokens ordered by submission time; queue actions do not update admission status."],
            ["Inspect a structured view of the accepted cohort", "The binary tree fills child positions level-by-level; inorder, preorder, and postorder show different roll-number visit sequences."],
            ["Locate a student using the roll number", "A separate ordered tree compares roll numbers and displays the comparison path for an exact lookup."],
        ], [65 * mm, 105 * mm]),
        Spacer(1, 5 * mm),
        paragraph(
            "For student relationships, the existing Student Network page stores connections in "
            "<code>student_connections</code>. The Data Structures page reads those same saved links, "
            "shows them as a neighbor list and a matrix, and explores the connected component from a "
            "chosen accepted student using a FIFO traversal queue.",
            styles["BodySmall"],
        ),
        paragraph(
            "Open Data Structures from the admin sidebar. Use Student Network to create persistent "
            "connections; the visual and traversal examples do not modify those links.",
            styles["Note"],
        ),
        PageBreak(),
        paragraph(
            "Phase 3: Find and organize approved students for admission decisions (CLO2)",
            styles["SectionTitle"],
        ),
        paragraph(
            "<b>Why it is implemented:</b> Administrators need to locate an eligible student and arrange "
            "records for admission review without exposing applicants whose admission is still pending.",
            styles["BodySmall"],
        ),
        paragraph(
            "<b>How partial lookup works:</b> The PHP page selects students with an Accepted application "
            "and passes only those records as a read-only snapshot. The browser normalizes the entered "
            "query to lower case, checks each student's name and roll number in turn, and collects every "
            "partial match. It reports how many records it inspected.",
            styles["BodySmall"],
        ),
        make_table([
            ["What staff need to do", "Implementation in this project"],
            ["Find an exact roll number", "The browser sorts a copy by roll number, checks the middle record, then moves the low or high boundary to keep searching only the relevant half. The output reports a match or no match and the comparisons made."],
            ["Order records for review", "The selected key compares roll number/name as text or CGPA numerically. Insertion ordering shifts larger items in an already ordered prefix. Merge ordering recursively splits the copied list and merges sorted halves. Both update only the displayed rows and count comparisons."],
            ["Verify the feature in the app", "Enter a partial name or roll number; then enter a complete roll number. Choose roll number, name, or CGPA and run both ordering controls. Compare output, visible row order, and counts."],
        ], [52 * mm, 118 * mm]),
        Spacer(1, 5 * mm),
        paragraph(
            "The lab explains the search and ordering techniques as implementation details, not as the "
            "purpose of the feature. For the regular database search workflow, admins can also open "
            "<code>search_sort.php</code> from the page link.",
            styles["Note"],
        ),
        PageBreak(),
        paragraph(
            "Phase 4: Plan teacher support for accepted students (CLO3)",
            styles["SectionTitle"],
        ),
        paragraph(
            "<b>Why it is implemented:</b> Academic teams need a fair starting point for distributing "
            "advising, prioritizing limited tutoring time, and checking whether adviser assignments can "
            "satisfy workload and semester constraints.",
            styles["BodySmall"],
        ),
        paragraph(
            "<b>How advising is balanced:</b> The page pairs accepted students with active teachers. For "
            "each student it chooses the least-loaded teacher in the same department when possible; if "
            "none is available, it chooses the least-loaded active teacher overall. Ties are resolved by "
            "teacher name. It initializes an empty list for each teacher, updates that teacher's list "
            "after each choice, and prints the resulting proposal. This greedy proposal is displayed only "
            "and is not saved.",
            styles["BodySmall"],
        ),
        paragraph(
            "<b>How tutoring time is allocated:</b> The admin chooses a whole-number budget from 1 to 50 "
            "hours. A 0/1 knapsack calculation selects the combination with the largest estimated support "
            "need that fits the budget. Estimated hours are derived from CGPA as "
            "<code>max(1, min(10, 11 - floor(CGPA)))</code>; the need score is "
            "<code>max(1, round((10 - CGPA) * 10))</code>. A table indexed by the first i students and "
            "each budget stores the best achievable score. Each cell chooses between skipping the student "
            "and including them (only if their hours fit); tracing the table backward recovers the chosen "
            "students. Each student can be chosen at most once. These are demonstration heuristics, not "
            "stored support plans or official risk assessments.",
            styles["BodySmall"],
        ),
        paragraph(
            "<b>How adviser constraints are checked:</b> The planner tries active teachers in same-department "
            "preference order, tracks each teacher's current load and assigned semesters, and backs up to "
            "try another teacher when a choice conflicts. It caps each teacher at "
            "<code>ceil(accepted student count / active teacher count)</code> and does not assign two "
            "students from the same semester to one teacher. For each student it tentatively updates the "
            "load, semester set, and assignment; if a later student cannot be placed, it undoes those "
            "changes and tries another teacher. If no valid combination exists, it reports that no "
            "capacity-safe plan was found. These proposals are not persisted.",
            styles["BodySmall"],
        ),
        paragraph(
            "To demonstrate CLO3, run the advising plan, change the tutoring-hour budget and run that plan, "
            "then run the adviser assignment. Explain that the three results answer different planning "
            "needs and are previews only; official teacher assignments still require a separate "
            "database-backed workflow.",
            styles["Note"],
        ),
    ]
    doc.build(story, onFirstPage=add_footer, onLaterPages=add_footer)


if __name__ == "__main__":
    build_pdf()
    print("Updated project-specific PDF: " + OUTPUT_PATH)
