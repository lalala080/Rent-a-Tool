<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'renter';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$menuCards = [];

if ($role === 'renter') {
    $menuCards = [
        [
            'title' => 'Browse Tools',
            'desc' => 'Explore available tools and compare rental prices.',
            'icon' => 'fa-solid fa-compass',
            'link' => 'browse_tools.php',
            'accent' => 'linear-gradient(135deg, #38bdf8 0%, #22c55e 100%)'
        ],
        [
            'title' => 'My Rentals',
            'desc' => 'Track your current, pending, and past rentals.',
            'icon' => 'fa-solid fa-calendar-check',
            'link' => 'my_rentals.php',
            'accent' => 'linear-gradient(135deg, #a855f7 0%, #6366f1 100%)'
        ],
        [
            'title' => 'Profile Setup',
            'desc' => 'Update your personal details and verification info.',
            'icon' => 'fa-solid fa-user-gear',
            'link' => 'profile_setup.php',
            'accent' => 'linear-gradient(135deg, #f97316 0%, #ef4444 100%)'
        ],
        [
            'title' => 'Notifications',
            'desc' => 'Check booking updates and rental alerts.',
            'icon' => 'fa-solid fa-bell',
            'link' => 'notifications.php',
            'accent' => 'linear-gradient(135deg, #0ea5e9 0%, #14b8a6 100%)'
        ]
    ];
}

