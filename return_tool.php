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
$rental = null;

// Check rental id
if (!isset($_GET['rental_id']) || !is_numeric($_GET['rental_id'])) {
    die("Invalid rental ID.");
}

$rentalId = (int) $_GET['rental_id'];

// Get rental details
$stmt = $conn->prepare("
    SELECT 
        rentals.*,
        tools.tool_name,
        tools.id AS tool_id,
        tools.deposit_amount AS tool_deposit_amount,
        login.username AS renter_username
    FROM rentals
    INNER JOIN tools ON rentals.tool_id = tools.id
    INNER JOIN login ON rentals.renter_id = login.id
    WHERE rentals.id = ? AND rentals.owner_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $rentalId, $ownerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Rental not found or access denied.");
}

$rental = $result->fetch_assoc();
$stmt->close();

// allow only approved or ongoing
if (!in_array($rental['status'], ['approved', 'ongoing'], true)) {
    die("This rental cannot be processed for return.");
}

// Handle return submit
if (isset($_POST['process_return'])) {
    $returnedDate = $_POST['returned_date'] ?? '';
    $conditionOnReturn = $_POST['condition_on_return'] ?? '';
    $lateFee = (float)($_POST['late_fee'] ?? 0);
    $damageFee = (float)($_POST['damage_fee'] ?? 0);
    $extraCharges = (float)($_POST['extra_charges'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($returnedDate === "" || $conditionOnReturn === "") {
        $message = "Please fill in returned date and condition.";
        $messageType = "error";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnedDate)) {
        $message = "Invalid returned date format.";
        $messageType = "error";
    } elseif (!in_array($conditionOnReturn, ['good', 'late', 'damaged', 'lost'], true)) {
        $message = "Invalid return condition.";
        $messageType = "error";
    } elseif ($lateFee < 0 || $damageFee < 0 || $extraCharges < 0) {
        $message = "Fees cannot be negative.";
        $messageType = "error";
    } else {
        $depositAmount = (float)$rental['deposit_fee'];
        $deductionAmount = $lateFee + $damageFee + $extraCharges;
        $refundAmount = $depositAmount - $deductionAmount;

        if ($refundAmount < 0) {
            $refundAmount = 0;
        }

        if ($refundAmount == $depositAmount) {
            $depositStatus = 'refunded';
        } elseif ($refundAmount > 0 && $refundAmount < $depositAmount) {
            $depositStatus = 'partially_refunded';
        } else {
            $depositStatus = 'forfeited';
        }

        $conn->begin_transaction();

        try {
            // prevent duplicate return log
            $existingReturn = $conn->prepare("SELECT id FROM returns_log WHERE rental_id = ? LIMIT 1");
            $existingReturn->bind_param("i", $rentalId);
            $existingReturn->execute();
            $existingReturnResult = $existingReturn->get_result();

            if ($existingReturnResult->num_rows > 0) {
                throw new Exception("This rental has already been processed for return.");
            }
            $existingReturn->close();

            // 1) insert into returns_log
            $insertReturn = $conn->prepare("
                INSERT INTO returns_log (
                    rental_id,
                    returned_date,
                    condition_on_return,
                    late_fee,
                    damage_fee,
                    extra_charges,
                    notes,
                    checked_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertReturn->bind_param(
                "issdddsi",
                $rentalId,
                $returnedDate,
                $conditionOnReturn,
                $lateFee,
                $damageFee,
                $extraCharges,
                $notes,
                $ownerId
            );

            if (!$insertReturn->execute()) {
                throw new Exception("Failed to save return log.");
            }
            $insertReturn->close();

            // 2) insert deposit record
            $insertDeposit = $conn->prepare("
                INSERT INTO deposits (
                    rental_id,
                    deposit_amount,
                    deduction_amount,
                    refund_amount,
                    deposit_status,
                    remarks
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $remarks = "Processed return with condition: " . $conditionOnReturn;
            $insertDeposit->bind_param(
                "idddss",
                $rentalId,
                $depositAmount,
                $deductionAmount,
                $refundAmount,
                $depositStatus,
                $remarks
            );

            if (!$insertDeposit->execute()) {
                throw new Exception("Failed to save deposit record.");
            }
            $insertDeposit->close();

            // 3) update rental status
            $updateRental = $conn->prepare("
                UPDATE rentals 
                SET status = 'returned' 
                WHERE id = ? AND owner_id = ? AND status IN ('approved', 'ongoing')
            ");
            $updateRental->bind_param("ii", $rentalId, $ownerId);

            if (!$updateRental->execute() || $updateRental->affected_rows === 0) {
                throw new Exception("Failed to update rental status.");
            }
            $updateRental->close();

            // 4) if not lost, increase available quantity by 1
            if ($conditionOnReturn !== 'lost') {
                $updateToolQty = $conn->prepare("
                    UPDATE tools
                    SET quantity = quantity + 1
                    WHERE id = ? AND owner_id = ?
                ");
                $updateToolQty->bind_param("ii", $rental['tool_id'], $ownerId);

                if (!$updateToolQty->execute()) {
                    throw new Exception("Failed to update tool quantity.");
                }
                $updateToolQty->close();
            }

            // 5) notification
            $notifMessage = "Your rental for \"" . $rental['tool_name'] . "\" has been marked as returned. Refund amount: ₱" . number_format($refundAmount, 2);
            $notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notif->bind_param("is", $rental['renter_id'], $notifMessage);

            if (!$notif->execute()) {
                throw new Exception("Return processed, but failed to create notification.");
            }
            $notif->close();

            $conn->commit();

            $message = "Return processed successfully. Refund amount: ₱" . number_format($refundAmount, 2);
            $messageType = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Return Tool - Niella's Rent-A-Tool</title>
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
            max-width: 1050px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 0 14px rgba(0,0,0,0.08);
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
        }

        .left, .right {
            padding: 28px;
        }

        .left {
            border-right: 1px solid #e5e7eb;
            background: #fafcff;
        }

        h2, h3 {
            margin-top: 0;
            color: #111827;
        }

        .info {
            margin-bottom: 10px;
            color: #374151;
            font-size: 0.96rem;
        }

        .calc-box {
            margin-top: 18px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px;
        }

        .calc-line {
            margin-bottom: 8px;
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
        input[type="number"],
        select,
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
            line-height: 1.5;
            margin-top: 8px;
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
        <a href="rental_requests.php">Rental Requests</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <a href="rental_requests.php" style="
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
            <h2>Return Summary</h2>

            <div class="info"><strong>Tool:</strong> <?php echo htmlspecialchars($rental['tool_name']); ?></div>
            <div class="info"><strong>Renter:</strong> <?php echo htmlspecialchars($rental['renter_username']); ?></div>
            <div class="info"><strong>Start Date:</strong> <?php echo htmlspecialchars($rental['start_date']); ?></div>
            <div class="info"><strong>End Date:</strong> <?php echo htmlspecialchars($rental['end_date']); ?></div>
            <div class="info"><strong>Total Days:</strong> <?php echo (int)$rental['total_days']; ?></div>
            <div class="info"><strong>Rental Fee:</strong> ₱<?php echo number_format((float)$rental['rental_fee'], 2); ?></div>
            <div class="info"><strong>Deposit Paid:</strong> ₱<?php echo number_format((float)$rental['deposit_fee'], 2); ?></div>
            <div class="info"><strong>Status:</strong> <?php echo htmlspecialchars($rental['status']); ?></div>

            <div class="calc-box">
                <h3>Deposit Formula</h3>
                <div class="calc-line">Deduction = Late Fee + Damage Fee + Extra Charges</div>
                <div class="calc-line">Refund = Deposit - Deduction</div>
                <div class="calc-line">If refund is below 0, refund becomes 0.</div>
            </div>

            <a href="rental_requests.php" class="back-link">← Back to Rental Requests</a>
        </div>

        <div class="right">
            <h2>Process Return</h2>

            <?php if ($message !== ""): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label>Returned Date</label>
                    <input type="date" name="returned_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="input-group">
                    <label>Condition on Return</label>
                    <select name="condition_on_return" required>
                        <option value="">Select condition</option>
                        <option value="good">Good</option>
                        <option value="late">Late</option>
                        <option value="damaged">Damaged</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Late Fee</label>
                    <input type="number" name="late_fee" step="0.01" min="0" value="0">
                </div>

                <div class="input-group">
                    <label>Damage Fee</label>
                    <input type="number" name="damage_fee" step="0.01" min="0" value="0">
                </div>

                <div class="input-group">
                    <label>Extra Charges</label>
                    <input type="number" name="extra_charges" step="0.01" min="0" value="0">
                </div>

                <div class="input-group">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Add notes about the item return..."></textarea>
                </div>

                <button type="submit" name="process_return">Process Return</button>
            </form>

            <div class="hint">
                Use late fee, damage fee, and extra charges only when needed. The system will automatically compute the final refundable deposit.
            </div>
        </div>
    </div>
</div>

</body>
</html>