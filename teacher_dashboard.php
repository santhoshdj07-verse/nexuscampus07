<?php

session_start();

if (!isset($_SESSION["teacher_id"]))
{
    header("Location: teacher_login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";
require_once "config/photo_utils.php";

$teacherId = $_SESSION["teacher_id"];
$teacherName = $_SESSION["teacher_name"] ?? "Teacher";
$teacherDept = $_SESSION["teacher_department"] ?? "N/A";
$teacherCode = $_SESSION["teacher_code"] ?? "N/A";

/* Get complete teacher information */
$teacher = null;
$stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $teacher = $result->fetch_assoc();
    }
    $stmt->close();
}

/* =========================================================
   FETCH DASHBOARD STATISTICS
   ========================================================= */

$assignedStudents = 0;
$marksPending = 0;
$marksEntered = 0;
$upcomingExams = 0;

/* Count assigned students (admitted and in same department/course) */
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT s.id) AS total
     FROM students s
     JOIN student_applications sa ON s.id = sa.student_id
     JOIN admission_processing ap ON sa.student_id = ap.student_id
     WHERE sa.application_status = 'Accepted'
     AND ap.admission_status IN ('Approved', 'Completed')
     AND s.department = ?"
);

if ($stmt)
{
    $stmt->bind_param("s", $teacherDept);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $assignedStudents = (int)($row["total"] ?? 0);
    }
    $stmt->close();
}

/* Count marks pending entry */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM student_marks sm
     JOIN subjects sub ON sm.subject_id = sub.id
     WHERE sub.department = ?
     AND sm.publish_status IN ('NOT_ENTERED', 'DRAFT')
     AND YEAR(sm.created_at) = YEAR(NOW())"
);

if ($stmt)
{
    $stmt->bind_param("s", $teacherDept);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $marksPending = (int)($row["total"] ?? 0);
    }
    $stmt->close();
}

/* Count marks entered */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM student_marks sm
     JOIN subjects sub ON sm.subject_id = sub.id
     WHERE sub.department = ?
     AND sm.publish_status IN ('DRAFT', 'PUBLISHED')
     AND sm.midterm_marks IS NOT NULL
     AND YEAR(sm.created_at) = YEAR(NOW())"
);

if ($stmt)
{
    $stmt->bind_param("s", $teacherDept);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $marksEntered = (int)($row["total"] ?? 0);
    }
    $stmt->close();
}

/* Count upcoming exams */
$todayDate = date("Y-m-d");
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT es.id) AS total
     FROM exam_schedule es
     JOIN subjects sub ON es.subject_id = sub.id
     WHERE sub.department = ?
     AND es.exam_date >= ?
     AND es.status = 'SCHEDULED'"
);

if ($stmt)
{
    $stmt->bind_param("ss", $teacherDept, $todayDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $upcomingExams = (int)($row["total"] ?? 0);
    }
    $stmt->close();
}

/* Get recent activities */
$recentActivities = array();
$actStmt = $conn->prepare(
    "SELECT sm.id, sub.subject_name, s.name, sm.publish_status, sm.updated_at
     FROM student_marks sm
     JOIN subjects sub ON sm.subject_id = sub.id
     JOIN students s ON sm.student_id = s.id
     WHERE sub.department = ?
     ORDER BY sm.updated_at DESC
     LIMIT 8"
);

if ($actStmt)
{
    $actStmt->bind_param("s", $teacherDept);
    $actStmt->execute();
    $actResult = $actStmt->get_result();
    if ($actResult)
    {
        while ($actRow = $actResult->fetch_assoc())
        {
            $recentActivities[] = $actRow;
        }
    }
    $actStmt->close();
}

