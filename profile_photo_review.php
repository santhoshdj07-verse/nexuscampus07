<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";
require_once "config/photo_utils.php";

$message = "";
$messageType = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestId = (int)($_POST["request_id"] ?? 0);
    $action = trim($_POST["action"] ?? "");
    $remarks = trim($_POST["admin_remarks"] ?? "");

    if (!verify_profile_photo_csrf($_POST["csrf_token"] ?? null)) {
        $message = "Your form expired. Refresh the page and try again.";
        $messageType = "error";
    } elseif ($requestId <= 0 || !in_array($action, array("approve", "reject"), true)) {
        $message = "Invalid photo review request.";
        $messageType = "error";
    } else {
        $conn->begin_transaction();
        $requestStmt = $conn->prepare(
            "SELECT entity_type, entity_id, photo_path, status
             FROM profile_photo_requests WHERE id = ? FOR UPDATE"
        );
        $requestStmt->bind_param("i", $requestId);
        $requestStmt->execute();
        $request = $requestStmt->get_result()->fetch_assoc();
        $requestStmt->close();

        if (
            !$request ||
            $request["status"] !== "Pending" ||
            !in_array($request["entity_type"], array("student", "teacher"), true)
        ) {
            $conn->rollback();
            $message = "This photo request has already been reviewed or is invalid.";
            $messageType = "error";
        } elseif ($action === "reject") {
            if ($remarks === "") {
                $remarks = "Photo did not meet the profile photo requirements.";
            }

            $rejectStmt = $conn->prepare(
                "UPDATE profile_photo_requests
                 SET status = 'Rejected', photo_path = NULL, admin_remarks = ?,
                     reviewed_at = NOW(), reviewed_by = ?
                 WHERE id = ? AND status = 'Pending'"
            );
            $adminId = (int)$_SESSION["admin_id"];
            $rejectStmt->bind_param("sii", $remarks, $adminId, $requestId);
            if ($rejectStmt->execute() && $conn->commit()) {
                delete_photo_file($request["photo_path"]);
                if ($request["entity_type"] === "student") {
                    cms_create_notification(
                        $conn,
                        (int)$request["entity_id"],
                        "Profile photo not approved",
                        "Your profile photo was not approved. " . $remarks,
                        "PROFILE_PHOTO_REJECTED"
                    );
                }
                $message = "Photo request rejected.";
            } else {
                $conn->rollback();
                $message = "Unable to reject the photo request.";
                $messageType = "error";
            }
            $rejectStmt->close();
        } else {
            $newPath = promote_pending_profile_photo(
                $request["entity_type"],
                (int)$request["entity_id"],
                $request["photo_path"]
            );

            if (!$newPath) {
                $conn->rollback();
                $message = "The uploaded photo could not be found or moved into profile storage.";
                $messageType = "error";
            } else {
                $tableName = $request["entity_type"] === "student" ? "students" : "teachers";
                $entityId = (int)$request["entity_id"];
                $currentPhotoStmt = $conn->prepare("SELECT photo_path FROM " . $tableName . " WHERE id = ? FOR UPDATE");
                $currentPhotoStmt->bind_param("i", $entityId);
                $currentPhotoStmt->execute();
                $currentPhoto = $currentPhotoStmt->get_result()->fetch_assoc();
                $currentPhotoStmt->close();

                $updateEntity = $conn->prepare(
                    "UPDATE " . $tableName . " SET photo_path = ?, photo_updated_at = NOW() WHERE id = ?"
                );
                $updateEntity->bind_param("si", $newPath, $entityId);
                $entitySaved = $currentPhoto && $updateEntity->execute();
                $updateEntity->close();

                $approveStmt = $conn->prepare(
                    "UPDATE profile_photo_requests
                     SET status = 'Approved', photo_path = ?, admin_remarks = ?,
                         reviewed_at = NOW(), reviewed_by = ?
                     WHERE id = ? AND status = 'Pending'"
                );
                $adminId = (int)$_SESSION["admin_id"];
                $approveStmt->bind_param("ssii", $newPath, $remarks, $adminId, $requestId);
                $requestSaved = $entitySaved && $approveStmt->execute();
                $approveStmt->close();

                if ($requestSaved && $conn->commit()) {
                    if (!empty($currentPhoto["photo_path"]) && $currentPhoto["photo_path"] !== $newPath) {
                        delete_photo_file($currentPhoto["photo_path"]);
                    }
                    if ($request["entity_type"] === "student") {
                        cms_create_notification(
                            $conn,
                            $entityId,
                            "Profile photo approved",
                            "Your new profile photo has been approved and is now active.",
                            "PROFILE_PHOTO_APPROVED"
                        );
                    }
                    $message = "Photo approved and profile updated.";
                } else {
                    $conn->rollback();
                    if (!restore_pending_profile_photo($newPath, $request["photo_path"])) {
                        error_log("Could not restore a profile photo after approval failed: " . $newPath);
                    }
                    $message = "Unable to approve the photo request. The existing profile photo was kept.";
                    $messageType = "error";
                }
            }
        }
    }
}

