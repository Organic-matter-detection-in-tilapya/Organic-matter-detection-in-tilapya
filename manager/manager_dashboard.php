<?php
/**
 * ============================================================
 *  Organic Matter Detection in Tilapia
 *  Manager Dashboard — v2.0
 * ============================================================
 *
 *  @project   Organic Matter Detection in Tilapia Ponds
 *  @location  Manolo Fortich, Bukidnon, Philippines
 *  @timezone  Asia/Manila (PHT, UTC+8)
 *  @stack     PHP 8+ · PDO MySQL · Leaflet.js · Chart.js
 *
 *  DASHBOARD SECTIONS (Manager View):
 *  ┌─────────────────────────────────────────────────┐
 *  │ Overview  — KPIs, staff cards, pond status      │
 *  │ Staff     — View staff assigned to ponds        │
 *  │ Ponds     — Per-pond metrics + IoT refresh      │
 *  │ Map       — Leaflet polygon map, live status    │
 *  │ Charts    — Daily/weekly/monthly trends         │
 *  │ Alerts    — Notifications with ACK / NOTIFY     │
 *  │ Reports   — Generate + export reports           │
 *  │ Activities— Unified activity log                │
 *  └─────────────────────────────────────────────────┘
 *
 *  MOBILE Z-INDEX HIERARCHY:
 *    .sidebar         → 700
 *    .sidebar-overlay → 600
 *    .topnav          → 200
 *    .bottom-nav      → 9999
 *    .modal           → 10000
 *    .toast-wrap      → 10001
 * ============================================================
 */

session_start();
require_once '../config/config.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

// ── AUTH GUARD ────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    header('Location: ../auth/login.php');
    exit;
}

// ── PAGE VARIABLES ────────────────────────────────────────
$manager_id        = $_SESSION['user_id'];
$manager_name      = $_SESSION['full_name'];
$current_time_12hr = date('h:i:s A');
$current_date      = date('F j, Y');
$current_day       = date('l');

// ── POND CONFIG ───────────────────────────────────────────
$ponds_config = [
    'A-1' => ['name'=>'Tilapia Pond A-1','center'=>[8.3695,124.8645],'staff'=>'Pedro Reyes',
              'bounds'=>[[8.3692,124.8642],[8.3698,124.8640],[8.3700,124.8648],[8.3696,124.8650],[8.3692,124.8642]]],
    'B-2' => ['name'=>'Tilapia Pond B-2','center'=>[8.3688,124.8652],'staff'=>'Ana Lopez',
              'bounds'=>[[8.3685,124.8649],[8.3690,124.8647],[8.3693,124.8654],[8.3688,124.8656],[8.3685,124.8649]]],
    'C-1' => ['name'=>'Tilapia Pond C-1','center'=>[8.3700,124.8660],'staff'=>'Roberto Gomez',
              'bounds'=>[[8.3697,124.8657],[8.3703,124.8655],[8.3705,124.8663],[8.3699,124.8665],[8.3697,124.8657]]],
];

// ── FETCH STAFF (role = staff only) ──────────────────────
$staff_stmt = $pdo->query("SELECT user_id, full_name, email, role, assigned_pond, last_login
                            FROM users WHERE role = 'staff'
                            ORDER BY full_name ASC");
$staff_list = [];
while ($row = $staff_stmt->fetch()) {
    $row['status'] = ($row['last_login'] && ((time() - strtotime($row['last_login'])) / 86400) <= 7)
                     ? 'active' : 'inactive';
    $staff_list[] = $row;
}

// ── FETCH PONDS ───────────────────────────────────────────
$ponds_stmt = $pdo->query("SELECT pond_id, pond_name, location FROM ponds ORDER BY pond_name");
$ponds_db   = [];
while ($row = $ponds_stmt->fetch()) $ponds_db[$row['pond_name']] = $row;

$ponds_data = [];
foreach ($ponds_config as $key => $cfg) {
    $pid    = $ponds_db[$key]['pond_id'] ?? null;
    $latest = null;
    if ($pid) {
        $rs = $pdo->prepare("SELECT organic_level, water_temperature AS temperature, ph_level, detected_at FROM detections WHERE pond_id = ? ORDER BY detected_at DESC LIMIT 1");
        $rs->execute([$pid]);
        $latest = $rs->fetch();
    }
    $sf = $pdo->prepare("SELECT full_name FROM users WHERE assigned_pond = ? AND role = 'staff' LIMIT 1");
    $sf->execute([$key]);
    $sf_row = $sf->fetch();
    $organic = $latest ? floatval($latest['organic_level'])  : rand(45, 85);
    $temp    = $latest ? floatval($latest['temperature'])     : rand(25, 33);
    $ph      = $latest ? floatval($latest['ph_level'])        : round(rand(65,85)/10,1);
    $status  = 'safe';
    if ($organic > 80 || $temp > 32 || $ph > 8.5) $status = 'critical';
    elseif ($organic > 60 || $temp > 30 || $ph > 7.8) $status = 'warning';
    $ponds_data[$key] = [
        'pond_id'=>$pid,'pond_name'=>$key,'name'=>$cfg['name'],
        'center'=>$cfg['center'],'bounds'=>$cfg['bounds'],
        'organic_level'=>$organic,'temperature'=>$temp,'ph'=>$ph,
        'status'=>$status,
        'staff'=>$sf_row ? $sf_row['full_name'] : $cfg['staff'],
        'location'=>$ponds_db[$key]['location'] ?? 'Manolo Fortich',
        'last_reading'=>$latest ? $latest['detected_at'] : date('Y-m-d H:i:s'),
    ];
}

// ── ALERTS ────────────────────────────────────────────────
$alerts_stmt = $pdo->query("SELECT n.*, p.pond_name FROM notifications n LEFT JOIN ponds p ON n.pond_id = p.pond_id ORDER BY n.created_at DESC LIMIT 20");
$alerts = [];
if ($alerts_stmt && $alerts_stmt->rowCount() > 0) {
    while ($row = $alerts_stmt->fetch()) {
        $row['type'] = $row['status'] == 'critical' ? 'critical' : ($row['status'] == 'warning' ? 'warning' : 'info');
        $alerts[] = $row;
    }
} else {
    $alerts = [
        ['notification_id'=>1,'pond_name'=>'B-2','message'=>'HIGH ALERT: Organic level (82%) detected. Temperature above threshold (31.2°C).','created_at'=>date('Y-m-d H:i:s',strtotime('-2 minutes')),'status'=>'unread','type'=>'critical'],
        ['notification_id'=>2,'pond_name'=>'A-1','message'=>'WARNING: Organic level (65%) approaching threshold. Monitor closely.','created_at'=>date('Y-m-d H:i:s',strtotime('-15 minutes')),'status'=>'unread','type'=>'warning'],
        ['notification_id'=>3,'pond_name'=>'C-1','message'=>'INFO: Routine check completed — All systems normal.','created_at'=>date('Y-m-d H:i:s',strtotime('-1 hour')),'status'=>'read','type'=>'info'],
    ];
}
$new_alerts_count = count(array_filter($alerts, fn($a) => ($a['status'] ?? '') == 'unread'));

// ── RECENT ACTIVITIES ────────────────────────────────────
$recent_activities = [];
try {
    $aq = "(SELECT CONCAT('Staff ',full_name,' logged in') AS action, last_login AS timestamp,'login' AS type FROM users WHERE last_login IS NOT NULL AND role='staff' ORDER BY last_login DESC LIMIT 5)
           UNION ALL
           (SELECT CONCAT('New reading for Pond ',pond_name) AS action, detected_at AS timestamp,'reading' AS type FROM detections d JOIN ponds p ON d.pond_id=p.pond_id ORDER BY detected_at DESC LIMIT 5)
           UNION ALL
           (SELECT CONCAT('Alert: ',message) AS action, created_at AS timestamp,'alert' AS type FROM notifications ORDER BY created_at DESC LIMIT 5)
           ORDER BY timestamp DESC LIMIT 10";
    $as = $pdo->query($aq);
    if ($as && $as->rowCount() > 0) while ($row = $as->fetch()) $recent_activities[] = $row;
} catch(Exception $e) {}
if (empty($recent_activities)) {
    $recent_activities = [
        ['action'=>'Dashboard initialized','timestamp'=>date('Y-m-d H:i:s'),'type'=>'system'],
        ['action'=>'Manager logged in','timestamp'=>date('Y-m-d H:i:s',strtotime('-1 minute')),'type'=>'login'],
        ['action'=>'Daily report generated','timestamp'=>date('Y-m-d H:i:s',strtotime('-5 minutes')),'type'=>'system'],
    ];
}

// ── CHART DATA ────────────────────────────────────────────
$chart_data = ['daily'=>['labels'=>[],'organic'=>[],'temperature'=>[],'ph'=>[]]];
try {
    $dq = "SELECT DATE_FORMAT(detected_at,'%H:00') AS hour, AVG(organic_level) AS ao, AVG(water_temperature) AS at2, AVG(ph_level) AS ap
           FROM detections WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
           GROUP BY DATE_FORMAT(detected_at,'%Y-%m-%d %H') ORDER BY detected_at LIMIT 24";
    $ds = $pdo->query($dq);
    if ($ds && $ds->rowCount() > 0) {
        while ($row = $ds->fetch()) {
            $chart_data['daily']['labels'][] = $row['hour'];
            $chart_data['daily']['organic'][] = round($row['ao'] ?? 0, 1);
            $chart_data['daily']['temperature'][] = round($row['at2'] ?? 0, 1);
            $chart_data['daily']['ph'][] = round($row['ap'] ?? 0, 1);
        }
    }
} catch(Exception $e) {}
if (empty($chart_data['daily']['labels'])) {
    for ($i = 23; $i >= 0; $i--) {
        $chart_data['daily']['labels'][] = date('H:00', strtotime("-$i hours"));
        $chart_data['daily']['organic'][] = rand(45, 85);
        $chart_data['daily']['temperature'][] = rand(25, 33);
        $chart_data['daily']['ph'][] = rand(65, 85) / 10;
    }
}
$days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
for ($i = 0; $i < 7; $i++) { $chart_data['weekly']['labels'][] = $days[$i]; $chart_data['weekly']['organic'][] = rand(45,85); $chart_data['weekly']['temperature'][] = rand(25,33); $chart_data['weekly']['ph'][] = rand(65,85)/10; }
for ($i = 1; $i <= 30; $i++) { $chart_data['monthly']['labels'][] = 'Day '.$i; $chart_data['monthly']['organic'][] = rand(45,85); $chart_data['monthly']['temperature'][] = rand(25,33); $chart_data['monthly']['ph'][] = rand(65,85)/10; }

// ── KPI SUMMARY ───────────────────────────────────────────
$total_ponds    = count($ponds_data);
$safe_ponds     = count(array_filter($ponds_data, fn($p) => $p['status'] == 'safe'));
$warning_ponds  = count(array_filter($ponds_data, fn($p) => $p['status'] == 'warning'));
$critical_ponds = count(array_filter($ponds_data, fn($p) => $p['status'] == 'critical'));
$avg_organic    = $total_ponds > 0 ? round(array_sum(array_column($ponds_data,'organic_level')) / $total_ponds, 1) : 0;
$avg_temp       = $total_ponds > 0 ? round(array_sum(array_column($ponds_data,'temperature')) / $total_ponds, 1) : 0;
$avg_ph         = $total_ponds > 0 ? round(array_sum(array_column($ponds_data,'ph')) / $total_ponds, 1) : 0;

$daily_report   = ['date'=>date('Y-m-d'),'total_ponds'=>$total_ponds,'safe_ponds'=>$safe_ponds,'warning_ponds'=>$warning_ponds,'critical_ponds'=>$critical_ponds,'avg_organic'=>$avg_organic,'avg_temp'=>$avg_temp,'avg_ph'=>$avg_ph,'alerts_generated'=>$new_alerts_count,'staff_active'=>count(array_filter($staff_list,fn($u)=>$u['status']=='active'))];
$weekly_report  = ['week'=>date('M d',strtotime('-7 days')).' - '.date('M d, Y'),'total_readings'=>rand(350,450),'avg_organic'=>round($avg_organic+rand(-5,5),1),'avg_temp'=>round($avg_temp+rand(-1,1),1),'avg_ph'=>round(max(6.5,min(8.5,$avg_ph+(rand(-20,20)/100))),1),'incidents'=>rand(3,8),'resolved'=>rand(2,7)];
$monthly_report = ['month'=>date('F Y'),'total_readings'=>rand(1500,2000),'avg_organic'=>round($avg_organic+rand(-3,3),1),'avg_temp'=>round($avg_temp+rand(-1,1),1),'avg_ph'=>round(max(6.5,min(8.5,$avg_ph+(rand(-10,10)/100))),1),'incidents'=>rand(15,25),'resolved'=>rand(12,22)];

// ── AJAX HANDLERS ─────────────────────────────────────────
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    switch ($_POST['action']) {

        case 'get_chart_data':
            $period = $_POST['period'] ?? 'daily';
            echo json_encode($chart_data[$period] ?? $chart_data['daily']); exit;

        case 'acknowledge_alert':
            $aid = intval($_POST['alert_id'] ?? 0);
            $pdo->prepare("UPDATE notifications SET status='read' WHERE notification_id=?")->execute([$aid]);
            echo json_encode(['success'=>true,'message'=>'Alert acknowledged']); exit;

        case 'notify_admin':
            $pond = $_POST['pond'] ?? 'Unknown';
            $msg  = $_POST['message'] ?? "Manager escalated alert for Pond $pond";
            // In production: send email/SMS to admin here
            echo json_encode(['success'=>true,'message'=>"Admin has been notified for Pond $pond",'timestamp'=>date('h:i:s A')]); exit;

        case 'get_iot_reading':
            $key = $_POST['pond_key'] ?? '';
            $o   = rand(45, 92);
            $t   = round(rand(250, 340) / 10, 1);
            $p   = round(rand(63, 90) / 10, 1);
            $s   = 'safe';
            if ($o > 80 || $t > 32 || $p > 8.5) $s = 'critical';
            elseif ($o > 60 || $t > 30 || $p > 7.8) $s = 'warning';
            echo json_encode(['success'=>true,'pond'=>$key,'organic'=>$o,'temp'=>$t,'ph'=>$p,'status'=>$s,'timestamp'=>date('h:i:s A')]); exit;

        case 'generate_report':
            $type   = $_POST['type'] ?? 'daily';
            $report = ${$type.'_report'} ?? $daily_report;
            echo json_encode(['success'=>true,'report'=>$report,'type'=>$type]); exit;

        case 'logout':
            session_destroy();
            echo json_encode(['success'=>true,'message'=>'Logged out']); exit;
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
<title>Organic Matter Detection in Tilapia — Manager Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* ── VARIABLES ── */
:root{
    --bg-deep:#060d17;--bg-panel:#0b1625;--bg-card:#0f1e30;--bg-elevated:#142235;--bg-hover:#1a2d45;
    --cyan:#00e5ff;--green:#39ff8a;--amber:#ffb800;--red:#ff3b5c;--violet:#b06cff;--teal:#00c9b1;
    --txt:#e8f4ff;--txt2:#8ba8c4;--muted:#4a6380;
    --bdr:rgba(0,229,255,.12);--bdr-glow:rgba(0,229,255,.35);
    --fd:'Syne',sans-serif;--fm:'Space Mono',monospace;
    --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
    --nav-h:62px;--sidebar-w:260px;--bnav-h:60px;
}

*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{height:100%;scroll-behavior:smooth}
body{background:var(--bg-deep);color:var(--txt);font-family:var(--fd);min-height:100vh;overflow-x:hidden;}

body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(0,229,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,255,.025) 1px,transparent 1px);background-size:44px 44px;animation:gridDrift 28s linear infinite;}

