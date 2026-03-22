<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/config.php';

ob_start();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']      = $user['user_id'];
                $_SESSION['full_name']    = $user['full_name'];
                $_SESSION['email']        = $user['email'];
                $_SESSION['role']         = $user['role'];
                $_SESSION['assigned_pond']= $user['assigned_pond'];
                $_SESSION['last_login']   = $user['last_login'];

                $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $upd->execute([$user['user_id']]);

                try {
                    $log = $pdo->prepare("INSERT INTO activities (user_id, action, ip_address, created_at) VALUES (?, 'login', ?, NOW())");
                    $log->execute([$user['user_id'], $_SERVER['REMOTE_ADDR']]);
                } catch (Exception $e) {}

                ob_end_clean();

                switch ($user['role']) {
                    case 'admin':   header("Location: ../admin/admin_dashboard.php");   break;
                    case 'manager': header("Location: ../manager/manager_dashboard.php"); break;
                    case 'staff':   header("Location: ../staff/staff_dashboard.php");   break;
                    default:        header("Location: login.php?error=invalid_role");
                }
                exit();
            } elseif ($user) {
                $error = "Incorrect password. Please try again.";
            } else {
                $error = "No account found with that email.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>AquaSystem — Login</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
/* ============================================================
   CSS VARIABLES
============================================================ */
:root {
    --bg-deep:      #05111f;
    --bg-panel:     #091929;
    --bg-card:      #0d2035;
    --bg-elevated:  #112840;
    --accent-cyan:  #00e5ff;
    --accent-green: #39ff8a;
    --accent-amber: #ffb800;
    --accent-red:   #ff3b5c;
    --text-primary: #e8f4ff;
    --text-secondary:#8ba8c4;
    --text-muted:   #3f607f;
    --border:       rgba(0,229,255,0.13);
    --border-glow:  rgba(0,229,255,0.38);
    --font-display: 'Syne', sans-serif;
    --font-mono:    'Space Mono', monospace;
    --card-w:       440px;
}

/* ============================================================
   RESET
============================================================ */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { height:100%; }
body {
    min-height: 100vh;
    min-height: 100dvh; /* dynamic viewport for mobile */
    background: var(--bg-deep);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-display);
    color: var(--text-primary);
    overflow-x: hidden;
    padding: env(safe-area-inset-top, 0) env(safe-area-inset-right, 0) env(safe-area-inset-bottom, 0) env(safe-area-inset-left, 0);
    position: relative;
}

/* ============================================================
   ANIMATED BACKGROUND LAYERS
============================================================ */

/* Grid */
.bg-grid {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image:
        linear-gradient(rgba(0,229,255,.028) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,229,255,.028) 1px, transparent 1px);
    background-size: 44px 44px;
    animation: gridDrift 28s linear infinite;
}
@keyframes gridDrift { 0%{background-position:0 0} 100%{background-position:44px 44px} }

/* Orbs */
.bg-orb {
    position: fixed; border-radius: 50%; pointer-events: none; z-index: 0;
    filter: blur(70px);
}
.bg-orb-1 {
    width: 500px; height: 500px;
    top: -150px; left: -150px;
    background: radial-gradient(circle, rgba(0,229,255,.09) 0%, transparent 70%);
    animation: orb1 18s ease-in-out infinite alternate;
}
.bg-orb-2 {
    width: 400px; height: 400px;
    bottom: -120px; right: -120px;
    background: radial-gradient(circle, rgba(57,255,138,.07) 0%, transparent 70%);
    animation: orb2 22s ease-in-out infinite alternate;
}
.bg-orb-3 {
    width: 300px; height: 300px;
    top: 50%; left: 55%;
    background: radial-gradient(circle, rgba(176,108,255,.05) 0%, transparent 70%);
    animation: orb3 15s ease-in-out infinite alternate;
}
@keyframes orb1 { 0%{transform:translate(0,0)} 100%{transform:translate(60px,80px)} }
@keyframes orb2 { 0%{transform:translate(0,0)} 100%{transform:translate(-60px,-60px)} }
@keyframes orb3 { 0%{transform:translate(0,0) scale(1)} 100%{transform:translate(-40px,40px) scale(1.2)} }

