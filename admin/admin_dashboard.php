<?php
session_start();
require_once '../config/config.php';
// admin_dashboard.php - Smart Tilapia Pond Monitoring System
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$admin_id    = $_SESSION['user_id'];
$admin_name  = $_SESSION['full_name'];
$current_time_12hr = date('h:i:s A');
$current_date      = date('F j, Y');
$current_day       = date('l');

$message      = '';
$message_type = '';

// ===== POND DATASET =====
$ponds_config = [
    'A-1' => [
        'name'   => 'Tilapia Pond A-1',
        'center' => [8.3695, 124.8645],
        'staff'  => 'Pedro Reyes',
        'bounds' => [
            [8.3692, 124.8642], [8.3698, 124.8640],
            [8.3700, 124.8648], [8.3696, 124.8650], [8.3692, 124.8642]
        ]
    ],
    'B-2' => [
        'name'   => 'Tilapia Pond B-2',
        'center' => [8.3688, 124.8652],
        'staff'  => 'Ana Lopez',
        'bounds' => [
            [8.3685, 124.8649], [8.3690, 124.8647],
            [8.3693, 124.8654], [8.3688, 124.8656], [8.3685, 124.8649]
        ]
    ],
    'C-1' => [
        'name'   => 'Tilapia Pond C-1',
        'center' => [8.3700, 124.8660],
        'staff'  => 'Roberto Gomez',
        'bounds' => [
            [8.3697, 124.8657], [8.3703, 124.8655],
            [8.3705, 124.8663], [8.3699, 124.8665], [8.3697, 124.8657]
        ]
    ]
];

// ===== HANDLE DELETE USER =====
if (isset($_GET['delete_user'])) {
    $uid = intval($_GET['delete_user']);
    if ($uid == $admin_id) {
        $message = "You cannot delete your own account!";
        $message_type = "error";
    } else {
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $chk->execute([$uid]);
        if ($chk->rowCount() > 0) {
            $del = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            if ($del->execute([$uid])) {
                $message = "User deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting user";
                $message_type = "error";
            }
        } else {
            $message = "User not found!";
            $message_type = "error";
        }
    }
}