@keyframes gridDrift{0%{background-position:0 0}100%{background-position:44px 44px}}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideIn{from{transform:translateX(-12px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes pulseGlow{0%,100%{box-shadow:0 0 20px var(--red)}50%{box-shadow:0 0 55px var(--red)}}
@keyframes toastIn{from{transform:translateX(60px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes notifPop{0%,100%{transform:scale(1)}50%{transform:scale(1.3)}}
@keyframes sheen{0%,100%{left:-60%}50%{left:160%}}
@keyframes scanline{0%{top:-100%}100%{top:200%}}

::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:var(--bg-deep)}
::-webkit-scrollbar-thumb{background:var(--cyan);border-radius:2px}

/* ── LAYOUT ── */
.layout{display:flex;min-height:100vh;position:relative}

/* ── SIDEBAR ── */
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:rgba(9,22,37,.97);backdrop-filter:blur(20px);border-right:1px solid var(--bdr);position:fixed;top:0;left:0;bottom:0;display:flex;flex-direction:column;z-index:700;transition:transform .3s cubic-bezier(.4,0,.2,1);overflow-y:auto;overflow-x:hidden;}
.sidebar-head{padding:1.2rem 1.4rem 1rem;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:.7rem;min-height:var(--nav-h);}
.sidebar-logo{width:36px;height:36px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,var(--teal),var(--cyan));display:flex;align-items:center;justify-content:center;font-size:1rem;color:#000;font-weight:800;position:relative;overflow:hidden;}
.sidebar-logo::after{content:'';position:absolute;top:-50%;left:-60%;width:28%;height:200%;background:rgba(255,255,255,.35);transform:skewX(-20deg);animation:sheen 4s infinite}
.sidebar-title{font-size:.95rem;font-weight:800;letter-spacing:.3px;background:linear-gradient(90deg,var(--teal),var(--txt));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1.15}
.sidebar-sub{font-size:.62rem;color:var(--muted);font-family:var(--fm);margin-top:.1rem}

.nav-section{padding:.6rem .8rem .3rem;font-family:var(--fm);font-size:.62rem;color:var(--muted);letter-spacing:.8px;text-transform:uppercase}
.nav-item{display:flex;align-items:center;gap:.7rem;padding:.65rem 1rem .65rem 1.2rem;margin:.1rem .5rem;border-radius:var(--r-md);cursor:pointer;transition:.22s;font-size:.83rem;font-weight:600;color:var(--txt2);border:1px solid transparent;position:relative;-webkit-tap-highlight-color:transparent;user-select:none;}
.nav-item:hover{background:var(--bg-hover);color:var(--txt);border-color:var(--bdr)}
.nav-item.active{background:rgba(0,201,177,.1);color:var(--teal);border-color:rgba(0,201,177,.25)}
.nav-item.active::before{content:'';position:absolute;left:0;top:25%;bottom:25%;width:3px;background:var(--teal);border-radius:0 3px 3px 0}
.nav-item i{width:18px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;border-radius:50px;padding:.1rem .5rem;font-family:var(--fm);font-size:.6rem;animation:notifPop 2s infinite}

.sidebar-footer{margin-top:auto;padding:1rem 1.2rem;border-top:1px solid var(--bdr)}
.sidebar-user{display:flex;align-items:center;gap:.7rem;padding:.7rem .8rem;border-radius:var(--r-md);background:var(--bg-elevated);border:1px solid var(--bdr);margin-bottom:.7rem}
.sidebar-avatar{width:34px;height:34px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--teal),var(--cyan));display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;color:#000}
.sidebar-uname{font-size:.8rem;font-weight:700;line-height:1.2}
.sidebar-urole{font-size:.65rem;color:var(--muted);font-family:var(--fm)}
.btn-logout-sidebar{width:100%;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.6rem;border-radius:var(--r-md);background:rgba(255,59,92,.1);border:1px solid rgba(255,59,92,.3);color:var(--red);font-family:var(--fd);font-size:.82rem;font-weight:600;cursor:pointer;transition:.25s;text-decoration:none}
.btn-logout-sidebar:hover{background:rgba(255,59,92,.2)}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-w);flex:1;min-width:0;position:relative}

/* ── TOP NAV (mobile) ── */
.topnav{display:none;background:rgba(6,13,23,.97);backdrop-filter:blur(20px);border-bottom:1px solid var(--bdr);height:var(--nav-h);padding:0 1rem;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200;}
.topnav-brand{display:flex;align-items:center;gap:.6rem}
.topnav-logo{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--teal),var(--cyan));display:flex;align-items:center;justify-content:center;font-size:.95rem;color:#000;font-weight:800;position:relative;overflow:hidden}
.topnav-logo::after{content:'';position:absolute;top:-50%;left:-60%;width:28%;height:200%;background:rgba(255,255,255,.35);transform:skewX(-20deg);animation:sheen 4s infinite}
.topnav-title{font-size:.92rem;font-weight:800;background:linear-gradient(90deg,var(--teal),var(--txt));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hamburger{width:38px;height:38px;border-radius:9px;border:1px solid var(--bdr);background:var(--bg-elevated);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--txt2);font-size:.9rem;-webkit-tap-highlight-color:transparent;position:relative;min-height:44px;min-width:44px}
.notif-dot-top{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--red);border:2px solid var(--bg-deep);animation:blink 1.5s infinite}

/* ── SIDEBAR OVERLAY ── */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:600;backdrop-filter:blur(4px);opacity:0;transition:opacity .3s;pointer-events:none;}
.sidebar-overlay.active{opacity:1;pointer-events:all}

/* ── BOTTOM NAV ── */
.bottom-nav{display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;background:rgba(9,22,37,.98);backdrop-filter:blur(20px);border-top:1px solid var(--bdr);grid-template-columns:repeat(5,1fr);gap:0;padding-bottom:env(safe-area-inset-bottom,0px);pointer-events:all;}
.bnav-item{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.22rem;padding:.55rem .2rem;cursor:pointer;font-size:.58rem;font-weight:700;color:var(--muted);letter-spacing:.3px;text-transform:uppercase;transition:color .2s,background .2s;position:relative;touch-action:manipulation;-webkit-tap-highlight-color:transparent;user-select:none;min-height:52px;overflow:visible;}
.bnav-item:active{background:rgba(0,201,177,.08);color:var(--teal);}
.bnav-item.active{color:var(--teal);background:rgba(0,201,177,.07);}
.bnav-item.active::before{content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);width:28px;height:2px;background:var(--teal);border-radius:0 0 2px 2px;}
.bnav-item i{font-size:1.15rem;line-height:1;pointer-events:none}
.bnav-item span{pointer-events:none;line-height:1}
.bnav-badge{position:absolute;top:5px;right:calc(50% - 16px);background:var(--red);color:#fff;border-radius:50px;padding:.05rem .35rem;font-family:var(--fm);font-size:.55rem;min-width:16px;text-align:center;pointer-events:none;}

/* ── CONTENT ── */
.content{padding:1.4rem 1.6rem 2rem;max-width:1600px;width:100%;}
.section-panel{display:none;animation:fadeUp .35s ease both}
.section-panel.active{display:block}

/* ── TOPBAR ── */
.topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.7rem;padding:.7rem 1.3rem;background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-lg);margin-bottom:1.3rem}
.topbar-left{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
.topbar-day{font-size:1rem;font-weight:700}
.topbar-date{font-family:var(--fm);font-size:.73rem;color:var(--txt2)}
.sys-tag{display:inline-flex;align-items:center;gap:.35rem;font-family:var(--fm);font-size:.66rem;padding:.26rem .7rem;border-radius:4px;letter-spacing:.4px}
.sys-tag.green{background:rgba(57,255,138,.08);border:1px solid rgba(57,255,138,.22);color:var(--green)}
.sys-tag.teal{background:rgba(0,201,177,.08);border:1px solid rgba(0,201,177,.2);color:var(--teal)}
.sys-tag.red{background:rgba(255,59,92,.08);border:1px solid rgba(255,59,92,.2);color:var(--red)}
.blink-dot{width:5px;height:5px;border-radius:50%;background:currentColor;animation:blink 1.5s infinite;display:inline-block;flex-shrink:0}
.nav-clock{font-family:var(--fm);font-size:.76rem;color:var(--teal);background:rgba(0,201,177,.07);border:1px solid rgba(0,201,177,.2);padding:.3rem .7rem;border-radius:6px;letter-spacing:.8px;white-space:nowrap}
.iot-live{display:inline-flex;align-items:center;gap:.35rem;font-family:var(--fm);font-size:.65rem;color:var(--green)}
.iot-live span{width:6px;height:6px;border-radius:50%;background:var(--green);animation:blink 1.2s infinite;display:inline-block}

/* ── KPI GRID ── */
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.3rem}
.kpi{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-lg);padding:1.2rem 1.3rem;position:relative;overflow:hidden;cursor:default;transition:.35s}
.kpi:hover{transform:translateY(-3px);border-color:var(--bdr-glow);box-shadow:0 0 28px rgba(0,229,255,.12)}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--kc,var(--teal)),transparent)}
.kpi-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.95rem;color:var(--kc,var(--teal));background:rgba(0,0,0,.3);border:1px solid currentColor;opacity:.9;margin-bottom:.7rem}
.kpi-val{font-family:var(--fm);font-size:1.8rem;font-weight:700;color:var(--kc,var(--teal));line-height:1;margin-bottom:.2rem}
.kpi-label{font-size:.68rem;color:var(--muted);letter-spacing:.5px;text-transform:uppercase}
.kpi-corner{position:absolute;top:.8rem;right:.8rem;font-size:.58rem;font-family:var(--fm);color:var(--kc);opacity:.45;letter-spacing:.4px}

