<?php
session_start();
require_once '../config/config.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$admin_id          = $_SESSION['user_id'];
$admin_name        = $_SESSION['full_name'];
$current_time_12hr = date('h:i:s A');
$current_date      = date('F j, Y');
$current_day       = date('l');
$message           = '';
$message_type      = '';

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
            $message      = $del->execute([$uid]) ? "User deleted successfully!" : "Error deleting user";
            $message_type = $del->execute([$uid]) ? "success" : "error";
        } else {
            $message = "User not found!";
            $message_type = "error";
        }
    }
}

if (isset($_POST['bulk_action']) && isset($_POST['selected_users'])) {
    $action       = $_POST['bulk_action'];
    $sel          = array_diff(array_map('intval', $_POST['selected_users']), [$admin_id]);
    if (!empty($sel)) {
        $ph = implode(',', array_fill(0, count($sel), '?'));
        if ($action == 'delete_selected') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id IN ($ph)");
            if ($stmt->execute($sel)) { $message = "Selected users deleted!"; $message_type = "success"; }
        } elseif ($action == 'activate_selected') {
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id IN ($ph)");
            if ($stmt->execute($sel)) { $message = "Selected users activated!"; $message_type = "success"; }
        } elseif ($action == 'deactivate_selected') {
            $old = date('Y-m-d H:i:s', strtotime('-10 years'));
            $stmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE user_id IN ($ph)");
            $p = array_merge([$old], $sel);
            if ($stmt->execute($p)) { $message = "Selected users deactivated!"; $message_type = "success"; }
        }
    }
}

$users_stmt = $pdo->query("SELECT user_id, full_name, email, role, assigned_pond, created_at, last_login
                            FROM users
                            ORDER BY CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 WHEN 'staff' THEN 3 END, full_name ASC");
$users = [];
while ($row = $users_stmt->fetch()) {
    $status = 'inactive';
    if ($row['last_login']) {
        $diff = (time() - strtotime($row['last_login'])) / 86400;
        if ($diff <= 7) $status = 'active';
    }
    $row['status'] = $status;
    $users[] = $row;
}

$ponds_config = [
    'A-1' => ['name'=>'Tilapia Pond A-1','center'=>[8.3695,124.8645],'staff'=>'Pedro Reyes',
              'bounds'=>[[8.3692,124.8642],[8.3698,124.8640],[8.3700,124.8648],[8.3696,124.8650],[8.3692,124.8642]]],
    'B-2' => ['name'=>'Tilapia Pond B-2','center'=>[8.3688,124.8652],'staff'=>'Ana Lopez',
              'bounds'=>[[8.3685,124.8649],[8.3690,124.8647],[8.3693,124.8654],[8.3688,124.8656],[8.3685,124.8649]]],
    'C-1' => ['name'=>'Tilapia Pond C-1','center'=>[8.3700,124.8660],'staff'=>'Roberto Gomez',
              'bounds'=>[[8.3697,124.8657],[8.3703,124.8655],[8.3705,124.8663],[8.3699,124.8665],[8.3697,124.8657]]],
];

$ponds_stmt = $pdo->query("SELECT pond_id, pond_name, location FROM ponds ORDER BY pond_name");
$ponds_db = [];
while ($row = $ponds_stmt->fetch()) $ponds_db[$row['pond_name']] = $row;

$ponds_data = [];
foreach ($ponds_config as $key => $cfg) {
    $pid    = isset($ponds_db[$key]) ? $ponds_db[$key]['pond_id'] : null;
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
    $ph      = $latest ? floatval($latest['ph_level'])        : round(rand(65, 85)/10, 1);
    $status  = 'safe';
    if ($organic > 80 || $temp > 32 || $ph > 8.5) $status = 'critical';
    elseif ($organic > 60 || $temp > 30 || $ph > 7.8) $status = 'warning';
    $ponds_data[$key] = [
        'pond_id'=>$pid,'pond_name'=>$key,'name'=>$cfg['name'],
        'center'=>$cfg['center'],'bounds'=>$cfg['bounds'],
        'organic_level'=>$organic,'temperature'=>$temp,'ph'=>$ph,
        'status'=>$status,
        'staff'=>$sf_row ? $sf_row['full_name'] : $cfg['staff'],
        'location'=>isset($ponds_db[$key]) ? $ponds_db[$key]['location'] : 'Manolo Fortich',
        'last_reading'=>$latest ? $latest['detected_at'] : date('Y-m-d H:i:s'),
    ];
}

$alerts_stmt = $pdo->query("SELECT n.*, p.pond_name FROM notifications n LEFT JOIN ponds p ON n.pond_id = p.pond_id ORDER BY n.created_at DESC LIMIT 20");
$alerts = [];
if ($alerts_stmt && $alerts_stmt->rowCount() > 0) {
    while ($row = $alerts_stmt->fetch()) {
        $row['type'] = $row['status'] == 'critical' ? 'critical' : ($row['status'] == 'warning' ? 'warning' : 'info');
        $alerts[] = $row;
    }
} else {
    $alerts = [
        ['notification_id'=>1,'pond_name'=>'B-2','message'=>'CRITICAL: High organic level (82%) detected','created_at'=>date('Y-m-d H:i:s',strtotime('-2 minutes')),'status'=>'unread','type'=>'critical'],
        ['notification_id'=>2,'pond_name'=>'A-1','message'=>'WARNING: Organic level approaching threshold','created_at'=>date('Y-m-d H:i:s',strtotime('-15 minutes')),'status'=>'unread','type'=>'warning'],
        ['notification_id'=>3,'pond_name'=>'C-1','message'=>'INFO: Routine maintenance completed','created_at'=>date('Y-m-d H:i:s',strtotime('-1 hour')),'status'=>'read','type'=>'info'],
    ];
}
$new_alerts_count = count(array_filter($alerts, fn($a) => ($a['status'] ?? '') == 'unread'));

$recent_activities = [];
try {
    $aq = "(SELECT CONCAT('User ',full_name,' logged in') AS action, last_login AS timestamp,'login' AS type FROM users WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 5)
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
        ['action'=>'System initialized','timestamp'=>date('Y-m-d H:i:s'),'type'=>'system'],
        ['action'=>'Admin logged in','timestamp'=>date('Y-m-d H:i:s',strtotime('-1 minute')),'type'=>'login'],
        ['action'=>'Daily report generated','timestamp'=>date('Y-m-d H:i:s',strtotime('-5 minutes')),'type'=>'system'],
    ];
}

$chart_data = ['daily'=>['labels'=>[],'organic'=>[],'temperature'=>[],'ph'=>[]]];
try {
    $dq = "SELECT DATE_FORMAT(detected_at,'%H:00') AS hour, AVG(organic_level) AS ao, AVG(water_temperature) AS at2, AVG(ph_level) AS ap
           FROM detections WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
           GROUP BY DATE_FORMAT(detected_at,'%Y-%m-%d %H') ORDER BY detected_at LIMIT 24";
    $ds = $pdo->query($dq);
    if ($ds && $ds->rowCount()>0) {
        while ($row=$ds->fetch()) {
            $chart_data['daily']['labels'][]=$row['hour'];
            $chart_data['daily']['organic'][]=round($row['ao']??0,1);
            $chart_data['daily']['temperature'][]=round($row['at2']??0,1);
            $chart_data['daily']['ph'][]=round($row['ap']??0,1);
        }
    }
} catch(Exception $e) {}
if (empty($chart_data['daily']['labels'])) {
    for ($i=23;$i>=0;$i--) {
        $chart_data['daily']['labels'][]=date('H:00',strtotime("-$i hours"));
        $chart_data['daily']['organic'][]=rand(45,85);
        $chart_data['daily']['temperature'][]=rand(25,33);
        $chart_data['daily']['ph'][]=rand(65,85)/10;
    }
}
$days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
for ($i=0;$i<7;$i++) { $chart_data['weekly']['labels'][]=$days[$i]; $chart_data['weekly']['organic'][]=rand(45,85); $chart_data['weekly']['temperature'][]=rand(25,33); $chart_data['weekly']['ph'][]=rand(65,85)/10; }
for ($i=1;$i<=30;$i++) { $chart_data['monthly']['labels'][]='Day '.$i; $chart_data['monthly']['organic'][]=rand(45,85); $chart_data['monthly']['temperature'][]=rand(25,33); $chart_data['monthly']['ph'][]=rand(65,85)/10; }

$total_ponds    = count($ponds_data);
$safe_ponds     = count(array_filter($ponds_data, fn($p)=>$p['status']=='safe'));
$warning_ponds  = count(array_filter($ponds_data, fn($p)=>$p['status']=='warning'));
$critical_ponds = count(array_filter($ponds_data, fn($p)=>$p['status']=='critical'));
$avg_organic    = $total_ponds>0 ? array_sum(array_column($ponds_data,'organic_level'))/$total_ponds : 0;
$avg_temp       = $total_ponds>0 ? array_sum(array_column($ponds_data,'temperature'))/$total_ponds : 0;
$avg_ph         = $total_ponds>0 ? array_sum(array_column($ponds_data,'ph'))/$total_ponds : 0;
$daily_report   = ['date'=>date('Y-m-d'),'total_ponds'=>$total_ponds,'safe_ponds'=>$safe_ponds,'warning_ponds'=>$warning_ponds,'critical_ponds'=>$critical_ponds,'avg_organic'=>round($avg_organic,1),'avg_temp'=>round($avg_temp,1),'avg_ph'=>round($avg_ph,1),'staff_active'=>count(array_filter($users,fn($u)=>$u['role']=='staff'&&$u['status']=='active')),'alerts_generated'=>$new_alerts_count];
$weekly_report  = ['week'=>date('M d',strtotime('-7 days')).' - '.date('M d, Y'),'total_readings'=>rand(350,450),'avg_organic'=>round($avg_organic+rand(-5,5),1),'avg_temp'=>round($avg_temp+rand(-1,1),1),'avg_ph'=>round(max(6.5,min(8.5,$avg_ph+(rand(-20,20)/100))),1),'incidents'=>rand(3,8),'resolved'=>rand(2,7)];
$monthly_report = ['month'=>date('F Y'),'total_readings'=>rand(1500,2000),'avg_organic'=>round($avg_organic+rand(-3,3),1),'avg_temp'=>round($avg_temp+rand(-1,1),1),'avg_ph'=>round(max(6.5,min(8.5,$avg_ph+(rand(-10,10)/100))),1),'incidents'=>rand(15,25),'resolved'=>rand(12,22)];

