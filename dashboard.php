<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";


/* =========================================================
   DASHBOARD DATA
   ========================================================= */

$totalStudents = 0;
$totalDepartments = 0;
$averageCgpa = 0;

$pendingApplications = 0;
$acceptedApplications = 0;
$pendingDocuments = 0;
$verifiedDocuments = 0;
$completedAdmissions = 0;
$rejectedDocuments = 0;
$admissionsReady = 0;

$adminName = "Administrator";


/* =========================================================
   ADMIN NAME
   ========================================================= */

if (
    isset($_SESSION["admin_name"]) &&
    $_SESSION["admin_name"] != ""
)
{
    $adminName = $_SESSION["admin_name"];
}


/* =========================================================
   HELPER - CHECK TABLE
   ========================================================= */

function tableExists($conn, $tableName)
{
    $tableName = $conn->real_escape_string($tableName);

    $result = $conn->query(
        "SHOW TABLES LIKE '$tableName'"
    );

    return ($result && $result->num_rows > 0);
}


/* =========================================================
   HELPER - CHECK COLUMN
   Keeps the dashboard compatible with the existing database
   structure and prevents application/document cards from
   disappearing when older modules use a different status
   column name.
   ========================================================= */

function columnExists($conn, $tableName, $columnName)
{
    if (!tableExists($conn, $tableName))
    {
        return false;
    }

    $tableName = $conn->real_escape_string($tableName);
    $columnName = $conn->real_escape_string($columnName);

    $result = $conn->query(
        "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'"
    );

    return ($result && $result->num_rows > 0);
}


/* =========================================================
   TOTAL STUDENTS
   ========================================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM students"
);

if ($result)
{
    $row = $result->fetch_assoc();

    $totalStudents = $row["total"];
}


/* =========================================================
   TOTAL DEPARTMENTS
   ========================================================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM departments"
);

if ($result)
{
    $row = $result->fetch_assoc();

    $totalDepartments = $row["total"];
}


/* =========================================================
   AVERAGE CGPA
   ========================================================= */

$result = $conn->query(
    "SELECT AVG(cgpa) AS average
     FROM students"
);

if ($result)
{
    $row = $result->fetch_assoc();

    if ($row["average"] !== null)
    {
        $averageCgpa = $row["average"];
    }
}


/* =========================================================
   STUDENT APPLICATIONS
   ========================================================= */

if (tableExists($conn, "student_applications"))
{
    $applicationStatusColumn = "";

    if (columnExists($conn, "student_applications", "application_status"))
    {
        $applicationStatusColumn = "application_status";
    }
    elseif (columnExists($conn, "student_applications", "status"))
    {
        $applicationStatusColumn = "status";
    }

    if ($applicationStatusColumn != "")
    {
        $result = $conn->query(
            "SELECT COUNT(*) AS total
             FROM student_applications
             WHERE `$applicationStatusColumn` = 'Submitted'
                OR `$applicationStatusColumn` = 'Pending'"
        );

        if ($result)
        {
            $row = $result->fetch_assoc();
            $pendingApplications = (int)$row["total"];
        }

        $result = $conn->query(
            "SELECT COUNT(*) AS total
             FROM student_applications
             WHERE `$applicationStatusColumn` = 'Accepted'"
        );

        if ($result)
        {
            $row = $result->fetch_assoc();
            $acceptedApplications = (int)$row["total"];
        }
    }
}


/* =========================================================
   DOCUMENT VERIFICATION
   ========================================================= */

if (tableExists($conn, "student_documents"))
{
    $documentStatusColumn = "";

    if (columnExists($conn, "student_documents", "verification_status"))
    {
        $documentStatusColumn = "verification_status";
    }
    elseif (columnExists($conn, "student_documents", "status"))
    {
        $documentStatusColumn = "status";
    }

    if ($documentStatusColumn != "")
    {
        $result = $conn->query(
            "SELECT COUNT(*) AS total
             FROM student_documents
             WHERE `$documentStatusColumn` = 'Pending'"
        );

        if ($result)
        {
            $row = $result->fetch_assoc();
            $pendingDocuments = (int)$row["total"];
        }

        $result = $conn->query(
            "SELECT COUNT(*) AS total
             FROM student_documents
             WHERE `$documentStatusColumn` = 'Verified'"
        );

        if ($result)
        {
            $row = $result->fetch_assoc();
            $verifiedDocuments = (int)$row["total"];
        }

        $result = $conn->query(
            "SELECT COUNT(*) AS total
             FROM student_documents
             WHERE `$documentStatusColumn` = 'Rejected'"
        );

        if ($result)
        {
            $row = $result->fetch_assoc();
            $rejectedDocuments = (int)$row["total"];
        }
    }
}


