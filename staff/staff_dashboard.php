<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

$full_name     = $_SESSION['full_name'] ?? 'Staff';
$assigned_pond = $_SESSION['assigned_pond'] ?? 'A-1';
$pond          = $assigned_pond;
$email         = $_SESSION['email'] ?? 'staff@example.com';
$last_login    = $_SESSION['last_login'] ?? date('Y-m-d H:i:s');
$staff_id      = $_SESSION['user_id'] ?? 1;

// ============================================================
// POND THRESHOLDS
// ============================================================
$thresholds = [
    'organic_warn'  => 20.0,
    'organic_crit'  => 28.0,
    'temp_warn'     => 29.0,
    'temp_crit'     => 31.5,
    'ph_low_warn'   => 6.8,
    'ph_low_crit'   => 6.5,
    'ph_high_warn'  => 8.2,
    'ph_high_crit'  => 8.5,
];

// ============================================================
// FETCH CHART DATA FROM DB
// ============================================================
$sql = "SELECT DATE(detected_at) AS sample_date,
               organic_mg_l,
               temperature_c,
               ph_level
        FROM user_ponds
        WHERE pond_name = ?
        ORDER BY detected_at ASC
        LIMIT 14";

$stmt = $pdo->prepare($sql);
$stmt->execute([$pond]);
$result = $stmt->fetchAll();

$dates = []; $organic = []; $temp = []; $ph = [];

if ($result && count($result) > 0) {
    foreach ($result as $row) {
        $dates[]   = date("M d", strtotime($row['sample_date']));
        $organic[] = floatval($row['organic_mg_l']);
        $temp[]    = floatval($row['temperature_c']);
        $ph[]      = floatval($row['ph_level']);
    }
} else {
    for ($i = 0; $i < 14; $i++) {
        $dates[]   = date("M d", strtotime("-" . (13 - $i) . " days"));
        $ov = round(rand(50, 260) / 10, 1);
        $tv = round(rand(260, 320) / 10, 1);
        $pv = round(rand(65, 85) / 10, 1);
        $organic[] = $ov;
        $temp[]    = $tv;
        $ph[]      = $pv;
    }
}

$latest_organic = end($organic);
$latest_temp    = end($temp);
$latest_ph      = end($ph);
$avg_organic    = round(array_sum($organic) / count($organic), 1);
$avg_temp       = round(array_sum($temp) / count($temp), 1);
$avg_ph         = round(array_sum($ph) / count($ph), 1);

function getPondStatus($org, $tmp, $ph, $thr) {
    if ($org >= $thr['organic_crit'] || $tmp >= $thr['temp_crit'] || $ph <= $thr['ph_low_crit'] || $ph >= $thr['ph_high_crit'])
        return 'critical';
    if ($org >= $thr['organic_warn'] || $tmp >= $thr['temp_warn'] || $ph <= $thr['ph_low_warn'] || $ph >= $thr['ph_high_warn'])
        return 'warning';
    return 'safe';
}
$current_status = getPondStatus($latest_organic, $latest_temp, $latest_ph, $thresholds);

// ============================================================
// FETCH MAINTENANCE LOGS
// ============================================================
$maintenance_logs = [];
try {
    $mlog = $pdo->prepare("SELECT * FROM maintenance_logs WHERE pond_name = ? ORDER BY logged_at DESC LIMIT 5");
    $mlog->execute([$pond]);
    $maintenance_logs = $mlog->fetchAll();
} catch (Exception $e) {}

