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


/* =========================================================
   BASIC SETTINGS
   ========================================================= */

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
   CSRF TOKEN
   ========================================================= */

if (!isset($_SESSION["csrf_token"]))
{
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];


/* =========================================================
   DATABASE ERROR HELPER
   ========================================================= */

function databaseErrorMessage($prefix, $conn)
{
    return $prefix . " " . $conn->error;
}


/* =========================================================
   FIND DEPARTMENT NAME COLUMN
   ========================================================= */

$departmentNameColumn = "";

$departmentColumnsResult = $conn->query(
    "SHOW COLUMNS FROM departments"
);

if ($departmentColumnsResult)
{
    while (
        $column =
        $departmentColumnsResult->fetch_assoc()
    )
    {
        $columnName = $column["Field"];

        if (
            strtolower($columnName) == "name"
        )
        {
            $departmentNameColumn = "name";
            break;
        }

        if (
            strtolower($columnName) == "department_name"
        )
        {
            $departmentNameColumn = "department_name";
        }

        if (
            strtolower($columnName) == "department"
        )
        {
            $departmentNameColumn = "department";
        }

        if (
            strtolower($columnName) == "dept_name"
        )
        {
            $departmentNameColumn = "dept_name";
        }
    }
}


/* =========================================================
   CREATE DEPARTMENT TABLE IF REQUIRED
   ========================================================= */

