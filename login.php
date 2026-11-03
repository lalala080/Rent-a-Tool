<?php
session_start();
require 'db.php';

$message = "";
$messageType = "";
$activeForm = "login";

// If already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] === "") {
        $_SESSION['role'] = 'renter';
    }

    header("Location: dashboard.php");
    exit;
}

// REGISTER
if (isset($_POST['register'])) {
    $activeForm = "register";

    $username = trim($_POST['reg_username'] ?? '');
    $email = trim($_POST['reg_email'] ?? '');
    $password = $_POST['reg_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === "" || $email === "" || $password === "" || $confirm === "") {
        $message = "Please fill in all fields.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $messageType = "error";
    } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } else {
        $check = $conn->prepare("SELECT id FROM login WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "Username or email already exists.";
            $messageType = "error";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $defaultRole = "renter";

            $stmt = $conn->prepare("INSERT INTO login (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $hashedPassword, $defaultRole);

            if ($stmt->execute()) {
                $message = "Registered successfully. You can now log in.";
                $messageType = "success";
                $activeForm = "login";
            } else {
                $message = "Registration failed.";
                $messageType = "error";
            }

            $stmt->close();
        }

        $check->close();
    }
}

// LOGIN
if (isset($_POST['login'])) {
    $activeForm = "login";

    $loginInput = trim($_POST['log_username'] ?? '');
    $password = $_POST['log_password'] ?? '';

    if ($loginInput === "" || $password === "") {
        $message = "Please enter username/email and password.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("SELECT * FROM login WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $loginInput, $loginInput);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if ((int)$user['is_deactivated'] === 1) {
                $message = "Account is deactivated.";
                $messageType = "error";
            } else {
                if (password_verify($password, $user['password'])) {
                    $reset = $conn->prepare("UPDATE login SET attempts = 0 WHERE id = ?");
                    $reset->bind_param("i", $user['id']);
                    $reset->execute();
                    $reset->close();

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = !empty($user['role']) ? $user['role'] : 'renter';

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $newAttempts = (int)$user['attempts'] + 1;

                    if ($newAttempts >= 3) {
                        $update = $conn->prepare("UPDATE login SET attempts = ?, is_deactivated = 1 WHERE id = ?");
                        $update->bind_param("ii", $newAttempts, $user['id']);
                        $update->execute();
                        $update->close();

                        $message = "Account deactivated after 3 failed attempts.";
                        $messageType = "error";
                    } else {
                        $update = $conn->prepare("UPDATE login SET attempts = ? WHERE id = ?");
                        $update->bind_param("ii", $newAttempts, $user['id']);
                        $update->execute();
                        $update->close();

                        $remaining = 3 - $newAttempts;
                        $message = "Wrong password. Attempts: {$newAttempts}. Remaining: {$remaining}.";
                        $messageType = "error";
                    }
                }
            }
        } else {
            $message = "User not found.";
            $messageType = "error";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rent-A-Tool</title>

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
    font-family:'Segoe UI',sans-serif;
}

:root{
    --bg:#020617;
    --panel:#081122;
    --panel2:#0b172d;
    --line:rgba(255,255,255,.08);
    --text:#f8fafc;
    --muted:#94a3b8;
    --primary:#22d3ee;
    --primary2:#06b6d4;
    --danger:#f87171;
    --success:#4ade80;
}

body{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px;
    background:
        radial-gradient(circle at 15% 20%, rgba(34,211,238,.12), transparent 22%),
        radial-gradient(circle at 85% 80%, rgba(59,130,246,.10), transparent 22%),
        linear-gradient(135deg, #020617 0%, #06101f 45%, #020617 100%);
    color:var(--text);
}

.wrapper{
    width:100%;
    max-width:1160px;
    min-height:660px;
    display:grid;
    grid-template-columns:1.15fr .95fr;
    background:rgba(4,10,24,.84);
    border:1px solid var(--line);
    border-radius:30px;
    overflow:hidden;
    backdrop-filter:blur(12px);
    box-shadow:
        0 30px 80px rgba(0,0,0,.45),
        inset 0 1px 0 rgba(255,255,255,.04);
}

.left-panel{
    position:relative;
    padding:68px 58px;
    display:flex;
    flex-direction:column;
    justify-content:center;
    background:
        linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
}

.left-panel::before{
    content:"";
    position:absolute;
    width:360px;
    height:360px;
    top:-120px;
    left:-80px;
    border-radius:50%;
    background:radial-gradient(circle, rgba(34,211,238,.18), transparent 65%);
    filter:blur(10px);
}

.left-panel::after{
    content:"";
    position:absolute;
    width:280px;
    height:280px;
    right:-80px;
    bottom:-80px;
    border-radius:50%;
    background:radial-gradient(circle, rgba(59,130,246,.14), transparent 65%);
    filter:blur(10px);
}

.left-panel > *{
    position:relative;
    z-index:2;
}

.brand{
    display:inline-flex;
    align-items:center;
    gap:10px;
    width:fit-content;
    padding:10px 16px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.08);
    background:rgba(255,255,255,.03);
    color:#dbeafe;
    font-size:.95rem;
    margin-bottom:28px;
    letter-spacing:.3px;
}

