<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'renter';

// Mark all as read when page is opened
$markRead = $conn->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE user_id = ? AND is_read = 0
");
$markRead->bind_param("i", $userId);
$markRead->execute();
$markRead->close();

// Fetch notifications
$stmt = $conn->prepare("
    SELECT id, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Niella's Rent-A-Tool</title>
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --bg-1: #eef6ff;
            --bg-2: #f8fbff;
            --border: rgba(148, 163, 184, 0.20);
            --text: #0f172a;
            --muted: #5b6b82;
            --primary: #2563eb;
            --primary-2: #06b6d4;
            --shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.08);
            --shadow: 0 20px 50px rgba(37, 99, 235, 0.10);
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.18), transparent 28%),
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.16), transparent 24%),
                linear-gradient(180deg, var(--bg-1) 0%, var(--bg-2) 55%, #f7fbff 100%);
            min-height: 100vh;
        }

        .page-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            margin-bottom: 24px;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow-soft);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.22);
        }

        .brand-text small {
            display: block;
            color: #64748b;
            font-size: 0.78rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .brand-text strong {
            font-size: 1.5rem;
            line-height: 1.1;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #1e293b;
            font-weight: 700;
            padding: 11px 16px;
            border-radius: 14px;
            transition: 0.25s ease;
            background: rgba(255, 255, 255, 0.62);
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #0f172a;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.12), rgba(6, 182, 212, 0.12));
            border-color: rgba(37, 99, 235, 0.20);
            transform: translateY(-1px);
        }

        .section-card {
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 30px;
            box-shadow: var(--shadow-soft);
        }

        .section-header {
            margin-bottom: 24px;
        }

        .section-header h2 {
            margin: 0 0 8px;
            font-size: 2rem;
        }

        .section-header p {
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
        }

        .notif-list {
            display: grid;
            gap: 18px;
        }

        .notif-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(255,255,255,0.94), rgba(248,250,252,0.96));
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 24px;
            padding: 22px 22px 20px;
            box-shadow: 0 16px 35px rgba(15, 23, 42, 0.07);
        }

        .notif-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(180deg, #2563eb, #06b6d4);
        }

        .notif-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 12px;
        }

        .notif-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.80rem;
            font-weight: 800;
            color: #1d4ed8;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.10), rgba(6, 182, 212, 0.10));
            border: 1px solid rgba(37, 99, 235, 0.16);
        }

        .notif-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: linear-gradient(135deg, #dbeafe, #cffafe);
            color: #2563eb;
            border: 1px solid rgba(148, 163, 184, 0.16);
        }

        .notif-body {
            display: flex;
            gap: 14px;
        }

        .notif-content {
            flex: 1;
        }

        .notif-message {
            color: #0f172a;
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 12px;
            word-break: break-word;
        }

        .notif-date {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 12px;
            font-size: 0.88rem;
            color: #64748b;
            background: rgba(248, 250, 252, 0.95);
            border: 1px solid rgba(226, 232, 240, 0.95);
        }

        .empty {
            padding: 46px 24px;
            text-align: center;
            background: rgba(255, 255, 255, 0.82);
            border-radius: 26px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            color: #64748b;
            box-shadow: var(--shadow-soft);
        }

        .empty-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #2563eb;
            background: linear-gradient(135deg, #dbeafe, #cffafe);
            border: 1px solid rgba(148, 163, 184, 0.16);
        }

        .empty h3 {
            margin: 0 0 10px;
            color: #0f172a;
            font-size: 1.5rem;
        }

        .empty p {
            margin: 0;
            font-size: 1rem;
        }
    </style>
</head>
<body>

<div class="page-shell">

    <div class="navbar">
        <div class="brand">
            <div class="brand-icon">🧰</div>
            <div class="brand-text">
                <small>Tool Rental Platform</small>
                <strong>Niella's Rent-A-Tool</strong>
            </div>
        </div>

        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <?php if ($role === 'renter'): ?>
                <a href="my_rentals.php">My Rentals</a>
            <?php else: ?>
                <a href="rental_requests.php">Rental Requests</a>
            <?php endif; ?>
            <a href="notifications.php" class="active">Notifications</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="section-card">

        <!-- BACK BUTTON -->
        <a href="dashboard.php" style="
            display:inline-block;
            margin-bottom:20px;
            padding:8px 14px;
            border:1px solid #ccc;
            border-radius:6px;
            text-decoration:none;
            color:#000;
            background:#f9f9f9;
        ">← Back</a>

        <div class="section-header">
            <h2>Notifications</h2>
            <p>Stay updated with your latest rental activity, approvals, payment updates, and account alerts.</p>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="notif-list">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="notif-card">
                        <div class="notif-top">
                            <div class="notif-badge">Recent Update</div>
                        </div>

                        <div class="notif-body">
                            <div class="notif-icon">🔔</div>

                            <div class="notif-content">
                                <div class="notif-message"><?php echo e($row['message']); ?></div>
                                <div class="notif-date"><?php echo e($row['created_at']); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <div class="empty-icon">🔔</div>
                <h3>No notifications yet</h3>
                <p>You don’t have any updates right now. New alerts will appear here once there’s activity on your account.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>