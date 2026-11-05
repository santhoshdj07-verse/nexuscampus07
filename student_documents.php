<?php

session_start();


/* =========================================================
   STUDENT SESSION PROTECTION
   ========================================================= */

if (!isset($_SESSION["student_id"]))
{
    header("Location: login.php?role=student");
    exit();
}


require_once "config/database.php";


/* =========================================================
   VARIABLES
   ========================================================= */

$studentId = (int)$_SESSION["student_id"];

$message = "";
$messageType = "";

$student = null;

$documents = array();


/* =========================================================
   UPLOAD DIRECTORY
   ========================================================= */

$uploadDirectory = "uploads/student_documents/";


if (!is_dir($uploadDirectory))
{
    mkdir($uploadDirectory, 0777, true);
}


/* =========================================================
   GET STUDENT DETAILS
   ========================================================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        roll_no,
        name,
        department,
        semester,
        phone,
        cgpa
     FROM students
     WHERE id = ?
     LIMIT 1"
);


if ($stmt)
{
    $stmt->bind_param(
        "i",
        $studentId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows == 1)
    {
        $student = $result->fetch_assoc();
    }

    $stmt->close();
}


/* =========================================================
   INVALID STUDENT
   ========================================================= */

if (!$student)
{
    session_destroy();

    header("Location: login.php?role=student");

    exit();
}


/* =========================================================
   GET LATEST STUDENT APPLICATION
   ========================================================= */

$application = null;
$applicationId = null;
$programType = "UG";
$documentsLocked = false;
$lockReason = "No active application found.";

$stmt = $conn->prepare(
    "SELECT *
     FROM student_applications
     WHERE student_id = ?
     ORDER BY id DESC
     LIMIT 1"
);

if ($stmt)
{
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0)
    {
        $application = $result->fetch_assoc();
        $applicationId = (int)$application["id"];
        
        if (isset($application["program_type"]))
        {
            $programType = strtoupper(trim($application["program_type"]));
        }
        
        /* Check if application is accepted and verified */
        $appStatus = $application["application_status"] ?? "";
        $verifiedAt = $application["verified_at"] ?? null;
        
        if ($appStatus !== "Accepted" || !$verifiedAt)
        {
            $documentsLocked = true;
            $lockReason = "Your application must be accepted before you can submit documents.";
        }
    }
    else
    {
        $documentsLocked = true;
        $lockReason = "Please submit an admission application first.";
    }
    
    $stmt->close();
}


/* =========================================================
   DOCUMENT TYPES (DYNAMIC BY PROGRAM)
   ========================================================= */

$requiredDocuments = cms_required_documents($programType);


