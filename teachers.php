<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: admin_login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";

$message = "";
$messageType = "";

/* Handle Add Teacher */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_teacher"]))
{
    $teacherCode = trim($_POST["teacher_code"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $designation = trim($_POST["designation"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    if ($teacherCode === "" || $name === "" || $email === "" || $password === "" || $department === "")
    {
        $message = "All required fields must be filled.";
        $messageType = "error";
    }
    elseif (strlen($password) < 6)
    {
        $message = "Password must be at least 6 characters.";
        $messageType = "error";
    }
    else
    {
        /* Check for duplicate teacher code */
        $checkStmt = $conn->prepare("SELECT id FROM teachers WHERE teacher_code = ?");
        if ($checkStmt)
        {
            $checkStmt->bind_param("s", $teacherCode);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0)
            {
                $message = "Teacher code already exists.";
                $messageType = "error";
            }
            else
            {
                /* Check for duplicate email */
                $checkStmt2 = $conn->prepare("SELECT id FROM teachers WHERE email = ?");
                if ($checkStmt2)
                {
                    $checkStmt2->bind_param("s", $email);
                    $checkStmt2->execute();
                    if ($checkStmt2->get_result()->num_rows > 0)
                    {
                        $message = "Email already registered.";
                        $messageType = "error";
                    }
                    else
                    {
                        /* Add teacher */
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $status = "ACTIVE";
                        $now = date("Y-m-d H:i:s");

                        $insertStmt = $conn->prepare(
                            "INSERT INTO teachers (teacher_code, name, email, password, department, designation, phone, status, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        );

                        if ($insertStmt)
                        {
                            $insertStmt->bind_param("ssssssssss", $teacherCode, $name, $email, $hashedPassword, $department, $designation, $phone, $status, $now, $now);

                            if ($insertStmt->execute())
                            {
                                $message = "Teacher account created successfully!";
                                $messageType = "success";
                            }
                            else
                            {
                                $message = "Error creating teacher account. Please try again.";
                                $messageType = "error";
                            }

                            $insertStmt->close();
                        }
                    }
                    $checkStmt2->close();
                }
            }
            $checkStmt->close();
        }
    }
}

/* Fetch all teachers */
$teachers = array();
$stmt = $conn->prepare(
    "SELECT id, teacher_code, name, email, department, designation, status, phone, created_at
     FROM teachers
     ORDER BY name ASC"
);

if ($stmt)
{
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result)
    {
        while ($row = $result->fetch_assoc())
        {
            $teachers[] = $row;
        }
    }

    $stmt->close();
}

/* Handle Toggle Status */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_status"]))
{
    $teacherId = (int)($_POST["teacher_id"] ?? 0);
    $newStatus = $_POST["new_status"] ?? "";

    if ($teacherId > 0 && ($newStatus === "ACTIVE" || $newStatus === "INACTIVE"))
    {
        $updateStmt = $conn->prepare("UPDATE teachers SET status = ? WHERE id = ?");
        if ($updateStmt)
        {
            $updateStmt->bind_param("si", $newStatus, $teacherId);

            if ($updateStmt->execute())
            {
                $message = "Teacher status updated successfully!";
                $messageType = "success";

                /* Refresh teachers list */
                $teachers = array();
                $stmt = $conn->prepare(
                    "SELECT id, teacher_code, name, email, department, designation, status, phone, created_at
                     FROM teachers
                     ORDER BY name ASC"
                );

                if ($stmt)
                {
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result)
                    {
                        while ($row = $result->fetch_assoc())
                        {
                            $teachers[] = $row;
                        }
                    }

                    $stmt->close();
                }
            }
            else
            {
                $message = "Error updating teacher status.";
                $messageType = "error";
            }

            $updateStmt->close();
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management - Smart Campus</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, Segoe UI, Arial, sans-serif;
            color: #f8fafc;
            background: radial-gradient(circle at 10% 10%, rgba(59,130,246,.13), transparent 30%),
                        radial-gradient(circle at 90% 15%, rgba(139,92,246,.12), transparent 30%),
                        #070b17;
            min-height: 100vh;
            padding: 35px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .title {
            font-size: 27px;
            font-weight: 700;
        }

        .subtitle {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 5px;
        }

        .logout {
            text-decoration: none;
            color: #fca5a5;
            padding: 10px 15px;
            border-radius: 9px;
            border: 1px solid rgba(239,68,68,.15);
            background: rgba(239,68,68,.07);
            transition: 0.2s;
        }

        .message {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 13px;
            border: 1px solid;
        }

        .error {
            background: rgba(239,68,68,.08);
            color: #fecaca;
            border-color: rgba(239,68,68,.16);
        }

        .success {
            background: rgba(34,197,94,.08);
            color: #bbf7d0;
            border-color: rgba(34,197,94,.16);
        }

        .card {
            padding: 24px;
            border-radius: 15px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 600;
        }

        input, select, textarea {
            width: 100%;
            border: 1px solid rgba(148,163,184,.22);
            border-radius: 10px;
            background: rgba(15,23,42,.85);
            padding: 12px 14px;
            font-size: 13px;
            color: #e2e8f0;
            transition: 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: rgba(59,130,246,.4);
            background: rgba(15,23,42,.95);
        }

        .button {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            color: white;
            transition: 0.2s;
        }

        .button:hover {
            transform: translateY(-2px);
        }

        .teachers-grid {
            display: grid;
            gap: 16px;
        }

        .teacher-card {
            padding: 18px;
            border-radius: 12px;
            background: rgba(13,19,36,.88);
            border: 1px solid rgba(148,163,184,.14);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .teacher-info {
            flex: 1;
        }

        .teacher-name {
            font-weight: 600;
            color: #e2e8f0;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .teacher-detail {
            color: #94a3b8;
            font-size: 11px;
            margin: 2px 0;
        }

        .teacher-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .status-badge {
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
        }

        .status-active {
            background: rgba(34,197,94,.15);
            color: #86efac;
        }

        .status-inactive {
            background: rgba(239,68,68,.15);
            color: #fecaca;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 11px;
            border: 1px solid rgba(148,163,184,.22);
            background: rgba(255,255,255,.05);
            color: #cbd5e1;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-small:hover {
            background: rgba(255,255,255,.08);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        @media (max-width: 800px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .teacher-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .teacher-actions {
                width: 100%;
                margin-top: 12px;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <div>
            <div class="title">Teacher Management</div>
            <div class="subtitle">SMART CAMPUS MANAGEMENT SYSTEM</div>
        </div>
        <a href="dashboard.php" class="logout" style="background: rgba(59,130,246,.07); color: #93c5fd; border-color: rgba(59,130,246,.15);">
            ← Dashboard
        </a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo ($messageType === "error") ? "error" : "success"; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Add Teacher Form -->
    <div class="card">
        <h2 style="margin: 0 0 20px; font-size: 18px;">Add New Teacher</h2>

        <form method="POST">

            <div class="form-row">
                <div class="form-group">
                    <label>Teacher Code *</label>
                    <input type="text" name="teacher_code" placeholder="e.g., T001" required>
                </div>
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" placeholder="Full name" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" placeholder="teacher@email.com" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" placeholder="Minimum 6 characters" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department" required>
                        <option value="">-- Select Department --</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Mechanical">Mechanical</option>
                        <option value="Civil">Civil</option>
                        <option value="Electrical">Electrical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" name="designation" placeholder="e.g., Assistant Professor">
                </div>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" placeholder="Phone number">
            </div>

            <button type="submit" name="add_teacher" class="button">
                CREATE TEACHER ACCOUNT
            </button>

        </form>
    </div>

    <!-- Teachers List -->
    <div class="card">
        <h2 style="margin: 0 0 20px; font-size: 18px;">All Teachers (<?php echo count($teachers); ?>)</h2>

        <?php if (!empty($teachers)): ?>
            <div class="teachers-grid">
                <?php foreach ($teachers as $teacher): ?>
                    <div class="teacher-card">
                        <div class="teacher-info">
                            <div class="teacher-name"><?php echo htmlspecialchars($teacher["name"]); ?></div>
                            <div class="teacher-detail">
                                <strong>Code:</strong> <?php echo htmlspecialchars($teacher["teacher_code"]); ?>
                            </div>
                            <div class="teacher-detail">
                                <strong>Email:</strong> <?php echo htmlspecialchars($teacher["email"]); ?>
                            </div>
                            <div class="teacher-detail">
                                <strong>Dept:</strong> <?php echo htmlspecialchars($teacher["department"]); ?>
                                <?php if ($teacher["designation"]): ?> | <strong>Designation:</strong> <?php echo htmlspecialchars($teacher["designation"]); ?><?php endif; ?>
                            </div>
                            <div class="teacher-detail">
                                <strong>Phone:</strong> <?php echo htmlspecialchars($teacher["phone"] ?? "N/A"); ?>
                            </div>
                        </div>

                        <div class="teacher-actions">
                            <span class="status-badge <?php echo ($teacher["status"] === "ACTIVE") ? "status-active" : "status-inactive"; ?>">
                                <?php echo htmlspecialchars($teacher["status"]); ?>
                            </span>

                            <form method="POST" style="display: inline; margin: 0;">
                                <input type="hidden" name="teacher_id" value="<?php echo $teacher["id"]; ?>">
                                <input type="hidden" name="new_status" value="<?php echo ($teacher["status"] === "ACTIVE") ? "INACTIVE" : "ACTIVE"; ?>">
                                <button type="submit" name="toggle_status" class="btn-small">
                                    <?php echo ($teacher["status"] === "ACTIVE") ? "Deactivate" : "Activate"; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 40px; margin-bottom: 12px;">👨‍🏫</div>
                <div>No teachers registered yet.</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 8px;">Create your first teacher account using the form above.</div>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
