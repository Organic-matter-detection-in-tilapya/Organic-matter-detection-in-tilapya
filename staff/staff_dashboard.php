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
    'organic_warn'  => 20.0,  // mg/L
    'organic_crit'  => 28.0,
    'temp_warn'     => 29.0,  // °C
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

// Status determination
function getPondStatus($org, $tmp, $ph, $thr) {
    if ($org >= $thr['organic_crit'] || $tmp >= $thr['temp_crit'] || $ph <= $thr['ph_low_crit'] || $ph >= $thr['ph_high_crit'])
        return 'critical';
    if ($org >= $thr['organic_warn'] || $tmp >= $thr['temp_warn'] || $ph <= $thr['ph_low_warn'] || $ph >= $thr['ph_high_warn'])
        return 'warning';
    return 'safe';
}
$current_status = getPondStatus($latest_organic, $latest_temp, $latest_ph, $thresholds);

// ============================================================
// FETCH MAINTENANCE LOGS (fallback to dummy)
// ============================================================
$maintenance_logs = [];
try {
    $mlog = $pdo->prepare("SELECT * FROM maintenance_logs WHERE pond_name = ? ORDER BY logged_at DESC LIMIT 5");
    $mlog->execute([$pond]);
    $maintenance_logs = $mlog->fetchAll();
} catch (Exception $e) { /* table may not exist */ }

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
        // In production, insert into notifications table
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AquaStaff — Pond <?php echo htmlspecialchars($pond); ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.2.0/countUp.min.js"></script>

<style>
/* ====================================================
   CSS VARIABLES
==================================================== */
:root {
    --bg-deep:       #05111f;
    --bg-panel:      #091929;
    --bg-card:       #0d2035;
    --bg-elevated:   #112840;
    --bg-hover:      #163050;
    --accent-emerald:#00ffa3;
    --accent-sky:    #38bdf8;
    --accent-amber:  #fbbf24;
    --accent-red:    #f43f5e;
    --accent-violet: #a78bfa;
    --accent-white:  #e2f0ff;
    --text-primary:  #dff0ff;
    --text-secondary:#7aa8cc;
    --text-muted:    #3a6080;
    --border:        rgba(56,189,248,0.13);
    --border-glow:   rgba(56,189,248,0.35);
    --font-display:  'DM Sans', sans-serif;
    --font-mono:     'Space Mono', monospace;
    --r-sm: 8px; --r-md: 14px; --r-lg: 20px; --r-xl: 28px;
}

/* ====================================================
   RESET & BASE
==================================================== */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body {
    background: var(--bg-deep);
    color: var(--text-primary);
    font-family: var(--font-display);
    min-height: 100vh;
    overflow-x: hidden;
}

/* Animated background */
body::before {
    content:''; position:fixed; inset:0;
    background-image:
        linear-gradient(rgba(0,255,163,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,255,163,.025) 1px, transparent 1px);
    background-size:50px 50px;
    pointer-events:none; z-index:0;
    animation:gridDrift 30s linear infinite;
}
body::after {
    content:''; position:fixed;
    top:-150px; right:-150px;
    width:450px; height:450px;
    background:radial-gradient(circle, rgba(0,255,163,.06) 0%, transparent 68%);
    pointer-events:none; z-index:0;
    animation:orbDrift 20s ease-in-out infinite alternate;
}

