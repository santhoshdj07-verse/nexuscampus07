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

$message = "";

$messageType = "";

$studentId = (int)$_SESSION["student_id"];

$student = null;

$application = null;


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
   GET EXISTING APPLICATION
   ========================================================= */

$stmt = $conn->prepare(
    "SELECT *
     FROM student_applications
     WHERE student_id = ?
     ORDER BY id DESC
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

    if ($result && $result->num_rows > 0)
    {
        $application = $result->fetch_assoc();
    }

    $stmt->close();
}


/* =========================================================
   FORM SUBMISSION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] == "POST")
{

    /* =====================================================
       COLLECT FORM DATA
       ===================================================== */

    $programType = "";

    $courseName = "";

    $previousQualification = "";

    $previousInstitution = "";

    $previousPercentage = "";

    $address = "";

    $city = "";

    $state = "";

    $pincode = "";


    if (isset($_POST["program_type"]))
    {
        $programType =
            trim($_POST["program_type"]);
    }


    if (isset($_POST["course_name"]))
    {
        $courseName =
            trim($_POST["course_name"]);
    }


    if (isset($_POST["previous_qualification"]))
    {
        $previousQualification =
            trim($_POST["previous_qualification"]);
    }


    if (isset($_POST["previous_institution"]))
    {
        $previousInstitution =
            trim($_POST["previous_institution"]);
    }


    if (isset($_POST["previous_percentage"]))
    {
        $previousPercentage =
            trim($_POST["previous_percentage"]);
    }


    if (isset($_POST["address"]))
    {
        $address =
            trim($_POST["address"]);
    }


    if (isset($_POST["city"]))
    {
        $city =
            trim($_POST["city"]);
    }


    if (isset($_POST["state"]))
    {
        $state =
            trim($_POST["state"]);
    }


    if (isset($_POST["pincode"]))
    {
        $pincode =
            trim($_POST["pincode"]);
    }


    /* =====================================================
       VALIDATION
       ===================================================== */

    if (
        $programType == "" ||
        $courseName == "" ||
        $previousQualification == "" ||
        $previousInstitution == "" ||
        $previousPercentage == "" ||
        $address == "" ||
        $city == "" ||
        $state == "" ||
        $pincode == ""
    )
    {

        $message =
            "Please fill all required application fields.";

        $messageType =
            "error";

    }
    elseif (
        !is_numeric($previousPercentage) ||
        $previousPercentage < 0 ||
        $previousPercentage > 100
    )
    {

        $message =
            "Previous percentage must be between 0 and 100.";

        $messageType =
            "error";

    }
    elseif (
        $programType != "UG" &&
        $programType != "PG"
    )
    {

        $message =
            "Please select a valid program type (UG or PG).";

        $messageType =
            "error";

    }
    elseif (!cms_is_valid_course_for_program($programType, $courseName))
    {

        $message =
            "The selected course is not valid for the selected program type.";

        $messageType =
            "error";

    }
    elseif (
        !is_numeric($previousPercentage) ||
        $previousPercentage < 0 ||
        $previousPercentage > 100
    )
    {

        $message =
            "Previous percentage must be between 0 and 100.";

        $messageType =
            "error";

    }
    elseif (
        !preg_match(
            "/^[0-9]{6}$/",
            $pincode
        )
    )
    {

        $message =
            "Pincode must contain exactly 6 digits.";

        $messageType =
            "error";

    }
    else
    {

        $eligibilityRule = cms_get_admission_rule($conn, $programType);
        $minimumPercentage = (float)($eligibilityRule["minimum_percentage"] ?? 45);
        $isEligible = (float)$previousPercentage >= $minimumPercentage;

        if (!$isEligible)
        {
            $message = "Not eligible for " . htmlspecialchars($programType) . " admission. Minimum required percentage is " . number_format($minimumPercentage, 2) . "%.";
            $messageType = "error";
        }
        else
        {

        /* =================================================
           CHECK EXISTING APPLICATION STATUS
           ================================================= */

        $existingApplication = null;


        $stmt = $conn->prepare(
            "SELECT *
             FROM student_applications
             WHERE student_id = ?
             ORDER BY id DESC
             LIMIT 1"
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

            if (
                $result &&
                $result->num_rows > 0
            )
            {
                $existingApplication =
                    $result->fetch_assoc();
            }

            $stmt->close();
        }


        /* =================================================
           PREVENT EDITING AFTER FINAL PROCESSING
           ================================================= */

        $canModify = true;


        if ($existingApplication)
        {

            $existingStatus =
                $existingApplication[
                    "application_status"
                ];


            if (
                $existingStatus == "Approved" ||
                $existingStatus == "Rejected" ||
                $existingStatus == "Processing" ||
                $existingStatus == "Accepted"
            )
            {
                $canModify = false;
            }
        }


        if (!$canModify)
        {

            $message =
                "This application has already entered the admission processing stage and cannot be modified.";

            $messageType =
                "error";

        }
        else
        {

            /* =============================================
               UPDATE EXISTING APPLICATION
               ============================================= */

            if ($existingApplication)
            {

                $applicationId =
                    (int)$existingApplication["id"];


                $sql = "
                    UPDATE student_applications
                    SET
                        program_type = ?,
                        course_name = ?,
                        previous_qualification = ?,
                        previous_institution = ?,
                        previous_percentage = ?,
                        address = ?,
                        city = ?,
                        state = ?,
                        pincode = ?
                    WHERE
                        id = ?
                        AND student_id = ?
                ";


                $stmt =
                    $conn->prepare($sql);


                if ($stmt)
                {

                    $stmt->bind_param(
                        "ssssssssssi",
                        $programType,
                        $courseName,
                        $previousQualification,
                        $previousInstitution,
                        $previousPercentage,
                        $address,
                        $city,
                        $state,
                        $pincode,
                        $applicationId,
                        $studentId
                    );


                    if ($stmt->execute())
                    {

                        $message =
                            "Admission application updated successfully.";

                        $messageType =
                            "success";

                    }
                    else
                    {

                        $message =
                            "Unable to update the application.";

                        $messageType =
                            "error";
                    }


                    $stmt->close();

                }
                else
                {

                    $message =
                        "Unable to prepare application update.";

                    $messageType =
                        "error";
                }

            }

            /* =============================================
               CREATE NEW APPLICATION
               ============================================= */

            else
            {

                /*
                   Application number format:

                   APP + student ID + current timestamp
                */

                $applicationNumber =
                    "APP" .
                    str_pad(
                        $studentId,
                        4,
                        "0",
                        STR_PAD_LEFT
                    ) .
                    date("YmdHis");


                $applicationStatus =
                    "Pending";


                $sql = "
                    INSERT INTO student_applications
                    (
                        student_id,
                        application_number,
                        program_type,
                        course_name,
                        previous_qualification,
                        previous_institution,
                        previous_percentage,
                        address,
                        city,
                        state,
                        pincode,
                        application_status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ";


                $stmt =
                    $conn->prepare($sql);


                if ($stmt)
                {

                    $stmt->bind_param(
                        "isssssssssss",
                        $studentId,
                        $applicationNumber,
                        $programType,
                        $courseName,
                        $previousQualification,
                        $previousInstitution,
                        $previousPercentage,
                        $address,
                        $city,
                        $state,
                        $pincode,
                        $applicationStatus
                    );


                    if ($stmt->execute())
                    {

                        $message =
                            "Admission application submitted successfully.";

                        $messageType =
                            "success";

                    }
                    else
                    {

                        $message =
                            "Unable to submit the application.";

                        $messageType =
                            "error";
                    }


                    $stmt->close();

                }
                else
                {

                    $message =
                        "Unable to prepare application submission.";

                    $messageType =
                        "error";
                }

            }

        }

        }

    }


    /* =====================================================
       REFRESH APPLICATION DATA
       ===================================================== */

    $stmt = $conn->prepare(
        "SELECT *
         FROM student_applications
         WHERE student_id = ?
         ORDER BY id DESC
         LIMIT 1"
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

        if (
            $result &&
            $result->num_rows > 0
        )
        {
            $application =
                $result->fetch_assoc();
        }

        $stmt->close();
    }

}


