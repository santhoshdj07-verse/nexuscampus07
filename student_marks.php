<?php

session_start();

if (!isset($_SESSION["student_id"]))
{
    header("Location: login.php?role=student");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";

$studentId = $_SESSION["student_id"];

/* Fetch student info */
$student = null;
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
if ($stmt)
{
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $student = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$student)
{
    session_destroy();
    header("Location: login.php?role=student");
    exit();
}

/* Check if student is admitted */
$isAdmitted = cms_is_student_admitted($conn, $studentId);

if (!$isAdmitted)
{
    $lockedMessage = "Academic records will become available after your admission is successfully completed.";
}

/* Fetch student application for program type */
$application = null;
$stmt = $conn->prepare(
    "SELECT * FROM student_applications WHERE student_id = ? ORDER BY id DESC LIMIT 1"
);
if ($stmt)
{
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $application = $result->fetch_assoc();
    }
    $stmt->close();
}

/* Determine available semesters */
$programType = $application ? ($application["program_type"] ?? "UG") : "UG";
$availableSemesters = array();
if ($programType === "PG")
{
    $availableSemesters = array("Semester 1", "Semester 2", "Semester 3", "Semester 4");
}
else
{
    $availableSemesters = array("Semester 1", "Semester 2", "Semester 3", "Semester 4", "Semester 5", "Semester 6");
}

/* Fetch overall CGPA */
$overallCGPA = 0;
if ($isAdmitted)
{
    $overallCGPA = cms_calculate_overall_cgpa($conn, $studentId);
}

/* Fetch semester GPA values for all semesters */
$semesterGPAs = array();
if ($isAdmitted)
{
    foreach ($availableSemesters as $sem)
    {
        $gpa = cms_calculate_semester_gpa($conn, $studentId, $sem);
        $semesterGPAs[$sem] = $gpa;
    }
}

/* Fetch all marks for student (only published) */
$marksData = array();
if ($isAdmitted)
{
    $stmt = $conn->prepare(
        "SELECT sm.id, sm.semester, sm.subject_id, sub.subject_code, sub.subject_name,
                sub.credits, sm.midterm_marks, sm.end_sem_marks, sm.total_marks,
                sm.percentage, sm.grade, sm.grade_point, sm.pass_status, sm.publish_status
         FROM student_marks sm
         JOIN subjects sub ON sm.subject_id = sub.id
         WHERE sm.student_id = ? AND sm.publish_status = 'PUBLISHED'
         ORDER BY sm.semester ASC, sub.subject_code ASC"
    );
    
    if ($stmt)
    {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result)
        {
            while ($row = $result->fetch_assoc())
            {
                $sem = $row["semester"];
                if (!isset($marksData[$sem]))
                {
                    $marksData[$sem] = array();
                }
                $marksData[$sem][] = $row;
            }
        }
        
        $stmt->close();
    }
}

/* Calculate academic summary */
$totalSubjects = 0;
$completedSubjects = 0;
$passedSubjects = 0;
$failedSubjects = 0;
$avgPercentage = 0;