/* =========================================================
   DOCUMENT UPLOAD
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] == "POST")
{

    if ($documentsLocked)
    {
        $message = "Document submission is currently disabled. " . $lockReason;
        $messageType = "error";
    }
    elseif (!isset($_POST["document_type"]))
    {
        $message =
            "Please select a document type.";

        $messageType =
            "error";
    }
    elseif (!isset($_FILES["document_file"]))
    {
        $message =
            "Please select a document file.";

        $messageType =
            "error";
    }
    else
    {

        $documentType =
            trim($_POST["document_type"]);


        /* =================================================
           CHECK VALID DOCUMENT TYPE
           ================================================= */

        if (!in_array(
            $documentType,
            $requiredDocuments
        ))
        {
            $message =
                "Invalid document type.";

            $messageType =
                "error";
        }
        else
        {

            $file =
                $_FILES["document_file"];


            /* =============================================
               CHECK UPLOAD ERROR
               ============================================= */

            if ($file["error"] != 0)
            {
                $message =
                    "There was an error uploading the document.";

                $messageType =
                    "error";
            }
            else
            {

                /* =========================================
                   FILE SIZE
                   Maximum 5 MB
                   ========================================= */

                $maxFileSize =
                    5 * 1024 * 1024;


                if ($file["size"] > $maxFileSize)
                {
                    $message =
                        "File size must not exceed 5 MB.";

                    $messageType =
                        "error";
                }
                else
                {

                    /* =====================================
                       FILE EXTENSION
                       ===================================== */

                    $originalName =
                        $file["name"];


                    $extension =
                        strtolower(
                            pathinfo(
                                $originalName,
                                PATHINFO_EXTENSION
                            )
                        );


                    $allowedExtensions =
                        array(
                            "pdf",
                            "jpg",
                            "jpeg",
                            "png"
                        );


                    if (!in_array(
                        $extension,
                        $allowedExtensions
                    ))
                    {
                        $message =
                            "Only PDF, JPG, JPEG and PNG files are allowed.";

                        $messageType =
                            "error";
                    }
                    else
                    {

                        /* =================================
                           CHECK MIME TYPE
                           ================================= */

                        $fileType =
                            mime_content_type(
                                $file["tmp_name"]
                            );


                        $allowedMimeTypes =
                            array(
                                "application/pdf",
                                "image/jpeg",
                                "image/png"
                            );


                        if (!in_array(
                            $fileType,
                            $allowedMimeTypes
                        ))
                        {
                            $message =
                                "Invalid file type.";

                            $messageType =
                                "error";
                        }
                        else
                        {

                            /* =============================
                               CHECK EXISTING DOCUMENT
                               ============================= */

                            $existingDocument =
                                null;


                            $stmt =
                                $conn->prepare(
                                    "SELECT *
                                     FROM student_documents
                                     WHERE student_id = ?
                                     AND document_type = ?
                                     LIMIT 1"
                                );


                            if ($stmt)
                            {
                                $stmt->bind_param(
                                    "is",
                                    $studentId,
                                    $documentType
                                );

                                $stmt->execute();

                                $result =
                                    $stmt->get_result();


                                if (
                                    $result &&
                                    $result->num_rows > 0
                                )
                                {
                                    $existingDocument =
                                        $result->fetch_assoc();
                                }


                                $stmt->close();
                            }


                            /* =============================
                               PREVENT VERIFIED DOCUMENT
                               ============================= */

                            if (
                                $existingDocument &&
                                $existingDocument[
                                    "verification_status"
                                ] == "Verified"
                            )
                            {
                                $message =
                                    "This document has already been verified and cannot be replaced.";

                                $messageType =
                                    "error";
                            }
                            else
                            {

                                /* =============================
                                   CREATE UNIQUE FILE NAME
                                   ============================= */

                                $safeDocumentType =
                                    preg_replace(
                                        "/[^A-Za-z0-9]/",
                                        "_",
                                        $documentType
                                    );


                                $newFileName =
                                    "STUDENT_" .
                                    $studentId .
                                    "_" .
                                    $safeDocumentType .
                                    "_" .
                                    time() .
                                    "_" .
                                    rand(1000,9999) .
                                    "." .
                                    $extension;


                                $destination =
                                    $uploadDirectory .
                                    $newFileName;


                                /* =============================
                                   MOVE FILE
                                   ============================= */

                                if (
                                    move_uploaded_file(
                                        $file["tmp_name"],
                                        $destination
                                    )
                                )
                                {

                                    /* =========================
                                       EXISTING DOCUMENT
                                       ========================= */

                                    if ($existingDocument)
                                    {

                                        /*
                                           Delete old physical file
                                           if it exists.
                                        */

                                        if (
                                            isset(
                                                $existingDocument[
                                                    "file_path"
                                                ]
                                            ) &&
                                            file_exists(
                                                $existingDocument[
                                                    "file_path"
                                                ]
                                            )
                                        )
                                        {
                                            unlink(
                                                $existingDocument[
                                                    "file_path"
                                                ]
                                            );
                                        }


                                        /* =====================
                                           UPDATE DOCUMENT
                                           ===================== */

                                        $verificationStatus =
                                            "Pending";


                                        $remarks = "";


                                        $stmt =
                                            $conn->prepare(
                                                "UPDATE student_documents
                                                 SET
                                                    application_id = ?,
                                                    file_name = ?,
                                                    file_path = ?,
                                                    verification_status = ?,
                                                    remarks = ?
                                                 WHERE
                                                    id = ?
                                                    AND student_id = ?"
                                            );


                                        if ($stmt)
                                        {

                                            $documentId =
                                                (int)
                                                $existingDocument[
                                                    "id"
                                                ];


                                            $stmt->bind_param(
                                                "issssi",
                                                $applicationId,
                                                $newFileName,
                                                $destination,
                                                $verificationStatus,
                                                $remarks,
                                                $documentId,
                                                $studentId
                                            );


                                            if (
                                                $stmt->execute()
                                            )
                                            {
                                                $message =
                                                    "Document re-uploaded successfully and is waiting for verification.";

                                                $messageType =
                                                    "success";
                                            }
                                            else
                                            {
                                                $message =
                                                    "Unable to update document record.";

                                                $messageType =
                                                    "error";
                                            }


                                            $stmt->close();
                                        }
                                        else
                                        {
                                            $message =
                                                "Unable to prepare document update.";

                                            $messageType =
                                                "error";
                                        }

                                    }

                                    /* =========================
                                       NEW DOCUMENT
                                       ========================= */

                                    else
                                    {

                                        $verificationStatus =
                                            "Pending";


                                        $remarks = "";


                                        $stmt =
                                            $conn->prepare(
                                                "INSERT INTO student_documents
                                                (
                                                    application_id,
                                                    student_id,
                                                    document_type,
                                                    file_name,
                                                    file_path,
                                                    verification_status,
                                                    remarks
                                                )
                                                VALUES
                                                (
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?
                                                )"
                                            );


                                        if ($stmt)
                                        {

                                            $stmt->bind_param(
                                                "ississs",
                                                $applicationId,
                                                $studentId,
                                                $documentType,
                                                $newFileName,
                                                $destination,
                                                $verificationStatus,
                                                $remarks
                                            );


                                            if (
                                                $stmt->execute()
                                            )
                                            {
                                                $message =
                                                    "Document uploaded successfully and is waiting for verification.";

                                                $messageType =
                                                    "success";
                                            }
                                            else
                                            {
                                                $message =
                                                    "Unable to save document information.";

                                                $messageType =
                                                    "error";
                                            }


                                            $stmt->close();
                                        }
                                        else
                                        {
                                            $message =
                                                "Unable to prepare document upload.";

                                            $messageType =
                                                "error";
                                        }

                                    }

                                }
                                else
                                {
                                    $message =
                                        "Unable to move uploaded file.";

                                    $messageType =
                                        "error";
                                }

                            }

                        }

                    }

                }

            }

        }

    }

}


