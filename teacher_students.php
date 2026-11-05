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

/* =========================================================
   FETCH ASSIGNED STUDENTS
   ========================================================= */

$students = array();

$stmt = $conn->prepare(
    "SELECT DISTINCT s.id, s.roll_no, s.name, s.department, sa.program_type, sa.course_name
     FROM students s
     JOIN student_applications sa ON s.id = sa.student_id
     JOIN admission_processing ap ON sa.student_id = ap.student_id
     WHERE sa.application_status = 'Accepted'
     AND ap.admission_status IN ('Approved', 'Completed')
     AND s.department = ?
     ORDER BY s.name ASC"
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
            $students[] = $row;
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
    <title>My Students - Smart Campus</title>
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

        .card {
            padding: 24px;
            border-radius: 15px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
            margin-bottom: 20px;
        }

        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 18px;
        }

        .student-card {
            padding: 20px;
            border-radius: 12px;
            background: rgba(13,19,36,.88);
            border: 1px solid rgba(148,163,184,.14);
            transition: 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .student-card:hover {
            transform: translateY(-4px);
            border-color: rgba(59,130,246,.3);
        }

        .student-name {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 8px;
            color: #e2e8f0;
        }

        .student-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .student-detail-label {
            color: #94a3b8;
        }

        .student-detail-value {
            color: #cbd5e1;
            font-weight: 500;
        }

        .student-actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(148,163,184,.10);
        }

        .btn {
            flex: 1;
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        @media (max-width: 800px) {
            .content {
                padding: 20px;
                margin-left: 0;
            }

            .students-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php require_once "includes/teacher_sidebar.php"; ?>

<div class="content">

    <div class="header">
        <div>
            <div class="title">My Students</div>
            <div class="subtitle">SMART CAMPUS MANAGEMENT SYSTEM</div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin: 0 0 8px; font-size: 18px;">Student List</h2>
        <p style="margin: 0; color: #94a3b8; font-size: 12px;">
            Showing <?php echo count($students); ?> admitted student<?php echo count($students) !== 1 ? 's' : ''; ?> in your department
        </p>
    </div>

    <?php if (!empty($students)): ?>
        <div class="students-grid">
            <?php foreach ($students as $student): ?>
                <div class="student-card">
                    <h3 class="student-name"><?php echo htmlspecialchars($student["name"]); ?></h3>
                    
                    <div class="student-detail">
                        <span class="student-detail-label">Roll No:</span>
                        <span class="student-detail-value"><?php echo htmlspecialchars($student["roll_no"]); ?></span>
                    </div>
                    
                    <div class="student-detail">
                        <span class="student-detail-label">Program:</span>
                        <span class="student-detail-value"><?php echo htmlspecialchars($student["program_type"]); ?></span>
                    </div>
                    
                    <div class="student-detail">
                        <span class="student-detail-label">Course:</span>
                        <span class="student-detail-value"><?php echo htmlspecialchars($student["course_name"]); ?></span>
                    </div>
                    
                    <div class="student-actions">
                        <a href="teacher_marks.php?student_id=<?php echo urlencode($student["id"]); ?>" class="btn btn-primary">
                            Enter Marks
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <div>No admitted students found in your department.</div>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
