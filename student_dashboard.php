<?php

session_start();

if (!isset($_SESSION["student_id"]))
{
    header("Location: login.php?role=student");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";
require_once "config/photo_utils.php";

$studentId = $_SESSION["student_id"];

$student = null;

$stmt = $conn->prepare(
    "SELECT *
     FROM students
     WHERE id = ?"
);

$stmt->bind_param("i", $studentId);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 1)
{
    $student = $result->fetch_assoc();
}
else
{
    session_destroy();

    header("Location: login.php?role=student");
    exit();
}


$application = null;

$stmt = $conn->prepare(
    "SELECT *
     FROM student_applications
     WHERE student_id = ?
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->bind_param("i", $studentId);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0)
{
    $application = $result->fetch_assoc();
}


$documentsVerified = 0;
$totalDocuments = 0;
$requiredDocCount = 0;

/* Get actual required document count based on program type */
if ($application && isset($application["program_type"]))
{
    $programType = strtoupper(trim($application["program_type"]));
    $requiredDocs = cms_required_documents($programType);
    $requiredDocCount = count($requiredDocs);
}

if ($requiredDocCount == 0)
{
    $requiredDocCount = 5; /* Default to UG if not set */
}

$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(
            CASE
                WHEN verification_status = 'Verified'
                THEN 1
                ELSE 0
            END
        ) AS verified
     FROM student_documents
     WHERE student_id = ?"
);

$stmt->bind_param("i", $studentId);

$stmt->execute();

$result = $stmt->get_result();

if ($result)
{
    $row = $result->fetch_assoc();

    $totalDocuments = (int)$row["total"];
    $documentsVerified = (int)$row["verified"];
}


$admission = null;

$stmt = $conn->prepare(
    "SELECT *
     FROM admission_processing
     WHERE student_id = ?
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->bind_param("i", $studentId);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0)
{
    $admission = $result->fetch_assoc();
}


/* =========================================================
   FETCH NOTIFICATIONS
   ========================================================= */

$notifications = array();
$unreadNotificationCount = 0;

$notifyStmt = $conn->prepare(
    "SELECT *
     FROM student_notifications
     WHERE student_id = ?
     ORDER BY created_at DESC
     LIMIT 10"
);

if ($notifyStmt)
{
    $notifyStmt->bind_param("i", $studentId);
    $notifyStmt->execute();
    $notifyResult = $notifyStmt->get_result();
    
    if ($notifyResult)
    {
        while ($notifyRow = $notifyResult->fetch_assoc())
        {
            $notifications[] = $notifyRow;
            if (!$notifyRow["is_read"])
            {
                $unreadNotificationCount++;
            }
        }
    }
    
    $notifyStmt->close();
}


/* =========================================================
   DOCUMENT SUBMISSION ACCESS
   ========================================================= */

$documentsEnabled = false;

if ($application)
{
    if (
        isset($application["application_status"]) &&
        $application["application_status"] === "Accepted" &&
        isset($application["verified_at"]) &&
        $application["verified_at"] != "" &&
        $application["verified_at"] != null
    )
    {
        $documentsEnabled = true;
    }
}

$documentMessage = "Application verification required";

if ($documentsEnabled)
{
    $documentMessage = "Document submission is unlocked";
}

/* =========================================================
   NEW FEATURE 8 - ADMISSION READINESS CALCULATION
   ========================================================= */

$readinessPercentage = 0;
$readinessItems = array();

if ($application)
{
    $readinessPercentage = 0;
    $completedSteps = 0;
    $totalSteps = 5;
    
    // Step 1: Application submitted
    $readinessItems[] = array(
        "status" => "completed",
        "text" => "Application submitted"
    );
    $completedSteps++;
    
    // Step 2: Application accepted/verified by admin
    if (
        isset($application["application_status"]) && 
        $application["application_status"] === "Accepted" &&
        isset($application["verified_at"]) &&
        $application["verified_at"] != "" &&
        $application["verified_at"] != null
    )
    {
        $readinessItems[] = array(
            "status" => "completed",
            "text" => "Application accepted"
        );
        $completedSteps++;
    }
    else
    {
        $readinessItems[] = array(
            "status" => "pending",
            "text" => "Application pending verification"
        );
    }
    
    // Step 3 & 4: Required documents uploaded and verified
    $documentsUploadedCount = $totalDocuments;
    $pendingDocCount = max(0, $requiredDocCount - $documentsVerified);
    
    if ($documentsUploadedCount > 0)
    {
        $readinessItems[] = array(
            "status" => "completed",
            "text" => "Required documents uploaded"
        );
        $completedSteps++;
    }
    else
    {
        $readinessItems[] = array(
            "status" => "pending",
            "text" => "Documents pending upload"
        );
    }
    
    if ($documentsVerified >= $requiredDocCount)
    {
        $readinessItems[] = array(
            "status" => "completed",
            "text" => "All required documents verified"
        );
        $completedSteps++;
    }
    else
    {
        $readinessItems[] = array(
            "status" => "pending",
            "text" => "$pendingDocCount documents pending verification"
        );
    }
    
    // Step 5: Admission processing completed
    if ($admission && isset($admission["admission_status"]) && $admission["admission_status"] === "Approved")
    {
        $readinessItems[] = array(
            "status" => "completed",
            "text" => "Admission approved"
        );
        $completedSteps++;
    }
    else
    {
        $readinessItems[] = array(
            "status" => "pending",
            "text" => "Admission processing pending"
        );
    }
    
    $readinessPercentage = min(100, max(0, (int)round(($completedSteps / $totalSteps) * 100)));
}
else
{
    $readinessPercentage = 0;
    $readinessItems[] = array(
        "status" => "pending",
        "text" => "Complete your admission application to begin"
    );
}

/* =========================================================
   STUDENT ATTENDANCE SUMMARY
   ========================================================= */

$attendanceSummary = array(
    "total" => 0,
    "present" => 0,
    "percent" => 0,
    "message" => "Attendance will appear after admission approval"
);

$attendanceVisible = (
    $admission &&
    isset($admission["admission_status"]) &&
    $admission["admission_status"] === "Approved"
);

