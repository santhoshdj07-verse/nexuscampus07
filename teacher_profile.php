<?php

session_start();

if (!isset($_SESSION["teacher_id"]))
{
    header("Location: teacher_login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";

$teacherId = $_SESSION["teacher_id"];
$message = "";
$messageType = "";

/* Fetch teacher details */
$teacher = null;
$stmt = $conn->prepare(
    "SELECT * FROM teachers WHERE id = ?"
);

if ($stmt)
{
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0)
    {
        $teacher = $result->fetch_assoc();
    }
    
    $stmt->close();
}

/* Handle password change */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["change_password"]))
{
    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "")
    {
        $message = "All fields are required.";
        $messageType = "error";
    }
    elseif ($newPassword !== $confirmPassword)
    {
        $message = "New passwords do not match.";
        $messageType = "error";
    }
    elseif (strlen($newPassword) < 6)
    {
        $message = "New password must be at least 6 characters.";
        $messageType = "error";
    }
    elseif (!password_verify($currentPassword, $teacher["password"]))
    {
        $message = "Current password is incorrect.";
        $messageType = "error";
    }
    else
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE teachers SET password = ? WHERE id = ?");
        
        if ($updateStmt)
        {
            $updateStmt->bind_param("si", $hashedPassword, $teacherId);
            
            if ($updateStmt->execute())
            {
                $message = "Password changed successfully!";
                $messageType = "success";
            }
            else
            {
                $message = "Error updating password. Please try again.";
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
    <title>My Profile - Smart Campus</title>
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
        }

        .content {
            padding: 35px;
            margin-left: 255px;
        }

        .title {
            font-size: 27px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 25px;
        }

        .card {
            padding: 24px;
            border-radius: 15px;
            background: rgba(13,19,36,.86);
            border: 1px solid rgba(148,163,184,.14);
            margin-bottom: 20px;
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

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .profile-section h3 {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 16px;
            color: #e2e8f0;
        }

        .profile-field {
            margin-bottom: 18px;
        }

        .profile-label {
            display: block;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .profile-value {
            color: #e2e8f0;
            font-size: 14px;
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

        input, select {
            width: 100%;
            border: 1px solid rgba(148,163,184,.22);
            border-radius: 10px;
            background: rgba(15,23,42,.85);
            padding: 12px 14px;
            font-size: 13px;
            color: #e2e8f0;
            transition: 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: rgba(59,130,246,.4);
            background: rgba(15,23,42,.95);
        }

        .form-group input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(148,163,184,.10);
            color: #cbd5e1;
            flex: 1;
        }

        .btn-secondary:hover {
            background: rgba(148,163,184,.15);
        }

        @media (max-width: 1000px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 800px) {
            .content {
                padding: 20px;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<?php require_once "includes/teacher_sidebar.php"; ?>

<div class="content">

    <div class="title">My Profile</div>
    <div class="subtitle">SMART CAMPUS MANAGEMENT SYSTEM</div>

    <a href="profile_photo.php" class="btn btn-primary" style="display: inline-block; margin: 0 0 18px; text-decoration: none;">
        Update profile photo
    </a>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo ($messageType === "error") ? "error" : "success"; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="profile-grid">

        <!-- Profile Information -->
        <div class="card">
            <h3>Profile Information</h3>

            <div class="profile-field">
                <span class="profile-label">Name</span>
                <span class="profile-value"><?php echo htmlspecialchars($teacher["name"] ?? ""); ?></span>
            </div>

            <div class="profile-field">
                <span class="profile-label">Email</span>
                <span class="profile-value"><?php echo htmlspecialchars($teacher["email"] ?? ""); ?></span>
            </div>

            <div class="profile-field">
                <span class="profile-label">Teacher Code</span>
                <span class="profile-value"><?php echo htmlspecialchars($teacher["teacher_code"] ?? ""); ?></span>
            </div>

            <div class="profile-field">
                <span class="profile-label">Department</span>
                <span class="profile-value"><?php echo htmlspecialchars($teacher["department"] ?? ""); ?></span>
            </div>

            <div class="profile-field">
                <span class="profile-label">Designation</span>
                <span class="profile-value"><?php echo htmlspecialchars($teacher["designation"] ?? ""); ?></span>
            </div>

            <div class="profile-field">
                <span class="profile-label">Phone</span>
                <span class="profile-value"><?php echo htmlspecialchars($teacher["phone"] ?? ""); ?></span>
            </div>

            <div class="profile-field">
                <span class="profile-label">Status</span>
                <span class="profile-value" style="color: #22c55e;">
                    <?php echo ($teacher["status"] === "ACTIVE") ? "Active" : "Inactive"; ?>
                </span>
            </div>

        </div>

        <!-- Change Password -->
        <div class="card">
            <h3>Change Password</h3>

            <form method="POST">

                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" placeholder="Minimum 6 characters" required>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <div class="button-group">
                    <button type="submit" name="change_password" class="btn btn-primary">
                        UPDATE PASSWORD
                    </button>
                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>
