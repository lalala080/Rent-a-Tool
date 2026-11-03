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

// FETCH TOOLS (UNCHANGED LOGIC)
$stmt = $conn->prepare("
    SELECT *
    FROM tools
    WHERE owner_id = ?
    ORDER BY id DESC
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
<title>My Tools</title>

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
    max-width:1200px;
    margin:auto;
    padding:20px;
}

.card{
    background:rgba(255,255,255,0.75);
    border-radius:28px;
    padding:25px;
    border:1px solid var(--border);
}

/* GRID */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:20px;
}

/* TOOL CARD */
.tool{
    background:white;
    border-radius:20px;
    overflow:hidden;
    border:1px solid #e5e7eb;
    transition:.25s;
}

.tool:hover{
    transform:translateY(-5px);
}

/* IMAGE */
.img{
    height:200px;
    background:#e5e7eb;
}

.img img{
    width:100%;
    height:100%;
    object-fit:cover;
}

/* BODY */
.body{
    padding:16px;
}

.title{
    font-weight:bold;
    font-size:1.2rem;
}

.location{
    color:var(--muted);
    font-size:.9rem;
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

.available{background:#dcfce7;color:#166534;}
.unavailable{background:#fee2e2;color:#991b1b;}

/* BUTTONS */
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
    text-decoration:none;
    color:white;
}

.edit{background:#2563eb;}
.delete{background:#ef4444;}
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

        <h2>My Tools</h2>

        <div class="grid">

        <?php while ($tool = $result->fetch_assoc()): ?>

            <div class="tool">

                <div class="img">
                    <?php if (!empty($tool['image_path'])): ?>
                        <img src="uploads/<?php echo e($tool['image_path']); ?>">
                    <?php endif; ?>
                </div>

                <div class="body">

                    <div class="title"><?php echo e($tool['tool_name']); ?></div>
                    <div class="location"><?php echo e($tool['location']); ?></div>

                    <div class="badge <?php echo $tool['availability_status']; ?>">
                        <?php echo e($tool['availability_status']); ?>
                    </div>

                    <div class="actions">
                        <a href="edit_tool.php?id=<?php echo $tool['id']; ?>" class="btn edit">Edit</a>
                        <a href="delete_tool.php?id=<?php echo $tool['id']; ?>" class="btn delete">Delete</a>
                    </div>

                </div>

            </div>

        <?php endwhile; ?>

        </div>

    </div>
</div>

</body>
</html>