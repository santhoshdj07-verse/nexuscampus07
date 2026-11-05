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

/* =========================================================
   GET STUDENT AND APPLICATION INFO
   ========================================================= */

$student = null;
$application = null;
$documents = array();
$admission = null;

$stmt = $conn->prepare(
    "SELECT id, roll_no, name FROM students WHERE id = ? LIMIT 1"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->num_rows > 0 ? $result->fetch_assoc() : null;
$stmt->close();

if (!$student)
{
    session_destroy();
    header("Location: login.php?role=student");
    exit();
}

$stmt = $conn->prepare(
    "SELECT * FROM student_applications 
     WHERE student_id = ? ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->num_rows > 0 ? $result->fetch_assoc() : null;
$stmt->close();

if (!$application)
{
    $application = array(
        "id" => 0,
        "application_number" => "Not Applied",
        "application_status" => "Not Started",
        "created_at" => null,
        "verified_at" => null,
        "program_type" => "UG"
    );
}

$appId = (int)$application["id"];

/* Get documents for this application */
if ($appId > 0)
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as total, 
                SUM(CASE WHEN verification_status = 'Verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN verification_status = 'Pending' THEN 1 ELSE 0 END) as pending
         FROM student_documents WHERE application_id = ?"
    );
    $stmt->bind_param("i", $appId);
    $stmt->execute();
    $result = $stmt->get_result();
    $docStats = $result->fetch_assoc();
    $stmt->close();
}
else
{
    $docStats = array("total" => 0, "verified" => 0, "pending" => 0);
}

/* Get admission processing status */
if ($appId > 0)
{
    $stmt = $conn->prepare(
        "SELECT * FROM admission_processing 
         WHERE student_id = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admission = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
}

/* =========================================================
   CALCULATE TIMELINE STAGES
   ========================================================= */

$appStatus = $application["application_status"] ?? "";
$verifiedAt = $application["verified_at"] ?? null;
$docCount = (int)($docStats["total"] ?? 0);
$docVerified = (int)($docStats["verified"] ?? 0);
$docPending = (int)($docStats["pending"] ?? 0);
$admissionStatus = $admission["admission_status"] ?? "";

/* Stage statuses: "completed", "active", "pending" */
$stages = array(
    array(
        "title" => "Application Submitted",
        "icon" => "📋",
        "status" => $appStatus !== "Not Started" ? "completed" : "pending",
        "date" => $application["created_at"] ?? null,
        "details" => "Application Number: " . ($application["application_number"] ?? "Not Applied")
    ),
    array(
        "title" => "Admin Review",
        "icon" => "👤",
        "status" => in_array($appStatus, array("Accepted", "Rejected")) ? "completed" : 
                   ($appStatus === "Pending" ? "active" : "pending"),
        "date" => $verifiedAt,
        "details" => $appStatus === "Accepted" ? "✓ Accepted" : 
                    ($appStatus === "Rejected" ? "✗ Rejected" : "Awaiting review...")
    ),
    array(
        "title" => "Documents Submitted",
        "icon" => "📄",
        "status" => ($docCount > 0) ? "completed" : 
                   ($appStatus === "Accepted" && $verifiedAt ? "active" : "pending"),
        "date" => null,
        "details" => $docCount > 0 ? $docVerified . " / " . $docCount . " Verified" : "Not started"
    ),
    array(
        "title" => "Document Verification",
        "icon" => "✓",
        "status" => ($docVerified > 0) ? "completed" : 
                   ($docCount > 0 ? "active" : "pending"),
        "date" => null,
        "details" => $docVerified > 0 ? 
                    ($docVerified === $docCount ? "✓ Complete" : $docVerified . " / " . $docCount . " verified") 
                    : "Not started"
    ),
    array(
        "title" => "Admission Processing",
        "icon" => "⚙️",
        "status" => ($admissionStatus === "Completed") ? "completed" : 
                   ($admissionStatus === "In Progress" ? "active" : "pending"),
        "date" => null,
        "details" => $admissionStatus !== "" ? ucfirst($admissionStatus) : "Awaiting eligibility..."
    ),
    array(
        "title" => "Admission Completed",
        "icon" => "🎓",
        "status" => ($admissionStatus === "Completed") ? "completed" : "pending",
        "date" => null,
        "details" => $admissionStatus === "Completed" ? "✓ Admission Finalized" : "Pending completion"
    )
);

?>

<!DOCTYPE html>
<html>
<head>
<title>Admission Timeline - Smart Campus</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>

* {
    box-sizing: border-box;
}

:root {
    --bg: #070b17;
    --panel: rgba(13,19,36,.90);
    --border: rgba(148,163,184,.14);
    --text: #f8fafc;
    --muted: #94a3b8;
    --blue: #3b82f6;
    --cyan: #06b6d4;
    --purple: #8b5cf6;
    --green: #22c55e;
    --red: #ef4444;
    --yellow: #f59e0b;
}

body {
    margin: 0;
    font-family: Inter, Segoe UI, Arial, sans-serif;
    background: radial-gradient(circle at 10% 10%, rgba(59,130,246,.13), transparent 30%),
                radial-gradient(circle at 90% 15%, rgba(139,92,246,.12), transparent 30%),
                radial-gradient(circle at 70% 90%, rgba(6,182,212,.08), transparent 30%),
                #070b17;
    color: var(--text);
    min-height: 100vh;
    padding: 30px 20px;
}

.container {
    max-width: 800px;
    margin: 0 auto;
}

.header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.header h1 {
    margin: 0;
    font-size: 28px;
    background: linear-gradient(135deg, #3b82f6, #06b6d4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.header p {
    color: var(--muted);
    font-size: 13px;
    margin: 8px 0 0 0;
}

.student-info {
    text-align: center;
    margin-bottom: 30px;
    font-size: 13px;
    color: var(--muted);
}

.timeline {
    position: relative;
    padding: 0;
}

/* Vertical line connector */
.timeline::before {
    content: "";
    position: absolute;
    left: 30px;
    top: 60px;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, 
        rgba(59,130,246,.4) 0%,
        rgba(6,182,212,.4) 50%,
        rgba(139,92,246,.4) 100%);
}

.timeline-item {
    margin-bottom: 30px;
    position: relative;
    padding-left: 80px;
}

/* Circle node */
.timeline-item::before {
    content: "";
    position: absolute;
    left: 15px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 3px solid var(--border);
    background: var(--panel);
    transition: all .3s ease;
    z-index: 10;
}

.timeline-item.completed::before {
    background: var(--green);
    border-color: var(--green);
    box-shadow: 0 0 20px rgba(34,197,94,.4);
}

.timeline-item.active::before {
    background: var(--blue);
    border-color: var(--blue);
    box-shadow: 0 0 20px rgba(59,130,246,.6),
                0 0 40px rgba(59,130,246,.3);
    animation: pulse 2s infinite;
}

.timeline-item.pending::before {
    background: transparent;
    border-color: var(--muted);
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 20px rgba(59,130,246,.6),
                    0 0 40px rgba(59,130,246,.3);
    }
    50% {
        box-shadow: 0 0 30px rgba(59,130,246,.8),
                    0 0 60px rgba(59,130,246,.5);
    }
}

/* Icon inside circle */
.timeline-item .icon {
    position: absolute;
    left: 14px;
    top: -2px;
    font-size: 18px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 11;
}

.timeline-content {
    padding: 15px 18px;
    border-radius: 12px;
    background: var(--panel);
    border: 1px solid var(--border);
    transition: all .3s ease;
}

.timeline-item.completed .timeline-content {
    border-color: rgba(34,197,94,.3);
    background: rgba(34,197,94,.05);
}

.timeline-item.active .timeline-content {
    border-color: rgba(59,130,246,.4);
    background: rgba(59,130,246,.08);
    box-shadow: 0 0 20px rgba(59,130,246,.15);
}

.timeline-content h3 {
    margin: 0 0 6px 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
}

.timeline-content p {
    margin: 0 0 4px 0;
    font-size: 12px;
    color: var(--muted);
    line-height: 1.4;
}

.timeline-content .date {
    font-size: 11px;
    color: var(--muted);
    margin-top: 6px;
    border-top: 1px solid var(--border);
    padding-top: 6px;
}

/* Status indicator */
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    margin-top: 6px;
    margin-right: 4px;
}