// ===== FETCH USERS =====
$users_stmt = $pdo->query("SELECT user_id, full_name, email, role, assigned_pond, created_at, last_login
                            FROM users
                            ORDER BY CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 WHEN 'staff' THEN 3 END, full_name ASC");
$users = [];
while ($row = $users_stmt->fetch()) {
    $status = 'inactive';
    if ($row['last_login']) {
        $diff = (time() - strtotime($row['last_login'])) / 86400;
        $status = ($diff <= 7) ? 'active' : 'inactive';
    }
    $row['status'] = $status;
    $users[] = $row;
}

// ===== FETCH PONDS FROM DB + MERGE WITH CONFIG =====
$ponds_stmt = $pdo->query("SELECT pond_id, pond_name, location FROM ponds ORDER BY pond_name");
$ponds_db   = [];
while ($row = $ponds_stmt->fetch()) {
    $ponds_db[$row['pond_name']] = $row;
}

$ponds_data = [];
foreach ($ponds_config as $key => $cfg) {
    $pid = isset($ponds_db[$key]) ? $ponds_db[$key]['pond_id'] : null;

    $latest = null;
    if ($pid) {
        $rs = $pdo->prepare("SELECT organic_level, water_temperature AS temperature, ph_level, detected_at
                              FROM detections WHERE pond_id = ? ORDER BY detected_at DESC LIMIT 1");
        $rs->execute([$pid]);
        $latest = $rs->fetch();
    }

    $staff_row = $pdo->prepare("SELECT full_name FROM users WHERE assigned_pond = ? AND role = 'staff' LIMIT 1");
    $staff_row->execute([$key]);
    $staff_db = $staff_row->fetch();

    $organic = $latest ? floatval($latest['organic_level'])  : rand(45, 85);
    $temp    = $latest ? floatval($latest['temperature'])     : rand(25, 33);
    $ph      = $latest ? floatval($latest['ph_level'])        : round(rand(65, 85) / 10, 1);

    $status = 'safe';
    if ($organic > 80 || $temp > 32 || $ph > 8.5) $status = 'critical';
    elseif ($organic > 60 || $temp > 30 || $ph > 7.8) $status = 'warning';

    $ponds_data[$key] = [
        'pond_id'       => $pid,
        'pond_name'     => $key,
        'name'          => $cfg['name'],
        'center'        => $cfg['center'],
        'bounds'        => $cfg['bounds'],
        'organic_level' => $organic,
        'temperature'   => $temp,
        'ph'            => $ph,
        'status'        => $status,
        'staff'         => $staff_db ? $staff_db['full_name'] : $cfg['staff'],
        'location'      => isset($ponds_db[$key]) ? $ponds_db[$key]['location'] : 'Manolo Fortich',
        'last_reading'  => $latest ? $latest['detected_at'] : date('Y-m-d H:i:s')
    ];
}

// ===== ALERTS =====
$alerts_stmt = $pdo->query("SELECT n.*, p.pond_name
                             FROM notifications n
                             LEFT JOIN ponds p ON n.pond_id = p.pond_id
                             ORDER BY n.created_at DESC LIMIT 20");
$alerts = [];
if ($alerts_stmt && $alerts_stmt->rowCount() > 0) {
    while ($row = $alerts_stmt->fetch()) {
        $row['type'] = ($row['status'] == 'critical') ? 'critical' : (($row['status'] == 'warning') ? 'warning' : 'info');
        $alerts[] = $row;
    }
} else {
    $alerts = [
        ['notification_id'=>1,'pond_name'=>'B-2','message'=>'CRITICAL: High organic level (82%) detected','created_at'=>date('Y-m-d H:i:s',strtotime('-2 minutes')),'status'=>'unread','type'=>'critical'],
        ['notification_id'=>2,'pond_name'=>'A-1','message'=>'WARNING: Organic level approaching threshold','created_at'=>date('Y-m-d H:i:s',strtotime('-15 minutes')),'status'=>'unread','type'=>'warning'],
        ['notification_id'=>3,'pond_name'=>'C-1','message'=>'INFO: Routine maintenance completed','created_at'=>date('Y-m-d H:i:s',strtotime('-1 hour')),'status'=>'read','type'=>'info']
    ];
}
$new_alerts_count = count(array_filter($alerts, fn($a) => ($a['status'] ?? '') == 'unread'));

// ===== RECENT ACTIVITIES =====
$recent_activities = [];
try {
    $act_q = "(SELECT CONCAT('User ', full_name, ' logged in') AS action, last_login AS timestamp, 'login' AS type FROM users WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 5)
              UNION ALL
              (SELECT CONCAT('New reading for Pond ', pond_name) AS action, detected_at AS timestamp, 'reading' AS type FROM detections d JOIN ponds p ON d.pond_id = p.pond_id ORDER BY detected_at DESC LIMIT 5)
              UNION ALL
              (SELECT CONCAT('Alert: ', message) AS action, created_at AS timestamp, 'alert' AS type FROM notifications ORDER BY created_at DESC LIMIT 5)
              ORDER BY timestamp DESC LIMIT 10";
    $act_stmt = $pdo->query($act_q);
    if ($act_stmt && $act_stmt->rowCount() > 0) {
        while ($row = $act_stmt->fetch()) $recent_activities[] = $row;
    }
} catch(Exception $e) {}

if (empty($recent_activities)) {
    $recent_activities = [
        ['action'=>'System initialized','timestamp'=>date('Y-m-d H:i:s'),'type'=>'system'],
        ['action'=>'Admin logged in','timestamp'=>date('Y-m-d H:i:s',strtotime('-1 minute')),'type'=>'login'],
        ['action'=>'Daily report generated','timestamp'=>date('Y-m-d H:i:s',strtotime('-5 minutes')),'type'=>'system']
    ];
}

// ===== CHART DATA =====
$chart_data = ['daily'=>['labels'=>[],'organic'=>[],'temperature'=>[],'ph'=>[]]];
try {
    $dq = "SELECT DATE_FORMAT(detected_at,'%H:00') AS hour, AVG(organic_level) AS avg_organic, AVG(water_temperature) AS avg_temp, AVG(ph_level) AS avg_ph
           FROM detections WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
           GROUP BY DATE_FORMAT(detected_at,'%Y-%m-%d %H') ORDER BY detected_at LIMIT 24";
    $ds = $pdo->query($dq);
    if ($ds && $ds->rowCount() > 0) {
        while ($row = $ds->fetch()) {
            $chart_data['daily']['labels'][]      = $row['hour'];
            $chart_data['daily']['organic'][]     = round($row['avg_organic'] ?? 0, 1);
            $chart_data['daily']['temperature'][] = round($row['avg_temp'] ?? 0, 1);
            $chart_data['daily']['ph'][]          = round($row['avg_ph'] ?? 0, 1);
        }
    }
} catch(Exception $e) {}

if (empty($chart_data['daily']['labels'])) {
    for ($i = 23; $i >= 0; $i--) {
        $chart_data['daily']['labels'][]      = date('H:00', strtotime("-$i hours"));
        $chart_data['daily']['organic'][]     = rand(45, 85);
        $chart_data['daily']['temperature'][] = rand(25, 33);
        $chart_data['daily']['ph'][]          = rand(65, 85) / 10;
    }
}

$days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
for ($i = 0; $i < 7; $i++) {
    $chart_data['weekly']['labels'][]      = $days[$i];
    $chart_data['weekly']['organic'][]     = rand(45, 85);
    $chart_data['weekly']['temperature'][] = rand(25, 33);
    $chart_data['weekly']['ph'][]          = rand(65, 85) / 10;
}
for ($i = 1; $i <= 30; $i++) {
    $chart_data['monthly']['labels'][]      = 'Day '.$i;
    $chart_data['monthly']['organic'][]     = rand(45, 85);
    $chart_data['monthly']['temperature'][] = rand(25, 33);
    $chart_data['monthly']['ph'][]          = rand(65, 85) / 10;
}

// ===== REPORT SUMMARIES =====
$total_ponds    = count($ponds_data);
$safe_ponds     = count(array_filter($ponds_data, fn($p) => $p['status'] == 'safe'));
$warning_ponds  = count(array_filter($ponds_data, fn($p) => $p['status'] == 'warning'));
$critical_ponds = count(array_filter($ponds_data, fn($p) => $p['status'] == 'critical'));
$avg_organic    = $total_ponds > 0 ? array_sum(array_column($ponds_data,'organic_level')) / $total_ponds : 0;
$avg_temp       = $total_ponds > 0 ? array_sum(array_column($ponds_data,'temperature'))   / $total_ponds : 0;
$avg_ph         = $total_ponds > 0 ? array_sum(array_column($ponds_data,'ph'))             / $total_ponds : 0;

$daily_report   = ['date'=>date('Y-m-d'),'total_ponds'=>$total_ponds,'safe_ponds'=>$safe_ponds,'warning_ponds'=>$warning_ponds,'critical_ponds'=>$critical_ponds,'avg_organic'=>round($avg_organic,1),'avg_temp'=>round($avg_temp,1),'avg_ph'=>round($avg_ph,1),'staff_active'=>count(array_filter($users,fn($u)=>$u['role']=='staff'&&($u['status']??'')==='active')),'alerts_generated'=>$new_alerts_count];
$weekly_report  = ['week'=>date('M d',strtotime('-7 days')).' - '.date('M d, Y'),'total_readings'=>rand(350,450),'avg_organic'=>round($avg_organic+rand(-5,5),1),'avg_temp'=>round($avg_temp+rand(-1,1),1),'avg_ph'=>round($avg_ph+(rand(-20,20)/100),1),'incidents'=>rand(3,8),'resolved'=>rand(2,7)];
$monthly_report = ['month'=>date('F Y'),'total_readings'=>rand(1500,2000),'avg_organic'=>round($avg_organic+rand(-3,3),1),'avg_temp'=>round($avg_temp+rand(-1,1),1),'avg_ph'=>round($avg_ph+(rand(-10,10)/100),1),'incidents'=>rand(15,25),'resolved'=>rand(12,22)];

// ===== AJAX HANDLERS =====
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'get_users':
            echo json_encode($users); exit;

        case 'add_user':
            $fn = trim($_POST['full_name'] ?? '');
            $em = trim($_POST['email'] ?? '');
            $pw = password_hash($_POST['password'] ?? 'default123', PASSWORD_DEFAULT);
            $rl = $_POST['role'] ?? 'staff';
            $ap = $_POST['assigned_pond'] ?? null;
            if (!$fn || !$em) { echo json_encode(['success'=>false,'message'=>'Name and email required']); exit; }
            if (!filter_var($em, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Invalid email']); exit; }
            $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $chk->execute([$em]);
            if ($chk->rowCount() > 0) { echo json_encode(['success'=>false,'message'=>'Email already exists']); exit; }
            $ins = $pdo->prepare("INSERT INTO users (full_name,email,password,role,assigned_pond,created_at) VALUES (?,?,?,?,?,NOW())");
            if ($ins->execute([$fn,$em,$pw,$rl,$ap])) {
                echo json_encode(['success'=>true,'message'=>'User added','user_id'=>$pdo->lastInsertId()]);
            } else {
                echo json_encode(['success'=>false,'message'=>'DB error']);
            }
            exit;

        case 'edit_user':
            $uid = intval($_POST['user_id'] ?? 0);
            $fn  = trim($_POST['full_name'] ?? '');
            $em  = trim($_POST['email'] ?? '');
            $rl  = $_POST['role'] ?? '';
            $ap  = $_POST['assigned_pond'] ?? null;
            if (!$fn || !$em) { echo json_encode(['success'=>false,'message'=>'Name and email required']); exit; }
            $chk = $pdo->prepare("SELECT user_id FROM users WHERE email=? AND user_id!=?");
            $chk->execute([$em,$uid]);
            if ($chk->rowCount() > 0) { echo json_encode(['success'=>false,'message'=>'Email exists']); exit; }
            $upd = $pdo->prepare("UPDATE users SET full_name=?,email=?,role=?,assigned_pond=? WHERE user_id=?");
            echo json_encode(['success'=>$upd->execute([$fn,$em,$rl,$ap,$uid]),'message'=>'User updated']);
            exit;

        case 'delete_user':
            $uid = intval($_POST['user_id'] ?? 0);
            if ($uid == $admin_id) { echo json_encode(['success'=>false,'message'=>'Cannot delete own account']); exit; }
            $del = $pdo->prepare("DELETE FROM users WHERE user_id=?");
            echo json_encode(['success'=>$del->execute([$uid]),'message'=>'User deleted']);
            exit;

        case 'deactivate_user':
            $uid = intval($_POST['user_id'] ?? 0);
            $old = date('Y-m-d H:i:s', strtotime('-10 years'));
            $upd = $pdo->prepare("UPDATE users SET last_login=? WHERE user_id=?");
            $upd->execute([$old,$uid]);
            echo json_encode(['success'=>true,'message'=>'User deactivated']); exit;

        case 'activate_user':
            $uid = intval($_POST['user_id'] ?? 0);
            $upd = $pdo->prepare("UPDATE users SET last_login=NOW() WHERE user_id=?");
            $upd->execute([$uid]);
            echo json_encode(['success'=>true,'message'=>'User activated']); exit;

        case 'get_user':
            $uid  = intval($_POST['user_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT user_id,full_name,email,role,assigned_pond,last_login FROM users WHERE user_id=?");
            $stmt->execute([$uid]);
            $u = $stmt->fetch();
            echo json_encode($u ? ['success'=>true,'user'=>$u] : ['success'=>false,'message'=>'Not found']);
            exit;

        case 'get_chart_data':
            $period = $_POST['period'] ?? 'daily';
            echo json_encode($chart_data[$period] ?? $chart_data['daily']); exit;

        case 'acknowledge_alert':
            $aid = intval($_POST['alert_id'] ?? 0);
            $pdo->prepare("UPDATE notifications SET status='read' WHERE notification_id=?")->execute([$aid]);
            echo json_encode(['success'=>true,'message'=>'Acknowledged']); exit;

        case 'resolve_alert':
            $aid = intval($_POST['alert_id'] ?? 0);
            $pdo->prepare("UPDATE notifications SET status='resolved' WHERE notification_id=?")->execute([$aid]);
            echo json_encode(['success'=>true,'message'=>'Resolved']); exit;

        case 'generate_report':
            $type   = $_POST['type'] ?? 'daily';
            $report = ${$type.'_report'} ?? $daily_report;
            echo json_encode(['success'=>true,'report'=>$report,'type'=>$type]); exit;

        case 'bulk_action':
            $btype = $_POST['bulk_type'] ?? '';
            $uids  = json_decode($_POST['user_ids'] ?? '[]', true);
            if (empty($uids)) { echo json_encode(['success'=>false,'message'=>'No users selected']); exit; }
            $uids = array_diff(array_map('intval',$uids),[$admin_id]);
            if (empty($uids)) { echo json_encode(['success'=>false,'message'=>'Cannot act on own account']); exit; }
            $ph = implode(',',array_fill(0,count($uids),'?'));
            if ($btype == 'delete') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id IN ($ph)");
                echo json_encode(['success'=>$stmt->execute($uids),'message'=>'Bulk delete done']); exit;
            } elseif ($btype == 'activate') {
                $stmt = $pdo->prepare("UPDATE users SET last_login=NOW() WHERE user_id IN ($ph)");
                echo json_encode(['success'=>$stmt->execute($uids),'message'=>'Bulk activate done']); exit;
            } elseif ($btype == 'deactivate') {
                $old  = date('Y-m-d H:i:s',strtotime('-10 years'));
                $stmt = $pdo->prepare("UPDATE users SET last_login=? WHERE user_id IN ($ph)");
                echo json_encode(['success'=>$stmt->execute(array_merge([$old],$uids)),'message'=>'Bulk deactivate done']); exit;
            }
            echo json_encode(['success'=>false,'message'=>'Invalid action']); exit;

        case 'get_iot_reading':
            $pond_key = $_POST['pond_key'] ?? '';
            $organic  = rand(45, 92);
            $temp     = round(rand(250, 340) / 10, 1);
            $ph       = round(rand(63, 90) / 10, 1);
            $status   = 'safe';
            if ($organic > 80 || $temp > 32 || $ph > 8.5) $status = 'critical';
            elseif ($organic > 60 || $temp > 30 || $ph > 7.8) $status = 'warning';
            echo json_encode(['success'=>true,'pond'=>$pond_key,'organic'=>$organic,'temp'=>$temp,'ph'=>$ph,'status'=>$status,'timestamp'=>date('h:i:s A')]);
            exit;

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AquaAdmin — Smart Tilapia Pond Monitoring</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* ===== CSS VARIABLES ===== */
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

*{margin:0;padding:0;box-sizing:border-box;}
html{scroll-behavior:smooth;}

body{
    background: var(--bg-deep);
    color: var(--text-primary);
    font-family: var(--font-display);
    min-height: 100vh;
    overflow-x: hidden;
    position: relative;
}

/* Animated background grid */
body::before{
    content:'';
    position:fixed;
    inset:0;
    background-image:
        linear-gradient(rgba(0,229,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,229,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events:none;
    z-index:0;
    animation: gridShift 20s linear infinite;
}
body::after{
    content:'';
    position:fixed;
    top:-200px; right:-200px;
    width:600px; height:600px;
    background: radial-gradient(circle, rgba(0,229,255,0.06) 0%, transparent 70%);
    pointer-events:none;
    z-index:0;
    animation: orb1 15s ease-in-out infinite alternate;
}

@keyframes gridShift{0%{background-position:0 0;}100%{background-position:40px 40px;}}
@keyframes orb1{0%{transform:translate(0,0);}100%{transform:translate(-100px,100px);}}
@keyframes pulseGlow{0%,100%{opacity:1;box-shadow:0 0 20px currentColor;}50%{opacity:.6;box-shadow:0 0 60px currentColor;}}
@keyframes spin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}
@keyframes fadeIn{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}
@keyframes slideRight{from{transform:translateX(-20px);opacity:0;}to{transform:translateX(0);opacity:1;}}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.3;}}
@keyframes scanline{0%{top:-100%;}100%{top:200%;}}
@keyframes pondPulse{0%,100%{opacity:.7;}50%{opacity:1;}}
@keyframes notif{0%{transform:scale(1);}50%{transform:scale(1.2);}100%{transform:scale(1);}}

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:var(--bg-deep);}
::-webkit-scrollbar-thumb{background:var(--accent-cyan);border-radius:2px;}

/* ===== NAVBAR ===== */
.navbar{
    position:sticky;top:0;z-index:1000;
    background: rgba(6,13,23,0.95);
    backdrop-filter:blur(20px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    height: 64px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    box-shadow: 0 1px 0 var(--border-glow);
}

.nav-brand{
    display:flex;align-items:center;gap:.8rem;
}
.nav-logo{
    width:38px;height:38px;
    background: linear-gradient(135deg, var(--accent-cyan), var(--accent-violet));
    border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;color:#000;font-weight:800;
    position:relative;overflow:hidden;
}
.nav-logo::after{
    content:'';
    position:absolute;top:-50%;left:-50%;
    width:30%;height:200%;
    background:rgba(255,255,255,.4);
    transform:skewX(-20deg);
    animation:logoSheen 4s infinite;
}
@keyframes logoSheen{0%,100%{left:-50%;}50%{left:150%;}}

.nav-title{
    font-family:var(--font-display);
    font-weight:800;font-size:1.05rem;
    letter-spacing:.5px;
    background:linear-gradient(90deg,var(--accent-cyan),var(--text-primary));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.nav-sub{font-size:.7rem;color:var(--text-muted);font-family:var(--font-mono);}

.nav-right{display:flex;align-items:center;gap:1rem;}

.nav-clock{
    font-family:var(--font-mono);
    font-size:.8rem;color:var(--accent-cyan);
    background:rgba(0,229,255,.07);
    border:1px solid var(--border);
    padding:.35rem .8rem;border-radius:6px;
    letter-spacing:1px;
    min-width:130px;text-align:center;
}

.nav-user{
    display:flex;align-items:center;gap:.6rem;
    background:var(--bg-elevated);
    border:1px solid var(--border);
    padding:.4rem .9rem;border-radius:50px;
    font-size:.82rem;cursor:default;
    transition:.3s;
}
.nav-user:hover{border-color:var(--accent-cyan);}

.notif-dot{
    background:var(--accent-red);
    color:#fff;border-radius:50%;
    padding:.15rem .42rem;font-size:.65rem;
    font-family:var(--font-mono);
    animation:notif 2s infinite;
}

.btn-logout{
    display:inline-flex;align-items:center;gap:.5rem;
    background:transparent;
    border:1px solid rgba(255,59,92,.35);
    color:var(--accent-red);
    padding:.4rem .9rem;border-radius:50px;
    font-size:.82rem;font-family:var(--font-display);
    cursor:pointer;transition:.3s;text-decoration:none;
}
.btn-logout:hover{background:rgba(255,59,92,.12);border-color:var(--accent-red);}

/* ===== LAYOUT ===== */
.wrap{
    position:relative;z-index:1;
    padding:1.5rem 2rem 3rem;
    max-width:1700px;margin:0 auto;
}

/* ===== TOPBAR ===== */
.topbar{
    display:flex;justify-content:space-between;align-items:center;
    padding:.75rem 1.5rem;
    background:var(--bg-card);
    border:1px solid var(--border);
    border-radius:var(--r-lg);
    margin-bottom:1.5rem;
    animation:fadeIn .5s ease both;
}
.topbar-left{display:flex;align-items:center;gap:1rem;}
.topbar-date{
    font-family:var(--font-mono);
    font-size:.78rem;color:var(--text-secondary);
    letter-spacing:.5px;
}
.topbar-day{
    font-size:1.1rem;font-weight:700;
    color:var(--text-primary);
}
.system-tag{
    display:inline-flex;align-items:center;gap:.4rem;
    font-family:var(--font-mono);font-size:.7rem;
    background:rgba(57,255,138,.08);
    border:1px solid rgba(57,255,138,.25);
    color:var(--accent-green);
    padding:.3rem .8rem;border-radius:4px;
    letter-spacing:.5px;
}
.system-tag .dot{
    width:6px;height:6px;border-radius:50%;
    background:var(--accent-green);
    animation:blink 2s infinite;
}

/* ===== KPI CARDS ===== */
.kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:1rem;
    margin-bottom:1.5rem;
}
@media(max-width:1200px){.kpi-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:640px){.kpi-grid{grid-template-columns:1fr;}}

.kpi{
    background:var(--bg-card);
    border:1px solid var(--border);
    border-radius:var(--r-lg);
    padding:1.3rem 1.5rem;
    position:relative;overflow:hidden;
    transition:.4s cubic-bezier(.4,0,.2,1);
    animation:fadeIn .5s ease both;
    cursor:default;
}
.kpi:hover{
    transform:translateY(-4px);
    border-color:var(--border-glow);
    box-shadow:var(--shadow-cyan);
}
.kpi::before{
    content:'';position:absolute;
    top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,transparent,var(--accent-color,var(--accent-cyan)),transparent);
}
.kpi-1{--accent-color:var(--accent-cyan);}
.kpi-2{--accent-color:var(--accent-green);}
.kpi-3{--accent-color:var(--accent-red);}
.kpi-4{--accent-color:var(--accent-amber);}

.kpi-icon{
    width:42px;height:42px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;margin-bottom:.9rem;
    color:var(--accent-color,var(--accent-cyan));
    background:rgba(0,0,0,.3);
    border:1px solid currentColor;
    opacity:.9;
}
.kpi-val{
    font-family:var(--font-mono);
    font-size:2rem;font-weight:700;
    color:var(--accent-color,var(--accent-cyan));
    line-height:1;margin-bottom:.3rem;
}
.kpi-label{font-size:.75rem;color:var(--text-muted);letter-spacing:.5px;text-transform:uppercase;}
.kpi-sparkle{
    position:absolute;top:1rem;right:1rem;
    font-size:.65rem;font-family:var(--font-mono);
    color:var(--accent-color,var(--accent-cyan));
    opacity:.5;letter-spacing:.5px;
}

/* ===== SECTION HEADERS ===== */
.sec-head{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:1rem;
}
.sec-title{
    display:flex;align-items:center;gap:.6rem;
    font-size:.9rem;font-weight:700;
    letter-spacing:.5px;text-transform:uppercase;
    color:var(--text-primary);
}
.sec-title i{color:var(--accent-cyan);}
.sec-badge{
    font-family:var(--font-mono);font-size:.65rem;
    padding:.2rem .6rem;border-radius:4px;
    background:rgba(0,229,255,.1);
    border:1px solid var(--border);
    color:var(--accent-cyan);letter-spacing:.5px;
}

/* ===== CARDS ===== */
.card{
    background:var(--bg-card);
    border:1px solid var(--border);
    border-radius:var(--r-xl);
    padding:1.4rem 1.5rem;
    animation:fadeIn .6s ease both;
    position:relative;
    overflow:hidden;
}
.card:hover{border-color:rgba(0,229,255,.2);}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem;}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;}
@media(max-width:1100px){.grid-2{grid-template-columns:1fr;} .grid-3{grid-template-columns:1fr 1fr;}}
@media(max-width:640px){.grid-3{grid-template-columns:1fr;}}

/* ===== MANAGE USERS ===== */
.users-card{margin-bottom:1.2rem;}

.search-row{
    display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.8rem;
}
.inp{
    background:var(--bg-elevated);
    border:1px solid var(--border);
    color:var(--text-primary);
    border-radius:var(--r-sm);
    padding:.5rem .9rem;
    font-family:var(--font-display);
    font-size:.82rem;
    transition:.3s;outline:none;
}
.inp:focus{border-color:var(--accent-cyan);box-shadow:0 0 0 3px rgba(0,229,255,.1);}
.inp-search{flex:1;min-width:200px;}
.inp::placeholder{color:var(--text-muted);}
.inp option{background:var(--bg-panel);}

.bulk-row{
    display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;
    margin-bottom:.8rem;padding:.6rem;
    background:var(--bg-elevated);
    border-radius:var(--r-sm);
    border:1px solid var(--border);
}
.sel-all-wrap{display:flex;align-items:center;gap:.5rem;font-size:.82rem;}
input[type=checkbox]{
    accent-color:var(--accent-cyan);
    width:15px;height:15px;cursor:pointer;
}
.sel-count{font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);margin-left:.3rem;}

/* ===== BUTTONS ===== */
.btn{
    display:inline-flex;align-items:center;gap:.4rem;
    border:none;border-radius:var(--r-sm);
    padding:.45rem 1rem;
    font-family:var(--font-display);font-size:.8rem;font-weight:600;
    cursor:pointer;transition:.25s cubic-bezier(.4,0,.2,1);
    letter-spacing:.3px;
    position:relative;overflow:hidden;
    white-space:nowrap;
}
.btn::after{
    content:'';position:absolute;inset:0;
    background:rgba(255,255,255,0);
    transition:.2s;
}
.btn:hover::after{background:rgba(255,255,255,.08);}
.btn:active{transform:scale(.97);}
.btn:disabled{opacity:.4;cursor:not-allowed;pointer-events:none;}

.btn-primary{background:var(--accent-cyan);color:#000;}
.btn-success{background:rgba(57,255,138,.15);color:var(--accent-green);border:1px solid rgba(57,255,138,.3);}
.btn-warning{background:rgba(255,184,0,.15);color:var(--accent-amber);border:1px solid rgba(255,184,0,.3);}
.btn-danger{background:rgba(255,59,92,.15);color:var(--accent-red);border:1px solid rgba(255,59,92,.3);}
.btn-ghost{background:var(--bg-elevated);color:var(--text-secondary);border:1px solid var(--border);}
.btn-sm{padding:.28rem .65rem;font-size:.72rem;}

/* ===== TABLE ===== */
.tbl-wrap{overflow-x:auto;margin-top:.5rem;}
table{width:100%;border-collapse:collapse;}
th{
    text-align:left;padding:.6rem .8rem;
    font-size:.7rem;font-weight:600;
    color:var(--text-muted);letter-spacing:.8px;text-transform:uppercase;
    border-bottom:1px solid var(--border);
    font-family:var(--font-mono);
}
td{
    padding:.65rem .8rem;
    border-bottom:1px solid rgba(255,255,255,.04);
    font-size:.82rem;
    vertical-align:middle;
}
tr:hover td{background:rgba(0,229,255,.03);}
tr:last-child td{border-bottom:none;}

/* ===== BADGES ===== */
.badge{
    display:inline-flex;align-items:center;gap:.3rem;
    padding:.22rem .65rem;border-radius:4px;
    font-size:.68rem;font-weight:600;font-family:var(--font-mono);
    letter-spacing:.3px;text-transform:uppercase;
}
.badge-active{background:rgba(57,255,138,.12);color:var(--accent-green);border:1px solid rgba(57,255,138,.25);}
.badge-inactive{background:rgba(255,59,92,.12);color:var(--accent-red);border:1px solid rgba(255,59,92,.25);}
.badge-admin{background:rgba(176,108,255,.15);color:var(--accent-violet);border:1px solid rgba(176,108,255,.3);}
.badge-manager{background:rgba(0,229,255,.12);color:var(--accent-cyan);border:1px solid var(--border);}
.badge-staff{background:rgba(57,255,138,.12);color:var(--accent-green);border:1px solid rgba(57,255,138,.25);}
.badge-safe{background:rgba(57,255,138,.1);color:var(--accent-green);}
.badge-warning{background:rgba(255,184,0,.1);color:var(--accent-amber);}
.badge-critical{background:rgba(255,59,92,.1);color:var(--accent-red);animation:blink 1.5s infinite;}
.badge-unread{background:rgba(255,59,92,.1);color:var(--accent-red);}
.badge-read{background:rgba(255,184,0,.1);color:var(--accent-amber);}
.badge-resolved{background:rgba(57,255,138,.1);color:var(--accent-green);}

.dot-pulse{
    width:6px;height:6px;border-radius:50%;background:currentColor;
    animation:blink 1.5s infinite;
}

/* ===== STAFF CARD ===== */
.staff-item{
    display:flex;align-items:center;gap:.9rem;
    padding:.7rem .9rem;
    border-radius:var(--r-md);
    cursor:pointer;margin-bottom:.4rem;
    border:1px solid transparent;
    transition:.3s;
}
.staff-item:hover{
    background:var(--bg-elevated);
    border-color:var(--border);
    transform:translateX(4px);
}
.staff-avatar{
    width:38px;height:38px;
    background:linear-gradient(135deg,var(--accent-cyan),var(--accent-violet));
    border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-weight:800;font-size:.85rem;color:#000;
    flex-shrink:0;
}
.staff-name{font-size:.88rem;font-weight:600;}
.staff-meta{font-size:.72rem;color:var(--text-muted);font-family:var(--font-mono);margin-top:.15rem;}

/* ===== POND CARD ===== */
.pond-card{
    padding:.9rem;border-radius:var(--r-md);
    background:var(--bg-elevated);
    border:1px solid var(--border);
    cursor:pointer;margin-bottom:.6rem;
    position:relative;overflow:hidden;
    transition:.3s cubic-bezier(.4,0,.2,1);
}
.pond-card:hover{border-color:var(--border-glow);transform:translateY(-2px);}
.pond-card::before{
    content:'';position:absolute;
    top:0;left:0;right:0;height:3px;
}
.pond-card.safe::before{background:var(--accent-green);}
.pond-card.warning::before{background:var(--accent-amber);}
.pond-card.critical::before{background:var(--accent-red);animation:pulseGlow 2s infinite;}

.pond-card-head{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:.6rem;
}
.pond-card-name{font-weight:700;font-size:.92rem;}
.metrics-row{
    display:grid;grid-template-columns:repeat(3,1fr);
    gap:.5rem;margin:.6rem 0;
}
.metric-chip{
    text-align:center;padding:.5rem .3rem;
    background:rgba(0,0,0,.25);border-radius:8px;
    transition:.25s;
}
.metric-chip:hover{background:rgba(0,229,255,.07);}
.metric-chip i{font-size:.85rem;margin-bottom:.2rem;}
.metric-val{font-family:var(--font-mono);font-size:.9rem;font-weight:700;}
.metric-lbl{font-size:.6rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;}
.ic-organic{color:var(--accent-green);}
.ic-temp{color:var(--accent-amber);}
.ic-ph{color:var(--accent-violet);}

.pond-ts{
    font-family:var(--font-mono);font-size:.65rem;
    color:var(--text-muted);
    display:flex;align-items:center;gap:.4rem;
}

/* ===== IOTSIM INDICATOR ===== */
.iot-live{
    display:inline-flex;align-items:center;gap:.4rem;
    font-family:var(--font-mono);font-size:.65rem;
    color:var(--accent-green);letter-spacing:.5px;
}
.iot-live span{
    width:6px;height:6px;border-radius:50%;
    background:var(--accent-green);
    animation:blink 1.2s infinite;
}

/* ===== MAP ===== */
#map{
    height:440px;border-radius:var(--r-lg);
    overflow:hidden;
    border:1px solid var(--border);
    position:relative;
}
.map-legend{
    display:flex;gap:.8rem;align-items:center;
    font-family:var(--font-mono);font-size:.7rem;
}
.leg-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.leg-item{display:flex;align-items:center;gap:.35rem;color:var(--text-secondary);}

/* Map scanline effect overlay */
.map-scan{
    position:absolute;top:0;left:0;right:0;
    pointer-events:none;z-index:500;
    height:3px;
    background:linear-gradient(90deg,transparent,rgba(0,229,255,.4),transparent);
    animation:scanline 5s linear infinite;
}

/* ===== CHART ===== */
.chart-wrap{height:230px;margin-top:.8rem;}
.period-tabs{display:flex;gap:.4rem;}
.period-btn{
    font-family:var(--font-mono);font-size:.68rem;
    padding:.28rem .7rem;border-radius:4px;
    background:var(--bg-elevated);
    border:1px solid var(--border);
    color:var(--text-muted);cursor:pointer;
    transition:.2s;letter-spacing:.3px;
}
.period-btn.active,.period-btn:hover{
    background:rgba(0,229,255,.12);
    border-color:var(--accent-cyan);
    color:var(--accent-cyan);
}

/* ===== ALERT ITEMS ===== */
.alert-item{
    display:flex;gap:.8rem;
    padding:.8rem;border-radius:var(--r-md);
    margin-bottom:.4rem;
    border-left:3px solid transparent;
    background:rgba(0,0,0,.2);
    cursor:pointer;transition:.25s;
}
.alert-item:hover{background:var(--bg-elevated);}
.alert-item.critical{border-left-color:var(--accent-red);}
.alert-item.warning{border-left-color:var(--accent-amber);}
.alert-item.info{border-left-color:var(--accent-cyan);}
.alert-icon{font-size:1rem;flex-shrink:0;margin-top:.1rem;}
.alert-icon.critical{color:var(--accent-red);}
.alert-icon.warning{color:var(--accent-amber);}
.alert-icon.info{color:var(--accent-cyan);}
.alert-pond{font-weight:700;font-size:.82rem;}
.alert-msg{font-size:.78rem;color:var(--text-secondary);margin:.15rem 0;}
.alert-foot{display:flex;justify-content:space-between;align-items:center;}
.alert-time{font-family:var(--font-mono);font-size:.65rem;color:var(--text-muted);}

/* ===== REPORT SECTION ===== */
.rpt-type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-bottom:1rem;}
.rpt-type-btn{
    background:var(--bg-elevated);
    border:1px solid var(--border);
    border-radius:var(--r-md);
    padding:.9rem .6rem;
    text-align:center;cursor:pointer;
    transition:.3s;color:var(--text-primary);
}
.rpt-type-btn:hover,.rpt-type-btn.active{
    border-color:var(--accent-cyan);
    background:rgba(0,229,255,.07);
}
.rpt-type-icon{font-size:1.4rem;margin-bottom:.4rem;}
.rpt-type-label{font-size:.78rem;font-weight:700;display:block;}
.rpt-type-sub{font-size:.62rem;color:var(--text-muted);font-family:var(--font-mono);}