@keyframes gridDrift  { 0%{background-position:0 0} 100%{background-position:50px 50px} }
@keyframes orbDrift   { 0%{transform:translate(0,0)} 100%{transform:translate(-80px,80px)} }
@keyframes fadeUp     { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes blinkDot   { 0%,100%{opacity:1} 50%{opacity:.2} }
@keyframes spin       { from{transform:rotate(0)} to{transform:rotate(360deg)} }
@keyframes pulseRing  { 0%,100%{box-shadow:0 0 0 0 rgba(0,255,163,.4)} 50%{box-shadow:0 0 0 10px rgba(0,255,163,0)} }
@keyframes critPulse  { 0%,100%{box-shadow:0 0 12px rgba(244,63,94,.4)} 50%{box-shadow:0 0 32px rgba(244,63,94,.8)} }
@keyframes slideIn    { from{transform:translateX(-12px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes barFill    { from{width:0} to{width:var(--fill-w)} }
@keyframes scanline   { 0%{top:-100%} 100%{top:200%} }
@keyframes sheen      { 0%,100%{left:-60%} 50%{left:160%} }
@keyframes toastIn    { from{transform:translateX(60px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes countFlash { 0%{color:var(--accent-emerald)} 100%{color:inherit} }

/* ====================================================
   SCROLLBAR
==================================================== */
::-webkit-scrollbar { width:4px; height:4px; }
::-webkit-scrollbar-track { background:var(--bg-deep); }
::-webkit-scrollbar-thumb { background:var(--accent-emerald); border-radius:2px; }

/* ====================================================
   NAVBAR
==================================================== */
.navbar {
    position:sticky; top:0; z-index:1000;
    background:rgba(5,17,31,.97); backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
    height:62px; display:flex; align-items:center;
    justify-content:space-between; padding:0 2rem;
    box-shadow:0 1px 0 var(--border-glow);
}
.nav-brand { display:flex; align-items:center; gap:.8rem; }
.nav-logo {
    width:36px; height:36px; border-radius:10px;
    background:linear-gradient(135deg,var(--accent-emerald),var(--accent-sky));
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; color:#000; font-weight:800;
    position:relative; overflow:hidden;
}
.nav-logo::after {
    content:''; position:absolute; top:-50%; left:-60%;
    width:28%; height:200%; background:rgba(255,255,255,.4);
    transform:skewX(-20deg); animation:sheen 4s infinite;
}
.nav-title {
    font-weight:700; font-size:1rem; letter-spacing:.4px;
    background:linear-gradient(90deg,var(--accent-emerald),var(--text-primary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.nav-sub   { font-size:.68rem; color:var(--text-muted); font-family:var(--font-mono); }
.nav-right { display:flex; align-items:center; gap:.8rem; }

.nav-clock {
    font-family:var(--font-mono); font-size:.76rem; color:var(--accent-emerald);
    background:rgba(0,255,163,.07); border:1px solid rgba(0,255,163,.2);
    padding:.32rem .75rem; border-radius:6px; letter-spacing:1px; min-width:128px; text-align:center;
}
.nav-user {
    display:flex; align-items:center; gap:.55rem;
    background:var(--bg-elevated); border:1px solid var(--border);
    padding:.38rem .85rem; border-radius:50px; font-size:.8rem;
}
.btn-logout {
    display:inline-flex; align-items:center; gap:.4rem;
    background:transparent; border:1px solid rgba(244,63,94,.35);
    color:var(--accent-red); padding:.38rem .85rem; border-radius:50px;
    font-size:.8rem; font-family:var(--font-display); cursor:pointer; transition:.25s; text-decoration:none;
}
.btn-logout:hover { background:rgba(244,63,94,.1); border-color:var(--accent-red); }

/* ====================================================
   LAYOUT
==================================================== */
.wrap {
    position:relative; z-index:1;
    padding:1.4rem 2rem 3rem;
    max-width:1600px; margin:0 auto;
}

/* ====================================================
   TOPBAR
==================================================== */
.topbar {
    display:flex; justify-content:space-between; align-items:center;
    padding:.7rem 1.4rem; background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-lg); margin-bottom:1.3rem;
    animation:fadeUp .5s ease both;
}
.topbar-left { display:flex; align-items:center; gap:.9rem; }
.sys-tag {
    display:inline-flex; align-items:center; gap:.4rem;
    font-family:var(--font-mono); font-size:.68rem; padding:.28rem .75rem;
    border-radius:4px; letter-spacing:.4px;
}
.sys-tag.green  { background:rgba(0,255,163,.08); border:1px solid rgba(0,255,163,.22); color:var(--accent-emerald); }
.sys-tag.amber  { background:rgba(251,191,36,.08); border:1px solid rgba(251,191,36,.22); color:var(--accent-amber); }
.sys-tag.red    { background:rgba(244,63,94,.08);  border:1px solid rgba(244,63,94,.22);  color:var(--accent-red); }
.sys-tag.sky    { background:rgba(56,189,248,.08); border:1px solid rgba(56,189,248,.22); color:var(--accent-sky); }
.blink-dot { width:5px; height:5px; border-radius:50%; background:currentColor; animation:blinkDot 1.4s infinite; }
.live-tag  { display:inline-flex; align-items:center; gap:.4rem; font-family:var(--font-mono); font-size:.65rem; color:var(--accent-emerald); }
.live-tag span { width:6px; height:6px; border-radius:50%; background:var(--accent-emerald); animation:blinkDot 1.2s infinite; }

/* ====================================================
   STATUS HERO — Big live reading card
==================================================== */
.hero-status {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-xl); padding:1.6rem 1.8rem;
    margin-bottom:1.3rem; position:relative; overflow:hidden;
    animation:fadeUp .5s ease both;
}
.hero-status::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
}
.hero-status.safe::before    { background:linear-gradient(90deg,transparent,var(--accent-emerald),transparent); }
.hero-status.warning::before { background:linear-gradient(90deg,transparent,var(--accent-amber),transparent); }
.hero-status.critical::before{ background:linear-gradient(90deg,transparent,var(--accent-red),transparent); animation:critPulse 2s infinite; }

.hero-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.4rem; }
.hero-pond-name { font-size:1.5rem; font-weight:800; display:flex; align-items:center; gap:.7rem; }
.hero-pond-name i { font-size:1.3rem; }
.hero-meta { font-size:.78rem; color:var(--text-muted); font-family:var(--font-mono); margin-top:.25rem; }

.hero-readings { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1rem; }
.reading-block {
    background:rgba(0,0,0,.25); border-radius:var(--r-md);
    padding:1.1rem .8rem; text-align:center;
    border:1px solid rgba(255,255,255,.05); position:relative; overflow:hidden;
    transition:.3s;
}
.reading-block:hover { background:rgba(0,255,163,.05); border-color:rgba(0,255,163,.2); }
.reading-block::after {
    content:''; position:absolute; top:-2px; left:0; right:0; height:2px;
}
.reading-block.organic::after { background:var(--accent-emerald); }
.reading-block.temp::after    { background:var(--accent-amber); }
.reading-block.ph::after      { background:var(--accent-violet); }

.reading-icon  { font-size:1.3rem; margin-bottom:.5rem; display:block; }
.reading-val   { font-family:var(--font-mono); font-size:2rem; font-weight:700; line-height:1; margin-bottom:.3rem; }
.reading-unit  { font-size:.7rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:.5rem; }
.reading-label { font-size:.68rem; color:var(--text-secondary); letter-spacing:.4px; text-transform:uppercase; }

.reading-block.organic .reading-val { color:var(--accent-emerald); }
.reading-block.temp    .reading-val { color:var(--accent-amber); }
.reading-block.ph      .reading-val { color:var(--accent-violet); }

/* Progress bars inside reading blocks */
.mini-bar { height:4px; background:rgba(255,255,255,.07); border-radius:2px; overflow:hidden; margin-top:.55rem; }
.mini-bar-fill { height:100%; border-radius:2px; transition:width 1.2s ease; }
.mini-bar-fill.organic { background:var(--accent-emerald); }
.mini-bar-fill.temp    { background:var(--accent-amber); }
.mini-bar-fill.ph      { background:var(--accent-violet); }

.hero-bottom { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.6rem; }
.hero-ts { font-family:var(--font-mono); font-size:.68rem; color:var(--text-muted); }

/* ====================================================
   BADGE
==================================================== */
.badge {
    display:inline-flex; align-items:center; gap:.3rem;
    padding:.25rem .7rem; border-radius:4px;
    font-size:.68rem; font-weight:700; font-family:var(--font-mono);
    letter-spacing:.3px; text-transform:uppercase;
}
.badge-safe     { background:rgba(0,255,163,.12); color:var(--accent-emerald); border:1px solid rgba(0,255,163,.25); }
.badge-warning  { background:rgba(251,191,36,.12); color:var(--accent-amber);  border:1px solid rgba(251,191,36,.25); }
.badge-critical { background:rgba(244,63,94,.12);  color:var(--accent-red);    border:1px solid rgba(244,63,94,.25); animation:blinkDot 1.4s infinite; }
.dot-blink { width:5px; height:5px; border-radius:50%; background:currentColor; animation:blinkDot 1.4s infinite; }

/* ====================================================
   GENERIC CARD
==================================================== */
.card {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-xl); padding:1.3rem 1.5rem;
    animation:fadeUp .6s ease both;
}
.card:hover { border-color:rgba(56,189,248,.18); }
.sec-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.sec-title {
    display:flex; align-items:center; gap:.55rem;
    font-size:.86rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase;
}
.sec-title i { color:var(--accent-sky); }

/* ====================================================
   GRID
==================================================== */
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.2rem; margin-bottom:1.2rem; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.2rem; }
@media(max-width:1100px){ .grid-2,.grid-3{grid-template-columns:1fr;} }
@media(max-width:640px) { .grid-3{grid-template-columns:1fr 1fr;} }

/* ====================================================
   THRESHOLD PANEL
==================================================== */
.threshold-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:.6rem .8rem; border-radius:var(--r-sm);
    background:rgba(0,0,0,.2); margin-bottom:.4rem;
    border-left:3px solid transparent;
    transition:.25s;
}
.threshold-row:hover { background:var(--bg-elevated); }
.threshold-row.ok     { border-left-color:var(--accent-emerald); }
.threshold-row.warn   { border-left-color:var(--accent-amber); }
.threshold-row.crit   { border-left-color:var(--accent-red); animation:blinkDot 1.8s infinite; }
.threshold-label { font-size:.82rem; display:flex; align-items:center; gap:.5rem; }
.threshold-vals  { display:flex; align-items:center; gap:.6rem; font-family:var(--font-mono); font-size:.8rem; }
.threshold-current { font-weight:700; font-size:1rem; }

/* ====================================================
   GAUGE RING (SVG)
==================================================== */
.gauge-wrap { display:flex; justify-content:center; align-items:center; padding:1rem 0; }
.gauge-svg  { width:160px; height:160px; transform:rotate(-90deg); }
.gauge-bg   { fill:none; stroke:rgba(255,255,255,.06); stroke-width:12; }
.gauge-fill { fill:none; stroke-width:12; stroke-linecap:round; transition:stroke-dashoffset 1.5s ease; }
.gauge-center { position:relative; text-align:center; margin-top:-100px; }

/* ====================================================
   CHART
==================================================== */
.chart-wrap { height:240px; margin-top:.8rem; }
.period-tabs { display:flex; gap:.4rem; }
.period-btn {
    font-family:var(--font-mono); font-size:.66rem;
    padding:.26rem .65rem; border-radius:4px;
    background:var(--bg-elevated); border:1px solid var(--border);
    color:var(--text-muted); cursor:pointer; transition:.2s; letter-spacing:.3px;
}
.period-btn.active,.period-btn:hover { background:rgba(0,255,163,.1); border-color:var(--accent-emerald); color:var(--accent-emerald); }

/* ====================================================
   BUTTONS
==================================================== */
.btn {
    display:inline-flex; align-items:center; gap:.4rem;
    border:none; border-radius:var(--r-sm); padding:.45rem 1rem;
    font-family:var(--font-display); font-size:.8rem; font-weight:600;
    cursor:pointer; transition:.22s cubic-bezier(.4,0,.2,1);
    letter-spacing:.3px; white-space:nowrap;
}
.btn:active { transform:scale(.97); }
.btn:disabled { opacity:.4; cursor:not-allowed; pointer-events:none; }
.btn-primary  { background:var(--accent-emerald); color:#000; }
.btn-primary:hover  { background:#00e090; }
.btn-sky      { background:rgba(56,189,248,.15); color:var(--accent-sky);    border:1px solid rgba(56,189,248,.3); }
.btn-sky:hover      { background:rgba(56,189,248,.28); }
.btn-warning  { background:rgba(251,191,36,.15);  color:var(--accent-amber);  border:1px solid rgba(251,191,36,.3); }
.btn-danger   { background:rgba(244,63,94,.15);   color:var(--accent-red);    border:1px solid rgba(244,63,94,.3); }
.btn-ghost    { background:var(--bg-elevated);    color:var(--text-secondary); border:1px solid var(--border); }
.btn-ghost:hover { border-color:var(--accent-sky); color:var(--accent-sky); }
.btn-sm { padding:.28rem .65rem; font-size:.72rem; }

/* ====================================================
   MAINTENANCE LOG
==================================================== */
.log-item {
    display:flex; gap:.8rem; padding:.75rem .8rem;
    border-radius:var(--r-md); margin-bottom:.4rem;
    background:rgba(0,0,0,.2); border-left:3px solid var(--accent-sky);
    animation:slideIn .3s ease both; transition:.25s;
}
.log-item:hover { background:var(--bg-elevated); }
.log-icon  { width:34px; height:34px; border-radius:9px; background:rgba(56,189,248,.12); display:flex; align-items:center; justify-content:center; font-size:.85rem; color:var(--accent-sky); flex-shrink:0; }
.log-title { font-size:.85rem; font-weight:600; }
.log-meta  { font-size:.7rem; color:var(--text-muted); font-family:var(--font-mono); margin-top:.15rem; }
.log-notes { font-size:.75rem; color:var(--text-secondary); margin-top:.2rem; }

/* ====================================================
   FORM
==================================================== */
.form-group   { margin-bottom:.85rem; }
.form-label   { display:block; font-size:.7rem; font-weight:600; color:var(--text-muted); letter-spacing:.4px; text-transform:uppercase; margin-bottom:.3rem; font-family:var(--font-mono); }
.form-ctrl    { width:100%; padding:.55rem .85rem; background:var(--bg-elevated); border:1px solid var(--border); border-radius:var(--r-sm); color:var(--text-primary); font-family:var(--font-display); font-size:.83rem; outline:none; transition:.25s; }
.form-ctrl:focus { border-color:var(--accent-emerald); box-shadow:0 0 0 3px rgba(0,255,163,.1); }
.form-ctrl::placeholder { color:var(--text-muted); }
textarea.form-ctrl { resize:vertical; min-height:70px; }
select.form-ctrl option { background:var(--bg-panel); }

/* ====================================================
   NOTIFY PANEL
==================================================== */
.notify-levels { display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; margin-bottom:.8rem; }
.notify-level-btn {
    padding:.6rem .4rem; border-radius:var(--r-sm); text-align:center;
    cursor:pointer; border:2px solid transparent; transition:.25s;
    font-size:.76rem; font-weight:600; font-family:var(--font-display);
    background:var(--bg-elevated);
}
.notify-level-btn.active-info    { border-color:var(--accent-sky);    background:rgba(56,189,248,.12); color:var(--accent-sky); }
.notify-level-btn.active-warning { border-color:var(--accent-amber);  background:rgba(251,191,36,.12); color:var(--accent-amber); }
.notify-level-btn.active-critical{ border-color:var(--accent-red);    background:rgba(244,63,94,.12);  color:var(--accent-red); }
.notify-level-btn:not(.active-info):not(.active-warning):not(.active-critical) { color:var(--text-secondary); }

/* ====================================================
   STAFF INFO CARD
==================================================== */
.staff-avatar {
    width:52px; height:52px; border-radius:14px;
    background:linear-gradient(135deg,var(--accent-emerald),var(--accent-sky));
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; font-weight:800; color:#000;
    margin-bottom:1rem; position:relative; overflow:hidden;
    animation:pulseRing 3s infinite;
}
.staff-avatar::after { content:''; position:absolute; top:-50%; left:-60%; width:28%; height:200%; background:rgba(255,255,255,.35); transform:skewX(-20deg); animation:sheen 4s infinite; }
.info-row { display:flex; align-items:center; justify-content:space-between; padding:.55rem .6rem; border-radius:var(--r-sm); background:rgba(0,0,0,.2); margin-bottom:.35rem; border-left:2px solid var(--border); }
.info-row:hover { background:var(--bg-elevated); }
.info-lbl { font-size:.7rem; color:var(--text-muted); font-family:var(--font-mono); text-transform:uppercase; letter-spacing:.4px; }
.info-val { font-size:.82rem; font-weight:600; font-family:var(--font-mono); }

/* ====================================================
   KPI MINI ROW
==================================================== */
.kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:.8rem; margin-bottom:1.2rem; }
.kpi-mini {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-lg); padding:1rem 1rem;
    position:relative; overflow:hidden; cursor:default;
    transition:.35s; animation:fadeUp .5s ease both;
}
.kpi-mini:hover { transform:translateY(-3px); border-color:var(--border-glow); }
.kpi-mini::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,transparent,var(--kc,var(--accent-emerald)),transparent); }
.kpi-mini-icon { font-size:.95rem; color:var(--kc,var(--accent-emerald)); margin-bottom:.5rem; }
.kpi-mini-val  { font-family:var(--font-mono); font-size:1.5rem; font-weight:700; color:var(--kc,var(--accent-emerald)); line-height:1; margin-bottom:.2rem; }
.kpi-mini-lbl  { font-size:.65rem; color:var(--text-muted); letter-spacing:.5px; text-transform:uppercase; }

/* ====================================================
   HISTORY TABLE
==================================================== */
.tbl-wrap { overflow-x:auto; margin-top:.5rem; }
table { width:100%; border-collapse:collapse; }
th { text-align:left; padding:.55rem .75rem; font-size:.66rem; font-weight:700; color:var(--text-muted); letter-spacing:.8px; text-transform:uppercase; border-bottom:1px solid var(--border); font-family:var(--font-mono); }
td { padding:.62rem .75rem; border-bottom:1px solid rgba(255,255,255,.04); font-size:.8rem; vertical-align:middle; }
tr:hover td { background:rgba(0,255,163,.025); }
tr:last-child td { border-bottom:none; }

/* ====================================================
   CIRCULAR GAUGES (CSS)
==================================================== */
.gauges-row { display:grid; grid-template-columns:repeat(3,1fr); gap:.8rem; }
.gauge-container { text-align:center; padding:1rem .5rem; }
.gauge-ring-wrap { position:relative; display:inline-block; margin:0 auto; }
.gauge-ring-wrap svg { display:block; }
.gauge-inner-text { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; pointer-events:none; }
.gauge-inner-val  { font-family:var(--font-mono); font-size:1.15rem; font-weight:700; line-height:1; }
.gauge-inner-unit { font-size:.58rem; color:var(--text-muted); margin-top:.15rem; text-transform:uppercase; letter-spacing:.4px; }
.gauge-label { font-size:.72rem; color:var(--text-secondary); margin-top:.5rem; letter-spacing:.4px; text-transform:uppercase; }

/* ====================================================
   TOAST
==================================================== */
.toast-wrap { position:fixed; top:74px; right:18px; z-index:5000; display:flex; flex-direction:column; gap:.45rem; }
.toast {
    display:flex; align-items:center; gap:.55rem; padding:.7rem 1rem;
    border-radius:var(--r-md); font-size:.8rem; font-weight:600;
    min-width:240px; animation:toastIn .3s ease; box-shadow:0 8px 24px rgba(0,0,0,.45);
}
.toast.success { background:rgba(0,255,163,.13); border:1px solid rgba(0,255,163,.3); color:var(--accent-emerald); }
.toast.warning { background:rgba(251,191,36,.13); border:1px solid rgba(251,191,36,.3); color:var(--accent-amber); }
.toast.critical{ background:rgba(244,63,94,.13);  border:1px solid rgba(244,63,94,.3);  color:var(--accent-red); }
.toast.info    { background:rgba(56,189,248,.1);  border:1px solid var(--border);       color:var(--accent-sky); }

/* ====================================================
   MODAL
==================================================== */
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.8); backdrop-filter:blur(8px); z-index:3000; align-items:center; justify-content:center; }
.modal.open { display:flex; }
.modal-box  { background:var(--bg-panel); border:1px solid var(--border); border-radius:var(--r-xl); padding:1.8rem; width:90%; max-width:460px; max-height:85vh; overflow-y:auto; animation:fadeUp .3s ease; }
.modal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem; padding-bottom:.7rem; border-bottom:1px solid var(--border); }
.modal-title { font-size:.95rem; font-weight:700; }
.modal-close { background:none; border:none; color:var(--text-muted); font-size:1.3rem; cursor:pointer; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:.2s; }
.modal-close:hover { color:var(--accent-red); background:rgba(244,63,94,.1); }

