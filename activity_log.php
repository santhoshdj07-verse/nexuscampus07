<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";
require_once "config/schema.php";

$adminName = $_SESSION["admin_name"] ?? "Administrator";
$currentDate = date("l, d F Y");

$logs = array();

$stmt = $conn->prepare(
    "SELECT *
     FROM activity_log
     ORDER BY created_at DESC
     LIMIT 100"
);

if ($stmt)
{
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc())
    {
        $logs[] = $row;
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log | Smart Campus</title>
    <style>
        * { box-sizing: border-box; }
        :root {
            --bg: #070b17;
            --panel: rgba(13,19,36,0.9);
            --border: rgba(148,163,184,0.14);
            --text: #f8fafc;
            --muted: #94a3b8;
            --blue: #3b82f6;
            --cyan: #06b6d4;
            --green: #22c55e;
            --red: #ef4444;
            --yellow: #f59e0b;
        }
        body {
            margin: 0;
            font-family: Inter, Segoe UI, Arial, sans-serif;
            background: radial-gradient(circle at 10% 10%, rgba(59,130,246,0.12), transparent 30%), radial-gradient(circle at 90% 15%, rgba(139,92,246,0.11), transparent 30%), #070b17;
            color: var(--text);
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 32px 24px 50px; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        .title { font-size: 30px; letter-spacing: -0.8px; margin: 0; }
        .subtitle { color: var(--muted); font-size: 12px; letter-spacing: 0.18em; text-transform: uppercase; }
        .top-actions { display: flex; gap: 10px; align-items: center; }
        .button {
            text-decoration: none;
            color: #dbeafe;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 700;
        }
        .button.primary {
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            color: white;
            border: none;
        }
        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
        }
        .panel-header h3 { margin: 0; font-size: 15px; }
        .panel-header span { color: #64748b; font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase; }
        .log-list { padding: 10px 0; }
        .log-item {
            display: grid;
            grid-template-columns: 56px 1fr auto;
            gap: 14px;
            align-items: start;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(148,163,184,0.06);
        }
        .log-item:last-child { border-bottom: none; }
        .dot {
            width: 10px;
            height: 10px;
            margin-top: 6px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            box-shadow: 0 0 12px rgba(59,130,246,0.55);
        }
        .log-title {
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 4px;
        }
        .log-desc {
            color: #cbd5e1;
            font-size: 12px;
            line-height: 1.7;
        }
        .meta {
            text-align: right;
            color: #94a3b8;
            font-size: 10px;
            padding-top: 3px;
            min-width: 120px;
        }
        .badge {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 5px 8px;
            border-radius: 999px;
            background: rgba(59,130,246,0.10);
            color: #93c5fd;
            border: 1px solid rgba(59,130,246,0.18);
        }
        @media (max-width: 700px) {
            .log-item { grid-template-columns: 30px 1fr; }
            .meta { grid-column: 2; text-align: left; }
            .header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <div class="subtitle">Admin trace</div>
                <h1 class="title">Activity Log</h1>
            </div>
            <div class="top-actions">
                <a class="button" href="dashboard.php">← Dashboard</a>
                <a class="button primary" href="reports.php">Reports</a>
            </div>
        </div>

        <section class="panel">
            <div class="panel-header">
                <h3>System Audit Trail</h3>
                <span><?php echo htmlspecialchars($currentDate); ?></span>
            </div>
            <div class="log-list">
                <?php if (empty($logs)): ?>
                    <div style="padding: 20px; color: #94a3b8; font-size: 12px;">No activity recorded yet.</div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="log-item">
                            <div class="dot"></div>
                            <div>
                                <div class="log-title"><?php echo htmlspecialchars($log["title"] ?? "System Action"); ?></div>
                                <div class="log-desc"><?php echo htmlspecialchars($log["description"] ?? ""); ?></div>
                            </div>
                            <div class="meta">
                                <div class="badge"><?php echo htmlspecialchars($log["action_type"] ?? "system"); ?></div><br>
                                <?php echo htmlspecialchars(date("d M Y H:i", strtotime($log["created_at"]))); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</body>
</html>
