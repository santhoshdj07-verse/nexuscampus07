<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";


/* =========================================================
   ADMIN NAME
   ========================================================= */

$adminName = "Administrator";

if (
    isset($_SESSION["admin_name"]) &&
    $_SESSION["admin_name"] != ""
)
{
    $adminName = $_SESSION["admin_name"];
}


$currentDate = date("l, d F Y");


/* =========================================================
   TOTAL STUDENTS
   ========================================================= */

$totalStudents = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM students"
);

if ($result)
{
    $row = $result->fetch_assoc();

    $totalStudents = (int)$row["total"];
}


/* =========================================================
   AVERAGE CGPA
   ========================================================= */

$averageCGPA = 0;

$result = $conn->query(
    "SELECT AVG(cgpa) AS average
     FROM students"
);

if ($result)
{
    $row = $result->fetch_assoc();

    if ($row["average"] != null)
    {
        $averageCGPA = (float)$row["average"];
    }
}


/* =========================================================
   HIGHEST CGPA
   ========================================================= */

$highestCGPA = 0;

$result = $conn->query(
    "SELECT MAX(cgpa) AS highest
     FROM students"
);

if ($result)
{
    $row = $result->fetch_assoc();

    if ($row["highest"] != null)
    {
        $highestCGPA = (float)$row["highest"];
    }
}


/* =========================================================
   LOWEST CGPA
   ========================================================= */

$lowestCGPA = 0;

$result = $conn->query(
    "SELECT MIN(cgpa) AS lowest
     FROM students"
);

if ($result)
{
    $row = $result->fetch_assoc();

    if ($row["lowest"] != null)
    {
        $lowestCGPA = (float)$row["lowest"];
    }
}


/* =========================================================
   DEPARTMENT-WISE COUNT
   ========================================================= */

$departmentResult = $conn->query(
    "SELECT
        department,
        COUNT(*) AS total
     FROM students
     GROUP BY department
     ORDER BY department ASC"
);


/* =========================================================
   SEMESTER-WISE COUNT
   ========================================================= */

$semesterResult = $conn->query(
    "SELECT
        semester,
        COUNT(*) AS total
     FROM students
     GROUP BY semester
     ORDER BY
        CASE semester
            WHEN 'I' THEN 1
            WHEN 'II' THEN 2
            WHEN 'III' THEN 3
            WHEN 'IV' THEN 4
            WHEN 'V' THEN 5
            WHEN 'VI' THEN 6
            WHEN 'VII' THEN 7
            WHEN 'VIII' THEN 8
            ELSE 9
        END"
);


/* =========================================================
   TOP STUDENTS
   ========================================================= */

$topStudentsResult = $conn->query(
    "SELECT
        roll_no,
        name,
        department,
        semester,
        cgpa
     FROM students
     ORDER BY cgpa DESC
     LIMIT 5"
);


/* =========================================================
   COMPLETE STUDENT REPORT
   ========================================================= */

$studentResult = $conn->query(
    "SELECT
        roll_no,
        name,
        department,
        semester,
        phone,
        cgpa
     FROM students
     ORDER BY name ASC"
);


/* =========================================================
   DEPARTMENT DATA
   ========================================================= */

$departmentData = array();

if ($departmentResult)
{
    while (
        $department =
        $departmentResult->fetch_assoc()
    )
    {
        $departmentData[] = $department;
    }
}


/* =========================================================
   SEMESTER DATA
   ========================================================= */

$semesterData = array();

if ($semesterResult)
{
    while (
        $semester =
        $semesterResult->fetch_assoc()
    )
    {
        $semesterData[] = $semester;
    }
}


/* =========================================================
   INDIVIDUAL STUDENT SEARCH
   ========================================================= */

$searchedStudent = null;
$searchRollNo = "";
$searchMessage = "";
$searchMessageType = "";

if (isset($_GET["student_roll_no"]))
{
    $searchRollNo =
        trim($_GET["student_roll_no"]);

    if ($searchRollNo != "")
    {
        $studentStatement =
            $conn->prepare(
                "SELECT
                    roll_no,
                    name,
                    department,
                    semester,
                    phone,
                    cgpa
                 FROM students
                 WHERE roll_no = ?
                 LIMIT 1"
            );

        if ($studentStatement)
        {
            $studentStatement->bind_param(
                "s",
                $searchRollNo
            );

            $studentStatement->execute();

            $studentSearchResult =
                $studentStatement->get_result();

            if (
                $studentSearchResult &&
                $studentSearchResult->num_rows > 0
            )
            {
                $searchedStudent =
                    $studentSearchResult->fetch_assoc();

                $searchMessage =
                    "Student record found.";

                $searchMessageType =
                    "success";
            }
            else
            {
                $searchMessage =
                    "No student found with Roll Number: "
                    . $searchRollNo;

                $searchMessageType =
                    "error";
            }

            $studentStatement->close();
        }
        else
        {
            $searchMessage =
                "Unable to search student.";

            $searchMessageType =
                "error";
        }
    }
}


/* =========================================================
   HELPER
   ========================================================= */

function percentageValue($value, $total)
{
    if ($total <= 0)
    {
        return 0;
    }

    return round(
        ($value / $total) * 100
    );
}

?>

<!DOCTYPE html>

<html>

<head>

<title>
Reports | Smart Campus
</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>


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
    --panel:rgba(13,19,36,0.90);
    --panel-light:rgba(255,255,255,0.035);
    --border:rgba(148,163,184,0.14);
    --text:#f8fafc;
    --muted:#94a3b8;
    --dim:#64748b;
    --blue:#3b82f6;
    --cyan:#06b6d4;
    --purple:#8b5cf6;
    --green:#22c55e;
    --yellow:#f59e0b;
    --red:#ef4444;
}


