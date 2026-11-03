<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'renter';
$username = $_SESSION['username'] ?? 'User';

$sql = "
    SELECT 
        tools.*,
        categories.category_name,
        login.username AS owner_username
    FROM tools
    LEFT JOIN categories ON tools.category_id = categories.id
    LEFT JOIN login ON tools.owner_id = login.id
    ORDER BY tools.created_at DESC
";

$result = $conn->query($sql);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getToolIcon($category, $name) {
    $text = strtolower(($category ?? '') . ' ' . ($name ?? ''));

    if (strpos($text, 'drill') !== false) return 'fa-solid fa-screwdriver-wrench';
    if (strpos($text, 'hammer') !== false) return 'fa-solid fa-hammer';
    if (strpos($text, 'grinder') !== false) return 'fa-solid fa-gear';
    if (strpos($text, 'ladder') !== false) return 'fa-solid fa-ladder-water';
    if (strpos($text, 'saw') !== false) return 'fa-solid fa-screwdriver';
    if (strpos($text, 'construction') !== false) return 'fa-solid fa-helmet-safety';
    if (strpos($text, 'power') !== false) return 'fa-solid fa-bolt';

    return 'fa-solid fa-toolbox';
}

function getAccent($index) {
    $accents = [
        'linear-gradient(135deg, #22c55e 0%, #06b6d4 100%)',
        'linear-gradient(135deg, #f97316 0%, #ef4444 100%)',
        'linear-gradient(135deg, #a855f7 0%, #6366f1 100%)',
        'linear-gradient(135deg, #0ea5e9 0%, #14b8a6 100%)',
        'linear-gradient(135deg, #eab308 0%, #f97316 100%)',
        'linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%)'
    ];

    return $accents[$index % count($accents)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent-A-Tool | Browse Tools</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg: #07111f;
            --bg-soft: rgba(15, 23, 42, 0.82);
            --panel: rgba(10, 18, 33, 0.84);
            --panel-2: rgba(17, 24, 39, 0.92);
            --stroke: rgba(255, 255, 255, 0.08);
            --text: #f8fafc;
            --muted: #94a3b8;
            --primary: #38bdf8;
            --secondary: #22c55e;
            --warning: #f59e0b;
            --danger: #fb7185;
            --shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
            --radius-xl: 28px;
            --radius-lg: 22px;
            --radius-md: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.18), transparent 28%),
                radial-gradient(circle at 85% 15%, rgba(34, 197, 94, 0.16), transparent 22%),
                linear-gradient(180deg, #08111e 0%, #030712 100%);
            padding: 24px;
        }

        .layout {
            max-width: 1560px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 290px 1fr;
            gap: 22px;
        }

        .panel {
            background: rgba(7, 14, 28, 0.72);
            border: 1px solid var(--stroke);
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
            border-radius: var(--radius-xl);
        }

        .sidebar {
            min-height: calc(100vh - 48px);
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background:
                linear-gradient(180deg, rgba(12, 20, 35, 0.96), rgba(4, 9, 20, 0.96)),
                radial-gradient(circle at top, rgba(56, 189, 248, 0.12), transparent 30%);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 26px;
        }

        .brand-logo {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: linear-gradient(135deg, #38bdf8 0%, #22c55e 100%);
            display: grid;
            place-items: center;
            font-size: 24px;
            box-shadow: 0 12px 28px rgba(34, 197, 94, 0.22);
        }

        .brand-text small {
            color: #7dd3fc;
            display: block;
            letter-spacing: 0.12em;
            font-size: 11px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.15;
        }

        .side-box {
            padding: 18px;
            border-radius: 22px;
            border: 1px solid rgba(56, 189, 248, 0.12);
            background: linear-gradient(135deg, rgba(56, 189, 248, 0.12), rgba(34, 197, 94, 0.06));
            margin-bottom: 22px;
        }

        .side-box h3 {
            font-size: 15px;
            margin-bottom: 8px;
        }

        .side-box p,
        .sidebar-note,
        .hero p,
        .card-subtitle,
        .tool-desc {
            color: var(--muted);
            line-height: 1.7;
        }

        .menu-title {
            color: #7dd3fc;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            margin: 18px 0 12px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            text-decoration: none;
            color: #e2e8f0;
            border-radius: 16px;
            transition: 0.25s ease;
            border: 1px solid transparent;
            background: rgba(255,255,255,0.02);
            margin-bottom: 8px;
        }

        .nav-links a:hover,
        .nav-links a.active {
            transform: translateX(4px);
            border-color: rgba(56, 189, 248, 0.18);
            background: linear-gradient(90deg, rgba(56, 189, 248, 0.12), rgba(34, 197, 94, 0.06));
            color: white;
        }

        .sidebar-note {
            padding: 16px;
            border-radius: 18px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--stroke);
            font-size: 13px;
        }

        .main {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .hero {
            padding: 30px;
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at 10% 20%, rgba(56, 189, 248, 0.22), transparent 28%),
                radial-gradient(circle at 80% 0%, rgba(34, 197, 94, 0.15), transparent 22%),
                linear-gradient(135deg, rgba(8, 15, 30, 0.98), rgba(15, 23, 42, 0.94));
        }

        .hero::after {
            content: '';
            position: absolute;
            width: 260px;
            height: 260px;
            right: -50px;
            top: -70px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.24), transparent 68%);
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            position: relative;
            z-index: 1;
        }

        .hero-copy h1 {
            font-size: clamp(30px, 4vw, 42px);
            margin-bottom: 10px;
            line-height: 1.08;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            color: #cbd5e1;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 24px;
            position: relative;
            z-index: 1;
        }

        .hero-stat {
            padding: 18px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .hero-stat span {
            display: block;
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 8px;
        }

        .hero-stat strong {
            font-size: 26px;
        }

        .hero-visual {
            min-width: 220px;
            display: flex;
            justify-content: flex-end;
        }

        .hero-tool {
            width: 190px;
            height: 190px;
            border-radius: 30px;
            background: linear-gradient(135deg, rgba(56, 189, 248, 0.18), rgba(34, 197, 94, 0.18));
            border: 1px solid rgba(255,255,255,0.10);
            display: grid;
            place-items: center;
            font-size: 66px;
            color: #f8fafc;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.06), 0 24px 50px rgba(2, 8, 23, 0.4);
        }

        .top-controls {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 22px;
        }

        .card {
            padding: 24px;
        }

        .card-title {
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .search-row {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr;
            gap: 12px;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #7dd3fc;
            pointer-events: none;
        }

        .search-row input,
        .search-row select {
            width: 100%;
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(255,255,255,0.08);
            color: white;
            padding: 14px 14px 14px 42px;
            outline: none;
            transition: 0.25s ease;
            font-size: 14px;
        }

        .search-row input:focus,
        .search-row select:focus {
            border-color: rgba(56, 189, 248, 0.5);
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.08);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .summary-box {
            border-radius: 20px;
            padding: 16px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
        }

        .summary-box h4 {
            color: #94a3b8;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }

        .summary-box p {
            font-size: 24px;
            font-weight: 800;
        }

        .tool-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .tool-card {
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
            background: linear-gradient(180deg, rgba(9, 15, 28, 0.96), rgba(12, 19, 35, 0.92));
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .tool-card:hover {
            transform: translateY(-4px);
            border-color: rgba(56, 189, 248, 0.32);
            box-shadow: 0 20px 45px rgba(2, 8, 23, 0.38);
        }

        .tool-image {
            height: 210px;
            padding: 18px;
            position: relative;
            display: flex;
            align-items: end;
            justify-content: space-between;
            overflow: hidden;
        }

        .tool-image::before,
        .tool-image::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.14);
        }

        .tool-image::before {
            width: 170px;
            height: 170px;
            right: -35px;
            top: -35px;
        }

        .tool-image::after {
            width: 80px;
            height: 80px;
            left: -16px;
            bottom: -16px;
            opacity: 0.45;
        }

        .tool-visual-icon {
            width: 82px;
            height: 82px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            font-size: 34px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.18);
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }

        .tool-image-text {
            position: relative;
            z-index: 1;
            text-align: right;
        }

        .tool-image-text span {
            display: block;
            font-size: 12px;
            color: rgba(255,255,255,0.78);
            margin-bottom: 6px;
        }

        .tool-image-text strong {
            font-size: 22px;
            line-height: 1.1;
        }

        .tool-body {
            padding: 18px;
        }

        .tool-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
        }

        .tool-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .tool-category {
            color: #7dd3fc;
            font-size: 13px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .status.available {
            background: rgba(34, 197, 94, 0.12);
            color: #bbf7d0;
            border-color: rgba(34, 197, 94, 0.18);
        }

        .status.unavailable {
            background: rgba(251, 113, 133, 0.12);
            color: #fecdd3;
            border-color: rgba(251, 113, 133, 0.18);
        }

        .status.maintenance {
            background: rgba(245, 158, 11, 0.12);
            color: #fde68a;
            border-color: rgba(245, 158, 11, 0.18);
        }

        .tool-highlights {
            padding: 12px;
            border-radius: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 14px 0 16px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
        }

        .highlight-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.05);
            font-size: 12px;
            color: #dbeafe;
        }

        .tool-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }

        .meta-box {
            border-radius: 16px;
            padding: 12px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
        }

        .meta-box span {
            color: #94a3b8;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }

        .meta-box strong {
            font-size: 14px;
        }

        .tool-footer {
            padding: 12px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 4px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
        }

        .footer-price strong {
            display: block;
            font-size: 24px;
        }

        .footer-price span {
            color: var(--muted);
            font-size: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            border: none;
            border-radius: 16px;
            color: white;
            padding: 14px 18px;
            cursor: pointer;
            font-weight: 700;
            transition: 0.25s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #38bdf8, #22c55e);
            box-shadow: 0 14px 30px rgba(34, 197, 94, 0.18);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .btn-disabled {
            background: rgba(255,255,255,0.06);
            color: #7c8aa0;
            box-shadow: none;
            cursor: not-allowed;
        }

        .empty-state {
            padding: 38px 18px;
            text-align: center;
            border-radius: 20px;
            border: 1px dashed rgba(255,255,255,0.12);
            color: var(--muted);
            display: none;
            background: rgba(255,255,255,0.03);
        }

        .empty-state i {
            font-size: 32px;
            color: #7dd3fc;
            margin-bottom: 10px;
            display: block;
        }

        @media (max-width: 1350px) {
            .top-controls {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1100px) {
            .tool-grid,
            .hero-stats {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 980px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                min-height: auto;
            }

            .hero-top {
                flex-direction: column;
            }

            .hero-visual {
                justify-content: flex-start;
            }
        }

        @media (max-width: 760px) {
            body {
                padding: 14px;
            }

            .hero,
            .card,
            .sidebar {
                padding: 20px;
            }

            .search-row,
            .summary-grid,
            .tool-meta {
                grid-template-columns: 1fr;
            }

            .tool-footer {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="panel sidebar">
            <div>
                <div class="brand">
                    <div class="brand-logo"><i class="fa-solid fa-toolbox"></i></div>
                    <div class="brand-text">
                        <small>Tool Rental Platform</small>
                        <div class="brand-name">Niella's<br>Rent-A-Tool</div>
                    </div>
                </div>

                <div class="side-box">
                    <h3><i class="fa-solid fa-bolt"></i> Welcome, <?php echo e($username); ?></h3>
                    <p>Browse available tools, compare prices, and rent faster with a cleaner and more modern interface.</p>
                </div>

                <div class="menu-title"><?php echo $role === 'admin' ? 'Admin Menu' : 'Renter Menu'; ?></div>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
                    <a href="browse_tools.php" class="active"><i class="fa-solid fa-compass"></i> Browse Tools</a>

                    <?php if ($role === 'renter'): ?>
                        <a href="my_rentals.php"><i class="fa-solid fa-calendar-check"></i> My Rentals</a>
                    <?php endif; ?>

                    <?php if ($role === 'admin'): ?>
                        <a href="add_tool.php"><i class="fa-solid fa-plus"></i> Add Tool</a>
                        <a href="my_tools.php"><i class="fa-solid fa-screwdriver-wrench"></i> My Tools</a>
                        <a href="rental_requests.php"><i class="fa-solid fa-inbox"></i> Rental Requests</a>
                        <a href="admin_panel.php"><i class="fa-solid fa-users-gear"></i> Manage Users</a>
                    <?php endif; ?>
                </div>

                <div class="menu-title">Account</div>
                <div class="nav-links">
                    <a href="profile_setup.php"><i class="fa-solid fa-user"></i> Profile</a>
                    <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>

            <div class="sidebar-note">
                <i class="fa-solid fa-shield-halved"></i>
                Rentals, deposits, and tool listings are shown based on your current account role and system data.
            </div>
        </aside>

        <main class="main">
            <section class="panel hero">
                <div class="hero-top">
                    <div class="hero-copy">
                        <div class="hero-badge"><i class="fa-solid fa-sparkles"></i> Upgraded browse tools experience</div>
                        <h1>Browse, compare, and rent tools faster</h1>
                        <p>Explore available tools with cleaner cards, premium layout styling, and a faster browsing experience built on your current system data.</p>
                    </div>
                    <div class="hero-visual">
                        <div class="hero-tool"><i class="fa-solid fa-drill"></i></div>
                    </div>
                </div>

                <div class="hero-stats">
                    <div class="hero-stat">
                        <span>Total Listings</span>
                        <strong id="totalListings">
                            <?php echo ($result && $result->num_rows > 0) ? (int)$result->num_rows : 0; ?>
                        </strong>
                    </div>
                    <div class="hero-stat">
                        <span>Available Now</span>
                        <strong id="availableCount">0</strong>
                    </div>
                    <div class="hero-stat">
                        <span>Starting Rate</span>
                        <strong id="startingRate">₱0</strong>
                    </div>
                </div>
            </section>

            <section class="top-controls">
                <div class="panel card">
                    <div class="card-title">Find a Tool</div>
                    <div class="card-subtitle">Search by name, category, owner, or location.</div>

                    <div class="search-row">
                        <div class="input-wrap">
                            <i class="fa-solid fa-magnifying-glass input-icon"></i>
                            <input type="text" id="searchInput" placeholder="Search tool name..." />
                        </div>
                        <div class="input-wrap">
                            <i class="fa-solid fa-layer-group input-icon"></i>
                            <select id="categoryFilter">
                                <option value="">All Categories</option>
                                <?php
                                $categoryResult = $conn->query("SELECT category_name FROM categories ORDER BY category_name ASC");
                                if ($categoryResult) {
                                    while ($cat = $categoryResult->fetch_assoc()) {
                                        echo '<option value="' . e($cat['category_name']) . '">' . e($cat['category_name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="input-wrap">
                            <i class="fa-solid fa-location-dot input-icon"></i>
                            <input type="text" id="locationFilter" placeholder="Filter by location..." />
                        </div>
                    </div>
                </div>

                <div class="panel card">
                    <div class="card-title">Browse Summary</div>
                    <div class="card-subtitle">Live snapshot of your tool listings.</div>

                    <div class="summary-grid">
                        <div class="summary-box">
                            <h4>Visible Tools</h4>
                            <p id="visibleCount">0</p>
                        </div>
                        <div class="summary-box">
                            <h4>Lowest Price</h4>
                            <p id="visibleLowest">₱0</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel card">
                <div class="card-title">Available Tools</div>
                <div class="card-subtitle">Choose a listing and view more details.</div>

                <div class="tool-grid" id="toolGrid">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php $index = 0; ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                                $accent = getAccent($index);
                                $icon = getToolIcon($row['category_name'] ?? '', $row['tool_name'] ?? '');
                                $statusClass = strtolower($row['availability_status'] ?? 'available');
                            ?>
                            <div
                                class="tool-card tool-item"
                                data-name="<?php echo e($row['tool_name']); ?>"
                                data-category="<?php echo e($row['category_name'] ?? 'Uncategorized'); ?>"
                                data-location="<?php echo e($row['location']); ?>"
                                data-owner="<?php echo e($row['owner_username']); ?>"
                                data-price="<?php echo (float)$row['price_per_day']; ?>"
                                data-status="<?php echo e($statusClass); ?>"
                            >
                                <div class="tool-image" style="background: <?php echo e($accent); ?>;">
                                    <div class="tool-visual-icon">
                                        <i class="<?php echo e($icon); ?>"></i>
                                    </div>
                                    <div class="tool-image-text">
                                        <span>Featured Tool</span>
                                        <strong><?php echo e($row['tool_name']); ?></strong>
                                    </div>
                                </div>

                                <div class="tool-body">
                                    <div class="tool-head">
                                        <div>
                                            <div class="tool-title"><?php echo e($row['tool_name']); ?></div>
                                            <div class="tool-category"><?php echo e($row['category_name'] ?? 'Uncategorized'); ?></div>
                                        </div>

                                        <span class="status <?php echo e($statusClass); ?>">
                                            <?php if ($statusClass === 'available'): ?>
                                                <i class="fa-solid fa-circle-check"></i>
                                            <?php elseif ($statusClass === 'maintenance'): ?>
                                                <i class="fa-solid fa-screwdriver-wrench"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-lock"></i>
                                            <?php endif; ?>
                                            <?php echo ucfirst(e($row['availability_status'])); ?>
                                        </span>
                                    </div>

                                    <div class="tool-desc">
                                        <?php echo nl2br(e($row['description'] ?: 'No description available.')); ?>
                                    </div>

                                    <div class="tool-highlights">
                                        <div class="highlight-chip"><i class="fa-solid fa-location-dot"></i> <?php echo e($row['location']); ?></div>
                                        <div class="highlight-chip"><i class="fa-solid fa-user"></i> <?php echo e($row['owner_username']); ?></div>
                                        <div class="highlight-chip"><i class="fa-solid fa-medal"></i> <?php echo e($row['condition_status']); ?></div>
                                    </div>

                                    <div class="tool-meta">
                                        <div class="meta-box">
                                            <span>Rental Price</span>
                                            <strong>₱<?php echo number_format((float)$row['price_per_day'], 2); ?>/day</strong>
                                        </div>
                                        <div class="meta-box">
                                            <span>Deposit</span>
                                            <strong>₱<?php echo number_format((float)$row['deposit_amount'], 2); ?></strong>
                                        </div>
                                        <div class="meta-box">
                                            <span>Quantity</span>
                                            <strong><?php echo (int)$row['quantity']; ?></strong>
                                        </div>
                                        <div class="meta-box">
                                            <span>Brand / Model</span>
                                            <strong><?php echo e(($row['brand'] ?: 'N/A') . ' / ' . ($row['model'] ?: 'N/A')); ?></strong>
                                        </div>
                                    </div>

                                    <div class="tool-footer">
                                        <div class="footer-price">
                                            <span>Daily rate</span>
                                            <strong>₱<?php echo number_format((float)$row['price_per_day'], 2); ?></strong>
                                        </div>

                                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                            <a href="tool_details.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-secondary">
                                                <i class="fa-solid fa-eye"></i> View
                                            </a>

                                            <?php if ($role === 'renter' && ($row['availability_status'] ?? '') === 'available' && (int)$row['owner_id'] !== $userId): ?>
                                                <a href="rent_tool.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-primary">
                                                    <i class="fa-solid fa-cart-plus"></i> Rent
                                                </a>
                                            <?php elseif ($role === 'renter'): ?>
                                                <span class="btn btn-disabled">
                                                    <i class="fa-solid fa-lock"></i> Not Available
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php $index++; ?>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <div class="empty-state" id="emptyState">
                    <i class="fa-solid fa-toolbox"></i>
                    No tools matched your filters.
                </div>
            </section>
        </main>
    </div>

    <script>
        function formatPeso(amount) {
            return '₱' + Number(amount).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function updateStats() {
            const items = document.querySelectorAll('.tool-item');
            let visibleCount = 0;
            let availableCount = 0;
            let lowestPrice = null;

            items.forEach(item => {
                if (item.style.display !== 'none') {
                    visibleCount++;
                    const price = Number(item.dataset.price);
                    const status = item.dataset.status;

                    if (lowestPrice === null || price < lowestPrice) {
                        lowestPrice = price;
                    }

                    if (status === 'available') {
                        availableCount++;
                    }
                }
            });

            document.getElementById('visibleCount').textContent = visibleCount;
            document.getElementById('availableCount').textContent = availableCount;
            document.getElementById('visibleLowest').textContent = lowestPrice !== null ? formatPeso(lowestPrice) : '₱0';
            document.getElementById('startingRate').textContent = lowestPrice !== null ? formatPeso(lowestPrice) : '₱0';
        }

        function filterTools() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase().trim();
            const categoryValue = document.getElementById('categoryFilter').value.toLowerCase().trim();
            const locationValue = document.getElementById('locationFilter').value.toLowerCase().trim();
            const toolItems = document.querySelectorAll('.tool-item');
            let visibleCount = 0;

            toolItems.forEach(item => {
                const name = item.dataset.name.toLowerCase();
                const category = item.dataset.category.toLowerCase();
                const location = item.dataset.location.toLowerCase();
                const owner = item.dataset.owner.toLowerCase();

                const matchSearch =
                    name.includes(searchValue) ||
                    category.includes(searchValue) ||
                    owner.includes(searchValue);

                const matchCategory = categoryValue === '' || category === categoryValue;
                const matchLocation = locationValue === '' || location.includes(locationValue);

                if (matchSearch && matchCategory && matchLocation) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            document.getElementById('emptyState').style.display = visibleCount === 0 ? 'block' : 'none';
            updateStats();
        }

        document.getElementById('searchInput').addEventListener('input', filterTools);
        document.getElementById('categoryFilter').addEventListener('change', filterTools);
        document.getElementById('locationFilter').addEventListener('input', filterTools);

        updateStats();
    </script>
</body>
</html>