<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";


/* =========================================================
   BASIC SETTINGS
   ========================================================= */

$searchType = "";
$searchValue = "";
$sortType = "";

$result = null;

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
   SEARCH
   ========================================================= */

if (isset($_GET["search"]))
{
    $searchType =
        isset($_GET["search_type"])
        ? $_GET["search_type"]
        : "";

    $searchValue =
        isset($_GET["search_value"])
        ? trim($_GET["search_value"])
        : "";


    if ($searchValue != "")
    {

        if ($searchType == "roll_no")
        {

            $sql =
                "SELECT
                    id,
                    roll_no,
                    name,
                    department,
                    semester,
                    phone,
                    cgpa
                 FROM students
                 WHERE roll_no LIKE ?
                   AND EXISTS (
                        SELECT 1
                        FROM student_applications sa
                        WHERE sa.student_id = students.id
                          AND sa.application_status = 'Accepted'
                   )
                 ORDER BY name ASC";


            $stmt = $conn->prepare($sql);


            if ($stmt)
            {

                $searchPattern =
                    "%" . $searchValue . "%";


                $stmt->bind_param(
                    "s",
                    $searchPattern
                );


                $stmt->execute();


                $result =
                    $stmt->get_result();

            }

        }

        elseif ($searchType == "name")
        {

            $sql =
                "SELECT
                    id,
                    roll_no,
                    name,
                    department,
                    semester,
                    phone,
                    cgpa
                 FROM students
                 WHERE name LIKE ?
                   AND EXISTS (
                        SELECT 1
                        FROM student_applications sa
                        WHERE sa.student_id = students.id
                          AND sa.application_status = 'Accepted'
                   )
                 ORDER BY name ASC";


            $stmt = $conn->prepare($sql);


            if ($stmt)
            {

                $searchPattern =
                    "%" . $searchValue . "%";


                $stmt->bind_param(
                    "s",
                    $searchPattern
                );


                $stmt->execute();


                $result =
                    $stmt->get_result();

            }

        }

    }

}


/* =========================================================
   SORT
   ========================================================= */

elseif (isset($_GET["sort"]))
{

    $sortType =
        isset($_GET["sort_type"])
        ? $_GET["sort_type"]
        : "";


    if ($sortType == "name_asc")
    {

        $result =
            $conn->query(
                "SELECT
                    id,
                    roll_no,
                    name,
                    department,
                    semester,
                    phone,
                    cgpa
                 FROM students
                 WHERE EXISTS (
                    SELECT 1
                    FROM student_applications sa
                    WHERE sa.student_id = students.id
                      AND sa.application_status = 'Accepted'
                 )
                 ORDER BY name ASC"
            );

    }

    elseif ($sortType == "name_desc")
    {

        $result =
            $conn->query(
                "SELECT
                    id,
                    roll_no,
                    name,
                    department,
                    semester,
                    phone,
                    cgpa
                 FROM students
                 WHERE EXISTS (
                    SELECT 1
                    FROM student_applications sa
                    WHERE sa.student_id = students.id
                      AND sa.application_status = 'Accepted'
                 )
                 ORDER BY name DESC"
            );

    }

    elseif ($sortType == "cgpa_high")
    {

        $result =
            $conn->query(
                "SELECT
                    id,
                    roll_no,
                    name,
                    department,
                    semester,
                    phone,
                    cgpa
                 FROM students
                 WHERE EXISTS (
                    SELECT 1
                    FROM student_applications sa
                    WHERE sa.student_id = students.id
                      AND sa.application_status = 'Accepted'
                 )
                 ORDER BY cgpa DESC"
            );

    }

    elseif ($sortType == "cgpa_low")
    {

        $result =
            $conn->query(
                "SELECT
                    id,
                    roll_no,
                    name,
                    department,
                    semester,
                    phone,
                    cgpa
                 FROM students
                 WHERE EXISTS (
                    SELECT 1
                    FROM student_applications sa
                    WHERE sa.student_id = students.id
                      AND sa.application_status = 'Accepted'
                 )
                 ORDER BY cgpa ASC"
            );

    }

}


/* =========================================================
   DEFAULT STUDENT LIST
   ========================================================= */