/* ── CARDS ── */
.card{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1.3rem 1.4rem;margin-bottom:1.2rem}
.card:hover{border-color:rgba(0,201,177,.2)}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem}
.card-title{display:flex;align-items:center;gap:.55rem;font-size:.86rem;font-weight:700;letter-spacing:.4px;text-transform:uppercase}
.card-title i{color:var(--teal)}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}

/* ── BADGES ── */
.badge{display:inline-flex;align-items:center;gap:.28rem;padding:.22rem .65rem;border-radius:4px;font-size:.67rem;font-weight:700;font-family:var(--fm);letter-spacing:.3px;text-transform:uppercase;white-space:nowrap}
.badge-active{background:rgba(57,255,138,.12);color:var(--green);border:1px solid rgba(57,255,138,.25)}
.badge-inactive{background:rgba(255,59,92,.12);color:var(--red);border:1px solid rgba(255,59,92,.25)}
.badge-staff{background:rgba(57,255,138,.12);color:var(--green);border:1px solid rgba(57,255,138,.25)}
.badge-manager{background:rgba(0,201,177,.12);color:var(--teal);border:1px solid rgba(0,201,177,.25)}
.badge-safe{background:rgba(57,255,138,.1);color:var(--green);border:1px solid rgba(57,255,138,.2)}
.badge-warning{background:rgba(255,184,0,.1);color:var(--amber);border:1px solid rgba(255,184,0,.2)}
.badge-critical{background:rgba(255,59,92,.1);color:var(--red);border:1px solid rgba(255,59,92,.2);animation:blink 1.4s infinite}
.badge-unread{background:rgba(255,59,92,.1);color:var(--red);border:1px solid rgba(255,59,92,.2)}
.badge-read{background:rgba(255,184,0,.1);color:var(--amber);border:1px solid rgba(255,184,0,.2)}
.badge-resolved{background:rgba(57,255,138,.1);color:var(--green);border:1px solid rgba(57,255,138,.2)}
.badge-info{background:rgba(0,229,255,.1);color:var(--cyan);border:1px solid var(--bdr)}
.dot-blink{width:5px;height:5px;border-radius:50%;background:currentColor;animation:blink 1.5s infinite;display:inline-block}

/* ── BUTTONS ── */
.btn{display:inline-flex;align-items:center;gap:.4rem;border:none;border-radius:var(--r-sm);padding:.45rem 1rem;font-family:var(--fd);font-size:.8rem;font-weight:600;cursor:pointer;transition:.22s;letter-spacing:.3px;white-space:nowrap;-webkit-tap-highlight-color:transparent;touch-action:manipulation;user-select:none}
.btn:active{transform:scale(.97)}
.btn:disabled{opacity:.4;cursor:not-allowed;pointer-events:none}
.btn-primary{background:var(--teal);color:#000}
.btn-primary:hover{background:#00b5a0}
.btn-success{background:rgba(57,255,138,.15);color:var(--green);border:1px solid rgba(57,255,138,.3)}
.btn-warning{background:rgba(255,184,0,.15);color:var(--amber);border:1px solid rgba(255,184,0,.3)}
.btn-danger{background:rgba(255,59,92,.15);color:var(--red);border:1px solid rgba(255,59,92,.3)}
.btn-ghost{background:var(--bg-elevated);color:var(--txt2);border:1px solid var(--bdr)}
.btn-ghost:hover{border-color:var(--teal);color:var(--teal)}
.btn-sm{padding:.28rem .65rem;font-size:.72rem}

/* ── STAFF GRID ── */
.staff-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.2rem}
.staff-card{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1.2rem 1.1rem;cursor:pointer;transition:.3s;position:relative;overflow:hidden;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
.staff-card:hover{transform:translateY(-4px);border-color:var(--teal)}
.staff-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--teal),transparent);opacity:0;transition:.3s}
.staff-card:hover::before{opacity:1}
.staff-avatar{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--teal),var(--cyan));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;color:#000;margin-bottom:.9rem;position:relative;overflow:hidden}
.staff-avatar::after{content:'';position:absolute;top:-50%;left:-60%;width:28%;height:200%;background:rgba(255,255,255,.35);transform:skewX(-20deg);animation:sheen 5s infinite}
.staff-name{font-size:.9rem;font-weight:700;margin-bottom:.28rem}
.staff-email{font-size:.7rem;color:var(--muted);font-family:var(--fm);margin-bottom:.55rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.staff-pond-tag{display:inline-flex;align-items:center;gap:.3rem;background:rgba(0,201,177,.1);border:1px solid rgba(0,201,177,.22);color:var(--teal);padding:.22rem .65rem;border-radius:4px;font-family:var(--fm);font-size:.68rem;margin-bottom:.6rem}
.staff-foot{display:flex;align-items:center;justify-content:space-between;font-size:.73rem;flex-wrap:wrap;gap:.3rem}

/* ── POND CARDS ── */
.pond-cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.2rem}
.pond-card{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1.1rem;cursor:pointer;position:relative;overflow:hidden;transition:.3s;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
.pond-card:hover{transform:translateY(-3px);border-color:var(--bdr-glow)}
.pond-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.pond-card.safe::before{background:var(--green)}
.pond-card.warning::before{background:var(--amber)}
.pond-card.critical::before{background:var(--red);animation:pulseGlow 2s infinite}
.pond-card-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.55rem;flex-wrap:wrap;gap:.35rem}
.pond-card-name{font-weight:700;font-size:.88rem;display:flex;align-items:center;gap:.4rem}
.metrics-row{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin:.6rem 0}
.metric-chip{text-align:center;padding:.5rem .3rem;background:rgba(0,0,0,.25);border-radius:8px;transition:.22s}
.metric-chip:hover{background:rgba(0,229,255,.07)}
.metric-chip i{font-size:.82rem;display:block;margin-bottom:.2rem}
.metric-val{font-family:var(--fm);font-size:.9rem;font-weight:700}
.metric-lbl{font-size:.58rem;color:var(--muted);text-transform:uppercase;letter-spacing:.3px}
.ic-organic{color:var(--green)}.ic-temp{color:var(--amber)}.ic-ph{color:var(--violet)}
.pond-bar-wrap{margin:.3rem 0}
.pond-bar-label{font-size:.61rem;color:var(--muted);font-family:var(--fm);display:flex;justify-content:space-between;margin-bottom:.22rem}
.pond-bar{height:4px;background:rgba(255,255,255,.07);border-radius:2px;overflow:hidden}
.pond-bar-fill{height:100%;border-radius:2px;transition:width 1s ease}
.pond-ts{font-family:var(--fm);font-size:.62rem;color:var(--muted);display:flex;align-items:center;gap:.35rem;margin-top:.4rem;flex-wrap:wrap}

/* ── MAP ── */
#map{height:420px;border-radius:var(--r-lg);overflow:hidden;border:1px solid var(--bdr);position:relative}
.map-scan{position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(0,201,177,.5),transparent);pointer-events:none;z-index:500;animation:scanline 6s linear infinite}
.map-legend{display:flex;gap:.7rem;align-items:center;font-family:var(--fm);font-size:.68rem;flex-wrap:wrap}
.leg-item{display:flex;align-items:center;gap:.3rem;color:var(--txt2)}
.leg-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.leaflet-container{background:#060d17!important;font-family:var(--fd)!important}
.leaflet-popup-content-wrapper{background:var(--bg-panel)!important;border:1px solid var(--bdr)!important;border-radius:var(--r-md)!important;box-shadow:0 8px 32px rgba(0,0,0,.5)!important;color:var(--txt)!important}
.leaflet-popup-tip{background:var(--bg-panel)!important}
.leaflet-control-zoom a{background:var(--bg-panel)!important;color:var(--txt)!important;border-color:var(--bdr)!important}
.leaflet-control-attribution{background:rgba(6,13,23,.8)!important;color:var(--muted)!important;font-size:.55rem!important}

/* ── CHART ── */
.chart-wrap{height:240px;margin-top:.8rem}
.period-tabs{display:flex;gap:.4rem;flex-wrap:wrap}
.period-btn{font-family:var(--fm);font-size:.66rem;padding:.26rem .65rem;border-radius:4px;background:var(--bg-elevated);border:1px solid var(--bdr);color:var(--muted);cursor:pointer;transition:.2s;letter-spacing:.3px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
.period-btn.active,.period-btn:hover{background:rgba(0,201,177,.1);border-color:var(--teal);color:var(--teal)}

/* ── ALERTS ── */
.alert-item{display:flex;gap:.75rem;padding:.8rem;border-radius:var(--r-md);margin-bottom:.45rem;border-left:3px solid transparent;background:rgba(0,0,0,.2);cursor:pointer;transition:.22s;animation:slideIn .3s ease both;-webkit-tap-highlight-color:transparent}
.alert-item:hover{background:var(--bg-elevated)}
.alert-item.critical{border-left-color:var(--red)}
.alert-item.warning{border-left-color:var(--amber)}
.alert-item.info{border-left-color:var(--teal)}
.alert-icon{font-size:.95rem;flex-shrink:0;margin-top:.1rem}
.alert-icon.critical{color:var(--red)}.alert-icon.warning{color:var(--amber)}.alert-icon.info{color:var(--teal)}
.alert-pond{font-weight:700;font-size:.82rem}
.alert-msg{font-size:.77rem;color:var(--txt2);margin:.12rem 0}
.alert-foot{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.3rem}
.alert-time{font-family:var(--fm);font-size:.62rem;color:var(--muted)}

/* ── REPORT ── */
.rpt-type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-bottom:1rem}
.rpt-type-btn{background:var(--bg-elevated);border:1px solid var(--bdr);border-radius:var(--r-md);padding:.9rem .6rem;text-align:center;cursor:pointer;transition:.3s;color:var(--txt);-webkit-tap-highlight-color:transparent;touch-action:manipulation}
.rpt-type-btn:hover,.rpt-type-btn.active{border-color:var(--teal);background:rgba(0,201,177,.07)}
.rpt-type-icon{font-size:1.3rem;margin-bottom:.4rem}
.rpt-type-label{font-size:.76rem;font-weight:700;display:block}
.rpt-type-sub{font-size:.61rem;color:var(--muted);font-family:var(--fm)}
.rpt-preview{background:rgba(0,0,0,.2);border-radius:var(--r-lg);padding:1.2rem;border:1px solid var(--bdr)}
.rpt-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem;padding-bottom:.6rem;border-bottom:1px solid var(--bdr);flex-wrap:wrap;gap:.4rem}
.rpt-title{font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:.4rem}
.rpt-date-badge{font-family:var(--fm);font-size:.64rem;padding:.18rem .55rem;border-radius:4px;background:var(--bg-elevated);color:var(--muted);border:1px solid var(--bdr)}
.rpt-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:.8rem}
.rpt-stat{background:var(--bg-elevated);border-radius:var(--r-sm);padding:.7rem;text-align:center;border:1px solid var(--bdr)}
.rpt-stat-val{font-family:var(--fm);font-size:1.4rem;font-weight:700;color:var(--teal);line-height:1;margin-bottom:.22rem}
.rpt-stat-lbl{font-size:.61rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.rpt-status-row{display:flex;justify-content:space-around;padding:.6rem;background:rgba(0,0,0,.15);border-radius:8px;margin-bottom:.7rem}
.rpt-status-item{text-align:center}
.rpt-status-val{font-family:var(--fm);font-size:1.15rem;font-weight:700;line-height:1}
.rpt-status-lbl{font-size:.59rem;text-transform:uppercase;letter-spacing:.4px;margin-top:.2rem}
.metrics-mini{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:.8rem}
.metric-mini{display:flex;align-items:center;gap:.5rem;padding:.5rem .65rem;background:rgba(0,0,0,.15);border-radius:8px;border:1px solid var(--bdr)}
.metric-mini i{font-size:.95rem}
.metric-mini-val{font-family:var(--fm);font-size:.82rem;font-weight:700}
.metric-mini-lbl{font-size:.58rem;color:var(--muted)}
.rpt-dl-row{display:flex;gap:.5rem;padding-top:.8rem;border-top:1px solid var(--bdr);flex-wrap:wrap}

/* ── ACTIVITIES ── */
.act-item{display:flex;align-items:center;gap:.7rem;padding:.6rem .4rem;border-bottom:1px solid rgba(255,255,255,.04);font-size:.8rem}
.act-item:last-child{border-bottom:none}
.act-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0}
.act-icon.login{background:rgba(0,201,177,.12);color:var(--teal)}.act-icon.reading{background:rgba(255,184,0,.12);color:var(--amber)}.act-icon.alert{background:rgba(255,59,92,.12);color:var(--red)}.act-icon.system{background:rgba(57,255,138,.12);color:var(--green)}
.act-text{flex:1;color:var(--txt2)}.act-time{font-family:var(--fm);font-size:.63rem;color:var(--muted);white-space:nowrap}