/* =========================================================
   GET ALL STUDENT DOCUMENTS
   ========================================================= */

$stmt = $conn->prepare(
    "SELECT *
     FROM student_documents
     WHERE student_id = ?
     ORDER BY id DESC"
);


if ($stmt)
{
    $stmt->bind_param(
        "i",
        $studentId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();


    if ($result)
    {
        while (
            $row =
            $result->fetch_assoc()
        )
        {
            $documents[] =
                $row;
        }
    }


    $stmt->close();
}


/* =========================================================
   DOCUMENT COUNTS
   ========================================================= */

$totalDocuments =
    count($documents);

$verifiedDocuments =
    0;

$pendingDocuments =
    0;

$rejectedDocuments =
    0;


foreach ($documents as $document)
{

    if (
        $document[
            "verification_status"
        ] == "Verified"
    )
    {
        $verifiedDocuments++;
    }
    elseif (
        $document[
            "verification_status"
        ] == "Rejected"
    )
    {
        $rejectedDocuments++;
    }
    else
    {
        $pendingDocuments++;
    }

}


$requiredCount =
    count($requiredDocuments);


$completionPercentage =
    0;


if ($requiredCount > 0)
{
    $completionPercentage =
        round(
            ($totalDocuments /
            $requiredCount) *
            100
        );
}


if ($completionPercentage > 100)
{
    $completionPercentage =
        100;
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
Student Documents | Smart Campus
</title>


<style>

/* =========================================================
   GLOBAL
   ========================================================= */

*
{
    box-sizing:border-box;
}


:root
{
    --bg:#070b17;

    --panel:rgba(13,19,36,.90);

    --border:rgba(148,163,184,.14);

    --text:#f8fafc;

    --muted:#94a3b8;

    --blue:#3b82f6;

    --cyan:#06b6d4;

    --purple:#8b5cf6;

    --green:#22c55e;

    --red:#ef4444;

    --yellow:#f59e0b;
}


body
{
    margin:0;

    min-height:100vh;

    font-family:
        Inter,
        Segoe UI,
        Arial,
        sans-serif;

    color:var(--text);

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

        radial-gradient(
            circle at 70% 90%,
            rgba(6,182,212,.08),
            transparent 30%
        ),

        #070b17;
}


body::before
{
    content:"";

    position:fixed;

    inset:0;

    pointer-events:none;

    opacity:.16;

    background-image:

        linear-gradient(
            rgba(255,255,255,.025) 1px,
            transparent 1px
        ),

        linear-gradient(
            90deg,
            rgba(255,255,255,.025) 1px,
            transparent 1px
        );

    background-size:40px 40px;
}


/* =========================================================
   CONTAINER
   ========================================================= */

.container
{
    max-width:1200px;

    margin:auto;

    padding:30px;

    position:relative;

    z-index:1;
}


/* =========================================================
   HEADER
   ========================================================= */

.header
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:20px;

    margin-bottom:22px;
}


.page-label
{
    color:#64748b;

    font-size:10px;

    font-weight:bold;

    letter-spacing:1.5px;

    margin-bottom:6px;
}


.title
{
    margin:0;

    font-size:27px;

    letter-spacing:-.6px;
}


.subtitle
{
    color:var(--muted);

    font-size:11px;

    margin-top:6px;
}


.header-actions
{
    display:flex;

    gap:10px;

    flex-wrap:wrap;
}


.header-button
{
    display:inline-block;

    text-decoration:none;

    color:#cbd5e1;

    padding:10px 13px;

    border-radius:9px;

    border:1px solid var(--border);

    background:
        rgba(255,255,255,.025);

    font-size:10px;

    transition:.2s;
}


.header-button:hover
{
    color:white;

    background:
        rgba(59,130,246,.10);

    border-color:
        rgba(59,130,246,.22);
}


.logout
{
    color:#fca5a5;

    border-color:
        rgba(239,68,68,.15);

    background:
        rgba(239,68,68,.06);
}


/* =========================================================
   STUDENT PROFILE
   ========================================================= */

.profile
{
    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:20px;
}


.profile-item
{
    padding:17px;

    border-radius:13px;

    background:
        rgba(13,19,36,.86);

    border:
        1px solid var(--border);
}


.profile-label
{
    color:#64748b;

    font-size:9px;

    font-weight:bold;

    letter-spacing:.8px;

    margin-bottom:7px;
}


.profile-value
{
    color:#e2e8f0;

    font-size:12px;

    font-weight:600;

    word-break:break-word;
}


/* =========================================================
   STATISTICS
   ========================================================= */

.stats
{
    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:20px;
}


.stat
{
    padding:19px;

    border-radius:14px;

    background:
        rgba(13,19,36,.86);

    border:
        1px solid var(--border);

    transition:.2s;
}


.stat:hover
{
    transform:
        translateY(-3px);

    border-color:
        rgba(96,165,250,.25);
}


.stat-label
{
    color:#64748b;

    font-size:9px;

    font-weight:bold;

    letter-spacing:.8px;

    margin-bottom:8px;
}


.stat-number
{
    font-size:24px;

    font-weight:700;
}


.stat-small
{
    color:#64748b;

    font-size:9px;

    margin-top:4px;
}


.stat-total .stat-number
{
    color:#60a5fa;
}


.stat-verified .stat-number
{
    color:#4ade80;
}


.stat-pending .stat-number
{
    color:#fbbf24;
}


.stat-rejected .stat-number
{
    color:#f87171;
}


/* =========================================================
   MESSAGE
   ========================================================= */

.message
{
    padding:13px 16px;

    border-radius:10px;

    margin-bottom:18px;

    font-size:11px;

    line-height:1.5;
}


.message.success
{
    color:#bbf7d0;

    background:
        rgba(34,197,94,.08);

    border:
        1px solid
        rgba(34,197,94,.16);
}


.message.error
{
    color:#fecaca;

    background:
        rgba(239,68,68,.08);

    border:
        1px solid
        rgba(239,68,68,.16);
}


/* =========================================================
   UPLOAD PANEL
   ========================================================= */

.panel
{
    background:
        rgba(13,19,36,.86);

    border:
        1px solid var(--border);

    border-radius:16px;

    overflow:hidden;

    margin-bottom:20px;
}


.panel-header
{
    padding:19px 21px;

    border-bottom:
        1px solid var(--border);
}


.panel-header h2
{
    margin:0;

    font-size:15px;
}


.panel-header p
{
    margin:6px 0 0;

    color:#64748b;

    font-size:10px;
}


.form-body
{
    padding:23px;
}


.form-grid
{
    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:18px;
}


.form-group
{
    display:flex;

    flex-direction:column;
}


.form-group.full
{
    grid-column:
        1 / -1;
}


.form-label
{
    color:#cbd5e1;

    font-size:10px;

    font-weight:bold;

    margin-bottom:8px;

    letter-spacing:.4px;
}


.form-select,
.form-file
{
    width:100%;

    padding:12px 13px;

    border-radius:9px;

    border:
        1px solid
        rgba(148,163,184,.14);

    background:
        rgba(255,255,255,.035);

    color:white;

    outline:none;

    font-family:inherit;

    font-size:11px;

    transition:.2s;
}


.form-select:focus,
.form-file:focus
{
    border-color:
        rgba(59,130,246,.50);

    background:
        rgba(59,130,246,.045);

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.07);
}


