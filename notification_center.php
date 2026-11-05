<?php

session_start();

if (!isset($_SESSION["student_id"]))
{
    header("Location: login.php?role=student");
    exit();
}

require_once "config/database.php";

$studentId = $_SESSION["student_id"];
$message = "";
$messageType = "";

/* Get student info */
$stmt = $conn->prepare(
    "SELECT id, roll_no, name FROM students WHERE id = ? LIMIT 1"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->num_rows > 0 ? $result->fetch_assoc() : null;
$stmt->close();

if (!$student)
{
    session_destroy();
    header("Location: login.php?role=student");
    exit();
}

/* Handle mark as read */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["notification_id"]))
{
    $notificationId = (int)$_POST["notification_id"];
    
    $stmt = $conn->prepare(
        "UPDATE student_notifications 
         SET is_read = 1 
         WHERE id = ? AND student_id = ?"
    );
    $stmt->bind_param("ii", $notificationId, $studentId);
    $stmt->execute();
    $stmt->close();
}

/* Handle mark all as read */
if (isset($_POST["mark_all_read"]))
{
    $stmt = $conn->prepare(
        "UPDATE student_notifications 
         SET is_read = 1 
         WHERE student_id = ? AND is_read = 0"
    );
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $stmt->close();
}

/* Get filter parameter */
$filter = isset($_GET["filter"]) ? trim($_GET["filter"]) : "all";
$validFilters = array("all", "unread", "application", "document", "admission");
if (!in_array($filter, $validFilters)) $filter = "all";

/* Build query based on filter */
$whereClause = "WHERE student_id = ?";

if ($filter === "unread")
{
    $whereClause .= " AND is_read = 0";
}
elseif ($filter === "application")
{
    $whereClause .= " AND notification_type LIKE 'APPLICATION%'";
}
elseif ($filter === "document")
{
    $whereClause .= " AND notification_type LIKE 'DOCUMENT%'";
}
elseif ($filter === "admission")
{
    $whereClause .= " AND notification_type LIKE 'ADMISSION%'";
}

/* Get notifications */
$notifications = array();
$stmt = $conn->prepare(
    "SELECT * FROM student_notifications 
     " . $whereClause . "
     ORDER BY created_at DESC 
     LIMIT 100"
);

if ($filter !== "all" && $filter !== "unread")
{
    $stmt->bind_param("i", $studentId);
}
else
{
    $stmt->bind_param("i", $studentId);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc())
{
    $notifications[] = $row;
}

$stmt->close();