/* =========================================================
   FORM DEFAULT VALUES
   ========================================================= */

$programTypeValue = "";

$courseNameValue = "";

$previousQualificationValue = "";

$previousInstitutionValue = "";

$previousPercentageValue = "";

$addressValue = "";

$cityValue = "";

$stateValue = "";

$pincodeValue = "";


if ($application)
{

    $programTypeValue =
        isset(
            $application["program_type"]
        )
        ?
        $application["program_type"]
        :
        "";


    $courseNameValue =
        isset(
            $application["course_name"]
        )
        ?
        $application["course_name"]
        :
        "";


    $previousQualificationValue =
        isset(
            $application[
                "previous_qualification"
            ]
        )
        ?
        $application[
            "previous_qualification"
        ]
        :
        "";


    $previousInstitutionValue =
        isset(
            $application[
                "previous_institution"
            ]
        )
        ?
        $application[
            "previous_institution"
        ]
        :
        "";


    $previousPercentageValue =
        isset(
            $application[
                "previous_percentage"
            ]
        )
        ?
        $application[
            "previous_percentage"
        ]
        :
        "";


    $addressValue =
        isset(
            $application["address"]
        )
        ?
        $application["address"]
        :
        "";


    $cityValue =
        isset(
            $application["city"]
        )
        ?
        $application["city"]
        :
        "";


    $stateValue =
        isset(
            $application["state"]
        )
        ?
        $application["state"]
        :
        "";


    $pincodeValue =
        isset(
            $application["pincode"]
        )
        ?
        $application["pincode"]
        :
        "";
}