.form-select option
{
    background:#111827;

    color:white;
}


.form-file::file-selector-button
{
    border:none;

    padding:8px 12px;

    margin-right:10px;

    border-radius:7px;

    background:
        rgba(59,130,246,.15);

    color:#93c5fd;

    cursor:pointer;
}


.help
{
    color:#64748b;

    font-size:9px;

    margin-top:7px;

    line-height:1.5;
}


.upload-actions
{
    display:flex;

    justify-content:flex-end;

    margin-top:20px;

    padding-top:20px;

    border-top:
        1px solid var(--border);
}


.button
{
    border:none;

    text-decoration:none;

    display:inline-block;

    padding:11px 17px;

    border-radius:9px;

    font-size:10px;

    font-weight:bold;

    cursor:pointer;

    transition:.2s;
}


.primary
{
    color:white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );

    box-shadow:
        0 8px 22px
        rgba(37,99,235,.18);
}


.primary:hover
{
    transform:
        translateY(-2px);

    box-shadow:
        0 12px 30px
        rgba(37,99,235,.28);
}


/* =========================================================
   PROGRESS
   ========================================================= */

.progress-section
{
    padding:20px;

    border-radius:14px;

    margin-bottom:20px;

    background:
        rgba(13,19,36,.86);

    border:
        1px solid var(--border);
}