.brand-dot{
    width:10px;
    height:10px;
    border-radius:50%;
    background:linear-gradient(135deg, var(--primary), #67e8f9);
    box-shadow:0 0 14px rgba(34,211,238,.8);
}

.subtitle{
    font-size:3.4rem;
    line-height:1.05;
    font-weight:800;
    letter-spacing:-1.5px;
    max-width:560px;
}

.desc{
    margin-top:18px;
    max-width:520px;
    color:var(--muted);
    line-height:1.8;
    font-size:1rem;
}

.feature-list{
    margin-top:34px;
    display:grid;
    gap:14px;
    max-width:480px;
}

.feature-item{
    display:flex;
    align-items:center;
    gap:12px;
    color:#cbd5e1;
    font-size:.95rem;
}

.feature-icon{
    width:34px;
    height:34px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(34,211,238,.10);
    border:1px solid rgba(34,211,238,.18);
    color:var(--primary);
    font-size:1rem;
}

.right-panel{
    padding:34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-left:1px solid rgba(255,255,255,.05);
    background:
        linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
}

.form-card{
    width:100%;
    max-width:410px;
    padding:30px;
    border-radius:26px;
    background:linear-gradient(180deg, rgba(11,23,45,.92), rgba(8,18,35,.92));
    border:1px solid rgba(255,255,255,.06);
    box-shadow:
        0 20px 50px rgba(0,0,0,.35),
        inset 0 1px 0 rgba(255,255,255,.03);
}

.form-head{
    margin-bottom:22px;
}

.form-kicker{
    color:var(--primary);
    font-size:.82rem;
    font-weight:700;
    letter-spacing:1.2px;
    text-transform:uppercase;
    margin-bottom:8px;
}

.form-title{
    font-size:1.8rem;
    font-weight:800;
    margin-bottom:8px;
}

.form-subtitle{
    color:var(--muted);
    font-size:.95rem;
    line-height:1.6;
}

.tabs{
    display:flex;
    gap:8px;
    padding:6px;
    border-radius:16px;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.06);
    margin-bottom:20px;
}

.tab-btn{
    flex:1;
    padding:12px 16px;
    border:none;
    border-radius:12px;
    background:transparent;
    color:#94a3b8;
    font-weight:700;
    cursor:pointer;
    transition:.25s ease;
}

.tab-btn:hover{
    color:#e2e8f0;
    background:rgba(255,255,255,.03);
}