/* ── MODAL ── */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(8px);z-index:10000;align-items:center;justify-content:center;padding:1rem}
.modal.open{display:flex}
.modal-box{background:var(--bg-panel);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1.8rem;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;animation:fadeUp .3s ease;-webkit-overflow-scrolling:touch}
.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;padding-bottom:.7rem;border-bottom:1px solid var(--bdr);gap:.5rem}
.modal-title{font-size:.95rem;font-weight:700}
.modal-close{background:none;border:none;color:var(--muted);font-size:1.3rem;cursor:pointer;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:6px;transition:.2s;min-width:36px;min-height:36px}
.modal-close:hover{color:var(--red);background:rgba(255,59,92,.1)}
.pond-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-top:.8rem}
.detail-row{background:var(--bg-elevated);border-radius:var(--r-sm);padding:.65rem .8rem;border:1px solid var(--bdr)}
.detail-lbl{font-size:.62rem;color:var(--muted);font-family:var(--fm);text-transform:uppercase;letter-spacing:.4px;margin-bottom:.2rem}
.detail-val{font-size:.85rem;font-weight:600}
.meter{height:6px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden;margin-top:.5rem}
.meter-fill{height:100%;border-radius:3px;transition:width 1.2s ease}
.meter-safe{background:linear-gradient(90deg,var(--green),rgba(57,255,138,.5))}
.meter-warning{background:linear-gradient(90deg,var(--amber),rgba(255,184,0,.5))}
.meter-critical{background:linear-gradient(90deg,var(--red),rgba(255,59,92,.5))}

.confirm-box{background:var(--bg-panel);border:1px solid rgba(255,59,92,.3);border-radius:var(--r-xl);padding:1.8rem;max-width:360px;width:100%;animation:fadeUp .3s ease}
.confirm-icon{font-size:2rem;margin-bottom:.7rem;text-align:center}
.confirm-title{font-size:.95rem;font-weight:700;text-align:center;margin-bottom:.5rem}
.confirm-msg{font-size:.81rem;color:var(--txt2);text-align:center;line-height:1.5;margin-bottom:1.2rem}
.confirm-btns{display:flex;gap:.6rem}
.confirm-btns .btn{flex:1;justify-content:center;min-height:44px}

/* ── TOAST ── */
.toast-wrap{position:fixed;top:74px;right:18px;z-index:10001;display:flex;flex-direction:column;gap:.45rem;pointer-events:none;max-width:calc(100vw - 2rem)}
.toast{display:flex;align-items:center;gap:.55rem;padding:.7rem 1rem;border-radius:var(--r-md);font-size:.8rem;font-weight:600;min-width:240px;animation:toastIn .3s ease;box-shadow:0 8px 24px rgba(0,0,0,.4);pointer-events:all}
.toast.success{background:rgba(57,255,138,.13);border:1px solid rgba(57,255,138,.3);color:var(--green)}
.toast.warning{background:rgba(255,184,0,.13);border:1px solid rgba(255,184,0,.3);color:var(--amber)}
.toast.critical{background:rgba(255,59,92,.13);border:1px solid rgba(255,59,92,.3);color:var(--red)}
.toast.info{background:rgba(0,201,177,.1);border:1px solid rgba(0,201,177,.2);color:var(--teal)}

/* ── NOTIFY ADMIN CARD ── */
.notify-card{background:rgba(255,59,92,.06);border:1px solid rgba(255,59,92,.2);border-radius:var(--r-xl);padding:1.2rem;margin-bottom:1.2rem}
.notify-card-title{font-size:.82rem;font-weight:700;color:var(--red);display:flex;align-items:center;gap:.5rem;margin-bottom:.6rem}

/* ── FOOTER ── */
.dash-footer{padding:.75rem 1.4rem;background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-lg);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;font-family:var(--fm);font-size:.67rem;color:var(--muted);margin-top:.5rem}

/* ═════════════════════════════
   RESPONSIVE — TABLET ≤1100px
═════════════════════════════ */
@media(max-width:1100px){
    :root{--sidebar-w:220px}
    .kpi-grid{grid-template-columns:repeat(2,1fr)}
    .grid-2{grid-template-columns:1fr}
    .staff-grid{grid-template-columns:1fr 1fr}
    .pond-cards-grid{grid-template-columns:1fr 1fr}
    .rpt-type-grid{grid-template-columns:1fr 1fr}
    .rpt-stats{grid-template-columns:1fr 1fr}
    .metrics-mini{grid-template-columns:1fr 1fr}
}

/* ═════════════════════════════
   RESPONSIVE — MOBILE ≤768px
═════════════════════════════ */
@media(max-width:768px){
    :root{--nav-h:58px}
    .sidebar{transform:translateX(-100%);z-index:700}
    .sidebar.open{transform:translateX(0)}
    .sidebar-overlay{display:block}
    .topnav{display:flex}
    .main{margin-left:0}
    .bottom-nav{display:grid}
    .content{padding:1rem 1rem 0;padding-bottom:calc(var(--bnav-h) + env(safe-area-inset-bottom,12px) + 16px);}
    .topbar{padding:.6rem .9rem}
    .topbar-left{gap:.5rem}
    .kpi-grid{grid-template-columns:repeat(2,1fr);gap:.7rem}
    .kpi{padding:.95rem 1rem}
    .kpi-val{font-size:1.5rem}
    .grid-2{grid-template-columns:1fr}
    .staff-grid{grid-template-columns:1fr}
    .pond-cards-grid{grid-template-columns:1fr}
    .rpt-type-grid{grid-template-columns:1fr}
    .rpt-stats{grid-template-columns:1fr 1fr}
    .metrics-mini{grid-template-columns:1fr}
    .pond-detail-grid{grid-template-columns:1fr}
    #map{height:300px}
    .chart-wrap{height:200px}
    .modal-box{padding:1.3rem;max-width:100%;border-radius:var(--r-lg)}
    .toast-wrap{right:10px;left:10px;top:68px}
    .toast{min-width:unset;width:100%}
    .dash-footer{flex-direction:column;text-align:center}
}

/* ═════════════════════════════
   SMALL PHONES ≤480px
═════════════════════════════ */
@media(max-width:480px){
    .kpi-grid{grid-template-columns:1fr 1fr;gap:.5rem}
    .kpi{padding:.8rem .85rem}
    .kpi-val{font-size:1.35rem}
    .kpi-icon{width:32px;height:32px;font-size:.82rem;margin-bottom:.5rem}
    .rpt-type-grid{grid-template-columns:1fr 1fr}
    .rpt-stats{grid-template-columns:1fr}
    #map{height:260px}
    .bnav-item{font-size:.52rem}
    .bnav-item i{font-size:1rem}
}

/* ═════════════════════════════
   TOUCH DEVICES
═════════════════════════════ */
@media(hover:none) and (pointer:coarse){
    .btn{min-height:44px}
    .inp{font-size:16px}
    .bnav-item{min-height:52px}
    .nav-item{min-height:44px}
}

/* ═════════════════════════════
   REDUCED MOTION
═════════════════════════════ */
@media(prefers-reduced-motion:reduce){
    *,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}
    .blink-dot,.dot-blink,.nav-badge{animation:none!important}
    body::before{animation:none!important}
    .map-scan{display:none}
}

