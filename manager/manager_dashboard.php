<?php
// manager_dashboard.php — Enhanced v2
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    header("Location: ../auth/login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

$manager_id       = $_SESSION['user_id'];
$manager_name     = $_SESSION['full_name'] ?? 'Manager';
$manager_email    = $_SESSION['email'] ?? 'manager@example.com';
$current_time_12hr = date('h:i:s A');
$current_date      = date('F j, Y');
$current_day       = date('l');

// ============================================================
// POND CONFIG — polygon bounds matching admin dashboard
// ============================================================
$pond_coordinates = [
    'A-1' => [
        'name'   => 'Tilapia Pond A-1',
        'center' => [8.3695, 124.8645],
        'staff'  => 'Pedro Reyes',
        'bounds' => [
            [8.3692, 124.8642], [8.3698, 124.8640],
            [8.3700, 124.8648], [8.3696, 124.8650],
            [8.3692, 124.8642]
        ]
    ],
    'B-2' => [
        'name'   => 'Tilapia Pond B-2',
        'center' => [8.3688, 124.8652],
        'staff'  => 'Ana Lopez',
        'bounds' => [
            [8.3685, 124.8649], [8.3690, 124.8647],
            [8.3693, 124.8654], [8.3688, 124.8656],
            [8.3685, 124.8649]
        ]
    ],
    'C-1' => [
        'name'   => 'Tilapia Pond C-1',
        'center' => [8.3700, 124.8660],
        'staff'  => 'Roberto Gomez',
        'bounds' => [
            [8.3697, 124.8657], [8.3703, 124.8655],
            [8.3705, 124.8663], [8.3699, 124.8665],
            [8.3697, 124.8657]
        ]
    ]
];

// ============================================================
// STAFF ASSIGNMENTS
// ============================================================
$staff_assignments = [
    ['user_id'=>3,'full_name'=>'Pedro Reyes',  'email'=>'pedro.reyes@company.com',  'assigned_pond'=>'A-1','last_login'=>date('Y-m-d H:i:s',strtotime('-2 hours')),  'status'=>'active'],
    ['user_id'=>4,'full_name'=>'Ana Lopez',    'email'=>'ana.lopez@company.com',    'assigned_pond'=>'B-2','last_login'=>date('Y-m-d H:i:s',strtotime('-30 minutes')),'status'=>'active'],
    ['user_id'=>5,'full_name'=>'Roberto Gomez','email'=>'roberto.gomez@company.com','assigned_pond'=>'C-1','last_login'=>date('Y-m-d H:i:s',strtotime('-15 minutes')),'status'=>'active'],
];

// ============================================================
// PONDS DATA
// ============================================================
$ponds_data = [
    'A-1' => ['pond_id'=>1,'pond_name'=>'A-1','organic_level'=>65,'temperature'=>28.5,'ph'=>7.2,'status'=>'warning', 'staff'=>'Pedro Reyes', 'location'=>'North Section','last_reading'=>date('Y-m-d H:i:s',strtotime('-5 minutes'))],
    'B-2' => ['pond_id'=>2,'pond_name'=>'B-2','organic_level'=>82,'temperature'=>31.2,'ph'=>8.1,'status'=>'critical','staff'=>'Ana Lopez',   'location'=>'South Section','last_reading'=>date('Y-m-d H:i:s',strtotime('-2 minutes'))],
    'C-1' => ['pond_id'=>3,'pond_name'=>'C-1','organic_level'=>45,'temperature'=>27.5,'ph'=>7.5,'status'=>'safe',    'staff'=>'Roberto Gomez','location'=>'East Section', 'last_reading'=>date('Y-m-d H:i:s',strtotime('-10 minutes'))],
];

// ============================================================
// NOTIFICATIONS
// ============================================================
$notifications = [
    ['notification_id'=>1,'pond_name'=>'B-2','message'=>'HIGH ALERT: Organic level 82% — Critical! Temperature 31.2°C','status'=>'unread','created_at'=>date('Y-m-d H:i:s',strtotime('-2 minutes')),'type'=>'critical'],
    ['notification_id'=>2,'pond_name'=>'A-1','message'=>'WARNING: Organic level 65% — Monitor closely','status'=>'unread','created_at'=>date('Y-m-d H:i:s',strtotime('-15 minutes')),'type'=>'warning'],
    ['notification_id'=>3,'pond_name'=>'C-1','message'=>'INFO: All systems normal — Safe condition','status'=>'read','created_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'type'=>'info'],
];
$unread_count = count(array_filter($notifications, fn($n) => $n['status'] === 'unread'));

// ============================================================
// RECENT READINGS
// ============================================================
$recent_readings = [
    ['detection_id'=>103,'pond_name'=>'C-1','organic_level'=>45,'water_temperature'=>27.5,'ph_level'=>7.5,'detected_at'=>date('Y-m-d H:i:s',strtotime('-10 minutes')),'status'=>'safe'],
    ['detection_id'=>102,'pond_name'=>'B-2','organic_level'=>82,'water_temperature'=>31.2,'ph_level'=>8.1,'detected_at'=>date('Y-m-d H:i:s',strtotime('-2 minutes')), 'status'=>'critical'],
    ['detection_id'=>101,'pond_name'=>'A-1','organic_level'=>65,'water_temperature'=>28.5,'ph_level'=>7.2,'detected_at'=>date('Y-m-d H:i:s',strtotime('-5 minutes')), 'status'=>'warning'],
];

// ============================================================
// CHART DATA — 24 hours
// ============================================================
$chart_data = ['labels'=>[],'organic'=>[],'temperature'=>[],'ph'=>[]];
for ($i = 23; $i >= 0; $i--) {
    $chart_data['labels'][]      = date('H:00', strtotime("-$i hours"));
    $chart_data['organic'][]     = round(60 + sin($i * 0.3) * 10 + rand(-2, 2), 1);
    $chart_data['temperature'][] = round(28 + sin($i * 0.2) * 3  + rand(-1, 1), 1);
    $chart_data['ph'][]          = round(7.2 + sin($i * 0.25) * 0.5 + rand(-1, 1) / 10, 1);
}

// ============================================================
// KPI SUMMARY
// ============================================================
$safe_count     = count(array_filter($ponds_data, fn($p) => $p['status'] === 'safe'));
$warning_count  = count(array_filter($ponds_data, fn($p) => $p['status'] === 'warning'));
$critical_count = count(array_filter($ponds_data, fn($p) => $p['status'] === 'critical'));
$avg_organic    = round(array_sum(array_column($ponds_data,'organic_level')) / count($ponds_data), 1);
$avg_temp       = round(array_sum(array_column($ponds_data,'temperature'))   / count($ponds_data), 1);

// ============================================================
// AJAX HANDLERS
// ============================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'get_chart_data') {
        $period = $_POST['period'] ?? 'daily';
        $data   = ['labels'=>[],'organic'=>[],'temperature'=>[],'ph'=>[]];
        if ($period === 'daily') {
            for ($i = 23; $i >= 0; $i--) {
                $data['labels'][]      = date('H:00', strtotime("-$i hours"));
                $data['organic'][]     = rand(45, 85);
                $data['temperature'][] = rand(25, 33);
                $data['ph'][]          = round(rand(65, 85) / 10, 1);
            }
        } elseif ($period === 'weekly') {
            foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d) {
                $data['labels'][]      = $d;
                $data['organic'][]     = rand(45, 85);
                $data['temperature'][] = rand(25, 33);
                $data['ph'][]          = round(rand(65, 85) / 10, 1);
            }
        } else {
            for ($i = 29; $i >= 0; $i--) {
                $data['labels'][]      = date('M d', strtotime("-$i days"));
                $data['organic'][]     = rand(45, 85);
                $data['temperature'][] = rand(25, 33);
                $data['ph'][]          = round(rand(65, 85) / 10, 1);
            }
        }
        echo json_encode($data); exit;
    }

    if ($_POST['action'] === 'notify_admin') {
        $pond = $_POST['pond'] ?? 'Unknown';
        echo json_encode(['success'=>true,'message'=>"Admin notified for Pond $pond",'timestamp'=>date('h:i:s A')]); exit;
    }

    if ($_POST['action'] === 'get_iot_reading') {
        $key     = $_POST['pond_key'] ?? '';
        $organic = rand(45, 92);
        $temp    = round(rand(250, 340) / 10, 1);
        $ph      = round(rand(63, 90) / 10, 1);
        $status  = 'safe';
        if ($organic > 80 || $temp > 32 || $ph > 8.5) $status = 'critical';
        elseif ($organic > 60 || $temp > 30 || $ph > 7.8) $status = 'warning';
        echo json_encode(['success'=>true,'pond'=>$key,'organic'=>$organic,'temp'=>$temp,'ph'=>$ph,'status'=>$status,'timestamp'=>date('h:i:s A')]); exit;
    }

    if ($_POST['action'] === 'acknowledge_alert') {
        $id = intval($_POST['alert_id'] ?? 0);
        echo json_encode(['success'=>true,'message'=>"Alert #$id acknowledged"]); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AquaManager — Pond Monitoring</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* ===================================================
   CSS VARIABLES — matches admin dashboard palette
=================================================== */
:root {
    --bg-deep:      #060d17;
    --bg-panel:     #0b1625;
    --bg-card:      #0f1e30;
    --bg-elevated:  #142235;
    --bg-hover:     #1a2d45;
    --accent-cyan:  #00e5ff;
    --accent-green: #39ff8a;
    --accent-amber: #ffb800;
    --accent-red:   #ff3b5c;
    --accent-violet:#b06cff;
    --accent-teal:  #00c9b1;
    --text-primary: #e8f4ff;
    --text-secondary:#8ba8c4;
    --text-muted:   #4a6380;
    --border:       rgba(0,229,255,0.12);
    --border-glow:  rgba(0,229,255,0.35);
    --font-display: 'Syne', sans-serif;
    --font-mono:    'Space Mono', monospace;
    --r-sm: 8px;
    --r-md: 14px;
    --r-lg: 20px;
    --r-xl: 28px;
    --shadow-cyan:  0 0 30px rgba(0,229,255,0.15);
    --shadow-card:  0 8px 32px rgba(0,0,0,0.4);
}

/* ===================================================
   RESET & BASE
=================================================== */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body {
    background: var(--bg-deep);
    color: var(--text-primary);
    font-family: var(--font-display);
    min-height: 100vh;
    overflow-x: hidden;
}

/* Animated grid bg */
body::before {
    content:'';
    position:fixed; inset:0;
    background-image:
        linear-gradient(rgba(0,229,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,229,255,.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events:none; z-index:0;
    animation: gridScroll 25s linear infinite;
}
body::after {
    content:'';
    position:fixed;
    bottom:-200px; left:-200px;
    width:500px; height:500px;
    background: radial-gradient(circle, rgba(0,201,177,.05) 0%, transparent 70%);
    pointer-events:none; z-index:0;
    animation: orbFloat 18s ease-in-out infinite alternate;
}

@keyframes gridScroll  { 0%{background-position:0 0}100%{background-position:40px 40px} }
@keyframes orbFloat    { 0%{transform:translate(0,0)}100%{transform:translate(80px,-80px)} }
@keyframes fadeUp      { from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)} }
@keyframes slideRight  { from{transform:translateX(-16px);opacity:0}to{transform:translateX(0);opacity:1} }
@keyframes blink       { 0%,100%{opacity:1}50%{opacity:.25} }
@keyframes spin        { from{transform:rotate(0deg)}to{transform:rotate(360deg)} }
@keyframes pulseGlow   { 0%,100%{box-shadow:0 0 20px currentColor}50%{box-shadow:0 0 55px currentColor} }
@keyframes pondPulse   { 0%,100%{opacity:.7}50%{opacity:1} }
@keyframes scanMap     { 0%{top:-100%}100%{top:200%} }
@keyframes notifBounce { 0%,100%{transform:scale(1)}50%{transform:scale(1.25)} }
@keyframes sheen       { 0%,100%{left:-50%}50%{left:150%} }
@keyframes dropIcon    { 0%{transform:translateY(-20px);opacity:0}100%{transform:translateY(0);opacity:1} }
@keyframes ringPulse   { 0%{transform:scale(.8);opacity:1}50%{transform:scale(1.8);opacity:.3}100%{transform:scale(.8);opacity:1} }

/* ===================================================
   SCROLLBAR
=================================================== */
::-webkit-scrollbar { width:4px; height:4px; }
::-webkit-scrollbar-track { background:var(--bg-deep); }
::-webkit-scrollbar-thumb { background:var(--accent-cyan); border-radius:2px; }

/* ===================================================
   NAVBAR
=================================================== */
.navbar {
    position:sticky; top:0; z-index:1000;
    background: rgba(6,13,23,.96);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    height: 64px;
    display:flex; align-items:center; justify-content:space-between;
    box-shadow: 0 1px 0 var(--border-glow);
}
.nav-brand { display:flex; align-items:center; gap:.8rem; }
.nav-logo {
    width:38px; height:38px;
    background: linear-gradient(135deg, var(--accent-teal), var(--accent-cyan));
    border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; color:#000; font-weight:800;
    position:relative; overflow:hidden;
}
.nav-logo::after {
    content:'';
    position:absolute; top:-50%; left:-50%;
    width:30%; height:200%;
    background:rgba(255,255,255,.4);
    transform:skewX(-20deg);
    animation: sheen 4s infinite;
}
.nav-title {
    font-weight:800; font-size:1.05rem; letter-spacing:.5px;
    background: linear-gradient(90deg, var(--accent-teal), var(--text-primary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.nav-sub { font-size:.7rem; color:var(--text-muted); font-family:var(--font-mono); }
.nav-right { display:flex; align-items:center; gap:.9rem; }
.nav-clock {
    font-family:var(--font-mono); font-size:.78rem; color:var(--accent-teal);
    background:rgba(0,201,177,.07); border:1px solid rgba(0,201,177,.2);
    padding:.35rem .8rem; border-radius:6px; letter-spacing:1px; min-width:130px; text-align:center;
}
.nav-user {
    display:flex; align-items:center; gap:.6rem;
    background:var(--bg-elevated); border:1px solid var(--border);
    padding:.4rem .9rem; border-radius:50px; font-size:.82rem; cursor:default;
    transition:.3s;
}
.nav-user:hover { border-color:var(--accent-teal); }
.notif-dot {
    background:var(--accent-red); color:#fff; border-radius:50%;
    padding:.15rem .42rem; font-size:.65rem; font-family:var(--font-mono);
    animation: notifBounce 2s infinite;
}
.btn-logout {
    display:inline-flex; align-items:center; gap:.5rem;
    background:transparent; border:1px solid rgba(255,59,92,.35);
    color:var(--accent-red); padding:.4rem .9rem; border-radius:50px;
    font-size:.82rem; font-family:var(--font-display); cursor:pointer;
    transition:.3s; text-decoration:none;
}
.btn-logout:hover { background:rgba(255,59,92,.12); border-color:var(--accent-red); }

/* ===================================================
   LAYOUT
=================================================== */
.wrap {
    position:relative; z-index:1;
    padding:1.5rem 2rem 3rem;
    max-width:1700px; margin:0 auto;
}

/* ===================================================
   TOPBAR
=================================================== */
.topbar {
    display:flex; justify-content:space-between; align-items:center;
    padding:.75rem 1.5rem;
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-lg); margin-bottom:1.5rem;
    animation: fadeUp .5s ease both;
}
.topbar-left { display:flex; align-items:center; gap:1rem; }
.topbar-date { font-family:var(--font-mono); font-size:.78rem; color:var(--text-secondary); letter-spacing:.5px; }
.topbar-day  { font-size:1.1rem; font-weight:700; }
.sys-tag {
    display:inline-flex; align-items:center; gap:.4rem;
    font-family:var(--font-mono); font-size:.7rem;
    padding:.3rem .8rem; border-radius:4px; letter-spacing:.5px;
}
.sys-tag.green { background:rgba(57,255,138,.08); border:1px solid rgba(57,255,138,.25); color:var(--accent-green); }
.sys-tag.teal  { background:rgba(0,201,177,.08);  border:1px solid rgba(0,201,177,.25);  color:var(--accent-teal); }
.sys-tag.red   { background:rgba(255,59,92,.08);  border:1px solid rgba(255,59,92,.25);  color:var(--accent-red); }
.blink-dot { width:6px; height:6px; border-radius:50%; background:currentColor; animation:blink 1.5s infinite; }

.sim-toggle {
    display:inline-flex; align-items:center; gap:.5rem;
    background:rgba(0,201,177,.08); border:1px solid rgba(0,201,177,.25);
    color:var(--accent-teal); padding:.35rem .9rem; border-radius:50px;
    font-family:var(--font-mono); font-size:.72rem; cursor:pointer; transition:.3s;
    letter-spacing:.4px;
}
.sim-toggle:hover { background:rgba(0,201,177,.18); }
.sim-toggle.paused { background:rgba(255,59,92,.08); border-color:rgba(255,59,92,.25); color:var(--accent-red); }

/* ===================================================
   KPI CARDS
=================================================== */
.kpi-grid {
    display:grid; grid-template-columns:repeat(5,1fr);
    gap:1rem; margin-bottom:1.5rem;
}
@media(max-width:1200px){ .kpi-grid{grid-template-columns:repeat(3,1fr);} }
@media(max-width:640px) { .kpi-grid{grid-template-columns:repeat(2,1fr);} }

.kpi {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-lg); padding:1.2rem 1.3rem;
    position:relative; overflow:hidden; cursor:default;
    transition:.4s cubic-bezier(.4,0,.2,1);
    animation: fadeUp .5s ease both;
}
.kpi:hover { transform:translateY(-4px); border-color:var(--border-glow); box-shadow:var(--shadow-cyan); }
.kpi::before {
    content:''; position:absolute; top:0; left:0; right:0; height:2px;
    background:linear-gradient(90deg,transparent,var(--kpi-color,var(--accent-teal)),transparent);
}
.kpi-icon {
    width:38px; height:38px; border-radius:9px;
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; margin-bottom:.8rem;
    color:var(--kpi-color,var(--accent-teal));
    background:rgba(0,0,0,.3); border:1px solid currentColor; opacity:.9;
}
.kpi-val {
    font-family:var(--font-mono); font-size:1.7rem; font-weight:700;
    color:var(--kpi-color,var(--accent-teal)); line-height:1; margin-bottom:.25rem;
}
.kpi-label { font-size:.72rem; color:var(--text-muted); letter-spacing:.5px; text-transform:uppercase; }
.kpi-corner { position:absolute; top:.8rem; right:.8rem; font-size:.6rem; font-family:var(--font-mono); color:var(--kpi-color); opacity:.5; letter-spacing:.4px; }

/* ===================================================
   GENERIC CARD
=================================================== */
.card {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-xl); padding:1.4rem 1.5rem;
    animation: fadeUp .6s ease both; position:relative; overflow:hidden;
}
.card:hover { border-color:rgba(0,229,255,.2); }

.sec-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.sec-title { display:flex; align-items:center; gap:.6rem; font-size:.88rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; }
.sec-title i { color:var(--accent-teal); }
.sec-badge {
    font-family:var(--font-mono); font-size:.65rem; padding:.2rem .6rem; border-radius:4px;
    background:rgba(0,201,177,.1); border:1px solid rgba(0,201,177,.25); color:var(--accent-teal); letter-spacing:.5px;
}

.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.2rem; margin-bottom:1.2rem; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1.2rem; margin-bottom:1.2rem; }
@media(max-width:1100px){ .grid-2,.grid-3{grid-template-columns:1fr;} }