if ($departmentNameColumn == "")
{
    $createDepartmentTable = $conn->query(
        "CREATE TABLE IF NOT EXISTS departments
        (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB"
    );

    if (!$createDepartmentTable)
    {
        $message =
            databaseErrorMessage(
                "Unable to create departments table.",
                $conn
            );

        $messageType = "error";
    }
    else
    {
        $departmentNameColumn = "name";
    }
}


/* =========================================================
   VERIFY STUDENTS TABLE
   ========================================================= */

$studentsTableExists = false;

$studentsTableCheck = $conn->query(
    "SHOW TABLES LIKE 'students'"
);

if (
    $studentsTableCheck &&
    $studentsTableCheck->num_rows > 0
)
{
    $studentsTableExists = true;
}


/* =========================================================
   ADD DEPARTMENT
   ========================================================= */

if (isset($_POST["add_department"]))
{
    /* -----------------------------------------------------
       CHECK CSRF
       ----------------------------------------------------- */

    $submittedToken =
        isset($_POST["csrf_token"])
        ? $_POST["csrf_token"]
        : "";

    if (
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    )
    {
        $message =
            "Security validation failed. Please try again.";

        $messageType = "error";
    }
    else
    {
        $departmentName =
            trim(
                $_POST["department_name"] ?? ""
            );

        /* -------------------------------------------------
           VALIDATION
           ------------------------------------------------- */

        if ($departmentName == "")
        {
            $message =
                "Department name cannot be empty.";

            $messageType = "error";
        }
        elseif (strlen($departmentName) < 2)
        {
            $message =
                "Department name must contain at least 2 characters.";

            $messageType = "error";
        }
        elseif (strlen($departmentName) > 100)
        {
            $message =
                "Department name cannot exceed 100 characters.";

            $messageType = "error";
        }
        elseif (
            !preg_match(
                "/^[A-Za-z0-9 &.\-]+$/",
                $departmentName
            )
        )
        {
            $message =
                "Department name contains invalid characters.";

            $messageType = "error";
        }
        else
        {
            /* ---------------------------------------------
               CHECK DUPLICATE
               --------------------------------------------- */

            $sql =
                "SELECT id
                 FROM departments
                 WHERE LOWER(`"
                . $departmentNameColumn .
                "`) = LOWER(?)";

            $check =
                $conn->prepare($sql);

            if ($check === false)
            {
                $message =
                    databaseErrorMessage(
                        "Department check failed.",
                        $conn
                    );

                $messageType = "error";
            }
            else
            {
                $check->bind_param(
                    "s",
                    $departmentName
                );

                $check->execute();

                $check->store_result();

                if ($check->num_rows > 0)
                {
                    $message =
                        "This department already exists.";

                    $messageType = "error";
                }
                else
                {
                    /* -------------------------------------
                       INSERT DEPARTMENT
                       ------------------------------------- */

                    $insertSql =
                        "INSERT INTO departments (`"
                        . $departmentNameColumn .
                        "`)
                         VALUES (?)";

                    $insert =
                        $conn->prepare(
                            $insertSql
                        );

                    if ($insert === false)
                    {
                        $message =
                            databaseErrorMessage(
                                "Department creation failed.",
                                $conn
                            );

                        $messageType = "error";
                    }
                    else
                    {
                        $insert->bind_param(
                            "s",
                            $departmentName
                        );

                        if ($insert->execute())
                        {
                            $message =
                                "Department added successfully.";

                            $messageType =
                                "success";
                        }
                        else
                        {
                            $message =
                                "Unable to add department: "
                                . $insert->error;

                            $messageType =
                                "error";
                        }

                        $insert->close();
                    }
                }

                $check->close();
            }
        }
    }
}


/* =========================================================
   DELETE DEPARTMENT
   ========================================================= */

if (isset($_POST["delete_department"]))
{
    /* -----------------------------------------------------
       CHECK CSRF
       ----------------------------------------------------- */

    $submittedToken =
        isset($_POST["csrf_token"])
        ? $_POST["csrf_token"]
        : "";

    if (
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    )
    {
        $message =
            "Security validation failed. Please try again.";

        $messageType = "error";
    }
    else
    {
        $departmentId =
            isset($_POST["department_id"])
            ? intval($_POST["department_id"])
            : 0;

        if ($departmentId <= 0)
        {
            $message =
                "Invalid department.";

            $messageType = "error";
        }
        else
        {
            /* ---------------------------------------------
               GET DEPARTMENT
               --------------------------------------------- */

            $sql =
                "SELECT `"
                . $departmentNameColumn .
                "` AS department_name
                 FROM departments
                 WHERE id = ?";

            $stmt =
                $conn->prepare($sql);

            if ($stmt === false)
            {
                $message =
                    databaseErrorMessage(
                        "Department lookup failed.",
                        $conn
                    );

                $messageType = "error";
            }
            else
            {
                $stmt->bind_param(
                    "i",
                    $departmentId
                );

                $stmt->execute();

                $result =
                    $stmt->get_result();

                if ($result->num_rows == 0)
                {
                    $message =
                        "Department not found.";

                    $messageType = "error";
                }
                else
                {
                    $departmentRow =
                        $result->fetch_assoc();

                    $departmentName =
                        $departmentRow[
                            "department_name"
                        ];

                    /* -------------------------------------
                       CHECK STUDENTS
                       ------------------------------------- */

                    if ($studentsTableExists)
                    {
                        $studentCheck =
                            $conn->prepare(
                                "SELECT COUNT(*) AS total
                                 FROM students
                                 WHERE LOWER(department)
                                 = LOWER(?)"
                            );

                        if ($studentCheck === false)
                        {
                            $message =
                                databaseErrorMessage(
                                    "Unable to check department students.",
                                    $conn
                                );

                            $messageType = "error";
                        }
                        else
                        {
                            $studentCheck->bind_param(
                                "s",
                                $departmentName
                            );

                            $studentCheck->execute();

                            $studentResult =
                                $studentCheck->get_result();

                            $studentRow =
                                $studentResult->fetch_assoc();

                            $studentCount =
                                intval(
                                    $studentRow["total"]
                                );

                            $studentCheck->close();

                            if ($studentCount > 0)
                            {
                                $message =
                                    "Cannot delete "
                                    . $departmentName
                                    . ". It contains "
                                    . $studentCount
                                    . " student(s).";

                                $messageType =
                                    "error";
                            }
                            else
                            {
                                /* -------------------------
                                   DELETE
                                   ------------------------- */

                                $delete =
                                    $conn->prepare(
                                        "DELETE FROM departments
                                         WHERE id = ?"
                                    );

                                if ($delete === false)
                                {
                                    $message =
                                        databaseErrorMessage(
                                            "Department deletion failed.",
                                            $conn
                                        );

                                    $messageType =
                                        "error";
                                }
                                else
                                {
                                    $delete->bind_param(
                                        "i",
                                        $departmentId
                                    );

                                    if (
                                        $delete->execute()
                                    )
                                    {
                                        if (
                                            $delete->affected_rows > 0
                                        )
                                        {
                                            $message =
                                                "Department deleted successfully.";

                                            $messageType =
                                                "success";
                                        }
                                        else
                                        {
                                            $message =
                                                "Department was not found.";

                                            $messageType =
                                                "error";
                                        }
                                    }
                                    else
                                    {
                                        $message =
                                            "Unable to delete department: "
                                            . $delete->error;

                                        $messageType =
                                            "error";
                                    }

                                    $delete->close();
                                }
                            }
                        }
                    }
                    else
                    {
                        /* ---------------------------------
                           NO STUDENTS TABLE
                           --------------------------------- */

                        $delete =
                            $conn->prepare(
                                "DELETE FROM departments
                                 WHERE id = ?"
                            );

                        if ($delete === false)
                        {
                            $message =
                                databaseErrorMessage(
                                    "Department deletion failed.",
                                    $conn
                                );

                            $messageType =
                                "error";
                        }
                        else
                        {
                            $delete->bind_param(
                                "i",
                                $departmentId
                            );

                            if ($delete->execute())
                            {
                                $message =
                                    "Department deleted successfully.";

                                $messageType =
                                    "success";
                            }
                            else
                            {
                                $message =
                                    "Unable to delete department: "
                                    . $delete->error;

                                $messageType =
                                    "error";
                            }

                            $delete->close();
                        }
                    }
                }

                $stmt->close();
            }
        }
    }
}


/* =========================================================
   SEARCH DEPARTMENT
   ========================================================= */

$departmentSearch = "";

if (
    isset($_GET["department_search"])
)
{
    $departmentSearch =
        trim(
            $_GET["department_search"]
        );
}


/* =========================================================
   DEPARTMENT LIST
   ========================================================= */

$departmentRows = array();

if ($studentsTableExists)
{
    if ($departmentSearch != "")
    {
        $sql =
            "SELECT
                d.id,
                d.`"
            . $departmentNameColumn .
            "` AS department_name,
                COUNT(s.id) AS student_count

             FROM departments d

             LEFT JOIN students s
             ON LOWER(s.department)
             =
             LOWER(
                d.`"
            . $departmentNameColumn .
            "`
             )

             WHERE d.`"
            . $departmentNameColumn .
            "` LIKE ?

             GROUP BY
                d.id,
                d.`"
            . $departmentNameColumn .
            "`

             ORDER BY
                d.`"
            . $departmentNameColumn .
            "` ASC";

        $stmt =
            $conn->prepare($sql);

        if ($stmt === false)
        {
            $message =
                databaseErrorMessage(
                    "Department search failed.",
                    $conn
                );

            $messageType = "error";
        }
        else
        {
            $searchValue =
                "%" .
                $departmentSearch .
                "%";

            $stmt->bind_param(
                "s",
                $searchValue
            );

            if ($stmt->execute())
            {
                $result =
                    $stmt->get_result();

                while (
                    $row =
                    $result->fetch_assoc()
                )
                {
                    $departmentRows[] =
                        $row;
                }
            }
            else
            {
                $message =
                    "Department search failed: "
                    . $stmt->error;

                $messageType = "error";
            }

            $stmt->close();
        }
    }
    else
    {
        $sql =
            "SELECT
                d.id,
                d.`"
            . $departmentNameColumn .
            "` AS department_name,
                COUNT(s.id) AS student_count

             FROM departments d

             LEFT JOIN students s
             ON LOWER(s.department)
             =
             LOWER(
                d.`"
            . $departmentNameColumn .
            "`
             )

             GROUP BY
                d.id,
                d.`"
            . $departmentNameColumn .
            "`

             ORDER BY
                d.`"
            . $departmentNameColumn .
            "` ASC";

        $result =
            $conn->query($sql);

        if ($result === false)
        {
            $message =
                databaseErrorMessage(
                    "Unable to load departments.",
                    $conn
                );

            $messageType = "error";
        }
        else
        {
            while (
                $row =
                $result->fetch_assoc()
            )
            {
                $departmentRows[] =
                    $row;
            }
        }
    }
}
else
{
    $sql =
        "SELECT
            id,
            `"
        . $departmentNameColumn .
        "` AS department_name

         FROM departments

         ORDER BY `"
        . $departmentNameColumn .
        "` ASC";

    $result =
        $conn->query($sql);

    if ($result)
    {
        while (
            $row =
            $result->fetch_assoc()
        )
        {
            $row["student_count"] = 0;

            $departmentRows[] =
                $row;
        }
    }
}


/* =========================================================
   TOTAL DEPARTMENTS
   ========================================================= */

$totalDepartments = 0;

$result =
    $conn->query(
        "SELECT COUNT(*) AS total
         FROM departments"
    );

if ($result)
{
    $row =
        $result->fetch_assoc();

    $totalDepartments =
        intval(
            $row["total"]
        );
}


/* =========================================================
   TOTAL STUDENTS
   ========================================================= */

$totalStudents = 0;

if ($studentsTableExists)
{
    $result =
        $conn->query(
            "SELECT COUNT(*) AS total
             FROM students"
        );

    if ($result)
    {
        $row =
            $result->fetch_assoc();

        $totalStudents =
            intval(
                $row["total"]
            );
    }
}


/* =========================================================
   EMPTY DEPARTMENTS
   ========================================================= */

$emptyDepartments = 0;

if ($studentsTableExists)
{
    $sql =
        "SELECT COUNT(*) AS total

         FROM departments d

         WHERE NOT EXISTS
         (
             SELECT 1
             FROM students s

             WHERE LOWER(s.department)
             =
             LOWER(
                 d.`"
            . $departmentNameColumn .
            "`
             )
         )";

    $result =
        $conn->query($sql);

    if ($result)
    {
        $row =
            $result->fetch_assoc();

        $emptyDepartments =
            intval(
                $row["total"]
            );
    }
}
else
{
    $emptyDepartments =
        $totalDepartments;
}


/* =========================================================
   INITIALS
   ========================================================= */

$adminInitial =
    strtoupper(
        substr(
            $adminName,
            0,
            1
        )
    );

?>

<!DOCTYPE html>

<html>

<head>

<title>
Department Management | Smart Campus
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
    box-sizing: border-box;
}