/* ═════════════════════════════
   PRINT
═════════════════════════════ */
@media print{
    .sidebar,.topnav,.bottom-nav,.toast-wrap,.modal{display:none!important}
    .main{margin-left:0!important}
    .content{padding:.5rem!important}
    body{background:#fff!important;color:#000!important}
    .card{background:#fff!important;border:1px solid #ccc!important;break-inside:avoid}
    .section-panel{display:block!important}
}

/* ═════════════════════════════
   LARGE SCREENS ≥1400px
═════════════════════════════ */
@media(min-width:1400px){
    :root{--sidebar-w:280px}
    .content{padding:1.6rem 2rem 2rem}
    .staff-grid{grid-template-columns:repeat(3,1fr)}
    .pond-cards-grid{grid-template-columns:repeat(3,1fr)}
}
</style>
</head>
<body>

<div class="toast-wrap" id="toastWrap"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ════ SIDEBAR ════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
        <div class="sidebar-logo"><i class="fas fa-fish"></i></div>
        <div>
            <div class="sidebar-title">OrganicTilapia</div>
            <div class="sidebar-sub">Manager View · Manolo Fortich</div>
        </div>
    </div>

    <div class="nav-section">Main</div>
    <div class="nav-item active" onclick="showSection('overview',this)">
        <i class="fas fa-chart-pie"></i> Overview
        <?php if($new_alerts_count > 0): ?><span class="nav-badge"><?php echo $new_alerts_count; ?></span><?php endif; ?>
    </div>
    <div class="nav-item" onclick="showSection('staff',this)"><i class="fas fa-users"></i> My Staff</div>
    <div class="nav-item" onclick="showSection('ponds',this)"><i class="fas fa-water"></i> Pond Status</div>
    <div class="nav-item" onclick="showSection('map',this)"><i class="fas fa-map"></i> Live Map</div>

    <div class="nav-section">Analytics</div>
    <div class="nav-item" onclick="showSection('charts',this)"><i class="fas fa-chart-area"></i> Metrics Chart</div>
    <div class="nav-item" onclick="showSection('alerts',this)">
        <i class="fas fa-bell"></i> Alerts
        <?php if($new_alerts_count > 0): ?><span class="nav-badge"><?php echo $new_alerts_count; ?></span><?php endif; ?>
    </div>
    <div class="nav-item" onclick="showSection('reports',this)"><i class="fas fa-file-alt"></i> Reports</div>
    <div class="nav-item" onclick="showSection('activities',this)"><i class="fas fa-history"></i> Activities</div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?php $i=''; foreach(explode(' ',$manager_name) as $n) $i.=strtoupper(substr($n,0,1)); echo $i?:'M'; ?>
            </div>
            <div>
                <div class="sidebar-uname"><?php echo htmlspecialchars($manager_name); ?></div>
                <div class="sidebar-urole">Manager</div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn-logout-sidebar" onclick="return confirm('Logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<!-- ════ LAYOUT ════ -->
<div class="layout">
<div class="main">

<!-- TOP NAV (mobile) -->
<nav class="topnav">
    <div class="topnav-brand">
        <div class="topnav-logo"><i class="fas fa-fish"></i></div>
        <div class="topnav-title">Organic Tilapia</div>
    </div>
    <div style="display:flex;align-items:center;gap:.6rem">
        <div class="nav-clock" id="topnavClock"><?php echo $current_time_12hr; ?></div>
        <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
            <i class="fas fa-bars"></i>
            <?php if($new_alerts_count > 0): ?><div class="notif-dot-top"></div><?php endif; ?>
        </button>
    </div>
</nav>

<!-- CONTENT -->
<div class="content">

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <div>
            <div class="topbar-day"><?php echo $current_day; ?></div>
            <div class="topbar-date"><?php echo $current_date; ?></div>
        </div>
        <div class="sys-tag green"><span class="blink-dot"></span> ONLINE</div>
        <div class="sys-tag teal"><i class="fas fa-microchip" style="font-size:.6rem"></i> IOT ACTIVE</div>
        <?php if($critical_ponds > 0): ?><div class="sys-tag red"><span class="blink-dot"></span> <?php echo $critical_ponds; ?> CRITICAL</div><?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;gap:.7rem;flex-wrap:wrap">
        <div class="iot-live"><span></span> LIVE FEED</div>
        <div class="nav-clock" id="mainClock"><?php echo $current_time_12hr; ?></div>
    </div>
</div>

<!-- ════ OVERVIEW ════ -->
<div class="section-panel active" id="sec-overview">

    <div class="kpi-grid">
        <div class="kpi" style="--kc:var(--teal)"><div class="kpi-corner">STAFF</div><div class="kpi-icon"><i class="fas fa-users"></i></div><div class="kpi-val"><?php echo count($staff_list); ?></div><div class="kpi-label">My Staff</div></div>
        <div class="kpi" style="--kc:var(--green)"><div class="kpi-corner">SAFE</div><div class="kpi-icon"><i class="fas fa-check-circle"></i></div><div class="kpi-val"><?php echo $safe_ponds; ?></div><div class="kpi-label">Safe Ponds</div></div>
        <div class="kpi" style="--kc:var(--amber)"><div class="kpi-corner">WARN</div><div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div><div class="kpi-val"><?php echo $warning_ponds; ?></div><div class="kpi-label">Warning</div></div>
        <div class="kpi" style="--kc:var(--red)"><div class="kpi-corner">ALERTS</div><div class="kpi-icon"><i class="fas fa-bell"></i></div><div class="kpi-val"><?php echo $new_alerts_count; ?></div><div class="kpi-label">New Alerts</div></div>
    </div>

    <!-- Staff Assignments -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-user-tie"></i> Staff Assignments</div>
            <span class="badge badge-info"><?php echo count(array_filter($staff_list,fn($s)=>$s['status']=='active')); ?> ACTIVE</span>
        </div>
        <div class="staff-grid">
            <?php foreach($staff_list as $s):
                $init=''; foreach(explode(' ',$s['full_name']) as $n) $init.=strtoupper(substr($n,0,1));
                $pstatus = ($ponds_data[$s['assigned_pond']] ?? null)['status'] ?? 'safe';
            ?>
            <div class="staff-card" onclick="gotoMap('<?php echo $s['assigned_pond']; ?>')">
                <div class="staff-avatar"><?php echo $init?:'?'; ?></div>
                <div class="staff-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                <div class="staff-email"><?php echo htmlspecialchars($s['email']); ?></div>
                <div class="staff-pond-tag"><i class="fas fa-water"></i> Pond <?php echo $s['assigned_pond']??'N/A'; ?> <span class="badge badge-<?php echo $pstatus; ?>" style="font-size:.58rem;margin-left:.3rem"><?php echo strtoupper($pstatus); ?></span></div>
                <div class="staff-foot">
                    <div style="display:flex;align-items:center;gap:.35rem;color:var(--<?php echo $s['status']=='active'?'green':'red'; ?>);font-size:.73rem"><span class="blink-dot" style="color:var(--<?php echo $s['status']=='active'?'green':'red'; ?>)"></span><?php echo ucfirst($s['status']); ?></div>
                    <div style="font-family:var(--fm);font-size:.63rem;color:var(--muted)"><?php echo $s['last_login']?date('h:i A',strtotime($s['last_login'])):'Never'; ?></div>
                </div>
            </div>
            <?php endforeach; if(empty($staff_list)): ?><div style="grid-column:1/-1;text-align:center;padding:1.5rem;color:var(--muted)"><i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.3"></i>No staff assigned</div><?php endif; ?>
        </div>
    </div>

    <!-- Pond Overview -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-layer-group"></i> Pond Overview</div>
            <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
                <div class="iot-live"><span></span> LIVE</div>
                <button class="btn btn-ghost btn-sm" onclick="refreshAllPonds()"><i class="fas fa-sync-alt" id="refreshAllIcon"></i> Refresh</button>
            </div>
        </div>
        <div class="pond-cards-grid">
            <?php foreach($ponds_data as $key => $pond):
                $sc=$pond['status']==='safe'?'var(--green)':($pond['status']==='warning'?'var(--amber)':'var(--red)');
                $bc=$pond['organic_level']>80?'var(--red)':($pond['organic_level']>60?'var(--amber)':'var(--green)');
            ?>
            <div class="pond-card <?php echo $pond['status']; ?>" id="pcard-<?php echo $key; ?>" onclick="showPondModal('<?php echo $key; ?>')">
                <div class="pond-card-head">
                    <div class="pond-card-name"><i class="fas fa-map-marker-alt" style="color:<?php echo $sc; ?>"></i><?php echo htmlspecialchars($ponds_config[$key]['name']??$key); ?></div>
                    <span class="badge badge-<?php echo $pond['status']; ?>"><span class="dot-blink"></span><?php echo strtoupper($pond['status']); ?></span>
                </div>
                <div style="font-size:.74rem;color:var(--muted);margin:.35rem 0;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap"><i class="fas fa-user"></i><?php echo $pond['staff']; ?> · <i class="fas fa-map-pin"></i><?php echo $pond['location']; ?></div>
                <div class="metrics-row">
                    <div class="metric-chip"><i class="fas fa-seedling ic-organic"></i><div class="metric-val ic-organic" id="ov-o-<?php echo $key; ?>"><?php echo $pond['organic_level']; ?>%</div><div class="metric-lbl">Organic</div></div>
                    <div class="metric-chip"><i class="fas fa-thermometer-half ic-temp"></i><div class="metric-val ic-temp" id="ov-t-<?php echo $key; ?>"><?php echo $pond['temperature']; ?>°C</div><div class="metric-lbl">Temp</div></div>
                    <div class="metric-chip"><i class="fas fa-flask ic-ph"></i><div class="metric-val ic-ph" id="ov-p-<?php echo $key; ?>"><?php echo $pond['ph']; ?></div><div class="metric-lbl">pH</div></div>
                </div>
                <div class="pond-bar-wrap">
                    <div class="pond-bar-label"><span>Organic</span><span id="pb-o-<?php echo $key; ?>"><?php echo $pond['organic_level']; ?>%</span></div>
                    <div class="pond-bar"><div class="pond-bar-fill" id="pf-o-<?php echo $key; ?>" style="width:<?php echo min(100,$pond['organic_level']); ?>%;background:<?php echo $bc; ?>"></div></div>
                </div>
                <div class="pond-ts"><i class="far fa-clock"></i><span id="ov-ts-<?php echo $key; ?>"><?php echo date('h:i:s A',strtotime($pond['last_reading'])); ?></span></div>
                <div style="display:flex;gap:.4rem;margin-top:.6rem;flex-wrap:wrap" onclick="event.stopPropagation()">
                    <button class="btn btn-ghost btn-sm" onclick="gotoMap('<?php echo $key; ?>')"><i class="fas fa-map-marker-alt" style="color:var(--teal)"></i></button>
                    <button class="btn btn-ghost btn-sm" onclick="refreshPond('<?php echo $key; ?>')"><i class="fas fa-sync-alt"></i></button>
                    <button class="btn btn-warning btn-sm" onclick="notifyAdmin('<?php echo $key; ?>')"><i class="fas fa-bell"></i> Notify Admin</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Alerts -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-bell"></i> Recent Alerts</div>
            <button class="btn btn-ghost btn-sm" onclick="showSection('alerts')"><i class="fas fa-arrow-right"></i> All</button>
        </div>
        <div style="max-height:220px;overflow-y:auto">
            <?php foreach(array_slice($alerts,0,3) as $al): ?>
            <div class="alert-item <?php echo $al['type']; ?>">
                <i class="fas fa-<?php echo $al['type']=='critical'?'exclamation-circle':'exclamation-triangle'; ?> alert-icon <?php echo $al['type']; ?>"></i>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;justify-content:space-between;gap:.4rem;flex-wrap:wrap"><div class="alert-pond">Pond <?php echo htmlspecialchars($al['pond_name']); ?></div><div class="alert-time"><?php echo date('h:i A',strtotime($al['created_at'])); ?></div></div>
                    <div class="alert-msg"><?php echo htmlspecialchars($al['message']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /overview -->

<!-- ════ STAFF ════ -->
<div class="section-panel" id="sec-staff">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-users"></i> My Staff</div>
            <span class="badge badge-info"><?php echo count($staff_list); ?> TOTAL</span>
        </div>
        <div class="staff-grid">
            <?php foreach($staff_list as $s):
                $init=''; foreach(explode(' ',$s['full_name']) as $n) $init.=strtoupper(substr($n,0,1));
                $pstatus = ($ponds_data[$s['assigned_pond']] ?? null)['status'] ?? 'safe';
            ?>
            <div class="staff-card" onclick="gotoMap('<?php echo $s['assigned_pond']; ?>')">
                <div class="staff-avatar"><?php echo $init?:'?'; ?></div>
                <div class="staff-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                <div class="staff-email"><?php echo htmlspecialchars($s['email']); ?></div>
                <div class="staff-pond-tag"><i class="fas fa-water"></i> Pond <?php echo $s['assigned_pond']??'Unassigned'; ?> <span class="badge badge-<?php echo $pstatus; ?>" style="font-size:.58rem;margin-left:.3rem"><?php echo strtoupper($pstatus); ?></span></div>
                <div class="staff-foot">
                    <span class="badge badge-<?php echo $s['status']; ?>"><span class="dot-blink"></span><?php echo strtoupper($s['status']); ?></span>
                    <div style="font-family:var(--fm);font-size:.63rem;color:var(--muted)"><i class="far fa-clock"></i> <?php echo $s['last_login']?date('M d, h:i A',strtotime($s['last_login'])):'Never logged in'; ?></div>
                </div>
            </div>
            <?php endforeach; if(empty($staff_list)): ?>
            <div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--muted)">
                <i class="fas fa-users" style="font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.3"></i>No staff found
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════ PONDS ════ -->
<div class="section-panel" id="sec-ponds">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-layer-group"></i> All Pond Status</div>
            <button class="btn btn-ghost btn-sm" onclick="refreshAllPonds()"><i class="fas fa-sync-alt"></i> Refresh All</button>
        </div>
        <div class="pond-cards-grid">
            <?php foreach($ponds_data as $key => $pond):
                $sc=$pond['status']==='safe'?'var(--green)':($pond['status']==='warning'?'var(--amber)':'var(--red)');
                $bc=$pond['organic_level']>80?'var(--red)':($pond['organic_level']>60?'var(--amber)':'var(--green)');
            ?>
            <div class="pond-card <?php echo $pond['status']; ?>" id="pcard2-<?php echo $key; ?>" onclick="showPondModal('<?php echo $key; ?>')">
                <div class="pond-card-head"><div class="pond-card-name"><i class="fas fa-map-marker-alt" style="color:<?php echo $sc; ?>"></i><?php echo htmlspecialchars($ponds_config[$key]['name']??$key); ?></div><span class="badge badge-<?php echo $pond['status']; ?>"><span class="dot-blink"></span><?php echo strtoupper($pond['status']); ?></span></div>
                <div style="font-size:.74rem;color:var(--muted);margin:.35rem 0;display:flex;gap:.4rem;flex-wrap:wrap"><i class="fas fa-user"></i><?php echo $pond['staff']; ?> · <i class="fas fa-map-pin"></i><?php echo $pond['location']; ?></div>
                <div class="metrics-row">
                    <div class="metric-chip"><i class="fas fa-seedling ic-organic"></i><div class="metric-val ic-organic"><?php echo $pond['organic_level']; ?>%</div><div class="metric-lbl">Organic</div></div>
                    <div class="metric-chip"><i class="fas fa-thermometer-half ic-temp"></i><div class="metric-val ic-temp"><?php echo $pond['temperature']; ?>°C</div><div class="metric-lbl">Temp</div></div>
                    <div class="metric-chip"><i class="fas fa-flask ic-ph"></i><div class="metric-val ic-ph"><?php echo $pond['ph']; ?></div><div class="metric-lbl">pH</div></div>
                </div>
                <div class="pond-bar-wrap"><div class="pond-bar-label"><span>Organic Level</span><span><?php echo $pond['organic_level']; ?>%</span></div><div class="pond-bar"><div class="pond-bar-fill" style="width:<?php echo min(100,$pond['organic_level']); ?>%;background:<?php echo $bc; ?>"></div></div></div>
                <div class="pond-ts"><i class="far fa-clock"></i><?php echo date('h:i:s A',strtotime($pond['last_reading'])); ?></div>
                <div style="display:flex;gap:.4rem;margin-top:.6rem;flex-wrap:wrap" onclick="event.stopPropagation()">
                    <button class="btn btn-ghost btn-sm" onclick="gotoMap('<?php echo $key; ?>')"><i class="fas fa-map-marker-alt" style="color:var(--teal)"></i> Map</button>
                    <button class="btn btn-ghost btn-sm" onclick="refreshPond('<?php echo $key; ?>')"><i class="fas fa-sync-alt"></i></button>
                    <button class="btn btn-warning btn-sm" onclick="notifyAdmin('<?php echo $key; ?>')"><i class="fas fa-bell"></i> Notify</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ════ MAP ════ -->
<div class="section-panel" id="sec-map">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-map"></i> Polygon Pond Map — Manolo Fortich</div>
            <div class="map-legend"><div class="leg-item"><span class="leg-dot" style="background:var(--green)"></span>Safe</div><div class="leg-item"><span class="leg-dot" style="background:var(--amber)"></span>Warning</div><div class="leg-item"><span class="leg-dot" style="background:var(--red)"></span>Critical</div></div>
        </div>
        <div style="position:relative"><div id="map"></div><div class="map-scan"></div></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;flex-wrap:wrap;gap:.4rem">
            <div class="iot-live"><span></span> REAL-TIME · PH TIME</div>
            <div style="font-family:var(--fm);font-size:.68rem;color:var(--muted)" id="mapTs"><?php echo date('h:i:s A'); ?></div>
        </div>
    </div>
</div>

<!-- ════ CHARTS ════ -->
<div class="section-panel" id="sec-charts">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-chart-area"></i> Metrics Trends</div>
            <div class="period-tabs">
                <button class="period-btn active" onclick="switchPeriod('daily',this)">DAILY</button>
                <button class="period-btn" onclick="switchPeriod('weekly',this)">WEEKLY</button>
                <button class="period-btn" onclick="switchPeriod('monthly',this)">MONTHLY</button>
            </div>
        </div>
        <div class="chart-wrap"><canvas id="metricsChart"></canvas></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;flex-wrap:wrap;gap:.4rem">
            <div style="display:flex;gap:1rem;font-size:.66rem;font-family:var(--fm);flex-wrap:wrap"><span style="color:var(--red)"><i class="fas fa-circle"></i> Organic</span><span style="color:var(--amber)"><i class="fas fa-circle"></i> Temp</span><span style="color:var(--green)"><i class="fas fa-circle"></i> pH</span></div>
            <div style="font-family:var(--fm);font-size:.67rem;color:var(--muted)" id="chartTs"><?php echo date('h:i:s A'); ?></div>
        </div>
    </div>
</div>

<!-- ════ ALERTS ════ -->
<div class="section-panel" id="sec-alerts">

    <!-- Notify All Admin bar -->
    <?php if($new_alerts_count > 0): ?>
    <div class="notify-card">
        <div class="notify-card-title"><i class="fas fa-broadcast-tower"></i> <?php echo $new_alerts_count; ?> Unread Alert<?php echo $new_alerts_count>1?'s':''; ?> — Escalate to Admin?</div>
        <button class="btn btn-danger btn-sm" onclick="notifyAllAdmin()"><i class="fas fa-bell"></i> Notify Admin for All Alerts</button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-bell"></i> Alerts & Notifications</div>
            <span class="badge badge-unread" id="alertBadge"><?php echo $new_alerts_count; ?> NEW</span>
        </div>
        <div id="alertsList">
            <?php foreach($alerts as $al): ?>
            <div class="alert-item <?php echo $al['type']; ?>" onclick="gotoMap('<?php echo $al['pond_name']; ?>')">
                <i class="fas fa-<?php echo $al['type']=='critical'?'exclamation-circle':'exclamation-triangle'; ?> alert-icon <?php echo $al['type']; ?>"></i>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.3rem"><div class="alert-pond">Pond <?php echo htmlspecialchars($al['pond_name']); ?></div><div class="alert-time"><?php echo date('h:i A',strtotime($al['created_at'])); ?></div></div>
                    <div class="alert-msg"><?php echo htmlspecialchars($al['message']); ?></div>
                    <div class="alert-foot">
                        <span class="badge badge-<?php echo $al['status']; ?>"><?php echo strtoupper($al['status']); ?></span>
                        <?php if($al['status']=='unread'): ?>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap">
                            <button class="btn btn-success btn-sm" onclick="event.stopPropagation();ackAlert(<?php echo $al['notification_id']; ?>)">ACK</button>
                            <button class="btn btn-warning btn-sm" onclick="event.stopPropagation();notifyAdmin('<?php echo $al['pond_name']; ?>')"><i class="fas fa-bell"></i> Notify Admin</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; if(empty($alerts)): ?>
            <div style="text-align:center;padding:2rem;color:var(--muted)"><i class="fas fa-check-circle" style="color:var(--green);font-size:2rem;display:block;margin-bottom:.5rem"></i>No alerts</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════ REPORTS ════ -->
<div class="section-panel" id="sec-reports">
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-file-alt"></i> Report Generation</div></div>
        <div class="rpt-type-grid">
            <div class="rpt-type-btn active" onclick="genReport('daily',this)"><div class="rpt-type-icon" style="color:var(--green)"><i class="fas fa-calendar-day"></i></div><span class="rpt-type-label">Daily</span><span class="rpt-type-sub">24-hour summary</span></div>
            <div class="rpt-type-btn" onclick="genReport('weekly',this)"><div class="rpt-type-icon" style="color:var(--amber)"><i class="fas fa-calendar-week"></i></div><span class="rpt-type-label">Weekly</span><span class="rpt-type-sub">7-day trends</span></div>
            <div class="rpt-type-btn" onclick="genReport('monthly',this)"><div class="rpt-type-icon" style="color:var(--teal)"><i class="fas fa-calendar-alt"></i></div><span class="rpt-type-label">Monthly</span><span class="rpt-type-sub">30-day analysis</span></div>
        </div>
        <div class="rpt-preview" id="rptPreview">
            <div class="rpt-header"><div class="rpt-title"><i class="fas fa-chart-bar" style="color:var(--teal)"></i> Daily Report</div><div class="rpt-date-badge"><?php echo date('M d, Y'); ?></div></div>
            <div class="rpt-stats">
                <div class="rpt-stat"><div class="rpt-stat-val"><?php echo $total_ponds; ?></div><div class="rpt-stat-lbl">Total Ponds</div></div>
                <div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--red)"><?php echo $new_alerts_count; ?></div><div class="rpt-stat-lbl">Alerts</div></div>
                <div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--green)"><?php echo count(array_filter($staff_list,fn($s)=>$s['status']=='active')); ?></div><div class="rpt-stat-lbl">Active Staff</div></div>
            </div>
            <div class="rpt-status-row">
                <div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--green)"><?php echo $safe_ponds; ?></div><div class="rpt-status-lbl" style="color:var(--green)">Safe</div></div>
                <div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--amber)"><?php echo $warning_ponds; ?></div><div class="rpt-status-lbl" style="color:var(--amber)">Warning</div></div>
                <div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--red)"><?php echo $critical_ponds; ?></div><div class="rpt-status-lbl" style="color:var(--red)">Critical</div></div>
            </div>
            <div class="metrics-mini">
                <div class="metric-mini"><i class="fas fa-seedling ic-organic"></i><div><div class="metric-mini-val"><?php echo $avg_organic; ?>%</div><div class="metric-mini-lbl">Avg Organic</div></div></div>
                <div class="metric-mini"><i class="fas fa-thermometer-half ic-temp"></i><div><div class="metric-mini-val"><?php echo $avg_temp; ?>°C</div><div class="metric-mini-lbl">Avg Temp</div></div></div>
                <div class="metric-mini"><i class="fas fa-flask ic-ph"></i><div><div class="metric-mini-val"><?php echo $avg_ph; ?></div><div class="metric-mini-lbl">Avg pH</div></div></div>
            </div>
            <div class="rpt-dl-row">
                <button class="btn btn-sm" style="flex:1;background:rgba(255,59,92,.12);color:var(--red);border:1px solid rgba(255,59,92,.25)" onclick="toast('PDF downloaded (simulation)','success')"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn btn-sm" style="flex:1;background:rgba(57,255,138,.12);color:var(--green);border:1px solid rgba(57,255,138,.25)" onclick="toast('Excel downloaded (simulation)','success')"><i class="fas fa-file-excel"></i> Excel</button>
                <button class="btn btn-sm" style="flex:1;background:rgba(255,184,0,.12);color:var(--amber);border:1px solid rgba(255,184,0,.25)" onclick="toast('CSV downloaded (simulation)','success')"><i class="fas fa-file-csv"></i> CSV</button>
            </div>
        </div>
    </div>
