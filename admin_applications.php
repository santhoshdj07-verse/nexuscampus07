<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";
require_once "config/email_notifications.php";

$message = "";
$messageType = "";

$adminName = "Administrator";

if (isset($_SESSION["admin_name"]) && $_SESSION["admin_name"] != "")
{
    $adminName = $_SESSION["admin_name"];
}

$currentDate = date("l, d F Y");

/* =========================================================
   ACCEPT / VERIFY APPLICATION
   ========================================================= */

if (isset($_POST["accept_application"]))
{
    $applicationId = isset($_POST["application_id"]) ? (int)$_POST["application_id"] : 0;
    $remarks = trim($_POST["admin_remarks"] ?? "");

    if ($applicationId <= 0)
    {
        $message = "Invalid application selected.";
        $messageType = "error";
    }
    else
    {
        if ($remarks == "")
        {
            $remarks = "Application accepted and verified by administrator.";
        }

        $previousStatus = "";
        $statusKnown = false;
        $statusCheck = $conn->prepare("SELECT application_status FROM student_applications WHERE id = ? LIMIT 1");
        if ($statusCheck) {
            $statusCheck->bind_param("i", $applicationId);
            $statusCheck->execute();
            $statusRow = $statusCheck->get_result()->fetch_assoc();
            $previousStatus = $statusRow["application_status"] ?? "";
            $statusKnown = $statusRow !== null;
            $statusCheck->close();
        }

        $stmt = $conn->prepare(
            "UPDATE student_applications
             SET application_status = 'Accepted',
                 admin_remarks = ?,
                 verified_at = NOW(),
                 verified_by = ?
             WHERE id = ?"
        );

        if ($stmt)
        {
            $adminId = (int)$_SESSION["admin_id"];
            $stmt->bind_param("sii", $remarks, $adminId, $applicationId);

            if ($stmt->execute())
            {
                /* Get student_id for notification */
                $studentId = 0;
                $studentEmail = "";
                $studentName = "Student";
                $getStudentStmt = $conn->prepare(
                    "SELECT sa.student_id, sa.application_number, s.name, s.email
                     FROM student_applications sa
                     JOIN students s ON s.id = sa.student_id
                     WHERE sa.id = ? LIMIT 1"
                );
                
                if ($getStudentStmt)
                {
                    $getStudentStmt->bind_param("i", $applicationId);
                    $getStudentStmt->execute();
                    $studentResult = $getStudentStmt->get_result();
                    $studentRow = $studentResult ? $studentResult->fetch_assoc() : null;
                    
                    if ($studentRow)
                    {
                        $studentId = (int)$studentRow["student_id"];
                        $studentEmail = $studentRow["email"] ?? "";
                        $studentName = $studentRow["name"] ?? "Student";
                    }
                    
                    $getStudentStmt->close();
                }
                
                /* Notify only when the application status actually changes. */
                if ($studentId > 0 && $statusKnown && $previousStatus !== "Accepted")
                {
                    cms_create_notification(
                        $conn,
                        $studentId,
                        "Application Accepted",
                        "Your admission application has been accepted by the administration. You can now submit your required documents.",
                        "APPLICATION_ACCEPTED",
                        $applicationId,
                        null
                    );

                    cms_send_status_email(
                        $studentEmail,
                        "Admission application accepted",
                        "Hello " . $studentName . ",\n\nYour admission application " .
                        ($studentRow["application_number"] ?? "") .
                        " has been accepted. You can now submit your required documents."
                    );
                }

                cms_log_activity(
                    $conn,
                    "admin",
                    (int)$_SESSION["admin_id"],
                    $_SESSION["admin_name"] ?? "Administrator",
                    "application_accepted",
                    "Application Accepted",
                    "Admin accepted application #" . $applicationId . " for student ID " . $studentId,
                    "student_applications",
                    $applicationId
                );
                
                /* Create admission-processing record once. */
                $check = $conn->prepare(
                    "SELECT student_id
                     FROM student_applications
                     WHERE id = ?
                     LIMIT 1"
                );

                if ($check)
                {
                    $check->bind_param("i", $applicationId);
                    $check->execute();
                    $appResult = $check->get_result();
                    $appRow = $appResult ? $appResult->fetch_assoc() : null;
                    $check->close();

                    if ($appRow)
                    {
                        $studentIdForAdmission = (int)$appRow["student_id"];

                        $insert = $conn->prepare(
                            "INSERT INTO admission_processing
                             (student_id, application_id, admission_status)
                             SELECT ?, ?, 'Pending'
                             WHERE NOT EXISTS
                             (SELECT id FROM admission_processing WHERE application_id = ?)"
                        );

                        if ($insert)
                        {
                            $insert->bind_param(
                                "iii",
                                $studentIdForAdmission,
                                $applicationId,
                                $applicationId
                            );
                            $insert->execute();
                            $insert->close();
                        }
                    }
                }

                $message = "Application accepted and verified successfully. The student can now submit documents.";
                $messageType = "success";
            }
            else
            {
                $message = "Unable to verify application: " . $stmt->error;
                $messageType = "error";
            }

            $stmt->close();
        }
        else
        {
            $message = "Unable to prepare application verification.";
            $messageType = "error";
        }
    }
}

