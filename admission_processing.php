<?php

session_start();


/* =========================================================
   ADMIN SESSION PROTECTION
   ========================================================= */

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}


require_once "config/database.php";
require_once "config/schema.php";
require_once "config/email_notifications.php";


/* =========================================================
   VARIABLES
   ========================================================= */

$message = "";
$messageType = "";

$currentDate = date("l, d F Y");

$adminName = "Administrator";


if (
    isset($_SESSION["admin_name"]) &&
    $_SESSION["admin_name"] != ""
)
{
    $adminName = $_SESSION["admin_name"];
}

function cms_notify_admission_status($conn, $record, $status, $remarks, $admissionNumber = "")
{
    $studentId = (int)$record["student_id"];
    $applicationId = (int)$record["application_id"];
    $statusMessage = "Your admission status is now " . $status . ".";
    if ($admissionNumber !== "") {
        $statusMessage .= " Your admission number is " . $admissionNumber . ".";
    }
    if (trim($remarks) !== "") {
        $statusMessage .= " Administrator note: " . trim($remarks);
    }

    cms_create_notification(
        $conn,
        $studentId,
        "Admission " . $status,
        $statusMessage,
        "ADMISSION_" . strtoupper($status),
        $applicationId
    );
    cms_send_status_email(
        $record["email"] ?? "",
        "Admission status: " . $status,
        "Hello " . ($record["name"] ?? "Student") . ",\n\n" . $statusMessage
    );
}