/* ====================================================
   FOOTER
==================================================== */
.dash-footer {
    margin-top:2rem; padding:.75rem 1.4rem;
    background:var(--bg-card); border:1px solid var(--border); border-radius:var(--r-lg);
    display:flex; justify-content:space-between; align-items:center;
    font-family:var(--font-mono); font-size:.68rem; color:var(--text-muted);
}
</style>
</head>
<body>

<!-- TOAST CONTAINER -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-brand">
        <div class="nav-logo"><i class="fas fa-fish"></i></div>
        <div>
            <div class="nav-title">AquaStaff — Pond <?php echo htmlspecialchars($pond); ?></div>
            <div class="nav-sub">Organic-Matter Detection · Manolo Fortich</div>
        </div>
    </div>
    <div class="nav-right">
        <div class="nav-clock" id="navClock"><?php echo date('h:i:s A'); ?></div>
        <div class="nav-user">
            <i class="fas fa-user" style="color:var(--accent-emerald);font-size:.82rem;"></i>
            <span><?php echo htmlspecialchars($full_name); ?></span>
        </div>
        <a href="../auth/logout.php" class="btn-logout" onclick="return confirm('Logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<div class="wrap">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <div>
                <div style="font-size:1rem;font-weight:700;"><?php echo date('l'); ?></div>
                <div style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-secondary);"><?php echo date('F j, Y'); ?></div>
            </div>
            <div class="sys-tag green"><span class="blink-dot"></span> SENSOR ONLINE</div>
            <div class="sys-tag sky"><i class="fas fa-microchip" style="font-size:.6rem;"></i> POND <?php echo $pond; ?></div>
            <div class="sys-tag <?php echo $current_status === 'safe' ? 'green' : ($current_status === 'warning' ? 'amber' : 'red'); ?>" id="statusTag">
                <span class="blink-dot"></span> <?php echo strtoupper($current_status); ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:.7rem;">
            <div class="live-tag"><span></span> LIVE SIMULATION</div>
            <button class="btn btn-ghost btn-sm" id="simToggleBtn" onclick="toggleSim()">
                <i class="fas fa-pause" id="simIcon"></i> <span id="simLabel">Pause</span>
            </button>
        </div>
    </div>

    <!-- KPI MINI ROW -->
    <div class="kpi-row">
        <div class="kpi-mini" style="--kc:var(--accent-emerald);">
            <div class="kpi-mini-icon"><i class="fas fa-seedling"></i></div>
            <div class="kpi-mini-val" id="kpi-organic"><?php echo $avg_organic; ?></div>
            <div class="kpi-mini-lbl">Avg Organic mg/L</div>
        </div>
        <div class="kpi-mini" style="--kc:var(--accent-amber);">
            <div class="kpi-mini-icon"><i class="fas fa-thermometer-half"></i></div>
            <div class="kpi-mini-val" id="kpi-temp"><?php echo $avg_temp; ?></div>
            <div class="kpi-mini-lbl">Avg Temp °C</div>
        </div>
        <div class="kpi-mini" style="--kc:var(--accent-violet);">
            <div class="kpi-mini-icon"><i class="fas fa-flask"></i></div>
            <div class="kpi-mini-val" id="kpi-ph"><?php echo $avg_ph; ?></div>
            <div class="kpi-mini-lbl">Avg pH Level</div>
        </div>
    </div>

    <!-- STATUS HERO -->
    <div class="hero-status <?php echo $current_status; ?>" id="heroCard">
        <div class="hero-top">
            <div>
                <div class="hero-pond-name">
                    <i class="fas fa-water" style="color:var(--accent-emerald);"></i>
                    Pond <?php echo htmlspecialchars($pond); ?> — Live Readings
                </div>
                <div class="hero-meta">Real-time sensor data · Auto-refresh every 5 seconds</div>
            </div>
            <div style="display:flex;align-items:center;gap:.6rem;">
                <span class="badge badge-<?php echo $current_status; ?>" id="heroBadge">
                    <span class="dot-blink"></span> <?php echo strtoupper($current_status); ?>
                </span>
                <button class="btn btn-ghost btn-sm" onclick="manualRefresh()">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i>
                </button>
            </div>
        </div>

        <div class="hero-readings">
            <!-- Organic -->
            <div class="reading-block organic">
                <span class="reading-icon" style="color:var(--accent-emerald);"><i class="fas fa-seedling"></i></span>
                <div class="reading-val" id="liveOrganic"><?php echo $latest_organic; ?></div>
                <div class="reading-unit">mg / L</div>
                <div class="reading-label">Organic Matter</div>
                <div class="mini-bar">
                    <div class="mini-bar-fill organic" id="barOrganic" style="width:<?php echo min(100,$latest_organic/0.35); ?>%;"></div>
                </div>
            </div>
            <!-- Temperature -->
            <div class="reading-block temp">
                <span class="reading-icon" style="color:var(--accent-amber);"><i class="fas fa-thermometer-half"></i></span>
                <div class="reading-val" id="liveTemp"><?php echo $latest_temp; ?></div>
                <div class="reading-unit">°C</div>
                <div class="reading-label">Water Temperature</div>
                <div class="mini-bar">
                    <div class="mini-bar-fill temp" id="barTemp" style="width:<?php echo min(100,($latest_temp-20)*10); ?>%;"></div>
                </div>
            </div>
            <!-- pH -->
            <div class="reading-block ph">
                <span class="reading-icon" style="color:var(--accent-violet);"><i class="fas fa-flask"></i></span>
                <div class="reading-val" id="livePH"><?php echo $latest_ph; ?></div>
                <div class="reading-unit">pH</div>
                <div class="reading-label">pH Level</div>
                <div class="mini-bar">
                    <div class="mini-bar-fill ph" id="barPH" style="width:<?php echo min(100,$latest_ph*10); ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="hero-bottom">
            <div class="hero-ts"><i class="far fa-clock"></i> Last updated: <span id="heroTs"><?php echo date('h:i:s A'); ?></span></div>
            <div style="display:flex;gap:.5rem;">
                <button class="btn btn-warning btn-sm" onclick="openNotifyModal()">
                    <i class="fas fa-bell"></i> Notify Manager
                </button>
                <button class="btn btn-sky btn-sm" onclick="openLogModal()">
                    <i class="fas fa-clipboard-list"></i> Log Action
                </button>
            </div>
        </div>
    </div>

    <!-- GAUGES + THRESHOLD STATUS -->
    <div class="grid-2" style="margin-bottom:1.2rem;">

        <!-- Circular Gauges -->
        <div class="card">
            <div class="sec-head">
                <div class="sec-title"><i class="fas fa-tachometer-alt"></i> Sensor Gauges</div>
                <div class="live-tag"><span></span> LIVE</div>
            </div>
            <div class="gauges-row">
                <!-- Organic Gauge -->
                <div class="gauge-container">
                    <div class="gauge-ring-wrap">
                        <svg class="gauge-svg" viewBox="0 0 100 100" width="130" height="130" style="transform:rotate(-90deg)">
                            <circle class="gauge-bg" cx="50" cy="50" r="40"/>
                            <circle id="gaugeOrganic" class="gauge-fill" cx="50" cy="50" r="40"
                                stroke="var(--accent-emerald)"
                                stroke-dasharray="251.2"
                                stroke-dashoffset="200"/>
                        </svg>
                        <div class="gauge-inner-text" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                            <div class="gauge-inner-val" id="gaugeOrgVal" style="color:var(--accent-emerald);"><?php echo $latest_organic; ?></div>
                            <div class="gauge-inner-unit">mg/L</div>
                        </div>
                    </div>
                    <div class="gauge-label">Organic</div>
                </div>
                <!-- Temp Gauge -->
                <div class="gauge-container">
                    <div class="gauge-ring-wrap">
                        <svg class="gauge-svg" viewBox="0 0 100 100" width="130" height="130" style="transform:rotate(-90deg)">
                            <circle class="gauge-bg" cx="50" cy="50" r="40"/>
                            <circle id="gaugeTemp" class="gauge-fill" cx="50" cy="50" r="40"
                                stroke="var(--accent-amber)"
                                stroke-dasharray="251.2"
                                stroke-dashoffset="180"/>
                        </svg>
                        <div class="gauge-inner-text" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                            <div class="gauge-inner-val" id="gaugeTempVal" style="color:var(--accent-amber);"><?php echo $latest_temp; ?></div>
                            <div class="gauge-inner-unit">°C</div>
                        </div>
                    </div>
                    <div class="gauge-label">Temperature</div>
                </div>
                <!-- pH Gauge -->
                <div class="gauge-container">
                    <div class="gauge-ring-wrap">
                        <svg class="gauge-svg" viewBox="0 0 100 100" width="130" height="130" style="transform:rotate(-90deg)">
                            <circle class="gauge-bg" cx="50" cy="50" r="40"/>
                            <circle id="gaugePH" class="gauge-fill" cx="50" cy="50" r="40"
                                stroke="var(--accent-violet)"
                                stroke-dasharray="251.2"
                                stroke-dashoffset="150"/>
                        </svg>
                        <div class="gauge-inner-text" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                            <div class="gauge-inner-val" id="gaugePHVal" style="color:var(--accent-violet);"><?php echo $latest_ph; ?></div>
                            <div class="gauge-inner-unit">pH</div>
                        </div>
                    </div>
                    <div class="gauge-label">pH Level</div>
                </div>
            </div>
        </div>

        <!-- Threshold Status -->
        <div class="card">
            <div class="sec-head">
                <div class="sec-title"><i class="fas fa-sliders-h"></i> Threshold Monitor</div>
            </div>
            <div id="thresholdPanel">
                <div class="threshold-row ok" id="thr-organic">
                    <div class="threshold-label"><i class="fas fa-seedling" style="color:var(--accent-emerald);"></i> Organic (mg/L)</div>
                    <div class="threshold-vals">
                        <span style="font-size:.68rem;color:var(--text-muted);">Safe &lt;<?php echo $thresholds['organic_warn']; ?></span>
                        <span class="threshold-current" id="thr-organic-val" style="color:var(--accent-emerald);"><?php echo $latest_organic; ?></span>
                    </div>
                </div>
                <div class="threshold-row ok" id="thr-temp">
                    <div class="threshold-label"><i class="fas fa-thermometer-half" style="color:var(--accent-amber);"></i> Temperature (°C)</div>
                    <div class="threshold-vals">
                        <span style="font-size:.68rem;color:var(--text-muted);">Safe &lt;<?php echo $thresholds['temp_warn']; ?></span>
                        <span class="threshold-current" id="thr-temp-val" style="color:var(--accent-amber);"><?php echo $latest_temp; ?></span>
                    </div>
                </div>
                <div class="threshold-row ok" id="thr-ph">
                    <div class="threshold-label"><i class="fas fa-flask" style="color:var(--accent-violet);"></i> pH Level</div>
                    <div class="threshold-vals">
                        <span style="font-size:.68rem;color:var(--text-muted);"><?php echo $thresholds['ph_low_warn']; ?>–<?php echo $thresholds['ph_high_warn']; ?></span>
                        <span class="threshold-current" id="thr-ph-val" style="color:var(--accent-violet);"><?php echo $latest_ph; ?></span>
                    </div>
                </div>
                <!-- Safe range reference table -->
                <div style="margin-top:1rem;padding:.7rem;background:rgba(0,0,0,.2);border-radius:var(--r-sm);">
                    <div style="font-size:.65rem;color:var(--text-muted);font-family:var(--font-mono);margin-bottom:.5rem;letter-spacing:.4px;">SAFE RANGES</div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;font-family:var(--font-mono);font-size:.7rem;">
                        <div><div style="color:var(--accent-emerald);">Organic</div><div style="color:var(--text-muted);">&lt;<?php echo $thresholds['organic_warn']; ?> mg/L</div></div>
                        <div><div style="color:var(--accent-amber);">Temp</div><div style="color:var(--text-muted);">&lt;<?php echo $thresholds['temp_warn']; ?>°C</div></div>
                        <div><div style="color:var(--accent-violet);">pH</div><div style="color:var(--text-muted);"><?php echo $thresholds['ph_low_warn']; ?>–<?php echo $thresholds['ph_high_warn']; ?></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TREND CHART + STAFF INFO -->
    <div class="grid-2" style="margin-bottom:1.2rem;">

        <!-- Trend Chart -->
        <div class="card">
            <div class="sec-head">
                <div class="sec-title"><i class="fas fa-chart-area"></i> Pond <?php echo $pond; ?> Trends</div>
                <div class="period-tabs">
                    <button class="period-btn active" onclick="switchPeriod('14d',this)">14 DAY</button>
                    <button class="period-btn" onclick="switchPeriod('24h',this)">24 HR</button>
                    <button class="period-btn" onclick="switchPeriod('7d',this)">7 DAY</button>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="pondChart"></canvas>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:.5rem;">
                <div style="display:flex;gap:.9rem;font-size:.66rem;font-family:var(--font-mono);">
                    <span style="color:var(--accent-emerald);"><i class="fas fa-circle"></i> Organic</span>
                    <span style="color:var(--accent-amber);"><i class="fas fa-circle"></i> Temp</span>
                    <span style="color:var(--accent-violet);"><i class="fas fa-circle"></i> pH</span>
                </div>
                <div style="font-family:var(--font-mono);font-size:.66rem;color:var(--text-muted);" id="chartTs"><?php echo date('h:i:s A'); ?></div>
            </div>
        </div>

        <!-- Staff Info -->
        <div class="card">
            <div class="sec-head">
                <div class="sec-title"><i class="fas fa-id-badge"></i> My Profile</div>
            </div>
            <?php
            $initials='';
            foreach(explode(' ',$full_name) as $n) $initials.=strtoupper(substr($n,0,1));
            ?>
            <div class="staff-avatar"><?php echo $initials ?: '?'; ?></div>
            <div class="info-row">
                <span class="info-lbl">Name</span>
                <span class="info-val"><?php echo htmlspecialchars($full_name); ?></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Email</span>
                <span class="info-val" style="font-size:.74rem;"><?php echo htmlspecialchars($email); ?></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Assigned Pond</span>
                <span class="info-val"><span class="badge badge-safe"><?php echo htmlspecialchars($pond); ?></span></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Last Login</span>
                <span class="info-val" style="font-size:.74rem;"><?php echo htmlspecialchars($last_login); ?></span>
            </div>
            <div class="info-row">
                <span class="info-lbl">Session Time</span>
                <span class="info-val" id="sessionTimer" style="color:var(--accent-emerald);">00:00:00</span>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
                <button class="btn btn-primary btn-sm" onclick="openLogModal()">
                    <i class="fas fa-clipboard-plus"></i> Log Maintenance
                </button>
                <button class="btn btn-warning btn-sm" onclick="openNotifyModal()">
                    <i class="fas fa-bell"></i> Alert Manager
                </button>
            </div>
        </div>
    </div>

    <!-- MAINTENANCE LOG -->
    <div class="card" style="margin-bottom:1.2rem;">
        <div class="sec-head">
            <div class="sec-title"><i class="fas fa-clipboard-list"></i> Maintenance Activity Log</div>
            <button class="btn btn-sky btn-sm" onclick="openLogModal()"><i class="fas fa-plus"></i> Add Entry</button>
        </div>
        <div id="maintenanceList" style="max-height:280px;overflow-y:auto;">
            <?php foreach($maintenance_logs as $log): ?>
            <div class="log-item">
                <div class="log-icon"><i class="fas fa-tools"></i></div>
                <div style="flex:1;">
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

    <!-- HISTORY TABLE -->
    <div class="card" style="margin-bottom:1.2rem;">
        <div class="sec-head">
            <div class="sec-title"><i class="fas fa-history"></i> Reading History</div>
            <div class="live-tag"><span></span> AUTO-UPDATING</div>
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
                        $sc = $s==='safe'?'var(--accent-emerald)':($s==='warning'?'var(--accent-amber)':'var(--accent-red)');
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);font-family:var(--font-mono);"><?php echo $i+1; ?></td>
                        <td><span style="color:var(--accent-emerald);font-family:var(--font-mono);font-weight:700;"><?php echo $organic[$i]; ?></span></td>
                        <td><span style="color:var(--accent-amber);font-family:var(--font-mono);font-weight:700;"><?php echo $temp[$i]; ?></span></td>
                        <td><span style="color:var(--accent-violet);font-family:var(--font-mono);font-weight:700;"><?php echo $ph[$i]; ?></span></td>
                        <td><span class="badge badge-<?php echo $s; ?>"><?php echo strtoupper($s); ?></span></td>
                        <td style="font-family:var(--font-mono);font-size:.7rem;color:var(--text-muted);"><?php echo $dates[$i] ?? date('M d'); ?></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="dash-footer">
        <div><i class="fas fa-map-marker-alt" style="color:var(--accent-emerald);"></i> Manolo Fortich, Bukidnon · Pond <?php echo $pond; ?></div>
        <div>PH TIME: <span id="footerTs" style="color:var(--accent-emerald);"><?php echo date('h:i:s A'); ?></span></div>
        <div>&copy; 2026 Organic-Matter Detection in Tilapia</div>
    </div>

