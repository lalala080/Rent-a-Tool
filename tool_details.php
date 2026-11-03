<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'renter';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid tool ID.");
}

$toolId = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT 
        tools.*,
        categories.category_name,
        login.username AS owner_username,
        login.email AS owner_email
    FROM tools
    LEFT JOIN categories ON tools.category_id = categories.id
    LEFT JOIN login ON tools.owner_id = login.id
    WHERE tools.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $toolId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Tool not found.");
}

$tool = $result->fetch_assoc();
$stmt->close();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$canRent = (
    $role === 'renter' &&
    ($tool['availability_status'] ?? '') === 'available' &&
    (int)$tool['owner_id'] !== $userId
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tool Details - Niella's Rent-A-Tool</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .navbar {
            background: #2563eb;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            font-weight: bold;
        }

        .container {
            max-width: 1200px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 0 14px rgba(0,0,0,0.08);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
        }

        .image-wrap {
            background: #eef2f7;
            min-height: 560px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .no-image {
            color: #6b7280;
            font-size: 1rem;
        }

        .details {
            padding: 34px;
        }

        .tool-title {
            font-size: 2.2rem;
            font-weight: bold;
            color: #111827;
            margin-bottom: 8px;
        }

        .category {
            display: inline-block;
            margin-bottom: 18px;
            background: #e0f2fe;
            color: #0369a1;
            padding: 8px 14px;
            border-radius: 999px;
            font-weight: bold;
            font-size: 0.92rem;
        }

        .info {
            font-size: 1rem;
            color: #374151;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .info strong {
            color: #1f2937;
        }

        .status {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 0.95rem;
            font-weight: bold;
            margin: 12px 0 20px;
        }

        .available {
            background: #dcfce7;
            color: #166534;
        }

        .unavailable {
            background: #fee2e2;
            color: #991b1b;
        }

        .maintenance {
            background: #fef3c7;
            color: #92400e;
        }

        .price-box {
            margin: 20px 0 24px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px;
        }

        .price-line {
            font-size: 1.05rem;
            color: #111827;
            margin-bottom: 12px;
        }

        .price-line:last-child {
            margin-bottom: 0;
        }

        .desc {
            margin-top: 8px;
            color: #374151;
            font-size: 1rem;
            line-height: 1.8;
        }

        .actions {
            margin-top: 28px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            padding: 14px 22px;
            border-radius: 14px;
            font-weight: bold;
            font-size: 1rem;
            transition: 0.2s ease;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        @media (max-width: 950px) {
            .card {
                grid-template-columns: 1fr;
            }

            .image-wrap {
                min-height: 360px;
            }

            .details {
                padding: 24px;
            }

            .tool-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>Niella's Rent-A-Tool</strong></div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="browse_tools.php">Browse Tools</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
     <a href="browse_tools.php" style="
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
        <div class="image-wrap">
            <?php if (!empty($tool['image_path'])): ?>
                <img src="uploads/<?php echo e($tool['image_path']); ?>" alt="Tool Image">
            <?php else: ?>
                <div class="no-image">No Image Available</div>
            <?php endif; ?>
        </div>

        <div class="details">
            <div class="tool-title"><?php echo e($tool['tool_name']); ?></div>

            <div class="category">
                <?php echo e($tool['category_name'] ?? 'Uncategorized'); ?>
            </div>

            <div class="info"><strong>Owner:</strong> <?php echo e($tool['owner_username']); ?></div>
            <div class="info"><strong>Owner Email:</strong> <?php echo e($tool['owner_email']); ?></div>
            <div class="info"><strong>Brand:</strong> <?php echo e($tool['brand'] ?: 'N/A'); ?></div>
            <div class="info"><strong>Model:</strong> <?php echo e($tool['model'] ?: 'N/A'); ?></div>
            <div class="info"><strong>Condition:</strong> <?php echo e($tool['condition_status']); ?></div>
            <div class="info"><strong>Quantity:</strong> <?php echo (int)$tool['quantity']; ?></div>
            <div class="info"><strong>Location:</strong> <?php echo e($tool['location']); ?></div>

            <span class="status <?php echo e($tool['availability_status']); ?>">
                <?php echo ucfirst(e($tool['availability_status'])); ?>
            </span>

            <div class="price-box">
                <div class="price-line"><strong>Price Per Day:</strong> ₱<?php echo number_format((float)$tool['price_per_day'], 2); ?></div>
                <div class="price-line"><strong>Deposit Amount:</strong> ₱<?php echo number_format((float)$tool['deposit_amount'], 2); ?></div>
            </div>

            <div class="desc">
                <strong>Description:</strong><br>
                <?php echo nl2br(e($tool['description'] ?: 'No description available.')); ?>
            </div>

            <div class="actions">
                <?php if ($canRent): ?>
                    <a href="rent_tool.php?id=<?php echo (int)$tool['id']; ?>" class="btn btn-primary">Rent This Tool</a>
                <?php endif; ?>

                <a href="browse_tools.php" class="btn btn-secondary">Back to Browse</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>