/* ===================================================
   STAFF CARDS
=================================================== */
.staff-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1.2rem; margin-bottom:1.2rem; }
@media(max-width:900px){ .staff-grid{grid-template-columns:1fr 1fr;} }
@media(max-width:600px){ .staff-grid{grid-template-columns:1fr;} }

.staff-card {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-xl); padding:1.4rem 1.3rem;
    cursor:pointer; transition:.35s cubic-bezier(.4,0,.2,1);
    animation: fadeUp .5s ease both;
    position:relative; overflow:hidden;
}
.staff-card::before {
    content:''; position:absolute; top:0; left:0; right:0; height:2px;
    background:linear-gradient(90deg,transparent,var(--accent-teal),transparent);
    opacity:0; transition:.3s;
}
.staff-card:hover { transform:translateY(-5px); border-color:var(--accent-teal); box-shadow:0 0 24px rgba(0,201,177,.12); }
.staff-card:hover::before { opacity:1; }

.staff-avatar {
    width:48px; height:48px;
    background: linear-gradient(135deg, var(--accent-teal), var(--accent-cyan));
    border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-weight:800; font-size:.95rem; color:#000;
    margin-bottom:1rem; position:relative; overflow:hidden;
}
.staff-avatar::after {
    content:''; position:absolute; top:-50%; left:-50%;
    width:30%; height:200%; background:rgba(255,255,255,.35);
    transform:skewX(-20deg); animation:sheen 5s infinite;
}
.staff-name { font-size:.95rem; font-weight:700; margin-bottom:.3rem; }
.staff-email { font-size:.72rem; color:var(--text-muted); font-family:var(--font-mono); margin-bottom:.7rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.staff-pond-tag {
    display:inline-flex; align-items:center; gap:.35rem;
    background:rgba(0,201,177,.1); border:1px solid rgba(0,201,177,.25);
    color:var(--accent-teal); padding:.25rem .7rem; border-radius:4px;
    font-family:var(--font-mono); font-size:.7rem; margin-bottom:.7rem;
}
.staff-foot { display:flex; align-items:center; justify-content:space-between; }
.staff-status { display:flex; align-items:center; gap:.35rem; font-size:.75rem; }

/* ===================================================
   POND CARDS — identical layout to admin
=================================================== */
.pond-cards-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1.2rem; margin-bottom:1.2rem; }
@media(max-width:900px){ .pond-cards-grid{grid-template-columns:1fr 1fr;} }
@media(max-width:600px){ .pond-cards-grid{grid-template-columns:1fr;} }