.rpt-preview{
    background:rgba(0,0,0,.2);border-radius:var(--r-lg);
    padding:1.2rem;border:1px solid var(--border);
}
.rpt-header{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:.9rem;padding-bottom:.6rem;
    border-bottom:1px solid var(--border);
}
.rpt-title{font-size:.85rem;font-weight:700;}
.rpt-date-badge{
    font-family:var(--font-mono);font-size:.65rem;
    padding:.2rem .6rem;border-radius:4px;
    background:var(--bg-elevated);
    color:var(--text-muted);
    border:1px solid var(--border);
}

.rpt-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:.8rem;}
.rpt-stat{
    background:var(--bg-elevated);border-radius:var(--r-sm);
    padding:.7rem;text-align:center;
    border:1px solid var(--border);
}
.rpt-stat-val{
    font-family:var(--font-mono);font-size:1.5rem;
    font-weight:700;color:var(--accent-cyan);
    line-height:1;margin-bottom:.25rem;
}
.rpt-stat-lbl{font-size:.62rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;}

.rpt-status-row{
    display:flex;justify-content:space-around;
    padding:.6rem;background:rgba(0,0,0,.15);border-radius:8px;
    margin-bottom:.8rem;
}
.rpt-status-item{text-align:center;}
.rpt-status-val{font-family:var(--font-mono);font-size:1.2rem;font-weight:700;line-height:1;}
.rpt-status-lbl{font-size:.6rem;text-transform:uppercase;letter-spacing:.4px;margin-top:.2rem;}