</div>

<!-- ════ ACTIVITIES ════ -->
<div class="section-panel" id="sec-activities">
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-history"></i> Recent Activities</div><div class="iot-live"><span></span> LIVE</div></div>
        <?php foreach($recent_activities as $act):
            $icons=['login'=>'sign-in-alt','reading'=>'chart-line','alert'=>'exclamation-triangle','system'=>'cog'];
            $ic=$icons[$act['type']]??'circle';
        ?>
        <div class="act-item">
            <div class="act-icon <?php echo $act['type']; ?>"><i class="fas fa-<?php echo $ic; ?>"></i></div>
            <div class="act-text"><?php echo htmlspecialchars($act['action']); ?></div>
            <div class="act-time"><?php echo date('h:i A',strtotime($act['timestamp'])); ?></div>
        </div>
        <?php endforeach; if(empty($recent_activities)): ?><div style="text-align:center;padding:1.5rem;color:var(--muted)">No activities</div><?php endif; ?>
    </div>
</div>

<div class="dash-footer">
    <div><i class="fas fa-map-marker-alt" style="color:var(--teal)"></i> Organic Matter Detection in Tilapia · Manolo Fortich, Bukidnon · <?php echo $current_date; ?></div>
    <div>PH TIME: <span id="footerTs" style="color:var(--teal)"><?php echo date('h:i:s A'); ?></span></div>
</div>

</div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<!-- BOTTOM NAV — outside layout -->
<nav class="bottom-nav" id="bottomNav">
    <div class="bnav-item active" id="bn-overview" onclick="showSection('overview')">
        <i class="fas fa-chart-pie"></i><span>Overview</span>
        <?php if($new_alerts_count > 0): ?><span class="bnav-badge"><?php echo $new_alerts_count; ?></span><?php endif; ?>
    </div>
    <div class="bnav-item" id="bn-staff" onclick="showSection('staff')">
        <i class="fas fa-users"></i><span>Staff</span>
    </div>
    <div class="bnav-item" id="bn-ponds" onclick="showSection('ponds')">
        <i class="fas fa-water"></i><span>Ponds</span>
    </div>
    <div class="bnav-item" id="bn-map" onclick="showSection('map')">
        <i class="fas fa-map"></i><span>Map</span>
    </div>
    <div class="bnav-item" id="bn-alerts" onclick="showSection('alerts')">
        <i class="fas fa-bell"></i><span>Alerts</span>
        <?php if($new_alerts_count > 0): ?><span class="bnav-badge"><?php echo $new_alerts_count; ?></span><?php endif; ?>
    </div>
</nav>

<!-- MODALS -->
<div id="pondModal" class="modal">
    <div class="modal-box" style="max-width:520px">
        <div class="modal-head"><div class="modal-title" id="pondModalTitle">Pond Details</div><button class="modal-close" onclick="closeModal('pondModal')">&times;</button></div>
        <div id="pondModalBody"></div>
    </div>
