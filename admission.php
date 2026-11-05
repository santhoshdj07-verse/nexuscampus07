<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";


/* =========================================================
   VARIABLES
   ========================================================= */

$message = "";
$messageType = "";

$adminName = "Administrator";

if (isset($_SESSION["admin_name"]) && $_SESSION["admin_name"] != "")
{
    $adminName = $_SESSION["admin_name"];
}


/* =========================================================
   ADD STUDENT TO WAITING QUEUE
   FIFO QUEUE
   ========================================================= */

if (isset($_POST["add_queue"]))
{
    $studentId = (int)$_POST["student_id"];

    if ($studentId <= 0)
    {
        $message = "Please select a valid student.";
        $messageType = "error";
    }
    else
    {
        $checkSql =
            "SELECT id, status
             FROM admissions
             WHERE student_id = ?
             AND status IN ('WAITING', 'ADMITTED')";

        $checkStmt = $conn->prepare($checkSql);

        if ($checkStmt)
        {
            $checkStmt->bind_param("i", $studentId);
            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0)
            {
                $message =
                    "This student is already in the admission process.";

                $messageType = "error";
            }
            else
            {
                $positionResult = $conn->query(
                    "SELECT MAX(queue_position) AS max_position
                     FROM admissions
                     WHERE status = 'WAITING'"
                );

                $nextPosition = 1;

                if ($positionResult)
                {
                    $positionRow =
                        $positionResult->fetch_assoc();

                    $nextPosition =
                        (int)$positionRow["max_position"] + 1;
                }

                $sql =
                    "INSERT INTO admissions
                    (student_id, status, queue_position)
                    VALUES (?, 'WAITING', ?)";

                $stmt = $conn->prepare($sql);

                if ($stmt)
                {
                    $stmt->bind_param(
                        "ii",
                        $studentId,
                        $nextPosition
                    );

                    if ($stmt->execute())
                    {
                        $message =
                            "Student added to waiting queue successfully.";

                        $messageType = "success";
                    }
                    else
                    {
                        $message =
                            "Error adding student to queue: "
                            . $stmt->error;

                        $messageType = "error";
                    }

                    $stmt->close();
                }
                else
                {
                    $message =
                        "Unable to prepare queue request.";

                    $messageType = "error";
                }
            }

            $checkStmt->close();
        }
        else
        {
            $message =
                "Unable to check admission status.";

            $messageType = "error";
        }
    }
}


/* =========================================================
   ADMIT FIRST STUDENT
   FIFO
   ========================================================= */

if (isset($_POST["admit_student"]))
{
    $sql =
        "SELECT
            a.id,
            a.student_id,
            a.queue_position,
            s.roll_no,
            s.name,
            s.department,
            s.semester,
            s.phone,
            s.cgpa
         FROM admissions a
         INNER JOIN students s
         ON a.student_id = s.id
         WHERE a.status = 'WAITING'
         ORDER BY a.queue_position ASC
         LIMIT 1";

    $resultAdmit = $conn->query($sql);

    if ($resultAdmit && $resultAdmit->num_rows > 0)
    {
        $student = $resultAdmit->fetch_assoc();

        $admissionId = (int)$student["id"];

        $updateSql =
            "UPDATE admissions
             SET status = 'ADMITTED',
                 admitted_at = NOW()
             WHERE id = ?";

        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt)
        {
            $updateStmt->bind_param(
                "i",
                $admissionId
            );

            if ($updateStmt->execute())
            {
                $message =
                    "Student admitted successfully! Roll No: "
                    . $student["roll_no"];

                $messageType = "success";
            }
            else
            {
                $message =
                    "Error admitting student: "
                    . $updateStmt->error;

                $messageType = "error";
            }

            $updateStmt->close();
        }
        else
        {
            $message =
                "Unable to process admission.";

            $messageType = "error";
        }
    }
    else
    {
        $message =
            "Waiting queue is empty.";

        $messageType = "error";
    }
}


/* =========================================================
   UNDO LAST ADMISSION
   STACK / LIFO
   ========================================================= */