:root
{
    --bg: #070b17;
    --panel: rgba(13,19,36,0.88);
    --panel2: rgba(17,26,46,0.94);

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

    color: var(--text);

    min-height: 100vh;

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

        radial-gradient(
            circle at 70% 90%,
            rgba(6,182,212,0.08),
            transparent 30%
        ),

        #070b17;
}

body::before
{
    content: "";

    position: fixed;

    inset: 0;

    pointer-events: none;

    opacity: 0.16;

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


/* =========================================================
   APP
   ========================================================= */

.app
{
    min-height: 100vh;

    position: relative;

    z-index: 1;
}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar
{
    width: 255px;

    position: fixed;

    left: 0;

    top: 0;

    bottom: 0;

    padding: 24px 16px;

    background:
        rgba(7,11,23,0.90);

    backdrop-filter:
        blur(18px);

    border-right:
        1px solid var(--border);

    display: flex;

    flex-direction: column;

    overflow-y: auto;
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

    font-size: 21px;

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

    font-size: 10px;

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

    transition: 0.2s;
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

    padding-top: 20px;
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
        rgba(239,68,68,0.14);

    color: white;
}


/* =========================================================
   MAIN
   ========================================================= */

.main
{
    margin-left: 255px;

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
    display: flex;

    justify-content:
        space-between;

    align-items: center;

    margin-bottom: 25px;
}

.page-label
{
    color: #64748b;

    font-size: 11px;

    letter-spacing: 1px;

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

    font-size: 11px;

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

    font-size: 9px;

    margin-top: 2px;
}


/* =========================================================
   ALERT
   ========================================================= */

.alert
{
    padding:
        14px 17px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-size: 12px;

    border: 1px solid;

    animation:
        alertIn 0.3s ease;
}

@keyframes alertIn
{
    from
    {
        opacity: 0;

        transform:
            translateY(-5px);
    }

    to
    {
        opacity: 1;

        transform:
            translateY(0);
    }
}

.alert.success
{
    background:
        rgba(34,197,94,0.08);

    border-color:
        rgba(34,197,94,0.22);

    color:
        #86efac;
}

.alert.error
{
    background:
        rgba(239,68,68,0.08);

    border-color:
        rgba(239,68,68,0.22);

    color:
        #fca5a5;
}


/* =========================================================
   HERO
   ========================================================= */

.hero
{
    position: relative;

    overflow: hidden;

    border-radius: 18px;

    padding:
        26px 30px;

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
    margin:
        0 0 7px;

    font-size: 22px;

    position: relative;

    z-index: 2;
}

.hero p
{
    margin: 0;

    max-width: 760px;

    color: #a8b5c8;

    font-size: 13px;

    line-height: 1.6;

    position: relative;

    z-index: 2;
}


/* =========================================================
   STATISTICS
   ========================================================= */

.stats
{
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 20px;
}

.stat
{
    padding: 19px;

    border-radius: 15px;

    background:
        rgba(13,19,36,0.86);

    border:
        1px solid var(--border);

    transition:
        0.2s;
}

.stat:hover
{
    transform:
        translateY(-4px);

    border-color:
        rgba(96,165,250,0.25);
}

.stat-label
{
    color: var(--muted);

    font-size: 10px;

    font-weight: bold;

    letter-spacing: 0.5px;
}

.stat-value
{
    font-size: 28px;

    font-weight: 750;

    margin-top: 12px;
}

.stat-meta
{
    color: #64748b;

    font-size: 9px;

    margin-top: 6px;
}


/* =========================================================
   MAIN GRID
   ========================================================= */

.grid
{
    display: grid;

    grid-template-columns:
        minmax(0,1.45fr)
        minmax(300px,0.75fr);

    gap: 20px;
}


/* =========================================================
   PANEL
   ========================================================= */

.panel
{
    background:
        rgba(13,19,36,0.88);

    border:
        1px solid var(--border);

    border-radius: 15px;

    overflow: hidden;

    margin-bottom: 20px;
}

.panel-header
{
    display: flex;

    justify-content:
        space-between;

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
}


/* =========================================================
   SEARCH
   ========================================================= */

.search-box
{
    padding: 20px;

    display: flex;

    gap: 10px;
}

.search-box input
{
    flex: 1;
}

.search-button
{
    width: 120px;

    background:
        linear-gradient(
            135deg,
            #7c3aed,
            #3b82f6
        );
}


/* =========================================================
   FORM
   ========================================================= */

.form-body
{
    padding: 20px;
}

label
{
    display: block;

    color: #94a3b8;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: 0.5px;

    margin-bottom: 7px;
}

input,
select
{
    width: 100%;

    padding:
        12px 13px;

    border-radius: 10px;

    border:
        1px solid
        rgba(148,163,184,0.15);

    outline: none;

    color: white;

    background:
        rgba(255,255,255,0.035);

    font-size: 12px;

    transition:
        0.2s;
}

input:focus,
select:focus
{
    border-color:
        rgba(59,130,246,0.55);

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,0.08);
}