/* =========================================================
   ADMISSION PROCESSING
   ========================================================= */

if (tableExists($conn, "admission_processing"))
{
    $result = $conn->query(
        "SELECT COUNT(*) AS total
         FROM admission_processing
         WHERE admission_status = 'Completed'"
    );

    if ($result)
    {
        $row = $result->fetch_assoc();
        $completedAdmissions = (int)$row["total"];
    }

    $result = $conn->query(
        "SELECT COUNT(*) AS total
         FROM admission_processing
         WHERE admission_status IN ('Pending', 'Processing')"
    );

    if ($result)
    {
        $row = $result->fetch_assoc();
        $admissionsReady = (int)$row["total"];
    }
}


/* =========================================================
   CURRENT DATE
   ========================================================= */

$currentDate = date("l, d F Y");

?>

<!DOCTYPE html>

<html>

<head>

    <title>NexusCampus | Smart Campus Management System</title>

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


        /* =================================================
           BACKGROUND GRID
           ================================================= */

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

            font-size: 18px;

            font-weight: 800;

            letter-spacing: 0.04em;

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

            flex: 1 1 auto;

            overflow-y: auto;

            min-height: 0;
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

            flex-shrink: 0;
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


        .quick-search
        {
            display: flex;

            align-items: center;

            gap: 8px;

            padding: 7px 10px 7px 12px;

            border-radius: 12px;

            border: 1px solid var(--border);

            background: rgba(255,255,255,0.025);

            min-width: 260px;
        }


        .quick-search input
        {
            background: transparent;

            border: none;

            outline: none;

            color: var(--text);

            width: 100%;

            font-size: 12px;
        }


        .quick-search button
        {
            border: none;

            background: linear-gradient(135deg, #3b82f6, #06b6d4);

            color: white;

            border-radius: 9px;

            padding: 8px 10px;

            font-size: 11px;

            font-weight: 700;

            cursor: pointer;
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

            max-width: 650px;

            line-height: 1.6;
        }


        .action-center
        {
            margin-bottom: 22px;

            background: rgba(13,19,36,0.86);

            border: 1px solid var(--border);

            border-radius: 18px;

            overflow: hidden;

            box-shadow: 0 18px 38px rgba(0,0,0,0.18);
        }


        .action-center-header
        {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 12px;

            padding: 18px 20px 12px;

            border-bottom: 1px solid var(--border);
        }


        .action-center-header h3
        {
            margin: 0;

            font-size: 15px;
        }


        .action-center-header span
        {
            color: #fca5a5;

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 0.12em;
        }


        .action-center-grid
        {
            display: grid;

            grid-template-columns: repeat(4, 1fr);

            gap: 12px;

            padding: 16px 18px 18px;
        }


        .action-card
        {
            display: block;

            text-decoration: none;

            color: var(--text);

            background: rgba(255,255,255,0.025);

            border: 1px solid rgba(148,163,184,0.10);

            border-radius: 14px;

            padding: 16px 14px;

            transition: 0.2s ease;
        }


        .action-card:hover
        {
            transform: translateY(-3px);

            border-color: rgba(96,165,250,0.20);

            box-shadow: 0 16px 30px rgba(59,130,246,0.10);
        }


        .action-card-top
        {
            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 10px;
        }


        .action-card-icon
        {
            width: 34px;

            height: 34px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 14px;
        }


        .action-card strong
        {
            display: block;

            font-size: 24px;

            letter-spacing: -0.8px;

            margin: 0 0 4px;
        }


        .action-card small
        {
            color: #94a3b8;

            font-size: 10px;

            letter-spacing: 0.08em;

            text-transform: uppercase;
        }


        /* =================================================
           STATISTICS
           ================================================= */

        .stats
        {
            display: grid;

            grid-template-columns:
                repeat(6, 1fr);

            gap: 12px;

            margin-bottom: 22px;
        }


        .stat
        {
            position: relative;

            overflow: hidden;

            background:
                rgba(13,19,36,0.86);

            border:
                1px solid var(--border);

            border-radius: 15px;

            padding: 18px;

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

            font-size: 10px;

            font-weight: 600;
        }


        .stat-icon
        {
            width: 31px;

            height: 31px;

            border-radius: 9px;

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


        .red-icon
        {
            background: rgba(239,68,68,0.12);

            color: #f87171;
        }


        .cyan-icon
        {
            background: rgba(6,182,212,0.12);

            color: #22d3ee;
        }


        .stat-value
        {
            font-size: 25px;

            font-weight: 750;

            margin-top: 15px;

            letter-spacing: -1px;
        }


        .stat-meta
        {
            margin-top: 6px;

            color: #64748b;

            font-size: 9px;
        }


        /* =================================================
           CONTENT GRID
           ================================================= */

        .content-grid
        {
            display: grid;

            grid-template-columns:
                minmax(0, 1.6fr)
                minmax(280px, 0.8fr);

            gap: 20px;
        }


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
           ANALYTICS
           ================================================= */

        .analytics
        {
            padding: 22px;
        }


        .chart
        {
            height: 210px;

            display: flex;

            align-items: flex-end;

            gap: 13px;

            padding:
                20px 10px 0;
        }


        .bar-wrapper
        {
            flex: 1;

            height: 100%;

            display: flex;

            flex-direction: column;

            justify-content: flex-end;

            align-items: center;

            gap: 8px;
        }


        .bar
        {
            width: 100%;

            max-width: 38px;

            min-height: 10px;

            border-radius:
                7px 7px 3px 3px;

            background:
                linear-gradient(
                    180deg,
                    #60a5fa,
                    #2563eb
                );

            box-shadow:
                0 0 18px rgba(59,130,246,0.12);

            transition:
                height 0.5s ease,
                transform 0.2s;
        }


        .bar:hover
        {
            transform: translateY(-5px);

            box-shadow:
                0 0 22px rgba(59,130,246,0.30);
        }


        .bar-label
        {
            color: #64748b;

            font-size: 9px;
        }


        .analytics-note
        {
            margin-top: 10px;

            color: #64748b;

            font-size: 10px;
        }


        /* =================================================
           QUICK ACTIONS
           ================================================= */

        .actions
        {
            padding: 15px;
        }


        .action
        {
            display: flex;

            align-items: center;

            gap: 12px;

            text-decoration: none;

            color: white;

            padding: 12px;

            border-radius: 11px;

            margin-bottom: 7px;

            background:
                rgba(255,255,255,0.025);

            border:
                1px solid transparent;

            transition: 0.2s;
        }


        .action:hover
        {
            background:
                rgba(59,130,246,0.08);

            border-color:
                rgba(59,130,246,0.15);

            transform: translateX(3px);
        }


        .action-icon
        {
            width: 34px;

            height: 34px;

            border-radius: 9px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                rgba(59,130,246,0.12);

            color: #60a5fa;

            font-size: 14px;
        }


        .action strong
        {
            display: block;

            font-size: 12px;
        }


        .action small
        {
            display: block;

            color: #64748b;

            font-size: 9px;

            margin-top: 3px;
        }


        .arrow
        {
            margin-left: auto;

            color: #475569;
        }


        /* =================================================
           ACTIVITY
           ================================================= */

        .activity
        {
            margin-top: 20px;
        }


        .activity-item
        {
            display: flex;

            gap: 13px;

            padding: 14px 20px;

            border-bottom:
                1px solid rgba(148,163,184,0.07);
        }


        .activity-item:last-child
        {
            border-bottom: none;
        }


        .activity-dot
        {
            width: 8px;

            height: 8px;

            border-radius: 50%;

            background: #3b82f6;

            margin-top: 5px;

            box-shadow:
                0 0 10px rgba(59,130,246,0.55);
        }


        .activity-text
        {
            font-size: 11px;

            color: #cbd5e1;
        }


        .activity-time
        {
            color: #64748b;

            font-size: 9px;

            margin-top: 4px;
        }


        /* =================================================
           APPLICATION & DOCUMENT WORKFLOW
           ================================================= */

        .workflow-panel
        {
            margin-top: 20px;
        }


        .workflow-grid
        {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            padding: 15px;
        }


        .workflow-card
        {
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            padding: 13px;
            border-radius: 11px;
            background: rgba(255,255,255,0.025);
            border: 1px solid transparent;
            transition: 0.2s;
        }


        .workflow-card:hover
        {
            background: rgba(59,130,246,0.08);
            border-color: rgba(59,130,246,0.15);
            transform: translateY(-2px);
        }


        .workflow-icon
        {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }


        .workflow-content
        {
            min-width: 0;
            flex: 1;
        }


        .workflow-content strong
        {
            display: block;
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }


        .workflow-content span
        {
            display: block;
            color: #64748b;
            font-size: 8px;
            margin-top: 3px;
        }


        .workflow-number
        {
            font-size: 20px;
            font-weight: 750;
            color: #f8fafc;
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

        @media (max-width: 1200px)
        {

            .stats
            {
                grid-template-columns:
                    repeat(3, 1fr);
            }

        }


        @media (max-width: 1200px)
        {
            .workflow-grid
            {
                grid-template-columns: repeat(3, 1fr);
            }
        }


        @media (max-width: 1000px)
        {

            .sidebar
            {
                width: 215px;
            }


            .main
            {
                margin-left: 215px;

                width: calc(100% - 215px);

                padding: 22px;
            }


            .content-grid
            {
                grid-template-columns: 1fr;
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


            .stats
            {
                grid-template-columns: 1fr;
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


            .hero
            {
                padding: 22px;
            }


            .hero h2
            {
                font-size: 20px;
            }

        }

    </style>

</head>


<body>


<div class="app">


    <?php require_once __DIR__ . "/includes/admin_sidebar.php"; ?>

    <!-- =================================================
         MAIN CONTENT
         ================================================= -->

    <main class="main">


        <!-- =================================================
             TOPBAR
             ================================================= -->

        <div class="topbar">


            <div>

                <div class="page-label">
                    ADMIN CONTROL CENTER
                </div>

                <h1 class="page-title">
                    Dashboard
                </h1>

            </div>


            <div class="top-right">


                <form class="quick-search" method="GET" action="search_sort.php">
                    <input
                        type="hidden"
                        name="search"
                        value="1"
                    >
                    <input
                        type="hidden"
                        name="search_type"
                        value="roll_no"
                    >
                    <input
                        type="text"
                        name="search_value"
                        placeholder="Search roll no..."
                        aria-label="Search student by roll number"
                    >
                    <button type="submit">⌕</button>
                </form>


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

                Welcome back,
                <?php

                echo htmlspecialchars(
                    $adminName
                );

                ?>.

            </h2>


            <p>

                Your college command center is ready.
                Monitor student records, applications,
                document verification, admissions,
                departments and academic information
                from one unified workspace.

            </p>

        </section>



        <section class="action-center">

            <div class="action-center-header">

                <h3>Pending Action Center</h3>

                <span>Action Required</span>

            </div>

            <div class="action-center-grid">

                <a href="admin_applications.php" class="action-card">
                    <div class="action-card-top">
                        <span class="action-card-icon" style="background: rgba(59,130,246,0.12); color: #60a5fa;">▤</span>
                        <span style="color: #f8fafc; font-size: 11px; font-weight: 700;">Review</span>
                    </div>
                    <strong><?php echo $pendingApplications; ?></strong>
                    <small>Applications</small>
                </a>

                <a href="document_verification.php" class="action-card">
                    <div class="action-card-top">
                        <span class="action-card-icon" style="background: rgba(239,68,68,0.12); color: #f87171;">▧</span>
                        <span style="color: #f8fafc; font-size: 11px; font-weight: 700;">Verify</span>
                    </div>
                    <strong><?php echo $pendingDocuments; ?></strong>
                    <small>Docs Pending</small>
                </a>

                <a href="document_verification.php" class="action-card">
                    <div class="action-card-top">
                        <span class="action-card-icon" style="background: rgba(239,68,68,0.12); color: #fca5a5;">↺</span>
                        <span style="color: #f8fafc; font-size: 11px; font-weight: 700;">Re-upload</span>
                    </div>
                    <strong><?php echo $rejectedDocuments; ?></strong>
                    <small>Rejected Docs</small>
                </a>

                <a href="admission_processing.php" class="action-card">
                    <div class="action-card-top">
                        <span class="action-card-icon" style="background: rgba(34,197,94,0.12); color: #4ade80;">✓</span>
                        <span style="color: #f8fafc; font-size: 11px; font-weight: 700;">Process</span>
                    </div>
                    <strong><?php echo $admissionsReady; ?></strong>
                    <small>Ready to Admit</small>
                </a>

            </div>

        </section>



        <!-- =================================================
             STATISTICS
             ================================================= -->

        <section class="stats">


            <!-- TOTAL STUDENTS -->

            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        TOTAL STUDENTS
                    </span>

                    <span class="stat-icon blue-icon">
                        ♙
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $totalStudents;

                    ?>

                </div>


                <div class="stat-meta">
                    Registered student records
                </div>

            </div>



            <!-- DEPARTMENTS -->

            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        DEPARTMENTS
                    </span>

                    <span class="stat-icon purple-icon">
                        ⌘
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $totalDepartments;

                    ?>

                </div>


                <div class="stat-meta">
                    Active academic departments
                </div>

            </div>



            <!-- AVERAGE CGPA -->

            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        AVERAGE CGPA
                    </span>

                    <span class="stat-icon green-icon">
                        ★
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo number_format(
                        (float)$averageCgpa,
                        2
                    );

                    ?>

                </div>


                <div class="stat-meta">
                    Overall student performance
                </div>

            </div>



            <!-- =================================================
                 NEW - PENDING APPLICATIONS
                 ================================================= -->

            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        PENDING APPLICATIONS
                    </span>

                    <span class="stat-icon cyan-icon">
                        ▤
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $pendingApplications;

                    ?>

                </div>


                <div class="stat-meta">
                    Applications awaiting review
                </div>

            </div>



            <!-- =================================================
                 NEW - DOCUMENTS
                 ================================================= -->

            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        DOCUMENTS PENDING
                    </span>

                    <span class="stat-icon red-icon">
                        ▧
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $pendingDocuments;

                    ?>

                </div>


                <div class="stat-meta">
                    Documents awaiting verification
                </div>

            </div>



            <!-- =================================================
                 NEW - ACCEPTED APPLICATIONS
                 ================================================= -->

            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        ACCEPTED APPLICATIONS
                    </span>

                    <span class="stat-icon green-icon">
                        ✓
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $acceptedApplications;

                    ?>

                </div>


                <div class="stat-meta">
                    Applications approved by admin
                </div>

            </div>


        </section>



        <!-- =================================================
             MAIN CONTENT GRID
             ================================================= -->

        <div class="content-grid">


            <!-- =================================================
                 ANALYTICS
                 ================================================= -->

            <section class="panel">

                <div class="panel-header">

                    <h3>
                        Student Overview
                    </h3>

                    <span>
                        LIVE DATABASE
                    </span>

                </div>


                <div class="analytics">


                    <div class="chart">


                        <div class="bar-wrapper">

                            <div
                                class="bar"
                                style="height: 42%;"
                            ></div>

                            <span class="bar-label">
                                1
                            </span>

                        </div>


                        <div class="bar-wrapper">

                            <div
                                class="bar"
                                style="height: 58%;"
                            ></div>

                            <span class="bar-label">
                                2
                            </span>

                        </div>


                        <div class="bar-wrapper">

                            <div
                                class="bar"
                                style="height: 47%;"
                            ></div>

                            <span class="bar-label">
                                3
                            </span>

                        </div>


                        <div class="bar-wrapper">

                            <div
                                class="bar"
                                style="height: 72%;"
                            ></div>

                            <span class="bar-label">
                                4
                            </span>

                        </div>


                        <div class="bar-wrapper">

                            <div
                                class="bar"
                                style="height: 63%;"
                            ></div>

                            <span class="bar-label">
                                5
                            </span>

                        </div>


                        <div class="bar-wrapper">

                            <div
                                class="bar"
                                style="height: 81%;"
                            ></div>

                            <span class="bar-label">
                                6
                            </span>

                        </div>


                        <div class="bar-wrapper">

                            <div
                                class="bar"
                                style="height: 69%;"
                            ></div>

                            <span class="bar-label">
                                7
                            </span>

                        </div>


                        <div class="bar-wrapper">

                            <div
                                class="bar"
                                style="height: 88%;"
                            ></div>

                            <span class="bar-label">
                                8
                            </span>

                        </div>


                    </div>


                    <div class="analytics-note">

                        Academic overview visualization.
                        Detailed statistics are available
                        in the Reports module.

                    </div>


                </div>

            </section>



            <!-- =================================================
                 QUICK ACTIONS
                 ================================================= -->

            <section class="panel">

                <div class="panel-header">

                    <h3>
                        Quick Actions
                    </h3>

                    <span>
                        SHORTCUTS
                    </span>

                </div>


                <div class="actions">


                    <!-- EXISTING -->

                    <a
                        href="students.php"
                        class="action"
                    >

                        <div class="action-icon">
                            +
                        </div>

                        <div>

                            <strong>
                                Manage Students
                            </strong>

                            <small>
                                Add or update records
                            </small>

                        </div>

                        <span class="arrow">
                            →
                        </span>

                    </a>

                    <a
                        href="teachers.php"
                        class="action"
                    >

                        <div class="action-icon">
                            👨‍🏫
                        </div>

                        <div>

                            <strong>
                                Manage Teachers
                            </strong>

                            <small>
                                Create approved teacher accounts
                            </small>

                        </div>

                        <span class="arrow">
                            →
                        </span>

                    </a>

                    <a
                        href="admission.php"
                        class="action"
                    >

                        <div class="action-icon">
                            +
                        </div>

                        <div>

                            <strong>
                                Admissions
                            </strong>

                            <small>
                                Manage waiting queue
                            </small>

                        </div>

                        <span class="arrow">
                            →
                        </span>

                    </a>



                    <!-- =================================================
                         NEW - APPLICATIONS
                         ================================================= -->

                    <a
                        href="admin_applications.php"
                        class="action"
                    >

                        <div class="action-icon">
                            ▤
                        </div>

                        <div>

                            <strong>
                                Review Applications
                            </strong>

                            <small>
                                Accept or reject student applications
                            </small>

                        </div>

                        <span class="arrow">
                            →
                        </span>

                    </a>



                    <!-- =================================================
                         NEW - DOCUMENT VERIFICATION
                         ================================================= -->

                    <a
                        href="document_verification.php"
                        class="action"
                    >

                        <div class="action-icon">
                            ▧
                        </div>

                        <div>

                            <strong>
                                Document Verification
                            </strong>

                            <small>
                                Verify submitted academic documents
                            </small>

                        </div>

                        <span class="arrow">
                            →
                        </span>

                    </a>



                    <!-- =================================================
                         NEW - ADMISSION PROCESSING
                         ================================================= -->

                    <a
                        href="admission_processing.php"
                        class="action"
                    >

                        <div class="action-icon">
                            ✓
                        </div>

                        <div>

                            <strong>
                                Admission Processing
                            </strong>

                            <small>
                                Process verified admissions
                            </small>

                        </div>

                        <span class="arrow">
                            →
                        </span>

                    </a>



                    <!-- EXISTING -->

                    <a
                        href="department.php"
                        class="action"
                    >

                        <div class="action-icon">
                            +
                        </div>

                        <div>

                            <strong>
                                Departments
                            </strong>

                            <small>
                                Explore hierarchy
                            </small>

                        </div>

                        <span class="arrow">
                            →
                        </span>

                    </a>



                    <a
                        href="search_sort.php"
                        class="action"
                    >

                        <div class="action-icon">
                            ⌕
                        </div>

                        <div>

                            <strong>
                                Student Explorer
                            </strong>

                            <small>
                                Search & sort records
                            </small>

                        </div>

                        <span class="arrow">
                            →
                        </span>

                    </a>



                    <a
                        href="reports.php"
                        class="action"
                    >

                        <div class="action-icon">
                            ▥
                        </div>

                        <div>

                            <strong>
                                View Reports
                            </strong>

                            <small>
                                College analytics
                            </small>

                        </div>

                        <span class="arrow">
                            →
                        </span>

                    </a>


                </div>

            </section>


        </div>



        <!-- =================================================
             SYSTEM ACTIVITY
             ================================================= -->

        <section class="panel activity">

            <div class="panel-header">

                <h3>
                    System Activity
                </h3>

                <span>
                    ADMIN PORTAL
                </span>

            </div>


            <div class="activity-item">

                <span class="activity-dot"></span>

                <div>

                    <div class="activity-text">

                        Student Management module is operational.

                    </div>

                    <div class="activity-time">

                        Database connected successfully

                    </div>

                </div>

            </div>


            <div class="activity-item">

                <span class="activity-dot"></span>

                <div>

                    <div class="activity-text">

                        Admission Queue is ready for processing.

                    </div>

                    <div class="activity-time">

                        Queue management available

                    </div>

                </div>

            </div>


            <!-- =================================================
                 NEW APPLICATION ACTIVITY
                 ================================================= -->

            <div class="activity-item">

                <span class="activity-dot"></span>

                <div>

                    <div class="activity-text">

                        <?php

                        echo $pendingApplications;

                        ?>

                        student application(s) awaiting
                        administrative review.

                    </div>

                    <div class="activity-time">

                        Application Management

                    </div>

                </div>

            </div>


            <!-- =================================================
                 NEW DOCUMENT ACTIVITY
                 ================================================= -->

            <div class="activity-item">

                <span class="activity-dot"></span>

                <div>

                    <div class="activity-text">

                        <?php

                        echo $pendingDocuments;

                        ?>

                        document(s) are awaiting verification.

                    </div>

                    <div class="activity-time">

                        Document Verification

                    </div>

                </div>

            </div>


            <div class="activity-item">

                <span class="activity-dot"></span>

                <div>

                    <div class="activity-text">

                        Department and Student Network
                        modules are available.

                    </div>

                    <div class="activity-time">

                        Academic structure online

                    </div>

                </div>

            </div>


            <div class="activity-item">

                <span class="activity-dot"></span>

                <div>

                    <div class="activity-text">

                        Search, sorting and reporting
                        services are operational.

                    </div>

                    <div class="activity-time">

                        All core services available

                    </div>

                </div>

            </div>


        </section>



        <!-- =================================================
             FOOTER
             ================================================= -->

        <!-- =================================================
             APPLICATION & DOCUMENT WORKFLOW
             ================================================= -->

        <section class="panel workflow-panel">

            <div class="panel-header">

                <h3>
                    Application & Document Workflow
                </h3>

                <span>
                    LIVE ADMISSION PIPELINE
                </span>

            </div>

            <div class="workflow-grid">

                <a href="admin_applications.php" class="workflow-card">
                    <div class="workflow-icon cyan-icon">▤</div>
                    <div class="workflow-content">
                        <strong>Applications</strong>
                        <span>Pending review</span>
                    </div>
                    <div class="workflow-number">
                        <?php echo $pendingApplications; ?>
                    </div>
                </a>

                <a href="admin_applications.php" class="workflow-card">
                    <div class="workflow-icon green-icon">✓</div>
                    <div class="workflow-content">
                        <strong>Accepted Applications</strong>
                        <span>Approved by admin</span>
                    </div>
                    <div class="workflow-number">
                        <?php echo $acceptedApplications; ?>
                    </div>
                </a>

                <a href="document_verification.php" class="workflow-card">
                    <div class="workflow-icon red-icon">▧</div>
                    <div class="workflow-content">
                        <strong>Documents Pending</strong>
                        <span>Awaiting verification</span>
                    </div>
                    <div class="workflow-number">
                        <?php echo $pendingDocuments; ?>
                    </div>
                </a>

                <a href="document_verification.php" class="workflow-card">
                    <div class="workflow-icon green-icon">✓</div>
                    <div class="workflow-content">
                        <strong>Documents Verified</strong>
                        <span>Verification completed</span>
                    </div>
                    <div class="workflow-number">
                        <?php echo $verifiedDocuments; ?>
                    </div>
                </a>

                <a href="admission_processing.php" class="workflow-card">
                    <div class="workflow-icon purple-icon">◆</div>
                    <div class="workflow-content">
                        <strong>Admissions Completed</strong>
                        <span>Final admission processed</span>
                    </div>
                    <div class="workflow-number">
                        <?php echo $completedAdmissions; ?>
                    </div>
                </a>

            </div>

        </section>


        <div class="footer">

            SMART CAMPUS MANAGEMENT SYSTEM

            &nbsp; • &nbsp;

            Administrator Control Center

            &nbsp; • &nbsp;

            Version 1.0

        </div>


    </main>


</div>


</body>

</html>

<?php

$conn->close();

?>