.progress-top
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:10px;
}


.progress-title
{
    color:#cbd5e1;

    font-size:11px;

    font-weight:bold;
}


.progress-value
{
    color:#60a5fa;

    font-size:11px;

    font-weight:bold;
}


.progress-bar
{
    width:100%;

    height:7px;

    border-radius:20px;

    overflow:hidden;

    background:
        rgba(255,255,255,.06);
}


.progress-fill
{
    height:100%;

    border-radius:20px;

    background:
        linear-gradient(
            90deg,
            #2563eb,
            #06b6d4,
            #8b5cf6
        );

    transition:.4s;
}


/* =========================================================
   DOCUMENT TABLE
   ========================================================= */

.documents-container
{
    overflow-x:auto;
}


.documents-table
{
    width:100%;

    border-collapse:collapse;

    min-width:850px;
}


.documents-table th
{
    text-align:left;

    padding:14px 16px;

    color:#64748b;

    font-size:9px;

    letter-spacing:.7px;

    border-bottom:
        1px solid var(--border);
}


.documents-table td
{
    padding:15px 16px;

    color:#cbd5e1;

    font-size:10px;

    border-bottom:
        1px solid
        rgba(148,163,184,.07);

    vertical-align:middle;
}


.documents-table tr:hover
{
    background:
        rgba(255,255,255,.018);
}


.document-name
{
    color:#f8fafc;

    font-weight:600;

    font-size:11px;
}


.file-name
{
    color:#64748b;

    max-width:180px;

    overflow:hidden;

    text-overflow:ellipsis;

    white-space:nowrap;
}


/* =========================================================
   STATUS
   ========================================================= */

.status
{
    display:inline-block;

    padding:6px 10px;

    border-radius:999px;

    font-size:9px;

    font-weight:bold;
}


.status-pending
{
    color:#fbbf24;

    background:
        rgba(245,158,11,.10);

    border:
        1px solid
        rgba(245,158,11,.16);
}


.status-verified
{
    color:#4ade80;

    background:
        rgba(34,197,94,.10);

    border:
        1px solid
        rgba(34,197,94,.16);
}


