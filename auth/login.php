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
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
/* ============================================================
   CSS VARIABLES
============================================================ */
:root {
    --sand-50:    #faf8f5;
    --sand-100:   #f3ede4;
    --sand-200:   #e8dfd0;
    --sand-300:   #d4c4ad;
    --teal-600:   #2d7a6e;
    --teal-700:   #245f55;
    --teal-800:   #1a4740;
    --ink-900:    #1c1f21;
    --ink-700:    #3d4347;
    --ink-500:    #6b7278;
    --ink-300:    #a8b0b8;
    --ink-100:    #eaecee;
    --white:      #ffffff;
    --error-bg:   #fdf2f2;
    --error-bd:   #f0c8c8;
    --error-txt:  #8b2635;
    --success-bg: #f0f7f4;
    --success-bd: #b8dbd2;
    --success-txt:#1a5c4e;
    --warn-bg:    #fdf8ee;
    --warn-bd:    #e8d5a0;
    --warn-txt:   #7a5c1a;
    --font-serif: 'Instrument Serif', Georgia, serif;
    --font-sans:  'DM Sans', system-ui, sans-serif;
    --radius-sm:  8px;
    --radius-md:  14px;
    --radius-lg:  20px;
    --radius-xl:  28px;
    --shadow-sm:  0 1px 3px rgba(28,31,33,.07), 0 1px 2px rgba(28,31,33,.05);
    --shadow-md:  0 4px 16px rgba(28,31,33,.08), 0 1px 4px rgba(28,31,33,.06);
    --shadow-lg:  0 16px 48px rgba(28,31,33,.10), 0 4px 16px rgba(28,31,33,.07);
    --card-w:     440px;
    --transition: .22s cubic-bezier(.4,0,.2,1);
}

/* ============================================================
   RESET
============================================================ */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { height:100%; }
body {
    min-height: 100vh;
    min-height: 100dvh;
    background: var(--sand-50);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-sans);
    color: var(--ink-900);
    overflow-x: hidden;
    padding: env(safe-area-inset-top, 0) env(safe-area-inset-right, 0) env(safe-area-inset-bottom, 0) env(safe-area-inset-left, 0);
    position: relative;
}

/* ============================================================
   BACKGROUND — subtle organic texture
============================================================ */
.bg-texture {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background:
        radial-gradient(ellipse 80% 60% at 15% 10%, rgba(45,122,110,.07) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 85% 90%, rgba(45,122,110,.05) 0%, transparent 55%),
        radial-gradient(ellipse 100% 80% at 50% 50%, var(--sand-100) 0%, var(--sand-50) 100%);
}

/* Subtle wave lines */
.bg-lines {
    position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; opacity: .45;
}
.bg-lines svg { width: 100%; height: 100%; }

/* ============================================================
   LAYOUT SPLIT — left panel (decorative) + right (form)
============================================================ */
.page-layout {
    position: relative; z-index: 1;
    width: 100%;
    max-width: 920px;
    margin: 1.5rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: 580px;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    animation: pageIn .55s cubic-bezier(.22,1,.36,1) both;
}
@keyframes pageIn {
    from { opacity: 0; transform: translateY(24px) scale(.985); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ---- LEFT PANEL ---- */
.panel-left {
    background: var(--teal-700);
    padding: 3rem 2.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}
.panel-left::before {
    content: '';
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse 120% 80% at -10% 110%, rgba(255,255,255,.07) 0%, transparent 55%),
        radial-gradient(ellipse 80% 60% at 110% -10%, rgba(255,255,255,.06) 0%, transparent 50%);
    pointer-events: none;
}

/* Decorative circle */
.deco-circle {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,.10);
    pointer-events: none;
}
.deco-circle-1 { width: 340px; height: 340px; right: -120px; top: -100px; }
.deco-circle-2 { width: 220px; height: 220px; right: -50px;  top: -30px;  border-color: rgba(255,255,255,.06); }
.deco-circle-3 { width: 180px; height: 180px; left: -60px;   bottom: -60px; }