if ($isAdmitted && !empty($marksData))
{
    $percentages = array();
    foreach ($marksData as $semMarks)
    {
        foreach ($semMarks as $mark)
        {
            $totalSubjects++;
            $completedSubjects++;
            $percentages[] = $mark["percentage"];
            
            if ($mark["pass_status"] === "PASS")
            {
                $passedSubjects++;
            }
            else
            {
                $failedSubjects++;
            }
        }
    }
    
    if (!empty($percentages))
    {
        $avgPercentage = round(array_sum($percentages) / count($percentages), 2);
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Marks - Smart Campus</title>
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
            padding: 35px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .title {
            font-size: 27px;
            font-weight: 700;
        }

        .subtitle {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 5px;
        }

        .logout {
            text-decoration: none;
            color: #fca5a5;
            padding: 10px 15px;
            border-radius: 9px;
            border: 1px solid rgba(239,68,68,.15);
            background: rgba(239,68,68,.07);
            transition: 0.2s;
        }

        .logout:hover {
            background: rgba(239,68,68,.12);
        }

        .card {
            padding: 24px;
            border-radius: 15px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
            margin-bottom: 20px;
        }

        .locked-message {
            padding: 20px;
            border-radius: 12px;
            background: rgba(59,130,246,.08);
            border: 1px solid rgba(59,130,246,.16);
            color: #93c5fd;
            text-align: center;
            margin-bottom: 25px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-box {
            padding: 16px;
            border-radius: 10px;
            background: rgba(13,19,36,.88);
            border: 1px solid rgba(148,163,184,.14);
            text-align: center;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .stat-value {
            color: #e2e8f0;
            font-size: 20px;
            font-weight: 700;
        }

        .semester-section {
            margin-bottom: 20px;
        }

        .semester-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            background: rgba(59,130,246,.10);
            border-radius: 10px;
            margin-bottom: 12px;
            border: 1px solid rgba(59,130,246,.16);
        }

        .semester-title {
            font-weight: 600;
            color: #e2e8f0;
        }

        .semester-gpa {
            color: #93c5fd;
            font-size: 12px;
            font-weight: 600;
        }

        .marks-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(13,19,36,.88);
            border-radius: 10px;
            overflow: hidden;
        }

        .marks-table th {
            background: rgba(59,130,246,.10);
            color: #e2e8f0;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            border-bottom: 1px solid rgba(148,163,184,.14);
        }

        .marks-table td {
            padding: 12px;
            border-bottom: 1px solid rgba(148,163,184,.10);
            color: #cbd5e1;
            font-size: 12px;
        }

        .marks-table tr:last-child td {
            border-bottom: none;
        }

        .grade-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            background: rgba(34,197,94,.15);
            color: #86efac;
            font-weight: 600;
        }

        .pass-badge {
            color: #86efac;
        }

        .fail-badge {
            color: #fecaca;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .nav-back {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 15px;
            border-radius: 8px;
            background: rgba(148,163,184,.10);
            color: #cbd5e1;
            text-decoration: none;
            font-size: 12px;
            transition: 0.2s;
        }

        .nav-back:hover {
            background: rgba(148,163,184,.15);
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 800px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .marks-table {
                font-size: 11px;
            }

            .marks-table th,
            .marks-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <div>
            <div class="title">My Marks</div>
            <div class="subtitle">SMART CAMPUS MANAGEMENT SYSTEM</div>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="student_dashboard.php" class="logout" style="background: rgba(59,130,246,.07); color: #93c5fd; border-color: rgba(59,130,246,.15);">
                ← Dashboard
            </a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>

    <a href="student_dashboard.php" class="nav-back">← Back to Dashboard</a>

    <?php if (!$isAdmitted): ?>
        <div class="locked-message">
            🔒 <?php echo htmlspecialchars($lockedMessage); ?>
        </div>
    <?php else: ?>

        <!-- Academic Summary -->
        <div class="card">
            <h2 style="margin: 0 0 16px; font-size: 18px;">Academic Summary</h2>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Overall CGPA</div>
                    <div class="stat-value"><?php echo number_format($overallCGPA, 2); ?></div>
                </div>

                <div class="stat-box">
                    <div class="stat-label">Total Subjects</div>
                    <div class="stat-value"><?php echo $totalSubjects; ?></div>
                </div>

                <div class="stat-box">
                    <div class="stat-label">Passed</div>
                    <div class="stat-value"><?php echo $passedSubjects; ?></div>
                </div>

                <div class="stat-box">
                    <div class="stat-label">Failed</div>
                    <div class="stat-value"><?php echo $failedSubjects; ?></div>
                </div>

                <div class="stat-box">
                    <div class="stat-label">Avg %</div>
                    <div class="stat-value"><?php echo number_format($avgPercentage, 1); ?>%</div>
                </div>
            </div>
        </div>

        <!-- Student Info Card -->
        <div class="card">
            <p style="margin: 0; color: #cbd5e1; font-size: 12px;">
                <strong>Name:</strong> <?php echo htmlspecialchars($student["name"]); ?><br/>
                <strong>Roll No:</strong> <?php echo htmlspecialchars($student["roll_no"]); ?><br/>
                <strong>Program:</strong> <?php echo htmlspecialchars($programType); ?> |
                <strong>Course:</strong> <?php echo htmlspecialchars($application["course_name"] ?? "N/A"); ?>
            </p>
        </div>

        <!-- Semester-wise Marks -->
        <?php if (!empty($marksData)): ?>

            <?php foreach ($availableSemesters as $sem): ?>
                <?php 
                    $semMarks = $marksData[$sem] ?? array();
                    $semGPA = $semesterGPAs[$sem] ?? 0;
                ?>

                <div class="semester-section">
                    <div class="semester-header">
                        <div class="semester-title"><?php echo htmlspecialchars($sem); ?></div>
                        <div class="semester-gpa">GPA: <?php echo number_format($semGPA, 2); ?></div>
                    </div>

                    <?php if (!empty($semMarks)): ?>
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Midterm</th>
                                    <th>End Sem</th>
                                    <th>Total</th>
                                    <th>%</th>
                                    <th>Grade</th>
                                    <th>Points</th>
                                    <th>Credits</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($semMarks as $mark): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mark["subject_code"]); ?></td>
                                        <td><?php echo htmlspecialchars($mark["subject_name"]); ?></td>
                                        <td><?php echo ($mark["midterm_marks"] !== null ? $mark["midterm_marks"] : "-"); ?></td>
                                        <td><?php echo ($mark["end_sem_marks"] !== null ? $mark["end_sem_marks"] : "-"); ?></td>
                                        <td><?php echo ($mark["total_marks"] !== null ? $mark["total_marks"] : "-"); ?></td>
                                        <td><?php echo ($mark["percentage"] !== null ? number_format($mark["percentage"], 1) . "%" : "-"); ?></td>
                                        <td><span class="grade-badge"><?php echo htmlspecialchars($mark["grade"] ?? "-"); ?></span></td>
                                        <td><?php echo ($mark["grade_point"] !== null ? $mark["grade_point"] : "-"); ?></td>
                                        <td><?php echo htmlspecialchars($mark["credits"]); ?></td>
                                        <td>
                                            <span class="<?php echo ($mark["pass_status"] === "PASS") ? "pass-badge" : "fail-badge"; ?>">
                                                <?php echo htmlspecialchars($mark["pass_status"] ?? "PENDING"); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            <?php endforeach; ?>

        <?php else: ?>
            <div class="card empty-state">
                <div style="font-size: 40px; margin-bottom: 12px;">📚</div>
                <div>No published marks available yet.</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 8px;">Your marks will appear here once your teachers publish them.</div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>
