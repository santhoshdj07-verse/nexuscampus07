<?php

session_start();

require_once "config/database.php";

if (isset($_SESSION["admin_id"]))
{
    header("Location: dashboard.php");
    exit();
}

if (isset($_SESSION["student_id"]))
{
    header("Location: student_dashboard.php");
    exit();
}

$studentLoginMessage = "";
$studentLoginType = "";
$adminLoginMessage = "";
$adminLoginType = "";
$teacherLoginMessage = "";
$teacherLoginType = "";
$registerMessage = "";
$registerType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    if (isset($_POST["student_login"]))
    {
        $rollNo = trim($_POST["student_identifier"] ?? "");
        $password = $_POST["student_password"] ?? "";

        if ($rollNo === "" || $password === "")
        {
            $studentLoginMessage = "Please enter your roll number and password.";
            $studentLoginType = "error";
        }
        else
        {
            $stmt = $conn->prepare("SELECT id, roll_no, name, department, semester, password FROM students WHERE roll_no = ? LIMIT 1");

            if (!$stmt)
            {
                $studentLoginMessage = "Unable to process student login.";
                $studentLoginType = "error";
            }
            else
            {
                $stmt->bind_param("s", $rollNo);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1)
                {
                    $student = $result->fetch_assoc();

                    if (!empty($student["password"]) && password_verify($password, $student["password"]))
                    {
                        $_SESSION["student_id"] = (int)$student["id"];
                        $_SESSION["student_roll_no"] = $student["roll_no"];
                        $_SESSION["student_name"] = $student["name"];
                        $_SESSION["student_department"] = $student["department"];
                        $_SESSION["student_semester"] = $student["semester"];
                        $_SESSION["role"] = "student";

                        header("Location: student_dashboard.php");
                        exit();
                    }

                    $studentLoginMessage = "Invalid roll number or password.";
                    $studentLoginType = "error";
                }
                else
                {
                    $studentLoginMessage = "Invalid roll number or password.";
                    $studentLoginType = "error";
                }

                $stmt->close();
            }
        }
    }
    elseif (isset($_POST["admin_login"]))
    {
        $username = trim($_POST["admin_username"] ?? "");
        $password = $_POST["admin_password"] ?? "";

        if ($username === "" || $password === "")
        {
            $adminLoginMessage = "Please enter your username and password.";
            $adminLoginType = "error";
        }
        else
        {
            $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ? LIMIT 1");

            if (!$stmt)
            {
                $adminLoginMessage = "Unable to process admin login.";
                $adminLoginType = "error";
            }
            else
            {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1)
                {
                    $admin = $result->fetch_assoc();

                    if (password_verify($password, $admin["password"]))
                    {
                        $_SESSION["admin_id"] = (int)$admin["id"];
                        $_SESSION["admin_username"] = $admin["username"];
                        $_SESSION["admin_name"] = $admin["username"];
                        $_SESSION["role"] = "admin";

                        header("Location: dashboard.php");
                        exit();
                    }

                    $adminLoginMessage = "Invalid username or password.";
                    $adminLoginType = "error";
                }
                else
                {
                    $adminLoginMessage = "Invalid username or password.";
                    $adminLoginType = "error";
                }

                $stmt->close();
            }
        }
    }
    elseif (isset($_POST["teacher_login"]))
    {
        $email = trim($_POST["teacher_email"] ?? "");
        $password = $_POST["teacher_password"] ?? "";

        if ($email === "" || $password === "")
        {
            $teacherLoginMessage = "Please enter your teacher email and password.";
            $teacherLoginType = "error";
        }
        else
        {
            $stmt = $conn->prepare("SELECT id, teacher_code, name, email, password, department, status FROM teachers WHERE email = ? LIMIT 1");

            if (!$stmt)
            {
                $teacherLoginMessage = "Unable to process teacher login.";
                $teacherLoginType = "error";
            }
            else
            {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1)
                {
                    $teacher = $result->fetch_assoc();

                    if ($teacher["status"] !== "ACTIVE")
                    {
                        $teacherLoginMessage = "Your account has been deactivated. Please contact the administrator.";
                        $teacherLoginType = "error";
                    }
                    elseif (!empty($teacher["password"]) && password_verify($password, $teacher["password"]))
                    {
                        $_SESSION["teacher_id"] = (int)$teacher["id"];
                        $_SESSION["teacher_login_id"] = (int)$teacher["id"];
                        $_SESSION["teacher_name"] = $teacher["name"];
                        $_SESSION["teacher_email"] = $teacher["email"];
                        $_SESSION["teacher_department"] = $teacher["department"];
                        $_SESSION["teacher_code"] = $teacher["teacher_code"];
                        $_SESSION["role"] = "teacher";

                        header("Location: teacher_dashboard.php");
                        exit();
                    }
                    else
                    {
                        $teacherLoginMessage = "Invalid email or password.";
                        $teacherLoginType = "error";
                    }
                }
                else
                {
                    $teacherLoginMessage = "Invalid email or password.";
                    $teacherLoginType = "error";
                }

                $stmt->close();
            }
        }
    }
    elseif (isset($_POST["student_register"]))
    {
        $name = trim($_POST["reg_name"] ?? "");
        $rollNo = trim($_POST["reg_roll_no"] ?? "");
        $department = strtolower(trim($_POST["reg_department"] ?? ""));
        $semester = strtoupper(trim($_POST["reg_semester"] ?? ""));
        $phone = trim($_POST["reg_phone"] ?? "");
        $cgpa = trim($_POST["reg_cgpa"] ?? "");
        $password = $_POST["reg_password"] ?? "";
        $confirmPassword = $_POST["reg_confirm_password"] ?? "";

        if ($name === "" || $rollNo === "" || $department === "" || $semester === "" || $phone === "" || $cgpa === "" || $password === "" || $confirmPassword === "")
        {
            $registerMessage = "Please fill all required fields.";
            $registerType = "error";
        }
        elseif (strlen($name) > 20)
        {
            $registerMessage = "Name must not exceed 20 characters.";
            $registerType = "error";
        }
        elseif (!preg_match("/^[A-Za-z ]+$/", $name))
        {
            $registerMessage = "Name can contain only letters and spaces.";
            $registerType = "error";
        }
        elseif (!preg_match("/^[0-9]{2}[a-zA-Z]{4}[0-9]{2}$/", $rollNo))
        {
            $registerMessage = "Roll number must follow the format 26mcaa45.";
            $registerType = "error";
        }
        elseif (!in_array($department, ["msc", "mca", "bca", "bsc", "law", "cse"], true))
        {
            $registerMessage = "Please select a valid department.";
            $registerType = "error";
        }
        elseif (!in_array($semester, ["I", "II", "III", "IV", "V", "VI", "VII", "VIII"], true))
        {
            $registerMessage = "Please select a valid semester.";
            $registerType = "error";
        }
        elseif (!preg_match("/^[0-9]{10}$/", $phone))
        {
            $registerMessage = "Phone number must contain exactly 10 digits.";
            $registerType = "error";
        }
        elseif (!preg_match("/^[0-9]{1}\.[0-9]{2}$/", $cgpa))
        {
            $registerMessage = "CGPA must be in the format 8.93.";
            $registerType = "error";
        }
        elseif ((float)$cgpa < 0 || (float)$cgpa > 10)
        {
            $registerMessage = "CGPA must be between 0.00 and 10.00.";
            $registerType = "error";
        }
        elseif (strlen($password) < 6)
        {
            $registerMessage = "Password must contain at least 6 characters.";
            $registerType = "error";
        }
        elseif ($password !== $confirmPassword)
        {
            $registerMessage = "Password and confirm password do not match.";
            $registerType = "error";
        }
        else
        {
            $stmt = $conn->prepare("SELECT id FROM students WHERE roll_no = ? LIMIT 1");

            if (!$stmt)
            {
                $registerMessage = "Unable to check registration details.";
                $registerType = "error";
            }
            else
            {
                $stmt->bind_param("s", $rollNo);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0)
                {
                    $registerMessage = "This roll number is already registered.";
                    $registerType = "error";
                }
                else
                {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $insert = $conn->prepare("INSERT INTO students (roll_no, name, department, semester, phone, cgpa, password) VALUES (?, ?, ?, ?, ?, ?, ?)");

                    if (!$insert)
                    {
                        $registerMessage = "Unable to complete registration.";
                        $registerType = "error";
                    }
                    else
                    {
                        $insert->bind_param("sssssss", $rollNo, $name, $department, $semester, $phone, $cgpa, $hashedPassword);

                        if ($insert->execute())
                        {
                            $registerMessage = "Registration successful. You can now log in with your roll number and password.";
                            $registerType = "success";
                        }
                        else
                        {
                            $registerMessage = "Unable to complete registration.";
                            $registerType = "error";
                        }

                        $insert->close();
                    }
                }

                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus Access</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: #070b17;
            color: #f8fafc;
        }
        .auth-shell {
            width: min(1100px, calc(100vw - 32px));
            padding: 24px 0;
        }
        .brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand-icon {
            width: 76px;
            height: 76px;
            margin: 0 auto 16px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2563eb, #0891b2);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 0.06em;
            box-shadow: 0 20px 50px rgba(59, 130, 246, 0.35);
        }
        .brand h1 {
            margin: 0;
            font-size: 34px;
            letter-spacing: 0.08em;
        }
        .brand p {
            margin: 10px 0 0;
            color: #94a3b8;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }
        .panel {
            background: rgba(13, 19, 36, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: 20px;
            padding: 24px 22px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.45);
        }
        .panel.full {
            grid-column: 1 / -1;
        }
        .panel h2 {
            margin: 0 0 18px;
            font-size: 20px;
        }
        .message {
            padding: 11px 12px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 13px;
        }
        .message.error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.18);
            color: #fecaca;
        }
        .message.success {
            background: rgba(34, 197, 94, 0.08);
            border: 1px solid rgba(34, 197, 94, 0.18);
            color: #bbf7d0;
        }
        .field {
            margin-bottom: 16px;
        }
        label {
            display: block;
            margin-bottom: 7px;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 600;
        }
        input, select {
            width: 100%;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 10px;
            background: rgba(15, 23, 42, 0.85);
            padding: 12px 13px;
            font-size: 14px;
            color: #f8fafc;
        }
        input:focus, select:focus {
            outline: none;
            border-color: rgba(59, 130, 246, 0.6);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        button {
            width: 100%;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #2563eb, #0891b2);
            color: white;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }
        @media (max-width: 820px) {
            .grid, .row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="brand">
            <div class="brand-icon">N</div>
            <h1>NEXUSCAMPUS</h1>
            <p>Smart Campus Management System</p>
        </div>

        <div class="grid">
            <section class="panel">
                <h2>Student Login</h2>

                <?php if ($studentLoginMessage !== "") { ?>
                    <div class="message <?php echo htmlspecialchars($studentLoginType); ?>"><?php echo htmlspecialchars($studentLoginMessage); ?></div>
                <?php } ?>

                <form method="POST">
                    <div class="field">
                        <label for="student_identifier">Roll Number</label>
                        <input id="student_identifier" type="text" name="student_identifier" placeholder="Enter roll number" required>
                    </div>
                    <div class="field">
                        <label for="student_password">Password</label>
                        <input id="student_password" type="password" name="student_password" placeholder="Enter password" required>
                    </div>
                    <button type="submit" name="student_login" value="1">Login as Student</button>
                </form>
            </section>

            <section class="panel">
                <h2>Admin Login</h2>

                <?php if ($adminLoginMessage !== "") { ?>
                    <div class="message <?php echo htmlspecialchars($adminLoginType); ?>"><?php echo htmlspecialchars($adminLoginMessage); ?></div>
                <?php } ?>

                <form method="POST">
                    <div class="field">
                        <label for="admin_username">Username</label>
                        <input id="admin_username" type="text" name="admin_username" placeholder="Enter username" required>
                    </div>
                    <div class="field">
                        <label for="admin_password">Password</label>
                        <input id="admin_password" type="password" name="admin_password" placeholder="Enter password" required>
                    </div>
                    <button type="submit" name="admin_login" value="1">Login as Admin</button>
                </form>
            </section>

            <section class="panel">
                <h2>Teacher Login</h2>

                <?php if ($teacherLoginMessage !== "") { ?>
                    <div class="message <?php echo htmlspecialchars($teacherLoginType); ?>"><?php echo htmlspecialchars($teacherLoginMessage); ?></div>
                <?php } ?>

                <form method="POST">
                    <div class="field">
                        <label for="teacher_email">Email</label>
                        <input id="teacher_email" type="email" name="teacher_email" placeholder="Enter teacher email" required>
                    </div>
                    <div class="field">
                        <label for="teacher_password">Password</label>
                        <input id="teacher_password" type="password" name="teacher_password" placeholder="Enter teacher password" required>
                    </div>
                    <button type="submit" name="teacher_login" value="1">Login as Teacher</button>
                </form>
            </section>

            <section class="panel full">
                <h2>Student Registration</h2>

                <?php if ($registerMessage !== "") { ?>
                    <div class="message <?php echo htmlspecialchars($registerType); ?>"><?php echo htmlspecialchars($registerMessage); ?></div>
                <?php } ?>

                <form method="POST">
                    <div class="row">
                        <div class="field">
                            <label for="reg_name">Full Name</label>
                            <input id="reg_name" type="text" name="reg_name" placeholder="Enter full name" required>
                        </div>
                        <div class="field">
                            <label for="reg_roll_no">Roll Number</label>
                            <input id="reg_roll_no" type="text" name="reg_roll_no" placeholder="26mcaa45" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="reg_department">Department</label>
                            <select id="reg_department" name="reg_department" required>
                                <option value="">Select department</option>
                                <option value="msc">MSc</option>
                                <option value="mca">MCA</option>
                                <option value="bca">BCA</option>
                                <option value="bsc">BSc</option>
                                <option value="law">Law</option>
                                <option value="cse">CSE</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="reg_semester">Semester</label>
                            <select id="reg_semester" name="reg_semester" required>
                                <option value="">Select semester</option>
                                <option>I</option>
                                <option>II</option>
                                <option>III</option>
                                <option>IV</option>
                                <option>V</option>
                                <option>VI</option>
                                <option>VII</option>
                                <option>VIII</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="reg_phone">Phone</label>
                            <input id="reg_phone" type="text" name="reg_phone" placeholder="10 digit phone number" required>
                        </div>
                        <div class="field">
                            <label for="reg_cgpa">CGPA</label>
                            <input id="reg_cgpa" type="text" name="reg_cgpa" placeholder="8.93" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="reg_password">Password</label>
                            <input id="reg_password" type="password" name="reg_password" placeholder="At least 6 characters" required>
                        </div>
                        <div class="field">
                            <label for="reg_confirm_password">Confirm Password</label>
                            <input id="reg_confirm_password" type="password" name="reg_confirm_password" placeholder="Re-enter password" required>
                        </div>
                    </div>

                    <button type="submit" name="student_register" value="1">Register Student</button>
                </form>
            </section>
        </div>
    </div>
</body>
</html>

