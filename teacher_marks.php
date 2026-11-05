<?php

session_start();

if (!isset($_SESSION["teacher_id"]))
{
    header("Location: teacher_login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";

$teacherId = $_SESSION["teacher_id"];
$teacherDept = $_SESSION["teacher_department"] ?? "";

$message = "";
$messageType = "";
$selectedStudentId = isset($_GET["student_id"]) ? (int)$_GET["student_id"] : 0;
$selectedSemester = isset($_GET["semester"]) ? trim($_GET["semester"]) : "";

/* Verify student belongs to teacher's department */
$authorizedStudent = false;
if ($selectedStudentId > 0)
{
    $stmt = $conn->prepare(
        "SELECT s.id FROM students s
         WHERE s.id = ? AND s.department = ?"
    );
    if ($stmt)
    {
        $stmt->bind_param("is", $selectedStudentId, $teacherDept);
        $stmt->execute();
        $result = $stmt->get_result();
        $authorizedStudent = ($result && $result->num_rows > 0);
        $stmt->close();
    }
}

/* Handle mark submission */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_marks"]))
{
    $studentId = isset($_POST["student_id"]) ? (int)$_POST["student_id"] : 0;
    $subjectId = isset($_POST["subject_id"]) ? (int)$_POST["subject_id"] : 0;
    $semester = trim($_POST["semester"] ?? "");
    $midtermMarks = isset($_POST["midterm"]) ? (int)$_POST["midterm"] : null;
    $endSemMarks = isset($_POST["end_sem"]) ? (int)$_POST["end_sem"] : null;
    $action = $_POST["submit_marks"] ?? "save";

    /* Validate inputs */
    if ($studentId <= 0 || $subjectId <= 0 || $semester === "")
    {
        $message = "Invalid request parameters.";
        $messageType = "error";
    }
    else
    {
        /* Verify authorization */
        $authCheck = $conn->prepare(
            "SELECT s.id FROM students s WHERE s.id = ? AND s.department = ?"
        );
        if ($authCheck)
        {
            $authCheck->bind_param("is", $studentId, $teacherDept);
            $authCheck->execute();
            $authResult = $authCheck->get_result();
            
            if ($authResult && $authResult->num_rows === 0)
            {
                $message = "Unauthorized: You cannot access this student's records.";
                $messageType = "error";
            }
            else
            {
                /* Get exam schedule and max marks */
                $examCheck = $conn->prepare(
                    "SELECT es.max_marks FROM exam_schedule es 
                     WHERE es.subject_id = ? AND es.semester = ?
                     LIMIT 1"
                );
                
                $maxMarks = 100;
                if ($examCheck)
                {
                    $examCheck->bind_param("is", $subjectId, $semester);
                    $examCheck->execute();
                    $examResult = $examCheck->get_result();
                    if ($examResult && $examResult->num_rows > 0)
                    {
                        $examRow = $examResult->fetch_assoc();
                        $maxMarks = (int)($examRow["max_marks"] ?? 100);
                    }
                    $examCheck->close();
                }

                /* Assume 30/70 split for midterm/end semester */
                $midtermMax = 30;
                $endSemMax = 70;
                if ($maxMarks === 100)
                {
                    $midtermMax = 30;
                    $endSemMax = 70;
                }
                else if ($maxMarks === 50)
                {
                    $midtermMax = 15;
                    $endSemMax = 35;
                }

                /* Validate marks */
                $validationError = "";
                if ($midtermMarks !== null && ($midtermMarks < 0 || $midtermMarks > $midtermMax))
                {
                    $validationError = "Midterm marks must be between 0 and " . $midtermMax;
                }
                elseif ($endSemMarks !== null && ($endSemMarks < 0 || $endSemMarks > $endSemMax))
                {
                    $validationError = "End semester marks must be between 0 and " . $endSemMax;
                }

                if ($validationError !== "")
                {
                    $message = $validationError;
                    $messageType = "error";
                }
                else
                {
                    /* Get or create mark record */
                    $academicYear = "2026-2027";
                    $existingMark = $conn->prepare(
                        "SELECT id, midterm_marks, end_sem_marks FROM student_marks
                         WHERE student_id = ? AND subject_id = ? AND semester = ? AND academic_year = ?"
                    );
                    
                    $markId = 0;
                    $oldMidterm = null;
                    $oldEndSem = null;
                    
                    if ($existingMark)
                    {
                        $existingMark->bind_param("iiss", $studentId, $subjectId, $semester, $academicYear);
                        $existingMark->execute();
                        $markResult = $existingMark->get_result();
                        
                        if ($markResult && $markResult->num_rows > 0)
                        {
                            $markRow = $markResult->fetch_assoc();
                            $markId = (int)$markRow["id"];
                            $oldMidterm = $markRow["midterm_marks"];
                            $oldEndSem = $markRow["end_sem_marks"];
                        }
                        
                        $existingMark->close();
                    }

                    if ($markId === 0)
                    {
                        /* Create new record */
                        $insertStmt = $conn->prepare(
                            "INSERT INTO student_marks 
                             (student_id, subject_id, semester, academic_year, publish_status)
                             VALUES (?, ?, ?, ?, 'NOT_ENTERED')"
                        );
                        
                        if ($insertStmt)
                        {
                            $status = "NOT_ENTERED";
                            $insertStmt->bind_param("iiss", $studentId, $subjectId, $semester, $academicYear);
                            if ($insertStmt->execute())
                            {
                                $markId = $conn->insert_id;
                            }
                            $insertStmt->close();
                        }
                    }

                    if ($markId > 0)
                    {
                        /* Calculate totals and grade */
                        $totalMarks = 0;
                        $percentage = 0;
                        $newStatus = "DRAFT";
                        
                        if ($midtermMarks !== null || $endSemMarks !== null)
                        {
                            $mid = $midtermMarks ?? 0;
                            $end = $endSemMarks ?? 0;
                            $totalMarks = $mid + $end;
                            $percentage = ($totalMarks / $maxMarks) * 100;
                            $newStatus = "DRAFT";
                        }

                        /* Get grade and grade point */
                        $gradeInfo = cms_get_grade_for_percentage($conn, $percentage);
                        $grade = $gradeInfo ? $gradeInfo["grade"] : null;
                        $gradePoint = $gradeInfo ? (float)$gradeInfo["grade_point"] : null;
                        $passStatus = $gradeInfo ? $gradeInfo["pass_fail"] : null;

                        /* Update mark record */
                        $updateStmt = $conn->prepare(
                            "UPDATE student_marks
                             SET midterm_marks = ?, end_sem_marks = ?, total_marks = ?,
                                 percentage = ?, grade = ?, grade_point = ?,
                                 pass_status = ?, publish_status = ?, teacher_id = ?, entered_at = NOW(),
                                 updated_at = NOW()
                             WHERE id = ?"
                        );
                        
                        if ($updateStmt)
                        {
                            $updateStmt->bind_param(
                                "iiiiddsssii",
                                $midtermMarks,
                                $endSemMarks,
                                $totalMarks,
                                $percentage,
                                $grade,
                                $gradePoint,
                                $passStatus,
                                $newStatus,
                                $teacherId,
                                $markId
                            );
                            
                            if ($updateStmt->execute())
                            {
                                /* Log mark change if it's an update */
                                if ($oldMidterm !== null || $oldEndSem !== null)
                                {
                                    $historyStmt = $conn->prepare(
                                        "INSERT INTO mark_change_history
                                         (mark_record_id, student_id, subject_id, teacher_id, old_midterm, new_midterm, old_end_sem, new_end_sem, changed_at)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                                    );
                                    
                                    if ($historyStmt)
                                    {
                                        $historyStmt->bind_param(
                                            "iiiiiiiii",
                                            $markId,
                                            $studentId,
                                            $subjectId,
                                            $teacherId,
                                            $oldMidterm,
                                            $midtermMarks,
                                            $oldEndSem,
                                            $endSemMarks
                                        );
                                        $historyStmt->execute();
                                        $historyStmt->close();
                                    }
                                }

                                if ($action === "publish")
                                {
                                    $pubStmt = $conn->prepare(
                                        "UPDATE student_marks SET publish_status = 'PUBLISHED', published_at = NOW() WHERE id = ?"
                                    );
                                    if ($pubStmt)
                                    {
                                        $pubStmt->bind_param("i", $markId);
                                        $pubStmt->execute();
                                        $pubStmt->close();
                                    }

                                    /* Create notification */
                                    cms_create_notification(
                                        $conn,
                                        $studentId,
                                        "Academic Result Published",
                                        "Your marks for this semester have been published. Please check your student portal.",
                                        "marks_published"
                                    );

                                    $message = "Marks saved and published successfully!";
                                    $messageType = "success";
                                }
                                else
                                {
                                    $message = "Marks saved successfully! You can publish them later.";
                                    $messageType = "success";
                                }
                            }
                            else
                            {
                                $message = "Error saving marks. Please try again.";
                                $messageType = "error";
                            }
                            
                            $updateStmt->close();
                        }
                    }
                }
            }
            
            $authCheck->close();
        }
    }
}

/* Fetch student data if selected */
$selectedStudent = null;
if ($selectedStudentId > 0 && $authorizedStudent)
{
    $stmt = $conn->prepare(
        "SELECT s.id, s.roll_no, s.name, sa.program_type, sa.course_name
         FROM students s
         JOIN student_applications sa ON s.id = sa.student_id
         WHERE s.id = ?"
    );
    
    if ($stmt)
    {
        $stmt->bind_param("i", $selectedStudentId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0)
        {
            $selectedStudent = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

/* Fetch subjects for selected student */
$subjects = array();
if ($selectedStudent && $selectedSemester !== "")
{
    $programType = $selectedStudent["program_type"] ?? "UG";
    $courseName = $selectedStudent["course_name"] ?? "";
    
    $stmt = $conn->prepare(
        "SELECT id, subject_code, subject_name, credits
         FROM subjects
         WHERE program_type = ? AND course_name = ? AND semester = ? AND status = 'ACTIVE'
         ORDER BY subject_code ASC"
    );
    
    if ($stmt)
    {
        $stmt->bind_param("sss", $programType, $courseName, $selectedSemester);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result)
        {
            while ($row = $result->fetch_assoc())
            {
                $subjects[] = $row;
            }
        }
        
        $stmt->close();
    }
}

/* Fetch student marks if available */
$studentMarks = array();
if ($selectedStudent && $selectedSemester !== "")
{
    $programType = $selectedStudent["program_type"] ?? "UG";
    $courseName = $selectedStudent["course_name"] ?? "";
    $academicYear = "2026-2027";
    
    $stmt = $conn->prepare(
        "SELECT sm.id, sm.subject_id, sub.subject_code, sub.subject_name, sub.credits,
                sm.midterm_marks, sm.end_sem_marks, sm.total_marks, sm.percentage,
                sm.grade, sm.publish_status
         FROM student_marks sm
         JOIN subjects sub ON sm.subject_id = sub.id
         WHERE sm.student_id = ? AND sm.semester = ? AND sm.academic_year = ?
         ORDER BY sub.subject_code ASC"
    );
    
    if ($stmt)
    {
        $stmt->bind_param("iss", $selectedStudentId, $selectedSemester, $academicYear);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result)
        {
            while ($row = $result->fetch_assoc())
            {
                $studentMarks[$row["subject_id"]] = $row;
            }
        }
        
        $stmt->close();
    }
}

/* Determine available semesters based on program type */
$availableSemesters = array();
if ($selectedStudent)
{
    $programType = $selectedStudent["program_type"] ?? "UG";
    if ($programType === "PG")
    {
        $availableSemesters = array("Semester 1", "Semester 2", "Semester 3", "Semester 4");
    }
    else
    {
        $availableSemesters = array("Semester 1", "Semester 2", "Semester 3", "Semester 4", "Semester 5", "Semester 6");
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Marks - Smart Campus</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, Segoe UI, Arial, sans-serif;
            color: #f8fafc;
            background: radial-gradient(circle at 10% 10%, rgba(59,130,246,.13), transparent 30%),
                        radial-gradient(circle at 90% 15%, rgba(139,92,246,.12), transparent 30%),
                        #070b17;
            min-height: 100vh;
        }

        .content {
            padding: 35px;
            margin-left: 255px;
        }

        .title {
            font-size: 27px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 25px;
        }

        .card {
            padding: 24px;
            border-radius: 15px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 600;
        }

        input, select {
            width: 100%;
            border: 1px solid rgba(148,163,184,.22);
            border-radius: 10px;
            background: rgba(15,23,42,.85);
            padding: 12px 14px;
            font-size: 13px;
            color: #e2e8f0;
            transition: 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: rgba(59,130,246,.4);
            background: rgba(15,23,42,.95);
        }

        .message {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 13px;
            border: 1px solid;
        }

        .error {
            background: rgba(239,68,68,.08);
            color: #fecaca;
            border-color: rgba(239,68,68,.16);
        }

        .success {
            background: rgba(34,197,94,.08);
            color: #bbf7d0;
            border-color: rgba(34,197,94,.16);
        }

        .marks-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .marks-table th {
            background: rgba(59,130,246,.10);
            color: #e2e8f0;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            border: 1px solid rgba(148,163,184,.14);
        }

        .marks-table td {
            padding: 12px;
            border: 1px solid rgba(148,163,184,.14);
            color: #cbd5e1;
            font-size: 12px;
        }

        .marks-table input {
            padding: 8px 10px;
            font-size: 12px;
            margin-bottom: 0;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(148,163,184,.10);
            color: #cbd5e1;
            flex: 1;
        }

        .btn-secondary:hover {
            background: rgba(148,163,184,.15);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        @media (max-width: 800px) {
            .content {
                padding: 20px;
                margin-left: 0;
            }

            .marks-table {
                overflow-x: auto;
                display: block;
            }
        }
    </style>
</head>
<body>

<?php require_once "includes/teacher_sidebar.php"; ?>

<div class="content">

    <div class="title">Enter Marks</div>
    <div class="subtitle">SMART CAMPUS MANAGEMENT SYSTEM</div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo ($messageType === "error") ? "error" : "success"; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Student Selection -->
    <form method="GET" class="card">
        <div class="form-group">
            <label>Select Student</label>
            <select name="student_id" onchange="this.form.submit()">
                <option value="">-- Choose a student --</option>
                <?php
                    $stmt = $conn->prepare(
                        "SELECT s.id, s.roll_no, s.name FROM students s
                         WHERE s.department = ? ORDER BY s.name ASC"
                    );
                    if ($stmt)
                    {
                        $stmt->bind_param("s", $teacherDept);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result)
                        {
                            while ($row = $result->fetch_assoc())
                            {
                                $selected = ($row["id"] == $selectedStudentId) ? "selected" : "";
                                echo "<option value='" . $row["id"] . "' $selected>" . htmlspecialchars($row["roll_no"] . " - " . $row["name"]) . "</option>";
                            }
                        }
                        $stmt->close();
                    }
                ?>
            </select>
        </div>
    </form>

    <?php if ($selectedStudent): ?>

        <div class="card">
            <h3 style="margin: 0 0 12px; font-size: 16px;">Student Information</h3>
            <p style="margin: 4px 0; color: #cbd5e1; font-size: 12px;">
                <strong>Name:</strong> <?php echo htmlspecialchars($selectedStudent["name"]); ?><br/>
                <strong>Roll No:</strong> <?php echo htmlspecialchars($selectedStudent["roll_no"]); ?><br/>
                <strong>Program:</strong> <?php echo htmlspecialchars($selectedStudent["program_type"]); ?> |
                <strong>Course:</strong> <?php echo htmlspecialchars($selectedStudent["course_name"]); ?>
            </p>
        </div>

        <!-- Semester Selection -->
        <form method="GET" class="card">
            <input type="hidden" name="student_id" value="<?php echo $selectedStudentId; ?>">
            <div class="form-group">
                <label>Select Semester</label>
                <select name="semester" onchange="this.form.submit()">
                    <option value="">-- Choose a semester --</option>
                    <?php foreach ($availableSemesters as $sem): ?>
                        <option value="<?php echo htmlspecialchars($sem); ?>" <?php echo ($sem === $selectedSemester) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($sem); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($selectedSemester !== "" && !empty($subjects)): ?>

            <!-- Marks Entry Form -->
            <div class="card">
                <h3 style="margin: 0 0 16px; font-size: 16px;">Mark Entry - <?php echo htmlspecialchars($selectedSemester); ?></h3>
                
                <form method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $selectedStudentId; ?>">
                    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selectedSemester); ?>">

                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Midterm (30)</th>
                                <th>End Sem (70)</th>
                                <th>Total</th>
                                <th>Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                                <?php 
                                    $mark = $studentMarks[$subject["id"]] ?? null;
                                    $totalMarks = $mark ? ($mark["total_marks"] ?? "") : "";
                                    $grade = $mark ? ($mark["grade"] ?? "") : "";
                                    $status = $mark ? ($mark["publish_status"] ?? "NOT_ENTERED") : "NOT_ENTERED";
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject["subject_code"]); ?></td>
                                    <td><?php echo htmlspecialchars($subject["subject_name"]); ?></td>
                                    <td>
                                        <input type="number" name="marks[<?php echo $subject["id"]; ?>][midterm]"
                                               value="<?php echo ($mark ? ($mark["midterm_marks"] ?? "") : ""); ?>"
                                               min="0" max="30" placeholder="0-30">
                                    </td>
                                    <td>
                                        <input type="number" name="marks[<?php echo $subject["id"]; ?>][end_sem]"
                                               value="<?php echo ($mark ? ($mark["end_sem_marks"] ?? "") : ""); ?>"
                                               min="0" max="70" placeholder="0-70">
                                    </td>
                                    <td><?php echo ($totalMarks !== "" ? $totalMarks : "-"); ?></td>
                                    <td><?php echo ($grade !== "" ? $grade : "-"); ?></td>
                                    <td><small><?php echo htmlspecialchars($status); ?></small></td>
                                </tr>
                                
                                <!-- Hidden fields for individual subject submission -->
                                <input type="hidden" name="subject_id" value="<?php echo $subject["id"]; ?>">
                                
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="button-group">
                        <button type="submit" name="submit_marks" value="save" class="btn btn-primary">
                            SAVE MARKS
                        </button>
                        <button type="submit" name="submit_marks" value="publish" class="btn btn-primary" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
                            SAVE & PUBLISH
                        </button>
                    </div>
                </form>
            </div>

        <?php elseif ($selectedSemester !== ""): ?>
            <div class="card empty-state">
                <div style="font-size: 32px; margin-bottom: 12px;">📚</div>
                <div>No subjects found for this semester and student combination.</div>
            </div>
        <?php endif; ?>

    <?php elseif ($selectedStudentId > 0 && !$authorizedStudent): ?>
        <div class="card empty-state">
            <div style="font-size: 32px; margin-bottom: 12px;">🔒</div>
            <div>You are not authorized to access this student's records.</div>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