.form-group
{
    margin-bottom: 15px;
}

.button
{
    width: 100%;

    border: none;

    cursor: pointer;

    padding:
        12px 16px;

    border-radius: 10px;

    color: white;

    font-weight: bold;

    font-size: 12px;

    transition:
        0.2s;
}

.add-button
{
    background:
        linear-gradient(
            135deg,
            #2563eb,
            #06b6d4
        );

    box-shadow:
        0 8px 25px
        rgba(37,99,235,0.22);
}

.add-button:hover
{
    transform:
        translateY(-2px);

    box-shadow:
        0 12px 30px
        rgba(37,99,235,0.30);
}


/* =========================================================
   TABLE
   ========================================================= */

.table-container
{
    overflow-x: auto;
}

table
{
    width: 100%;

    border-collapse:
        collapse;
}

th
{
    text-align: left;

    padding:
        13px 16px;

    color: #64748b;

    font-size: 9px;

    letter-spacing: 0.8px;

    border-bottom:
        1px solid var(--border);
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
}

tr:hover td
{
    background:
        rgba(59,130,246,0.035);
}

.department-name
{
    color: white;

    font-weight: bold;
}

.count-badge
{
    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 30px;

    padding:
        5px 8px;

    border-radius: 20px;

    color: #93c5fd;

    background:
        rgba(59,130,246,0.10);

    border:
        1px solid
        rgba(59,130,246,0.16);

    font-size: 10px;
}