.status-badge.completed {
    background: rgba(34,197,94,.15);
    color: var(--green);
}

.status-badge.active {
    background: rgba(59,130,246,.15);
    color: var(--blue);
    animation: blink .5s infinite;
}

.status-badge.pending {
    background: rgba(148,163,184,.1);
    color: var(--muted);
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: .7; }
}

.progress-summary {
    margin-top: 40px;
    padding: 20px;
    border-radius: 12px;
    background: rgba(59,130,246,.05);
    border: 1px solid rgba(59,130,246,.2);
}

.progress-summary h3 {
    margin: 0 0 12px 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--blue);
}

.progress-bar {
    width: 100%;
    height: 8px;
    border-radius: 10px;
    background: var(--border);
    overflow: hidden;
    margin-bottom: 12px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--blue), var(--cyan), var(--purple));
    border-radius: 10px;
    transition: width .6s ease;
    box-shadow: 0 0 15px rgba(59,130,246,.5);
}

.progress-text {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: var(--muted);
}

.back-button {
    display: inline-block;
    text-decoration: none;
    color: var(--blue);
    font-size: 12px;
    margin-top: 20px;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid rgba(59,130,246,.3);
    transition: all .2s;
}

.back-button:hover {
    background: rgba(59,130,246,.1);
    border-color: rgba(59,130,246,.5);
}

