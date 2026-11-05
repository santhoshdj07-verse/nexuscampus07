<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";
require_once "config/email_notifications.php";

$message = "";
$messageType = "";

$adminName = "Administrator";

if (isset($_SESSION["admin_name"]) && $_SESSION["admin_name"] !== "")
{
    $adminName = $_SESSION["admin_name"];
}

$currentDate = date("l, d F Y");

/* =========================================================
   VERIFY / REJECT DOCUMENT
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    $documentId = isset($_POST["document_id"])
        ? (int)$_POST["document_id"]
        : 0;

    $action = isset($_POST["action"])
        ? trim($_POST["action"])
        : "";

    $remarks = isset($_POST["remarks"])
        ? trim($_POST["remarks"])
        : "";

    if (
        $documentId > 0 &&
        ($action === "Verified" || $action === "Rejected")
    )
    {
        /* Only documents belonging to accepted + verified applications
           can enter document verification. */
        $check = $conn->prepare(
            "SELECT
                sd.id,
                sd.file_path,
                sd.verification_status,
                sa.application_status,
                sa.verified_at
             FROM student_documents sd
             INNER JOIN student_applications sa
                 ON sa.id = sd.application_id
             WHERE sd.id = ?
             AND sa.application_status = 'Accepted'
             AND sa.verified_at IS NOT NULL
             LIMIT 1"
        );

        if ($check)
        {
            $check->bind_param("i", $documentId);
            $check->execute();
            $checkResult = $check->get_result();
            $documentRecord = $checkResult
                ? $checkResult->fetch_assoc()
                : null;
            $check->close();

            if ($documentRecord)
            {
                $previousDocumentStatus = $documentRecord["verification_status"] ?? "";
                if ($action === "Verified" && $remarks === "")
                {
                    $remarks = "Document verified by administrator.";
                }

                if ($action === "Rejected" && $remarks === "")
                {
                    $remarks = "Document rejected. Please upload a valid document.";
                }

                $stmt = $conn->prepare(
                    "UPDATE student_documents
                     SET verification_status = ?,
                         admin_remarks = ?,
                         verified_at = IF(? = 'Verified', NOW(), NULL)
                     WHERE id = ?"
                );

                if ($stmt)
                {
                    $stmt->bind_param(
                        "sssi",
                        $action,
                        $remarks,
                        $action,
                        $documentId
                    );

                    if ($stmt->execute())
                    {
                        /* Get student details for the status notification */
                        $notificationStmt = $conn->prepare(
                            "SELECT sd.student_id, sd.application_id, sd.document_type,
                                    s.name, s.email
                             FROM student_documents sd 
                             JOIN students s ON s.id = sd.student_id
                             WHERE sd.id = ? LIMIT 1"
                        );
                        
                        if ($notificationStmt)
                        {
                            $notificationStmt->bind_param("i", $documentId);
                            $notificationStmt->execute();
                            $notifResult = $notificationStmt->get_result();
                            $notifRow = $notifResult ? $notifResult->fetch_assoc() : null;
                            
                            if ($notifRow && $previousDocumentStatus !== $action)
                            {
                                $studentId = (int)$notifRow["student_id"];
                                $applicationId = (int)$notifRow["application_id"];
                                $docType = $notifRow["document_type"];
                                $emailSubject = $action === "Verified"
                                    ? "Document verified"
                                    : "Document needs attention";
                                $emailMessage = $action === "Verified"
                                    ? "Hello " . $notifRow["name"] . ",\n\nYour " . $docType . " has been verified."
                                    : "Hello " . $notifRow["name"] . ",\n\nYour " . $docType .
                                        " was rejected. Reason: " . $remarks .
                                        "\n\nPlease sign in to review the remarks and upload a corrected document.";

                                cms_send_status_email(
                                    $notifRow["email"] ?? "",
                                    $emailSubject,
                                    $emailMessage
                                );
                                
                                if ($action === "Verified")
                                {
                                    cms_create_notification(
                                        $conn,
                                        $studentId,
                                        "Document Verified",
                                        "Your " . htmlspecialchars($docType) . " has been verified successfully.",
                                        "DOCUMENT_VERIFIED",
                                        $applicationId,
                                        $documentId
                                    );
                                }
                                else /* Rejected */
                                {
                                    cms_create_notification(
                                        $conn,
                                        $studentId,
                                        "Document Rejected",
                                        "Your " . htmlspecialchars($docType) . " was rejected. Reason: " . htmlspecialchars($remarks) . " Please re-upload the document.",
                                        "DOCUMENT_REJECTED",
                                        $applicationId,
                                        $documentId
                                    );
                                }

                                cms_log_activity(
                                    $conn,
                                    "admin",
                                    (int)$_SESSION["admin_id"],
                                    $_SESSION["admin_name"] ?? "Administrator",
                                    $action === "Verified" ? "document_verified" : "document_rejected",
                                    $action === "Verified" ? "Document Verified" : "Document Rejected",
                                    "Admin " . strtolower($action) . " document #" . $documentId . " (" . ($docType ?? "document") . "). " . $remarks,
                                    "student_documents",
                                    $documentId
                                );
                            }
                            
                            $notificationStmt->close();
                        }
                        
                        $message =
                            $action === "Verified"
                            ? "Document verified successfully."
                            : "Document rejected successfully.";

                        $messageType =
                            $action === "Verified"
                            ? "success"
                            : "error";
                    }
                    else
                    {
                        $message = "Unable to update document verification.";
                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }
            else
            {
                $message = "This document is not eligible for verification.";
                $messageType = "error";
            }
        }
    }
}