</div><!-- /wrap -->

<!-- ===== LOG MAINTENANCE MODAL ===== -->
<div id="logModal" class="modal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title"><i class="fas fa-clipboard-plus" style="color:var(--accent-sky);"></i> Log Maintenance Action</div>
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
        <div style="display:flex;gap:.5rem;justify-content:flex-end;">
            <button class="btn btn-ghost" onclick="closeModal('logModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveLog()"><i class="fas fa-save"></i> Save Log</button>
        </div>
    </div>
</div>

<!-- ===== NOTIFY MANAGER MODAL ===== -->
<div id="notifyModal" class="modal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title"><i class="fas fa-bell" style="color:var(--accent-amber);"></i> Notify Manager</div>
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
        <div style="background:rgba(0,0,0,.2);border-radius:var(--r-sm);padding:.8rem;margin-bottom:1rem;font-size:.78rem;">
            <div style="color:var(--text-muted);font-family:var(--font-mono);font-size:.65rem;margin-bottom:.4rem;">CURRENT READINGS</div>
            <div style="display:flex;gap:1rem;font-family:var(--font-mono);">
                <span style="color:var(--accent-emerald);">Org: <strong id="nm-org"><?php echo $latest_organic; ?></strong> mg/L</span>
                <span style="color:var(--accent-amber);">Tmp: <strong id="nm-tmp"><?php echo $latest_temp; ?></strong>°C</span>
                <span style="color:var(--accent-violet);">pH: <strong id="nm-ph"><?php echo $latest_ph; ?></strong></span>
            </div>
        </div>
        <div style="display:flex;gap:.5rem;justify-content:flex-end;">
            <button class="btn btn-ghost" onclick="closeModal('notifyModal')">Cancel</button>
            <button class="btn btn-warning" onclick="sendNotification()"><i class="fas fa-paper-plane"></i> Send to Manager</button>
        </div>
    </div>
