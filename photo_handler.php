<?php

/* =========================================================
   PROFILE PHOTO UPLOAD HANDLER
   Processes upload, replace, and delete requests
   ========================================================= */

session_start();

header("Content-Type: application/json");

// Check if admin is logged in
if (!isset($_SESSION["admin_id"])) {
    http_response_code(403);
    echo json_encode(array("success" => false, "message" => "Access denied. Admin login required."));
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";
require_once "config/photo_utils.php";

$adminId = $_SESSION["admin_id"];
$adminName = $_SESSION["admin_name"] ?? "Admin";

$action = isset($_POST["action"]) ? trim($_POST["action"]) : "";
$entityType = strtolower(trim($_POST["entity_type"] ?? ""));
$entityId = isset($_POST["entity_id"]) ? (int)$_POST["entity_id"] : 0;

$response = array("success" => false, "message" => "Invalid request.");

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !verify_profile_photo_csrf($_POST["csrf_token"] ?? null)) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Invalid or expired request. Refresh the page and try again."));
    exit();
}

if (!in_array($entityType, array("student", "teacher"), true)) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Invalid profile type."));
    exit();
}

// Ensure upload directories exist
ensure_profile_photo_directories();

if ($action === "upload" && !empty($entityType) && $entityId > 0) {
    
    // Validate entity exists
    $tableName = strtolower($entityType) === "student" ? "students" : "teachers";
    $stmt = $conn->prepare("SELECT id FROM " . $tableName . " WHERE id = ? LIMIT 1");
    
    if ($stmt) {
        $stmt->bind_param("i", $entityId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $response = array("success" => false, "message" => ucfirst($entityType) . " not found.");
            $stmt->close();
        } else {
            $stmt->close();
            
            // Check if file was uploaded
            if (!isset($_FILES["photo"]) || $_FILES["photo"]["error"] != 0) {
                $response = array("success" => false, "message" => "No file uploaded or upload error.");
            } else {
                // Upload the file
                $uploadResult = replace_profile_photo($conn, $entityType, $entityId, $_FILES["photo"]);
                
                if ($uploadResult["success"]) {
                    // Update database
                    $newPath = $uploadResult["filepath"];
                    $updateStmt = $conn->prepare(
                        "UPDATE " . $tableName . " SET photo_path = ?, photo_updated_at = NOW() WHERE id = ?"
                    );
                    
                    if ($updateStmt) {
                        $updateStmt->bind_param("si", $newPath, $entityId);
                        if ($updateStmt->execute()) {
                            if (!empty($uploadResult["previous_filepath"]) &&
                                $uploadResult["previous_filepath"] !== $newPath &&
                                !delete_photo_file($uploadResult["previous_filepath"])) {
                                error_log("Unable to remove replaced profile photo: " . $uploadResult["previous_filepath"]);
                            }

                            // Log activity
                            $entityName = strtolower($entityType) === "student" ? "Student" : "Teacher";
                            log_photo_activity($conn, $adminId, $adminName, "upload", $entityType, $entityId, 
                                             "Uploaded profile photo for " . $entityName . " ID: " . $entityId);
                            
                            $response = array(
                                "success" => true,
                                "message" => "Photo uploaded successfully.",
                                "filepath" => $newPath
                            );
                        } else {
                            delete_photo_file($newPath);
                            $response = array("success" => false, "message" => "Failed to save photo to database.");
                        }
                        $updateStmt->close();
                    }
                    else {
                        delete_photo_file($newPath);
                        $response = array("success" => false, "message" => "Unable to prepare photo database update.");
                    }
                } else {
                    $response = $uploadResult;
                }
            }
        }
    } else {
        $response = array("success" => false, "message" => "Database error.");
    }
    
} else if ($action === "delete" && !empty($entityType) && $entityId > 0) {
    
    // Validate entity exists
    $tableName = strtolower($entityType) === "student" ? "students" : "teachers";
    $stmt = $conn->prepare("SELECT photo_path FROM " . $tableName . " WHERE id = ? LIMIT 1");
    
    if ($stmt) {
        $stmt->bind_param("i", $entityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response = array("success" => false, "message" => ucfirst($entityType) . " not found.");
            $stmt->close();
        } else {
            $row = $result->fetch_assoc();
            $photoPath = $row["photo_path"];
            $stmt->close();
            
            // Update database - clear photo path
            $updateStmt = $conn->prepare(
                "UPDATE " . $tableName . " SET photo_path = NULL, photo_updated_at = NOW() WHERE id = ?"
            );
            
            if ($updateStmt) {
                $updateStmt->bind_param("i", $entityId);
                if ($updateStmt->execute()) {
                    if (!empty($photoPath) && !delete_photo_file($photoPath)) {
                        error_log("Unable to remove deleted profile photo: " . $photoPath);
                    }

                    // Log activity
                    log_photo_activity($conn, $adminId, $adminName, "delete", $entityType, $entityId, 
                                     "Deleted profile photo for " . ucfirst($entityType) . " ID: " . $entityId);
                    
                    $response = array("success" => true, "message" => "Photo removed successfully.");
                } else {
                    $response = array("success" => false, "message" => "Failed to update database.");
                }
                $updateStmt->close();
            }
        }
    } else {
        $response = array("success" => false, "message" => "Database error.");
    }
}

// Return JSON response
echo json_encode($response);

?>