if (isset($_POST["undo_admission"]))
{
    $sql =
        "SELECT
            a.id,
            a.student_id,
            s.roll_no,
            s.name
         FROM admissions a
         INNER JOIN students s
         ON a.student_id = s.id
         WHERE a.status = 'ADMITTED'
         ORDER BY a.admitted_at DESC, a.id DESC
         LIMIT 1";

    $resultUndo = $conn->query($sql);

    if ($resultUndo && $resultUndo->num_rows > 0)
    {
        $student = $resultUndo->fetch_assoc();

        $admissionId = (int)$student["id"];

        $positionResult = $conn->query(
            "SELECT MAX(queue_position) AS max_position
             FROM admissions
             WHERE status = 'WAITING'"
        );

        $nextPosition = 1;

        if ($positionResult)
        {
            $positionRow =
                $positionResult->fetch_assoc();

            $nextPosition =
                (int)$positionRow["max_position"] + 1;
        }

        $updateSql =
            "UPDATE admissions
             SET status = 'WAITING',
                 queue_position = ?,
                 admitted_at = NULL
             WHERE id = ?";

        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt)
        {
            $updateStmt->bind_param(
                "ii",
                $nextPosition,
                $admissionId
            );

            if ($updateStmt->execute())
            {
                $message =
                    "Last admission undone. "
                    . $student["name"]
                    . " returned to the waiting queue.";

                $messageType = "success";
            }
            else
            {
                $message =
                    "Error undoing admission: "
                    . $updateStmt->error;

                $messageType = "error";
            }

            $updateStmt->close();
        }
        else
        {
            $message =
                "Unable to process undo operation.";

            $messageType = "error";
        }
    }
    else
    {
        $message =
            "There are no admissions available to undo.";

        $messageType = "error";
    }
}


/* =========================================================
   AVAILABLE STUDENTS
   ========================================================= */

$studentsResult = $conn->query(
    "SELECT *
     FROM students
     WHERE id NOT IN
     (
         SELECT student_id
         FROM admissions
         WHERE status IN ('WAITING', 'ADMITTED')
     )
     ORDER BY name ASC"
);


/* =========================================================
   WAITING QUEUE
   ========================================================= */

$queueResult = $conn->query(
    "SELECT
        a.id,
        a.queue_position,
        s.roll_no,
        s.name,
        s.department,
        s.semester,
        s.cgpa
     FROM admissions a
     INNER JOIN students s
     ON a.student_id = s.id
     WHERE a.status = 'WAITING'
     ORDER BY a.queue_position ASC"
);


/* =========================================================
   ADMISSION STACK
   ========================================================= */

$stackResult = $conn->query(
    "SELECT
        a.id,
        a.admitted_at,
        s.roll_no,
        s.name,
        s.department
     FROM admissions a
     INNER JOIN students s
     ON a.student_id = s.id
     WHERE a.status = 'ADMITTED'
     ORDER BY a.admitted_at DESC, a.id DESC"
);


/* =========================================================
   STATISTICS
   ========================================================= */

$waitingCount = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM admissions
     WHERE status = 'WAITING'"
);

if ($result)
{
    $row = $result->fetch_assoc();

    $waitingCount = (int)$row["total"];
}


$admittedCount = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM admissions
     WHERE status = 'ADMITTED'"
);

if ($result)
{
    $row = $result->fetch_assoc();

    $admittedCount = (int)$row["total"];
}


$availableCount = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM students
     WHERE id NOT IN
     (
         SELECT student_id
         FROM admissions
         WHERE status IN ('WAITING', 'ADMITTED')
     )"
);

if ($result)
{
    $row = $result->fetch_assoc();

    $availableCount = (int)$row["total"];
}


/* =========================================================
   CURRENT DATE
   ========================================================= */

$currentDate = date("l, d F Y");

?>

<!DOCTYPE html>

<html>