/* Floating particles */
.particles { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
.particle {
    position: absolute;
    width: 2px; height: 2px;
    border-radius: 50%;
    background: var(--accent-cyan);
    opacity: 0;
    animation: floatUp linear infinite;
}
@keyframes floatUp {
    0%   { transform:translateY(0) translateX(0); opacity:0; }
    10%  { opacity:.6; }
    90%  { opacity:.2; }
    100% { transform:translateY(-100vh) translateX(30px); opacity:0; }
}

/* ============================================================
   MAIN CARD
============================================================ */
.login-wrap {
    position: relative; z-index: 1;
    width: 100%;
    max-width: var(--card-w);
    margin: 1rem;
    animation: cardAppear .6s cubic-bezier(.22,1,.36,1) both;
}
@keyframes cardAppear {
    from { opacity:0; transform:translateY(28px) scale(.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}

.login-card {
    background: rgba(9,25,41,.92);
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border: 1px solid var(--border);
    border-radius: 28px;
    padding: 2.6rem 2.4rem 2rem;
    box-shadow:
        0 0 0 1px rgba(0,229,255,.06),
        0 32px 64px rgba(0,0,0,.55),
        0 0 80px rgba(0,229,255,.04) inset;
    position: relative;
    overflow: hidden;
}

/* Top glow line */
.login-card::before {
    content: '';
    position: absolute; top: 0; left: 10%; right: 10%; height: 1px;
    background: linear-gradient(90deg, transparent, var(--accent-cyan), transparent);
    opacity: .6;
}

/* Corner accents */
.login-card::after {
    content: '';
    position: absolute;
    top: -1px; right: -1px;
    width: 80px; height: 80px;
    border-top: 1px solid rgba(0,229,255,.35);
    border-right: 1px solid rgba(0,229,255,.35);
    border-radius: 0 28px 0 0;
    pointer-events: none;
}
.corner-bl {
    position: absolute;
    bottom: -1px; left: -1px;
    width: 60px; height: 60px;
    border-bottom: 1px solid rgba(57,255,138,.25);
    border-left: 1px solid rgba(57,255,138,.25);
    border-radius: 0 0 0 28px;
    pointer-events: none;
}

/* ============================================================
   LOGO / HEADER
============================================================ */
.logo-area {
    text-align: center;
    margin-bottom: 2rem;
}
.logo-icon {
    display: inline-flex;
    width: 72px; height: 72px;
    background: linear-gradient(135deg, rgba(0,229,255,.15), rgba(57,255,138,.1));
    border: 1px solid rgba(0,229,255,.25);
    border-radius: 22px;
    align-items: center; justify-content: center;
    font-size: 2rem;
    color: var(--accent-cyan);
    margin-bottom: 1.1rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 0 24px rgba(0,229,255,.15);
}
.logo-icon::after {
    content: '';
    position: absolute; top: -60%; left: -60%;
    width: 32%; height: 220%;
    background: rgba(255,255,255,.3);
    transform: skewX(-20deg);
    animation: iconSheen 4s ease-in-out infinite;
}
@keyframes iconSheen {
    0%,100% { left:-60%; opacity:0; }
    40%     { opacity:1; }
    60%     { left:160%; opacity:0; }
}
.logo-title {
    font-size: 1.65rem; font-weight: 800;
    letter-spacing: -.5px;
    background: linear-gradient(100deg, var(--accent-cyan) 0%, var(--text-primary) 60%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    line-height: 1.1;
    margin-bottom: .3rem;
}
.logo-sub {
    font-family: var(--font-mono);
    font-size: .7rem; color: var(--text-muted);
    letter-spacing: 1.5px; text-transform: uppercase;
}

/* ============================================================
   STATUS PILLS
============================================================ */
.status-row {
    display: flex; justify-content: center; gap: .5rem;
    margin-bottom: 1.8rem; flex-wrap: wrap;
}
.status-pill {
    display: inline-flex; align-items: center; gap: .35rem;
    font-family: var(--font-mono); font-size: .62rem;
    padding: .28rem .7rem; border-radius: 4px;
    letter-spacing: .4px; text-transform: uppercase;
}
.pill-online {
    background: rgba(57,255,138,.1); border: 1px solid rgba(57,255,138,.25); color: var(--accent-green);
}
.pill-secure {
    background: rgba(0,229,255,.08); border: 1px solid rgba(0,229,255,.2); color: var(--accent-cyan);
}
.pill-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; animation: blinkDot 1.5s infinite; }
@keyframes blinkDot { 0%,100%{opacity:1} 50%{opacity:.2} }

/* ============================================================
   ALERTS
============================================================ */
.alert {
    display: flex; align-items: flex-start; gap: .65rem;
    padding: .85rem 1rem; border-radius: 12px;
    margin-bottom: 1.4rem; font-size: .84rem; line-height: 1.45;
    animation: shakeIn .4s ease;
}
@keyframes shakeIn {
    0%  { transform: translateX(-6px); opacity: 0; }
    30% { transform: translateX(4px); }
    60% { transform: translateX(-2px); }
    100%{ transform: translateX(0); opacity: 1; }
}
.alert-error {
    background: rgba(255,59,92,.1); border: 1px solid rgba(255,59,92,.3); color: var(--accent-red);
}
.alert-success {
    background: rgba(57,255,138,.1); border: 1px solid rgba(57,255,138,.3); color: var(--accent-green);
}
.alert-info {
    background: rgba(255,184,0,.1); border: 1px solid rgba(255,184,0,.3); color: var(--accent-amber);
}
.alert i { margin-top: .05rem; flex-shrink: 0; }

/* ============================================================
   FORM
============================================================ */
.form-group {
    margin-bottom: 1.2rem;
    position: relative;
}
.form-label {
    display: flex; align-items: center; gap: .45rem;
    font-size: .72rem; font-weight: 700;
    color: var(--text-muted); letter-spacing: .6px; text-transform: uppercase;
    margin-bottom: .45rem; font-family: var(--font-mono);
}
.form-label i { color: var(--accent-cyan); font-size: .75rem; }

.form-input {
    width: 100%;
    padding: .85rem 1rem .85rem 3rem;
    background: rgba(0,0,0,.25);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text-primary);
    font-family: var(--font-display);
    font-size: .95rem;
    outline: none;
    transition: border-color .25s, box-shadow .25s, background .25s;
    -webkit-appearance: none; /* removes iOS inner shadow */
}
.form-input::placeholder { color: var(--text-muted); }
.form-input:hover {
    border-color: rgba(0,229,255,.25);
    background: rgba(0,229,255,.03);
}
.form-input:focus {
    border-color: var(--accent-cyan);
    background: rgba(0,229,255,.05);
    box-shadow: 0 0 0 3px rgba(0,229,255,.12);
}
/* iOS autofill color fix */
.form-input:-webkit-autofill,
.form-input:-webkit-autofill:hover,
.form-input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--text-primary) !important;
    -webkit-box-shadow: 0 0 0 1000px #0d2035 inset !important;
    transition: background-color 5000s ease-in-out 0s;
}