.pond-card {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-xl); padding:1.2rem;
    cursor:pointer; position:relative; overflow:hidden;
    transition:.35s cubic-bezier(.4,0,.2,1);
    animation: fadeUp .6s ease both;
}
.pond-card::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
}
.pond-card.safe::before    { background:var(--accent-green); }
.pond-card.warning::before { background:var(--accent-amber); }
.pond-card.critical::before{ background:var(--accent-red); animation:pulseGlow 2s infinite; }
.pond-card:hover { transform:translateY(-4px); border-color:var(--border-glow); }
.pond-card.critical { animation:pondPulse 2s infinite; }

.pond-card-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem; }
.pond-card-name { font-weight:700; font-size:.92rem; display:flex; align-items:center; gap:.4rem; }
.pond-card-name i { font-size:.85rem; }

.metrics-row { display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; margin:.7rem 0; }
.metric-chip {
    text-align:center; padding:.55rem .3rem;
    background:rgba(0,0,0,.25); border-radius:8px;
    transition:.25s; cursor:default;
}
.metric-chip:hover { background:rgba(0,229,255,.07); }
.metric-chip i    { font-size:.85rem; display:block; margin-bottom:.2rem; }
.metric-val { font-family:var(--font-mono); font-size:.95rem; font-weight:700; }
.metric-lbl { font-size:.58rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.3px; }

.ic-organic { color:var(--accent-green); }
.ic-temp    { color:var(--accent-amber); }
.ic-ph      { color:var(--accent-violet); }

.pond-bar-wrap { margin:.4rem 0; }
.pond-bar-label { font-size:.62rem; color:var(--text-muted); font-family:var(--font-mono); display:flex; justify-content:space-between; margin-bottom:.25rem; }
.pond-bar { height:4px; background:rgba(255,255,255,.07); border-radius:2px; overflow:hidden; }
.pond-bar-fill { height:100%; border-radius:2px; transition:width 1s ease; }

.pond-info-row { font-size:.75rem; color:var(--text-muted); margin:.5rem 0; display:flex; align-items:center; gap:.5rem; }
.pond-ts { font-family:var(--font-mono); font-size:.63rem; color:var(--text-muted); display:flex; align-items:center; gap:.35rem; margin-top:.4rem; }
.pond-actions { display:flex; gap:.4rem; margin-top:.7rem; }

/* ===================================================
   BADGES
=================================================== */
.badge {
    display:inline-flex; align-items:center; gap:.3rem;
    padding:.22rem .65rem; border-radius:4px;
    font-size:.67rem; font-weight:700; font-family:var(--font-mono);
    letter-spacing:.3px; text-transform:uppercase;
}
.badge-safe     { background:rgba(57,255,138,.12); color:var(--accent-green); border:1px solid rgba(57,255,138,.25); }
.badge-warning  { background:rgba(255,184,0,.12);  color:var(--accent-amber);  border:1px solid rgba(255,184,0,.25); }
.badge-critical { background:rgba(255,59,92,.12);  color:var(--accent-red);   border:1px solid rgba(255,59,92,.25); animation:blink 1.4s infinite; }
.badge-active   { background:rgba(57,255,138,.12); color:var(--accent-green); border:1px solid rgba(57,255,138,.25); }
.badge-unread   { background:rgba(255,59,92,.12);  color:var(--accent-red);   border:1px solid rgba(255,59,92,.25); }
.badge-read     { background:rgba(255,184,0,.12);  color:var(--accent-amber);  border:1px solid rgba(255,184,0,.25); }
.badge-info     { background:rgba(0,229,255,.1);   color:var(--accent-cyan);  border:1px solid var(--border); }
.dot-blink { width:5px; height:5px; border-radius:50%; background:currentColor; animation:blink 1.5s infinite; }