$attendanceStmt = $conn->prepare(
    "SELECT COUNT(*) AS total_sessions,
            SUM(CASE WHEN ca.status = 'present' THEN 1 ELSE 0 END) AS present_sessions
     FROM class_attendance ca
     JOIN class_sessions cs ON cs.id = ca.session_id
     WHERE ca.student_id = ?
     AND cs.class_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)"
);

if ($attendanceStmt)
{
    $attendanceStmt->bind_param("i", $studentId);
    $attendanceStmt->execute();
    $attendanceResult = $attendanceStmt->get_result();

    if ($attendanceResult && $attendanceResult->num_rows > 0)
    {
        $attendanceRow = $attendanceResult->fetch_assoc();
        $attendanceSummary["total"] = (int)($attendanceRow["total_sessions"] ?? 0);
        $attendanceSummary["present"] = (int)($attendanceRow["present_sessions"] ?? 0);

        if ($attendanceSummary["total"] > 0)
        {
            $attendanceSummary["percent"] = (int)round(($attendanceSummary["present"] / $attendanceSummary["total"]) * 100);
            $attendanceSummary["message"] = $attendanceSummary["percent"] >= 85 ? "Attendance is above the required minimum" : "Attendance is below the required minimum";
        }
        else
        {
            $attendanceSummary["message"] = "Attendance will appear after class marking begins";
        }
    }

    $attendanceStmt->close();
}

/* =========================================================
   NEW FEATURE 9 - FETCH RECENT ACTIVITY
   ========================================================= */

$recentActivities = array();

/* Check if activity_log table exists */
$activityTableExists = cms_table_exists($conn, "activity_log");

if ($activityTableExists)
{
    /* Query activity log for this student */
    $stmt = $conn->prepare(
        "SELECT * FROM activity_log
         WHERE actor_type = 'student' AND actor_id = ?
         ORDER BY created_at DESC
         LIMIT 10"
    );
    
    if ($stmt)
    {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $activityResult = $stmt->get_result();
        
        if ($activityResult)
        {
            while ($activityRow = $activityResult->fetch_assoc())
            {
                $recentActivities[] = $activityRow;
            }
        }
        
        $stmt->close();
    }
}

/* Build activities from application and documents if no activity log exists or is empty */
if (empty($recentActivities))
{
    if ($application)
    {
        $recentActivities[] = array(
            "title" => "Application submitted",
            "created_at" => $application["created_at"],
            "action_type" => "application_submitted"
        );
        
        if (isset($application["application_status"]) && $application["application_status"] === "Accepted")
        {
            if (isset($application["verified_at"]) && $application["verified_at"] != "")
            {
                $recentActivities[] = array(
                    "title" => "Application accepted",
                    "created_at" => $application["verified_at"],
                    "action_type" => "application_accepted"
                );
            }
        }
        elseif (isset($application["application_status"]) && $application["application_status"] === "Rejected")
        {
            $recentActivities[] = array(
                "title" => "Application rejected",
                "created_at" => $application["updated_at"],
                "action_type" => "application_rejected"
            );
        }
    }
    
    /* Get document activities */
    $docStmt = $conn->prepare(
        "SELECT id, document_type, verification_status, uploaded_at, verified_at FROM student_documents
         WHERE student_id = ?
         ORDER BY uploaded_at DESC
         LIMIT 5"
    );
    
    if ($docStmt)
    {
        $docStmt->bind_param("i", $studentId);
        $docStmt->execute();
        $docResult = $docStmt->get_result();
        
        if ($docResult)
        {
            while ($docRow = $docResult->fetch_assoc())
            {
                $recentActivities[] = array(
                    "title" => htmlspecialchars($docRow["document_type"]) . " uploaded",
                    "created_at" => $docRow["uploaded_at"],
                    "action_type" => "document_uploaded"
                );
                
                if ($docRow["verification_status"] === "Verified" && $docRow["verified_at"] != "")
                {
                    $recentActivities[] = array(
                        "title" => htmlspecialchars($docRow["document_type"]) . " verified",
                        "created_at" => $docRow["verified_at"],
                        "action_type" => "document_verified"
                    );
                }
            }
        }
        
        $docStmt->close();
    }
    
    /* Sort by date descending */
    usort($recentActivities, function($a, $b) {
        return strtotime($b["created_at"]) - strtotime($a["created_at"]);
    });
    
    /* Limit to 10 */
    $recentActivities = array_slice($recentActivities, 0, 10);
}

/* =========================================================
   DETERMINE READINESS BUTTON LINK
   ========================================================= */

$readinessContinueLink = "student_application.php";

if ($application)
{
    if (isset($application["application_status"]) && $application["application_status"] === "Accepted")
    {
        if ($documentsVerified >= $requiredDocCount)
        {
            $readinessContinueLink = "admission_processing.php";
        }
        else
        {
            $readinessContinueLink = "student_documents.php";
        }
    }
}

/* =========================================================
   ACADEMIC DATA - FETCH STUDENT MARKS & GPA
   ========================================================= */

$isAdmittedForAcademics = cms_is_student_admitted($conn, $studentId);
$programTypeForAcademics = $application ? ($application["program_type"] ?? "UG") : "UG";
$overallCGPA = 0;
$semesterGPAs = array();
$completedSemesters = 0;
$totalSemestersExpected = ($programTypeForAcademics === "PG") ? 4 : 6;

if ($isAdmittedForAcademics)
{
    $overallCGPA = cms_calculate_overall_cgpa($conn, $studentId);
    
    /* Get list of semesters */
    if ($programTypeForAcademics === "PG")
    {
        $semesters = array("Semester 1", "Semester 2", "Semester 3", "Semester 4");
    }
    else
    {
        $semesters = array("Semester 1", "Semester 2", "Semester 3", "Semester 4", "Semester 5", "Semester 6");
    }
    
    /* Get GPA for each semester */
    foreach ($semesters as $sem)
    {
        $gpa = cms_calculate_semester_gpa($conn, $studentId, $sem);
        $semesterGPAs[$sem] = $gpa;
        
        /* Count completed semesters (those with at least one published mark) */
        $checkStmt = $conn->prepare(
            "SELECT COUNT(*) as cnt FROM student_marks WHERE student_id = ? AND semester = ? AND publish_status = 'PUBLISHED'"
        );
        if ($checkStmt)
        {
            $checkStmt->bind_param("is", $studentId, $sem);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkResult && $checkResult->num_rows > 0)
            {
                $checkRow = $checkResult->fetch_assoc();
                if ($checkRow["cnt"] > 0)
                {
                    $completedSemesters++;
                }
            }
            $checkStmt->close();
        }
    }
}