/* =========================================================
   ADMISSION PROCESSING ACTION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $processingId = 0;

    $action = "";

    $remarks = "";


    if (isset($_POST["processing_id"]))
    {
        $processingId =
            (int)$_POST["processing_id"];
    }


    if (isset($_POST["action"]))
    {
        $action =
            trim($_POST["action"]);
    }


    if (isset($_POST["remarks"]))
    {
        $remarks =
            trim($_POST["remarks"]);
    }


    /*
       Allowed actions:

       Processing
       Completed
       Rejected
    */

    if (
        $processingId > 0 &&
        (
            $action == "Processing" ||
            $action == "Completed" ||
            $action == "Rejected"
        )
    )
    {

        /* =================================================
           CHECK APPLICATION ELIGIBILITY
           ================================================= */

        $checkSql = "
            SELECT
                ap.id,
                ap.student_id,
                ap.application_id,
                ap.admission_status AS current_status,
                sa.application_number,
                s.name,
                s.email

            FROM admission_processing ap

            INNER JOIN student_applications sa
                ON sa.id = ap.application_id
            INNER JOIN students s
                ON s.id = ap.student_id

            WHERE
                ap.id = ?

                AND sa.application_status = 'Accepted'
        ";


        $check =
            $conn->prepare($checkSql);


        if ($check)
        {

            $check->bind_param(
                "i",
                $processingId
            );


            $check->execute();


            $checkResult =
                $check->get_result();


            $record =
                $checkResult->fetch_assoc();


            $check->close();


            if ($record)
            {

                /* =================================================
                   COMPLETE ADMISSION
                   ================================================= */

                if ($action == "Completed")
                {

                    /*
                       Before completing admission,
                       every submitted document must
                       be verified.
                    */

                    $documentSql = "
                        SELECT

                            COUNT(*) AS total,

                            SUM(
                                CASE
                                    WHEN verification_status =
                                    'Verified'
                                    THEN 1
                                    ELSE 0
                                END
                            ) AS verified

                        FROM student_documents

                        WHERE application_id = ?
                    ";


                    $docs =
                        $conn->prepare(
                            $documentSql
                        );


                    if ($docs)
                    {

                        $docs->bind_param(
                            "i",
                            $record["application_id"]
                        );


                        $docs->execute();


                        $documentResult =
                            $docs->get_result();


                        $documentData =
                            $documentResult->fetch_assoc();


                        $docs->close();


                        $totalDocuments =
                            (int)$documentData["total"];


                        $verifiedDocuments =
                            (int)$documentData["verified"];


                        /*
                           Do not complete admission
                           if no documents exist or
                           not every document is verified.
                        */

                        if (
                            $totalDocuments == 0 ||
                            $verifiedDocuments !=
                            $totalDocuments
                        )
                        {

                            $message =
                                "Admission cannot be completed until all submitted documents are verified.";

                            $messageType =
                                "error";

                        }
                        else
                        {

                            /* =================================================
                               GENERATE ADMISSION NUMBER
                               ================================================= */

                            $admissionNumber =
                                "ADM" .
                                date("Y") .
                                strtoupper(
                                    substr(
                                        uniqid(),
                                        -6
                                    )
                                );


                            /* =================================================
                               UPDATE ADMISSION
                               ================================================= */

                            $updateSql = "
                                UPDATE admission_processing

                                SET
                                    admission_status = 'Completed',

                                    admission_number = ?,

                                    admin_remarks = ?,

                                    processed_at = NOW()

                                WHERE id = ?
                            ";


                            $stmt =
                                $conn->prepare(
                                    $updateSql
                                );


                            if ($stmt)
                            {

                                $stmt->bind_param(
                                    "ssi",
                                    $admissionNumber,
                                    $remarks,
                                    $processingId
                                );


                                if ($stmt->execute())
                                {
                                    if (($record["current_status"] ?? "") !== "Completed") {
                                        cms_notify_admission_status(
                                            $conn,
                                            $record,
                                            "Completed",
                                            $remarks,
                                            $admissionNumber
                                        );
                                    }

                                    $message =
                                        "Admission completed successfully. Admission number: " .
                                        $admissionNumber;

                                    $messageType =
                                        "success";

                                }
                                else
                                {

                                    $message =
                                        "Unable to complete admission.";

                                    $messageType =
                                        "error";

                                }


                                $stmt->close();

                            }
                            else
                            {

                                $message =
                                    "Unable to prepare admission completion request.";

                                $messageType =
                                    "error";

                            }

                        }

                    }
                    else
                    {

                        $message =
                            "Unable to check student documents.";

                        $messageType =
                            "error";

                    }

                }


                /* =================================================
                   PROCESSING / REJECTED
                   ================================================= */

                else
                {

                    $updateSql = "
                        UPDATE admission_processing

                        SET
                            admission_status = ?,

                            admin_remarks = ?

                        WHERE id = ?
                    ";


                    $stmt =
                        $conn->prepare(
                            $updateSql
                        );


                    if ($stmt)
                    {

                        $stmt->bind_param(
                            "ssi",
                            $action,
                            $remarks,
                            $processingId
                        );


                        if ($stmt->execute())
                        {
                            if (($record["current_status"] ?? "") !== $action) {
                                cms_notify_admission_status(
                                    $conn,
                                    $record,
                                    $action,
                                    $remarks
                                );
                            }

                            $message =
                                "Admission processing status updated successfully.";

                            $messageType =
                                "success";

                        }
                        else
                        {

                            $message =
                                "Unable to update admission processing status.";

                            $messageType =
                                "error";

                        }


                        $stmt->close();

                    }
                    else
                    {

                        $message =
                            "Unable to prepare admission processing request.";

                        $messageType =
                            "error";

                    }

                }

            }
            else
            {

                $message =
                    "This admission is not eligible for processing.";

                $messageType =
                    "error";

            }

        }
        else
        {

            $message =
                "Unable to verify admission record.";

            $messageType =
                "error";

        }

    }
    else
    {

        $message =
            "Invalid admission processing request.";

        $messageType =
            "error";

    }
}


/* =========================================================
   GET ADMISSION PROCESSING RECORDS
   ========================================================= */

$records = false;


$sql = "
    SELECT

        ap.id,

        ap.student_id,

        ap.application_id,

        ap.admission_status,

        ap.admission_number,

        ap.admin_remarks,

        sa.application_number,

        sa.program_type,

        sa.course_name,

        s.roll_no,

        s.name,

        (
            SELECT COUNT(*)

            FROM student_documents sd

            WHERE
                sd.application_id =
                ap.application_id

        ) AS total_docs,

        (
            SELECT COUNT(*)

            FROM student_documents sd

            WHERE
                sd.application_id =
                ap.application_id

                AND sd.verification_status =
                'Verified'

        ) AS verified_docs

    FROM admission_processing ap

    INNER JOIN student_applications sa
        ON sa.id = ap.application_id

    INNER JOIN students s
        ON s.id = ap.student_id

    ORDER BY
        ap.created_at DESC
