<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$message = "";
$messageType = "";

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
   CREATE CONNECTION TABLE
   ========================================================= */

$createTable = $conn->query(
    "CREATE TABLE IF NOT EXISTS student_connections
    (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student1_id INT NOT NULL,
        student2_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);

if (!$createTable)
{
    $message =
        "Unable to create student connection table: "
        . $conn->error;

    $messageType = "error";
}


/* =========================================================
   GET STUDENTS
   ========================================================= */

$students = array();

$studentResult = $conn->query(
    "SELECT
        id,
        roll_no,
        name,
        department,
        semester
     FROM students
     WHERE EXISTS (
        SELECT 1
        FROM student_applications sa
        WHERE sa.student_id = students.id
          AND sa.application_status = 'Accepted'
     )
     ORDER BY name ASC"
);

if ($studentResult)
{
    while ($student = $studentResult->fetch_assoc())
    {
        $students[] = $student;
    }

    $studentResult->free();
}


/* =========================================================
   ADD STUDENT CONNECTION
   ========================================================= */

if (isset($_POST["add_connection"]))
{
    $student1 =
        isset($_POST["student1"])
        ? intval($_POST["student1"])
        : 0;

    $student2 =
        isset($_POST["student2"])
        ? intval($_POST["student2"])
        : 0;


    /* =====================================================
       BASIC VALIDATION
       ===================================================== */

    if ($student1 <= 0 || $student2 <= 0)
    {
        $message =
            "Please select both students.";

        $messageType = "error";
    }

    elseif ($student1 == $student2)
    {
        $message =
            "A student cannot be connected to themselves.";

        $messageType = "error";
    }

    else
    {

        /* =================================================
           IMPORTANT FIX

           Use ONE query instead of two simultaneous
           prepared statements.

           This prevents:
           "Commands out of sync"
        ================================================= */

        $studentQuery = $conn->prepare(
            "SELECT
                id,
                name,
                department,
                semester
             FROM students
             WHERE id IN (?, ?)
               AND EXISTS (
                    SELECT 1
                    FROM student_applications sa
                    WHERE sa.student_id = students.id
                      AND sa.application_status = 'Accepted'
               )"
        );


        if ($studentQuery === false)
        {
            $message =
                "Unable to validate students: "
                . $conn->error;

            $messageType = "error";
        }

        else
        {

            $studentQuery->bind_param(
                "ii",
                $student1,
                $student2
            );


            if (!$studentQuery->execute())
            {
                $message =
                    "Unable to validate students: "
                    . $studentQuery->error;

                $messageType = "error";
            }

            else
            {

                $studentResultCheck =
                    $studentQuery->get_result();


                /*
                 * We must have exactly two students.
                 */

                if (
                    !$studentResultCheck ||
                    $studentResultCheck->num_rows != 2
                )
                {
                    $message =
                        "One or both selected students do not exist.";

                    $messageType = "error";
                }

                else
                {

                    $firstStudent = null;
                    $secondStudent = null;


                    /*
                     * Read both records safely.
                     */

                    while (
                        $row =
                        $studentResultCheck->fetch_assoc()
                    )
                    {
                        if (
                            intval($row["id"])
                            ==
                            $student1
                        )
                        {
                            $firstStudent = $row;
                        }

                        elseif (
                            intval($row["id"])
                            ==
                            $student2
                        )
                        {
                            $secondStudent = $row;
                        }
                    }


                    /*
                     * Free result BEFORE doing another query.
                     * This is important for MySQL.
                     */

                    $studentResultCheck->free();


                    if (
                        $firstStudent === null ||
                        $secondStudent === null
                    )
                    {
                        $message =
                            "Unable to identify both selected students.";

                        $messageType = "error";
                    }

                    else
                    {

                        /* =====================================
                           COMPARE DEPARTMENTS
                           ===================================== */

                        $sameDepartment =
                            strtolower(
                                trim(
                                    $firstStudent["department"]
                                )
                            )
                            ==
                            strtolower(
                                trim(
                                    $secondStudent["department"]
                                )
                            );


                        /* =====================================
                           COMPARE SEMESTERS
                           ===================================== */

                        $sameSemester =
                            strtoupper(
                                trim(
                                    $firstStudent["semester"]
                                )
                            )
                            ==
                            strtoupper(
                                trim(
                                    $secondStudent["semester"]
                                )
                            );


                        /* =====================================
                           DETERMINE CONNECTION REASON
                           ===================================== */

                        $connectionReason = "";


                        if (
                            $sameDepartment &&
                            $sameSemester
                        )
                        {
                            $connectionReason =
                                "Same Department & Same Semester";
                        }

                        elseif ($sameDepartment)
                        {
                            $connectionReason =
                                "Same Department";
                        }

                        elseif ($sameSemester)
                        {
                            $connectionReason =
                                "Same Semester";
                        }


                        /* =====================================
                           CONNECTION RULE

                           DIFFERENT DEPARTMENT +
                           DIFFERENT SEMESTER
                           = NOT ALLOWED
                        ===================================== */

                        if ($connectionReason == "")
                        {
                            $message =
                                "Connection not allowed. Students must have the same department or the same semester.";

                            $messageType = "error";
                        }

                        else
                        {

                            /* =================================
                               CHECK DUPLICATE CONNECTION
                            ================================= */

                            $checkConnection =
                                $conn->prepare(
                                    "SELECT id
                                     FROM student_connections
                                     WHERE
                                     (
                                        student1_id = ?
                                        AND
                                        student2_id = ?
                                     )
                                     OR
                                     (
                                        student1_id = ?
                                        AND
                                        student2_id = ?
                                     )"
                                );


                            if (
                                $checkConnection === false
                            )
                            {
                                $message =
                                    "Connection check failed: "
                                    . $conn->error;

                                $messageType = "error";
                            }

                            else
                            {

                                $checkConnection->bind_param(
                                    "iiii",
                                    $student1,
                                    $student2,
                                    $student2,
                                    $student1
                                );


                                if (
                                    !$checkConnection->execute()
                                )
                                {
                                    $message =
                                        "Connection check failed: "
                                        . $checkConnection->error;

                                    $messageType =
                                        "error";
                                }

                                else
                                {

                                    $connectionResult =
                                        $checkConnection->get_result();


                                    if (
                                        $connectionResult &&
                                        $connectionResult->num_rows > 0
                                    )
                                    {
                                        $message =
                                            "These students are already connected.";

                                        $messageType =
                                            "error";
                                    }

                                    else
                                    {

                                        /* =====================
                                           INSERT CONNECTION
                                        ===================== */

                                        $insert =
                                            $conn->prepare(
                                                "INSERT INTO
                                                student_connections
                                                (
                                                    student1_id,
                                                    student2_id
                                                )
                                                VALUES (?, ?)"
                                            );


                                        if (
                                            $insert === false
                                        )
                                        {
                                            $message =
                                                "Unable to create connection: "
                                                . $conn->error;

                                            $messageType =
                                                "error";
                                        }

                                        else
                                        {

                                            $insert->bind_param(
                                                "ii",
                                                $student1,
                                                $student2
                                            );


                                            if (
                                                $insert->execute()
                                            )
                                            {
                                                $message =
                                                    "Students connected successfully because of: "
                                                    . $connectionReason;

                                                $messageType =
                                                    "success";
                                            }

                                            else
                                            {
                                                $message =
                                                    "Unable to create connection: "
                                                    . $insert->error;

                                                $messageType =
                                                    "error";
                                            }


                                            $insert->close();
                                        }
                                    }


                                    if ($connectionResult)
                                    {
                                        $connectionResult->free();
                                    }
                                }


                                $checkConnection->close();
                            }
                        }
                    }
                }
            }


            $studentQuery->close();
        }
    }
}


/* =========================================================
   REMOVE CONNECTION
   ========================================================= */

if (isset($_GET["delete_connection"]))
{
    $connectionId =
        intval($_GET["delete_connection"]);


    if ($connectionId <= 0)
    {
        $message =
            "Invalid connection.";

        $messageType = "error";
    }

    else
    {

        $delete =
            $conn->prepare(
                "DELETE FROM student_connections
                 WHERE id = ?"
            );


        if ($delete === false)
        {
            $message =
                "Unable to remove connection: "
                . $conn->error;

            $messageType = "error";
        }

        else
        {

            $delete->bind_param(
                "i",
                $connectionId
            );


            if ($delete->execute())
            {
                if ($delete->affected_rows > 0)
                {
                    $message =
                        "Student connection removed successfully.";

                    $messageType =
                        "success";
                }

                else
                {
                    $message =
                        "Connection was not found.";

                    $messageType =
                        "error";
                }
            }

            else
            {
                $message =
                    "Unable to remove connection: "
                    . $delete->error;

                $messageType =
                    "error";
            }


            $delete->close();
        }
    }
}


/* =========================================================
   GET CONNECTIONS
   ========================================================= */

$connections = array();


$connectionResult = $conn->query(
    "SELECT

        sc.id,

        s1.id AS student1_id,
        s1.roll_no AS roll1,
        s1.name AS name1,
        s1.department AS department1,
        s1.semester AS semester1,

        s2.id AS student2_id,
        s2.roll_no AS roll2,
        s2.name AS name2,
        s2.department AS department2,
        s2.semester AS semester2

     FROM student_connections sc

     INNER JOIN students s1
     ON sc.student1_id = s1.id
     AND EXISTS (
        SELECT 1
        FROM student_applications sa1
        WHERE sa1.student_id = s1.id
          AND sa1.application_status = 'Accepted'
     )

     INNER JOIN students s2
     ON sc.student2_id = s2.id
     AND EXISTS (
        SELECT 1
        FROM student_applications sa2
        WHERE sa2.student_id = s2.id
          AND sa2.application_status = 'Accepted'
     )

     ORDER BY sc.id DESC"
);


if ($connectionResult)
{
    while (
        $connection =
        $connectionResult->fetch_assoc()
    )
    {

        /* =============================================
           DETERMINE REASON AGAIN
           ============================================= */

        $sameDepartment =
            strtolower(
                trim(
                    $connection["department1"]
                )
            )
            ==
            strtolower(
                trim(
                    $connection["department2"]
                )
            );


        $sameSemester =
            strtoupper(
                trim(
                    $connection["semester1"]
                )
            )
            ==
            strtoupper(
                trim(
                    $connection["semester2"]
                )
            );


        if (
            $sameDepartment &&
            $sameSemester
        )
        {
            $connection["reason"] =
                "Same Department & Same Semester";
        }

        elseif ($sameDepartment)
        {
            $connection["reason"] =
                "Same Department";
        }

        elseif ($sameSemester)
        {
            $connection["reason"] =
                "Same Semester";
        }

        else
        {
            $connection["reason"] =
                "Relationship";
        }


        $connections[] =
            $connection;
    }

    $connectionResult->free();
}


/* =========================================================
   COUNTS
   ========================================================= */

$totalStudents =
    count($students);

$totalConnections =
    count($connections);


/* =========================================================
   GRAPH NODES
   ========================================================= */

$graphNodes =
    array();


foreach ($students as $student)
{
    $graphNodes[] =
        array(
            "id" =>
                intval(
                    $student["id"]
                ),

            "roll" =>
                $student["roll_no"],

            "name" =>
                $student["name"],

            "department" =>
                $student["department"],

            "semester" =>
                $student["semester"]
        );
}


/* =========================================================
   GRAPH EDGES
   ========================================================= */

$graphEdges =
    array();


foreach ($connections as $connection)
{
    $graphEdges[] =
        array(
            "id" =>
                intval(
                    $connection["id"]
                ),

            "from" =>
                intval(
                    $connection["student1_id"]
                ),

            "to" =>
                intval(
                    $connection["student2_id"]
                ),

            "reason" =>
                $connection["reason"]
        );
}


$graphNodesJson =
    json_encode(
        $graphNodes,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    );


$graphEdgesJson =
    json_encode(
        $graphEdges,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    );

?>

<!DOCTYPE html>

<html>

<head>

<title>
Student Network | Smart Campus
</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<style>

* {
    box-sizing:border-box;
}

:root
{
    --bg:#070b17;
    --panel:rgba(13,19,36,.94);
    --border:rgba(148,163,184,.14);
    --text:#f8fafc;
    --muted:#94a3b8;
    --blue:#3b82f6;
    --cyan:#06b6d4;
    --purple:#8b5cf6;
    --green:#22c55e;
    --red:#ef4444;
    --orange:#f59e0b;
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
            rgba(59,130,246,.14),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 15%,
            rgba(139,92,246,.13),
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

    background:rgba(7,11,23,.94);

    backdrop-filter:blur(18px);

    border-right:1px solid var(--border);

    display:flex;

    flex-direction:column;

    z-index:20;
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

    border:1px solid var(--border);

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

    border:1px solid var(--border);

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

    max-width:950px;

    color:#a8b5c8;

    font-size:13px;

    line-height:1.7;
}


/* =========================================================
   ALERT
   ========================================================= */

.alert
{
    padding:
        14px 17px;

    border-radius:12px;

    margin-bottom:20px;

    font-size:12px;

    border:1px solid;
}

.alert.success
{
    background:
        rgba(34,197,94,.08);

    border-color:
        rgba(34,197,94,.22);

    color:#86efac;
}

.alert.error
{
    background:
        rgba(239,68,68,.08);

    border-color:
        rgba(239,68,68,.22);

    color:#fca5a5;
}


/* =========================================================
   STATS
   ========================================================= */

.stats
{
    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:16px;

    margin-bottom:20px;
}

.stat
{
    padding:20px;

    border-radius:15px;

    background:var(--panel);

    border:
        1px solid var(--border);
}

.stat-label
{
    color:var(--muted);

    font-size:10px;

    font-weight:bold;

    letter-spacing:.8px;
}

.stat-value
{
    font-size:29px;

    font-weight:bold;

    margin-top:10px;
}

.stat-meta
{
    color:#64748b;

    font-size:9px;

    margin-top:5px;
}


/* =========================================================
   PANEL
   ========================================================= */

.panel
{
    background:var(--panel);

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


/* =========================================================
   FORM
   ========================================================= */

.form-body
{
    padding:20px;
}

.connection-grid
{
    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:15px;

    margin-bottom:16px;
}

.form-group
{
    margin-bottom:14px;
}

label
{
    display:block;

    color:#94a3b8;

    font-size:10px;

    font-weight:bold;

    letter-spacing:.5px;

    margin-bottom:7px;
}

select
{
    width:100%;

    padding:13px;

    border-radius:10px;

    border:
        1px solid
        rgba(148,163,184,.15);

    outline:none;

    color:white;

    background:
        rgba(255,255,255,.035);

    font-size:12px;
}

select:focus
{
    border-color:
        rgba(139,92,246,.60);

    box-shadow:
        0 0 0 3px
        rgba(139,92,246,.08);
}

select option
{
    background:#0d1324;

    color:white;
}

.button
{
    width:100%;

    border:none;

    cursor:pointer;

    padding:
        13px 16px;

    border-radius:10px;

    color:white;

    font-weight:bold;

    font-size:12px;

    background:
        linear-gradient(
            135deg,
            #8b5cf6,
            #3b82f6
        );

    transition:.2s;
}

.button:hover
{
    transform:
        translateY(-2px);

    box-shadow:
        0 10px 30px
        rgba(139,92,246,.25);
}


/* =========================================================
   RULE BOX
   ========================================================= */

.connection-rule
{
    margin-top:15px;

    padding:14px 16px;

    border-radius:11px;

    background:
        rgba(34,211,238,.045);

    border:
        1px solid
        rgba(34,211,238,.12);

    color:#94a3b8;

    font-size:10px;

    line-height:1.7;
}

.connection-rule strong
{
    color:#67e8f9;
}


/* =========================================================
   GRAPH
   ========================================================= */

.graph-panel
{
    position:relative;

    height:650px;

    overflow:hidden;

    background:
        radial-gradient(
            circle at center,
            rgba(59,130,246,.055),
            transparent 55%
        ),
        #080d1b;
}

.graph-panel::before
{
    content:"";

    position:absolute;

    inset:0;

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

    background-size:35px 35px;

    pointer-events:none;
}

.graph-area
{
    position:absolute;

    inset:0;

    overflow:hidden;

    cursor:grab;
}

.graph-area.dragging
{
    cursor:grabbing;
}

.graph-world
{
    position:absolute;

    width:100%;

    height:100%;

    transform-origin:center center;
}

#networkSvg
{
    position:absolute;

    left:0;
    top:0;

    width:100%;
    height:100%;

    pointer-events:none;

    overflow:visible;
}

.edge
{
    stroke:
        rgba(139,92,246,.65);

    stroke-width:2.5;

    filter:
        drop-shadow(
            0 0 5px
            rgba(139,92,246,.25)
        );
}

.edge.active
{
    stroke:#22d3ee;

    stroke-width:3;

    filter:
        drop-shadow(
            0 0 8px
            rgba(34,211,238,.65)
        );
}


/* =========================================================
   EDGE REASON LABEL
   ========================================================= */

.edge-label
{
    fill:#c4b5fd;

    font-size:9px;

    font-weight:bold;

    text-anchor:middle;

    dominant-baseline:middle;

    pointer-events:none;

    paint-order:stroke;

    stroke:#080d1b;

    stroke-width:4px;

    stroke-linejoin:round;
}


/* =========================================================
   GRAPH NODE
   ========================================================= */

.graph-node
{
    position:absolute;

    width:145px;

    min-height:88px;

    transform:
        translate(-50%,-50%);

    border-radius:14px;

    padding:11px;

    background:
        linear-gradient(
            145deg,
            rgba(30,41,80,.96),
            rgba(13,19,36,.96)
        );

    border:
        1px solid
        rgba(59,130,246,.35);

    box-shadow:
        0 8px 30px
        rgba(0,0,0,.35),

        0 0 20px
        rgba(59,130,246,.08);

    cursor:grab;

    user-select:none;

    transition:
        box-shadow .2s,
        border-color .2s,
        opacity .2s;

    z-index:5;
}

.graph-node:hover
{
    border-color:
        rgba(34,211,238,.75);

    box-shadow:
        0 12px 40px
        rgba(0,0,0,.45),

        0 0 25px
        rgba(34,211,238,.16);

    z-index:10;
}

.graph-node.selected
{
    border-color:#22d3ee;

    box-shadow:
        0 0 0 3px
        rgba(34,211,238,.12),

        0 0 35px
        rgba(34,211,238,.35);

    z-index:15;
}

.graph-node.dimmed
{
    opacity:.18;
}

.node-top
{
    display:flex;

    align-items:center;

    gap:7px;

    margin-bottom:6px;
}

.node-dot
{
    width:8px;
    height:8px;

    border-radius:50%;

    background:#3b82f6;

    box-shadow:
        0 0 10px
        rgba(59,130,246,.8);

    flex-shrink:0;
}

.node-roll
{
    color:#60a5fa;

    font-size:8px;

    font-weight:bold;

    overflow:hidden;

    white-space:nowrap;

    text-overflow:ellipsis;
}

.node-name
{
    font-size:11px;

    font-weight:bold;

    color:#f8fafc;

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;
}

.node-dept
{
    color:#64748b;

    font-size:8px;

    margin-top:4px;

    text-transform:uppercase;
}

.node-semester
{
    color:#a78bfa;

    font-size:8px;

    margin-top:3px;
}

.node-degree
{
    position:absolute;

    top:-7px;

    right:-7px;

    min-width:20px;

    height:20px;

    padding:0 5px;

    border-radius:50px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:8px;

    font-weight:bold;

    color:white;

    background:
        linear-gradient(
            135deg,
            #8b5cf6,
            #3b82f6
        );

    border:
        1px solid
        rgba(255,255,255,.12);
}


/* =========================================================
   GRAPH CONTROLS
   ========================================================= */

.graph-controls
{
    position:absolute;

    right:18px;

    top:18px;

    display:flex;

    flex-direction:column;

    gap:7px;

    z-index:20;
}

.graph-control
{
    width:38px;

    height:38px;

    border-radius:10px;

    border:
        1px solid
        rgba(148,163,184,.18);

    background:
        rgba(7,11,23,.82);

    color:#cbd5e1;

    cursor:pointer;

    font-size:16px;

    backdrop-filter:blur(10px);

    transition:.2s;
}

.graph-control:hover
{
    color:white;

    border-color:
        rgba(139,92,246,.55);

    background:
        rgba(139,92,246,.16);
}


/* =========================================================
   SEARCH
   ========================================================= */

.graph-search
{
    position:absolute;

    top:18px;
    left:18px;

    width:260px;

    z-index:20;
}

.graph-search input
{
    width:100%;

    padding:
        11px 13px;

    border-radius:10px;

    border:
        1px solid
        rgba(148,163,184,.18);

    outline:none;

    color:white;

    background:
        rgba(7,11,23,.82);

    backdrop-filter:blur(10px);

    font-size:11px;
}

.graph-search input:focus
{
    border-color:
        rgba(34,211,238,.55);
}

.graph-search input::placeholder
{
    color:#64748b;
}


/* =========================================================
   LEGEND
   ========================================================= */

.graph-legend
{
    position:absolute;

    left:18px;

    bottom:18px;

    z-index:20;

    padding:10px 13px;

    border-radius:10px;

    background:
        rgba(7,11,23,.88);

    border:
        1px solid
        rgba(148,163,184,.15);

    backdrop-filter:blur(10px);

    font-size:9px;

    color:#94a3b8;
}

.legend-row
{
    display:flex;

    align-items:center;

    gap:7px;

    margin:5px 0;
}

.legend-node
{
    width:8px;

    height:8px;

    border-radius:50%;

    background:#3b82f6;
}

.legend-line
{
    width:20px;

    height:2px;

    background:#8b5cf6;
}


/* =========================================================
   EMPTY
   ========================================================= */

.graph-empty
{
    position:absolute;

    inset:0;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:center;

    color:#64748b;

    text-align:center;

    pointer-events:none;
}

.graph-empty-icon
{
    font-size:48px;

    color:#8b5cf6;

    margin-bottom:10px;

    opacity:.7;
}

.graph-empty strong
{
    color:#94a3b8;

    font-size:13px;
}

.graph-empty span
{
    margin-top:6px;

    font-size:10px;
}


/* =========================================================
   CONNECTION LIST
   ========================================================= */

.connection-list
{
    padding:
        10px 20px 20px;
}

.connection-card
{
    display:grid;

    grid-template-columns:
        1fr 80px 1fr 150px;

    align-items:center;

    gap:12px;

    padding:15px;

    margin-top:10px;

    border-radius:12px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(148,163,184,.08);
}

.student-node
{
    padding:12px;

    border-radius:10px;

    background:
        rgba(59,130,246,.07);

    border:
        1px solid
        rgba(59,130,246,.10);
}

.student-roll
{
    color:#60a5fa;

    font-size:9px;

    font-weight:bold;
}

.student-name
{
    color:white;

    font-size:12px;

    margin-top:5px;
}

.student-dept
{
    color:#64748b;

    font-size:9px;

    margin-top:4px;
}

.student-semester
{
    color:#a78bfa;

    font-size:9px;

    margin-top:3px;
}

.connection-line
{
    text-align:center;

    color:#a78bfa;

    font-size:22px;
}

.connection-reason
{
    margin-top:6px;

    padding:7px 8px;

    border-radius:7px;

    background:
        rgba(139,92,246,.08);

    border:
        1px solid
        rgba(139,92,246,.14);

    color:#c4b5fd;

    font-size:8px;

    font-weight:bold;

    line-height:1.4;

    text-align:center;
}

.remove
{
    display:inline-block;

    text-decoration:none;

    padding:8px 10px;

    text-align:center;

    border-radius:8px;

    color:#fca5a5;

    background:
        rgba(239,68,68,.07);

    border:
        1px solid
        rgba(239,68,68,.12);

    font-size:9px;
}

.remove:hover
{
    color:white;

    background:
        rgba(239,68,68,.18);
}

.empty
{
    text-align:center;

    padding:45px 20px;

    color:#64748b;

    font-size:12px;

    line-height:1.7;
}


/* =========================================================
   INFO
   ========================================================= */

.info
{
    padding:20px;
}

.info-box
{
    padding:16px;

    border-radius:12px;

    background:
        rgba(139,92,246,.06);

    border:
        1px solid
        rgba(139,92,246,.12);

    margin-bottom:12px;
}

.info-title
{
    color:#a78bfa;

    font-size:11px;

    font-weight:bold;
}

.info-text
{
    color:#64748b;

    font-size:10px;

    line-height:1.7;

    margin-top:6px;
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

@media(max-width:1000px)
{
    .stats
    {
        grid-template-columns:
            1fr 1fr;
    }

    .graph-panel
    {
        height:600px;
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

    .connection-card
    {
        grid-template-columns:1fr;
    }

    .connection-line
    {
        transform:rotate(90deg);
    }
}

@media(max-width:700px)
{
    .connection-grid
    {
        grid-template-columns:1fr;
    }

    .stats
    {
        grid-template-columns:1fr;
    }

    .graph-search
    {
        width:190px;
    }

    .graph-node
    {
        width:120px;
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


<div class="topbar">

    <div>

        <div class="page-label">
            GRAPH & RELATIONSHIP CENTER
        </div>

        <h1 class="page-title">
            Student Network
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
     ALERT
     ===================================================== -->

<?php

if ($message != "")
{

?>

<div
    class="alert
    <?php

    echo $messageType == "success"
        ? "success"
        : "error";

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
     HERO
     ===================================================== -->

<section class="hero">

    <h2>
        Student Relationship Network
    </h2>

    <p>

        The Student Network represents relationships
        between students using a graph structure.
        Each student is represented as a node and
        each valid relationship is represented as an
        edge. A connection is created only when the
        students share the same department or the
        same semester.

    </p>

</section>


<!-- =====================================================
     STATS
     ===================================================== -->

<section class="stats">


    <div class="stat">

        <div class="stat-label">
            TOTAL STUDENTS
        </div>

        <div class="stat-value">

            <?php

            echo $totalStudents;

            ?>

        </div>

        <div class="stat-meta">
            Graph nodes available
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            ACTIVE CONNECTIONS
        </div>

        <div class="stat-value">

            <?php

            echo $totalConnections;

            ?>

        </div>

        <div class="stat-meta">
            Valid graph edges stored
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            NETWORK RULE
        </div>

        <div
            class="stat-value"
            style="
            color:#22c55e;
            font-size:18px;
            margin-top:15px;
            "
        >

            DEPT / SEMESTER

        </div>

        <div class="stat-meta">
            Relationship-based graph
        </div>

    </div>


</section>


<!-- =====================================================
     CONNECT STUDENTS
     ===================================================== -->

<section class="panel">

    <div class="panel-header">

        <h3>
            Connect Two Students
        </h3>

        <span>
            CREATE VALID GRAPH EDGE
        </span>

    </div>


    <div class="form-body">

        <form
            method="POST"
            action="student_network.php"
        >

            <div class="connection-grid">


                <div class="form-group">

                    <label>
                        STUDENT 01
                    </label>

                    <select
                        name="student1"
                        required
                    >

                        <option value="">
                            Select first student
                        </option>


                        <?php

                        foreach (
                            $students
                            as $student
                        )
                        {

                        ?>

                        <option
                            value="<?php
                            echo intval(
                                $student["id"]
                            );
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $student["roll_no"]
                            );

                            echo " — ";

                            echo htmlspecialchars(
                                $student["name"]
                            );

                            echo " | ";

                            echo strtoupper(
                                htmlspecialchars(
                                    $student["department"]
                                )
                            );

                            echo " | Sem ";

                            echo htmlspecialchars(
                                $student["semester"]
                            );

                            ?>

                        </option>

                        <?php

                        }

                        ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        STUDENT 02
                    </label>

                    <select
                        name="student2"
                        required
                    >

                        <option value="">
                            Select second student
                        </option>


                        <?php

                        foreach (
                            $students
                            as $student
                        )
                        {

                        ?>

                        <option
                            value="<?php
                            echo intval(
                                $student["id"]
                            );
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $student["roll_no"]
                            );

                            echo " — ";

                            echo htmlspecialchars(
                                $student["name"]
                            );

                            echo " | ";

                            echo strtoupper(
                                htmlspecialchars(
                                    $student["department"]
                                )
                            );

                            echo " | Sem ";

                            echo htmlspecialchars(
                                $student["semester"]
                            );

                            ?>

                        </option>

                        <?php

                        }

                        ?>

                    </select>

                </div>

            </div>


            <button
                type="submit"
                name="add_connection"
                class="button"
            >

                ⟷ CONNECT STUDENTS

            </button>


            <div class="connection-rule">

                <strong>
                    CONNECTION RULE:
                </strong>

                Students can be connected only when
                they have the <strong>same department</strong>
                or the <strong>same semester</strong>.

                <br>

                If both conditions are satisfied, the
                reason will be displayed as

                <strong>
                    Same Department & Same Semester
                </strong>.

            </div>

        </form>

    </div>

</section>


<!-- =====================================================
     LIVE GRAPH
     ===================================================== -->

<section class="panel">

    <div class="panel-header">

        <h3>
            Live Student Graph
        </h3>

        <span>
            NODES + RELATIONSHIP EDGES
        </span>

    </div>


    <div class="graph-panel">


        <div class="graph-search">

            <input
                type="text"
                id="graphSearch"
                placeholder="Search student name or roll number..."
            >

        </div>


        <div class="graph-controls">

            <button
                type="button"
                class="graph-control"
                id="zoomIn"
                title="Zoom In"
            >
                +
            </button>

            <button
                type="button"
                class="graph-control"
                id="zoomOut"
                title="Zoom Out"
            >
                −
            </button>

            <button
                type="button"
                class="graph-control"
                id="resetGraph"
                title="Reset Graph"
            >
                ↻
            </button>

        </div>


        <div
            class="graph-area"
            id="graphArea"
        >

            <div
                class="graph-world"
                id="graphWorld"
            >

                <svg
                    id="networkSvg"
                >
                </svg>


                <div
                    id="nodeContainer"
                >
                </div>

            </div>

        </div>


        <div class="graph-legend">

            <div class="legend-row">

                <span class="legend-node"></span>

                Student Node

            </div>

            <div class="legend-row">

                <span class="legend-line"></span>

                Valid Relationship

            </div>

            <div class="legend-row">

                <span
                    style="
                    color:#c4b5fd;
                    "
                >
                    A—B
                </span>

                Reason shown on connection

            </div>

        </div>


        <div
            class="graph-empty"
            id="graphEmpty"
            style="display:none;"
        >

            <div class="graph-empty-icon">
                ◎
            </div>

            <strong>
                No Students Available
            </strong>

            <span>
                Add students to display the network graph.
            </span>

        </div>

    </div>

</section>


<!-- =====================================================
     CONNECTION LIST
     ===================================================== -->

<section class="panel">

    <div class="panel-header">

        <h3>
            Student Network Connections
        </h3>

        <span>

            <?php

            echo $totalConnections;

            ?>

            CONNECTIONS

        </span>

    </div>


    <?php

    if (
        count($connections) == 0
    )
    {

    ?>

        <div class="empty">

            ◎

            <br>

            No student connections exist yet.

            <br>

            Select two students above.

            <br>

            They must share the same department
            or semester.

        </div>

    <?php

    }

    else
    {

    ?>

        <div class="connection-list">


        <?php

        foreach (
            $connections
            as $connection
        )
        {

        ?>

            <div class="connection-card">


                <div class="student-node">

                    <div class="student-roll">

                        <?php

                        echo htmlspecialchars(
                            $connection["roll1"]
                        );

                        ?>

                    </div>


                    <div class="student-name">

                        <?php

                        echo htmlspecialchars(
                            $connection["name1"]
                        );

                        ?>

                    </div>


                    <div class="student-dept">

                        Department:

                        <?php

                        echo strtoupper(
                            htmlspecialchars(
                                $connection[
                                    "department1"
                                ]
                            )
                        );

                        ?>

                    </div>


                    <div class="student-semester">

                        Semester:

                        <?php

                        echo htmlspecialchars(
                            $connection[
                                "semester1"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div>

                    <div
                        class="connection-line"
                    >
                        ⟷
                    </div>

                    <div
                        class="connection-reason"
                    >

                        <?php

                        echo htmlspecialchars(
                            $connection["reason"]
                        );

                        ?>

                    </div>

                </div>


                <div class="student-node">

                    <div class="student-roll">

                        <?php

                        echo htmlspecialchars(
                            $connection["roll2"]
                        );

                        ?>

                    </div>


                    <div class="student-name">

                        <?php

                        echo htmlspecialchars(
                            $connection["name2"]
                        );

                        ?>

                    </div>


                    <div class="student-dept">

                        Department:

                        <?php

                        echo strtoupper(
                            htmlspecialchars(
                                $connection[
                                    "department2"
                                ]
                            )
                        );

                        ?>

                    </div>


                    <div class="student-semester">

                        Semester:

                        <?php

                        echo htmlspecialchars(
                            $connection[
                                "semester2"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div>

                    <a
                        href="student_network.php?delete_connection=<?php
                        echo intval(
                            $connection["id"]
                        );
                        ?>"
                        class="remove"
                        onclick="
                        return confirm(
                            'Remove this student connection?'
                        );
                        "
                    >

                        REMOVE

                    </a>

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


<!-- =====================================================
     INFORMATION
     ===================================================== -->

<section class="panel">

    <div class="panel-header">

        <h3>
            Network Intelligence
        </h3>

        <span>
            GRAPH SYSTEM
        </span>

    </div>


    <div class="info">

        <div class="info-box">

            <div class="info-title">
                WHY ARE STUDENTS CONNECTED?
            </div>

            <div class="info-text">

                A student relationship is created when
                two students share a common academic
                characteristic. The system checks their
                department and semester automatically.

            </div>

        </div>


        <div class="info-box">

            <div class="info-title">
                SAME DEPARTMENT
            </div>

            <div class="info-text">

                If two students belong to the same
                department, such as MCA, they can be
                connected even if they are studying in
                different semesters.

            </div>

        </div>


        <div class="info-box">

            <div class="info-title">
                SAME SEMESTER
            </div>

            <div class="info-text">

                If two students belong to the same
                semester, they can be connected even
                when they belong to different departments.

            </div>

        </div>


        <div class="info-box">

            <div class="info-title">
                SAME DEPARTMENT + SAME SEMESTER
            </div>

            <div class="info-text">

                If both department and semester are the
                same, the system identifies the strongest
                relationship as:

                <strong>
                    Same Department & Same Semester
                </strong>.

            </div>

        </div>


        <div class="info-box">

            <div class="info-title">
                INVALID RELATIONSHIP
            </div>

            <div class="info-text">

                Students from different departments and
                different semesters cannot be connected.
                This prevents meaningless graph edges.

            </div>

        </div>


        <div class="info-box">

            <div class="info-title">
                GRAPH DATA STRUCTURE
            </div>

            <div class="info-text">

                Each student is a vertex (node), while a
                valid relationship between two students
                is an edge. Therefore, the Student Network
                demonstrates the practical use of a graph
                data structure in the Smart College
                Management System.

            </div>

        </div>

    </div>

</section>


<div class="footer">

    SMART CAMPUS MANAGEMENT SYSTEM

    &nbsp; • &nbsp;

    STUDENT NETWORK

    &nbsp; • &nbsp;

    VERSION 1.0

</div>


</main>


<script>

/* =========================================================
   GRAPH DATA
   ========================================================= */

var graphNodes =
    <?php
    echo $graphNodesJson;
    ?>;


var graphEdges =
    <?php
    echo $graphEdgesJson;
    ?>;


/* =========================================================
   GRAPH ELEMENTS
   ========================================================= */

var graphArea =
    document.getElementById(
        "graphArea"
    );


var graphWorld =
    document.getElementById(
        "graphWorld"
    );


var nodeContainer =
    document.getElementById(
        "nodeContainer"
    );


var networkSvg =
    document.getElementById(
        "networkSvg"
    );


var graphEmpty =
    document.getElementById(
        "graphEmpty"
    );


var graphSearch =
    document.getElementById(
        "graphSearch"
    );


/* =========================================================
   GRAPH STATE
   ========================================================= */

var positions = {};

var scale = 1;

var offsetX = 0;

var offsetY = 0;


/* =========================================================
   NODE DEGREE
   ========================================================= */

var degrees = {};


for (
    var i = 0;
    i < graphNodes.length;
    i++
)
{
    degrees[
        graphNodes[i].id
    ] = 0;
}


for (
    var j = 0;
    j < graphEdges.length;
    j++
)
{
    var edge =
        graphEdges[j];


    if (
        degrees[edge.from]
        !== undefined
    )
    {
        degrees[edge.from]++;
    }


    if (
        degrees[edge.to]
        !== undefined
    )
    {
        degrees[edge.to]++;
    }
}


/* =========================================================
   INITIAL POSITIONS
   ========================================================= */

function createInitialPositions()
{
    positions = {};

    var width =
        graphArea.clientWidth;

    var height =
        graphArea.clientHeight;

    var count =
        graphNodes.length;


    if (count == 0)
    {
        return;
    }


    var centerX =
        width / 2;

    var centerY =
        height / 2;


    var radius =
        Math.min(
            width,
            height
        ) * .33;


    for (
        var i = 0;
        i < count;
        i++
    )
    {
        var angle =
            (Math.PI * 2 * i)
            / count;


        positions[
            graphNodes[i].id
        ] =
        {
            x:
                centerX
                +
                Math.cos(angle)
                * radius,

            y:
                centerY
                +
                Math.sin(angle)
                * radius
        };
    }
}


/* =========================================================
   BUILD GRAPH
   ========================================================= */

function buildGraph()
{
    nodeContainer.innerHTML = "";

    networkSvg.innerHTML = "";


    if (graphNodes.length == 0)
    {
        graphEmpty.style.display =
            "flex";

        return;
    }


    graphEmpty.style.display =
        "none";


    createInitialPositions();


    networkSvg.setAttribute(
        "width",
        graphArea.clientWidth
    );


    networkSvg.setAttribute(
        "height",
        graphArea.clientHeight
    );


    /* =====================================================
       CREATE EDGES
       ===================================================== */

    for (
        var i = 0;
        i < graphEdges.length;
        i++
    )
    {
        var currentEdge =
            graphEdges[i];


        var line =
            document.createElementNS(
                "http://www.w3.org/2000/svg",
                "line"
            );


        line.setAttribute(
            "class",
            "edge"
        );


        line.setAttribute(
            "data-from",
            currentEdge.from
        );


        line.setAttribute(
            "data-to",
            currentEdge.to
        );


        networkSvg.appendChild(
            line
        );


        var label =
            document.createElementNS(
                "http://www.w3.org/2000/svg",
                "text"
            );


        label.setAttribute(
            "class",
            "edge-label"
        );


        label.setAttribute(
            "data-from",
            currentEdge.from
        );


        label.setAttribute(
            "data-to",
            currentEdge.to
        );


        label.textContent =
            currentEdge.reason;


        networkSvg.appendChild(
            label
        );
    }


    /* =====================================================
       CREATE NODES
       ===================================================== */

    for (
        var j = 0;
        j < graphNodes.length;
        j++
    )
    {
        createNode(
            graphNodes[j]
        );
    }


    updateGraph();
}


/* =========================================================
   CREATE NODE
   ========================================================= */

function createNode(student)
{
    var node =
        document.createElement(
            "div"
        );


    node.className =
        "graph-node";


    node.setAttribute(
        "data-id",
        student.id
    );


    node.setAttribute(
        "data-search",
        (
            student.name
            + " "
            + student.roll
            + " "
            + student.department
            + " "
            + student.semester
        ).toLowerCase()
    );


    node.innerHTML =

        '<div class="node-top">' +

            '<span class="node-dot"></span>' +

            '<span class="node-roll">' +

                escapeHtml(
                    student.roll
                ) +

            '</span>' +

        '</div>' +

        '<div class="node-name">' +

            escapeHtml(
                student.name
            ) +

        '</div>' +

        '<div class="node-dept">' +

            'DEPT: ' +

            escapeHtml(
                student.department
            ) +

        '</div>' +

        '<div class="node-semester">' +

            'SEM: ' +

            escapeHtml(
                student.semester
            ) +

        '</div>' +

        '<span class="node-degree">' +

            (
                degrees[student.id]
                || 0
            ) +

        '</span>';


    nodeContainer.appendChild(
        node
    );


    makeNodeDraggable(
        node,
        student.id
    );
}


/* =========================================================
   ESCAPE HTML
   ========================================================= */

function escapeHtml(value)
{
    return String(value)

        .replace(
            /&/g,
            "&amp;"
        )

        .replace(
            /</g,
            "&lt;"
        )

        .replace(
            />/g,
            "&gt;"
        )

        .replace(
            /"/g,
            "&quot;"
        )

        .replace(
            /'/g,
            "&#039;"
        );
}


/* =========================================================
   UPDATE GRAPH
   ========================================================= */

function updateGraph()
{
    graphWorld.style.transform =
        "translate("
        + offsetX
        + "px,"
        + offsetY
        + "px) scale("
        + scale
        + ")";


    var nodes =
        nodeContainer.children;


    for (
        var i = 0;
        i < nodes.length;
        i++
    )
    {
        var node =
            nodes[i];


        var id =
            parseInt(
                node.getAttribute(
                    "data-id"
                )
            );


        if (positions[id])
        {
            node.style.left =
                positions[id].x
                + "px";


            node.style.top =
                positions[id].y
                + "px";
        }
    }


    var svgElements =
        networkSvg.children;


    for (
        var j = 0;
        j < svgElements.length;
        j++
    )
    {
        var element =
            svgElements[j];


        var from =
            parseInt(
                element.getAttribute(
                    "data-from"
                )
            );


        var to =
            parseInt(
                element.getAttribute(
                    "data-to"
                )
            );


        if (
            positions[from]
            &&
            positions[to]
        )
        {

            var x1 =
                positions[from].x;

            var y1 =
                positions[from].y;

            var x2 =
                positions[to].x;

            var y2 =
                positions[to].y;


            if (
                element.tagName
                ==
                "line"
            )
            {
                element.setAttribute(
                    "x1",
                    x1
                );

                element.setAttribute(
                    "y1",
                    y1
                );

                element.setAttribute(
                    "x2",
                    x2
                );

                element.setAttribute(
                    "y2",
                    y2
                );
            }


            if (
                element.tagName
                ==
                "text"
            )
            {
                var middleX =
                    (x1 + x2) / 2;

                var middleY =
                    (y1 + y2) / 2;


                element.setAttribute(
                    "x",
                    middleX
                );


                element.setAttribute(
                    "y",
                    middleY - 8
                );
            }
        }
    }
}


/* =========================================================
   DRAG NODE
   ========================================================= */

function makeNodeDraggable(
    node,
    id
)
{
    var dragging = false;

    var startX = 0;

    var startY = 0;


    node.addEventListener(
        "mousedown",
        function(event)
        {
            event.preventDefault();

            event.stopPropagation();

            dragging = true;


            node.style.cursor =
                "grabbing";


            startX =
                event.clientX;

            startY =
                event.clientY;


            node.classList.add(
                "selected"
            );
        }
    );


    document.addEventListener(
        "mousemove",
        function(event)
        {
            if (!dragging)
            {
                return;
            }


            var dx =
                (
                    event.clientX
                    - startX
                )
                / scale;


            var dy =
                (
                    event.clientY
                    - startY
                )
                / scale;


            positions[id].x +=
                dx;

            positions[id].y +=
                dy;


            startX =
                event.clientX;

            startY =
                event.clientY;


            updateGraph();
        }
    );


    document.addEventListener(
        "mouseup",
        function()
        {
            if (!dragging)
            {
                return;
            }


            dragging = false;


            node.style.cursor =
                "grab";
        }
    );
}


/* =========================================================
   GRAPH PANNING
   ========================================================= */

var panning = false;

var panStartX = 0;

var panStartY = 0;


graphArea.addEventListener(
    "mousedown",
    function(event)
    {
        if (
            event.target.classList
            &&
            event.target.classList.contains(
                "graph-node"
            )
        )
        {
            return;
        }


        panning = true;


        graphArea.classList.add(
            "dragging"
        );


        panStartX =
            event.clientX;

        panStartY =
            event.clientY;
    }
);


document.addEventListener(
    "mousemove",
    function(event)
    {
        if (!panning)
        {
            return;
        }


        offsetX +=
            event.clientX
            - panStartX;


        offsetY +=
            event.clientY
            - panStartY;


        panStartX =
            event.clientX;

        panStartY =
            event.clientY;


        updateGraph();
    }
);


document.addEventListener(
    "mouseup",
    function()
    {
        panning = false;

        graphArea.classList.remove(
            "dragging"
        );
    }
);


/* =========================================================
   ZOOM IN
   ========================================================= */

document.getElementById(
    "zoomIn"
).addEventListener(
    "click",
    function()
    {
        scale += .1;


        if (scale > 2)
        {
            scale = 2;
        }


        updateGraph();
    }
);


/* =========================================================
   ZOOM OUT
   ========================================================= */

document.getElementById(
    "zoomOut"
).addEventListener(
    "click",
    function()
    {
        scale -= .1;


        if (scale < .5)
        {
            scale = .5;
        }


        updateGraph();
    }
);


/* =========================================================
   RESET
   ========================================================= */

document.getElementById(
    "resetGraph"
).addEventListener(
    "click",
    function()
    {
        scale = 1;

        offsetX = 0;

        offsetY = 0;

        graphSearch.value = "";

        buildGraph();

        clearSearch();
    }
);


/* =========================================================
   SEARCH
   ========================================================= */

graphSearch.addEventListener(
    "input",
    function()
    {
        var search =
            graphSearch.value
            .toLowerCase()
            .trim();


        var nodes =
            nodeContainer.children;


        if (search == "")
        {
            clearSearch();

            return;
        }


        for (
            var i = 0;
            i < nodes.length;
            i++
        )
        {
            var node =
                nodes[i];


            var data =
                node.getAttribute(
                    "data-search"
                );


            if (
                data.indexOf(
                    search
                ) >= 0
            )
            {
                node.classList.remove(
                    "dimmed"
                );

                node.classList.add(
                    "selected"
                );
            }

            else
            {
                node.classList.add(
                    "dimmed"
                );

                node.classList.remove(
                    "selected"
                );
            }
        }
    }
);


/* =========================================================
   CLEAR SEARCH
   ========================================================= */

function clearSearch()
{
    var nodes =
        nodeContainer.children;


    for (
        var i = 0;
        i < nodes.length;
        i++
    )
    {
        nodes[i].classList.remove(
            "dimmed"
        );

        nodes[i].classList.remove(
            "selected"
        );
    }
}


/* =========================================================
   RESIZE
   ========================================================= */

window.addEventListener(
    "resize",
    function()
    {
        buildGraph();
    }
);


/* =========================================================
   START
   ========================================================= */

buildGraph();

</script>


</body>

</html>


<?php

$conn->close();

?>