?>

<!DOCTYPE html>

<html>

<head>

<title>Student Dashboard</title>

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<style>

* {
    box-sizing: border-box;
}

body
{
    margin:0;

    font-family:
    Inter,
    Segoe UI,
    Arial,
    sans-serif;

    color:#f8fafc;

    background:
    radial-gradient(
        circle at 10% 10%,
        rgba(59,130,246,.13),
        transparent 30%
    ),

    radial-gradient(
        circle at 90% 15%,
        rgba(139,92,246,.12),
        transparent 30%
    ),

    #070b17;

    min-height:100vh;
}

.container
{
    max-width:1200px;

    margin:auto;

    padding:35px;
}

.header
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:25px;
}

.title
{
    font-size:27px;

    font-weight:700;
}

.subtitle
{
    color:#94a3b8;

    font-size:12px;

    margin-top:5px;
}

.logout
{
    text-decoration:none;

    color:#fca5a5;

    padding:10px 15px;

    border-radius:9px;

    border:1px solid rgba(239,68,68,.15);

    background:rgba(239,68,68,.07);
}

.profile
{
    padding:25px;

    border-radius:18px;

    background:
    rgba(13,19,36,.86);

    border:
    1px solid rgba(148,163,184,.14);

    margin-bottom:20px;
}

.profile h2
{
    margin-top:0;
}

.grid
{
    display:grid;

    grid-template-columns:
    repeat(3,1fr);

    gap:18px;
}

.card
{
    padding:22px;

    border-radius:15px;

    background:
    rgba(13,19,36,.86);

    border:
    1px solid rgba(148,163,184,.14);

    transition:.2s;
}

.card:hover
{
    transform:translateY(-4px);

    border-color:
    rgba(96,165,250,.3);
}

.card-icon
{
    font-size:25px;

    margin-bottom:15px;
}

.card h3
{
    margin:0 0 8px;

    font-size:15px;
}

.card p
{
    color:#94a3b8;

    font-size:11px;

    line-height:1.6;
}

.button
{
    display:inline-block;

    text-decoration:none;

    color:white;

    padding:10px 14px;

    border-radius:9px;

    background:
    linear-gradient(
        135deg,
        #2563eb,
        #06b6d4
    );

    font-size:12px;

    margin-top:8px;
}

.status
{
    display:inline-block;

    padding:5px 9px;

    border-radius:20px;

    font-size:10px;

    background:rgba(59,130,246,.12);

    color:#60a5fa;
}

/* =========================================================
   NEW FEATURE 1 - PERSONALIZED HERO SECTION
   ========================================================= */

.hero-section
{
    padding:35px 28px;

    border-radius:18px;

    background:
    linear-gradient(
        135deg,
        rgba(13,19,36,.95),
        rgba(25,28,45,.90)
    );

    border:
    1px solid rgba(59,130,246,.25);

    margin-bottom:25px;

    box-shadow:
    0 0 30px rgba(59,130,246,.08),
    inset 0 0 60px rgba(59,130,246,.03);
}

.hero-content
{
    display:grid;

    grid-template-columns:
    1fr 1fr;

    gap:40px;

    align-items:start;
}

.hero-greeting
{
    display:flex;

    flex-direction:column;
}

.hero-greeting h1
{
    margin:0 0 8px;

    font-size:28px;

    font-weight:700;

    background:
    linear-gradient(
        135deg,
        #60a5fa,
        #06b6d4
    );

    -webkit-background-clip:text;

    -webkit-text-fill-color:transparent;

    background-clip:text;
}

.hero-greeting p
{
    margin:0;

    color:#cbd5e1;

    font-size:13px;

    line-height:1.6;
}

.hero-stats
{
    display:grid;

    grid-template-columns:
    repeat(3,1fr);

    gap:15px;
}

.hero-stat
{
    padding:15px;

    border-radius:10px;

    background:
    rgba(255,255,255,.05);

    border:
    1px solid rgba(148,163,184,.12);

    text-align:center;
}

.hero-stat-label
{
    color:#94a3b8;

    font-size:10px;

    font-weight:600;

    text-transform:uppercase;

    margin-bottom:8px;

    letter-spacing:0.5px;
}

.hero-stat-value
{
    color:#e2e8f0;

    font-size:16px;

    font-weight:700;
}

/* =========================================================
   NEW FEATURE 3 - LARGE ADMISSION STATUS CARD
   ========================================================= */

.admission-status-card
{
    padding:30px;

    border-radius:16px;

    background:
    rgba(13,19,36,.88);

    border:
    1px solid rgba(148,163,184,.14);

    margin-bottom:25px;

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:30px;

    align-items:center;
}

.admission-status-icon
{
    font-size:48px;

    text-align:center;
}

.admission-status-content h2
{
    margin:0 0 8px;

    font-size:18px;

    font-weight:700;

    color:#e2e8f0;
}

.admission-status-content p
{
    margin:0 0 12px;

    color:#cbd5e1;

    font-size:12px;

    line-height:1.6;
}

.admission-status-detail
{
    padding:10px 0;

    border-bottom:
    1px solid rgba(148,163,184,.10);

    display:flex;

    justify-content:space-between;

    font-size:12px;
}

.admission-status-detail:last-child
{
    border-bottom:none;
}

.admission-status-label
{
    color:#94a3b8;
}

.admission-status-value
{
    color:#e2e8f0;

    font-weight:600;
}

.admission-status-accepted
{
    border-color:
    rgba(34,197,94,.30);

    background:
    linear-gradient(
        135deg,
        rgba(34,197,94,.08),
        rgba(20,184,166,.05)
    );
}

.admission-status-pending
{
    border-color:
    rgba(245,158,11,.30);

    background:
    linear-gradient(
        135deg,
        rgba(245,158,11,.08),
        rgba(251,146,60,.05)
    );
}

