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

$stmt = $conn->prepare("
    SELECT 
        rentals.*,
        tools.tool_name,
        tools.image_path,
        tools.location,
        tools.price_per_day,
        login.username AS owner_username,
        (
            SELECT payment_status
            FROM payments
            WHERE payments.rental_id = rentals.id
              AND payments.payer_id = rentals.renter_id
            ORDER BY payments.id DESC
            LIMIT 1
        ) AS latest_payment_status
    FROM rentals
    LEFT JOIN tools ON rentals.tool_id = tools.id
    LEFT JOIN login ON rentals.owner_id = login.id
    WHERE rentals.renter_id = ?
    ORDER BY rentals.created_at DESC
");
$stmt->bind_param("i", $renterId);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rentals</title>
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --bg-1: #eef6ff;
            --bg-2: #f8fbff;
            --card: rgba(255, 255, 255, 0.78);
            --border: rgba(148, 163, 184, 0.20);
            --text: #0f172a;
            --muted: #5b6b82;
            --primary: #2563eb;
            --primary-2: #06b6d4;
            --shadow: 0 20px 50px rgba(37, 99, 235, 0.10);
            --shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.18), transparent 28%),
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.16), transparent 24%),
                linear-gradient(180deg, var(--bg-1) 0%, var(--bg-2) 55%, #f7fbff 100%);
            min-height: 100vh;
        }

        .page-shell {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            margin-bottom: 24px;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow-soft);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.22);
        }

        .brand-text small {
            display: block;
            color: #64748b;
            font-size: 0.78rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .brand-text strong {
            font-size: 1.5rem;
            line-height: 1.1;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #1e293b;
            font-weight: 700;
            padding: 11px 16px;
            border-radius: 14px;
            transition: 0.25s ease;
            background: rgba(255, 255, 255, 0.62);
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #0f172a;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.12), rgba(6, 182, 212, 0.12));
            border-color: rgba(37, 99, 235, 0.20);
            transform: translateY(-1px);
        }

        .section-card {
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 28px;
            box-shadow: var(--shadow-soft);
            margin-top: 10px;
        }

        .section-header {
            margin-bottom: 22px;
        }

        .section-header h2 {
            margin: 0 0 8px;
            font-size: 2rem;
        }

        .section-header p {
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
        }

        .rental-list {
            display: grid;
            gap: 20px;
        }

        .rental-card {
            display: grid;
            grid-template-columns: 270px 1fr;
            background: linear-gradient(180deg, rgba(255,255,255,0.94), rgba(248,250,252,0.96));
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 16px 35px rgba(15, 23, 42, 0.07);
        }

        .media-wrap {
            position: relative;
            min-height: 100%;
            background: linear-gradient(135deg, #dbeafe, #cffafe);
        }

        .rental-image,
        .no-image {
            width: 100%;
            height: 100%;
            min-height: 260px;
            display: block;
            object-fit: cover;
        }

        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            font-weight: 700;
            font-size: 1rem;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.55), transparent 30%),
                linear-gradient(135deg, #dbeafe, #cffafe);
        }

        .image-badge {
            position: absolute;
            left: 16px;
            bottom: 16px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.88);
            color: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.18);
            font-size: 0.85rem;
            font-weight: 800;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.10);
        }

        .rental-body {
            padding: 24px;
        }

        .top-line {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }

        .title-block h3 {
            margin: 0;
            font-size: 1.6rem;
            line-height: 1.15;
        }

        .title-block .sub {
            margin-top: 8px;
            color: var(--muted);
            font-size: 0.98rem;
        }

        .chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 14px 0 18px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 800;
            border: 1px solid transparent;
        }

        .pending {
            background: #fff7d6;
            color: #9a6700;
            border-color: #f6d98b;
        }

        .approved {
            background: #dbeafe;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .rejected {
            background: #fee2e2;
            color: #b42318;
            border-color: #fecaca;
        }

        .ongoing {
            background: #e0e7ff;
            color: #4338ca;
            border-color: #c7d2fe;
        }

        .returned {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .cancelled {
            background: #f1f5f9;
            color: #475569;
            border-color: #e2e8f0;
        }

        .verified {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .payment-pending {
            background: #fff7d6;
            color: #9a6700;
            border-color: #f6d98b;
        }

        .payment-rejected {
            background: #fee2e2;
            color: #b42318;
            border-color: #fecaca;
        }

        .payment-none {
            background: #eef2f7;
            color: #475569;
            border-color: #d9e2ec;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .detail-card {
            background: rgba(248, 250, 252, 0.95);
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 18px;
            padding: 14px 16px;
        }

        .detail-label {
            color: #64748b;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .detail-value {
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
            word-break: break-word;
        }

        .amount-box {
            margin-top: 4px;
            background: linear-gradient(135deg, rgba(239,246,255,0.95), rgba(236,254,255,0.95));
            border: 1px solid rgba(125, 211, 252, 0.28);
            border-radius: 22px;
            padding: 18px;
        }

        .amount-box h4 {
            margin: 0 0 14px;
            font-size: 1rem;
        }

        .amount-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            color: #0f172a;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.35);
        }

        .amount-line:last-child {
            border-bottom: none;
            padding-bottom: 0;
            font-size: 1.06rem;
            font-weight: 800;
        }

        .meta-row {
            margin-top: 16px;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .pay-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            border-radius: 14px;
            padding: 12px 18px;
            font-weight: 800;
            color: white;
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.20);
            transition: 0.25s ease;
        }

        .pay-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 28px rgba(37, 99, 235, 0.25);
        }

        .empty {
            padding: 42px 24px;
            text-align: center;
            background: rgba(255, 255, 255, 0.82);
            border-radius: 26px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            color: #64748b;
            box-shadow: var(--shadow-soft);
        }

        .empty h3 {
            margin: 0 0 10px;
            color: #0f172a;
            font-size: 1.5rem;
        }

        .empty p {
            margin: 0;
            font-size: 1rem;
        }

        @media (max-width: 1100px) {
            .rental-card {
                grid-template-columns: 1fr;
            }

            .media-wrap {
                min-height: 240px;
            }
        }

        @media (max-width: 760px) {
            .page-shell {
                padding: 16px;
            }

            .navbar {
                padding: 16px;
                flex-direction: column;
                align-items: stretch;
            }

            .brand {
                justify-content: center;
            }

            .nav-links {
                justify-content: center;
            }

            .section-card {
                padding: 20px;
            }

            .top-line {
                flex-direction: column;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .amount-line {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <div class="navbar">
        <div class="brand">
            <div class="brand-icon">🧰</div>
            <div class="brand-text">
                <small>Tool Rental Platform</small>
                <strong>Niella's Rent-A-Tool</strong>
            </div>
        </div>

        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="browse_tools.php">Browse Tools</a>
            <a href="my_rentals.php" class="active">My Rentals</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">
            <h2>My Rentals</h2>
            <p>Browse all your requests, payment progress, and rental schedules.</p>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="rental-list">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php $paymentStatus = $row['latest_payment_status'] ?? null; ?>

                    <div class="rental-card">
                        <div class="media-wrap">
                            <?php if (!empty($row['image_path'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($row['image_path']); ?>" alt="Tool Image" class="rental-image">
                            <?php else: ?>
                                <div class="no-image">No Image Available</div>
                            <?php endif; ?>

                            <div class="image-badge">
                                ₱<?php echo number_format((float)$row['price_per_day'], 2); ?>/day
                            </div>
                        </div>

                        <div class="rental-body">
                            <div class="top-line">
                                <div class="title-block">
                                    <h3><?php echo htmlspecialchars($row['tool_name']); ?></h3>
                                    <div class="sub">
                                        Owned by <?php echo htmlspecialchars($row['owner_username']); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="chip-group">
                                <span class="status <?php echo htmlspecialchars($row['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                </span>

                                <?php
                                if ($paymentStatus === 'verified') {
                                    echo '<span class="status verified">Payment Verified</span>';
                                } elseif ($paymentStatus === 'pending') {
                                    echo '<span class="status payment-pending">Payment Pending</span>';
                                } elseif ($paymentStatus === 'rejected') {
                                    echo '<span class="status payment-rejected">Payment Rejected</span>';
                                } else {
                                    echo '<span class="status payment-none">No Payment Yet</span>';
                                }
                                ?>
                            </div>

                            <div class="details-grid">
                                <div class="detail-card">
                                    <div class="detail-label">Location</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($row['location']); ?></div>
                                </div>
                                <div class="detail-card">
                                    <div class="detail-label">Total Days</div>
                                    <div class="detail-value"><?php echo (int)$row['total_days']; ?> day(s)</div>
                                </div>
                                <div class="detail-card">
                                    <div class="detail-label">Start Date</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($row['start_date']); ?></div>
                                </div>
                                <div class="detail-card">
                                    <div class="detail-label">End Date</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($row['end_date']); ?></div>
                                </div>
                            </div>

                            <div class="amount-box">
                                <h4>Rental Summary</h4>
                                <div class="amount-line">
                                    <span>Price / Day</span>
                                    <strong>₱<?php echo number_format((float)$row['price_per_day'], 2); ?></strong>
                                </div>
                                <div class="amount-line">
                                    <span>Rental Fee</span>
                                    <strong>₱<?php echo number_format((float)$row['rental_fee'], 2); ?></strong>
                                </div>
                                <div class="amount-line">
                                    <span>Deposit Fee</span>
                                    <strong>₱<?php echo number_format((float)$row['deposit_fee'], 2); ?></strong>
                                </div>
                                <div class="amount-line">
                                    <span>Total Amount</span>
                                    <strong>₱<?php echo number_format((float)$row['total_amount'], 2); ?></strong>
                                </div>
                            </div>

                            <div class="meta-row">
                                <strong>Requested On:</strong> <?php echo htmlspecialchars($row['created_at']); ?>
                            </div>

                            <div class="actions">
                                <?php if (in_array($row['status'], ['pending', 'approved'], true) && $paymentStatus !== 'verified'): ?>
                                    <a href="pay_rental.php?rental_id=<?php echo (int)$row['id']; ?>" class="pay-btn">
                                        Pay Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <h3>No rentals found yet</h3>
                <p>You don’t have any rental records at the moment. Browse tools and start renting.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>