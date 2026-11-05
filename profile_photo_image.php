<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    http_response_code(403);
    exit();
}

require_once "config/database.php";
require_once "config/photo_utils.php";

$requestId = (int)($_GET["id"] ?? 0);
if ($requestId <= 0) {
    http_response_code(404);
    exit();
}

$stmt = $conn->prepare(
    "SELECT photo_path FROM profile_photo_requests WHERE id = ? AND status = 'Pending' LIMIT 1"
);
$stmt->bind_param("i", $requestId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

$photoPath = $request ? get_photo_file_path($request["photo_path"]) : null;
$pendingDirectory = realpath(get_profile_photo_upload_dir() . "pending");
$realPhotoPath = $photoPath ? realpath($photoPath) : false;

if (
    $realPhotoPath === false ||
    $pendingDirectory === false ||
    strpos($realPhotoPath, $pendingDirectory . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($realPhotoPath)
) {
    http_response_code(404);
    exit();
}

$imageInfo = @getimagesize($realPhotoPath);
if (!$imageInfo || $imageInfo["mime"] !== "image/jpeg") {
    http_response_code(415);
    exit();
}

header("Content-Type: image/jpeg");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store");
header("Content-Length: " . filesize($realPhotoPath));
readfile($realPhotoPath);

?>
