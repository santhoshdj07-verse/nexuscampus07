<?php

session_start();

// Check if admin is logged in
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";
require_once "config/photo_utils.php";

$adminName = $_SESSION["admin_name"] ?? "Administrator";
$selectedTab = isset($_GET["tab"]) ? trim($_GET["tab"]) : "students";
$selectedTab = in_array($selectedTab, array("students", "teachers"), true) ? $selectedTab : "students";
$searchResults = array();
$selectedEntity = null;
$csrfToken = profile_photo_csrf_token();
$pendingPhotoRequests = 0;
$requestCountResult = $conn->query(
    "SELECT COUNT(*) AS total FROM profile_photo_requests WHERE status = 'Pending'"
);
if ($requestCountResult) {
    $pendingPhotoRequests = (int)$requestCountResult->fetch_assoc()["total"];
}

// Handle search
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["search"])) {
    $searchTerm = trim($_POST["search_term"] ?? "");
    
    if (!empty($searchTerm)) {
        if ($selectedTab === "students") {
            // Search students by name, roll number, or ID
            $searchParam = "%{$searchTerm}%";
            $stmt = $conn->prepare(
                "SELECT s.*, sa.application_status 
                 FROM students s
                 LEFT JOIN student_applications sa ON s.id = sa.student_id
                 WHERE s.name LIKE ? OR s.roll_no LIKE ? OR s.id LIKE ?
                 ORDER BY s.name
                 LIMIT 50"
            );
            
            if ($stmt) {
                $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $searchResults[] = $row;
                }
                $stmt->close();
            }
        } else {
            // Search teachers by name, teacher ID, or code
            $searchParam = "%{$searchTerm}%";
            $stmt = $conn->prepare(
                "SELECT * FROM teachers
                 WHERE name LIKE ? OR teacher_code LIKE ? OR id LIKE ?
                 ORDER BY name
                 LIMIT 50"
            );
            
            if ($stmt) {
                $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $searchResults[] = $row;
                }
                $stmt->close();
            }
        }
    }
}