body
{
    margin:0;

    font-family:
        Inter,
        Segoe UI,
        Arial,
        sans-serif;

    color:var(--text);

    min-height:100vh;

    background:

        radial-gradient(
            circle at 10% 10%,
            rgba(59,130,246,0.14),
            transparent 30%
        ),

        radial-gradient(
            circle at 90% 10%,
            rgba(139,92,246,0.13),
            transparent 30%
        ),

        radial-gradient(
            circle at 50% 100%,
            rgba(6,182,212,0.07),
            transparent 35%
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

    z-index:0;
}


body::after
{
    content:"";

    position:fixed;

    width:500px;

    height:500px;

    right:-220px;

    bottom:-220px;

    border-radius:50%;

    background:
        rgba(139,92,246,.06);

    filter:blur(90px);

    pointer-events:none;
}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar
{
    width:255px;

    position:fixed;

    left:0;

    top:0;

    bottom:0;

    padding:24px 16px;

    background:
        rgba(7,11,23,.92);

    backdrop-filter:blur(18px);

    border-right:
        1px solid var(--border);

    display:flex;

    flex-direction:column;

    z-index:10;
}


.brand
{
    display:flex;

    align-items:center;

    gap:12px;

    padding:
        8px 10px 28px;
}


.brand-icon
{
    width:43px;

    height:43px;

    border-radius:13px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:21px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #06b6d4
        );

    box-shadow:
        0 0 25px
        rgba(37,99,235,.35);
}


.brand-text strong
{
    display:block;

    font-size:15px;

    letter-spacing:.5px;
}


.brand-text span
{
    display:block;

    color:var(--muted);

    font-size:10px;

    margin-top:3px;
}


.nav-title
{
    color:#64748b;

    font-size:10px;

    font-weight:bold;

    letter-spacing:1.5px;

    padding:
        12px 12px 8px;
}


.nav
{
    display:flex;

    flex-direction:column;

    gap:5px;
}


.nav a
{
    text-decoration:none;

    color:#94a3b8;

    padding:12px 13px;

    border-radius:10px;

    display:flex;

    align-items:center;

    gap:12px;

    font-size:13px;

    transition:.2s;
}


.nav a:hover
{
    color:white;

    background:
        rgba(59,130,246,.10);

    transform:
        translateX(3px);
}


.nav a.active
{
    color:white;

    background:
        linear-gradient(
            90deg,
            rgba(139,92,246,.22),
            rgba(59,130,246,.06)
        );

    border:
        1px solid
        rgba(139,92,246,.18);

    box-shadow:
        inset 3px 0 0 #8b5cf6;
}


.nav-icon
{
    width:22px;

    text-align:center;

    font-size:16px;
}


.sidebar-bottom
{
    margin-top:auto;
}


.system-status
{
    padding:13px;

    border-radius:12px;

    background:
        rgba(34,197,94,.06);

    border:
        1px solid
        rgba(34,197,94,.14);

    margin-bottom:12px;
}


.status-row
{
    display:flex;

    align-items:center;

    gap:8px;

    font-size:11px;

    color:#a7f3d0;
}


.status-dot
{
    width:7px;

    height:7px;

    border-radius:50%;

    background:#22c55e;

    box-shadow:
        0 0 10px
        rgba(34,197,94,.8);
}


.logout
{
    text-decoration:none;

    display:flex;

    align-items:center;

    gap:10px;

    padding:12px;

    border-radius:10px;

    color:#fca5a5;

    background:
        rgba(239,68,68,.06);

    border:
        1px solid
        rgba(239,68,68,.10);

    font-size:13px;
}


.logout:hover
{
    background:
        rgba(239,68,68,.13);
}


/* =========================================================
   MAIN
   ========================================================= */

.main
{
    margin-left:255px;

    width:
        calc(100% - 255px);

    padding:
        28px 34px 50px;

    position:relative;

    z-index:1;
}


/* =========================================================
   TOPBAR
   ========================================================= */

.topbar
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:25px;
}


.page-label
{
    color:#64748b;

    font-size:11px;

    letter-spacing:1px;

    margin-bottom:5px;
}


.page-title
{
    margin:0;

    font-size:27px;
}


.top-right
{
    display:flex;

    align-items:center;

    gap:15px;
}


.date
{
    color:var(--muted);

    font-size:11px;

    padding:9px 13px;

    border:
        1px solid var(--border);

    border-radius:9px;

    background:
        rgba(255,255,255,.025);
}


.profile
{
    display:flex;

    align-items:center;

    gap:10px;

    padding:
        6px 10px 6px 6px;

    border:
        1px solid var(--border);

    border-radius:12px;

    background:
        rgba(255,255,255,.025);
}


.avatar
{
    width:34px;

    height:34px;

    border-radius:10px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-weight:bold;

    background:
        linear-gradient(
            135deg,
            #8b5cf6,
            #3b82f6
        );
}


/* =========================================================
   HERO
   ========================================================= */

.hero
{
    border-radius:18px;

    padding:27px 30px;

    margin-bottom:20px;

    background:

        linear-gradient(
            115deg,
            rgba(139,92,246,.20),
            rgba(37,99,235,.16),
            rgba(6,182,212,.12)
        );

    border:
        1px solid
        rgba(139,92,246,.18);

    position:relative;

    overflow:hidden;
}


.hero::after
{
    content:"";

    position:absolute;

    width:180px;

    height:180px;

    right:-60px;

    top:-70px;

    border-radius:50%;

    border:
        1px solid
        rgba(255,255,255,.08);

    box-shadow:
        0 0 0 25px rgba(255,255,255,.02),
        0 0 0 50px rgba(255,255,255,.015);
}


.hero h2
{
    margin:
        0 0 7px;

    font-size:22px;

    position:relative;

    z-index:1;
}