if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    switch ($_POST['action']) {
        case 'get_users': echo json_encode($users); exit;
        case 'add_user':
            $fn=$_POST['full_name']??''; $em=$_POST['email']??''; $pw=password_hash($_POST['password']??'default123',PASSWORD_DEFAULT); $rl=$_POST['role']??'staff'; $ap=$_POST['assigned_pond']??null;
            if (!$fn||!$em){echo json_encode(['success'=>false,'message'=>'Name and email required']);exit;}
            if (!filter_var($em,FILTER_VALIDATE_EMAIL)){echo json_encode(['success'=>false,'message'=>'Invalid email']);exit;}
            $chk=$pdo->prepare("SELECT user_id FROM users WHERE email=?"); $chk->execute([$em]);
            if ($chk->rowCount()>0){echo json_encode(['success'=>false,'message'=>'Email exists']);exit;}
            $ins=$pdo->prepare("INSERT INTO users (full_name,email,password,role,assigned_pond,created_at) VALUES (?,?,?,?,?,NOW())");
            echo json_encode($ins->execute([$fn,$em,$pw,$rl,$ap])?['success'=>true,'message'=>'User added','user_id'=>$pdo->lastInsertId()]:['success'=>false,'message'=>'DB error']); exit;
        case 'edit_user':
            $uid=intval($_POST['user_id']??0); $fn=$_POST['full_name']??''; $em=$_POST['email']??''; $rl=$_POST['role']??''; $ap=$_POST['assigned_pond']??null;
            if (!$fn||!$em){echo json_encode(['success'=>false,'message'=>'Name and email required']);exit;}
            $chk=$pdo->prepare("SELECT user_id FROM users WHERE email=? AND user_id!=?"); $chk->execute([$em,$uid]);
            if ($chk->rowCount()>0){echo json_encode(['success'=>false,'message'=>'Email exists']);exit;}
            $upd=$pdo->prepare("UPDATE users SET full_name=?,email=?,role=?,assigned_pond=? WHERE user_id=?");
            echo json_encode(['success'=>$upd->execute([$fn,$em,$rl,$ap,$uid]),'message'=>'User updated']); exit;
        case 'delete_user':
            $uid=intval($_POST['user_id']??0);
            if ($uid==$admin_id){echo json_encode(['success'=>false,'message'=>'Cannot delete own account']);exit;}
            $del=$pdo->prepare("DELETE FROM users WHERE user_id=?"); echo json_encode(['success'=>$del->execute([$uid]),'message'=>'User deleted']); exit;
        case 'deactivate_user':
            $uid=intval($_POST['user_id']??0); $old=date('Y-m-d H:i:s',strtotime('-10 years'));
            $upd=$pdo->prepare("UPDATE users SET last_login=? WHERE user_id=?"); $upd->execute([$old,$uid]); echo json_encode(['success'=>true,'message'=>'User deactivated']); exit;
        case 'activate_user':
            $uid=intval($_POST['user_id']??0); $upd=$pdo->prepare("UPDATE users SET last_login=NOW() WHERE user_id=?"); $upd->execute([$uid]); echo json_encode(['success'=>true,'message'=>'User activated']); exit;
        case 'get_user':
            $uid=intval($_POST['user_id']??0); $stmt=$pdo->prepare("SELECT user_id,full_name,email,role,assigned_pond,last_login FROM users WHERE user_id=?"); $stmt->execute([$uid]); $u=$stmt->fetch();
            echo json_encode($u?['success'=>true,'user'=>$u]:['success'=>false,'message'=>'Not found']); exit;
        case 'get_chart_data':
            $period=$_POST['period']??'daily'; echo json_encode($chart_data[$period]??$chart_data['daily']); exit;
        case 'acknowledge_alert':
            $aid=intval($_POST['alert_id']??0); $pdo->prepare("UPDATE notifications SET status='read' WHERE notification_id=?")->execute([$aid]); echo json_encode(['success'=>true,'message'=>'Acknowledged']); exit;
        case 'resolve_alert':
            $aid=intval($_POST['alert_id']??0); $pdo->prepare("UPDATE notifications SET status='resolved' WHERE notification_id=?")->execute([$aid]); echo json_encode(['success'=>true,'message'=>'Resolved']); exit;
        case 'generate_report':
            $type=$_POST['type']??'daily'; $report=${$type.'_report'}??$daily_report; echo json_encode(['success'=>true,'report'=>$report,'type'=>$type]); exit;
        case 'bulk_action':
            $bt=$_POST['bulk_type']??''; $uids=json_decode($_POST['user_ids']??'[]',true);
            if (empty($uids)){echo json_encode(['success'=>false,'message'=>'No users selected']);exit;}
            $uids=array_diff(array_map('intval',$uids),[$admin_id]);
            if (empty($uids)){echo json_encode(['success'=>false,'message'=>'Cannot act on own account']);exit;}
            $ph=implode(',',array_fill(0,count($uids),'?'));
            if ($bt=='delete'){$stmt=$pdo->prepare("DELETE FROM users WHERE user_id IN ($ph)"); echo json_encode(['success'=>$stmt->execute($uids),'message'=>'Bulk delete done']);}
            elseif ($bt=='activate'){$stmt=$pdo->prepare("UPDATE users SET last_login=NOW() WHERE user_id IN ($ph)"); echo json_encode(['success'=>$stmt->execute($uids),'message'=>'Bulk activate done']);}
            elseif ($bt=='deactivate'){$old=date('Y-m-d H:i:s',strtotime('-10 years')); $stmt=$pdo->prepare("UPDATE users SET last_login=? WHERE user_id IN ($ph)"); echo json_encode(['success'=>$stmt->execute(array_merge([$old],$uids)),'message'=>'Bulk deactivate done']);}
            else echo json_encode(['success'=>false,'message'=>'Invalid action']); exit;
        case 'get_iot_reading':
            $pk=$_POST['pond_key']??'';
            $o=rand(45,92);
            $t=round(rand(250,340)/10,1);
            $p=round(rand(63,90)/10,1);
            $s='safe';
            if ($o>80||$t>32||$p>8.5) $s='critical';
            elseif ($o>60||$t>30||$p>7.8) $s='warning';
            echo json_encode(['success'=>true,'pond'=>$pk,'organic'=>$o,'temp'=>$t,'ph'=>$p,'status'=>$s,'timestamp'=>date('h:i:s A')]); exit;

        case 'get_pond_history':
            $pk = $_POST['pond_key'] ?? '';
            $history = ['labels'=>[],'organic'=>[],'temperature'=>[],'ph'=>[]];
            $pid_row = null;
            try {
                $pid_stmt = $pdo->prepare("SELECT pond_id FROM ponds WHERE pond_name=? LIMIT 1");
                $pid_stmt->execute([$pk]);
                $pid_row = $pid_stmt->fetch();
            } catch(Exception $e) {}
            if ($pid_row) {
                try {
                    $h_stmt = $pdo->prepare("SELECT organic_level, water_temperature, ph_level, detected_at FROM detections WHERE pond_id=? ORDER BY detected_at DESC LIMIT 10");
                    $h_stmt->execute([$pid_row['pond_id']]);
                    $rows = array_reverse($h_stmt->fetchAll());
                    foreach ($rows as $r) {
                        $history['labels'][]      = date('H:i', strtotime($r['detected_at']));
                        $history['organic'][]     = round($r['organic_level'], 1);
                        $history['temperature'][] = round($r['water_temperature'], 1);
                        $history['ph'][]          = round($r['ph_level'], 1);
                    }
                } catch(Exception $e) {}
            }
            if (empty($history['labels'])) {
                for ($i=9; $i>=0; $i--) {
                    $history['labels'][]      = date('H:i', strtotime("-{$i}0 minutes"));
                    $history['organic'][]     = rand(45,85);
                    $history['temperature'][] = rand(25,33);
                    $history['ph'][]          = rand(65,85)/10;
                }
            }
            echo json_encode(['success'=>true,'pond'=>$pk,'history'=>$history]); exit;

        case 'get_system_stats':
            $mem_limit   = ini_get('memory_limit');
            $mem_usage   = round(memory_get_usage(true) / 1048576, 1);
            $mem_peak    = round(memory_get_peak_usage(true) / 1048576, 1);
            $total_users = 0; $total_ponds_db = 0; $total_readings = 0; $total_alerts = 0;
            try {
                $total_users    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $total_ponds_db = $pdo->query("SELECT COUNT(*) FROM ponds")->fetchColumn();
                $total_readings = $pdo->query("SELECT COUNT(*) FROM detections WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
                $total_alerts   = $pdo->query("SELECT COUNT(*) FROM notifications WHERE status='unread'")->fetchColumn();
            } catch(Exception $e) {}
            echo json_encode([
                'success'        => true,
                'php_version'    => PHP_VERSION,
                'memory_usage'   => $mem_usage,
                'memory_peak'    => $mem_peak,
                'memory_limit'   => $mem_limit,
                'server_time'    => date('Y-m-d H:i:s'),
                'ph_time'        => date('D, M d Y h:i:s A'),
                'total_users'    => intval($total_users),
                'total_ponds'    => intval($total_ponds_db),
                'total_readings' => intval($total_readings),
                'total_alerts'   => intval($total_alerts),
                'uptime'         => date('H:i:s', mktime(0,0,intval(shell_exec('cut -d. -f1 /proc/uptime') ?? 0))),
            ]); exit;

        case 'save_settings':
            $_SESSION['notif_critical'] = isset($_POST['notif_critical']) ? 1 : 0;
            $_SESSION['notif_warning']  = isset($_POST['notif_warning'])  ? 1 : 0;
            $_SESSION['notif_info']     = isset($_POST['notif_info'])     ? 1 : 0;
            $_SESSION['refresh_rate']   = intval($_POST['refresh_rate'] ?? 5);
            $_SESSION['theme_mode']     = $_POST['theme_mode'] ?? 'dark';
            echo json_encode(['success'=>true,'message'=>'Settings saved successfully']); exit;

        case 'get_settings':
            echo json_encode([
                'success'        => true,
                'notif_critical' => $_SESSION['notif_critical'] ?? 1,
                'notif_warning'  => $_SESSION['notif_warning']  ?? 1,
                'notif_info'     => $_SESSION['notif_info']     ?? 0,
                'refresh_rate'   => $_SESSION['refresh_rate']   ?? 5,
                'theme_mode'     => $_SESSION['theme_mode']     ?? 'dark',
            ]); exit;

        case 'logout':
            session_destroy(); echo json_encode(['success'=>true,'message'=>'Logged out']); exit;

        case 'get_admin_mgr_notifs':
            try {
                $rows = $pdo->prepare("SELECT mn.*, u.full_name AS manager_name
                    FROM manager_notifications mn
                    LEFT JOIN users u ON mn.sender_id = u.user_id
                    ORDER BY mn.sent_at DESC LIMIT 30");
                $rows->execute();
                echo json_encode(['success'=>true,'notifications'=>$rows->fetchAll()]);
            } catch(Exception $e) {
                echo json_encode(['success'=>true,'notifications'=>[],'note'=>'Table not found — run manager_notifications.sql']);
            }
            exit;

        case 'mark_notif_received':
            $nid = intval($_POST['notif_id'] ?? 0);
            try {
                $pdo->prepare("UPDATE manager_notifications
                    SET status='Received', received_at=NOW()
                    WHERE id=? AND status='Pending'")->execute([$nid]);
                echo json_encode(['success'=>true]);
            } catch(Exception $e) { echo json_encode(['success'=>false]); }
            exit;

        case 'mark_notif_done':
            $nid  = intval($_POST['notif_id'] ?? 0);
            $note = trim($_POST['admin_note'] ?? '');
            try {
                $pdo->prepare("UPDATE manager_notifications
                    SET status='Completed', completed_at=NOW(), admin_note=?
                    WHERE id=?")->execute([$note ?: null, $nid]);
                echo json_encode(['success'=>true,'message'=>'Marked as completed']);
            } catch(Exception $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'get_pending_mgr_count':
            try {
                $cnt = $pdo->query("SELECT COUNT(*) FROM manager_notifications WHERE status='Pending'")->fetchColumn();
                echo json_encode(['success'=>true,'count'=>intval($cnt)]);
            } catch(Exception $e) { echo json_encode(['success'=>true,'count'=>0]); }
            exit;
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
<title>Organic Matter Detection in Tilapia</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
:root{
    --bg-deep:#060d17;--bg-panel:#0b1625;--bg-card:#0f1e30;--bg-elevated:#142235;--bg-hover:#1a2d45;
    --cyan:#00e5ff;--green:#39ff8a;--amber:#ffb800;--red:#ff3b5c;--violet:#b06cff;
    --txt:#e8f4ff;--txt2:#8ba8c4;--muted:#4a6380;
    --bdr:rgba(0,229,255,.12);--bdr-glow:rgba(0,229,255,.35);
    --fd:'Syne',sans-serif;--fm:'Space Mono',monospace;
    --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;
    --nav-h:62px;--sidebar-w:260px;
    --bnav-h:60px;
}

*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{height:100%;scroll-behavior:smooth}
body{
    background:var(--bg-deep);color:var(--txt);
    font-family:var(--fd);min-height:100vh;overflow-x:hidden;
}

body::before{
    content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
    background-image:
        linear-gradient(rgba(0,229,255,.025) 1px,transparent 1px),
        linear-gradient(90deg,rgba(0,229,255,.025) 1px,transparent 1px);
    background-size:44px 44px;
    animation:gridDrift 28s linear infinite;
}

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

.layout{display:flex;min-height:100vh;position:relative}

.sidebar{
    width:var(--sidebar-w);flex-shrink:0;
    background:rgba(9,22,37,.97);backdrop-filter:blur(20px);
    border-right:1px solid var(--bdr);
    position:fixed;top:0;left:0;bottom:0;
    display:flex;flex-direction:column;
    z-index:500;
    transition:transform .3s cubic-bezier(.4,0,.2,1);
    overflow-y:auto;overflow-x:hidden;
}
.sidebar-head{
    padding:1.2rem 1.4rem 1rem;border-bottom:1px solid var(--bdr);
    display:flex;align-items:center;gap:.7rem;min-height:var(--nav-h);
}
.sidebar-logo{
    width:36px;height:36px;border-radius:10px;flex-shrink:0;
    background:linear-gradient(135deg,var(--cyan),var(--violet));
    display:flex;align-items:center;justify-content:center;
    font-size:1rem;color:#000;font-weight:800;
    position:relative;overflow:hidden;
}
.sidebar-logo::after{content:'';position:absolute;top:-50%;left:-60%;width:28%;height:200%;background:rgba(255,255,255,.35);transform:skewX(-20deg);animation:sheen 4s infinite}
.sidebar-title{font-size:.95rem;font-weight:800;letter-spacing:.3px;background:linear-gradient(90deg,var(--cyan),var(--txt));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1.15}
.sidebar-sub{font-size:.62rem;color:var(--muted);font-family:var(--fm);margin-top:.1rem}

.nav-section{padding:.6rem .8rem .3rem;font-family:var(--fm);font-size:.62rem;color:var(--muted);letter-spacing:.8px;text-transform:uppercase}
.nav-item{
    display:flex;align-items:center;gap:.7rem;
    padding:.65rem 1rem .65rem 1.2rem;margin:.1rem .5rem;
    border-radius:var(--r-md);cursor:pointer;transition:.22s;
    font-size:.83rem;font-weight:600;color:var(--txt2);
    border:1px solid transparent;position:relative;
    -webkit-tap-highlight-color:transparent;
    user-select:none;
}
.nav-item:hover{background:var(--bg-hover);color:var(--txt);border-color:var(--bdr)}
.nav-item.active{background:rgba(0,229,255,.1);color:var(--cyan);border-color:rgba(0,229,255,.25)}
.nav-item.active::before{content:'';position:absolute;left:0;top:25%;bottom:25%;width:3px;background:var(--cyan);border-radius:0 3px 3px 0}
.nav-item i{width:18px;text-align:center;font-size:.88rem;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;border-radius:50px;padding:.1rem .5rem;font-family:var(--fm);font-size:.6rem;animation:notifPop 2s infinite}

.sidebar-footer{margin-top:auto;padding:1rem 1.2rem;border-top:1px solid var(--bdr)}
.sidebar-user{display:flex;align-items:center;gap:.7rem;padding:.7rem .8rem;border-radius:var(--r-md);background:var(--bg-elevated);border:1px solid var(--bdr);margin-bottom:.7rem}
.sidebar-avatar{width:34px;height:34px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--cyan),var(--violet));display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;color:#000}
.sidebar-uname{font-size:.8rem;font-weight:700;line-height:1.2}
.sidebar-urole{font-size:.65rem;color:var(--muted);font-family:var(--fm)}
.btn-logout-sidebar{width:100%;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.6rem;border-radius:var(--r-md);background:rgba(255,59,92,.1);border:1px solid rgba(255,59,92,.3);color:var(--red);font-family:var(--fd);font-size:.82rem;font-weight:600;cursor:pointer;transition:.25s;text-decoration:none}
.btn-logout-sidebar:hover{background:rgba(255,59,92,.2)}

.main{margin-left:var(--sidebar-w);flex:1;min-width:0;position:relative}

.topnav{
    display:none;
    background:rgba(6,13,23,.97);backdrop-filter:blur(20px);
    border-bottom:1px solid var(--bdr);
    height:var(--nav-h);padding:0 1rem;
    align-items:center;justify-content:space-between;
    position:sticky;top:0;
    z-index:200;
}
.topnav-brand{display:flex;align-items:center;gap:.6rem}
.topnav-logo{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--cyan),var(--violet));display:flex;align-items:center;justify-content:center;font-size:.95rem;color:#000;font-weight:800;position:relative;overflow:hidden}
.topnav-logo::after{content:'';position:absolute;top:-50%;left:-60%;width:28%;height:200%;background:rgba(255,255,255,.35);transform:skewX(-20deg);animation:sheen 4s infinite}
.topnav-title{font-size:.92rem;font-weight:800;background:linear-gradient(90deg,var(--cyan),var(--txt));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hamburger{width:38px;height:38px;border-radius:9px;border:1px solid var(--bdr);background:var(--bg-elevated);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--txt2);font-size:.9rem;-webkit-tap-highlight-color:transparent;position:relative;min-height:44px;min-width:44px}
.notif-dot-top{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:var(--red);border:2px solid var(--bg-deep);animation:blink 1.5s infinite}

.sidebar-overlay{
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);
    z-index:600;
    backdrop-filter:blur(4px);opacity:0;transition:opacity .3s;
    pointer-events:none;
}
.sidebar-overlay.active{opacity:1;pointer-events:all}

.bottom-nav{
    display:none;
    position:fixed;
    bottom:0;left:0;right:0;
    z-index:9999;
    background:rgba(255, 255, 255, 0.98);
    backdrop-filter:blur(20px);
    border-top:1px solid var(--bdr);
    grid-template-columns:repeat(5,1fr);
    gap:0;
    padding-bottom:env(safe-area-inset-bottom, 0px);
    pointer-events:all;
}
.bnav-item{
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:.22rem;
    padding:.55rem .2rem;
    cursor:pointer;
    font-size:.58rem;
    font-weight:700;
    color:var(--muted);
    letter-spacing:.3px;
    text-transform:uppercase;
    transition:color .2s, background .2s;
    position:relative;
    touch-action:manipulation;
    -webkit-tap-highlight-color:transparent;
    user-select:none;
    min-height:52px;
    overflow:visible;
}
.bnav-item:active{
    background:rgba(0,229,255,.08);
    color:var(--cyan);
}
.bnav-item.active{
    color:var(--cyan);
    background:rgba(0,229,255,.07);
}
.bnav-item.active::before{
    content:'';
    position:absolute;
    top:0;left:50%;
    transform:translateX(-50%);
    width:28px;height:2px;
    background:var(--cyan);
    border-radius:0 0 2px 2px;
}
.bnav-item i{font-size:1.15rem;line-height:1;pointer-events:none}
.bnav-item span{pointer-events:none;line-height:1}
.bnav-badge{
    position:absolute;top:5px;right:calc(50% - 16px);
    background:var(--red);color:#fff;border-radius:50px;
    padding:.05rem .35rem;font-family:var(--fm);font-size:.55rem;
    min-width:16px;text-align:center;pointer-events:none;
}

.content{
    padding:1.4rem 1.6rem 2rem;
    max-width:1600px;width:100%;
}

.section-panel{display:none;animation:fadeUp .35s ease both}
.section-panel.active{display:block}

.topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.7rem;padding:.7rem 1.3rem;background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-lg);margin-bottom:1.3rem}
.topbar-left{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
.topbar-day{font-size:1rem;font-weight:700}
.topbar-date{font-family:var(--fm);font-size:.73rem;color:var(--txt2)}
.sys-tag{display:inline-flex;align-items:center;gap:.35rem;font-family:var(--fm);font-size:.66rem;padding:.26rem .7rem;border-radius:4px;letter-spacing:.4px}
.sys-tag.green{background:rgba(57,255,138,.08);border:1px solid rgba(57,255,138,.22);color:var(--green)}
.sys-tag.cyan{background:rgba(0,229,255,.08);border:1px solid rgba(0,229,255,.2);color:var(--cyan)}
.blink-dot{width:5px;height:5px;border-radius:50%;background:currentColor;animation:blink 1.5s infinite;display:inline-block}
.nav-clock{font-family:var(--fm);font-size:.76rem;color:var(--cyan);background:rgba(0,229,255,.07);border:1px solid rgba(0,229,255,.2);padding:.3rem .7rem;border-radius:6px;letter-spacing:.8px;white-space:nowrap}
.iot-live{display:inline-flex;align-items:center;gap:.35rem;font-family:var(--fm);font-size:.65rem;color:var(--green)}
.iot-live span{width:6px;height:6px;border-radius:50%;background:var(--green);animation:blink 1.2s infinite;display:inline-block}

.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.3rem}
.kpi{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-lg);padding:1.2rem 1.3rem;position:relative;overflow:hidden;cursor:default;transition:.35s}
.kpi:hover{transform:translateY(-3px);border-color:var(--bdr-glow);box-shadow:0 0 28px rgba(0,229,255,.12)}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--kc,var(--cyan)),transparent)}
.kpi-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.95rem;color:var(--kc,var(--cyan));background:rgba(0,0,0,.3);border:1px solid currentColor;opacity:.9;margin-bottom:.7rem}
.kpi-val{font-family:var(--fm);font-size:1.8rem;font-weight:700;color:var(--kc,var(--cyan));line-height:1;margin-bottom:.2rem}
.kpi-label{font-size:.68rem;color:var(--muted);letter-spacing:.5px;text-transform:uppercase}
.kpi-corner{position:absolute;top:.8rem;right:.8rem;font-size:.58rem;font-family:var(--fm);color:var(--kc);opacity:.45;letter-spacing:.4px}

.card{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1.3rem 1.4rem;margin-bottom:1.2rem}
.card:hover{border-color:rgba(0,229,255,.2)}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem}
.card-title{display:flex;align-items:center;gap:.55rem;font-size:.86rem;font-weight:700;letter-spacing:.4px;text-transform:uppercase}
.card-title i{color:var(--cyan)}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}

.badge{display:inline-flex;align-items:center;gap:.28rem;padding:.22rem .65rem;border-radius:4px;font-size:.67rem;font-weight:700;font-family:var(--fm);letter-spacing:.3px;text-transform:uppercase}
.badge-active{background:rgba(57,255,138,.12);color:var(--green);border:1px solid rgba(57,255,138,.25)}
.badge-inactive{background:rgba(255,59,92,.12);color:var(--red);border:1px solid rgba(255,59,92,.25)}
.badge-admin{background:rgba(176,108,255,.15);color:var(--violet);border:1px solid rgba(176,108,255,.3)}
.badge-manager{background:rgba(0,229,255,.12);color:var(--cyan);border:1px solid var(--bdr)}
.badge-staff{background:rgba(57,255,138,.12);color:var(--green);border:1px solid rgba(57,255,138,.25)}
.badge-safe{background:rgba(57,255,138,.1);color:var(--green);border:1px solid rgba(57,255,138,.2)}
.badge-warning{background:rgba(255,184,0,.1);color:var(--amber);border:1px solid rgba(255,184,0,.2)}
.badge-critical{background:rgba(255,59,92,.1);color:var(--red);border:1px solid rgba(255,59,92,.2);animation:blink 1.4s infinite}
.badge-unread{background:rgba(255,59,92,.1);color:var(--red);border:1px solid rgba(255,59,92,.2)}
.badge-read{background:rgba(255,184,0,.1);color:var(--amber);border:1px solid rgba(255,184,0,.2)}
.badge-resolved{background:rgba(57,255,138,.1);color:var(--green);border:1px solid rgba(57,255,138,.2)}
.badge-info{background:rgba(0,229,255,.1);color:var(--cyan);border:1px solid var(--bdr)}
.dot-blink{width:5px;height:5px;border-radius:50%;background:currentColor;animation:blink 1.5s infinite;display:inline-block}

.btn{display:inline-flex;align-items:center;gap:.4rem;border:none;border-radius:var(--r-sm);padding:.45rem 1rem;font-family:var(--fd);font-size:.8rem;font-weight:600;cursor:pointer;transition:.22s;letter-spacing:.3px;white-space:nowrap;-webkit-tap-highlight-color:transparent;touch-action:manipulation;user-select:none}
.btn:active{transform:scale(.97)}
.btn:disabled{opacity:.4;cursor:not-allowed;pointer-events:none}
.btn-primary{background:var(--cyan);color:#000}
.btn-primary:hover{background:#00ccee}
.btn-success{background:rgba(57,255,138,.15);color:var(--green);border:1px solid rgba(57,255,138,.3)}
.btn-warning{background:rgba(255,184,0,.15);color:var(--amber);border:1px solid rgba(255,184,0,.3)}
.btn-danger{background:rgba(255,59,92,.15);color:var(--red);border:1px solid rgba(255,59,92,.3)}
.btn-ghost{background:var(--bg-elevated);color:var(--txt2);border:1px solid var(--bdr)}
.btn-ghost:hover{border-color:var(--cyan);color:var(--cyan)}
.btn-sm{padding:.28rem .65rem;font-size:.72rem}

.search-row{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.8rem}
.inp{background:var(--bg-elevated);border:1px solid var(--bdr);color:var(--txt);border-radius:var(--r-sm);padding:.5rem .85rem;font-family:var(--fd);font-size:.82rem;outline:none;transition:.25s;-webkit-appearance:none}
.inp:focus{border-color:var(--cyan);box-shadow:0 0 0 3px rgba(0,229,255,.1)}
.inp::placeholder{color:var(--muted)}
.inp option{background:var(--bg-panel)}
.inp-search{flex:1;min-width:160px}
.bulk-row{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.8rem;padding:.6rem .8rem;background:var(--bg-elevated);border-radius:var(--r-sm);border:1px solid var(--bdr)}
.sel-all-label{font-size:.82rem;display:flex;align-items:center;gap:.4rem;cursor:pointer}
.sel-count{font-family:var(--fm);font-size:.7rem;color:var(--muted);margin-left:.3rem}
input[type=checkbox]{accent-color:var(--cyan);width:15px;height:15px;cursor:pointer}
.tbl-wrap{overflow-x:auto;margin-top:.5rem;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;min-width:640px}
th{text-align:left;padding:.6rem .75rem;font-size:.67rem;font-weight:700;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;border-bottom:1px solid var(--bdr);font-family:var(--fm);white-space:nowrap}
td{padding:.62rem .75rem;border-bottom:1px solid rgba(255,255,255,.04);font-size:.82rem;vertical-align:middle}
tr:hover td{background:rgba(0,229,255,.03)}
tr:last-child td{border-bottom:none}

.user-mobile-card{display:none;background:var(--bg-elevated);border:1px solid var(--bdr);border-radius:var(--r-lg);padding:1rem;margin-bottom:.7rem;animation:slideIn .3s ease both}
.umc-head{display:flex;align-items:center;gap:.7rem;margin-bottom:.7rem}
.umc-avatar{width:40px;height:40px;border-radius:10px;background:var(--bg-card);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;color:var(--cyan);flex-shrink:0}
.umc-name{font-size:.9rem;font-weight:700}
.umc-email{font-family:var(--fm);font-size:.68rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.umc-row{display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:.79rem}
.umc-row:last-child{border-bottom:none}
.umc-lbl{color:var(--muted);font-family:var(--fm);font-size:.65rem;text-transform:uppercase;letter-spacing:.4px}
.umc-actions{display:flex;gap:.4rem;margin-top:.7rem;flex-wrap:wrap}

.staff-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.2rem}
.staff-card{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1.2rem 1.1rem;cursor:pointer;transition:.3s;position:relative;overflow:hidden;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
.staff-card:hover{transform:translateY(-4px);border-color:var(--cyan)}
.staff-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--cyan),transparent);opacity:0;transition:.3s}
.staff-card:hover::before{opacity:1}
.staff-avatar{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--cyan),var(--violet));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;color:#000;margin-bottom:.9rem;position:relative;overflow:hidden}
.staff-avatar::after{content:'';position:absolute;top:-50%;left:-60%;width:28%;height:200%;background:rgba(255,255,255,.35);transform:skewX(-20deg);animation:sheen 5s infinite}
.staff-name{font-size:.9rem;font-weight:700;margin-bottom:.3rem}
.staff-pond-tag{display:inline-flex;align-items:center;gap:.3rem;background:rgba(0,229,255,.1);border:1px solid rgba(0,229,255,.22);color:var(--cyan);padding:.22rem .65rem;border-radius:4px;font-family:var(--fm);font-size:.68rem;margin-bottom:.6rem}
.staff-foot{display:flex;align-items:center;justify-content:space-between;font-size:.73rem}

.pond-cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.2rem}
.pond-card{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1.1rem;cursor:pointer;position:relative;overflow:hidden;transition:.3s;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
.pond-card:hover{transform:translateY(-3px);border-color:var(--bdr-glow)}
.pond-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.pond-card.safe::before{background:var(--green)}
.pond-card.warning::before{background:var(--amber)}
.pond-card.critical::before{background:var(--red);animation:pulseGlow 2s infinite}
.pond-card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.55rem}
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
.pond-ts{font-family:var(--fm);font-size:.62rem;color:var(--muted);display:flex;align-items:center;gap:.35rem;margin-top:.4rem}

#map{height:420px;border-radius:var(--r-lg);overflow:hidden;border:1px solid var(--bdr);position:relative}
.map-scan{position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(0,229,255,.5),transparent);pointer-events:none;z-index:500;animation:scanline 6s linear infinite}
.map-legend{display:flex;gap:.7rem;align-items:center;font-family:var(--fm);font-size:.68rem;flex-wrap:wrap}
.leg-item{display:flex;align-items:center;gap:.3rem;color:var(--txt2)}
.leg-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}