.empty-badge
{
    color: #86efac;

    background:
        rgba(34,197,94,0.08);

    border-color:
        rgba(34,197,94,0.16);
}

.active-badge
{
    color: #93c5fd;

    background:
        rgba(59,130,246,0.08);
}

.delete
{
    display: inline-block;

    text-decoration: none;

    padding:
        6px 10px;

    border-radius: 7px;

    color: #fca5a5;

    background:
        rgba(239,68,68,0.07);

    border:
        1px solid
        rgba(239,68,68,0.12);

    font-size: 9px;

    cursor: pointer;

    transition:
        0.2s;
}

.delete:hover
{
    color: white;

    background:
        rgba(239,68,68,0.18);
}


/* =========================================================
   NETWORK MODULE CARD
   ========================================================= */

.network-card
{
    padding: 20px;
}

.network-box
{
    padding: 18px;

    border-radius: 13px;

    background:
        rgba(139,92,246,0.06);

    border:
        1px solid
        rgba(139,92,246,0.13);

    margin-bottom: 14px;
}

.network-icon
{
    width: 42px;

    height: 42px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #8b5cf6,
            #3b82f6
        );

    font-size: 19px;

    margin-bottom: 12px;
}

.network-title
{
    font-size: 13px;

    font-weight: bold;

    color: white;
}

