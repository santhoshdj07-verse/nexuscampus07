<?php

session_start();

if (isset($_SESSION["student_id"])) {
    $entityType = "student";
    $entityId = (int)$_SESSION["student_id"];
    $tableName = "students";
    $backUrl = "student_dashboard.php";
} elseif (isset($_SESSION["teacher_id"])) {
    $entityType = "teacher";
    $entityId = (int)$_SESSION["teacher_id"];
    $tableName = "teachers";
    $backUrl = "teacher_dashboard.php";
} else {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";
require_once "config/photo_utils.php";

$message = $_SESSION["profile_photo_message"] ?? "";
$messageType = $_SESSION["profile_photo_message_type"] ?? "success";
unset($_SESSION["profile_photo_message"], $_SESSION["profile_photo_message_type"]);

$profileStmt = $conn->prepare("SELECT id, name, email, photo_path FROM " . $tableName . " WHERE id = ? LIMIT 1");
$profileStmt->bind_param("i", $entityId);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc();
$profileStmt->close();

if (!$profile) {
    session_destroy();
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim($_POST["action"] ?? "");
    $message = "";
    $messageType = "error";

    if (!verify_profile_photo_csrf($_POST["csrf_token"] ?? null)) {
        $message = "Your form expired. Refresh the page and try again.";
    } elseif ($action === "save_email" && $entityType === "student") {
        $email = trim($_POST["email"] ?? "");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Enter a valid email address.";
        } else {
            $updateEmail = $conn->prepare("UPDATE students SET email = ? WHERE id = ?");
            if ($updateEmail) {
                $updateEmail->bind_param("si", $email, $entityId);
                if ($updateEmail->execute()) {
                    $message = "Contact email saved. Admission and document updates can now be emailed to you.";
                    $messageType = "success";
                } else {
                    $message = "Unable to save your email address.";
                }
                $updateEmail->close();
            } else {
                $message = "Unable to prepare the email update.";
            }
        }
    } elseif ($action === "submit_photo" && isset($_FILES["photo"])) {
        $pendingCheck = $conn->prepare(
            "SELECT id FROM profile_photo_requests
             WHERE entity_type = ? AND entity_id = ? AND status = 'Pending'
             LIMIT 1"
        );
        $pendingCheck->bind_param("si", $entityType, $entityId);
        $pendingCheck->execute();
        $hasPendingRequest = $pendingCheck->get_result()->num_rows > 0;
        $pendingCheck->close();

        if ($hasPendingRequest) {
            $message = "Your photo is already waiting for admin approval.";
        } else {
            $savedPhoto = save_pending_profile_photo($entityType, $entityId, $_FILES["photo"]);
            if (!$savedPhoto["success"]) {
                $message = $savedPhoto["message"];
            } else {
                $conn->begin_transaction();
                $entityLock = $conn->prepare("SELECT id FROM " . $tableName . " WHERE id = ? FOR UPDATE");
                $entityLock->bind_param("i", $entityId);
                $entityLock->execute();
                $entityExists = $entityLock->get_result()->num_rows > 0;
                $entityLock->close();

                $pendingCheck = $conn->prepare(
                    "SELECT id FROM profile_photo_requests
                     WHERE entity_type = ? AND entity_id = ? AND status = 'Pending'
                     LIMIT 1 FOR UPDATE"
                );
                $pendingCheck->bind_param("si", $entityType, $entityId);
                $pendingCheck->execute();
                $hasPendingRequest = $pendingCheck->get_result()->num_rows > 0;
                $pendingCheck->close();

                if (!$entityExists || $hasPendingRequest) {
                    $conn->rollback();
                    delete_photo_file($savedPhoto["filepath"]);
                    $message = $hasPendingRequest
                        ? "Your photo is already waiting for admin approval."
                        : "Your account could not be found.";
                } else {
                    $insertRequest = $conn->prepare(
                        "INSERT INTO profile_photo_requests (entity_type, entity_id, photo_path)
                         VALUES (?, ?, ?)"
                    );
                    if ($insertRequest) {
                        $insertRequest->bind_param("sis", $entityType, $entityId, $savedPhoto["filepath"]);
                        if ($insertRequest->execute()) {
                            $conn->commit();
                            $message = "Photo submitted. Your current profile photo stays visible until an admin approves the new one.";
                            $messageType = "success";
                        } else {
                            $conn->rollback();
                            delete_photo_file($savedPhoto["filepath"]);
                            $message = "Unable to submit your photo for review.";
                        }
                        $insertRequest->close();
                    } else {
                        $conn->rollback();
                        delete_photo_file($savedPhoto["filepath"]);
                        $message = "Unable to prepare your photo request.";
                    }
                }
            }
        }
    } else {
        $message = "Invalid request.";
    }

    $_SESSION["profile_photo_message"] = $message;
    $_SESSION["profile_photo_message_type"] = $messageType;
    header("Location: profile_photo.php");
    exit();
}