.admission-status-rejected
{
    border-color:
    rgba(239,68,68,.30);

    background:
    linear-gradient(
        135deg,
        rgba(239,68,68,.08),
        rgba(244,63,94,.05)
    );
}

.admission-status-processing
{
    border-color:
    rgba(59,130,246,.30);

    background:
    linear-gradient(
        135deg,
        rgba(59,130,246,.08),
        rgba(99,102,241,.05)
    );
}

/* =========================================================
   NEW FEATURE 8 - ADMISSION READINESS METER
   ========================================================= */

.readiness-section
{
    padding:28px;

    border-radius:16px;

    background:
    rgba(13,19,36,.88);

    border:
    1px solid rgba(148,163,184,.14);

    margin-bottom:25px;
}

.readiness-header
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:20px;
}

.readiness-title
{
    font-size:16px;

    font-weight:700;

    color:#e2e8f0;
}

.readiness-percentage
{
    font-size:20px;

    font-weight:700;

    background:
    linear-gradient(
        135deg,
        #60a5fa,
        #06b6d4
    );

    -webkit-background-clip:text;

    -webkit-text-fill-color:transparent;

    background-clip:text;
}

.readiness-progress-bar
{
    width:100%;

    height:8px;

    border-radius:20px;

    background:
    rgba(255,255,255,.08);

    overflow:hidden;

    margin-bottom:20px;

    border:
    1px solid rgba(148,163,184,.10);
}

.readiness-progress-fill
{
    height:100%;

    background:
    linear-gradient(
        90deg,
        #2563eb,
        #06b6d4
    );

    border-radius:20px;

    transition:width .5s ease;
}

.readiness-checklist
{
    display:flex;

    flex-direction:column;

    gap:12px;

    margin-bottom:20px;
}

.readiness-item
{
    display:flex;

    align-items:center;

    gap:12px;

    padding:10px;

    border-radius:8px;

    background:
    rgba(255,255,255,.02);

    font-size:12px;
}

.readiness-item-icon
{
    font-size:14px;

    min-width:16px;
}

.readiness-item.completed .readiness-item-icon
{
    color:#22c55e;
}

.readiness-item.pending .readiness-item-icon
{
    color:#f59e0b;
}

.readiness-item-text
{
    color:#cbd5e1;
}

.readiness-footer
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    padding-top:15px;

    border-top:
    1px solid rgba(148,163,184,.10);
}

.readiness-footer-text
{
    color:#94a3b8;

    font-size:12px;
}

/* =========================================================
   NEW FEATURE 9 - RECENT ACTIVITY TIMELINE
   ========================================================= */

.activity-timeline
{
    padding:28px;

    border-radius:16px;

    background:
    rgba(13,19,36,.88);

    border:
    1px solid rgba(148,163,184,.14);

    margin-bottom:25px;
}

.activity-timeline-title
{
    font-size:16px;

    font-weight:700;

    color:#e2e8f0;

    margin:0 0 20px;
}

.activity-timeline-list
{
    display:flex;

    flex-direction:column;

    gap:14px;
}

.activity-item
{
    display:flex;

    gap:14px;

    padding:12px;

    border-radius:8px;

    background:
    rgba(255,255,255,.02);

    border-left:3px solid rgba(59,130,246,.4);
}

.activity-item-icon
{
    font-size:16px;

    min-width:20px;

    text-align:center;

    margin-top:2px;
}

.activity-item-content
{
    flex:1;
}

.activity-item-title
{
    color:#e2e8f0;

    font-size:12px;

    font-weight:600;

    margin:0 0 4px;
}

.activity-item-time
{
    color:#64748b;

    font-size:10px;

    margin:0;
}

.activity-item-success
{
    border-left-color:
    rgba(34,197,94,.40);
}

.activity-item-pending
{
    border-left-color:
    rgba(245,158,11,.40);
}

.activity-item-rejected
{
    border-left-color:
    rgba(239,68,68,.40);
}

.activity-item-processing
{
    border-left-color:
    rgba(59,130,246,.40);
}

.activity-item-neutral
{
    border-left-color:
    rgba(148,163,184,.40);
}

.activity-empty
{
    text-align:center;

    color:#94a3b8;

    font-size:12px;

    padding:20px;
}

/* =========================================================
   ACADEMIC SECTION STYLES
   ========================================================= */

.academic-section
{
    padding:28px;

    border-radius:16px;

    background:
    rgba(13,19,36,.88);

    border:
    1px solid rgba(148,163,184,.14);

    margin-bottom:25px;
}

.academic-header
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:20px;
}

.academic-title
{
    font-size:16px;

    font-weight:700;

    color:#e2e8f0;
}

.academic-cgpa
{
    font-size:22px;

    font-weight:700;

    background:
    linear-gradient(
        135deg,
        #60a5fa,
        #06b6d4
    );

    -webkit-background-clip:text;

    -webkit-text-fill-color:transparent;

    background-clip:text;
}

.academic-locked
{
    padding:20px;

    border-radius:12px;

    background:rgba(59,130,246,.08);

    border:1px solid rgba(59,130,246,.16);

    color:#93c5fd;

    text-align:center;
}

.academic-grid
{
    display:grid;

    grid-template-columns:
    repeat(4,1fr);

    gap:14px;

    margin-bottom:20px;
}

.academic-stat
{
    padding:16px;

    border-radius:10px;

    background:rgba(13,19,36,.88);

    border:1px solid rgba(148,163,184,.14);

    text-align:center;
}

.academic-stat-label
{
    color:#94a3b8;

    font-size:10px;

    font-weight:600;

    text-transform:uppercase;

    margin-bottom:6px;
}

.academic-stat-value
{
    color:#e2e8f0;

    font-size:18px;

    font-weight:700;
}

.semester-cards
{
    display:grid;

    grid-template-columns:
    repeat(2,1fr);

    gap:14px;

    margin-bottom:20px;
}

.semester-card
{
    padding:16px;

    border-radius:10px;

    background:rgba(13,19,36,.88);

    border:1px solid rgba(148,163,184,.14);
}

.semester-card-title
{
    font-size:12px;

    font-weight:600;

    color:#e2e8f0;

    margin:0 0 8px;
}

.semester-card-gpa
{
    font-size:16px;

    font-weight:700;

    color:#60a5fa;
}