$requests = array();
$result = $conn->query(
    "SELECT r.id, r.entity_type, r.entity_id, r.photo_path, r.requested_at,
            s.name AS student_name, s.roll_no, t.name AS teacher_name, t.teacher_code
     FROM profile_photo_requests r
     LEFT JOIN students s ON r.entity_type = 'student' AND s.id = r.entity_id
     LEFT JOIN teachers t ON r.entity_type = 'teacher' AND t.id = r.entity_id
     WHERE r.status = 'Pending'
     ORDER BY r.requested_at ASC"
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}

$csrfToken = profile_photo_csrf_token();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo approvals - Smart Campus</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 34px 18px; color: #e8eefb; font-family: Inter, "Segoe UI", Arial, sans-serif; background: #080e1d; }
        main { width: min(1000px, 100%); margin: auto; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; }
        h1 { margin: 0; font-size: 28px; }
        p { color: #93a3bd; line-height: 1.55; }
        .link, button { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; color: #e8eefb; border: 1px solid rgba(148,163,184,.2); border-radius: 10px; background: rgba(255,255,255,.04); text-decoration: none; font-weight: 650; cursor: pointer; }
        .card { display: grid; grid-template-columns: 220px 1fr; gap: 22px; margin: 14px 0; padding: 20px; border: 1px solid rgba(148,163,184,.15); border-radius: 16px; background: rgba(15,23,42,.8); }
        .photo { width: 210px; height: 210px; object-fit: cover; border-radius: 14px; background: #101a2d; }
        h2 { margin: 0 0 5px; font-size: 18px; }
        .muted { margin: 4px 0 16px; color: #93a3bd; font-size: 13px; }
        label { display: block; margin: 14px 0 6px; color: #b8c5d8; font-size: 12px; font-weight: 650; }
        textarea { width: 100%; min-height: 76px; padding: 10px; resize: vertical; color: #e8eefb; border: 1px solid rgba(148,163,184,.2); border-radius: 9px; background: #0b1324; }
        .actions { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 12px; }
        .approve { border: 0; background: linear-gradient(120deg,#16a34a,#059669); }
        .reject { color: #fecaca; border-color: rgba(248,113,113,.3); }
        .message { padding: 12px 14px; border-radius: 10px; margin: 15px 0; color: #bbf7d0; background: rgba(34,197,94,.1); }
        .message.error { color: #fecaca; background: rgba(239,68,68,.1); }
        .empty { padding: 44px 24px; text-align: center; color: #b8c5d8; border: 1px dashed rgba(148,163,184,.2); border-radius: 16px; }
        @media (max-width: 650px) { header { align-items: flex-start; flex-direction: column; } .card { grid-template-columns: 1fr; } .photo { width: min(100%,260px); height: auto; aspect-ratio: 1; } }
    </style>
</head>
<body>
<main>
    <header>
        <div>
            <h1>Profile photo approvals</h1>
            <p>Review the crop before it replaces the current photo.</p>
        </div>
        <a class="link" href="admin_photo_management.php">Back to photo management</a>
    </header>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo $messageType === "error" ? "error" : ""; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!$requests): ?>
        <div class="empty">No profile photos are waiting for approval.</div>
    <?php endif; ?>

    <?php foreach ($requests as $request): ?>
        <?php
        $entityName = $request["entity_type"] === "student" ? $request["student_name"] : $request["teacher_name"];
        $entityCode = $request["entity_type"] === "student" ? $request["roll_no"] : $request["teacher_code"];
        ?>
        <section class="card">
            <img class="photo" src="profile_photo_image.php?id=<?php echo (int)$request["id"]; ?>" alt="Submitted profile photo">
            <div>
                <h2><?php echo htmlspecialchars($entityName ?? "Account"); ?></h2>
                <p class="muted">
                    <?php echo ucfirst($request["entity_type"]); ?>
                    <?php echo $entityCode ? " · " . htmlspecialchars($entityCode) : ""; ?>
                    · Submitted <?php echo htmlspecialchars(date("M j, Y g:i a", strtotime($request["requested_at"]))); ?>
                </p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="request_id" value="<?php echo (int)$request["id"]; ?>">
                    <label for="remarks-<?php echo (int)$request["id"]; ?>">Note to user (optional)</label>
                    <textarea id="remarks-<?php echo (int)$request["id"]; ?>" name="admin_remarks" maxlength="1000" placeholder="Add a short note if the photo needs changes."></textarea>
                    <div class="actions">
                        <button class="approve" type="submit" name="action" value="approve">Approve photo</button>
                        <button class="reject" type="submit" name="action" value="reject">Reject photo</button>
                    </div>
                </form>
            </div>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>
