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

// fetch categories
$categories = [];
$catQuery = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
if ($catQuery) {
    while ($row = $catQuery->fetch_assoc()) {
        $categories[] = $row;
    }
}

if (isset($_POST['add_tool'])) {
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $toolName = trim($_POST['tool_name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $conditionStatus = $_POST['condition_status'] ?? 'good';
    $pricePerDay = trim($_POST['price_per_day'] ?? '');
    $depositAmount = trim($_POST['deposit_amount'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $availabilityStatus = $_POST['availability_status'] ?? 'available';
    $location = trim($_POST['location'] ?? '');

    $imageName = null;
    $uploadDir = "uploads/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if ($toolName === "" || $pricePerDay === "" || $depositAmount === "" || $quantity === "" || $location === "") {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif (!is_numeric($pricePerDay) || $pricePerDay < 0) {
        $message = "Invalid price per day.";
        $messageType = "error";
    } elseif (!is_numeric($depositAmount) || $depositAmount < 0) {
        $message = "Invalid deposit amount.";
        $messageType = "error";
    } elseif (!is_numeric($quantity) || $quantity < 1) {
        $message = "Quantity must be at least 1.";
        $messageType = "error";
    } else {
        if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $fileType = mime_content_type($_FILES['image_path']['tmp_name']);

            if (!in_array($fileType, $allowedTypes)) {
                $message = "Only JPG, PNG, and WEBP images are allowed.";
                $messageType = "error";
            } else {
                $ext = pathinfo($_FILES['image_path']['name'], PATHINFO_EXTENSION);
                $imageName = uniqid('tool_', true) . "." . strtolower($ext);
                move_uploaded_file($_FILES['image_path']['tmp_name'], $uploadDir . $imageName);
            }
        }

        if ($message === "") {
            $stmt = $conn->prepare("
                INSERT INTO tools (
                    owner_id,
                    category_id,
                    tool_name,
                    brand,
                    model,
                    description,
                    condition_status,
                    price_per_day,
                    deposit_amount,
                    quantity,
                    availability_status,
                    location,
                    image_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iisssssddisss",
                $ownerId,
                $categoryId,
                $toolName,
                $brand,
                $model,
                $description,
                $conditionStatus,
                $pricePerDay,
                $depositAmount,
                $quantity,
                $availabilityStatus,
                $location,
                $imageName
            );

            if ($stmt->execute()) {
                $message = "Tool added successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to add tool.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Tool</title>

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
    font-family:"Segoe UI", Arial;
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
    max-width:1000px;
    margin:30px auto;
    padding:0 20px;
}

.card{
    background:rgba(255,255,255,0.75);
    border-radius:28px;
    padding:30px;
    border:1px solid var(--border);
    box-shadow:0 10px 30px rgba(0,0,0,0.08);
}

/* HEADER */
.header{
    margin-bottom:20px;
}

.header h2{
    margin:0;
    font-size:2rem;
}

.header p{
    color:var(--muted);
}

/* MESSAGE */
.message{
    padding:12px;
    border-radius:12px;
    margin-bottom:15px;
    font-weight:bold;
}

.success{
    background:#dcfce7;
    color:#166534;
}

.error{
    background:#fee2e2;
    color:#991b1b;
}

/* FORM */
.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}

.full{
    grid-column:1 / -1;
}

label{
    font-weight:600;
    font-size:.9rem;
    margin-bottom:6px;
    display:block;
}

input,select,textarea{
    width:100%;
    padding:12px;
    border-radius:12px;
    border:1px solid var(--border);
    background:white;
}

textarea{
    min-height:100px;
}

/* BUTTON */
button{
    margin-top:10px;
    padding:14px;
    border:none;
    border-radius:14px;
    font-weight:bold;
    color:white;
    background:linear-gradient(135deg,var(--primary),var(--primary2));
    cursor:pointer;
    transition:.2s;
}

button:hover{
    transform:translateY(-2px);
    box-shadow:0 10px 20px rgba(37,99,235,.2);
}

@media(max-width:700px){
    .grid{grid-template-columns:1fr;}
}
</style>
</head>

<body>

<div class="navbar">
    <div><strong>Niella's Rent-A-Tool</strong></div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="my_tools.php">My Tools</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <a href="my_tools.php" style="
    display:inline-block;
    margin-bottom:20px;
    padding:8px 14px;
    border:1px solid #ccc;
    border-radius:6px;
    text-decoration:none;
    color:#000;
    background:#f9f9f9;
">
← Back
</a>
    <div class="card">

        <div class="header">
            <h2>Add Tool</h2>
            <p>Create a new tool listing for rental</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="grid">

                <div>
                    <label>Tool Name</label>
                    <input type="text" name="tool_name" required>
                </div>

                <div>
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">Select</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo $cat['category_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Brand</label>
                    <input type="text" name="brand">
                </div>

                <div>
                    <label>Model</label>
                    <input type="text" name="model">
                </div>

                <div class="full">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>

                <div>
                    <label>Condition</label>
                    <select name="condition_status">
                        <option value="good">Good</option>
                        <option value="used">Used</option>
                        <option value="damaged">Damaged</option>
                    </select>
                </div>

                <div>
                    <label>Availability</label>
                    <select name="availability_status">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>

                <div>
                    <label>Price per Day</label>
                    <input type="number" name="price_per_day" required>
                </div>

                <div>
                    <label>Deposit Amount</label>
                    <input type="number" name="deposit_amount" required>
                </div>

                <div>
                    <label>Quantity</label>
                    <input type="number" name="quantity" required>
                </div>

                <div>
                    <label>Location</label>
                    <input type="text" name="location" required>
                </div>

                <div class="full">
                    <label>Upload Image</label>
                    <input type="file" name="image_path">
                </div>

                <div class="full">
                    <button type="submit" name="add_tool">Add Tool</button>
                </div>

            </div>

        </form>

    </div>
</div>

</body>
</html>