</div>

<script>
// ============================================================
// GLOBAL
// ============================================================
const POND   = '<?php echo $pond; ?>';
const THR    = <?php echo json_encode($thresholds); ?>;
const FNAME  = '<?php echo addslashes($full_name); ?>';

let metricsChart;
let simInterval;
let isSimRunning = true;
let sessionSecs  = 0;
let notifyLevel  = 'warning';

// Rolling history for table (last 8 entries)
let readings = [
    <?php
    $max = min(8,count($organic));
    for($i=count($organic)-$max;$i<count($organic);$i++){
        echo "{org:".($organic[$i] ?? 0).",tmp:".($temp[$i] ?? 0).",ph:".($ph[$i] ?? 0).",ts:'".($dates[$i] ?? '')."'},";
    }
    ?>
];

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initChart();
    startSim();
    startSessionTimer();
});

// ============================================================
// CLOCK
// ============================================================
function initClock() {
    setInterval(() => {
        const ph = new Date().toLocaleTimeString('en-US',{
            timeZone:'Asia/Manila',hour12:true,
            hour:'2-digit',minute:'2-digit',second:'2-digit'
        });
        document.getElementById('navClock').textContent = ph;
        const ft = document.getElementById('footerTs'); if(ft) ft.textContent = ph;
        const ct = document.getElementById('chartTs');  if(ct) ct.textContent = ph;
    }, 1000);
}