.hero p
{
    margin:0;

    max-width:800px;

    color:#a8b5c8;

    font-size:13px;

    line-height:1.6;

    position:relative;

    z-index:1;
}


/* =========================================================
   ACTION BAR
   ========================================================= */

.action-bar
{
    display:flex;

    justify-content:flex-end;

    align-items:center;

    gap:10px;

    margin-bottom:20px;
}


.action-button
{
    border:
        1px solid
        rgba(59,130,246,.20);

    color:white;

    background:
        linear-gradient(
            135deg,
            rgba(59,130,246,.20),
            rgba(139,92,246,.15)
        );

    padding:
        10px 15px;

    border-radius:10px;

    cursor:pointer;

    font-size:11px;

    font-weight:bold;

    transition:.2s;
}


.action-button:hover
{
    transform:
        translateY(-2px);

    box-shadow:
        0 10px 25px
        rgba(59,130,246,.15);
}


/* =========================================================
   PRINT OPTIONS
   ========================================================= */

.print-options
{
    display:none;

    margin-bottom:20px;

    padding:18px;

    border-radius:15px;

    background:
        rgba(13,19,36,.96);

    border:
        1px solid
        rgba(59,130,246,.18);

    box-shadow:
        0 15px 40px
        rgba(0,0,0,.25);

    animation:
        optionOpen .2s ease;
}


@keyframes optionOpen
{
    from
    {
        opacity:0;
        transform:translateY(-5px);
    }

    to
    {
        opacity:1;
        transform:translateY(0);
    }
}


.print-options.show
{
    display:block;
}


.print-options-title
{
    color:#94a3b8;

    font-size:10px;

    font-weight:bold;

    letter-spacing:1px;

    margin-bottom:13px;
}


.print-option-grid
{
    display:grid;

    grid-template-columns:
        repeat(5,1fr);

    gap:10px;
}


.print-option
{
    border:
        1px solid
        rgba(148,163,184,.12);

    background:
        rgba(255,255,255,.025);

    color:#cbd5e1;

    padding:13px 10px;

    border-radius:10px;

    cursor:pointer;

    font-size:10px;

    transition:.2s;

    text-align:center;
}


.print-option:hover
{
    border-color:
        rgba(139,92,246,.30);

    background:
        rgba(139,92,246,.08);

    color:white;

    transform:
        translateY(-2px);
}


.print-option-icon
{
    display:block;

    font-size:18px;

    margin-bottom:7px;
}


/* =========================================================
   SEARCH STUDENT
   ========================================================= */

.student-search-panel
{
    margin-bottom:20px;

    padding:18px 20px;

    border-radius:15px;

    background:
        var(--panel);

    border:
        1px solid var(--border);
}


.student-search-title
{
    font-size:13px;

    font-weight:bold;

    margin-bottom:5px;
}


.student-search-description
{
    color:#64748b;

    font-size:9px;

    margin-bottom:14px;
}


.search-form
{
    display:flex;

    gap:10px;

    max-width:650px;
}


.search-input
{
    flex:1;

    min-width:0;

    background:
        rgba(255,255,255,.035);

    color:white;

    border:
        1px solid
        rgba(148,163,184,.15);

    border-radius:10px;

    padding:
        11px 13px;

    outline:none;

    font-size:11px;

    transition:.2s;
}


.search-input:focus
{
    border-color:
        rgba(59,130,246,.55);

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.08);
}


.search-input::placeholder
{
    color:#475569;
}


.search-button
{
    border:none;

    color:white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );

    padding:
        0 20px;

    border-radius:10px;

    cursor:pointer;

    font-size:10px;

    font-weight:bold;

    transition:.2s;
}


.search-button:hover
{
    transform:
        translateY(-2px);

    box-shadow:
        0 8px 25px
        rgba(59,130,246,.20);
}


.clear-search
{
    display:flex;

    align-items:center;

    justify-content:center;

    padding:
        0 14px;

    border-radius:10px;

    text-decoration:none;

    color:#94a3b8;

    border:
        1px solid
        rgba(148,163,184,.12);

    background:
        rgba(255,255,255,.025);

    font-size:10px;
}


.clear-search:hover
{
    color:white;

    background:
        rgba(255,255,255,.06);
}


/* =========================================================
   SEARCH MESSAGE
   ========================================================= */

.search-message
{
    margin-top:12px;

    padding:10px 12px;

    border-radius:9px;

    font-size:10px;
}


.search-message.success
{
    color:#86efac;

    background:
        rgba(34,197,94,.07);

    border:
        1px solid
        rgba(34,197,94,.13);
}


.search-message.error
{
    color:#fca5a5;

    background:
        rgba(239,68,68,.07);

    border:
        1px solid
        rgba(239,68,68,.13);
}


/* =========================================================
   INDIVIDUAL REPORT
   ========================================================= */

.individual-report
{
    margin-bottom:20px;

    border-radius:15px;

    background:
        var(--panel);

    border:
        1px solid
        rgba(59,130,246,.18);

    overflow:hidden;
}


.individual-header
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:
        17px 20px;

    border-bottom:
        1px solid var(--border);

    background:
        linear-gradient(
            90deg,
            rgba(59,130,246,.08),
            rgba(139,92,246,.06)
        );
}


.individual-header h3
{
    margin:0;

    font-size:14px;
}


.individual-header span
{
    color:#64748b;

    font-size:9px;

    letter-spacing:1px;
}


.individual-body
{
    padding:20px;
}


.individual-grid
{
    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:12px;
}


.info-box
{
    padding:14px;

    border-radius:11px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(148,163,184,.09);
}


.info-label
{
    color:#64748b;

    font-size:8px;

    font-weight:bold;

    letter-spacing:1px;

    margin-bottom:7px;
}


.info-value
{
    color:#f8fafc;

    font-size:12px;

    font-weight:bold;

    word-break:break-word;
}