if (empty($maintenance_logs)) {
    $maintenance_logs = [
        ['log_id'=>1,'pond_name'=>$pond,'action'=>'Water filter cleaned','logged_by'=>$full_name,'logged_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'notes'=>'Filter replaced, flow restored'],
        ['log_id'=>2,'pond_name'=>$pond,'action'=>'pH adjustment done','logged_by'=>$full_name,'logged_at'=>date('Y-m-d H:i:s',strtotime('-3 days')),'notes'=>'Added buffer solution'],
        ['log_id'=>3,'pond_name'=>$pond,'action'=>'Sensor calibration','logged_by'=>$full_name,'logged_at'=>date('Y-m-d H:i:s',strtotime('-5 days')),'notes'=>'All sensors recalibrated'],
    ];
}

// ============================================================
// AJAX HANDLERS
// ============================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'get_live_reading') {
        $pond_key = $_POST['pond'] ?? $pond;
        $new_org  = round(rand(80, 320) / 10, 1);
        $new_tmp  = round(rand(250, 335) / 10, 1);
        $new_ph   = round(rand(62, 90)  / 10, 1);
        $status   = getPondStatus($new_org, $new_tmp, $new_ph, $thresholds);
        echo json_encode([
            'success'   => true,
            'organic'   => $new_org,
            'temp'      => $new_tmp,
            'ph'        => $new_ph,
            'status'    => $status,
            'timestamp' => date('h:i:s A')
        ]);
        exit;
    }

    if ($_POST['action'] === 'log_maintenance') {
        $action_text = trim($_POST['action_text'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');
        if (empty($action_text)) {
            echo json_encode(['success' => false, 'message' => 'Action is required']); exit;
        }
        $entry = [
            'log_id'    => rand(100,999),
            'pond_name' => $pond,
            'action'    => $action_text,
            'logged_by' => $full_name,
            'logged_at' => date('Y-m-d H:i:s'),
            'notes'     => $notes
        ];
        try {
            $ins = $pdo->prepare("INSERT INTO maintenance_logs (pond_name, action, logged_by, notes, logged_at) VALUES (?,?,?,?,NOW())");
            $ins->execute([$pond, $action_text, $full_name, $notes]);
            $entry['log_id'] = $pdo->lastInsertId();
        } catch (Exception $e) {}
        echo json_encode(['success' => true, 'message' => 'Log saved', 'entry' => $entry]);
        exit;
    }

    if ($_POST['action'] === 'notify_manager') {
        $msg   = trim($_POST['message'] ?? '');
        $level = $_POST['level'] ?? 'info';
        echo json_encode(['success' => true, 'message' => 'Manager notified successfully', 'timestamp' => date('h:i:s A')]);
        exit;
    }

    if ($_POST['action'] === 'get_history') {
        $data = ['labels'=>[],'organic'=>[],'temp'=>[],'ph'=>[]];
        for ($i = 23; $i >= 0; $i--) {
            $data['labels'][]  = date('H:00', strtotime("-$i hours"));
            $data['organic'][] = round(rand(80,280)/10, 1);
            $data['temp'][]    = round(rand(260,330)/10, 1);
            $data['ph'][]      = round(rand(65,85)/10, 1);
        }
        echo json_encode($data); exit;
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
<title>AquaStaff — Pond <?php echo htmlspecialchars($pond); ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ====================================================
   CSS VARIABLES — White background theme
==================================================== */
:root {
    /* ── Backgrounds (light) ── */
    --bg-deep:       #f0f4f8;
    --bg-panel:      #ffffff;
    --bg-card:       #ffffff;
    --bg-elevated:   #e8f0f7;
    --bg-hover:      #dce8f2;

    /* ── Accent colours (unchanged) ── */
    --cyan:          #00e5ff;
    --green:         #39ff8a;
    --amber:         #ffb800;
    --red:           #ff3b5c;
    --violet:        #b06cff;
    --teal:          #00c9b1;

    /* ── Text (dark on light) ── */
    --txt:           #0d1f2d;
    --txt2:          #2e4a63;
    --muted:         #6b8ba8;

    /* ── Borders (light-mode) ── */
    --bdr:           rgba(0,201,177,.18);
    --bdr-glow:      rgba(0,201,177,.45);

    /* ── Fonts & radii (unchanged) ── */
    --fd:            'Syne', sans-serif;
    --fm:            'Space Mono', monospace;
    --r-sm:  8px;
    --r-md:  14px;
    --r-lg:  20px;
    --r-xl:  28px;
    --nav-h:     62px;
    --sidebar-w: 260px;
    --bnav-h:    60px;
}

/* ====================================================
   RESET & BASE
==================================================== */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { height:100%; scroll-behavior:smooth; }
body {
    background: var(--bg-deep);
    color: var(--txt);
    font-family: var(--fd);
    min-height: 100vh;
    overflow-x: hidden;
}
body::before {
    content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
    background-image:
        linear-gradient(rgba(0,201,177,.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,201,177,.06) 1px, transparent 1px);
    background-size:44px 44px;
    animation:gridDrift 28s linear infinite;
}

@keyframes gridDrift  { 0%{background-position:0 0} 100%{background-position:44px 44px} }
@keyframes fadeUp     { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideIn    { from{transform:translateX(-12px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes blink      { 0%,100%{opacity:1} 50%{opacity:.2} }
@keyframes spin       { to{transform:rotate(360deg)} }
@keyframes pulseGlow  { 0%,100%{box-shadow:0 0 20px var(--red)} 50%{box-shadow:0 0 55px var(--red)} }
@keyframes toastIn    { from{transform:translateX(60px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes sheen      { 0%,100%{left:-60%} 50%{left:160%} }
@keyframes rippleAnim { 0%{transform:scale(0);opacity:1} 100%{transform:scale(2.5);opacity:0} }
@keyframes countFlash { 0%{color:var(--teal)} 100%{color:inherit} }

::-webkit-scrollbar { width:4px; height:4px; }
::-webkit-scrollbar-track { background:var(--bg-deep); }
::-webkit-scrollbar-thumb { background:var(--teal); border-radius:2px; }

/* ====================================================
   LAYOUT
==================================================== */
.layout { display:flex; min-height:100vh; position:relative; }

/* ====================================================
   SIDEBAR
==================================================== */
.sidebar {
    width:var(--sidebar-w); flex-shrink:0;
    background:rgba(255,255,255,.97); backdrop-filter:blur(20px);
    border-right:1px solid var(--bdr);
    position:fixed; top:0; left:0; bottom:0;
    display:flex; flex-direction:column;
    z-index:700; transition:transform .3s cubic-bezier(.4,0,.2,1);
    overflow-y:auto; overflow-x:hidden;
    box-shadow: 2px 0 16px rgba(0,0,0,.06);
}
.sidebar-head {
    padding:1.2rem 1.4rem 1rem; border-bottom:1px solid var(--bdr);
    display:flex; align-items:center; gap:.7rem; min-height:var(--nav-h);
}
.sidebar-logo {
    width:36px; height:36px; border-radius:10px; flex-shrink:0;
    background:linear-gradient(135deg,var(--teal),var(--cyan));
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; color:#000; font-weight:800;
    position:relative; overflow:hidden;
}
.sidebar-logo::after {
    content:''; position:absolute; top:-50%; left:-60%;
    width:28%; height:200%; background:rgba(255,255,255,.35);
    transform:skewX(-20deg); animation:sheen 4s infinite;
}
.sidebar-title {
    font-size:.95rem; font-weight:800;
    background:linear-gradient(90deg,var(--teal),var(--txt));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    line-height:1.15;
}
.sidebar-sub { font-size:.62rem; color:var(--muted); font-family:var(--fm); margin-top:.1rem; }

.nav-section {
    padding:.6rem .8rem .3rem;
    font-family:var(--fm); font-size:.62rem; color:var(--muted);
    letter-spacing:.8px; text-transform:uppercase;
}
.nav-item {
    display:flex; align-items:center; gap:.7rem;
    padding:.65rem 1rem .65rem 1.2rem; margin:.1rem .5rem;
    border-radius:var(--r-md); cursor:pointer; transition:.22s;
    font-size:.83rem; font-weight:600; color:var(--txt2);
    border:1px solid transparent; position:relative;
    -webkit-tap-highlight-color:transparent; user-select:none;
}
.nav-item:hover { background:var(--bg-hover); color:var(--txt); border-color:var(--bdr); }
.nav-item.active {
    background:rgba(0,201,177,.12); color:var(--teal);
    border-color:rgba(0,201,177,.28);
}
.nav-item.active::before {
    content:''; position:absolute; left:0; top:25%; bottom:25%;
    width:3px; background:var(--teal); border-radius:0 3px 3px 0;
}
.nav-item i { width:18px; text-align:center; font-size:.88rem; flex-shrink:0; }

.sidebar-footer { margin-top:auto; padding:1rem 1.2rem; border-top:1px solid var(--bdr); }
.sidebar-user {
    display:flex; align-items:center; gap:.7rem; padding:.7rem .8rem;
    border-radius:var(--r-md); background:var(--bg-elevated);
    border:1px solid var(--bdr); margin-bottom:.7rem;
}
.sidebar-avatar {
    width:34px; height:34px; border-radius:9px; flex-shrink:0;
    background:linear-gradient(135deg,var(--teal),var(--cyan));
    display:flex; align-items:center; justify-content:center;
    font-size:.78rem; font-weight:800; color:#000;
}
.sidebar-uname { font-size:.8rem; font-weight:700; line-height:1.2; }
.sidebar-urole { font-size:.65rem; color:var(--muted); font-family:var(--fm); }
.btn-logout-sidebar {
    width:100%; display:flex; align-items:center; justify-content:center; gap:.5rem;
    padding:.6rem; border-radius:var(--r-md);
    background:rgba(255,59,92,.08); border:1px solid rgba(255,59,92,.25);
    color:var(--red); font-family:var(--fd); font-size:.82rem; font-weight:600;
    cursor:pointer; transition:.25s; text-decoration:none;
}
.btn-logout-sidebar:hover { background:rgba(255,59,92,.15); }

/* ====================================================
   MAIN CONTENT AREA
==================================================== */
.main { margin-left:var(--sidebar-w); flex:1; min-width:0; position:relative; }

/* ====================================================
   TOPNAV (mobile)
==================================================== */
.topnav {
    display:none;
    background:rgba(255,255,255,.97); backdrop-filter:blur(20px);
    border-bottom:1px solid var(--bdr);
    height:var(--nav-h); padding:0 1rem;
    align-items:center; justify-content:space-between;
    position:sticky; top:0; z-index:200;
    box-shadow:0 2px 12px rgba(0,0,0,.06);
}
.topnav-brand { display:flex; align-items:center; gap:.6rem; }
.topnav-logo {
    width:34px; height:34px; border-radius:9px;
    background:linear-gradient(135deg,var(--teal),var(--cyan));
    display:flex; align-items:center; justify-content:center;
    font-size:.95rem; color:#000; font-weight:800;
    position:relative; overflow:hidden;
}
.topnav-logo::after {
    content:''; position:absolute; top:-50%; left:-60%;
    width:28%; height:200%; background:rgba(255,255,255,.35);
    transform:skewX(-20deg); animation:sheen 4s infinite;
}
.topnav-title {
    font-size:.88rem; font-weight:800;
    background:linear-gradient(90deg,var(--teal),var(--txt));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.topnav-right { display:flex; align-items:center; gap:.5rem; }
.topnav-clock {
    font-family:var(--fm); font-size:.68rem; color:var(--teal);
    background:rgba(0,201,177,.08); border:1px solid rgba(0,201,177,.2);
    padding:.26rem .6rem; border-radius:6px; letter-spacing:.6px; white-space:nowrap;
}
.topnav-logout {
    display:flex; align-items:center; justify-content:center;
    width:38px; height:38px; min-width:44px; min-height:44px;
    border-radius:9px; background:rgba(255,59,92,.08);
    border:1px solid rgba(255,59,92,.25);
    color:var(--red); font-size:.88rem;
    cursor:pointer; text-decoration:none;
    -webkit-tap-highlight-color:transparent; touch-action:manipulation; transition:.22s;
}
.topnav-logout:hover { background:rgba(255,59,92,.18); }
.topnav-logout:active { transform:scale(.95); }
.hamburger {
    width:38px; height:38px; border-radius:9px;
    border:1px solid var(--bdr); background:var(--bg-elevated);
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; color:var(--txt2); font-size:.9rem;
    -webkit-tap-highlight-color:transparent;
    min-height:44px; min-width:44px;
}

/* Sidebar overlay */
.sidebar-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.35); z-index:600;
    backdrop-filter:blur(4px);
    opacity:0; transition:opacity .3s; pointer-events:none;
}
.sidebar-overlay.active { opacity:1; pointer-events:all; }

/* ====================================================
   BOTTOM NAV (mobile)
==================================================== */
.bottom-nav {
    display:none; position:fixed; bottom:0; left:0; right:0;
    z-index:9999; background:rgba(255,255,255,.98);
    backdrop-filter:blur(20px); border-top:1px solid var(--bdr);
    grid-template-columns:repeat(5,1fr);
    padding-bottom:env(safe-area-inset-bottom,0px);
    pointer-events:all;
    box-shadow:0 -2px 12px rgba(0,0,0,.06);
}
.bnav-item {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:.22rem; padding:.55rem .2rem; cursor:pointer;
    font-size:.58rem; font-weight:700; color:var(--muted);
    letter-spacing:.3px; text-transform:uppercase;
    transition:color .2s, background .2s; position:relative;
    touch-action:manipulation; -webkit-tap-highlight-color:transparent;
    user-select:none; min-height:52px;
}
.bnav-item:active { background:rgba(0,201,177,.08); color:var(--teal); }
.bnav-item.active { color:var(--teal); background:rgba(0,201,177,.07); }
.bnav-item.active::before {
    content:''; position:absolute; top:0; left:50%; transform:translateX(-50%);
    width:28px; height:2px; background:var(--teal); border-radius:0 0 2px 2px;
}
.bnav-item i { font-size:1.1rem; line-height:1; pointer-events:none; }
.bnav-item span { pointer-events:none; line-height:1; }
.bnav-item.bnav-logout { color:rgba(255,59,92,.6); text-decoration:none; }
.bnav-item.bnav-logout:active,.bnav-item.bnav-logout:hover { color:var(--red); background:rgba(255,59,92,.08); }

/* ====================================================
   CONTENT
==================================================== */
.content { padding:1.4rem 1.6rem 2rem; max-width:1600px; width:100%; }
.section-panel { display:none; animation:fadeUp .35s ease both; }
.section-panel.active { display:block; }

/* ====================================================
   TOPBAR
==================================================== */
.topbar {
    display:flex; justify-content:space-between; align-items:center;
    flex-wrap:wrap; gap:.7rem; padding:.7rem 1.3rem;
    background:var(--bg-card); border:1px solid var(--bdr);
    border-radius:var(--r-lg); margin-bottom:1.3rem;
    box-shadow:0 2px 12px rgba(0,0,0,.05);
}
.topbar-left { display:flex; align-items:center; gap:.8rem; flex-wrap:wrap; }
.topbar-day  { font-size:1rem; font-weight:700; }
.topbar-date { font-family:var(--fm); font-size:.73rem; color:var(--txt2); }
.sys-tag {
    display:inline-flex; align-items:center; gap:.35rem;
    font-family:var(--fm); font-size:.66rem;
    padding:.26rem .7rem; border-radius:4px; letter-spacing:.4px;
}
.sys-tag.green  { background:rgba(57,255,138,.1);   border:1px solid rgba(57,255,138,.3);  color:#0a7c3e; }
.sys-tag.teal   { background:rgba(0,201,177,.1);    border:1px solid rgba(0,201,177,.25);  color:#007a6e; }
.sys-tag.amber  { background:rgba(255,184,0,.1);    border:1px solid rgba(255,184,0,.3);   color:#8a6200; }
.sys-tag.red    { background:rgba(255,59,92,.08);   border:1px solid rgba(255,59,92,.2);   color:var(--red); }
.blink-dot { width:5px; height:5px; border-radius:50%; background:currentColor; animation:blink 1.5s infinite; display:inline-block; flex-shrink:0; }
.nav-clock {
    font-family:var(--fm); font-size:.76rem; color:var(--teal);
    background:rgba(0,201,177,.08); border:1px solid rgba(0,201,177,.2);
    padding:.3rem .7rem; border-radius:6px; letter-spacing:.8px; white-space:nowrap;
}
.iot-live {
    display:inline-flex; align-items:center; gap:.35rem;
    font-family:var(--fm); font-size:.65rem; color:#0a7c3e;
}
.iot-live span {
    width:6px; height:6px; border-radius:50%; background:#0a7c3e;
    animation:blink 1.2s infinite; display:inline-block;
}

/* ====================================================
   KPI GRID
==================================================== */
.kpi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.3rem; }
.kpi {
    background:var(--bg-card); border:1px solid var(--bdr);
    border-radius:var(--r-lg); padding:1.2rem 1.3rem;
    position:relative; overflow:hidden; cursor:default; transition:.35s;
    box-shadow:0 2px 10px rgba(0,0,0,.04);
}
.kpi:hover { transform:translateY(-3px); border-color:var(--bdr-glow); box-shadow:0 6px 28px rgba(0,201,177,.12); }
.kpi::before {
    content:''; position:absolute; top:0; left:0; right:0; height:2px;
    background:linear-gradient(90deg,transparent,var(--kc,var(--teal)),transparent);
}
.kpi-icon {
    width:38px; height:38px; border-radius:9px;
    display:flex; align-items:center; justify-content:center;
    font-size:.95rem; color:var(--kc,var(--teal));
    background:rgba(0,201,177,.08); border:1px solid currentColor;
    opacity:.9; margin-bottom:.7rem;
}
.kpi-val   { font-family:var(--fm); font-size:1.8rem; font-weight:700; color:var(--kc,var(--teal)); line-height:1; margin-bottom:.2rem; }
.kpi-label { font-size:.68rem; color:var(--muted); letter-spacing:.5px; text-transform:uppercase; }
.kpi-corner { position:absolute; top:.8rem; right:.8rem; font-size:.58rem; font-family:var(--fm); color:var(--kc); opacity:.45; letter-spacing:.4px; }

/* ====================================================
   GENERIC CARD
==================================================== */
.card {
    background:var(--bg-card); border:1px solid var(--bdr);
    border-radius:var(--r-xl); padding:1.3rem 1.4rem; margin-bottom:1.2rem;
    box-shadow:0 2px 10px rgba(0,0,0,.04);
}
.card:hover { border-color:rgba(0,201,177,.25); }
.card-head {
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:1rem; flex-wrap:wrap; gap:.5rem;
}
.card-title {
    display:flex; align-items:center; gap:.55rem;
    font-size:.86rem; font-weight:700; letter-spacing:.4px; text-transform:uppercase;
}
.card-title i { color:var(--teal); }

/* ====================================================
   GRID LAYOUTS
==================================================== */
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.2rem; margin-bottom:1.2rem; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.2rem; }

/* ====================================================
   LIVE READINGS — hero card
==================================================== */
.hero-status {
    background:var(--bg-card); border:1px solid var(--bdr);
    border-radius:var(--r-xl); padding:1.6rem 1.8rem;
    margin-bottom:1.3rem; position:relative; overflow:hidden;
    animation:fadeUp .5s ease both;
    box-shadow:0 2px 12px rgba(0,0,0,.05);
}
.hero-status::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
}
.hero-status.safe::before    { background:linear-gradient(90deg,transparent,var(--green),transparent); }
.hero-status.warning::before { background:linear-gradient(90deg,transparent,var(--amber),transparent); }
.hero-status.critical::before{ background:linear-gradient(90deg,transparent,var(--red),transparent); animation:pulseGlow 2s infinite; }

.hero-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.4rem; flex-wrap:wrap; gap:.6rem; }
.hero-pond-name { font-size:1.35rem; font-weight:800; display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }
.hero-meta { font-size:.75rem; color:var(--muted); font-family:var(--fm); margin-top:.25rem; }

.hero-readings { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1rem; }
.reading-block {
    background:var(--bg-elevated); border-radius:var(--r-md);
    padding:1.1rem .8rem; text-align:center;
    border:1px solid var(--bdr); position:relative; overflow:hidden; transition:.3s;
}
.reading-block:hover { background:rgba(0,201,177,.06); border-color:rgba(0,201,177,.25); }
.reading-block::after {
    content:''; position:absolute; top:0; left:0; right:0; height:2px;
}
.reading-block.organic::after { background:var(--green); }
.reading-block.temp::after    { background:var(--amber); }
.reading-block.ph::after      { background:var(--violet); }
.reading-icon  { font-size:1.2rem; margin-bottom:.5rem; display:block; }
.reading-val   { font-family:var(--fm); font-size:1.9rem; font-weight:700; line-height:1; margin-bottom:.3rem; }
.reading-unit  { font-size:.68rem; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:.4rem; }
.reading-label { font-size:.65rem; color:var(--txt2); letter-spacing:.4px; text-transform:uppercase; }
.reading-block.organic .reading-val { color:var(--green); }
.reading-block.temp    .reading-val { color:var(--amber); }
.reading-block.ph      .reading-val { color:var(--violet); }
.mini-bar { height:4px; background:rgba(0,0,0,.08); border-radius:2px; overflow:hidden; margin-top:.55rem; }
.mini-bar-fill { height:100%; border-radius:2px; transition:width 1.2s ease; }
.mini-bar-fill.organic { background:var(--green); }
.mini-bar-fill.temp    { background:var(--amber); }
.mini-bar-fill.ph      { background:var(--violet); }
.hero-bottom { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.6rem; }
.hero-ts { font-family:var(--fm); font-size:.68rem; color:var(--muted); }

/* ====================================================
   BADGES
==================================================== */
.badge {
    display:inline-flex; align-items:center; gap:.28rem;
    padding:.22rem .65rem; border-radius:4px;
    font-size:.67rem; font-weight:700; font-family:var(--fm);
    letter-spacing:.3px; text-transform:uppercase; white-space:nowrap;
}
.badge-safe     { background:rgba(57,255,138,.12); color:#0a7c3e;  border:1px solid rgba(57,255,138,.35); }
.badge-warning  { background:rgba(255,184,0,.12);  color:#8a6200;  border:1px solid rgba(255,184,0,.35); }
.badge-critical { background:rgba(255,59,92,.12);  color:var(--red);    border:1px solid rgba(255,59,92,.3); animation:blink 1.4s infinite; }
.badge-info     { background:rgba(0,201,177,.1);   color:#007a6e;   border:1px solid rgba(0,201,177,.25); }
.dot-blink { width:5px; height:5px; border-radius:50%; background:currentColor; animation:blink 1.5s infinite; display:inline-block; }

/* ====================================================
   BUTTONS
==================================================== */
.btn {
    display:inline-flex; align-items:center; gap:.4rem;
    border:none; border-radius:var(--r-sm); padding:.45rem 1rem;
    font-family:var(--fd); font-size:.8rem; font-weight:600;
    cursor:pointer; transition:.22s; letter-spacing:.3px; white-space:nowrap;
    -webkit-tap-highlight-color:transparent; touch-action:manipulation; user-select:none;
}
.btn:active  { transform:scale(.97); }
.btn:disabled{ opacity:.4; cursor:not-allowed; pointer-events:none; }
.btn-primary { background:var(--teal); color:#fff; }
.btn-primary:hover { background:#00b5a0; }
.btn-warning { background:rgba(255,184,0,.12); color:#8a6200; border:1px solid rgba(255,184,0,.3); }
.btn-danger  { background:rgba(255,59,92,.1);  color:var(--red);   border:1px solid rgba(255,59,92,.25); }
.btn-ghost   { background:var(--bg-elevated);   color:var(--txt2);  border:1px solid var(--bdr); }
.btn-ghost:hover { border-color:var(--teal); color:var(--teal); }
.btn-sky     { background:rgba(0,201,177,.1);   color:#007a6e;  border:1px solid rgba(0,201,177,.25); }
.btn-sky:hover { background:rgba(0,201,177,.18); }
.btn-sm { padding:.28rem .65rem; font-size:.72rem; }

/* ====================================================
   CIRCULAR GAUGES
==================================================== */
.gauges-row { display:grid; grid-template-columns:repeat(3,1fr); gap:.8rem; }
.gauge-container { text-align:center; padding:1rem .5rem; }
.gauge-ring-wrap { position:relative; display:inline-block; margin:0 auto; }
.gauge-ring-wrap svg { display:block; }
.gauge-inner-text {
    position:absolute; inset:0;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    pointer-events:none;
}
.gauge-inner-val  { font-family:var(--fm); font-size:1.1rem; font-weight:700; line-height:1; }
.gauge-inner-unit { font-size:.58rem; color:var(--muted); margin-top:.15rem; text-transform:uppercase; letter-spacing:.4px; }
.gauge-label { font-size:.72rem; color:var(--txt2); margin-top:.5rem; letter-spacing:.4px; text-transform:uppercase; }
.gauge-bg   { fill:none; stroke:rgba(0,0,0,.08); stroke-width:12; }
.gauge-fill { fill:none; stroke-width:12; stroke-linecap:round; transition:stroke-dashoffset 1.5s ease; }

/* ====================================================
   THRESHOLD PANEL
==================================================== */
.threshold-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:.6rem .8rem; border-radius:var(--r-sm);
    background:var(--bg-elevated); margin-bottom:.4rem;
    border-left:3px solid transparent; transition:.25s;
}
.threshold-row:hover { background:var(--bg-hover); }
.threshold-row.ok   { border-left-color:var(--green); }
.threshold-row.warn { border-left-color:var(--amber); }
.threshold-row.crit { border-left-color:var(--red); animation:blink 1.8s infinite; }
.threshold-label { font-size:.82rem; display:flex; align-items:center; gap:.5rem; }
.threshold-vals  { display:flex; align-items:center; gap:.6rem; font-family:var(--fm); font-size:.8rem; }
.threshold-current { font-weight:700; font-size:.95rem; }

/* ====================================================
   CHART
==================================================== */
.chart-wrap { height:240px; margin-top:.8rem; }
.period-tabs { display:flex; gap:.4rem; flex-wrap:wrap; }
.period-btn {
    font-family:var(--fm); font-size:.66rem; padding:.26rem .65rem;
    border-radius:4px; background:var(--bg-elevated);
    border:1px solid var(--bdr); color:var(--muted); cursor:pointer; transition:.2s;
    letter-spacing:.3px; -webkit-tap-highlight-color:transparent; touch-action:manipulation;
}
.period-btn.active,.period-btn:hover { background:rgba(0,201,177,.1); border-color:var(--teal); color:var(--teal); }

/* ====================================================
   MAINTENANCE LOG
==================================================== */
.log-item {
    display:flex; gap:.8rem; padding:.75rem .8rem;
    border-radius:var(--r-md); margin-bottom:.4rem;
    background:var(--bg-elevated); border-left:3px solid var(--teal);
    animation:slideIn .3s ease both; transition:.25s;
}
.log-item:hover { background:var(--bg-hover); }
.log-icon  { width:34px; height:34px; border-radius:9px; background:rgba(0,201,177,.12); display:flex; align-items:center; justify-content:center; font-size:.85rem; color:var(--teal); flex-shrink:0; }
.log-title { font-size:.85rem; font-weight:600; }
.log-meta  { font-size:.7rem; color:var(--muted); font-family:var(--fm); margin-top:.15rem; }
.log-notes { font-size:.75rem; color:var(--txt2); margin-top:.2rem; }

/* ====================================================
   FORM
==================================================== */
.form-group   { margin-bottom:.85rem; }
.form-label   { display:block; font-size:.7rem; font-weight:600; color:var(--muted); letter-spacing:.4px; text-transform:uppercase; margin-bottom:.3rem; font-family:var(--fm); }
.form-ctrl    { width:100%; padding:.55rem .85rem; background:var(--bg-elevated); border:1px solid var(--bdr); border-radius:var(--r-sm); color:var(--txt); font-family:var(--fd); font-size:.83rem; outline:none; transition:.25s; -webkit-appearance:none; }
.form-ctrl:focus { border-color:var(--teal); box-shadow:0 0 0 3px rgba(0,201,177,.1); }
.form-ctrl::placeholder { color:var(--muted); }
textarea.form-ctrl { resize:vertical; min-height:70px; }
select.form-ctrl option { background:var(--bg-panel); color:var(--txt); }

/* ====================================================
   PROFILE INFO ROWS
==================================================== */
.staff-avatar-wrap {
    width:52px; height:52px; border-radius:14px;
    background:linear-gradient(135deg,var(--teal),var(--cyan));
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; font-weight:800; color:#000;
    margin-bottom:1rem; position:relative; overflow:hidden;
}
.staff-avatar-wrap::after {
    content:''; position:absolute; top:-50%; left:-60%;
    width:28%; height:200%; background:rgba(255,255,255,.35);
    transform:skewX(-20deg); animation:sheen 4s infinite;
}
.info-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:.55rem .6rem; border-radius:var(--r-sm);
    background:var(--bg-elevated); margin-bottom:.35rem;
    border-left:2px solid var(--bdr); transition:.25s;
}
.info-row:hover { background:var(--bg-hover); }
.info-lbl { font-size:.7rem; color:var(--muted); font-family:var(--fm); text-transform:uppercase; letter-spacing:.4px; }
.info-val { font-size:.82rem; font-weight:600; font-family:var(--fm); }

/* ====================================================
   HISTORY TABLE
==================================================== */
.tbl-wrap { overflow-x:auto; margin-top:.5rem; }
table { width:100%; border-collapse:collapse; }
th { text-align:left; padding:.55rem .75rem; font-size:.66rem; font-weight:700; color:var(--muted); letter-spacing:.8px; text-transform:uppercase; border-bottom:1px solid var(--bdr); font-family:var(--fm); }
td { padding:.62rem .75rem; border-bottom:1px solid rgba(0,0,0,.05); font-size:.8rem; vertical-align:middle; }
tr:hover td { background:rgba(0,201,177,.04); }
tr:last-child td { border-bottom:none; }

/* ====================================================
   NOTIFY PANEL
==================================================== */
.notify-levels { display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; margin-bottom:.8rem; }
.notify-level-btn {
    padding:.6rem .4rem; border-radius:var(--r-sm); text-align:center;
    cursor:pointer; border:2px solid transparent; transition:.25s;
    font-size:.76rem; font-weight:600; font-family:var(--fd);
    background:var(--bg-elevated); color:var(--txt2);
    -webkit-tap-highlight-color:transparent; touch-action:manipulation;
}
.notify-level-btn.active-info     { border-color:var(--teal);  background:rgba(0,201,177,.1);   color:#007a6e; }
.notify-level-btn.active-warning  { border-color:var(--amber); background:rgba(255,184,0,.12);  color:#8a6200; }
.notify-level-btn.active-critical { border-color:var(--red);   background:rgba(255,59,92,.12);  color:var(--red); }

/* ====================================================
   MODAL
==================================================== */
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(8px); z-index:10000; align-items:center; justify-content:center; padding:1rem; }
.modal.open { display:flex; }
.modal-box  { background:var(--bg-panel); border:1px solid var(--bdr); border-radius:var(--r-xl); padding:1.8rem; width:100%; max-width:460px; max-height:90vh; overflow-y:auto; animation:fadeUp .3s ease; -webkit-overflow-scrolling:touch; box-shadow:0 16px 48px rgba(0,0,0,.15); }
.modal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem; padding-bottom:.7rem; border-bottom:1px solid var(--bdr); gap:.5rem; }
.modal-title { font-size:.95rem; font-weight:700; }
.modal-close { background:none; border:none; color:var(--muted); font-size:1.3rem; cursor:pointer; width:44px; height:44px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:.2s; min-width:44px; min-height:44px; -webkit-tap-highlight-color:transparent; touch-action:manipulation; }
.modal-close:hover { color:var(--red); background:rgba(255,59,92,.1); }

/* ====================================================
   TOAST
==================================================== */
.toast-wrap { position:fixed; top:74px; right:18px; z-index:10001; display:flex; flex-direction:column; gap:.45rem; pointer-events:none; max-width:calc(100vw - 2rem); }
.toast { display:flex; align-items:center; gap:.55rem; padding:.7rem 1rem; border-radius:var(--r-md); font-size:.8rem; font-weight:600; min-width:240px; animation:toastIn .3s ease; box-shadow:0 8px 24px rgba(0,0,0,.15); pointer-events:all; }
.toast.success  { background:rgba(57,255,138,.15);  border:1px solid rgba(57,255,138,.4);  color:#0a7c3e; }
.toast.warning  { background:rgba(255,184,0,.15);   border:1px solid rgba(255,184,0,.4);   color:#8a6200; }
.toast.critical { background:rgba(255,59,92,.15);   border:1px solid rgba(255,59,92,.35);  color:var(--red); }
.toast.info     { background:rgba(0,201,177,.12);   border:1px solid rgba(0,201,177,.25);  color:#007a6e; }

/* ====================================================
   OVERVIEW SECTION — Status Banner & Sensor Cards
==================================================== */

/* Status Banner */
.ov-banner {
    display:flex; justify-content:space-between; align-items:center;
    flex-wrap:wrap; gap:.8rem;
    padding:1rem 1.3rem; border-radius:var(--r-lg);
    margin-bottom:1.2rem; border:1px solid transparent;
    position:relative; overflow:hidden; animation:fadeUp .4s ease both;
}
.ov-banner::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
}
.ov-banner.safe     { background:rgba(57,255,138,.07);  border-color:rgba(57,255,138,.28); }
.ov-banner.safe::before { background:linear-gradient(90deg,transparent,var(--green),transparent); }
.ov-banner.warning  { background:rgba(255,184,0,.07);   border-color:rgba(255,184,0,.3); }
.ov-banner.warning::before { background:linear-gradient(90deg,transparent,var(--amber),transparent); }
.ov-banner.critical { background:rgba(255,59,92,.07);   border-color:rgba(255,59,92,.3); animation:pulseGlow 2.2s infinite; }
.ov-banner.critical::before { background:linear-gradient(90deg,transparent,var(--red),transparent); }

.ov-banner-left { display:flex; align-items:center; gap:.9rem; flex-wrap:wrap; }
.ov-banner-icon { font-size:1.6rem; flex-shrink:0; }
.ov-banner.safe     .ov-banner-icon { color:var(--green); }
.ov-banner.warning  .ov-banner-icon { color:var(--amber); }
.ov-banner.critical .ov-banner-icon { color:var(--red); }
.ov-banner-title { font-size:.92rem; font-weight:700; margin-bottom:.18rem; }
.ov-banner.safe     .ov-banner-title { color:#0a7c3e; }
.ov-banner.warning  .ov-banner-title { color:#8a6200; }
.ov-banner.critical .ov-banner-title { color:var(--red); }
.ov-banner-msg { font-size:.78rem; color:var(--txt2); }
.ov-next-refresh {
    font-family:var(--fm); font-size:.68rem; color:var(--muted);
    background:var(--bg-elevated); border:1px solid var(--bdr);
    padding:.28rem .7rem; border-radius:6px;
    white-space:nowrap;
}
.ov-next-refresh span { color:var(--teal); font-weight:700; }

/* Sensor Summary Cards Grid */
.ov-sensor-grid {
    display:grid; grid-template-columns:repeat(3,1fr); gap:1rem;
    margin-bottom:1rem;
}
.ov-sensor-card {
    background:var(--bg-card); border:1px solid var(--bdr);
    border-radius:var(--r-lg); padding:1.1rem 1.1rem;
    transition:.3s; position:relative; overflow:hidden;
    animation:fadeUp .5s ease both;
    box-shadow:0 2px 10px rgba(0,0,0,.04);
}
.ov-sensor-card:hover { border-color:var(--bdr-glow); transform:translateY(-2px); box-shadow:0 6px 24px rgba(0,201,177,.1); }
.ov-sensor-header {
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:.65rem; gap:.4rem; flex-wrap:wrap;
}
.ov-sensor-label { font-size:.74rem; font-weight:700; display:flex; align-items:center; gap:.4rem; color:var(--txt2); }
.ov-sensor-badge {
    font-family:var(--fm); font-size:.58rem; font-weight:700;
    padding:.15rem .55rem; border-radius:4px; letter-spacing:.3px;
    background:rgba(57,255,138,.1); color:#0a7c3e; border:1px solid rgba(57,255,138,.3);
}
.ov-sensor-badge.warn   { background:rgba(255,184,0,.1); color:#8a6200; border-color:rgba(255,184,0,.3); }
.ov-sensor-badge.crit   { background:rgba(255,59,92,.1);  color:var(--red);   border-color:rgba(255,59,92,.3); animation:blink 1.4s infinite; }
.ov-sensor-value { display:flex; align-items:baseline; gap:.35rem; margin-bottom:.65rem; }
.ov-val { font-family:var(--fm); font-size:2.1rem; font-weight:700; line-height:1; }
.ov-unit { font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
#ovCardOrganic .ov-val { color:var(--green); }
#ovCardTemp    .ov-val { color:var(--amber); }
#ovCardPH      .ov-val { color:var(--violet); }
.ov-sensor-bar { height:5px; background:rgba(0,0,0,.08); border-radius:3px; overflow:hidden; margin-bottom:.5rem; }
.ov-bar-fill { height:100%; border-radius:3px; transition:width 1.2s ease; }
.ov-bar-organic { background:linear-gradient(90deg,var(--green),rgba(57,255,138,.5)); }
.ov-bar-temp    { background:linear-gradient(90deg,var(--amber),rgba(255,184,0,.5)); }
.ov-bar-ph      { background:linear-gradient(90deg,var(--violet),rgba(176,108,255,.5)); }
.ov-sensor-thresholds {
    display:flex; justify-content:space-between;
    font-family:var(--fm); font-size:.6rem; color:var(--muted);
}

/* Actions Row */
.ov-actions-row {
    display:flex; justify-content:space-between; align-items:center;
    flex-wrap:wrap; gap:.6rem; margin-bottom:1.2rem;
    padding:.7rem 1rem; background:var(--bg-elevated);
    border:1px solid var(--bdr); border-radius:var(--r-md);
}
.ov-last-updated { font-family:var(--fm); font-size:.7rem; color:var(--muted); }
.ov-last-updated strong { color:var(--txt2); }

/* ====================================================
   FOOTER
==================================================== */
.dash-footer {
    padding:.75rem 1.4rem; background:var(--bg-card); border:1px solid var(--bdr);
    border-radius:var(--r-lg); display:flex; justify-content:space-between; align-items:center;
    flex-wrap:wrap; gap:.5rem; font-family:var(--fm); font-size:.67rem; color:var(--muted);
    margin-top:.5rem; box-shadow:0 2px 8px rgba(0,0,0,.04);
}

/* ====================================================
   SIM TOGGLE
==================================================== */
.sim-bar {
    display:flex; align-items:center; gap:.6rem; flex-wrap:wrap;
}

/* ====================================================
   RESPONSIVE — TABLET ≤1100px
==================================================== */
@media(max-width:1100px) {
    :root { --sidebar-w:220px; }
    .kpi-grid  { grid-template-columns:repeat(3,1fr); }
    .grid-2    { grid-template-columns:1fr; }
    .grid-3    { grid-template-columns:1fr 1fr; }
    .gauges-row{ grid-template-columns:repeat(3,1fr); }
    .hero-readings { grid-template-columns:repeat(3,1fr); }
    .ov-sensor-grid { grid-template-columns:1fr 1fr 1fr; }
}

/* ====================================================
   RESPONSIVE — MOBILE ≤768px
==================================================== */
@media(max-width:768px) {
    :root { --nav-h:58px; }
    .sidebar { transform:translateX(-100%); z-index:700; }
    .sidebar.open { transform:translateX(0); }
    .sidebar-overlay { display:block; }
    .topnav { display:flex; }
    .main   { margin-left:0; }
    .bottom-nav { display:grid; }
    .content {
        padding:1rem;
        padding-bottom:calc(var(--bnav-h) + env(safe-area-inset-bottom,12px) + 16px);
    }
    .topbar { padding:.6rem .9rem; }
    .topbar-left { gap:.5rem; }
    .ov-sensor-grid { grid-template-columns:1fr; }
    .ov-banner { flex-direction:column; align-items:flex-start; }
    .ov-actions-row { flex-direction:column; align-items:flex-start; }
    .kpi-grid { grid-template-columns:1fr 1fr; gap:.7rem; }
    .kpi { padding:.95rem 1rem; }
    .kpi-val { font-size:1.5rem; }
    .grid-2 { grid-template-columns:1fr; }
    .grid-3 { grid-template-columns:1fr 1fr; }
    .gauges-row { grid-template-columns:repeat(3,1fr); }
    .hero-readings { grid-template-columns:1fr; gap:.7rem; }
    .chart-wrap { height:200px; }
    .modal-box { padding:1.3rem; max-width:100%; border-radius:var(--r-lg); }
    .toast-wrap { right:10px; left:10px; top:68px; }
    .toast { min-width:unset; width:100%; }
    .dash-footer { flex-direction:column; text-align:center; }
    .notify-levels { grid-template-columns:repeat(3,1fr); }
}

/* ====================================================
   RESPONSIVE — SMALL ≤480px
==================================================== */
@media(max-width:480px) {
    .kpi-grid { grid-template-columns:1fr 1fr; gap:.5rem; }
    .kpi { padding:.8rem .85rem; }
    .kpi-val { font-size:1.3rem; }
    .kpi-icon { width:32px; height:32px; font-size:.82rem; margin-bottom:.5rem; }
    .grid-3 { grid-template-columns:1fr; }
    .gauges-row { grid-template-columns:1fr 1fr; }
    .bnav-item { font-size:.52rem; }
    .bnav-item i { font-size:1rem; }
}

/* TOUCH */
@media(hover:none) and (pointer:coarse) {
    .btn { min-height:44px; }
    .bnav-item { min-height:52px; }
    .nav-item  { min-height:44px; }
}

/* REDUCED MOTION */
@media(prefers-reduced-motion:reduce) {
    *,*::before,*::after { animation-duration:.01ms!important; transition-duration:.01ms!important; }
    .blink-dot,.dot-blink { animation:none!important; }
    body::before { animation:none!important; }
}

/* PRINT */
@media print {
    .sidebar,.topnav,.bottom-nav,.toast-wrap,.modal { display:none!important; }
    .main { margin-left:0!important; }
    .content { padding:.5rem!important; }
    body { background:#fff!important; color:#000!important; }
    .card { background:#fff!important; border:1px solid #ccc!important; break-inside:avoid; }
    .section-panel { display:block!important; }
}
</style>
</head>
<body>

<!-- TOAST CONTAINER -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ====================================================
     SIDEBAR
==================================================== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
        <div class="sidebar-logo"><i class="fas fa-fish"></i></div>
        <div>
            <div class="sidebar-title">AquaStaff</div>
            <div class="sidebar-sub">Pond <?php echo htmlspecialchars($pond); ?> · Manolo Fortich</div>
        </div>
    </div>

    <div class="nav-section">Monitoring</div>
    <div class="nav-item active" onclick="showSection('overview',this)">
        <i class="fas fa-chart-pie"></i> Overview
    </div>
    <div class="nav-item" onclick="showSection('readings',this)">
        <i class="fas fa-tachometer-alt"></i> Live Readings
    </div>
    <div class="nav-item" onclick="showSection('trends',this)">
        <i class="fas fa-chart-area"></i> Trends
    </div>
    <div class="nav-item" onclick="showSection('history',this)">
        <i class="fas fa-history"></i> Reading History
    </div>

    <div class="nav-section">Operations</div>
    <div class="nav-item" onclick="showSection('maintenance',this)">
        <i class="fas fa-clipboard-list"></i> Maintenance Log
    </div>
    <div class="nav-item" onclick="showSection('profile',this)">
        <i class="fas fa-id-badge"></i> My Profile
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <?php
            $initials='';
            foreach(explode(' ',$full_name) as $n) $initials.=strtoupper(substr($n,0,1));
            ?>
            <div class="sidebar-avatar"><?php echo $initials ?: '?'; ?></div>
            <div>
                <div class="sidebar-uname"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="sidebar-urole">Staff · Pond <?php echo htmlspecialchars($pond); ?></div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn-logout-sidebar" onclick="return confirm('Logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<!-- ====================================================
     LAYOUT
==================================================== -->
<div class="layout">
<div class="main">

<!-- TOPNAV (mobile) -->
<nav class="topnav">
    <div class="topnav-brand">
        <div class="topnav-logo"><i class="fas fa-fish"></i></div>
        <div class="topnav-title">AquaStaff — Pond <?php echo htmlspecialchars($pond); ?></div>
    </div>
    <div class="topnav-right">
        <div class="topnav-clock" id="topnavClock"><?php echo date('h:i:s A'); ?></div>
        <a href="../auth/logout.php" class="topnav-logout" onclick="return confirm('Logout?')" aria-label="Logout" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
        <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>

<!-- CONTENT -->
<div class="content">

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <div>
            <div class="topbar-day"><?php echo date('l'); ?></div>
            <div class="topbar-date"><?php echo date('F j, Y'); ?></div>
        </div>
        <div class="sys-tag green"><span class="blink-dot"></span> SENSOR ONLINE</div>
        <div class="sys-tag teal"><i class="fas fa-microchip" style="font-size:.6rem"></i> POND <?php echo $pond; ?></div>
        <div class="sys-tag <?php echo $current_status === 'safe' ? 'green' : ($current_status === 'warning' ? 'amber' : 'red'); ?>" id="statusTag">
            <span class="blink-dot"></span> <?php echo strtoupper($current_status); ?>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:.7rem;flex-wrap:wrap">
        <div class="iot-live"><span></span> LIVE SIMULATION</div>
        <div class="nav-clock" id="mainClock"><?php echo date('h:i:s A'); ?></div>
        <button class="btn btn-ghost btn-sm" id="simToggleBtn" onclick="toggleSim()">
            <i class="fas fa-pause" id="simIcon"></i> <span id="simLabel">Pause</span>
        </button>
    </div>
</div>

<!-- ==============================
     SECTION: OVERVIEW
============================== -->
<div class="section-panel active" id="sec-overview">

    <!-- ── Pond Status Banner ─────────────────────────────── -->
    <?php
    $bannerClass = $current_status === 'safe'
        ? 'ov-banner safe'
        : ($current_status === 'warning' ? 'ov-banner warning' : 'ov-banner critical');
    $bannerIcon  = $current_status === 'safe'
        ? 'check-circle'
        : ($current_status === 'warning' ? 'exclamation-triangle' : 'times-circle');
    $bannerMsg   = $current_status === 'safe'
        ? 'All readings are within safe range. No action required.'
        : ($current_status === 'warning'
            ? 'One or more readings are approaching threshold. Monitor closely.'
            : 'Critical reading detected! Immediate action may be required.');
    ?>
    <div class="<?php echo $bannerClass; ?>" id="ovBanner">
        <div class="ov-banner-left">
            <div class="ov-banner-icon"><i class="fas fa-<?php echo $bannerIcon; ?>"></i></div>
            <div>
                <div class="ov-banner-title" id="ovBannerTitle">
                    Pond <?php echo htmlspecialchars($pond); ?> — Status:
                    <span id="ovBannerStatus"><?php echo strtoupper($current_status); ?></span>
                </div>
                <div class="ov-banner-msg" id="ovBannerMsg"><?php echo $bannerMsg; ?></div>
            </div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <div class="ov-next-refresh">
                Next refresh in <span id="ovCountdown">15</span>s
            </div>
            <button class="btn btn-ghost btn-sm" onclick="manualRefresh()">
                <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh Now
            </button>
        </div>
    </div>

    <!-- ── Live Sensor Summary Cards ─────────────────────── -->
    <div class="ov-sensor-grid">

        <!-- Organic -->
        <div class="ov-sensor-card" id="ovCardOrganic">
            <div class="ov-sensor-header">
                <div class="ov-sensor-label"><i class="fas fa-seedling" style="color:var(--green)"></i> Organic Matter</div>
                <span class="ov-sensor-badge" id="ovOrgBadge">SAFE</span>
            </div>
            <div class="ov-sensor-value">
                <span class="ov-val" id="ovOrgVal"><?php echo $latest_organic; ?></span>
                <span class="ov-unit">mg/L</span>
            </div>
            <div class="ov-sensor-bar">
                <div class="ov-bar-fill ov-bar-organic" id="ovBarOrganic" style="width:<?php echo min(100,$latest_organic/0.35); ?>%"></div>
            </div>
            <div class="ov-sensor-thresholds">
                <span>Safe &lt;<?php echo $thresholds['organic_warn']; ?></span>
                <span>Warn &lt;<?php echo $thresholds['organic_crit']; ?></span>
            </div>
        </div>

        <!-- Temperature -->
        <div class="ov-sensor-card" id="ovCardTemp">
            <div class="ov-sensor-header">
                <div class="ov-sensor-label"><i class="fas fa-thermometer-half" style="color:var(--amber)"></i> Temperature</div>
                <span class="ov-sensor-badge" id="ovTempBadge">SAFE</span>
            </div>
            <div class="ov-sensor-value">
                <span class="ov-val" id="ovTempVal"><?php echo $latest_temp; ?></span>
                <span class="ov-unit">°C</span>
            </div>
            <div class="ov-sensor-bar">
                <div class="ov-bar-fill ov-bar-temp" id="ovBarTemp" style="width:<?php echo min(100,($latest_temp-20)*10); ?>%"></div>
            </div>
            <div class="ov-sensor-thresholds">
                <span>Safe &lt;<?php echo $thresholds['temp_warn']; ?>°C</span>
                <span>Crit &gt;<?php echo $thresholds['temp_crit']; ?>°C</span>
            </div>
        </div>

        <!-- pH -->
        <div class="ov-sensor-card" id="ovCardPH">
            <div class="ov-sensor-header">
                <div class="ov-sensor-label"><i class="fas fa-flask" style="color:var(--violet)"></i> pH Level</div>
                <span class="ov-sensor-badge" id="ovPHBadge">SAFE</span>
            </div>
            <div class="ov-sensor-value">
                <span class="ov-val" id="ovPHVal"><?php echo $latest_ph; ?></span>
                <span class="ov-unit">pH</span>
            </div>
            <div class="ov-sensor-bar">
                <div class="ov-bar-fill ov-bar-ph" id="ovBarPH" style="width:<?php echo min(100,$latest_ph*10); ?>%"></div>
            </div>
            <div class="ov-sensor-thresholds">
                <span>Range <?php echo $thresholds['ph_low_warn']; ?>–<?php echo $thresholds['ph_high_warn']; ?></span>
                <span>Crit &lt;<?php echo $thresholds['ph_low_crit']; ?> / &gt;<?php echo $thresholds['ph_high_crit']; ?></span>
            </div>
        </div>

    </div><!-- /ov-sensor-grid -->

    <!-- ── Quick Actions + Last Updated ─────────────────── -->
    <div class="ov-actions-row">
        <div class="ov-last-updated">
            <i class="far fa-clock"></i> Last reading: <strong id="ovLastTs"><?php echo date('h:i:s A'); ?></strong>
            &nbsp;·&nbsp; Next in <strong id="ovCountdown2">15</strong>s
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button class="btn btn-warning btn-sm" onclick="openNotifyModal()">
                <i class="fas fa-bell"></i> Notify Manager
            </button>
            <button class="btn btn-sky btn-sm" onclick="openLogModal()">
                <i class="fas fa-clipboard-list"></i> Log Action
            </button>
            <button class="btn btn-ghost btn-sm" onclick="showSection('readings')">
                <i class="fas fa-tachometer-alt"></i> Full Sensors
            </button>
        </div>
    </div>

    <!-- ── System Status Row ─────────────────────────────── -->
    <div class="grid-2" style="margin-bottom:1.2rem">

        <!-- Threshold Status -->
        <div class="card">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-sliders-h"></i> Threshold Status</div>
                <div class="iot-live"><span></span> LIVE</div>
            </div>
            <div class="threshold-row ok" id="ov-thr-organic">
                <div class="threshold-label"><i class="fas fa-seedling" style="color:var(--green)"></i> Organic</div>
                <div class="threshold-vals">
                    <span style="font-size:.66rem;color:var(--muted)">Limit: <?php echo $thresholds['organic_warn']; ?> mg/L</span>
                    <span class="threshold-current" id="ov-thr-org-val" style="color:var(--green)"><?php echo $latest_organic; ?> mg/L</span>
                </div>
            </div>
            <div class="threshold-row ok" id="ov-thr-temp">
                <div class="threshold-label"><i class="fas fa-thermometer-half" style="color:var(--amber)"></i> Temperature</div>
                <div class="threshold-vals">
                    <span style="font-size:.66rem;color:var(--muted)">Limit: <?php echo $thresholds['temp_warn']; ?>°C</span>
                    <span class="threshold-current" id="ov-thr-temp-val" style="color:var(--amber)"><?php echo $latest_temp; ?>°C</span>
                </div>
            </div>
            <div class="threshold-row ok" id="ov-thr-ph">
                <div class="threshold-label"><i class="fas fa-flask" style="color:var(--violet)"></i> pH Level</div>
                <div class="threshold-vals">
                    <span style="font-size:.66rem;color:var(--muted)">Range: <?php echo $thresholds['ph_low_warn']; ?>–<?php echo $thresholds['ph_high_warn']; ?></span>
                    <span class="threshold-current" id="ov-thr-ph-val" style="color:var(--violet)"><?php echo $latest_ph; ?></span>
                </div>
            </div>
        </div>

        <!-- Recent Maintenance -->
        <div class="card">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-clipboard-list"></i> Recent Activity</div>
                <button class="btn btn-ghost btn-sm" onclick="showSection('maintenance')"><i class="fas fa-arrow-right"></i> All Logs</button>
            </div>
            <div style="max-height:180px;overflow-y:auto">
                <?php if(empty($maintenance_logs)): ?>
                <div style="text-align:center;padding:1.5rem;color:var(--muted);font-size:.82rem">
                    <i class="fas fa-clipboard" style="font-size:1.5rem;display:block;margin-bottom:.4rem;opacity:.3"></i>
                    No recent maintenance entries
                </div>
                <?php else: foreach(array_slice($maintenance_logs,0,3) as $log): ?>
                <div class="log-item">
                    <div class="log-icon"><i class="fas fa-tools"></i></div>
                    <div style="flex:1">
                        <div class="log-title"><?php echo htmlspecialchars($log['action']); ?></div>
                        <div class="log-meta">
                            <i class="far fa-clock"></i> <?php echo date('M d, h:i A', strtotime($log['logged_at'])); ?>
                        </div>
                        <?php if (!empty($log['notes'])): ?>
                        <div class="log-notes"><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($log['notes']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div><!-- /grid-2 -->

</div><!-- /sec-overview -->

<!-- ==============================
     SECTION: LIVE READINGS
============================== -->
<div class="section-panel" id="sec-readings">

    <!-- Gauges + Thresholds -->
    <div class="grid-2" style="margin-bottom:1.2rem">

        <div class="card">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-tachometer-alt"></i> Sensor Gauges</div>
                <div class="iot-live"><span></span> LIVE</div>
            </div>
            <div class="gauges-row">
                <div class="gauge-container">
                    <div class="gauge-ring-wrap">
                        <svg viewBox="0 0 100 100" width="130" height="130" style="transform:rotate(-90deg)">
                            <circle class="gauge-bg" cx="50" cy="50" r="40"/>
                            <circle id="gaugeOrganic" class="gauge-fill" cx="50" cy="50" r="40" stroke="var(--green)" stroke-dasharray="251.2" stroke-dashoffset="200"/>
                        </svg>
                        <div class="gauge-inner-text">
                            <div class="gauge-inner-val" id="gaugeOrgVal" style="color:var(--green)"><?php echo $latest_organic; ?></div>
                            <div class="gauge-inner-unit">mg/L</div>
                        </div>
                    </div>
                    <div class="gauge-label">Organic</div>
                </div>
                <div class="gauge-container">
                    <div class="gauge-ring-wrap">
                        <svg viewBox="0 0 100 100" width="130" height="130" style="transform:rotate(-90deg)">
                            <circle class="gauge-bg" cx="50" cy="50" r="40"/>
                            <circle id="gaugeTemp" class="gauge-fill" cx="50" cy="50" r="40" stroke="var(--amber)" stroke-dasharray="251.2" stroke-dashoffset="180"/>
                        </svg>
                        <div class="gauge-inner-text">
                            <div class="gauge-inner-val" id="gaugeTempVal" style="color:var(--amber)"><?php echo $latest_temp; ?></div>
                            <div class="gauge-inner-unit">°C</div>
                        </div>
                    </div>
                    <div class="gauge-label">Temperature</div>
                </div>
                <div class="gauge-container">
                    <div class="gauge-ring-wrap">
                        <svg viewBox="0 0 100 100" width="130" height="130" style="transform:rotate(-90deg)">
                            <circle class="gauge-bg" cx="50" cy="50" r="40"/>
                            <circle id="gaugePH" class="gauge-fill" cx="50" cy="50" r="40" stroke="var(--violet)" stroke-dasharray="251.2" stroke-dashoffset="150"/>
                        </svg>
                        <div class="gauge-inner-text">
                            <div class="gauge-inner-val" id="gaugePHVal" style="color:var(--violet)"><?php echo $latest_ph; ?></div>
                            <div class="gauge-inner-unit">pH</div>
                        </div>
                    </div>
                    <div class="gauge-label">pH Level</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-sliders-h"></i> Threshold Monitor</div>
            </div>
            <div id="thresholdPanel">
                <div class="threshold-row ok" id="thr-organic">
                    <div class="threshold-label"><i class="fas fa-seedling" style="color:var(--green)"></i> Organic (mg/L)</div>
                    <div class="threshold-vals">
                        <span style="font-size:.68rem;color:var(--muted)">Safe &lt;<?php echo $thresholds['organic_warn']; ?></span>
                        <span class="threshold-current" id="thr-organic-val" style="color:var(--green)"><?php echo $latest_organic; ?></span>
                    </div>
                </div>
                <div class="threshold-row ok" id="thr-temp">
                    <div class="threshold-label"><i class="fas fa-thermometer-half" style="color:var(--amber)"></i> Temperature (°C)</div>
                    <div class="threshold-vals">
                        <span style="font-size:.68rem;color:var(--muted)">Safe &lt;<?php echo $thresholds['temp_warn']; ?></span>
                        <span class="threshold-current" id="thr-temp-val" style="color:var(--amber)"><?php echo $latest_temp; ?></span>
                    </div>
                </div>
                <div class="threshold-row ok" id="thr-ph">
                    <div class="threshold-label"><i class="fas fa-flask" style="color:var(--violet)"></i> pH Level</div>
                    <div class="threshold-vals">
                        <span style="font-size:.68rem;color:var(--muted)"><?php echo $thresholds['ph_low_warn']; ?>–<?php echo $thresholds['ph_high_warn']; ?></span>
                        <span class="threshold-current" id="thr-ph-val" style="color:var(--violet)"><?php echo $latest_ph; ?></span>
                    </div>
                </div>
                <div style="margin-top:1rem;padding:.7rem;background:var(--bg-elevated);border-radius:var(--r-sm);border:1px solid var(--bdr)">
                    <div style="font-size:.65rem;color:var(--muted);font-family:var(--fm);margin-bottom:.5rem;letter-spacing:.4px">SAFE RANGES</div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;font-family:var(--fm);font-size:.7rem">
                        <div><div style="color:var(--green)">Organic</div><div style="color:var(--muted)">&lt;<?php echo $thresholds['organic_warn']; ?> mg/L</div></div>
                        <div><div style="color:var(--amber)">Temp</div><div style="color:var(--muted)">&lt;<?php echo $thresholds['temp_warn']; ?>°C</div></div>
                        <div><div style="color:var(--violet)">pH</div><div style="color:var(--muted)"><?php echo $thresholds['ph_low_warn']; ?>–<?php echo $thresholds['ph_high_warn']; ?></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Full hero card duplicated for readings section -->
    <div class="hero-status <?php echo $current_status; ?>" id="heroCard2">
        <div class="hero-top">
            <div>
                <div class="hero-pond-name">
                    <i class="fas fa-water" style="color:var(--teal)"></i>
                    Pond <?php echo htmlspecialchars($pond); ?> — Current Values
                </div>
                <div class="hero-meta">Sensor · Manolo Fortich, Bukidnon · Refresh every 15s</div>
            </div>
            <div style="display:flex;align-items:center;gap:.6rem">
                <span class="badge badge-<?php echo $current_status; ?>" id="heroBadge2">
                    <span class="dot-blink"></span> <?php echo strtoupper($current_status); ?>
                </span>
            </div>
        </div>
        <div class="hero-readings">
            <div class="reading-block organic">
                <span class="reading-icon" style="color:var(--green)"><i class="fas fa-seedling"></i></span>
                <div class="reading-val" id="liveOrganic2"><?php echo $latest_organic; ?></div>
                <div class="reading-unit">mg / L</div>
                <div class="reading-label">Organic Matter</div>
            </div>
            <div class="reading-block temp">
                <span class="reading-icon" style="color:var(--amber)"><i class="fas fa-thermometer-half"></i></span>
                <div class="reading-val" id="liveTemp2"><?php echo $latest_temp; ?></div>
                <div class="reading-unit">°C</div>
                <div class="reading-label">Water Temperature</div>
            </div>
            <div class="reading-block ph">
                <span class="reading-icon" style="color:var(--violet)"><i class="fas fa-flask"></i></span>
                <div class="reading-val" id="livePH2"><?php echo $latest_ph; ?></div>
                <div class="reading-unit">pH</div>
                <div class="reading-label">pH Level</div>
            </div>
        </div>
        <div class="hero-bottom">
            <div class="hero-ts"><i class="far fa-clock"></i> Last updated: <span id="heroTs2"><?php echo date('h:i:s A'); ?></span></div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <button class="btn btn-warning btn-sm" onclick="openNotifyModal()"><i class="fas fa-bell"></i> Notify Manager</button>
                <button class="btn btn-sky btn-sm" onclick="openLogModal()"><i class="fas fa-clipboard-list"></i> Log Action</button>
            </div>
        </div>
    </div>
</div>

<!-- ==============================
     SECTION: TRENDS CHART
============================== -->
<div class="section-panel" id="sec-trends">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-chart-area"></i> Pond <?php echo $pond; ?> Trends</div>
            <div class="period-tabs">
                <button class="period-btn active" onclick="switchPeriod('14d',this)">14 DAY</button>
                <button class="period-btn" onclick="switchPeriod('24h',this)">24 HR</button>
                <button class="period-btn" onclick="switchPeriod('7d',this)">7 DAY</button>
            </div>
        </div>
        <div class="chart-wrap"><canvas id="pondChart"></canvas></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;flex-wrap:wrap;gap:.4rem">
            <div style="display:flex;gap:.9rem;font-size:.66rem;font-family:var(--fm);flex-wrap:wrap">
                <span style="color:var(--green)"><i class="fas fa-circle"></i> Organic</span>
                <span style="color:var(--amber)"><i class="fas fa-circle"></i> Temp</span>
                <span style="color:var(--violet)"><i class="fas fa-circle"></i> pH</span>
            </div>
            <div style="font-family:var(--fm);font-size:.66rem;color:var(--muted)" id="chartTs"><?php echo date('h:i:s A'); ?></div>
        </div>
    </div>
</div>

<!-- ==============================
     SECTION: READING HISTORY
============================== -->
<div class="section-panel" id="sec-history">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-history"></i> Reading History</div>
            <div class="iot-live"><span></span> AUTO-UPDATING</div>
        </div>
        <div class="tbl-wrap">
            <table id="historyTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Organic (mg/L)</th>
                        <th>Temp (°C)</th>
                        <th>pH</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <?php
                    $max = min(8, count($organic));
                    for ($i = count($organic)-1; $i >= max(0, count($organic)-$max); $i--):
                        $s = getPondStatus($organic[$i],$temp[$i],$ph[$i],$thresholds);
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-family:var(--fm)"><?php echo $i+1; ?></td>
                        <td><span style="color:var(--green);font-family:var(--fm);font-weight:700"><?php echo $organic[$i]; ?></span></td>
                        <td><span style="color:var(--amber);font-family:var(--fm);font-weight:700"><?php echo $temp[$i]; ?></span></td>
                        <td><span style="color:var(--violet);font-family:var(--fm);font-weight:700"><?php echo $ph[$i]; ?></span></td>
                        <td><span class="badge badge-<?php echo $s; ?>"><?php echo strtoupper($s); ?></span></td>
                        <td style="font-family:var(--fm);font-size:.7rem;color:var(--muted)"><?php echo $dates[$i] ?? date('M d'); ?></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==============================
     SECTION: MAINTENANCE LOG
============================== -->
<div class="section-panel" id="sec-maintenance">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-clipboard-list"></i> Maintenance Activity Log</div>
            <button class="btn btn-primary btn-sm" onclick="openLogModal()"><i class="fas fa-plus"></i> Add Entry</button>
        </div>
        <div id="maintenanceList" style="max-height:480px;overflow-y:auto">
            <?php foreach($maintenance_logs as $log): ?>
            <div class="log-item">
                <div class="log-icon"><i class="fas fa-tools"></i></div>
                <div style="flex:1">
                    <div class="log-title"><?php echo htmlspecialchars($log['action']); ?></div>
                    <div class="log-meta">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($log['logged_by']); ?> &nbsp;·&nbsp;
                        <i class="far fa-clock"></i> <?php echo date('M d, h:i A', strtotime($log['logged_at'])); ?>
                    </div>
                    <?php if (!empty($log['notes'])): ?>
                    <div class="log-notes"><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($log['notes']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ==============================
     SECTION: MY PROFILE
============================== -->
<div class="section-panel" id="sec-profile">
    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-id-badge"></i> My Profile</div>
            </div>
            <?php
            $initials='';
            foreach(explode(' ',$full_name) as $n) $initials.=strtoupper(substr($n,0,1));
            ?>
            <div class="staff-avatar-wrap"><?php echo $initials ?: '?'; ?></div>
            <div class="info-row">
                <span class="info-lbl">Full Name</span>
                <span class="info-val"><?php echo htmlspecialchars($full_name); ?></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Email</span>
                <span class="info-val" style="font-size:.74rem"><?php echo htmlspecialchars($email); ?></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Role</span>
                <span class="info-val"><span class="badge badge-info">STAFF</span></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Assigned Pond</span>
                <span class="info-val"><span class="badge badge-safe"><?php echo htmlspecialchars($pond); ?></span></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Last Login</span>
                <span class="info-val" style="font-size:.74rem"><?php echo htmlspecialchars($last_login); ?></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Session Time</span>
                <span class="info-val" id="sessionTimer" style="color:var(--teal)">00:00:00</span>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
                <button class="btn btn-primary btn-sm" onclick="openLogModal()">
                    <i class="fas fa-clipboard-plus"></i> Log Maintenance
                </button>
                <button class="btn btn-warning btn-sm" onclick="openNotifyModal()">
                    <i class="fas fa-bell"></i> Alert Manager
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div class="card-title"><i class="fas fa-map-marker-alt"></i> Pond Assignment</div>
            </div>
            <div style="text-align:center;padding:1.5rem 0">
                <div style="font-size:3rem;margin-bottom:.7rem;color:var(--teal)"><i class="fas fa-water"></i></div>
                <div style="font-size:1.8rem;font-weight:800;color:var(--teal);font-family:var(--fm);margin-bottom:.3rem">Pond <?php echo htmlspecialchars($pond); ?></div>
                <div style="font-size:.82rem;color:var(--txt2);margin-bottom:.5rem">Manolo Fortich, Bukidnon</div>
                <span class="badge badge-<?php echo $current_status; ?>"><span class="dot-blink"></span> <?php echo strtoupper($current_status); ?></span>
            </div>
            <div class="info-row" style="margin-top:.5rem">
                <span class="info-lbl">Location</span>
                <span class="info-val" style="font-size:.75rem">Manolo Fortich, Bukidnon, PH</span>
            </div>
            <div class="info-row">
                <span class="info-lbl">System</span>
                <span class="info-val" style="font-size:.75rem">Organic Matter Detection</span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Sensor Status</span>
                <span class="info-val"><span class="badge badge-safe"><span class="dot-blink"></span> ONLINE</span></span>
            </div>
        </div>
    </div>
</div>

<!-- FOOTER -->
<div class="dash-footer">
    <div><i class="fas fa-map-marker-alt" style="color:var(--teal)"></i> Manolo Fortich, Bukidnon · Pond <?php echo $pond; ?></div>
    <div>PH TIME: <span id="footerTs" style="color:var(--teal)"><?php echo date('h:i:s A'); ?></span></div>
    <div>&copy; 2026 Organic-Matter Detection in Tilapia</div>
</div>

</div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<!-- BOTTOM NAV (mobile) -->
<nav class="bottom-nav" id="bottomNav">
    <div class="bnav-item active" id="bn-overview" onclick="showSection('overview')">
        <i class="fas fa-chart-pie"></i><span>Overview</span>
    </div>
    <div class="bnav-item" id="bn-readings" onclick="showSection('readings')">
        <i class="fas fa-tachometer-alt"></i><span>Sensors</span>
    </div>
    <div class="bnav-item" id="bn-trends" onclick="showSection('trends')">
        <i class="fas fa-chart-area"></i><span>Trends</span>
    </div>
    <div class="bnav-item" id="bn-maintenance" onclick="showSection('maintenance')">
        <i class="fas fa-clipboard-list"></i><span>Log</span>
    </div>
    <a href="../auth/logout.php" class="bnav-item bnav-logout" onclick="return confirm('Logout?')" style="text-decoration:none">
        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
    </a>
</nav>

<!-- LOG MAINTENANCE MODAL -->
<div id="logModal" class="modal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title"><i class="fas fa-clipboard-plus" style="color:var(--teal)"></i> Log Maintenance Action</div>
            <button class="modal-close" onclick="closeModal('logModal')">&times;</button>
        </div>
        <div class="form-group">
            <label class="form-label">Action Performed *</label>
            <input type="text" class="form-ctrl" id="logAction" placeholder="e.g. Cleaned water filter, Adjusted pH...">
        </div>
        <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-ctrl" id="logCategory">
                <option value="maintenance">Maintenance</option>
                <option value="cleaning">Cleaning</option>
                <option value="calibration">Sensor Calibration</option>
                <option value="feeding">Fish Feeding</option>
                <option value="treatment">Water Treatment</option>
                <option value="inspection">Inspection</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Notes / Observations</label>
            <textarea class="form-ctrl" id="logNotes" placeholder="Additional details..."></textarea>
        </div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <button class="btn btn-ghost" onclick="closeModal('logModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveLog()"><i class="fas fa-save"></i> Save Log</button>
        </div>
    </div>
</div>

<!-- NOTIFY MANAGER MODAL -->
<div id="notifyModal" class="modal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title"><i class="fas fa-bell" style="color:var(--amber)"></i> Notify Manager</div>
            <button class="modal-close" onclick="closeModal('notifyModal')">&times;</button>
        </div>
        <div class="form-group">
            <label class="form-label">Alert Level</label>
            <div class="notify-levels">
                <div class="notify-level-btn" id="nLvlInfo" onclick="setNotifyLevel('info',this)">
                    <i class="fas fa-info-circle"></i><br>Info
                </div>
                <div class="notify-level-btn active-warning" id="nLvlWarning" onclick="setNotifyLevel('warning',this)">
                    <i class="fas fa-exclamation-triangle"></i><br>Warning
                </div>
                <div class="notify-level-btn" id="nLvlCritical" onclick="setNotifyLevel('critical',this)">
                    <i class="fas fa-exclamation-circle"></i><br>Critical
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Message</label>
            <textarea class="form-ctrl" id="notifyMsg" rows="3" placeholder="Describe the issue...">Organic level alert for Pond <?php echo $pond; ?>. Please review current readings.</textarea>
        </div>
        <div style="background:var(--bg-elevated);border-radius:var(--r-sm);padding:.8rem;margin-bottom:1rem;font-size:.78rem;border:1px solid var(--bdr)">
            <div style="color:var(--muted);font-family:var(--fm);font-size:.65rem;margin-bottom:.4rem">CURRENT READINGS</div>
            <div style="display:flex;gap:1rem;font-family:var(--fm);flex-wrap:wrap">
                <span style="color:var(--green)">Org: <strong id="nm-org"><?php echo $latest_organic; ?></strong> mg/L</span>
                <span style="color:var(--amber)">Tmp: <strong id="nm-tmp"><?php echo $latest_temp; ?></strong>°C</span>
                <span style="color:var(--violet)">pH: <strong id="nm-ph"><?php echo $latest_ph; ?></strong></span>
            </div>
        </div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end">
            <button class="btn btn-ghost" onclick="closeModal('notifyModal')">Cancel</button>
            <button class="btn btn-warning" onclick="sendNotification()"><i class="fas fa-paper-plane"></i> Send to Manager</button>
        </div>
    </div>
</div>

<script>
// ============================================================
// GLOBALS
// ============================================================
const POND  = '<?php echo $pond; ?>';
const THR   = <?php echo json_encode($thresholds); ?>;
const FNAME = '<?php echo addslashes($full_name); ?>';

const ALL_SECTIONS  = ['overview','readings','trends','history','maintenance','profile'];
const BNAV_SECTIONS = ['overview','readings','trends','maintenance'];

let metricsChart;
let simInterval;
let countdownInterval;
let isSimRunning = true;
let sessionSecs  = 0;
let notifyLevel  = 'warning';

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initChart();
    startSim();
    startSessionTimer();
    // Populate overview panel immediately with server-side values
    updateOverviewPanel(
        <?php echo $latest_organic; ?>,
        <?php echo $latest_temp; ?>,
        <?php echo $latest_ph; ?>,
        '<?php echo $current_status; ?>',
        '<?php echo date('h:i:s A'); ?>'
    );
});

// ============================================================
// CLOCK
// ============================================================
function initClock() {
    setInterval(() => {
        const ph = new Date().toLocaleTimeString('en-US',{
            timeZone:'Asia/Manila', hour12:true,
            hour:'2-digit', minute:'2-digit', second:'2-digit'
        });
        ['mainClock','topnavClock'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=ph;});
        ['chartTs','footerTs'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=ph;});
    }, 1000);
}

// ============================================================
// SESSION TIMER
// ============================================================
function startSessionTimer() {
    setInterval(() => {
        sessionSecs++;
        const h=String(Math.floor(sessionSecs/3600)).padStart(2,'0');
        const m=String(Math.floor((sessionSecs%3600)/60)).padStart(2,'0');
        const s=String(sessionSecs%60).padStart(2,'0');
        const el=document.getElementById('sessionTimer');
        if(el) el.textContent=`${h}:${m}:${s}`;
    }, 1000);
}

// ============================================================
// SIDEBAR
// ============================================================
function toggleSidebar(){
    const sb=document.getElementById('sidebar'), ov=document.getElementById('sidebarOverlay');
    const open=sb.classList.contains('open');
    if(open){sb.classList.remove('open');ov.classList.remove('active');}
    else{sb.classList.add('open');ov.classList.add('active');}
}
function closeSidebar(){
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

// ============================================================
// SECTION NAVIGATION
// ============================================================
function showSection(name, clickedItem) {
    ALL_SECTIONS.forEach(s=>{
        const el=document.getElementById('sec-'+s);
        if(el) el.classList.remove('active');
    });
    const t=document.getElementById('sec-'+name);
    if(t) t.classList.add('active');

    document.querySelectorAll('.nav-item').forEach(item=>{
        item.classList.remove('active');
        const oc=item.getAttribute('onclick')||'';
        if(oc.indexOf("'"+name+"'")!==-1) item.classList.add('active');
    });

    document.querySelectorAll('.bnav-item:not(.bnav-logout)').forEach(b=>b.classList.remove('active'));
    const bnEl=document.getElementById('bn-'+name);
    if(bnEl) bnEl.classList.add('active');

    if(name==='trends' && metricsChart) setTimeout(()=>metricsChart.resize(),100);
    if(window.innerWidth<=768) closeSidebar();
    window.scrollTo({top:0,behavior:'smooth'});
}

// ============================================================
// CHART
// ============================================================
function initChart() {
    const ctx = document.getElementById('pondChart').getContext('2d');
    const go = ctx.createLinearGradient(0,0,0,240);
    go.addColorStop(0,'rgba(57,255,138,.22)'); go.addColorStop(1,'rgba(57,255,138,0)');
    const gt = ctx.createLinearGradient(0,0,0,240);
    gt.addColorStop(0,'rgba(255,184,0,.18)'); gt.addColorStop(1,'rgba(255,184,0,0)');
    const gp = ctx.createLinearGradient(0,0,0,240);
    gp.addColorStop(0,'rgba(176,108,255,.18)'); gp.addColorStop(1,'rgba(176,108,255,0)');

    metricsChart = new Chart(ctx, {
        type:'line',
        data:{
            labels: <?php echo json_encode($dates); ?>,
            datasets:[
                {label:'Organic (mg/L)', data:<?php echo json_encode($organic); ?>, borderColor:'#39ff8a', backgroundColor:go, fill:true, tension:.4, borderWidth:2, pointRadius:3, pointHoverRadius:6, yAxisID:'yO'},
                {label:'Temp (°C)',      data:<?php echo json_encode($temp); ?>,    borderColor:'#ffb800', backgroundColor:gt, fill:true, tension:.4, borderWidth:2, pointRadius:0, pointHoverRadius:5, yAxisID:'yT'},
                {label:'pH',             data:<?php echo json_encode($ph); ?>,      borderColor:'#b06cff', backgroundColor:gp, fill:true, tension:.4, borderWidth:2, pointRadius:0, pointHoverRadius:5, yAxisID:'yP'}
            ]
        },
        options:{
            responsive:true, maintainAspectRatio:false,
            animation:{duration:800},
            interaction:{mode:'index',intersect:false},
            plugins:{
                legend:{display:false},
                tooltip:{backgroundColor:'rgba(255,255,255,.97)',borderColor:'rgba(0,201,177,.25)',borderWidth:1,titleColor:'#0d1f2d',bodyColor:'#2e4a63',padding:10}
            },
            scales:{
                x:{grid:{color:'rgba(0,0,0,.06)',drawBorder:false},ticks:{color:'#6b8ba8',font:{family:"'Space Mono'",size:9},maxTicksLimit:8}},
                yO:{position:'left',  grid:{color:'rgba(0,0,0,.06)',drawBorder:false},ticks:{color:'#6b8ba8',font:{family:"'Space Mono'",size:9}}},
                yT:{position:'right', grid:{drawOnChartArea:false},ticks:{color:'#6b8ba8',font:{family:"'Space Mono'",size:9}}},
                yP:{position:'right', grid:{drawOnChartArea:false},ticks:{color:'#6b8ba8',font:{family:"'Space Mono'",size:9}},offset:true}
            }
        }
    });
}

function switchPeriod(period, btn) {
    document.querySelectorAll('.period-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const orig=btn.textContent;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=get_history'})
    .then(r=>r.json())
    .then(d=>{
        metricsChart.data.labels=d.labels;
        metricsChart.data.datasets[0].data=d.organic;
        metricsChart.data.datasets[1].data=d.temp;
        metricsChart.data.datasets[2].data=d.ph;
        metricsChart.update();
        btn.textContent=orig;
        toast(`Chart updated: ${period}`,'info');
    });
}

// ============================================================
// SIMULATION
// ============================================================
function startSim() {
    isSimRunning=true;
    document.getElementById('simIcon').className='fas fa-pause';
    document.getElementById('simLabel').textContent='Pause';
    simInterval=setInterval(fetchReading,15000);
    startCountdown();
}
function stopSim() {
    isSimRunning=false;
    clearInterval(simInterval);
    clearInterval(countdownInterval);
    document.getElementById('simIcon').className='fas fa-play';
    document.getElementById('simLabel').textContent='Resume';
}
function toggleSim() {
    isSimRunning ? stopSim() : startSim();
    toast(isSimRunning?'Simulation paused':'Simulation resumed', isSimRunning?'warning':'success');
}

function fetchReading() {
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=get_live_reading&pond=${POND}`})
    .then(r=>r.json())
    .then(d=>{
        if(!d.success) return;
        updateDisplay(d.organic,d.temp,d.ph,d.status,d.timestamp);
        addHistoryRow(d.organic,d.temp,d.ph,d.status);
        if(d.status==='critical')
            toast(`⚠ CRITICAL: Organic ${d.organic} mg/L on Pond ${POND}`,'critical');
        resetCountdown();
    });
}

// ── Countdown timer (visible in Overview) ──────────────────
function startCountdown() {
    clearInterval(countdownInterval);
    let secs = 15;
    function tick() {
        secs = Math.max(0, secs - 1);
        ['ovCountdown','ovCountdown2'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=secs;});
    }
    tick();
    countdownInterval = setInterval(tick, 1000);
}
function resetCountdown() {
    startCountdown();
}

function manualRefresh() {
    const icon=document.getElementById('refreshIcon');
    icon.style.animation='spin 1s linear infinite';
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=get_live_reading&pond=${POND}`})
    .then(r=>r.json())
    .then(d=>{
        icon.style.animation='';
        if(!d.success) return;
        updateDisplay(d.organic,d.temp,d.ph,d.status,d.timestamp);
        resetCountdown();
        toast('Readings refreshed','success');
    });
}

// ============================================================
// UPDATE DISPLAY
// ============================================================
function updateDisplay(organic, temp, ph, status, ts) {
    // ── Live Readings section (heroCard2) ──────────────────
    ['liveOrganic2'].forEach(id=>{const e=document.getElementById(id);if(e){e.textContent=organic;e.style.animation='countFlash .6s ease';setTimeout(()=>e.style.animation='',600);}});
    ['liveTemp2'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=temp;});
    ['livePH2'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=ph;});
    ['heroTs2'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=ts||new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true});});

    // Hero status class + badge (readings section only)
    const hc2=document.getElementById('heroCard2');
    if(hc2) hc2.className=`hero-status ${status}`;
    const hb2=document.getElementById('heroBadge2');
    if(hb2){hb2.className=`badge badge-${status}`;hb2.innerHTML=`<span class="dot-blink"></span> ${status.toUpperCase()}`;}

    // ── Progress bars (readings section) ──────────────────
    const bO=document.getElementById('barOrganic'),bT=document.getElementById('barTemp'),bP=document.getElementById('barPH');
    if(bO){bO.style.width=Math.min(100,organic/0.35)+'%';bO.style.background=organic>=THR.organic_crit?'var(--red)':(organic>=THR.organic_warn?'var(--amber)':'var(--green)');}
    if(bT) bT.style.width=Math.min(100,(temp-20)*10)+'%';
    if(bP) bP.style.width=Math.min(100,ph*10)+'%';

    // ── Gauges ─────────────────────────────────────────────
    updateGauge('gaugeOrganic',organic,0,35);
    updateGauge('gaugeTemp',temp,20,38);
    updateGauge('gaugePH',ph,5,10);
    const gOv=document.getElementById('gaugeOrgVal'),gTv=document.getElementById('gaugeTempVal'),gPv=document.getElementById('gaugePHVal');
    if(gOv) gOv.textContent=organic;
    if(gTv) gTv.textContent=temp;
    if(gPv) gPv.textContent=ph;

    // ── Threshold panel (readings section) ─────────────────
    updateThreshold('thr-organic','thr-organic-val',organic,THR.organic_warn,THR.organic_crit,'var(--green)',true);
    updateThreshold('thr-temp',  'thr-temp-val',  temp,  THR.temp_warn,  THR.temp_crit,  'var(--amber)',true);
    updateThresholdPH('thr-ph','thr-ph-val',ph);

    // ── Topbar status tag ──────────────────────────────────
    const tag=document.getElementById('statusTag');
    if(tag){const cls=status==='safe'?'green':(status==='warning'?'amber':'red');tag.className=`sys-tag ${cls}`;tag.innerHTML=`<span class="blink-dot"></span> ${status.toUpperCase()}`;}

    // ── Notify modal current readings ──────────────────────
    const nO=document.getElementById('nm-org'),nT=document.getElementById('nm-tmp'),nP=document.getElementById('nm-ph');
    if(nO) nO.textContent=organic;
    if(nT) nT.textContent=temp;
    if(nP) nP.textContent=ph;

    // ── Overview section updates ───────────────────────────
    updateOverviewPanel(organic, temp, ph, status, ts);
}

// ============================================================
// UPDATE OVERVIEW PANEL
// ============================================================
function updateOverviewPanel(organic, temp, ph, status, ts) {
    const now = ts || new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true});

    const ovOrg = document.getElementById('ovOrgVal');
    const ovTmp = document.getElementById('ovTempVal');
    const ovPH  = document.getElementById('ovPHVal');
    if(ovOrg){ovOrg.textContent=organic;ovOrg.style.animation='countFlash .6s ease';setTimeout(()=>ovOrg.style.animation='',600);}
    if(ovTmp) ovTmp.textContent=temp;
    if(ovPH)  ovPH.textContent=ph;

    ['ovLastTs'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=now;});

    const ovBO=document.getElementById('ovBarOrganic'),ovBT=document.getElementById('ovBarTemp'),ovBP=document.getElementById('ovBarPH');
    if(ovBO) ovBO.style.width=Math.min(100,organic/0.35)+'%';
    if(ovBT) ovBT.style.width=Math.min(100,(temp-20)*10)+'%';
    if(ovBP) ovBP.style.width=Math.min(100,ph*10)+'%';

    function sensorBadge(elId, val, warn, crit, isHigh) {
        const el=document.getElementById(elId); if(!el) return;
        const bad  = isHigh ? val>=crit : val<=crit;
        const warn_ = isHigh ? val>=warn : val<=warn;
        if(bad)       { el.className='ov-sensor-badge crit'; el.textContent='CRITICAL'; }
        else if(warn_){ el.className='ov-sensor-badge warn'; el.textContent='WARNING'; }
        else          { el.className='ov-sensor-badge';      el.textContent='SAFE'; }
    }
    sensorBadge('ovOrgBadge',  organic, THR.organic_warn, THR.organic_crit, true);
    sensorBadge('ovTempBadge', temp,    THR.temp_warn,    THR.temp_crit,    true);

    const phBadge=document.getElementById('ovPHBadge');
    if(phBadge){
        if(ph<=THR.ph_low_crit||ph>=THR.ph_high_crit)     { phBadge.className='ov-sensor-badge crit'; phBadge.textContent='CRITICAL'; }
        else if(ph<=THR.ph_low_warn||ph>=THR.ph_high_warn) { phBadge.className='ov-sensor-badge warn'; phBadge.textContent='WARNING'; }
        else                                               { phBadge.className='ov-sensor-badge';      phBadge.textContent='SAFE'; }
    }

    const banner=document.getElementById('ovBanner');
    const bannerStatus=document.getElementById('ovBannerStatus');
    const bannerMsg=document.getElementById('ovBannerMsg');
    if(banner) banner.className='ov-banner '+status;
    if(bannerStatus) bannerStatus.textContent=status.toUpperCase();
    const msgs={
        safe:    'All readings are within safe range. No action required.',
        warning: 'One or more readings are approaching threshold. Monitor closely.',
        critical:'Critical reading detected! Immediate action may be required.'
    };
    if(bannerMsg) bannerMsg.textContent=msgs[status]||msgs.safe;

    updateThreshold('ov-thr-organic','ov-thr-org-val',  organic, THR.organic_warn, THR.organic_crit, 'var(--green)', true);
    updateThreshold('ov-thr-temp',   'ov-thr-temp-val', temp,    THR.temp_warn,    THR.temp_crit,    'var(--amber)', true);
    updateThresholdPH('ov-thr-ph',   'ov-thr-ph-val',   ph);

    const otv=document.getElementById('ov-thr-org-val');  if(otv) otv.textContent=organic+' mg/L';
    const ttv=document.getElementById('ov-thr-temp-val'); if(ttv) ttv.textContent=temp+'°C';
    const ptv=document.getElementById('ov-thr-ph-val');   if(ptv) ptv.textContent=ph;
}

function updateGauge(id, val, min, max) {
    const el=document.getElementById(id);if(!el) return;
    const pct=Math.max(0,Math.min(1,(val-min)/(max-min)));
    el.style.strokeDashoffset=251.2*(1-pct);
}

function updateThreshold(rowId, valId, val, warn, crit, okColor, highIsBad) {
    const row=document.getElementById(rowId),span=document.getElementById(valId);if(!row||!span) return;
    span.textContent=val;
    const isBad=highIsBad?val>=crit:val<=crit;
    const isWarn=highIsBad?val>=warn:val<=warn;
    if(isBad)      {row.className='threshold-row crit';span.style.color='var(--red)';}
    else if(isWarn){row.className='threshold-row warn';span.style.color='var(--amber)';}
    else           {row.className='threshold-row ok';  span.style.color=okColor;}
}

function updateThresholdPH(rowId, valId, ph) {
    const row=document.getElementById(rowId),span=document.getElementById(valId);if(!row||!span) return;
    span.textContent=ph;
    if(ph<=THR.ph_low_crit||ph>=THR.ph_high_crit)     {row.className='threshold-row crit';span.style.color='var(--red)';}
    else if(ph<=THR.ph_low_warn||ph>=THR.ph_high_warn) {row.className='threshold-row warn';span.style.color='var(--amber)';}
    else                                               {row.className='threshold-row ok';  span.style.color='var(--violet)';}
}

// ============================================================
// HISTORY TABLE
// ============================================================
let readings = [
    <?php
    $max=min(8,count($organic));
    for($i=count($organic)-$max;$i<count($organic);$i++){
        echo "{org:".($organic[$i]??0).",tmp:".($temp[$i]??0).",ph:".($ph[$i]??0).",ts:'".($dates[$i]??'')."'},";
    }
    ?>
];

function addHistoryRow(organic, temp, ph, status) {
    readings.unshift({org:organic,tmp:temp,ph:ph,ts:new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true})});
    if(readings.length>12) readings.pop();
    const tbody=document.getElementById('historyBody');if(!tbody) return;
    const newRow=document.createElement('tr');
    newRow.style.animation='slideIn .3s ease';
    newRow.innerHTML=`
        <td style="color:var(--muted);font-family:var(--fm)">${readings.length}</td>
        <td><span style="color:var(--green);font-family:var(--fm);font-weight:700">${organic}</span></td>
        <td><span style="color:var(--amber);font-family:var(--fm);font-weight:700">${temp}</span></td>
        <td><span style="color:var(--violet);font-family:var(--fm);font-weight:700">${ph}</span></td>
        <td><span class="badge badge-${status}">${status.toUpperCase()}</span></td>
        <td style="font-family:var(--fm);font-size:.7rem;color:var(--muted)">Just now</td>`;
    tbody.insertBefore(newRow,tbody.firstChild);
    if(tbody.children.length>12) tbody.removeChild(tbody.lastChild);
}

// ============================================================
// MAINTENANCE LOG
// ============================================================
function openLogModal()  { openModal('logModal'); }

function saveLog() {
    const action=document.getElementById('logAction').value.trim();
    const notes =document.getElementById('logNotes').value.trim();
    if(!action){toast('Action field is required','warning');return;}
    const saveBtn=document.querySelector('#logModal .btn-primary');
    saveBtn.disabled=true;saveBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=log_maintenance&action_text=${encodeURIComponent(action)}&notes=${encodeURIComponent(notes)}`})
    .then(r=>r.json())
    .then(d=>{
        saveBtn.disabled=false;saveBtn.innerHTML='<i class="fas fa-save"></i> Save Log';
        if(!d.success){toast(d.message,'critical');return;}
        const list=document.getElementById('maintenanceList');
        const el=document.createElement('div');
        el.className='log-item';
        el.innerHTML=`
            <div class="log-icon"><i class="fas fa-tools"></i></div>
            <div style="flex:1">
                <div class="log-title">${action}</div>
                <div class="log-meta"><i class="fas fa-user"></i> ${FNAME} &nbsp;·&nbsp; <i class="far fa-clock"></i> Just now</div>
                ${notes?`<div class="log-notes"><i class="fas fa-sticky-note"></i> ${notes}</div>`:''}
            </div>`;
        if(list) list.insertBefore(el,list.firstChild);
        document.getElementById('logAction').value='';
        document.getElementById('logNotes').value='';
        closeModal('logModal');
        toast('Maintenance log saved','success');
    });
}

// ============================================================
// NOTIFY MANAGER
// ============================================================
function openNotifyModal() { openModal('notifyModal'); }

function setNotifyLevel(level, el) {
    notifyLevel=level;
    document.querySelectorAll('.notify-level-btn').forEach(b=>b.className='notify-level-btn');
    el.className=`notify-level-btn active-${level}`;
}

function sendNotification() {
    const msg=document.getElementById('notifyMsg').value.trim();
    if(!msg){toast('Message required','warning');return;}
    const btn=document.querySelector('#notifyModal .btn-warning');
    btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending…';
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=notify_manager&message=${encodeURIComponent(msg)}&level=${notifyLevel}`})
    .then(r=>r.json())
    .then(d=>{
        btn.disabled=false;btn.innerHTML='<i class="fas fa-paper-plane"></i> Send to Manager';
        closeModal('notifyModal');
        toast(d.message,'success');
    });
}

// ============================================================
// MODAL HELPERS
// ============================================================
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m.id);}));
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal.open').forEach(m=>m.classList.remove('open'));});

// ============================================================
// TOAST
// ============================================================
function toast(msg, type='info') {
    const c=document.getElementById('toastWrap'),t=document.createElement('div');
    t.className=`toast ${type}`;
    const icons={success:'check-circle',warning:'exclamation-triangle',critical:'times-circle',info:'info-circle'};
    t.innerHTML=`<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
    c.appendChild(t);
    setTimeout(()=>{t.style.opacity='0';t.style.transform='translateX(50px)';t.style.transition='.3s';setTimeout(()=>t.remove(),300);},3500);
}

// ============================================================
// RESIZE
// ============================================================
window.addEventListener('resize',()=>{if(metricsChart)metricsChart.resize();});
window.addEventListener('orientationchange',()=>{setTimeout(()=>{if(metricsChart)metricsChart.resize();},350);});

// ============================================================
// SWIPE TO OPEN SIDEBAR
// ============================================================
(function(){
    let sx=0,sy=0;
    document.addEventListener('touchstart',e=>{sx=e.touches[0].clientX;sy=e.touches[0].clientY;},{passive:true});
    document.addEventListener('touchend',e=>{
        const dx=e.changedTouches[0].clientX-sx, dy=Math.abs(e.changedTouches[0].clientY-sy);
        if(dy>60) return;
        if(dx>70&&sx<30){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('active');}
        else if(dx<-70&&document.getElementById('sidebar').classList.contains('open')) closeSidebar();
    },{passive:true});
})();

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown',e=>{
    if(document.querySelector('.modal.open')) return;
    if(e.altKey){const m={'1':'overview','2':'readings','3':'trends','4':'history','5':'maintenance','6':'profile'};if(m[e.key]){e.preventDefault();showSection(m[e.key]);}}
});
</script>
</body>
</html>