/* ===================================================
   BUTTONS
=================================================== */
.btn {
    display:inline-flex; align-items:center; gap:.4rem;
    border:none; border-radius:var(--r-sm); padding:.45rem 1rem;
    font-family:var(--font-display); font-size:.8rem; font-weight:600;
    cursor:pointer; transition:.25s cubic-bezier(.4,0,.2,1);
    letter-spacing:.3px; white-space:nowrap; position:relative; overflow:hidden;
}
.btn:active { transform:scale(.97); }
.btn:disabled { opacity:.4; cursor:not-allowed; pointer-events:none; }
.btn-primary { background:var(--accent-teal); color:#000; }
.btn-primary:hover { background:#00b5a0; }
.btn-success { background:rgba(57,255,138,.15); color:var(--accent-green); border:1px solid rgba(57,255,138,.3); }
.btn-warning { background:rgba(255,184,0,.15);  color:var(--accent-amber);  border:1px solid rgba(255,184,0,.3); }
.btn-danger  { background:rgba(255,59,92,.15);  color:var(--accent-red);   border:1px solid rgba(255,59,92,.3); }
.btn-ghost   { background:var(--bg-elevated);   color:var(--text-secondary); border:1px solid var(--border); }
.btn-ghost:hover { border-color:var(--accent-teal); color:var(--accent-teal); }
.btn-sm { padding:.28rem .65rem; font-size:.72rem; }

/* ===================================================
   MAP
=================================================== */
.map-card { margin-bottom:1.2rem; }
#map { height:440px; border-radius:var(--r-lg); overflow:hidden; border:1px solid var(--border); position:relative; }
.map-scan {
    position:absolute; left:0; right:0; height:3px;
    background:linear-gradient(90deg,transparent,rgba(0,201,177,.45),transparent);
    pointer-events:none; z-index:500;
    animation:scanMap 6s linear infinite;
}
.map-legend { display:flex; gap:.8rem; align-items:center; font-family:var(--font-mono); font-size:.7rem; }
.leg-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.leg-item { display:flex; align-items:center; gap:.35rem; color:var(--text-secondary); }
.iot-live { display:inline-flex; align-items:center; gap:.4rem; font-family:var(--font-mono); font-size:.65rem; color:var(--accent-green); letter-spacing:.5px; }
.iot-live span { width:6px; height:6px; border-radius:50%; background:var(--accent-green); animation:blink 1.2s infinite; }

/* Leaflet overrides */
.leaflet-container          { background:#060d17!important; font-family:var(--font-display)!important; }
.leaflet-popup-content-wrapper { background:var(--bg-panel)!important; border:1px solid var(--border)!important; border-radius:var(--r-md)!important; box-shadow:0 8px 32px rgba(0,0,0,.5)!important; color:var(--text-primary)!important; }
.leaflet-popup-tip          { background:var(--bg-panel)!important; }
.leaflet-popup-close-button { color:var(--text-secondary)!important; }
.leaflet-control-zoom a     { background:var(--bg-panel)!important; color:var(--text-primary)!important; border-color:var(--border)!important; }
.leaflet-control-attribution{ background:rgba(6,13,23,.8)!important; color:var(--text-muted)!important; }

/* ===================================================
   CHART
=================================================== */
.chart-wrap { height:280px; margin-top:.8rem; }
.period-tabs { display:flex; gap:.4rem; }
.period-btn {
    font-family:var(--font-mono); font-size:.68rem;
    padding:.28rem .7rem; border-radius:4px;
    background:var(--bg-elevated); border:1px solid var(--border);
    color:var(--text-muted); cursor:pointer; transition:.2s; letter-spacing:.3px;
}
.period-btn.active, .period-btn:hover { background:rgba(0,201,177,.12); border-color:var(--accent-teal); color:var(--accent-teal); }

/* ===================================================
   READINGS TABLE
=================================================== */
.tbl-wrap { overflow-x:auto; margin-top:.6rem; }
table { width:100%; border-collapse:collapse; }
th { text-align:left; padding:.6rem .8rem; font-size:.68rem; font-weight:600; color:var(--text-muted); letter-spacing:.8px; text-transform:uppercase; border-bottom:1px solid var(--border); font-family:var(--font-mono); }
td { padding:.65rem .8rem; border-bottom:1px solid rgba(255,255,255,.04); font-size:.82rem; vertical-align:middle; }
tr:hover td { background:rgba(0,201,177,.03); }
tr:last-child td { border-bottom:none; }

/* ===================================================
   ALERTS
=================================================== */
.alert-item {
    display:flex; gap:.8rem; padding:.8rem;
    border-radius:var(--r-md); margin-bottom:.5rem;
    border-left:3px solid transparent; background:rgba(0,0,0,.2);
    cursor:pointer; transition:.25s;
    animation: slideRight .3s ease both;
}
.alert-item:hover { background:var(--bg-elevated); }
.alert-item.critical { border-left-color:var(--accent-red); }
.alert-item.warning  { border-left-color:var(--accent-amber); }
.alert-item.info     { border-left-color:var(--accent-cyan); }
.alert-icon { font-size:1rem; flex-shrink:0; margin-top:.1rem; }
.alert-icon.critical { color:var(--accent-red); }
.alert-icon.warning  { color:var(--accent-amber); }
.alert-icon.info     { color:var(--accent-cyan); }
.alert-pond  { font-weight:700; font-size:.82rem; }
.alert-msg   { font-size:.78rem; color:var(--text-secondary); margin:.12rem 0; }
.alert-foot  { display:flex; justify-content:space-between; align-items:center; }
.alert-time  { font-family:var(--font-mono); font-size:.63rem; color:var(--text-muted); }

/* ===================================================
   NOTIFY PANEL
=================================================== */
.notify-card {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-xl); padding:1.4rem 1.5rem;
    animation: fadeUp .6s ease both;
}

/* ===================================================
   POND DETAIL MODAL
=================================================== */
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.8); backdrop-filter:blur(8px); z-index:3000; align-items:center; justify-content:center; }
.modal.open { display:flex; }
.modal-box {
    background:var(--bg-panel); border:1px solid var(--border);
    border-radius:var(--r-xl); padding:1.8rem;
    width:90%; max-width:500px; max-height:85vh; overflow-y:auto;
    animation: fadeUp .3s ease;
}
.modal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem; padding-bottom:.7rem; border-bottom:1px solid var(--border); }
.modal-title { font-size:1rem; font-weight:700; }
.modal-close { background:none; border:none; color:var(--text-muted); font-size:1.4rem; cursor:pointer; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:.2s; }
.modal-close:hover { color:var(--accent-red); background:rgba(255,59,92,.1); }
.pond-detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:.7rem; margin-top:.9rem; }
.detail-row { background:var(--bg-elevated); border-radius:var(--r-sm); padding:.65rem .8rem; border:1px solid var(--border); }
.detail-lbl { font-size:.63rem; color:var(--text-muted); font-family:var(--font-mono); text-transform:uppercase; letter-spacing:.4px; margin-bottom:.2rem; }
.detail-val { font-size:.86rem; font-weight:600; }
.meter { height:6px; background:rgba(255,255,255,.06); border-radius:3px; overflow:hidden; margin-top:.5rem; }
.meter-fill { height:100%; border-radius:3px; transition:width 1.2s ease; }
.meter-safe     { background:linear-gradient(90deg,var(--accent-green),rgba(57,255,138,.5)); }
.meter-warning  { background:linear-gradient(90deg,var(--accent-amber),rgba(255,184,0,.5)); }
.meter-critical { background:linear-gradient(90deg,var(--accent-red),rgba(255,59,92,.5)); }

/* ===================================================
   TOAST
=================================================== */
.toast-container { position:fixed; top:78px; right:20px; z-index:5000; display:flex; flex-direction:column; gap:.5rem; }
.toast {
    display:flex; align-items:center; gap:.6rem;
    padding:.75rem 1.1rem; border-radius:var(--r-md);
    font-size:.8rem; font-weight:600; min-width:250px;
    animation: fadeUp .3s ease; box-shadow:0 8px 24px rgba(0,0,0,.4);
}
.toast.success { background:rgba(57,255,138,.15); border:1px solid rgba(57,255,138,.3); color:var(--accent-green); }
.toast.warning { background:rgba(255,184,0,.15);  border:1px solid rgba(255,184,0,.3);  color:var(--accent-amber); }
.toast.critical{ background:rgba(255,59,92,.15);  border:1px solid rgba(255,59,92,.3);  color:var(--accent-red); }
.toast.info    { background:rgba(0,229,255,.1);   border:1px solid var(--border);        color:var(--accent-cyan); }

