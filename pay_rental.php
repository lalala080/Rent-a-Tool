<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['role'] ?? 'renter') !== 'renter') {
    die("Access denied.");
}

$renterId = (int)$_SESSION['user_id'];
$message = "";
$messageType = "";

if (!isset($_GET['rental_id']) || !is_numeric($_GET['rental_id'])) {
    die("Invalid rental ID.");
}

$rentalId = (int)$_GET['rental_id'];

$stmt = $conn->prepare("
    SELECT 
        rentals.*,
        tools.tool_name,
        tools.image_path,
        login.username AS owner_username
    FROM rentals
    LEFT JOIN tools ON rentals.tool_id = tools.id
    LEFT JOIN login ON rentals.owner_id = login.id
    WHERE rentals.id = ? AND rentals.renter_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $rentalId, $renterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Rental not found or access denied.");
}

$rental = $result->fetch_assoc();
$stmt->close();

if (!in_array($rental['status'], ['approved', 'pending'], true)) {
    die("This rental is not eligible for payment.");
}

$checkPayment = $conn->prepare("
    SELECT id, payment_status
    FROM payments
    WHERE rental_id = ? AND payer_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$checkPayment->bind_param("ii", $rentalId, $renterId);
$checkPayment->execute();
$paymentResult = $checkPayment->get_result();
$existingPayment = $paymentResult->fetch_assoc();
$checkPayment->close();

if (isset($_POST['submit_payment'])) {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paymentFor = $_POST['payment_for'] ?? 'full';
    $notes = trim($_POST['notes'] ?? '');
    $amount = (float)$rental['total_amount'];

    if (!in_array($paymentMethod, ['gcash', 'maya', 'cash', 'bank_transfer'], true)) {
        $message = "Invalid payment method.";
        $messageType = "error";
    } elseif (!in_array($paymentFor, ['deposit', 'rental', 'full'], true)) {
        $message = "Invalid payment type.";
        $messageType = "error";
    } elseif (in_array($paymentMethod, ['gcash', 'maya', 'bank_transfer'], true) && (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== 0)) {
        $message = "Please upload payment proof.";
        $messageType = "error";
    } else {
        if ($paymentFor === 'deposit') {
            $amount = (float)$rental['deposit_fee'];
        } elseif ($paymentFor === 'rental') {
            $amount = (float)$rental['rental_fee'];
        } else {
            $amount = (float)$rental['total_amount'];
        }

        $proofImage = null;
        $uploadDir = "uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $mimeType = mime_content_type($_FILES['proof_image']['tmp_name']);

            if (!in_array($mimeType, $allowedTypes, true)) {
                $message = "Only JPG, PNG, and WEBP images are allowed for payment proof.";
                $messageType = "error";
            } else {
                $ext = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
                $proofImage = uniqid('payment_', true) . "." . strtolower($ext);

                if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $uploadDir . $proofImage)) {
                    $message = "Failed to upload payment proof.";
                    $messageType = "error";
                }
            }
        }

        if ($message === "") {
            $insert = $conn->prepare("
                INSERT INTO payments (
                    rental_id,
                    payer_id,
                    amount,
                    payment_for,
                    payment_method,
                    proof_image,
                    payment_status,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $insert->bind_param(
                "iidssss",
                $rentalId,
                $renterId,
                $amount,
                $paymentFor,
                $paymentMethod,
                $proofImage,
                $notes
            );

            if ($insert->execute()) {
                header("Location: my_rentals.php");
                exit;
            } else {
                $message = "Failed to submit payment.";
                $messageType = "error";
            }

            $insert->close();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pay Rental</title>
    <style>
        body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; }
        .navbar {
            background:#2563eb; color:white; padding:15px 30px;
            display:flex; justify-content:space-between; align-items:center;
        }
        .navbar a { color:white; text-decoration:none; margin-left:15px; font-weight:bold; }
        .container {
            max-width:1000px; margin:35px auto; padding:0 20px;
        }
        .card {
            background:white; border-radius:18px; box-shadow:0 0 14px rgba(0,0,0,0.08);
            overflow:hidden; display:grid; grid-template-columns:1fr 1fr;
        }
        .left, .right { padding:28px; }
        .left { border-right:1px solid #e5e7eb; }
        .title { font-size:1.8rem; font-weight:bold; color:#111827; margin-bottom:12px; }
        .info { margin-bottom:10px; color:#374151; }
        .amount-box {
            margin-top:16px; background:#f8fafc; border:1px solid #e5e7eb;
            border-radius:14px; padding:16px;
        }
        .amount-line { margin-bottom:10px; color:#111827; }
        .message { padding:12px 14px; border-radius:10px; margin-bottom:16px; }
        .success { background:#dcfce7; color:#166534; }
        .error { background:#fee2e2; color:#991b1b; }
        .input-group { margin-bottom:16px; }
        label { display:block; margin-bottom:7px; font-weight:bold; color:#374151; }
        select, textarea, input[type="file"] {
            width:100%; padding:11px 12px; border:1px solid #d1d5db;
            border-radius:10px; box-sizing:border-box;
        }
        textarea { min-height:100px; resize:vertical; }
        button {
            background:#2563eb; color:white; border:none; padding:13px 18px;
            border-radius:10px; cursor:pointer; font-weight:bold;
        }
        button:hover { background:#1d4ed8; }
        .note {
            background:#eff6ff; border:1px solid #bfdbfe; padding:14px;
            border-radius:12px; color:#1e3a8a; line-height:1.6;
        }
        .badge {
            display:inline-block; padding:7px 12px; border-radius:999px;
            font-size:0.85rem; font-weight:bold; margin-top:8px;
        }
        .pending { background:#fef3c7; color:#92400e; }
        .verified { background:#dcfce7; color:#166534; }
        .rejected { background:#fee2e2; color:#991b1b; }
        @media (max-width: 850px) {
            .card { grid-template-columns:1fr; }
            .left { border-right:none; border-bottom:1px solid #e5e7eb; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>Niella's Rent-A-Tool</strong></div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="my_rentals.php">My Rentals</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
     <a href="my_rentals.php" style="
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
            <div class="title">Pay Rental</div>

            <div class="info"><strong>Tool:</strong> <?php echo htmlspecialchars($rental['tool_name']); ?></div>
            <div class="info"><strong>Owner:</strong> <?php echo htmlspecialchars($rental['owner_username']); ?></div>
            <div class="info"><strong>Start Date:</strong> <?php echo htmlspecialchars($rental['start_date']); ?></div>
            <div class="info"><strong>End Date:</strong> <?php echo htmlspecialchars($rental['end_date']); ?></div>
            <div class="info"><strong>Status:</strong> <?php echo htmlspecialchars($rental['status']); ?></div>

            <div class="amount-box">
                <div class="amount-line"><strong>Rental Fee:</strong> ₱<?php echo number_format((float)$rental['rental_fee'], 2); ?></div>
                <div class="amount-line"><strong>Deposit Fee:</strong> ₱<?php echo number_format((float)$rental['deposit_fee'], 2); ?></div>
                <div class="amount-line"><strong>Total Amount:</strong> ₱<?php echo number_format((float)$rental['total_amount'], 2); ?></div>
            </div>

            <div style="margin-top:16px;" class="note">
                <strong>Sample Manual Payment Instructions</strong><br>
                GCash: 09123456789<br>
                Maya: 09123456789<br>
                Bank Transfer: 1234-5678-90<br><br>
                Upload your payment proof after sending the payment.
            </div>

            <?php if ($existingPayment): ?>
                <div style="margin-top:16px;">
                    <strong>Latest Payment Status:</strong>
                    <span class="badge <?php echo htmlspecialchars($existingPayment['payment_status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($existingPayment['payment_status'])); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <div class="right">
            <h2>Submit Payment</h2>

            <?php if ($message !== ""): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="input-group">
                    <label>Payment For</label>
                    <select name="payment_for" required>
                        <option value="full">Full Payment</option>
                        <option value="deposit">Deposit Only</option>
                        <option value="rental">Rental Fee Only</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Payment Method</label>
                    <select name="payment_method" required>
                        <option value="">Select method</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Payment Proof</label>
                    <input type="file" name="proof_image" accept="image/*">
                </div>

                <div class="input-group">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Optional note about your payment..."></textarea>
                </div>

                <button type="submit" name="submit_payment">Submit Payment</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>