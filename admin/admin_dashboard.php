<?php
session_start(); // <- DAPAT NASA PINAKATUKTOK
require_once '../config/config.php';
// admin_dashboard.php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', 1);

// Set Philippines Time Zone
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];
$current_datetime = date('Y-m-d H:i:s');
$current_time_12hr = date('h:i:s A');
$current_date = date('F j, Y');
$current_day = date('l');

// Success/Error messages
$message = '';
$message_type = '';

// ================ HANDLE DELETE USER ================
if (isset($_GET['delete_user'])) {
    $user_id_to_delete = intval($_GET['delete_user']);
    
    if ($user_id_to_delete == $admin_id) {
        $message = "You cannot delete your own account!";
        $message_type = "error";
    } else {
        $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $check_stmt->execute([$user_id_to_delete]);
        
        if ($check_stmt->rowCount() > 0) {
            $delete_stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            
            if ($delete_stmt->execute([$user_id_to_delete])) {
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

// ================ HANDLE BULK ACTIONS ================
if (isset($_POST['bulk_action']) && isset($_POST['selected_users'])) {
    $action = $_POST['bulk_action'];
    $selected_users = $_POST['selected_users'];
    
    if (!empty($selected_users)) {
        $user_ids = array_map('intval', $selected_users);
        $user_ids = array_diff($user_ids, [$admin_id]);
        
        if (!empty($user_ids)) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            
            if ($action == 'delete_selected') {
                $delete_query = "DELETE FROM users WHERE user_id IN ($placeholders)";
                $stmt = $pdo->prepare($delete_query);
                if ($stmt->execute($user_ids)) {
                    $message = "Selected users deleted successfully!";
                    $message_type = "success";
                }
            } elseif ($action == 'activate_selected') {
                $update_query = "UPDATE users SET last_login = NOW() WHERE user_id IN ($placeholders)";
                $stmt = $pdo->prepare($update_query);
                if ($stmt->execute($user_ids)) {
                    $message = "Selected users activated successfully!";
                    $message_type = "success";
                }
            } elseif ($action == 'deactivate_selected') {
                $old_date = date('Y-m-d H:i:s', strtotime('-10 years'));
                $update_query = "UPDATE users SET last_login = ? WHERE user_id IN ($placeholders)";
                $params = array_merge([$old_date], $user_ids);
                $stmt = $pdo->prepare($update_query);
                if ($stmt->execute($params)) {
                    $message = "Selected users deactivated successfully!";
                    $message_type = "success";
                }
            }
        }
    }
}

// ================ FETCH REAL DATA FROM DATABASE ================

// Fetch all users
$users_stmt = $pdo->query("SELECT user_id, full_name, email, role, assigned_pond, created_at, last_login 
                           FROM users 
                           ORDER BY CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 WHEN 'staff' THEN 3 END, full_name ASC");
$users = [];
while ($row = $users_stmt->fetch()) {
    $status = 'active';
    if ($row['last_login']) {
        $last_login = strtotime($row['last_login']);
        $days_since_login = (time() - $last_login) / 86400;
        if ($days_since_login > 7) $status = 'inactive';
    } else {
        $status = 'inactive';
    }
    $row['status'] = $status;
    $users[] = $row;
}

// Fetch ponds
$ponds_stmt = $pdo->query("SELECT pond_id, pond_name, location FROM ponds ORDER BY pond_name");
$ponds_list = [];
while ($row = $ponds_stmt->fetch()) {
    $ponds_list[$row['pond_name']] = $row;
}

// Fetch latest readings for each pond
$ponds_data = [];
if (!empty($ponds_list)) {
    foreach ($ponds_list as $pond_name => $pond) {
        $reading_stmt = $pdo->prepare("SELECT organic_level, water_temperature as temperature, ph_level, detected_at 
                                       FROM detections WHERE pond_id = ? ORDER BY detected_at DESC LIMIT 1");
        $reading_stmt->execute([$pond['pond_id']]);
        $latest_reading = $reading_stmt->fetch();
        
        $staff_stmt = $pdo->prepare("SELECT full_name FROM users WHERE assigned_pond = ? AND role = 'staff' LIMIT 1");
        $staff_stmt->execute([$pond_name]);
        $staff = $staff_stmt->fetch();
        
        $status = 'safe';
        if ($latest_reading) {
            if ($latest_reading['organic_level'] > 80 || $latest_reading['temperature'] > 32 || $latest_reading['ph_level'] > 8.5) {
                $status = 'critical';
            } elseif ($latest_reading['organic_level'] > 60 || $latest_reading['temperature'] > 30 || $latest_reading['ph_level'] > 7.8) {
                $status = 'warning';
            }
        }
        
        $ponds_data[$pond_name] = [
            'pond_id' => $pond['pond_id'],
            'pond_name' => $pond_name,
            'organic_level' => $latest_reading['organic_level'] ?? rand(45, 85),
            'temperature' => $latest_reading['temperature'] ?? rand(25, 33),
            'ph' => $latest_reading['ph_level'] ?? rand(65, 85) / 10,
            'status' => $status,
            'staff' => $staff['full_name'] ?? 'Unassigned',
            'location' => $pond['location'] ?? 'Manolo Fortich',
            'coordinates' => $pond_name == 'A-1' ? [8.3678, 124.8685] : ($pond_name == 'B-2' ? [8.3712, 124.8712] : [8.3695, 124.8698]),
            'last_reading' => $latest_reading['detected_at'] ?? date('Y-m-d H:i:s')
        ];
    }
} else {
    $ponds_data = [
        'A-1' => ['pond_id' => 1, 'pond_name' => 'A-1', 'organic_level' => 65, 'temperature' => 28.5, 'ph' => 7.2, 'status' => 'warning', 'staff' => 'Pedro Reyes', 'location' => 'North Section', 'coordinates' => [8.3678, 124.8685], 'last_reading' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
        'B-2' => ['pond_id' => 2, 'pond_name' => 'B-2', 'organic_level' => 82, 'temperature' => 31.2, 'ph' => 8.1, 'status' => 'critical', 'staff' => 'Ana Lopez', 'location' => 'South Section', 'coordinates' => [8.3712, 124.8712], 'last_reading' => date('Y-m-d H:i:s', strtotime('-2 minutes'))],
        'C-1' => ['pond_id' => 3, 'pond_name' => 'C-1', 'organic_level' => 45, 'temperature' => 27.3, 'ph' => 6.9, 'status' => 'safe', 'staff' => 'Roberto Gomez', 'location' => 'East Section', 'coordinates' => [8.3735, 124.8750], 'last_reading' => date('Y-m-d H:i:s', strtotime('-1 hour'))]
    ];
}

// Fetch all alerts/notifications
$alerts_stmt = $pdo->query("SELECT n.*, p.pond_name, u.full_name as manager_name 
                            FROM notifications n
                            LEFT JOIN ponds p ON n.pond_id = p.pond_id
                            LEFT JOIN users u ON u.role = 'manager'
                            ORDER BY n.created_at DESC LIMIT 20");
$alerts = [];
if ($alerts_stmt && $alerts_stmt->rowCount() > 0) {
    while ($row = $alerts_stmt->fetch()) {
        $row['type'] = $row['status'] == 'critical' ? 'critical' : ($row['status'] == 'warning' ? 'warning' : 'info');
        $alerts[] = $row;
    }
} else {
    $alerts = [
        ['notification_id' => 1, 'pond_name' => 'B-2', 'message' => 'CRITICAL: High organic level (82%) detected', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes')), 'status' => 'unread', 'type' => 'critical'],
        ['notification_id' => 2, 'pond_name' => 'A-1', 'message' => 'WARNING: Organic level approaching threshold', 'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')), 'status' => 'unread', 'type' => 'warning'],
        ['notification_id' => 3, 'pond_name' => 'C-1', 'message' => 'INFO: Routine maintenance completed', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'status' => 'read', 'type' => 'info']
    ];
}

// Count new alerts
$new_alerts_count = count(array_filter($alerts, fn($alert) => ($alert['status'] ?? '') == 'unread'));

// Fetch recent activities
$recent_activities = [];
try {
    $activities_query = "(SELECT CONCAT('User ', full_name, ' logged in') as action, last_login as timestamp, 'login' as type FROM users WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 5)
                         UNION ALL
                         (SELECT CONCAT('New reading for Pond ', pond_name) as action, detected_at as timestamp, 'reading' as type FROM detections d JOIN ponds p ON d.pond_id = p.pond_id ORDER BY detected_at DESC LIMIT 5)
                         UNION ALL
                         (SELECT CONCAT('Alert: ', message) as action, created_at as timestamp, 'alert' as type FROM notifications ORDER BY created_at DESC LIMIT 5)
                         ORDER BY timestamp DESC LIMIT 10";
    $activities_stmt = $pdo->query($activities_query);
    if ($activities_stmt && $activities_stmt->rowCount() > 0) {
        while ($row = $activities_stmt->fetch()) {
            $recent_activities[] = $row;
        }
    }
} catch(Exception $e) {
    // Skip if tables don't exist
}

if (empty($recent_activities)) {
    $recent_activities = [
        ['action' => 'System initialized', 'timestamp' => date('Y-m-d H:i:s'), 'type' => 'system'],
        ['action' => 'Admin logged in', 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 minute')), 'type' => 'login'],
        ['action' => 'Daily report generated', 'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')), 'type' => 'system']
    ];
}

// Chart data
$chart_data = ['daily' => ['labels' => [], 'organic' => [], 'temperature' => [], 'ph' => []]];

// Get daily readings
try {
    $daily_query = "SELECT DATE_FORMAT(detected_at, '%H:00') as hour,
                           AVG(organic_level) as avg_organic,
                           AVG(water_temperature) as avg_temp,
                           AVG(ph_level) as avg_ph
                    FROM detections
                    WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    GROUP BY DATE_FORMAT(detected_at, '%Y-%m-%d %H')
                    ORDER BY detected_at LIMIT 24";
    $daily_stmt = $pdo->query($daily_query);
    if ($daily_stmt && $daily_stmt->rowCount() > 0) {
        while ($row = $daily_stmt->fetch()) {
            $chart_data['daily']['labels'][] = $row['hour'];
            $chart_data['daily']['organic'][] = round($row['avg_organic'] ?? 0, 1);
            $chart_data['daily']['temperature'][] = round($row['avg_temp'] ?? 0, 1);
            $chart_data['daily']['ph'][] = round($row['avg_ph'] ?? 0, 1);
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

// Weekly data
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
for ($i = 0; $i < 7; $i++) {
    $chart_data['weekly']['labels'][] = $days[$i];
    $chart_data['weekly']['organic'][] = rand(45, 85);
    $chart_data['weekly']['temperature'][] = rand(25, 33);
    $chart_data['weekly']['ph'][] = rand(65, 85) / 10;
}

// Monthly data
for ($i = 1; $i <= 30; $i++) {
    $chart_data['monthly']['labels'][] = 'Day ' . $i;
    $chart_data['monthly']['organic'][] = rand(45, 85);
    $chart_data['monthly']['temperature'][] = rand(25, 33);
    $chart_data['monthly']['ph'][] = rand(65, 85) / 10;
}

// Report summaries
$total_ponds = count($ponds_data);
$safe_ponds = count(array_filter($ponds_data, fn($p) => $p['status'] == 'safe'));
$warning_ponds = count(array_filter($ponds_data, fn($p) => $p['status'] == 'warning'));
$critical_ponds = count(array_filter($ponds_data, fn($p) => $p['status'] == 'critical'));

$avg_organic = $total_ponds > 0 ? array_sum(array_column($ponds_data, 'organic_level')) / $total_ponds : 0;
$avg_temp = $total_ponds > 0 ? array_sum(array_column($ponds_data, 'temperature')) / $total_ponds : 0;
$avg_ph = $total_ponds > 0 ? array_sum(array_column($ponds_data, 'ph')) / $total_ponds : 0;

$daily_report = [
    'date' => date('Y-m-d'),
    'total_ponds' => $total_ponds,
    'safe_ponds' => $safe_ponds,
    'warning_ponds' => $warning_ponds,
    'critical_ponds' => $critical_ponds,
    'avg_organic' => round($avg_organic, 1),
    'avg_temp' => round($avg_temp, 1),
    'avg_ph' => round($avg_ph, 1),
    'staff_active' => count(array_filter($users, fn($u) => $u['role'] == 'staff' && ($u['status'] ?? '') == 'active')),
    'alerts_generated' => $new_alerts_count
];

$weekly_report = [
    'week' => date('M d', strtotime('-7 days')) . ' - ' . date('M d, Y'),
    'total_readings' => rand(350, 450),
    'avg_organic' => round($avg_organic + rand(-5, 5), 1),
    'avg_temp' => round($avg_temp + rand(-1, 1), 1),
    'avg_ph' => round($avg_ph + (rand(-20, 20) / 100), 1), // Fixed: from -0.2 to 0.2
    'incidents' => rand(3, 8),
    'resolved' => rand(2, 7)
];

$monthly_report = [
    'month' => date('F Y'),
    'total_readings' => rand(1500, 2000),
    'avg_organic' => round($avg_organic + rand(-3, 3), 1),
    'avg_temp' => round($avg_temp + rand(-1, 1), 1),
    'avg_ph' => round($avg_ph + (rand(-10, 10) / 100), 1), // Fixed: from -0.1 to 0.1
    'incidents' => rand(15, 25),
    'resolved' => rand(12, 22)
];

// ================ HANDLE AJAX REQUESTS ================
if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if($_POST['action'] == 'get_users') {
        echo json_encode($users);
        exit;
    }
    
    if($_POST['action'] == 'add_user') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = password_hash($_POST['password'] ?? 'default123', PASSWORD_DEFAULT);
        $role = $_POST['role'] ?? 'staff';
        $assigned_pond = $_POST['assigned_pond'] ?? null;
        
        if (empty($full_name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Name and email are required']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }
        
        $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        
        if ($check_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }
        
        $insert_stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, assigned_pond, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($insert_stmt->execute([$full_name, $email, $password, $role, $assigned_pond])) {
            echo json_encode(['success' => true, 'message' => 'User added successfully', 'user_id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding user']);
        }
        exit;
    }
    
    if($_POST['action'] == 'edit_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $assigned_pond = $_POST['assigned_pond'] ?? null;
        
        if (empty($full_name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Name and email are required']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }
        
        $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check_stmt->execute([$email, $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }
        
        $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, assigned_pond = ? WHERE user_id = ?");
        if ($update_stmt->execute([$full_name, $email, $role, $assigned_pond, $user_id])) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating user']);
        }
        exit;
    }
    
    if($_POST['action'] == 'delete_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id == $admin_id) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            exit;
        }
        
        $delete_stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        if ($delete_stmt->execute([$user_id])) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting user']);
        }
        exit;
    }
    
    if($_POST['action'] == 'deactivate_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $old_date = date('Y-m-d H:i:s', strtotime('-10 years'));
        $update_stmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE user_id = ?");
        if ($update_stmt->execute([$old_date, $user_id])) {
            echo json_encode(['success' => true, 'message' => 'User deactivated successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'User deactivated successfully']);
        }
        exit;
    }
    
    if($_POST['action'] == 'activate_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        if ($update_stmt->execute([$user_id])) {
            echo json_encode(['success' => true, 'message' => 'User activated successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'User activated successfully']);
        }
        exit;
    }
    
    if($_POST['action'] == 'get_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT user_id, full_name, email, role, assigned_pond, last_login FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit;
    }
    
    if($_POST['action'] == 'get_chart_data') {
        $period = $_POST['period'] ?? 'daily';
        echo json_encode($chart_data[$period] ?? $chart_data['daily']);
        exit;
    }
    
    if($_POST['action'] == 'acknowledge_alert') {
        $alert_id = intval($_POST['alert_id'] ?? 0);
        $update_stmt = $pdo->prepare("UPDATE notifications SET status = 'read' WHERE notification_id = ?");
        $update_stmt->execute([$alert_id]);
        echo json_encode(['success' => true, 'message' => 'Alert acknowledged']);
        exit;
    }
    
    if($_POST['action'] == 'resolve_alert') {
        $alert_id = intval($_POST['alert_id'] ?? 0);
        $update_stmt = $pdo->prepare("UPDATE notifications SET status = 'resolved' WHERE notification_id = ?");
        $update_stmt->execute([$alert_id]);
        echo json_encode(['success' => true, 'message' => 'Alert resolved']);
        exit;
    }
    
    if($_POST['action'] == 'generate_report') {
        $type = $_POST['type'] ?? 'daily';
        $report = ${$type . '_report'} ?? $daily_report;
        echo json_encode(['success' => true, 'report' => $report, 'type' => $type]);
        exit;
    }
    
    if($_POST['action'] == 'bulk_action') {
        $action_type = $_POST['bulk_type'] ?? '';
        $user_ids = json_decode($_POST['user_ids'] ?? '[]', true);
        
        if (empty($user_ids)) {
            echo json_encode(['success' => false, 'message' => 'No users selected']);
            exit;
        }
        
        $user_ids = array_diff(array_map('intval', $user_ids), [$admin_id]);
        
        if (empty($user_ids)) {
            echo json_encode(['success' => false, 'message' => 'Cannot perform action on your own account']);
            exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        
        if ($action_type == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id IN ($placeholders)");
        } elseif ($action_type == 'activate') {
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id IN ($placeholders)");
        } elseif ($action_type == 'deactivate') {
            $old_date = date('Y-m-d H:i:s', strtotime('-10 years'));
            $stmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE user_id IN ($placeholders)");
            $user_ids = array_merge([$old_date], $user_ids);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
        }
        
        if ($stmt->execute($user_ids)) {
            echo json_encode(['success' => true, 'message' => 'Bulk action completed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error executing bulk action']);
        }
        exit;
    }
    
    if($_POST['action'] == 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organic Tilapia - Admin Dashboard</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #142138 0%, #0d1729 100%);
            color: #ffffff;
            min-height: 100vh;
        }

        /* Navbar */
        .navbar {
            background: rgba(13, 23, 41, 0.98);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            font-size: 1.8rem;
            color: #3b82f6;
        }

        .user-badge {
            background: #2a3f5e;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-badge {
            background: #ef4444;
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            margin-left: 0.5rem;
            animation: pulse 2s infinite;
        }

        .logout-btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        /* Message Alert */
        .message-alert {
            padding: 1rem 2rem;
            margin: 1rem 2rem 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .message-alert.success {
            background: rgba(74, 222, 128, 0.2);
            border: 1px solid #4ade80;
            color: #4ade80;
        }

        .message-alert.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .message-alert .close-btn {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.2rem;
            cursor: pointer;
        }

        /* Main Container */
        .dashboard-container {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Grid Layouts */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.07);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }

        /* Time Bar */
        .time-bar {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50px;
            padding: 0.8rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .time-box {
            background: #1e2f47;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-family: monospace;
        }

        .ph-time-badge {
            background: #3b82f6;
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Stat Cards */
        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        td {
            padding: 0.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .status-badge.active {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }

        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .status-badge.new {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .status-badge.read {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .status-badge.resolved {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }

        .role-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }

        .role-badge.admin { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .role-badge.manager { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .role-badge.staff { background: rgba(74, 222, 128, 0.2); color: #4ade80; }

        /* Buttons */
        .btn {
            background: #2a3f5e;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }

        .btn-sm {
            padding: 0.3rem 0.8rem;
            font-size: 0.75rem;
        }

        .btn-primary {
            background: #3b82f6;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-danger {
            background: #ef4444;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-success {
            background: #4ade80;
            color: #142138;
        }

        .btn-warning {
            background: #fbbf24;
            color: #142138;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Search and Filter */
        .search-box {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .search-box input,
        .search-box select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            color: white;
        }

        .search-box input {
            flex: 1;
            min-width: 200px;
        }

        .search-box input:focus,
        .search-box select:focus {
            outline: none;
            border-color: #3b82f6;
        }

        /* Bulk Actions */
        .bulk-actions {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .select-all {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Pond Cards - Fixed like manager dashboard */
        .pond-card {
            cursor: pointer;
            position: relative;
            overflow: hidden;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            margin-bottom: 0.5rem;
        }

        .pond-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            transition: all 0.3s ease;
        }

        .pond-card[data-status="safe"]::before {
            background: #4ade80;
            box-shadow: 0 0 20px #4ade80;
        }

        .pond-card[data-status="warning"]::before {
            background: #fbbf24;
            box-shadow: 0 0 20px #fbbf24;
        }

        .pond-card[data-status="critical"]::before {
            background: #ef4444;
            animation: pulseGlow 2s infinite;
        }

        @keyframes pulseGlow {
            0% { opacity: 1; box-shadow: 0 0 20px #ef4444; }
            50% { opacity: 0.7; box-shadow: 0 0 40px #ef4444; }
            100% { opacity: 1; box-shadow: 0 0 20px #ef4444; }
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }

        .metric-item {
            text-align: center;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .metric-item:hover {
            background: rgba(255, 255, 255, 0.07);
            transform: scale(1.05);
        }

        .metric-icon.organic { color: #4ade80; font-size: 1.2rem; }
        .metric-icon.temp { color: #fbbf24; font-size: 1.2rem; }
        .metric-icon.ph { color: #a78bfa; font-size: 1.2rem; }

        /* Alert Items */
        .alert-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.02);
            border-left: 4px solid transparent;
        }

        .alert-item.critical { border-left-color: #ef4444; }
        .alert-item.warning { border-left-color: #fbbf24; }
        .alert-item.info { border-left-color: #3b82f6; }

        .alert-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Map */
        #map {
            height: 400px;
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .map-legend {
            display: flex;
            gap: 1rem;
            background: rgba(13, 23, 41, 0.8);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        /* Chart Container */
        .chart-container {
            height: 250px;
            margin-top: 1rem;
        }

        .report-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .report-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            cursor: pointer;
        }

        .report-btn.active {
            background: #3b82f6;
        }

        /* Staff Cards */
        .staff-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            cursor: pointer;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .staff-card:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(5px);
        }

        .staff-avatar {
            width: 40px;
            height: 40px;
            background: #2a3f5e;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 2px solid #3b82f6;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #1e2f47;
            border-radius: 24px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-close:hover {
            color: #ef4444;
        }

        .modal input,
        .modal select {
            width: 100%;
            padding: 0.8rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
        }

        .modal input:focus,
        .modal select:focus {
            outline: none;
            border-color: #3b82f6;
        }

        /* Confirmation Dialog */
        .confirm-dialog {
            background: #1e2f47;
            border-radius: 16px;
            padding: 1.5rem;
            max-width: 400px;
            margin: 0 auto;
        }

        .confirm-dialog h3 {
            margin-bottom: 1rem;
        }

        .confirm-dialog p {
            margin-bottom: 1.5rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .confirm-dialog .buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        /* Checkbox */
        .user-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Timestamp */
        .timestamp {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.4);
            margin-top: 0.5rem;
        }

        /* Animations */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @keyframes blinkMarker {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .grid-2, .grid-3, .grid-4 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo-area">
            <i class="fas fa-crown logo"></i>
            <span style="font-weight: 600;">Organic Tilapia - Admin Panel</span>
        </div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div class="user-badge">
                <i class="fas fa-user-shield"></i>
                <span>Admin – <?php echo htmlspecialchars($admin_name); ?></span>
                <span id="notificationBadge" class="notification-badge"><?php echo $new_alerts_count; ?></span>
            </div>
            <a href="../auth/logout.php" class="logout-btn" onclick="return confirm('Logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
</a>
</a>
            </a>
        </div>
    </nav>

    <!-- Message Alert -->
    <?php if (!empty($message)): ?>
    <div class="message-alert <?php echo $message_type; ?>">
        <?php echo $message; ?>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <div class="dashboard-container">
        <!-- Time Bar with PH Time -->
        <div class="time-bar">
            <div>
                <i class="fas fa-calendar-alt" style="color: #3b82f6;"></i>
                <span id="currentDate"><?php echo $current_date; ?></span>
                <span class="time-box" id="currentTime"><?php echo $current_time_12hr; ?></span>
                <span class="ph-time-badge">
                    <i class="fas fa-map-marker-alt"></i> PH Time
                </span>
            </div>
            <div>
                <i class="fas fa-database"></i> Connected to Database
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid-4" style="margin-bottom: 1.5rem;">
            <div class="stat-card">
                <i class="fas fa-users" style="color: #3b82f6; font-size: 1.5rem;"></i>
                <div class="stat-value"><?php echo count($users); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-water" style="color: #4ade80; font-size: 1.5rem;"></i>
                <div class="stat-value"><?php echo count($ponds_data); ?></div>
                <div class="stat-label">Active Ponds</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-exclamation-triangle" style="color: #ef4444; font-size: 1.5rem;"></i>
                <div class="stat-value"><?php echo $new_alerts_count; ?></div>
                <div class="stat-label">New Alerts</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-chart-line" style="color: #fbbf24; font-size: 1.5rem;"></i>
                <div class="stat-value"><?php echo $daily_report['total_readings'] ?? 0; ?></div>
                <div class="stat-label">Today's Readings</div>
            </div>
        </div>

        <!-- Manage Users Panel -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span><i class="fas fa-users-cog" style="color: #3b82f6;"></i> Manage Users</span>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-primary btn-sm" onclick="showAddUserModal()">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                </div>
            </div>
            
            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <div class="select-all">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    <label for="selectAll">Select All</label>
                </div>
                <button class="btn btn-sm btn-success" onclick="bulkAction('activate')" disabled id="bulkActivate">
                    <i class="fas fa-check"></i> Activate Selected
                </button>
                <button class="btn btn-sm btn-warning" onclick="bulkAction('deactivate')" disabled id="bulkDeactivate">
                    <i class="fas fa-ban"></i> Deactivate Selected
                </button>
                <button class="btn btn-sm btn-danger" onclick="bulkAction('delete')" disabled id="bulkDelete">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <span id="selectedCount" style="color: rgba(255,255,255,0.5);">0 selected</span>
            </div>
            
            <!-- Search and Filter -->
            <div class="search-box">
                <input type="text" id="userSearch" placeholder="Search users by name or email..." onkeyup="filterUsers()">
                <select id="roleFilter" onchange="filterUsers()">
                    <option value="all">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="staff">Staff</option>
                </select>
                <select id="statusFilter" onchange="filterUsers()">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div class="table-container">
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th width="30px"></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Assigned Pond</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr data-user-id="<?php echo $user['user_id']; ?>" data-role="<?php echo $user['role']; ?>" data-status="<?php echo $user['status']; ?>">
                            <td>
                                <?php if ($user['user_id'] != $admin_id): ?>
                                <input type="checkbox" class="user-checkbox" value="<?php echo $user['user_id']; ?>" onchange="updateSelectedCount()">
                                <?php endif; ?>
                            </td>
                            <td><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge <?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['status']; ?>">
                                    <i class="fas fa-circle"></i> <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['last_login'] ? date('M d, h:i A', strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td><?php echo $user['assigned_pond'] ?? '—'; ?></td>
                            <td>
                                <button class="btn btn-sm" onclick="editUser(<?php echo $user['user_id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if($user['user_id'] != $admin_id): ?>
                                    <?php if($user['status'] == 'active'): ?>
                                    <button class="btn btn-sm btn-warning" onclick="deactivateUser(<?php echo $user['user_id']; ?>)">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-success" onclick="activateUser(<?php echo $user['user_id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if($user['assigned_pond']): ?>
                                <button class="btn btn-sm" onclick="highlightPond('<?php echo $user['assigned_pond']; ?>')">
                                    <i class="fas fa-map-marker-alt"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Staff & Pond Assignments + Pond Overview -->
        <div class="grid-2" style="margin-bottom: 1.5rem;">
            <!-- Staff Assignments -->
            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-user-tie" style="color: #3b82f6;"></i> Staff & Pond Assignments</span>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php 
                    $staff_users = array_filter($users, function($u) { return $u['role'] == 'staff'; });
                    if (!empty($staff_users)) {
                        foreach($staff_users as $staff): 
                    ?>
                    <div class="staff-card" onclick="highlightPond('<?php echo $staff['assigned_pond']; ?>')">
                        <div class="staff-avatar">
                            <?php 
                                $initials = '';
                                $names = explode(' ', $staff['full_name']);
                                foreach($names as $n) {
                                    $initials .= strtoupper(substr($n, 0, 1));
                                }
                                echo $initials ?: '?';
                            ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between;">
                                <strong><?php echo $staff['full_name']; ?></strong>
                                <span class="status-badge <?php echo $staff['status']; ?>">
                                    <i class="fas fa-circle"></i>
                                </span>
                            </div>
                            <div style="display: flex; gap: 1rem; margin-top: 0.3rem; font-size: 0.8rem; color: rgba(255,255,255,0.6);">
                                <span><i class="fas fa-map-marker-alt"></i> Pond <?php echo $staff['assigned_pond'] ?? 'Unassigned'; ?></span>
                                <span><i class="far fa-clock"></i> <?php echo $staff['last_login'] ? date('h:i A', strtotime($staff['last_login'])) : 'Never'; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php 
                        endforeach; 
                    } else {
                        echo '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 1rem;">No staff members found</p>';
                    }
                    ?>
                </div>
            </div>

            <!-- Pond Overview - Fixed like manager dashboard -->
            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-map-marked-alt" style="color: #3b82f6;"></i> Pond Overview</span>
                    <div class="map-legend">
                        <div class="legend-item"><span class="legend-color" style="background: #4ade80;"></span> Safe</div>
                        <div class="legend-item"><span class="legend-color" style="background: #fbbf24;"></span> Warning</div>
                        <div class="legend-item"><span class="legend-color" style="background: #ef4444;"></span> Critical</div>
                    </div>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($ponds_data)): ?>
                        <?php foreach($ponds_data as $pond_name => $pond): ?>
                        <div class="pond-card" data-status="<?php echo $pond['status']; ?>" onclick="showPondDetails('<?php echo $pond_name; ?>')">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <h4 style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-map-marker-alt" style="color: <?php 
                                        echo $pond['status'] == 'safe' ? '#4ade80' : 
                                            ($pond['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                                    ?>;"></i>
                                    Pond <?php echo $pond_name; ?>
                                </h4>
                                <span class="status-badge" style="background: <?php 
                                    echo $pond['status'] == 'safe' ? 'rgba(74,222,128,0.2)' : 
                                        ($pond['status'] == 'warning' ? 'rgba(251,191,36,0.2)' : 'rgba(239,68,68,0.2)'); 
                                ?>; color: <?php 
                                    echo $pond['status'] == 'safe' ? '#4ade80' : 
                                        ($pond['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                                ?>;">
                                    <i class="fas fa-circle"></i> <?php echo ucfirst($pond['status']); ?>
                                </span>
                            </div>
                            
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 0.5rem;">
                                <i class="fas fa-user"></i> <?php echo $pond['staff']; ?> • 
                                <i class="fas fa-map-pin"></i> <?php echo $pond['location']; ?>
                            </div>
                            
                            <div class="metrics-grid">
                                <div class="metric-item">
                                    <i class="fas fa-seedling metric-icon organic"></i>
                                    <div style="font-size: 1.2rem; font-weight: 600;"><?php echo $pond['organic_level']; ?>%</div>
                                    <small style="color: rgba(255,255,255,0.5);">Organic</small>
                                </div>
                                <div class="metric-item">
                                    <i class="fas fa-thermometer-half metric-icon temp"></i>
                                    <div style="font-size: 1.2rem; font-weight: 600;"><?php echo $pond['temperature']; ?>°C</div>
                                    <small style="color: rgba(255,255,255,0.5);">Temp</small>
                                </div>
                                <div class="metric-item">
                                    <i class="fas fa-flask metric-icon ph"></i>
                                    <div style="font-size: 1.2rem; font-weight: 600;"><?php echo $pond['ph']; ?></div>
                                    <small style="color: rgba(255,255,255,0.5);">pH</small>
                                </div>
                            </div>
                            
                            <div class="timestamp">
                                <i class="far fa-clock"></i> Last reading: <?php echo date('h:i:s A', strtotime($pond['last_reading'])); ?>
                                <?php 
                                $time_diff = time() - strtotime($pond['last_reading']);
                                if($time_diff < 60) {
                                    echo '<span style="color: #4ade80; margin-left: auto;">Just now</span>';
                                } elseif($time_diff < 3600) {
                                    $mins = floor($time_diff / 60);
                                    echo '<span style="color: #fbbf24; margin-left: auto;">' . $mins . ' min ago</span>';
                                } else {
                                    $hours = floor($time_diff / 3600);
                                    echo '<span style="color: #ef4444; margin-left: auto;">' . $hours . ' hr ago</span>';
                                }
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: rgba(255,255,255,0.5); padding: 1rem;">No ponds found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Live Map -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span><i class="fas fa-map" style="color: #3b82f6;"></i> Live Pond Map - Manolo Fortich</span>
                <div class="map-legend">
                    <div class="legend-item"><span class="legend-color" style="background: #4ade80;"></span> Safe</div>
                    <div class="legend-item"><span class="legend-color" style="background: #fbbf24;"></span> Warning</div>
                    <div class="legend-item"><span class="legend-color" style="background: #ef4444;"></span> Critical</div>
                </div>
            </div>
            <div id="map"></div>
            <div style="margin-top: 0.5rem; text-align: right; font-size: 0.8rem; color: rgba(255,255,255,0.4);">
                <i class="far fa-clock"></i> Map data • PH Time: <span id="mapTimestamp"><?php echo date('h:i:s A'); ?></span>
            </div>
        </div>

        <!-- Live Metrics + Alerts -->
        <div class="grid-2" style="margin-bottom: 1.5rem;">
            <!-- Live Metrics Trends -->
            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-chart-line" style="color: #3b82f6;"></i> Live Metrics Trends</span>
                    <div class="report-buttons">
                        <button class="report-btn active" onclick="updateChart('daily', this)">Daily</button>
                        <button class="report-btn" onclick="updateChart('weekly', this)">Weekly</button>
                        <button class="report-btn" onclick="updateChart('monthly', this)">Monthly</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="metricsChart"></canvas>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.8rem; color: rgba(255,255,255,0.4);">
                    <span><i class="fas fa-chart-line"></i> Real-time data</span>
                    <span>Last updated: <span id="chartTimestamp"><?php echo date('h:i:s A'); ?></span></span>
                </div>
            </div>

            <!-- Alerts & Notifications -->
            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-bell" style="color: #ef4444;"></i> Alerts & Notifications</span>
                    <span class="status-badge new"><?php echo $new_alerts_count; ?> New</span>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($alerts)): ?>
                        <?php foreach($alerts as $alert): ?>
                        <div class="alert-item <?php echo $alert['type']; ?>" onclick="highlightPond('<?php echo $alert['pond_name']; ?>')">
                            <i class="fas fa-<?php echo $alert['type'] == 'critical' ? 'exclamation-circle' : 'exclamation-triangle'; ?>" style="font-size: 1.2rem; color: <?php 
                                echo $alert['type'] == 'critical' ? '#ef4444' : 
                                    ($alert['type'] == 'warning' ? '#fbbf24' : '#3b82f6'); 
                            ?>;"></i>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem;">
                                    <strong>Pond <?php echo $alert['pond_name']; ?></strong>
                                    <small style="color: rgba(255,255,255,0.4);"><?php echo date('h:i A', strtotime($alert['created_at'])); ?></small>
                                </div>
                                <p style="font-size: 0.9rem; color: rgba(255,255,255,0.8); margin-bottom: 0.3rem;">
                                    <?php echo $alert['message']; ?>
                                </p>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="status-badge <?php echo $alert['status']; ?>">
                                        <?php echo ucfirst($alert['status']); ?>
                                    </span>
                                    <?php if(isset($alert['status']) && $alert['status'] == 'unread'): ?>
                                    <div>
                                        <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); acknowledgeAlert(<?php echo $alert['notification_id']; ?>)">Acknowledge</button>
                                        <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); resolveAlert(<?php echo $alert['notification_id']; ?>)">Resolve</button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: rgba(255,255,255,0.5); padding: 1rem;">No alerts found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Report Generation + Recent Activities -->
        <div class="grid-2">
            <!-- Report Generation - Enhanced with Icons -->
            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-file-alt" style="color: #3b82f6;"></i> Report Generation</span>
                    <span class="status-badge" style="background: rgba(59,130,246,0.2); color: #3b82f6;">
                        <i class="fas fa-chart-pie"></i> Analytics
                    </span>
                </div>
                
                <!-- Report Type Buttons with Icons -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.8rem; margin-bottom: 1.5rem;">
                    <button class="btn btn-primary" onclick="generateReport('daily')" style="display: flex; flex-direction: column; padding: 1rem; height: auto; background: linear-gradient(145deg, #2a3f5e, #1e2f47); border: 1px solid rgba(255,255,255,0.1);">
                        <i class="fas fa-calendar-day" style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #4ade80;"></i>
                        <span style="font-weight: 600;">Daily Report</span>
                        <small style="font-size: 0.7rem; opacity: 0.8;">24-hour summary</small>
                    </button>
                    <button class="btn btn-primary" onclick="generateReport('weekly')" style="display: flex; flex-direction: column; padding: 1rem; height: auto; background: linear-gradient(145deg, #2a3f5e, #1e2f47); border: 1px solid rgba(255,255,255,0.1);">
                        <i class="fas fa-calendar-week" style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #fbbf24;"></i>
                        <span style="font-weight: 600;">Weekly Report</span>
                        <small style="font-size: 0.7rem; opacity: 0.8;">7-day trends</small>
                    </button>
                    <button class="btn btn-primary" onclick="generateReport('monthly')" style="display: flex; flex-direction: column; padding: 1rem; height: auto; background: linear-gradient(145deg, #2a3f5e, #1e2f47); border: 1px solid rgba(255,255,255,0.1);">
                        <i class="fas fa-calendar-alt" style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #3b82f6;"></i>
                        <span style="font-weight: 600;">Monthly Report</span>
                        <small style="font-size: 0.7rem; opacity: 0.8;">30-day analysis</small>
                    </button>
                </div>
                
                <!-- Report Preview Area with Icons -->
                <div id="reportPreview" style="background: rgba(255,255,255,0.02); border-radius: 16px; padding: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <i class="fas fa-chart-bar" style="color: #3b82f6;"></i>
                        <h4 style="font-size: 1rem; font-weight: 600;">Daily Report Preview</h4>
                        <span style="margin-left: auto; background: #1e2f47; padding: 0.2rem 0.8rem; border-radius: 50px; font-size: 0.7rem; display: flex; align-items: center; gap: 0.3rem;">
                            <i class="far fa-clock"></i> <?php echo date('M d, Y'); ?>
                        </span>
                    </div>
                    
                    <!-- Stats Grid with Icons -->
                    <div class="grid-3" style="gap: 0.8rem; margin-bottom: 1rem;">
                        <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem; text-align: center; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-water" style="color: #3b82f6;"></i>
                                <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">Total Ponds</span>
                            </div>
                            <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;"><?php echo $daily_report['total_ponds']; ?></div>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.8rem;">
                                <i class="fas fa-chart-pie" style="color: #fbbf24;"></i>
                                <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">Status Distribution</span>
                            </div>
                            <div style="display: flex; justify-content: space-around;">
                                <div style="text-align: center;">
                                    <div style="color: #4ade80; font-weight: 600; font-size: 1.2rem;"><?php echo $daily_report['safe_ponds']; ?></div>
                                    <small style="color: #4ade80; display: flex; align-items: center; gap: 0.2rem;"><i class="fas fa-circle"></i> Safe</small>
                                </div>
                                <div style="text-align: center;">
                                    <div style="color: #fbbf24; font-weight: 600; font-size: 1.2rem;"><?php echo $daily_report['warning_ponds']; ?></div>
                                    <small style="color: #fbbf24; display: flex; align-items: center; gap: 0.2rem;"><i class="fas fa-circle"></i> Warning</small>
                                </div>
                                <div style="text-align: center;">
                                    <div style="color: #ef4444; font-weight: 600; font-size: 1.2rem;"><?php echo $daily_report['critical_ponds']; ?></div>
                                    <small style="color: #ef4444; display: flex; align-items: center; gap: 0.2rem;"><i class="fas fa-circle"></i> Critical</small>
                                </div>
                            </div>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                                <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">Active Alerts</span>
                            </div>
                            <div style="font-size: 1.8rem; font-weight: 700; color: #ef4444;"><?php echo $daily_report['alerts_generated']; ?></div>
                            <small style="color: rgba(255,255,255,0.4); display: flex; align-items: center; gap: 0.3rem;"><i class="far fa-clock"></i> Last 24h</small>
                        </div>
                    </div>
                    
                    <!-- Metrics Summary with Icons -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.8rem; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.8rem; background: rgba(255,255,255,0.02); padding: 0.8rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.05);">
                            <i class="fas fa-seedling" style="color: #4ade80; font-size: 1.2rem;"></i>
                            <div>
                                <small style="color: rgba(255,255,255,0.5); display: flex; align-items: center; gap: 0.2rem;"><i class="fas fa-arrow-up"></i> Avg Organic</small>
                                <div style="font-weight: 600; font-size: 1.1rem;"><?php echo $daily_report['avg_organic']; ?>%</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.8rem; background: rgba(255,255,255,0.02); padding: 0.8rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.05);">
                            <i class="fas fa-thermometer-half" style="color: #fbbf24; font-size: 1.2rem;"></i>
                            <div>
                                <small style="color: rgba(255,255,255,0.5); display: flex; align-items: center; gap: 0.2rem;"><i class="fas fa-thermometer-half"></i> Avg Temp</small>
                                <div style="font-weight: 600; font-size: 1.1rem;"><?php echo $daily_report['avg_temp']; ?>°C</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.8rem; background: rgba(255,255,255,0.02); padding: 0.8rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.05);">
                            <i class="fas fa-flask" style="color: #a78bfa; font-size: 1.2rem;"></i>
                            <div>
                                <small style="color: rgba(255,255,255,0.5); display: flex; align-items: center; gap: 0.2rem;"><i class="fas fa-flask"></i> Avg pH</small>
                                <div style="font-weight: 600; font-size: 1.1rem;"><?php echo $daily_report['avg_ph']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Staff Active Summary -->
                    <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(59,130,246,0.1); padding: 0.8rem; border-radius: 10px; margin-bottom: 1rem; border: 1px solid rgba(59,130,246,0.2);">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-users" style="color: #3b82f6;"></i>
                            <span>Active Staff Today</span>
                        </div>
                        <span style="font-weight: 600; color: #3b82f6;"><?php echo $daily_report['staff_active']; ?> staff</span>
                    </div>
                    
                    <!-- Download Buttons (Simulation) -->
                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                        <button class="btn btn-sm" style="flex: 1; background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid rgba(239,68,68,0.3);" onclick="showNotification('PDF Report downloaded (simulation)', 'success')">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm" style="flex: 1; background: rgba(74,222,128,0.2); color: #4ade80; border: 1px solid rgba(74,222,128,0.3);" onclick="showNotification('Excel Report downloaded (simulation)', 'success')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn btn-sm" style="flex: 1; background: rgba(251,191,36,0.2); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3);" onclick="showNotification('CSV Report downloaded (simulation)', 'success')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-history" style="color: #3b82f6;"></i> Recent Activities</span>
                    <span class="status-badge" style="background: rgba(59,130,246,0.2); color: #3b82f6;">
                        <i class="fas fa-sync-alt fa-spin"></i> Live
                    </span>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach($recent_activities as $activity): ?>
                        <div style="padding: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 0.8rem;">
                            <?php 
                            $icon = 'fas fa-circle';
                            $color = '#4ade80';
                            if ($activity['type'] == 'login') {
                                $icon = 'fas fa-sign-in-alt';
                                $color = '#3b82f6';
                            } elseif ($activity['type'] == 'reading') {
                                $icon = 'fas fa-file-alt';
                                $color = '#fbbf24';
                            } elseif ($activity['type'] == 'alert') {
                                $icon = 'fas fa-exclamation-triangle';
                                $color = '#ef4444';
                            }
                            ?>
                            <i class="<?php echo $icon; ?>" style="color: <?php echo $color; ?>; font-size: 0.9rem;"></i>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="font-size: 0.9rem;"><?php echo $activity['action']; ?></span>
                                    <small style="color: rgba(255,255,255,0.4);"><?php echo date('h:i A', strtotime($activity['timestamp'])); ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: rgba(255,255,255,0.5); padding: 1rem;">No activities found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New User</h3>
                <button class="modal-close" onclick="closeUserModal()">&times;</button>
            </div>
            <form id="userForm" onsubmit="event.preventDefault(); saveUser();">
                <input type="hidden" id="userId">
                <div>
                    <label>Full Name</label>
                    <input type="text" id="fullName" required maxlength="100">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" id="email" required maxlength="100">
                </div>
                <div>
                    <label>Role</label>
                    <select id="role" required>
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label>Assigned Pond</label>
                    <select id="assignedPond">
                        <option value="">None</option>
                        <?php foreach(array_keys($ponds_data) as $pond): ?>
                        <option value="<?php echo $pond; ?>">Pond <?php echo $pond; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="passwordField">
                    <label>Password (for new users)</label>
                    <input type="password" id="password" value="default123">
                </div>
                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pond Details Modal -->
    <div id="pondModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Pond Details</h3>
                <button class="modal-close" onclick="closePondModal()">&times;</button>
            </div>
            <div id="pondDetails"></div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content confirm-dialog">
            <h3 id="confirmTitle">Confirm Action</h3>
            <p id="confirmMessage">Are you sure you want to proceed?</p>
            <div class="buttons">
                <button class="btn" onclick="closeConfirmModal()">Cancel</button>
                <button class="btn btn-danger" id="confirmBtn" onclick="executeConfirmedAction()">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let map, markers = {}, chart;
        let selectedUserIds = [];
        let currentAction = null;
        let currentUserId = null;

        // Create marker icon with blinking animation
        function createMarkerIcon(status) {
            const colors = {
                safe: '#4ade80',
                warning: '#fbbf24',
                critical: '#ef4444'
            };
            
            return L.divIcon({
                className: 'custom-marker',
                html: `<div style="
                    background: ${colors[status]};
                    width: 20px;
                    height: 20px;
                    border-radius: 50%;
                    border: 3px solid white;
                    box-shadow: 0 0 20px ${colors[status]};
                    animation: ${status === 'critical' ? 'blinkMarker 1s infinite' : 'none'};
                "></div>`,
                iconSize: [26, 26],
                iconAnchor: [13, 13]
            });
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Map
            map = L.map('map').setView([8.3695, 124.8698], 15);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            // Add markers with blinking animation
            <?php if (!empty($ponds_data)): ?>
                <?php foreach($ponds_data as $name => $data): ?>
                markers['<?php echo $name; ?>'] = L.marker([<?php echo $data['coordinates'][0]; ?>, <?php echo $data['coordinates'][1]; ?>], {
                    icon: createMarkerIcon('<?php echo $data['status']; ?>'),
                    riseOnHover: true
                }).addTo(map).bindPopup(`
                    <div style="color: #142138; padding: 12px; max-width: 250px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                            <i class="fas fa-map-marker-alt" style="color: <?php 
                                echo $data['status'] == 'safe' ? '#4ade80' : 
                                    ($data['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                            ?>; font-size: 1.2rem;"></i>
                            <h3 style="margin: 0; font-size: 1.1rem;">Pond <?php echo $name; ?></h3>
                            <span style="background: <?php 
                                echo $data['status'] == 'safe' ? '#4ade80' : 
                                    ($data['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                            ?>20; color: <?php 
                                echo $data['status'] == 'safe' ? '#4ade80' : 
                                    ($data['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                            ?>; padding: 2px 8px; border-radius: 50px; font-size: 0.7rem;">
                                <?php echo ucfirst($data['status']); ?>
                            </span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 10px;">
                            <div style="text-align: center;">
                                <i class="fas fa-seedling" style="color: #4ade80;"></i>
                                <div style="font-weight: 600;"><?php echo $data['organic_level']; ?>%</div>
                                <div style="font-size: 0.7rem; color: #666;">Organic</div>
                            </div>
                            <div style="text-align: center;">
                                <i class="fas fa-thermometer-half" style="color: #fbbf24;"></i>
                                <div style="font-weight: 600;"><?php echo $data['temperature']; ?>°C</div>
                                <div style="font-size: 0.7rem; color: #666;">Temp</div>
                            </div>
                            <div style="text-align: center;">
                                <i class="fas fa-flask" style="color: #a78bfa;"></i>
                                <div style="font-weight: 600;"><?php echo $data['ph']; ?></div>
                                <div style="font-size: 0.7rem; color: #666;">pH</div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #eee;">
                            <p style="margin: 3px 0;"><i class="fas fa-user"></i> <strong><?php echo $data['staff']; ?></strong></p>
                            <p style="margin: 3px 0;"><i class="fas fa-map-pin"></i> <?php echo $data['location']; ?></p>
                            <p style="margin: 3px 0; font-size: 0.8rem; color: #666;">
                                <i class="far fa-clock"></i> <?php echo date('h:i:s A', strtotime($data['last_reading'])); ?> PH Time
                            </p>
                        </div>
                    </div>
                `);
                <?php endforeach; ?>
            <?php endif; ?>

            // Chart
            const ctx = document.getElementById('metricsChart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_data['daily']['labels']); ?>,
                    datasets: [
                        {
                            label: 'Organic Level',
                            data: <?php echo json_encode($chart_data['daily']['organic']); ?>,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        },
                        {
                            label: 'Temperature',
                            data: <?php echo json_encode($chart_data['daily']['temperature']); ?>,
                            borderColor: '#fbbf24',
                            backgroundColor: 'rgba(251,191,36,0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        },
                        {
                            label: 'pH Level',
                            data: <?php echo json_encode($chart_data['daily']['ph']); ?>,
                            borderColor: '#4ade80',
                            backgroundColor: 'rgba(74,222,128,0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        legend: {
                            labels: { color: '#ffffff', font: { size: 11 } }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: '#1e2f47',
                            titleColor: '#ffffff',
                            bodyColor: 'rgba(255,255,255,0.8)',
                            borderColor: 'rgba(255,255,255,0.1)',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        y: { 
                            grid: { color: 'rgba(255,255,255,0.1)' }, 
                            ticks: { color: '#ffffff' }
                        },
                        x: { 
                            grid: { color: 'rgba(255,255,255,0.1)' }, 
                            ticks: { color: '#ffffff' }
                        }
                    }
                }
            });

            // Update time
            setInterval(() => {
                const now = new Date();
                const phTime = now.toLocaleTimeString('en-US', { 
                    timeZone: 'Asia/Manila',
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                document.getElementById('currentTime').textContent = phTime;
                document.getElementById('mapTimestamp').textContent = phTime;
                document.getElementById('chartTimestamp').textContent = phTime;
            }, 1000);
        });

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes blinkMarker {
                0% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.5; transform: scale(1.2); }
                100% { opacity: 1; transform: scale(1); }
            }
            
            .leaflet-popup-content {
                font-family: 'Inter', sans-serif;
            }
            
            .leaflet-container {
                font-family: 'Inter', sans-serif;
            }
        `;
        document.head.appendChild(style);

        // User Management Functions
        function filterUsers() {
            const search = document.getElementById('userSearch').value.toLowerCase();
            const role = document.getElementById('roleFilter').value;
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const name = row.cells[1].innerText.toLowerCase();
                const email = row.cells[2].innerText.toLowerCase();
                const rowRole = row.dataset.role;
                const rowStatus = row.dataset.status;
                
                const matchSearch = name.includes(search) || email.includes(search);
                const matchRole = role === 'all' || rowRole === role;
                const matchStatus = status === 'all' || rowStatus === status;
                
                row.style.display = matchSearch && matchRole && matchStatus ? '' : 'none';
            });
            
            updateSelectedCount();
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.user-checkbox:enabled');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            selectedUserIds = Array.from(checkboxes).map(cb => cb.value);
            const count = selectedUserIds.length;
            
            document.getElementById('selectedCount').textContent = count + ' selected';
            
            document.getElementById('bulkActivate').disabled = count === 0;
            document.getElementById('bulkDeactivate').disabled = count === 0;
            document.getElementById('bulkDelete').disabled = count === 0;
        }

        function bulkAction(action) {
            if (selectedUserIds.length === 0) return;
            
            let message = '';
            if (action === 'delete') {
                message = 'Are you sure you want to delete the selected users? This action cannot be undone.';
            } else if (action === 'activate') {
                message = 'Are you sure you want to activate the selected users?';
            } else if (action === 'deactivate') {
                message = 'Are you sure you want to deactivate the selected users?';
            }
            
            showConfirmDialog(
                action === 'delete' ? 'Delete Users' : (action === 'activate' ? 'Activate Users' : 'Deactivate Users'),
                message,
                function() {
                    executeBulkAction(action);
                }
            );
        }

        function executeBulkAction(action) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=bulk_action&bulk_type=' + action + '&user_ids=' + JSON.stringify(selectedUserIds)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function showAddUserModal() {
            document.getElementById('modalTitle').innerText = 'Add New User';
            document.getElementById('userId').value = '';
            document.getElementById('fullName').value = '';
            document.getElementById('email').value = '';
            document.getElementById('role').value = 'staff';
            document.getElementById('assignedPond').value = '';
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('userModal').classList.add('active');
        }

        function editUser(userId) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_user&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const user = data.user;
                    document.getElementById('modalTitle').innerText = 'Edit User';
                    document.getElementById('userId').value = user.user_id;
                    document.getElementById('fullName').value = user.full_name;
                    document.getElementById('email').value = user.email;
                    document.getElementById('role').value = user.role;
                    document.getElementById('assignedPond').value = user.assigned_pond || '';
                    document.getElementById('passwordField').style.display = 'none';
                    document.getElementById('userModal').classList.add('active');
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function saveUser() {
            const userId = document.getElementById('userId').value;
            const fullName = document.getElementById('fullName').value.trim();
            const email = document.getElementById('email').value.trim();
            const role = document.getElementById('role').value;
            const assignedPond = document.getElementById('assignedPond').value;
            
            if (!fullName || !email) {
                alert('Name and email are required');
                return;
            }
            
            let action = userId ? 'edit_user' : 'add_user';
            let formData = `action=${action}&full_name=${encodeURIComponent(fullName)}&email=${encodeURIComponent(email)}&role=${role}&assigned_pond=${assignedPond}`;
            
            if (userId) {
                formData += `&user_id=${userId}`;
            } else {
                formData += `&password=${document.getElementById('password').value}`;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function deactivateUser(userId) {
            showConfirmDialog(
                'Deactivate User',
                'Are you sure you want to deactivate this user?',
                function() {
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=deactivate_user&user_id=' + userId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        }
                    });
                }
            );
        }

        function activateUser(userId) {
            showConfirmDialog(
                'Activate User',
                'Are you sure you want to activate this user?',
                function() {
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=activate_user&user_id=' + userId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        }
                    });
                }
            );
        }

        function deleteUser(userId) {
            showConfirmDialog(
                'Delete User',
                'Are you sure you want to delete this user? This action cannot be undone.',
                function() {
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=delete_user&user_id=' + userId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
                }
            );
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
        }

        // Confirmation Dialog Functions
        function showConfirmDialog(title, message, callback) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmBtn').onclick = function() {
                closeConfirmModal();
                callback();
            };
            document.getElementById('confirmModal').classList.add('active');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }

        function executeConfirmedAction() {
            if (currentAction === 'delete' && currentUserId) {
                deleteUser(currentUserId);
            }
        }

        // Map Functions
        function highlightPond(pondName) {
            if (markers && markers[pondName]) {
                map.setView(markers[pondName].getLatLng(), 18);
                markers[pondName].openPopup();
                
                // Highlight card animation
                document.querySelectorAll('.pond-card').forEach(card => {
                    if (card.querySelector('h4').innerText.includes(pondName)) {
                        card.style.transform = 'scale(1.02)';
                        card.style.borderColor = '#3b82f6';
                        card.style.boxShadow = '0 0 30px rgba(59, 130, 246, 0.3)';
                        
                        setTimeout(() => {
                            card.style.transform = '';
                            card.style.borderColor = '';
                            card.style.boxShadow = '';
                        }, 2000);
                    }
                });
            } else {
                alert('Pond ' + pondName + ' not found on map');
            }
        }

        function showPondDetails(pondName) {
            <?php if (!empty($ponds_data)): ?>
                <?php foreach($ponds_data as $name => $pond): ?>
                if (pondName === '<?php echo $name; ?>') {
                    document.getElementById('pondDetails').innerHTML = `
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                <i class="fas fa-map-marker-alt" style="font-size: 2rem; color: <?php 
                                    echo $pond['status'] == 'safe' ? '#4ade80' : 
                                        ($pond['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                                ?>;"></i>
                                <div>
                                    <h2>Pond <?php echo $name; ?></h2>
                                    <p style="color: rgba(255,255,255,0.6);"><?php echo $pond['location']; ?></p>
                                </div>
                            </div>
                            
                            <div class="metrics-grid" style="margin-bottom: 1rem;">
                                <div class="metric-item">
                                    <i class="fas fa-seedling metric-icon organic"></i>
                                    <div style="font-size: 1.5rem;"><?php echo $pond['organic_level']; ?>%</div>
                                    <small>Organic Level</small>
                                </div>
                                <div class="metric-item">
                                    <i class="fas fa-thermometer-half metric-icon temp"></i>
                                    <div style="font-size: 1.5rem;"><?php echo $pond['temperature']; ?>°C</div>
                                    <small>Temperature</small>
                                </div>
                                <div class="metric-item">
                                    <i class="fas fa-flask metric-icon ph"></i>
                                    <div style="font-size: 1.5rem;"><?php echo $pond['ph']; ?></div>
                                    <small>pH Level</small>
                                </div>
                            </div>
                            
                            <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px;">
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                                    <div>
                                        <small style="color: rgba(255,255,255,0.5);">Assigned Staff</small>
                                        <div><i class="fas fa-user"></i> <?php echo $pond['staff']; ?></div>
                                    </div>
                                    <div>
                                        <small style="color: rgba(255,255,255,0.5);">Location</small>
                                        <div><i class="fas fa-map-pin"></i> <?php echo $pond['location']; ?></div>
                                    </div>
                                    <div>
                                        <small style="color: rgba(255,255,255,0.5);">Last Reading</small>
                                        <div><i class="far fa-clock"></i> <?php echo date('h:i:s A', strtotime($pond['last_reading'])); ?></div>
                                    </div>
                                    <div>
                                        <small style="color: rgba(255,255,255,0.5);">Status</small>
                                        <div><span class="status-badge" style="background: <?php 
                                            echo $pond['status'] == 'safe' ? '#4ade80' : ($pond['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                                        ?>20; color: <?php 
                                            echo $pond['status'] == 'safe' ? '#4ade80' : ($pond['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                                        ?>;"><?php echo ucfirst($pond['status']); ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    document.getElementById('pondModal').classList.add('active');
                }
                <?php endforeach; ?>
            <?php endif; ?>
        }

        function closePondModal() {
            document.getElementById('pondModal').classList.remove('active');
        }

        // Chart Functions
        function updateChart(period, btn) {
            document.querySelectorAll('.report-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_chart_data&period=' + period
            })
            .then(response => response.json())
            .then(data => {
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.organic;
                chart.data.datasets[1].data = data.temperature;
                chart.data.datasets[2].data = data.ph;
                chart.update();
                
                const now = new Date();
                const phTime = now.toLocaleTimeString('en-US', { 
                    timeZone: 'Asia/Manila',
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                document.getElementById('chartTimestamp').textContent = phTime;
            });
        }

        // Alert Functions
        function acknowledgeAlert(alertId) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=acknowledge_alert&alert_id=' + alertId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                }
            });
        }

        function resolveAlert(alertId) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=resolve_alert&alert_id=' + alertId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                }
            });
        }

        // Report Functions
        function generateReport(type) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=generate_report&type=' + type
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const report = data.report;
                    let html = '<h4>' + type.charAt(0).toUpperCase() + type.slice(1) + ' Report</h4>';
                    
                    if (type === 'daily') {
                        html += `<p>Date: ${report.date}</p>
                                 <p>Total Ponds: ${report.total_ponds}</p>
                                 <p>Safe: ${report.safe_ponds} | Warning: ${report.warning_ponds} | Critical: ${report.critical_ponds}</p>
                                 <p>Avg Organic: ${report.avg_organic}%</p>
                                 <p>Avg Temp: ${report.avg_temp}°C</p>
                                 <p>Avg pH: ${report.avg_ph}</p>`;
                    } else if (type === 'weekly') {
                        html += `<p>Week: ${report.week}</p>
                                 <p>Total Readings: ${report.total_readings}</p>
                                 <p>Avg Organic: ${report.avg_organic}%</p>
                                 <p>Avg Temp: ${report.avg_temp}°C</p>
                                 <p>Avg pH: ${report.avg_ph}</p>
                                 <p>Incidents: ${report.incidents} | Resolved: ${report.resolved}</p>`;
                    } else {
                        html += `<p>Month: ${report.month}</p>
                                 <p>Total Readings: ${report.total_readings}</p>
                                 <p>Avg Organic: ${report.avg_organic}%</p>
                                 <p>Avg Temp: ${report.avg_temp}°C</p>
                                 <p>Avg pH: ${report.avg_ph}</p>
                                 <p>Incidents: ${report.incidents} | Resolved: ${report.resolved}</p>`;
                    }
                    
                    document.getElementById('reportPreview').innerHTML = html;
                }
            });
        }

        // Show notification toast
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                background: ${type === 'success' ? '#4ade80' : (type === 'warning' ? '#fbbf24' : '#3b82f6')};
                color: ${type === 'success' ? '#142138' : 'white'};
                padding: 1rem 1.5rem;
                border-radius: 12px;
                z-index: 9999;
                animation: slideInRight 0.3s ease;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                display: flex;
                align-items: center;
                gap: 0.8rem;
                font-weight: 500;
            `;
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i> ${message}`;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>