/* ===================================================
   FOOTER
=================================================== */
.dash-footer {
    margin-top:2rem; padding:.8rem 1.5rem;
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--r-lg); display:flex; justify-content:space-between; align-items:center;
    font-family:var(--font-mono); font-size:.7rem; color:var(--text-muted);
}
</style>
</head>
<body>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-brand">
        <div class="nav-logo"><i class="fas fa-fish"></i></div>
        <div>
            <div class="nav-title">AquaManager</div>
            <div class="nav-sub">Pond Monitoring · Manolo Fortich, BUK</div>
        </div>
    </div>
    <div class="nav-right">
        <div class="nav-clock" id="navClock"><?php echo $current_time_12hr; ?></div>
        <div class="nav-user">
            <i class="fas fa-user-tie" style="color:var(--accent-teal);font-size:.85rem;"></i>
            <span><?php echo htmlspecialchars($manager_name); ?></span>
            <span class="notif-dot" id="notifBadge"><?php echo $unread_count; ?></span>
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
                <div class="topbar-day"><?php echo $current_day; ?></div>
                <div class="topbar-date"><?php echo $current_date; ?></div>
            </div>
            <div class="sys-tag green"><span class="blink-dot"></span> SYSTEM ONLINE</div>
            <div class="sys-tag teal"><i class="fas fa-microchip" style="font-size:.6rem;"></i> IOT ACTIVE</div>
            <?php if($critical_count > 0): ?>
            <div class="sys-tag red"><span class="blink-dot"></span> <?php echo $critical_count; ?> CRITICAL</div>
            <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:.8rem;">
            <div class="iot-live"><span></span> LIVE FEED</div>
            <div class="sim-toggle" id="simToggle" onclick="toggleSim()">
                <span class="blink-dot" id="simDot" style="color:var(--accent-teal);"></span>
                <span id="simLabel">SIM RUNNING</span>
            </div>
        </div>
    </div>

    <!-- KPI GRID -->
    <div class="kpi-grid">
        <div class="kpi" style="--kpi-color:var(--accent-teal);">
            <div class="kpi-corner">PONDS</div>
            <div class="kpi-icon"><i class="fas fa-water"></i></div>
            <div class="kpi-val"><?php echo count($ponds_data); ?></div>
            <div class="kpi-label">Total Ponds</div>
        </div>
        <div class="kpi" style="--kpi-color:var(--accent-green);">
            <div class="kpi-corner">SAFE</div>
            <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
            <div class="kpi-val"><?php echo $safe_count; ?></div>
            <div class="kpi-label">Safe Ponds</div>
        </div>
        <div class="kpi" style="--kpi-color:var(--accent-amber);">
            <div class="kpi-corner">WARN</div>
            <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="kpi-val"><?php echo $warning_count; ?></div>
            <div class="kpi-label">Warning Ponds</div>
        </div>
        <div class="kpi" style="--kpi-color:var(--accent-red);">
            <div class="kpi-corner">CRIT</div>
            <div class="kpi-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="kpi-val" id="kpiCritical"><?php echo $critical_count; ?></div>
            <div class="kpi-label">Critical Ponds</div>
        </div>
        <div class="kpi" style="--kpi-color:var(--accent-violet);">
            <div class="kpi-corner">AVG</div>
            <div class="kpi-icon"><i class="fas fa-seedling"></i></div>
            <div class="kpi-val"><?php echo $avg_organic; ?>%</div>
            <div class="kpi-label">Avg Organic</div>
        </div>
    </div>

    <!-- STAFF SECTION -->
    <div class="card" style="margin-bottom:1.2rem;">
        <div class="sec-head">
            <div class="sec-title"><i class="fas fa-users"></i> Staff Assignments</div>
            <div class="sec-badge"><?php echo count($staff_assignments); ?> STAFF</div>
        </div>
        <div class="staff-grid">
            <?php foreach($staff_assignments as $s):
                $init='';
                foreach(explode(' ',$s['full_name']) as $n) $init.=strtoupper(substr($n,0,1));
                $pond = $ponds_data[$s['assigned_pond']] ?? null;
                $pstatus = $pond ? $pond['status'] : 'safe';
            ?>
            <div class="staff-card" onclick="focusPond('<?php echo $s['assigned_pond']; ?>')">
                <div class="staff-avatar"><?php echo $init; ?></div>
                <div class="staff-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                <div class="staff-email"><?php echo htmlspecialchars($s['email']); ?></div>
                <div class="staff-pond-tag">
                    <i class="fas fa-water"></i> Pond <?php echo $s['assigned_pond']; ?>
                    <span class="badge badge-<?php echo $pstatus; ?>" style="margin-left:.3rem;font-size:.6rem;">
                        <?php echo strtoupper($pstatus); ?>
                    </span>
                </div>
                <div class="staff-foot">
                    <div class="staff-status" style="color:var(--accent-green);">
                        <span class="dot-blink"></span> Active
                    </div>
                    <div style="font-family:var(--font-mono);font-size:.65rem;color:var(--text-muted);">
                        <i class="far fa-clock"></i> <?php echo date('h:i A',strtotime($s['last_login'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- POND STATUS CARDS -->
    <div class="card" style="margin-bottom:1.2rem;">
        <div class="sec-head">
            <div class="sec-title"><i class="fas fa-layer-group"></i> Pond Status</div>
            <div style="display:flex;align-items:center;gap:.6rem;">
                <div class="iot-live"><span></span> LIVE</div>
                <button class="btn btn-ghost btn-sm" onclick="refreshAllPonds()">
                    <i class="fas fa-sync-alt" id="refreshAllIcon"></i> Refresh All
                </button>
            </div>
        </div>
        <div class="pond-cards-grid">
            <?php foreach($ponds_data as $key => $pond):
                $scolor = $pond['status']==='safe'?'var(--accent-green)':($pond['status']==='warning'?'var(--accent-amber)':'var(--accent-red)');
                $organic_pct = min(100, $pond['organic_level']);
                $temp_pct    = min(100, ($pond['temperature']-20)*10);
                $ph_pct      = min(100, $pond['ph']*10);
                $bar_class   = $pond['organic_level']>80?'meter-critical':($pond['organic_level']>60?'meter-warning':'meter-safe');
            ?>
            <div class="pond-card <?php echo $pond['status']; ?>" id="pcard-<?php echo $key; ?>" onclick="showPondModal('<?php echo $key; ?>')">
                <div class="pond-card-head">
                    <div class="pond-card-name">
                        <i class="fas fa-map-marker-alt" style="color:<?php echo $scolor; ?>;"></i>
                        <?php echo $pond_coordinates[$key]['name']; ?>
                    </div>
                    <span class="badge badge-<?php echo $pond['status']; ?>">
                        <span class="dot-blink"></span><?php echo strtoupper($pond['status']); ?>
                    </span>
                </div>

                <div class="pond-info-row">
                    <i class="fas fa-user"></i> <?php echo $pond['staff']; ?>
                    &nbsp;·&nbsp;
                    <i class="fas fa-map-pin"></i> <?php echo $pond['location']; ?>
                </div>

                <div class="metrics-row">
                    <div class="metric-chip">
                        <i class="fas fa-seedling ic-organic"></i>
                        <div class="metric-val ic-organic" id="ov-organic-<?php echo $key; ?>"><?php echo $pond['organic_level']; ?>%</div>
                        <div class="metric-lbl">Organic</div>
                    </div>
                    <div class="metric-chip">
                        <i class="fas fa-thermometer-half ic-temp"></i>
                        <div class="metric-val ic-temp" id="ov-temp-<?php echo $key; ?>"><?php echo $pond['temperature']; ?>°C</div>
                        <div class="metric-lbl">Temp</div>
                    </div>
                    <div class="metric-chip">
                        <i class="fas fa-flask ic-ph"></i>
                        <div class="metric-val ic-ph" id="ov-ph-<?php echo $key; ?>"><?php echo $pond['ph']; ?></div>
                        <div class="metric-lbl">pH</div>
                    </div>
                </div>

                <!-- Progress bars -->
                <div class="pond-bar-wrap">
                    <div class="pond-bar-label">
                        <span><i class="fas fa-seedling"></i> Organic</span>
                        <span id="pbar-organic-<?php echo $key; ?>"><?php echo $pond['organic_level']; ?>%</span>
                    </div>
                    <div class="pond-bar">
                        <div class="pond-bar-fill <?php echo $bar_class; ?>" id="pfill-organic-<?php echo $key; ?>" style="width:<?php echo $organic_pct; ?>%;background:<?php echo $pond['organic_level']>80?'var(--accent-red)':($pond['organic_level']>60?'var(--accent-amber)':'var(--accent-green)'); ?>;"></div>
                    </div>
                </div>
                <div class="pond-bar-wrap">
                    <div class="pond-bar-label">
                        <span><i class="fas fa-thermometer-half"></i> Temperature</span>
                        <span id="pbar-temp-<?php echo $key; ?>"><?php echo $pond['temperature']; ?>°C</span>
                    </div>
                    <div class="pond-bar">
                        <div class="pond-bar-fill" id="pfill-temp-<?php echo $key; ?>" style="width:<?php echo $temp_pct; ?>%;background:var(--accent-amber);"></div>
                    </div>
                </div>

                <div class="pond-ts">
                    <i class="far fa-clock"></i>
                    <span id="ov-ts-<?php echo $key; ?>"><?php echo date('h:i:s A',strtotime($pond['last_reading'])); ?></span>
                </div>

                <div class="pond-actions" onclick="event.stopPropagation()">
                    <button class="btn btn-ghost btn-sm" onclick="focusPond('<?php echo $key; ?>')">
                        <i class="fas fa-map-marker-alt" style="color:var(--accent-teal);"></i> Map
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="refreshPond('<?php echo $key; ?>')">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="notifyAdmin('<?php echo $key; ?>')">
                        <i class="fas fa-bell"></i> Notify
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAP -->
    <div class="card map-card">
        <div class="sec-head">
            <div class="sec-title"><i class="fas fa-map"></i> Polygon Pond Map — Manolo Fortich</div>
            <div class="map-legend">
                <div class="leg-item"><span class="leg-dot" style="background:var(--accent-green);"></span>Safe</div>
                <div class="leg-item"><span class="leg-dot" style="background:var(--accent-amber);"></span>Warning</div>
                <div class="leg-item"><span class="leg-dot" style="background:var(--accent-red);"></span>Critical</div>
            </div>
        </div>
        <div style="position:relative;">
            <div id="map"></div>
            <div class="map-scan"></div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.6rem;">
            <div class="iot-live"><span></span> REAL-TIME MAP · PH TIME</div>
            <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--text-muted);" id="mapTs"><?php echo date('h:i:s A'); ?></div>
        </div>
    </div>

    <!-- CHART + ALERTS -->
    <div class="grid-2" style="margin-bottom:1.2rem;">

        <!-- Chart -->
        <div class="card">
            <div class="sec-head">
                <div class="sec-title"><i class="fas fa-chart-area"></i> Metrics Trends</div>
                <div class="period-tabs">
                    <button class="period-btn active" onclick="switchPeriod('daily',this)">DAILY</button>
                    <button class="period-btn" onclick="switchPeriod('weekly',this)">WEEKLY</button>
                    <button class="period-btn" onclick="switchPeriod('monthly',this)">MONTHLY</button>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="metricsChart"></canvas>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;">
                <div style="display:flex;gap:1rem;font-size:.67rem;font-family:var(--font-mono);">
                    <span style="color:var(--accent-red);"><i class="fas fa-circle"></i> Organic</span>
                    <span style="color:var(--accent-amber);"><i class="fas fa-circle"></i> Temp</span>
                    <span style="color:var(--accent-green);"><i class="fas fa-circle"></i> pH</span>
                </div>
                <div style="font-family:var(--font-mono);font-size:.68rem;color:var(--text-muted);" id="chartTs"><?php echo date('h:i:s A'); ?></div>
            </div>
        </div>

        <!-- Alerts -->
        <div class="card">
            <div class="sec-head">
                <div class="sec-title"><i class="fas fa-bell"></i> Alerts & Notifications</div>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <span class="badge badge-unread" id="alertBadge"><?php echo $unread_count; ?> NEW</span>
                    <button class="btn btn-warning btn-sm" onclick="notifyAll()"><i class="fas fa-broadcast-tower"></i> Notify All</button>
                </div>
            </div>
            <div style="max-height:300px;overflow-y:auto;" id="alertsList">
                <?php foreach($notifications as $n): ?>
                <div class="alert-item <?php echo $n['type']; ?>" onclick="focusPond('<?php echo $n['pond_name']; ?>')">
                    <i class="fas fa-<?php echo $n['type']==='critical'?'exclamation-circle':'exclamation-triangle'; ?> alert-icon <?php echo $n['type']; ?>"></i>
                    <div style="flex:1;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.15rem;">
                            <div class="alert-pond">Pond <?php echo $n['pond_name']; ?></div>
                            <div class="alert-time"><?php echo date('h:i A',strtotime($n['created_at'])); ?></div>
                        </div>
                        <div class="alert-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                        <div class="alert-foot">
                            <span class="badge badge-<?php echo $n['status']; ?>"><?php echo strtoupper($n['status']); ?></span>
                            <?php if($n['status']==='unread'): ?>
                            <div style="display:flex;gap:.3rem;">
                                <button class="btn btn-success btn-sm" onclick="event.stopPropagation();ackAlert(<?php echo $n['notification_id']; ?>)">ACK</button>
                                <button class="btn btn-warning btn-sm" onclick="event.stopPropagation();notifyAdmin('<?php echo $n['pond_name']; ?>')">NOTIFY</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RECENT READINGS TABLE -->
    <div class="card" style="margin-bottom:1.2rem;">
        <div class="sec-head">
            <div class="sec-title"><i class="fas fa-history"></i> Recent Readings</div>
            <div class="iot-live"><span></span> AUTO-UPDATING</div>
        </div>
        <div class="tbl-wrap">
            <table id="readingsTable">
                <thead>
                    <tr>
                        <th>Pond</th>
                        <th>Status</th>
                        <th>Organic</th>
                        <th>Temperature</th>
                        <th>pH</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_readings as $r):
                        $rc = $r['status']==='safe'?'var(--accent-green)':($r['status']==='warning'?'var(--accent-amber)':'var(--accent-red)');
                    ?>
                    <tr>
                        <td><strong><i class="fas fa-map-marker-alt" style="color:var(--accent-teal);"></i> <?php echo $r['pond_name']; ?></strong></td>
                        <td><span class="badge badge-<?php echo $r['status']; ?>"><span class="dot-blink"></span><?php echo strtoupper($r['status']); ?></span></td>
                        <td><span style="color:var(--accent-green);font-family:var(--font-mono);font-weight:700;"><?php echo $r['organic_level']; ?>%</span></td>
                        <td><span style="color:var(--accent-amber);font-family:var(--font-mono);font-weight:700;"><?php echo $r['water_temperature']; ?>°C</span></td>
                        <td><span style="color:var(--accent-violet);font-family:var(--font-mono);font-weight:700;"><?php echo $r['ph_level']; ?></span></td>
                        <td style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);"><?php echo date('h:i:s A',strtotime($r['detected_at'])); ?></td>
                        <td>
                            <button class="btn btn-ghost btn-sm" onclick="focusPond('<?php echo $r['pond_name']; ?>')">
                                <i class="fas fa-map-marker-alt" style="color:var(--accent-teal);"></i>
                            </button>
                            <button class="btn btn-warning btn-sm" onclick="notifyAdmin('<?php echo $r['pond_name']; ?>')">
                                <i class="fas fa-bell"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="dash-footer">
        <div><i class="fas fa-map-marker-alt" style="color:var(--accent-teal);"></i> Manolo Fortich, Bukidnon · <?php echo $current_date; ?></div>
        <div>PH TIME: <span id="footerTs" style="color:var(--accent-teal);"><?php echo date('h:i:s A'); ?></span></div>
    </div>