.leaflet-container{background:#060d17!important;font-family:var(--fd)!important}
.leaflet-popup-content-wrapper{background:var(--bg-panel)!important;border:1px solid var(--bdr)!important;border-radius:var(--r-md)!important;box-shadow:0 8px 32px rgba(0,0,0,.5)!important;color:var(--txt)!important}
.leaflet-popup-tip{background:var(--bg-panel)!important}
.leaflet-control-zoom a{background:var(--bg-panel)!important;color:var(--txt)!important;border-color:var(--bdr)!important}
.leaflet-control-attribution{background:rgba(6,13,23,.8)!important;color:var(--muted)!important}

.chart-wrap{height:240px;margin-top:.8rem}
.period-tabs{display:flex;gap:.4rem}
.period-btn{font-family:var(--fm);font-size:.66rem;padding:.26rem .65rem;border-radius:4px;background:var(--bg-elevated);border:1px solid var(--bdr);color:var(--muted);cursor:pointer;transition:.2s;letter-spacing:.3px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
.period-btn.active,.period-btn:hover{background:rgba(0,229,255,.1);border-color:var(--cyan);color:var(--cyan)}

.alert-item{display:flex;gap:.75rem;padding:.8rem;border-radius:var(--r-md);margin-bottom:.45rem;border-left:3px solid transparent;background:rgba(0,0,0,.2);cursor:pointer;transition:.22s;animation:slideIn .3s ease both;-webkit-tap-highlight-color:transparent}
.alert-item:hover{background:var(--bg-elevated)}
.alert-item.critical{border-left-color:var(--red)}
.alert-item.warning{border-left-color:var(--amber)}
.alert-item.info{border-left-color:var(--cyan)}
.alert-icon{font-size:.95rem;flex-shrink:0;margin-top:.1rem}
.alert-icon.critical{color:var(--red)}.alert-icon.warning{color:var(--amber)}.alert-icon.info{color:var(--cyan)}
.alert-pond{font-weight:700;font-size:.82rem}
.alert-msg{font-size:.77rem;color:var(--txt2);margin:.12rem 0}
.alert-foot{display:flex;justify-content:space-between;align-items:center}
.alert-time{font-family:var(--fm);font-size:.62rem;color:var(--muted)}

.alert-kpi-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:.7rem;margin-bottom:1.2rem}
.alert-kpi{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-lg);padding:.85rem 1rem;text-align:center;position:relative;overflow:hidden;transition:.3s;cursor:default}
.alert-kpi:hover{transform:translateY(-2px);border-color:var(--bdr-glow)}
.alert-kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--akc,var(--cyan)),transparent)}
.alert-kpi-val{font-family:var(--fm);font-size:1.6rem;font-weight:700;color:var(--akc,var(--cyan));line-height:1;margin-bottom:.2rem}
.alert-kpi-lbl{font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.alert-toolbar{display:flex;gap:.6rem;margin-bottom:.8rem;flex-wrap:wrap;align-items:center}
.alert-filter-tabs{display:flex;gap:.3rem;flex-wrap:wrap;flex:1;min-width:0}
.alert-ftab{background:var(--bg-elevated);border:1px solid var(--bdr);color:var(--muted);border-radius:6px;padding:.28rem .7rem;font-family:var(--fm);font-size:.66rem;cursor:pointer;transition:.2s;white-space:nowrap;letter-spacing:.3px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
.alert-ftab:hover{border-color:var(--cyan);color:var(--cyan)}
.alert-ftab.active{background:rgba(0,229,255,.1);border-color:var(--cyan);color:var(--cyan)}
.alert-ftab-count{background:rgba(0,0,0,.3);border-radius:50px;padding:.05rem .35rem;font-size:.58rem;margin-left:.25rem}
.alert-search-inp{width:180px;flex-shrink:0}
.alert-icon-col{display:flex;flex-direction:column;align-items:center;gap:.3rem;flex-shrink:0;padding-top:.1rem}
.alert-unread-pip{width:7px;height:7px;border-radius:50%;background:var(--red);animation:blink 1.5s infinite;display:block}
.alert-header-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:.2rem;gap:.4rem;flex-wrap:wrap}
.alert-ago{color:var(--muted);font-size:.6rem;font-family:var(--fm)}
.alert-actions{display:flex;gap:.3rem;align-items:center;flex-wrap:wrap}
.al-resolved{opacity:.55}
.al-resolved:hover{opacity:.75}
@media(max-width:768px){.alert-kpi-strip{grid-template-columns:repeat(3,1fr);gap:.5rem}.alert-kpi{padding:.65rem .5rem}.alert-kpi-val{font-size:1.3rem}.alert-toolbar{flex-direction:column;align-items:stretch}.alert-search-inp{width:100%}.alert-filter-tabs{gap:.25rem}.alert-ftab{font-size:.6rem;padding:.24rem .55rem}}
@media(max-width:480px){.alert-kpi-strip{grid-template-columns:repeat(2,1fr)}.alert-kpi-strip .alert-kpi:last-child{grid-column:1/-1}}

.rpt-type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-bottom:1rem}
.rpt-type-btn{background:var(--bg-elevated);border:1px solid var(--bdr);border-radius:var(--r-md);padding:.9rem .6rem;text-align:center;cursor:pointer;transition:.3s;color:var(--txt);-webkit-tap-highlight-color:transparent;touch-action:manipulation}
.rpt-type-btn:hover,.rpt-type-btn.active{border-color:var(--cyan);background:rgba(0,229,255,.07)}
.rpt-type-icon{font-size:1.3rem;margin-bottom:.4rem}
.rpt-type-label{font-size:.76rem;font-weight:700;display:block}
.rpt-type-sub{font-size:.61rem;color:var(--muted);font-family:var(--fm)}
.rpt-preview{background:rgba(0,0,0,.2);border-radius:var(--r-lg);padding:1.2rem;border:1px solid var(--bdr)}
.rpt-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem;padding-bottom:.6rem;border-bottom:1px solid var(--bdr)}
.rpt-title{font-size:.85rem;font-weight:700}
.rpt-date-badge{font-family:var(--fm);font-size:.64rem;padding:.18rem .55rem;border-radius:4px;background:var(--bg-elevated);color:var(--muted);border:1px solid var(--bdr)}
.rpt-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:.8rem}
.rpt-stat{background:var(--bg-elevated);border-radius:var(--r-sm);padding:.7rem;text-align:center;border:1px solid var(--bdr)}
.rpt-stat-val{font-family:var(--fm);font-size:1.4rem;font-weight:700;color:var(--cyan);line-height:1;margin-bottom:.22rem}
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
.rpt-dl-row{display:flex;gap:.5rem;padding-top:.8rem;border-top:1px solid var(--bdr)}

.act-item{display:flex;align-items:center;gap:.7rem;padding:.6rem .4rem;border-bottom:1px solid rgba(255,255,255,.04);font-size:.8rem}
.act-item:last-child{border-bottom:none}
.act-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0}
.act-icon.login{background:rgba(0,229,255,.12);color:var(--cyan)}.act-icon.reading{background:rgba(255,184,0,.12);color:var(--amber)}.act-icon.alert{background:rgba(255,59,92,.12);color:var(--red)}.act-icon.system{background:rgba(57,255,138,.12);color:var(--green)}
.act-text{flex:1;color:var(--txt2)}.act-time{font-family:var(--fm);font-size:.63rem;color:var(--muted);white-space:nowrap}

.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);backdrop-filter:blur(8px);z-index:10000;align-items:center;justify-content:center;padding:1rem}
.modal.open{display:flex}
.modal-box{background:var(--bg-panel);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1.8rem;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;animation:fadeUp .3s ease;-webkit-overflow-scrolling:touch}
.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;padding-bottom:.7rem;border-bottom:1px solid var(--bdr)}
.modal-title{font-size:.95rem;font-weight:700}
.modal-close{background:none;border:none;color:var(--muted);font-size:1.3rem;cursor:pointer;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:6px;transition:.2s;-webkit-tap-highlight-color:transparent;touch-action:manipulation;min-width:36px;min-height:36px}
.modal-close:hover{color:var(--red);background:rgba(255,59,92,.1)}
.form-group{margin-bottom:.9rem}
.form-label{display:block;font-size:.68rem;font-weight:700;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:.3rem;font-family:var(--fm)}
.form-ctrl{width:100%;padding:.6rem .85rem;background:var(--bg-elevated);border:1px solid var(--bdr);border-radius:var(--r-sm);color:var(--txt);font-family:var(--fd);font-size:.85rem;outline:none;transition:.25s;-webkit-appearance:none}
.form-ctrl:focus{border-color:var(--cyan);box-shadow:0 0 0 3px rgba(0,229,255,.1)}
.form-ctrl option{background:var(--bg-panel)}
.form-actions{display:flex;gap:.6rem;justify-content:flex-end;margin-top:1.2rem;padding-top:.8rem;border-top:1px solid var(--bdr)}

.confirm-box{background:var(--bg-panel);border:1px solid rgba(255,59,92,.3);border-radius:var(--r-xl);padding:1.8rem;max-width:360px;width:100%;animation:fadeUp .3s ease}
.confirm-icon{font-size:2rem;margin-bottom:.7rem;text-align:center}
.confirm-title{font-size:.95rem;font-weight:700;text-align:center;margin-bottom:.5rem}
.confirm-msg{font-size:.81rem;color:var(--txt2);text-align:center;line-height:1.5;margin-bottom:1.2rem}
.confirm-btns{display:flex;gap:.6rem}
.confirm-btns .btn{flex:1;justify-content:center;min-height:44px}

.pond-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-top:.8rem}
.detail-row{background:var(--bg-elevated);border-radius:var(--r-sm);padding:.65rem .8rem;border:1px solid var(--bdr)}
.detail-lbl{font-size:.62rem;color:var(--muted);font-family:var(--fm);text-transform:uppercase;letter-spacing:.4px;margin-bottom:.2rem}
.detail-val{font-size:.85rem;font-weight:600}
.meter{height:6px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden;margin-top:.5rem}
.meter-fill{height:100%;border-radius:3px;transition:width 1.2s ease}
.meter-safe{background:linear-gradient(90deg,var(--green),rgba(57,255,138,.5))}
.meter-warning{background:linear-gradient(90deg,var(--amber),rgba(255,184,0,.5))}
.meter-critical{background:linear-gradient(90deg,var(--red),rgba(255,59,92,.5))}

.sys-alert{display:flex;justify-content:space-between;align-items:center;padding:.75rem 1.1rem;border-radius:var(--r-md);margin-bottom:1rem;font-size:.83rem;animation:fadeUp .4s ease}
.sys-alert.success{background:rgba(57,255,138,.1);border:1px solid rgba(57,255,138,.3);color:var(--green)}
.sys-alert.error{background:rgba(255,59,92,.1);border:1px solid rgba(255,59,92,.3);color:var(--red)}
.sys-alert-close{background:none;border:none;color:inherit;font-size:1rem;cursor:pointer;min-width:28px;min-height:28px}

.toast-wrap{position:fixed;top:74px;right:18px;z-index:10001;display:flex;flex-direction:column;gap:.45rem;pointer-events:none}
.toast{display:flex;align-items:center;gap:.55rem;padding:.7rem 1rem;border-radius:var(--r-md);font-size:.8rem;font-weight:600;min-width:240px;animation:toastIn .3s ease;box-shadow:0 8px 24px rgba(0,0,0,.4);pointer-events:all}
.toast.success{background:rgba(57,255,138,.13);border:1px solid rgba(57,255,138,.3);color:var(--green)}
.toast.warning{background:rgba(255,184,0,.13);border:1px solid rgba(255,184,0,.3);color:var(--amber)}
.toast.critical{background:rgba(255,59,92,.13);border:1px solid rgba(255,59,92,.3);color:var(--red)}
.toast.info{background:rgba(0,229,255,.1);border:1px solid var(--bdr);color:var(--cyan)}

.adm-notif-list{display:flex;flex-direction:column;gap:.6rem}
.adm-notif-item{background:var(--bg-elevated);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1rem 1.1rem;border-left:4px solid var(--muted);transition:.25s;animation:slideIn .3s ease both}
.adm-notif-item.status-Pending  {border-left-color:var(--amber)}
.adm-notif-item.status-Received {border-left-color:var(--cyan)}
.adm-notif-item.status-Completed{border-left-color:var(--green);opacity:.7}
.adm-notif-item:hover{border-color:var(--bdr-glow);background:var(--bg-hover)}
.adm-notif-head{display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;flex-wrap:wrap;margin-bottom:.35rem}
.adm-notif-sender{font-size:.82rem;font-weight:700;display:flex;align-items:center;gap:.4rem}
.adm-notif-msg{font-size:.82rem;color:var(--txt2);margin:.25rem 0;line-height:1.5}
.adm-notif-meta{font-family:var(--fm);font-size:.62rem;color:var(--muted);display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.3rem}
.adm-notif-foot{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.4rem;margin-top:.65rem;padding-top:.6rem;border-top:1px solid var(--bdr)}
.adm-notif-note-inp{flex:1;min-width:160px;background:var(--bg-card);border:1px solid var(--bdr);color:var(--txt);border-radius:var(--r-sm);padding:.38rem .7rem;font-family:var(--fd);font-size:.78rem;outline:none;transition:.25s}
.adm-notif-note-inp:focus{border-color:var(--cyan);box-shadow:0 0 0 3px rgba(0,229,255,.08)}
.adm-notif-note-inp::placeholder{color:var(--muted)}
.status-pill{display:inline-flex;align-items:center;gap:.28rem;padding:.18rem .6rem;border-radius:50px;font-family:var(--fm);font-size:.6rem;font-weight:700;letter-spacing:.3px;white-space:nowrap}
.status-pill.Pending  {background:rgba(255,184,0,.12);color:var(--amber);border:1px solid rgba(255,184,0,.25)}
.status-pill.Received {background:rgba(0,229,255,.1); color:var(--cyan); border:1px solid rgba(0,229,255,.2)}
.status-pill.Completed{background:rgba(57,255,138,.1);color:var(--green);border:1px solid rgba(57,255,138,.2)}
.priority-pip{display:inline-block;width:7px;height:7px;border-radius:50%;flex-shrink:0;vertical-align:middle}
.pri-low     {background:var(--muted)}
.pri-normal  {background:var(--cyan)}
.pri-high    {background:var(--amber)}
.pri-critical{background:var(--red);animation:blink 1.5s infinite}
.adm-inbox-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-bottom:1rem}
.adm-inbox-kpi{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-lg);padding:.7rem 1rem;text-align:center;position:relative;overflow:hidden}
.adm-inbox-kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--ikc,var(--cyan)),transparent)}
.adm-inbox-kpi-val{font-family:var(--fm);font-size:1.5rem;font-weight:700;color:var(--ikc,var(--cyan));line-height:1;margin-bottom:.15rem}
.adm-inbox-kpi-lbl{font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
@media(max-width:768px){.adm-inbox-strip{grid-template-columns:repeat(3,1fr)}.adm-notif-foot{flex-direction:column;align-items:stretch}.adm-notif-note-inp{width:100%}}

.dash-footer{padding:.75rem 1.4rem;background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-lg);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;font-family:var(--fm);font-size:.67rem;color:var(--muted);margin-top:.5rem}

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

@media(max-width:768px){
    :root{--nav-h:58px}
    .sidebar{transform:translateX(-100%);z-index:700}
    .sidebar.open{transform:translateX(0);z-index:700}
    .sidebar-overlay{display:block}
    .topnav{display:flex}
    .main{margin-left:0}
    .bottom-nav{display:grid}
    .content{padding:1rem 1rem 0;padding-bottom:calc(var(--bnav-h) + env(safe-area-inset-bottom, 12px) + 16px)}
    .topbar{padding:.6rem .9rem}
    .topbar-left{gap:.5rem}
    .kpi-grid{grid-template-columns:repeat(2,1fr);gap:.7rem}
    .kpi{padding:.95rem 1rem}
    .kpi-val{font-size:1.5rem}
    .grid-2{grid-template-columns:1fr}
    .staff-grid{grid-template-columns:1fr}
    .pond-cards-grid{grid-template-columns:1fr}
    .tbl-wrap table{display:none}
    .user-mobile-card{display:block}
    .bulk-row{gap:.4rem}
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

@media(max-width:480px){
    .kpi-grid{grid-template-columns:1fr 1fr;gap:.5rem}
    .kpi{padding:.8rem .85rem}
    .kpi-val{font-size:1.35rem}
    .kpi-icon{width:32px;height:32px;font-size:.82rem;margin-bottom:.5rem}
    .rpt-stats{grid-template-columns:1fr}
    .rpt-type-grid{grid-template-columns:1fr 1fr}
    #map{height:260px}
    .bnav-item{font-size:.52rem}
    .bnav-item i{font-size:1rem}
}

@media(hover:none) and (pointer:coarse){
    .btn{min-height:44px}
    .inp{font-size:16px}
    .form-ctrl{font-size:16px}
    .bnav-item{min-height:52px}
    .nav-item{min-height:44px}
}

@media(prefers-reduced-motion:reduce){
    *,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}
    .blink-dot,.dot-blink,.nav-badge{animation:none!important}
    body::before{animation:none!important}
    .map-scan{display:none}
}

.session-bar{
    display:flex;align-items:center;gap:.7rem;padding:.45rem 1rem;
    background:rgba(255,184,0,.07);border:1px solid rgba(255,184,0,.2);
    border-radius:var(--r-sm);font-family:var(--fm);font-size:.68rem;color:var(--amber);
    margin-bottom:.8rem;flex-wrap:wrap;
}
.session-bar i{color:var(--amber);flex-shrink:0}
.session-bar .session-time{font-weight:700;letter-spacing:.5px}
.session-bar.warning{background:rgba(255,59,92,.1);border-color:rgba(255,59,92,.3);color:var(--red)}
.session-progress{flex:1;height:4px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden;min-width:80px}
.session-progress-fill{height:100%;border-radius:2px;background:var(--amber);transition:width 1s linear}
.session-bar.warning .session-progress-fill{background:var(--red);animation:blink 1s infinite}