@media (max-width: 600px) {
    .timeline::before {
        left: 20px;
    }
    
    .timeline-item {
        padding-left: 60px;
    }
    
    .timeline-item::before {
        left: 7px;
        width: 24px;
        height: 24px;
        border-width: 2px;
    }
    
    .timeline-item .icon {
        left: 6px;
        font-size: 14px;
    }
    
    .header h1 {
        font-size: 22px;
    }
}

</style>
</head>
<body>

<div class="container">

    <div class="header">
        <h1>📍 Admission Timeline</h1>
        <p>Track your complete admission journey</p>
    </div>

    <div class="student-info">
        👤 <?php echo htmlspecialchars($student["name"]); ?> 
        (<?php echo htmlspecialchars($student["roll_no"]); ?>)
    </div>

    <div class="timeline">
        
        <?php 
        $completedCount = 0;
        $totalStages = count($stages);
        
        foreach ($stages as $stage):
            $isCompleted = $stage["status"] === "completed";
            $isActive = $stage["status"] === "active";
            
            if ($isCompleted) $completedCount++;
        ?>

        <div class="timeline-item <?php echo $stage["status"]; ?>">
            
            <div class="icon">
                <?php echo $stage["icon"]; ?>
            </div>

            <div class="timeline-content">
                
                <h3>
                    <?php echo htmlspecialchars($stage["title"]); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($stage["details"]); ?>
                </p>

                <?php if ($stage["date"]): ?>
                <div class="date">
                    📅 <?php echo date("M d, Y", strtotime($stage["date"])); ?>
                </div>
                <?php endif; ?>

                <div>
                    <span class="status-badge <?php echo $stage["status"]; ?>">
                        <?php 
                        if ($isCompleted) echo "✓ COMPLETED";
                        elseif ($isActive) echo "● IN PROGRESS";
                        else echo "○ PENDING";
                        ?>
                    </span>
                </div>

            </div>

        </div>

        <?php endforeach; ?>

    </div>

    <!-- Progress Summary -->
    <div class="progress-summary">
        
        <h3>⚡ Overall Progress</h3>

        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo ($completedCount / $totalStages) * 100; ?>%"></div>
        </div>

        <div class="progress-text">
            <span><?php echo $completedCount; ?> of <?php echo $totalStages; ?> stages completed</span>
            <span><?php echo round(($completedCount / $totalStages) * 100); ?>%</span>
        </div>

    </div>

    <a href="student_dashboard.php" class="back-button">
        ← Back to Dashboard
    </a>

</div>

</body>
</html>