/* =========================================================
   REJECT APPLICATION
   ========================================================= */

if (isset($_POST["reject_application"]))
{
    $applicationId = isset($_POST["application_id"]) ? (int)$_POST["application_id"] : 0;
    $remarks = trim($_POST["admin_remarks"] ?? "");

    if ($remarks == "")
    {
        $remarks = "Application rejected by administrator.";
    }

    if ($applicationId > 0)
    {
        $previousStatus = "";
        $statusKnown = false;
        $statusCheck = $conn->prepare("SELECT application_status FROM student_applications WHERE id = ? LIMIT 1");
        if ($statusCheck) {
            $statusCheck->bind_param("i", $applicationId);
            $statusCheck->execute();
            $statusRow = $statusCheck->get_result()->fetch_assoc();
            $previousStatus = $statusRow["application_status"] ?? "";
            $statusKnown = $statusRow !== null;
            $statusCheck->close();
        }

        $stmt = $conn->prepare(
            "UPDATE student_applications
             SET application_status = 'Rejected',
                 admin_remarks = ?,
                 verified_at = NULL,
                 verified_by = NULL
             WHERE id = ?"
        );

        if ($stmt)
        {
            $stmt->bind_param("si", $remarks, $applicationId);

            if ($stmt->execute())
            {
                /* Get student_id for notification */
                $studentId = 0;
                $studentEmail = "";
                $studentName = "Student";
                $getStudentStmt = $conn->prepare(
                    "SELECT sa.student_id, sa.application_number, s.name, s.email
                     FROM student_applications sa
                     JOIN students s ON s.id = sa.student_id
                     WHERE sa.id = ? LIMIT 1"
                );
                
                if ($getStudentStmt)
                {
                    $getStudentStmt->bind_param("i", $applicationId);
                    $getStudentStmt->execute();
                    $studentResult = $getStudentStmt->get_result();
                    $studentRow = $studentResult ? $studentResult->fetch_assoc() : null;
                    
                    if ($studentRow)
                    {
                        $studentId = (int)$studentRow["student_id"];
                        $studentEmail = $studentRow["email"] ?? "";
                        $studentName = $studentRow["name"] ?? "Student";
                    }
                    
                    $getStudentStmt->close();
                }
                
                /* Notify only when the application status actually changes. */
                if ($studentId > 0 && $statusKnown && $previousStatus !== "Rejected")
                {
                    cms_create_notification(
                        $conn,
                        $studentId,
                        "Application Rejected",
                        "Your admission application has been rejected. Reason: " . htmlspecialchars($remarks),
                        "APPLICATION_REJECTED",
                        $applicationId,
                        null
                    );

                    cms_send_status_email(
                        $studentEmail,
                        "Admission application update",
                        "Hello " . $studentName . ",\n\nYour admission application " .
                        ($studentRow["application_number"] ?? "") .
                        " was not accepted. Administrator remarks: " . $remarks
                    );
                }

                cms_log_activity(
                    $conn,
                    "admin",
                    (int)$_SESSION["admin_id"],
                    $_SESSION["admin_name"] ?? "Administrator",
                    "application_rejected",
                    "Application Rejected",
                    "Admin rejected application #" . $applicationId . ". Reason: " . $remarks,
                    "student_applications",
                    $applicationId
                );
                
                $message = "Application rejected.";
                $messageType = "error";
            }
            else
            {
                $message = "Unable to reject application.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }
}

/* =========================================================
   RESET APPLICATION TO PENDING
   ========================================================= */

if (isset($_POST["reset_application"]))
{
    $applicationId = isset($_POST["application_id"]) ? (int)$_POST["application_id"] : 0;

    if ($applicationId > 0)
    {
        $stmt = $conn->prepare(
            "UPDATE student_applications
             SET application_status = 'Pending',
                 admin_remarks = '',
                 verified_at = NULL,
                 verified_by = NULL
             WHERE id = ?"
        );

        if ($stmt)
        {
            $stmt->bind_param("i", $applicationId);

            if ($stmt->execute())
            {
                $message = "Application moved back to pending.";
                $messageType = "success";
            }
            else
            {
                $message = "Unable to update application.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }
}

/* =========================================================
   SEARCH
   ========================================================= */

$search = "";

if (isset($_GET["search"]))
{
    $search = trim($_GET["search"]);
}

/* =========================================================
   APPLICATION COUNTS
   ========================================================= */

$totalApplications = 0;
$pendingApplications = 0;
$acceptedApplications = 0;
$rejectedApplications = 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM student_applications");
if ($result) { $row = $result->fetch_assoc(); $totalApplications = (int)$row["total"]; }

$result = $conn->query("SELECT COUNT(*) AS total FROM student_applications WHERE application_status = 'Pending'");
if ($result) { $row = $result->fetch_assoc(); $pendingApplications = (int)$row["total"]; }

$result = $conn->query("SELECT COUNT(*) AS total FROM student_applications WHERE application_status = 'Accepted'");
if ($result) { $row = $result->fetch_assoc(); $acceptedApplications = (int)$row["total"]; }

$result = $conn->query("SELECT COUNT(*) AS total FROM student_applications WHERE application_status = 'Rejected'");
if ($result) { $row = $result->fetch_assoc(); $rejectedApplications = (int)$row["total"]; }

/* =========================================================
   GET APPLICATIONS
   Uses the real student registration account through student_id.
   ========================================================= */

$applications = array();

$baseSql =
    "SELECT
        sa.id,
        sa.student_id,
        sa.application_number,
        sa.program_type,
        sa.course_name,
        sa.previous_qualification,
        sa.previous_institution,
        sa.previous_percentage,
        sa.address,
        sa.city,
        sa.state,
        sa.pincode,
        sa.application_status,
        sa.admin_remarks,
        sa.verified_at,
        sa.verified_by,
        sa.created_at AS submitted_at,
        s.name AS student_name,
        s.roll_no,
        s.department,
        s.semester,
        s.phone,
        s.cgpa
     FROM student_applications sa
     INNER JOIN students s ON s.id = sa.student_id";

if ($search != "")
{
    $searchValue = "%" . $search . "%";

    $stmt = $conn->prepare(
        $baseSql .
        " WHERE s.name LIKE ?
            OR s.roll_no LIKE ?
            OR sa.course_name LIKE ?
            OR s.department LIKE ?
            OR sa.application_number LIKE ?
         ORDER BY sa.id DESC"
    );

    if ($stmt)
    {
        $stmt->bind_param(
            "sssss",
            $searchValue,
            $searchValue,
            $searchValue,
            $searchValue,
            $searchValue
        );

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc())
        {
            $applications[] = $row;
        }

        $stmt->close();
    }
}
else
{
    $result = $conn->query(
        $baseSql . " ORDER BY sa.id DESC"
    );

    if ($result)
    {
        while ($row = $result->fetch_assoc())
        {
            $applications[] = $row;
        }
    }
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Applications | Smart Campus Management System</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <style>

        /* =================================================
           GLOBAL
           ================================================= */

        * {
            box-sizing: border-box;
        }

        :root
        {
            --bg: #070b17;
            --panel: #0d1324;
            --panel-light: #111a2e;
            --border: rgba(148,163,184,0.14);
            --text: #f8fafc;
            --muted: #94a3b8;
            --blue: #3b82f6;
            --cyan: #06b6d4;
            --purple: #8b5cf6;
            --green: #22c55e;
            --red: #ef4444;
            --yellow: #f59e0b;
        }


        body
        {
            margin: 0;

            font-family:
                Inter,
                Segoe UI,
                Arial,
                sans-serif;

            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(59,130,246,0.13),
                    transparent 30%
                ),

                radial-gradient(
                    circle at 90% 15%,
                    rgba(139,92,246,0.12),
                    transparent 30%
                ),

                radial-gradient(
                    circle at 70% 90%,
                    rgba(6,182,212,0.08),
                    transparent 30%
                ),

                #070b17;

            color: var(--text);

            min-height: 100vh;
        }


        body::before
        {
            content: "";

            position: fixed;

            inset: 0;

            pointer-events: none;

            opacity: 0.18;

            background-image:
                linear-gradient(
                    rgba(255,255,255,0.025) 1px,
                    transparent 1px
                ),

                linear-gradient(
                    90deg,
                    rgba(255,255,255,0.025) 1px,
                    transparent 1px
                );

            background-size: 40px 40px;
        }


        /* =================================================
           APPLICATION
           ================================================= */

        .app
        {
            display: flex;

            min-height: 100vh;

            position: relative;

            z-index: 1;
        }


        /* =================================================
           SIDEBAR
           ================================================= */

        .sidebar
        {
            width: 255px;

            min-height: 100vh;

            position: fixed;

            left: 0;

            top: 0;

            bottom: 0;

            background:
                rgba(7,11,23,0.88);

            backdrop-filter: blur(18px);

            border-right:
                1px solid var(--border);

            padding: 24px 16px;

            display: flex;

            flex-direction: column;
        }


        /* =================================================
           BRAND
           ================================================= */

        .brand
        {
            display: flex;

            align-items: center;

            gap: 12px;

            padding: 8px 10px 28px;
        }


        .brand-icon
        {
            width: 43px;

            height: 43px;

            border-radius: 13px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

            font-weight: bold;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #06b6d4
                );

            box-shadow:
                0 0 25px rgba(37,99,235,0.35);
        }


        .brand-text strong
        {
            display: block;

            font-size: 15px;

            letter-spacing: 0.5px;
        }


        .brand-text span
        {
            display: block;

            color: var(--muted);

            font-size: 11px;

            margin-top: 3px;
        }


        /* =================================================
           NAVIGATION
           ================================================= */

        .nav-title
        {
            color: #64748b;

            font-size: 10px;

            font-weight: bold;

            letter-spacing: 1.5px;

            padding: 12px 12px 8px;
        }


        .nav
        {
            display: flex;

            flex-direction: column;

            gap: 5px;
        }


        .nav a
        {
            text-decoration: none;

            color: #94a3b8;

            padding: 12px 13px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            gap: 12px;

            font-size: 13px;

            transition:
                background 0.2s,
                color 0.2s,
                transform 0.2s;
        }


        .nav a:hover
        {
            color: white;

            background:
                rgba(59,130,246,0.10);

            transform: translateX(3px);
        }


        .nav a.active
        {
            color: white;

            background:
                linear-gradient(
                    90deg,
                    rgba(59,130,246,0.22),
                    rgba(59,130,246,0.06)
                );

            border:
                1px solid rgba(59,130,246,0.18);

            box-shadow:
                inset 3px 0 0 #3b82f6;
        }


        .nav-icon
        {
            width: 22px;

            text-align: center;

            font-size: 16px;
        }


        /* =================================================
           SIDEBAR BOTTOM
           ================================================= */

        .sidebar-bottom
        {
            margin-top: auto;
        }


        .system-status
        {
            padding: 13px;

            border-radius: 12px;

            background:
                rgba(34,197,94,0.06);

            border:
                1px solid rgba(34,197,94,0.14);

            margin-bottom: 12px;
        }


        .status-row
        {
            display: flex;

            align-items: center;

            gap: 8px;

            font-size: 11px;

            color: #a7f3d0;
        }


        .status-dot
        {
            width: 7px;

            height: 7px;

            border-radius: 50%;

            background: #22c55e;

            box-shadow:
                0 0 10px rgba(34,197,94,0.8);
        }


        .logout
        {
            text-decoration: none;

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 12px;

            border-radius: 10px;

            color: #fca5a5;

            background:
                rgba(239,68,68,0.06);

            border:
                1px solid rgba(239,68,68,0.10);

            font-size: 13px;

            transition: 0.2s;
        }


        .logout:hover
        {
            background:
                rgba(239,68,68,0.13);

            color: white;
        }


        /* =================================================
           MAIN
           ================================================= */

        .main
        {
            margin-left: 255px;

            width: calc(100% - 255px);

            padding: 28px 34px 40px;
        }


        /* =================================================
           TOPBAR
           ================================================= */

        .topbar
        {
            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 30px;
        }


        .page-label
        {
            color: #64748b;

            font-size: 12px;

            margin-bottom: 5px;
        }


        .page-title
        {
            margin: 0;

            font-size: 27px;

            letter-spacing: -0.7px;
        }


        .top-right
        {
            display: flex;

            align-items: center;

            gap: 15px;
        }


        .date
        {
            color: var(--muted);

            font-size: 12px;

            padding: 9px 13px;

            border:
                1px solid var(--border);

            border-radius: 9px;

            background:
                rgba(255,255,255,0.025);
        }


        .profile
        {
            display: flex;

            align-items: center;

            gap: 10px;

            padding: 6px 10px 6px 6px;

            border:
                1px solid var(--border);

            border-radius: 12px;

            background:
                rgba(255,255,255,0.025);
        }


        .avatar
        {
            width: 34px;

            height: 34px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;

            background:
                linear-gradient(
                    135deg,
                    #8b5cf6,
                    #3b82f6
                );
        }


        .profile-name
        {
            font-size: 12px;

            font-weight: bold;
        }


        .profile-role
        {
            color: #64748b;

            font-size: 10px;

            margin-top: 2px;
        }


        /* =================================================
           HERO
           ================================================= */

        .hero
        {
            position: relative;

            overflow: hidden;

            border-radius: 18px;

            padding: 27px 30px;

            margin-bottom: 22px;

            background:
                linear-gradient(
                    115deg,
                    rgba(37,99,235,0.20),
                    rgba(8,47,73,0.17),
                    rgba(139,92,246,0.15)
                );

            border:
                1px solid rgba(96,165,250,0.16);

            box-shadow:
                0 20px 50px rgba(0,0,0,0.20);
        }


        .hero::after
        {
            content: "";

            position: absolute;

            width: 260px;

            height: 260px;

            right: -100px;

            top: -140px;

            border-radius: 50%;

            background:
                rgba(59,130,246,0.18);

            filter: blur(20px);
        }


        .hero h2
        {
            margin: 0 0 8px;

            font-size: 23px;
        }


        .hero p
        {
            margin: 0;

            color: #a8b5c8;

            font-size: 13px;

            max-width: 700px;

            line-height: 1.6;
        }


        /* =================================================
           STATISTICS
           ================================================= */

        .stats
        {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 16px;

            margin-bottom: 22px;
        }


        .stat
        {
            background:
                rgba(13,19,36,0.86);

            border:
                1px solid var(--border);

            border-radius: 15px;

            padding: 19px;

            transition:
                transform 0.2s,
                border 0.2s;
        }


        .stat:hover
        {
            transform: translateY(-4px);

            border-color:
                rgba(96,165,250,0.25);
        }


        .stat-top
        {
            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .stat-label
        {
            color: var(--muted);

            font-size: 11px;

            font-weight: 600;
        }


        .stat-icon
        {
            width: 34px;

            height: 34px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 14px;
        }


        .blue-icon
        {
            background: rgba(59,130,246,0.12);

            color: #60a5fa;
        }


        .purple-icon
        {
            background: rgba(139,92,246,0.12);

            color: #a78bfa;
        }


        .green-icon
        {
            background: rgba(34,197,94,0.12);

            color: #4ade80;
        }


        .yellow-icon
        {
            background: rgba(245,158,11,0.12);

            color: #fbbf24;
        }


        .stat-value
        {
            font-size: 27px;

            font-weight: 750;

            margin-top: 15px;
        }


        .stat-meta
        {
            margin-top: 6px;

            color: #64748b;

            font-size: 9px;
        }


        /* =================================================
           PANEL
           ================================================= */

        .panel
        {
            background:
                rgba(13,19,36,0.86);

            border:
                1px solid var(--border);

            border-radius: 15px;

            overflow: hidden;
        }


        .panel-header
        {
            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 19px 20px;

            border-bottom:
                1px solid var(--border);
        }


        .panel-header h3
        {
            margin: 0;

            font-size: 14px;
        }


        .panel-header span
        {
            color: #64748b;

            font-size: 10px;
        }


        /* =================================================
           MESSAGE
           ================================================= */

        .message
        {
            padding: 13px 16px;

            border-radius: 10px;

            margin-bottom: 18px;

            font-size: 12px;
        }


        .message.success
        {
            color: #86efac;

            background:
                rgba(34,197,94,0.08);

            border:
                1px solid rgba(34,197,94,0.18);
        }


        .message.error
        {
            color: #fca5a5;

            background:
                rgba(239,68,68,0.08);

            border:
                1px solid rgba(239,68,68,0.18);
        }


        /* =================================================
           SEARCH
           ================================================= */

        .search-area
        {
            padding: 18px 20px;

            border-bottom:
                1px solid var(--border);

            display: flex;

            gap: 10px;
        }


        .search-input
        {
            flex: 1;

            background:
                rgba(255,255,255,0.035);

            border:
                1px solid rgba(148,163,184,0.15);

            color: white;

            border-radius: 9px;

            padding: 11px 13px;

            outline: none;

            font-size: 12px;
        }


        .search-input:focus
        {
            border-color:
                rgba(59,130,246,0.45);

            box-shadow:
                0 0 15px rgba(59,130,246,0.08);
        }


        .search-btn
        {
            border: none;

            border-radius: 9px;

            padding: 0 18px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #06b6d4
                );

            color: white;

            font-weight: 600;

            cursor: pointer;
        }


        .clear-btn
        {
            display: flex;

            align-items: center;

            text-decoration: none;

            padding: 0 15px;

            border-radius: 9px;

            color: #94a3b8;

            border:
                1px solid var(--border);

            background:
                rgba(255,255,255,0.025);

            font-size: 11px;
        }


        /* =================================================
           TABLE
           ================================================= */

        .table-container
        {
            width: 100%;

            overflow-x: auto;
        }


        table
        {
            width: 100%;

            border-collapse: collapse;

            min-width: 1000px;
        }


        th
        {
            text-align: left;

            padding: 13px 16px;

            color: #64748b;

            font-size: 10px;

            letter-spacing: 0.7px;

            border-bottom:
                1px solid var(--border);

            background:
                rgba(255,255,255,0.015);
        }


        td
        {
            padding: 15px 16px;

            border-bottom:
                1px solid rgba(148,163,184,0.07);

            font-size: 11px;

            color: #cbd5e1;

            vertical-align: middle;
        }


        tr:hover td
        {
            background:
                rgba(59,130,246,0.025);
        }


        .student-name
        {
            color: white;

            font-weight: 600;
        }


        .sub-text
        {
            color: #64748b;

            font-size: 9px;

            margin-top: 3px;
        }


        /* =================================================
           STATUS
           ================================================= */

        .status
        {
            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 9px;

            font-weight: 600;
        }


        .status::before
        {
            content: "";

            width: 6px;

            height: 6px;

            border-radius: 50%;
        }


        .status.pending
        {
            color: #fcd34d;

            background:
                rgba(245,158,11,0.09);
        }


        .status.pending::before
        {
            background: #f59e0b;
        }


        .status.accepted
        {
            color: #86efac;

            background:
                rgba(34,197,94,0.09);
        }


        .status.accepted::before
        {
            background: #22c55e;
        }


        .status.rejected
        {
            color: #fca5a5;

            background:
                rgba(239,68,68,0.09);
        }


        .status.rejected::before
        {
            background: #ef4444;
        }


        /* =================================================
           ACTION BUTTONS
           ================================================= */

        .action-area
        {
            display: flex;

            gap: 7px;

            flex-wrap: wrap;
        }


        .btn
        {
            border: none;

            border-radius: 7px;

            padding: 7px 10px;

            font-size: 9px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.2s;
        }


        .btn:hover
        {
            transform: translateY(-1px);
        }


        .btn-accept
        {
            color: #86efac;

            background:
                rgba(34,197,94,0.10);

            border:
                1px solid rgba(34,197,94,0.16);
        }


        .btn-accept:hover
        {
            background:
                rgba(34,197,94,0.18);
        }


        .btn-reject
        {
            color: #fca5a5;

            background:
                rgba(239,68,68,0.10);

            border:
                1px solid rgba(239,68,68,0.16);
        }


        .btn-reject:hover
        {
            background:
                rgba(239,68,68,0.18);
        }


        .btn-reset
        {
            color: #93c5fd;

            background:
                rgba(59,130,246,0.10);

            border:
                1px solid rgba(59,130,246,0.16);
        }


        /* =================================================
           EMPTY
           ================================================= */

        .empty
        {
            padding: 60px 20px;

            text-align: center;

            color: #64748b;
        }


        .empty-icon
        {
            font-size: 35px;

            margin-bottom: 12px;
        }


        .empty strong
        {
            display: block;

            color: #94a3b8;

            font-size: 13px;

            margin-bottom: 5px;
        }


        .empty span
        {
            font-size: 10px;
        }


        /* =================================================
           REMARK MODAL
           ================================================= */

        .modal
        {
            display: none;

            position: fixed;

            inset: 0;

            background:
                rgba(0,0,0,0.72);

            backdrop-filter: blur(6px);

            align-items: center;

            justify-content: center;

            z-index: 100;
        }


        .modal-box
        {
            width: 440px;

            max-width: 90%;

            background:
                #0d1324;

            border:
                1px solid rgba(96,165,250,0.18);

            border-radius: 15px;

            padding: 24px;

            box-shadow:
                0 25px 70px rgba(0,0,0,0.45);
        }


        .modal-box h3
        {
            margin: 0 0 8px;

            font-size: 17px;
        }


        .modal-box p
        {
            color: #64748b;

            font-size: 11px;

            margin-bottom: 18px;
        }


        .remarks
        {
            width: 100%;

            min-height: 110px;

            resize: vertical;

            background:
                rgba(255,255,255,0.035);

            border:
                1px solid rgba(148,163,184,0.15);

            color: white;

            border-radius: 9px;

            padding: 12px;

            outline: none;

            font-family: inherit;

            font-size: 11px;
        }


        .modal-actions
        {
            display: flex;

            justify-content: flex-end;

            gap: 8px;

            margin-top: 15px;
        }


        .cancel-btn
        {
            padding: 9px 15px;

            border-radius: 8px;

            border:
                1px solid var(--border);

            background:
                rgba(255,255,255,0.03);

            color: #94a3b8;

            cursor: pointer;
        }


        .confirm-reject
        {
            padding: 9px 15px;

            border-radius: 8px;

            border: none;

            background:
                #ef4444;

            color: white;

            cursor: pointer;

            font-weight: 600;
        }


        .btn-view
        {
            color:#c4b5fd;
            background:rgba(139,92,246,.10);
            border:1px solid rgba(139,92,246,.18);
        }

        .view-modal-box
        {
            width:760px;
            max-height:85vh;
            overflow:auto;
        }

        .view-grid
        {
            display:grid;
            grid-template-columns:repeat(2,1fr);
            gap:10px;
            margin-top:15px;
        }

        .view-grid > div
        {
            padding:11px;
            border:1px solid rgba(148,163,184,.09);
            border-radius:9px;
            background:rgba(255,255,255,.025);
        }

        .view-grid .view-full
        {
            grid-column:1 / -1;
        }

        .view-grid span
        {
            display:block;
            color:#64748b;
            font-size:9px;
            margin-bottom:5px;
        }

        .view-grid strong
        {
            color:#e2e8f0;
            font-size:11px;
            line-height:1.5;
        }

        /* =================================================
           FOOTER
           ================================================= */

        .footer
        {
            text-align: center;

            padding: 28px 0 5px;

            color: #475569;

            font-size: 10px;

            letter-spacing: 0.3px;
        }


        /* =================================================
           MOBILE
           ================================================= */

        @media (max-width: 1100px)
        {
            .stats
            {
                grid-template-columns:
                    repeat(2, 1fr);
            }
        }


        @media (max-width: 750px)
        {
            .sidebar
            {
                position: relative;

                width: 100%;

                min-height: auto;

                border-right: none;

                border-bottom:
                    1px solid var(--border);
            }


            .app
            {
                display: block;
            }


            .main
            {
                margin-left: 0;

                width: 100%;

                padding: 18px;
            }


            .topbar
            {
                align-items: flex-start;

                gap: 15px;

                flex-direction: column;
            }


            .top-right
            {
                width: 100%;

                justify-content: space-between;
            }


            .stats
            {
                grid-template-columns: 1fr;
            }


            .nav
            {
                display: grid;

                grid-template-columns:
                    repeat(2, 1fr);
            }
        }


        @media (max-width: 480px)
        {
            .nav
            {
                grid-template-columns: 1fr;
            }


            .date
            {
                display: none;
            }


            .search-area
            {
                flex-direction: column;
            }


            .search-btn,
            .clear-btn
            {
                padding: 11px;

                justify-content: center;
            }
        }

    </style>

</head>


<body>


<?php require_once __DIR__ . "/includes/admin_sidebar.php"; ?>

<div class="app">

    <!-- =================================================
         MAIN
         ================================================= -->

    <main class="main">


        <!-- TOPBAR -->

        <div class="topbar">


            <div>

                <div class="page-label">
                    ADMIN CONTROL CENTER
                </div>

                <h1 class="page-title">
                    Applications
                </h1>

            </div>


            <div class="top-right">


                <div class="date">

                    <?php

                    echo htmlspecialchars(
                        $currentDate
                    );

                    ?>

                </div>


                <div class="profile">

                    <div class="avatar">

                        <?php

                        echo strtoupper(
                            substr($adminName, 0, 1)
                        );

                        ?>

                    </div>


                    <div>

                        <div class="profile-name">

                            <?php

                            echo htmlspecialchars(
                                $adminName
                            );

                            ?>

                        </div>

                        <div class="profile-role">
                            System Administrator
                        </div>

                    </div>

                </div>


            </div>


        </div>



        <!-- =================================================
             HERO
             ================================================= -->

        <section class="hero">

            <h2>
                Student Applications
            </h2>

            <p>
                Review applications submitted by students,
                verify their details and accept or reject
                applications before document verification
                and admission processing.
            </p>

        </section>



        <!-- =================================================
             MESSAGE
             ================================================= -->

        <?php if ($message != "") { ?>

            <div
                class="message
                <?php
                echo $messageType;
                ?>"
            >

                <?php

                echo htmlspecialchars(
                    $message
                );

                ?>

            </div>

        <?php } ?>



        <!-- =================================================
             STATISTICS
             ================================================= -->

        <section class="stats">


            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        TOTAL APPLICATIONS
                    </span>

                    <span class="stat-icon blue-icon">
                        ▤
                    </span>

                </div>

                <div class="stat-value">
                    <?php echo $totalApplications; ?>
                </div>

                <div class="stat-meta">
                    Applications received
                </div>

            </div>



            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        PENDING
                    </span>

                    <span class="stat-icon yellow-icon">
                        ◷
                    </span>

                </div>

                <div class="stat-value">
                    <?php echo $pendingApplications; ?>
                </div>

                <div class="stat-meta">
                    Waiting for review
                </div>

            </div>



            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        ACCEPTED
                    </span>

                    <span class="stat-icon green-icon">
                        ✓
                    </span>

                </div>

                <div class="stat-value">
                    <?php echo $acceptedApplications; ?>
                </div>

                <div class="stat-meta">
                    Applications approved
                </div>

            </div>



            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        REJECTED
                    </span>

                    <span class="stat-icon"
                          style="
                          background:rgba(239,68,68,0.10);
                          color:#f87171;
                          ">

                        ×

                    </span>

                </div>

                <div class="stat-value">
                    <?php echo $rejectedApplications; ?>
                </div>

                <div class="stat-meta">
                    Applications rejected
                </div>

            </div>


        </section>



        <!-- =================================================
             APPLICATION PANEL
             ================================================= -->

        <section class="panel">


            <div class="panel-header">

                <h3>
                    Application Records
                </h3>

                <span>
                    LIVE DATABASE
                </span>

            </div>



            <!-- SEARCH -->

            <form
                method="GET"
                class="search-area"
            >

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search by student name, roll number, course or department..."
                    value="<?php
                    echo htmlspecialchars($search);
                    ?>"
                >


                <button
                    type="submit"
                    class="search-btn"
                >

                    Search

                </button>


                <?php if ($search != "") { ?>

                    <a
                        href="admin_applications.php"
                        class="clear-btn"
                    >

                        Clear

                    </a>

                <?php } ?>

            </form>



            <!-- TABLE -->

            <?php if (count($applications) > 0) { ?>

                <div class="table-container">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    APPLICATION
                                </th>

                                <th>
                                    STUDENT
                                </th>

                                <th>
                                    COURSE
                                </th>

                                <th>
                                    DEPARTMENT
                                </th>

                                <th>
                                    TYPE
                                </th>

                                <th>
                                    STATUS
                                </th>

                                <th>
                                    SUBMITTED
                                </th>

                                <th>
                                    ACTION
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($applications as $application) { ?>


                            <tr>


                                <!-- APPLICATION ID -->

                                <td>

                                    <strong>
                                        #<?php

                                        echo (int)
                                            $application["id"];

                                        ?>
                                    </strong>

                                </td>



                                <!-- STUDENT -->

                                <td>

                                    <div class="student-name">

                                        <?php

                                        echo htmlspecialchars(
                                            $application["student_name"]
                                        );

                                        ?>

                                    </div>

                                    <div class="sub-text">

                                        <?php

                                        echo htmlspecialchars(
                                            $application["roll_no"] ?? ""
                                        );

                                        ?>

                                    </div>

                                </td>



                                <!-- COURSE -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $application["course_name"]
                                        ?? "-"
                                    );

                                    ?>

                                </td>



                                <!-- DEPARTMENT -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $application["department"]
                                        ?? "-"
                                    );

                                    ?>

                                </td>



                                <!-- APPLICATION TYPE -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $application["program_type"]
                                        ?? "-"
                                    );

                                    ?>

                                </td>



                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $status =
                                        $application[
                                            "application_status"
                                        ];

                                    $statusClass = "pending";

                                    if (
                                        $status ==
                                        "Accepted"
                                    )
                                    {
                                        $statusClass =
                                            "accepted";
                                    }

                                    if (
                                        $status ==
                                        "Rejected"
                                    )
                                    {
                                        $statusClass =
                                            "rejected";
                                    }

                                    ?>


                                    <span
                                        class="status
                                        <?php
                                        echo $statusClass;
                                        ?>"
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $status
                                        );

                                        ?>

                                    </span>

                                </td>



                                <!-- DATE -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime(
                                                $application[
                                                    "submitted_at"
                                                ]
                                            )
                                        )
                                    );

                                    ?>

                                </td>



                                <!-- ACTION -->

                                <td>

                                    <div class="action-area">

                                    <button
                                        type="button"
                                        class="btn btn-view"
                                        onclick="openApplicationModal(<?php echo (int)$application["id"]; ?>)"
                                    >
                                        ◉ View
                                    </button>

                                    <?php

                                    if ($status == "Pending")
                                    {

                                    ?>

                                        <!-- ACCEPT -->

                                        <form
                                            method="POST"
                                            style="display:inline;"
                                        >

                                            <input
                                                type="hidden"
                                                name="application_id"
                                                value="<?php
                                                echo (int)
                                                $application["id"];
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="accept_application"
                                                class="btn btn-accept"
                                                onclick="
                                                return confirm(
                                                'Accept this application?'
                                                );
                                                "
                                            >

                                                ✓ Accept

                                            </button>

                                        </form>



                                        <!-- REJECT -->

                                        <button
                                            type="button"
                                            class="btn btn-reject"
                                            onclick="
                                            openRejectModal(
                                            <?php
                                            echo (int)
                                            $application["id"];
                                            ?>
                                            );
                                            "
                                        >

                                            × Reject

                                        </button>


                                    <?php

                                    }
                                    else
                                    {

                                    ?>

                                        <!-- RESET -->

                                        <form
                                            method="POST"
                                            style="display:inline;"
                                        >

                                            <input
                                                type="hidden"
                                                name="application_id"
                                                value="<?php
                                                echo (int)
                                                $application["id"];
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="reset_application"
                                                class="btn btn-reset"
                                                onclick="
                                                return confirm(
                                                'Move this application back to pending?'
                                                );
                                                "
                                            >

                                                ↻ Reset

                                            </button>

                                        </form>


                                    <?php

                                    }

                                    ?>


                                    </div>

                                </td>


                            </tr>


                        <?php } ?>


                        </tbody>

                    </table>

                </div>


            <?php } else { ?>


                <div class="empty">

                    <div class="empty-icon">
                        ▤
                    </div>

                    <strong>
                        No applications found
                    </strong>

                    <span>
                        Student applications will appear here
                        after they are submitted.
                    </span>

                </div>


            <?php } ?>


        </section>



        <!-- =================================================
             FOOTER
             ================================================= -->

        <div class="footer">

            SMART CAMPUS MANAGEMENT SYSTEM
            &nbsp; • &nbsp;
            Administrator Control Center
            &nbsp; • &nbsp;
            Version 1.0

        </div>


    </main>