.input-icon {
    position: absolute;
    left: 1rem; top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: .88rem;
    pointer-events: none;
    transition: color .25s;
}
.form-group:focus-within .input-icon { color: var(--accent-cyan); }

/* Password toggle */
.pw-wrap { position: relative; }
.pw-toggle {
    position: absolute;
    right: 1rem; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: var(--text-muted); cursor: pointer;
    font-size: .85rem; padding: .3rem;
    border-radius: 6px; transition: color .2s;
    -webkit-tap-highlight-color: transparent;
}
.pw-toggle:hover { color: var(--accent-cyan); }

/* ============================================================
   LOGIN BUTTON
============================================================ */
.login-btn {
    width: 100%; padding: 1rem;
    background: linear-gradient(135deg, var(--accent-cyan), #0099cc);
    color: #000; font-weight: 800; font-size: .95rem;
    border: none; border-radius: 14px;
    cursor: pointer; letter-spacing: .4px;
    font-family: var(--font-display);
    position: relative; overflow: hidden;
    transition: transform .2s, box-shadow .2s;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    margin-top: .4rem;
    display: flex; align-items: center; justify-content: center; gap: .6rem;
    min-height: 52px; /* comfortable tap target */
}
.login-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(0,229,255,.35);
}
.login-btn:active:not(:disabled) { transform: scale(.98); }
.login-btn:disabled { opacity: .65; cursor: not-allowed; }

/* Ripple */
.login-btn::after {
    content: '';
    position: absolute; inset: 0;
    background: rgba(255,255,255,0);
    transition: background .3s;
}
.login-btn:active::after { background: rgba(255,255,255,.12); }