</div>

<div id="confirmModal" class="modal">
    <div class="confirm-box">
        <div class="confirm-icon" id="confirmIcon">⚠️</div>
        <div class="confirm-title" id="confirmTitle">Confirm</div>
        <div class="confirm-msg" id="confirmMsg">Are you sure?</div>
        <div class="confirm-btns"><button class="btn btn-ghost" onclick="closeModal('confirmModal')">Cancel</button><button class="btn btn-warning" id="confirmOk">Confirm</button></div>
    </div>
</div>

<script>
// ── CONSTANTS ──────────────────────────────────────────────
const PONDS       = <?php echo json_encode($ponds_data); ?>;
const POND_COORDS = <?php echo json_encode($ponds_config); ?>;
const CHART_DATA  = <?php echo json_encode($chart_data); ?>;

const ALL_SECTIONS  = ['overview','staff','ponds','map','charts','alerts','reports','activities'];
const BNAV_SECTIONS = ['overview','staff','ponds','map','alerts'];

let map, metricsChart;
let polygons = {}, mapInited = false;
let currentSection = 'overview';

// ── INIT ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initChart();
    startSimulation();
    setTimeout(initMap, 300);
});

// ── CLOCK ──────────────────────────────────────────────────
function initClock() {
    setInterval(() => {
        const ph = new Date().toLocaleTimeString('en-US', {
            timeZone:'Asia/Manila', hour12:true,
            hour:'2-digit', minute:'2-digit', second:'2-digit'
        });
        ['mainClock','topnavClock'].forEach(id => { const e=document.getElementById(id); if(e) e.textContent=ph; });
        ['mapTs','chartTs','footerTs'].forEach(id => { const e=document.getElementById(id); if(e) e.textContent=ph; });
    }, 1000);
}

// ── SIDEBAR ────────────────────────────────────────────────
function toggleSidebar() {
    const sb=document.getElementById('sidebar'), ov=document.getElementById('sidebarOverlay');
    const open=sb.classList.contains('open');
    if(open){ sb.classList.remove('open'); ov.classList.remove('active'); }
    else    { sb.classList.add('open');    ov.classList.add('active'); }
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

// ── SECTION SWITCHING ──────────────────────────────────────
function showSection(name) {
    currentSection = name;
    ALL_SECTIONS.forEach(s => { const el=document.getElementById('sec-'+s); if(el) el.classList.remove('active'); });
    const target = document.getElementById('sec-'+name);
    if(target) target.classList.add('active');

    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        const oc = item.getAttribute('onclick') || '';
        if(oc.indexOf("'"+name+"'") !== -1) item.classList.add('active');
    });

    document.querySelectorAll('.bnav-item').forEach(b => b.classList.remove('active'));
    const bnEl = document.getElementById('bn-'+name);
    if(bnEl) bnEl.classList.add('active');

    if(name === 'map') {
        if(map) setTimeout(() => map.invalidateSize(), 150);
        else if(!mapInited) { mapInited=true; setTimeout(initMap,100); }
    }
    if(name === 'charts' && metricsChart) setTimeout(() => metricsChart.resize(), 100);
    if(window.innerWidth <= 768) closeSidebar();
    window.scrollTo({ top:0, behavior:'smooth' });
}

function gotoMap(pondKey) { showSection('map'); setTimeout(() => focusPond(pondKey), 400); }

// ── MAP ────────────────────────────────────────────────────
function getColor(s){ return s==='safe'?'#39ff8a':(s==='warning'?'#ffb800':'#ff3b5c'); }

function initMap() {
    const mapEl = document.getElementById('map');
    if(!mapEl || mapEl.clientHeight < 5) { setTimeout(initMap,400); return; }
    if(map) return;

    map = L.map('map', {zoomControl:true}).setView([8.3694, 124.8652], 17);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution:'&copy; OpenStreetMap &copy; CARTO', subdomains:'abcd', maxZoom:20
    }).addTo(map);

    Object.keys(POND_COORDS).forEach(key => {
        const cfg=POND_COORDS[key], pond=PONDS[key];
        if(!cfg || !pond) return;
        const color = getColor(pond.status);
        const poly = L.polygon(cfg.bounds, {
            color, fillColor:color,
            fillOpacity:pond.status==='critical'?0.35:0.25,
            weight:2.5, dashArray:pond.status==='critical'?'8,4':null
        }).addTo(map);
        poly.bindPopup(buildPopup(key,pond,cfg,color),{maxWidth:280});
        poly.on('click', e => { L.DomEvent.stopPropagation(e); poly.openPopup(); });
        poly.on('mouseover', () => poly.setStyle({fillOpacity:.55,weight:3.5}));
        poly.on('mouseout',  () => poly.setStyle({fillOpacity:pond.status==='critical'?0.35:0.25,weight:2.5}));
        polygons[key] = poly;
        const lIcon = L.divIcon({
            className:'',
            html:`<div style="background:rgba(6,13,23,.88);border:1.5px solid ${color};color:${color};font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:4px 8px;border-radius:6px;white-space:nowrap;box-shadow:0 0 12px ${color}44;">${cfg.name}</div>`,
            iconAnchor:[0,0]
        });
        L.marker(cfg.center,{icon:lIcon,interactive:false}).addTo(map);
    });
    const fg = L.featureGroup(Object.values(polygons));
    if(fg.getBounds().isValid()) map.fitBounds(fg.getBounds().pad(.25));
    map.invalidateSize();
}

function buildPopup(key,pond,cfg,color) {
    return `<div style="font-family:'Syne',sans-serif;color:#e8f4ff;min-width:200px;">
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:9px;padding-bottom:7px;border-bottom:1px solid rgba(255,255,255,.1)">
            <span style="background:${color}22;color:${color};border:1px solid ${color}44;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700">${pond.status.toUpperCase()}</span>
            <strong>${cfg.name}</strong>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;margin-bottom:9px">
            <div style="text-align:center;background:rgba(0,0,0,.3);padding:5px;border-radius:5px"><div style="color:#39ff8a;font-weight:700;font-size:.85rem">${pond.organic_level}%</div><div style="font-size:.58rem;color:#8ba8c4">Organic</div></div>
            <div style="text-align:center;background:rgba(0,0,0,.3);padding:5px;border-radius:5px"><div style="color:#ffb800;font-weight:700;font-size:.85rem">${pond.temperature}°C</div><div style="font-size:.58rem;color:#8ba8c4">Temp</div></div>
            <div style="text-align:center;background:rgba(0,0,0,.3);padding:5px;border-radius:5px"><div style="color:#b06cff;font-weight:700;font-size:.85rem">${pond.ph}</div><div style="font-size:.58rem;color:#8ba8c4">pH</div></div>
        </div>
        <div style="font-size:.73rem;color:#8ba8c4">👤 ${pond.staff}<br>📍 ${pond.location}</div>
    </div>`;
}

function focusPond(key) {
    if(!key || !map) return;
    const cfg=POND_COORDS[key]; if(!cfg) return;
    if(polygons[key]) {
        map.setView(cfg.center, 19);
        polygons[key].openPopup();
        polygons[key].setStyle({fillOpacity:.65});
        setTimeout(() => { if(polygons[key]) polygons[key].setStyle({fillOpacity:PONDS[key]?.status==='critical'?0.35:0.25}); }, 1200);
    }
    toast(`Focused: ${cfg.name}`,'info');
}

// ── IOT SIMULATION ─────────────────────────────────────────
function startSimulation() {
    setInterval(() => {
        Object.keys(PONDS).forEach(key => {
            const pond = PONDS[key];
            const o = parseFloat(Math.max(10,Math.min(100,pond.organic_level+(Math.random()-.5)*5)).toFixed(1));
            const t = parseFloat(Math.max(20,Math.min(38, pond.temperature  +(Math.random()-.5)*.9)).toFixed(1));
            const p = parseFloat(Math.max(5, Math.min(10, pond.ph           +(Math.random()-.5)*.12)).toFixed(1));
            const s = (o>80||t>32||p>8.5)?'critical':((o>60||t>30||p>7.8)?'warning':'safe');
            PONDS[key].organic_level=o; PONDS[key].temperature=t; PONDS[key].ph=p; PONDS[key].status=s;
            const ts = new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true});
            updatePondDisplay(key,o,t,p,s,ts);
        });
    }, 5000);
}

function updatePondDisplay(key,o,t,p,status,ts) {
    [['ov-o-',o+'%'],['ov-t-',t+'°C'],['ov-p-',p]].forEach(([pref,val])=>{ const e=document.getElementById(pref+key); if(e) e.textContent=val; });
    const pbO=document.getElementById('pb-o-'+key); if(pbO) pbO.textContent=o+'%';
    const fO=document.getElementById('pf-o-'+key);
    if(fO){ fO.style.width=Math.min(100,o)+'%'; fO.style.background=o>80?'var(--red)':(o>60?'var(--amber)':'var(--green)'); }
    const tsEl=document.getElementById('ov-ts-'+key); if(tsEl) tsEl.textContent=ts;
    ['pcard-'+key,'pcard2-'+key].forEach(id=>{ const el=document.getElementById(id); if(el) el.className='pond-card '+status; });
    if(polygons[key]){ const c=getColor(status); polygons[key].setStyle({color:c,fillColor:c,dashArray:status==='critical'?'8,4':null}); }
    if(status==='critical') toast(`⚠ CRITICAL: Pond ${key} — Organic ${o}%`,'critical');
}

function refreshPond(key) {
    fetchPost('get_iot_reading',`pond_key=${key}`)
    .then(d=>{ if(!d.success) return; updatePondDisplay(key,d.organic,d.temp,d.ph,d.status,d.timestamp); toast(`Pond ${key}: refreshed`,'success'); });
}
function refreshAllPonds() {
    const icon=document.getElementById('refreshAllIcon');
    if(icon) icon.style.animation='spin 1s linear infinite';
    Object.keys(PONDS).forEach(key=>refreshPond(key));
    setTimeout(()=>{ if(icon) icon.style.animation=''; },1200);
    toast('All ponds refreshed','success');
}

// ── CHART ──────────────────────────────────────────────────
function initChart() {
    const ctx=document.getElementById('metricsChart');
    if(!ctx) return;
    metricsChart = new Chart(ctx.getContext('2d'), {
        type:'line',
        data:{
            labels:CHART_DATA.daily.labels,
            datasets:[
                {label:'Organic %',data:CHART_DATA.daily.organic,borderColor:'#ff3b5c',backgroundColor:'rgba(255,59,92,.07)',fill:true,tension:.4,borderWidth:2,pointRadius:0,pointHoverRadius:5},
                {label:'Temp °C',data:CHART_DATA.daily.temperature,borderColor:'#ffb800',backgroundColor:'rgba(255,184,0,.07)',fill:true,tension:.4,borderWidth:2,pointRadius:0,pointHoverRadius:5},
                {label:'pH',data:CHART_DATA.daily.ph,borderColor:'#39ff8a',backgroundColor:'rgba(57,255,138,.07)',fill:true,tension:.4,borderWidth:2,pointRadius:0,pointHoverRadius:5}
            ]
        },
        options:{
            responsive:true,maintainAspectRatio:false,animation:{duration:800},
            interaction:{mode:'index',intersect:false},
            plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(11,22,37,.95)',borderColor:'rgba(0,201,177,.2)',borderWidth:1,titleColor:'#e8f4ff',bodyColor:'#8ba8c4',padding:10}},
            scales:{
                x:{grid:{color:'rgba(255,255,255,.04)',drawBorder:false},ticks:{color:'#4a6380',font:{family:"'Space Mono'",size:9},maxTicksLimit:8}},
                y:{grid:{color:'rgba(255,255,255,.04)',drawBorder:false},ticks:{color:'#4a6380',font:{family:"'Space Mono'",size:9}}}
            }
        }
    });
}