.individual-actions
{
    display:flex;

    justify-content:flex-end;

    gap:10px;

    margin-top:15px;
}


.individual-print-button
{
    border:
        1px solid
        rgba(34,197,94,.20);

    color:#86efac;

    background:
        rgba(34,197,94,.07);

    padding:
        10px 15px;

    border-radius:9px;

    cursor:pointer;

    font-size:10px;

    font-weight:bold;
}


.individual-print-button:hover
{
    background:
        rgba(34,197,94,.13);

    transform:
        translateY(-2px);
}


/* =========================================================
   STATISTICS
   ========================================================= */

.statistics
{
    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:16px;

    margin-bottom:20px;
}


.stat-card
{
    position:relative;

    padding:20px;

    border-radius:15px;

    background:
        var(--panel);

    border:
        1px solid var(--border);

    overflow:hidden;

    transition:.25s;
}


.stat-card:hover
{
    transform:
        translateY(-3px);

    border-color:
        rgba(139,92,246,.25);

    box-shadow:
        0 15px 40px
        rgba(0,0,0,.18);
}


.stat-card::before
{
    content:"";

    position:absolute;

    left:0;

    top:0;

    width:100%;

    height:2px;

    background:
        linear-gradient(
            90deg,
            #3b82f6,
            #8b5cf6,
            #06b6d4
        );
}


.stat-icon
{
    width:38px;

    height:38px;

    border-radius:11px;

    display:flex;

    align-items:center;

    justify-content:center;

    margin-bottom:13px;

    font-size:17px;

    background:
        rgba(59,130,246,.10);

    border:
        1px solid
        rgba(59,130,246,.15);
}


.stat-title
{
    color:var(--muted);

    font-size:10px;

    font-weight:bold;

    letter-spacing:.8px;
}


.stat-value
{
    font-size:28px;

    font-weight:bold;

    margin-top:8px;

    letter-spacing:-.5px;
}


.stat-description
{
    color:#64748b;

    font-size:9px;

    margin-top:5px;
}


.blue
{
    color:#60a5fa;
}


.purple
{
    color:#a78bfa;
}


.cyan
{
    color:#22d3ee;
}


.green
{
    color:#4ade80;
}


/* =========================================================
   PANELS
   ========================================================= */

.panel
{
    background:
        var(--panel);

    border:
        1px solid var(--border);

    border-radius:15px;

    overflow:hidden;

    margin-bottom:20px;
}


.panel-header
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:
        18px 20px;

    border-bottom:
        1px solid var(--border);
}


.panel-header h3
{
    margin:0;

    font-size:14px;
}


.panel-header span
{
    color:#64748b;

    font-size:9px;

    letter-spacing:1px;
}


.panel-body
{
    padding:20px;
}


/* =========================================================
   TWO COLUMN
   ========================================================= */

.two-column
{
    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:20px;
}


/* =========================================================
   TABLE
   ========================================================= */

.table-container
{
    overflow-x:auto;
}


table
{
    width:100%;

    border-collapse:collapse;
}


th
{
    background:
        rgba(59,130,246,.08);

    color:#93c5fd;

    padding:12px;

    text-align:left;

    font-size:10px;

    letter-spacing:.5px;

    border-bottom:
        1px solid var(--border);
}


td
{
    padding:12px;

    border-bottom:
        1px solid
        rgba(148,163,184,.08);

    color:#cbd5e1;

    font-size:11px;
}


tr
{
    transition:.15s;
}


tr:hover
{
    background:
        rgba(59,130,246,.035);
}


.number
{
    text-align:center;

    font-weight:bold;

    color:#f8fafc;
}


/* =========================================================
   PROGRESS
   ========================================================= */

.progress-wrapper
{
    min-width:130px;
}


.progress-top
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:6px;

    font-size:9px;

    color:#94a3b8;
}


.progress-bar
{
    width:100%;

    height:6px;

    border-radius:20px;

    background:
        rgba(255,255,255,.06);

    overflow:hidden;
}


.progress-fill
{
    height:100%;

    border-radius:20px;

    background:
        linear-gradient(
            90deg,
            #3b82f6,
            #8b5cf6
        );
}


/* =========================================================
   TOP STUDENTS
   ========================================================= */

.top-student
{
    display:grid;

    grid-template-columns:
        44px 1fr auto;

    align-items:center;

    gap:13px;

    padding:13px;

    margin-bottom:10px;

    border-radius:12px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(148,163,184,.08);

    transition:.2s;
}


.top-student:hover
{
    background:
        rgba(139,92,246,.06);

    border-color:
        rgba(139,92,246,.15);

    transform:
        translateX(3px);
}


.rank
{
    width:38px;

    height:38px;

    border-radius:11px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:12px;

    font-weight:bold;

    background:
        linear-gradient(
            135deg,
            rgba(139,92,246,.20),
            rgba(59,130,246,.15)
        );

    color:#c4b5fd;

    border:
        1px solid
        rgba(139,92,246,.18);
}


.student-info
{
    min-width:0;
}


.student-name
{
    font-size:12px;

    font-weight:bold;

    color:white;

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;
}


.student-details
{
    color:#64748b;

    font-size:9px;

    margin-top:4px;
}


.cgpa
{
    min-width:58px;

    text-align:center;

    padding:8px 9px;

    border-radius:9px;

    color:#86efac;

    background:
        rgba(34,197,94,.07);

    border:
        1px solid
        rgba(34,197,94,.13);

    font-size:12px;

    font-weight:bold;
}


/* =========================================================
   COMPLETE REPORT
   ========================================================= */

.report-header
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:15px;
}


.report-count
{
    color:#64748b;

    font-size:10px;

    padding:
        6px 10px;

    border-radius:8px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid var(--border);
}


.student-table th
{
    white-space:nowrap;
}