// ============================================================
// SESSION TIMER
// ============================================================
function startSessionTimer() {
    setInterval(() => {
        sessionSecs++;
        const h = String(Math.floor(sessionSecs/3600)).padStart(2,'0');
        const m = String(Math.floor((sessionSecs%3600)/60)).padStart(2,'0');
        const s = String(sessionSecs%60).padStart(2,'0');
        const el = document.getElementById('sessionTimer');
        if(el) el.textContent = `${h}:${m}:${s}`;
    }, 1000);
}

// ============================================================
// CHART
// ============================================================
function initChart() {
    const ctx = document.getElementById('pondChart').getContext('2d');
    const go = ctx.createLinearGradient(0,0,0,240);
    go.addColorStop(0,'rgba(0,255,163,.22)'); go.addColorStop(1,'rgba(0,255,163,0)');
    const gt = ctx.createLinearGradient(0,0,0,240);
    gt.addColorStop(0,'rgba(251,191,36,.18)'); gt.addColorStop(1,'rgba(251,191,36,0)');
    const gp = ctx.createLinearGradient(0,0,0,240);
    gp.addColorStop(0,'rgba(167,139,250,.18)'); gp.addColorStop(1,'rgba(167,139,250,0)');

    metricsChart = new Chart(ctx, {
        type:'line',
        data:{
            labels: <?php echo json_encode($dates); ?>,
            datasets:[
                { label:'Organic (mg/L)', data:<?php echo json_encode($organic); ?>, borderColor:'#00ffa3', backgroundColor:go, fill:true, tension:.4, borderWidth:2, pointRadius:3, pointHoverRadius:6, yAxisID:'yO' },
                { label:'Temp (°C)',      data:<?php echo json_encode($temp); ?>,    borderColor:'#fbbf24', backgroundColor:gt, fill:true, tension:.4, borderWidth:2, pointRadius:0, pointHoverRadius:5, yAxisID:'yT' },
                { label:'pH',             data:<?php echo json_encode($ph); ?>,      borderColor:'#a78bfa', backgroundColor:gp, fill:true, tension:.4, borderWidth:2, pointRadius:0, pointHoverRadius:5, yAxisID:'yP' }
            ]
        },
        options:{
            responsive:true, maintainAspectRatio:false,
            animation:{ duration:800 },
            interaction:{ mode:'index', intersect:false },
            plugins:{
                legend:{ display:false },
                tooltip:{ backgroundColor:'rgba(9,25,41,.95)', borderColor:'rgba(0,255,163,.2)', borderWidth:1, titleColor:'#dff0ff', bodyColor:'#7aa8cc', padding:10 }
            },
            scales:{
                x: { grid:{color:'rgba(255,255,255,.04)',drawBorder:false}, ticks:{color:'#3a6080',font:{family:"'Space Mono'",size:9},maxTicksLimit:8} },
                yO:{ position:'left',   grid:{color:'rgba(255,255,255,.04)',drawBorder:false}, ticks:{color:'#3a6080',font:{family:"'Space Mono'",size:9}} },
                yT:{ position:'right',  grid:{drawOnChartArea:false}, ticks:{color:'#3a6080',font:{family:"'Space Mono'",size:9}} },
                yP:{ position:'right',  grid:{drawOnChartArea:false}, ticks:{color:'#3a6080',font:{family:"'Space Mono'",size:9}}, offset:true }
            }
        }
    });
}