.net-banner{
    display:none;position:fixed;top:var(--nav-h);left:0;right:0;z-index:8000;
    padding:.5rem 1rem;text-align:center;font-family:var(--fm);font-size:.72rem;
    font-weight:700;letter-spacing:.5px;animation:fadeUp .3s ease;
}
.net-banner.offline{background:rgba(255,59,92,.95);color:#fff;display:block}
.net-banner.online-back{background:rgba(57,255,138,.9);color:#000;display:block}

.iot-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:1rem;
    margin-bottom:1.2rem;
    align-items:start;
    grid-template-columns:repeat(3,minmax(0,1fr));
}
.iot-card{
    background:var(--bg-card);
    border:1px solid var(--bdr);
    border-radius:var(--r-xl);
    padding:1.1rem;
    transition:.3s;
    display:flex;
    flex-direction:column;
    min-height:0;
    overflow:hidden;
    width:100%;
    min-width:0;
    box-sizing:border-box;
}
.iot-card:hover{border-color:var(--bdr-glow)}
.iot-card::before{
    content:'';display:block;height:3px;border-radius:3px 3px 0 0;
    margin:-1.1rem -1.1rem .8rem -1.1rem;
    background:linear-gradient(90deg,transparent,var(--cyan),transparent);
    opacity:.4;
}
.iot-card-head{
    display:flex;justify-content:space-between;align-items:flex-start;
    margin-bottom:.6rem;gap:.4rem;
    min-width:0;overflow:hidden;
}
.iot-card-title{
    font-size:.75rem;font-weight:700;letter-spacing:.2px;text-transform:uppercase;
    display:flex;align-items:center;gap:.35rem;
    min-width:0;overflow:hidden;
    white-space:nowrap;text-overflow:ellipsis;
    flex:1;
}
.iot-card-head > div:last-child{ flex-shrink:0 }
.iot-mini-chart{
    height:85px;margin:.4rem 0;flex-shrink:0;
    width:100%;max-width:100%;overflow:hidden;
}
.iot-mini-chart canvas{ max-width:100%!important; }
.iot-val-row{
    display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem;margin:.5rem 0;
    min-width:0;width:100%;
}
.iot-val-chip{
    text-align:center;padding:.38rem .2rem;
    background:rgba(0,0,0,.25);border-radius:7px;
    min-width:0;overflow:hidden;
}
.iot-val-chip .val{
    font-family:var(--fm);font-size:.82rem;font-weight:700;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    display:block;
}
.iot-val-chip .lbl{
    font-size:.52rem;color:var(--muted);margin-top:.1rem;
    letter-spacing:.3px;display:block;
}
.iot-table-wrap{
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
    border-radius:var(--r-sm);
    border:1px solid var(--bdr);
    margin-top:.5rem;
    flex:1;
    max-width:100%;
    width:100%;
}
.iot-readings-table{
    width:100%;
    table-layout:fixed;
    border-collapse:collapse;
    font-family:var(--fm);
    font-size:.65rem;
}
.iot-readings-table th{
    color:var(--muted);font-weight:700;
    padding:.3rem .4rem;
    border-bottom:1px solid var(--bdr);
    text-align:left;font-size:.58rem;
    letter-spacing:.4px;text-transform:uppercase;
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.iot-readings-table td{
    padding:.28rem .4rem;
    border-bottom:1px solid rgba(255,255,255,.03);
    color:var(--txt2);
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.iot-readings-table tr:last-child td{border-bottom:none}
.iot-readings-table tr:hover td{background:rgba(0,229,255,.04);color:var(--txt)}
.sys-stats-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem;margin-bottom:1rem}
.sys-stat{background:var(--bg-elevated);border:1px solid var(--bdr);border-radius:var(--r-md);padding:.75rem .9rem;display:flex;align-items:center;gap:.65rem;min-width:0;overflow:hidden}
.sys-stat-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.82rem;flex-shrink:0}
.sys-stat-val{font-family:var(--fm);font-size:.95rem;font-weight:700;line-height:1;margin-bottom:.15rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sys-stat-lbl{font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
@media(max-width:1100px){
    .iot-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .sys-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:768px){
    .iot-grid{grid-template-columns:minmax(0,1fr)}
    .sys-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:480px){
    .sys-stats-grid{grid-template-columns:minmax(0,1fr)}
    .iot-val-chip .val{font-size:.78rem}
}

.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.2rem}
.settings-block{background:var(--bg-card);border:1px solid var(--bdr);border-radius:var(--r-xl);padding:1.3rem}
.settings-block-title{font-size:.78rem;font-weight:700;letter-spacing:.4px;text-transform:uppercase;margin-bottom:1rem;display:flex;align-items:center;gap:.45rem;padding-bottom:.6rem;border-bottom:1px solid var(--bdr)}
.settings-block-title i{color:var(--cyan)}
.setting-row{display:flex;justify-content:space-between;align-items:center;padding:.55rem 0;border-bottom:1px solid rgba(255,255,255,.04)}
.setting-row:last-child{border-bottom:none}
.setting-label{font-size:.82rem;font-weight:500}
.setting-desc{font-size:.68rem;color:var(--muted);margin-top:.1rem;font-family:var(--fm)}
.toggle{position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background:var(--bg-elevated);border:1px solid var(--bdr);border-radius:22px;transition:.3s}
.toggle-slider::before{content:'';position:absolute;height:16px;width:16px;left:2px;bottom:2px;background:var(--muted);border-radius:50%;transition:.3s}
.toggle input:checked + .toggle-slider{background:rgba(0,229,255,.15);border-color:var(--cyan)}
.toggle input:checked + .toggle-slider::before{transform:translateX(18px);background:var(--cyan)}
.range-wrap{display:flex;align-items:center;gap:.6rem;flex:1;justify-content:flex-end}
.range-input{-webkit-appearance:none;appearance:none;width:100px;height:4px;border-radius:2px;background:var(--bg-elevated);outline:none;cursor:pointer}
.range-input::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:16px;height:16px;border-radius:50%;background:var(--cyan);cursor:pointer;border:2px solid var(--bg-panel)}
.range-val{font-family:var(--fm);font-size:.72rem;color:var(--cyan);min-width:28px;text-align:right}
.about-block{background:var(--bg-elevated);border:1px solid var(--bdr);border-radius:var(--r-lg);padding:1rem 1.2rem}
.about-row{display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:.8rem}
.about-row:last-child{border-bottom:none}
.about-row span:first-child{color:var(--muted);font-family:var(--fm);font-size:.7rem}
.about-row span:last-child{font-weight:600}
@media(max-width:768px){
    .settings-grid{grid-template-columns:1fr}
}

@keyframes shimmer{0%{background-position:-400px 0}100%{background-position:400px 0}}
.skeleton{background:linear-gradient(90deg,var(--bg-elevated) 25%,var(--bg-hover) 50%,var(--bg-elevated) 75%);background-size:800px 100%;animation:shimmer 1.5s infinite;border-radius:4px;display:inline-block}

@media print{
    .sidebar,.topnav,.bottom-nav,.toast-wrap,.modal,.session-bar,.net-banner{display:none!important}
    .main{margin-left:0!important}
    .content{padding:.5rem!important}
    body{background:#fff!important;color:#000!important}
    .card{background:#fff!important;border:1px solid #ccc!important;break-inside:avoid;margin-bottom:.5rem}
    .kpi{background:#f5f5f5!important;border:1px solid #ddd!important}
    .badge{border:1px solid #999!important}
    .btn{display:none!important}
    #map{height:280px!important}
    .section-panel{display:block!important}
}

@media(min-width:1400px){
    :root{--sidebar-w:280px}
    .content{padding:1.6rem 2rem 2rem}
    .staff-grid{grid-template-columns:repeat(4,1fr)}
    .pond-cards-grid{grid-template-columns:repeat(4,1fr)}
    .iot-grid{grid-template-columns:repeat(3,1fr)}
    .sys-stats-grid{grid-template-columns:repeat(3,1fr)}
}

/* ─── WHITE THEME OVERRIDE ───────────────────────────────────────────── */
:root {
    --bg-deep:     #f0f4f8;
    --bg-panel:    #ffffff;
    --bg-card:     #ffffff;
    --bg-elevated: #f5f8fc;
    --bg-hover:    #e8f0fb;
    --txt:  #0f1e30;
    --txt2: #3a5470;
    --muted:#6b8aaa;
    --bdr:      rgba(0,120,200,.13);
    --bdr-glow: rgba(0,180,255,.35);
}
body::before {
    background-image:
        linear-gradient(rgba(0,150,220,.045) 1px,transparent 1px),
        linear-gradient(90deg,rgba(0,150,220,.045) 1px,transparent 1px);
}
.sidebar{background:rgba(255,255,255,.98);border-right:1px solid var(--bdr);box-shadow:2px 0 16px rgba(0,100,180,.07)}
.sidebar-sub{color:#6b8aaa}
.nav-section{color:#a0b8d0}
.nav-item{color:#3a5470}
.nav-item:hover{background:#eef4fb;color:#0f1e30}
.nav-item.active{background:rgba(0,180,255,.09);color:var(--cyan)}
.sidebar-user{background:#f0f6ff;border-color:var(--bdr)}
.sidebar-uname{color:#0f1e30}
.sidebar-urole{color:#6b8aaa}
.topnav{background:rgba(255,255,255,.97);border-bottom:1px solid var(--bdr);box-shadow:0 2px 10px rgba(0,100,180,.06)}
.topbar{background:#ffffff;border:1px solid var(--bdr);box-shadow:0 2px 8px rgba(0,100,180,.05)}
.topbar-day{color:#0f1e30}
.topbar-date{color:#6b8aaa}
.card{background:#ffffff;border:1px solid var(--bdr);box-shadow:0 2px 12px rgba(0,100,180,.06)}
.card:hover{border-color:rgba(0,180,255,.28)}
.kpi{background:#ffffff;border:1px solid var(--bdr);box-shadow:0 2px 10px rgba(0,100,180,.06)}
.kpi:hover{box-shadow:0 4px 20px rgba(0,180,255,.14);border-color:var(--bdr-glow)}
.kpi-label{color:#6b8aaa}
.kpi-corner{opacity:.35}
.bottom-nav{background:rgba(255,255,255,.99);border-top:1px solid var(--bdr);box-shadow:0 -2px 12px rgba(0,100,180,.08)}
.bnav-item{color:#6b8aaa}
.bnav-item.active{color:var(--cyan);background:rgba(0,180,255,.07)}
th{color:#6b8aaa;border-bottom:1px solid var(--bdr)}
td{border-bottom:1px solid rgba(0,100,180,.07)}
tr:hover td{background:rgba(0,180,255,.04)}
.inp,.form-ctrl{background:#f5f8fc;border:1px solid var(--bdr);color:#0f1e30}
.inp:focus,.form-ctrl:focus{border-color:var(--cyan);box-shadow:0 0 0 3px rgba(0,200,255,.1)}
.inp::placeholder,.form-ctrl::placeholder{color:#a0b8d0}
.btn-ghost{background:#f0f6ff;color:#3a5470;border:1px solid var(--bdr)}
.btn-ghost:hover{border-color:var(--cyan);color:var(--cyan);background:#e8f4fb}
.modal-box,.confirm-box{background:#ffffff;border:1px solid var(--bdr);box-shadow:0 16px 48px rgba(0,100,180,.15)}
.modal-head{border-bottom:1px solid var(--bdr)}
.modal-title{color:#0f1e30}
.modal-close{color:#6b8aaa}
.modal-close:hover{color:var(--red);background:rgba(255,59,92,.08)}
.pond-card,.staff-card,.iot-card{background:#ffffff;border:1px solid var(--bdr);box-shadow:0 2px 8px rgba(0,100,180,.05)}
.metric-chip{background:rgba(0,130,200,.06)}
.metric-chip:hover{background:rgba(0,180,255,.1)}
.alert-item{background:#f7fafd}
.alert-item:hover{background:#eef4fb}
.settings-block{background:#ffffff;border:1px solid var(--bdr)}
.about-block{background:#f5f8fc;border:1px solid var(--bdr)}
.act-text{color:#3a5470}
.rpt-preview{background:#f5f8fc;border:1px solid var(--bdr)}
.rpt-stat{background:#eef4fb;border:1px solid var(--bdr)}
.iot-table-wrap{border:1px solid var(--bdr)}
.iot-readings-table td{color:#3a5470}
.iot-readings-table tr:hover td{background:rgba(0,180,255,.05);color:#0f1e30}
::-webkit-scrollbar-track{background:#f0f4f8}
::-webkit-scrollbar-thumb{background:var(--cyan)}
.dash-footer{background:#ffffff;border:1px solid var(--bdr);box-shadow:0 -1px 8px rgba(0,100,180,.05)}
.bulk-row{background:#f5f8fc;border:1px solid var(--bdr)}
.sys-stat{background:#f5f8fc;border:1px solid var(--bdr)}
.adm-notif-item{background:#f7fafd;border-color:var(--bdr)}
.adm-notif-item:hover{background:#eef4fb}
.adm-notif-note-inp{background:#f0f6ff;border:1px solid var(--bdr);color:#0f1e30}
.adm-notif-note-inp::placeholder{color:#a0b8d0}
.leaflet-popup-content-wrapper{background:#ffffff!important;border:1px solid var(--bdr)!important;color:#0f1e30!important}
.leaflet-popup-tip{background:#ffffff!important}
.leaflet-control-zoom a{background:#ffffff!important;color:#0f1e30!important;border-color:var(--bdr)!important}
/* ─── END WHITE THEME ────────────────────────────────────────────────── */
</style>

<div class="toast-wrap" id="toastWrap"></div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
        <div class="sidebar-logo"><i class="fas fa-fish"></i></div>
        <div>
            <div class="sidebar-title">OrgaMatter</div>
            <div class="sidebar-sub">Tilapia Detection · BUK</div>
        </div>
    </div>

    <div class="nav-section">Main</div>
    <div class="nav-item active" onclick="showSection('overview',this)"><i class="fas fa-chart-pie"></i> Overview <span class="nav-badge" id="sideAlerts"><?php echo $new_alerts_count > 0 ? $new_alerts_count : ''; ?></span></div>
    <div class="nav-item" onclick="showSection('users',this)"><i class="fas fa-users-cog"></i> Manage Users</div>
    <div class="nav-item" onclick="showSection('ponds',this)"><i class="fas fa-water"></i> Pond Status</div>
    <div class="nav-item" onclick="showSection('map',this)"><i class="fas fa-map"></i> Live Map</div>

    <div class="nav-section">Analytics</div>
    <div class="nav-item" onclick="showSection('charts',this)"><i class="fas fa-chart-area"></i> Metrics Chart</div>
    <div class="nav-item" onclick="showSection('alerts',this)"><i class="fas fa-bell"></i> Alerts</div>
    <div class="nav-item" onclick="showSection('reports',this)"><i class="fas fa-file-alt"></i> Reports</div>
    <div class="nav-item" onclick="showSection('activities',this)"><i class="fas fa-history"></i> Activities</div>
    <div class="nav-item" onclick="showSection('iot',this)"><i class="fas fa-microchip"></i> IOT Panel</div>

    <div class="nav-section">System</div>
    <div class="nav-item" onclick="showSection('mgr-inbox',this)">
        <i class="fas fa-inbox"></i> Manager Requests
        <span class="nav-badge" id="admInboxBadge" style="display:none"></span>
    </div>
    <div class="nav-item" onclick="showSection('settings',this)"><i class="fas fa-cog"></i> Settings</div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?php $i=''; foreach(explode(' ',$admin_name) as $n) $i.=strtoupper(substr($n,0,1)); echo $i?:'A'; ?>
            </div>
            <div>
                <div class="sidebar-uname"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="sidebar-urole">Administrator</div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn-logout-sidebar" onclick="return confirm('Logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<div class="layout">
<div class="main">

<nav class="topnav">
    <div class="topnav-brand">
        <div class="topnav-logo"><i class="fas fa-fish"></i></div>
        <div class="topnav-title">OrgaMatter</div>
    </div>
    <div style="display:flex;align-items:center;gap:.6rem;">
        <div class="nav-clock" id="topnavClock"><?php echo $current_time_12hr; ?></div>
        <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
            <i class="fas fa-bars"></i>
            <?php if($new_alerts_count > 0): ?><div class="notif-dot-top"></div><?php endif; ?>
        </button>
    </div>
</nav>

<div class="content">

<?php if (!empty($message)): ?>
<div class="sys-alert <?php echo $message_type; ?>" id="sysAlert">
    <span><?php echo htmlspecialchars($message); ?></span>
    <button class="sys-alert-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<div class="topbar">
    <div class="topbar-left">
        <div>
            <div class="topbar-day"><?php echo $current_day; ?></div>
            <div class="topbar-date"><?php echo $current_date; ?></div>
        </div>
        <div class="sys-tag green"><span class="blink-dot"></span> ONLINE</div>
        <div class="sys-tag cyan"><i class="fas fa-database" style="font-size:.6rem"></i> DB</div>
    </div>
    <div style="display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;">
        <div class="iot-live"><span></span> LIVE</div>
        <div class="nav-clock" id="mainClock"><?php echo $current_time_12hr; ?></div>
    </div>
</div>

<div class="net-banner" id="netBanner"></div>

<div class="section-panel active" id="sec-overview">
    <div class="kpi-grid">
        <div class="kpi" style="--kc:var(--cyan)"><div class="kpi-corner">USERS</div><div class="kpi-icon"><i class="fas fa-users"></i></div><div class="kpi-val"><?php echo count($users); ?></div><div class="kpi-label">Total Users</div></div>
        <div class="kpi" style="--kc:var(--green)"><div class="kpi-corner">PONDS</div><div class="kpi-icon"><i class="fas fa-water"></i></div><div class="kpi-val"><?php echo $total_ponds; ?></div><div class="kpi-label">Active Ponds</div></div>
        <div class="kpi" style="--kc:var(--red)"><div class="kpi-corner">ALERTS</div><div class="kpi-icon"><i class="fas fa-bell"></i></div><div class="kpi-val"><?php echo $new_alerts_count; ?></div><div class="kpi-label">New Alerts</div></div>
        <div class="kpi" style="--kc:var(--amber)"><div class="kpi-corner">TODAY</div><div class="kpi-icon"><i class="fas fa-chart-line"></i></div><div class="kpi-val"><?php echo rand(180,420); ?></div><div class="kpi-label">Readings</div></div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-user-tie"></i> Staff Assignments</div>
            <div class="badge badge-info"><?php echo count(array_filter($users,fn($u)=>$u['role']=='staff')); ?> STAFF</div>
        </div>
        <div class="staff-grid">
            <?php
            $staff_list = array_filter($users, fn($u) => $u['role'] == 'staff');
            foreach ($staff_list as $s):
                $init=''; foreach(explode(' ',$s['full_name']) as $n) $init.=strtoupper(substr($n,0,1));
                $pstatus = ($ponds_data[$s['assigned_pond']] ?? null)['status'] ?? 'safe';
            ?>
            <div class="staff-card" onclick="gotoMap('<?php echo $s['assigned_pond']; ?>')">
                <div class="staff-avatar"><?php echo $init?:'?'; ?></div>
                <div class="staff-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                <div style="font-size:.72rem;color:var(--muted);font-family:var(--fm);margin-bottom:.5rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($s['email']); ?></div>
                <div class="staff-pond-tag"><i class="fas fa-water"></i> Pond <?php echo $s['assigned_pond']??'N/A'; ?> <span class="badge badge-<?php echo $pstatus; ?>" style="font-size:.58rem;margin-left:.3rem"><?php echo strtoupper($pstatus); ?></span></div>
                <div class="staff-foot">
                    <div style="display:flex;align-items:center;gap:.35rem;color:var(--green);font-size:.73rem"><span class="blink-dot" style="color:var(--green)"></span> Active</div>
                    <div style="font-family:var(--fm);font-size:.63rem;color:var(--muted)"><?php echo $s['last_login']?date('h:i A',strtotime($s['last_login'])):'Never'; ?></div>
                </div>
            </div>
            <?php endforeach; if(empty($staff_list)): ?><div style="grid-column:1/-1;text-align:center;padding:1.5rem;color:var(--muted)">No staff found</div><?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-layer-group"></i> Pond Overview</div>
            <div style="display:flex;align-items:center;gap:.6rem">
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
                <div style="font-size:.74rem;color:var(--muted);margin:.4rem 0"><i class="fas fa-user"></i> <?php echo $pond['staff']; ?> · <i class="fas fa-map-pin"></i> <?php echo $pond['location']; ?></div>
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
                <div style="display:flex;gap:.4rem;margin-top:.6rem" onclick="event.stopPropagation()">
                    <button class="btn btn-ghost btn-sm" onclick="gotoMap('<?php echo $key; ?>')"><i class="fas fa-map-marker-alt" style="color:var(--cyan)"></i></button>
                    <button class="btn btn-ghost btn-sm" onclick="refreshPond('<?php echo $key; ?>')"><i class="fas fa-sync-alt"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-bell"></i> Recent Alerts</div>
            <button class="btn btn-ghost btn-sm" onclick="showSection('alerts')"><i class="fas fa-arrow-right"></i> All</button>
        </div>
        <div style="max-height:220px;overflow-y:auto">
            <?php foreach(array_slice($alerts,0,3) as $al): ?>
            <div class="alert-item <?php echo $al['type']; ?>">
                <i class="fas fa-<?php echo $al['type']=='critical'?'exclamation-circle':'exclamation-triangle'; ?> alert-icon <?php echo $al['type']; ?>"></i>
                <div style="flex:1">
                    <div style="display:flex;justify-content:space-between"><div class="alert-pond">Pond <?php echo htmlspecialchars($al['pond_name']); ?></div><div class="alert-time"><?php echo date('h:i A',strtotime($al['created_at'])); ?></div></div>
                    <div class="alert-msg"><?php echo htmlspecialchars($al['message']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="section-panel" id="sec-users">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-users-cog"></i> Manage Users</div>
            <button class="btn btn-primary btn-sm" onclick="openAddUser()"><i class="fas fa-plus"></i> Add User</button>
        </div>
        <div class="bulk-row">
            <label class="sel-all-label"><input type="checkbox" id="selectAll" onchange="toggleAll()"> Select All</label>
            <button class="btn btn-success btn-sm" id="btnActivate" disabled onclick="bulkDo('activate')"><i class="fas fa-check"></i> Activate</button>
            <button class="btn btn-warning btn-sm" id="btnDeactivate" disabled onclick="bulkDo('deactivate')"><i class="fas fa-ban"></i> Deactivate</button>
            <button class="btn btn-danger btn-sm" id="btnDelete" disabled onclick="bulkDo('delete')"><i class="fas fa-trash"></i> Delete</button>
            <span class="sel-count" id="selCount">0 selected</span>
        </div>
        <div class="search-row">
            <input class="inp inp-search" type="text" id="userSearch" placeholder="Search name or email…" oninput="filterUsers()">
            <select class="inp" id="roleFilter" onchange="filterUsers()"><option value="all">All Roles</option><option value="admin">Admin</option><option value="manager">Manager</option><option value="staff">Staff</option></select>
            <select class="inp" id="statusFilter" onchange="filterUsers()"><option value="all">All Status</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
        <div class="tbl-wrap">
            <table id="usersTable">
                <thead><tr><th width="30px"></th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Pond</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr data-role="<?php echo $u['role']; ?>" data-status="<?php echo $u['status']; ?>">
                        <td><?php if($u['user_id']!=$admin_id): ?><input type="checkbox" class="ubox" value="<?php echo $u['user_id']; ?>" onchange="updateSel()"><?php endif; ?></td>
                        <td><div style="display:flex;align-items:center;gap:.45rem"><div style="width:28px;height:28px;border-radius:7px;background:var(--bg-elevated);border:1px solid var(--bdr);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;color:var(--cyan);flex-shrink:0"><?php echo strtoupper(substr($u['full_name'],0,1)); ?></div><?php echo htmlspecialchars($u['full_name']); ?></div></td>
                        <td style="font-family:var(--fm);font-size:.74rem;color:var(--txt2)"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo strtoupper($u['role']); ?></span></td>
                        <td><span class="badge badge-<?php echo $u['status']; ?>"><span class="dot-blink"></span><?php echo strtoupper($u['status']); ?></span></td>
                        <td style="font-family:var(--fm);font-size:.71rem;color:var(--muted)"><?php echo $u['last_login']?date('M d, h:i A',strtotime($u['last_login'])):'Never'; ?></td>
                        <td><?php echo $u['assigned_pond']?'<span class="badge badge-safe">'.htmlspecialchars($u['assigned_pond']).'</span>':'<span style="color:var(--muted)">—</span>'; ?></td>
                        <td><div style="display:flex;gap:.3rem;flex-wrap:wrap">
                            <button class="btn btn-ghost btn-sm" onclick="openEditUser(<?php echo $u['user_id']; ?>)"><i class="fas fa-edit"></i></button>
                            <?php if($u['user_id']!=$admin_id): ?>
                            <?php if($u['status']=='active'): ?><button class="btn btn-warning btn-sm" onclick="doDeactivate(<?php echo $u['user_id']; ?>)"><i class="fas fa-ban"></i></button>
                            <?php else: ?><button class="btn btn-success btn-sm" onclick="doActivate(<?php echo $u['user_id']; ?>)"><i class="fas fa-check"></i></button><?php endif; ?>
                            <button class="btn btn-danger btn-sm" onclick="doDelete(<?php echo $u['user_id']; ?>)"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                            <?php if($u['assigned_pond']): ?><button class="btn btn-ghost btn-sm" onclick="gotoMap('<?php echo $u['assigned_pond']; ?>')"><i class="fas fa-map-marker-alt" style="color:var(--cyan)"></i></button><?php endif; ?>
                        </div></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="userMobileCards">
            <?php foreach($users as $u):
                $init=''; foreach(explode(' ',$u['full_name']) as $n) $init.=strtoupper(substr($n,0,1));
            ?>
            <div class="user-mobile-card" data-role="<?php echo $u['role']; ?>" data-status="<?php echo $u['status']; ?>">
                <div class="umc-head">
                    <?php if($u['user_id']!=$admin_id): ?><input type="checkbox" class="ubox" value="<?php echo $u['user_id']; ?>" onchange="updateSel()"><?php endif; ?>
                    <div class="umc-avatar"><?php echo $init?:'?'; ?></div>
                    <div style="flex:1;min-width:0"><div class="umc-name"><?php echo htmlspecialchars($u['full_name']); ?></div><div class="umc-email"><?php echo htmlspecialchars($u['email']); ?></div></div>
                    <span class="badge badge-<?php echo $u['role']; ?>"><?php echo strtoupper($u['role']); ?></span>
                </div>
                <div class="umc-row"><span class="umc-lbl">Status</span><span class="badge badge-<?php echo $u['status']; ?>"><span class="dot-blink"></span><?php echo strtoupper($u['status']); ?></span></div>
                <div class="umc-row"><span class="umc-lbl">Last Login</span><span style="font-family:var(--fm);font-size:.73rem;color:var(--muted)"><?php echo $u['last_login']?date('M d, h:i A',strtotime($u['last_login'])):'Never'; ?></span></div>
                <div class="umc-row"><span class="umc-lbl">Pond</span><?php echo $u['assigned_pond']?'<span class="badge badge-safe">'.htmlspecialchars($u['assigned_pond']).'</span>':'<span style="color:var(--muted)">—</span>'; ?></div>
                <div class="umc-actions">
                    <button class="btn btn-ghost btn-sm" onclick="openEditUser(<?php echo $u['user_id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                    <?php if($u['user_id']!=$admin_id): ?>
                    <?php if($u['status']=='active'): ?><button class="btn btn-warning btn-sm" onclick="doDeactivate(<?php echo $u['user_id']; ?>)"><i class="fas fa-ban"></i> Deactivate</button>
                    <?php else: ?><button class="btn btn-success btn-sm" onclick="doActivate(<?php echo $u['user_id']; ?>)"><i class="fas fa-check"></i> Activate</button><?php endif; ?>
                    <button class="btn btn-danger btn-sm" onclick="doDelete(<?php echo $u['user_id']; ?>)"><i class="fas fa-trash"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

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
                <div style="font-size:.74rem;color:var(--muted);margin:.4rem 0"><i class="fas fa-user"></i> <?php echo $pond['staff']; ?> · <i class="fas fa-map-pin"></i> <?php echo $pond['location']; ?></div>
                <div class="metrics-row">
                    <div class="metric-chip"><i class="fas fa-seedling ic-organic"></i><div class="metric-val ic-organic"><?php echo $pond['organic_level']; ?>%</div><div class="metric-lbl">Organic</div></div>
                    <div class="metric-chip"><i class="fas fa-thermometer-half ic-temp"></i><div class="metric-val ic-temp"><?php echo $pond['temperature']; ?>°C</div><div class="metric-lbl">Temp</div></div>
                    <div class="metric-chip"><i class="fas fa-flask ic-ph"></i><div class="metric-val ic-ph"><?php echo $pond['ph']; ?></div><div class="metric-lbl">pH</div></div>
                </div>
                <div class="pond-bar-wrap"><div class="pond-bar-label"><span>Organic Level</span><span><?php echo $pond['organic_level']; ?>%</span></div><div class="pond-bar"><div class="pond-bar-fill" style="width:<?php echo min(100,$pond['organic_level']); ?>%;background:<?php echo $bc; ?>"></div></div></div>
                <div class="pond-ts"><i class="far fa-clock"></i><?php echo date('h:i:s A',strtotime($pond['last_reading'])); ?></div>
                <div style="display:flex;gap:.4rem;margin-top:.6rem" onclick="event.stopPropagation()">
                    <button class="btn btn-ghost btn-sm" onclick="gotoMap('<?php echo $key; ?>')"><i class="fas fa-map-marker-alt" style="color:var(--cyan)"></i> Map</button>
                    <button class="btn btn-ghost btn-sm" onclick="refreshPond('<?php echo $key; ?>')"><i class="fas fa-sync-alt"></i> Refresh</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="section-panel" id="sec-map">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-map"></i> Polygon Pond Map — Manolo Fortich</div>
            <div class="map-legend"><div class="leg-item"><span class="leg-dot" style="background:var(--green)"></span>Safe</div><div class="leg-item"><span class="leg-dot" style="background:var(--amber)"></span>Warning</div><div class="leg-item"><span class="leg-dot" style="background:var(--red)"></span>Critical</div></div>
        </div>
        <div style="position:relative"><div id="map"></div><div class="map-scan"></div></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem">
            <div class="iot-live"><span></span> REAL-TIME · PH TIME</div>
            <div style="font-family:var(--fm);font-size:.68rem;color:var(--muted)" id="mapTs"><?php echo date('h:i:s A'); ?></div>
        </div>
    </div>
</div>

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
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem">
            <div style="display:flex;gap:1rem;font-size:.66rem;font-family:var(--fm)"><span style="color:var(--red)"><i class="fas fa-circle"></i> Organic</span><span style="color:var(--amber)"><i class="fas fa-circle"></i> Temp</span><span style="color:var(--green)"><i class="fas fa-circle"></i> pH</span></div>
            <div style="font-family:var(--fm);font-size:.67rem;color:var(--muted)" id="chartTs"><?php echo date('h:i:s A'); ?></div>
        </div>
    </div>
</div>

<div class="section-panel" id="sec-alerts">

    <div class="alert-kpi-strip">
        <div class="alert-kpi" id="akpi-total">
            <div class="alert-kpi-val" id="akpi-total-val"><?php echo count($alerts); ?></div>
            <div class="alert-kpi-lbl">Total</div>
        </div>
        <div class="alert-kpi" id="akpi-unread" style="--akc:var(--red)">
            <div class="alert-kpi-val" id="akpi-unread-val"><?php echo $new_alerts_count; ?></div>
            <div class="alert-kpi-lbl">Unread</div>
        </div>
        <div class="alert-kpi" id="akpi-critical" style="--akc:var(--red)">
            <div class="alert-kpi-val" id="akpi-critical-val"><?php echo count(array_filter($alerts,fn($a)=>$a['type']==='critical')); ?></div>
            <div class="alert-kpi-lbl">Critical</div>
        </div>
        <div class="alert-kpi" id="akpi-warning" style="--akc:var(--amber)">
            <div class="alert-kpi-val" id="akpi-warning-val"><?php echo count(array_filter($alerts,fn($a)=>$a['type']==='warning')); ?></div>
            <div class="alert-kpi-lbl">Warning</div>
        </div>
        <div class="alert-kpi" id="akpi-resolved" style="--akc:var(--green)">
            <div class="alert-kpi-val" id="akpi-resolved-val"><?php echo count(array_filter($alerts,fn($a)=>$a['status']==='resolved')); ?></div>
            <div class="alert-kpi-lbl">Resolved</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-bell"></i> Alerts & Notifications</div>
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                <span class="badge badge-unread" id="alertBadge"><?php echo $new_alerts_count; ?> NEW</span>
                <button class="btn btn-success btn-sm" id="btnMarkAll" onclick="markAllRead()" <?php echo $new_alerts_count==0?'disabled':''; ?>>
                    <i class="fas fa-check-double"></i> Mark All Read
                </button>
            </div>
        </div>

        <div class="alert-toolbar">
            <div class="alert-filter-tabs" id="alertFilterTabs">
                <button class="alert-ftab active" data-filter="all"    onclick="filterAlerts('all',this)">All <span class="alert-ftab-count" id="fc-all"><?php echo count($alerts); ?></span></button>
                <button class="alert-ftab" data-filter="unread"  onclick="filterAlerts('unread',this)">Unread <span class="alert-ftab-count" id="fc-unread"><?php echo $new_alerts_count; ?></span></button>
                <button class="alert-ftab" data-filter="critical" onclick="filterAlerts('critical',this)">Critical <span class="alert-ftab-count" id="fc-critical"><?php echo count(array_filter($alerts,fn($a)=>$a['type']==='critical')); ?></span></button>
                <button class="alert-ftab" data-filter="warning"  onclick="filterAlerts('warning',this)">Warning <span class="alert-ftab-count" id="fc-warning"><?php echo count(array_filter($alerts,fn($a)=>$a['type']==='warning')); ?></span></button>
                <button class="alert-ftab" data-filter="resolved" onclick="filterAlerts('resolved',this)">Resolved <span class="alert-ftab-count" id="fc-resolved"><?php echo count(array_filter($alerts,fn($a)=>$a['status']==='resolved')); ?></span></button>
            </div>
            <input class="inp alert-search-inp" type="text" id="alertSearch" placeholder="Search alerts…" oninput="searchAlerts(this.value)">
        </div>

        <div id="alertsList">
            <?php foreach($alerts as $al):
                $icon = $al['type']==='critical' ? 'exclamation-circle' : ($al['type']==='warning' ? 'exclamation-triangle' : 'info-circle');
                $is_unread = $al['status']==='unread';
                $is_resolved = $al['status']==='resolved';
            ?>
            <div class="alert-item <?php echo $al['type']; ?> <?php echo $is_resolved?'al-resolved':''; ?>"
                 id="alert-<?php echo $al['notification_id']; ?>"
                 data-type="<?php echo $al['type']; ?>"
                 data-status="<?php echo $al['status']; ?>"
                 data-pond="<?php echo htmlspecialchars($al['pond_name']); ?>"
                 data-msg="<?php echo strtolower(htmlspecialchars($al['message'])); ?>"
                 onclick="alertItemClick(event,'<?php echo $al['pond_name']; ?>')">

                <div class="alert-icon-col">
                    <i class="fas fa-<?php echo $icon; ?> alert-icon <?php echo $al['type']; ?>"></i>
                    <?php if($is_unread): ?>
                    <span class="alert-unread-pip"></span>
                    <?php endif; ?>
                </div>

                <div style="flex:1;min-width:0">
                    <div class="alert-header-row">
                        <div class="alert-pond">
                            <i class="fas fa-water" style="font-size:.65rem;opacity:.6"></i>
                            Pond <?php echo htmlspecialchars($al['pond_name']); ?>
                        </div>
                        <div class="alert-time">
                            <i class="far fa-clock" style="font-size:.6rem"></i>
                            <?php echo date('h:i A', strtotime($al['created_at'])); ?>
                            <span class="alert-ago" data-ts="<?php echo strtotime($al['created_at']); ?>"></span>
                        </div>
                    </div>

                    <div class="alert-msg"><?php echo htmlspecialchars($al['message']); ?></div>

                    <div class="alert-foot">
                        <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap">
                            <span class="badge badge-<?php echo $al['type']; ?>"><?php echo strtoupper($al['type']); ?></span>
                            <span class="badge badge-<?php echo $al['status']; ?>" id="status-badge-<?php echo $al['notification_id']; ?>"><?php echo strtoupper($al['status']); ?></span>
                        </div>
                        <div class="alert-actions" id="actions-<?php echo $al['notification_id']; ?>">
                            <?php if(!$is_resolved): ?>
                            <button class="btn btn-ghost btn-sm"
                                    onclick="event.stopPropagation();gotoMap('<?php echo $al['pond_name']; ?>')"
                                    title="View on map">
                                <i class="fas fa-map-marker-alt" style="color:var(--cyan)"></i>
                            </button>
                            <?php if($is_unread): ?>
                            <button class="btn btn-success btn-sm"
                                    id="btn-ack-<?php echo $al['notification_id']; ?>"
                                    onclick="event.stopPropagation();ackAlert(<?php echo $al['notification_id']; ?>,this)">
                                <i class="fas fa-check"></i> ACK
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-primary btn-sm"
                                    id="btn-resolve-<?php echo $al['notification_id']; ?>"
                                    onclick="event.stopPropagation();resolveAlert(<?php echo $al['notification_id']; ?>,this)">
                                <i class="fas fa-check-double"></i> Resolve
                            </button>
                            <?php else: ?>
                            <span style="font-family:var(--fm);font-size:.65rem;color:var(--green)">
                                <i class="fas fa-check-circle"></i> Resolved
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div id="alertEmptyState" style="display:none;text-align:center;padding:2.5rem 1rem;color:var(--muted)">
                <i class="fas fa-check-circle" style="color:var(--green);font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.7"></i>
                <div style="font-size:.9rem;font-weight:600;margin-bottom:.3rem">No alerts found</div>
                <div style="font-size:.75rem">Try a different filter or search term</div>
            </div>

            <?php if(empty($alerts)): ?>
            <div style="text-align:center;padding:2.5rem 1rem;color:var(--muted)">
                <i class="fas fa-check-circle" style="color:var(--green);font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
                <div style="font-size:.9rem;font-weight:600">All clear — no alerts</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="section-panel" id="sec-reports">
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-file-alt"></i> Report Generation</div></div>
        <div class="rpt-type-grid">
            <div class="rpt-type-btn active" onclick="genReport('daily',this)"><div class="rpt-type-icon" style="color:var(--green)"><i class="fas fa-calendar-day"></i></div><span class="rpt-type-label">Daily</span><span class="rpt-type-sub">24-hour summary</span></div>
            <div class="rpt-type-btn" onclick="genReport('weekly',this)"><div class="rpt-type-icon" style="color:var(--amber)"><i class="fas fa-calendar-week"></i></div><span class="rpt-type-label">Weekly</span><span class="rpt-type-sub">7-day trends</span></div>
            <div class="rpt-type-btn" onclick="genReport('monthly',this)"><div class="rpt-type-icon" style="color:var(--cyan)"><i class="fas fa-calendar-alt"></i></div><span class="rpt-type-label">Monthly</span><span class="rpt-type-sub">30-day analysis</span></div>
        </div>
        <div class="rpt-preview" id="rptPreview">
            <div class="rpt-header"><div class="rpt-title"><i class="fas fa-chart-bar" style="color:var(--cyan)"></i> Daily Report</div><div class="rpt-date-badge"><?php echo date('M d, Y'); ?></div></div>
            <div class="rpt-stats">
                <div class="rpt-stat"><div class="rpt-stat-val"><?php echo $daily_report['total_ponds']; ?></div><div class="rpt-stat-lbl">Total Ponds</div></div>
                <div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--red)"><?php echo $daily_report['alerts_generated']; ?></div><div class="rpt-stat-lbl">Alerts</div></div>
                <div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--green)"><?php echo $daily_report['staff_active']; ?></div><div class="rpt-stat-lbl">Active Staff</div></div>
            </div>
            <div class="rpt-status-row">
                <div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--green)"><?php echo $daily_report['safe_ponds']; ?></div><div class="rpt-status-lbl" style="color:var(--green)">Safe</div></div>
                <div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--amber)"><?php echo $daily_report['warning_ponds']; ?></div><div class="rpt-status-lbl" style="color:var(--amber)">Warning</div></div>
                <div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--red)"><?php echo $daily_report['critical_ponds']; ?></div><div class="rpt-status-lbl" style="color:var(--red)">Critical</div></div>
            </div>
            <div class="metrics-mini">
                <div class="metric-mini"><i class="fas fa-seedling ic-organic"></i><div><div class="metric-mini-val"><?php echo $daily_report['avg_organic']; ?>%</div><div class="metric-mini-lbl">Avg Organic</div></div></div>
                <div class="metric-mini"><i class="fas fa-thermometer-half ic-temp"></i><div><div class="metric-mini-val"><?php echo $daily_report['avg_temp']; ?>°C</div><div class="metric-mini-lbl">Avg Temp</div></div></div>
                <div class="metric-mini"><i class="fas fa-flask ic-ph"></i><div><div class="metric-mini-val"><?php echo $daily_report['avg_ph']; ?></div><div class="metric-mini-lbl">Avg pH</div></div></div>
            </div>
            <div class="rpt-dl-row">
                <button class="btn btn-sm" style="flex:1;background:rgba(255,59,92,.12);color:var(--red);border:1px solid rgba(255,59,92,.25)" onclick="toast('PDF downloaded (simulation)','success')"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn btn-sm" style="flex:1;background:rgba(57,255,138,.12);color:var(--green);border:1px solid rgba(57,255,138,.25)" onclick="toast('Excel downloaded (simulation)','success')"><i class="fas fa-file-excel"></i> Excel</button>
                <button class="btn btn-sm" style="flex:1;background:rgba(255,184,0,.12);color:var(--amber);border:1px solid rgba(255,184,0,.25)" onclick="toast('CSV downloaded (simulation)','success')"><i class="fas fa-file-csv"></i> CSV</button>
            </div>
        </div>
    </div>
</div>

<div class="section-panel" id="sec-activities">
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-history"></i> Recent Activities</div><div class="iot-live"><span></span> LIVE</div></div>
        <?php foreach($recent_activities as $act): $icons=['login'=>'sign-in-alt','reading'=>'chart-line','alert'=>'exclamation-triangle','system'=>'cog']; $ic=$icons[$act['type']]??'circle'; ?>
        <div class="act-item">
            <div class="act-icon <?php echo $act['type']; ?>"><i class="fas fa-<?php echo $ic; ?>"></i></div>
            <div class="act-text"><?php echo htmlspecialchars($act['action']); ?></div>
            <div class="act-time"><?php echo date('h:i A',strtotime($act['timestamp'])); ?></div>
        </div>
        <?php endforeach; if(empty($recent_activities)): ?><div style="text-align:center;padding:1.5rem;color:var(--muted)">No activities</div><?php endif; ?>
    </div>
</div>

<div class="section-panel" id="sec-iot">

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-server"></i> System Status</div>
            <button class="btn btn-ghost btn-sm" onclick="loadSysStats()">
                <i class="fas fa-sync-alt" id="sysRefreshIcon"></i> Refresh
            </button>
        </div>
        <div class="sys-stats-grid" id="sysStatsGrid">
            <div class="sys-stat">
                <div class="sys-stat-icon" style="background:rgba(0,229,255,.1);color:var(--cyan)"><i class="fas fa-code"></i></div>
                <div><div class="sys-stat-val" id="ssStat-php">—</div><div class="sys-stat-lbl">PHP Version</div></div>
            </div>
            <div class="sys-stat">
                <div class="sys-stat-icon" style="background:rgba(255,184,0,.1);color:var(--amber)"><i class="fas fa-memory"></i></div>
                <div><div class="sys-stat-val" id="ssStat-mem">—</div><div class="sys-stat-lbl">Memory Used</div></div>
            </div>
            <div class="sys-stat">
                <div class="sys-stat-icon" style="background:rgba(57,255,138,.1);color:var(--green)"><i class="fas fa-users"></i></div>
                <div><div class="sys-stat-val" id="ssStat-users">—</div><div class="sys-stat-lbl">Total Users</div></div>
            </div>
            <div class="sys-stat">
                <div class="sys-stat-icon" style="background:rgba(176,108,255,.1);color:var(--violet)"><i class="fas fa-water"></i></div>
                <div><div class="sys-stat-val" id="ssStat-ponds">—</div><div class="sys-stat-lbl">DB Ponds</div></div>
            </div>
            <div class="sys-stat">
                <div class="sys-stat-icon" style="background:rgba(0,229,255,.1);color:var(--cyan)"><i class="fas fa-chart-line"></i></div>
                <div><div class="sys-stat-val" id="ssStat-reads">—</div><div class="sys-stat-lbl">Readings (24h)</div></div>
            </div>
            <div class="sys-stat">
                <div class="sys-stat-icon" style="background:rgba(255,59,92,.1);color:var(--red)"><i class="fas fa-bell"></i></div>
                <div><div class="sys-stat-val" id="ssStat-alerts">—</div><div class="sys-stat-lbl">Unread Alerts</div></div>
            </div>
        </div>
        <div style="font-family:var(--fm);font-size:.67rem;color:var(--muted);margin-top:.5rem">
            Server: <span id="ssStat-time" style="color:var(--cyan)">—</span>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-microchip"></i> Pond IOT History</div>
            <div style="display:flex;align-items:center;gap:.6rem">
                <div class="iot-live"><span></span> LIVE</div>
                <button class="btn btn-ghost btn-sm" onclick="Object.keys(PONDS).forEach(k=>loadPondHistory(k))">
                    <i class="fas fa-sync-alt"></i> Refresh All
                </button>
            </div>
        </div>
        <div class="iot-grid">
            <?php foreach($ponds_data as $key => $pond):
                $sc   = $pond['status']==='safe'?'var(--green)':($pond['status']==='warning'?'var(--amber)':'var(--red)');
                $bdr2 = $pond['status']==='safe'?'rgba(57,255,138,.3)':($pond['status']==='warning'?'rgba(255,184,0,.3)':'rgba(255,59,92,.3)');
                $bc_o = $pond['organic_level']>80?'rgba(255,59,92,.15)':($pond['organic_level']>60?'rgba(255,184,0,.12)':'rgba(57,255,138,.12)');
            ?>
            <div class="iot-card" style="border-color:<?php echo $bdr2; ?>">

                <div class="iot-card-head">
                    <div class="iot-card-title" style="color:<?php echo $sc; ?>">
                        <i class="fas fa-water" style="flex-shrink:0"></i>
                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Pond <?php echo $key; ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:.3rem;flex-shrink:0">
                        <span class="badge badge-<?php echo $pond['status']; ?>" style="font-size:.53rem;padding:.15rem .45rem">
                            <?php echo strtoupper($pond['status']); ?>
                        </span>
                        <button class="btn btn-ghost btn-sm" onclick="loadPondHistory('<?php echo $key; ?>')"
                                style="padding:.18rem .4rem;min-height:unset;font-size:.65rem">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <div style="font-size:.68rem;color:var(--muted);margin-bottom:.45rem;
                            overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <i class="fas fa-user" style="color:var(--cyan)"></i> <?php echo htmlspecialchars($pond['staff']); ?>
                    &nbsp;·&nbsp;
                    <i class="fas fa-map-pin" style="color:var(--red)"></i> <?php echo htmlspecialchars($pond['location']); ?>
                </div>

                <div class="iot-mini-chart">
                    <canvas id="iotChart-<?php echo $key; ?>"></canvas>
                </div>

                <div class="iot-val-row">
                    <div class="iot-val-chip" style="border:1px solid <?php echo $bc_o; ?>">
                        <span class="val ic-organic" id="iot-o-<?php echo $key; ?>"><?php echo $pond['organic_level']; ?>%</span>
                        <span class="lbl">ORGANIC</span>
                    </div>
                    <div class="iot-val-chip" style="border:1px solid rgba(255,184,0,.12)">
                        <span class="val ic-temp" id="iot-t-<?php echo $key; ?>"><?php echo $pond['temperature']; ?>°C</span>
                        <span class="lbl">TEMP</span>
                    </div>
                    <div class="iot-val-chip" style="border:1px solid rgba(176,108,255,.12)">
                        <span class="val ic-ph" id="iot-p-<?php echo $key; ?>"><?php echo $pond['ph']; ?></span>
                        <span class="lbl">PH</span>
                    </div>
                </div>

                <div class="iot-table-wrap">
                    <table class="iot-readings-table">
                        <colgroup>
                            <col style="width:28%">
                            <col style="width:24%">
                            <col style="width:24%">
                            <col style="width:24%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th style="color:var(--green)">Org%</th>
                                <th style="color:var(--amber)">Temp</th>
                                <th style="color:var(--violet)">pH</th>
                            </tr>
                        </thead>
                        <tbody id="iotTbody-<?php echo $key; ?>">
                            <tr>
                                <td colspan="4" style="text-align:center;color:var(--muted);
                                    padding:.6rem;font-style:italic;white-space:normal">
                                    Loading…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-file-export"></i> Export Data</div>
            <span class="badge badge-info">Client-side CSV</span>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.7rem">
            <button class="btn btn-ghost" onclick="exportCSV('users')">
                <i class="fas fa-users" style="color:var(--cyan)"></i> Export Users CSV
            </button>
            <button class="btn btn-ghost" onclick="exportCSV('ponds')">
                <i class="fas fa-water" style="color:var(--green)"></i> Export Pond Data CSV
            </button>
            <button class="btn btn-ghost" onclick="exportCSV('alerts')">
                <i class="fas fa-bell" style="color:var(--amber)"></i> Export Alerts CSV
            </button>
        </div>
        <div style="font-family:var(--fm);font-size:.67rem;color:var(--muted)">
            <i class="fas fa-info-circle" style="color:var(--cyan)"></i>
            CSV export includes current session data. For full database export, use phpMyAdmin or direct DB access.
        </div>
    </div>
</div>

<div class="section-panel" id="sec-settings">
    <div class="settings-grid">
        <div class="settings-block">
            <div class="settings-block-title"><i class="fas fa-bell"></i> Notification Preferences</div>
            <div class="setting-row">
                <div>
                    <div class="setting-label">Critical Alerts</div>
                    <div class="setting-desc">Popup toast for critical pond status</div>
                </div>
                <label class="toggle"><input type="checkbox" id="tog-critical" checked onchange="saveSetting()"><span class="toggle-slider"></span></label>
            </div>
            <div class="setting-row">
                <div>
                    <div class="setting-label">Warning Alerts</div>
                    <div class="setting-desc">Popup toast for warning pond status</div>
                </div>
                <label class="toggle"><input type="checkbox" id="tog-warning" checked onchange="saveSetting()"><span class="toggle-slider"></span></label>
            </div>
            <div class="setting-row">
                <div>
                    <div class="setting-label">Info Alerts</div>
                    <div class="setting-desc">Informational notifications</div>
                </div>
                <label class="toggle"><input type="checkbox" id="tog-info" onchange="saveSetting()"><span class="toggle-slider"></span></label>
            </div>
            <div class="setting-row">
                <div>
                    <div class="setting-label">IOT Refresh Rate</div>
                    <div class="setting-desc">Seconds between auto-refresh</div>
                </div>
                <div class="range-wrap">
                    <input type="range" class="range-input" id="refreshRate" min="3" max="30" value="5" oninput="document.getElementById('refreshRateVal').textContent=this.value+'s';saveSetting()">
                    <span class="range-val" id="refreshRateVal">5s</span>
                </div>
            </div>
        </div>

        <div class="settings-block">
            <div class="settings-block-title"><i class="fas fa-sliders-h"></i> Display & Interface</div>
            <div class="setting-row">
                <div>
                    <div class="setting-label">IOT Simulation</div>
                    <div class="setting-desc">Auto-update pond readings every 5s</div>
                </div>
                <label class="toggle"><input type="checkbox" id="tog-sim" checked onchange="toggleSimulation()"><span class="toggle-slider"></span></label>
            </div>
            <div class="setting-row">
                <div>
                    <div class="setting-label">Animated Background</div>
                    <div class="setting-desc">Grid animation on background</div>
                </div>
                <label class="toggle"><input type="checkbox" id="tog-anim" checked onchange="toggleAnimation()"><span class="toggle-slider"></span></label>
            </div>
            <div class="setting-row">
                <div>
                    <div class="setting-label">Sound Alerts</div>
                    <div class="setting-desc">Browser sound for critical alerts</div>
                </div>
                <label class="toggle"><input type="checkbox" id="tog-sound" onchange="saveSetting()"><span class="toggle-slider"></span></label>
            </div>
            <div class="setting-row">
                <div>
                    <div class="setting-label">Session Timeout</div>
                    <div class="setting-desc">Minutes before auto-logout warning</div>
                </div>
                <div class="range-wrap">
                    <input type="range" class="range-input" id="sessionTimeout" min="10" max="60" value="30" oninput="document.getElementById('sessionTimeoutVal').textContent=this.value+'m';updateSessionDuration()">
                    <span class="range-val" id="sessionTimeoutVal">30m</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-info-circle"></i> About AquaSystem</div>
        </div>
        <div class="about-block">
            <div class="about-row"><span>System</span><span>Organic Matter Detection in Tilapia</span></div>
            <div class="about-row"><span>Version</span><span style="color:var(--cyan)">v2.0.0</span></div>
            <div class="about-row"><span>Location</span><span>Manolo Fortich, Bukidnon, PH</span></div>
            <div class="about-row"><span>Timezone</span><span>Asia/Manila (PHT UTC+8)</span></div>
            <div class="about-row"><span>PHP</span><span><?php echo PHP_VERSION; ?></span></div>
            <div class="about-row"><span>Server Time</span><span style="font-family:var(--fm);font-size:.78rem"><?php echo date('Y-m-d H:i:s'); ?></span></div>
            <div class="about-row"><span>Logged in as</span><span style="color:var(--cyan)"><?php echo htmlspecialchars($admin_name); ?> (Admin)</span></div>
            <div class="about-row"><span>Ponds Monitored</span><span><?php echo $total_ponds; ?></span></div>
            <div class="about-row"><span>Active Alerts</span><span style="color:var(--red)"><?php echo $new_alerts_count; ?></span></div>
        </div>
        <div style="display:flex;gap:.6rem;margin-top:.8rem;flex-wrap:wrap">
            <button class="btn btn-ghost btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print Dashboard</button>
            <button class="btn btn-ghost btn-sm" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Reload Page</button>
            <button class="btn btn-danger btn-sm" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> Logout Now</button>
        </div>
    </div>
</div>

<div class="section-panel" id="sec-mgr-inbox">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fas fa-inbox"></i> Manager Requests &amp; Notifications</div>
            <button class="btn btn-ghost btn-sm" onclick="loadAdminMgrNotifs()">
                <i class="fas fa-sync-alt" id="admInboxRefreshIcon"></i> Refresh
            </button>
        </div>

        <div class="adm-inbox-strip">
            <div class="adm-inbox-kpi" style="--ikc:var(--amber)">
                <div class="adm-inbox-kpi-val" id="admKpi-pending">—</div>
                <div class="adm-inbox-kpi-lbl">⏳ Pending</div>
            </div>
            <div class="adm-inbox-kpi" style="--ikc:var(--cyan)">
                <div class="adm-inbox-kpi-val" id="admKpi-received">—</div>
                <div class="adm-inbox-kpi-lbl">👁 Received</div>
            </div>
            <div class="adm-inbox-kpi" style="--ikc:var(--green)">
                <div class="adm-inbox-kpi-val" id="admKpi-completed">—</div>
                <div class="adm-inbox-kpi-lbl">✅ Completed</div>
            </div>
        </div>

        <div id="admNotifsList">
            <div style="text-align:center;padding:2rem;color:var(--muted);font-family:var(--fm);font-size:.78rem">
                <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;display:block;margin-bottom:.5rem"></i>Loading…
            </div>
        </div>
    </div>
</div>

<div class="dash-footer">
    <div><i class="fas fa-map-marker-alt" style="color:var(--cyan)"></i> Manolo Fortich, Bukidnon · <?php echo $current_date; ?></div>
    <div>PH TIME: <span id="footerTs" style="color:var(--cyan)"><?php echo date('h:i:s A'); ?></span></div>
</div>

</div>
</div>
</div>

<nav class="bottom-nav" id="bottomNav">
    <div class="bnav-item active" id="bn-overview" onclick="showSection('overview')">
        <i class="fas fa-chart-pie"></i>
        <span>Overview</span>
        <?php if($new_alerts_count > 0): ?><span class="bnav-badge"><?php echo $new_alerts_count; ?></span><?php endif; ?>
    </div>
    <div class="bnav-item" id="bn-users" onclick="showSection('users')">
        <i class="fas fa-users"></i><span>Users</span>
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

<div id="userModal" class="modal">
    <div class="modal-box">
        <div class="modal-head"><div class="modal-title" id="modalTitle">Add New User</div><button class="modal-close" onclick="closeModal('userModal')">&times;</button></div>
        <form onsubmit="event.preventDefault();saveUser()">
            <input type="hidden" id="uid">
            <div class="form-group"><label class="form-label">Full Name *</label><input class="form-ctrl" type="text" id="fName" required maxlength="100" placeholder="Juan dela Cruz"></div>
            <div class="form-group"><label class="form-label">Email *</label><input class="form-ctrl" type="email" id="fEmail" required maxlength="100" placeholder="juan@example.com" autocomplete="off"></div>
            <div class="form-group"><label class="form-label">Role</label><select class="form-ctrl" id="fRole"><option value="staff">Staff</option><option value="manager">Manager</option><option value="admin">Admin</option></select></div>
            <div class="form-group"><label class="form-label">Assigned Pond</label><select class="form-ctrl" id="fPond"><option value="">— None —</option><?php foreach(array_keys($ponds_data) as $pk): ?><option value="<?php echo $pk; ?>">Pond <?php echo $pk; ?></option><?php endforeach; ?></select></div>
            <div class="form-group" id="pwField"><label class="form-label">Password</label><input class="form-ctrl" type="password" id="fPw" value="default123" autocomplete="new-password"></div>
            <div class="form-actions"><button type="button" class="btn btn-ghost" onclick="closeModal('userModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
        </form>
    </div>
</div>

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
        <div class="confirm-btns"><button class="btn btn-ghost" onclick="closeModal('confirmModal')">Cancel</button><button class="btn btn-danger" id="confirmOk">Confirm</button></div>
    </div>
</div>

<script>
const PONDS       = <?php echo json_encode($ponds_data); ?>;
const POND_COORDS = <?php echo json_encode($ponds_config); ?>;
const CHART_DATA  = <?php echo json_encode($chart_data); ?>;
const ADMIN_ID    = <?php echo $admin_id; ?>;

let map, metricsChart;
let polygons = {}, labelMarkers = {};
let selectedIds = [];
let mapInited = false;
let currentSection = 'overview';

const ALL_SECTIONS = ['overview','users','ponds','map','charts','alerts','reports','activities','iot','settings','mgr-inbox'];
const BNAV_SECTIONS = ['overview','users','ponds','map','alerts'];

document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initChart();
    startSimulation();
    setTimeout(initMap, 300);
});

function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    const isOpen = sb.classList.contains('open');
    if (isOpen) {
        sb.classList.remove('open');
        ov.classList.remove('active');
    } else {
        sb.classList.add('open');
        ov.classList.add('active');
    }
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

function showSection(name) {
    currentSection = name;

    ALL_SECTIONS.forEach(s => {
        const el = document.getElementById('sec-' + s);
        if (el) el.classList.remove('active');
    });
    const target = document.getElementById('sec-' + name);
    if (target) target.classList.add('active');

    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        const oc = item.getAttribute('onclick') || '';
        if (oc.indexOf("'" + name + "'") !== -1) {
            item.classList.add('active');
        }
    });

    document.querySelectorAll('.bnav-item').forEach(b => b.classList.remove('active'));
    const bnEl = document.getElementById('bn-' + name);
    if (bnEl) bnEl.classList.add('active');

    if (name === 'map') {
        if (map) {
            setTimeout(() => map.invalidateSize(), 150);
        } else if (!mapInited) {
            mapInited = true;
            setTimeout(initMap, 100);
        }
    }

    if (name === 'charts' && metricsChart) {
        setTimeout(() => metricsChart.resize(), 100);
    }

    if (window.innerWidth <= 768) closeSidebar();

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function gotoMap(pondKey) {
    showSection('map');
    setTimeout(() => focusPond(pondKey), 400);
}

function initClock() {
    setInterval(() => {
        const ph = new Date().toLocaleTimeString('en-US', {
            timeZone:'Asia/Manila', hour12:true,
            hour:'2-digit', minute:'2-digit', second:'2-digit'
        });
        ['mainClock','topnavClock'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = ph;
        });
        ['mapTs','chartTs','footerTs'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = ph;
        });
    }, 1000);
}

function getColor(s) { return s==='safe'?'#39ff8a':(s==='warning'?'#ffb800':'#ff3b5c'); }

function initMap() {
    const mapEl = document.getElementById('map');
    if (!mapEl || mapEl.clientHeight < 5) {
        setTimeout(initMap, 400);
        return;
    }
    if (map) return;

    map = L.map('map', {
        zoomControl:        false,
        scrollWheelZoom:    false,
        doubleClickZoom:    false,
        touchZoom:          false,
        boxZoom:            false,
        keyboard:           false,
        dragging:           true,
    }).setView([8.3694, 124.8652], 17);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution:'&copy; OpenStreetMap &copy; CARTO', subdomains:'abcd', maxZoom:20
    }).addTo(map);

    Object.keys(POND_COORDS).forEach(key => {
        const cfg  = POND_COORDS[key];
        const pond = PONDS[key];
        if (!cfg || !pond) return;
        const color = getColor(pond.status);

        const poly = L.polygon(cfg.bounds, {
            color, fillColor:color,
            fillOpacity: pond.status==='critical'?0.35:0.25,
            weight:2.5,
            dashArray: pond.status==='critical'?'8,4':null
        }).addTo(map);

        poly.bindPopup(buildPopup(key, pond, cfg, color), { maxWidth:280 });
        poly.on('click', e => { L.DomEvent.stopPropagation(e); poly.openPopup(); });
        poly.on('mouseover', () => poly.setStyle({ fillOpacity:0.55, weight:3.5 }));
        poly.on('mouseout',  () => poly.setStyle({ fillOpacity:pond.status==='critical'?0.35:0.25, weight:2.5 }));
        polygons[key] = poly;

        const lIcon = L.divIcon({
            className:'',
            html:`<div style="background:rgba(6,13,23,.88);border:1.5px solid ${color};color:${color};font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:4px 8px;border-radius:6px;white-space:nowrap;box-shadow:0 0 12px ${color}44;">${cfg.name}</div>`,
            iconAnchor:[0,0]
        });
        L.marker(cfg.center, { icon:lIcon, interactive:false }).addTo(map);
    });

    const fg = L.featureGroup(Object.values(polygons));
    if (fg.getBounds().isValid()) {
        const bounds = fg.getBounds();
        map.fitBounds(bounds.pad(0.08));
        map.setMaxBounds(bounds.pad(0.18));
        map.once('moveend', () => {
            const lockedZoom = map.getZoom();
            map.setMinZoom(lockedZoom);
            map.setMaxZoom(lockedZoom);
        });
    }

    map.invalidateSize();
}

function buildPopup(key, pond, cfg, color) {
    return `<div style="font-family:'Syne',sans-serif;color:#e8f4ff;min-width:200px;">
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:9px;padding-bottom:7px;border-bottom:1px solid rgba(255,255,255,.1);">
            <span style="background:${color}22;color:${color};border:1px solid ${color}44;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;">${pond.status.toUpperCase()}</span>
            <strong>${cfg.name}</strong>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;margin-bottom:9px;">
            <div style="text-align:center;background:rgba(0,0,0,.3);padding:5px;border-radius:5px;"><div style="color:#39ff8a;font-weight:700;font-size:.85rem;">${pond.organic_level}%</div><div style="font-size:.58rem;color:#8ba8c4;">Organic</div></div>
            <div style="text-align:center;background:rgba(0,0,0,.3);padding:5px;border-radius:5px;"><div style="color:#ffb800;font-weight:700;font-size:.85rem;">${pond.temperature}°C</div><div style="font-size:.58rem;color:#8ba8c4;">Temp</div></div>
            <div style="text-align:center;background:rgba(0,0,0,.3);padding:5px;border-radius:5px;"><div style="color:#b06cff;font-weight:700;font-size:.85rem;">${pond.ph}</div><div style="font-size:.58rem;color:#8ba8c4;">pH</div></div>
        </div>
        <div style="font-size:.73rem;color:#8ba8c4;">👤 ${pond.staff}<br>📍 ${pond.location}</div>
    </div>`;
}

function focusPond(key) {
    if (!key || !map) return;
    const cfg = POND_COORDS[key];
    if (!cfg) return;
    if (polygons[key]) {
        map.panTo(cfg.center, { animate:true, duration:0.5 });
        polygons[key].openPopup();
        polygons[key].setStyle({ fillOpacity:0.65 });
        setTimeout(() => {
            if (polygons[key]) polygons[key].setStyle({ fillOpacity:PONDS[key]?.status==='critical'?0.35:0.25 });
        }, 1200);
    }
    toast(`Focused: ${cfg.name}`, 'info');
}

function startSimulation() {
    setInterval(() => {
        Object.keys(PONDS).forEach(key => {
            const pond = PONDS[key];
            const o = parseFloat(Math.max(10,Math.min(100, pond.organic_level+(Math.random()-.5)*5)).toFixed(1));
            const t = parseFloat(Math.max(20,Math.min(38,  pond.temperature  +(Math.random()-.5)*.9)).toFixed(1));
            const p = parseFloat(Math.max(5, Math.min(10,  pond.ph           +(Math.random()-.5)*.12)).toFixed(1));
            const s = (o>80||t>32||p>8.5)?'critical':((o>60||t>30||p>7.8)?'warning':'safe');
            PONDS[key].organic_level=o; PONDS[key].temperature=t; PONDS[key].ph=p; PONDS[key].status=s;
            updatePondDisplay(key,o,t,p,s,new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour12:true}));
        });
    }, 5000);
}

function updatePondDisplay(key,o,t,p,status,ts) {
    const pairs=[['ov-o-',o+'%'],['ov-t-',t+'°C'],['ov-p-',p]];
    pairs.forEach(([pref,val]) => { const el=document.getElementById(pref+key); if(el) el.textContent=val; });
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
    ['refreshAllIcon'].forEach(id=>{ const el=document.getElementById(id); if(el) el.style.animation='spin 1s linear infinite'; });
    Object.keys(PONDS).forEach(key=>refreshPond(key));
    setTimeout(()=>{ ['refreshAllIcon'].forEach(id=>{ const el=document.getElementById(id); if(el) el.style.animation=''; }); },1200);
    toast('All ponds refreshed','success');
}

function initChart() {
    const ctx = document.getElementById('metricsChart');
    if (!ctx) return;
    metricsChart = new Chart(ctx.getContext('2d'), {
        type:'line',
        data:{
            labels:CHART_DATA.daily.labels,
            datasets:[
                { label:'Organic %',  data:CHART_DATA.daily.organic,     borderColor:'#ff3b5c',backgroundColor:'rgba(255,59,92,.07)', fill:true,tension:.4,borderWidth:2,pointRadius:0,pointHoverRadius:5 },
                { label:'Temp °C',    data:CHART_DATA.daily.temperature, borderColor:'#ffb800',backgroundColor:'rgba(255,184,0,.07)',fill:true,tension:.4,borderWidth:2,pointRadius:0,pointHoverRadius:5 },
                { label:'pH',         data:CHART_DATA.daily.ph,          borderColor:'#39ff8a',backgroundColor:'rgba(57,255,138,.07)',fill:true,tension:.4,borderWidth:2,pointRadius:0,pointHoverRadius:5 }
            ]
        },
        options:{
            responsive:true,maintainAspectRatio:false,animation:{duration:800},
            interaction:{mode:'index',intersect:false},
            plugins:{ legend:{display:false}, tooltip:{backgroundColor:'rgba(11,22,37,.95)',borderColor:'rgba(0,229,255,.2)',borderWidth:1,titleColor:'#e8f4ff',bodyColor:'#8ba8c4',padding:10} },
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

function ackAlert(id, btn) {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    fetchPost('acknowledge_alert', `alert_id=${id}`)
    .then(d => {
        if (!d.success) {
            btn.innerHTML = orig;
            btn.disabled = false;
            toast(d.message || 'Failed to acknowledge', 'critical');
            return;
        }
        const row = document.getElementById('alert-' + id);
        if (row) {
            const pip = row.querySelector('.alert-unread-pip');
            if (pip) pip.remove();
            const sb = document.getElementById('status-badge-' + id);
            if (sb) { sb.className = 'badge badge-read'; sb.textContent = 'READ'; }
            btn.remove();
            row.dataset.status = 'read';
        }
        _updateAlertCounts();
        toast('Alert acknowledged', 'success');
    })
    .catch(() => { btn.innerHTML = orig; btn.disabled = false; toast('Network error', 'critical'); });
}

function resolveAlert(id, btn) {
    confirm_('Resolve Alert', 'Mark this alert as resolved?', '✅', () => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        fetchPost('resolve_alert', `alert_id=${id}`)
        .then(d => {
            if (!d.success) {
                btn.innerHTML = orig;
                btn.disabled = false;
                toast(d.message || 'Failed to resolve', 'critical');
                return;
            }
            const row = document.getElementById('alert-' + id);
            if (row) {
                row.classList.add('al-resolved');
                row.dataset.status = 'resolved';
                const pip = row.querySelector('.alert-unread-pip');
                if (pip) pip.remove();
                const sb = document.getElementById('status-badge-' + id);
                if (sb) { sb.className = 'badge badge-resolved'; sb.textContent = 'RESOLVED'; }
                const actions = document.getElementById('actions-' + id);
                if (actions) {
                    actions.innerHTML = '<span style="font-family:var(--fm);font-size:.65rem;color:var(--green)"><i class="fas fa-check-circle"></i> Resolved</span>';
                }
            }
            _updateAlertCounts();
            toast('Alert resolved', 'success');
        })
        .catch(() => { btn.innerHTML = orig; btn.disabled = false; toast('Network error', 'critical'); });
    });
}

function markAllRead() {
    const unreadRows = Array.from(document.querySelectorAll('#alertsList .alert-item[data-status="unread"]'));
    if (!unreadRows.length) { toast('No unread alerts', 'info'); return; }

    const btn = document.getElementById('btnMarkAll');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…'; }

    let delay = 0;
    unreadRows.forEach(row => {
        const id  = row.id.replace('alert-', '');
        const ackBtn = document.getElementById('btn-ack-' + id);
        setTimeout(() => {
            fetchPost('acknowledge_alert', `alert_id=${id}`)
            .then(d => {
                if (!d.success) return;
                const pip = row.querySelector('.alert-unread-pip');
                if (pip) pip.remove();
                const sb = document.getElementById('status-badge-' + id);
                if (sb) { sb.className = 'badge badge-read'; sb.textContent = 'READ'; }
                if (ackBtn) ackBtn.remove();
                row.dataset.status = 'read';
                _updateAlertCounts();
            });
        }, delay);
        delay += 80;
    });

    setTimeout(() => {
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark All Read'; }
        toast(`${unreadRows.length} alert(s) marked as read`, 'success');
    }, delay + 200);
}

let _currentFilter = 'all';
let _currentSearch = '';

function filterAlerts(filter, btn) {
    _currentFilter = filter;
    document.querySelectorAll('.alert-ftab').forEach(t => t.classList.remove('active'));
    if (btn) btn.classList.add('active');
    _applyAlertFilter();
}

function searchAlerts(query) {
    _currentSearch = query.toLowerCase().trim();
    _applyAlertFilter();
}

function _applyAlertFilter() {
    const rows  = document.querySelectorAll('#alertsList .alert-item');
    let visible = 0;
    rows.forEach(row => {
        const type   = row.dataset.type   || '';
        const status = row.dataset.status || '';
        const msg    = row.dataset.msg    || '';
        const pond   = row.dataset.pond   || '';

        let typeMatch = false;
        switch (_currentFilter) {
            case 'all':      typeMatch = true; break;
            case 'unread':   typeMatch = status === 'unread'; break;
            case 'critical': typeMatch = type   === 'critical'; break;
            case 'warning':  typeMatch = type   === 'warning'; break;
            case 'resolved': typeMatch = status === 'resolved'; break;
        }

        const searchMatch = !_currentSearch ||
            msg.includes(_currentSearch) ||
            pond.toLowerCase().includes(_currentSearch);

        const show = typeMatch && searchMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const empty = document.getElementById('alertEmptyState');
    if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
}

function _updateAlertCounts() {
    const rows     = Array.from(document.querySelectorAll('#alertsList .alert-item'));
    const total    = rows.length;
    const unread   = rows.filter(r => r.dataset.status  === 'unread').length;
    const critical = rows.filter(r => r.dataset.type    === 'critical').length;
    const warning  = rows.filter(r => r.dataset.type    === 'warning').length;
    const resolved = rows.filter(r => r.dataset.status  === 'resolved').length;

    const badge = document.getElementById('alertBadge');
    if (badge) badge.textContent = unread + ' NEW';

    const sb = document.getElementById('sideAlerts');
    if (sb) sb.textContent = unread > 0 ? unread : '';

    document.querySelectorAll('#bn-alerts .bnav-badge').forEach(b => {
        b.textContent = unread > 0 ? unread : '';
        b.style.display = unread > 0 ? '' : 'none';
    });

    const _kpi = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    _kpi('akpi-total-val',    total);
    _kpi('akpi-unread-val',   unread);
    _kpi('akpi-critical-val', critical);
    _kpi('akpi-warning-val',  warning);
    _kpi('akpi-resolved-val', resolved);

    _kpi('fc-all',      total);
    _kpi('fc-unread',   unread);
    _kpi('fc-critical', critical);
    _kpi('fc-warning',  warning);
    _kpi('fc-resolved', resolved);

    const marAllBtn = document.getElementById('btnMarkAll');
    if (marAllBtn) marAllBtn.disabled = unread === 0;
}

function alertItemClick(e, pondName) {
    if (e.target.closest('.btn')) return;
    gotoMap(pondName);
}

(function initAlertRelativeTimes() {
    function updateTimes() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.alert-ago[data-ts]').forEach(el => {
            const diff = now - parseInt(el.dataset.ts || 0);
            let label = '';
            if      (diff < 60)      label = 'just now';
            else if (diff < 3600)    label = `· ${Math.floor(diff/60)}m ago`;
            else if (diff < 86400)   label = `· ${Math.floor(diff/3600)}h ago`;
            else                     label = `· ${Math.floor(diff/86400)}d ago`;
            el.textContent = label;
        });
    }
    updateTimes();
    setInterval(updateTimes, 30000);
})();

function filterUsers() {
    const q=document.getElementById('userSearch').value.toLowerCase();
    const role=document.getElementById('roleFilter').value;
    const status=document.getElementById('statusFilter').value;
    document.querySelectorAll('#usersTable tbody tr').forEach(row => {
        const name=(row.cells[1]?.innerText||'').toLowerCase();
        const email=(row.cells[2]?.innerText||'').toLowerCase();
        const m=(name.includes(q)||email.includes(q))&&(role==='all'||row.dataset.role===role)&&(status==='all'||row.dataset.status===status);
        row.style.display=m?'':'none';
    });
    document.querySelectorAll('.user-mobile-card').forEach(card=>{
        const name=card.querySelector('.umc-name')?.innerText.toLowerCase()||'';
        const email=card.querySelector('.umc-email')?.innerText.toLowerCase()||'';
        const m=(name.includes(q)||email.includes(q))&&(role==='all'||card.dataset.role===role)&&(status==='all'||card.dataset.status===status);
        card.style.display=m?'':'none';
    });
    updateSel();
}
function toggleAll(){ const chk=document.getElementById('selectAll').checked; document.querySelectorAll('.ubox').forEach(b=>b.checked=chk); updateSel(); }
function updateSel(){
    selectedIds=Array.from(document.querySelectorAll('.ubox:checked')).map(b=>b.value);
    const n=selectedIds.length;
    document.getElementById('selCount').textContent=`${n} selected`;
    ['btnActivate','btnDeactivate','btnDelete'].forEach(id=>{const el=document.getElementById(id);if(el) el.disabled=n===0;});
}
function bulkDo(type){
    if(!selectedIds.length) return;
    const msgs={activate:`Activate ${selectedIds.length} user(s)?`,deactivate:`Deactivate ${selectedIds.length} user(s)?`,delete:`Delete ${selectedIds.length} user(s)? Cannot be undone.`};
    confirm_({activate:'Activate Users',deactivate:'Deactivate Users',delete:'Delete Users'}[type],msgs[type],'⚡',()=>{
        fetchPost('bulk_action',`bulk_type=${type}&user_ids=${JSON.stringify(selectedIds)}`).then(d=>{toast(d.message,d.success?'success':'critical');if(d.success) setTimeout(()=>location.reload(),700);});
    });
}
function openAddUser(){ document.getElementById('modalTitle').textContent='Add New User'; document.getElementById('uid').value=''; document.getElementById('fName').value=''; document.getElementById('fEmail').value=''; document.getElementById('fRole').value='staff'; document.getElementById('fPond').value=''; document.getElementById('pwField').style.display='block'; openModal('userModal'); }
function openEditUser(id){ fetchPost('get_user',`user_id=${id}`).then(d=>{ if(!d.success){toast(d.message,'critical');return;} const u=d.user; document.getElementById('modalTitle').textContent='Edit User'; document.getElementById('uid').value=u.user_id; document.getElementById('fName').value=u.full_name; document.getElementById('fEmail').value=u.email; document.getElementById('fRole').value=u.role; document.getElementById('fPond').value=u.assigned_pond||''; document.getElementById('pwField').style.display='none'; openModal('userModal'); }); }
function saveUser(){ const id=document.getElementById('uid').value,fn=document.getElementById('fName').value.trim(),em=document.getElementById('fEmail').value.trim(),rl=document.getElementById('fRole').value,ap=document.getElementById('fPond').value; if(!fn||!em){toast('Name and email required','warning');return;} const action=id?'edit_user':'add_user'; let body=`action=${action}&full_name=${encodeURIComponent(fn)}&email=${encodeURIComponent(em)}&role=${rl}&assigned_pond=${ap}`; if(id) body+=`&user_id=${id}`; else body+=`&password=${document.getElementById('fPw').value}`; fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body}).then(r=>r.json()).then(d=>{toast(d.message,d.success?'success':'critical');if(d.success){closeModal('userModal');setTimeout(()=>location.reload(),600);}}); }
function doDeactivate(id){ confirm_('Deactivate User','Deactivate this user?','🚫',()=>{ fetchPost('deactivate_user',`user_id=${id}`).then(d=>{toast(d.message,'warning');setTimeout(()=>location.reload(),600);}); }); }
function doActivate(id){ confirm_('Activate User','Activate this user?','✅',()=>{ fetchPost('activate_user',`user_id=${id}`).then(d=>{toast(d.message,'success');setTimeout(()=>location.reload(),600);}); }); }
function doDelete(id){ confirm_('Delete User','Permanently delete this user?','🗑️',()=>{ fetchPost('delete_user',`user_id=${id}`).then(d=>{toast(d.message,d.success?'success':'critical');if(d.success)setTimeout(()=>location.reload(),600);}); }); }

function showPondModal(key){ const pond=PONDS[key],cfg=POND_COORDS[key]; if(!pond||!cfg) return; const color=getColor(pond.status),mc=pond.organic_level>80?'meter-critical':(pond.organic_level>60?'meter-warning':'meter-safe'); document.getElementById('pondModalTitle').innerHTML=`<i class="fas fa-map-marker-alt" style="color:${color}"></i> ${cfg.name}`; document.getElementById('pondModalBody').innerHTML=`<div style="text-align:center;margin-bottom:.9rem"><span class="badge badge-${pond.status}" style="font-size:.8rem;padding:.4rem 1rem"><span class="dot-blink"></span> ${pond.status.toUpperCase()}</span></div><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-bottom:1rem"><div style="text-align:center;padding:.85rem .4rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--bdr)"><i class="fas fa-seedling ic-organic" style="font-size:1.3rem;display:block;margin-bottom:.3rem"></i><div class="metric-val ic-organic" style="font-size:1.3rem">${pond.organic_level}%</div><div class="metric-lbl">Organic</div><div class="meter" style="margin-top:.4rem"><div class="meter-fill ${mc}" style="width:${pond.organic_level}%"></div></div></div><div style="text-align:center;padding:.85rem .4rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--bdr)"><i class="fas fa-thermometer-half ic-temp" style="font-size:1.3rem;display:block;margin-bottom:.3rem"></i><div class="metric-val ic-temp" style="font-size:1.3rem">${pond.temperature}°C</div><div class="metric-lbl">Temp</div><div class="meter" style="margin-top:.4rem"><div class="meter-fill meter-warning" style="width:${Math.min(100,(pond.temperature-20)*10)}%"></div></div></div><div style="text-align:center;padding:.85rem .4rem;background:var(--bg-elevated);border-radius:var(--r-md);border:1px solid var(--bdr)"><i class="fas fa-flask ic-ph" style="font-size:1.3rem;display:block;margin-bottom:.3rem"></i><div class="metric-val ic-ph" style="font-size:1.3rem">${pond.ph}</div><div class="metric-lbl">pH</div><div class="meter" style="margin-top:.4rem"><div class="meter-fill meter-safe" style="width:${Math.min(100,pond.ph*10)}%"></div></div></div></div><div class="pond-detail-grid"><div class="detail-row"><div class="detail-lbl">Staff</div><div class="detail-val"><i class="fas fa-user" style="color:var(--cyan)"></i> ${pond.staff}</div></div><div class="detail-row"><div class="detail-lbl">Location</div><div class="detail-val"><i class="fas fa-map-pin" style="color:var(--red)"></i> ${pond.location}</div></div><div class="detail-row"><div class="detail-lbl">Coordinates</div><div class="detail-val" style="font-family:var(--fm);font-size:.75rem">${cfg.center[0].toFixed(4)}, ${cfg.center[1].toFixed(4)}</div></div><div class="detail-row"><div class="detail-lbl">Last Reading</div><div class="detail-val" style="font-family:var(--fm);font-size:.75rem">${new Date().toLocaleTimeString('en-US',{hour12:true,timeZone:'Asia/Manila'})}</div></div></div><div style="display:flex;gap:.5rem;margin-top:1rem"><button class="btn btn-primary btn-sm" style="flex:1" onclick="closeModal('pondModal');gotoMap('${key}')"><i class="fas fa-map-marker-alt"></i> Map</button><button class="btn btn-ghost btn-sm" style="flex:1" onclick="refreshPond('${key}');closeModal('pondModal')"><i class="fas fa-sync-alt"></i> Refresh</button></div>`; openModal('pondModal'); }

function genReport(type,btn){ document.querySelectorAll('.rpt-type-btn').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); fetchPost('generate_report',`type=${type}`).then(d=>{ if(!d.success) return; const r=d.report,labels={daily:'Daily Report',weekly:'Weekly Report',monthly:'Monthly Report'},dates={daily:'<?php echo date('M d, Y'); ?>',weekly:r.week||'',monthly:r.month||''}; const preview=document.getElementById('rptPreview'); preview.innerHTML=`<div class="rpt-header"><div class="rpt-title"><i class="fas fa-chart-bar" style="color:var(--cyan)"></i> ${labels[type]}</div><div class="rpt-date-badge">${dates[type]}</div></div>${type==='daily'?`<div class="rpt-stats"><div class="rpt-stat"><div class="rpt-stat-val">${r.total_ponds}</div><div class="rpt-stat-lbl">Total Ponds</div></div><div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--red)">${r.alerts_generated}</div><div class="rpt-stat-lbl">Alerts</div></div><div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--green)">${r.staff_active}</div><div class="rpt-stat-lbl">Active Staff</div></div></div><div class="rpt-status-row"><div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--green)">${r.safe_ponds}</div><div class="rpt-status-lbl" style="color:var(--green)">Safe</div></div><div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--amber)">${r.warning_ponds}</div><div class="rpt-status-lbl" style="color:var(--amber)">Warning</div></div><div class="rpt-status-item"><div class="rpt-status-val" style="color:var(--red)">${r.critical_ponds}</div><div class="rpt-status-lbl" style="color:var(--red)">Critical</div></div></div>`:`<div class="rpt-stats"><div class="rpt-stat"><div class="rpt-stat-val">${r.total_readings}</div><div class="rpt-stat-lbl">Readings</div></div><div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--amber)">${r.incidents}</div><div class="rpt-stat-lbl">Incidents</div></div><div class="rpt-stat"><div class="rpt-stat-val" style="color:var(--green)">${r.resolved}</div><div class="rpt-stat-lbl">Resolved</div></div></div>`}<div class="metrics-mini"><div class="metric-mini"><i class="fas fa-seedling ic-organic"></i><div><div class="metric-mini-val">${r.avg_organic}%</div><div class="metric-mini-lbl">Avg Organic</div></div></div><div class="metric-mini"><i class="fas fa-thermometer-half ic-temp"></i><div><div class="metric-mini-val">${r.avg_temp}°C</div><div class="metric-mini-lbl">Avg Temp</div></div></div><div class="metric-mini"><i class="fas fa-flask ic-ph"></i><div><div class="metric-mini-val">${r.avg_ph}</div><div class="metric-mini-lbl">Avg pH</div></div></div></div><div class="rpt-dl-row"><button class="btn btn-sm" style="flex:1;background:rgba(255,59,92,.12);color:var(--red);border:1px solid rgba(255,59,92,.25)" onclick="toast('PDF downloaded','success')"><i class="fas fa-file-pdf"></i> PDF</button><button class="btn btn-sm" style="flex:1;background:rgba(57,255,138,.12);color:var(--green);border:1px solid rgba(57,255,138,.25)" onclick="toast('Excel downloaded','success')"><i class="fas fa-file-excel"></i> Excel</button><button class="btn btn-sm" style="flex:1;background:rgba(255,184,0,.12);color:var(--amber);border:1px solid rgba(255,184,0,.25)" onclick="toast('CSV downloaded','success')"><i class="fas fa-file-csv"></i> CSV</button></div>`; toast(`${labels[type]} generated`,'success'); }); }

function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m.id);}));
document.addEventListener('keydown',e=>{if(e.key==='Escape') document.querySelectorAll('.modal.open').forEach(m=>m.classList.remove('open'));});
function confirm_(title,msg,icon,cb){ document.getElementById('confirmTitle').textContent=title; document.getElementById('confirmMsg').textContent=msg; document.getElementById('confirmIcon').textContent=icon||'⚠️'; document.getElementById('confirmOk').onclick=()=>{closeModal('confirmModal');cb&&cb();}; openModal('confirmModal'); }

function toast(msg,type='info'){ const c=document.getElementById('toastWrap'),t=document.createElement('div'); t.className=`toast ${type}`; const icons={success:'check-circle',warning:'exclamation-triangle',critical:'times-circle',info:'info-circle'}; t.innerHTML=`<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`; c.appendChild(t); setTimeout(()=>{t.style.opacity='0';t.style.transform='translateX(50px)';t.style.transition='.3s';setTimeout(()=>t.remove(),300);},3500); }

function fetchPost(action,body=''){return fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=${action}${body?'&'+body:''}`}).then(r=>r.json());}

const ADM_PRIORITY_COLORS = {low:'pri-low',normal:'pri-normal',high:'pri-high',critical:'pri-critical'};
const ADM_PRIORITY_LABELS = {low:'Low',normal:'Normal',high:'High',critical:'CRITICAL'};

function loadAdminMgrNotifs() {
    const icon = document.getElementById('admInboxRefreshIcon');
    if (icon) icon.style.animation = 'spin 1s linear infinite';

    fetchPost('get_admin_mgr_notifs')
    .then(d => {
        if (icon) icon.style.animation = '';
        const list = document.getElementById('admNotifsList');
        if (!list) return;

        const notifs = d.notifications || [];

        const pending   = notifs.filter(n => n.status === 'Pending').length;
        const received  = notifs.filter(n => n.status === 'Received').length;
        const completed = notifs.filter(n => n.status === 'Completed').length;

        const _kpi = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        _kpi('admKpi-pending',   pending);
        _kpi('admKpi-received',  received);
        _kpi('admKpi-completed', completed);

        const badge = document.getElementById('admInboxBadge');
        if (badge) { badge.textContent = pending > 0 ? pending : ''; badge.style.display = pending > 0 ? '' : 'none'; }

        notifs.forEach(n => {
            if (n.status === 'Pending') {
                fetchPost('mark_notif_received', `notif_id=${n.id}`).catch(() => {});
            }
        });

        if (notifs.length === 0) {
            list.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--muted)">
                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.3"></i>
                <div style="font-size:.85rem">No manager notifications yet.</div>
            </div>`;
            return;
        }

        list.innerHTML = notifs.map(n => {
            const priorClass = ADM_PRIORITY_COLORS[n.priority] || 'pri-normal';
            const priorLabel = ADM_PRIORITY_LABELS[n.priority] || n.priority;
            const displayStatus = n.status === 'Pending' ? 'Received' : n.status;

            const sentTime  = new Date(n.sent_at).toLocaleString('en-US',{timeZone:'Asia/Manila',hour12:true,month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
            const rcvTime   = n.received_at  ? new Date(n.received_at).toLocaleString('en-US',  {timeZone:'Asia/Manila',hour12:true,hour:'2-digit',minute:'2-digit'}) : 'Just now';
            const doneTime  = n.completed_at ? new Date(n.completed_at).toLocaleString('en-US', {timeZone:'Asia/Manila',hour12:true,hour:'2-digit',minute:'2-digit'}) : null;

            const statusIcons = {Pending:'⏳',Received:'👁',Completed:'✅'};
            const isDone = n.status === 'Completed';

            return `<div class="adm-notif-item status-${displayStatus}" id="adm-notif-${n.id}">
                <div class="adm-notif-head">
                    <div class="adm-notif-sender">
                        <span class="priority-pip ${priorClass}"></span>
                        <i class="fas fa-user-tie" style="color:var(--cyan);font-size:.8rem"></i>
                        <span>${_admEsc(n.manager_name || 'Manager')}</span>
                        ${n.pond_name ? `<span class="badge badge-info" style="font-size:.58rem">Pond ${_admEsc(n.pond_name)}</span>` : ''}
                        <span style="font-family:var(--fm);font-size:.6rem;color:var(--muted)">${priorLabel}</span>
                    </div>
                    <span class="status-pill ${displayStatus}">${statusIcons[displayStatus] || ''} ${displayStatus}</span>
                </div>
                <div class="adm-notif-msg">${_admEsc(n.message)}</div>
                <div class="adm-notif-meta">
                    <span><i class="far fa-clock"></i> Sent: ${sentTime}</span>
                    <span style="color:var(--cyan)"><i class="fas fa-eye"></i> Received: ${rcvTime}</span>
                    ${doneTime ? `<span style="color:var(--green)"><i class="fas fa-check"></i> Done: ${doneTime}</span>` : ''}
                </div>
                ${n.admin_note ? `<div style="margin-top:.4rem;font-size:.76rem;color:var(--teal);font-style:italic"><i class="fas fa-reply"></i> Your note: ${_admEsc(n.admin_note)}</div>` : ''}
                ${!isDone ? `
                <div class="adm-notif-foot">
                    <input class="adm-notif-note-inp" type="text"
                        id="note-${n.id}"
                        placeholder="Optional note to manager…"
                        maxlength="255">
                    <button class="btn btn-success btn-sm"
                            onclick="markNotifDone(${n.id})">
                        <i class="fas fa-check-double"></i> Mark Done
                    </button>
                </div>` : `
                <div style="margin-top:.5rem;font-family:var(--fm);font-size:.65rem;color:var(--green)">
                    <i class="fas fa-check-circle"></i> Completed — no further action needed
                </div>`}
            </div>`;
        }).join('');
    })
    .catch(() => { if (icon) icon.style.animation = ''; });
}

function markNotifDone(id) {
    const noteEl = document.getElementById('note-' + id);
    const note   = noteEl ? noteEl.value.trim() : '';

    confirm_('Mark as Done', 'Mark this request as Completed?', '✅', () => {
        fetchPost('mark_notif_done', `notif_id=${id}&admin_note=${encodeURIComponent(note)}`)
        .then(d => {
            if (!d.success) { toast(d.message || 'Failed', 'critical'); return; }
            toast('Marked as Completed — manager will see the update.', 'success');
            loadAdminMgrNotifs();
        })
        .catch(() => toast('Network error', 'critical'));
    });
}

function _admEsc(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function _pollAdmInboxBadge() {
    fetchPost('get_pending_mgr_count')
    .then(d => {
        const badge = document.getElementById('admInboxBadge');
        if (!badge) return;
        const cnt = d.count || 0;
        badge.textContent  = cnt > 0 ? cnt : '';
        badge.style.display = cnt > 0 ? '' : 'none';
    })
    .catch(() => {});
}
setInterval(_pollAdmInboxBadge, 60000);

setInterval(() => {
    if (document.getElementById('sec-mgr-inbox')?.classList.contains('active')) {
        loadAdminMgrNotifs();
    }
}, 30000);

document.addEventListener('DOMContentLoaded', () => { _pollAdmInboxBadge(); });

window.addEventListener('resize',()=>{if(map)setTimeout(()=>map.invalidateSize(),100);if(metricsChart)metricsChart.resize();});
window.addEventListener('orientationchange',()=>{setTimeout(()=>{if(map)map.invalidateSize();if(metricsChart)metricsChart.resize();},350);});

(function(){ const a=document.getElementById('sysAlert'); if(a) setTimeout(()=>{a.style.opacity='0';a.style.transition='.4s';setTimeout(()=>a.remove(),400);},5000); })();

let sessionTotal = 1800;
let sessionLeft = sessionTotal;
let sessionWarned = false;

function initSessionTimer() {
    setInterval(() => {
        sessionLeft = Math.max(0, sessionLeft - 1);
        const bar  = document.getElementById('sessionBar');
        const fill = document.getElementById('sessionFill');
        const timeEl = document.getElementById('sessionTime');
        if (!bar || !fill || !timeEl) return;

        const pct  = (sessionLeft / sessionTotal) * 100;
        const mins = Math.floor(sessionLeft / 60);
        const secs = sessionLeft % 60;
        timeEl.textContent = `${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
        fill.style.width = pct + '%';

        if (sessionLeft <= 120 && !sessionWarned) {
            sessionWarned = true;
            bar.classList.add('warning');
            toast('Session expiring in 2 minutes! Save your work.', 'warning');
        }
        if (sessionLeft === 0) {
            toast('Session expired. Redirecting to login…', 'critical');
            setTimeout(() => { window.location.href = '../auth/logout.php'; }, 2000);
        }
    }, 1000);
}

function updateSessionDuration() {
    const mins   = parseInt(document.getElementById('sessionTimeout')?.value || 30);
    sessionTotal = mins * 60;
    sessionLeft  = sessionTotal;
    sessionWarned = false;
    const bar = document.getElementById('sessionBar');
    if (bar) bar.classList.remove('warning');
}

function initNetworkStatus() {
    const banner = document.getElementById('netBanner');
    if (!banner) return;

    window.addEventListener('offline', () => {
        banner.textContent = '⚠ No internet connection — some features may not work';
        banner.className   = 'net-banner offline';
    });

    window.addEventListener('online', () => {
        banner.textContent = '✓ Connection restored';
        banner.className   = 'net-banner online-back';
        setTimeout(() => { banner.className = 'net-banner'; banner.textContent = ''; }, 3000);
    });
}

function initKeyboardShortcuts() {
    document.addEventListener('keydown', e => {
        if (document.querySelector('.modal.open')) return;
        if (e.altKey) {
            const map = {'1':'overview','2':'users','3':'ponds','4':'map',
                         '5':'charts','6':'alerts','7':'reports','8':'activities',
                         '9':'iot','0':'settings'};
            if (map[e.key]) { e.preventDefault(); showSection(map[e.key]); }
        }
    });
}

function initSwipeGestures() {
    let startX = 0, startY = 0;
    document.addEventListener('touchstart', e => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    }, { passive: true });
    document.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - startX;
        const dy = Math.abs(e.changedTouches[0].clientY - startY);
        if (dy > 60) return;
        if (dx > 70 && startX < 30) {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sidebarOverlay').classList.add('active');
        } else if (dx < -70 && document.getElementById('sidebar').classList.contains('open')) {
            closeSidebar();
        }
    }, { passive: true });
}

const iotCharts = {};

function initIotCharts() {
    <?php foreach(array_keys($ponds_data) as $key): ?>
    (function() {
        const ctx = document.getElementById('iotChart-<?php echo $key; ?>');
        if (!ctx) return;
        iotCharts['<?php echo $key; ?>'] = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    { label:'Organic', data:[], borderColor:'#39ff8a', backgroundColor:'rgba(57,255,138,.08)', tension:.4, borderWidth:1.5, pointRadius:0, fill:true },
                    { label:'Temp',    data:[], borderColor:'#ffb800', backgroundColor:'rgba(255,184,0,.05)', tension:.4, borderWidth:1.5, pointRadius:0, fill:true },
                    { label:'pH',      data:[], borderColor:'#b06cff', backgroundColor:'rgba(176,108,255,.05)', tension:.4, borderWidth:1.5, pointRadius:0, fill:true }
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false, animation:{duration:400},
                interaction:{mode:'index',intersect:false},
                plugins:{ legend:{display:false}, tooltip:{backgroundColor:'rgba(11,22,37,.9)',titleColor:'#e8f4ff',bodyColor:'#8ba8c4',borderColor:'rgba(0,229,255,.15)',borderWidth:1,padding:8} },
                scales:{
                    x:{display:false},
                    y:{display:false}
                }
            }
        });
        loadPondHistory('<?php echo $key; ?>');
    })();
    <?php endforeach; ?>
}

function loadPondHistory(key) {
    fetchPost('get_pond_history', `pond_key=${key}`)
    .then(d => {
        if (!d.success || !d.history) return;
        const h = d.history;
        const ch = iotCharts[key];
        if (ch) {
            ch.data.labels = h.labels;
            ch.data.datasets[0].data = h.organic;
            ch.data.datasets[1].data = h.temperature;
            ch.data.datasets[2].data = h.ph;
            ch.update();
        }
        const tbody = document.getElementById('iotTbody-' + key);
        if (tbody && h.labels.length) {
            tbody.innerHTML = h.labels.map((lbl, i) => `
                <tr>
                    <td>${lbl}</td>
                    <td style="color:var(--green)">${h.organic[i]}%</td>
                    <td style="color:var(--amber)">${h.temperature[i]}°C</td>
                    <td style="color:var(--violet)">${h.ph[i]}</td>
                </tr>`).join('');
        }
        const o = document.getElementById('iot-o-' + key);
        const t = document.getElementById('iot-t-' + key);
        const p = document.getElementById('iot-p-' + key);
        if (o && h.organic.length)     o.textContent = h.organic[h.organic.length-1] + '%';
        if (t && h.temperature.length) t.textContent = h.temperature[h.temperature.length-1] + '°C';
        if (p && h.ph.length)          p.textContent = h.ph[h.ph.length-1];
    });
}

function loadSysStats() {
    const icon = document.getElementById('sysRefreshIcon');
    if (icon) icon.style.animation = 'spin 1s linear infinite';

    fetchPost('get_system_stats')
    .then(d => {
        if (icon) icon.style.animation = '';
        if (!d.success) return;
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        set('ssStat-php',    'PHP ' + d.php_version);
        set('ssStat-mem',    d.memory_usage + ' MB');
        set('ssStat-users',  d.total_users);
        set('ssStat-ponds',  d.total_ponds);
        set('ssStat-reads',  d.total_readings);
        set('ssStat-alerts', d.total_alerts);
        set('ssStat-time',   d.ph_time);
    });
}

function exportCSV(type) {
    let csv = '', filename = '';

    if (type === 'ponds') {
        filename = 'ponds_' + Date.now() + '.csv';
        csv = 'Pond,Status,Organic(%),Temp(°C),pH,Staff,Location,Last Reading\n';
        <?php foreach($ponds_data as $key => $pond): ?>
        csv += `<?php echo $key; ?>,<?php echo $pond['status']; ?>,<?php echo $pond['organic_level']; ?>,<?php echo $pond['temperature']; ?>,<?php echo $pond['ph']; ?>,"<?php echo addslashes($pond['staff']); ?>","<?php echo addslashes($pond['location']); ?>","<?php echo $pond['last_reading']; ?>"\n`;
        <?php endforeach; ?>
    } else if (type === 'alerts') {
        filename = 'alerts_' + Date.now() + '.csv';
        csv = 'Pond,Message,Status,Type,Created At\n';
        <?php foreach($alerts as $al): ?>
        csv += `"<?php echo addslashes($al['pond_name'] ?? ''); ?>","<?php echo addslashes($al['message']); ?>","<?php echo $al['status']; ?>","<?php echo $al['type']; ?>","<?php echo $al['created_at']; ?>"\n`;
        <?php endforeach; ?>
    } else if (type === 'users') {
        filename = 'users_' + Date.now() + '.csv';
        csv = 'Name,Email,Role,Status,Pond,Last Login\n';
        <?php foreach($users as $u): ?>
        csv += `"<?php echo addslashes($u['full_name']); ?>","<?php echo addslashes($u['email']); ?>","<?php echo $u['role']; ?>","<?php echo $u['status']; ?>","<?php echo $u['assigned_pond'] ?? ''; ?>","<?php echo $u['last_login'] ?? ''; ?>"\n`;
        <?php endforeach; ?>
    }

    if (!csv) { toast('No data to export', 'warning'); return; }
    const blob  = new Blob([csv], { type: 'text/csv' });
    const url   = URL.createObjectURL(blob);
    const a     = document.createElement('a');
    a.href      = url;
    a.download  = filename;
    a.click();
    URL.revokeObjectURL(url);
    toast(`Exported: ${filename}`, 'success');
}

let simulationRunning = true;
let simulationInterval = null;

function saveSetting() {
    const data = {
        notif_critical: document.getElementById('tog-critical')?.checked ? 1 : 0,
        notif_warning:  document.getElementById('tog-warning')?.checked  ? 1 : 0,
        notif_info:     document.getElementById('tog-info')?.checked     ? 1 : 0,
        refresh_rate:   document.getElementById('refreshRate')?.value    || 5,
    };
    fetchPost('save_settings',
        `notif_critical=${data.notif_critical}&notif_warning=${data.notif_warning}&notif_info=${data.notif_info}&refresh_rate=${data.refresh_rate}`)
    .then(d => { if (d.success) toast('Settings saved', 'success'); });
}

function toggleSimulation() {
    simulationRunning = document.getElementById('tog-sim')?.checked ?? true;
    toast(simulationRunning ? 'IOT simulation resumed' : 'IOT simulation paused', 'info');
}

function toggleAnimation() {
    const on = document.getElementById('tog-anim')?.checked ?? true;
    document.body.style.setProperty('--anim-state', on ? 'running' : 'paused');
    document.querySelectorAll('*').forEach(el => {
        if (on) el.style.animationPlayState = '';
        else    el.style.animationPlayState = 'paused';
    });
    toast(on ? 'Animations enabled' : 'Animations paused', 'info');
}

function confirmLogout() {
    confirm_('Logout', 'Are you sure you want to logout?', '🚪', () => {
        window.location.href = '../auth/logout.php';
    });
}

let iotInited = false;

function maybeInitIot(name) {
    if (name === 'iot' && !iotInited) {
        iotInited = true;
        initIotCharts();
        loadSysStats();
    }
}

const _origShowSection = showSection;
showSection = function(name) {
    _origShowSection(name);
    maybeInitIot(name);
    if (name === 'mgr-inbox') loadAdminMgrNotifs();
};

document.addEventListener('DOMContentLoaded', () => {
    initSessionTimer();
    initNetworkStatus();
    initKeyboardShortcuts();
    initSwipeGestures();
});

function highlightText(el, query) {
    if (!el || !query) return;
    const orig = el.dataset.origText || el.textContent;
    el.dataset.origText = orig;
    if (!query.trim()) { el.innerHTML = orig; return; }
    const re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
    el.innerHTML = orig.replace(re, '<mark style="background:rgba(0,229,255,.3);color:var(--cyan);border-radius:2px;padding:0 2px">$1</mark>');
}

(function() {
    let idleTimer = null;
    let idleWarn  = false;
    const IDLE_LIMIT = 5 * 60 * 1000;

    function resetIdle() {
        if (idleWarn) { idleWarn = false; }
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => {
            if (!idleWarn) {
                idleWarn = true;
                toast('You\'ve been idle for 5 minutes', 'warning');
            }
        }, IDLE_LIMIT);
    }

    ['mousedown','mousemove','keydown','touchstart','scroll'].forEach(evt => {
        document.addEventListener(evt, resetIdle, { passive: true });
    });
    resetIdle();
})();

(function() {
    const searchInput = document.getElementById('userSearch');
    if (!searchInput) return;
    searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#usersTable tbody tr td:nth-child(2)').forEach(td => {
            highlightText(td, q);
        });
        document.querySelectorAll('.umc-name').forEach(el => {
            highlightText(el, q);
        });
    });
})();

document.addEventListener('click', function(e) {
    const card = e.target.closest('.pond-card, .staff-card, .kpi');
    if (!card) return;
    const rect   = card.getBoundingClientRect();
    const ripple = document.createElement('span');
    const size   = Math.max(rect.width, rect.height);
    ripple.style.cssText = `
        position:absolute;border-radius:50%;
        width:${size}px;height:${size}px;
        left:${e.clientX - rect.left - size/2}px;
        top:${e.clientY - rect.top - size/2}px;
        background:rgba(0,229,255,.15);
        transform:scale(0);animation:rippleAnim .5s ease;
        pointer-events:none;z-index:0;
    `;
    if (getComputedStyle(card).position === 'static') card.style.position = 'relative';
    card.appendChild(ripple);
    setTimeout(() => ripple.remove(), 500);
});

(function() {
    const s = document.createElement('style');
    s.textContent = '@keyframes rippleAnim{0%{transform:scale(0);opacity:1}100%{transform:scale(2.5);opacity:0}}';
    document.head.appendChild(s);
})();

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        Object.keys(PONDS).forEach(key => {
            fetchPost('get_iot_reading', `pond_key=${key}`)
            .then(d => {
                if (d && d.success) {
                    updatePondDisplay(key, d.organic, d.temp, d.ph, d.status, d.timestamp);
                }
            })
            .catch(() => {});
        });
    }
});

console.log('%c ██████╗  ██████╗ ', 'color:#00e5ff;font-weight:bold');
console.log('%c AquaAdmin v2.0 — Organic Matter Detection in Tilapia', 'color:#39ff8a;font-size:13px;font-weight:bold');
console.log('%c Manolo Fortich, Bukidnon, Philippines', 'color:#8ba8c4');
console.log('%c Alt+1…0: sections │ Esc: close modal', 'color:#4a6380');
console.log('%c IOT simulation active — refreshing every 5s', 'color:#ffb800');
console.log('%c Bottom nav: Overview · Users · Ponds · Map · Alerts', 'color:#b06cff');
</script>
</body>
</html>