.tab-btn.active{
    color:#06202a;
    background:linear-gradient(135deg, var(--primary2), #67e8f9);
    box-shadow:0 10px 20px rgba(34,211,238,.24);
}

.message{
    margin-bottom:18px;
    padding:13px 14px;
    border-radius:14px;
    font-size:.88rem;
    line-height:1.5;
    border:1px solid transparent;
}

.error{
    color:#fecaca;
    background:rgba(248,113,113,.10);
    border-color:rgba(248,113,113,.18);
}

.success{
    color:#bbf7d0;
    background:rgba(74,222,128,.10);
    border-color:rgba(74,222,128,.18);
}

.input-group{
    margin-bottom:16px;
}

.input-group label{
    display:block;
    margin-bottom:8px;
    color:#dbeafe;
    font-size:.84rem;
    font-weight:600;
}

.input-wrap{
    position:relative;
}

.input-group input{
    width:100%;
    padding:14px 16px;
    border-radius:14px;
    border:1px solid rgba(148,163,184,.20);
    background:rgba(255,255,255,.04);
    color:#fff;
    font-size:.95rem;
    transition:.25s ease;
}

.input-group input::placeholder{
    color:#64748b;
}

.input-group input:focus{
    outline:none;
    border-color:rgba(34,211,238,.70);
    box-shadow:0 0 0 4px rgba(34,211,238,.10);
    background:rgba(255,255,255,.05);
}

.password-toggle{
    position:absolute;
    right:12px;
    top:50%;
    transform:translateY(-50%);
    background:none;
    border:none;
    color:#94a3b8;
    cursor:pointer;
    font-size:.82rem;
    font-weight:700;
    padding:4px 6px;
}

.password-toggle:hover{
    color:#e2e8f0;
}

.btn{
    width:100%;
    margin-top:8px;
    padding:14px 16px;
    border:none;
    border-radius:14px;
    cursor:pointer;
    font-weight:800;
    letter-spacing:1.5px;
    color:#07222b;
    background:linear-gradient(135deg, var(--primary2), #67e8f9);
    box-shadow:0 16px 30px rgba(34,211,238,.18);
    transition:.25s ease;
}

.btn:hover{
    transform:translateY(-1px);
    box-shadow:0 18px 34px rgba(34,211,238,.24);
}

.form-footer{
    margin-top:16px;
    text-align:center;
    color:#64748b;
    font-size:.82rem;
}

.hidden{
    display:none;
}

@media (max-width: 960px){
    .wrapper{
        grid-template-columns:1fr;
        min-height:auto;
    }

    .left-panel{
        display:none;
    }

    .right-panel{
        border-left:none;
        padding:22px;
    }

    .form-card{
        max-width:100%;
        padding:24px 20px;
    }
}

@media (max-width: 480px){
    body{
        padding:14px;
    }

    .form-title{
        font-size:1.5rem;
    }

    .tab-btn{
        font-size:.9rem;
        padding:11px 10px;
    }
}
</style>
</head>

<body>

<div class="wrapper">

    <div class="left-panel">
        <div class="brand">
            <span class="brand-dot"></span>
            <span>RT • Rent-A-Tool</span>
        </div>

        <div class="subtitle">
            Borrow Tools.<br>
            Secure Every Rental.
        </div>

        <div class="desc">
            Access your account to manage rentals, deposits, and secure transactions with a cleaner and more modern experience.
        </div>

        <div class="feature-list">
        
        </div>
    </div>

    <div class="right-panel">
        <div class="form-card">

            <div class="form-head">
                <div class="form-kicker">Account Access</div>
                <div class="form-title">Welcome back</div>
                <div class="form-subtitle">Sign in to continue or create a new account.</div>
            </div>

            <div class="tabs">
                <button type="button" class="tab-btn <?php echo $activeForm==='login'?'active':'';?>" onclick="showForm(event, 'login')">Sign in</button>
                <button type="button" class="tab-btn <?php echo $activeForm==='register'?'active':'';?>" onclick="showForm(event, 'register')">Sign up</button>
            </div>

            <?php if($message!=""): ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div id="loginForm" class="<?php echo $activeForm==='login'?'':'hidden';?>">
                <form method="POST">
                    <div class="input-group">
                        <label>Username or Email</label>
                        <div class="input-wrap">
                            <input type="text" name="log_username" placeholder="Enter your username or email" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <div class="input-wrap">
                            <input type="password" name="log_password" id="loginPassword" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', this)">Show</button>
                        </div>
                    </div>

                    <button name="login" class="btn">LOGIN</button>
                </form>

                <div class="form-footer">
                    Secure login for renters and users
                </div>
            </div>

            <div id="registerForm" class="<?php echo $activeForm==='register'?'':'hidden';?>">
                <form method="POST">
                    <div class="input-group">
                        <label>Username</label>
                        <div class="input-wrap">
                            <input type="text" name="reg_username" placeholder="Choose a username" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Email</label>
                        <div class="input-wrap">
                            <input type="email" name="reg_email" placeholder="Enter your email address" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <div class="input-wrap">
                            <input type="password" name="reg_password" id="registerPassword" placeholder="Create your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('registerPassword', this)">Show</button>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Confirm Password</label>
                        <div class="input-wrap">
                            <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">Show</button>
                        </div>
                    </div>

                    <button name="register" class="btn">REGISTER</button>
                </form>

                <div class="form-footer">
                    Create your account to start renting tools
                </div>
            </div>

        </div>
    </div>

</div>

<script>
function showForm(event, form){
    document.getElementById('loginForm').style.display = form === 'login' ? 'block' : 'none';
    document.getElementById('registerForm').style.display = form === 'register' ? 'block' : 'none';

    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
}

function togglePassword(inputId, btn){
    const input = document.getElementById(inputId);
    if(input.type === "password"){
        input.type = "text";
        btn.textContent = "Hide";
    }else{
        input.type = "password";
        btn.textContent = "Show";
    }
}
</script>

</body>
</html>