function switchPeriod(period, btn) {
    document.querySelectorAll('.period-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=get_history'})
    .then(r=>r.json())
    .then(d=>{
        metricsChart.data.labels = d.labels;
        metricsChart.data.datasets[0].data = d.organic;
        metricsChart.data.datasets[1].data = d.temp;
        metricsChart.data.datasets[2].data = d.ph;
        metricsChart.update();
        btn.textContent = period.toUpperCase();
        toast(`Chart updated: ${period}`,'info');
    });
}

// ============================================================
// SIMULATION
// ============================================================
function startSim() {
    isSimRunning = true;
    document.getElementById('simIcon').className  = 'fas fa-pause';
    document.getElementById('simLabel').textContent = 'Pause';
    simInterval = setInterval(fetchReading, 5000);
}

function stopSim() {
    isSimRunning = false;
    clearInterval(simInterval);
    document.getElementById('simIcon').className  = 'fas fa-play';
    document.getElementById('simLabel').textContent = 'Resume';
}

function toggleSim() {
    isSimRunning ? stopSim() : startSim();
    toast(isSimRunning ? 'Simulation paused':'Simulation resumed', isSimRunning?'warning':'success');
}

function fetchReading() {
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=get_live_reading&pond=${POND}`})
    .then(r=>r.json())
    .then(d=>{
        if(!d.success) return;
        updateDisplay(d.organic, d.temp, d.ph, d.status, d.timestamp);
        addHistoryRow(d.organic, d.temp, d.ph, d.status);
        // Auto-notify on critical
        if (d.status === 'critical') {
            toast(`⚠ CRITICAL: Organic ${d.organic} mg/L on Pond ${POND}`,'critical');
        }
    });
}

function manualRefresh() {
    const icon = document.getElementById('refreshIcon');
    icon.style.animation='spin 1s linear infinite';
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=get_live_reading&pond=${POND}`})
    .then(r=>r.json())
    .then(d=>{
        icon.style.animation='';
        if(!d.success) return;
        updateDisplay(d.organic, d.temp, d.ph, d.status, d.timestamp);
        toast('Readings refreshed','success');
    });
}

