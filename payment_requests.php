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

$message = "";
$messageType = "";

// HANDLE VERIFY / REJECT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($paymentId > 0 && in_array($action, ['verify', 'reject'], true)) {
        $status = $action === 'verify' ? 'verified' : 'rejected';

        $stmt = $conn->prepare("UPDATE payments SET payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $paymentId);

        if ($stmt->execute()) {
            $message = "Payment updated!";
            $messageType = "success";
        } else {
            $message = "Error updating payment.";
            $messageType = "error";
        }

        $stmt->close();
    }
}

// FETCH PAYMENTS
$result = $conn->query("
    SELECT 
        payments.*,
        rentals.status AS rental_status,
        tools.tool_name,
        login.username AS renter_name
    FROM payments
    LEFT JOIN rentals ON payments.rental_id = rentals.id
    LEFT JOIN tools ON rentals.tool_id = tools.id
    LEFT JOIN login ON payments.payer_id = login.id
    ORDER BY payments.paid_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Requests</title>
    <style>
        body { font-family: Arial; background:#f4f6f9; margin:0; }
        .navbar {
            background:#2563eb; color:white; padding:15px 30px;
            display:flex; justify-content:space-between;
        }
        .navbar a { color:white; margin-left:15px; text-decoration:none; }

        .container { max-width:1100px; margin:30px auto; padding:0 20px; }

        .card {
            background:white;
            padding:20px;
            border-radius:15px;
            box-shadow:0 0 10px rgba(0,0,0,0.08);
            margin-bottom:15px;
        }

        .badge {
            padding:6px 10px;
            border-radius:20px;
            font-size:0.8rem;
            font-weight:bold;
        }

        .pending { background:#fef3c7; color:#92400e; }
        .verified { background:#dcfce7; color:#166534; }
        .rejected { background:#fee2e2; color:#991b1b; }

        button {
            padding:8px 12px;
            border:none;
            border-radius:8px;
            cursor:pointer;
            font-weight:bold;
        }

        .verify { background:green; color:white; }
        .reject { background:red; color:white; }

        img { max-width:200px; border-radius:10px; margin-top:10px; }

        .msg { padding:10px; border-radius:8px; margin-bottom:15px; }
        .success { background:#dcfce7; }
        .error { background:#fee2e2; }
    </style>
</head>
<body>

<div class="navbar">
    <div><strong>Admin Panel</strong></div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="rental_requests.php">Rentals</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h2>Payment Requests</h2>

    <?php if ($message): ?>
        <div class="msg <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card">

                <strong>Tool:</strong> <?php echo $row['tool_name']; ?><br>
                <strong>Renter:</strong> <?php echo $row['renter_name']; ?><br>
                <strong>Amount:</strong> ₱<?php echo number_format($row['amount'], 2); ?><br>
                <strong>Method:</strong> <?php echo $row['payment_method']; ?><br>
                <strong>Type:</strong> <?php echo $row['payment_for']; ?><br>

                <br>

                <span class="badge <?php echo $row['payment_status']; ?>">
                    <?php echo strtoupper($row['payment_status']); ?>
                </span>

                <?php if ($row['proof_image']): ?>
                    <div>
                        <strong>Proof:</strong><br>
                        <img src="uploads/<?php echo $row['proof_image']; ?>">
                    </div>
                <?php endif; ?>

                <?php if ($row['payment_status'] === 'pending'): ?>
                    <form method="POST" style="margin-top:10px;">
                        <input type="hidden" name="payment_id" value="<?php echo $row['id']; ?>">
                        <button name="action" value="verify" class="verify">Verify</button>
                        <button name="action" value="reject" class="reject">Reject</button>
                    </form>
                <?php endif; ?>

            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No payments yet.</p>
    <?php endif; ?>

</div>

</body>
</html>