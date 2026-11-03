<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    die("Access denied.");
}

$adminId = (int)$_SESSION['user_id'];
$message = "";
$messageType = "";

// HANDLE ACTIONS (UNCHANGED)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId === $adminId) {
        $message = "You cannot modify your own account.";
        $messageType = "error";
    } else {
        if ($action === 'deactivate') {
            $stmt = $conn->prepare("UPDATE login SET is_deactivated = 1 WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $message = "User deactivated.";
            $messageType = "success";
        }

        if ($action === 'reactivate') {
            $stmt = $conn->prepare("UPDATE login SET is_deactivated = 0, attempts = 0 WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $message = "User reactivated.";
            $messageType = "success";
        }
    }
}

// FETCH USERS (UNCHANGED)
$result = $conn->query("
    SELECT id, username, email, role, attempts, is_deactivated
    FROM login
    ORDER BY id DESC
");

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Users</title>

<style>
*{box-sizing:border-box;}

:root{
    --bg1:#eef6ff;
    --bg2:#f8fbff;
    --border:rgba(148,163,184,0.2);
    --text:#0f172a;
    --muted:#64748b;
    --primary:#2563eb;
    --primary2:#06b6d4;
}

body{
    margin:0;
    font-family:"Segoe UI",Arial;
    color:var(--text);
    background:
        radial-gradient(circle at top left, rgba(56,189,248,.15), transparent 30%),
        radial-gradient(circle at top right, rgba(37,99,235,.12), transparent 25%),
        linear-gradient(180deg,var(--bg1),var(--bg2));
}

/* NAVBAR */
.navbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:18px 28px;
    margin:20px;
    border-radius:20px;
    background:rgba(255,255,255,0.7);
    backdrop-filter:blur(16px);
    border:1px solid var(--border);
}

.navbar a{
    text-decoration:none;
    margin-left:12px;
    font-weight:bold;
    color:#1e293b;
}

/* CONTAINER */
.container{
    max-width:1100px;
    margin:auto;
    padding:20px;
}

.card{
    background:rgba(255,255,255,0.75);
    border-radius:28px;
    padding:25px;
    border:1px solid var(--border);
    box-shadow:0 10px 30px rgba(0,0,0,0.08);
}

h2{
    margin-top:0;
}

/* MESSAGE */
.message{
    padding:12px;
    border-radius:10px;
    margin-bottom:15px;
    font-weight:bold;
}

.success{background:#dcfce7;color:#166534;}
.error{background:#fee2e2;color:#991b1b;}

/* TABLE */
.table-wrap{
    overflow:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:14px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
}

th{
    color:#475569;
}

/* BADGES */
.badge{
    padding:6px 10px;
    border-radius:999px;
    font-size:.8rem;
    font-weight:bold;
}

.active{background:#dcfce7;color:#166534;}
.deactivated{background:#fee2e2;color:#991b1b;}
.role{background:#dbeafe;color:#1d4ed8;}

/* BUTTONS */
.btn{
    padding:8px 12px;
    border:none;
    border-radius:10px;
    font-weight:bold;
    cursor:pointer;
}

.btn-red{
    background:#ef4444;
    color:white;
}

.btn-green{
    background:#22c55e;
    color:white;
}

.btn:hover{
    opacity:.9;
}
</style>
</head>

<body>

<div class="navbar">
    <div><strong>Niella's Rent-A-Tool</strong></div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="add_tool.php">Add Tool</a>
        <a href="my_tools.php">My Tools</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="card">

        <h2>Manage Users</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Attempts</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo e($row['id']); ?></td>
                    <td><?php echo e($row['username']); ?></td>
                    <td><?php echo e($row['email']); ?></td>

                    <td>
                        <span class="badge role"><?php echo e($row['role']); ?></span>
                    </td>

                    <td><?php echo e($row['attempts']); ?></td>

                    <td>
                        <?php if ($row['is_deactivated']): ?>
                            <span class="badge deactivated">Deactivated</span>
                        <?php else: ?>
                            <span class="badge active">Active</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">

                            <?php if ($row['is_deactivated']): ?>
                                <button name="action" value="reactivate" class="btn btn-green">
                                    Reactivate
                                </button>
                            <?php else: ?>
                                <button name="action" value="deactivate" class="btn btn-red">
                                    Deactivate
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>

            </table>
        </div>

    </div>
</div>

</body>
</html>