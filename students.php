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


/* =========================================================
   ADMIN NAME
   ========================================================= */

if (isset($_SESSION["admin_name"]) && $_SESSION["admin_name"] != "")
{
    $adminName = $_SESSION["admin_name"];
}


/* =========================================================
   DELETE STUDENT
   ========================================================= */

if (isset($_GET["delete"]))
{
    $id = intval($_GET["delete"]);

    if ($id > 0)
    {
        $stmt = $conn->prepare(
            "DELETE FROM students WHERE id = ?"
        );

        if ($stmt)
        {
            $stmt->bind_param("i", $id);

            if ($stmt->execute())
            {
                $message = "Student record deleted successfully.";
                $messageType = "success";
            }
            else
            {
                $message = "Unable to delete student record.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   ADD STUDENT
   ========================================================= */

if (isset($_POST["add_student"]))
{
    $rollNo = trim($_POST["roll_no"]);
    $name = trim($_POST["name"]);
    $department = strtolower(trim($_POST["department"]));
    $semester = strtoupper(trim($_POST["semester"]));
    $phone = trim($_POST["phone"]);
    $cgpa = trim($_POST["cgpa"]);


    /* -----------------------------------------------------
       ROLL NUMBER
       ----------------------------------------------------- */

    if (!preg_match(
        "/^[0-9]{2}[a-zA-Z]{4}[0-9]{2}$/",
        $rollNo
    ))
    {
        $message = "Invalid roll number. Example: 26MCAA45.";
        $messageType = "error";
    }


    /* -----------------------------------------------------
       NAME
       ----------------------------------------------------- */

    elseif (
        strlen($name) > 20 ||
        !preg_match("/^[A-Za-z ]+$/", $name)
    )
    {
        $message = "Name must contain only letters and spaces and must not exceed 20 characters.";
        $messageType = "error";
    }


    /* -----------------------------------------------------
       DEPARTMENT
       ----------------------------------------------------- */

    elseif (
        !in_array(
            $department,
            array(
                "msc",
                "mca",
                "bca",
                "bsc",
                "law",
                "cse"
            )
        )
    )
    {
        $message = "Invalid department.";
        $messageType = "error";
    }


    /* -----------------------------------------------------
       SEMESTER
       ----------------------------------------------------- */

    elseif (
        !in_array(
            $semester,
            array(
                "I",
                "II",
                "III",
                "IV",
                "V",
                "VI",
                "VII",
                "VIII"
            )
        )
    )
    {
        $message = "Invalid semester.";
        $messageType = "error";
    }


    /* -----------------------------------------------------
       PHONE
       ----------------------------------------------------- */

    elseif (!preg_match("/^[0-9]{10}$/", $phone))
    {
        $message = "Phone number must contain exactly 10 digits.";
        $messageType = "error";
    }


    /* -----------------------------------------------------
       CGPA
       ----------------------------------------------------- */

    elseif (!preg_match("/^[0-9]{1}\.[0-9]{2}$/", $cgpa))
    {
        $message = "CGPA must be in format like 8.93.";
        $messageType = "error";
    }


    elseif (
        floatval($cgpa) < 0 ||
        floatval($cgpa) > 10
    )
    {
        $message = "CGPA must be between 0.00 and 10.00.";
        $messageType = "error";
    }


    /* -----------------------------------------------------
       INSERT
       ----------------------------------------------------- */

    else
    {
        $check = $conn->prepare(
            "SELECT id FROM students WHERE roll_no = ?"
        );

        if ($check)
        {
            $check->bind_param("s", $rollNo);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0)
            {
                $message = "A student with this roll number already exists.";
                $messageType = "error";
            }
            else
            {
                $stmt = $conn->prepare(
                    "INSERT INTO students
                    (roll_no, name, department, semester, phone, cgpa)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                if ($stmt)
                {
                    $stmt->bind_param(
                        "sssssd",
                        $rollNo,
                        $name,
                        $department,
                        $semester,
                        $phone,
                        $cgpa
                    );

                    if ($stmt->execute())
                    {
                        $message = "Student added successfully.";
                        $messageType = "success";
                    }
                    else
                    {
                        $message = "Unable to add student.";
                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }

            $check->close();
        }
    }
}


/* =========================================================
   UPDATE STUDENT
   ========================================================= */

if (isset($_POST["update_student"]))
{
    $id = intval($_POST["student_id"]);

    $rollNo = trim($_POST["roll_no"]);
    $name = trim($_POST["name"]);
    $department = strtolower(trim($_POST["department"]));
    $semester = strtoupper(trim($_POST["semester"]));
    $phone = trim($_POST["phone"]);
    $cgpa = trim($_POST["cgpa"]);


    if (
        !preg_match(
            "/^[0-9]{2}[a-zA-Z]{4}[0-9]{2}$/",
            $rollNo
        )
    )
    {
        $message = "Invalid roll number.";
        $messageType = "error";
    }

    elseif (
        strlen($name) > 20 ||
        !preg_match("/^[A-Za-z ]+$/", $name)
    )
    {
        $message = "Invalid student name.";
        $messageType = "error";
    }

    elseif (
        !in_array(
            $department,
            array(
                "msc",
                "mca",
                "bca",
                "bsc",
                "law",
                "cse"
            )
        )
    )
    {
        $message = "Invalid department.";
        $messageType = "error";
    }

    elseif (
        !in_array(
            $semester,
            array(
                "I",
                "II",
                "III",
                "IV",
                "V",
                "VI",
                "VII",
                "VIII"
            )
        )
    )
    {
        $message = "Invalid semester.";
        $messageType = "error";
    }

    elseif (!preg_match("/^[0-9]{10}$/", $phone))
    {
        $message = "Invalid phone number.";
        $messageType = "error";
    }

    elseif (!preg_match("/^[0-9]{1}\.[0-9]{2}$/", $cgpa))
    {
        $message = "Invalid CGPA format.";
        $messageType = "error";
    }

    elseif (
        floatval($cgpa) < 0 ||
        floatval($cgpa) > 10
    )
    {
        $message = "CGPA must be between 0.00 and 10.00.";
        $messageType = "error";
    }

    else
    {
        $stmt = $conn->prepare(
            "UPDATE students
             SET roll_no = ?,
                 name = ?,
                 department = ?,
                 semester = ?,
                 phone = ?,
                 cgpa = ?
             WHERE id = ?"
        );

        if ($stmt)
        {
            $stmt->bind_param(
                "sssssdi",
                $rollNo,
                $name,
                $department,
                $semester,
                $phone,
                $cgpa,
                $id
            );

            if ($stmt->execute())
            {
                $message = "Student record updated successfully.";
                $messageType = "success";
            }
            else
            {
                $message = "Unable to update student record.";
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
   GET STUDENTS
   ========================================================= */

if ($search != "")
{
    $stmt = $conn->prepare(
        "SELECT *
         FROM students
         WHERE roll_no LIKE ?
         OR name LIKE ?
         OR department LIKE ?
         ORDER BY id DESC"
    );

    $searchValue = "%" . $search . "%";

    $stmt->bind_param(
        "sss",
        $searchValue,
        $searchValue,
        $searchValue
    );

    $stmt->execute();

    $students = $stmt->get_result();
}
else
{
    $students = $conn->query(
        "SELECT *
         FROM students
         ORDER BY id DESC"
    );
}


/* =========================================================
   TOTAL STUDENTS
   ========================================================= */

$totalStudents = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM students"
);

if ($result)
{
    $row = $result->fetch_assoc();

    $totalStudents = $row["total"];
}


/* =========================================================
   AVERAGE CGPA
   ========================================================= */

$averageCgpa = 0;

$result = $conn->query(
    "SELECT AVG(cgpa) AS average FROM students"
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
   DEPARTMENT COUNT
   ========================================================= */

$departmentCount = 0;

$result = $conn->query(
    "SELECT COUNT(DISTINCT department) AS total
     FROM students"
);

if ($result)
{
    $row = $result->fetch_assoc();

    $departmentCount = $row["total"];
}


/* =========================================================
   EDIT STUDENT
   ========================================================= */

$editStudent = null;

if (isset($_GET["edit"]))
{
    $editId = intval($_GET["edit"]);

    $stmt = $conn->prepare(
        "SELECT *
         FROM students
         WHERE id = ?"
    );

    if ($stmt)
    {
        $stmt->bind_param("i", $editId);

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0)
        {
            $editStudent = $result->fetch_assoc();
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Student Management | Smart Campus</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <style>

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

            transition: 0.2s;
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


        .topbar
        {
            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 25px;
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
           PAGE HERO
           ================================================= */

        .hero
        {
            position: relative;

            overflow: hidden;

            border-radius: 18px;

            padding: 25px 28px;

            margin-bottom: 20px;

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


        .hero h2
        {
            margin: 0 0 7px;

            font-size: 22px;
        }


        .hero p
        {
            margin: 0;

            color: #a8b5c8;

            font-size: 13px;

            line-height: 1.6;
        }


        /* =================================================
           STAT CARDS
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
            background:
                rgba(13,19,36,0.86);

            border:
                1px solid var(--border);

            border-radius: 15px;

            padding: 18px;
        }


        .stat-label
        {
            color: var(--muted);

            font-size: 11px;

            font-weight: bold;
        }


        .stat-value
        {
            font-size: 27px;

            font-weight: 750;

            margin-top: 12px;
        }


        .stat-meta
        {
            color: #64748b;

            font-size: 10px;

            margin-top: 5px;
        }


        /* =================================================
           TOOLBAR
           ================================================= */

        .toolbar
        {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 15px;
        }


        .search-box
        {
            flex: 1;

            max-width: 600px;

            display: flex;

            align-items: center;

            gap: 10px;

            background:
                rgba(13,19,36,0.86);

            border:
                1px solid var(--border);

            border-radius: 12px;

            padding: 4px 6px 4px 14px;
        }


        .search-box span
        {
            color: #64748b;

            font-size: 16px;
        }


        .search-box input
        {
            flex: 1;

            background: transparent;

            border: none;

            outline: none;

            color: white;

            padding: 11px 5px;

            font-size: 12px;
        }


        .search-box input::placeholder
        {
            color: #64748b;
        }


        .search-button
        {
            border: none;

            cursor: pointer;

            padding: 10px 16px;

            border-radius: 9px;

            color: white;

            font-weight: bold;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #3b82f6
                );

            box-shadow:
                0 0 18px rgba(37,99,235,0.20);
        }


        .add-button
        {
            border: none;

            cursor: pointer;

            padding: 13px 18px;

            border-radius: 11px;

            color: white;

            font-weight: bold;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #06b6d4
                );

            box-shadow:
                0 0 25px rgba(37,99,235,0.25);

            transition: 0.2s;
        }


        .add-button:hover
        {
            transform: translateY(-2px);

            box-shadow:
                0 0 30px rgba(37,99,235,0.40);
        }


        /* =================================================
           ALERT
           ================================================= */

        .alert
        {
            padding: 13px 16px;

            border-radius: 11px;

            margin-bottom: 15px;

            font-size: 12px;
        }


        .alert.success
        {
            background:
                rgba(34,197,94,0.08);

            border:
                1px solid rgba(34,197,94,0.18);

            color: #86efac;
        }


        .alert.error
        {
            background:
                rgba(239,68,68,0.08);

            border:
                1px solid rgba(239,68,68,0.18);

            color: #fca5a5;
        }


        /* =================================================
           TABLE
           ================================================= */

        .table-panel
        {
            background:
                rgba(13,19,36,0.86);

            border:
                1px solid var(--border);

            border-radius: 16px;

            overflow: hidden;
        }


        .table-header
        {
            padding: 18px 20px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            border-bottom:
                1px solid var(--border);
        }


        .table-header h3
        {
            margin: 0;

            font-size: 14px;
        }


        .table-header span
        {
            color: #64748b;

            font-size: 10px;
        }


        .table-wrapper
        {
            overflow-x: auto;
        }


        table
        {
            width: 100%;

            border-collapse: collapse;

            min-width: 850px;
        }


        th
        {
            text-align: left;

            padding: 14px 17px;

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
            padding: 15px 17px;

            font-size: 12px;

            color: #cbd5e1;

            border-bottom:
                1px solid rgba(148,163,184,0.06);
        }


        tr:hover td
        {
            background:
                rgba(59,130,246,0.035);
        }


        .student-name
        {
            color: white;

            font-weight: bold;
        }


        .roll
        {
            color: #60a5fa;

            font-family: monospace;

            font-size: 11px;
        }


        .department
        {
            display: inline-block;

            padding: 5px 8px;

            border-radius: 7px;

            background:
                rgba(139,92,246,0.10);

            color: #c4b5fd;

            font-size: 10px;

            font-weight: bold;

            text-transform: uppercase;
        }


        .semester
        {
            color: #94a3b8;
        }


        .cgpa
        {
            font-weight: bold;

            color: #4ade80;
        }


        .phone
        {
            color: #94a3b8;

            font-family: monospace;

            font-size: 11px;
        }


        .actions-cell
        {
            white-space: nowrap;
        }


        .edit-btn,
        .delete-btn
        {
            display: inline-block;

            text-decoration: none;

            padding: 7px 10px;

            border-radius: 7px;

            font-size: 10px;

            margin-right: 4px;

            transition: 0.2s;
        }


        .edit-btn
        {
            color: #93c5fd;

            background:
                rgba(59,130,246,0.10);

            border:
                1px solid rgba(59,130,246,0.14);
        }


        .delete-btn
        {
            color: #fca5a5;

            background:
                rgba(239,68,68,0.08);

            border:
                1px solid rgba(239,68,68,0.12);
        }


        .edit-btn:hover
        {
            background:
                rgba(59,130,246,0.18);
        }


        .delete-btn:hover
        {
            background:
                rgba(239,68,68,0.16);
        }


        .empty
        {
            text-align: center;

            padding: 55px 20px;

            color: #64748b;
        }


        .empty-icon
        {
            font-size: 35px;

            margin-bottom: 10px;

            color: #475569;
        }


        /* =================================================
           FORM PANEL
           ================================================= */

        .form-panel
        {
            background:
                rgba(13,19,36,0.95);

            border:
                1px solid rgba(59,130,246,0.18);

            border-radius: 16px;

            margin-bottom: 20px;

            overflow: hidden;

            box-shadow:
                0 20px 60px rgba(0,0,0,0.25);
        }


        .form-header
        {
            padding: 20px 22px;

            border-bottom:
                1px solid var(--border);

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .form-header h3
        {
            margin: 0;

            font-size: 15px;
        }


        .form-header p
        {
            margin: 4px 0 0;

            color: #64748b;

            font-size: 10px;
        }


        .close-form
        {
            text-decoration: none;

            color: #94a3b8;

            font-size: 20px;
        }


        .form-body
        {
            padding: 22px;
        }


        .form-grid
        {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 17px;
        }


        .field
        {
            display: flex;

            flex-direction: column;

            gap: 7px;
        }


        .field label
        {
            color: #94a3b8;

            font-size: 10px;

            font-weight: bold;

            letter-spacing: 0.5px;
        }


        .field input,
        .field select
        {
            width: 100%;

            padding: 12px 13px;

            border-radius: 9px;

            border:
                1px solid rgba(148,163,184,0.14);

            outline: none;

            background:
                rgba(255,255,255,0.035);

            color: white;

            font-size: 12px;

            transition: 0.2s;
        }


        .field input:focus,
        .field select:focus
        {
            border-color:
                rgba(59,130,246,0.55);

            box-shadow:
                0 0 0 3px rgba(59,130,246,0.08);
        }


        .field select option
        {
            background: #0d1324;

            color: white;
        }


        .form-actions
        {
            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 20px;
        }


        .cancel-btn
        {
            text-decoration: none;

            padding: 11px 16px;

            border-radius: 9px;

            color: #94a3b8;

            background:
                rgba(255,255,255,0.035);

            border:
                1px solid var(--border);

            font-size: 11px;
        }


        .submit-btn
        {
            border: none;

            cursor: pointer;

            padding: 11px 18px;

            border-radius: 9px;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #06b6d4
                );

            font-weight: bold;

            font-size: 11px;
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
           RESPONSIVE
           ================================================= */

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

            .stats
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

            .topbar
            {
                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }

            .top-right
            {
                width: 100%;

                justify-content: space-between;
            }

            .toolbar
            {
                flex-direction: column;

                align-items: stretch;
            }

            .search-box
            {
                max-width: none;
            }

            .form-grid
            {
                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 480px)
        {
            .date
            {
                display: none;
            }

            .hero
            {
                padding: 21px;
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
                    Student Management
                </h1>

            </div>


            <div class="top-right">


                <div class="date">

                    <?php

                    echo date("l, d F Y");

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
                Student Records Command Center
            </h2>

            <p>

                Manage, monitor and maintain all student
                records from one centralized academic
                management workspace.

            </p>

        </section>



        <!-- =================================================
             ALERT
             ================================================= -->

        <?php

        if ($message != "")
        {

        ?>

            <div
                class="alert
                <?php echo $messageType; ?>"
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
             EDIT FORM
             ================================================= -->

        <?php

        if ($editStudent !== null)
        {

        ?>

        <section class="form-panel">


            <div class="form-header">

                <div>

                    <h3>
                        Edit Student Record
                    </h3>

                    <p>
                        Update academic and personal information
                    </p>

                </div>


                <a
                    href="students.php"
                    class="close-form"
                >
                    ×
                </a>

            </div>


            <form
                method="POST"
                class="form-body"
            >


                <input
                    type="hidden"
                    name="student_id"
                    value="<?php
                    echo $editStudent["id"];
                    ?>"
                >


                <div class="form-grid">


                    <div class="field">

                        <label>
                            ROLL NUMBER
                        </label>

                        <input
                            type="text"
                            name="roll_no"
                            maxlength="8"
                            value="<?php
                            echo htmlspecialchars(
                                $editStudent["roll_no"]
                            );
                            ?>"
                            required
                        >

                    </div>


                    <div class="field">

                        <label>
                            STUDENT NAME
                        </label>

                        <input
                            type="text"
                            name="name"
                            maxlength="20"
                            value="<?php
                            echo htmlspecialchars(
                                $editStudent["name"]
                            );
                            ?>"
                            required
                        >

                    </div>


                    <div class="field">

                        <label>
                            DEPARTMENT
                        </label>

                        <select
                            name="department"
                            required
                        >

                            <?php

                            $departments = array(
                                "msc",
                                "mca",
                                "bca",
                                "bsc",
                                "law",
                                "cse"
                            );

                            foreach (
                                $departments
                                as $dept
                            )
                            {

                            ?>

                                <option
                                    value="<?php
                                    echo $dept;
                                    ?>"
                                    <?php

                                    if (
                                        strtolower(
                                            $editStudent["department"]
                                        ) == $dept
                                    )
                                    {
                                        echo "selected";
                                    }

                                    ?>
                                >

                                    <?php
                                    echo strtoupper($dept);
                                    ?>

                                </option>

                            <?php

                            }

                            ?>

                        </select>

                    </div>


                    <div class="field">

                        <label>
                            SEMESTER
                        </label>

                        <select
                            name="semester"
                            required
                        >

                            <?php

                            $semesters = array(
                                "I",
                                "II",
                                "III",
                                "IV",
                                "V",
                                "VI",
                                "VII",
                                "VIII"
                            );

                            foreach (
                                $semesters
                                as $sem
                            )
                            {

                            ?>

                                <option
                                    value="<?php
                                    echo $sem;
                                    ?>"
                                    <?php

                                    if (
                                        strtoupper(
                                            $editStudent["semester"]
                                        ) == $sem
                                    )
                                    {
                                        echo "selected";
                                    }

                                    ?>
                                >

                                    Semester <?php
                                    echo $sem;
                                    ?>

                                </option>

                            <?php

                            }

                            ?>

                        </select>

                    </div>


                    <div class="field">

                        <label>
                            PHONE NUMBER
                        </label>

                        <input
                            type="text"
                            name="phone"
                            maxlength="10"
                            value="<?php
                            echo htmlspecialchars(
                                $editStudent["phone"]
                            );
                            ?>"
                            required
                        >

                    </div>


                    <div class="field">

                        <label>
                            CGPA
                        </label>

                        <input
                            type="text"
                            name="cgpa"
                            maxlength="4"
                            placeholder="8.93"
                            value="<?php
                            echo htmlspecialchars(
                                $editStudent["cgpa"]
                            );
                            ?>"
                            required
                        >

                    </div>


                </div>


                <div class="form-actions">

                    <a
                        href="students.php"
                        class="cancel-btn"
                    >
                        Cancel
                    </a>


                    <button
                        type="submit"
                        name="update_student"
                        class="submit-btn"
                    >
                        Save Changes
                    </button>

                </div>


            </form>


        </section>

        <?php

        }

        ?>



        <!-- =================================================
             STATISTICS
             ================================================= -->

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
                    Registered student records
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    ACTIVE DEPARTMENTS
                </div>

                <div class="stat-value">
                    <?php
                    echo $departmentCount;
                    ?>
                </div>

                <div class="stat-meta">
                    Departments represented by students
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    AVERAGE CGPA
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


        </section>



        <!-- =================================================
             SEARCH TOOLBAR
             ================================================= -->

        <div class="toolbar">


            <form
                method="GET"
                class="search-box"
            >

                <span>
                    ⌕
                </span>

                <input
                    type="text"
                    name="search"
                    placeholder="Search by roll number, name or department..."
                    value="<?php
                    echo htmlspecialchars($search);
                    ?>"
                >

                <button
                    type="submit"
                    class="search-button"
                >
                    Search
                </button>

            </form>


            <button
                type="button"
                class="add-button"
                onclick="showAddForm()"
            >

                + Add Student

            </button>


        </div>



        <!-- =================================================
             ADD FORM
             ================================================= -->

        <section
            class="form-panel"
            id="addForm"
            style="display:none;"
        >


            <div class="form-header">

                <div>

                    <h3>
                        Add New Student
                    </h3>

                    <p>
                        Register a new student in the academic database
                    </p>

                </div>


                <a
                    href="javascript:void(0)"
                    class="close-form"
                    onclick="hideAddForm()"
                >
                    ×
                </a>

            </div>


            <form
                method="POST"
                class="form-body"
            >


                <div class="form-grid">


                    <div class="field">

                        <label>
                            ROLL NUMBER
                        </label>

                        <input
                            type="text"
                            name="roll_no"
                            maxlength="8"
                            placeholder="26MCAA45"
                            required
                        >

                    </div>


                    <div class="field">

                        <label>
                            STUDENT NAME
                        </label>

                        <input
                            type="text"
                            name="name"
                            maxlength="20"
                            placeholder="Student Name"
                            required
                        >

                    </div>


                    <div class="field">

                        <label>
                            DEPARTMENT
                        </label>

                        <select
                            name="department"
                            required
                        >

                            <option value="">
                                Select Department
                            </option>

                            <option value="msc">
                                MSC
                            </option>

                            <option value="mca">
                                MCA
                            </option>

                            <option value="bca">
                                BCA
                            </option>

                            <option value="bsc">
                                BSC
                            </option>

                            <option value="law">
                                LAW
                            </option>

                            <option value="cse">
                                CSE
                            </option>

                        </select>

                    </div>


                    <div class="field">

                        <label>
                            SEMESTER
                        </label>

                        <select
                            name="semester"
                            required
                        >

                            <option value="">
                                Select Semester
                            </option>

                            <option value="I">
                                Semester I
                            </option>

                            <option value="II">
                                Semester II
                            </option>

                            <option value="III">
                                Semester III
                            </option>

                            <option value="IV">
                                Semester IV
                            </option>

                            <option value="V">
                                Semester V
                            </option>

                            <option value="VI">
                                Semester VI
                            </option>

                            <option value="VII">
                                Semester VII
                            </option>

                            <option value="VIII">
                                Semester VIII
                            </option>

                        </select>

                    </div>


                    <div class="field">

                        <label>
                            PHONE NUMBER
                        </label>

                        <input
                            type="text"
                            name="phone"
                            maxlength="10"
                            placeholder="9876543210"
                            required
                        >

                    </div>


                    <div class="field">

                        <label>
                            CGPA
                        </label>

                        <input
                            type="text"
                            name="cgpa"
                            maxlength="4"
                            placeholder="8.93"
                            required
                        >

                    </div>


                </div>


                <div class="form-actions">

                    <button
                        type="button"
                        class="cancel-btn"
                        onclick="hideAddForm()"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        name="add_student"
                        class="submit-btn"
                    >
                        + Add Student
                    </button>

                </div>


            </form>


        </section>



        <!-- =================================================
             STUDENT TABLE
             ================================================= -->

        <section class="table-panel">


            <div class="table-header">

                <h3>
                    Student Directory
                </h3>

                <span>

                    <?php

                    if ($search != "")
                    {
                        echo "SEARCH RESULTS";
                    }
                    else
                    {
                        echo "LIVE DATABASE";
                    }

                    ?>

                </span>

            </div>


            <div class="table-wrapper">


                <?php

                if (
                    $students &&
                    $students->num_rows > 0
                )
                {

                ?>

                <table>


                    <thead>

                        <tr>

                            <th>
                                ROLL NUMBER
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

                            <th>
                                ACTIONS
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    while (
                        $student =
                        $students->fetch_assoc()
                    )
                    {

                    ?>

                    <tr>


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
                                    $student["department"]
                                );

                                ?>

                            </span>

                        </td>


                        <td>

                            <span class="semester">

                                Semester
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
                                    (float)$student["cgpa"],
                                    2
                                );

                                ?>

                            </span>

                        </td>


                        <td class="actions-cell">


                            <a
                                href="student_profile.php?id=<?php
                                echo $student["id"];
                                ?>"
                                class="edit-btn"
                                style="background: rgba(34,197,94,0.10); border-color: rgba(34,197,94,0.16); color: #86efac;"
                            >
                                View
                            </a>

                            <a
                                href="students.php?edit=<?php
                                echo $student["id"];
                                ?>"
                                class="edit-btn"
                            >
                                Edit
                            </a>


                            <a
                                href="students.php?delete=<?php
                                echo $student["id"];
                                ?>"
                                class="delete-btn"
                                onclick="return confirm(
                                    'Are you sure you want to delete this student?'
                                );"
                            >
                                Delete
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
                else
                {

                ?>

                    <div class="empty">

                        <div class="empty-icon">
                            ◇
                        </div>

                        <strong>
                            No student records found
                        </strong>

                        <p>
                            Try another search or add a new student.
                        </p>

                    </div>

                <?php

                }

                ?>


            </div>


        </section>



        <div class="footer">

            SMART CAMPUS MANAGEMENT SYSTEM
            &nbsp; • &nbsp;
            Student Management Console
            &nbsp; • &nbsp;
            Version 1.0

        </div>


    </main>


</div>



<script>


/* =========================================================
   ADD FORM
   ========================================================= */

function showAddForm()
{
    document.getElementById("addForm").style.display = "block";

    document.getElementById("addForm").scrollIntoView({
        behavior: "smooth"
    });
}


function hideAddForm()
{
    document.getElementById("addForm").style.display = "none";
}


/* =========================================================
   AUTO HIDE SUCCESS MESSAGE
   ========================================================= */

setTimeout(function()
{
    var alertBox =
        document.querySelector(".alert");

    if (alertBox)
    {
        alertBox.style.opacity = "0";

        alertBox.style.transition =
            "opacity 0.5s";

        setTimeout(function()
        {
            alertBox.remove();

        }, 500);
    }

}, 5000);


</script>


</body>

</html>

<?php

$conn->close();

?>