.student-table td
{
    white-space:nowrap;
}


.roll
{
    color:#60a5fa;

    font-weight:bold;
}


.department-badge
{
    display:inline-block;

    padding:
        5px 8px;

    border-radius:7px;

    background:
        rgba(139,92,246,.08);

    color:#c4b5fd;

    border:
        1px solid
        rgba(139,92,246,.12);

    font-size:9px;

    font-weight:bold;
}


.semester-badge
{
    display:inline-block;

    padding:
        5px 8px;

    border-radius:7px;

    background:
        rgba(6,182,212,.08);

    color:#67e8f9;

    border:
        1px solid
        rgba(6,182,212,.12);

    font-size:9px;

    font-weight:bold;
}


.cgpa-value
{
    color:#4ade80;

    font-weight:bold;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty
{
    text-align:center;

    padding:40px 20px;

    color:#64748b;

    font-size:11px;

    line-height:1.7;
}


.empty-icon
{
    font-size:30px;

    color:#475569;

    margin-bottom:8px;
}


/* =========================================================
   FOOTER
   ========================================================= */

.footer
{
    text-align:center;

    color:#475569;

    font-size:9px;

    padding:
        10px 0 5px;

    letter-spacing:.5px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1050px)
{

    .statistics
    {
        grid-template-columns:
            repeat(2,1fr);
    }


    .print-option-grid
    {
        grid-template-columns:
            repeat(3,1fr);
    }


    .individual-grid
    {
        grid-template-columns:
            repeat(2,1fr);
    }

}


@media(max-width:900px)
{

    .sidebar
    {
        position:relative;

        width:100%;

        bottom:auto;

        min-height:auto;
    }


    .main
    {
        margin-left:0;

        width:100%;

        padding:20px;
    }


    .topbar
    {
        flex-direction:column;

        align-items:flex-start;

        gap:15px;
    }


    .two-column
    {
        grid-template-columns:1fr;
    }


    .top-right
    {
        width:100%;
    }

}


@media(max-width:600px)
{

    .statistics
    {
        grid-template-columns:1fr;
    }


    .action-bar
    {
        justify-content:flex-start;
    }


    .date
    {
        display:none;
    }


    .profile
    {
        margin-left:auto;
    }


    .hero
    {
        padding:22px;
    }


    .top-student
    {
        grid-template-columns:
            38px 1fr;
    }


    .cgpa
    {
        grid-column:2;

        width:max-content;
    }


    .print-option-grid
    {
        grid-template-columns:
            1fr 1fr;
    }


    .search-form
    {
        flex-direction:column;
    }


    .search-button,
    .clear-search
    {
        min-height:40px;
    }


    .individual-grid
    {
        grid-template-columns:1fr;
    }


    .individual-header
    {
        align-items:flex-start;

        flex-direction:column;

        gap:5px;
    }

}


/* =========================================================
   PRINT
   ========================================================= */

@media print
{

    body
    {
        background:white;

        color:black;
    }


    body::before,
    body::after
    {
        display:none;
    }


    .sidebar
    {
        display:none !important;
    }


    .main
    {
        margin-left:0;

        width:100%;

        padding:0;
    }


    .topbar
    {
        border-bottom:
            2px solid #222;

        padding-bottom:15px;
    }


    .page-label
    {
        color:#555;
    }


    .page-title
    {
        color:#111;
    }


    .date,
    .profile
    {
        color:#222;

        border:none;

        background:none;
    }


    .avatar
    {
        display:none;
    }


    .hero
    {
        background:white;

        color:black;

        border:
            1px solid #ddd;
    }


    .hero h2
    {
        color:#111;
    }


    .hero p
    {
        color:#555;
    }


    .action-bar,
    .print-options,
    .student-search-panel
    {
        display:none !important;
    }


    .panel,
    .stat-card,
    .individual-report
    {
        background:white;

        border:
            1px solid #ddd;

        box-shadow:none;
    }


    .panel-header,
    .individual-header
    {
        border-bottom:
            1px solid #ddd;
    }


    .panel-header h3,
    .individual-header h3
    {
        color:#111;
    }


    th
    {
        background:#e5e7eb;

        color:#111;
    }


    td
    {
        color:#222;

        border-bottom:
            1px solid #ddd;
    }


    .footer
    {
        color:#777;
    }


    .individual-actions
    {
        display:none;
    }

}


/* =========================================================
   SELECTIVE PRINT MODE
   ========================================================= */

body.print-department .printable-section,
body.print-semester .printable-section,
body.print-performers .printable-section,
body.print-complete .printable-section,
body.print-individual .printable-section
{
    display:none;
}


body.print-department #departmentReport
{
    display:block !important;
}


body.print-semester #semesterReport
{
    display:block !important;
}


body.print-performers #performersReport
{
    display:block !important;
}


body.print-complete #completeReport
{
    display:block !important;
}


body.print-individual #individualReport
{
    display:block !important;
}


@media print
{

    body.print-department .hero,
    body.print-department .topbar,
    body.print-department .footer
    {
        display:block !important;
    }


    body.print-semester .hero,
    body.print-semester .topbar,
    body.print-semester .footer
    {
        display:block !important;
    }


    body.print-performers .hero,
    body.print-performers .topbar,
    body.print-performers .footer
    {
        display:block !important;
    }


    body.print-complete .hero,
    body.print-complete .topbar,
    body.print-complete .footer
    {
        display:block !important;
    }


    body.print-individual .hero,
    body.print-individual .topbar,
    body.print-individual .footer
    {
        display:block !important;
    }


    body.print-department #departmentReport,
    body.print-semester #semesterReport,
    body.print-performers #performersReport,
    body.print-complete #completeReport,
    body.print-individual #individualReport
    {
        display:block !important;
    }

}

</style>

</head>


<body>


<?php require_once __DIR__ . "/includes/admin_sidebar.php"; ?>

<!-- =====================================================
     MAIN
     ===================================================== -->

<main class="main">


<!-- =====================================================
     TOPBAR
     ===================================================== -->

<div class="topbar">


    <div>

        <div class="page-label">
            ANALYTICS & REPORTING CENTER
        </div>


        <h1 class="page-title">
            Reports & Analytics
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

                <div
                    style="
                    font-size:12px;
                    font-weight:bold;
                    "
                >

                    <?php

                    echo htmlspecialchars(
                        $adminName
                    );

                    ?>

                </div>


                <div
                    style="
                    color:#64748b;
                    font-size:9px;
                    margin-top:2px;
                    "
                >

                    System Administrator

                </div>

            </div>

        </div>

    </div>

</div>



<!-- =====================================================
     HERO
     ===================================================== -->

<section class="hero">


    <h2>
        College Intelligence Dashboard
    </h2>


    <p>

        Monitor student statistics, academic
        performance, department distribution,
        semester distribution and top-performing
        students from one centralized reporting
        interface.

    </p>


</section>



<!-- =====================================================
     ACTION BAR
     ===================================================== -->

<div class="action-bar">


    <button
        class="action-button"
        onclick="togglePrintOptions()"
    >

        ⎙ &nbsp; PRINT REPORT

    </button>


</div>



<!-- =====================================================
     PRINT OPTIONS
     ===================================================== -->

<div
    class="print-options"
    id="printOptions"
>


    <div class="print-options-title">

        SELECT REPORT TO PRINT

    </div>


    <div class="print-option-grid">


        <button
            class="print-option"
            onclick="printReport('department')"
        >

            <span class="print-option-icon">
                ◫
            </span>

            Department Distribution

        </button>


        <button
            class="print-option"
            onclick="printReport('semester')"
        >

            <span class="print-option-icon">
                ▤
            </span>

            Semester Distribution

        </button>


        <button
            class="print-option"
            onclick="printReport('performers')"
        >

            <span class="print-option-icon">
                ▲
            </span>

            Academic Performers

        </button>


        <button
            class="print-option"
            onclick="printReport('complete')"
        >

            <span class="print-option-icon">
                ▥
            </span>

            Complete Student Report

        </button>


        <button
            class="print-option"
            onclick="printIndividualFromMenu()"
        >

            <span class="print-option-icon">
                ♙
            </span>

            Individual Student Report

        </button>


    </div>


</div>



<!-- =====================================================
     SEARCH STUDENT
     ===================================================== -->

<section class="student-search-panel">


    <div class="student-search-title">

        Individual Student Report

    </div>


    <div class="student-search-description">

        Search a student using the Roll Number to view
        complete academic and personal information.

    </div>


    <form
        method="GET"
        action="reports.php"
        class="search-form"
    >


        <input
            type="text"
            name="student_roll_no"
            class="search-input"
            placeholder="Enter student Roll Number e.g. 26mcaa45"
            value="<?php
                echo htmlspecialchars(
                    $searchRollNo
                );
            ?>"
            required
        >


        <button
            type="submit"
            class="search-button"
        >

            SEARCH STUDENT

        </button>


        <?php

        if ($searchRollNo != "")
        {

        ?>

            <a
                href="reports.php"
                class="clear-search"
            >

                CLEAR

            </a>

        <?php

        }

        ?>


    </form>


    <?php

    if ($searchMessage != "")
    {

    ?>

        <div
            class="search-message
            <?php
            echo $searchMessageType;
            ?>"
        >

            <?php

            echo htmlspecialchars(
                $searchMessage
            );

            ?>

        </div>

    <?php

    }

    ?>


</section>



<!-- =====================================================
     INDIVIDUAL STUDENT REPORT
     ===================================================== -->

<?php

if ($searchedStudent != null)
{

?>

<section
    class="individual-report printable-section"
    id="individualReport"
>


    <div class="individual-header">


        <h3>
            Individual Student Report
        </h3>


        <span>
            STUDENT INFORMATION
        </span>


    </div>


    <div class="individual-body">


        <div class="individual-grid">


            <div class="info-box">

                <div class="info-label">
                    ROLL NUMBER
                </div>

                <div class="info-value">
                    <?php

                    echo htmlspecialchars(
                        $searchedStudent["roll_no"]
                    );

                    ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    STUDENT NAME
                </div>

                <div class="info-value">
                    <?php

                    echo htmlspecialchars(
                        $searchedStudent["name"]
                    );

                    ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    DEPARTMENT
                </div>

                <div class="info-value">
                    <?php

                    echo htmlspecialchars(
                        strtoupper(
                            $searchedStudent["department"]
                        )
                    );

                    ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    SEMESTER
                </div>

                <div class="info-value">
                    <?php

                    echo htmlspecialchars(
                        $searchedStudent["semester"]
                    );

                    ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    PHONE NUMBER
                </div>

                <div class="info-value">
                    <?php

                    echo htmlspecialchars(
                        $searchedStudent["phone"]
                    );

                    ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    CGPA
                </div>

                <div
                    class="info-value"
                    style="color:#4ade80;"
                >

                    <?php

                    echo number_format(
                        (float)$searchedStudent["cgpa"],
                        2
                    );

                    ?>

                </div>

            </div>


        </div>


        <div class="individual-actions">


            <button
                class="individual-print-button"
                onclick="printReport('individual')"
            >

                ⎙ &nbsp; PRINT INDIVIDUAL REPORT

            </button>


        </div>


    </div>


</section>

<?php

}

?>



<!-- =====================================================
     STATISTICS
     ===================================================== -->

<section class="statistics">


    <div class="stat-card">


        <div class="stat-icon blue">
            ♙
        </div>


        <div class="stat-title">
            TOTAL STUDENTS
        </div>


        <div class="stat-value blue">

            <?php

            echo $totalStudents;

            ?>

        </div>


        <div class="stat-description">

            Registered students in system

        </div>


    </div>



    <div class="stat-card">


        <div class="stat-icon purple">
            ◈
        </div>


        <div class="stat-title">
            AVERAGE CGPA
        </div>


        <div class="stat-value purple">

            <?php

            echo number_format(
                $averageCGPA,
                2
            );

            ?>

        </div>


        <div class="stat-description">

            Overall academic average

        </div>


    </div>



    <div class="stat-card">


        <div class="stat-icon cyan">
            ▲
        </div>


        <div class="stat-title">
            HIGHEST CGPA
        </div>


        <div class="stat-value cyan">

            <?php

            echo number_format(
                $highestCGPA,
                2
            );

            ?>

        </div>


        <div class="stat-description">

            Highest academic performance

        </div>


    </div>



    <div class="stat-card">


        <div class="stat-icon green">
            ▼
        </div>


        <div class="stat-title">
            LOWEST CGPA
        </div>


        <div class="stat-value green">

            <?php

            echo number_format(
                $lowestCGPA,
                2
            );

            ?>

        </div>


        <div class="stat-description">

            Lowest recorded CGPA

        </div>


    </div>


</section>



<!-- =====================================================
     DEPARTMENT + SEMESTER
     ===================================================== -->

<section class="two-column">


<!-- =====================================================
     DEPARTMENT REPORT
     ===================================================== -->

<div
    class="panel printable-section"
    id="departmentReport"
>


    <div class="panel-header">


        <h3>
            Department Distribution
        </h3>


        <span>
            DEPARTMENT ANALYTICS
        </span>


    </div>


    <div class="panel-body">


        <div class="table-container">


        <?php

        if (
            count($departmentData) == 0
        )
        {

        ?>


            <div class="empty">

                <div class="empty-icon">
                    ◌
                </div>

                No department data available.

            </div>


        <?php

        }
        else
        {

        ?>


            <table>


                <thead>

                    <tr>

                        <th>
                            DEPARTMENT
                        </th>

                        <th>
                            STUDENTS
                        </th>

                        <th>
                            DISTRIBUTION
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php

                foreach (
                    $departmentData
                    as $department
                )
                {

                    $count =
                        (int)$department["total"];

                    $percentage =
                        percentageValue(
                            $count,
                            $totalStudents
                        );

                ?>


                    <tr>


                        <td>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    strtoupper(
                                        $department["department"]
                                    )
                                );

                                ?>

                            </strong>

                        </td>


                        <td class="number">

                            <?php

                            echo $count;

                            ?>

                        </td>


                        <td>


                            <div class="progress-wrapper">


                                <div class="progress-top">

                                    <span>
                                        STUDENTS
                                    </span>

                                    <span>

                                        <?php

                                        echo $percentage;

                                        ?>%

                                    </span>

                                </div>


                                <div class="progress-bar">

                                    <div
                                        class="progress-fill"
                                        style="
                                        width:
                                        <?php
                                        echo $percentage;
                                        ?>%;
                                        "
                                    ></div>

                                </div>


                            </div>


                        </td>


                    </tr>


                <?php

                }

                ?>


                </tbody>


            </table>


        <?php

        }

        ?>


        </div>


    </div>