<head>

    <title>Admissions | Smart Campus</title>

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


        html
        {
            scroll-behavior: smooth;
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

            z-index: 0;
        }


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
                rgba(7,11,23,0.90);

            backdrop-filter: blur(18px);

            border-right:
                1px solid var(--border);

            padding: 24px 16px;

            display: flex;

            flex-direction: column;

            z-index: 20;
        }


        .brand
        {
            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                8px 10px 28px;
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
                0 0 25px
                rgba(37,99,235,0.35);
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


        .nav-title
        {
            color: #64748b;

            font-size: 10px;

            font-weight: bold;

            letter-spacing: 1.5px;

            padding:
                12px 12px 8px;
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

            transform:
                translateX(3px);
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
                1px solid
                rgba(59,130,246,0.18);

            box-shadow:
                inset 3px 0 0 #3b82f6;
        }


        .nav-icon
        {
            width: 22px;

            text-align: center;

            font-size: 16px;
        }


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
                1px solid
                rgba(34,197,94,0.14);

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
                0 0 10px
                rgba(34,197,94,0.8);

            animation:
                pulse 2s infinite;
        }


        @keyframes pulse
        {
            0%
            {
                opacity: 1;
            }

            50%
            {
                opacity: 0.4;
            }

            100%
            {
                opacity: 1;
            }
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
                1px solid
                rgba(239,68,68,0.10);

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

            width:
                calc(100% - 255px);

            padding:
                28px 34px 45px;
        }


        /* =================================================
           TOPBAR
           ================================================= */

        .topbar
        {
            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 28px;
        }


        .page-label
        {
            color: #64748b;

            font-size: 11px;

            margin-bottom: 5px;

            letter-spacing: 1.5px;

            font-weight: bold;
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

            padding:
                9px 13px;

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

            padding:
                6px 10px 6px 6px;

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

            padding:
                27px 30px;

            margin-bottom: 20px;

            background:
                linear-gradient(
                    115deg,
                    rgba(37,99,235,0.20),
                    rgba(8,47,73,0.17),
                    rgba(139,92,246,0.15)
                );

            border:
                1px solid
                rgba(96,165,250,0.16);

            box-shadow:
                0 20px 50px
                rgba(0,0,0,0.20);
        }


        .hero::before
        {
            content: "";

            position: absolute;

            width: 180px;

            height: 180px;

            right: 40px;

            top: -90px;

            border-radius: 50%;

            border:
                1px solid
                rgba(96,165,250,0.18);

            box-shadow:
                0 0 50px
                rgba(59,130,246,0.12);
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


        .hero-content
        {
            position: relative;

            z-index: 2;
        }


        .hero-tag
        {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                6px 10px;

            border-radius: 20px;

            color: #93c5fd;

            background:
                rgba(59,130,246,0.10);

            border:
                1px solid
                rgba(59,130,246,0.16);

            font-size: 9px;

            font-weight: bold;

            letter-spacing: 1px;

            margin-bottom: 12px;
        }


        .hero-tag span
        {
            width: 6px;

            height: 6px;

            border-radius: 50%;

            background: #22c55e;

            box-shadow:
                0 0 8px
                rgba(34,197,94,0.8);
        }


        .hero h2
        {
            margin:
                0 0 8px;

            font-size: 23px;
        }


        .hero p
        {
            margin: 0;

            color: #a8b5c8;

            font-size: 13px;

            max-width: 720px;

            line-height: 1.6;
        }


        /* =================================================
           ALERT
           ================================================= */

        .message
        {
            position: relative;

            padding:
                15px 18px;

            border-radius: 12px;

            margin-bottom: 20px;

            font-size: 12px;

            font-weight: 600;

            animation:
                messageIn 0.35s ease;
        }


        @keyframes messageIn
        {
            from
            {
                opacity: 0;

                transform:
                    translateY(-8px);
            }

            to
            {
                opacity: 1;

                transform:
                    translateY(0);
            }
        }


        .message.success
        {
            background:
                rgba(34,197,94,0.08);

            color: #86efac;

            border:
                1px solid
                rgba(34,197,94,0.18);
        }


        .message.error
        {
            background:
                rgba(239,68,68,0.08);

            color: #fca5a5;

            border:
                1px solid
                rgba(239,68,68,0.18);
        }


        /* =================================================
           STATISTICS
           ================================================= */

        .stats
        {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 16px;

            margin-bottom: 20px;
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

            padding: 20px;

            transition:
                transform 0.2s,
                border 0.2s;
        }


        .stat:hover
        {
            transform:
                translateY(-4px);

            border-color:
                rgba(96,165,250,0.25);
        }


        .stat::after
        {
            content: "";

            position: absolute;

            width: 100px;

            height: 100px;

            right: -50px;

            bottom: -50px;

            border-radius: 50%;

            background:
                rgba(59,130,246,0.07);

            filter: blur(5px);
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

            letter-spacing: 0.5px;
        }


        .stat-icon
        {
            width: 35px;

            height: 35px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 15px;
        }


        .blue-icon
        {
            background:
                rgba(59,130,246,0.12);

            color: #60a5fa;
        }


        .green-icon
        {
            background:
                rgba(34,197,94,0.12);

            color: #4ade80;
        }


        .purple-icon
        {
            background:
                rgba(139,92,246,0.12);

            color: #a78bfa;
        }


        .stat-value
        {
            font-size: 29px;

            font-weight: 750;

            margin-top: 16px;

            letter-spacing: -1px;
        }


        .stat-meta
        {
            margin-top: 6px;

            color: #64748b;

            font-size: 10px;
        }


        /* =================================================
           MAIN GRID
           ================================================= */

        .main-grid
        {
            display: grid;

            grid-template-columns:
                minmax(0, 1.55fr)
                minmax(300px, 0.75fr);

            gap: 20px;

            align-items: start;
        }


        .panel
        {
            background:
                rgba(13,19,36,0.86);

            border:
                1px solid var(--border);

            border-radius: 15px;

            overflow: hidden;

            box-shadow:
                0 15px 35px
                rgba(0,0,0,0.10);
        }


        .panel-header
        {
            display: flex;

            justify-content: space-between;

            align-items: center;

            padding:
                18px 20px;

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

            font-size: 9px;

            letter-spacing: 1px;

            font-weight: bold;
        }


        /* =================================================
           ADD QUEUE
           ================================================= */

        .add-panel
        {
            padding: 20px;
        }


        .input-label
        {
            display: block;

            color: #cbd5e1;

            font-size: 11px;

            font-weight: 600;

            margin-bottom: 8px;
        }


        .select-wrapper
        {
            position: relative;
        }


        .select-wrapper::after
        {
            content: "⌄";

            position: absolute;

            right: 14px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #64748b;

            pointer-events: none;
        }


        select
        {
            width: 100%;

            padding:
                13px 40px 13px 14px;

            border:
                1px solid
                rgba(148,163,184,0.16);

            border-radius: 10px;

            background:
                rgba(255,255,255,0.025);

            color: #e2e8f0;

            font-size: 12px;

            outline: none;

            appearance: none;

            transition:
                border 0.2s,
                box-shadow 0.2s;
        }


        select:focus
        {
            border-color:
                rgba(59,130,246,0.55);

            box-shadow:
                0 0 0 3px
                rgba(59,130,246,0.08);
        }


        select option
        {
            background: #0d1324;

            color: white;
        }


        .primary-button
        {
            width: 100%;

            margin-top: 12px;

            padding: 13px 16px;

            border: none;

            border-radius: 10px;

            color: white;

            font-size: 12px;

            font-weight: bold;

            cursor: pointer;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0891b2
                );

            box-shadow:
                0 8px 25px
                rgba(37,99,235,0.18);

            transition:
                transform 0.2s,
                box-shadow 0.2s;
        }


        .primary-button:hover
        {
            transform:
                translateY(-2px);

            box-shadow:
                0 12px 30px
                rgba(37,99,235,0.28);
        }


        /* =================================================
           QUEUE INFO
           ================================================= */

        .queue-info
        {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 10px;

            margin-top: 16px;
        }


        .info-box
        {
            padding: 13px;

            border-radius: 11px;

            background:
                rgba(255,255,255,0.025);

            border:
                1px solid
                rgba(148,163,184,0.08);
        }


        .info-label
        {
            display: block;

            color: #64748b;

            font-size: 9px;

            letter-spacing: 0.5px;

            margin-bottom: 5px;
        }


        .info-value
        {
            font-size: 12px;

            font-weight: bold;
        }


        /* =================================================
           CONTROL PANEL
           ================================================= */

        .control-body
        {
            padding: 20px;
        }


        .control-card
        {
            padding: 18px;

            border-radius: 13px;

            background:
                linear-gradient(
                    135deg,
                    rgba(34,197,94,0.07),
                    rgba(6,182,212,0.04)
                );

            border:
                1px solid
                rgba(34,197,94,0.13);

            margin-bottom: 12px;
        }


        .control-card.red
        {
            background:
                linear-gradient(
                    135deg,
                    rgba(239,68,68,0.07),
                    rgba(139,92,246,0.04)
                );

            border-color:
                rgba(239,68,68,0.13);
        }


        .control-icon
        {
            width: 38px;

            height: 38px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 10px;

            background:
                rgba(34,197,94,0.12);

            color: #4ade80;

            margin-bottom: 12px;

            font-size: 17px;
        }


        .control-card.red .control-icon
        {
            background:
                rgba(239,68,68,0.12);

            color: #f87171;
        }


        .control-title
        {
            font-size: 12px;

            font-weight: bold;

            margin-bottom: 5px;
        }


        .control-description
        {
            color: #64748b;

            font-size: 10px;

            line-height: 1.5;

            margin-bottom: 13px;
        }


        .control-button
        {
            width: 100%;

            padding: 11px;

            border: none;

            border-radius: 9px;

            color: white;

            font-size: 11px;

            font-weight: bold;

            cursor: pointer;

            background:
                linear-gradient(
                    135deg,
                    #16a34a,
                    #059669
                );

            transition: 0.2s;
        }


        .control-button:hover
        {
            transform:
                translateY(-2px);
        }


        .control-button.red-button
        {
            background:
                linear-gradient(
                    135deg,
                    #dc2626,
                    #be123c
                );
        }


        /* =================================================
           QUEUE PANEL
           ================================================= */

        .queue-panel
        {
            margin-top: 20px;
        }


        .queue-list
        {
            padding: 14px;
        }


        .queue-item
        {
            display: grid;

            grid-template-columns:
                48px
                minmax(0,1fr)
                auto;

            gap: 13px;

            align-items: center;

            padding: 14px;

            margin-bottom: 8px;

            border-radius: 12px;

            background:
                rgba(255,255,255,0.025);

            border:
                1px solid
                rgba(148,163,184,0.07);

            transition:
                transform 0.2s,
                border 0.2s,
                background 0.2s;
        }


        .queue-item:last-child
        {
            margin-bottom: 0;
        }


        .queue-item:hover
        {
            transform:
                translateX(4px);

            background:
                rgba(59,130,246,0.05);

            border-color:
                rgba(59,130,246,0.16);
        }


        .queue-position
        {
            width: 40px;

            height: 40px;

            border-radius: 11px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;

            font-size: 13px;

            color: #93c5fd;

            background:
                rgba(59,130,246,0.10);

            border:
                1px solid
                rgba(59,130,246,0.13);
        }


        .queue-position.first
        {
            color: #86efac;

            background:
                rgba(34,197,94,0.10);

            border-color:
                rgba(34,197,94,0.15);

            box-shadow:
                0 0 15px
                rgba(34,197,94,0.08);
        }


        .student-main
        {
            min-width: 0;
        }


        .student-name
        {
            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }


        .student-roll
        {
            color: #60a5fa;

            font-size: 10px;

            margin-top: 3px;
        }


        .student-details
        {
            color: #64748b;

            font-size: 9px;

            margin-top: 4px;
        }


        .queue-badge
        {
            padding:
                5px 8px;

            border-radius: 20px;

            color: #93c5fd;

            background:
                rgba(59,130,246,0.08);

            border:
                1px solid
                rgba(59,130,246,0.12);

            font-size: 8px;

            font-weight: bold;

            white-space: nowrap;
        }


        .queue-badge.first-badge
        {
            color: #86efac;

            background:
                rgba(34,197,94,0.08);

            border-color:
                rgba(34,197,94,0.14);
        }


        /* =================================================
           FIFO INDICATOR
           ================================================= */

        .fifo-bar
        {
            display: flex;

            align-items: center;

            gap: 8px;

            padding:
                13px 15px;

            margin:
                0 14px 14px;

            border-radius: 10px;

            background:
                rgba(6,182,212,0.05);

            border:
                1px solid
                rgba(6,182,212,0.12);

            color: #67e8f9;

            font-size: 9px;

            font-weight: bold;

            letter-spacing: 0.5px;
        }


        .fifo-arrow
        {
            color: #06b6d4;

            font-size: 15px;
        }


        /* =================================================
           EMPTY STATE
           ================================================= */

        .empty
        {
            padding: 45px 20px;

            text-align: center;

            color: #64748b;
        }


        .empty-icon
        {
            width: 50px;

            height: 50px;

            margin:
                0 auto 13px;

            border-radius: 15px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                rgba(59,130,246,0.08);

            color: #60a5fa;

            font-size: 20px;
        }


        .empty-title
        {
            color: #cbd5e1;

            font-size: 12px;

            font-weight: bold;

            margin-bottom: 5px;
        }


        .empty-text
        {
            font-size: 10px;

            line-height: 1.5;
        }


        /* =================================================
           ADMISSION STACK
           ================================================= */

        .stack-panel
        {
            margin-top: 20px;
        }


        .stack-list
        {
            padding: 14px;
        }


        .stack-item
        {
            display: grid;

            grid-template-columns:
                42px
                minmax(0,1fr)
                auto;

            align-items: center;

            gap: 12px;

            padding: 13px;

            margin-bottom: 8px;

            border-radius: 11px;

            background:
                rgba(255,255,255,0.025);

            border:
                1px solid
                rgba(148,163,184,0.07);
        }


        .stack-item:first-child
        {
            background:
                rgba(239,68,68,0.045);

            border-color:
                rgba(239,68,68,0.13);
        }


        .stack-number
        {
            width: 34px;

            height: 34px;

            border-radius: 9px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 9px;

            font-weight: bold;

            color: #94a3b8;

            background:
                rgba(148,163,184,0.07);
        }


        .stack-item:first-child
        .stack-number
        {
            color: #fca5a5;

            background:
                rgba(239,68,68,0.10);
        }


        .stack-name
        {
            font-size: 11px;

            font-weight: bold;
        }


        .stack-roll
        {
            color: #64748b;

            font-size: 9px;

            margin-top: 3px;
        }


        .stack-time
        {
            color: #64748b;

            font-size: 8px;

            text-align: right;
        }


        .top-label
        {
            color: #f87171;

            font-size: 8px;

            font-weight: bold;

            letter-spacing: 0.7px;

            margin-bottom: 3px;
        }


        /* =================================================
           FOOTER
           ================================================= */

        .footer
        {
            text-align: center;

            padding:
                28px 0 5px;

            color: #475569;

            font-size: 10px;

            letter-spacing: 0.3px;
        }


        /* =================================================
           RESPONSIVE
           ================================================= */

        @media (max-width: 1100px)
        {
            .sidebar
            {
                width: 220px;
            }

            .main
            {
                margin-left: 220px;

                width:
                    calc(100% - 220px);

                padding:
                    24px;
            }

            .main-grid
            {
                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 800px)
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

                flex-direction: column;

                gap: 15px;
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
                    repeat(2,1fr);
            }
        }


        @media (max-width: 500px)
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


            .queue-item
            {
                grid-template-columns:
                    42px minmax(0,1fr);
            }


            .queue-badge
            {
                display: none;
            }


            .stack-item
            {
                grid-template-columns:
                    38px minmax(0,1fr);
            }


            .stack-time
            {
                display: none;
            }
        }

    </style>