</div><!-- /wrap -->

<!-- POND DETAIL MODAL -->
<div id="pondModal" class="modal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title" id="pondModalTitle">Pond Details</div>
            <button class="modal-close" onclick="closeModal('pondModal')">&times;</button>
        </div>
        <div id="pondModalBody"></div>
    </div>
</div>

<script>
// ============================================================
// GLOBAL STATE
// ============================================================
const PONDS      = <?php echo json_encode($ponds_data); ?>;
const POND_COORDS= <?php echo json_encode($pond_coordinates); ?>;
const CHART_INIT = {
    labels:      <?php echo json_encode($chart_data['labels']); ?>,
    organic:     <?php echo json_encode($chart_data['organic']); ?>,
    temperature: <?php echo json_encode($chart_data['temperature']); ?>,
    ph:          <?php echo json_encode($chart_data['ph']); ?>
};

let map, metricsChart;
let polygons = {}, labelMarkers = {};
let simInterval, isSimRunning = true;
let clickMarkers = [];

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initMap();
    initChart();
    startSim();
});

// ============================================================
// CLOCK
// ============================================================
function initClock() {
    setInterval(() => {
        const ph = new Date().toLocaleTimeString('en-US', {
            timeZone:'Asia/Manila', hour12:true,
            hour:'2-digit', minute:'2-digit', second:'2-digit'
        });
        document.getElementById('navClock').textContent = ph;
        const mt = document.getElementById('mapTs'); if(mt) mt.textContent = ph;
        const ct = document.getElementById('chartTs'); if(ct) ct.textContent = ph;
        const ft = document.getElementById('footerTs'); if(ft) ft.textContent = ph;
    }, 1000);
}

// ============================================================
// MAP — POLYGON RENDERING (identical to admin)
// ============================================================
function getColor(status) {
    return status==='safe'?'#39ff8a':(status==='warning'?'#ffb800':'#ff3b5c');
}

function initMap() {
    map = L.map('map', { zoomControl:true }).setView([8.3694, 124.8652], 17);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution:'&copy; OpenStreetMap &copy; CARTO', subdomains:'abcd', maxZoom:20
    }).addTo(map);

    // Map click → place temp icon
    map.on('click', e => placeClickIcon(e.latlng));

    // Draw polygons
    Object.keys(POND_COORDS).forEach(key => {
        const cfg    = POND_COORDS[key];
        const pond   = PONDS[key];
        const color  = getColor(pond.status);
        const bounds = cfg.bounds;

        const poly = L.polygon(bounds, {
            color, fillColor:color,
            fillOpacity: pond.status==='critical'?0.35:0.25,
            weight:2.5,
            dashArray: pond.status==='critical'?'8,4':null
        }).addTo(map);

        poly.bindPopup(buildPopup(key, pond, cfg, color), { maxWidth:300 });
        poly.on('click', e => { L.DomEvent.stopPropagation(e); poly.openPopup(); });
        poly.on('mouseover', () => poly.setStyle({ fillOpacity:0.5, weight:3.5 }));
        poly.on('mouseout',  () => poly.setStyle({ fillOpacity:pond.status==='critical'?0.35:0.25, weight:2.5 }));
        polygons[key] = poly;

        // Label marker
        const lIcon = L.divIcon({
            className:'',
            html:`<div style="background:rgba(6,13,23,.85);border:1.5px solid ${color};color:${color};
                  font-family:'Space Mono',monospace;font-size:11px;font-weight:700;
                  padding:4px 9px;border-radius:6px;white-space:nowrap;
                  box-shadow:0 0 12px ${color}44;">
                  ${cfg.name}</div>`,
            iconAnchor:[0,0]
        });
        const lm = L.marker(cfg.center, { icon:lIcon, interactive:false }).addTo(map);
        labelMarkers[key] = lm;
    });

    // Fit to all polygons
    const fg = L.featureGroup(Object.values(polygons));
    if (fg.getBounds().isValid()) map.fitBounds(fg.getBounds().pad(0.2));
}

