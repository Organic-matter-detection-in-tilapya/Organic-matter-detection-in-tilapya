<?php
// manager_dashboard_simulation.php
session_start();
require_once '../config/config.php';

// Check if user is logged in and is manager - REMOVE the simulation override
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    header("Location: login.php");
    exit();
}

// Set Philippines Time Zone
date_default_timezone_set('Asia/Manila');

// Get actual user data from session (not simulation override)
$manager_id = $_SESSION['user_id'];
$manager_name = $_SESSION['full_name']; // This will come from actual login
$manager_email = $_SESSION['email'];

$current_datetime = date('Y-m-d H:i:s');
$current_time_12hr = date('h:i:s A');
$current_date = date('F j, Y');
$current_day = date('l');

// Dummy data based sa database structure mo - lahat naka PH time
$staff_assignments = [
    [
        'user_id' => 3,
        'full_name' => 'Pedro Reyes',
        'email' => 'staff1@company.com',
        'assigned_pond' => 'A-1',
        'last_login' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'status' => 'active'
    ],
    [
        'user_id' => 4,
        'full_name' => 'Ana Lopez',
        'email' => 'staff2@company.com',
        'assigned_pond' => 'B-2',
        'last_login' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        'status' => 'active'
    ]
];

// Dummy ponds data na may PH timestamps
$ponds_data = [
    'A-1' => [
        'pond_id' => 1,
        'pond_name' => 'A-1',
        'organic_level' => 65,
        'temperature' => 28.5,
        'ph' => 7.2,
        'status' => 'warning',
        'staff' => 'Pedro Reyes',
        'location' => 'North Section',
        'last_reading' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
    ],
    'B-2' => [
        'pond_id' => 2,
        'pond_name' => 'B-2',
        'organic_level' => 82,
        'temperature' => 31.2,
        'ph' => 8.1,
        'status' => 'critical',
        'staff' => 'Ana Lopez',
        'location' => 'South Section',
        'last_reading' => date('Y-m-d H:i:s', strtotime('-2 minutes'))
    ]
];

// Dummy notifications/alerts na may PH timestamps
$notifications = [
    [
        'notification_id' => 1,
        'pond_id' => 2,
        'pond_name' => 'B-2',
        'message' => 'High organic level (82%) detected. Temperature above threshold (31.2°C).',
        'status' => 'unread',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
        'type' => 'critical'
    ],
    [
        'notification_id' => 2,
        'pond_id' => 1,
        'pond_name' => 'A-1',
        'message' => 'Organic level approaching threshold (65%). Monitor closely.',
        'status' => 'unread',
        'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
        'type' => 'warning'
    ],
    [
        'notification_id' => 3,
        'pond_id' => 1,
        'pond_name' => 'A-1',
        'message' => 'Routine check: All systems normal',
        'status' => 'read',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'type' => 'info'
    ]
];

// Dummy chart data na may PH time labels
$chart_data = [
    'labels' => [],
    'organic' => [],
    'temperature' => [],
    'ph' => []
];

// Generate 24 hours of dummy data with PH time labels
for ($i = 23; $i >= 0; $i--) {
    $hour = date('H:00', strtotime("-$i hours"));
    $chart_data['labels'][] = $hour;
    
    $trend = sin($i * 0.3) * 10;
    $chart_data['organic'][] = round(60 + $trend + rand(-3, 3), 1);
    $chart_data['temperature'][] = round(28 + sin($i * 0.2) * 3 + rand(-1, 1), 1);
    $chart_data['ph'][] = round(7.2 + sin($i * 0.25) * 0.5 + rand(-1, 1) / 10, 1);
}