</div>



<!-- =================================================
     REJECTION MODAL
     ================================================= -->

<div
    class="modal"
    id="rejectModal"
>


    <div class="modal-box">


        <h3>
            Reject Application
        </h3>


        <p>
            Please provide a reason for rejecting
            this student's application.
        </p>


        <form method="POST">


            <input
                type="hidden"
                name="application_id"
                id="rejectApplicationId"
            >


            <textarea
                name="admin_remarks"
                class="remarks"
                placeholder="Enter rejection reason..."
                required
            ></textarea>


            <div class="modal-actions">


                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeRejectModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    name="reject_application"
                    class="confirm-reject"
                >

                    Reject Application

                </button>


            </div>


        </form>


    </div>


</div>



<script>

function openApplicationModal(applicationId)
{
    var modal = document.getElementById("applicationViewModal");
    var data = document.getElementById("applicationData_" + applicationId);

    if (modal && data)
    {
        document.getElementById("viewApplicationContent").innerHTML = data.innerHTML;
        modal.style.display = "flex";
    }
}

function closeApplicationModal()
{
    var modal = document.getElementById("applicationViewModal");

    if (modal)
    {
        modal.style.display = "none";
    }
}


function openRejectModal(applicationId)
{
    document.getElementById(
        "rejectApplicationId"
    ).value = applicationId;

    document.getElementById(
        "rejectModal"
    ).style.display = "flex";
}


function closeRejectModal()
{
    document.getElementById(
        "rejectModal"
    ).style.display = "none";
}


window.onclick = function(event)
{
    const modal =
        document.getElementById("rejectModal");

    if (event.target === modal)
    {
        closeRejectModal();
    }
}

</script>


</body>

</html>

<?php

$conn->close();

?>