// If entity ID is provided via GET or POST, fetch it
if (isset($_GET["entity_id"]) || isset($_POST["entity_id"])) {
    $entityId = (int)(isset($_GET["entity_id"]) ? $_GET["entity_id"] : $_POST["entity_id"]);
    
    if ($selectedTab === "students") {
        $stmt = $conn->prepare(
            "SELECT s.*, sa.application_status, sa.id as application_id
             FROM students s
             LEFT JOIN student_applications sa ON s.id = sa.student_id
             WHERE s.id = ?
             LIMIT 1"
        );
    } else {
        $stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
    }
    
    if ($stmt) {
        $stmt->bind_param("i", $entityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $selectedEntity = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Photo Management - EduEnroll Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            color: #2c3e50;
        }

        .tab-btn.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }

        .search-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .search-box button {
            padding: 12px 30px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .search-box button:hover {
            background: #2980b9;
        }

        .main-content {
            display: flex;
            gap: 30px;
        }

        .search-results {
            flex: 0 0 35%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .results-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
            font-weight: 600;
        }

        .results-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .result-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .result-item:hover {
            background: #f8f9fa;
        }

        .result-item.selected {
            background: #e3f2fd;
            border-left: 4px solid #3498db;
        }

        .result-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .result-info {
            font-size: 12px;
            color: #999;
        }

        .photo-management {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .photo-management h3 {
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .info-item {
            font-size: 14px;
        }

        .info-label {
            color: #999;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .info-value {
            color: #333;
        }

        .photo-preview-section {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .photo-preview {
            width: 200px;
            height: 200px;
            margin: 0 auto 15px;
            border-radius: 8px;
            background: #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid #ddd;
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #999;
        }

        .photo-placeholder i {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .upload-section {
            border: 2px dashed #3498db;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            background: #f0f8ff;
        }

        .upload-section:hover {
            border-color: #2980b9;
            background: #e3f2fd;
        }

        .upload-section.dragging {
            border-color: #27ae60;
            background: #e8f5e9;
        }

        .upload-section i {
            font-size: 32px;
            color: #3498db;
            margin-bottom: 10px;
        }

        .upload-section p {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .upload-section .small {
            color: #999;
            font-size: 12px;
        }

        #photo-input {
            display: none;
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
            }

            .search-results {
                flex: 1;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>
        <i class="fas fa-image"></i>
        Profile Photo Management
    </h1>
    <p>Upload and manage student and teacher profile photos</p>
    <a href="profile_photo_review.php" style="display: inline-flex; gap: 8px; align-items: center; margin-top: 12px; padding: 9px 13px; border: 1px solid rgba(255,255,255,.22); border-radius: 9px; color: #fff; text-decoration: none; font-size: 13px; font-weight: 600;">
        <i class="fas fa-user-check"></i>
        Review photo requests<?php echo $pendingPhotoRequests ? " (" . $pendingPhotoRequests . ")" : ""; ?>
    </a>
</div>

<div class="container">
    
    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn <?php echo ($selectedTab === "students") ? "active" : ""; ?>" 
                onclick="switchTab('students')">
            <i class="fas fa-user-graduate"></i> Students
        </button>
        <button class="tab-btn <?php echo ($selectedTab === "teachers") ? "active" : ""; ?>" 
                onclick="switchTab('teachers')">
            <i class="fas fa-chalkboard-user"></i> Teachers
        </button>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <form method="POST" class="search-box">
            <input type="text" 
                   name="search_term" 
                   placeholder="Search by name, ID, or roll number..." 
                   value="<?php echo isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : ''; ?>"
                   required>
            <input type="hidden" name="search" value="1">
            <button type="submit">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Search Results -->
        <div class="search-results">
            <?php if (!empty($searchResults)): ?>
                <div class="results-header">
                    Found <?php echo count($searchResults); ?> result(s)
                </div>
                <div class="results-list">
                    <?php foreach ($searchResults as $entity): ?>
                        <div class="result-item <?php echo ($selectedEntity && $selectedEntity['id'] === $entity['id']) ? 'selected' : ''; ?>" 
                             onclick="selectEntity('<?php echo htmlspecialchars(json_encode($entity)); ?>')">
                            <div class="result-name">
                                <?php echo htmlspecialchars($entity['name']); ?>
                            </div>
                            <div class="result-info">
                                <?php
                                if ($selectedTab === "students") {
                                    echo "Roll: " . htmlspecialchars($entity['roll_no'] ?? "N/A") . 
                                         " | Dept: " . htmlspecialchars($entity['department'] ?? "N/A");
                                    if (isset($entity['application_status'])) {
                                        echo " | Status: " . htmlspecialchars($entity['application_status']);
                                    }
                                } else {
                                    echo "Code: " . htmlspecialchars($entity['teacher_code'] ?? "N/A") . 
                                         " | Dept: " . htmlspecialchars($entity['department'] ?? "N/A");
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($_SERVER["REQUEST_METHOD"] === "POST"): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>No <?php echo $selectedTab; ?> found matching your search.</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Search for a <?php echo rtrim($selectedTab, 's'); ?> to get started</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Photo Management -->
        <div class="photo-management">
            <?php if ($selectedEntity): ?>
                
                <div class="message" id="message"></div>

                <!-- Entity Info -->
                <div class="info-section">
                    <h3>
                        <?php echo htmlspecialchars($selectedEntity['name']); ?>
                    </h3>
                    <div class="info-grid">
                        <?php if ($selectedTab === "students"): ?>
                            <div class="info-item">
                                <div class="info-label">Roll Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($selectedEntity['roll_no'] ?? "N/A"); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Student ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($selectedEntity['id']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?php echo htmlspecialchars($selectedEntity['department'] ?? "N/A"); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Semester</div>
                                <div class="info-value"><?php echo htmlspecialchars($selectedEntity['semester'] ?? "N/A"); ?></div>
                            </div>
                            <?php if (isset($selectedEntity['application_status'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Application Status</div>
                                    <div class="info-value">
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $selectedEntity['application_status'])); ?>">
                                            <?php echo htmlspecialchars($selectedEntity['application_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="info-item">
                                <div class="info-label">Teacher Code</div>
                                <div class="info-value"><?php echo htmlspecialchars($selectedEntity['teacher_code'] ?? "N/A"); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Teacher ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($selectedEntity['id']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?php echo htmlspecialchars($selectedEntity['department'] ?? "N/A"); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Designation</div>
                                <div class="info-value"><?php echo htmlspecialchars($selectedEntity['designation'] ?? "N/A"); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Photo Preview -->
                <div class="photo-preview-section">
                    <h3>Current Photo</h3>
                    <div class="photo-preview" id="photo-preview">
                        <?php $selectedPhotoPath = get_photo_file_path($selectedEntity['photo_path'] ?? null); ?>
                        <?php if (!empty($selectedEntity['photo_path']) && $selectedPhotoPath && file_exists($selectedPhotoPath)): ?>
                            <img src="<?php echo htmlspecialchars(get_photo_public_url($selectedEntity['photo_path'])); ?>?v=<?php echo time(); ?>" 
                                 alt="Profile Photo">
                        <?php else: ?>
                            <div class="photo-placeholder">
                                <i class="fas fa-image"></i>
                                <p>No Photo Uploaded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upload Section -->
                <div class="upload-section" id="upload-area">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Drag & Drop Photo Here</strong></p>
                    <p class="small">or click to select</p>
                </div>

                <input type="file" id="photo-input" name="photo" accept="image/jpeg,image/png,image/webp">

                <!-- Action Buttons -->
                <div class="button-group">
                    <button class="btn btn-primary" onclick="uploadPhoto()" id="upload-btn" style="display: none;">
                        <i class="fas fa-upload"></i> Upload Photo
                    </button>
                    <?php if (!empty($selectedEntity['photo_path'])): ?>
                        <button class="btn btn-danger" onclick="deletePhoto()">
                            <i class="fas fa-trash"></i> Remove Photo
                        </button>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-tie"></i>
                    <p>Select a <?php echo rtrim($selectedTab, 's'); ?> from the list to manage their profile photo</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
    const selectedTab = "<?php echo htmlspecialchars($selectedTab); ?>";
    const selectedEntityId = <?php echo $selectedEntity ? $selectedEntity['id'] : 'null'; ?>;
    const csrfToken = "<?php echo htmlspecialchars($csrfToken); ?>";
    let selectedFile = null;

    function switchTab(tab) {
        window.location.href = "admin_photo_management.php?tab=" + tab;
    }

    function selectEntity(entityData) {
        const entity = JSON.parse(entityData);
        const url = "admin_photo_management.php?tab=" + selectedTab + "&entity_id=" + entity.id;
        window.location.href = url;
    }

    // Drag and drop
    const uploadArea = document.getElementById('upload-area');
    const photoInput = document.getElementById('photo-input');
    const uploadBtn = document.getElementById('upload-btn');

    if (uploadArea) {
        uploadArea.addEventListener('click', () => photoInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragging');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragging');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragging');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                photoInput.files = files;
                previewPhoto();
            }
        });
    }

    if (photoInput) {
        photoInput.addEventListener('change', previewPhoto);
    }

    function previewPhoto() {
        const file = photoInput.files[0];
        if (!file) return;

        selectedFile = file;
        
        // Show upload button
        if (uploadBtn) {
            uploadBtn.style.display = 'inline-block';
        }

        // Show file preview
        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.getElementById('photo-preview');
            if (preview) {
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            }
        };
        reader.readAsDataURL(file);
    }

    function uploadPhoto() {
        if (!selectedFile) {
            showMessage('Please select a file first.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('entity_type', selectedTab.slice(0, -1)); // Remove 's'
        formData.append('entity_id', selectedEntityId);
        formData.append('photo', selectedFile);
        formData.append('csrf_token', csrfToken);

        fetch('photo_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showMessage(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        })
        .catch(error => {
            showMessage('Upload failed. Please try again.', 'error');
            console.error('Error:', error);
        });
    }

    function deletePhoto() {
        if (!confirm('Are you sure you want to remove this profile photo?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('entity_type', selectedTab.slice(0, -1)); // Remove 's'
        formData.append('entity_id', selectedEntityId);
        formData.append('csrf_token', csrfToken);

        fetch('photo_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showMessage(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        })
        .catch(error => {
            showMessage('Delete failed. Please try again.', 'error');
            console.error('Error:', error);
        });
    }

    function showMessage(message, type) {
        const messageDiv = document.getElementById('message');
        if (!messageDiv) return;

        messageDiv.textContent = message;
        messageDiv.className = 'message ' + type;
        
        setTimeout(() => {
            messageDiv.className = 'message';
        }, 5000);
    }
</script>

</body>
</html>