// Dummy readings history na may PH timestamps
$recent_readings = [
    [
        'detection_id' => 101,
        'pond_name' => 'A-1',
        'organic_level' => 65,
        'water_temperature' => 28.5,
        'ph_level' => 7.2,
        'detected_at' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
        'status' => 'warning'
    ],
    [
        'detection_id' => 102,
        'pond_name' => 'B-2',
        'organic_level' => 82,
        'water_temperature' => 31.2,
        'ph_level' => 8.1,
        'detected_at' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
        'status' => 'critical'
    ],
    [
        'detection_id' => 100,
        'pond_name' => 'A-1',
        'organic_level' => 63,
        'water_temperature' => 28.2,
        'ph_level' => 7.1,
        'detected_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
        'status' => 'normal'
    ]
];

// Handle AJAX requests for simulation
if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if($_POST['action'] == 'get_chart_data') {
        $period = $_POST['period'] ?? 'daily';
        
        $data = ['labels' => [], 'organic' => [], 'temperature' => [], 'ph' => []];
        
        if ($period == 'daily') {
            $points = 24;
            for ($i = 23; $i >= 0; $i--) {
                $data['labels'][] = date('H:00', strtotime("-$i hours"));
                $data['organic'][] = rand(45, 85);
                $data['temperature'][] = rand(25, 33);
                $data['ph'][] = rand(65, 85) / 10;
            }
        } elseif ($period == 'weekly') {
            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            for ($i = 6; $i >= 0; $i--) {
                $data['labels'][] = $days[$i];
                $data['organic'][] = rand(45, 85);
                $data['temperature'][] = rand(25, 33);
                $data['ph'][] = rand(65, 85) / 10;
            }
        } else {
            for ($i = 30; $i >= 1; $i--) {
                $data['labels'][] = date('M d', strtotime("-$i days"));
                $data['organic'][] = rand(45, 85);
                $data['temperature'][] = rand(25, 33);
                $data['ph'][] = rand(65, 85) / 10;
            }
        }
        
        echo json_encode($data);
        exit;
    }
    
    if($_POST['action'] == 'notify_admin') {
        $notification_id = $_POST['notification_id'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin notified successfully',
            'notification_id' => $notification_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    if($_POST['action'] == 'get_live_updates') {
        echo json_encode([
            'notifications' => rand(1, 3),
            'timestamp' => date('Y-m-d H:i:s'),
            'time_12hr' => date('h:i:s A'),
            'message' => 'New data available'
        ]);
        exit;
    }
    
    if($_POST['action'] == 'mark_read') {
        $notification_id = $_POST['notification_id'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
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
    <title>Organic Tilapia - Manager Dashboard (Simulation)</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        /* Copy all your existing CSS here - same as before */
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            font-size: 1.8rem;
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
            padding: 0.5rem;
            border-radius: 12px;
        }

        .simulation-badge {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            border: 1px solid rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .user-badge {
            background: #2a3f5e;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
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

        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
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
            border: 1px solid rgba(255, 255, 255, 0.05);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .time-display {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .time-box {
            background: #1e2f47;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-family: monospace;
            font-size: 0.9rem;
        }

        .date-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .live-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(239, 68, 68, 0.1);
            padding: 0.4rem 1rem;
            border-radius: 50px;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        /* Pond Cards */
        .pond-card {
            cursor: pointer;
            position: relative;
            overflow: hidden;
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

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .timestamp {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Staff List */
        .staff-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .staff-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .staff-item:hover {
            background: rgba(255, 255, 255, 0.07);
            transform: translateX(5px);
            border-color: rgba(59, 130, 246, 0.3);
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

        /* Map */
        #map {
            height: 400px;
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        /* Report Buttons */
        .report-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .report-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .report-btn.active {
            background: #2a3f5e;
            border-color: #3b82f6;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }

        .report-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        /* Chart Container */
        .chart-container {
            height: 300px;
            margin-top: 1rem;
            position: relative;
        }

        /* Alerts */
        .alert-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.8rem;
            cursor: pointer;
            animation: slideIn 0.5s ease;
            border: 1px solid transparent;
        }

        .alert-item:hover {
            border-color: rgba(255, 255, 255, 0.2);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-item.critical {
            background: rgba(239, 68, 68, 0.15);
            border-left: 4px solid #ef4444;
        }

        .alert-item.warning {
            background: rgba(251, 191, 36, 0.15);
            border-left: 4px solid #fbbf24;
        }

        .alert-item.info {
            background: rgba(59, 130, 246, 0.15);
            border-left: 4px solid #3b82f6;
        }

        .notify-btn {
            background: #2a3f5e;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notify-btn:hover {
            background: #3b82f6;
            transform: translateY(-2px);
        }

        .notify-btn.small {
            padding: 0.3rem 1rem;
            font-size: 0.8rem;
        }

        /* Notification Badge */
        .notification-badge {
            background: #ef4444;
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            margin-left: 0.5rem;
            animation: pulse 2s infinite;
        }

        /* Map Legend */
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

        /* PH Time Badge */
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

        /* Responsive */
        @media (max-width: 1200px) {
            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }
            
            .dashboard-container {
                padding: 1rem;
            }
            
            .time-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo-area">
            <i class="fas fa-fish logo"></i>
            <span style="font-weight: 600;">Organic Tilapia Monitoring System</span>
            <span class="simulation-badge">
                <i class="fas fa-sim-card"></i> SIMULATION MODE
            </span>
            <span id="notificationBadge" class="notification-badge" style="display: none;">0</span>
        </div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div class="user-badge">
                <i class="fas fa-user-tie"></i>
                <span>Manager – <?php echo htmlspecialchars($manager_name); ?></span>
            <a href="../auth/logout.php" class="logout-btn" onclick="return confirm('Logout from Manager Dashboard?')">
    <i class="fas fa-sign-out-alt"></i> Logout
</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Time Bar with PH Time -->
        <div class="time-bar">
            <div class="time-display">
                <div class="date-box">
                    <i class="fas fa-calendar-alt" style="color: #3b82f6;"></i>
                    <span><?php echo $current_date; ?></span>
                    <span class="ph-time-badge">
                        <i class="fas fa-map-marker-alt"></i> PH Time
                    </span>
                </div>
                <div class="time-box" id="currentTime">
                    <?php echo $current_time_12hr; ?>
                </div>
                <div style="color: rgba(255,255,255,0.5); font-size: 0.9rem;">
                    <i class="fas fa-sun"></i> <?php echo $current_day; ?>
                </div>
            </div>
            <div class="live-indicator">
                <span class="live-dot"></span>
                <span>Simulation Mode - Live Data Streaming</span>
            </div>
        </div>

        <!-- Rest of your HTML remains the same -->
        <!-- Staff & Pond Assignments -->
        <div class="grid-2">
            <!-- Staff Assignments Card -->
            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-users" style="color: #3b82f6;"></i> Staff Assignments</span>
                    <span style="font-size: 0.8rem; color: rgba(255,255,255,0.5);">
                        <i class="fas fa-sync-alt fa-spin"></i> Live
                    </span>
                </div>
                <div class="staff-list">
                    <?php foreach($staff_assignments as $staff): ?>
                    <div class="staff-item" onclick="highlightStaffPond('<?php echo $staff['assigned_pond']; ?>', '<?php echo $staff['full_name']; ?>')">
                        <div style="display: flex; align-items: center; gap: 1rem; width: 100%;">
                            <div class="staff-avatar">
                                <?php 
                                    $initials = '';
                                    $names = explode(' ', $staff['full_name']);
                                    foreach($names as $n) {
                                        $initials .= strtoupper(substr($n, 0, 1));
                                    }
                                    echo $initials;
                                ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                                    <span class="status-badge" style="background: rgba(74,222,128,0.2); color: #4ade80;">
                                        <i class="fas fa-circle"></i> Active
                                    </span>
                                </div>
                                <div style="display: flex; gap: 1rem; margin-top: 0.3rem; font-size: 0.85rem; color: rgba(255,255,255,0.6);">
                                    <span><i class="fas fa-map-marker-alt"></i> Pond <?php echo $staff['assigned_pond'] ?? 'Unassigned'; ?></span>
                                    <span><i class="far fa-clock"></i> Last: <?php echo date('h:i A', strtotime($staff['last_login'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Ponds Overview -->
            <div class="grid-2" style="margin: 0; gap: 1rem;">
                <?php foreach($ponds_data as $pond_name => $data): ?>
                <div class="card pond-card" data-status="<?php echo $data['status']; ?>" onclick="highlightPond('<?php echo $pond_name; ?>')">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <h3 style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-map-marker-alt" style="color: <?php 
                                echo $data['status'] == 'safe' ? '#4ade80' : 
                                    ($data['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                            ?>;"></i>
                            Pond <?php echo $pond_name; ?>
                        </h3>
                        <span class="status-badge" style="background: <?php 
                            echo $data['status'] == 'safe' ? 'rgba(74,222,128,0.2)' : 
                                ($data['status'] == 'warning' ? 'rgba(251,191,36,0.2)' : 'rgba(239,68,68,0.2)'); 
                        ?>; color: <?php 
                            echo $data['status'] == 'safe' ? '#4ade80' : 
                                ($data['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                        ?>;">
                            <i class="fas fa-circle"></i> <?php echo ucfirst($data['status']); ?>
                        </span>
                    </div>
                    
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 0.8rem;">
                        <i class="fas fa-user"></i> <?php echo $data['staff']; ?> • 
                        <i class="fas fa-map-pin"></i> <?php echo $data['location']; ?>
                    </div>
                    
                    <div class="metrics-grid">
                        <div class="metric-item">
                            <i class="fas fa-seedling metric-icon organic"></i>
                            <div style="font-size: 1.3rem; font-weight: 600;"><?php echo $data['organic_level']; ?>%</div>
                            <small style="color: rgba(255,255,255,0.5);">Organic</small>
                        </div>
                        <div class="metric-item">
                            <i class="fas fa-thermometer-half metric-icon temp"></i>
                            <div style="font-size: 1.3rem; font-weight: 600;"><?php echo $data['temperature']; ?>°C</div>
                            <small style="color: rgba(255,255,255,0.5);">Temp</small>
                        </div>
                        <div class="metric-item">
                            <i class="fas fa-flask metric-icon ph"></i>
                            <div style="font-size: 1.3rem; font-weight: 600;"><?php echo $data['ph']; ?></div>
                            <small style="color: rgba(255,255,255,0.5);">pH</small>
                        </div>
                    </div>
                    
                    <div class="timestamp">
                        <i class="far fa-clock"></i> Last reading: <?php echo date('h:i:s A', strtotime($data['last_reading'])); ?>
                        <?php 
                        $time_diff = time() - strtotime($data['last_reading']);
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
            </div>
        </div>

        <!-- Live Map -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                <span><i class="fas fa-map-marked-alt" style="color: #3b82f6;"></i> Live Pond Map - Manolo Fortich</span>
                <div class="map-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #4ade80;"></div>
                        <span>Safe</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #fbbf24;"></div>
                        <span>Warning</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #ef4444;"></div>
                        <span>Critical</span>
                    </div>
                </div>
            </div>
            <div id="map"></div>
            <div style="margin-top: 0.5rem; text-align: right; font-size: 0.8rem; color: rgba(255,255,255,0.4);">
                <i class="far fa-clock"></i> Map data simulated • PH Time: <span id="mapTimestamp"><?php echo date('h:i:s A'); ?></span>
            </div>
        </div>

        <!-- Live Metrics and Staff Monitoring -->
        <div class="grid-2">
            <!-- Live Metrics Trends -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <span><i class="fas fa-chart-line" style="color: #3b82f6;"></i> Live Metrics Trends</span>
                    <div class="report-buttons">
                        <button class="report-btn active" onclick="updateChartSimulation('daily', this)">Daily</button>
                        <button class="report-btn" onclick="updateChartSimulation('weekly', this)">Weekly</button>
                        <button class="report-btn" onclick="updateChartSimulation('monthly', this)">Monthly</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="metricsChart"></canvas>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 1rem; font-size: 0.8rem; color: rgba(255,255,255,0.4);">
                    <span><i class="fas fa-chart-line"></i> Simulation data (PH Time)</span>
                    <span>Last updated: <span id="chartTimestamp"><?php echo date('h:i:s A'); ?></span></span>
                </div>
            </div>

            <!-- Recent Readings / Staff Monitoring -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span><i class="fas fa-history" style="color: #3b82f6;"></i> Recent Pond Readings</span>
                    <span class="status-badge" style="background: rgba(59,130,246,0.2); color: #3b82f6;">
                        <i class="fas fa-sync-alt fa-spin"></i> Live Updates
                    </span>
                </div>
                <div class="staff-list">
                    <?php foreach($recent_readings as $reading): ?>
                    <div class="staff-item" onclick="highlightPond('<?php echo $reading['pond_name']; ?>')">
                        <div style="width: 100%;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong><i class="fas fa-map-marker-alt"></i> Pond <?php echo $reading['pond_name']; ?></strong>
                                <span class="status-badge" style="background: <?php 
                                    echo $reading['status'] == 'critical' ? 'rgba(239,68,68,0.2)' : 
                                        ($reading['status'] == 'warning' ? 'rgba(251,191,36,0.2)' : 'rgba(74,222,128,0.2)'); 
                                ?>; color: <?php 
                                    echo $reading['status'] == 'critical' ? '#ef4444' : 
                                        ($reading['status'] == 'warning' ? '#fbbf24' : '#4ade80'); 
                                ?>;">
                                    <?php echo ucfirst($reading['status']); ?>
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; background: rgba(255,255,255,0.02); padding: 0.8rem; border-radius: 8px;">
                                <div>
                                    <small style="color: rgba(255,255,255,0.5);">Organic</small>
                                    <div style="font-weight: 600; color: #4ade80;"><?php echo $reading['organic_level']; ?>%</div>
                                </div>
                                <div>
                                    <small style="color: rgba(255,255,255,0.5);">Temp</small>
                                    <div style="font-weight: 600; color: #fbbf24;"><?php echo $reading['water_temperature']; ?>°C</div>
                                </div>
                                <div>
                                    <small style="color: rgba(255,255,255,0.5);">pH</small>
                                    <div style="font-weight: 600; color: #a78bfa;"><?php echo $reading['ph_level']; ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.75rem; color: rgba(255,255,255,0.4); display: flex; justify-content: space-between;">
                                <span><i class="far fa-clock"></i> <?php echo date('h:i:s A', strtotime($reading['detected_at'])); ?></span>
                                <?php 
                                $time_diff = time() - strtotime($reading['detected_at']);
                                if($time_diff < 60) {
                                    echo '<span style="color: #4ade80;">Just now</span>';
                                } elseif($time_diff < 3600) {
                                    $mins = floor($time_diff / 60);
                                    echo '<span style="color: #fbbf24;">' . $mins . ' min ago</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Notifications / Alerts Section -->
        <div class="card" style="margin-top: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                <span><i class="fas fa-bell" style="color: #ef4444;"></i> Notifications & Alerts</span>
                <button class="notify-btn" onclick="notifyAllAdminSimulation()">
                    <i class="fas fa-bell"></i> Notify All Admin
                </button>
            </div>
            <div>
                <?php 
                $unread_count = 0;
                foreach($notifications as $notification): 
                    if($notification['status'] == 'unread') $unread_count++;
                ?>
                <div class="alert-item <?php echo $notification['type']; ?>" onclick="markNotificationReadSimulation(<?php echo $notification['notification_id']; ?>)">
                    <i class="fas fa-<?php 
                        echo $notification['type'] == 'critical' ? 'exclamation-circle' : 
                            ($notification['type'] == 'warning' ? 'exclamation-triangle' : 'info-circle'); 
                    ?>" style="font-size: 1.2rem; color: <?php 
                        echo $notification['type'] == 'critical' ? '#ef4444' : 
                            ($notification['type'] == 'warning' ? '#fbbf24' : '#3b82f6'); 
                    ?>;"></i>
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem; flex-wrap: wrap;">
                            <strong>Pond <?php echo $notification['pond_name']; ?></strong>
                            <?php if($notification['status'] == 'unread'): ?>
                            <span style="background: #ef4444; color: white; padding: 0.2rem 0.5rem; border-radius: 50px; font-size: 0.7rem;">NEW</span>
                            <?php endif; ?>
                            <span style="font-size: 0.7rem; color: rgba(255,255,255,0.4);">
                                <i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($notification['created_at'])); ?>
                            </span>
                        </div>
                        <p style="font-size: 0.9rem; color: rgba(255,255,255,0.8); margin-bottom: 0.3rem;">
                            <?php echo $notification['message']; ?>
                        </p>
                        <small style="color: rgba(255,255,255,0.4);">
                            <?php 
                            $time_diff = time() - strtotime($notification['created_at']);
                            if($time_diff < 60) {
                                echo 'Just now';
                            } elseif($time_diff < 3600) {
                                $mins = floor($time_diff / 60);
                                echo $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
                            } elseif($time_diff < 86400) {
                                $hours = floor($time_diff / 3600);
                                echo $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                            } else {
                                $days = floor($time_diff / 86400);
                                echo $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                            }
                            ?>
                        </small>
                    </div>
                    <?php if($notification['type'] == 'critical' || $notification['type'] == 'warning'): ?>
                    <button class="notify-btn small" onclick="event.stopPropagation(); notifyAdminSimulation(<?php echo $notification['notification_id']; ?>)">
                        <i class="fas fa-bell"></i> Notify Admin
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php if(empty($notifications)): ?>
                <div style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.5);">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: #4ade80; margin-bottom: 1rem;"></i>
                    <p>No notifications. All systems normal.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if($unread_count > 0): ?>
            <div style="margin-top: 1rem; padding: 0.5rem; background: rgba(239,68,68,0.1); border-radius: 8px; text-align: center; font-size: 0.9rem;">
                <i class="fas fa-info-circle"></i> You have <strong><?php echo $unread_count; ?></strong> unread notification<?php echo $unread_count > 1 ? 's' : ''; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Simulation Footer with PH Time -->
        <div style="margin-top: 2rem; padding: 1rem; text-align: center; background: rgba(255,255,255,0.02); border-radius: 50px; font-size: 0.85rem; color: rgba(255,255,255,0.4); border: 1px solid rgba(255,255,255,0.05);">
            <i class="fas fa-sim-card"></i> SIMULATION MODE - All data shown are for demonstration purposes only • 
            <span>PH Time: <span id="footerTimestamp"><?php echo date('h:i:s A'); ?></span></span>
            <span style="margin-left: 1rem; background: #1e2f47; padding: 0.2rem 0.5rem; border-radius: 50px; font-size: 0.7rem;">
                <i class="fas fa-map-marker-alt"></i> Asia/Manila
            </span>
        </div>
    </div>

    <script>
        // Set timezone to PH time in JavaScript
        const phTimeOptions = { 
            timeZone: 'Asia/Manila',
            hour12: true,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        
        const phDateOptions = {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        
        const phDayOptions = {
            timeZone: 'Asia/Manila',
            weekday: 'long'
        };

        // Initialize map and markers
        let map, markers = {}, chart;
        let updateInterval;

        // Custom marker icons with blinking animation
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
            // Initialize map
            map = L.map('map').setView([8.3695, 124.8698], 15);
            
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            // Add markers for each pond
            <?php 
            $marker_positions = [
                'A-1' => [8.3678, 124.8685],
                'B-2' => [8.3712, 124.8712]
            ];
            
            foreach($ponds_data as $pond_name => $data): 
                $coords = $marker_positions[$pond_name] ?? [8.3695, 124.8698];
            ?>
                markers['<?php echo $pond_name; ?>'] = L.marker(
                    [<?php echo $coords[0]; ?>, <?php echo $coords[1]; ?>],
                    { 
                        icon: createMarkerIcon('<?php echo $data['status']; ?>'),
                        riseOnHover: true
                    }
                ).addTo(map)
                .bindPopup(`
                    <div style="color: #142138; padding: 12px; max-width: 250px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                            <i class="fas fa-map-marker-alt" style="color: <?php 
                                echo $data['status'] == 'safe' ? '#4ade80' : 
                                    ($data['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                            ?>; font-size: 1.2rem;"></i>
                            <h3 style="margin: 0; font-size: 1.1rem;">Pond <?php echo $pond_name; ?></h3>
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
                            <p style="margin: 3px 0; font-size: 0.8rem; color: #666;">
                                <i class="far fa-clock"></i> <?php echo date('h:i:s A', strtotime($data['last_reading'])); ?> PH Time
                            </p>
                        </div>
                    </div>
                `);
            <?php endforeach; ?>

            // Initialize chart
            const ctx = document.getElementById('metricsChart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_data['labels']); ?>,
                    datasets: [
                        {
                            label: 'Organic Level',
                            data: <?php echo json_encode($chart_data['organic']); ?>,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        },
                        {
                            label: 'Temperature',
                            data: <?php echo json_encode($chart_data['temperature']); ?>,
                            borderColor: '#fbbf24',
                            backgroundColor: 'rgba(251, 191, 36, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        },
                        {
                            label: 'pH Level',
                            data: <?php echo json_encode($chart_data['ph']); ?>,
                            borderColor: '#4ade80',
                            backgroundColor: 'rgba(74, 222, 128, 0.1)',
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

            // Start simulation updates with PH time
            startSimulationUpdates();
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

        // Start simulation updates with PH time
        function startSimulationUpdates() {
            // Update current time every second (PH time)
            setInterval(() => {
                const now = new Date();
                const phTime = now.toLocaleTimeString('en-US', { 
                    timeZone: 'Asia/Manila',
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                const phDate = now.toLocaleDateString('en-US', {
                    timeZone: 'Asia/Manila',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                const phDay = now.toLocaleDateString('en-US', {
                    timeZone: 'Asia/Manila',
                    weekday: 'long'
                });
                
                document.getElementById('currentTime').textContent = phTime;
                document.getElementById('mapTimestamp').textContent = phTime;
                document.getElementById('footerTimestamp').textContent = phTime;
            }, 1000);

            // Simulate random notification count updates
            setInterval(() => {
                const badge = document.getElementById('notificationBadge');
                const randomCount = Math.floor(Math.random() * 3) + 1;
                badge.textContent = randomCount;
                badge.style.display = 'inline';
                
                // Randomly update chart timestamp
                const now = new Date();
                const phTime = now.toLocaleTimeString('en-US', { 
                    timeZone: 'Asia/Manila',
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                document.getElementById('chartTimestamp').textContent = phTime;
            }, 30000);
        }

        // Update chart simulation
        function updateChartSimulation(period, btn) {
            // Update active button
            document.querySelectorAll('.report-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Show loading state
            const chartContainer = document.querySelector('.chart-container');
            chartContainer.style.opacity = '0.5';

            // Simulate API call
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_chart_data&period=' + period
            })
            .then(response => response.json())
            .then(data => {
                setTimeout(() => {
                    chart.data.labels = data.labels;
                    chart.data.datasets[0].data = data.organic;
                    chart.data.datasets[1].data = data.temperature;
                    chart.data.datasets[2].data = data.ph;
                    chart.update();
                    
                    chartContainer.style.opacity = '1';
                    
                    const now = new Date();
                    const phTime = now.toLocaleTimeString('en-US', { 
                        timeZone: 'Asia/Manila',
                        hour12: true,
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    document.getElementById('chartTimestamp').textContent = phTime;
                    
                    showSimulationFeedback(`Chart updated to ${period} view (PH Time)`);
                }, 600);
            });
        }

        // Highlight pond on map
        function highlightPond(pondName) {
            if (markers[pondName]) {
                map.setView(markers[pondName].getLatLng(), 18);
                markers[pondName].openPopup();
                
                // Highlight card animation
                document.querySelectorAll('.pond-card').forEach(card => {
                    if (card.querySelector('h3').innerText.includes(pondName)) {
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
                
                showSimulationFeedback(`Pond ${pondName} highlighted on map`);
            } else {
                showSimulationFeedback(`Pond ${pondName} location not set`, 'warning');
            }
        }

        // Highlight staff's assigned pond
        function highlightStaffPond(pondName, staffName) {
            if (pondName) {
                highlightPond(pondName);
                showSimulationFeedback(`Viewing ${staffName}'s pond (Pond ${pondName})`);
            } else {
                showSimulationFeedback(`${staffName} has no assigned pond`, 'warning');
            }
        }

        // Notify admin simulation
        function notifyAdminSimulation(notificationId) {
            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Notified';
                btn.style.background = '#4ade80';
                btn.style.color = '#142138';
                
                showNotification('Admin notified successfully', 'success');
                
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.style.background = '';
                    btn.style.color = '';
                    btn.disabled = false;
                }, 2000);
            }, 1000);
        }

        // Notify all admin simulation
        function notifyAllAdminSimulation() {
            const btns = document.querySelectorAll('.alert-item .notify-btn');
            let count = 0;
            
            btns.forEach(btn => {
                if (!btn.disabled) {
                    btn.click();
                    count++;
                }
            });
            
            if (count > 0) {
                showNotification(`Notified admin about ${count} alert(s)`, 'success');
            } else {
                showNotification('No pending notifications to send', 'info');
            }
        }

        // Mark notification as read simulation
        function markNotificationReadSimulation(notificationId) {
            const alertItem = event.currentTarget;
            alertItem.style.opacity = '0.5';
            
            setTimeout(() => {
                alertItem.remove();
                showNotification('Notification marked as read', 'info');
                
                // Update badge count
                const remaining = document.querySelectorAll('.alert-item').length;
                if (remaining === 0) {
                    document.querySelector('.alert-section').innerHTML = `
                        <div style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-check-circle" style="font-size: 3rem; color: #4ade80; margin-bottom: 1rem;"></i>
                            <p>No notifications. All systems normal.</p>
                        </div>
                    `;
                }
            }, 500);
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

        // Show simulation feedback
        function showSimulationFeedback(message, type = 'info') {
            const feedback = document.createElement('div');
            feedback.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px;
                background: #1e2f47;
                color: white;
                padding: 0.8rem 1.2rem;
                border-radius: 50px;
                z-index: 9999;
                animation: fadeInUp 0.3s ease;
                font-size: 0.9rem;
                border: 1px solid rgba(255,255,255,0.1);
                display: flex;
                align-items: center;
                gap: 0.5rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            `;
            feedback.innerHTML = `<i class="fas fa-sim-card" style="color: #3b82f6;"></i> ${message}`;
            
            document.body.appendChild(feedback);
            
            setTimeout(() => {
                feedback.style.animation = 'fadeOutDown 0.3s ease';
                setTimeout(() => feedback.remove(), 300);
            }, 2000);
        }

        // Remove the simulateLogout function since we're using direct link to logout.php
        // Or keep it but redirect to logout.php
        function simulateLogout() {
            if (confirm('Logout from Manager Dashboard?')) {
                window.location.href = 'logout.php';
            }
        }

        // Add animation keyframes
        const animationStyles = document.createElement('style');
        animationStyles.textContent = `
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
            
            @keyframes fadeInUp {
                from {
                    transform: translateY(100%);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            @keyframes fadeOutDown {
                from {
                    transform: translateY(0);
                    opacity: 1;
                }
                to {
                    transform: translateY(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(animationStyles);
    </script>
</body>
</html>