/* Count unread */
$stmt = $conn->prepare(
    "SELECT COUNT(*) as count FROM student_notifications 
     WHERE student_id = ? AND is_read = 0"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$unreadCount = $result->fetch_assoc()["count"];
$stmt->close();

/* Get notification statistics */
$stmt = $conn->prepare(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN notification_type LIKE 'APPLICATION%' THEN 1 ELSE 0 END) as app_count,
        SUM(CASE WHEN notification_type LIKE 'DOCUMENT%' THEN 1 ELSE 0 END) as doc_count,
        SUM(CASE WHEN notification_type LIKE 'ADMISSION%' THEN 1 ELSE 0 END) as adm_count
     FROM student_notifications WHERE student_id = ?"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

function getNotificationIcon($type)
{
    $type = strtoupper($type);
    
    if (strpos($type, "ACCEPTED") !== false) return "✓";
    if (strpos($type, "REJECTED") !== false) return "✗";
    if (strpos($type, "VERIFIED") !== false) return "✓";
    if (strpos($type, "APPLICATION") !== false) return "📋";
    if (strpos($type, "DOCUMENT") !== false) return "📄";
    if (strpos($type, "ADMISSION") !== false) return "🎓";
    
    return "•";
}

function getNotificationColor($type)
{
    $type = strtoupper($type);
    
    if (strpos($type, "ACCEPTED") !== false) return "green";
    if (strpos($type, "VERIFIED") !== false) return "green";
    if (strpos($type, "REJECTED") !== false) return "red";
    if (strpos($type, "APPLICATION") !== false) return "blue";
    if (strpos($type, "DOCUMENT") !== false) return "cyan";
    if (strpos($type, "ADMISSION") !== false) return "purple";
    
    return "gray";
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Notification Center - Smart Campus</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>

* {
    box-sizing: border-box;
}

:root {
    --bg: #070b17;
    --panel: rgba(13,19,36,.90);
    --border: rgba(148,163,184,.14);
    --text: #f8fafc;
    --muted: #94a3b8;
    --blue: #3b82f6;
    --cyan: #06b6d4;
    --purple: #8b5cf6;
    --green: #22c55e;
    --red: #ef4444;
}

body {
    margin: 0;
    font-family: Inter, Segoe UI, Arial, sans-serif;
    background: radial-gradient(circle at 10% 10%, rgba(59,130,246,.13), transparent 30%),
                radial-gradient(circle at 90% 15%, rgba(139,92,246,.12), transparent 30%),
                #070b17;
    color: var(--text);
    min-height: 100vh;
    padding: 30px 20px;
}

.container {
    max-width: 900px;
    margin: 0 auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.header h1 {
    margin: 0;
    font-size: 26px;
}

.header-subtitle {
    color: var(--muted);
    font-size: 12px;
    margin-top: 4px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 8px 14px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,.05);
    color: var(--text);
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}

.btn:hover {
    background: rgba(59,130,246,.1);
    border-color: rgba(59,130,246,.3);
}

.btn-primary {
    background: rgba(59,130,246,.15);
    border-color: rgba(59,130,246,.3);
    color: var(--blue);
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 25px;
}

.stat-card {
    padding: 16px;
    border-radius: 10px;
    background: var(--panel);
    border: 1px solid var(--border);
    text-align: center;
}

.stat-card .number {
    font-size: 20px;
    font-weight: 700;
    color: var(--blue);
    margin-bottom: 6px;
}

.stat-card .label {
    font-size: 11px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.filters {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 7px 12px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.filter-btn:hover {
    color: var(--blue);
    border-color: rgba(59,130,246,.3);
}

.filter-btn.active {
    background: rgba(59,130,246,.15);
    color: var(--blue);
    border-color: rgba(59,130,246,.3);
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.notification-item {
    padding: 16px;
    border-radius: 10px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-left: 4px solid;
    display: flex;
    gap: 14px;
    align-items: flex-start;
    cursor: pointer;
    transition: all .2s;
}

.notification-item:hover {
    background: rgba(255,255,255,.02);
}

.notification-item.unread {
    background: rgba(59,130,246,.08);
    border-left-color: var(--blue);
}

.notification-item.green { border-left-color: var(--green); }
.notification-item.red { border-left-color: var(--red); }
.notification-item.blue { border-left-color: var(--blue); }
.notification-item.cyan { border-left-color: var(--cyan); }
.notification-item.purple { border-left-color: var(--purple); }
.notification-item.gray { border-left-color: var(--muted); }

.notification-icon {
    font-size: 20px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: rgba(59,130,246,.1);
}

.notification-item.green .notification-icon {
    background: rgba(34,197,94,.1);
    color: var(--green);
}

.notification-item.red .notification-icon {
    background: rgba(239,68,68,.1);
    color: var(--red);
}

.notification-item.cyan .notification-icon {
    background: rgba(6,182,212,.1);
    color: var(--cyan);
}

.notification-item.purple .notification-icon {
    background: rgba(139,92,246,.1);
    color: var(--purple);
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin: 0 0 6px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.notification-title .unread-badge {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--blue);
}

.notification-message {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.5;
    margin: 0;
}

.notification-time {
    font-size: 10px;
    color: var(--muted);
    margin-top: 8px;
}

.notification-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.action-btn {
    padding: 5px 8px;
    border-radius: 4px;
    border: none;
    background: rgba(59,130,246,.1);
    color: var(--blue);
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
}

.action-btn:hover {
    background: rgba(59,130,246,.2);
}

.empty-state {
    text-align: center;
    padding: 60px 30px;
    color: var(--muted);
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

.empty-state-text {
    font-size: 14px;
    margin-bottom: 8px;
}

.empty-state-subtext {
    font-size: 12px;
    color: var(--muted);
}

.back-button {
    display: inline-block;
    text-decoration: none;
    color: var(--blue);
    font-size: 12px;
    margin-top: 20px;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid rgba(59,130,246,.3);
    transition: all .2s;
}

.back-button:hover {
    background: rgba(59,130,246,.1);
    border-color: rgba(59,130,246,.5);
}

@media (max-width: 700px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .notification-item {
        flex-direction: column;
    }
    
    .filters {
        overflow-x: auto;
    }
}

</style>
</head>
<body>

<div class="container">

    <div class="header">
        <div>
            <h1>🔔 Notification Center</h1>
            <div class="header-subtitle"><?php echo htmlspecialchars($student["name"]); ?> (<?php echo htmlspecialchars($student["roll_no"]); ?>)</div>
        </div>
        <div class="header-actions">
            <?php if ($unreadCount > 0): ?>
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_all_read" class="btn btn-primary">
                    Mark All as Read
                </button>
            </form>
            <?php endif; ?>
            <a href="student_dashboard.php" class="btn">Back to Dashboard</a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats">
        <div class="stat-card">
            <div class="number"><?php echo $stats["total"]; ?></div>
            <div class="label">Total Notifications</div>
        </div>
        <div class="stat-card">
            <div class="number" style="color: var(--blue);"><?php echo $stats["app_count"]; ?></div>
            <div class="label">Application</div>
        </div>
        <div class="stat-card">
            <div class="number" style="color: var(--cyan);"><?php echo $stats["doc_count"]; ?></div>
            <div class="label">Document</div>
        </div>
        <div class="stat-card">
            <div class="number" style="color: var(--purple);"><?php echo $unreadCount; ?></div>
            <div class="label">Unread</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters">
        <a href="?filter=all" class="filter-btn <?php echo $filter === "all" ? "active" : ""; ?>">
            All Notifications
        </a>
        <a href="?filter=unread" class="filter-btn <?php echo $filter === "unread" ? "active" : ""; ?>">
            Unread
        </a>
        <a href="?filter=application" class="filter-btn <?php echo $filter === "application" ? "active" : ""; ?>">
            Application
        </a>
        <a href="?filter=document" class="filter-btn <?php echo $filter === "document" ? "active" : ""; ?>">
            Document
        </a>
        <a href="?filter=admission" class="filter-btn <?php echo $filter === "admission" ? "active" : ""; ?>">
            Admission
        </a>
    </div>

    <!-- Notifications List -->
    <?php if (count($notifications) > 0): ?>

    <div class="notifications-list">
        
        <?php foreach ($notifications as $notif): 
            $color = getNotificationColor($notif["notification_type"]);
        ?>

        <div class="notification-item <?php echo $color; ?> <?php echo !$notif["is_read"] ? "unread" : ""; ?>">
            
            <div class="notification-icon">
                <?php echo getNotificationIcon($notif["notification_type"]); ?>
            </div>

            <div class="notification-content">
                
                <h3 class="notification-title">
                    <?php if (!$notif["is_read"]): ?>
                    <span class="unread-badge"></span>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($notif["title"]); ?>
                </h3>

                <p class="notification-message">
                    <?php echo htmlspecialchars($notif["message"]); ?>
                </p>

                <div class="notification-time">
                    <?php 
                    $createdTime = strtotime($notif["created_at"]);
                    $now = time();
                    $diff = $now - $createdTime;
                    
                    if ($diff < 60) {
                        echo "Just now";
                    } elseif ($diff < 3600) {
                        echo floor($diff / 60) . " minutes ago";
                    } elseif ($diff < 86400) {
                        echo floor($diff / 3600) . " hours ago";
                    } elseif ($diff < 604800) {
                        echo floor($diff / 86400) . " days ago";
                    } else {
                        echo date("M d, Y", $createdTime);
                    }
                    ?>
                </div>
            </div>

            <?php if (!$notif["is_read"]): ?>
            <div class="notification-actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="notification_id" value="<?php echo $notif["id"]; ?>">
                    <button type="submit" class="action-btn">Mark Read</button>
                </form>
            </div>
            <?php endif; ?>

        </div>

        <?php endforeach; ?>

    </div>

    <?php else: ?>

    <div class="empty-state">
        <div class="empty-state-icon">📭</div>
        <div class="empty-state-text">No Notifications</div>
        <div class="empty-state-subtext">You're all caught up! Check back later.</div>
    </div>

    <?php endif; ?>

    <a href="student_dashboard.php" class="back-button">
        ← Back to Dashboard
    </a>

</div>

</body>
</html>