</head>


<body>


<div class="app">


    <?php require_once __DIR__ . "/includes/admin_sidebar.php"; ?>

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
                    Admission Management
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

            <div class="hero-content">

                <div class="hero-tag">

                    <span></span>

                    ADMISSION PIPELINE ACTIVE

                </div>


                <h2>
                    Admission Command Center
                </h2>


                <p>
                    Manage the student admission pipeline
                    using FIFO queue processing and
                    LIFO stack-based admission reversal.
                    Monitor every stage from one unified
                    control center.
                </p>

            </div>

        </section>



        <!-- =================================================
             MESSAGE
             ================================================= -->

        <?php

        if ($message != "")
        {

        ?>

            <div
                class="message
                <?php echo htmlspecialchars($messageType); ?>"
            >

                <?php

                if ($messageType == "success")
                {
                    echo "✓ ";
                }
                else
                {
                    echo "⚠ ";
                }

                echo htmlspecialchars($message);

                ?>

            </div>

        <?php

        }

        ?>



        <!-- =================================================
             STATISTICS
             ================================================= -->

        <section class="stats">


            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        WAITING QUEUE
                    </span>

                    <span class="stat-icon blue-icon">
                        ≡
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $waitingCount;

                    ?>

                </div>


                <div class="stat-meta">
                    Students waiting for admission
                </div>

            </div>



            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        ADMITTED
                    </span>

                    <span class="stat-icon green-icon">
                        ✓
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $admittedCount;

                    ?>

                </div>


                <div class="stat-meta">
                    Successfully admitted students
                </div>

            </div>



            <div class="stat">

                <div class="stat-top">

                    <span class="stat-label">
                        AVAILABLE
                    </span>

                    <span class="stat-icon purple-icon">
                        ♙
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $availableCount;

                    ?>

                </div>


                <div class="stat-meta">
                    Students available for queue
                </div>

            </div>


        </section>



        <!-- =================================================
             MAIN GRID
             ================================================= -->

        <div class="main-grid">


            <!-- =================================================
                 ADD STUDENT
                 ================================================= -->

            <section class="panel">


                <div class="panel-header">

                    <h3>
                        Add Student to Waiting Queue
                    </h3>

                    <span>
                        FIFO QUEUE
                    </span>

                </div>


                <div class="add-panel">


                    <?php

                    if (
                        $studentsResult &&
                        $studentsResult->num_rows > 0
                    )
                    {

                    ?>


                        <form method="POST">


                            <label class="input-label">
                                SELECT STUDENT
                            </label>


                            <div class="select-wrapper">

                                <select
                                    name="student_id"
                                    required
                                >

                                    <option value="">
                                        Select a student
                                    </option>


                                    <?php

                                    while (
                                        $student =
                                        $studentsResult->fetch_assoc()
                                    )
                                    {

                                    ?>

                                        <option
                                            value="<?php
                                            echo (int)$student["id"];
                                            ?>"
                                        >

                                            <?php

                                            echo htmlspecialchars(
                                                $student["roll_no"]
                                            );

                                            echo " • ";

                                            echo htmlspecialchars(
                                                $student["name"]
                                            );

                                            echo " • ";

                                            echo strtoupper(
                                                htmlspecialchars(
                                                    $student["department"]
                                                )
                                            );

                                            ?>

                                        </option>

                                    <?php

                                    }

                                    ?>

                                </select>

                            </div>


                            <button
                                type="submit"
                                name="add_queue"
                                class="primary-button"
                            >

                                + ADD TO WAITING QUEUE

                            </button>


                        </form>


                    <?php

                    }
                    else
                    {

                    ?>

                        <div class="empty">

                            <div class="empty-icon">
                                ✓
                            </div>

                            <div class="empty-title">
                                No Students Available
                            </div>

                            <div class="empty-text">
                                All registered students are
                                already in the admission process.
                            </div>

                        </div>

                    <?php

                    }

                    ?>


                    <div class="queue-info">


                        <div class="info-box">

                            <span class="info-label">
                                PROCESSING METHOD
                            </span>

                            <span class="info-value">
                                FIFO
                            </span>

                        </div>


                        <div class="info-box">

                            <span class="info-label">
                                NEXT POSITION
                            </span>

                            <span class="info-value">

                                <?php

                                echo $waitingCount + 1;

                                ?>

                            </span>

                        </div>


                    </div>


                </div>


            </section>



            <!-- =================================================
                 ADMISSION CONTROL
                 ================================================= -->

            <section class="panel">


                <div class="panel-header">

                    <h3>
                        Admission Control
                    </h3>

                    <span>
                        OPERATIONS
                    </span>

                </div>


                <div class="control-body">


                    <div class="control-card">


                        <div class="control-icon">
                            ▶
                        </div>


                        <div class="control-title">
                            Admit Next Student
                        </div>


                        <div class="control-description">
                            Processes the student at the front
                            of the FIFO waiting queue.
                        </div>


                        <form method="POST">

                            <button
                                type="submit"
                                name="admit_student"
                                class="control-button"
                                <?php

                                if ($waitingCount == 0)
                                {
                                    echo "disabled";
                                }

                                ?>
                            >

                                ADMIT FIRST STUDENT

                            </button>

                        </form>


                    </div>



                    <div class="control-card red">


                        <div class="control-icon">
                            ↶
                        </div>


                        <div class="control-title">
                            Undo Last Admission
                        </div>


                        <div class="control-description">
                            Uses LIFO stack logic to return the
                            most recently admitted student.
                        </div>


                        <form method="POST">

                            <button
                                type="submit"
                                name="undo_admission"
                                class="control-button red-button"
                                onclick="
                                    return confirm(
                                        'Undo the last admission?'
                                    );
                                "
                                <?php

                                if ($admittedCount == 0)
                                {
                                    echo "disabled";
                                }

                                ?>
                            >

                                UNDO LAST ADMISSION

                            </button>

                        </form>


                    </div>


                </div>


            </section>


        </div>



        <!-- =================================================
             WAITING QUEUE
             ================================================= -->

        <section class="panel queue-panel">


            <div class="panel-header">

                <h3>
                    Admission Waiting Queue
                </h3>

                <span>
                    FIRST IN → FIRST OUT
                </span>

            </div>


            <?php

            if (
                !$queueResult ||
                $queueResult->num_rows == 0
            )
            {

            ?>

                <div class="empty">

                    <div class="empty-icon">
                        ≡
                    </div>

                    <div class="empty-title">
                        Waiting Queue is Empty
                    </div>

                    <div class="empty-text">
                        Add students to the queue to begin
                        admission processing.
                    </div>

                </div>

            <?php

            }
            else
            {

            ?>


                <div class="fifo-bar">

                    <span>
                        QUEUE FLOW
                    </span>

                    <span class="fifo-arrow">
                        →
                    </span>

                    <span>
                        FIRST STUDENT
                    </span>

                    <span class="fifo-arrow">
                        →
                    </span>

                    <span>
                        ADMISSION PROCESS
                    </span>

                </div>


                <div class="queue-list">


                    <?php

                    while (
                        $student =
                        $queueResult->fetch_assoc()
                    )
                    {

                        $isFirst =
                            ((int)$student["queue_position"] === 1);

                    ?>

                        <div class="queue-item">


                            <div
                                class="
                                queue-position
                                <?php

                                if ($isFirst)
                                {
                                    echo "first";
                                }

                                ?>
                                "
                            >

                                <?php

                                echo (int)
                                    $student["queue_position"];

                                ?>

                            </div>


                            <div class="student-main">


                                <div class="student-name">

                                    <?php

                                    echo htmlspecialchars(
                                        $student["name"]
                                    );

                                    ?>

                                </div>


                                <div class="student-roll">

                                    <?php

                                    echo htmlspecialchars(
                                        $student["roll_no"]
                                    );

                                    ?>

                                </div>


                                <div class="student-details">

                                    <?php

                                    echo strtoupper(
                                        htmlspecialchars(
                                            $student["department"]
                                        )
                                    );

                                    echo " • ";

                                    echo htmlspecialchars(
                                        $student["semester"]
                                    );

                                    echo " • CGPA ";

                                    echo htmlspecialchars(
                                        $student["cgpa"]
                                    );

                                    ?>

                                </div>


                            </div>


                            <div
                                class="
                                queue-badge
                                <?php

                                if ($isFirst)
                                {
                                    echo "first-badge";
                                }

                                ?>
                                "
                            >

                                <?php

                                if ($isFirst)
                                {
                                    echo "NEXT";
                                }
                                else
                                {
                                    echo "WAITING";
                                }

                                ?>

                            </div>


                        </div>

                    <?php

                    }

                    ?>


                </div>


            <?php

            }

            ?>


        </section>



        <!-- =================================================
             ADMISSION STACK
             ================================================= -->

        <section class="panel stack-panel">


            <div class="panel-header">

                <div>

                    <h3>
                        Admission Stack
                    </h3>

                </div>

                <span>
                    LIFO • LAST IN → FIRST OUT
                </span>

            </div>


            <?php

            if (
                !$stackResult ||
                $stackResult->num_rows == 0
            )
            {

            ?>

                <div class="empty">

                    <div class="empty-icon">
                        ↶
                    </div>

                    <div class="empty-title">
                        Admission Stack is Empty
                    </div>

                    <div class="empty-text">
                        Once students are admitted, the most
                        recent admission will appear at the top.
                    </div>

                </div>

            <?php

            }
            else
            {

            ?>


                <div class="stack-list">


                    <?php

                    $stackPosition = 1;


                    while (
                        $student =
                        $stackResult->fetch_assoc()
                    )
                    {

                    ?>


                        <div class="stack-item">


                            <div class="stack-number">

                                <?php

                                echo $stackPosition;

                                ?>

                            </div>


                            <div>


                                <?php

                                if ($stackPosition == 1)
                                {

                                ?>

                                    <div class="top-label">
                                        STACK TOP • LAST ADMITTED
                                    </div>

                                <?php

                                }

                                ?>


                                <div class="stack-name">

                                    <?php

                                    echo htmlspecialchars(
                                        $student["name"]
                                    );

                                    ?>

                                </div>


                                <div class="stack-roll">

                                    <?php

                                    echo htmlspecialchars(
                                        $student["roll_no"]
                                    );

                                    echo " • ";

                                    echo strtoupper(
                                        htmlspecialchars(
                                            $student["department"]
                                        )
                                    );

                                    ?>

                                </div>


                            </div>


                            <div class="stack-time">

                                <?php

                                echo htmlspecialchars(
                                    $student["admitted_at"]
                                );

                                ?>

                            </div>


                        </div>


                    <?php

                        $stackPosition++;

                    }

                    ?>


                </div>


            <?php

            }

            ?>


        </section>



        <!-- =================================================
             FOOTER
             ================================================= -->

        <div class="footer">

            SMART CAMPUS MANAGEMENT SYSTEM

            &nbsp; • &nbsp;

            ADMISSION COMMAND CENTER

            &nbsp; • &nbsp;

            FIFO + LIFO

            &nbsp; • &nbsp;

            VERSION 1.0

        </div>


    </main>


</div>


</body>

</html>

<?php

$conn->close();

?>