.marks-button
{
    display:inline-block;

    text-decoration:none;

    color:white;

    padding:10px 14px;

    border-radius:8px;

    background:
    linear-gradient(
        135deg,
        #2563eb,
        #06b6d4
    );

    font-size:12px;

    margin-top:12px;

    transition:.2s;
}

.marks-button:hover
{
    transform:translateY(-2px);
}

@media(max-width:1024px)
{
    .hero-content
    {
        grid-template-columns:1fr;

        gap:25px;
    }

    .hero-stats
    {
        grid-template-columns:
        repeat(3,1fr);
    }

    .admission-status-card
    {
        grid-template-columns:1fr;

        gap:20px;
    }

    .grid
    {
        grid-template-columns:
        repeat(2,1fr);
    }

    .academic-grid
    {
        grid-template-columns:
        repeat(2,1fr);
    }

    .semester-cards
    {
        grid-template-columns:1fr;
    }
}

@media(max-width:800px)
{
    .grid
    {
        grid-template-columns:1fr;
    }

    .container
    {
        padding:20px;
    }

    .hero-stats
    {
        grid-template-columns:
        repeat(2,1fr);
    }

    .header
    {
        flex-direction:column;

        align-items:flex-start;

        gap:15px;
    }

    .header > div:last-child
    {
        width:100%;

        justify-content:flex-end !important;
    }

    .hero-greeting h1
    {
        font-size:22px;
    }

    .readiness-footer
    {
        flex-direction:column;

        align-items:flex-start;

        gap:12px;
    }
}

