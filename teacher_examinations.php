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

/* Fetch upcoming examinations */
$exams = array();
$todayDate = date("Y-m-d");

$stmt = $conn->prepare(
    "SELECT es.id, es.exam_date, es.exam_type, es.max_marks,
            sub.subject_code, sub.subject_name, sub.semester
     FROM exam_schedule es
     JOIN subjects sub ON es.subject_id = sub.id
     WHERE sub.department = ?
     AND es.exam_date >= ?
     ORDER BY es.exam_date ASC
     LIMIT 30"
);

if ($stmt)
{
    $stmt->bind_param("ss", $teacherDept, $todayDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result)
    {
        while ($row = $result->fetch_assoc())
        {
            $exams[] = $row;
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
    <title>Examinations - Smart Campus</title>
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

        .exam-grid {
            display: grid;
            gap: 16px;
        }

        .exam-item {
            padding: 18px;
            border-radius: 12px;
            background: rgba(13,19,36,.88);
            border: 1px solid rgba(148,163,184,.14);
            border-left: 4px solid rgba(59,130,246,.4);
        }

        .exam-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .exam-subject {
            font-weight: 600;
            color: #e2e8f0;
            font-size: 14px;
        }

        .exam-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(59,130,246,.15);
            color: #93c5fd;
            font-size: 10px;
            font-weight: 600;
        }

        .exam-details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            font-size: 12px;
        }

        .detail-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-label {
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: #cbd5e1;
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #94a3b8;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        @media (max-width: 1024px) {
            .exam-details {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 800px) {
            .content {
                padding: 20px;
                margin-left: 0;
            }

            .exam-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php require_once "includes/teacher_sidebar.php"; ?>

<div class="content">

    <div class="title">Examination Schedule</div>
    <div class="subtitle">SMART CAMPUS MANAGEMENT SYSTEM</div>

    <div class="card">
        <p style="margin: 0; color: #94a3b8; font-size: 12px;">
            Showing <?php echo count($exams); ?> upcoming exam<?php echo count($exams) !== 1 ? 's' : ''; ?> for your department
        </p>
    </div>

    <?php if (!empty($exams)): ?>
        <div class="exam-grid">
            <?php foreach ($exams as $exam): ?>
                <div class="exam-item">
                    <div class="exam-item-header">
                        <div>
                            <div class="exam-subject">
                                <?php echo htmlspecialchars($exam["subject_code"]); ?> - <?php echo htmlspecialchars($exam["subject_name"]); ?>
                            </div>
                            <div class="detail-value" style="margin-top: 4px; font-size: 11px; color: #94a3b8;">
                                <?php echo htmlspecialchars($exam["semester"]); ?>
                            </div>
                        </div>
                        <span class="exam-type-badge"><?php echo htmlspecialchars($exam["exam_type"]); ?></span>
                    </div>

                    <div class="exam-details">
                        <div class="detail-row">
                            <span class="detail-label">Exam Date</span>
                            <span class="detail-value"><?php echo date("d M Y", strtotime($exam["exam_date"])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Days Remaining</span>
                            <span class="detail-value">
                                <?php 
                                    $daysRemaining = (strtotime($exam["exam_date"]) - time()) / (24 * 3600);
                                    echo $daysRemaining > 0 ? floor($daysRemaining) : "Today";
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Maximum Marks</span>
                            <span class="detail-value"><?php echo htmlspecialchars($exam["max_marks"]); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Time</span>
                            <span class="detail-value">TBD</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card empty-state">
            <div class="empty-state-icon">📅</div>
            <div>No upcoming examinations scheduled.</div>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