.metrics-mini{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:.8rem;}
.metric-mini{
    display:flex;align-items:center;gap:.5rem;
    padding:.55rem .7rem;
    background:rgba(0,0,0,.15);border-radius:8px;
    border:1px solid var(--border);
}
.metric-mini i{font-size:1rem;}
.metric-mini-val{font-family:var(--font-mono);font-size:.85rem;font-weight:700;}
.metric-mini-lbl{font-size:.6rem;color:var(--text-muted);}

.rpt-dl-row{
    display:flex;gap:.5rem;
    padding-top:.8rem;border-top:1px solid var(--border);
}

/* ===== ACTIVITIES ===== */
.act-item{
    display:flex;align-items:center;gap:.7rem;
    padding:.6rem .5rem;
    border-bottom:1px solid rgba(255,255,255,.04);
    font-size:.8rem;
}
.act-item:last-child{border-bottom:none;}
.act-icon{
    width:28px;height:28px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    font-size:.75rem;flex-shrink:0;
}
.act-icon.login{background:rgba(0,229,255,.12);color:var(--accent-cyan);}
.act-icon.reading{background:rgba(255,184,0,.12);color:var(--accent-amber);}
.act-icon.alert{background:rgba(255,59,92,.12);color:var(--accent-red);}
.act-icon.system{background:rgba(57,255,138,.12);color:var(--accent-green);}
.act-text{flex:1;color:var(--text-secondary);}
.act-time{font-family:var(--font-mono);font-size:.65rem;color:var(--text-muted);white-space:nowrap;}

/* ===== IOT PANEL ===== */
.iot-panel{
    margin-bottom:1.2rem;
    background:var(--bg-card);
    border:1px solid var(--border);
    border-radius:var(--r-xl);
    padding:1.3rem 1.5rem;
}
.iot-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:.8rem;}
@media(max-width:900px){.iot-grid{grid-template-columns:1fr;}}

.iot-sensor{
    background:var(--bg-elevated);
    border:1px solid var(--border);
    border-radius:var(--r-lg);
    padding:1rem;
    position:relative;overflow:hidden;
    transition:.3s;
}
.iot-sensor:hover{border-color:var(--accent-cyan);box-shadow:var(--shadow-cyan);}
.iot-sensor-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.8rem;}
.iot-pond-name{font-weight:700;font-size:.9rem;}
.iot-staff{font-size:.7rem;color:var(--text-muted);font-family:var(--font-mono);margin-top:.15rem;}
.iot-readings{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:.7rem;}
.iot-reading{
    text-align:center;padding:.5rem .3rem;
    background:rgba(0,0,0,.2);border-radius:8px;
}
.iot-reading-val{font-family:var(--font-mono);font-size:1rem;font-weight:700;}
.iot-reading-lbl{font-size:.58rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;margin-top:.1rem;}
.iot-bar-wrap{margin-bottom:.4rem;}
.iot-bar-label{font-size:.65rem;color:var(--text-muted);font-family:var(--font-mono);margin-bottom:.3rem;display:flex;justify-content:space-between;}
.iot-bar{height:4px;background:rgba(255,255,255,.07);border-radius:2px;overflow:hidden;}
.iot-bar-fill{height:100%;border-radius:2px;transition:width 1s ease;}
.iot-bar-organic .iot-bar-fill{background:var(--accent-green);}
.iot-bar-temp .iot-bar-fill{background:var(--accent-amber);}
.iot-bar-ph .iot-bar-fill{background:var(--accent-violet);}
.iot-footer{display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;}
.iot-timestamp{font-family:var(--font-mono);font-size:.62rem;color:var(--text-muted);}

.iot-sensor.safe-glow{box-shadow:0 0 20px rgba(57,255,138,.08);}
.iot-sensor.warning-glow{box-shadow:0 0 20px rgba(255,184,0,.08);}
.iot-sensor.critical-glow{box-shadow:0 0 20px rgba(255,59,92,.1);animation:pondPulse 2s infinite;}

/* ===== MODAL ===== */
.modal{
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,.8);backdrop-filter:blur(8px);
    z-index:3000;align-items:center;justify-content:center;
}
.modal.open{display:flex;}
.modal-box{
    background:var(--bg-panel);
    border:1px solid var(--border);
    border-radius:var(--r-xl);
    padding:1.8rem;
    width:90%;max-width:480px;
    max-height:85vh;overflow-y:auto;
    animation:fadeIn .3s ease;
    position:relative;
}
.modal-head{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:1.3rem;padding-bottom:.8rem;
    border-bottom:1px solid var(--border);
}
.modal-title{font-size:1rem;font-weight:700;}
.modal-close{
    background:none;border:none;color:var(--text-muted);
    font-size:1.4rem;cursor:pointer;transition:.2s;
    width:28px;height:28px;display:flex;align-items:center;justify-content:center;
    border-radius:6px;
}
.modal-close:hover{color:var(--accent-red);background:rgba(255,59,92,.1);}

.form-group{margin-bottom:.9rem;}
.form-label{
    display:block;font-size:.72rem;font-weight:600;
    color:var(--text-muted);letter-spacing:.4px;text-transform:uppercase;
    margin-bottom:.35rem;font-family:var(--font-mono);
}
.form-ctrl{
    width:100%;padding:.6rem .9rem;
    background:var(--bg-elevated);
    border:1px solid var(--border);
    border-radius:var(--r-sm);
    color:var(--text-primary);
    font-family:var(--font-display);font-size:.85rem;
    outline:none;transition:.3s;
}
.form-ctrl:focus{border-color:var(--accent-cyan);box-shadow:0 0 0 3px rgba(0,229,255,.1);}
.form-ctrl option{background:var(--bg-panel);}
.form-actions{
    display:flex;gap:.6rem;justify-content:flex-end;
    margin-top:1.2rem;padding-top:.8rem;
    border-top:1px solid var(--border);
}

/* Confirm modal */
.confirm-box{
    background:var(--bg-panel);
    border:1px solid rgba(255,59,92,.3);
    border-radius:var(--r-xl);
    padding:1.8rem;
    max-width:380px;width:90%;
    animation:fadeIn .3s ease;
}
.confirm-icon{font-size:2rem;margin-bottom:.8rem;text-align:center;}
.confirm-title{font-size:1rem;font-weight:700;text-align:center;margin-bottom:.5rem;}
.confirm-msg{font-size:.82rem;color:var(--text-secondary);text-align:center;line-height:1.5;margin-bottom:1.3rem;}
.confirm-btns{display:flex;gap:.6rem;}
.confirm-btns .btn{flex:1;justify-content:center;}

/* ===== MSG ALERT ===== */
.sys-alert{
    display:flex;justify-content:space-between;align-items:center;
    padding:.8rem 1.2rem;border-radius:var(--r-md);
    margin-bottom:1rem;font-size:.85rem;
    animation:fadeIn .4s ease;
}
.sys-alert.success{background:rgba(57,255,138,.1);border:1px solid rgba(57,255,138,.3);color:var(--accent-green);}
.sys-alert.error{background:rgba(255,59,92,.1);border:1px solid rgba(255,59,92,.3);color:var(--accent-red);}
.sys-alert-close{background:none;border:none;color:inherit;font-size:1.1rem;cursor:pointer;}

/* ===== POND DETAILS MODAL ===== */
.pond-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-top:1rem;}
.detail-row{
    background:var(--bg-elevated);
    border-radius:var(--r-sm);padding:.7rem .9rem;
    border:1px solid var(--border);
}
.detail-lbl{font-size:.65rem;color:var(--text-muted);font-family:var(--font-mono);text-transform:uppercase;letter-spacing:.4px;margin-bottom:.2rem;}
.detail-val{font-size:.88rem;font-weight:600;}

.meter{height:8px;background:rgba(255,255,255,.06);border-radius:4px;overflow:hidden;margin-top:.6rem;}
.meter-fill{height:100%;border-radius:4px;transition:width 1.2s ease;}
.meter-safe{background:linear-gradient(90deg,var(--accent-green),rgba(57,255,138,.6));}
.meter-warning{background:linear-gradient(90deg,var(--accent-amber),rgba(255,184,0,.6));}
.meter-critical{background:linear-gradient(90deg,var(--accent-red),rgba(255,59,92,.6));}

/* Toast */
.toast-container{position:fixed;top:80px;right:20px;z-index:5000;display:flex;flex-direction:column;gap:.5rem;}
.toast{
    display:flex;align-items:center;gap:.6rem;
    padding:.8rem 1.2rem;border-radius:var(--r-md);
    font-size:.82rem;font-weight:600;
    min-width:260px;
    animation:fadeIn .3s ease;
    box-shadow:0 8px 24px rgba(0,0,0,.4);
}
.toast.success{background:rgba(57,255,138,.15);border:1px solid rgba(57,255,138,.3);color:var(--accent-green);}
.toast.warning{background:rgba(255,184,0,.15);border:1px solid rgba(255,184,0,.3);color:var(--accent-amber);}
.toast.error{background:rgba(255,59,92,.15);border:1px solid rgba(255,59,92,.3);color:var(--accent-red);}
.toast.info{background:rgba(0,229,255,.12);border:1px solid var(--border);color:var(--accent-cyan);}