/* Loading spinner */
.spinner {
    width: 18px; height: 18px;
    border: 2.5px solid rgba(0,0,0,.25);
    border-top-color: #000;
    border-radius: 50%;
    animation: spin .7s linear infinite;
    display: none;
    flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ============================================================
   DEMO CREDENTIALS
============================================================ */
.demo-section {
    margin-top: 1.6rem;
    border-top: 1px solid var(--border);
    padding-top: 1.4rem;
}
.demo-header {
    display: flex; align-items: center; gap: .5rem;
    font-size: .72rem; font-family: var(--font-mono);
    color: var(--text-muted); text-transform: uppercase;
    letter-spacing: .7px; margin-bottom: 1rem;
    cursor: pointer; user-select: none;
    -webkit-tap-highlight-color: transparent;
}
.demo-header i { color: var(--accent-cyan); font-size: .78rem; }
.demo-chevron { margin-left: auto; transition: transform .3s; }
.demo-section.open .demo-chevron { transform: rotate(180deg); }

.demo-list {
    display: none;
    flex-direction: column; gap: .5rem;
}
.demo-section.open .demo-list { display: flex; }

.demo-row {
    display: flex; align-items: center; gap: .75rem;
    padding: .7rem .85rem;
    background: rgba(0,0,0,.2);
    border: 1px solid var(--border);
    border-radius: 10px;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}
.demo-row:hover, .demo-row:active {
    border-color: rgba(0,229,255,.3);
    background: rgba(0,229,255,.04);
}
.demo-role-icon {
    width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: .82rem; flex-shrink: 0;
}
.icon-admin   { background: rgba(176,108,255,.15); color: #b06cff; }
.icon-manager { background: rgba(0,229,255,.12);   color: var(--accent-cyan); }
.icon-staff   { background: rgba(57,255,138,.12);  color: var(--accent-green); }

.demo-info { flex: 1; min-width: 0; }
.demo-role { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-bottom: .12rem; }
.demo-creds {
    font-family: var(--font-mono); font-size: .67rem;
    color: var(--text-muted); white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
}
.demo-fill-btn {
    font-family: var(--font-mono); font-size: .62rem;
    color: var(--accent-cyan); letter-spacing: .3px;
    opacity: .6; flex-shrink: 0;
}

/* ============================================================
   FOOTER
============================================================ */
.page-footer {
    text-align: center; margin-top: 1.3rem;
    font-family: var(--font-mono); font-size: .64rem;
    color: var(--text-muted); letter-spacing: .5px;
    line-height: 1.7;
}

/* ============================================================
   RESPONSIVE — MOBILE FIRST
============================================================ */

/* Small phones (< 360px) */
@media (max-width: 359px) {
    .login-card { padding: 2rem 1.3rem 1.5rem; border-radius: 22px; }
    .logo-icon  { width: 60px; height: 60px; font-size: 1.6rem; }
    .logo-title { font-size: 1.35rem; }
    .form-input { font-size: .9rem; padding: .78rem .85rem .78rem 2.8rem; }
    .login-btn  { font-size: .88rem; padding: .9rem; }
    .demo-creds { font-size: .6rem; }
}

/* Standard phones (360–480px) */
@media (min-width: 360px) and (max-width: 480px) {
    .login-card { padding: 2.2rem 1.6rem 1.6rem; }
    .login-btn  { padding: .95rem; }
}

/* Larger phones / small tablets (481–767px) */
@media (min-width: 481px) and (max-width: 767px) {
    :root { --card-w: 420px; }
    .login-card { padding: 2.4rem 2rem 1.8rem; }
}

/* Tablets landscape and up (768px+) — full card */
@media (min-width: 768px) {
    :root { --card-w: 440px; }
}

/* Landscape phone orientation */
@media (max-height: 600px) and (orientation: landscape) {
    body { align-items: flex-start; padding-top: 1rem; }
    .login-wrap { margin: .5rem auto; }
    .login-card { padding: 1.5rem 2rem; }
    .logo-area  { margin-bottom: 1.1rem; }
    .logo-icon  { width: 52px; height: 52px; font-size: 1.5rem; margin-bottom: .6rem; }
    .logo-title { font-size: 1.3rem; }
    .logo-sub   { display: none; }
    .status-row { margin-bottom: 1rem; }
    .form-group { margin-bottom: .9rem; }
    .demo-section { margin-top: 1rem; padding-top: 1rem; }
}

/* Very tall screens — vertically center with max height breathing room */
@media (min-height: 900px) {
    .login-wrap { margin: auto; }
}

/* Touch device enhancements */
@media (hover: none) and (pointer: coarse) {
    .form-input  { font-size: 16px; } /* prevents iOS zoom on focus */
    .login-btn   { min-height: 54px; }
    .demo-row    { min-height: 52px; }
}
</style>
</head>
<body>

<!-- BACKGROUND LAYERS -->
<div class="bg-grid"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>
<div class="bg-orb bg-orb-3"></div>
<div class="particles" id="particles"></div>

<!-- LOGIN WRAPPER -->
<div class="login-wrap">
    <div class="login-card">
        <div class="corner-bl"></div>

        <!-- LOGO -->
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-fish"></i></div>
            <div class="logo-title">AquaSystem</div>
            <div class="logo-sub">Organic Matter Detection · Tilapia</div>
        </div>

        <!-- STATUS PILLS -->
        <div class="status-row">
            <span class="status-pill pill-online">
                <span class="pill-dot"></span> System Online
            </span>
            <span class="status-pill pill-secure">
                <i class="fas fa-shield-alt" style="font-size:.6rem;"></i> Secure Login
            </span>
        </div>

        <!-- PHP ALERTS -->
        <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid_role'): ?>
        <div class="alert alert-error">
            <i class="fas fa-ban"></i>
            <span>Invalid user role. Please contact the administrator.</span>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['loggedout']) && $_GET['loggedout'] == 1): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>You have been successfully logged out.</span>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
        <div class="alert alert-info">
            <i class="fas fa-clock"></i>
            <span>Your session expired. Please log in again.</span>
        </div>
        <?php endif; ?>

        <!-- FORM -->
        <form method="POST" id="loginForm" novalidate>

            <!-- Email -->
            <div class="form-group">
                <label class="form-label" for="emailInput">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <div style="position:relative;">
                    <i class="fas fa-at input-icon"></i>
                    <input
                        type="email"
                        id="emailInput"
                        name="email"
                        class="form-input"
                        placeholder="you@example.com"
                        autocomplete="email"
                        inputmode="email"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label" for="passwordInput">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="pw-wrap" style="position:relative;">
                    <i class="fas fa-key input-icon"></i>
                    <input
                        type="password"
                        id="passwordInput"
                        name="password"
                        class="form-input"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show/hide password">
                        <i class="fas fa-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="login-btn" id="loginBtn">
                <span id="btnText">Sign In</span>
                <i class="fas fa-arrow-right" id="btnArrow"></i>
                <div class="spinner" id="btnSpinner"></div>
            </button>

        </form>

        <!-- DEMO CREDENTIALS (collapsible) -->
        <div class="demo-section" id="demoSection">
            <div class="demo-header" onclick="toggleDemo()" role="button" tabindex="0" onkeydown="if(event.key==='Enter')toggleDemo()">
                <i class="fas fa-key"></i>
                <span>Demo Credentials</span>
                <i class="fas fa-chevron-down demo-chevron"></i>
            </div>
            <div class="demo-list" id="demoList">

                <div class="demo-row" onclick="fillCreds('admin@company.com','admin123')" role="button" tabindex="0" onkeydown="if(event.key==='Enter')fillCreds('admin@company.com','admin123')">
                    <div class="demo-role-icon icon-admin"><i class="fas fa-user-shield"></i></div>
                    <div class="demo-info">
                        <div class="demo-role" style="color:#b06cff;">Administrator</div>
                        <div class="demo-creds">admin@company.com · admin123</div>
                    </div>
                    <div class="demo-fill-btn">USE <i class="fas fa-arrow-right"></i></div>
                </div>

                <div class="demo-row" onclick="fillCreds('manager@company.com','manager123')" role="button" tabindex="0" onkeydown="if(event.key==='Enter')fillCreds('manager@company.com','manager123')">
                    <div class="demo-role-icon icon-manager"><i class="fas fa-user-tie"></i></div>
                    <div class="demo-info">
                        <div class="demo-role" style="color:var(--accent-cyan);">Manager</div>
                        <div class="demo-creds">manager@company.com · manager123</div>
                    </div>
                    <div class="demo-fill-btn">USE <i class="fas fa-arrow-right"></i></div>
                </div>

                <div class="demo-row" onclick="fillCreds('staff1@company.com','staff123')" role="button" tabindex="0" onkeydown="if(event.key==='Enter')fillCreds('staff1@company.com','staff123')">
                    <div class="demo-role-icon icon-staff"><i class="fas fa-user"></i></div>
                    <div class="demo-info">
                        <div class="demo-role" style="color:var(--accent-green);">Staff</div>
                        <div class="demo-creds">staff1@company.com · staff123</div>
                    </div>
                    <div class="demo-fill-btn">USE <i class="fas fa-arrow-right"></i></div>
                </div>

            </div>
        </div>

        <!-- FOOTER -->
        <div class="page-footer">
            &copy; 2026 Organic-Matter Detection in Tilapia<br>
            Manolo Fortich, Bukidnon · All rights reserved
        </div>

    </div><!-- /login-card -->