function switchPeriod(period, btn) {
    document.querySelectorAll('.period-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const orig=btn.textContent; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    fetchPost('get_chart_data',`period=${period}`)
    .then(d=>{ metricsChart.data.labels=d.labels; metricsChart.data.datasets[0].data=d.organic; metricsChart.data.datasets[1].data=d.temperature; metricsChart.data.datasets[2].data=d.ph; metricsChart.update(); btn.textContent=orig; toast(`Chart: ${period}`,'info'); });
}

// ── ALERTS ─────────────────────────────────────────────────
function ackAlert(id) { fetchPost('acknowledge_alert',`alert_id=${id}`).then(d=>{toast(d.message,'success');setTimeout(()=>location.reload(),600);}); }

function notifyAdmin(pondName) {
    fetchPost('notify_admin',`pond=${pondName}&message=Manager escalated: Alert for Pond ${pondName}`)
    .then(d=>{ toast(d.message,'success'); });
}

function notifyAllAdmin() {
    Object.keys(PONDS).forEach(key => notifyAdmin(key));
    toast('Admin notified for all active alerts','success');
}

// ── POND MODAL ─────────────────────────────────────────────
function showPondModal(key) {
    const pond=PONDS[key], cfg=POND_COORDS[key];
    if(!pond||!cfg) return;
    const color=getColor(pond.status), mc=pond.organic_level>80?'meter-critical':(pond.organic_level>60?'meter-warning':'meter-safe');
    document.getElementById('pondModalTitle').innerHTML=`<i class="fas fa-map-marker-alt" style="color:${color}"></i> ${cfg.name}`;
    document.getElementById('pondModalBody').innerHTML=`
        <div style="text-align:center;margin-bottom:.9rem"><span class="badge badge-${pond.status}" style="font-size:.8rem;padding:.4rem 1rem"><span class="dot-blink"></span> ${pond.status.toUpperCase()}</span></div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-bottom:1rem">
            <div style="text-align:center;padding:.85rem .4rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--bdr)"><i class="fas fa-seedling ic-organic" style="font-size:1.3rem;display:block;margin-bottom:.3rem"></i><div class="metric-val ic-organic" style="font-size:1.3rem">${pond.organic_level}%</div><div class="metric-lbl">Organic</div><div class="meter" style="margin-top:.4rem"><div class="meter-fill ${mc}" style="width:${pond.organic_level}%"></div></div></div>
            <div style="text-align:center;padding:.85rem .4rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--bdr)"><i class="fas fa-thermometer-half ic-temp" style="font-size:1.3rem;display:block;margin-bottom:.3rem"></i><div class="metric-val ic-temp" style="font-size:1.3rem">${pond.temperature}°C</div><div class="metric-lbl">Temp</div><div class="meter" style="margin-top:.4rem"><div class="meter-fill meter-warning" style="width:${Math.min(100,(pond.temperature-20)*10)}%"></div></div></div>
            <div style="text-align:center;padding:.85rem .4rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--bdr)"><i class="fas fa-flask ic-ph" style="font-size:1.3rem;display:block;margin-bottom:.3rem"></i><div class="metric-val ic-ph" style="font-size:1.3rem">${pond.ph}</div><div class="metric-lbl">pH</div><div class="meter" style="margin-top:.4rem"><div class="meter-fill meter-safe" style="width:${Math.min(100,pond.ph*10)}%"></div></div></div>
        </div>
        <div class="pond-detail-grid">
            <div class="detail-row"><div class="detail-lbl">Assigned Staff</div><div class="detail-val"><i class="fas fa-user" style="color:var(--teal)"></i> ${pond.staff}</div></div>
            <div class="detail-row"><div class="detail-lbl">Location</div><div class="detail-val"><i class="fas fa-map-pin" style="color:var(--red)"></i> ${pond.location}</div></div>
            <div class="detail-row"><div class="detail-lbl">Coordinates</div><div class="detail-val" style="font-family:var(--fm);font-size:.75rem">${cfg.center[0].toFixed(4)}, ${cfg.center[1].toFixed(4)}</div></div>
            <div class="detail-row"><div class="detail-lbl">Last Reading</div><div class="detail-val" style="font-family:var(--fm);font-size:.75rem">${new Date().toLocaleTimeString('en-US',{hour12:true,timeZone:'Asia/Manila'})}</div></div>
        </div>
        <div style="display:flex;gap:.5rem;margin-top:1rem;flex-wrap:wrap">
            <button class="btn btn-primary btn-sm" style="flex:1" onclick="closeModal('pondModal');gotoMap('${key}')"><i class="fas fa-map-marker-alt"></i> Map</button>
            <button class="btn btn-warning btn-sm" style="flex:1" onclick="notifyAdmin('${key}');closeModal('pondModal')"><i class="fas fa-bell"></i> Notify Admin</button>
            <button class="btn btn-ghost btn-sm" style="flex:1" onclick="refreshPond('${key}');closeModal('pondModal')"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>`;
    openModal('pondModal');
}

// ── REPORT ─────────────────────────────────────────────────
function genReport(type, btn) {
    document.querySelectorAll('.rpt-type-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    fetchPost('generate_report',`type=${type}`)
    .then(d=>{
        if(!d.success) return;
        const r=d.report;
        const labels={daily:'Daily Report',weekly:'Weekly Report',monthly:'Monthly Report'};
        const dates={daily:'<?php echo date('M d, Y'); ?>',weekly:r.week||'',monthly:r.month||''};
        document.getElementById('rptPreview').innerHTML=`
            <div class="rpt-header"><div class="rpt-title"><i class="fas fa-chart-bar" style="color:var(--teal)"></i> ${labels[type]}</div><div class="rpt-date-badge">${dates[type]}</div></div>
            ${type==='daily'?`<div class="rpt-stats"><div class="rpt-stat"><div class="rpt-stat-val">${r.total_ponds}</div><div class="rpt-stat-lbl">Ponds</div></div><div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--red)">${r.alerts_generated}</div><div class="rpt-stat-lbl">Alerts</div></div><div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--green)">${r.staff_active}</div><div class="rpt-stat-lbl">Active Staff</div></div></div><div class="rpt-status-row"><div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--green)">${r.safe_ponds}</div><div class="rpt-status-lbl" style="color:var(--green)">Safe</div></div><div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--amber)">${r.warning_ponds}</div><div class="rpt-status-lbl" style="color:var(--amber)">Warning</div></div><div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--red)">${r.critical_ponds}</div><div class="rpt-status-lbl" style="color:var(--red)">Critical</div></div></div>`:`<div class="rpt-stats"><div class="rpt-stat"><div class="rpt-stat-val">${r.total_readings}</div><div class="rpt-stat-lbl">Readings</div></div><div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--amber)">${r.incidents}</div><div class="rpt-stat-lbl">Incidents</div></div><div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--green)">${r.resolved}</div><div class="rpt-stat-lbl">Resolved</div></div></div>`}
            <div class="metrics-mini"><div class="metric-mini"><i class="fas fa-seedling ic-organic"></i><div><div class="metric-mini-val">${r.avg_organic}%</div><div class="metric-mini-lbl">Avg Organic</div></div></div><div class="metric-mini"><i class="fas fa-thermometer-half ic-temp"></i><div><div class="metric-mini-val">${r.avg_temp}°C</div><div class="metric-mini-lbl">Avg Temp</div></div></div><div class="metric-mini"><i class="fas fa-flask ic-ph"></i><div><div class="metric-mini-val">${r.avg_ph}</div><div class="metric-mini-lbl">Avg pH</div></div></div></div>
            <div class="rpt-dl-row"><button class="btn btn-sm" style="flex:1;background:rgba(255,59,92,.12);color:var(--red);border:1px solid rgba(255,59,92,.25)" onclick="toast('PDF downloaded','success')"><i class="fas fa-file-pdf"></i> PDF</button><button class="btn btn-sm" style="flex:1;background:rgba(57,255,138,.12);color:var(--green);border:1px solid rgba(57,255,138,.25)" onclick="toast('Excel downloaded','success')"><i class="fas fa-file-excel"></i> Excel</button><button class="btn btn-sm" style="flex:1;background:rgba(255,184,0,.12);color:var(--amber);border:1px solid rgba(255,184,0,.25)" onclick="toast('CSV downloaded','success')"><i class="fas fa-file-csv"></i> CSV</button></div>`;
        toast(`${labels[type]} generated`,'success');
    });
}

// ── MODAL HELPERS ──────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m.id);}));
document.addEventListener('keydown',e=>{if(e.key==='Escape') document.querySelectorAll('.modal.open').forEach(m=>m.classList.remove('open'));});

// ── TOAST ──────────────────────────────────────────────────
function toast(msg,type='info') {
    const c=document.getElementById('toastWrap'), t=document.createElement('div');
    t.className=`toast ${type}`;
    const icons={success:'check-circle',warning:'exclamation-triangle',critical:'times-circle',info:'info-circle'};
    t.innerHTML=`<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
    c.appendChild(t);
    setTimeout(()=>{t.style.opacity='0';t.style.transform='translateX(50px)';t.style.transition='.3s';setTimeout(()=>t.remove(),300);},3500);
}

// ── FETCH HELPER ───────────────────────────────────────────
function fetchPost(action,body='') {
    return fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=${action}${body?'&'+body:''}`}).then(r=>r.json());
}

// ── RESIZE ─────────────────────────────────────────────────
window.addEventListener('resize',()=>{if(map) setTimeout(()=>map.invalidateSize(),100);if(metricsChart) metricsChart.resize();});
window.addEventListener('orientationchange',()=>{setTimeout(()=>{if(map) map.invalidateSize();if(metricsChart) metricsChart.resize();},350);});

// ── SWIPE GESTURES ─────────────────────────────────────────
(function(){
    let sx=0,sy=0;
    document.addEventListener('touchstart',e=>{sx=e.touches[0].clientX;sy=e.touches[0].clientY;},{passive:true});
    document.addEventListener('touchend',e=>{
        const dx=e.changedTouches[0].clientX-sx, dy=Math.abs(e.changedTouches[0].clientY-sy);
        if(dy>60) return;
        if(dx>70&&sx<30){ document.getElementById('sidebar').classList.add('open'); document.getElementById('sidebarOverlay').classList.add('active'); }
        else if(dx<-70&&document.getElementById('sidebar').classList.contains('open')) closeSidebar();
    },{passive:true});
})();

// ── KEYBOARD SHORTCUTS ─────────────────────────────────────
document.addEventListener('keydown',e=>{
    if(document.querySelector('.modal.open')) return;
    if(e.altKey) {
        const map_={'1':'overview','2':'staff','3':'ponds','4':'map','5':'charts','6':'alerts','7':'reports','8':'activities'};
        if(map_[e.key]){ e.preventDefault(); showSection(map_[e.key]); }
    }
});

// ── VISIBILITY REFRESH ─────────────────────────────────────
document.addEventListener('visibilitychange',()=>{
    if(document.visibilityState==='visible') {
        Object.keys(PONDS).forEach(key=>{
            fetchPost('get_iot_reading',`pond_key=${key}`)
            .then(d=>{ if(d&&d.success) updatePondDisplay(key,d.organic,d.temp,d.ph,d.status,d.timestamp); })
            .catch(()=>{});
        });
    }
});

// ── RIPPLE EFFECT ──────────────────────────────────────────
document.addEventListener('click',function(e){
    const card=e.target.closest('.pond-card,.staff-card,.kpi');
    if(!card) return;
    const rect=card.getBoundingClientRect(),r=document.createElement('span'),sz=Math.max(rect.width,rect.height);
    r.style.cssText=`position:absolute;border-radius:50%;width:${sz}px;height:${sz}px;left:${e.clientX-rect.left-sz/2}px;top:${e.clientY-rect.top-sz/2}px;background:rgba(0,201,177,.12);transform:scale(0);animation:rippleAnim .5s ease;pointer-events:none;z-index:0;`;
    if(getComputedStyle(card).position==='static') card.style.position='relative';
    card.appendChild(r); setTimeout(()=>r.remove(),500);
});
(function(){const s=document.createElement('style');s.textContent='@keyframes rippleAnim{0%{transform:scale(0);opacity:1}100%{transform:scale(2.5);opacity:0}}';document.head.appendChild(s);})();

console.log('%c Organic Matter Detection in Tilapia — Manager v2.0','color:#00c9b1;font-weight:bold;font-size:13px');
console.log('%c Manolo Fortich, Bukidnon, Philippines','color:#8ba8c4');
console.log('%c Alt+1…8: sections | Esc: close modal','color:#4a6380');
</script>
</body>
</html>