.status-rejected
{
    color:#f87171;

    background:
        rgba(239,68,68,.10);

    border:
        1px solid
        rgba(239,68,68,.16);
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert
{
    padding:14px 16px;

    border-radius:10px;

    margin-bottom:16px;

    font-size:13px;

    border:1px solid;
}


.alert strong
{
    display:block;

    margin-bottom:4px;

    font-weight:600;
}


.alert p
{
    margin:0;

    color:inherit;
}


.alert-info
{
    color:#06b6d4;

    background:
        rgba(6,182,212,.10);

    border-color:
        rgba(6,182,212,.20);
}


.alert-success
{
    color:#22c55e;

    background:
        rgba(34,197,94,.10);

    border-color:
        rgba(34,197,94,.20);
}


.alert-warning
{
    color:#f59e0b;

    background:
        rgba(245,158,11,.10);

    border-color:
        rgba(245,158,11,.20);
}


.alert-error
{
    color:#ef4444;

    background:
        rgba(239,68,68,.10);

    border-color:
        rgba(239,68,68,.20);
}


/* =========================================================
   VIEW BUTTON
   ========================================================= */

.view-button
{
    display:inline-block;

    padding:7px 10px;

    border-radius:7px;

    text-decoration:none;

    color:#93c5fd;

    background:
        rgba(59,130,246,.08);

    border:
        1px solid
        rgba(59,130,246,.15);

    font-size:9px;

    margin-right:5px;
}


.view-button:hover
{
    background:
        rgba(59,130,246,.15);
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty
{
    text-align:center;

    padding:45px 20px;

    color:#64748b;

    font-size:11px;
}


.empty-icon
{
    font-size:35px;

    margin-bottom:12px;
}


/* =========================================================
   REQUIREMENTS
   ========================================================= */

.requirements
{
    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:12px;

    padding:20px;
}


.requirement
{
    padding:14px;

    border-radius:10px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(148,163,184,.08);

    color:#cbd5e1;

    font-size:10px;
}


.requirement strong
{
    color:#f8fafc;
}


.requirement span
{
    display:block;

    color:#64748b;

    margin-top:5px;

    font-size:9px;
}


/* =========================================================
   FOOTER
   ========================================================= */

.footer
{
    text-align:center;

    padding:20px 0 5px;

    color:#475569;

    font-size:9px;

    letter-spacing:.4px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:900px)
{

    .profile,
    .stats
    {
        grid-template-columns:
            repeat(2,1fr);
    }

}


@media(max-width:700px)
{

    .container
    {
        padding:20px;
    }


    .header
    {
        flex-direction:column;

        align-items:flex-start;
    }


    .form-grid
    {
        grid-template-columns:1fr;
    }


    .form-group.full
    {
        grid-column:auto;
    }


    .requirements
    {
        grid-template-columns:1fr;
    }

}


@media(max-width:500px)
{

    .profile,
    .stats
    {
        grid-template-columns:1fr;
    }


    .header-actions
    {
        width:100%;
    }


    .header-button
    {
        flex:1;

        text-align:center;
    }


    .form-body
    {
        padding:17px;
    }


    .upload-actions
    {
        justify-content:stretch;
    }


    .button
    {
        width:100%;

        text-align:center;
    }

}

</style>

</head>


<body>


<div class="container">


<!-- =====================================================
     HEADER
     ===================================================== -->

<div class="header">

    <div>

        <div class="page-label">
            STUDENT PORTAL
        </div>

        <h1 class="title">
            Document Submission
        </h1>

        <div class="subtitle">
            SMART CAMPUS MANAGEMENT SYSTEM
        </div>

    </div>


    <div class="header-actions">

        <a
            href="student_dashboard.php"
            class="header-button"
        >
            ← Dashboard
        </a>


        <a
            href="student_application.php"
            class="header-button"
        >
            Admission Application
        </a>


        <a
            href="logout.php"
            class="header-button logout"
        >
            Logout
        </a>

    </div>

</div>



<!-- =====================================================
     STUDENT PROFILE
     ===================================================== -->

<div class="profile">


    <div class="profile-item">

        <div class="profile-label">
            STUDENT NAME
        </div>

        <div class="profile-value">

            <?php

            echo htmlspecialchars(
                $student["name"]
            );

            ?>

        </div>

    </div>


    <div class="profile-item">

        <div class="profile-label">
            ROLL NUMBER
        </div>

        <div class="profile-value">

            <?php

            echo htmlspecialchars(
                $student["roll_no"]
            );

            ?>

        </div>

    </div>


    <div class="profile-item">

        <div class="profile-label">
            DEPARTMENT
        </div>

        <div class="profile-value">

            <?php

            echo htmlspecialchars(
                $student["department"]
            );

            ?>

        </div>

    </div>


    <div class="profile-item">

        <div class="profile-label">
            SEMESTER
        </div>

        <div class="profile-value">

            <?php

            echo htmlspecialchars(
                $student["semester"]
            );

            ?>

        </div>

    </div>


</div>



<!-- =====================================================
     STATISTICS
     ===================================================== -->

<div class="stats">


    <div class="stat stat-total">

        <div class="stat-label">
            UPLOADED
        </div>

        <div class="stat-number">
            <?php echo $totalDocuments; ?>
        </div>

        <div class="stat-small">
            Documents submitted
        </div>

    </div>


    <div class="stat stat-verified">

        <div class="stat-label">
            VERIFIED
        </div>

        <div class="stat-number">
            <?php echo $verifiedDocuments; ?>
        </div>

        <div class="stat-small">
            Documents approved
        </div>

    </div>


    <div class="stat stat-pending">

        <div class="stat-label">
            PENDING
        </div>

        <div class="stat-number">
            <?php echo $pendingDocuments; ?>
        </div>

        <div class="stat-small">
            Awaiting verification
        </div>

    </div>


    <div class="stat stat-rejected">

        <div class="stat-label">
            REJECTED
        </div>

        <div class="stat-number">
            <?php echo $rejectedDocuments; ?>
        </div>

        <div class="stat-small">
            Requires re-upload
        </div>

    </div>


</div>



<!-- =====================================================
     MESSAGE
     ===================================================== -->

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

    echo htmlspecialchars(
        $message
    );

    ?>

</div>

<?php

}

?>



<!-- =====================================================
     PROGRESS
     ===================================================== -->

<div class="progress-section">

    <div class="progress-top">

        <div class="progress-title">
            DOCUMENT SUBMISSION PROGRESS
        </div>

        <div class="progress-value">

            <?php

            echo $totalDocuments;

            echo " / ";

            echo $requiredCount;

            echo " Required";

            ?>

        </div>

    </div>


    <div class="progress-bar">

        <div
            class="progress-fill"
            style="width:
            <?php
            echo $completionPercentage;
            ?>%;"
        ></div>

    </div>

</div>



<!-- =====================================================
     UPLOAD PANEL
     ===================================================== -->

<section class="panel">


    <div class="panel-header">

        <h2>
            Upload Document
        </h2>

        <p>
            Upload your required academic and identity documents.
        </p>

    </div>


    <?php if ($documentsLocked): ?>

    <div class="alert alert-info">

        <strong>
            Document Submission Disabled
        </strong>

        <p>
            <?php echo htmlspecialchars($lockReason); ?>
        </p>

    </div>

    <?php endif; ?>


    <div class="form-body">


        <form
            method="POST"
            action=""
            enctype="multipart/form-data"
        >


            <div class="form-grid">


                <div class="form-group">

                    <label class="form-label">
                        DOCUMENT TYPE
                    </label>


                    <select
                        name="document_type"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Select Document
                        </option>


                        <?php

                        foreach (
                            $requiredDocuments
                            as $requiredDocument
                        )
                        {

                        ?>

                        <option
                            value="<?php
                            echo htmlspecialchars(
                                $requiredDocument
                            );
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $requiredDocument
                            );

                            ?>

                        </option>

                        <?php

                        }

                        ?>

                    </select>

                </div>



                <div class="form-group">

                    <label class="form-label">
                        SELECT FILE
                    </label>


                    <input
                        type="file"
                        name="document_file"
                        class="form-file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                    >


                    <div class="help">

                        Allowed:
                        PDF, JPG, JPEG, PNG
                        &nbsp; • &nbsp;
                        Maximum size: 5 MB

                    </div>

                </div>


            </div>


            <div class="upload-actions">

                <button
                    type="submit"
                    class="button primary"
                >

                    Upload Document

                </button>

            </div>


        </form>


    </div>