</div>



<!-- =====================================================
     SEMESTER REPORT
     ===================================================== -->

<div
    class="panel printable-section"
    id="semesterReport"
>


    <div class="panel-header">


        <h3>
            Semester Distribution
        </h3>


        <span>
            ACADEMIC ANALYTICS
        </span>


    </div>


    <div class="panel-body">


        <div class="table-container">


        <?php

        if (
            count($semesterData) == 0
        )
        {

        ?>


            <div class="empty">

                <div class="empty-icon">
                    ◌
                </div>

                No semester data available.

            </div>


        <?php

        }
        else
        {

        ?>


            <table>


                <thead>

                    <tr>

                        <th>
                            SEMESTER
                        </th>

                        <th>
                            STUDENTS
                        </th>

                        <th>
                            DISTRIBUTION
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php

                foreach (
                    $semesterData
                    as $semester
                )
                {

                    $count =
                        (int)$semester["total"];

                    $percentage =
                        percentageValue(
                            $count,
                            $totalStudents
                        );

                ?>


                    <tr>


                        <td>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $semester["semester"]
                                );

                                ?>

                            </strong>

                        </td>


                        <td class="number">

                            <?php

                            echo $count;

                            ?>

                        </td>


                        <td>


                            <div class="progress-wrapper">


                                <div class="progress-top">

                                    <span>
                                        STUDENTS
                                    </span>

                                    <span>

                                        <?php

                                        echo $percentage;

                                        ?>%

                                    </span>

                                </div>


                                <div class="progress-bar">

                                    <div
                                        class="progress-fill"
                                        style="
                                        width:
                                        <?php
                                        echo $percentage;
                                        ?>%;
                                        "
                                    ></div>

                                </div>


                            </div>


                        </td>


                    </tr>


                <?php

                }

                ?>


                </tbody>


            </table>


        <?php

        }

        ?>


        </div>


    </div>


