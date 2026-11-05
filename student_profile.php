<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";

$studentId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$message = "";
$messageType = "";

if ($studentId <= 0)
{
    $message = "Invalid student ID";
    $messageType = "error";
}

$student = null;
$application = null;
$documents = array();
$admission = null;
$academic = null;

if ($studentId > 0)
{
    /* Get student info */
    $stmt = $conn->prepare(
        "SELECT * FROM students WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    
    if (!$student)
    {
        $message = "Student not found";
        $messageType = "error";
    }
    else
    {
        /* Get latest application */
        $stmt = $conn->prepare(
            "SELECT * FROM student_applications 
             WHERE student_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result->num_rows > 0 ? $result->fetch_assoc() : null;
        $stmt->close();
        
        /* Get documents */
        if ($application)
        {
            $stmt = $conn->prepare(
                "SELECT * FROM student_documents 
                 WHERE application_id = ? 
                 ORDER BY document_type ASC"
            );
            $stmt->bind_param("i", $application["id"]);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc())
            {
                $documents[] = $row;
            }
            $stmt->close();
        }
        
        /* Get admission status */
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
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Student Profile 360° - Smart Campus</title>
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
    --green: #22c55e;
    --red: #ef4444;
}

body {
    margin: 0;
    font-family: Inter, Segoe UI, Arial, sans-serif;
    background: radial-gradient(circle at 10% 10%, rgba(59,130,246,.13), transparent 30%),
                radial-gradient(circle at 90% 15%, rgba(139,92,246,.12), transparent 30%),
                #070b17;
    color: var(--text);
    min-height: 100vh;
    padding: 30px 20px;
}

.container {
    max-width: 1000px;
    margin: 0 auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.header h1 {
    margin: 0;
    font-size: 24px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 8px 14px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,.05);
    color: var(--text);
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
}

.btn:hover {
    background: rgba(59,130,246,.1);
    border-color: rgba(59,130,246,.3);
}

.alert {
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 12px;
}

.alert-error {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.2);
    color: #fca5a5;
}

.section {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 20px;
}

.section-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--blue);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 0 0 18px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.info-item {
    border-radius: 8px;
    padding: 12px;
    background: rgba(255,255,255,.02);
    border: 1px solid var(--border);
}

.info-label {
    font-size: 10px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 6px;
    display: block;
}

.info-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 4px;
}

.status-accepted {
    background: rgba(34,197,94,.15);
    color: var(--green);
}

.status-pending {
    background: rgba(245,158,11,.15);
    color: #fbbf24;
}

.status-rejected {
    background: rgba(239,68,68,.15);
    color: #fca5a5;
}

.status-verified {
    background: rgba(34,197,94,.15);
    color: var(--green);
}

.document-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
}

