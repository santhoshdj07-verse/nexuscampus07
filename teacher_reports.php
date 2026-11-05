<?php

session_start();

if (!isset($_SESSION["teacher_id"]))
{
    header("Location: teacher_login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";

$teacherDept = $_SESSION["teacher_department"] ?? "";

/* Get classroom statistics */
$totalStudents = 0;
$totalSubjects = 0;
$avgPercentage = 0;
$passPercentage = 0;

/* Count students */
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT s.id) AS total
     FROM students s
     WHERE s.department = ?"
);
if ($stmt)
{
    $stmt->bind_param("s", $teacherDept);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $totalStudents = (int)($row["total"] ?? 0);
    }
    $stmt->close();
}

/* Count subjects */
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT id) AS total FROM subjects WHERE department = ?"
);
if ($stmt)
{
    $stmt->bind_param("s", $teacherDept);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $totalSubjects = (int)($row["total"] ?? 0);
    }
    $stmt->close();
}

/* Average percentage */
$stmt = $conn->prepare(
    "SELECT AVG(sm.percentage) AS avg_percentage
     FROM student_marks sm
     JOIN subjects sub ON sm.subject_id = sub.id
     WHERE sub.department = ? AND sm.publish_status = 'PUBLISHED'"
);
if ($stmt)
{
    $stmt->bind_param("s", $teacherDept);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $avgPercentage = round($row["avg_percentage"] ?? 0, 2);
    }
    $stmt->close();
}

/* Pass percentage */
$stmt = $conn->prepare(
    "SELECT 
        COUNT(*) AS total_marks,
        SUM(CASE WHEN sm.pass_status = 'PASS' THEN 1 ELSE 0 END) AS passed
     FROM student_marks sm
     JOIN subjects sub ON sm.subject_id = sub.id
     WHERE sub.department = ? AND sm.publish_status = 'PUBLISHED'"
);
if ($stmt)
{
    $stmt->bind_param("s", $teacherDept);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $total = (int)($row["total_marks"] ?? 0);
        $passed = (int)($row["passed"] ?? 0);
        if ($total > 0)
        {
            $passPercentage = round(($passed / $total) * 100, 2);
        }
    }
    $stmt->close();
}

/* Semester-wise performance */
$semesterPerf = array();
$stmt = $conn->prepare(
    "SELECT sm.semester, AVG(sm.percentage) AS avg_pct, 
            COUNT(*) AS total, SUM(CASE WHEN sm.pass_status = 'PASS' THEN 1 ELSE 0 END) AS passed
     FROM student_marks sm
     JOIN subjects sub ON sm.subject_id = sub.id
     WHERE sub.department = ? AND sm.publish_status = 'PUBLISHED'
     GROUP BY sm.semester
     ORDER BY sm.semester ASC"
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
            $semesterPerf[] = $row;
        }
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Reports - Smart Campus</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            padding: 20px;
            border-radius: 12px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
            text-align: center;
        }

        .stat-icon {
            font-size: 24px;
            margin-bottom: 10px;
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
            font-size: 24px;
            font-weight: 700;
        }

        .report-card {
            padding: 24px;
            border-radius: 15px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
            margin-bottom: 20px;
        }

        .report-title {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 16px;
            color: #e2e8f0;
        }

        .semester-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid rgba(148,163,184,.10);
        }

        .semester-row:last-child {
            border-bottom: none;
        }

        .semester-name {
            color: #cbd5e1;
            font-weight: 600;
            min-width: 100px;
        }

        .semester-stats {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .semester-stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .semester-stat-label {
            color: #94a3b8;
            font-size: 10px;
            font-weight: 600;
        }

        .semester-stat-value {
            color: #e2e8f0;
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 800px) {
            .content {
                padding: 20px;
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .semester-stats {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

<?php require_once "includes/teacher_sidebar.php"; ?>

<div class="content">

    <div class="title">Academic Reports</div>
    <div class="subtitle">SMART CAMPUS MANAGEMENT SYSTEM</div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-label">Total Students</div>
            <div class="stat-value"><?php echo $totalStudents; ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-label">Total Subjects</div>
            <div class="stat-value"><?php echo $totalSubjects; ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-label">Avg Percentage</div>
            <div class="stat-value"><?php echo $avgPercentage; ?>%</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">✓</div>
            <div class="stat-label">Pass Percentage</div>
            <div class="stat-value"><?php echo $passPercentage; ?>%</div>
        </div>
    </div>

    <!-- Semester Performance -->
    <div class="report-card">
        <h3 class="report-title">Semester-wise Performance</h3>
        
        <?php if (!empty($semesterPerf)): ?>
            <?php foreach ($semesterPerf as $sem): ?>
                <div class="semester-row">
                    <div class="semester-name"><?php echo htmlspecialchars($sem["semester"]); ?></div>
                    <div class="semester-stats">
                        <div class="semester-stat-item">
                            <span class="semester-stat-label">Average %</span>
                            <span class="semester-stat-value"><?php echo round($sem["avg_pct"], 2); ?>%</span>
                        </div>
                        <div class="semester-stat-item">
                            <span class="semester-stat-label">Total Marks</span>
                            <span class="semester-stat-value"><?php echo $sem["total"]; ?></span>
                        </div>
                        <div class="semester-stat-item">
                            <span class="semester-stat-label">Passed</span>
                            <span class="semester-stat-value"><?php echo $sem["passed"]; ?></span>
                        </div>
                        <div class="semester-stat-item">
                            <span class="semester-stat-label">Pass %</span>
                            <span class="semester-stat-value"><?php echo round(($sem["passed"] / $sem["total"]) * 100, 1); ?>%</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 30px; color: #94a3b8;">
                No performance data available yet.
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