</div>


</section>



<!-- =====================================================
     TOP 5 STUDENTS
     ===================================================== -->

<section
    class="panel printable-section"
    id="performersReport"
>


    <div class="panel-header">


        <h3>
            Top 5 Academic Performers
        </h3>


        <span>
            PERFORMANCE LEADERBOARD
        </span>


    </div>


    <div class="panel-body">


    <?php

    if (
        !$topStudentsResult ||
        $topStudentsResult->num_rows == 0
    )
    {

    ?>


        <div class="empty">

            <div class="empty-icon">
                ◌
            </div>

            No student performance data available.

        </div>


    <?php

    }
    else
    {

        $rank = 1;


        while (
            $student =
            $topStudentsResult->fetch_assoc()
        )
        {

    ?>


        <div class="top-student">


            <div class="rank">

                #

                <?php

                echo $rank;

                ?>

            </div>


            <div class="student-info">


                <div class="student-name">

                    <?php

                    echo htmlspecialchars(
                        $student["name"]
                    );

                    ?>

                </div>


                <div class="student-details">

                    Roll No:

                    <?php

                    echo htmlspecialchars(
                        $student["roll_no"]
                    );

                    ?>

                    &nbsp; • &nbsp;

                    <?php

                    echo htmlspecialchars(
                        strtoupper(
                            $student["department"]
                        )
                    );

                    ?>

                    &nbsp; • &nbsp;

                    Semester

                    <?php

                    echo htmlspecialchars(
                        $student["semester"]
                    );

                    ?>

                </div>


            </div>


            <div class="cgpa">

                <?php

                echo number_format(
                    (float)$student["cgpa"],
                    2
                );

                ?>

            </div>


        </div>


    <?php


            $rank++;

        }

    }

    ?>


    </div>