.brand-area { position: relative; z-index: 1; }
.brand-icon {
    width: 52px; height: 52px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 1.2rem;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(8px);
}
.brand-name {
    font-family: var(--font-serif);
    font-size: 2rem; font-weight: 400;
    color: white; letter-spacing: -.3px;
    line-height: 1.1; margin-bottom: .4rem;
}
.brand-tagline {
    font-size: .78rem; font-weight: 300;
    color: rgba(255,255,255,.55);
    letter-spacing: .3px; line-height: 1.5;
    max-width: 200px;
}

.panel-stats { position: relative; z-index: 1; }
.stat-item {
    padding: .9rem 1rem;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: var(--radius-md);
    margin-bottom: .6rem;
    backdrop-filter: blur(4px);
}
.stat-item:last-child { margin-bottom: 0; }
.stat-label {
    font-size: .68rem; font-weight: 500;
    color: rgba(255,255,255,.45);
    text-transform: uppercase; letter-spacing: .7px;
    margin-bottom: .2rem;
}
.stat-value {
    font-size: .88rem; font-weight: 400;
    color: rgba(255,255,255,.85);
    display: flex; align-items: center; gap: .45rem;
}
.stat-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #5ee8c8;
    box-shadow: 0 0 6px rgba(94,232,200,.6);
    flex-shrink: 0;
    animation: pulse 2.5s ease infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(.85)} }

/* ---- RIGHT PANEL ---- */
.panel-right {
    background: var(--white);
    padding: 3rem 2.6rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* ============================================================
   PANEL RIGHT HEADER
============================================================ */
.form-heading {
    margin-bottom: 2rem;
}
.form-heading h1 {
    font-family: var(--font-serif);
    font-size: 1.75rem; font-weight: 400;
    color: var(--ink-900); letter-spacing: -.3px;
    margin-bottom: .35rem;
}
.form-heading p {
    font-size: .84rem; color: var(--ink-500); font-weight: 300; line-height: 1.5;
}

/* ============================================================
   ALERTS
============================================================ */
.alert {
    display: flex; align-items: flex-start; gap: .6rem;
    padding: .8rem 1rem; border-radius: var(--radius-sm);
    margin-bottom: 1.3rem; font-size: .83rem; line-height: 1.5;
    animation: alertIn .3s ease both;
}
@keyframes alertIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
.alert i { margin-top: .1rem; flex-shrink: 0; font-size: .8rem; }
.alert-error   { background: var(--error-bg);   border: 1px solid var(--error-bd);   color: var(--error-txt); }
.alert-success { background: var(--success-bg); border: 1px solid var(--success-bd); color: var(--success-txt); }
.alert-info    { background: var(--warn-bg);    border: 1px solid var(--warn-bd);    color: var(--warn-txt); }

/* ============================================================
   FORM
============================================================ */
.form-group {
    margin-bottom: 1.1rem;
    position: relative;
}
.form-label {
    display: block;
    font-size: .75rem; font-weight: 500;
    color: var(--ink-700); letter-spacing: .2px;
    margin-bottom: .42rem;
}
.form-input {
    width: 100%;
    padding: .78rem 1rem .78rem 2.8rem;
    background: var(--sand-50);
    border: 1.5px solid var(--ink-100);
    border-radius: var(--radius-sm);
    color: var(--ink-900);
    font-family: var(--font-sans);
    font-size: .92rem; font-weight: 400;
    outline: none;
    transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
    -webkit-appearance: none;
}
.form-input::placeholder { color: var(--ink-300); }
.form-input:hover {
    border-color: var(--sand-300);
    background: var(--white);
}
.form-input:focus {
    border-color: var(--teal-600);
    background: var(--white);
    box-shadow: 0 0 0 3.5px rgba(45,122,110,.10);
}
.form-input:-webkit-autofill,
.form-input:-webkit-autofill:hover,
.form-input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--ink-900) !important;
    -webkit-box-shadow: 0 0 0 1000px #faf8f5 inset !important;
    transition: background-color 5000s ease-in-out 0s;
}
.input-icon {
    position: absolute;
    left: .9rem; top: 50%;
    transform: translateY(-50%);
    color: var(--ink-300);
    font-size: .78rem;
    pointer-events: none;
    transition: color var(--transition);
}
.form-group:focus-within .input-icon { color: var(--teal-600); }