.network-description
{
    color: #64748b;

    font-size: 10px;

    line-height: 1.6;

    margin-top: 6px;
}

.network-button
{
    display: block;

    text-decoration: none;

    text-align: center;

    width: 100%;

    padding: 12px;

    border-radius: 10px;

    color: white;

    font-size: 11px;

    font-weight: bold;

    background:
        linear-gradient(
            135deg,
            #8b5cf6,
            #3b82f6
        );

    transition:
        0.2s;
}

.network-button:hover
{
    transform:
        translateY(-2px);

    box-shadow:
        0 10px 25px
        rgba(139,92,246,0.22);
}


/* =========================================================
   INFO BOX
   ========================================================= */

.info-box
{
    padding: 20px;
}

.info-item
{
    padding: 14px;

    border-radius: 11px;

    margin-bottom: 10px;

    background:
        rgba(255,255,255,0.025);

    border:
        1px solid
        rgba(148,163,184,0.08);
}

.info-item:last-child
{
    margin-bottom: 0;
}

.info-title
{
    font-size: 10px;

    font-weight: bold;

    letter-spacing: 0.5px;

    color: #93c5fd;
}

.info-text
{
    color: #64748b;

    font-size: 9px;

    line-height: 1.6;

    margin-top: 5px;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty
{
    text-align: center;

    padding:
        40px 20px;

    color: #64748b;

    font-size: 11px;
}

.empty-icon
{
    font-size: 28px;

    margin-bottom: 10px;

    opacity: 0.5;
}


/* =========================================================
   FOOTER
   ========================================================= */

.footer
{
    text-align: center;

    color: #475569;

    font-size: 9px;

    padding:
        10px 0 5px;

    letter-spacing: 0.5px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1100px)
{
    .stats
    {
        grid-template-columns:
            repeat(2,1fr);
    }

    .grid
    {
        grid-template-columns: 1fr;
    }
}

@media(max-width:750px)
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

    .main
    {
        margin-left: 0;

        width: 100%;

        padding: 18px;
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

        flex-wrap: wrap;
    }

    .stats
    {
        grid-template-columns: 1fr;
    }

    .search-box
    {
        flex-direction: column;
    }

    .search-button
    {
        width: 100%;
    }

    table
    {
        min-width: 650px;
    }
}

</style>

</head>


<body>

<div class="app">


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">
            ◈
        </div>

        <div class="brand-text">

            <strong>
                SMART CAMPUS
            </strong>

            <span>
                MANAGEMENT SYSTEM
            </span>

        </div>

    </div>


    <div class="nav-title">
        MAIN MENU
    </div>


    <nav class="nav">

        <a href="dashboard.php">

            <span class="nav-icon">
                ⌂
            </span>

            Dashboard

        </a>


        <a href="students.php">

            <span class="nav-icon">
                ♙
            </span>

            Students

        </a>


        <a href="admission.php">

            <span class="nav-icon">
                ▣
            </span>

            Admissions

        </a>


        <a
            href="department.php"
            class="active"
        >

            <span class="nav-icon">
                ⌘
            </span>

            Departments

        </a>


        <!-- IMPORTANT CHANGE:
             Student Network is now a separate module -->

        <a href="student_network.php">

            <span class="nav-icon">
                ◎
            </span>

            Student Network

        </a>


        <a href="search_sort.php">

            <span class="nav-icon">
                ⌕
            </span>

            Search & Sort

        </a>


        <a href="reports.php">

            <span class="nav-icon">
                ▥
            </span>

            Reports

        </a>

    </nav>


    <div class="sidebar-bottom">

        <div class="system-status">

            <div class="status-row">

                <span class="status-dot"></span>

                System Operational

            </div>

        </div>


        <a
            href="logout.php"
            class="logout"
        >

            <span>
                ⇥
            </span>

            Logout

        </a>

    </div>