</section>



<!-- =====================================================
     DOCUMENT LIST
     ===================================================== -->

<section class="panel">


    <div class="panel-header">

        <h2>
            My Submitted Documents
        </h2>

        <p>
            Track the verification status of your uploaded documents.
        </p>

    </div>


    <?php

    if (count($documents) == 0)
    {

    ?>

        <div class="empty">

            <div class="empty-icon">
                📄
            </div>

            No documents uploaded yet.

            <br>

            Upload your required documents using
            the form above.

        </div>

    <?php

    }
    else
    {

    ?>

    <div class="documents-container">

        <table class="documents-table">


            <thead>

                <tr>

                    <th>
                        DOCUMENT
                    </th>

                    <th>
                        FILE
                    </th>

                    <th>
                        STATUS
                    </th>

                    <th>
                        REMARKS
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


            <?php

            foreach (
                $documents
                as $document
            )
            {

                $documentStatus =
                    isset(
                        $document[
                            "verification_status"
                        ]
                    )
                    ?
                    $document[
                        "verification_status"
                    ]
                    :
                    "Pending";


                $statusClass =
                    "status-pending";


                if (
                    $documentStatus ==
                    "Verified"
                )
                {
                    $statusClass =
                        "status-verified";
                }
                elseif (
                    $documentStatus ==
                    "Rejected"
                )
                {
                    $statusClass =
                        "status-rejected";
                }

            ?>

                <tr>


                    <td>

                        <div class="document-name">

                            <?php

                            echo htmlspecialchars(
                                $document[
                                    "document_type"
                                ]
                            );

                            ?>

                        </div>

                    </td>


                    <td>

                        <div class="file-name">

                            <?php

                            echo htmlspecialchars(
                                $document[
                                    "file_name"
                                ]
                            );

                            ?>

                        </div>

                    </td>


                    <td>

                        <span
                            class="status
                            <?php

                            echo $statusClass;

                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $documentStatus
                            );

                            ?>

                        </span>

                    </td>


                    <td>

                        <?php

                        if (
                            isset(
                                $document[
                                    "remarks"
                                ]
                            ) &&
                            $document[
                                "remarks"
                            ] != ""
                        )
                        {

                            echo htmlspecialchars(
                                $document[
                                    "remarks"
                                ]
                            );

                        }
                        else
                        {

                            echo "No remarks";

                        }

                        ?>

                    </td>


                    <td>

                        <?php

                        if (
                            isset(
                                $document[
                                    "created_at"
                                ]
                            )
                        )
                        {

                            echo htmlspecialchars(
                                $document[
                                    "created_at"
                                ]
                            );

                        }
                        else
                        {

                            echo "—";

                        }

                        ?>

                    </td>


                    <td>


                        <?php

                        if (
                            isset(
                                $document[
                                    "file_path"
                                ]
                            ) &&
                            file_exists(
                                $document[
                                    "file_path"
                                ]
                            )
                        )
                        {

                        ?>

                            <a
                                href="<?php

                                echo htmlspecialchars(
                                    $document[
                                        "file_path"
                                    ]
                                );

                                ?>"
                                target="_blank"
                                class="view-button"
                            >

                                View

                            </a>

                        <?php

                        }

                        ?>


                        <?php

                        if (
                            $documentStatus ==
                            "Rejected"
                        )
                        {

                        ?>

                            <span
                                style="
                                color:#f87171;
                                font-size:9px;
                                "
                            >

                                Re-upload Required

                            </span>

                        <?php

                        }

                        ?>


                    </td>


                </tr>

            <?php

            }

            ?>


            </tbody>


        </table>

    </div>

    <?php

    }

    ?>