";


$records =
    $conn->query($sql);


/* =========================================================
   STATISTICS
   ========================================================= */

$totalAdmissions = 0;

$pendingAdmissions = 0;

$processingAdmissions = 0;

$completedAdmissions = 0;

$rejectedAdmissions = 0;


$countResult =
    $conn->query(
        "
        SELECT

            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN admission_status = 'Pending'
                    THEN 1
                    ELSE 0
                END
            ) AS pending,

            SUM(
                CASE
                    WHEN admission_status = 'Processing'
                    THEN 1
                    ELSE 0
                END
            ) AS processing,

            SUM(
                CASE
                    WHEN admission_status = 'Completed'
                    THEN 1
                    ELSE 0
                END
            ) AS completed,

            SUM(
                CASE
                    WHEN admission_status = 'Rejected'
                    THEN 1
                    ELSE 0
                END
            ) AS rejected

        FROM admission_processing
        "
    );


if ($countResult)
{
    $countRow =
        $countResult->fetch_assoc();


    $totalAdmissions =
        (int)$countRow["total"];


    $pendingAdmissions =
        (int)$countRow["pending"];


    $processingAdmissions =
        (int)$countRow["processing"];


    $completedAdmissions =
        (int)$countRow["completed"];


    $rejectedAdmissions =
        (int)$countRow["rejected"];
}

?>