.document-table th {
    padding: 10px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.document-table td {
    padding: 10px;
    font-size: 12px;
    border-bottom: 1px solid var(--border);
}

.document-table tr:hover {
    background: rgba(59,130,246,.05);
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: var(--muted);
    font-size: 13px;
}

.empty-state-icon {
    font-size: 28px;
    margin-bottom: 10px;
}

.stat-box {
    display: inline-block;
    padding: 12px 16px;
    background: rgba(59,130,246,.08);
    border: 1px solid rgba(59,130,246,.15);
    border-radius: 8px;
    margin: 0 8px 8px 0;
    font-size: 12px;
}

.stat-box strong {
    color: var(--blue);
    font-weight: 700;
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

@media (max-width: 700px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .header {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .document-table {
        font-size: 11px;
    }
}

</style>
</head>
<body>

<div class="container">

    <div class="header">
        <h1>👤 Student Profile 360°</h1>
        <div class="header-actions">
            <a href="admin_applications.php" class="btn">Back to Applications</a>
        </div>
    </div>

    <?php if ($message !== ""): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($student): ?>

    <!-- STUDENT INFORMATION -->
    <div class="section">
        <div class="section-title">📋 Personal Information</div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Roll Number</span>
                <div class="info-value"><?php echo htmlspecialchars($student["roll_no"]); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Full Name</span>
                <div class="info-value"><?php echo htmlspecialchars($student["name"]); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Department</span>
                <div class="info-value"><?php echo htmlspecialchars($student["department"]); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Semester</span>
                <div class="info-value"><?php echo htmlspecialchars($student["semester"]); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Phone</span>
                <div class="info-value"><?php echo htmlspecialchars($student["phone"] ?? "N/A"); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">CGPA</span>
                <div class="info-value"><?php echo htmlspecialchars($student["cgpa"] ?? "N/A"); ?></div>
            </div>
        </div>
    </div>

    <!-- APPLICATION INFORMATION -->
    <div class="section">
        <div class="section-title">📝 Application Information</div>
        
        <?php if ($application): ?>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Application Number</span>
                <div class="info-value"><?php echo htmlspecialchars($application["application_number"]); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Program Type</span>
                <div class="info-value">
                    <strong><?php echo htmlspecialchars($application["program_type"]); ?></strong>
                </div>
            </div>
            <div class="info-item">
                <span class="info-label">Course</span>
                <div class="info-value"><?php echo htmlspecialchars($application["course_name"]); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Status</span>
                <div class="info-value">
                    <span class="status-badge status-<?php 
                        echo strtolower($application["application_status"]);
                    ?>">
                        <?php echo htmlspecialchars($application["application_status"]); ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <span class="info-label">Application Date</span>
                <div class="info-value"><?php echo date("M d, Y", strtotime($application["created_at"])); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Verified Date</span>
                <div class="info-value"><?php 
                    echo $application["verified_at"] ? date("M d, Y", strtotime($application["verified_at"])) : "Pending";
                ?></div>
            </div>
        </div>

        <?php if ($application["admin_remarks"]): ?>
        <div style="margin-top: 16px; padding: 12px; background: rgba(255,255,255,.02); border-left: 3px solid var(--blue); border-radius: 4px;">
            <strong style="font-size: 11px; color: var(--muted); display: block; margin-bottom: 6px;">ADMIN REMARKS</strong>
            <div style="font-size: 12px; color: var(--text); line-height: 1.5;">
                <?php echo htmlspecialchars($application["admin_remarks"]); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>

        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            No application submitted yet
        </div>

        <?php endif; ?>

    </div>

    <!-- DOCUMENTS INFORMATION -->
    <div class="section">
        <div class="section-title">📄 Documents Submitted</div>
        
        <?php if (count($documents) > 0): ?>
        
        <div style="margin-bottom: 12px;">
            <?php 
            $verified = 0;
            $rejected = 0;
            $pending = 0;
            
            foreach ($documents as $doc) {
                if ($doc["verification_status"] === "Verified") $verified++;
                elseif ($doc["verification_status"] === "Rejected") $rejected++;
                else $pending++;
            }
            ?>
            <div class="stat-box"><strong><?php echo count($documents); ?></strong> Total</div>
            <div class="stat-box"><strong style="color: var(--green);"><?php echo $verified; ?></strong> Verified</div>
            <div class="stat-box"><strong style="color: #fbbf24;"><?php echo $pending; ?></strong> Pending</div>
            <div class="stat-box"><strong style="color: var(--red);"><?php echo $rejected; ?></strong> Rejected</div>
        </div>

        <table class="document-table">
            <thead>
                <tr>
                    <th>Document Type</th>
                    <th>Status</th>
                    <th>Uploaded Date</th>
                    <th>Verified Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?php echo htmlspecialchars($doc["document_type"]); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower($doc["verification_status"]); ?>">
                            <?php echo htmlspecialchars($doc["verification_status"]); ?>
                        </span>
                    </td>
                    <td><?php echo date("M d, Y", strtotime($doc["uploaded_at"])); ?></td>
                    <td><?php echo $doc["verified_at"] ? date("M d, Y", strtotime($doc["verified_at"])) : "-"; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php else: ?>

        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            No documents uploaded yet
        </div>

        <?php endif; ?>

    </div>

    <!-- ADMISSION INFORMATION -->
    <div class="section">
        <div class="section-title">🎓 Admission Status</div>
        
        <?php if ($admission): ?>

        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Admission Number</span>
                <div class="info-value"><?php echo htmlspecialchars($admission["admission_number"] ?? "Not Generated"); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Admission Status</span>
                <div class="info-value">
                    <span class="status-badge status-<?php echo strtolower($admission["admission_status"]); ?>">
                        <?php echo htmlspecialchars($admission["admission_status"]); ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <span class="info-label">Admission Date</span>
                <div class="info-value"><?php echo date("M d, Y", strtotime($admission["created_at"])); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Updated Date</span>
                <div class="info-value"><?php echo date("M d, Y", strtotime($admission["updated_at"] ?? $admission["created_at"])); ?></div>
            </div>
        </div>

        <?php else: ?>

        <div class="empty-state">
            <div class="empty-state-icon">⏳</div>
            Admission processing not yet initiated
        </div>

        <?php endif; ?>

    </div>

    <a href="admin_applications.php" class="back-button">
        ← Back to Applications
    </a>

    <?php else: ?>

    <div class="alert alert-error">
        Please select a valid student to view their profile.
    </div>

    <?php endif; ?>

</div>

</body>
</html>