$requestStmt = $conn->prepare(
    "SELECT status, admin_remarks, requested_at
     FROM profile_photo_requests
     WHERE entity_type = ? AND entity_id = ?
     ORDER BY id DESC LIMIT 1"
);
$requestStmt->bind_param("si", $entityType, $entityId);
$requestStmt->execute();
$latestRequest = $requestStmt->get_result()->fetch_assoc();
$requestStmt->close();

$photoFilePath = get_photo_file_path($profile["photo_path"] ?? null);
$csrfToken = profile_photo_csrf_token();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile settings - Smart Campus</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 36px 18px;
            color: #e8eefb;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            background: radial-gradient(circle at 12% 8%, rgba(59,130,246,.18), transparent 30%), #080e1d;
        }
        .shell { width: min(900px, 100%); margin: 0 auto; }
        .top { display: flex; justify-content: space-between; gap: 18px; align-items: center; margin-bottom: 22px; }
        h1 { margin: 0; font-size: clamp(24px, 4vw, 32px); }
        .muted { margin: 7px 0 0; color: #93a3bd; font-size: 14px; }
        .back, button {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 11px 15px; border: 1px solid rgba(148,163,184,.2);
            border-radius: 10px; color: #e8eefb; background: rgba(255,255,255,.04);
            text-decoration: none; font-weight: 650; cursor: pointer;
        }
        .card {
            margin: 16px 0; padding: 23px; border: 1px solid rgba(148,163,184,.15);
            border-radius: 16px; background: rgba(15,23,42,.82); box-shadow: 0 18px 55px rgba(0,0,0,.18);
        }
        .layout { display: grid; grid-template-columns: 230px 1fr; gap: 24px; align-items: start; }
        .current-photo {
            width: 190px; height: 190px; overflow: hidden; border-radius: 18px;
            display: grid; place-items: center; background: linear-gradient(145deg,#172641,#0e1729);
            border: 1px solid rgba(148,163,184,.2); color: #8fa2bd; font-size: 44px;
        }
        .current-photo img { width: 100%; height: 100%; object-fit: cover; }
        h2 { margin: 0 0 7px; font-size: 18px; }
        .status { margin: 15px 0 0; padding: 12px 14px; border-radius: 10px; font-size: 13px; line-height: 1.55; }
        .status.success { color: #bbf7d0; background: rgba(34,197,94,.1); }
        .status.error { color: #fecaca; background: rgba(239,68,68,.1); }
        .notice { color: #c3d2e8; background: rgba(59,130,246,.09); }
        .request { margin-top: 12px; padding: 11px 13px; border-radius: 10px; color: #cbd5e1; background: rgba(255,255,255,.035); border: 1px solid rgba(148,163,184,.1); font-size: 13px; }
        label { display: block; margin: 16px 0 7px; color: #b8c5d8; font-size: 13px; font-weight: 650; }
        input[type=email], input[type=file], input[type=range] {
            display: block; width: 100%; color: #e8eefb; font: inherit;
        }
        input[type=email], input[type=file] {
            padding: 12px; border: 1px solid rgba(148,163,184,.2); border-radius: 10px;
            background: rgba(8,14,29,.7);
        }
        .primary { margin-top: 16px; border: 0; color: white; background: linear-gradient(120deg,#2563eb,#0891b2); }
        .crop-wrap { display: none; width: min(100%, 280px); margin: 16px 0; }
        canvas { display: block; width: 260px; height: 260px; max-width: 100%; border-radius: 14px; background: #0b1324; cursor: grab; touch-action: none; }
        canvas:active { cursor: grabbing; }
        .hint { color: #93a3bd; font-size: 12px; line-height: 1.6; }
        .message { padding: 12px 14px; border-radius: 10px; margin: 16px 0; }
        @media (max-width: 650px) {
            .top { align-items: flex-start; flex-direction: column; }
            .layout { grid-template-columns: 1fr; }
            .current-photo { width: 150px; height: 150px; }
            .card { padding: 18px; }
        }
    </style>
</head>
<body>
<main class="shell">
    <header class="top">
        <div>
            <h1>Profile settings</h1>
            <p class="muted">Manage your photo and contact details.</p>
        </div>
        <a class="back" href="<?php echo htmlspecialchars($backUrl); ?>">Back to dashboard</a>
    </header>

    <?php if ($message !== ""): ?>
        <div class="message status <?php echo $messageType === "error" ? "error" : "success"; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <section class="card layout">
        <div>
            <div class="current-photo">
                <?php if (!empty($profile["photo_path"]) && $photoFilePath && file_exists($photoFilePath)): ?>
                    <img src="<?php echo htmlspecialchars(get_photo_public_url($profile["photo_path"])); ?>?v=<?php echo filemtime($photoFilePath); ?>" alt="Current profile photo">
                <?php else: ?>
                    <span aria-hidden="true">👤</span>
                <?php endif; ?>
            </div>
            <div class="request">
                <?php if (($latestRequest["status"] ?? "") === "Pending"): ?>
                    Photo review: <strong>Waiting for approval</strong>
                <?php elseif (($latestRequest["status"] ?? "") === "Rejected"): ?>
                    Photo review: <strong>Not approved</strong>
                    <?php if (!empty($latestRequest["admin_remarks"])): ?>
                        <p class="hint"><?php echo htmlspecialchars($latestRequest["admin_remarks"]); ?></p>
                    <?php endif; ?>
                <?php elseif (($latestRequest["status"] ?? "") === "Approved"): ?>
                    Photo review: <strong>Approved</strong>
                <?php else: ?>
                    <strong>No photo requests yet</strong>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h2><?php echo htmlspecialchars($profile["name"]); ?></h2>
            <p class="muted"><?php echo $entityType === "student" ? "Student" : "Teacher"; ?> profile</p>

            <?php if ($entityType === "student"): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="save_email">
                    <label for="email">Contact email</label>
                    <input id="email" type="email" name="email" maxlength="150" required
                           value="<?php echo htmlspecialchars($profile["email"] ?? ""); ?>"
                           placeholder="you@example.com">
                    <button class="primary" type="submit">Save email</button>
                    <p class="hint">We use this address for admission and document status updates. In-app notifications remain available too.</p>
                </form>
            <?php endif; ?>

            <div class="status notice">
                Choose a photo, adjust the square crop, then submit it. Your current photo stays in place until an admin approves the update.
            </div>
            <?php if (($latestRequest["status"] ?? "") !== "Pending"): ?>
                <form id="photo-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="submit_photo">
                    <label for="photo-file">New profile photo</label>
                    <input id="photo-file" type="file" accept="image/jpeg,image/png,image/webp" required>
                    <div class="crop-wrap" id="crop-wrap">
                        <canvas id="crop-canvas" width="320" height="320" aria-label="Photo crop preview"></canvas>
                        <label for="zoom">Zoom</label>
                        <input id="zoom" type="range" min="1" max="2.5" step="0.01" value="1">
                        <p class="hint">Drag the image to position it. The final photo is centered, square, and resized to 512 × 512.</p>
                    </div>
                    <button class="primary" type="submit">Submit photo for approval</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>
<script>
(() => {
    const form = document.getElementById('photo-form');
    if (!form) return;

    const input = document.getElementById('photo-file');
    const wrap = document.getElementById('crop-wrap');
    const canvas = document.getElementById('crop-canvas');
    const context = canvas.getContext('2d');
    const zoom = document.getElementById('zoom');
    let image = null;
    let scale = 1;
    let baseScale = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragStart = null;
    let prepared = false;

    function drawCrop() {
        if (!image) return;
        scale = baseScale * Number(zoom.value);
        const width = image.naturalWidth * scale;
        const height = image.naturalHeight * scale;
        offsetX = Math.min(0, Math.max(canvas.width - width, offsetX));
        offsetY = Math.min(0, Math.max(canvas.height - height, offsetY));
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, offsetX, offsetY, width, height);
    }

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            input.value = '';
            return;
        }
        image = new Image();
        image.onload = () => {
            wrap.style.display = 'block';
            zoom.value = '1';
            baseScale = Math.max(canvas.width / image.naturalWidth, canvas.height / image.naturalHeight);
            scale = baseScale;
            offsetX = (canvas.width - image.naturalWidth * scale) / 2;
            offsetY = (canvas.height - image.naturalHeight * scale) / 2;
            drawCrop();
        };
        image.src = URL.createObjectURL(file);
    });

    zoom.addEventListener('input', drawCrop);
    canvas.addEventListener('pointerdown', event => {
        dragStart = {x: event.clientX, y: event.clientY, offsetX, offsetY};
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', event => {
        if (!dragStart) return;
        const bounds = canvas.getBoundingClientRect();
        offsetX = dragStart.offsetX + (event.clientX - dragStart.x) * canvas.width / bounds.width;
        offsetY = dragStart.offsetY + (event.clientY - dragStart.y) * canvas.height / bounds.height;
        drawCrop();
    });
    canvas.addEventListener('pointerup', () => { dragStart = null; });
    canvas.addEventListener('pointercancel', () => { dragStart = null; });

    form.addEventListener('submit', event => {
        if (prepared) return;
        event.preventDefault();
        if (!image) return;

        const output = document.createElement('canvas');
        output.width = 512;
        output.height = 512;
        const outputContext = output.getContext('2d');
        const ratio = output.width / canvas.width;
        outputContext.drawImage(
            image,
            offsetX * ratio,
            offsetY * ratio,
            image.naturalWidth * scale * ratio,
            image.naturalHeight * scale * ratio
        );
        output.toBlob(blob => {
            if (!blob) return;
            const resizedPhoto = new File([blob], 'profile-photo.jpg', {type: 'image/jpeg'});
            const transfer = new DataTransfer();
            transfer.items.add(resizedPhoto);
            input.name = 'photo';
            input.files = transfer.files;
            prepared = true;
            form.requestSubmit();
        }, 'image/jpeg', 0.88);
    });
})();
</script>
</body>
</html>