function buildPopup(key, pond, cfg, color) {
    return `
    <div style="font-family:'Syne',sans-serif;color:#e8f4ff;min-width:220px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.1);">
            <span style="background:${color}22;color:${color};border:1px solid ${color}44;padding:2px 8px;border-radius:4px;font-size:11px;font-family:'Space Mono',monospace;font-weight:700;">${pond.status.toUpperCase()}</span>
            <strong>${cfg.name}</strong>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:10px;">
            <div style="text-align:center;background:rgba(0,0,0,.3);padding:6px;border-radius:6px;">
                <div style="color:#39ff8a;font-weight:700;font-family:'Space Mono',monospace;" id="mp-o-${key}">${pond.organic_level}%</div>
                <div style="font-size:.6rem;color:#8ba8c4;">Organic</div>
            </div>
            <div style="text-align:center;background:rgba(0,0,0,.3);padding:6px;border-radius:6px;">
                <div style="color:#ffb800;font-weight:700;font-family:'Space Mono',monospace;" id="mp-t-${key}">${pond.temperature}°C</div>
                <div style="font-size:.6rem;color:#8ba8c4;">Temp</div>
            </div>
            <div style="text-align:center;background:rgba(0,0,0,.3);padding:6px;border-radius:6px;">
                <div style="color:#b06cff;font-weight:700;font-family:'Space Mono',monospace;" id="mp-p-${key}">${pond.ph}</div>
                <div style="font-size:.6rem;color:#8ba8c4;">pH</div>
            </div>
        </div>
        <div style="font-size:.75rem;color:#8ba8c4;display:flex;flex-direction:column;gap:3px;">
            <span>👤 ${pond.staff}</span>
            <span>📍 ${pond.location}</span>
        </div>
    </div>`;
}

function placeClickIcon(latlng) {
    const icon = L.divIcon({
        className:'',
        html:`<div style="animation:dropIcon .3s ease;position:relative;">
            <div style="position:absolute;top:-16px;left:-16px;width:32px;height:32px;
                background:rgba(0,201,177,.25);border-radius:50%;animation:ringPulse 2s infinite;"></div>
            <div style="background:#00c9b1;width:22px;height:22px;border-radius:50%;
                border:3px solid white;box-shadow:0 0 18px #00c9b1;
                display:flex;align-items:center;justify-content:center;color:#000;font-size:10px;
                transform:translate(-11px,-11px);">
                <i class="fas fa-map-pin"></i>
            </div>
            <div style="position:absolute;top:-38px;left:-52px;background:#0b1625;color:#e8f4ff;
                padding:3px 10px;border-radius:20px;font-size:10px;white-space:nowrap;
                border:1px solid #00c9b1;pointer-events:none;font-family:'Space Mono',monospace;">
                ${new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true})}
            </div>
        </div>`,
        iconSize:[22,22], iconAnchor:[0,0]
    });
    const m = L.marker(latlng, { icon, zIndexOffset:1000 }).addTo(map);
    clickMarkers.push(m);
    setTimeout(() => { map.removeLayer(m); clickMarkers = clickMarkers.filter(x=>x!==m); }, 3000);
}

function focusPond(key) {
    if (!key || !polygons[key]) return;
    const cfg = POND_COORDS[key];
    if (!cfg) return;
    map.setView(cfg.center, 19);
    polygons[key].openPopup();
    polygons[key].setStyle({ fillOpacity:0.65 });
    setTimeout(() => {
        const pond = PONDS[key];
        polygons[key].setStyle({ fillOpacity:pond.status==='critical'?0.35:0.25 });
    }, 1200);
    toast(`Focused: ${cfg.name}`, 'info');
}

// ============================================================
// CHART
// ============================================================
function initChart() {
    const ctx = document.getElementById('metricsChart').getContext('2d');
    metricsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: CHART_INIT.labels,
            datasets: [
                { label:'Organic %',  data:CHART_INIT.organic,      borderColor:'#ff3b5c', backgroundColor:'rgba(255,59,92,.08)',  fill:true,tension:.4,borderWidth:2,pointRadius:0,pointHoverRadius:5 },
                { label:'Temp °C',    data:CHART_INIT.temperature,   borderColor:'#ffb800', backgroundColor:'rgba(255,184,0,.08)', fill:true,tension:.4,borderWidth:2,pointRadius:0,pointHoverRadius:5 },
                { label:'pH',         data:CHART_INIT.ph,            borderColor:'#39ff8a', backgroundColor:'rgba(57,255,138,.08)',fill:true,tension:.4,borderWidth:2,pointRadius:0,pointHoverRadius:5 }
            ]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            animation:{ duration:800, easing:'easeInOutQuart' },
            interaction:{ mode:'index', intersect:false },
            plugins:{
                legend:{ display:false },
                tooltip:{
                    backgroundColor:'rgba(11,22,37,.95)',
                    borderColor:'rgba(0,201,177,.2)', borderWidth:1,
                    titleColor:'#e8f4ff', bodyColor:'#8ba8c4', padding:10
                }
            },
            scales:{
                x:{ grid:{color:'rgba(255,255,255,.04)',drawBorder:false}, ticks:{color:'#4a6380',font:{family:"'Space Mono'",size:10},maxTicksLimit:8} },
                y:{ grid:{color:'rgba(255,255,255,.04)',drawBorder:false}, ticks:{color:'#4a6380',font:{family:"'Space Mono'",size:10}} }
            }
        }
    });
}

function switchPeriod(period, btn) {
    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const origText = btn.textContent;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=get_chart_data&period=${period}`
    })
    .then(r => r.json())
    .then(d => {
        metricsChart.data.labels = d.labels;
        metricsChart.data.datasets[0].data = d.organic;
        metricsChart.data.datasets[1].data = d.temperature;
        metricsChart.data.datasets[2].data = d.ph;
        metricsChart.update();
        btn.textContent = origText;
        toast(`Chart: ${period} view`, 'info');
    });
}

// ============================================================
// SIMULATION
// ============================================================
function startSim() {
    isSimRunning = true;
    document.getElementById('simDot').style.color   = 'var(--accent-teal)';
    document.getElementById('simLabel').textContent  = 'SIM RUNNING';
    document.getElementById('simToggle').classList.remove('paused');
    simInterval = setInterval(() => {
        simulatePonds();
        maybeAddAlert();
    }, 5000);
}

function stopSim() {
    isSimRunning = false;
    clearInterval(simInterval);
    document.getElementById('simDot').style.color   = 'var(--accent-red)';
    document.getElementById('simLabel').textContent  = 'SIM PAUSED';
    document.getElementById('simToggle').classList.add('paused');
}

function toggleSim() { isSimRunning ? stopSim() : startSim(); }

function simulatePonds() {
    Object.keys(PONDS).forEach(key => {
        const pond   = PONDS[key];
        const newOrg = parseFloat((pond.organic_level + (Math.random()-.5)*5).toFixed(1));
        const newTmp = parseFloat((pond.temperature   + (Math.random()-.5)*0.9).toFixed(1));
        const newPh  = parseFloat((pond.ph            + (Math.random()-.5)*0.12).toFixed(1));

        const organic = Math.max(10,Math.min(100,newOrg));
        const temp    = Math.max(20,Math.min(38,newTmp));
        const ph      = Math.max(5,Math.min(10,newPh));

        const status = (organic>80||temp>32||ph>8.5)?'critical':((organic>60||temp>30||ph>7.8)?'warning':'safe');
        const color  = getColor(status);
        const ts     = new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true});

        PONDS[key].organic_level = organic;
        PONDS[key].temperature   = temp;
        PONDS[key].ph            = ph;
        PONDS[key].status        = status;

        // Update overview card
        const ovO  = document.getElementById(`ov-organic-${key}`); if(ovO) ovO.textContent = organic+'%';
        const ovT  = document.getElementById(`ov-temp-${key}`);    if(ovT) ovT.textContent = temp+'°C';
        const ovP  = document.getElementById(`ov-ph-${key}`);      if(ovP) ovP.textContent = ph;
        const ovTs = document.getElementById(`ov-ts-${key}`);      if(ovTs) ovTs.textContent = ts;

        const pbO = document.getElementById(`pbar-organic-${key}`); if(pbO) pbO.textContent = organic+'%';
        const pbT = document.getElementById(`pbar-temp-${key}`);    if(pbT) pbT.textContent = temp+'°C';
        const fO  = document.getElementById(`pfill-organic-${key}`);
        const fT  = document.getElementById(`pfill-temp-${key}`);
        if(fO){ fO.style.width=Math.min(100,organic)+'%'; fO.style.background=organic>80?'var(--accent-red)':(organic>60?'var(--accent-amber)':'var(--accent-green)'); }
        if(fT){ fT.style.width=Math.min(100,(temp-20)*10)+'%'; }

        // Update polygon color
        if(polygons[key]) polygons[key].setStyle({ color, fillColor:color, dashArray:status==='critical'?'8,4':null });

        // Update pond card class
        const card = document.getElementById(`pcard-${key}`);
        if(card){ card.className = `pond-card ${status}`; }
    });
}

function refreshPond(key) {
    fetch('', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=get_iot_reading&pond_key=${key}`
    })
    .then(r => r.json())
    .then(d => {
        if(!d.success) return;
        const ts = new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true});
        const ovO  = document.getElementById(`ov-organic-${key}`); if(ovO) ovO.textContent = d.organic+'%';
        const ovT  = document.getElementById(`ov-temp-${key}`);    if(ovT) ovT.textContent = d.temp+'°C';
        const ovP  = document.getElementById(`ov-ph-${key}`);      if(ovP) ovP.textContent = d.ph;
        const ovTs = document.getElementById(`ov-ts-${key}`);      if(ovTs) ovTs.textContent = ts;
        if(polygons[key]) polygons[key].setStyle({ color:getColor(d.status), fillColor:getColor(d.status) });
        toast(`Pond ${key}: refreshed`, 'success');
    });
}