if ($result === null)
{

    $result =
        $conn->query(
            "SELECT
                id,
                roll_no,
                name,
                department,
                semester,
                phone,
                cgpa
             FROM students
             WHERE EXISTS (
                SELECT 1
                FROM student_applications sa
                WHERE sa.student_id = students.id
                  AND sa.application_status = 'Accepted'
             )
             ORDER BY id DESC"
        );

}


/* =========================================================
   RESULT COUNT
   ========================================================= */

$resultCount = 0;

if ($result)
{
    $resultCount = $result->num_rows;
}

?>

<!DOCTYPE html>

<html>

<head>

<title>
Search & Sort | Smart Campus
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

    --border:rgba(148,163,184,0.14);

    --text:#f8fafc;

    --muted:#94a3b8;

    --blue:#3b82f6;

    --cyan:#06b6d4;

    --purple:#8b5cf6;

    --green:#22c55e;

    --red:#ef4444;
}


html
{
    scroll-behavior:smooth;
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
            circle at 90% 15%,
            rgba(139,92,246,0.13),
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

    z-index:0;
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

    transition:.2s;
}


.logout:hover
{
    background:
        rgba(239,68,68,.14);

    color:white;
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
    content:"⌕";

    position:absolute;

    right:35px;

    top:50%;

    transform:
        translateY(-50%);

    font-size:75px;

    color:
        rgba(255,255,255,.045);
}


.hero h2
{
    margin:
        0 0 7px;

    font-size:22px;
}


.hero p
{
    margin:0;

    max-width:800px;

    color:#a8b5c8;

    font-size:13px;

    line-height:1.6;
}


/* =========================================================
   CARD
   ========================================================= */

.card
{
    background:
        var(--panel);

    border:
        1px solid var(--border);

    border-radius:15px;

    overflow:hidden;

    margin-bottom:20px;

    box-shadow:
        0 12px 35px
        rgba(0,0,0,.12);
}


.card-header
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:
        18px 20px;

    border-bottom:
        1px solid var(--border);
}


.card-title
{
    display:flex;

    align-items:center;

    gap:12px;
}


.card-icon
{
    width:36px;

    height:36px;

    border-radius:10px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:
        rgba(139,92,246,.12);

    border:
        1px solid
        rgba(139,92,246,.18);

    color:#c4b5fd;

    font-size:17px;
}


.card-header h2
{
    margin:0;

    font-size:14px;
}


.card-header p
{
    margin:3px 0 0;

    color:#64748b;

    font-size:9px;
}


.card-tag
{
    color:#64748b;

    font-size:9px;

    letter-spacing:1px;
}


/* =========================================================
   SEARCH FORM
   ========================================================= */

.form-body
{
    padding:20px;
}


.form-grid
{
    display:grid;

    grid-template-columns:
        0.8fr 1.5fr auto;

    gap:15px;

    align-items:end;
}


.form-group
{
    display:flex;

    flex-direction:column;
}


.form-group label
{
    color:#94a3b8;

    font-size:10px;

    font-weight:bold;

    letter-spacing:.5px;

    margin-bottom:7px;
}


.form-group input,
.form-group select
{
    width:100%;

    padding:13px;

    border:
        1px solid
        rgba(148,163,184,.15);

    border-radius:10px;

    outline:none;

    color:white;

    background:
        rgba(255,255,255,.035);

    font-size:12px;

    transition:.2s;
}


.form-group input::placeholder
{
    color:#64748b;
}


.form-group input:focus,
.form-group select:focus
{
    border-color:
        rgba(139,92,246,.60);

    box-shadow:
        0 0 0 3px
        rgba(139,92,246,.08);
}


.form-group select option
{
    background:#0d1324;

    color:white;
}


.search-button
{
    min-width:125px;

    padding:13px 18px;

    border:none;

    border-radius:10px;

    cursor:pointer;

    color:white;

    font-size:12px;

    font-weight:bold;

    background:
        linear-gradient(
            135deg,
            #8b5cf6,
            #3b82f6
        );

    transition:.2s;
}


.search-button:hover
{
    transform:
        translateY(-2px);

    box-shadow:
        0 10px 30px
        rgba(139,92,246,.25);
}


