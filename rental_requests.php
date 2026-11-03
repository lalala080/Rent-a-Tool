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

$ownerId = (int)$_SESSION['user_id'];
$message = "";
$messageType = "";

// HANDLE ACTIONS (UNCHANGED)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rentalId = (int)($_POST['rental_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE rentals SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $rentalId);
        $stmt->execute();
        $message = "Rental approved.";
        $messageType = "success";
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE rentals SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $rentalId);
        $stmt->execute();
        $message = "Rental rejected.";
        $messageType = "error";
    }
}

// FETCH REQUESTS (UNCHANGED)
$stmt = $conn->prepare("
    SELECT rentals.*, tools.tool_name, login.username
    FROM rentals
    JOIN tools ON rentals.tool_id = tools.id
    JOIN login ON rentals.renter_id = login.id
    WHERE tools.owner_id = ?
    ORDER BY rentals.id DESC
");
$stmt->bind_param("i", $ownerId);
$stmt->execute();
$result = $stmt->get_result();

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Rental Requests</title>

<style>
*{box-sizing:border-box;}

:root{
    --bg1:#eef6ff;
    --bg2:#f8fbff;
    --border:rgba(148,163,184,0.2);
    --text:#0f172a;
    --muted:#64748b;
}

body{
    margin:0;
    font-family:"Segoe UI",Arial;
    background:
        radial-gradient(circle at top left, rgba(56,189,248,.15), transparent 30%),
        linear-gradient(180deg,var(--bg1),var(--bg2));
}

/* NAVBAR */
.navbar{
    display:flex;
    justify-content:space-between;
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
}

/* REQUEST CARD */
.req{
    background:white;
    border-radius:20px;
    padding:20px;
    margin-bottom:16px;
    border:1px solid #e5e7eb;
}

.title{
    font-weight:bold;
    font-size:1.2rem;
}

.meta{
    color:var(--muted);
    margin-top:4px;
}

/* BADGES */
.badge{
    display:inline-block;
    margin-top:8px;
    padding:6px 10px;
    border-radius:999px;
    font-size:.8rem;
    font-weight:bold;
}

.pending{background:#fff7d6;color:#9a6700;}
.approved{background:#dcfce7;color:#166534;}
.rejected{background:#fee2e2;color:#991b1b;}

/* DETAILS */
.details{
    margin-top:10px;
    font-size:.9rem;
}

/* ACTIONS */
.actions{
    margin-top:12px;
    display:flex;
    gap:10px;
}

.btn{
    padding:8px 12px;
    border:none;
    border-radius:10px;
    font-weight:bold;
    cursor:pointer;
    color:white;
}

.approve{background:#22c55e;}
.reject{background:#ef4444;}
</style>
</head>

<body>

<div class="navbar">
    <div><strong>Niella's Rent-A-Tool</strong></div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="my_tools.php">My Tools</a>
        <a href="rental_requests.php">Requests</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="card">

        <h2>Rental Requests</h2>

        <?php if ($message): ?>
            <div class="badge <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php while ($row = $result->fetch_assoc()): ?>

        <div class="req">

            <div class="title"><?php echo e($row['tool_name']); ?></div>
            <div class="meta">Requested by <?php echo e($row['username']); ?></div>

            <div class="badge <?php echo e($row['status']); ?>">
                <?php echo e($row['status']); ?>
            </div>

            <div class="details">
                <div><b>Days:</b> <?php echo e($row['total_days']); ?></div>
                <div><b>Start:</b> <?php echo e($row['start_date']); ?></div>
                <div><b>End:</b> <?php echo e($row['end_date']); ?></div>
            </div>

            <?php if ($row['status'] === 'pending'): ?>
            <div class="actions">
                <form method="POST">
                    <input type="hidden" name="rental_id" value="<?php echo $row['id']; ?>">
                    <button name="action" value="approve" class="btn approve">Approve</button>
                </form>

                <form method="POST">
                    <input type="hidden" name="rental_id" value="<?php echo $row['id']; ?>">
                    <button name="action" value="reject" class="btn reject">Reject</button>
                </form>
            </div>
            <?php endif; ?>

        </div>

        <?php endwhile; ?>

    </div>
</div>

</body>
</html>