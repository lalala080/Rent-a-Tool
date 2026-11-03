<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['role'] ?? 'renter') !== 'renter') {
    die("Only renters can request rentals.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid tool ID.");
}

$toolId = (int) $_GET['id'];
$renterId = (int) $_SESSION['user_id'];
$message = "";
$messageType = "";

// Get tool details
$stmt = $conn->prepare("
    SELECT 
        tools.*,
        categories.category_name,
        login.username AS owner_username
    FROM tools
    LEFT JOIN categories ON tools.category_id = categories.id
    LEFT JOIN login ON tools.owner_id = login.id
    WHERE tools.id = ?
");
$stmt->bind_param("i", $toolId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Tool not found.");
}

$tool = $result->fetch_assoc();
$stmt->close();

// Prevent renting own tool
if ((int)$tool['owner_id'] === $renterId) {
    die("You cannot rent your own tool.");
}

// Only available tools can be rented
if (($tool['availability_status'] ?? '') !== 'available') {
    die("This tool is currently not available.");
}

if (isset($_POST['rent_tool'])) {
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $requestNote = trim($_POST['request_note'] ?? '');

    if ($startDate === "" || $endDate === "") {
        $message = "Please select both start date and end date.";
        $messageType = "error";
    } else {
        $today = date('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $message = "Invalid date format.";
            $messageType = "error";
        } elseif ($startDate < $today) {
            $message = "Start date cannot be in the past.";
            $messageType = "error";
        } elseif ($endDate < $startDate) {
            $message = "End date cannot be earlier than start date.";
            $messageType = "error";
        } else {
            // Count overlapping approved/ongoing rentals
            $checkOverlap = $conn->prepare("
                SELECT COUNT(*) AS overlapping_count
                FROM rentals
                WHERE tool_id = ?
                  AND status IN ('approved', 'ongoing')
                  AND start_date <= ?
                  AND end_date >= ?
            ");
            $checkOverlap->bind_param("iss", $toolId, $endDate, $startDate);
            $checkOverlap->execute();
            $overlapResult = $checkOverlap->get_result();
            $overlapData = $overlapResult->fetch_assoc();
            $overlappingCount = (int)($overlapData['overlapping_count'] ?? 0);
            $checkOverlap->close();

            $availableQuantity = (int)$tool['quantity'];

            if ($overlappingCount >= $availableQuantity) {
                $message = "This tool is fully booked for the selected dates.";
                $messageType = "error";
            } else {
                $start = new DateTime($startDate);
                $end = new DateTime($endDate);
                $interval = $start->diff($end);
                $totalDays = $interval->days + 1;

                $rentalFee = (float)$tool['price_per_day'] * $totalDays;
                $depositFee = (float)$tool['deposit_amount'];
                $totalAmount = $rentalFee + $depositFee;
                $ownerId = (int)$tool['owner_id'];

                $insert = $conn->prepare("
                    INSERT INTO rentals (
                        tool_id,
                        renter_id,
                        owner_id,
                        start_date,
                        end_date,
                        total_days,
                        rental_fee,
                        deposit_fee,
                        total_amount,
                        status,
                        request_note
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                ");
                $insert->bind_param(
                    "iiissiddds",
                    $toolId,
                    $renterId,
                    $ownerId,
                    $startDate,
                    $endDate,
                    $totalDays,
                    $rentalFee,
                    $depositFee,
                    $totalAmount,
                    $requestNote
                );

            if ($insert->execute()) {
                header("Location: my_rentals.php");
                exit;
            } else {
                $message = "Failed to submit rental request.";
                $messageType = "error";
            }

                $insert->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rent Tool - Niella's Rent-A-Tool</title>
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
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 0 14px rgba(0,0,0,0.08);
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .left, .right {
            padding: 28px;
        }

        .left {
            border-right: 1px solid #e5e7eb;
        }

        .tool-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #111827;
            margin-bottom: 10px;
        }

        .tool-info {
            margin-bottom: 10px;
            color: #374151;
        }

        .price-box {
            margin-top: 16px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px;
        }

        .price-line {
            margin-bottom: 10px;
            color: #111827;
        }

        .message {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .input-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            color: #374151;
        }

        input[type="date"],
        textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 13px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            background: #1d4ed8;
        }

        .hint {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 8px;
            line-height: 1.5;
        }

        .back-link {
            display: inline-block;
            margin-top: 14px;
            text-decoration: none;
            color: #2563eb;
            font-weight: bold;
        }

        @media (max-width: 850px) {
            .card {
                grid-template-columns: 1fr;
            }

            .left {
                border-right: none;
                border-bottom: 1px solid #e5e7eb;
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
        <div class="left">
            <div class="tool-title"><?php echo htmlspecialchars($tool['tool_name']); ?></div>
            <div class="tool-info"><strong>Category:</strong> <?php echo htmlspecialchars($tool['category_name'] ?? 'Uncategorized'); ?></div>
            <div class="tool-info"><strong>Owner:</strong> <?php echo htmlspecialchars($tool['owner_username']); ?></div>
            <div class="tool-info"><strong>Price Per Day:</strong> ₱<?php echo number_format((float)$tool['price_per_day'], 2); ?></div>
            <div class="tool-info"><strong>Deposit:</strong> ₱<?php echo number_format((float)$tool['deposit_amount'], 2); ?></div>
            <div class="tool-info"><strong>Location:</strong> <?php echo htmlspecialchars($tool['location']); ?></div>
            <div class="tool-info"><strong>Condition:</strong> <?php echo htmlspecialchars($tool['condition_status']); ?></div>
            <div class="tool-info"><strong>Quantity Available:</strong> <?php echo (int)$tool['quantity']; ?></div>

            <div class="price-box">
                <div class="price-line"><strong>Deposit Logic:</strong></div>
                <div class="price-line">Rental Fee = Price Per Day × Total Days</div>
                <div class="price-line">Total Amount = Rental Fee + Deposit</div>
                <div class="price-line">Deposit is refundable after return, subject to deductions for late fees or damages.</div>
            </div>

            <a href="tool_details.php?id=<?php echo (int)$tool['id']; ?>" class="back-link">← Back to Tool Details</a>
        </div>

        <div class="right">
            <h2>Request Rental</h2>

            <?php if ($message !== ""): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" required>
                </div>

                <div class="input-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" required>
                </div>

                <div class="input-group">
                    <label>Request Note</label>
                    <textarea name="request_note" placeholder="Optional message to the owner..."></textarea>
                </div>

                <button type="submit" name="rent_tool">Submit Rental Request</button>
            </form>

            <div class="hint">
                The total rental fee and deposit will be calculated and saved automatically once you submit the request.
            </div>
        </div>
    </div>
</div>

</body>
</html>