/* =========================================================
   APPLICATION STATUS
   ========================================================= */

$applicationStatus =
    "Not Submitted";


if ($application)
{

    if (
        isset(
            $application[
                "application_status"
            ]
        ) &&
        $application[
            "application_status"
        ] != ""
    )
    {

        $applicationStatus =
            $application[
                "application_status"
            ];
    }
}


$locked = false;


if (
    $applicationStatus == "Processing" ||
    $applicationStatus == "Approved" ||
    $applicationStatus == "Rejected"
)
{
    $locked = true;
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
Admission Application | Smart Campus
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

    --panel-light:#111a2e;

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
    max-width:1180px;

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

    align-items:center;

    gap:10px;
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
   STATUS BANNER
   ========================================================= */

.status-banner
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:20px;

    padding:18px 20px;

    border-radius:15px;

    margin-bottom:20px;

    background:
        rgba(13,19,36,.86);

    border:
        1px solid var(--border);
}


.status-title
{
    color:#94a3b8;

    font-size:10px;

    font-weight:bold;

    letter-spacing:.8px;

    margin-bottom:7px;
}


.status-number
{
    color:white;

    font-size:14px;

    font-weight:700;
}


.status-pill
{
    display:inline-block;

    padding:7px 12px;

    border-radius:999px;

    font-size:10px;

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


.status-processing
{
    color:#60a5fa;

    background:
        rgba(59,130,246,.10);

    border:
        1px solid
        rgba(59,130,246,.16);
}


.status-approved
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


.status-accepted
{
    color:#4ade80;

    background:
        rgba(34,197,94,.10);

    border:
        1px solid
        rgba(34,197,94,.16);
}


.status-not
{
    color:#94a3b8;

    background:
        rgba(148,163,184,.08);

    border:
        1px solid
        rgba(148,163,184,.12);
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
   PROFILE
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
   FORM PANEL
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
        repeat(2,1fr);

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


.form-label span
{
    color:#ef4444;
}


.form-input,
.form-select,
.form-textarea
{
    width:100%;

    padding:
        12px 13px;

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


.form-input::placeholder,
.form-textarea::placeholder
{
    color:#475569;
}


.form-input:focus,
.form-select:focus,
.form-textarea:focus
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


.form-textarea
{
    min-height:90px;

    resize:vertical;
}


.form-input:disabled,
.form-select:disabled,
.form-textarea:disabled
{
    opacity:.65;

    cursor:not-allowed;
}


/* =========================================================
   BUTTON AREA
   ========================================================= */

.form-actions
{
    display:flex;

    justify-content:flex-end;

    gap:10px;

    margin-top:22px;

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


.secondary
{
    color:#cbd5e1;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid var(--border);
}


.secondary:hover
{
    color:white;

    background:
        rgba(255,255,255,.07);
}


/* =========================================================
   LOCK MESSAGE
   ========================================================= */

.lock-message
{
    padding:15px 17px;

    margin-bottom:20px;

    border-radius:12px;

    color:#fbbf24;

    background:
        rgba(245,158,11,.07);

    border:
        1px solid
        rgba(245,158,11,.14);

    font-size:10px;

    line-height:1.6;
}


/* =========================================================
   APPLICATION DETAILS
   ========================================================= */

.details-grid
{
    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:15px;

    padding:21px;
}


.detail
{
    padding:15px;

    border-radius:11px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(148,163,184,.08);
}


.detail-label
{
    color:#64748b;

    font-size:9px;

    margin-bottom:6px;
}


.detail-value
{
    color:#e2e8f0;

    font-size:11px;

    line-height:1.5;
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

    .profile
    {
        grid-template-columns:
            repeat(2,1fr);
    }


    .details-grid
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


    .header-actions
    {
        width:100%;

        flex-wrap:wrap;
    }


    .form-grid
    {
        grid-template-columns:1fr;
    }


    .form-group.full
    {
        grid-column:auto;
    }

}


@media(max-width:500px)
{

    .profile,
    .details-grid
    {
        grid-template-columns:1fr;
    }


    .status-banner
    {
        flex-direction:column;

        align-items:flex-start;
    }


    .form-body
    {
        padding:17px;
    }


    .form-actions
    {
        flex-direction:column;
    }


    .button
    {
        text-align:center;

        width:100%;
    }

}

</style>

</head>


<body>


<div class="container">


    <!-- =================================================
         HEADER
         ================================================= -->

    <div class="header">

        <div>

            <div class="page-label">
                STUDENT PORTAL
            </div>

            <h1 class="title">
                Admission Application
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
                href="logout.php"
                class="header-button logout"
            >
                Logout
            </a>

        </div>

    </div>



    <!-- =================================================
         APPLICATION STATUS
         ================================================= -->

    <div class="status-banner">


        <div>

            <div class="status-title">
                APPLICATION NUMBER
            </div>


            <div class="status-number">

                <?php

                if ($application)
                {
                    echo htmlspecialchars(
                        $application[
                            "application_number"
                        ]
                    );
                }
                else
                {
                    echo "Not Generated";
                }

                ?>

            </div>

        </div>


        <div>

            <?php

            $statusClass =
                "status-not";


            if ($applicationStatus == "Pending")
            {
                $statusClass =
                    "status-pending";
            }
            elseif (
                $applicationStatus ==
                "Processing"
            )
            {
                $statusClass =
                    "status-processing";
            }
            elseif (
                $applicationStatus ==
                "Approved"
            )
            {
                $statusClass =
                    "status-approved";
            }
            elseif (
                $applicationStatus ==
                "Rejected"
            )
            {
                $statusClass =
                    "status-rejected";
            }

            ?>


            <span
                class="status-pill
                <?php
                echo $statusClass;
                ?>"
            >

                <?php

                echo htmlspecialchars(
                    $applicationStatus
                );

                ?>

            </span>

        </div>


    </div>


    <?php
        $eligibilityRule = cms_get_admission_rule($conn, $programTypeValue ?: "UG");
        $minimumPercentage = (float)($eligibilityRule["minimum_percentage"] ?? 45);
        $currentPercentage = is_numeric($previousPercentageValue) ? (float)$previousPercentageValue : 0;
        $isEligible = $currentPercentage >= $minimumPercentage;
    ?>

    <div style="margin: 0 0 20px; padding: 18px 20px; border-radius: 15px; border: 1px solid rgba(148,163,184,.14); background: rgba(13,19,36,.86);">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:20px; flex-wrap:wrap;">
            <div>
                <div style="font-size:10px; letter-spacing: 1.2px; color:#94a3b8; text-transform: uppercase; margin-bottom: 6px;">Eligibility Check</div>
                <div style="font-size:15px; font-weight:700;">
                    Previous Percentage: <span style="color: #f8fafc;"><?php echo number_format($currentPercentage, 2); ?>%</span>
                    &nbsp;|&nbsp;
                    Required Minimum: <span style="color: #93c5fd;"><?php echo number_format($minimumPercentage, 2); ?>%</span>
                </div>
            </div>
            <span style="padding: 8px 12px; border-radius: 999px; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; <?php echo $isEligible ? 'background: rgba(34,197,94,.12); color: #4ade80; border: 1px solid rgba(34,197,94,.18);' : 'background: rgba(239,68,68,.10); color: #fca5a5; border: 1px solid rgba(239,68,68,.18);'; ?>">
                <?php echo $isEligible ? 'Eligible' : 'Not Eligible'; ?>
            </span>
        </div>
    </div>


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

            echo htmlspecialchars(
                $message
            );

            ?>

        </div>

    <?php

    }

    ?>



    <!-- =================================================
         STUDENT INFORMATION
         ================================================= -->

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



    <!-- =================================================
         LOCK MESSAGE
         ================================================= -->

    <?php

    if ($locked)
    {

    ?>

        <div class="lock-message">

            🔒

            Your application is currently

            <strong>

                <?php

                echo htmlspecialchars(
                    $applicationStatus
                );

                ?>

            </strong>

            and can no longer be modified
            from the student portal.

            Please contact the administration
            if you need assistance.

        </div>

    <?php

    }

    ?>



    <!-- =================================================
         APPLICATION FORM
         ================================================= -->

    <section class="panel">


        <div class="panel-header">

            <h2>
                Application Information
            </h2>

            <p>
                Enter your admission details carefully.
                All fields marked with * are required.
            </p>

        </div>



        <div class="form-body">


            <form
                method="POST"
                action=""
            >


                <div class="form-grid">


                    <!-- PROGRAM -->

                    <div class="form-group">

                        <label class="form-label">

                            PROGRAM TYPE

                            <span>*</span>

                        </label>


                        <select
                            id="program_type"
                            name="program_type"
                            class="form-select"
                            onchange="updateCourses()"
                            <?php
                            if ($locked)
                            {
                                echo "disabled";
                            }
                            ?>
                            required
                        >

                            <option value="">
                                Select Program Type
                            </option>


                            <option
                                value="UG"
                                <?php

                                if (
                                    $programTypeValue
                                    ==
                                    "UG"
                                )
                                {
                                    echo "selected";
                                }

                                ?>
                            >
                                Undergraduate (UG)
                            </option>


                            <option
                                value="PG"
                                <?php

                                if (
                                    $programTypeValue
                                    ==
                                    "PG"
                                )
                                {
                                    echo "selected";
                                }

                                ?>
                            >
                                Postgraduate (PG)
                            </option>


                        </select>

                    </div>



                    <!-- COURSE -->

                    <div class="form-group">

                        <label class="form-label">

                            COURSE NAME

                            <span>*</span>

                        </label>


                        <select
                            id="course_name"
                            name="course_name"
                            class="form-select"
                            <?php
                            if ($locked)
                            {
                                echo "disabled";
                            }
                            ?>
                            required
                        >

                            <option value="">
                                Select Course
                            </option>

                        </select>

                    </div>



                    <!-- QUALIFICATION -->

                    <div class="form-group">

                        <label class="form-label">

                            PREVIOUS QUALIFICATION

                            <span>*</span>

                        </label>


                        <select
                            name="previous_qualification"
                            class="form-select"
                            <?php
                            if ($locked)
                            {
                                echo "disabled";
                            }
                            ?>
                            required
                        >

                            <option value="">
                                Select Qualification
                            </option>


                            <option
                                value="10th"
                                <?php

                                if (
                                    $previousQualificationValue
                                    ==
                                    "10th"
                                )
                                {
                                    echo "selected";
                                }

                                ?>
                            >
                                10th
                            </option>


                            <option
                                value="12th"
                                <?php

                                if (
                                    $previousQualificationValue
                                    ==
                                    "12th"
                                )
                                {
                                    echo "selected";
                                }

                                ?>
                            >
                                12th
                            </option>


                            <option
                                value="Diploma"
                                <?php

                                if (
                                    $previousQualificationValue
                                    ==
                                    "Diploma"
                                )
                                {
                                    echo "selected";
                                }

                                ?>
                            >
                                Diploma
                            </option>


                            <option
                                value="UG"
                                <?php

                                if (
                                    $previousQualificationValue
                                    ==
                                    "UG"
                                )
                                {
                                    echo "selected";
                                }

                                ?>
                            >
                                Undergraduate Degree
                            </option>


                        </select>

                    </div>



                    <!-- INSTITUTION -->

                    <div class="form-group">

                        <label class="form-label">

                            PREVIOUS INSTITUTION

                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="previous_institution"
                            class="form-input"
                            placeholder="Enter previous institution"
                            value="<?php
                                echo htmlspecialchars(
                                    $previousInstitutionValue
                                );
                            ?>"
                            <?php
                            if ($locked)
                            {
                                echo "disabled";
                            }
                            ?>
                            required
                        >

                    </div>



                    <!-- PERCENTAGE -->

                    <div class="form-group">

                        <label class="form-label">

                            PREVIOUS PERCENTAGE

                            <span>*</span>

                        </label>


                        <input
                            type="number"
                            name="previous_percentage"
                            class="form-input"
                            placeholder="Example: 82.50"
                            min="0"
                            max="100"
                            step="0.01"
                            value="<?php
                                echo htmlspecialchars(
                                    $previousPercentageValue
                                );
                            ?>"
                            <?php
                            if ($locked)
                            {
                                echo "disabled";
                            }
                            ?>
                            required
                        >

                    </div>



                    <!-- CITY -->

                    <div class="form-group">

                        <label class="form-label">

                            CITY

                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="city"
                            class="form-input"
                            placeholder="Enter city"
                            value="<?php
                                echo htmlspecialchars(
                                    $cityValue
                                );
                            ?>"
                            <?php
                            if ($locked)
                            {
                                echo "disabled";
                            }
                            ?>
                            required
                        >

                    </div>



                    <!-- STATE -->

                    <div class="form-group">

                        <label class="form-label">

                            STATE

                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="state"
                            class="form-input"
                            placeholder="Enter state"
                            value="<?php
                                echo htmlspecialchars(
                                    $stateValue
                                );
                            ?>"
                            <?php
                            if ($locked)
                            {
                                echo "disabled";
                            }
                            ?>
                            required
                        >

                    </div>



                    <!-- PINCODE -->

                    <div class="form-group">

                        <label class="form-label">

                            PINCODE

                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="pincode"
                            class="form-input"
                            placeholder="6 digit pincode"
                            maxlength="6"
                            value="<?php
                                echo htmlspecialchars(
                                    $pincodeValue
                                );
                            ?>"
                            <?php
                            if ($locked)
                            {
                                echo "disabled";
                            }
                            ?>
                            required
                        >

                    </div>



                    <!-- ADDRESS -->

                    <div class="form-group full">

                        <label class="form-label">

                            FULL ADDRESS

                            <span>*</span>

                        </label>


                        <textarea
                            name="address"
                            class="form-textarea"
                            placeholder="Enter your complete residential address"
                            <?php
                            if ($locked)
                            {
                                echo "disabled";
                            }
                            ?>
                            required
                        ><?php

                        echo htmlspecialchars(
                            $addressValue
                        );

                        ?></textarea>

                    </div>


                </div>



                <!-- ACTIONS -->

                <div class="form-actions">


                    <a
                        href="student_dashboard.php"
                        class="button secondary"
                    >

                        Cancel

                    </a>


                    <?php

                    if (!$locked)
                    {

                    ?>

                        <button
                            type="submit"
                            class="button primary"
                        >

                            <?php

                            if ($application)
                            {
                                echo "Update Application";
                            }
                            else
                            {
                                echo "Submit Application";
                            }

                            ?>

                        </button>

                    <?php

                    }

                    ?>

                </div>


            </form>


        </div>


    </section>



    <!-- =================================================
         EXISTING APPLICATION DETAILS
         ================================================= -->

    <?php

    if ($application)
    {

    ?>

        <section class="panel">


            <div class="panel-header">

                <h2>
                    Application Record
                </h2>

                <p>
                    Current information stored in the
                    college admission system.
                </p>

            </div>



            <div class="details-grid">


                <div class="detail">

                    <div class="detail-label">
                        APPLICATION NUMBER
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application[
                                "application_number"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        PROGRAM
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application[
                                "program_type"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        COURSE
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application[
                                "course_name"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        QUALIFICATION
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application[
                                "previous_qualification"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        PREVIOUS INSTITUTION
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application[
                                "previous_institution"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        PERCENTAGE
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application[
                                "previous_percentage"
                            ]
                        );

                        ?>

                        %

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        CITY
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application["city"]
                        );

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        STATE
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application["state"]
                        );

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        PINCODE
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application["pincode"]
                        );

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        APPLICATION STATUS
                    </div>

                    <div class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $application[
                                "application_status"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        SUBMITTED ON
                    </div>

                    <div class="detail-value">

                        <?php

                        if (
                            isset(
                                $application[
                                    "created_at"
                                ]
                            )
                        )
                        {
                            echo htmlspecialchars(
                                $application[
                                    "created_at"
                                ]
                            );
                        }
                        else
                        {
                            echo "Available in application record";
                        }

                        ?>

                    </div>

                </div>


                <div class="detail">

                    <div class="detail-label">
                        ADDRESS
                    </div>

                    <div class="detail-value">

                        <?php

                        echo nl2br(
                            htmlspecialchars(
                                $application[
                                    "address"
                                ]
                            )
                        );

                        ?>

                    </div>

                </div>


            </div>


        </section>

    <?php

    }

    ?>



    <!-- =================================================
         FOOTER
         ================================================= -->

    <div class="footer">

        SMART CAMPUS MANAGEMENT SYSTEM

        &nbsp; • &nbsp;

        STUDENT ADMISSION PORTAL

        &nbsp; • &nbsp;

        Version 1.0

    </div>


</div>


</body>

</html>


<script>

function updateCourses()
{
    const programType = document.getElementById('program_type').value;
    const courseSelect = document.getElementById('course_name');
    
    courseSelect.innerHTML = '<option value="">Select Course</option>';
    
    let courses = {};
    
    if (programType === 'UG')
    {
        courses = {
            'BCA': 'Bachelor of Computer Applications',
            'BSc': 'Bachelor of Science',
            'BA': 'Bachelor of Arts',
            'B.Com': 'Bachelor of Commerce',
            'LLB': 'Bachelor of Laws',
            'BBA': 'Bachelor of Business Administration',
            'B.Tech': 'Bachelor of Technology'
        };
    }
    else if (programType === 'PG')
    {
        courses = {
            'MCA': 'Master of Computer Applications',
            'MSc': 'Master of Science',
            'MA': 'Master of Arts',
            'M.Com': 'Master of Commerce',
            'MBA': 'Master of Business Administration',
            'M.Tech': 'Master of Technology',
            'LLM': 'Master of Laws'
        };
    }
    
    for (let courseCode in courses)
    {
        const option = document.createElement('option');
        option.value = courseCode;
        option.textContent = courseCode + ' - ' + courses[courseCode];
        <?php
        if ($courseNameValue)
        {
            echo "if (courseCode === '" . addslashes($courseNameValue) . "') { option.selected = true; }";
        }
        ?>
        courseSelect.appendChild(option);
    }
}

document.addEventListener('DOMContentLoaded', function()
{
    if (document.getElementById('program_type').value)
    {
        updateCourses();
    }
});

</script>


<?php

$conn->close();

?>