function refreshAllPonds() {
    const icon = document.getElementById('refreshAllIcon');
    if(icon) icon.style.animation='spin 1s linear infinite';
    Object.keys(PONDS).forEach(key => refreshPond(key));
    setTimeout(() => { if(icon) icon.style.animation=''; }, 1200);
    toast('All ponds refreshed', 'success');
}

// ============================================================
// ALERTS
// ============================================================
function maybeAddAlert() {
    if (Math.random() > 0.4) return; // 60% chance each cycle
    const keys     = Object.keys(PONDS);
    const key      = keys[Math.floor(Math.random()*keys.length)];
    const pond     = PONDS[key];
    const types    = ['critical','warning','info'];
    const type     = types[Math.floor(Math.random()*types.length)];
    const msgs     = {
        critical:`HIGH ALERT: Pond ${key} — Organic ${pond.organic_level}%, Temp ${pond.temperature}°C`,
        warning: `WARNING: Pond ${key} — Organic ${pond.organic_level}% approaching threshold`,
        info:    `INFO: Pond ${key} — Routine check completed`
    };
    const icons    = { critical:'exclamation-circle', warning:'exclamation-triangle', info:'info-circle' };

    const el = document.createElement('div');
    el.className = `alert-item ${type}`;
    el.innerHTML = `
        <i class="fas fa-${icons[type]} alert-icon ${type}"></i>
        <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.12rem;">
                <div class="alert-pond">Pond ${key}</div>
                <div class="alert-time">Just now</div>
            </div>
            <div class="alert-msg">${msgs[type]}</div>
            <div class="alert-foot">
                <span class="badge badge-unread"><span class="dot-blink"></span>NEW</span>
                <button class="btn btn-warning btn-sm" onclick="notifyAdmin('${key}')"><i class="fas fa-bell"></i> Notify</button>
            </div>
        </div>`;

    const list = document.getElementById('alertsList');
    list.insertBefore(el, list.firstChild);
    if (list.children.length > 6) list.removeChild(list.lastChild);

    const badge = document.getElementById('alertBadge');
    if(badge){ const n = parseInt(badge.textContent)||0; badge.textContent = (n+1)+' NEW'; }

    toast(msgs[type], type==='info'?'info':(type==='warning'?'warning':'critical'));
}

function ackAlert(id) {
    fetch('', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=acknowledge_alert&alert_id=${id}`
    })
    .then(r=>r.json())
    .then(d => toast(d.message,'success'));
}

function notifyAdmin(pondName) {
    fetch('', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=notify_admin&pond=${pondName}`
    })
    .then(r=>r.json())
    .then(d => toast(d.message,'success'));
}

function notifyAll() {
    Object.keys(PONDS).forEach(key => notifyAdmin(key));
    toast('All admins notified for all ponds','success');
}

// ============================================================
// POND MODAL
// ============================================================
function showPondModal(key) {
    const pond = PONDS[key];
    const cfg  = POND_COORDS[key];
    if(!pond||!cfg) return;
    const color   = getColor(pond.status);
    const organic = pond.organic_level;
    const mc      = organic>80?'meter-critical':(organic>60?'meter-warning':'meter-safe');

    document.getElementById('pondModalTitle').innerHTML =
        `<i class="fas fa-map-marker-alt" style="color:${color};"></i> ${cfg.name}`;

    document.getElementById('pondModalBody').innerHTML = `
        <div style="text-align:center;margin-bottom:1rem;">
            <span class="badge badge-${pond.status}" style="font-size:.8rem;padding:.4rem 1rem;">
                <span class="dot-blink"></span> ${pond.status.toUpperCase()}
            </span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-bottom:1rem;">
            <div style="text-align:center;padding:.9rem .5rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--border);">
                <i class="fas fa-seedling ic-organic" style="font-size:1.4rem;display:block;margin-bottom:.3rem;"></i>
                <div class="metric-val ic-organic" style="font-size:1.4rem;">${organic}%</div>
                <div class="metric-lbl">Organic</div>
                <div class="meter" style="margin-top:.5rem;"><div class="meter-fill ${mc}" style="width:${organic}%;"></div></div>
            </div>
            <div style="text-align:center;padding:.9rem .5rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--border);">
                <i class="fas fa-thermometer-half ic-temp" style="font-size:1.4rem;display:block;margin-bottom:.3rem;"></i>
                <div class="metric-val ic-temp" style="font-size:1.4rem;">${pond.temperature}°C</div>
                <div class="metric-lbl">Temperature</div>
                <div class="meter" style="margin-top:.5rem;"><div class="meter-fill meter-warning" style="width:${Math.min(100,(pond.temperature-20)*10)}%;"></div></div>
            </div>
            <div style="text-align:center;padding:.9rem .5rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--border);">
                <i class="fas fa-flask ic-ph" style="font-size:1.4rem;display:block;margin-bottom:.3rem;"></i>
                <div class="metric-val ic-ph" style="font-size:1.4rem;">${pond.ph}</div>
                <div class="metric-lbl">pH Level</div>
                <div class="meter" style="margin-top:.5rem;"><div class="meter-fill meter-safe" style="width:${Math.min(100,pond.ph*10)}%;"></div></div>
            </div>
        </div>
        <div class="pond-detail-grid">
            <div class="detail-row"><div class="detail-lbl">Assigned Staff</div><div class="detail-val"><i class="fas fa-user" style="color:var(--accent-teal);"></i> ${pond.staff}</div></div>
            <div class="detail-row"><div class="detail-lbl">Location</div><div class="detail-val"><i class="fas fa-map-pin" style="color:var(--accent-red);"></i> ${pond.location}</div></div>
            <div class="detail-row"><div class="detail-lbl">Coordinates</div><div class="detail-val" style="font-family:var(--font-mono);font-size:.76rem;">${cfg.center[0].toFixed(4)}, ${cfg.center[1].toFixed(4)}</div></div>
            <div class="detail-row"><div class="detail-lbl">Last Reading</div><div class="detail-val" style="font-family:var(--font-mono);font-size:.76rem;">${new Date().toLocaleTimeString('en-US',{hour12:true,timeZone:'Asia/Manila'})}</div></div>
        </div>
        <div style="display:flex;gap:.5rem;margin-top:1rem;">
            <button class="btn btn-primary btn-sm" style="flex:1;" onclick="closeModal('pondModal');focusPond('${key}');">
                <i class="fas fa-map-marker-alt"></i> Focus on Map
            </button>
            <button class="btn btn-warning btn-sm" style="flex:1;" onclick="notifyAdmin('${key}');closeModal('pondModal');">
                <i class="fas fa-bell"></i> Notify Admin
            </button>
            <button class="btn btn-ghost btn-sm" style="flex:1;" onclick="refreshPond('${key}');closeModal('pondModal');">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>`;
    openModal('pondModal');
}

// ============================================================
// MODAL HELPERS
// ============================================================
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) closeModal(m.id); }));
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal.open').forEach(m => m.classList.remove('open')); });

// ============================================================
// TOAST
// ============================================================
function toast(msg, type='info') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    const icons = { success:'check-circle', warning:'exclamation-triangle', critical:'times-circle', info:'info-circle' };
    t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(40px)'; t.style.transition='.3s'; setTimeout(()=>t.remove(),300); }, 3500);
}

// ============================================================
// RESIZE
// ============================================================
window.addEventListener('resize', () => {
    if(map) setTimeout(()=>map.invalidateSize(),100);
    if(metricsChart) metricsChart.resize();
});
</script>
</body>
</html>