@media(max-width:600px)
{
    .container
    {
        padding:15px;
    }

    .hero-section
    {
        padding:20px;
    }

    .hero-greeting h1
    {
        font-size:18px;
    }

    .hero-stats
    {
        grid-template-columns:1fr;
    }

    .admission-status-card
    {
        padding:20px;
    }

    .readiness-section
    {
        padding:20px;
    }

    .activity-timeline
    {
        padding:20px;
    }
}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <div>

            <div class="title">
                Student Dashboard
            </div>

            <div class="subtitle">
                SMART CAMPUS MANAGEMENT SYSTEM
            </div>

        </div>

        <div style="display: flex; gap: 12px; align-items: center;">

            <!-- Notification Bell -->
            <a href="notification_center.php" style="position: relative; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 9px; border: 1px solid rgba(148,163,184,.14); background: rgba(255,255,255,.025); transition: .2s; color: #cbd5e1; font-size: 16px;" title="Notifications">
                🔔
                <?php if ($unreadNotificationCount > 0): ?>
                <span style="position: absolute; top: -4px; right: -4px; background: linear-gradient(135deg, #3b82f6, #06b6d4); color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; border: 2px solid #070b17;">
                    <?php echo min($unreadNotificationCount, 9); ?>
                </span>
                <?php endif; ?>
            </a>

            <!-- Admission Timeline -->
            <a href="admission_timeline.php" style="padding: 8px 12px; border-radius: 9px; border: 1px solid rgba(148,163,184,.14); background: rgba(255,255,255,.025); text-decoration: none; color: #cbd5e1; font-size: 11px; font-weight: 600; transition: .2s;" title="View Admission Timeline">
                📍 Timeline
            </a>

            <a href="profile_photo.php" style="padding: 8px 12px; border-radius: 9px; border: 1px solid rgba(148,163,184,.14); background: rgba(255,255,255,.025); text-decoration: none; color: #cbd5e1; font-size: 11px; font-weight: 600; transition: .2s;" title="Update your profile photo">
                👤 Profile
            </a>

            <a
                href="logout.php"
                class="logout"
            >
                Logout
            </a>

        </div>

    </div>


    <!-- =========================================================
         NEW FEATURE 1 - PERSONALIZED WELCOME HERO
         ========================================================= -->

    <div class="hero-section">

        <div class="hero-content">

            <div class="hero-greeting">

                <h1>
                    <?php
                        $hour = (int)date('H');
                        $greeting = "Good Evening";
                        
                        if ($hour < 12)
                        {
                            $greeting = "Good Morning";
                        }
                        elseif ($hour < 17)
                        {
                            $greeting = "Good Afternoon";
                        }
                        
                        echo $greeting . ", " . htmlspecialchars($student["name"]) . " 👋";
                    ?>
                </h1>

                <p>
                    Your Smart Campus admission journey is in progress.
                </p>

            </div>

            <div class="hero-stats">

                <div class="hero-stat">

                    <div class="hero-stat-label">
                        Application
                    </div>

                    <div class="hero-stat-value">
                        <?php
                            if ($application)
                            {
                                echo htmlspecialchars($application["application_status"]);
                            }
                            else
                            {
                                echo "Not Submitted";
                            }
                        ?>
                    </div>

                </div>

                <div class="hero-stat">

                    <div class="hero-stat-label">
                        Documents
                    </div>

                    <div class="hero-stat-value">
                        <?php
                            echo $documentsVerified . " / " . $requiredDocCount;
                        ?>
                    </div>

                </div>

                <div class="hero-stat">

                    <div class="hero-stat-label">
                        Admission
                    </div>

                    <div class="hero-stat-value">
                        <?php
                            if ($admission)
                            {
                                echo htmlspecialchars($admission["admission_status"]);
                            }
                            else
                            {
                                echo "Not Started";
                            }
                        ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="profile">

        <div style="display: flex; gap: 30px; align-items: flex-start;">

            <!-- Profile Photo Section -->
            <div style="flex-shrink: 0;">
                <div style="width: 150px; height: 150px; border-radius: 12px; background: rgba(255,255,255,.1); border: 2px solid rgba(148,163,184,.3); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    <?php $studentPhotoPath = get_photo_file_path($student["photo_path"] ?? null); ?>
                    <?php if (!empty($student["photo_path"]) && $studentPhotoPath && file_exists($studentPhotoPath)): ?>
                        <img src="<?php echo htmlspecialchars(get_photo_public_url($student["photo_path"])); ?>?v=<?php echo filemtime($studentPhotoPath); ?>" 
                             alt="Profile Photo" 
                             style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <div style="text-align: center; color: #94a3b8;">
                            <div style="font-size: 48px; margin-bottom: 8px;">👤</div>
                            <div style="font-size: 12px;">No Photo</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Info Section -->
            <div style="flex: 1;">
                <h2>
                    Welcome,
                    <?php
                    echo htmlspecialchars($student["name"]);
                    ?>
                </h2>

                <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <p style="color: #94a3b8; font-size: 12px; margin-bottom: 4px;">
                            Registration Number
                        </p>
                        <p style="margin: 0; color: #e2e8f0; font-weight: 600;">
                            <?php
                            echo htmlspecialchars($student["roll_no"]);
                            ?>
                        </p>
                    </div>

                    <div>
                        <p style="color: #94a3b8; font-size: 12px; margin-bottom: 4px;">
                            Department
                        </p>
                        <p style="margin: 0; color: #e2e8f0; font-weight: 600;">
                            <?php
                            echo htmlspecialchars($student["department"] ?? "N/A");
                            ?>
                        </p>
                    </div>

                    <div>
                        <p style="color: #94a3b8; font-size: 12px; margin-bottom: 4px;">
                            Semester
                        </p>
                        <p style="margin: 0; color: #e2e8f0; font-weight: 600;">
                            <?php
                            echo htmlspecialchars($student["semester"] ?? "N/A");
                            ?>
                        </p>
                    </div>

                    <div>
                        <p style="color: #94a3b8; font-size: 12px; margin-bottom: 4px;">
                            CGPA
                        </p>
                        <p style="margin: 0; color: #e2e8f0; font-weight: 600;">
                            <?php
                            echo htmlspecialchars($student["cgpa"] ?? "N/A");
                            ?>
                        </p>
                    </div>
                </div>
            </div>

        </div>

    </div>


    <!-- Notifications Section -->
    <?php if (count($notifications) > 0): ?>

    <div style="margin-bottom:25px;">

        <h3 style="margin:0 0 15px 0;color:#e2e8f0;font-size:14px;font-weight:600;">
            RECENT NOTIFICATIONS
        </h3>

        <div style="display:grid;gap:12px;">

            <?php foreach ($notifications as $notif): ?>

            <div style="padding:14px 16px;border-radius:10px;background:rgba(13,19,36,.90);border:1px solid rgba(148,163,184,.14);display:flex;gap:12px;align-items:flex-start;">

                <div style="flex:1;">

                    <div style="font-weight:600;color:#e2e8f0;font-size:13px;margin-bottom:4px;">
                        <?php echo htmlspecialchars($notif["title"]); ?>
                    </div>

                    <div style="color:#94a3b8;font-size:12px;line-height:1.5;">
                        <?php echo htmlspecialchars($notif["message"]); ?>
                    </div>

                    <div style="color:#64748b;font-size:10px;margin-top:6px;">
                        <?php 
                        $createdTime = strtotime($notif["created_at"]);
                        $now = time();
                        $diff = $now - $createdTime;
                        
                        if ($diff < 60) {
                            echo "Just now";
                        } elseif ($diff < 3600) {
                            echo floor($diff / 60) . " minutes ago";
                        } elseif ($diff < 86400) {
                            echo floor($diff / 3600) . " hours ago";
                        } else {
                            echo floor($diff / 86400) . " days ago";
                        }
                        ?>
                    </div>

                </div>

                <?php if (!$notif["is_read"]): ?>
                <div style="width:8px;height:8px;background:#3b82f6;border-radius:50%;margin-top:4px;"></div>
                <?php endif; ?>

            </div>

            <?php endforeach; ?>

        </div>

    </div>

    <?php endif; ?>


    <?php if ($attendanceVisible): ?>
        <div class="readiness-section" style="margin-top: 10px;">

            <div class="readiness-header">

                <h3 class="readiness-title">
                    ATTENDANCE SUMMARY
                </h3>

                <div class="readiness-percentage">
                    <?php echo $attendanceSummary["percent"]; ?>%
                </div>

            </div>

            <div class="readiness-progress-bar">
                <div class="readiness-progress-fill" style="width: <?php echo min(100, max(0, $attendanceSummary["percent"])); ?>%;"></div>
            </div>

            <div class="readiness-checklist">

                <div class="readiness-item completed">
                    <div class="readiness-item-icon">✓</div>
                    <div class="readiness-item-text">
                        <?php echo htmlspecialchars($attendanceSummary["present"]); ?> present sessions
                    </div>
                </div>

                <div class="readiness-item pending">
                    <div class="readiness-item-icon">⏳</div>
                    <div class="readiness-item-text">
                        <?php echo htmlspecialchars($attendanceSummary["total"] - $attendanceSummary["present"]); ?> sessions marked absent or late
                    </div>
                </div>

            </div>

            <div class="readiness-footer">

                <div class="readiness-footer-text">
                    <?php echo htmlspecialchars($attendanceSummary["message"]); ?>
                </div>

                <div class="button" style="opacity: 0.95; pointer-events:none; cursor:default;">
                    REQUIRED: 85%
                </div>

            </div>

        </div>
    <?php else: ?>
        <div class="readiness-section" style="margin-top: 10px;">
            <div class="readiness-header">
                <h3 class="readiness-title">ATTENDANCE SUMMARY</h3>
                <div class="readiness-percentage">--</div>
            </div>
            <div class="readiness-footer">
                <div class="readiness-footer-text">Attendance will be visible after admission approval and profile photo upload.</div>
                <div class="button" style="opacity: 0.95; pointer-events:none; cursor:default;">PENDING</div>
            </div>
        </div>
    <?php endif; ?>


    <!-- =========================================================
         NEW FEATURE 3 - LARGE ADMISSION STATUS CARD
         ========================================================= -->

    <?php
        $statusClass = "admission-status-pending";
        $statusIcon = "⏳";
        $statusTitle = "APPLICATION PENDING";
        
        if ($application)
        {
            $appStatus = $application["application_status"];
            
            if ($appStatus === "Accepted")
            {
                $statusClass = "admission-status-accepted";
                $statusIcon = "✓";
                $statusTitle = "APPLICATION ACCEPTED";
            }
            elseif ($appStatus === "Rejected")
            {
                $statusClass = "admission-status-rejected";
                $statusIcon = "✕";
                $statusTitle = "APPLICATION REJECTED";
            }
            elseif ($appStatus === "Processing")
            {
                $statusClass = "admission-status-processing";
                $statusIcon = "◷";
                $statusTitle = "APPLICATION PROCESSING";
            }
        }
        else
        {
            $statusIcon = "📝";
            $statusTitle = "NO APPLICATION SUBMITTED";
        }
    ?>

    <div class="admission-status-card <?php echo $statusClass; ?>">

        <div class="admission-status-icon">
            🎓
        </div>

        <div class="admission-status-content">

            <h2><?php echo $statusIcon; ?> <?php echo $statusTitle; ?></h2>

            <?php if ($application): ?>

                <?php if ($application["application_status"] === "Accepted"): ?>
                    <p>
                        Your application has been reviewed and accepted by the administration.
                    </p>
                <?php elseif ($application["application_status"] === "Rejected"): ?>
                    <p>
                        Unfortunately, your application could not be accepted at this time.
                    </p>
                    <?php if ($application["admin_remarks"]): ?>
                        <p style="color: #fecaca; font-size: 11px;">
                            <strong>Admin Remarks:</strong><br/>
                            <?php echo htmlspecialchars($application["admin_remarks"]); ?>
                        </p>
                    <?php endif; ?>
                <?php elseif ($application["application_status"] === "Processing"): ?>
                    <p>
                        Your application is currently under review by the administration. Please wait for further updates.
                    </p>
                <?php else: ?>
                    <p>
                        Your application is pending review. The administration will evaluate it soon.
                    </p>
                <?php endif; ?>

                <div class="admission-status-detail">
                    <span class="admission-status-label">Application Number:</span>
                    <span class="admission-status-value">
                        <?php echo htmlspecialchars($application["application_number"] ?? "N/A"); ?>
                    </span>
                </div>

                <div class="admission-status-detail">
                    <span class="admission-status-label">Program Type:</span>
                    <span class="admission-status-value">
                        <?php echo htmlspecialchars($application["program_type"] ?? "N/A"); ?>
                    </span>
                </div>

                <div class="admission-status-detail">
                    <span class="admission-status-label">Course Name:</span>
                    <span class="admission-status-value">
                        <?php echo htmlspecialchars($application["course_name"] ?? "N/A"); ?>
                    </span>
                </div>

                <div style="margin-top: 15px;">
                    <a href="student_application.php" class="button">
                        VIEW APPLICATION
                    </a>
                </div>

            <?php else: ?>

                <p>
                    No admission application submitted yet. Complete your application to start the admission process.
                </p>

                <div style="margin-top: 15px;">
                    <a href="student_application.php" class="button">
                        CREATE APPLICATION
                    </a>
                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- =========================================================
         NEW FEATURE 8 - ADMISSION READINESS METER
         ========================================================= -->

    <div class="readiness-section">

        <div class="readiness-header">

            <h3 class="readiness-title">
                ADMISSION READINESS
            </h3>

            <div class="readiness-percentage">
                <?php echo $readinessPercentage; ?>%
            </div>

        </div>

        <div class="readiness-progress-bar">
            <div class="readiness-progress-fill" style="width: <?php echo $readinessPercentage; ?>%;"></div>
        </div>

        <div class="readiness-checklist">

            <?php foreach ($readinessItems as $item): ?>

                <div class="readiness-item <?php echo $item['status']; ?>">

                    <div class="readiness-item-icon">
                        <?php echo ($item['status'] === 'completed') ? '✓' : '⏳'; ?>
                    </div>

                    <div class="readiness-item-text">
                        <?php echo htmlspecialchars($item['text']); ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <div class="readiness-footer">

            <div class="readiness-footer-text">
                <?php echo $readinessPercentage; ?>% ready for final admission
            </div>

            <a href="<?php echo $readinessContinueLink; ?>" class="button">
                CONTINUE ADMISSION
            </a>

        </div>

    </div>


    <!-- =========================================================
         NEW FEATURE 9 - RECENT ACTIVITY TIMELINE
         ========================================================= -->

    <div class="activity-timeline">

        <h3 class="activity-timeline-title">
            RECENT ACTIVITY
        </h3>

        <?php if (!empty($recentActivities)): ?>

            <div class="activity-timeline-list">

                <?php foreach ($recentActivities as $activity): ?>

                    <?php
                        $activityIcon = "●";
                        $activityClass = "activity-item-neutral";
                        $activityType = isset($activity["action_type"]) ? $activity["action_type"] : "";
                        
                        if (strpos($activityType, "accepted") !== false || strpos($activity["title"], "accepted") !== false)
                        {
                            $activityIcon = "✓";
                            $activityClass = "activity-item-success";
                        }
                        elseif (strpos($activityType, "verified") !== false || strpos($activity["title"], "verified") !== false)
                        {
                            $activityIcon = "✓";
                            $activityClass = "activity-item-success";
                        }
                        elseif (strpos($activityType, "rejected") !== false || strpos($activity["title"], "rejected") !== false)
                        {
                            $activityIcon = "✕";
                            $activityClass = "activity-item-rejected";
                        }
                        elseif (strpos($activityType, "processing") !== false || strpos($activityType, "pending") !== false || strpos($activity["title"], "pending") !== false)
                        {
                            $activityIcon = "⏳";
                            $activityClass = "activity-item-pending";
                        }
                        elseif (strpos($activityType, "submitted") !== false || strpos($activity["title"], "submitted") !== false || strpos($activity["title"], "uploaded") !== false)
                        {
                            $activityIcon = "📝";
                            $activityClass = "activity-item-neutral";
                        }
                    ?>

                    <div class="activity-item <?php echo $activityClass; ?>">

                        <div class="activity-item-icon">
                            <?php echo $activityIcon; ?>
                        </div>

                        <div class="activity-item-content">

                            <div class="activity-item-title">
                                <?php 
                                    $title = isset($activity["title"]) ? $activity["title"] : "";
                                    echo htmlspecialchars($title);
                                ?>
                            </div>

                            <div class="activity-item-time">
                                <?php 
                                    $timestamp = strtotime($activity["created_at"]);
                                    $dateStr = date('d M Y', $timestamp);
                                    $timeStr = date('h:i A', $timestamp);
                                    echo $dateStr . " • " . $timeStr;
                                ?>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="activity-empty">
                No recent activity yet.
            </div>

        <?php endif; ?>

    </div>


    <!-- EXISTING CARDS GRID -->

    <div class="grid">


        <div class="card">

            <div class="card-icon">
                📝
            </div>

    <?php if ($documentsEnabled) { ?>

    <div class="verification-banner" style="padding:16px 18px;border-radius:13px;margin-bottom:20px;background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.16);color:#bbf7d0;">
        <strong>✓ Application Accepted &amp; Verified</strong>
        <div style="margin-top:5px;color:#86efac;font-size:11px;">
            Your application has been verified by the administration. Your required <?php echo htmlspecialchars($application["program_type"]); ?> document submission is now available.
        </div>
    </div>

    <?php } elseif ($application && $application["application_status"] === "Rejected") { ?>

    <div style="padding:16px 18px;border-radius:13px;margin-bottom:20px;background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.16);color:#fecaca;">
        <strong>✕ Application Rejected</strong>
        <div style="margin-top:5px;font-size:11px;">
            <?php echo htmlspecialchars($application["admin_remarks"] ?? "Please contact the administration."); ?>
        </div>
    </div>

    <?php } elseif ($application) { ?>

    <div style="padding:16px 18px;border-radius:13px;margin-bottom:20px;background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.16);color:#fbbf24;">
        <strong>◷ Application Under Review</strong>
        <div style="margin-top:5px;font-size:11px;">
            Document submission will automatically unlock after the administration accepts and verifies your application.
        </div>
    </div>

    <?php } ?>

            <h3>
                Admission Application
            </h3>

            <p>
                Complete your admission
                application and submit it
                to the administration.
            </p>

            <a
                href="student_application.php"
                class="button"
            >
                Open Application
            </a>

        </div>


        <div class="card">

            <div class="card-icon">
                📄
            </div>

            <h3>
                Document Submission
            </h3>

            <p>
                Upload your required
                academic documents and
                monitor verification.
            </p>

            <?php if ($documentsEnabled) { ?>

            <a
                href="student_documents.php"
                class="button"
            >
                Submit Documents
            </a>

            <?php } else { ?>

            <span
                class="status"
                style="margin-top:8px;display:inline-block;"
            >
                🔒 Locked — Verify Application First
            </span>

            <?php } ?>

        </div>


        <div class="card">

            <div class="card-icon">
                ✓
            </div>

            <h3>
                Application Status
            </h3>

            <p>

            <?php

            if ($application)
            {
                if ($documentsEnabled)
                {
                    echo "Application Accepted & Verified";
                }
                else
                {
                    echo "Application: ";
                    echo htmlspecialchars(
                        $application["application_status"]
                    );
                }
            }
            else
            {
                echo "No application submitted yet.";
            }

            ?>

            </p>

        </div>


        <div class="card">

            <div class="card-icon">
                🔐
            </div>

            <h3>
                Document Verification
            </h3>

            <p>

                <?php

                echo $totalDocuments;
                echo " / ";
                echo $requiredDocCount;

                ?>

                documents submitted.

            </p>

            <span class="status">

                <?php

                if (!$documentsEnabled)
                {
                    echo "Locked until application verification";
                }
                elseif (
                    $requiredDocCount > 0 &&
                    $totalDocuments >= $requiredDocCount &&
                    $documentsVerified >= $requiredDocCount
                )
                {
                    echo "Submission Complete";
                }
                else
                {
                    echo "Submission Pending";
                }

                ?>

            </span>

        </div>


        <div class="card">

            <div class="card-icon">
                🎓
            </div>

            <h3>
                Admission Processing
            </h3>

            <p>

            <?php

            if ($admission)
            {
                echo htmlspecialchars(
                    $admission["admission_status"]
                );
            }
            else
            {
                echo "Admission processing has not started.";
            }

            ?>

            </p>

        </div>


        <div class="card">

            <div class="card-icon">
                🪪
            </div>

            <h3>
                Registration Number
            </h3>

            <p>
                Your registered student
                identification number.
            </p>

            <strong>

                <?php
                echo htmlspecialchars(
                    $student["roll_no"]
                );
                ?>

            </strong>

        </div>


    </div>

    <!-- =========================================================
         ACADEMIC SECTION - MARKS & GPA
         ========================================================= -->

    <?php if ($isAdmittedForAcademics): ?>

    <div class="academic-section">

        <div class="academic-header">

            <h3 class="academic-title">
                ACADEMIC PERFORMANCE
            </h3>

            <div class="academic-cgpa">
                CGPA: <?php echo number_format($overallCGPA, 2); ?>
            </div>

        </div>

        <div class="academic-grid">

            <div class="academic-stat">
                <div class="academic-stat-label">Completed</div>
                <div class="academic-stat-value"><?php echo $completedSemesters; ?>/<?php echo $totalSemestersExpected; ?></div>
            </div>

            <div class="academic-stat">
                <div class="academic-stat-label">Program</div>
                <div class="academic-stat-value"><?php echo htmlspecialchars($programTypeForAcademics); ?></div>
            </div>

            <div class="academic-stat">
                <div class="academic-stat-label">Current Sem</div>
                <div class="academic-stat-value"><?php echo $completedSemesters + 1; ?></div>
            </div>

            <div class="academic-stat">
                <div class="academic-stat-label">Overall GPA</div>
                <div class="academic-stat-value"><?php echo number_format($overallCGPA, 2); ?></div>
            </div>

        </div>

        <div class="semester-cards">
            <?php foreach ($semesterGPAs as $semName => $gpa): ?>
                <div class="semester-card">
                    <div class="semester-card-title"><?php echo htmlspecialchars($semName); ?></div>
                    <div class="semester-card-gpa">GPA: <?php echo number_format($gpa, 2); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div>
            <a href="student_marks.php" class="marks-button">
                VIEW DETAILED MARKS
            </a>
        </div>

    </div>

    <?php else: ?>

    <div class="academic-section">

        <div class="academic-header">
            <h3 class="academic-title">ACADEMIC PERFORMANCE</h3>
        </div>

        <div class="academic-locked">
            🔒 Academic records will become available after your admission is successfully completed.
        </div>

    </div>

    <?php endif; ?>

</div>

</body>
</html>