/* Get current semester from existing subjects */
$currentSemester = "";
$stmt = $conn->prepare(
    "SELECT DISTINCT semester FROM subjects WHERE department = ? LIMIT 1"
);
if ($stmt)
{
    $stmt->bind_param("s", $teacherDept);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $currentSemester = $row["semester"] ?? "N/A";
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Smart Campus</title>
    <style>
        * {
            box-sizing: border-box;
        }

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

        .welcome-card {
            padding: 28px;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(13,19,36,.95), rgba(25,28,45,.90));
            border: 1px solid rgba(59,130,246,.25);
            margin-bottom: 25px;
            box-shadow: 0 0 30px rgba(59,130,246,.08);
        }

        .welcome-card h1 {
            margin: 0 0 8px;
            font-size: 24px;
            background: linear-gradient(135deg, #60a5fa, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-card p {
            margin: 0;
            color: #cbd5e1;
            font-size: 13px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            padding: 22px;
            border-radius: 15px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
            text-align: center;
        }

        .stat-icon {
            font-size: 28px;
            margin-bottom: 12px;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .stat-value {
            color: #e2e8f0;
            font-size: 26px;
            font-weight: 700;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .action-card {
            padding: 24px;
            border-radius: 15px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
            text-decoration: none;
            color: #f8fafc;
            transition: 0.2s;
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateY(-4px);
            border-color: rgba(96,165,250,.3);
        }

        .action-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .action-title {
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 6px;
        }

        .action-desc {
            font-size: 12px;
            color: #94a3b8;
            margin: 0;
        }

        .activity-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .section-card {
            padding: 24px;
            border-radius: 15px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 16px;
            color: #e2e8f0;
        }

        .activity-item {
            padding: 12px;
            border-radius: 8px;
            background: rgba(255,255,255,.02);
            margin-bottom: 10px;
            border-left: 3px solid rgba(59,130,246,.4);
            font-size: 12px;
        }

        .activity-item-title {
            color: #e2e8f0;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .activity-item-detail {
            color: #94a3b8;
            font-size: 11px;
        }

        .empty-state {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
            font-size: 12px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .activity-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 800px) {
            .content {
                padding: 20px;
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-value {
                font-size: 22px;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

<?php require_once "includes/teacher_sidebar.php"; ?>

<div class="content">

    <div class="header">
        <div>
            <div class="title">Teacher Dashboard</div>
            <div class="subtitle">SMART CAMPUS MANAGEMENT SYSTEM</div>
        </div>
        <a href="logout_teacher.php" class="logout">Logout</a>
    </div>

    <!-- Welcome Section -->
    <div class="welcome-card">
        <div style="display: flex; gap: 20px; align-items: flex-start;">
            <!-- Teacher Photo -->
            <div style="flex-shrink: 0;">
                <div style="width: 120px; height: 120px; border-radius: 10px; background: rgba(255,255,255,.1); border: 2px solid rgba(148,163,184,.3); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    <?php $teacherPhotoPath = $teacher ? get_photo_file_path($teacher["photo_path"] ?? null) : null; ?>
                    <?php if ($teacher && !empty($teacher["photo_path"]) && $teacherPhotoPath && file_exists($teacherPhotoPath)): ?>
                        <img src="<?php echo htmlspecialchars(get_photo_public_url($teacher["photo_path"])); ?>?v=<?php echo filemtime($teacherPhotoPath); ?>" 
                             alt="Teacher Photo" 
                             style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <div style="text-align: center; color: #94a3b8;">
                            <div style="font-size: 40px; margin-bottom: 4px;">👨‍🏫</div>
                            <div style="font-size: 10px;">No Photo</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Teacher Info -->
            <div style="flex: 1;">
                <h1>Welcome, <?php echo htmlspecialchars($teacherName); ?> 👋</h1>
                <p style="margin-top: 8px;">Department: <strong><?php echo htmlspecialchars($teacherDept); ?></strong> | Teacher Code: <strong><?php echo htmlspecialchars($teacherCode); ?></strong></p>
                <?php if ($teacher && !empty($teacher["designation"])): ?>
                    <p style="margin: 6px 0 0 0; color: #cbd5e1; font-size: 13px;">Designation: <strong><?php echo htmlspecialchars($teacher["designation"]); ?></strong></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-label">Assigned Students</div>
            <div class="stat-value"><?php echo $assignedStudents; ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-label">Marks Pending</div>
            <div class="stat-value"><?php echo $marksPending; ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">✓</div>
            <div class="stat-label">Marks Entered</div>
            <div class="stat-value"><?php echo $marksEntered; ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-label">Upcoming Exams</div>
            <div class="stat-value"><?php echo $upcomingExams; ?></div>
        </div>

    </div>

    <!-- Quick Action Grid -->
    <div class="action-grid">

        <a href="teacher_students.php" class="action-card">
            <div class="action-icon">👥</div>
            <div class="action-title">My Students</div>
            <p class="action-desc">View and manage your class students</p>
        </a>

        <a href="teacher_marks.php" class="action-card">
            <div class="action-icon">📊</div>
            <div class="action-title">Enter Marks</div>
            <p class="action-desc">Record student examination marks</p>
        </a>

        <a href="teacher_attendance.php" class="action-card">
            <div class="action-icon">✅</div>
            <div class="action-title">Attendance</div>
            <p class="action-desc">Mark class attendance for your students</p>
        </a>

        <a href="teacher_examinations.php" class="action-card">
            <div class="action-icon">📅</div>
            <div class="action-title">Examinations</div>
            <p class="action-desc">View exam schedule and dates</p>
        </a>

    </div>

    <!-- Activity Section -->
    <div class="activity-section">

        <div class="section-card">
            <div class="section-title">Recent Mark Activities</div>
            <?php if (!empty($recentActivities)): ?>
                <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-item-title">
                            <?php echo htmlspecialchars($activity["subject_name"]); ?>
                        </div>
                        <div class="activity-item-detail">
                            Student: <?php echo htmlspecialchars($activity["name"]); ?>
                            • Status: <?php echo htmlspecialchars($activity["publish_status"]); ?>
                        </div>
                        <div class="activity-item-detail">
                            <?php echo date("d M Y • h:i A", strtotime($activity["updated_at"])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No recent activities yet.</div>
            <?php endif; ?>
        </div>

        <div class="section-card">
            <div class="section-title">Current Information</div>
            <div class="activity-item">
                <div class="activity-item-title">Department</div>
                <div class="activity-item-detail"><?php echo htmlspecialchars($teacherDept); ?></div>
            </div>
            <div class="activity-item">
                <div class="activity-item-title">Teacher Code</div>
                <div class="activity-item-detail"><?php echo htmlspecialchars($teacherCode); ?></div>
            </div>
            <div class="activity-item">
                <div class="activity-item-title">Current Semester</div>
                <div class="activity-item-detail"><?php echo htmlspecialchars($currentSemester !== "" ? $currentSemester : "N/A"); ?></div>
            </div>
            <div class="activity-item">
                <div class="activity-item-title">Academic Year</div>
                <div class="activity-item-detail">2026-2027</div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
