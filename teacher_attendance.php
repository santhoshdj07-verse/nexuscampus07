<?php
session_start();

if (!isset($_SESSION["teacher_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";

$teacherId = (int)($_SESSION["teacher_id"] ?? 0);
$teacherName = $_SESSION["teacher_name"] ?? "Teacher";
$teacherDept = $_SESSION["teacher_department"] ?? "";
$selectedDate = $_POST["class_date"] ?? date("Y-m-d");
$notice = "";
$noticeType = "";

$periods = array(
    array("label" => "Period 1", "start" => "09:00", "end" => "09:50"),
    array("label" => "Period 2", "start" => "10:10", "end" => "11:00"),
    array("label" => "Period 3", "start" => "11:20", "end" => "12:10")
);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_attendance"]))
{
    $sessionId = (int)($_POST["session_id"] ?? 0);
    $students = array();

    $studentQuery = $conn->prepare(
        "SELECT s.id, s.name, s.roll_no
         FROM students s
         JOIN admission_processing ap ON ap.student_id = s.id
         WHERE s.department = ?
         AND ap.admission_status = 'Approved'
         ORDER BY s.name
         LIMIT 5"
    );

    if ($studentQuery)
    {
        $studentQuery->bind_param("s", $teacherDept);
        $studentQuery->execute();
        $studentResult = $studentQuery->get_result();

        while ($studentRow = $studentResult->fetch_assoc())
        {
            $students[] = $studentRow;
        }

        $studentQuery->close();
    }

    if ($sessionId > 0 && !empty($students))
    {
        foreach ($students as $student)
        {
            $status = $_POST["attendance_" . $sessionId . "_" . $student["id"]] ?? "present";
            $status = in_array($status, array("present", "absent", "late"), true) ? $status : "present";

            $upsert = $conn->prepare(
                "INSERT INTO class_attendance (session_id, student_id, status)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), marked_at = CURRENT_TIMESTAMP"
            );

            if ($upsert)
            {
                $upsert->bind_param("iis", $sessionId, $student["id"], $status);
                $upsert->execute();
                $upsert->close();
            }
        }

        $notice = "Attendance saved successfully.";
        $noticeType = "success";
    }
    else
    {
        $notice = "No approved students are available for this class.";
        $noticeType = "error";
    }
}

foreach ($periods as $period)
{
    $periodLabel = $period["label"];
    $startTime = $period["start"];
    $endTime = $period["end"];

    $checkStmt = $conn->prepare(
        "SELECT id FROM class_sessions WHERE teacher_id = ? AND class_date = ? AND period_label = ? LIMIT 1"
    );

    if ($checkStmt)
    {
        $checkStmt->bind_param("iss", $teacherId, $selectedDate, $periodLabel);
        $checkStmt->execute();
        $exists = $checkStmt->get_result();

        if ($exists && $exists->num_rows === 0)
        {
            $insert = $conn->prepare(
                "INSERT INTO class_sessions (teacher_id, class_date, period_label, start_time, end_time, subject_name)
                 VALUES (?, ?, ?, ?, ?, 'General Studies')"
            );

            if ($insert)
            {
                $insert->bind_param("issss", $teacherId, $selectedDate, $periodLabel, $startTime, $endTime);
                $insert->execute();
                $insert->close();
            }
        }

        $checkStmt->close();
    }
}

$sessions = array();
$sessionStmt = $conn->prepare(
    "SELECT * FROM class_sessions WHERE teacher_id = ? AND class_date = ? ORDER BY start_time"
);

if ($sessionStmt)
{
    $sessionStmt->bind_param("is", $teacherId, $selectedDate);
    $sessionStmt->execute();
    $sessionResult = $sessionStmt->get_result();

    while ($row = $sessionResult->fetch_assoc())
    {
        $sessions[] = $row;
    }

    $sessionStmt->close();
}

$approvedStudents = array();
$studentStmt = $conn->prepare(
    "SELECT s.id, s.name, s.roll_no, s.photo_path
     FROM students s
     JOIN admission_processing ap ON ap.student_id = s.id
     WHERE s.department = ?
     AND ap.admission_status = 'Approved'
     ORDER BY s.name
     LIMIT 5"
);

if ($studentStmt)
{
    $studentStmt->bind_param("s", $teacherDept);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();

    while ($row = $studentResult->fetch_assoc())
    {
        $approvedStudents[] = $row;
    }

    $studentStmt->close();
}

$attendanceMap = array();
foreach ($sessions as $session)
{
    $sessionId = (int)$session["id"];
    $attendanceMap[$sessionId] = array();

    $attendanceStmt = $conn->prepare(
        "SELECT student_id, status FROM class_attendance WHERE session_id = ?"
    );

    if ($attendanceStmt)
    {
        $attendanceStmt->bind_param("i", $sessionId);
        $attendanceStmt->execute();
        $attendanceResult = $attendanceStmt->get_result();

        while ($row = $attendanceResult->fetch_assoc())
        {
            $attendanceMap[$sessionId][$row["student_id"]] = $row["status"];
        }

        $attendanceStmt->close();
    }
}

if (empty($sessions))
{
    $notice = "No timetable was created yet. The system added a default three-period schedule for today.";
    $noticeType = "success";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, Segoe UI, Arial, sans-serif;
            background: #070b17;
            color: #f8fafc;
        }
        .content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px 60px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        .title {
            font-size: 30px;
            font-weight: 700;
        }
        .subtitle { color: #94a3b8; font-size: 12px; letter-spacing: .08em; }
        .logout {
            display: inline-block;
            text-decoration: none;
            color: #fca5a5;
            background: rgba(239,68,68,.08);
            border: 1px solid rgba(239,68,68,.2);
            border-radius: 10px;
            padding: 10px 16px;
        }
        .panel {
            background: rgba(13,19,36,.9);
            border: 1px solid rgba(148,163,184,.18);
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 20px 60px rgba(15,23,42,.35);
        }
        .notice {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 13px;
        }
        .notice.success {
            background: rgba(34,197,94,.08);
            border: 1px solid rgba(34,197,94,.18);
            color: #bbf7d0;
        }
        .notice.error {
            background: rgba(239,68,68,.08);
            border: 1px solid rgba(239,68,68,.18);
            color: #fecaca;
        }
        .toolbar {
            display: flex;
            align-items: end;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .field { display: flex; flex-direction: column; gap: 8px; }
        label { color: #cbd5e1; font-size: 13px; font-weight: 600; }
        input, select {
            background: rgba(15,23,42,.85);
            border: 1px solid rgba(148,163,184,.18);
            border-radius: 10px;
            padding: 12px 14px;
            color: #f8fafc;
            min-width: 180px;
        }
        .btn {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #2563eb, #0891b2);
            color: white;
            font-size: 14px;
            font-weight: 700;
            padding: 12px 18px;
            cursor: pointer;
            min-height: 46px;
        }
        .row {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 12px 14px;
            background: rgba(255,255,255,.02);
            border: 1px solid rgba(148,163,184,.1);
            border-radius: 12px;
        }
        .student-meta strong { display: block; font-size: 14px; }
        .student-meta span { color: #94a3b8; font-size: 11px; }
        .choice-wrap {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }
        .choice {
            position: relative;
            min-height: 44px;
        }
        .choice input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .choice label {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            width: 100%;
            border: 1px solid rgba(148,163,184,.18);
            border-radius: 10px;
            background: rgba(15,23,42,.85);
            color: #e2e8f0;
            cursor: pointer;
            font-weight: 700;
            font-size: 12px;
        }
        .choice input:checked + label {
            background: linear-gradient(135deg, rgba(37,99,235,.3), rgba(8,145,178,.26));
            border-color: rgba(96,165,250,.45);
        }
        .session-card {
            margin-bottom: 28px;
        }
        .session-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .session-title {
            font-size: 20px;
            font-weight: 700;
        }
        .session-time {
            color: #94a3b8;
            font-size: 12px;
        }
        .empty {
            color: #94a3b8;
            padding: 12px;
            font-size: 13px;
            text-align: center;
        }
        @media (max-width: 900px) {
            .row { grid-template-columns: 1fr; }
            .choice-wrap { grid-template-columns: repeat(3, 1fr); }
            .topbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="topbar">
            <div>
                <div class="title">Attendance</div>
                <div class="subtitle">SMART CAMPUS • TEACHER PORTAL</div>
            </div>
            <a href="logout_teacher.php" class="logout">Logout</a>
        </div>

        <?php if ($notice !== "") { ?>
            <div class="notice <?php echo htmlspecialchars($noticeType); ?>"><?php echo htmlspecialchars($notice); ?></div>
        <?php } ?>

        <div class="panel">
            <form method="POST">
                <div class="toolbar">
                    <div class="field">
                        <label for="class_date">Select Date</label>
                        <input type="date" id="class_date" name="class_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                    </div>
                    <button type="submit" name="refresh" class="btn">Refresh Schedule</button>
                    <a href="teacher_dashboard.php" class="btn" style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">Back to Dashboard</a>
                </div>
            </form>
        </div>

        <?php if (!empty($sessions)): ?>
            <?php foreach ($sessions as $session): ?>
                <?php $sessionId = (int)$session["id"]; ?>
                <div class="panel session-card">
                    <div class="session-head">
                        <div class="session-title"><?php echo htmlspecialchars($session["period_label"]); ?></div>
                        <div class="session-time"><?php echo htmlspecialchars($session["start_time"]); ?> - <?php echo htmlspecialchars($session["end_time"]); ?> • <?php echo htmlspecialchars($session["subject_name"]); ?></div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
                        <input type="hidden" name="class_date" value="<?php echo htmlspecialchars($selectedDate); ?>">

                        <?php if (!empty($approvedStudents)): ?>
                            <?php foreach ($approvedStudents as $student): ?>
                                <?php $studentId = (int)$student["id"]; ?>
                                <?php $currentStatus = $attendanceMap[$sessionId][$studentId] ?? "present"; ?>
                                <div class="row">
                                    <div class="student-meta">
                                        <strong><?php echo htmlspecialchars($student["name"]); ?></strong>
                                        <span><?php echo htmlspecialchars($student["roll_no"]); ?></span>
                                    </div>
                                    <div>
                                        <div class="choice-wrap">
                                            <div class="choice">
                                                <input type="radio" id="present_<?php echo $sessionId; ?>_<?php echo $studentId; ?>" name="attendance_<?php echo $sessionId; ?>_<?php echo $studentId; ?>" value="present" <?php echo $currentStatus === "present" ? "checked" : ""; ?>>
                                                <label for="present_<?php echo $sessionId; ?>_<?php echo $studentId; ?>">Present</label>
                                            </div>
                                            <div class="choice">
                                                <input type="radio" id="late_<?php echo $sessionId; ?>_<?php echo $studentId; ?>" name="attendance_<?php echo $sessionId; ?>_<?php echo $studentId; ?>" value="late" <?php echo $currentStatus === "late" ? "checked" : ""; ?>>
                                                <label for="late_<?php echo $sessionId; ?>_<?php echo $studentId; ?>">Late</label>
                                            </div>
                                            <div class="choice">
                                                <input type="radio" id="absent_<?php echo $sessionId; ?>_<?php echo $studentId; ?>" name="attendance_<?php echo $sessionId; ?>_<?php echo $studentId; ?>" value="absent" <?php echo $currentStatus === "absent" ? "checked" : ""; ?>>
                                                <label for="absent_<?php echo $sessionId; ?>_<?php echo $studentId; ?>">Absent</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="session-time">Status</div>
                                        <div style="font-weight:700; color:#e2e8f0; text-transform:capitalize; margin-top:8px;">
                                            <?php echo htmlspecialchars($currentStatus); ?>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <button type="submit" name="save_attendance" value="1" class="btn">Save</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty">No approved students available in this department.</div>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="panel empty">No sessions were found for the selected date.</div>
        <?php endif; ?>
    </div>
</body>
</html>
