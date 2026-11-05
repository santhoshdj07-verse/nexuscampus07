<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

if (!isset($_GET["id"]))
{
    header("Location: students.php");
    exit();
}

$id = intval($_GET["id"]);

$message = "";
$messageType = "";

$sql = "SELECT * FROM students WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0)
{
    $stmt->close();
    $conn->close();

    header("Location: students.php");
    exit();
}

$student = $result->fetch_assoc();

$stmt->close();


if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $rollNo = trim($_POST["roll_no"]);
    $name = trim($_POST["name"]);
    $department = trim($_POST["department"]);
    $semester = trim($_POST["semester"]);
    $phone = trim($_POST["phone"]);
    $cgpa = trim($_POST["cgpa"]);

    if (!preg_match("/^[0-9]{2}[a-zA-Z]{4}[0-9]{2}$/", $rollNo))
    {
        $message = "Invalid Roll No! Enter like: 26mcaa45";
        $messageType = "error";
    }
    elseif (strlen($name) > 20 || !preg_match("/^[A-Za-z ]+$/", $name))
    {
        $message = "Invalid Name! Use letters and spaces only, maximum 20 characters.";
        $messageType = "error";
    }
    elseif (!in_array(strtolower($department), array(
        "msc",
        "mca",
        "bca",
        "bsc",
        "law",
        "cse"
    )))
    {
        $message = "Invalid Department! Use only: msc, mca, bca, bsc, law, cse.";
        $messageType = "error";
    }
    elseif (!preg_match("/^(I|II|III|IV|V|VI|VII|VIII)$/", strtoupper($semester)))
    {
        $message = "Invalid Semester! Enter Roman numerals like: I, II, III, IV, V, VI, VII, VIII.";
        $messageType = "error";
    }
    elseif (!preg_match("/^[0-9]{10}$/", $phone))
    {
        $message = "Invalid Phone! Enter exactly 10 digits.";
        $messageType = "error";
    }
    elseif (!preg_match("/^[0-9]{1}\.[0-9]{2}$/", $cgpa))
    {
        $message = "Invalid CGPA! Enter like: 8.93";
        $messageType = "error";
    }
    elseif ((float)$cgpa < 0 || (float)$cgpa > 10)
    {
        $message = "Invalid CGPA! CGPA must be between 0.00 and 10.00.";
        $messageType = "error";
    }
    else
    {
        $department = strtolower($department);
        $semester = strtoupper($semester);

        $sql = "UPDATE students
                SET roll_no = ?,
                    name = ?,
                    department = ?,
                    semester = ?,
                    phone = ?,
                    cgpa = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssssssi",
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
            $message = "Student updated successfully!";
            $messageType = "success";

            $student["roll_no"] = $rollNo;
            $student["name"] = $name;
            $student["department"] = $department;
            $student["semester"] = $semester;
            $student["phone"] = $phone;
            $student["cgpa"] = $cgpa;
        }
        else
        {
            if ($stmt->errno == 1062)
            {
                $message = "Roll No already exists!";
            }
            else
            {
                $message = "Error updating student: " . $stmt->error;
            }

            $messageType = "error";
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Update Student</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body
        {
            margin: 0;

            font-family: Arial, sans-serif;

            background: #f1f5f9;
        }

        .header
        {
            background: #1e3a8a;

            color: white;

            padding: 20px 35px;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }

        .header h1
        {
            margin: 0;

            font-size: 24px;
        }

        .back
        {
            color: white;

            text-decoration: none;

            background: #475569;

            padding: 9px 16px;

            border-radius: 6px;
        }

        .container
        {
            width: 90%;

            max-width: 900px;

            margin: 35px auto;
        }

        .card
        {
            background: white;

            padding: 30px;

            border-radius: 10px;

            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        h2
        {
            color: #1e3a8a;

            margin-top: 0;
        }

        .form-grid
        {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 18px;
        }

        .form-group
        {
            display: flex;

            flex-direction: column;
        }

        label
        {
            font-weight: bold;

            margin-bottom: 7px;
        }

        input,
        select
        {
            padding: 11px;

            border: 1px solid #cbd5e1;

            border-radius: 6px;

            font-size: 15px;
        }

        .hint
        {
            font-size: 12px;

            color: #64748b;

            margin-top: 5px;
        }

        .message
        {
            padding: 12px;

            border-radius: 6px;

            margin-bottom: 20px;

            font-weight: bold;
        }

        .success
        {
            background: #dcfce7;

            color: #166534;
        }

        .error
        {
            background: #fee2e2;

            color: #b91c1c;
        }

        .buttons
        {
            margin-top: 25px;
        }

        .update-button
        {
            padding: 12px 25px;

            border: none;

            border-radius: 6px;

            background: #2563eb;

            color: white;

            font-weight: bold;

            cursor: pointer;
        }

        .cancel-button
        {
            display: inline-block;

            margin-left: 10px;

            padding: 11px 22px;

            border-radius: 6px;

            background: #64748b;

            color: white;

            text-decoration: none;
        }

        @media (max-width: 700px)
        {
            .form-grid
            {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

    <div class="header">

        <h1>
            SMART COLLEGE MANAGEMENT SYSTEM
        </h1>

        <a
            href="students.php"
            class="back"
        >
            ← Students
        </a>

    </div>


    <div class="container">

        <div class="card">

            <h2>
                Update Student
            </h2>


            <?php

            if ($message != "")
            {
                echo "<div class='message $messageType'>";
                echo htmlspecialchars($message);
                echo "</div>";
            }

            ?>


            <form method="POST">


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Roll No
                        </label>

                        <input
                            type="text"
                            name="roll_no"
                            maxlength="8"
                            value="<?php echo htmlspecialchars($student["roll_no"]); ?>"
                            required
                        >

                        <span class="hint">
                            Format: 26mcaa45
                        </span>

                    </div>


                    <div class="form-group">

                        <label>
                            Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            maxlength="20"
                            value="<?php echo htmlspecialchars($student["name"]); ?>"
                            required
                        >

                        <span class="hint">
                            Letters and spaces only, maximum 20 characters.
                        </span>

                    </div>


                    <div class="form-group">

                        <label>
                            Department
                        </label>

                        <select name="department" required>

                            <option value="msc"
                            <?php
                            if ($student["department"] == "msc")
                                echo "selected";
                            ?>>
                                MSC
                            </option>

                            <option value="mca"
                            <?php
                            if ($student["department"] == "mca")
                                echo "selected";
                            ?>>
                                MCA
                            </option>

                            <option value="bca"
                            <?php
                            if ($student["department"] == "bca")
                                echo "selected";
                            ?>>
                                BCA
                            </option>

                            <option value="bsc"
                            <?php
                            if ($student["department"] == "bsc")
                                echo "selected";
                            ?>>
                                BSC
                            </option>

                            <option value="law"
                            <?php
                            if ($student["department"] == "law")
                                echo "selected";
                            ?>>
                                LAW
                            </option>

                            <option value="cse"
                            <?php
                            if ($student["department"] == "cse")
                                echo "selected";
                            ?>>
                                CSE
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Semester
                        </label>

                        <select name="semester" required>

                            <option value="I"
                            <?php
                            if ($student["semester"] == "I")
                                echo "selected";
                            ?>>
                                I
                            </option>

                            <option value="II"
                            <?php
                            if ($student["semester"] == "II")
                                echo "selected";
                            ?>>
                                II
                            </option>

                            <option value="III"
                            <?php
                            if ($student["semester"] == "III")
                                echo "selected";
                            ?>>
                                III
                            </option>

                            <option value="IV"
                            <?php
                            if ($student["semester"] == "IV")
                                echo "selected";
                            ?>>
                                IV
                            </option>

                            <option value="V"
                            <?php
                            if ($student["semester"] == "V")
                                echo "selected";
                            ?>>
                                V
                            </option>

                            <option value="VI"
                            <?php
                            if ($student["semester"] == "VI")
                                echo "selected";
                            ?>>
                                VI
                            </option>

                            <option value="VII"
                            <?php
                            if ($student["semester"] == "VII")
                                echo "selected";
                            ?>>
                                VII
                            </option>

                            <option value="VIII"
                            <?php
                            if ($student["semester"] == "VIII")
                                echo "selected";
                            ?>>
                                VIII
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            maxlength="10"
                            value="<?php echo htmlspecialchars($student["phone"]); ?>"
                            required
                        >

                        <span class="hint">
                            Exactly 10 digits.
                        </span>

                    </div>


                    <div class="form-group">

                        <label>
                            CGPA
                        </label>

                        <input
                            type="text"
                            name="cgpa"
                            maxlength="4"
                            value="<?php echo htmlspecialchars($student["cgpa"]); ?>"
                            required
                        >

                        <span class="hint">
                            Example: 8.93
                        </span>

                    </div>


                </div>


                <div class="buttons">

                    <button
                        type="submit"
                        class="update-button"
                    >
                        Update Student
                    </button>

                    <a
                        href="students.php"
                        class="cancel-button"
                    >
                        Cancel
                    </a>

                </div>


            </form>

        </div>

    </div>

</body>

</html>

<?php

$conn->close();

?>