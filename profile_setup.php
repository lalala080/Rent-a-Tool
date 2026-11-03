<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = "";
$messageType = "";

// Check if profile already exists
$profile = null;
$check = $conn->prepare("SELECT * FROM user_profiles WHERE login_id = ?");
$check->bind_param("i", $userId);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $profile = $result->fetch_assoc();
}
$check->close();

// SAVE / UPDATE PROFILE
if (isset($_POST['save_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    $profileImageName = $profile['profile_image'] ?? null;
    $validIdImageName = $profile['valid_id_image'] ?? null;

    $uploadDir = "uploads/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

    // Upload profile image
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $mimeType = mime_content_type($_FILES['profile_image']['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) {
            $message = "Invalid profile image type. Only JPG, PNG, and WEBP are allowed.";
            $messageType = "error";
        } else {
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $profileImageName = uniqid('profile_', true) . "." . strtolower($ext);
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $profileImageName)) {
                $message = "Failed to upload profile image.";
                $messageType = "error";
            }
        }
    }

    // Upload valid ID
    if ($message === "" && isset($_FILES['valid_id_image']) && $_FILES['valid_id_image']['error'] === 0) {
        $mimeType = mime_content_type($_FILES['valid_id_image']['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) {
            $message = "Invalid valid ID image type. Only JPG, PNG, and WEBP are allowed.";
            $messageType = "error";
        } else {
            $ext = pathinfo($_FILES['valid_id_image']['name'], PATHINFO_EXTENSION);
            $validIdImageName = uniqid('validid_', true) . "." . strtolower($ext);
            if (!move_uploaded_file($_FILES['valid_id_image']['tmp_name'], $uploadDir . $validIdImageName)) {
                $message = "Failed to upload valid ID image.";
                $messageType = "error";
            }
        }
    }

    if ($fullName === "") {
        $message = "Full name is required.";
        $messageType = "error";
    } elseif ($message === "") {
        if ($profile) {
            $stmt = $conn->prepare("
                UPDATE user_profiles
                SET full_name = ?, phone = ?, address = ?, profile_image = ?, valid_id_image = ?
                WHERE login_id = ?
            ");
            $stmt->bind_param("sssssi", $fullName, $phone, $address, $profileImageName, $validIdImageName, $userId);

            if ($stmt->execute()) {
                $message = "Profile updated successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to update profile.";
                $messageType = "error";
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO user_profiles (login_id, full_name, phone, address, profile_image, valid_id_image)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssss", $userId, $fullName, $phone, $address, $profileImageName, $validIdImageName);

            if ($stmt->execute()) {
                $message = "Profile saved successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to save profile.";
                $messageType = "error";
            }
            $stmt->close();
        }

        // Refresh data
        $reload = $conn->prepare("SELECT * FROM user_profiles WHERE login_id = ?");
        $reload->bind_param("i", $userId);
        $reload->execute();
        $profile = $reload->get_result()->fetch_assoc();
        $reload->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Setup - Niella's Rent-A-Tool</title>
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --bg-1: #eef6ff;
            --bg-2: #f8fbff;
            --border: rgba(148, 163, 184, 0.20);
            --text: #0f172a;
            --muted: #5b6b82;
            --primary: #2563eb;
            --primary-2: #06b6d4;
            --shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.08);
            --shadow: 0 20px 50px rgba(37, 99, 235, 0.10);
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
            max-width: 1180px;
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
            padding: 30px;
            box-shadow: var(--shadow-soft);
        }

        .section-header {
            margin-bottom: 24px;
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

        .message {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            align-items: start;
        }

        .profile-side,
        .profile-form-card {
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 24px;
            box-shadow: var(--shadow);
        }

        .profile-side {
            padding: 24px;
            position: sticky;
            top: 24px;
        }

        .avatar-wrap {
            width: 140px;
            height: 140px;
            margin: 0 auto 18px;
            border-radius: 28px;
            overflow: hidden;
            background: linear-gradient(135deg, #dbeafe, #cffafe);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 14px 28px rgba(37, 99, 235, 0.12);
        }

        .avatar-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 52px;
            color: #2563eb;
            font-weight: 800;
        }

        .side-title {
            text-align: center;
            margin: 0 0 6px;
            font-size: 1.4rem;
        }

        .side-sub {
            text-align: center;
            margin: 0 0 22px;
            color: var(--muted);
            font-size: 0.96rem;
        }

        .mini-info {
            display: grid;
            gap: 12px;
        }

        .mini-card {
            background: linear-gradient(135deg, rgba(239,246,255,0.95), rgba(236,254,255,0.95));
            border: 1px solid rgba(125, 211, 252, 0.28);
            border-radius: 18px;
            padding: 14px 16px;
        }

        .mini-label {
            color: #64748b;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .mini-value {
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
            word-break: break-word;
        }

        .profile-form-card {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 18px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 800;
            color: #334155;
            font-size: 0.95rem;
        }

        input[type="text"],
        textarea,
        input[type="file"] {
            width: 100%;
            padding: 13px 14px;
            border-radius: 14px;
            border: 1px solid #dbe3ee;
            background: rgba(255, 255, 255, 0.92);
            color: #0f172a;
            font-size: 0.97rem;
            outline: none;
            transition: 0.25s ease;
        }

        input[type="text"]:focus,
        textarea:focus,
        input[type="file"]:focus {
            border-color: rgba(37, 99, 235, 0.35);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .helper-text {
            margin-top: 4px;
            font-size: 0.85rem;
            color: #64748b;
        }

        .preview-box {
            margin-top: 10px;
            padding: 14px;
            border-radius: 18px;
            border: 1px solid rgba(226, 232, 240, 0.95);
            background: rgba(248, 250, 252, 0.95);
        }

        .preview-title {
            font-size: 0.88rem;
            font-weight: 800;
            color: #475569;
            margin-bottom: 10px;
        }

        .preview-box img {
            width: 100%;
            max-width: 220px;
            height: 180px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid rgba(203, 213, 225, 0.9);
            display: block;
        }

        .form-actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .save-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 14px;
            padding: 13px 20px;
            font-weight: 800;
            color: white;
            cursor: pointer;
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.20);
            transition: 0.25s ease;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 28px rgba(37, 99, 235, 0.25);
        }

        @media (max-width: 980px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }

            .profile-side {
                position: static;
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

            .section-card,
            .profile-form-card,
            .profile-side {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .avatar-wrap {
                width: 120px;
                height: 120px;
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
            <a href="my_rentals.php">My Rentals</a>
            <a href="profile_setup.php" class="active">Profile Setup</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="section-card">

        <a href="dashboard.php" style="
            display:inline-block;
            margin-bottom:20px;
            padding:8px 14px;
            border:1px solid #ccc;
            border-radius:6px;
            text-decoration:none;
            color:#000;
            background:#f9f9f9;
        ">← Back</a>

        <div class="section-header">
            <h2>Profile Setup</h2>
            <p>Update your personal details, upload a profile image, and submit your valid ID for account verification.</p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-layout">
            <div class="profile-side">
                <div class="avatar-wrap">
                    <?php if (!empty($profile['profile_image'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile Image">
                    <?php else: ?>
                        <div class="avatar-placeholder">👤</div>
                    <?php endif; ?>
                </div>

                <h3 class="side-title">
                    <?php echo htmlspecialchars($profile['full_name'] ?? ($_SESSION['username'] ?? 'Your Profile')); ?>
                </h3>
                <p class="side-sub">Keep your renter information complete and updated.</p>

                <div class="mini-info">
                    <div class="mini-card">
                        <div class="mini-label">Phone</div>
                        <div class="mini-value"><?php echo htmlspecialchars($profile['phone'] ?? 'Not set'); ?></div>
                    </div>
                    <div class="mini-card">
                        <div class="mini-label">Address</div>
                        <div class="mini-value"><?php echo htmlspecialchars($profile['address'] ?? 'Not set'); ?></div>
                    </div>
                    <div class="mini-card">
                        <div class="mini-label">Valid ID</div>
                        <div class="mini-value"><?php echo !empty($profile['valid_id_image']) ? 'Uploaded' : 'Not uploaded'; ?></div>
                    </div>
                </div>
            </div>

            <div class="profile-form-card">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="input-group">
                            <label for="full_name">Full Name</label>
                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="input-group">
                            <label for="phone">Phone Number</label>
                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>"
                            >
                        </div>

                        <div class="input-group full">
                            <label for="address">Address</label>
                            <textarea
                                id="address"
                                name="address"
                            ><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="input-group">
                            <label for="profile_image">Profile Image</label>
                            <input
                                type="file"
                                id="profile_image"
                                name="profile_image"
                                accept=".jpg,.jpeg,.png,.webp,image/*"
                            >
                            <div class="helper-text">Accepted formats: JPG, PNG, WEBP</div>

                            <?php if (!empty($profile['profile_image'])): ?>
                                <div class="preview-box">
                                    <div class="preview-title">Current Profile Image</div>
                                    <img src="uploads/<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile Image">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="input-group">
                            <label for="valid_id_image">Valid ID Image</label>
                            <input
                                type="file"
                                id="valid_id_image"
                                name="valid_id_image"
                                accept=".jpg,.jpeg,.png,.webp,image/*"
                            >
                            <div class="helper-text">Upload a clear government-issued ID</div>

                            <?php if (!empty($profile['valid_id_image'])): ?>
                                <div class="preview-box">
                                    <div class="preview-title">Current Valid ID</div>
                                    <img src="uploads/<?php echo htmlspecialchars($profile['valid_id_image']); ?>" alt="Valid ID">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="save_profile" class="save-btn">Save and Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>