/* Password toggle */
.pw-wrap { position: relative; }
.pw-toggle {
    position: absolute;
    right: .9rem; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: var(--ink-300); cursor: pointer;
    font-size: .8rem; padding: .3rem;
    border-radius: 5px; transition: color var(--transition);
    -webkit-tap-highlight-color: transparent;
}
.pw-toggle:hover { color: var(--teal-600); }

/* ============================================================
   SUBMIT BUTTON
============================================================ */
.login-btn {
    width: 100%; padding: .85rem 1rem;
    background: var(--teal-700);
    color: white; font-weight: 500; font-size: .92rem;
    border: none; border-radius: var(--radius-sm);
    cursor: pointer; letter-spacing: .2px;
    font-family: var(--font-sans);
    position: relative; overflow: hidden;
    transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    margin-top: .5rem;
    display: flex; align-items: center; justify-content: center; gap: .55rem;
    min-height: 50px;
}
.login-btn:hover:not(:disabled) {
    background: var(--teal-800);
    box-shadow: 0 8px 24px rgba(26,71,64,.25);
    transform: translateY(-1px);
}
.login-btn:active:not(:disabled) { transform: scale(.99); }
.login-btn:disabled { opacity: .6; cursor: not-allowed; }

.spinner {
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin .65s linear infinite;
    display: none; flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ============================================================
   DIVIDER
============================================================ */
.divider {
    display: flex; align-items: center; gap: .8rem;
    margin: 1.4rem 0 1.1rem;
}
.divider-line { flex: 1; height: 1px; background: var(--ink-100); }
.divider-txt { font-size: .72rem; color: var(--ink-300); font-weight: 400; white-space: nowrap; }

/* ============================================================
   DEMO CREDENTIALS
============================================================ */
.demo-toggle {
    width: 100%;
    display: flex; align-items: center; gap: .5rem;
    padding: .65rem .85rem;
    background: none;
    border: 1.5px solid var(--ink-100);
    border-radius: var(--radius-sm);
    color: var(--ink-500); font-family: var(--font-sans);
    font-size: .78rem; font-weight: 500;
    cursor: pointer; text-align: left;
    transition: border-color var(--transition), color var(--transition), background var(--transition);
    -webkit-tap-highlight-color: transparent;
    letter-spacing: .1px;
}
.demo-toggle i:first-child { color: var(--teal-600); font-size: .75rem; }
.demo-chevron { margin-left: auto; font-size: .65rem; transition: transform .25s; color: var(--ink-300); }
.demo-toggle:hover { border-color: var(--teal-600); color: var(--ink-700); background: var(--sand-50); }
.demo-open .demo-chevron { transform: rotate(180deg); }

.demo-list {
    display: none;
    flex-direction: column; gap: .4rem;
    margin-top: .5rem;
}
.demo-list.open { display: flex; }

.demo-row {
    display: flex; align-items: center; gap: .7rem;
    padding: .65rem .85rem;
    border: 1.5px solid var(--ink-100);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: border-color var(--transition), background var(--transition);
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    background: var(--white);
}
.demo-row:hover, .demo-row:active {
    border-color: var(--teal-600);
    background: var(--sand-50);
}
.demo-avatar {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .72rem; flex-shrink: 0;
}
.av-admin   { background: #f0ebf9; color: #7c4dbd; }
.av-manager { background: #e6f4f2; color: var(--teal-700); }
.av-staff   { background: #edf5e8; color: #3a7a28; }
.demo-info { flex: 1; min-width: 0; }
.demo-role {
    font-size: .73rem; font-weight: 600;
    color: var(--ink-700); margin-bottom: .1rem; letter-spacing: .1px;
}
.demo-creds {
    font-size: .68rem; color: var(--ink-300);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.demo-use-lbl {
    font-size: .65rem; color: var(--teal-600);
    font-weight: 500; letter-spacing: .2px; flex-shrink: 0;
    display: flex; align-items: center; gap: .25rem;
}

/* ============================================================
   FOOTER
============================================================ */
.page-footer {
    text-align: center; margin-top: 1.4rem;
    font-size: .68rem; color: var(--ink-300); line-height: 1.8;
}

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 700px) {
    .page-layout {
        grid-template-columns: 1fr;
        max-width: 440px;
        min-height: unset;
        margin: .75rem;
    }
    .panel-left {
        padding: 2rem 1.8rem;
        min-height: 180px;
    }
    .brand-area { display: flex; align-items: center; gap: 1rem; }
    .brand-icon { margin-bottom: 0; width: 42px; height: 42px; font-size: 1rem; flex-shrink: 0; }
    .brand-name { font-size: 1.5rem; margin-bottom: .15rem; }
    .brand-tagline { font-size: .72rem; }
    .panel-stats { display: none; }
    .deco-circle-1, .deco-circle-3 { display: none; }
    .panel-right { padding: 2rem 1.8rem; }
}

@media (max-width: 359px) {
    .panel-right { padding: 1.6rem 1.3rem; }
    .form-input { font-size: .9rem; }
}

@media (max-height: 600px) and (orientation: landscape) {
    body { align-items: flex-start; }
    .page-layout { margin: .5rem auto; }
    .panel-left { padding: 1.5rem; }
    .panel-stats { display: none; }
    .panel-right { padding: 1.5rem 2rem; }
    .form-heading { margin-bottom: 1.1rem; }
    .form-heading h1 { font-size: 1.4rem; }
    .form-group { margin-bottom: .8rem; }
}

@media (hover: none) and (pointer: coarse) {
    .form-input { font-size: 16px; }
    .login-btn { min-height: 52px; }
    .demo-row  { min-height: 50px; }
}
</style>
</head>
<body>

<!-- BACKGROUND -->
<div class="bg-texture"></div>
<div class="bg-lines">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
        <path d="M-100,200 Q360,120 720,200 Q1080,280 1540,200" stroke="rgba(45,122,110,.06)" stroke-width="1" fill="none"/>
        <path d="M-100,380 Q360,300 720,380 Q1080,460 1540,380" stroke="rgba(45,122,110,.05)" stroke-width="1" fill="none"/>
        <path d="M-100,560 Q360,480 720,560 Q1080,640 1540,560" stroke="rgba(45,122,110,.04)" stroke-width="1" fill="none"/>
        <path d="M-100,700 Q360,620 720,700 Q1080,780 1540,700" stroke="rgba(45,122,110,.03)" stroke-width="1" fill="none"/>
    </svg>
</div>

<!-- PAGE LAYOUT -->
<div class="page-layout">

    <!-- LEFT PANEL -->
    <div class="panel-left">
        <div class="deco-circle deco-circle-1"></div>
        <div class="deco-circle deco-circle-2"></div>
        <div class="deco-circle deco-circle-3"></div>

        <div class="brand-area">
            <div class="brand-icon"><i class="fas fa-fish"></i></div>
            <div>
                <div class="brand-name">AquaSystem</div>
                <div class="brand-tagline">Organic Matter Detection &amp; Tilapia Management</div>
            </div>
        </div>

        <div class="panel-stats">
            <div class="stat-item">
                <div class="stat-label">System Status</div>
                <div class="stat-value">
                    <span class="stat-dot"></span>
                    All systems operational
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Secure Connection</div>
                <div class="stat-value">
                    <i class="fas fa-shield-alt" style="color:rgba(255,255,255,.45);font-size:.75rem;"></i>
                    SSL encrypted session
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Location</div>
                <div class="stat-value">Manolo Fortich, Bukidnon</div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="panel-right">

        <div class="form-heading">
            <h1>Welcome back</h1>
            <p>Sign in to access your dashboard and monitoring tools.</p>
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
            <span>Your session expired. Please sign in again.</span>
        </div>
        <?php endif; ?>

        <!-- FORM -->
        <form method="POST" id="loginForm" novalidate>

            <!-- Email -->
            <div class="form-group">
                <label class="form-label" for="emailInput">Email address</label>
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
                <label class="form-label" for="passwordInput">Password</label>
                <div class="pw-wrap" style="position:relative;">
                    <i class="fas fa-lock input-icon"></i>
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
                <span id="btnText">Sign in</span>
                <i class="fas fa-arrow-right" id="btnArrow" style="font-size:.8rem;"></i>
                <div class="spinner" id="btnSpinner"></div>
            </button>

        </form>

        <!-- DEMO CREDENTIALS -->
        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-txt">Demo credentials</span>
            <div class="divider-line"></div>
        </div>

        <button class="demo-toggle" id="demoToggle" onclick="toggleDemo()" type="button">
            <i class="fas fa-key"></i>
            <span>View demo accounts</span>
            <i class="fas fa-chevron-down demo-chevron" id="demoChevron"></i>
        </button>

        <div class="demo-list" id="demoList">
            <div class="demo-row" onclick="fillCreds('admin@company.com','admin123')" role="button" tabindex="0" onkeydown="if(event.key==='Enter')fillCreds('admin@company.com','admin123')">
                <div class="demo-avatar av-admin"><i class="fas fa-user-shield"></i></div>
                <div class="demo-info">
                    <div class="demo-role">Administrator</div>
                    <div class="demo-creds">admin@company.com · admin123</div>
                </div>
                <div class="demo-use-lbl">Use <i class="fas fa-arrow-right" style="font-size:.6rem;"></i></div>
            </div>

            <div class="demo-row" onclick="fillCreds('manager@company.com','manager123')" role="button" tabindex="0" onkeydown="if(event.key==='Enter')fillCreds('manager@company.com','manager123')">
                <div class="demo-avatar av-manager"><i class="fas fa-user-tie"></i></div>
                <div class="demo-info">
                    <div class="demo-role">Manager</div>
                    <div class="demo-creds">manager@company.com · manager123</div>
                </div>
                <div class="demo-use-lbl">Use <i class="fas fa-arrow-right" style="font-size:.6rem;"></i></div>
            </div>

            <div class="demo-row" onclick="fillCreds('staff1@company.com','staff123')" role="button" tabindex="0" onkeydown="if(event.key==='Enter')fillCreds('staff1@company.com','staff123')">
                <div class="demo-avatar av-staff"><i class="fas fa-user"></i></div>
                <div class="demo-info">
                    <div class="demo-role">Staff</div>
                    <div class="demo-creds">staff1@company.com · staff123</div>
                </div>
                <div class="demo-use-lbl">Use <i class="fas fa-arrow-right" style="font-size:.6rem;"></i></div>
            </div>
        </div>

        <div class="page-footer">
            &copy; 2026 Organic-Matter Detection in Tilapia &nbsp;·&nbsp; Manolo Fortich, Bukidnon
        </div>

    </div><!-- /panel-right -->
</div><!-- /page-layout -->

<script>
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
    const list    = document.getElementById('demoList');
    const toggle  = document.getElementById('demoToggle');
    const chevron = document.getElementById('demoChevron');
    const isOpen  = list.classList.contains('open');
    list.classList.toggle('open', !isOpen);
    toggle.classList.toggle('demo-open', !isOpen);
}

function fillCreds(email, password) {
    const eInp = document.getElementById('emailInput');
    const pInp = document.getElementById('passwordInput');

    eInp.value = email;
    pInp.value = password;

    // Subtle highlight feedback
    [eInp, pInp].forEach(el => {
        el.style.transition = 'border-color .3s, box-shadow .3s';
        el.style.borderColor = 'var(--teal-600)';
        el.style.boxShadow   = '0 0 0 3.5px rgba(45,122,110,.12)';
        setTimeout(() => {
            el.style.borderColor = '';
            el.style.boxShadow   = '';
        }, 900);
    });

    if (window.innerWidth < 700) {
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
    const card = document.querySelector('.panel-right');
    card.style.animation = 'none';
    card.style.transform = 'translateX(-6px)';
    setTimeout(() => {
        card.style.transition = 'transform .35s cubic-bezier(.36,.07,.19,.97)';
        card.style.transform  = 'translateX(0)';
    }, 30);
    setTimeout(() => { card.style.transition = ''; }, 450);
}

// ============================================================
// AUTO-OPEN DEMO IF QUERY PARAM
// ============================================================
if (new URLSearchParams(window.location.search).get('demo') === '1') {
    document.getElementById('demoList').classList.add('open');
    document.getElementById('demoToggle').classList.add('demo-open');
}

// ============================================================
// INPUT — check autofill on load
// ============================================================
['emailInput','passwordInput'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    setTimeout(() => {
        if (el.value) el.classList.add('has-value');
    }, 200);
});
</script>
</body>
</html>