/* Leaflet overrides */
.leaflet-container{background:#060d17!important;font-family:var(--font-display)!important;}
.leaflet-popup-content-wrapper{
    background:var(--bg-panel)!important;
    border:1px solid var(--border)!important;
    border-radius:var(--r-md)!important;
    box-shadow:0 8px 32px rgba(0,0,0,.5)!important;
    color:var(--text-primary)!important;
}
.leaflet-popup-tip{background:var(--bg-panel)!important;}
.leaflet-popup-close-button{color:var(--text-secondary)!important;}
.leaflet-control-zoom a{
    background:var(--bg-panel)!important;
    color:var(--text-primary)!important;
    border-color:var(--border)!important;
}
.leaflet-control-attribution{background:rgba(6,13,23,.8)!important;color:var(--text-muted)!important;}
</style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="nav-brand">
        <div class="nav-logo"><i class="fas fa-fish"></i></div>
        <div>
            <div class="nav-title">AquaAdmin</div>
            <div class="nav-sub">Smart Tilapia Monitoring · Manolo Fortich, BUK</div>
        </div>
    </div>
    <div class="nav-right">
        <div class="nav-clock" id="navClock"><?php echo $current_time_12hr; ?></div>
        <div class="nav-user">
            <i class="fas fa-user-shield" style="color:var(--accent-cyan);font-size:.85rem;"></i>
            <span><?php echo htmlspecialchars($admin_name); ?></span>
            <span class="notif-dot" id="notifBadge"><?php echo $new_alerts_count; ?></span>
        </div>
        <a href="../auth/logout.php" class="btn-logout" onclick="return confirm('Confirm logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<div class="wrap">

    <?php if (!empty($message)): ?>
    <div class="sys-alert <?php echo $message_type; ?>">
        <span><i class="fas fa-<?php echo $message_type=='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></span>
        <button class="sys-alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- TOP BAR -->
    <div class="topbar">
        <div class="topbar-left">
            <div>
                <div class="topbar-day"><?php echo $current_day; ?></div>
                <div class="topbar-date"><?php echo $current_date; ?></div>
            </div>
            <div class="system-tag">
                <span class="dot"></span> SYSTEM ONLINE
            </div>
            <div class="system-tag" style="border-color:rgba(0,229,255,.25);color:var(--accent-cyan);background:rgba(0,229,255,.08);">
                <i class="fas fa-database" style="font-size:.6rem;"></i> DB CONNECTED
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:.8rem;">
            <div class="iot-live"><span></span> IoT SIM ACTIVE</div>
            <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--text-muted);">
                <?php echo $total_ponds; ?> PONDS · <?php echo count($users); ?> USERS
            </div>
        </div>
    </div>

    <!-- KPI GRID -->
    <div class="kpi-grid">
        <div class="kpi kpi-1">
            <div class="kpi-sparkle">USERS</div>
            <div class="kpi-icon"><i class="fas fa-users"></i></div>
            <div class="kpi-val"><?php echo count($users); ?></div>
            <div class="kpi-label">Total Users</div>
        </div>
        <div class="kpi kpi-2">
            <div class="kpi-sparkle">PONDS</div>
            <div class="kpi-icon"><i class="fas fa-water"></i></div>
            <div class="kpi-val"><?php echo $total_ponds; ?></div>
            <div class="kpi-label">Active Ponds</div>
        </div>
        <div class="kpi kpi-3">
            <div class="kpi-sparkle">ALERTS</div>
            <div class="kpi-icon"><i class="fas fa-bell"></i></div>
            <div class="kpi-val" id="kpiAlerts"><?php echo $new_alerts_count; ?></div>
            <div class="kpi-label">New Alerts</div>
        </div>
        <div class="kpi kpi-4">
            <div class="kpi-sparkle">READINGS</div>
            <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
            <div class="kpi-val"><?php echo rand(180,420); ?></div>
            <div class="kpi-label">Today's Readings</div>
        </div>
    </div>

    <!-- IOT SIMULATION PANEL -->
    <div class="iot-panel">
        <div class="sec-head">
            <div class="sec-title"><i class="fas fa-microchip"></i> IoT Sensor Simulation</div>
            <div style="display:flex;align-items:center;gap:.6rem;">
                <div class="iot-live"><span></span> LIVE FEED</div>
                <button class="btn btn-primary btn-sm" onclick="refreshAllIoT()">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh All
                </button>
            </div>
        </div>
        <div class="iot-grid" id="iotGrid">
            <?php foreach($ponds_data as $key => $pond): ?>
            <div class="iot-sensor <?php echo $pond['status']; ?>-glow" id="iot-<?php echo $key; ?>">
                <div class="iot-sensor-top">
                    <div>
                        <div class="iot-pond-name"><i class="fas fa-map-marker-alt" style="color:<?php echo $pond['status']=='safe'?'var(--accent-green)':($pond['status']=='warning'?'var(--accent-amber)':'var(--accent-red)'); ?>;"></i> <?php echo $pond['name']; ?></div>
                        <div class="iot-staff"><i class="fas fa-user"></i> <?php echo $pond['staff']; ?></div>
                    </div>
                    <span class="badge badge-<?php echo $pond['status']; ?>">
                        <span class="dot-pulse"></span><?php echo strtoupper($pond['status']); ?>
                    </span>
                </div>

                <div class="iot-readings">
                    <div class="iot-reading">
                        <div class="iot-reading-val ic-organic" id="val-organic-<?php echo $key; ?>"><?php echo $pond['organic_level']; ?>%</div>
                        <div class="iot-reading-lbl">Organic</div>
                    </div>
                    <div class="iot-reading">
                        <div class="iot-reading-val ic-temp" id="val-temp-<?php echo $key; ?>"><?php echo $pond['temperature']; ?>°</div>
                        <div class="iot-reading-lbl">Temp °C</div>
                    </div>
                    <div class="iot-reading">
                        <div class="iot-reading-val ic-ph" id="val-ph-<?php echo $key; ?>"><?php echo $pond['ph']; ?></div>
                        <div class="iot-reading-lbl">pH</div>
                    </div>
                </div>

                <!-- Progress bars -->
                <div class="iot-bar-wrap iot-bar-organic">
                    <div class="iot-bar-label">
                        <span><i class="fas fa-seedling"></i> Organic Level</span>
                        <span id="bar-organic-<?php echo $key; ?>"><?php echo $pond['organic_level']; ?>%</span>
                    </div>
                    <div class="iot-bar">
                        <div class="iot-bar-fill" id="fill-organic-<?php echo $key; ?>" style="width:<?php echo $pond['organic_level']; ?>%;background:<?php echo $pond['organic_level']>80?'var(--accent-red)':($pond['organic_level']>60?'var(--accent-amber)':'var(--accent-green)'); ?>;"></div>
                    </div>
                </div>
                <div class="iot-bar-wrap iot-bar-temp">
                    <div class="iot-bar-label">
                        <span><i class="fas fa-thermometer-half"></i> Temperature</span>
                        <span id="bar-temp-<?php echo $key; ?>"><?php echo $pond['temperature']; ?>°C</span>
                    </div>
                    <div class="iot-bar">
                        <div class="iot-bar-fill" id="fill-temp-<?php echo $key; ?>" style="width:<?php echo min(100,($pond['temperature']-20)*10); ?>%;"></div>
                    </div>
                </div>
                <div class="iot-bar-wrap iot-bar-ph">
                    <div class="iot-bar-label">
                        <span><i class="fas fa-flask"></i> pH Level</span>
                        <span id="bar-ph-<?php echo $key; ?>"><?php echo $pond['ph']; ?></span>
                    </div>
                    <div class="iot-bar">
                        <div class="iot-bar-fill" id="fill-ph-<?php echo $key; ?>" style="width:<?php echo min(100,$pond['ph']*10); ?>%;"></div>
                    </div>
                </div>

                <div class="iot-footer">
                    <div class="iot-timestamp"><i class="far fa-clock"></i> <span id="ts-<?php echo $key; ?>"><?php echo date('h:i:s A', strtotime($pond['last_reading'])); ?></span></div>
                    <button class="btn btn-ghost btn-sm" onclick="refreshIoT('<?php echo $key; ?>')">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAP + STAFF PANEL -->
    <div class="grid-2" style="margin-bottom:1.2rem;">

        <!-- LIVE MAP -->
        <div class="card" style="grid-column:1/2;">
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
                <div class="iot-live"><span></span> REAL-TIME MAP · <span style="color:var(--text-muted);">PH TIME</span></div>
                <div class="topbar-date" id="mapTs"><?php echo date('h:i:s A'); ?></div>
            </div>
        </div>

        <!-- STAFF + POND OVERVIEW -->
        <div style="display:flex;flex-direction:column;gap:1rem;">

            <!-- Staff Assignments -->
            <div class="card" style="flex:1;">
                <div class="sec-head">
                    <div class="sec-title"><i class="fas fa-user-tie"></i> Staff Assignments</div>
                    <div class="sec-badge"><?php echo count(array_filter($users,fn($u)=>$u['role']=='staff')); ?> STAFF</div>
                </div>
                <div style="max-height:200px;overflow-y:auto;">
                    <?php
                    $staff_list = array_filter($users, fn($u) => $u['role'] == 'staff');
                    if (!empty($staff_list)):
                        foreach ($staff_list as $s):
                            $init = '';
                            foreach (explode(' ', $s['full_name']) as $n) $init .= strtoupper(substr($n,0,1));
                    ?>
                    <div class="staff-item" onclick="focusPond('<?php echo $s['assigned_pond']; ?>')">
                        <div class="staff-avatar"><?php echo $init ?: '?'; ?></div>
                        <div style="flex:1;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div class="staff-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                <span class="badge badge-<?php echo $s['status']; ?>">
                                    <span class="dot-pulse"></span><?php echo strtoupper($s['status']); ?>
                                </span>
                            </div>
                            <div class="staff-meta">
                                <i class="fas fa-water"></i> Pond <?php echo $s['assigned_pond'] ?? 'Unassigned'; ?> &nbsp;·&nbsp;
                                <i class="far fa-clock"></i> <?php echo $s['last_login'] ? date('h:i A',strtotime($s['last_login'])) : 'Never'; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <div style="text-align:center;padding:1.5rem;color:var(--text-muted);">No staff found</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pond Quick Overview -->
            <div class="card" style="flex:1;">
                <div class="sec-head">
                    <div class="sec-title"><i class="fas fa-layer-group"></i> Pond Overview</div>
                    <div class="sec-badge"><?php echo $total_ponds; ?> PONDS</div>
                </div>
                <div style="max-height:200px;overflow-y:auto;">
                    <?php foreach($ponds_data as $key => $pond): ?>
                    <div class="pond-card <?php echo $pond['status']; ?>" onclick="showPondModal('<?php echo $key; ?>')">
                        <div class="pond-card-head">
                            <div class="pond-card-name">
                                <i class="fas fa-map-marker-alt" style="color:<?php echo $pond['status']=='safe'?'var(--accent-green)':($pond['status']=='warning'?'var(--accent-amber)':'var(--accent-red)'); ?>;"></i>
                                <?php echo $pond['name']; ?>
                            </div>
                            <span class="badge badge-<?php echo $pond['status']; ?>">
                                <span class="dot-pulse"></span><?php echo strtoupper($pond['status']); ?>
                            </span>
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
                        <div class="pond-ts">
                            <i class="far fa-clock"></i>
                            <span id="ov-ts-<?php echo $key; ?>"><?php echo date('h:i:s A',strtotime($pond['last_reading'])); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MANAGE USERS -->
    <div class="card users-card">
        <div class="sec-head">
            <div class="sec-title"><i class="fas fa-users-cog"></i> Manage Users</div>
            <button class="btn btn-primary btn-sm" onclick="openAddUser()">
                <i class="fas fa-plus"></i> Add User
            </button>
        </div>

        <div class="bulk-row">
            <div class="sel-all-wrap">
                <input type="checkbox" id="selectAll" onchange="toggleAll()">
                <label for="selectAll" style="font-size:.82rem;cursor:pointer;">Select All</label>
            </div>
            <button class="btn btn-success btn-sm" id="btnActivate" disabled onclick="bulkDo('activate')"><i class="fas fa-check"></i> Activate</button>
            <button class="btn btn-warning btn-sm" id="btnDeactivate" disabled onclick="bulkDo('deactivate')"><i class="fas fa-ban"></i> Deactivate</button>
            <button class="btn btn-danger btn-sm" id="btnDelete" disabled onclick="bulkDo('delete')"><i class="fas fa-trash"></i> Delete</button>
            <span class="sel-count" id="selCount">0 selected</span>
        </div>

        <div class="search-row">
            <input class="inp inp-search" type="text" id="userSearch" placeholder="Search by name or email…" onkeyup="filterUsers()">
            <select class="inp" id="roleFilter" onchange="filterUsers()">
                <option value="all">All Roles</option>
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="staff">Staff</option>
            </select>
            <select class="inp" id="statusFilter" onchange="filterUsers()">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div class="tbl-wrap">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th style="width:34px;"></th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Pond</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr data-role="<?php echo $u['role']; ?>" data-status="<?php echo $u['status']; ?>">
                        <td>
                            <?php if($u['user_id'] != $admin_id): ?>
                            <input type="checkbox" class="ubox" value="<?php echo $u['user_id']; ?>" onchange="updateSel()">
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <div style="width:28px;height:28px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:var(--accent-cyan);">
                                    <?php echo strtoupper(substr($u['full_name'],0,1)); ?>
                                </div>
                                <?php echo htmlspecialchars($u['full_name']); ?>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary);font-family:var(--font-mono);font-size:.75rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo strtoupper($u['role']); ?></span></td>
                        <td>
                            <span class="badge badge-<?php echo $u['status']; ?>">
                                <span class="dot-pulse"></span><?php echo strtoupper($u['status']); ?>
                            </span>
                        </td>
                        <td style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);">
                            <?php echo $u['last_login'] ? date('M d, h:i A',strtotime($u['last_login'])) : 'Never'; ?>
                        </td>
                        <td><?php echo $u['assigned_pond'] ? '<span class="badge badge-safe">'.htmlspecialchars($u['assigned_pond']).'</span>' : '<span style="color:var(--text-muted);">—</span>'; ?></td>
                        <td>
                            <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                                <button class="btn btn-ghost btn-sm" onclick="openEditUser(<?php echo $u['user_id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if($u['user_id'] != $admin_id): ?>
                                    <?php if($u['status']=='active'): ?>
                                    <button class="btn btn-warning btn-sm" onclick="doDeactivate(<?php echo $u['user_id']; ?>)" title="Deactivate">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-success btn-sm" onclick="doActivate(<?php echo $u['user_id']; ?>)" title="Activate">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-danger btn-sm" onclick="doDelete(<?php echo $u['user_id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if($u['assigned_pond']): ?>
                                <button class="btn btn-ghost btn-sm" onclick="focusPond('<?php echo $u['assigned_pond']; ?>')" title="View on Map">
                                    <i class="fas fa-map-marker-alt" style="color:var(--accent-cyan);"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CHART + ALERTS -->
    <div class="grid-2" style="margin-bottom:1.2rem;">

        <!-- Metrics Chart -->
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
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.6rem;">
                <div style="display:flex;gap:1rem;font-size:.68rem;font-family:var(--font-mono);">
                    <span style="color:var(--accent-red);"><i class="fas fa-circle"></i> Organic</span>
                    <span style="color:var(--accent-amber);"><i class="fas fa-circle"></i> Temp</span>
                    <span style="color:var(--accent-green);"><i class="fas fa-circle"></i> pH</span>
                </div>
                <div class="topbar-date" id="chartTs"><?php echo date('h:i:s A'); ?></div>
            </div>
        </div>

        <!-- Alerts -->
        <div class="card">
            <div class="sec-head">
                <div class="sec-title"><i class="fas fa-bell"></i> Alerts & Notifications</div>
                <span class="badge badge-unread" id="alertBadge"><?php echo $new_alerts_count; ?> NEW</span>
            </div>
            <div style="max-height:300px;overflow-y:auto;">
                <?php if (!empty($alerts)): ?>
                    <?php foreach($alerts as $al): ?>
                    <div class="alert-item <?php echo $al['type']; ?>" onclick="focusPond('<?php echo $al['pond_name']; ?>')">
                        <i class="fas fa-<?php echo $al['type']=='critical'?'exclamation-circle':'exclamation-triangle'; ?> alert-icon <?php echo $al['type']; ?>"></i>
                        <div style="flex:1;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.2rem;">
                                <div class="alert-pond">Pond <?php echo htmlspecialchars($al['pond_name']); ?></div>
                                <div class="alert-time"><?php echo date('h:i A',strtotime($al['created_at'])); ?></div>
                            </div>
                            <div class="alert-msg"><?php echo htmlspecialchars($al['message']); ?></div>
                            <div class="alert-foot">
                                <span class="badge badge-<?php echo $al['status']; ?>"><?php echo strtoupper($al['status']); ?></span>
                                <?php if(isset($al['status']) && $al['status']=='unread'): ?>
                                <div style="display:flex;gap:.3rem;">
                                    <button class="btn btn-success btn-sm" onclick="event.stopPropagation();ackAlert(<?php echo $al['notification_id']; ?>)">ACK</button>
                                    <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();resolveAlert(<?php echo $al['notification_id']; ?>)">RESOLVE</button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:2rem;color:var(--text-muted);">
                        <i class="fas fa-check-circle" style="font-size:2rem;color:var(--accent-green);display:block;margin-bottom:.5rem;"></i>
                        No alerts — all ponds normal
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- REPORTS + ACTIVITIES -->
    <div class="grid-2">

        <!-- Report Generation -->
        <div class="card">
            <div class="sec-head">
                <div class="sec-title"><i class="fas fa-file-chart-line"></i> Report Generation</div>
                <span class="badge" style="background:rgba(0,229,255,.1);color:var(--accent-cyan);">ANALYTICS</span>
            </div>
            <div class="rpt-type-grid">
                <div class="rpt-type-btn active" onclick="genReport('daily',this)">
                    <div class="rpt-type-icon" style="color:var(--accent-green);"><i class="fas fa-calendar-day"></i></div>
                    <span class="rpt-type-label">Daily</span>
                    <span class="rpt-type-sub">24-hour</span>
                </div>
                <div class="rpt-type-btn" onclick="genReport('weekly',this)">
                    <div class="rpt-type-icon" style="color:var(--accent-amber);"><i class="fas fa-calendar-week"></i></div>
                    <span class="rpt-type-label">Weekly</span>
                    <span class="rpt-type-sub">7-day trends</span>
                </div>
                <div class="rpt-type-btn" onclick="genReport('monthly',this)">
                    <div class="rpt-type-icon" style="color:var(--accent-cyan);"><i class="fas fa-calendar-alt"></i></div>
                    <span class="rpt-type-label">Monthly</span>
                    <span class="rpt-type-sub">30-day analysis</span>
                </div>
            </div>
            <div class="rpt-preview">
                <div class="rpt-header">
                    <div class="rpt-title"><i class="fas fa-chart-bar" style="color:var(--accent-cyan);"></i> Daily Report</div>
                    <div class="rpt-date-badge"><?php echo date('M d, Y'); ?></div>
                </div>
                <div class="rpt-stats">
                    <div class="rpt-stat">
                        <div class="rpt-stat-val"><?php echo $daily_report['total_ponds']; ?></div>
                        <div class="rpt-stat-lbl">Total Ponds</div>
                    </div>
                    <div class="rpt-stat">
                        <div class="rpt-stat-val" style="color:var(--accent-red);"><?php echo $daily_report['alerts_generated']; ?></div>
                        <div class="rpt-stat-lbl">Active Alerts</div>
                    </div>
                    <div class="rpt-stat">
                        <div class="rpt-stat-val" style="color:var(--accent-green);"><?php echo $daily_report['staff_active']; ?></div>
                        <div class="rpt-stat-lbl">Active Staff</div>
                    </div>
                </div>
                <div class="rpt-status-row">
                    <div class="rpt-status-item">
                        <div class="rpt-status-val" style="color:var(--accent-green);"><?php echo $daily_report['safe_ponds']; ?></div>
                        <div class="rpt-status-lbl" style="color:var(--accent-green);">Safe</div>
                    </div>
                    <div class="rpt-status-item">
                        <div class="rpt-status-val" style="color:var(--accent-amber);"><?php echo $daily_report['warning_ponds']; ?></div>
                        <div class="rpt-status-lbl" style="color:var(--accent-amber);">Warning</div>
                    </div>
                    <div class="rpt-status-item">
                        <div class="rpt-status-val" style="color:var(--accent-red);"><?php echo $daily_report['critical_ponds']; ?></div>
                        <div class="rpt-status-lbl" style="color:var(--accent-red);">Critical</div>
                    </div>
                </div>
                <div class="metrics-mini">
                    <div class="metric-mini">
                        <i class="fas fa-seedling ic-organic"></i>
                        <div>
                            <div class="metric-mini-val"><?php echo $daily_report['avg_organic']; ?>%</div>
                            <div class="metric-mini-lbl">Avg Organic</div>
                        </div>
                    </div>
                    <div class="metric-mini">
                        <i class="fas fa-thermometer-half ic-temp"></i>
                        <div>
                            <div class="metric-mini-val"><?php echo $daily_report['avg_temp']; ?>°C</div>
                            <div class="metric-mini-lbl">Avg Temp</div>
                        </div>
                    </div>
                    <div class="metric-mini">
                        <i class="fas fa-flask ic-ph"></i>
                        <div>
                            <div class="metric-mini-val"><?php echo $daily_report['avg_ph']; ?></div>
                            <div class="metric-mini-lbl">Avg pH</div>
                        </div>
                    </div>
                </div>
                <div class="rpt-dl-row">
                    <button class="btn btn-sm" style="flex:1;background:rgba(255,59,92,.12);color:var(--accent-red);border:1px solid rgba(255,59,92,.25);" onclick="toast('PDF downloaded (simulation)','success')">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button class="btn btn-sm" style="flex:1;background:rgba(57,255,138,.12);color:var(--accent-green);border:1px solid rgba(57,255,138,.25);" onclick="toast('Excel downloaded (simulation)','success')">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button class="btn btn-sm" style="flex:1;background:rgba(255,184,0,.12);color:var(--accent-amber);border:1px solid rgba(255,184,0,.25);" onclick="toast('CSV downloaded (simulation)','success')">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card">
            <div class="sec-head">
                <div class="sec-title"><i class="fas fa-history"></i> Recent Activities</div>
                <div class="iot-live"><span></span> LIVE</div>
            </div>
            <div style="max-height:420px;overflow-y:auto;">
                <?php if (!empty($recent_activities)): ?>
                    <?php foreach($recent_activities as $act): ?>
                    <div class="act-item">
                        <div class="act-icon <?php echo $act['type']; ?>">
                            <i class="fas fa-<?php
                                echo $act['type']=='login'?'sign-in-alt':(
                                     $act['type']=='reading'?'chart-line':(
                                     $act['type']=='alert'?'exclamation-triangle':'cog'));
                            ?>"></i>
                        </div>
                        <div class="act-text"><?php echo htmlspecialchars($act['action']); ?></div>
                        <div class="act-time"><?php echo date('h:i A',strtotime($act['timestamp'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:2rem;color:var(--text-muted);">No activities</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /wrap -->

<!-- ===== ADD/EDIT USER MODAL ===== -->
<div id="userModal" class="modal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title" id="modalTitle">Add New User</div>
            <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault();saveUser();">
            <input type="hidden" id="uid">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input class="form-ctrl" type="text" id="fName" required maxlength="100" placeholder="Juan dela Cruz">
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input class="form-ctrl" type="email" id="fEmail" required maxlength="100" placeholder="juan@example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-ctrl" id="fRole">
                    <option value="staff">Staff</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Assigned Pond</label>
                <select class="form-ctrl" id="fPond">
                    <option value="">— None —</option>
                    <?php foreach(array_keys($ponds_data) as $pk): ?>
                    <option value="<?php echo $pk; ?>">Pond <?php echo $pk; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="pwField">
                <label class="form-label">Password</label>
                <input class="form-ctrl" type="password" id="fPw" value="default123">
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('userModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== POND DETAIL MODAL ===== -->
<div id="pondModal" class="modal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-head">
            <div class="modal-title" id="pondModalTitle">Pond Details</div>
            <button class="modal-close" onclick="closeModal('pondModal')">&times;</button>
        </div>
        <div id="pondModalBody"></div>
    </div>
</div>

<!-- ===== CONFIRM MODAL ===== -->
<div id="confirmModal" class="modal">
    <div class="confirm-box">
        <div class="confirm-icon" id="confirmIcon">⚠️</div>
        <div class="confirm-title" id="confirmTitle">Confirm Action</div>
        <div class="confirm-msg" id="confirmMsg">Are you sure you want to proceed?</div>
        <div class="confirm-btns">
            <button class="btn btn-ghost" onclick="closeModal('confirmModal')">Cancel</button>
            <button class="btn btn-danger" id="confirmOk">Confirm</button>
        </div>
    </div>
</div>

<script>
// ========================================================
// GLOBAL STATE
// ========================================================
const PONDS = <?php echo json_encode($ponds_data, JSON_PRETTY_PRINT); ?>;
const CHART_DATA = <?php echo json_encode($chart_data); ?>;

let map, polygons = {}, labelMarkers = {}, metricsChart;
let selectedIds = [];
let confirmCb = null;

// ========================================================
// INIT
// ========================================================
document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initMap();
    initChart();
    startIoTSimulation();
});

// ========================================================
// CLOCK
// ========================================================
function initClock() {
    setInterval(() => {
        const ph = new Date().toLocaleTimeString('en-US', {
            timeZone: 'Asia/Manila',
            hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
        document.getElementById('navClock').textContent = ph;
        const ts = document.getElementById('mapTs');
        if(ts) ts.textContent = ph;
        const ct = document.getElementById('chartTs');
        if(ct) ct.textContent = ph;
    }, 1000);
}

// ========================================================
// MAP — LEAFLET + POLYGON PONDS
// ========================================================
function getStatusColor(status) {
    return status === 'safe' ? '#39ff8a' : status === 'warning' ? '#ffb800' : '#ff3b5c';
}

function initMap() {
    map = L.map('map', { zoomControl: true, attributionControl: true })
           .setView([8.3694, 124.8652], 17);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        subdomains: 'abcd', maxZoom: 20
    }).addTo(map);

    // Draw polygons + labels
    Object.keys(PONDS).forEach(key => {
        const pond = PONDS[key];
        const color = getStatusColor(pond.status);
        const bounds = pond.bounds;

        const poly = L.polygon(bounds, {
            color: color,
            fillColor: color,
            fillOpacity: pond.status === 'critical' ? 0.35 : 0.25,
            weight: 2.5,
            dashArray: pond.status === 'critical' ? '8,4' : null
        }).addTo(map);

        poly.bindPopup(buildMapPopup(key, pond), { maxWidth: 300 });
        poly.on('click', () => { poly.openPopup(); });
        poly.on('mouseover', () => {
            poly.setStyle({ fillOpacity: 0.5, weight: 3.5 });
        });
        poly.on('mouseout', () => {
            poly.setStyle({ fillOpacity: pond.status === 'critical' ? 0.35 : 0.25, weight: 2.5 });
        });

        polygons[key] = poly;

        // Label marker at center
        const icon = L.divIcon({
            className: '',
            html: `<div style="
                background:rgba(6,13,23,.85);
                border:1.5px solid ${color};
                color:${color};
                font-family:'Space Mono',monospace;
                font-size:11px;font-weight:700;
                padding:4px 8px;border-radius:6px;
                white-space:nowrap;
                box-shadow:0 0 12px ${color}44;
                ${pond.status==='critical'?'animation:pondPulse 2s infinite;':''}
            ">${pond.name}</div>`,
            iconAnchor: [0, 0]
        });
        const lm = L.marker(pond.center, { icon, interactive: false }).addTo(map);
        labelMarkers[key] = lm;
    });

    // Fit all polygons
    const group = Object.values(polygons);
    if (group.length) {
        const fg = L.featureGroup(group);
        map.fitBounds(fg.getBounds().pad(0.2));
    }
}

function buildMapPopup(key, pond) {
    const color = getStatusColor(pond.status);
    return `
    <div style="font-family:'Syne',sans-serif;color:#e8f4ff;min-width:220px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.1);">
            <span style="background:${color}22;color:${color};border:1px solid ${color}44;padding:2px 8px;border-radius:4px;font-size:11px;font-family:'Space Mono',monospace;font-weight:700;">${pond.status.toUpperCase()}</span>
            <strong style="font-size:.9rem;">${pond.name}</strong>
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

function focusPond(key) {
    if (!key || !polygons[key]) return;
    const pond = PONDS[key];
    if (!pond) return;
    map.setView(pond.center, 19);
    polygons[key].openPopup();

    // Flash polygon
    const origColor = getStatusColor(pond.status);
    polygons[key].setStyle({ fillOpacity: 0.7 });
    setTimeout(() => polygons[key].setStyle({ fillOpacity: pond.status === 'critical' ? 0.35 : 0.25 }), 1000);

    toast(`Focused: ${pond.name}`, 'info');
}

// ========================================================
// IOT SIMULATION
// ========================================================
function startIoTSimulation() {
    setInterval(() => {
        Object.keys(PONDS).forEach(key => {
            // Simulate slight fluctuation
            const pond = PONDS[key];
            const newOrganic = Math.max(10, Math.min(100, pond.organic_level + (Math.random() - 0.5) * 4));
            const newTemp    = Math.max(20, Math.min(38,  pond.temperature    + (Math.random() - 0.5) * 0.8));
            const newPh      = Math.max(5,  Math.min(10,  pond.ph             + (Math.random() - 0.5) * 0.15));

            updateIoTDisplay(key, newOrganic.toFixed(1), newTemp.toFixed(1), parseFloat(newPh).toFixed(1));

            PONDS[key].organic_level = parseFloat(newOrganic.toFixed(1));
            PONDS[key].temperature   = parseFloat(newTemp.toFixed(1));
            PONDS[key].ph            = parseFloat(newPh.toFixed(1));
        });
    }, 5000);
}

function updateIoTDisplay(key, organic, temp, ph) {
    const ts = new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true});
    organic = parseFloat(organic);
    temp    = parseFloat(temp);
    ph      = parseFloat(ph);

    // IoT panel
    const valO = document.getElementById(`val-organic-${key}`);
    const valT = document.getElementById(`val-temp-${key}`);
    const valP = document.getElementById(`val-ph-${key}`);
    if(valO) valO.textContent = organic + '%';
    if(valT) valT.textContent = temp + '°';
    if(valP) valP.textContent = ph;

    const barO = document.getElementById(`bar-organic-${key}`);
    const barT = document.getElementById(`bar-temp-${key}`);
    const barP = document.getElementById(`bar-ph-${key}`);
    if(barO) barO.textContent = organic + '%';
    if(barT) barT.textContent = temp + '°C';
    if(barP) barP.textContent = ph;

    const fillO = document.getElementById(`fill-organic-${key}`);
    const fillT = document.getElementById(`fill-temp-${key}`);
    const fillP = document.getElementById(`fill-ph-${key}`);
    if(fillO) {
        fillO.style.width = Math.min(100, organic) + '%';
        fillO.style.background = organic > 80 ? 'var(--accent-red)' : organic > 60 ? 'var(--accent-amber)' : 'var(--accent-green)';
    }
    if(fillT) fillT.style.width = Math.min(100, (temp - 20) * 10) + '%';
    if(fillP) fillP.style.width = Math.min(100, ph * 10) + '%';

    const tsEl = document.getElementById(`ts-${key}`);
    if(tsEl) tsEl.textContent = ts;

    // Overview panel
    const ovO = document.getElementById(`ov-organic-${key}`);
    const ovT = document.getElementById(`ov-temp-${key}`);
    const ovP = document.getElementById(`ov-ph-${key}`);
    const ovTs = document.getElementById(`ov-ts-${key}`);
    if(ovO) ovO.textContent = organic + '%';
    if(ovT) ovT.textContent = temp + '°C';
    if(ovP) ovP.textContent = ph;
    if(ovTs) ovTs.textContent = ts;
}

function refreshIoT(key) {
    const btn = document.querySelector(`#iot-${key} .btn i.fa-sync-alt`);
    if(btn) { btn.style.animation='spin 1s linear infinite'; }

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_iot_reading&pond_key=${key}`
    })
    .then(r => r.json())
    .then(d => {
        if(d.success) {
            updateIoTDisplay(key, d.organic, d.temp, d.ph);
            toast(`Pond ${key}: data refreshed`, 'success');
        }
        if(btn) btn.style.animation = '';
    })
    .catch(() => { if(btn) btn.style.animation=''; });
}

function refreshAllIoT() {
    const icon = document.getElementById('refreshIcon');
    if(icon) icon.style.animation = 'spin 1s linear infinite';
    let done = 0;
    Object.keys(PONDS).forEach(key => {
        refreshIoT(key);
        done++;
        if(done === Object.keys(PONDS).length) {
            setTimeout(() => { if(icon) icon.style.animation = ''; }, 1200);
        }
    });
    toast('All IoT sensors refreshed', 'success');
}

// ========================================================
// CHART
// ========================================================
function initChart() {
    const ctx = document.getElementById('metricsChart').getContext('2d');
    metricsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: CHART_DATA.daily.labels,
            datasets: [
                {
                    label: 'Organic %',
                    data: CHART_DATA.daily.organic,
                    borderColor: '#ff3b5c',
                    backgroundColor: 'rgba(255,59,92,.08)',
                    fill: true, tension: 0.4, borderWidth: 2,
                    pointRadius: 0, pointHoverRadius: 5
                },
                {
                    label: 'Temp °C',
                    data: CHART_DATA.daily.temperature,
                    borderColor: '#ffb800',
                    backgroundColor: 'rgba(255,184,0,.08)',
                    fill: true, tension: 0.4, borderWidth: 2,
                    pointRadius: 0, pointHoverRadius: 5
                },
                {
                    label: 'pH',
                    data: CHART_DATA.daily.ph,
                    borderColor: '#39ff8a',
                    backgroundColor: 'rgba(57,255,138,.08)',
                    fill: true, tension: 0.4, borderWidth: 2,
                    pointRadius: 0, pointHoverRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 800, easing: 'easeInOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(11,22,37,.95)',
                    borderColor: 'rgba(0,229,255,.2)',
                    borderWidth: 1,
                    titleColor: '#e8f4ff',
                    bodyColor: '#8ba8c4',
                    padding: 10
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,.04)', drawBorder: false },
                    ticks: { color: '#4a6380', font: { family: "'Space Mono'", size: 10 }, maxTicksLimit: 8 }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,.04)', drawBorder: false },
                    ticks: { color: '#4a6380', font: { family: "'Space Mono'", size: 10 } }
                }
            }
        }
    });
}

function switchPeriod(period, btn) {
    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const d = CHART_DATA[period];
    metricsChart.data.labels = d.labels;
    metricsChart.data.datasets[0].data = d.organic;
    metricsChart.data.datasets[1].data = d.temperature;
    metricsChart.data.datasets[2].data = d.ph;
    metricsChart.update();
}

// ========================================================
// USER MANAGEMENT
// ========================================================
function filterUsers() {
    const q      = document.getElementById('userSearch').value.toLowerCase();
    const role   = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    document.querySelectorAll('#usersTable tbody tr').forEach(row => {
        const name   = (row.cells[1]?.innerText || '').toLowerCase();
        const email  = (row.cells[2]?.innerText || '').toLowerCase();
        const rRole  = row.dataset.role;
        const rStat  = row.dataset.status;
        row.style.display = (
            (name.includes(q) || email.includes(q)) &&
            (role === 'all' || rRole === role) &&
            (status === 'all' || rStat === status)
        ) ? '' : 'none';
    });
    updateSel();
}

function toggleAll() {
    const chk = document.getElementById('selectAll').checked;
    document.querySelectorAll('.ubox').forEach(b => b.checked = chk);
    updateSel();
}

function updateSel() {
    selectedIds = Array.from(document.querySelectorAll('.ubox:checked')).map(b => b.value);
    const n = selectedIds.length;
    document.getElementById('selCount').textContent = `${n} selected`;
    const dis = n === 0;
    document.getElementById('btnActivate').disabled   = dis;
    document.getElementById('btnDeactivate').disabled = dis;
    document.getElementById('btnDelete').disabled     = dis;
}

function bulkDo(type) {
    if(!selectedIds.length) return;
    const labels = { activate:'Activate Users', deactivate:'Deactivate Users', delete:'Delete Users' };
    const msgs   = {
        activate:`Activate ${selectedIds.length} selected user(s)?`,
        deactivate:`Deactivate ${selectedIds.length} selected user(s)?`,
        delete:`Permanently delete ${selectedIds.length} selected user(s)? This cannot be undone.`
    };
    confirm(labels[type], msgs[type], '⚡', () => executeBulk(type));
}

function executeBulk(type) {
    fetchPost('bulk_action', `bulk_type=${type}&user_ids=${JSON.stringify(selectedIds)}`)
    .then(d => { toast(d.message, d.success?'success':'error'); if(d.success) setTimeout(()=>location.reload(),800); });
}

function openAddUser() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('uid').value = '';
    document.getElementById('fName').value = '';
    document.getElementById('fEmail').value = '';
    document.getElementById('fRole').value = 'staff';
    document.getElementById('fPond').value = '';
    document.getElementById('pwField').style.display = 'block';
    openModal('userModal');
}

function openEditUser(id) {
    fetchPost('get_user', `user_id=${id}`)
    .then(d => {
        if(!d.success) { toast(d.message,'error'); return; }
        const u = d.user;
        document.getElementById('modalTitle').textContent = 'Edit User';
        document.getElementById('uid').value   = u.user_id;
        document.getElementById('fName').value  = u.full_name;
        document.getElementById('fEmail').value = u.email;
        document.getElementById('fRole').value  = u.role;
        document.getElementById('fPond').value  = u.assigned_pond || '';
        document.getElementById('pwField').style.display = 'none';
        openModal('userModal');
    });
}

function saveUser() {
    const id = document.getElementById('uid').value;
    const fn = document.getElementById('fName').value.trim();
    const em = document.getElementById('fEmail').value.trim();
    const rl = document.getElementById('fRole').value;
    const ap = document.getElementById('fPond').value;
    if(!fn||!em){toast('Name and email required','error');return;}
    const action = id ? 'edit_user' : 'add_user';
    let body = `action=${action}&full_name=${encodeURIComponent(fn)}&email=${encodeURIComponent(em)}&role=${rl}&assigned_pond=${ap}`;
    if(id) body += `&user_id=${id}`;
    else   body += `&password=${document.getElementById('fPw').value}`;
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body})
    .then(r=>r.json())
    .then(d=>{
        toast(d.message, d.success?'success':'error');
        if(d.success){closeModal('userModal');setTimeout(()=>location.reload(),600);}
    });
}

function doDeactivate(id) {
    confirm('Deactivate User','Are you sure you want to deactivate this user?','🚫', ()=>{
        fetchPost('deactivate_user',`user_id=${id}`)
        .then(d=>{toast(d.message,'warning');setTimeout(()=>location.reload(),600);});
    });
}

function doActivate(id) {
    confirm('Activate User','Are you sure you want to activate this user?','✅', ()=>{
        fetchPost('activate_user',`user_id=${id}`)
        .then(d=>{toast(d.message,'success');setTimeout(()=>location.reload(),600);});
    });
}

function doDelete(id) {
    confirm('Delete User','This action cannot be undone. Permanently delete this user?','🗑️', ()=>{
        fetchPost('delete_user',`user_id=${id}`)
        .then(d=>{toast(d.message,d.success?'success':'error');if(d.success)setTimeout(()=>location.reload(),600);});
    });
}

// ========================================================
// POND MODAL
// ========================================================
function showPondModal(key) {
    const pond = PONDS[key];
    if(!pond) return;
    const color = getStatusColor(pond.status);
    const organic = pond.organic_level;
    const meterClass = organic > 80 ? 'meter-critical' : organic > 60 ? 'meter-warning' : 'meter-safe';

    document.getElementById('pondModalTitle').innerHTML =
        `<i class="fas fa-map-marker-alt" style="color:${color};"></i> ${pond.name}`;

    document.getElementById('pondModalBody').innerHTML = `
        <div style="margin-bottom:1rem;text-align:center;">
            <span class="badge badge-${pond.status}" style="font-size:.8rem;padding:.4rem 1rem;">
                <span class="dot-pulse"></span> ${pond.status.toUpperCase()}
            </span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem;margin-bottom:1rem;">
            <div style="text-align:center;padding:1rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--border);">
                <i class="fas fa-seedling ic-organic" style="font-size:1.5rem;display:block;margin-bottom:.4rem;"></i>
                <div class="metric-val ic-organic" style="font-size:1.4rem;">${pond.organic_level}%</div>
                <div class="metric-lbl">Organic</div>
                <div class="meter" style="margin-top:.6rem;"><div class="meter-fill ${meterClass}" style="width:${pond.organic_level}%;"></div></div>
            </div>
            <div style="text-align:center;padding:1rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--border);">
                <i class="fas fa-thermometer-half ic-temp" style="font-size:1.5rem;display:block;margin-bottom:.4rem;"></i>
                <div class="metric-val ic-temp" style="font-size:1.4rem;">${pond.temperature}°C</div>
                <div class="metric-lbl">Temperature</div>
                <div class="meter" style="margin-top:.6rem;"><div class="meter-fill meter-warning" style="width:${Math.min(100,(pond.temperature-20)*10)}%;"></div></div>
            </div>
            <div style="text-align:center;padding:1rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--border);">
                <i class="fas fa-flask ic-ph" style="font-size:1.5rem;display:block;margin-bottom:.4rem;"></i>
                <div class="metric-val ic-ph" style="font-size:1.4rem;">${pond.ph}</div>
                <div class="metric-lbl">pH Level</div>
                <div class="meter" style="margin-top:.6rem;"><div class="meter-fill meter-safe" style="width:${Math.min(100,pond.ph*10)}%;"></div></div>
            </div>
        </div>
        <div class="pond-detail-grid">
            <div class="detail-row">
                <div class="detail-lbl">Assigned Staff</div>
                <div class="detail-val"><i class="fas fa-user" style="color:var(--accent-cyan);"></i> ${pond.staff}</div>
            </div>
            <div class="detail-row">
                <div class="detail-lbl">Location</div>
                <div class="detail-val"><i class="fas fa-map-pin" style="color:var(--accent-red);"></i> ${pond.location}</div>
            </div>
            <div class="detail-row">
                <div class="detail-lbl">Last Reading</div>
                <div class="detail-val" style="font-family:var(--font-mono);font-size:.8rem;">${new Date(pond.last_reading).toLocaleTimeString('en-US',{hour12:true,timeZone:'Asia/Manila'})}</div>
            </div>
            <div class="detail-row">
                <div class="detail-lbl">Coordinates</div>
                <div class="detail-val" style="font-family:var(--font-mono);font-size:.78rem;">${pond.center[0].toFixed(4)}, ${pond.center[1].toFixed(4)}</div>
            </div>
        </div>
        <div style="display:flex;gap:.5rem;margin-top:1rem;">
            <button class="btn btn-primary btn-sm" style="flex:1;" onclick="closeModal('pondModal');focusPond('${key}');">
                <i class="fas fa-map-marker-alt"></i> Focus on Map
            </button>
            <button class="btn btn-ghost btn-sm" style="flex:1;" onclick="refreshIoT('${key}');closeModal('pondModal');">
                <i class="fas fa-sync-alt"></i> Refresh IoT
            </button>
        </div>
    `;
    openModal('pondModal');
}

// ========================================================
// ALERTS
// ========================================================
function ackAlert(id) {
    fetchPost('acknowledge_alert',`alert_id=${id}`)
    .then(d=>{toast(d.message,'warning');setTimeout(()=>location.reload(),500);});
}

function resolveAlert(id) {
    fetchPost('resolve_alert',`alert_id=${id}`)
    .then(d=>{toast(d.message,'success');setTimeout(()=>location.reload(),500);});
}

// ========================================================
// REPORT
// ========================================================
function genReport(type, btn) {
    document.querySelectorAll('.rpt-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    fetchPost('generate_report',`type=${type}`)
    .then(d => {
        if(!d.success) return;
        const r = d.report;
        const labels = {daily:'Daily Report',weekly:'Weekly Report',monthly:'Monthly Report'};
        const dates  = {
            daily: `<?php echo date('M d, Y'); ?>`,
            weekly: r.week || '',
            monthly: r.month || ''
        };
        const preview = document.querySelector('.rpt-preview');
        preview.innerHTML = `
            <div class="rpt-header">
                <div class="rpt-title"><i class="fas fa-chart-bar" style="color:var(--accent-cyan);"></i> ${labels[type]}</div>
                <div class="rpt-date-badge">${dates[type]}</div>
            </div>
            ${type==='daily'?`
            <div class="rpt-stats">
                <div class="rpt-stat"><div class="rpt-stat-val">${r.total_ponds}</div><div class="rpt-stat-lbl">Total Ponds</div></div>
                <div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--accent-red);">${r.alerts_generated}</div><div class="rpt-stat-lbl">Alerts</div></div>
                <div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--accent-green);">${r.staff_active}</div><div class="rpt-stat-lbl">Active Staff</div></div>
            </div>
            <div class="rpt-status-row">
                <div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--accent-green);">${r.safe_ponds}</div><div class="rpt-status-lbl" style="color:var(--accent-green);">Safe</div></div>
                <div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--accent-amber);">${r.warning_ponds}</div><div class="rpt-status-lbl" style="color:var(--accent-amber);">Warning</div></div>
                <div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--accent-red);">${r.critical_ponds}</div><div class="rpt-status-lbl" style="color:var(--accent-red);">Critical</div></div>
            </div>`:`
            <div class="rpt-stats">
                <div class="rpt-stat"><div class="rpt-stat-val">${r.total_readings}</div><div class="rpt-stat-lbl">Readings</div></div>
                <div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--accent-amber);">${r.incidents}</div><div class="rpt-stat-lbl">Incidents</div></div>
                <div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--accent-green);">${r.resolved}</div><div class="rpt-stat-lbl">Resolved</div></div>
            </div>`}
            <div class="metrics-mini">
                <div class="metric-mini"><i class="fas fa-seedling ic-organic"></i><div><div class="metric-mini-val">${r.avg_organic}%</div><div class="metric-mini-lbl">Avg Organic</div></div></div>
                <div class="metric-mini"><i class="fas fa-thermometer-half ic-temp"></i><div><div class="metric-mini-val">${r.avg_temp}°C</div><div class="metric-mini-lbl">Avg Temp</div></div></div>
                <div class="metric-mini"><i class="fas fa-flask ic-ph"></i><div><div class="metric-mini-val">${r.avg_ph}</div><div class="metric-mini-lbl">Avg pH</div></div></div>
            </div>
            <div class="rpt-dl-row">
                <button class="btn btn-sm" style="flex:1;background:rgba(255,59,92,.12);color:var(--accent-red);border:1px solid rgba(255,59,92,.25);" onclick="toast('PDF downloaded','success')"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn btn-sm" style="flex:1;background:rgba(57,255,138,.12);color:var(--accent-green);border:1px solid rgba(57,255,138,.25);" onclick="toast('Excel downloaded','success')"><i class="fas fa-file-excel"></i> Excel</button>
                <button class="btn btn-sm" style="flex:1;background:rgba(255,184,0,.12);color:var(--accent-amber);border:1px solid rgba(255,184,0,.25);" onclick="toast('CSV downloaded','success')"><i class="fas fa-file-csv"></i> CSV</button>
            </div>
        `;
        toast(`${labels[type]} generated`, 'success');
    });
}

// ========================================================
// MODAL HELPERS
// ========================================================
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }

// Close modal on backdrop click
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if(e.target === m) closeModal(m.id); });
});

// Confirm dialog
function confirm(title, msg, icon, cb) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMsg').textContent   = msg;
    document.getElementById('confirmIcon').textContent  = icon || '⚠️';
    document.getElementById('confirmOk').onclick = () => { closeModal('confirmModal'); cb && cb(); };
    openModal('confirmModal');
}

// ========================================================
// TOAST
// ========================================================
function toast(msg, type = 'info') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    const icons = {success:'check-circle',warning:'exclamation-triangle',error:'times-circle',info:'info-circle'};
    t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity='0';t.style.transform='translateX(40px)';t.style.transition='.3s'; setTimeout(()=>t.remove(),300); }, 3000);
}

// ========================================================
// FETCH HELPER
// ========================================================
function fetchPost(action, body = '') {
    return fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${action}${body ? '&' + body : ''}`
    }).then(r => r.json());
}

// Escape key to close any open modal
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') {
        document.querySelectorAll('.modal.open').forEach(m => m.classList.remove('open'));
    }
});
</script>
</body>
</html>