</section>



<!-- =====================================================
     DOCUMENT REQUIREMENTS
     ===================================================== -->

<section class="panel">


    <div class="panel-header">

        <h2>
            Required Documents
        </h2>

        <p>
            Make sure all required documents are clear and readable.
        </p>

    </div>


    <div class="requirements">


        <div class="requirement">

            📘

            <strong>
                10th Marksheet
            </strong>

            <span>
                Upload your Class 10 academic marksheet.
            </span>

        </div>


        <div class="requirement">

            📗

            <strong>
                12th Marksheet
            </strong>

            <span>
                Upload your Class 12 academic marksheet.
            </span>

        </div>


        <div class="requirement">

            📄

            <strong>
                Transfer Certificate
            </strong>

            <span>
                Upload your institution transfer certificate.
            </span>

        </div>


        <div class="requirement">

            🪪

            <strong>
                Aadhar Card
            </strong>

            <span>
                Upload a clear identity document.
            </span>

        </div>


    </div>

</section>



<!-- =====================================================
     FOOTER
     ===================================================== -->

<div class="footer">

    SMART CAMPUS MANAGEMENT SYSTEM

    &nbsp; • &nbsp;

    STUDENT DOCUMENT PORTAL

    &nbsp; • &nbsp;

    Version 1.0

</div>


</div>


</body>

</html>


<?php

$conn->close();

?>