<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >


    <title>
        Admission Processing | Smart Campus
    </title>


    <style>


        /* =================================================
           GLOBAL
           ================================================= */

        *
        {
            box-sizing: border-box;
        }


        :root
        {
            --bg: #070b17;

            --panel: #0d1324;

            --panel-light: #111a2e;

            --border:
                rgba(148,163,184,0.14);

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

            backdrop-filter:
                blur(18px);

            border-right:
                1px solid var(--border);

            padding:
                24px 16px;

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


        /* =================================================
           NAVIGATION
           ================================================= */

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

            padding:
                12px 13px;

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
                28px 34px 40px;
        }


        /* =================================================
           TOPBAR
           ================================================= */

        .topbar
        {
            display: flex;

            align-items: center;

            justify-content:
                space-between;

            margin-bottom: 22px;
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
           BACK BUTTON
           ================================================= */

        .back
        {
            display: inline-block;

            text-decoration: none;

            color: #60a5fa;

            font-size: 12px;

            margin-bottom: 18px;

            padding:
                8px 12px;

            border-radius: 8px;

            border:
                1px solid
                rgba(59,130,246,0.15);

            background:
                rgba(59,130,246,0.05);

            transition: 0.2s;
        }


        .back:hover
        {
            background:
                rgba(59,130,246,0.12);
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

            margin-bottom: 22px;

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

            max-width: 760px;

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

            gap: 18px;

            margin-bottom: 22px;
        }


        .stat
        {
            background:
                rgba(13,19,36,0.86);

            border:
                1px solid var(--border);

            border-radius: 15px;

            padding: 20px;

            transition: 0.2s;
        }


        .stat:hover
        {
            transform:
                translateY(-4px);

            border-color:
                rgba(96,165,250,0.25);
        }


        .stat-top
        {
            display: flex;

            justify-content:
                space-between;

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


        .yellow-icon
        {
            background:
                rgba(245,158,11,0.12);

            color: #fbbf24;
        }


        .green-icon
        {
            background:
                rgba(34,197,94,0.12);

            color: #4ade80;
        }


        .red-icon
        {
            background:
                rgba(239,68,68,0.12);

            color: #f87171;
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

            margin-top: 17px;

            letter-spacing: -1px;
        }


        .stat-meta
        {
            margin-top: 7px;

            color: #64748b;

            font-size: 10px;
        }


        /* =================================================
           MESSAGE
           ================================================= */

        .message
        {
            padding:
                13px 16px;

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
                1px solid
                rgba(34,197,94,0.18);
        }


        .message.error
        {
            color: #fca5a5;

            background:
                rgba(239,68,68,0.08);

            border:
                1px solid
                rgba(239,68,68,0.18);
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

            justify-content:
                space-between;

            align-items: center;

            padding:
                19px 20px;

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
           TABLE
           ================================================= */

        .table-container
        {
            overflow-x: auto;
        }


        table
        {
            width: 100%;

            border-collapse:
                collapse;

            min-width: 1250px;
        }


        th
        {
            text-align: left;

            padding:
                14px 16px;

            color: #64748b;

            font-size: 10px;

            font-weight: 600;

            border-bottom:
                1px solid var(--border);

            background:
                rgba(255,255,255,0.015);
        }


        td
        {
            padding:
                15px 16px;

            color: #cbd5e1;

            font-size: 11px;

            border-bottom:
                1px solid
                rgba(148,163,184,0.07);

            vertical-align: middle;
        }


        tr:hover td
        {
            background:
                rgba(59,130,246,0.035);
        }


        .student-name
        {
            color: white;

            font-weight: 600;

            margin-bottom: 4px;
        }


        .student-meta
        {
            color: #64748b;

            font-size: 9px;

            line-height: 1.6;
        }


        .application-number
        {
            color: #60a5fa;

            font-size: 10px;

            font-weight: 600;
        }


        .program
        {
            color: #94a3b8;

            font-size: 9px;

            margin-top: 4px;
        }


        .course-name
        {
            color: #e2e8f0;

            font-weight: 600;

            margin-bottom: 4px;
        }


        /* =================================================
           DOCUMENT STATUS
           ================================================= */

        .documents
        {
            min-width: 130px;
        }


        .document-count
        {
            color: #cbd5e1;

            font-size: 10px;

            font-weight: 600;

            margin-bottom: 7px;
        }


        .progress
        {
            width: 110px;

            height: 6px;

            background:
                rgba(148,163,184,0.10);

            border-radius: 999px;

            overflow: hidden;
        }


        .progress-bar
        {
            height: 100%;

            border-radius: 999px;

            background:
                linear-gradient(
                    90deg,
                    #2563eb,
                    #06b6d4
                );
        }


        .document-ready
        {
            color: #4ade80;

            font-size: 9px;

            margin-top: 5px;
        }


        .document-pending
        {
            color: #fbbf24;

            font-size: 9px;

            margin-top: 5px;
        }


        /* =================================================
           STATUS
           ================================================= */

        .status
        {
            display: inline-block;

            padding:
                6px 10px;

            border-radius: 999px;

            font-size: 9px;

            font-weight: 600;

            border: 1px solid transparent;
        }


        .status-pending
        {
            color: #fbbf24;

            background:
                rgba(245,158,11,0.10);

            border-color:
                rgba(245,158,11,0.15);
        }


        .status-processing
        {
            color: #60a5fa;

            background:
                rgba(59,130,246,0.10);

            border-color:
                rgba(59,130,246,0.15);
        }


        .status-completed
        {
            color: #4ade80;

            background:
                rgba(34,197,94,0.10);

            border-color:
                rgba(34,197,94,0.15);
        }


        .status-rejected
        {
            color: #f87171;

            background:
                rgba(239,68,68,0.10);

            border-color:
                rgba(239,68,68,0.15);
        }


        /* =================================================
           ADMISSION NUMBER
           ================================================= */

        .admission-number
        {
            color: #a78bfa;

            font-size: 10px;

            font-weight: 600;
        }


        .no-admission-number
        {
            color: #475569;

            font-size: 10px;
        }


        /* =================================================
           ACTION AREA
           ================================================= */

        .remarks
        {
            width: 180px;

            min-height: 34px;

            resize: vertical;

            padding:
                8px 10px;

            border-radius: 8px;

            border:
                1px solid
                rgba(148,163,184,0.14);

            background:
                #111a2e;

            color: white;

            outline: none;

            font-family: inherit;

            font-size: 10px;
        }


        .remarks:focus
        {
            border-color:
                rgba(59,130,246,0.45);
        }


        .action-buttons
        {
            display: flex;

            gap: 6px;

            margin-top: 7px;

            flex-wrap: wrap;
        }


        .btn
        {
            border: none;

            border-radius: 8px;

            padding:
                8px 11px;

            color: white;

            font-size: 10px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.2s;
        }


        .btn:hover
        {
            transform:
                translateY(-1px);
        }


        .processing-btn
        {
            background:
                rgba(59,130,246,0.16);

            color: #60a5fa;

            border:
                1px solid
                rgba(59,130,246,0.20);
        }


        .processing-btn:hover
        {
            background:
                rgba(59,130,246,0.25);
        }


        .complete-btn
        {
            background:
                rgba(34,197,94,0.16);

            color: #4ade80;

            border:
                1px solid
                rgba(34,197,94,0.20);
        }


        .complete-btn:hover
        {
            background:
                rgba(34,197,94,0.25);
        }


        .reject-btn
        {
            background:
                rgba(239,68,68,0.14);

            color: #f87171;

            border:
                1px solid
                rgba(239,68,68,0.20);
        }


        .reject-btn:hover
        {
            background:
                rgba(239,68,68,0.24);
        }


        .completed-remarks
        {
            max-width: 210px;

            color: #94a3b8;

            font-size: 10px;

            line-height: 1.5;
        }


        .completed-label
        {
            color: #4ade80;

            font-size: 9px;

            margin-bottom: 5px;

            font-weight: 600;
        }


        .rejected-label
        {
            color: #f87171;

            font-size: 9px;

            margin-bottom: 5px;

            font-weight: 600;
        }


        .processing-label
        {
            color: #60a5fa;

            font-size: 9px;

            margin-bottom: 5px;

            font-weight: 600;
        }


        /* =================================================
           EMPTY
           ================================================= */

        .empty
        {
            text-align: center;

            padding:
                55px 20px;

            color: #64748b;
        }


        .empty-icon
        {
            font-size: 32px;

            margin-bottom: 10px;

            opacity: 0.6;
        }


        .empty strong
        {
            display: block;

            color: #cbd5e1;

            font-size: 13px;
        }


        .empty p
        {
            font-size: 10px;

            margin-top: 6px;
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

        @media (max-width: 1200px)
        {
            .stats
            {
                grid-template-columns:
                    repeat(2, 1fr);
            }
        }


        @media (max-width: 900px)
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

                padding: 20px;
            }


            .topbar
            {
                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }


            .top-right
            {
                width: 100%;

                justify-content:
                    space-between;
            }
        }


        @media (max-width: 600px)
        {
            .stats
            {
                grid-template-columns: 1fr;
            }


            .top-right
            {
                flex-direction: column;

                align-items: flex-start;
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
                    Admission Processing
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
                            substr(
                                $adminName,
                                0,
                                1
                            )
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
             BACK BUTTON
             ================================================= -->

        <a
            href="dashboard.php"
            class="back"
        >

            ← Back to Dashboard

        </a>



        <!-- =================================================
             HERO
             ================================================= -->

        <section class="hero">


            <h2>

                Admission Processing Center

            </h2>


            <p>

                Process accepted student applications,
                verify document completion and finalize
                student admissions from one unified
                administrative workspace.

            </p>


        </section>



        <!-- =================================================
             STATISTICS
             ================================================= -->

        <section class="stats">


            <!-- TOTAL -->

            <div class="stat">


                <div class="stat-top">

                    <span class="stat-label">
                        TOTAL ADMISSIONS
                    </span>


                    <span class="stat-icon blue-icon">
                        ✓
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $totalAdmissions;

                    ?>

                </div>


                <div class="stat-meta">
                    All admission records
                </div>


            </div>



            <!-- PENDING -->

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

                    <?php

                    echo $pendingAdmissions;

                    ?>

                </div>


                <div class="stat-meta">
                    Awaiting processing
                </div>


            </div>



            <!-- PROCESSING -->

            <div class="stat">


                <div class="stat-top">

                    <span class="stat-label">
                        PROCESSING
                    </span>


                    <span class="stat-icon blue-icon">
                        ↻
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $processingAdmissions;

                    ?>

                </div>


                <div class="stat-meta">
                    Currently being processed
                </div>


            </div>



            <!-- COMPLETED -->

            <div class="stat">


                <div class="stat-top">

                    <span class="stat-label">
                        COMPLETED
                    </span>


                    <span class="stat-icon green-icon">
                        ✓
                    </span>

                </div>


                <div class="stat-value">

                    <?php

                    echo $completedAdmissions;

                    ?>

                </div>


                <div class="stat-meta">
                    Successfully admitted
                </div>


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
                <?php
                    echo htmlspecialchars(
                        $messageType
                    );
                ?>"
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


                echo htmlspecialchars(
                    $message
                );

                ?>

            </div>

        <?php

        }

        ?>



        <!-- =================================================
             ADMISSION PIPELINE
             ================================================= -->

        <section class="panel">


            <div class="panel-header">


                <h3>
                    Admission Pipeline
                </h3>


                <span>
                    ADMISSION PROCESSING
                </span>


            </div>



            <div class="table-container">


                <table>


                    <thead>

                        <tr>

                            <th>
                                STUDENT
                            </th>

                            <th>
                                APPLICATION
                            </th>

                            <th>
                                COURSE
                            </th>

                            <th>
                                DOCUMENTS
                            </th>

                            <th>
                                STATUS
                            </th>

                            <th>
                                ADMISSION NO.
                            </th>

                            <th>
                                ACTION
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    if (
                        $records &&
                        $records->num_rows > 0
                    )
                    {

                        while (
                            $row =
                            $records->fetch_assoc()
                        )
                        {


                            $status =
                                $row[
                                    "admission_status"
                                ];


                            $statusClass =
                                "status-pending";


                            if (
                                $status ==
                                "Processing"
                            )
                            {
                                $statusClass =
                                    "status-processing";
                            }
                            elseif (
                                $status ==
                                "Completed"
                            )
                            {
                                $statusClass =
                                    "status-completed";
                            }
                            elseif (
                                $status ==
                                "Rejected"
                            )
                            {
                                $statusClass =
                                    "status-rejected";
                            }


                            $totalDocs =
                                (int)$row[
                                    "total_docs"
                                ];


                            $verifiedDocs =
                                (int)$row[
                                    "verified_docs"
                                ];


                            $documentsReady =

                                $totalDocs > 0 &&

                                $verifiedDocs ==
                                $totalDocs;


                            $progress =
                                0;


                            if (
                                $totalDocs > 0
                            )
                            {

                                $progress =
                                    (
                                        $verifiedDocs /
                                        $totalDocs
                                    ) * 100;

                            }


                    ?>


                        <tr>


                            <!-- =================================================
                                 STUDENT
                                 ================================================= -->

                            <td>


                                <div
                                    class="student-name"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $row["name"]
                                    );

                                    ?>

                                </div>


                                <div
                                    class="student-meta"
                                >

                                    Roll No:
                                    <?php

                                    echo htmlspecialchars(
                                        $row["roll_no"]
                                    );

                                    ?>

                                </div>


                            </td>



                            <!-- =================================================
                                 APPLICATION
                                 ================================================= -->

                            <td>


                                <div
                                    class="application-number"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $row[
                                            "application_number"
                                        ]
                                    );

                                    ?>

                                </div>


                                <div
                                    class="program"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $row[
                                            "program_type"
                                        ]
                                    );

                                    ?>

                                </div>


                            </td>



                            <!-- =================================================
                                 COURSE
                                 ================================================= -->

                            <td>


                                <div
                                    class="course-name"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $row[
                                            "course_name"
                                        ]
                                    );

                                    ?>

                                </div>


                            </td>



                            <!-- =================================================
                                 DOCUMENTS
                                 ================================================= -->

                            <td>


                                <div
                                    class="documents"
                                >


                                    <div
                                        class="document-count"
                                    >

                                        <?php

                                        echo $verifiedDocs;

                                        ?>

                                        /

                                        <?php

                                        echo $totalDocs;

                                        ?>

                                        verified

                                    </div>


                                    <div
                                        class="progress"
                                    >

                                        <div
                                            class="progress-bar"
                                            style="width:
                                                <?php
                                                    echo $progress;
                                                ?>%;"
                                        ></div>

                                    </div>


                                    <?php

                                    if (
                                        $documentsReady
                                    )
                                    {

                                    ?>

                                        <div
                                            class="document-ready"
                                        >

                                            ✓ All documents verified

                                        </div>

                                    <?php

                                    }
                                    else
                                    {

                                    ?>

                                        <div
                                            class="document-pending"
                                        >

                                            ◷ Verification pending

                                        </div>

                                    <?php

                                    }

                                    ?>

                                </div>


                            </td>



                            <!-- =================================================
                                 STATUS
                                 ================================================= -->

                            <td>


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



                            <!-- =================================================
                                 ADMISSION NUMBER
                                 ================================================= -->

                            <td>


                                <?php

                                if (
                                    isset(
                                        $row[
                                            "admission_number"
                                        ]
                                    ) &&
                                    $row[
                                        "admission_number"
                                    ] != ""
                                )
                                {

                                ?>

                                    <div
                                        class="admission-number"
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $row[
                                                "admission_number"
                                            ]
                                        );

                                        ?>

                                    </div>

                                <?php

                                }
                                else
                                {

                                ?>

                                    <div
                                        class="
                                        no-admission-number"
                                    >

                                        Not assigned

                                    </div>

                                <?php

                                }

                                ?>


                            </td>



                            <!-- =================================================
                                 ACTION
                                 ================================================= -->

                            <td>


                            <?php

                            /*
                               Completed and Rejected records
                               cannot be processed again.
                            */

                            if (
                                $status != "Completed" &&
                                $status != "Rejected"
                            )
                            {

                            ?>


                                <form
                                    method="POST"
                                >


                                    <input
                                        type="hidden"
                                        name="processing_id"
                                        value="<?php
                                            echo (int)
                                                $row["id"];
                                        ?>"
                                    >


                                    <textarea
                                        name="remarks"
                                        class="remarks"
                                        placeholder="Optional remarks..."
                                    ></textarea>


                                    <div
                                        class="action-buttons"
                                    >


                                        <!-- PROCESSING -->

                                        <button
                                            type="submit"
                                            name="action"
                                            value="Processing"
                                            class="
                                            btn
                                            processing-btn"
                                        >

                                            ↻ Processing

                                        </button>



                                        <!-- COMPLETE -->

                                        <?php

                                        /*
                                           Complete button is shown
                                           only when all documents
                                           have been verified.
                                        */

                                        if (
                                            $documentsReady
                                        )
                                        {

                                        ?>

                                            <button
                                                type="submit"
                                                name="action"
                                                value="Completed"
                                                class="
                                                btn
                                                complete-btn"
                                            >

                                                ✓ Complete

                                            </button>

                                        <?php

                                        }

                                        ?>


                                        <!-- REJECT -->

                                        <button
                                            type="submit"
                                            name="action"
                                            value="Rejected"
                                            class="
                                            btn
                                            reject-btn"
                                        >

                                            × Reject

                                        </button>


                                    </div>


                                </form>


                            <?php

                            }
                            else
                            {

                            ?>


                                <div
                                    class="completed-remarks"
                                >


                                    <?php

                                    if (
                                        $status ==
                                        "Completed"
                                    )
                                    {

                                    ?>

                                        <div
                                            class="completed-label"
                                        >

                                            ✓ Admission Completed

                                        </div>

                                    <?php

                                    }
                                    elseif (
                                        $status ==
                                        "Rejected"
                                    )
                                    {

                                    ?>

                                        <div
                                            class="rejected-label"
                                        >

                                            × Admission Rejected

                                        </div>

                                    <?php

                                    }


                                    if (
                                        isset(
                                            $row[
                                                "admin_remarks"
                                            ]
                                        ) &&
                                        $row[
                                            "admin_remarks"
                                        ] != ""
                                    )
                                    {

                                        echo nl2br(
                                            htmlspecialchars(
                                                $row[
                                                    "admin_remarks"
                                                ]
                                            )
                                        );

                                    }
                                    else
                                    {

                                        echo "No remarks";

                                    }

                                    ?>


                                </div>


                            <?php

                            }

                            ?>


                            </td>


                        </tr>


                    <?php

                        }

                    }
                    else
                    {

                    ?>


                        <tr>

                            <td
                                colspan="7"
                            >


                                <div class="empty">


                                    <div
                                        class="empty-icon"
                                    >

                                        ✓

                                    </div>


                                    <strong>

                                        No Admission Processing Records

                                    </strong>


                                    <p>

                                        Accepted student applications
                                        will appear here for
                                        admission processing.

                                    </p>


                                </div>


                            </td>

                        </tr>


                    <?php

                    }

                    ?>


                    </tbody>


                </table>


            </div>


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


</body>

</html>


<?php

$conn->close();

?>