.reset-row
{
    margin-top:14px;

    display:flex;

    justify-content:space-between;

    align-items:center;
}


.search-tip
{
    color:#475569;

    font-size:10px;
}


.reset
{
    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:9px 13px;

    background:
        rgba(100,116,139,.08);

    color:#94a3b8;

    text-decoration:none;

    border:
        1px solid
        rgba(148,163,184,.10);

    border-radius:8px;

    font-size:10px;

    transition:.2s;
}


.reset:hover
{
    color:white;

    background:
        rgba(100,116,139,.16);
}


/* =========================================================
   SORT
   ========================================================= */

.sort-body
{
    padding:20px;
}


.sort-grid
{
    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:12px;
}


.sort-form
{
    margin:0;
}


.sort-button
{
    width:100%;

    min-height:75px;

    padding:14px;

    border:
        1px solid
        rgba(59,130,246,.12);

    border-radius:11px;

    background:
        rgba(59,130,246,.055);

    color:#cbd5e1;

    font-weight:bold;

    cursor:pointer;

    font-size:11px;

    transition:.2s;
}


.sort-button:hover
{
    color:white;

    transform:
        translateY(-3px);

    background:
        linear-gradient(
            135deg,
            rgba(139,92,246,.18),
            rgba(59,130,246,.12)
        );

    border-color:
        rgba(139,92,246,.30);

    box-shadow:
        0 10px 25px
        rgba(59,130,246,.12);
}


.sort-icon
{
    display:block;

    font-size:21px;

    color:#a78bfa;

    margin-bottom:7px;
}


/* =========================================================
   RESULTS HEADER
   ========================================================= */

.results-header
{
    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:
        18px 20px;

    border-bottom:
        1px solid var(--border);
}


.results-title
{
    display:flex;

    align-items:center;

    gap:10px;
}


.results-title h2
{
    margin:0;

    font-size:14px;
}


.results-badge
{
    padding:
        5px 9px;

    border-radius:20px;

    color:#c4b5fd;

    background:
        rgba(139,92,246,.10);

    border:
        1px solid
        rgba(139,92,246,.15);

    font-size:9px;

    font-weight:bold;
}


.result-info
{
    padding:
        14px 20px;

    color:#64748b;

    font-size:10px;

    border-bottom:
        1px solid
        rgba(148,163,184,.06);
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

    min-width:900px;
}


th
{
    background:
        rgba(59,130,246,.07);

    color:#94a3b8;

    padding:13px 14px;

    text-align:left;

    font-size:9px;

    letter-spacing:.8px;

    border-bottom:
        1px solid
        rgba(148,163,184,.10);
}


td
{
    padding:13px 14px;

    border-bottom:
        1px solid
        rgba(148,163,184,.07);

    font-size:11px;

    color:#cbd5e1;
}


tbody tr
{
    transition:.18s;
}


tbody tr:hover
{
    background:
        rgba(59,130,246,.045);
}


.id-badge
{
    display:inline-block;

    min-width:28px;

    text-align:center;

    padding:4px 7px;

    border-radius:6px;

    color:#94a3b8;

    background:
        rgba(148,163,184,.07);

    font-size:9px;
}


.roll
{
    color:#60a5fa;

    font-weight:bold;
}


.student-name
{
    color:white;

    font-weight:bold;
}


.department
{
    display:inline-block;

    padding:5px 8px;

    border-radius:6px;

    color:#c4b5fd;

    background:
        rgba(139,92,246,.08);

    border:
        1px solid
        rgba(139,92,246,.12);

    font-size:9px;

    font-weight:bold;
}


.semester
{
    color:#94a3b8;

    font-size:10px;
}


.phone
{
    color:#94a3b8;
}


