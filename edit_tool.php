<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$ownerId = $_SESSION['user_id'];
$message = "";
$messageType = "";

// check tool id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid tool ID.");
}

$toolId = (int) $_GET['id'];

// fetch tool
$stmt = $conn->prepare("SELECT * FROM tools WHERE id = ? AND owner_id = ?");
$stmt->bind_param("ii", $toolId, $ownerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Tool not found or access denied.");
}

$tool = $result->fetch_assoc();
$stmt->close();

// fetch categories
$categories = [];
$res = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

// update tool
if (isset($_POST['update_tool'])) {
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

    $imageName = $tool['image_path'];

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
        // upload new image
        if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $fileType = mime_content_type($_FILES['image_path']['tmp_name']);

            if (!in_array($fileType, $allowedTypes)) {
                $message = "Only JPG, PNG, and WEBP images are allowed.";
                $messageType = "error";
            } else {
                $uploadDir = "uploads/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $ext = pathinfo($_FILES['image_path']['name'], PATHINFO_EXTENSION);
                $imageName = uniqid('tool_', true) . "." . strtolower($ext);

                if (!move_uploaded_file($_FILES['image_path']['tmp_name'], $uploadDir . $imageName)) {
                    $message = "Failed to upload image.";
                    $messageType = "error";
                }
            }
        }

        if ($message === "") {
            $stmt = $conn->prepare("
                UPDATE tools SET
                    category_id=?,
                    tool_name=?,
                    brand=?,
                    model=?,
                    description=?,
                    condition_status=?,
                    price_per_day=?,
                    deposit_amount=?,
                    quantity=?,
                    availability_status=?,
                    location=?,
                    image_path=?
                WHERE id=? AND owner_id=?
            ");

            $stmt->bind_param(
                "isssssddisssii",
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
                $imageName,
                $toolId,
                $ownerId
            );

            if ($stmt->execute()) {
                $message = "Tool updated successfully.";
                $messageType = "success";

                // refresh tool data
                $reload = $conn->prepare("SELECT * FROM tools WHERE id = ? AND owner_id = ?");
                $reload->bind_param("ii", $toolId, $ownerId);
                $reload->execute();
                $tool = $reload->get_result()->fetch_assoc();
                $reload->close();
            } else {
                $message = "Update failed.";
                $messageType = "error";
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Tool</title>
    <style>
        body { font-family: Arial; background:#f4f6f9; margin:0; }
        .container { max-width:900px; margin:30px auto; background:white; padding:25px; border-radius:15px; }
        input, select, textarea { width:100%; padding:10px; margin:8px 0; box-sizing:border-box; }
        button { padding:12px; background:#2563eb; color:white; border:none; border-radius:8px; cursor:pointer; }
        .success { background:#dcfce7; padding:10px; margin-bottom:12px; }
        .error { background:#fee2e2; padding:10px; margin-bottom:12px; }
    </style>
</head>
<body>

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
    <h2>Edit Tool</h2>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label>Tool Name</label>
        <input type="text" name="tool_name" value="<?php echo htmlspecialchars($tool['tool_name'] ?? ''); ?>" required>

        <label>Category</label>
        <select name="category_id">
            <option value="">Select category</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int)$cat['id']; ?>"
                    <?php if ((string)$tool['category_id'] === (string)$cat['id']) echo "selected"; ?>>
                    <?php echo htmlspecialchars($cat['category_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Brand</label>
        <input type="text" name="brand" value="<?php echo htmlspecialchars($tool['brand'] ?? ''); ?>">

        <label>Model</label>
        <input type="text" name="model" value="<?php echo htmlspecialchars($tool['model'] ?? ''); ?>">

        <label>Description</label>
        <textarea name="description"><?php echo htmlspecialchars($tool['description'] ?? ''); ?></textarea>

        <label>Condition</label>
        <select name="condition_status">
            <option value="new" <?php if(($tool['condition_status'] ?? '')==="new") echo "selected"; ?>>New</option>
            <option value="good" <?php if(($tool['condition_status'] ?? '')==="good") echo "selected"; ?>>Good</option>
            <option value="fair" <?php if(($tool['condition_status'] ?? '')==="fair") echo "selected"; ?>>Fair</option>
            <option value="damaged" <?php if(($tool['condition_status'] ?? '')==="damaged") echo "selected"; ?>>Damaged</option>
        </select>

        <label>Price Per Day</label>
        <input type="number" step="0.01" min="0" name="price_per_day" value="<?php echo htmlspecialchars($tool['price_per_day'] ?? '0'); ?>" required>

        <label>Deposit</label>
        <input type="number" step="0.01" min="0" name="deposit_amount" value="<?php echo htmlspecialchars($tool['deposit_amount'] ?? '0'); ?>" required>

        <label>Quantity</label>
        <input type="number" min="1" name="quantity" value="<?php echo htmlspecialchars($tool['quantity'] ?? '1'); ?>" required>

        <label>Status</label>
        <select name="availability_status">
            <option value="available" <?php if(($tool['availability_status'] ?? '')==="available") echo "selected"; ?>>Available</option>
            <option value="unavailable" <?php if(($tool['availability_status'] ?? '')==="unavailable") echo "selected"; ?>>Unavailable</option>
            <option value="maintenance" <?php if(($tool['availability_status'] ?? '')==="maintenance") echo "selected"; ?>>Maintenance</option>
        </select>

        <label>Location</label>
        <input type="text" name="location" value="<?php echo htmlspecialchars($tool['location'] ?? ''); ?>" required>

        <label>Change Image</label>
        <input type="file" name="image_path" accept=".jpg,.jpeg,.png,.webp,image/*">

        <button type="submit" name="update_tool">Update Tool</button>
    </form>
</div>

</body>
</html>