</aside>


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
            ACADEMIC CONTROL CENTER
        </div>

        <h1 class="page-title">
            Department Management
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

                echo htmlspecialchars(
                    $adminInitial
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


<!-- =====================================================
     ALERT
     ===================================================== -->

<?php

if ($message != "")
{

?>

<div
    class="alert <?php

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
        Academic Structure Command Center
    </h2>

    <p>

        Manage academic departments, monitor
        department-wise student distribution,
        control department records and access the
        complete student relationship network from
        one unified Smart Campus workspace.

    </p>

</section>


<!-- =====================================================
     STATISTICS
     ===================================================== -->

<section class="stats">


    <div class="stat">

        <div class="stat-label">
            TOTAL DEPARTMENTS
        </div>

        <div class="stat-value">

            <?php

            echo $totalDepartments;

            ?>

        </div>

        <div class="stat-meta">
            Registered academic units
        </div>

    </div>


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
            Student records
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            EMPTY DEPARTMENTS
        </div>

        <div class="stat-value">

            <?php

            echo $emptyDepartments;

            ?>

        </div>

        <div class="stat-meta">
            Departments without students
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            NETWORK MODULE
        </div>

        <div
            class="stat-value"
            style="font-size:20px;"
        >
            ACTIVE
        </div>

        <div class="stat-meta">
            Student graph available
        </div>

    </div>


</section>


<!-- =====================================================
     MAIN GRID
     ===================================================== -->

<div class="grid">


<!-- =====================================================
     LEFT COLUMN
     ===================================================== -->

<div>


<!-- =====================================================
     DEPARTMENT DIRECTORY
     ===================================================== -->

<section class="panel">

    <div class="panel-header">

        <h3>
            Department Directory
        </h3>

        <span>
            LIVE DATABASE
        </span>

    </div>


    <!-- SEARCH -->

    <form
        method="GET"
        action="department.php"
        class="search-box"
    >

        <input
            type="text"
            name="department_search"
            placeholder="Search department..."
            value="<?php

            echo htmlspecialchars(
                $departmentSearch
            );

            ?>"
            maxlength="100"
        >

        <button
            type="submit"
            class="button search-button"
        >

            SEARCH

        </button>

    </form>


    <!-- TABLE -->

    <div class="table-container">

        <?php

        if (
            count($departmentRows) == 0
        )
        {

        ?>

            <div class="empty">

                <div class="empty-icon">
                    ⌘
                </div>

                No departments found.

                <?php

                if ($departmentSearch != "")
                {

                ?>

                    <br><br>

                    Try another department name.

                <?php

                }

                ?>

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
                        DEPARTMENT
                    </th>

                    <th>
                        STUDENTS
                    </th>

                    <th>
                        STATUS
                    </th>

                    <th>
                        ACTION
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php

            foreach (
                $departmentRows
                as $department
            )
            {

                $count =
                    intval(
                        $department[
                            "student_count"
                        ]
                    );

            ?>

                <tr>

                    <td>

                        #

                        <?php

                        echo intval(
                            $department["id"]
                        );

                        ?>

                    </td>


                    <td>

                        <span
                            class="department-name"
                        >

                            <?php

                            echo htmlspecialchars(
                                $department[
                                    "department_name"
                                ]
                            );

                            ?>

                        </span>

                    </td>


                    <td>

                        <span
                            class="count-badge"
                        >

                            <?php

                            echo $count;

                            ?>

                        </span>

                    </td>


                    <td>

                        <?php

                        if ($count == 0)
                        {

                        ?>

                            <span
                                class="count-badge empty-badge"
                            >

                                EMPTY

                            </span>

                        <?php

                        }
                        else
                        {

                        ?>

                            <span
                                class="count-badge active-badge"
                            >

                                ACTIVE

                            </span>

                        <?php

                        }

                        ?>

                    </td>


                    <td>

                        <?php

                        if ($count == 0)
                        {

                        ?>

                            <form
                                method="POST"
                                action="department.php"
                                style="display:inline;"
                                onsubmit="
                                return confirm(
                                    'Delete this empty department?'
                                );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php

                                    echo htmlspecialchars(
                                        $csrfToken
                                    );

                                    ?>"
                                >

                                <input
                                    type="hidden"
                                    name="department_id"
                                    value="<?php

                                    echo intval(
                                        $department["id"]
                                    );

                                    ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_department"
                                    class="delete"
                                >

                                    DELETE

                                </button>

                            </form>

                        <?php

                        }
                        else
                        {

                        ?>

                            <span
                                style="
                                color:#475569;
                                font-size:9px;
                                "
                            >

                                STUDENTS EXIST

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

        <?php

        }

        ?>

    </div>

</section>


<!-- =====================================================
     DEPARTMENT MANAGEMENT INFORMATION
     ===================================================== -->

<section class="panel">

    <div class="panel-header">

        <h3>
            Department Control Information
        </h3>

        <span>
            SYSTEM RULES
        </span>

    </div>


    <div class="info-box">


        <div class="info-item">

            <div class="info-title">
                DEPARTMENT PROTECTION
            </div>

            <div class="info-text">

                Departments containing students cannot
                be deleted. This protects existing
                student records from accidental removal.

            </div>

        </div>


        <div class="info-item">

            <div class="info-title">
                DUPLICATE PREVENTION
            </div>

            <div class="info-text">

                Department names are checked
                case-insensitively before insertion.

            </div>

        </div>


        <div class="info-item">

            <div class="info-title">
                STUDENT NETWORK
            </div>

            <div class="info-text">

                Student-to-student graph relationships
                are managed through the dedicated
                Student Network module.

            </div>

        </div>


    </div>

</section>


</div>


<!-- =====================================================
     RIGHT COLUMN
     ===================================================== -->

<div>


<!-- =====================================================
     ADD DEPARTMENT
     ===================================================== -->

<section class="panel">

    <div class="panel-header">

        <h3>
            Add Department
        </h3>

        <span>
            CREATE
        </span>

    </div>


    <div class="form-body">

        <form
            method="POST"
            action="department.php"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php

                echo htmlspecialchars(
                    $csrfToken
                );

                ?>"
            >


            <div class="form-group">

                <label>
                    DEPARTMENT NAME
                </label>

                <input
                    type="text"
                    name="department_name"
                    placeholder="Example: Artificial Intelligence"
                    maxlength="100"
                    required
                >

            </div>


            <button
                type="submit"
                name="add_department"
                class="button add-button"
            >

                + ADD DEPARTMENT

            </button>

        </form>

    </div>

</section>


<!-- =====================================================
     STUDENT NETWORK
     ===================================================== -->

<section class="panel">

    <div class="panel-header">

        <h3>
            Student Network
        </h3>

        <span>
            GRAPH SYSTEM
        </span>

    </div>


    <div class="network-card">

        <div class="network-box">

            <div class="network-icon">
                ◎
            </div>

            <div class="network-title">
                Student Relationship Graph
            </div>

            <div class="network-description">

                Connect students, prevent duplicate
                relationships, remove connections and
                explore the complete student network.

            </div>

        </div>


        <a
            href="student_network.php"
            class="network-button"
        >

            OPEN STUDENT NETWORK

        </a>

    </div>

</section>


<!-- =====================================================
     QUICK INFORMATION
     ===================================================== -->

<section class="panel">

    <div class="panel-header">

        <h3>
            Academic Intelligence
        </h3>

        <span>
            OVERVIEW
        </span>

    </div>


    <div class="info-box">


        <div class="info-item">

            <div class="info-title">
                ACADEMIC UNITS
            </div>

            <div class="info-text">

                <?php

                echo $totalDepartments;

                ?>

                department(s) are currently
                registered in the system.

            </div>

        </div>


        <div class="info-item">

            <div class="info-title">
                STUDENT DISTRIBUTION
            </div>

            <div class="info-text">

                <?php

                echo $totalStudents;

                ?>

                student record(s) are currently
                associated with the college.

            </div>

        </div>


        <div class="info-item">

            <div class="info-title">
                EMPTY UNITS
            </div>

            <div class="info-text">

                <?php

                echo $emptyDepartments;

                ?>

                department(s) currently contain
                no students.

            </div>

        </div>


    </div>

</section>


</div>

</div>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<div class="footer">

    SMART CAMPUS MANAGEMENT SYSTEM

    &nbsp; • &nbsp;

    DEPARTMENT MANAGEMENT

    &nbsp; • &nbsp;

    ACADEMIC STRUCTURE

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