.cgpa
{
    display:inline-block;

    padding:5px 8px;

    border-radius:7px;

    color:#86efac;

    background:
        rgba(34,197,94,.07);

    border:
        1px solid
        rgba(34,197,94,.12);

    font-weight:bold;

    font-size:10px;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty
{
    text-align:center;

    padding:55px 20px;

    color:#64748b;

    font-size:12px;

    line-height:1.8;
}


.empty-icon
{
    width:55px;

    height:55px;

    margin:
        0 auto 12px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:15px;

    background:
        rgba(139,92,246,.07);

    border:
        1px solid
        rgba(139,92,246,.12);

    color:#a78bfa;

    font-size:25px;
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

@media(max-width:1100px)
{

    .sort-grid
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


    .form-grid
    {
        grid-template-columns:1fr;
    }


    .search-button
    {
        width:100%;
    }

}


@media(max-width:600px)
{

    .sort-grid
    {
        grid-template-columns:1fr;
    }


    .top-right
    {
        width:100%;

        flex-direction:column;

        align-items:flex-start;
    }


    .date,
    .profile
    {
        width:100%;
    }


    .hero
    {
        padding:22px;
    }


    .hero::after
    {
        display:none;
    }


    .reset-row
    {
        flex-direction:column;

        align-items:flex-start;

        gap:12px;
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
            DATA DISCOVERY & ANALYTICS
        </div>

        <h1 class="page-title">
            Search & Sort
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
        Student Data Explorer
    </h2>

    <p>

        Quickly locate student records using
        roll number or name, then organize
        the results by name or academic
        performance.

    </p>

</section>



<!-- =====================================================
     SEARCH CARD
     ===================================================== -->

<section class="card">


    <div class="card-header">


        <div class="card-title">


            <div class="card-icon">
                ⌕
            </div>


            <div>

                <h2>
                    Search Students
                </h2>

                <p>
                    Find student records instantly
                </p>

            </div>


        </div>


        <span class="card-tag">
            SEARCH ENGINE
        </span>


    </div>


    <div class="form-body">


        <form method="GET">


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        SEARCH BY
                    </label>


                    <select
                        name="search_type"
                        required
                    >

                        <option
                            value="roll_no"
                            <?php

                            if (
                                $searchType
                                ==
                                "roll_no"
                            )
                            {
                                echo "selected";
                            }

                            ?>
                        >
                            Roll Number
                        </option>


                        <option
                            value="name"
                            <?php

                            if (
                                $searchType
                                ==
                                "name"
                            )
                            {
                                echo "selected";
                            }

                            ?>
                        >
                            Student Name
                        </option>

                    </select>

                </div>



                <div class="form-group">

                    <label>
                        SEARCH VALUE
                    </label>


                    <input
                        type="text"
                        name="search_value"
                        value="<?php

                        echo htmlspecialchars(
                            $searchValue
                        );

                        ?>"
                        placeholder="Enter roll number or student name"
                        required
                    >

                </div>



                <div>

                    <button
                        type="submit"
                        name="search"
                        class="search-button"
                    >

                        ⌕ &nbsp; SEARCH

                    </button>

                </div>


            </div>


        </form>


        <div class="reset-row">


            <div class="search-tip">

                Search supports partial
                roll numbers and names.

            </div>


            <a
                href="search_sort.php"
                class="reset"
            >

                ↻ Reset Search

            </a>


        </div>


    </div>


</section>



<!-- =====================================================
     SORT CARD
     ===================================================== -->

<section class="card">


    <div class="card-header">


        <div class="card-title">


            <div class="card-icon">
                ⇅
            </div>


            <div>

                <h2>
                    Sort Students
                </h2>

                <p>
                    Organize records by name or CGPA
                </p>

            </div>


        </div>


        <span class="card-tag">
            SORT ENGINE
        </span>


    </div>


    <div class="sort-body">


        <div class="sort-grid">


            <form
                method="GET"
                class="sort-form"
            >

                <input
                    type="hidden"
                    name="sort_type"
                    value="name_asc"
                >


                <button
                    type="submit"
                    name="sort"
                    class="sort-button"
                >

                    <span class="sort-icon">
                        A↑
                    </span>

                    Name — A to Z

                </button>

            </form>



            <form
                method="GET"
                class="sort-form"
            >

                <input
                    type="hidden"
                    name="sort_type"
                    value="name_desc"
                >


                <button
                    type="submit"
                    name="sort"
                    class="sort-button"
                >

                    <span class="sort-icon">
                        Z↓
                    </span>

                    Name — Z to A

                </button>

            </form>



            <form
                method="GET"
                class="sort-form"
            >

                <input
                    type="hidden"
                    name="sort_type"
                    value="cgpa_high"
                >


                <button
                    type="submit"
                    name="sort"
                    class="sort-button"
                >

                    <span class="sort-icon">
                        ↑
                    </span>

                    Highest CGPA First

                </button>

            </form>



            <form
                method="GET"
                class="sort-form"
            >

                <input
                    type="hidden"
                    name="sort_type"
                    value="cgpa_low"
                >


                <button
                    type="submit"
                    name="sort"
                    class="sort-button"
                >

                    <span class="sort-icon">
                        ↓
                    </span>

                    Lowest CGPA First

                </button>

            </form>


        </div>


    </div>


</section>



<!-- =====================================================
     RESULTS CARD
     ===================================================== -->

<section class="card">


    <div class="results-header">


        <div class="results-title">


            <h2>
                Student Results
            </h2>


            <span class="results-badge">

                <?php

                echo $resultCount;

                ?>

                RECORDS

            </span>


        </div>


        <span class="card-tag">
            LIVE DATABASE RESULTS
        </span>


    </div>



    <div class="result-info">


        <?php

        if (isset($_GET["search"]))
        {

            if ($searchValue != "")
            {
                echo "Search results for: ";
                echo htmlspecialchars(
                    $searchValue
                );
            }
            else
            {
                echo "Search results";
            }

        }

        elseif (isset($_GET["sort"]))
        {

            if ($sortType == "name_asc")
            {
                echo "Students sorted by name — A to Z";
            }

            elseif ($sortType == "name_desc")
            {
                echo "Students sorted by name — Z to A";
            }

            elseif ($sortType == "cgpa_high")
            {
                echo "Students sorted by CGPA — Highest first";
            }

            elseif ($sortType == "cgpa_low")
            {
                echo "Students sorted by CGPA — Lowest first";
            }

            else
            {
                echo "Sorted student results";
            }

        }

        else
        {
            echo "Showing all student records";
        }

        ?>

    </div>



    <div class="table-container">


        <?php

        if (
            !$result ||
            $result->num_rows == 0
        )
        {

        ?>


            <div class="empty">


                <div class="empty-icon">
                    ⌕
                </div>


                No students found.


                <br>


                Try another search value
                or reset the search.


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
                            ID
                        </th>

                        <th>
                            ROLL NO
                        </th>

                        <th>
                            STUDENT
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
                    $result->fetch_assoc()
                )
                {

                ?>


                    <tr>


                        <td>

                            <span class="id-badge">

                                <?php

                                echo htmlspecialchars(
                                    $student["id"]
                                );

                                ?>

                            </span>

                        </td>



                        <td>

                            <span class="roll">

                                <?php

                                echo htmlspecialchars(
                                    $student["roll_no"]
                                );

                                ?>

                            </span>

                        </td>



                        <td>

                            <span class="student-name">

                                <?php

                                echo htmlspecialchars(
                                    $student["name"]
                                );

                                ?>

                            </span>

                        </td>



                        <td>

                            <span class="department">

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

                            <span class="semester">

                                <?php

                                echo htmlspecialchars(
                                    $student["semester"]
                                );

                                ?>

                            </span>

                        </td>



                        <td>

                            <span class="phone">

                                <?php

                                echo htmlspecialchars(
                                    $student["phone"]
                                );

                                ?>

                            </span>

                        </td>



                        <td>

                            <span class="cgpa">

                                <?php

                                echo number_format(
                                    (float)
                                    $student["cgpa"],
                                    2
                                );

                                ?>

                            </span>

                        </td>

                        <td>
                            <a
                                href="student_profile.php?id=<?php echo $student["id"]; ?>"
                                style="display:inline-block; text-decoration:none; background: rgba(34,197,94,0.10); color: #86efac; border: 1px solid rgba(34,197,94,0.16); border-radius: 7px; padding: 7px 10px; font-size: 10px; font-weight: 700;"
                            >
                                View
                            </a>
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


</section>



<!-- =====================================================
     FOOTER
     ===================================================== -->

<div class="footer">

    SMART CAMPUS MANAGEMENT SYSTEM

    &nbsp; • &nbsp;

    SEARCH & SORT

    &nbsp; • &nbsp;

    VERSION 1.0

</div>


</main>


</body>

</html>


<?php

$conn->close();

?>