/* =========================================================
   DOCUMENTS
   ========================================================= */

$documents = $conn->query(
    "SELECT
        sd.id,
        sd.document_type,
        sd.file_name,
        sd.file_path,
        sd.verification_status,
        sd.admin_remarks,
        sd.uploaded_at,
        sd.verified_at,
        sa.application_number,
        sa.program_type,
        sa.course_name,
        sa.application_status,
        s.roll_no,
        s.name
     FROM student_documents sd
     INNER JOIN students s
         ON s.id = sd.student_id
     INNER JOIN student_applications sa
         ON sa.id = sd.application_id
     WHERE sa.application_status = 'Accepted'
     AND sa.verified_at IS NOT NULL
     ORDER BY sd.uploaded_at DESC"
);

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Verification | Smart Campus</title>
<style>
*{box-sizing:border-box}
:root{--border:rgba(148,163,184,.14)}
body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:radial-gradient(circle at 10% 10%,rgba(59,130,246,.13),transparent 30%),radial-gradient(circle at 90% 15%,rgba(139,92,246,.12),transparent 30%),#070b17;color:#f8fafc;min-height:100vh}
body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.18;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:40px 40px}
.app{display:flex;min-height:100vh}.sidebar{width:255px;position:fixed;inset:0 auto 0 0;background:rgba(7,11,23,.88);backdrop-filter:blur(18px);border-right:1px solid var(--border);padding:24px 16px;display:flex;flex-direction:column}.brand{display:flex;align-items:center;gap:12px;padding:8px 10px 28px}.brand-icon{width:43px;height:43px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#2563eb,#06b6d4);font-weight:bold}.brand-text strong{display:block;font-size:15px}.brand-text span{display:block;color:#94a3b8;font-size:11px}.nav-title{color:#64748b;font-size:10px;font-weight:bold;letter-spacing:1.5px;padding:12px}.nav{display:flex;flex-direction:column;gap:5px}.nav a{color:#94a3b8;text-decoration:none;padding:12px 13px;border-radius:10px;display:flex;gap:12px;font-size:13px}.nav a:hover,.nav a.active{color:white;background:rgba(59,130,246,.1)}.nav a.active{border:1px solid rgba(59,130,246,.18);box-shadow:inset 3px 0 #3b82f6}.nav-icon{width:22px;text-align:center}.sidebar-bottom{margin-top:auto}.system-status{padding:13px;border-radius:12px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.14);margin-bottom:12px}.status-row{display:flex;align-items:center;gap:8px;font-size:11px;color:#a7f3d0}.status-dot{width:7px;height:7px;border-radius:50%;background:#22c55e}.logout{display:flex;gap:10px;padding:12px;border-radius:10px;color:#fca5a5;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.1);text-decoration:none;font-size:13px}.main{margin-left:255px;width:calc(100% - 255px);padding:28px 34px}.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px}.page-label{color:#64748b;font-size:12px}.page-title{margin:5px 0 0;font-size:27px}.top-right{display:flex;align-items:center;gap:15px}.date,.profile{border:1px solid var(--border);background:rgba(255,255,255,.025);border-radius:10px}.date{padding:9px 13px;color:#94a3b8;font-size:12px}.profile{display:flex;gap:10px;align-items:center;padding:6px 10px 6px 6px}.avatar{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#8b5cf6,#3b82f6);font-weight:bold}.profile-name{font-size:12px;font-weight:bold}.profile-role{color:#64748b;font-size:10px}.back{display:inline-block;color:#60a5fa;text-decoration:none;margin-bottom:16px}.hero,.panel{background:rgba(13,19,36,.86);border:1px solid var(--border);border-radius:15px}.hero{padding:27px 30px;margin-bottom:20px;background:linear-gradient(115deg,rgba(37,99,235,.2),rgba(8,47,73,.17),rgba(139,92,246,.15))}.hero h2{margin:0 0 8px}.hero p{margin:0;color:#a8b5c8;font-size:13px;line-height:1.6}.message{padding:13px;border-radius:10px;margin-bottom:18px}.success{background:rgba(34,197,94,.08);color:#4ade80;border:1px solid rgba(34,197,94,.15)}.error{background:rgba(239,68,68,.08);color:#f87171;border:1px solid rgba(239,68,68,.15)}.panel-header{display:flex;justify-content:space-between;padding:19px 20px;border-bottom:1px solid var(--border)}.panel-header h3{margin:0;font-size:14px}.panel-header span{color:#64748b;font-size:10px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1200px}th,td{text-align:left;padding:14px 16px;border-bottom:1px solid rgba(148,163,184,.07);font-size:11px}th{color:#64748b;font-size:10px}td{color:#cbd5e1}.status{padding:5px 9px;border-radius:999px;font-size:9px}.pending{background:rgba(245,158,11,.1);color:#fbbf24}.verified{background:rgba(34,197,94,.1);color:#4ade80}.rejected{background:rgba(239,68,68,.1);color:#f87171}.btn{border:0;border-radius:8px;padding:7px 10px;color:white;font-size:10px;cursor:pointer}.verify{background:#166534}.reject{background:#991b1b}.remarks{width:180px;padding:7px;border-radius:7px;border:1px solid var(--border);background:#111a2e;color:white;font-size:10px}.actions{display:flex;gap:6px;margin-top:6px}.footer{text-align:center;padding:28px 0;color:#475569;font-size:10px}@media(max-width:900px){.sidebar{position:relative;width:100%;min-height:auto}.app{display:block}.main{margin-left:0;width:100%;padding:18px}.topbar{flex-direction:column;align-items:flex-start;gap:15px}.top-right{width:100%;justify-content:space-between}}
</style>
</head>
<body>
<div class="app">
<?php require_once __DIR__ . "/includes/admin_sidebar.php"; ?>
<main class="main">
<div class="topbar"><div><div class="page-label">ADMIN CONTROL CENTER</div><h1 class="page-title">Document Verification</h1></div><div class="top-right"><div class="date"><?php echo htmlspecialchars($currentDate); ?></div><div class="profile"><div class="avatar"><?php echo strtoupper(substr($adminName,0,1)); ?></div><div><div class="profile-name"><?php echo htmlspecialchars($adminName); ?></div><div class="profile-role">System Administrator</div></div></div></div></div>
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<section class="hero"><h2>Document Verification Center</h2><p>Verify the academic documents submitted by students whose admission application has already been accepted and verified.</p></section>
<?php if($message !== ""){ ?><div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div><?php } ?>
<section class="panel">
<div class="panel-header"><h3>Submitted Documents</h3><span>DOCUMENT VERIFICATION</span></div>
<div class="table-wrap">
<table>
<thead><tr><th>Student</th><th>Application</th><th>Program / Course</th><th>Document</th><th>File</th><th>Status</th><th>Uploaded</th><th>Action</th></tr></thead>
<tbody>
<?php
if ($documents && $documents->num_rows > 0)
{
    while ($row = $documents->fetch_assoc())
    {
        $cls = strtolower($row["verification_status"]);
?>
<tr>
<td><?php echo htmlspecialchars($row["name"]); ?><br><small><?php echo htmlspecialchars($row["roll_no"]); ?></small></td>
<td><?php echo htmlspecialchars($row["application_number"]); ?></td>
<td><?php echo htmlspecialchars($row["program_type"]); ?> - <?php echo htmlspecialchars($row["course_name"]); ?></td>
<td><?php echo htmlspecialchars($row["document_type"]); ?></td>
<td><a href="<?php echo htmlspecialchars($row["file_path"]); ?>" target="_blank" style="color:#60a5fa"><?php echo htmlspecialchars($row["file_name"]); ?></a></td>
<td><span class="status <?php echo htmlspecialchars($cls); ?>"><?php echo htmlspecialchars($row["verification_status"]); ?></span></td>
<td><?php echo htmlspecialchars($row["uploaded_at"]); ?></td>
<td>
<?php
if ($row["verification_status"] === "Pending")
{
?>
<form method="post">
<input type="hidden" name="document_id" value="<?php echo (int)$row["id"]; ?>">
<input class="remarks" name="remarks" placeholder="Optional remarks">
<div class="actions">
<button class="btn verify" name="action" value="Verified">Verify</button>
<button class="btn reject" name="action" value="Rejected">Reject</button>
</div>
</form>
<?php
}
else
{
    echo htmlspecialchars($row["admin_remarks"] ?? "No remarks");
}
?>
</td>
</tr>
<?php
    }
}
else
{
?>
<tr><td colspan="8">No documents are awaiting verification yet.</td></tr>
<?php
}
?>
</tbody>
</table>
</div>
</section>
<div class="footer">SMART CAMPUS MANAGEMENT SYSTEM &nbsp; • &nbsp; Administrator Control Center &nbsp; • &nbsp; Version 1.0</div>
</main>
</div>
</body>
</html>

<?php
$conn->close();
?>