</section>



<!-- =====================================================
     COMPLETE STUDENT REPORT
     ===================================================== -->

<section
    class="panel printable-section"
    id="completeReport"
>


    <div class="panel-header">


        <h3>
            Complete Student Report
        </h3>


        <span>
            FULL DATABASE REPORT
        </span>


    </div>


    <div class="panel-body">


        <div class="report-header">


            <div
                style="
                color:#94a3b8;
                font-size:11px;
                "
            >

                Complete list of registered students

            </div>


            <div class="report-count">

                <?php

                echo $totalStudents;

                ?>

                RECORDS

            </div>


        </div>


        <div class="table-container">


        <?php

        if (
            !$studentResult ||
            $studentResult->num_rows == 0
        )
        {

        ?>


            <div class="empty">

                <div class="empty-icon">
                    ◌
                </div>

                No students available.

            </div>


        <?php

        }
        else
        {

        ?>


            <table class="student-table">


                <thead>


                    <tr>


                        <th>
                            ROLL NO
                        </th>


                        <th>
                            NAME
                        </th>


                        <th>
                            DEPARTMENT
                        </th>


                        <th>
                            SEMESTER
                        </th>


                        <th>
                            PHONE
                        </th>


                        <th>
                            CGPA
                        </th>


                    </tr>


                </thead>


                <tbody>


                <?php

                while (
                    $student =
                    $studentResult->fetch_assoc()
                )
                {

                ?>


                    <tr>


                        <td class="roll">

                            <?php

                            echo htmlspecialchars(
                                $student["roll_no"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $student["name"]
                            );

                            ?>

                        </td>


                        <td>


                            <span
                                class="department-badge"
                            >

                                <?php

                                echo htmlspecialchars(
                                    strtoupper(
                                        $student["department"]
                                    )
                                );

                                ?>

                            </span>


                        </td>


                        <td>


                            <span
                                class="semester-badge"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $student["semester"]
                                );

                                ?>

                            </span>


                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $student["phone"]
                            );

                            ?>

                        </td>


                        <td class="cgpa-value">

                            <?php

                            echo number_format(
                                (float)$student["cgpa"],
                                2
                            );

                            ?>

                        </td>


                    </tr>


                <?php

                }

                ?>


                </tbody>


            </table>


        <?php

        }

        ?>


        </div>


    </div>


</section>



<!-- =====================================================
     FOOTER
     ===================================================== -->

<div class="footer">

    SMART CAMPUS MANAGEMENT SYSTEM

    &nbsp; • &nbsp;

    REPORTS & ANALYTICS

    &nbsp; • &nbsp;

    VERSION 1.0

</div>


</main>



<!-- =====================================================
     JAVASCRIPT
     ===================================================== -->

<script>


/* =========================================================
   TOGGLE PRINT OPTIONS
   ========================================================= */

function togglePrintOptions()
{
    var options =
        document.getElementById(
            "printOptions"
        );

    if (
        options.classList.contains("show")
    )
    {
        options.classList.remove("show");
    }
    else
    {
        options.classList.add("show");
    }
}


/* =========================================================
   PRINT SELECTED REPORT
   ========================================================= */

function printReport(type)
{
    document.body.classList.remove(
        "print-department",
        "print-semester",
        "print-performers",
        "print-complete",
        "print-individual"
    );


    if (type == "department")
    {
        document.body.classList.add(
            "print-department"
        );
    }


    if (type == "semester")
    {
        document.body.classList.add(
            "print-semester"
        );
    }


    if (type == "performers")
    {
        document.body.classList.add(
            "print-performers"
        );
    }


    if (type == "complete")
    {
        document.body.classList.add(
            "print-complete"
        );
    }


    if (type == "individual")
    {
        var individual =
            document.getElementById(
                "individualReport"
            );


        if (!individual)
        {
            alert(
                "Please search a student by Roll Number first."
            );

            return;
        }


        document.body.classList.add(
            "print-individual"
        );
    }


    setTimeout(
        function()
        {
            window.print();
        },
        100
    );
}


/* =========================================================
   INDIVIDUAL REPORT FROM PRINT MENU
   ========================================================= */

function printIndividualFromMenu()
{
    var individual =
        document.getElementById(
            "individualReport"
        );


    if (!individual)
    {
        alert(
            "Please search a student by Roll Number first."
        );

        return;
    }


    printReport("individual");
}


/* =========================================================
   RESET AFTER PRINT
   ========================================================= */

window.onafterprint =
function()
{
    document.body.classList.remove(
        "print-department",
        "print-semester",
        "print-performers",
        "print-complete",
        "print-individual"
    );
};

</script>


</body>

</html>


<?php

$conn->close();

?>