</div><!-- /login-wrap -->

<script>
// ============================================================
// PARTICLES
// ============================================================
(function() {
    const wrap = document.getElementById('particles');
    const count = window.innerWidth < 480 ? 12 : 22;
    for (let i = 0; i < count; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.cssText = `
            left: ${Math.random()*100}%;
            bottom: ${Math.random()*-20}%;
            width: ${1+Math.random()*2}px;
            height: ${1+Math.random()*2}px;
            animation-duration: ${8+Math.random()*14}s;
            animation-delay: ${Math.random()*12}s;
            opacity: ${0.2+Math.random()*0.5};
        `;
        wrap.appendChild(p);
    }
})();

// ============================================================
// PASSWORD TOGGLE
// ============================================================
document.getElementById('pwToggle').addEventListener('click', function() {
    const inp  = document.getElementById('passwordInput');
    const icon = document.getElementById('pwIcon');
    const show = inp.type === 'password';
    inp.type   = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    inp.focus();
});

// ============================================================
// DEMO CREDENTIALS
// ============================================================
function toggleDemo() {
    document.getElementById('demoSection').classList.toggle('open');
}

function fillCreds(email, password) {
    const eInp = document.getElementById('emailInput');
    const pInp = document.getElementById('passwordInput');

    // Animate fill
    eInp.style.transition = 'border-color .3s, box-shadow .3s';
    pInp.style.transition = 'border-color .3s, box-shadow .3s';

    eInp.value = email;
    pInp.value = password;

    // Flash border to show fill
    eInp.style.borderColor = 'var(--accent-cyan)';
    pInp.style.borderColor = 'var(--accent-cyan)';
    eInp.style.boxShadow   = '0 0 0 3px rgba(0,229,255,.15)';
    pInp.style.boxShadow   = '0 0 0 3px rgba(0,229,255,.15)';

    setTimeout(() => {
        eInp.style.borderColor = '';
        pInp.style.borderColor = '';
        eInp.style.boxShadow   = '';
        pInp.style.boxShadow   = '';
    }, 900);

    // Scroll to button on mobile
    if (window.innerWidth < 768) {
        setTimeout(() => {
            document.getElementById('loginBtn').scrollIntoView({ behavior:'smooth', block:'center' });
        }, 150);
    }
}