if ($role === 'admin') {
    $menuCards = [
        [
            'title' => 'Manage Users',
            'desc' => 'View user accounts and reactivate deactivated users.',
            'icon' => 'fa-solid fa-users-gear',
            'link' => 'admin_panel.php',
            'accent' => 'linear-gradient(135deg, #38bdf8 0%, #22c55e 100%)'
        ],
        [
            'title' => 'Add Tool',
            'desc' => 'Create a new tool listing for the platform.',
            'icon' => 'fa-solid fa-plus',
            'link' => 'add_tool.php',
            'accent' => 'linear-gradient(135deg, #f97316 0%, #ef4444 100%)'
        ],
        [
            'title' => 'My Tools',
            'desc' => 'Manage your tool listings, edits, and availability.',
            'icon' => 'fa-solid fa-screwdriver-wrench',
            'link' => 'my_tools.php',
            'accent' => 'linear-gradient(135deg, #a855f7 0%, #6366f1 100%)'
        ],
        [
            'title' => 'Rental Requests',
            'desc' => 'Approve, reject, and monitor booking requests.',
            'icon' => 'fa-solid fa-inbox',
            'link' => 'rental_requests.php',
            'accent' => 'linear-gradient(135deg, #0ea5e9 0%, #14b8a6 100%)'
        ],
        [
            'title' => 'Payment Requests',
            'desc' => 'Verify and manage renter payments.',
            'icon' => 'fa-solid fa-credit-card',
            'link' => 'payment_requests.php',
            'accent' => 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)'
        ],
        [
            'title' => 'Browse Tools',
            'desc' => 'Preview all available tool listings on the platform.',
            'icon' => 'fa-solid fa-compass',
            'link' => 'browse_tools.php',
            'accent' => 'linear-gradient(135deg, #eab308 0%, #f97316 100%)'
        ],
        [
            'title' => 'Profile Setup',
            'desc' => 'Update your personal details and account information.',
            'icon' => 'fa-solid fa-user-gear',
            'link' => 'profile_setup.php',
            'accent' => 'linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%)'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent-A-Tool | Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg: #07111f;
            --stroke: rgba(255, 255, 255, 0.08);
            --text: #f8fafc;
            --muted: #94a3b8;
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
        .section-subtitle,
        .card-desc {
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

        .section {
            padding: 24px;
        }

        .section-title {
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .section-subtitle {
            margin-bottom: 18px;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .dash-card {
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
            background: linear-gradient(180deg, rgba(9, 15, 28, 0.96), rgba(12, 19, 35, 0.92));
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .dash-card:hover {
            transform: translateY(-4px);
            border-color: rgba(56, 189, 248, 0.32);
            box-shadow: 0 20px 45px rgba(2, 8, 23, 0.38);
        }

        .dash-card-top {
            height: 160px;
            padding: 18px;
            position: relative;
            display: flex;
            align-items: end;
            justify-content: space-between;
            overflow: hidden;
        }

        .dash-card-top::before,
        .dash-card-top::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.14);
        }

        .dash-card-top::before {
            width: 140px;
            height: 140px;
            right: -35px;
            top: -35px;
        }

        .dash-card-top::after {
            width: 70px;
            height: 70px;
            left: -16px;
            bottom: -16px;
            opacity: 0.45;
        }

        .dash-card-icon {
            width: 74px;
            height: 74px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            font-size: 30px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.18);
            position: relative;
            z-index: 1;
        }

        .dash-card-toptext {
            position: relative;
            z-index: 1;
            text-align: right;
        }

        .dash-card-toptext span {
            display: block;
            font-size: 12px;
            color: rgba(255,255,255,0.78);
            margin-bottom: 6px;
        }

        .dash-card-toptext strong {
            font-size: 20px;
            line-height: 1.1;
        }

        .dash-card-body {
            padding: 18px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .card-desc {
            margin-bottom: 16px;
        }

        .card-footer {
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
            border-radius: 18px;
            padding: 12px;
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
            background: linear-gradient(135deg, #38bdf8, #22c55e);
            box-shadow: 0 14px 30px rgba(34, 197, 94, 0.18);
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        @media (max-width: 1200px) {
            .card-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 1100px) {
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
            .section,
            .sidebar {
                padding: 20px;
            }

            .card-grid {
                grid-template-columns: 1fr;
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
                    <h3><i class="fa-solid fa-bolt"></i> Welcome back, <?php echo e($username); ?></h3>
                    <p>
                        You are currently signed in as
                        <strong><?php echo e($role); ?></strong>.
                        Use the dashboard below to access your tools, rentals, and account controls.
                    </p>
                </div>

                <div class="menu-title"><?php echo $role === 'admin' ? 'Admin Menu' : 'Renter Menu'; ?></div>
                <div class="nav-links">
                    <a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i> Dashboard</a>
                    <a href="browse_tools.php"><i class="fa-solid fa-compass"></i> Browse Tools</a>

                    <?php if ($role === 'renter'): ?>
                        <a href="my_rentals.php"><i class="fa-solid fa-calendar-check"></i> My Rentals</a>
                    <?php endif; ?>

                    <?php if ($role === 'admin'): ?>
                        <a href="add_tool.php"><i class="fa-solid fa-plus"></i> Add Tool</a>
                        <a href="my_tools.php"><i class="fa-solid fa-screwdriver-wrench"></i> My Tools</a>
                        <a href="rental_requests.php"><i class="fa-solid fa-inbox"></i> Rental Requests</a>
                        <a href="payment_requests.php"><i class="fa-solid fa-credit-card"></i> Payments</a>
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
                Your available pages and actions are based on the role saved in your current logged-in account.
            </div>
        </aside>

        <main class="main">
            <section class="panel hero">
                <div class="hero-top">
                    <div class="hero-copy">
                        <div class="hero-badge"><i class="fa-solid fa-sparkles"></i> </div>
                        <h1><?php echo $role === 'admin' ? 'Manage your tool rental platform smarter' : 'RENT YOUR TOOL NOW!'; ?></h1>
                        <p>
                            <?php if ($role === 'admin'): ?>
                                Monitor users, manage listings, handle rental requests, and verify renter payments in one polished admin workspace.
                            <?php else: ?>
                                Browse tools, review your rentals, and manage your account in one modern renter dashboard.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="hero-visual">
                        <div class="hero-tool">
                            <i class="fa-solid <?php echo $role === 'admin' ? 'fa-chart-line' : 'fa-drill'; ?>"></i>
                        </div>
                    </div>
                </div>

                <div class="hero-stats">
                    <div class="hero-stat">
                        <span>Current User</span>
                        <strong><?php echo e($username); ?></strong>
                    </div>
                    <div class="hero-stat">
                        <span>Account Role</span>
                        <strong><?php echo ucfirst(e($role)); ?></strong>
                    </div>
                    <div class="hero-stat">
                        <span>Quick Access</span>
                        <strong><?php echo count($menuCards); ?> Pages</strong>
                    </div>
                </div>
            </section>

            <section class="panel section">
                <div class="section-title">Quick Access</div>
                <div class="section-subtitle">
                    Open the pages you use most often from your current <?php echo e($role); ?> account.
                </div>

                <div class="card-grid">
                    <?php foreach ($menuCards as $card): ?>
                        <div class="dash-card">
                            <div class="dash-card-top" style="background: <?php echo e($card['accent']); ?>;">
                                <div class="dash-card-icon">
                                    <i class="<?php echo e($card['icon']); ?>"></i>
                                </div>
                                <div class="dash-card-toptext">
                                    <span>Quick Access</span>
                                    <strong><?php echo e($card['title']); ?></strong>
                                </div>
                            </div>

                            <div class="dash-card-body">
                                <div class="card-title"><?php echo e($card['title']); ?></div>
                                <div class="card-desc"><?php echo e($card['desc']); ?></div>

                                <div class="card-footer">
                                    <a href="<?php echo e($card['link']); ?>" class="btn">
                                        <i class="fa-solid fa-arrow-right"></i> Open
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>