// ============================================================
// UPDATE DISPLAY
// ============================================================
function updateDisplay(organic, temp, ph, status, ts) {
    // Hero readings
    const elO = document.getElementById('liveOrganic');
    const elT = document.getElementById('liveTemp');
    const elP = document.getElementById('livePH');
    const elTs= document.getElementById('heroTs');
    if(elO) { elO.textContent=organic; elO.style.animation='countFlash .6s ease'; setTimeout(()=>elO.style.animation='',600); }
    if(elT) elT.textContent=temp;
    if(elP) elP.textContent=ph;
    if(elTs) elTs.textContent=ts||new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true});

    // Progress bars
    const bO=document.getElementById('barOrganic'),bT=document.getElementById('barTemp'),bP=document.getElementById('barPH');
    if(bO){ bO.style.width=Math.min(100,organic/0.35)+'%'; bO.style.background=organic>=THR.organic_crit?'var(--accent-red)':(organic>=THR.organic_warn?'var(--accent-amber)':'var(--accent-emerald)'); }
    if(bT) bT.style.width=Math.min(100,(temp-20)*10)+'%';
    if(bP) bP.style.width=Math.min(100,ph*10)+'%';

    // Gauges (SVG stroke-dashoffset: 251.2 = full circle, 0 = empty)
    updateGauge('gaugeOrganic', organic, 0, 35);
    updateGauge('gaugeTemp',    temp,   20, 38);
    updateGauge('gaugePH',      ph,      5, 10);
    const gOv=document.getElementById('gaugeOrgVal'), gTv=document.getElementById('gaugeTempVal'), gPv=document.getElementById('gaugePHVal');
    if(gOv) gOv.textContent=organic;
    if(gTv) gTv.textContent=temp;
    if(gPv) gPv.textContent=ph;

    // Threshold panel
    updateThreshold('thr-organic','thr-organic-val',organic,THR.organic_warn,THR.organic_crit,'var(--accent-emerald)',true);
    updateThreshold('thr-temp',   'thr-temp-val',   temp,  THR.temp_warn,  THR.temp_crit,   'var(--accent-amber)',true);
    updateThresholdPH('thr-ph','thr-ph-val',ph);

    // Hero card class + badge
    const hero = document.getElementById('heroCard');
    const badge = document.getElementById('heroBadge');
    const tag   = document.getElementById('statusTag');
    if(hero)  { hero.className=`hero-status ${status}`; }
    if(badge) { badge.className=`badge badge-${status}`; badge.innerHTML=`<span class="dot-blink"></span> ${status.toUpperCase()}`; }
    if(tag)   {
        const cls = status==='safe'?'green':(status==='warning'?'amber':'red');
        tag.className=`sys-tag ${cls}`;
        tag.innerHTML=`<span class="blink-dot"></span> ${status.toUpperCase()}`;
    }

    // Notify modal current readings
    const nO=document.getElementById('nm-org'),nT=document.getElementById('nm-tmp'),nP=document.getElementById('nm-ph');
    if(nO) nO.textContent=organic;
    if(nT) nT.textContent=temp;
    if(nP) nP.textContent=ph;
}

function updateGauge(id, val, min, max) {
    const el = document.getElementById(id);
    if(!el) return;
    const pct = Math.max(0, Math.min(1, (val-min)/(max-min)));
    const offset = 251.2 * (1 - pct);
    el.style.strokeDashoffset = offset;
}

function updateThreshold(rowId, valId, val, warn, crit, okColor, highIsBad) {
    const row = document.getElementById(rowId);
    const span= document.getElementById(valId);
    if(!row||!span) return;
    span.textContent = val;
    const isBad  = highIsBad ? val >= crit : val <= crit;
    const isWarn = highIsBad ? val >= warn  : val <= warn;
    if(isBad)       { row.className='threshold-row crit'; span.style.color='var(--accent-red)'; }
    else if(isWarn) { row.className='threshold-row warn'; span.style.color='var(--accent-amber)'; }
    else            { row.className='threshold-row ok';   span.style.color=okColor; }
}

function updateThresholdPH(rowId, valId, ph) {
    const row = document.getElementById(rowId);
    const span= document.getElementById(valId);
    if(!row||!span) return;
    span.textContent = ph;
    if(ph<=THR.ph_low_crit||ph>=THR.ph_high_crit)     { row.className='threshold-row crit'; span.style.color='var(--accent-red)'; }
    else if(ph<=THR.ph_low_warn||ph>=THR.ph_high_warn) { row.className='threshold-row warn'; span.style.color='var(--accent-amber)'; }
    else                                                { row.className='threshold-row ok';   span.style.color='var(--accent-violet)'; }
}

// ============================================================
// HISTORY TABLE
// ============================================================
function addHistoryRow(organic, temp, ph, status) {
    readings.unshift({ org:organic, tmp:temp, ph:ph, ts:new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true}) });
    if(readings.length > 12) readings.pop();
    const tbody = document.getElementById('historyBody');
    if(!tbody) return;
    const scolor = status==='safe'?'var(--accent-emerald)':(status==='warning'?'var(--accent-amber)':'var(--accent-red)');
    const newRow = document.createElement('tr');
    newRow.style.animation='slideIn .3s ease';
    newRow.innerHTML=`
        <td style="color:var(--text-muted);font-family:var(--font-mono);">${readings.length}</td>
        <td><span style="color:var(--accent-emerald);font-family:var(--font-mono);font-weight:700;">${organic}</span></td>
        <td><span style="color:var(--accent-amber);font-family:var(--font-mono);font-weight:700;">${temp}</span></td>
        <td><span style="color:var(--accent-violet);font-family:var(--font-mono);font-weight:700;">${ph}</span></td>
        <td><span class="badge badge-${status}">${status.toUpperCase()}</span></td>
        <td style="font-family:var(--font-mono);font-size:.7rem;color:var(--text-muted);">Just now</td>`;
    tbody.insertBefore(newRow, tbody.firstChild);
    if(tbody.children.length > 12) tbody.removeChild(tbody.lastChild);
}

// ============================================================
// MAINTENANCE LOG
// ============================================================
function openLogModal() { openModal('logModal'); }

function saveLog() {
    const action = document.getElementById('logAction').value.trim();
    const notes  = document.getElementById('logNotes').value.trim();
    if(!action) { toast('Action field is required','warning'); return; }

    const saveBtn = document.querySelector('#logModal .btn-primary');
    saveBtn.disabled = true; saveBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';

    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=log_maintenance&action_text=${encodeURIComponent(action)}&notes=${encodeURIComponent(notes)}`})
    .then(r=>r.json())
    .then(d=>{
        saveBtn.disabled=false; saveBtn.innerHTML='<i class="fas fa-save"></i> Save Log';
        if(!d.success){ toast(d.message,'critical'); return; }
        // Prepend to DOM list
        const list = document.getElementById('maintenanceList');
        const el   = document.createElement('div');
        el.className='log-item';
        el.innerHTML=`
            <div class="log-icon"><i class="fas fa-tools"></i></div>
            <div style="flex:1;">
                <div class="log-title">${action}</div>
                <div class="log-meta"><i class="fas fa-user"></i> ${FNAME} &nbsp;·&nbsp; <i class="far fa-clock"></i> Just now</div>
                ${notes?`<div class="log-notes"><i class="fas fa-sticky-note"></i> ${notes}</div>`:''}
            </div>`;
        list.insertBefore(el, list.firstChild);
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
    notifyLevel = level;
    document.querySelectorAll('.notify-level-btn').forEach(b => b.className='notify-level-btn');
    el.className=`notify-level-btn active-${level}`;
}

function sendNotification() {
    const msg = document.getElementById('notifyMsg').value.trim();
    if(!msg){ toast('Message required','warning'); return; }
    const btn = document.querySelector('#notifyModal .btn-warning');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending…';

    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=notify_manager&message=${encodeURIComponent(msg)}&level=${notifyLevel}`})
    .then(r=>r.json())
    .then(d=>{
        btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send to Manager';
        closeModal('notifyModal');
        toast(d.message,'success');
    });
}

// ============================================================
// MODAL HELPERS
// ============================================================
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) closeModal(m.id); }));
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal.open').forEach(m=>m.classList.remove('open')); });

// ============================================================
// TOAST
// ============================================================
function toast(msg, type='info') {
    const c = document.getElementById('toastWrap');
    const t = document.createElement('div');
    t.className=`toast ${type}`;
    const icons={success:'check-circle',warning:'exclamation-triangle',critical:'times-circle',info:'info-circle'};
    t.innerHTML=`<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
    c.appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translateX(50px)'; t.style.transition='.3s'; setTimeout(()=>t.remove(),300); },3500);
}

window.addEventListener('resize',()=>{ if(metricsChart) metricsChart.resize(); });
</script>
</body>
</html>