// ============================================================
// FORM SUBMIT — loading state
// ============================================================
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const email = document.getElementById('emailInput').value.trim();
    const pass  = document.getElementById('passwordInput').value;

    if (!email || !pass) {
        e.preventDefault();
        shakeCard();
        return;
    }

    const btn     = document.getElementById('loginBtn');
    const txt     = document.getElementById('btnText');
    const arrow   = document.getElementById('btnArrow');
    const spinner = document.getElementById('btnSpinner');

    btn.disabled         = true;
    txt.textContent      = 'Signing in…';
    arrow.style.display  = 'none';
    spinner.style.display= 'block';
});

function shakeCard() {
    const card = document.querySelector('.login-card');
    card.style.animation = 'none';
    card.style.transform = 'translateX(-8px)';
    setTimeout(() => {
        card.style.transition = 'transform .4s cubic-bezier(.36,.07,.19,.97)';
        card.style.transform  = 'translateX(0)';
    }, 30);
    setTimeout(() => { card.style.transition = ''; }, 500);
}

// ============================================================
// AUTO-OPEN DEMO IF QUERY PARAM
// ============================================================
if (new URLSearchParams(window.location.search).get('demo') === '1') {
    document.getElementById('demoSection').classList.add('open');
}

// ============================================================
// INPUT LABEL ANIMATION — add active class on fill
// ============================================================
['emailInput','passwordInput'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
        el.classList.toggle('has-value', el.value.length > 0);
    });
    // Check on load (in case browser autofills)
    setTimeout(() => {
        if (el.value) el.classList.add('has-value');
    }, 200);
});
</script>
</body>
</html>