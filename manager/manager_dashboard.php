<?php
// manager_dashboard_enhanced.php
session_start();
require_once '../config/config.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    header("Location: ../auth/login.php");
    exit();
}

// Set Philippines Time Zone
date_default_timezone_set('Asia/Manila');

// Get actual user data from session
$manager_id = $_SESSION['user_id'];
$manager_name = $_SESSION['full_name'] ?? 'Manager';
$manager_email = $_SESSION['email'] ?? 'manager@example.com';

$current_datetime = date('Y-m-d H:i:s');
$current_time_12hr = date('h:i:s A');
$current_date = date('F j, Y');
$current_day = date('l');

// ============================================
// POND COORDINATES - MANOLO FORTICH
// ============================================
$pond_coordinates = [
    'A-1' => [
        'name' => 'Tilapia Pond A-1',
        'center' => [8.3695, 124.8645],
        'staff' => 'Pedro Reyes',
        'bounds' => [
            [8.3692, 124.8642], [8.3698, 124.8640],
            [8.3700, 124.8648], [8.3696, 124.8650],
            [8.3692, 124.8642]
        ]
    ],
    'B-2' => [
        'name' => 'Tilapia Pond B-2',
        'center' => [8.3688, 124.8652],
        'staff' => 'Ana Lopez',
        'bounds' => [
            [8.3685, 124.8649], [8.3690, 124.8647],
            [8.3693, 124.8654], [8.3688, 124.8656],
            [8.3685, 124.8649]
        ]
    ],
    'C-1' => [
        'name' => 'Tilapia Pond C-1',
        'center' => [8.3700, 124.8660],
        'staff' => 'Roberto Gomez',
        'bounds' => [
            [8.3697, 124.8657], [8.3703, 124.8655],
            [8.3705, 124.8663], [8.3699, 124.8665],
            [8.3697, 124.8657]
        ]
    ]
];

// ============================================
// STAFF ASSIGNMENTS
// ============================================
$staff_assignments = [
    [
        'user_id' => 3,
        'full_name' => 'Pedro Reyes',
        'email' => 'pedro.reyes@company.com',
        'assigned_pond' => 'A-1',
        'last_login' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'status' => 'active',
        'avatar_color' => '#4ade80'
    ],
    [
        'user_id' => 4,
        'full_name' => 'Ana Lopez',
        'email' => 'ana.lopez@company.com',
        'assigned_pond' => 'B-2',
        'last_login' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        'status' => 'active',
        'avatar_color' => '#fbbf24'
    ],
    [
        'user_id' => 5,
        'full_name' => 'Roberto Gomez',
        'email' => 'roberto.gomez@company.com',
        'assigned_pond' => 'C-1',
        'last_login' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
        'status' => 'active',
        'avatar_color' => '#a78bfa'
    ]
];

// ============================================
// PONDS DATA
// ============================================
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
    ],
    'C-1' => [
        'pond_id' => 3,
        'pond_name' => 'C-1',
        'organic_level' => 45,
        'temperature' => 27.5,
        'ph' => 7.5,
        'status' => 'safe',
        'staff' => 'Roberto Gomez',
        'location' => 'East Section',
        'last_reading' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
    ]
];

// ============================================
// NOTIFICATIONS
// ============================================
$notifications = [
    [
        'notification_id' => 1,
        'pond_id' => 2,
        'pond_name' => 'B-2',
        'message' => 'HIGH ALERT: Organic level 82% - Critical! Temperature 31.2°C',
        'status' => 'unread',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
        'type' => 'critical'
    ],
    [
        'notification_id' => 2,
        'pond_id' => 1,
        'pond_name' => 'A-1',
        'message' => 'WARNING: Organic level 65% - Monitor closely',
        'status' => 'unread',
        'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
        'type' => 'warning'
    ],
    [
        'notification_id' => 3,
        'pond_id' => 3,
        'pond_name' => 'C-1',
        'message' => 'INFO: All systems normal - Safe condition',
        'status' => 'read',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'type' => 'info'
    ]
];

// ============================================
// CHART DATA - FIXED
// ============================================
$chart_data = [
    'labels' => [],
    'organic' => [],
    'temperature' => [],
    'ph' => []
];

// Generate 24 hours of data
for ($i = 23; $i >= 0; $i--) {
    $hour = date('H:00', strtotime("-$i hours"));
    $chart_data['labels'][] = $hour;
    
    // Create smooth trending data
    $base_organic = 60 + sin($i * 0.3) * 10;
    $base_temp = 28 + sin($i * 0.2) * 3;
    $base_ph = 7.2 + sin($i * 0.25) * 0.5;
    
    $chart_data['organic'][] = round($base_organic + rand(-2, 2), 1);
    $chart_data['temperature'][] = round($base_temp + rand(-1, 1), 1);
    $chart_data['ph'][] = round($base_ph + rand(-0.1, 0.1), 1);
}

// ============================================
// RECENT READINGS
// ============================================
$recent_readings = [
    [
        'detection_id' => 103,
        'pond_name' => 'C-1',
        'organic_level' => 45,
        'water_temperature' => 27.5,
        'ph_level' => 7.5,
        'detected_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        'status' => 'safe'
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
        'detection_id' => 101,
        'pond_name' => 'A-1',
        'organic_level' => 65,
        'water_temperature' => 28.5,
        'ph_level' => 7.2,
        'detected_at' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
        'status' => 'warning'
    ]
];

// AJAX Handlers
if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if($_POST['action'] == 'get_chart_data') {
        $period = $_POST['period'] ?? 'daily';
        
        $data = ['labels' => [], 'organic' => [], 'temperature' => [], 'ph' => []];
        
        if ($period == 'daily') {
            for ($i = 23; $i >= 0; $i--) {
                $data['labels'][] = date('H:00', strtotime("-$i hours"));
                $data['organic'][] = rand(45, 85);
                $data['temperature'][] = rand(25, 33);
                $data['ph'][] = round(rand(65, 85) / 10, 1);
            }
        } elseif ($period == 'weekly') {
            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            for ($i = 6; $i >= 0; $i--) {
                $data['labels'][] = $days[$i];
                $data['organic'][] = rand(45, 85);
                $data['temperature'][] = rand(25, 33);
                $data['ph'][] = round(rand(65, 85) / 10, 1);
            }
        } else {
            for ($i = 29; $i >= 0; $i--) {
                $data['labels'][] = date('M d', strtotime("-$i days"));
                $data['organic'][] = rand(45, 85);
                $data['temperature'][] = rand(25, 33);
                $data['ph'][] = round(rand(65, 85) / 10, 1);
            }
        }
        
        echo json_encode($data);
        exit;
    }
    
    if($_POST['action'] == 'notify_admin') {
        echo json_encode([
            'success' => true,
            'message' => 'Admin notified successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - 3 Ponds Monitoring</title>
    
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
            background: rgba(59, 130, 246, 0.1);
            padding: 0.5rem;
            border-radius: 12px;
        }

        .location-badge {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .user-badge {
            background: #2a3f5e;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        /* Dashboard Container */
        .dashboard-container {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
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

        .ph-time-badge {
            background: #3b82f6;
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
        }

        .live-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(239, 68, 68, 0.1);
            padding: 0.4rem 1rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .live-indicator:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        .live-dot.running {
            background: #4ade80;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Section Title */
        .section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 2rem 0 1.5rem;
        }

        .section-title i {
            color: #3b82f6;
            font-size: 1.3rem;
        }

        .section-title h2 {
            font-size: 1.3rem;
            font-weight: 600;
        }

        /* Staff Grid */
        .staff-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .staff-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .staff-card:hover {
            transform: translateY(-5px);
            border-color: #3b82f6;
        }

        .staff-avatar {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        /* Ponds Grid */
        .ponds-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .pond-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pond-card:hover {
            transform: translateY(-5px);
            border-color: #3b82f6;
        }

        .pond-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pond-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .pond-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
            margin: 1rem 0;
        }

        .metric-box {
            background: rgba(255, 255, 255, 0.03);
            padding: 0.8rem;
            border-radius: 12px;
            text-align: center;
        }

        .metric-value {
            font-size: 1.3rem;
            font-weight: 600;
            margin-top: 0.3rem;
        }

        /* Map Container */
        .map-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .map-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        #map {
            height: 400px;
            width: 100%;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .map-legend {
            display: flex;
            gap: 1rem;
            background: rgba(13, 23, 41, 0.8);
            padding: 0.5rem 1rem;
            border-radius: 50px;
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
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

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
        }

        .report-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .chart-wrapper {
            position: relative;
            height: 350px;
            width: 100%;
        }

        /* Readings Grid */
        .readings-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .reading-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reading-card:hover {
            transform: translateY(-3px);
            border-color: #3b82f6;
        }

        /* Notifications */
        .notifications-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .alert-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.8rem;
            animation: slideIn 0.3s ease;
        }

        .alert-item.critical { background: rgba(239, 68, 68, 0.15); border-left: 4px solid #ef4444; }
        .alert-item.warning { background: rgba(251, 191, 36, 0.15); border-left: 4px solid #fbbf24; }
        .alert-item.info { background: rgba(59, 130, 246, 0.15); border-left: 4px solid #3b82f6; }

        .notify-btn {
            background: #2a3f5e;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notify-btn:hover {
            background: #3b82f6;
        }

        .notify-btn.small {
            padding: 0.3rem 1rem;
            font-size: 0.8rem;
        }

        /* Footer */
        .footer {
            margin-top: 2rem;
            padding: 1rem;
            text-align: center;
            background: rgba(255,255,255,0.02);
            border-radius: 50px;
            color: rgba(255,255,255,0.4);
        }

        /* Animations */
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .staff-grid, .ponds-grid, .readings-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .staff-grid, .ponds-grid, .readings-grid {
                grid-template-columns: 1fr;
            }
            #map { height: 300px; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="logo-area">
        <i class="fas fa-fish logo"></i>
        <span style="font-weight: 600;">Pond Manager System</span>
        <span class="location-badge">
            <i class="fas fa-map-marker-alt"></i> Manolo Fortich
        </span>
    </div>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="user-badge">
            <i class="fas fa-user-tie"></i>
            <span><?php echo htmlspecialchars($manager_name); ?></span>
        </div>
        <a href="../auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<div class="dashboard-container">
    
    <!-- Time Bar -->
    <div class="time-bar">
        <div class="time-display">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-calendar-alt" style="color: #3b82f6;"></i>
                <span><?php echo $current_date; ?></span>
                <span class="ph-time-badge">
                    <i class="fas fa-clock"></i> <?php echo $current_time_12hr; ?>
                </span>
            </div>
        </div>
        <div class="live-indicator" id="simulationToggle" onclick="toggleSimulation()">
            <span class="live-dot running" id="simulationDot"></span>
            <span id="simulationStatus">Simulation Running</span>
        </div>
    </div>

    <!-- Staff Section -->
    <div class="section-title">
        <i class="fas fa-users"></i>
        <h2>Staff Assignments</h2>
    </div>
    
    <div class="staff-grid">
        <?php foreach($staff_assignments as $staff): ?>
        <div class="staff-card" onclick="highlightPond('<?php echo $staff['assigned_pond']; ?>')">
            <div class="staff-avatar" style="background: <?php echo $staff['avatar_color']; ?>;">
                <?php 
                    $initials = '';
                    $names = explode(' ', $staff['full_name']);
                    foreach($names as $n) {
                        $initials .= substr($n, 0, 1);
                    }
                    echo $initials;
                ?>
            </div>
            <div style="font-size: 1.1rem; font-weight: 600;"><?php echo $staff['full_name']; ?></div>
            <div style="background: rgba(59,130,246,0.2); color: #3b82f6; padding: 0.2rem 0.8rem; border-radius: 50px; display: inline-block; margin: 0.5rem 0;">
                <i class="fas fa-map-marker-alt"></i> Pond <?php echo $staff['assigned_pond']; ?>
            </div>
            <div style="display: flex; align-items: center; gap: 0.3rem; color: #4ade80;">
                <i class="fas fa-circle"></i> Active
                <span style="margin-left: auto; color: rgba(255,255,255,0.4); font-size: 0.7rem;">
                    <i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($staff['last_login'])); ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Ponds Section -->
    <div class="section-title">
        <i class="fas fa-water"></i>
        <h2>Pond Status</h2>
    </div>
    
    <div class="ponds-grid">
        <?php foreach($ponds_data as $pond_name => $data): ?>
        <div class="pond-card" onclick="highlightPond('<?php echo $pond_name; ?>')" id="pond-<?php echo $pond_name; ?>">
            <div class="pond-header">
                <div class="pond-name">
                    <span class="status-indicator" style="background: <?php 
                        echo $data['status'] == 'safe' ? '#4ade80' : 
                            ($data['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                    ?>;"></span>
                    Pond <?php echo $pond_name; ?>
                </div>
                <span style="color: <?php 
                    echo $data['status'] == 'safe' ? '#4ade80' : 
                        ($data['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                ?>; font-weight: 600;">
                    <i class="fas fa-<?php 
                        echo $data['status'] == 'safe' ? 'check-circle' : 
                            ($data['status'] == 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); 
                    ?>"></i> <?php echo ucfirst($data['status']); ?>
                </span>
            </div>
            
            <div style="background: rgba(255,255,255,0.03); padding: 0.5rem; border-radius: 10px; margin-bottom: 1rem;">
                <i class="fas fa-user"></i> <?php echo $data['staff']; ?> • 
                <i class="fas fa-map-pin"></i> <?php echo $data['location']; ?>
            </div>
            
            <div class="pond-metrics">
                <div class="metric-box">
                    <i class="fas fa-seedling" style="color: #4ade80;"></i>
                    <div class="metric-value" id="organic-<?php echo $pond_name; ?>"><?php echo $data['organic_level']; ?></div>
                    <small>mg/L</small>
                </div>
                <div class="metric-box">
                    <i class="fas fa-thermometer-half" style="color: #fbbf24;"></i>
                    <div class="metric-value" id="temp-<?php echo $pond_name; ?>"><?php echo $data['temperature']; ?>°</div>
                    <small>°C</small>
                </div>
                <div class="metric-box">
                    <i class="fas fa-flask" style="color: #a78bfa;"></i>
                    <div class="metric-value" id="ph-<?php echo $pond_name; ?>"><?php echo $data['ph']; ?></div>
                    <small>pH</small>
                </div>
            </div>
            
            <div style="margin-top: 1rem; font-size: 0.75rem; color: rgba(255,255,255,0.4);">
                <i class="far fa-clock"></i> <span id="time-<?php echo $pond_name; ?>"><?php echo date('h:i:s A', strtotime($data['last_reading'])); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Map Section -->
    <div class="map-container">
        <div class="map-header">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-map-marked-alt" style="color: #3b82f6;"></i>
                <h3>Pond Locations - Manolo Fortich</h3>
            </div>
            <div class="map-legend">
                <div class="legend-item"><div class="legend-color" style="background: #4ade80;"></div><span>Safe</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #fbbf24;"></div><span>Warning</span></div>
                <div class="legend-item"><div class="legend-color" style="background: #ef4444;"></div><span>Critical</span></div>
            </div>
        </div>
        <div id="map"></div>
    </div>

    <!-- Chart Section -->
    <div class="chart-container">
        <div class="chart-header">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-chart-line" style="color: #3b82f6;"></i>
                <h3>Live Metrics Trends</h3>
            </div>
            <div class="report-buttons">
                <button class="report-btn active" onclick="updateChart('daily', this)">Daily</button>
                <button class="report-btn" onclick="updateChart('weekly', this)">Weekly</button>
                <button class="report-btn" onclick="updateChart('monthly', this)">Monthly</button>
            </div>
        </div>
        <div class="chart-wrapper">
            <canvas id="metricsChart"></canvas>
        </div>
    </div>

    <!-- Recent Readings -->
    <div class="section-title">
        <i class="fas fa-history"></i>
        <h2>Recent Readings</h2>
    </div>
    
    <div class="readings-grid" id="recentReadings">
        <?php foreach($recent_readings as $reading): ?>
        <div class="reading-card" onclick="highlightPond('<?php echo $reading['pond_name']; ?>')">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <strong><i class="fas fa-map-marker-alt"></i> Pond <?php echo $reading['pond_name']; ?></strong>
                <span style="color: <?php 
                    echo $reading['status'] == 'safe' ? '#4ade80' : 
                        ($reading['status'] == 'warning' ? '#fbbf24' : '#ef4444'); 
                ?>;">
                    <i class="fas fa-<?php 
                        echo $reading['status'] == 'safe' ? 'check-circle' : 
                            ($reading['status'] == 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); 
                    ?>"></i> <?php echo ucfirst($reading['status']); ?>
                </span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin: 1rem 0;">
                <div><small>Organic</small><br><strong style="color: #4ade80;"><?php echo $reading['organic_level']; ?></strong></div>
                <div><small>Temp</small><br><strong style="color: #fbbf24;"><?php echo $reading['water_temperature']; ?>°C</strong></div>
                <div><small>pH</small><br><strong style="color: #a78bfa;"><?php echo $reading['ph_level']; ?></strong></div>
            </div>
            <div style="font-size: 0.7rem; color: rgba(255,255,255,0.4);">
                <i class="far fa-clock"></i> <?php echo date('h:i:s A', strtotime($reading['detected_at'])); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Notifications -->
    <div class="notifications-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-bell" style="color: #ef4444;"></i>
                <h3>Notifications & Alerts</h3>
            </div>
            <button class="notify-btn" onclick="notifyAllAdmin()">
                <i class="fas fa-bell"></i> Notify All
            </button>
        </div>
        <div id="notificationsList">
            <?php foreach($notifications as $notification): ?>
            <div class="alert-item <?php echo $notification['type']; ?>" id="notification-<?php echo $notification['notification_id']; ?>">
                <i class="fas fa-<?php 
                    echo $notification['type'] == 'critical' ? 'exclamation-circle' : 
                        ($notification['type'] == 'warning' ? 'exclamation-triangle' : 'info-circle'); 
                ?>"></i>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <strong>Pond <?php echo $notification['pond_name']; ?></strong>
                        <?php if($notification['status'] == 'unread'): ?>
                        <span style="background: #ef4444; padding: 0.2rem 0.5rem; border-radius: 50px; font-size: 0.7rem;">NEW</span>
                        <?php endif; ?>
                    </div>
                    <p style="font-size: 0.9rem;"><?php echo $notification['message']; ?></p>
                    <small style="color: rgba(255,255,255,0.4);"><?php echo date('h:i A', strtotime($notification['created_at'])); ?></small>
                </div>
                <button class="notify-btn small" onclick="notifyAdmin(<?php echo $notification['notification_id']; ?>)">
                    Notify
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <i class="fas fa-map-marker-alt"></i> Manolo Fortich, Bukidnon • 
        <span>PH Time: <span id="footerTimestamp"><?php echo date('h:i:s A'); ?></span></span>
    </div>
</div>

<script>
// ============================================
// GLOBAL VARIABLES
// ============================================
let map, chart;
let polygons = {};
let simulationInterval;
let isSimulationRunning = true;
let clickMarkers = [];

// PHP data to JavaScript
const pondCoordinates = <?php echo json_encode($pond_coordinates); ?>;
const pondsData = <?php echo json_encode($ponds_data); ?>;

// Chart data from PHP
const initialChartData = {
    labels: <?php echo json_encode($chart_data['labels']); ?>,
    organic: <?php echo json_encode($chart_data['organic']); ?>,
    temperature: <?php echo json_encode($chart_data['temperature']); ?>,
    ph: <?php echo json_encode($chart_data['ph']); ?>
};

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initChart();
    startTimeUpdates();
    startSimulation();
});

// ============================================
// MAP FUNCTIONS WITH CLICK ICON
// ============================================
function initMap() {
    map = L.map('map').setView([8.3695, 124.8650], 15);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // Add click event to map
    map.on('click', function(e) {
        addClickIcon(e.latlng);
    });

    // Add polygons for each pond
    Object.keys(pondCoordinates).forEach(pondName => {
        const pond = pondCoordinates[pondName];
        const data = pondsData[pondName];
        
        let color = data.status === 'safe' ? '#4ade80' : 
                   (data.status === 'warning' ? '#fbbf24' : '#ef4444');
        
        const polygon = L.polygon(pond.bounds, {
            color: color,
            weight: 3,
            fillColor: color,
            fillOpacity: 0.3
        }).addTo(map);
        
        polygon.on('mouseover', function() { this.setStyle({ fillOpacity: 0.6 }); });
        polygon.on('mouseout', function() { this.setStyle({ fillOpacity: 0.3 }); });
        polygon.on('click', function(e) {
            L.DomEvent.stopPropagation(e);
            highlightPond(pondName);
        });
        
        const marker = L.marker(pond.center, {
            icon: L.divIcon({
                html: `<div style="background: ${color}; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 15px ${color};"></div>`,
                iconSize: [16, 16]
            })
        }).addTo(map);
        
        const popupContent = `
            <div style="min-width: 200px;">
                <h4 style="margin-bottom: 10px;">Pond ${pondName}</h4>
                <div><span style="color: ${color};">●</span> ${data.status.toUpperCase()}</div>
                <div>👤 Staff: ${data.staff}</div>
                <div>🌿 Organic: ${data.organic_level} mg/L</div>
                <div>🌡️ Temp: ${data.temperature}°C</div>
                <div>💧 pH: ${data.ph}</div>
            </div>
        `;
        
        polygon.bindPopup(popupContent);
        marker.bindPopup(popupContent);
        polygons[pondName] = polygon;
    });
}

function addClickIcon(latlng) {
    const clickIcon = L.divIcon({
        html: `
            <div style="
                position: relative;
                animation: dropIcon 0.3s ease;
            ">
                <div style="
                    position: absolute;
                    top: -15px;
                    left: -15px;
                    width: 30px;
                    height: 30px;
                    background: rgba(59, 130, 246, 0.3);
                    border-radius: 50%;
                    animation: pulseRing 2s infinite;
                "></div>
                <div style="
                    background: #3b82f6;
                    width: 24px;
                    height: 24px;
                    border-radius: 50%;
                    border: 3px solid white;
                    box-shadow: 0 0 20px #3b82f6;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 12px;
                    transform: translate(-12px, -12px);
                ">
                    <i class="fas fa-map-pin"></i>
                </div>
                <div style="
                    position: absolute;
                    top: -40px;
                    left: -50px;
                    background: #142138;
                    color: white;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 11px;
                    white-space: nowrap;
                    border: 1px solid #3b82f6;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
                    pointer-events: none;
                    opacity: 0;
                    animation: fadeIn 0.3s ease forwards 0.2s;
                ">
                    <i class="fas fa-clock"></i> ${new Date().toLocaleTimeString()}
                </div>
            </div>
        `,
        className: 'click-marker',
        iconSize: [24, 24],
        iconAnchor: [0, 0]
    });
    
    const clickMarker = L.marker(latlng, { 
        icon: clickIcon,
        zIndexOffset: 1000
    }).addTo(map);
    
    clickMarkers.push(clickMarker);
    
    setTimeout(() => {
        map.removeLayer(clickMarker);
        clickMarkers = clickMarkers.filter(m => m !== clickMarker);
    }, 3000);
}

function highlightPond(pondName) {
    if (polygons[pondName]) {
        map.setView(pondCoordinates[pondName].center, 18);
        polygons[pondName].openPopup();
    }
}

// ============================================
// CHART FUNCTIONS
// ============================================
function initChart() {
    const ctx = document.getElementById('metricsChart').getContext('2d');
    
    const gradientOrganic = ctx.createLinearGradient(0, 0, 0, 350);
    gradientOrganic.addColorStop(0, 'rgba(239, 68, 68, 0.3)');
    gradientOrganic.addColorStop(1, 'rgba(239, 68, 68, 0.0)');
    
    const gradientTemp = ctx.createLinearGradient(0, 0, 0, 350);
    gradientTemp.addColorStop(0, 'rgba(251, 191, 36, 0.3)');
    gradientTemp.addColorStop(1, 'rgba(251, 191, 36, 0.0)');
    
    const gradientPH = ctx.createLinearGradient(0, 0, 0, 350);
    gradientPH.addColorStop(0, 'rgba(74, 222, 128, 0.3)');
    gradientPH.addColorStop(1, 'rgba(74, 222, 128, 0.0)');
    
    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: initialChartData.labels,
            datasets: [
                {
                    label: 'Organic (mg/L)',
                    data: initialChartData.organic,
                    borderColor: '#ef4444',
                    backgroundColor: gradientOrganic,
                    borderWidth: 2,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#fff',
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Temperature (°C)',
                    data: initialChartData.temperature,
                    borderColor: '#fbbf24',
                    backgroundColor: gradientTemp,
                    borderWidth: 2,
                    pointBackgroundColor: '#fbbf24',
                    pointBorderColor: '#fff',
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'pH Level',
                    data: initialChartData.ph,
                    borderColor: '#4ade80',
                    backgroundColor: gradientPH,
                    borderWidth: 2,
                    pointBackgroundColor: '#4ade80',
                    pointBorderColor: '#fff',
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#fff', font: { size: 11 } } },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#142138',
                    titleColor: '#fff',
                    bodyColor: 'rgba(255,255,255,0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1
                }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.1)' }, ticks: { color: '#fff' } },
                y: { grid: { color: 'rgba(255,255,255,0.1)' }, ticks: { color: '#fff' } }
            }
        }
    });
}

function updateChart(period, btn) {
    document.querySelectorAll('.report-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_chart_data&period=' + period
    })
    .then(response => response.json())
    .then(data => {
        chart.data.labels = data.labels;
        chart.data.datasets[0].data = data.organic;
        chart.data.datasets[1].data = data.temperature;
        chart.data.datasets[2].data = data.ph;
        chart.update();
        
        btn.innerHTML = period.charAt(0).toUpperCase() + period.slice(1);
        showNotification(`Chart updated to ${period} view`, 'success');
    });
}

// ============================================
// SIMULATION FUNCTIONS
// ============================================
function startSimulation() {
    isSimulationRunning = true;
    document.getElementById('simulationDot').className = 'live-dot running';
    document.getElementById('simulationStatus').textContent = 'Simulation Running';
    
    simulationInterval = setInterval(() => {
        simulateReadings();
        simulateRandomNotification();
    }, 5000);
}

function stopSimulation() {
    isSimulationRunning = false;
    document.getElementById('simulationDot').className = 'live-dot';
    document.getElementById('simulationStatus').textContent = 'Simulation Paused';
    clearInterval(simulationInterval);
}

function toggleSimulation() {
    if (isSimulationRunning) {
        stopSimulation();
    } else {
        startSimulation();
    }
}

function simulateReadings() {
    const ponds = ['A-1', 'B-2', 'C-1'];
    
    ponds.forEach(pond => {
        const organic = Math.floor(Math.random() * 50) + 35; // 35-85
        const temp = (Math.random() * 8 + 24).toFixed(1); // 24-32
        const ph = (Math.random() * 2.5 + 6.0).toFixed(1); // 6.0-8.5
        
        let status = 'safe';
        let statusColor = '#4ade80';
        let icon = 'check-circle';
        
        if (organic > 75 || temp > 31 || ph > 8.2 || ph < 6.5) {
            status = 'critical';
            statusColor = '#ef4444';
            icon = 'exclamation-circle';
        } else if (organic > 60 || temp > 29 || ph > 7.8 || ph < 6.8) {
            status = 'warning';
            statusColor = '#fbbf24';
            icon = 'exclamation-triangle';
        }
        
        // Update pond card
        const pondCard = document.getElementById(`pond-${pond}`);
        if (pondCard) {
            const statusElement = pondCard.querySelector('.pond-header span:last-child');
            const indicator = pondCard.querySelector('.status-indicator');
            const organicEl = document.getElementById(`organic-${pond}`);
            const tempEl = document.getElementById(`temp-${pond}`);
            const phEl = document.getElementById(`ph-${pond}`);
            const timeEl = document.getElementById(`time-${pond}`);
            
            if (statusElement) {
                statusElement.innerHTML = `<i class="fas fa-${icon}"></i> ${status.toUpperCase()}`;
                statusElement.style.color = statusColor;
            }
            if (indicator) indicator.style.background = statusColor;
            if (organicEl) organicEl.textContent = organic;
            if (tempEl) tempEl.textContent = temp + '°';
            if (phEl) phEl.textContent = ph;
            if (timeEl) {
                const now = new Date();
                timeEl.textContent = now.toLocaleTimeString('en-US', { hour12: true });
            }
        }
        
        // Update map polygon
        if (polygons[pond]) {
            polygons[pond].setStyle({
                color: statusColor,
                fillColor: statusColor
            });
        }
    });
    
    // Update recent readings
    simulateRecentReadings();
}

function simulateRecentReadings() {
    const readingCards = document.querySelectorAll('.reading-card');
    const statuses = ['safe', 'warning', 'critical'];
    const icons = {
        safe: 'check-circle',
        warning: 'exclamation-triangle',
        critical: 'exclamation-circle'
    };
    const colors = {
        safe: '#4ade80',
        warning: '#fbbf24',
        critical: '#ef4444'
    };
    
    readingCards.forEach(card => {
        const randomStatus = statuses[Math.floor(Math.random() * statuses.length)];
        const organic = Math.floor(Math.random() * 50) + 35;
        const temp = (Math.random() * 8 + 24).toFixed(1);
        const ph = (Math.random() * 2.5 + 6.0).toFixed(1);
        
        const statusSpan = card.querySelector('span[style*="color"]');
        const organicStrong = card.querySelectorAll('strong')[0];
        const tempStrong = card.querySelectorAll('strong')[1];
        const phStrong = card.querySelectorAll('strong')[2];
        const timeDiv = card.querySelector('.reading-card > div:last-child');
        
        if (statusSpan) {
            statusSpan.innerHTML = `<i class="fas fa-${icons[randomStatus]}"></i> ${randomStatus.toUpperCase()}`;
            statusSpan.style.color = colors[randomStatus];
        }
        if (organicStrong) organicStrong.textContent = organic;
        if (tempStrong) tempStrong.textContent = temp + '°C';
        if (phStrong) phStrong.textContent = ph;
        if (timeDiv) {
            const now = new Date();
            timeDiv.innerHTML = `<i class="far fa-clock"></i> ${now.toLocaleTimeString('en-US', { hour12: true })}`;
        }
    });
}

function simulateRandomNotification() {
    const pondNames = ['A-1', 'B-2', 'C-1'];
    const randomPond = pondNames[Math.floor(Math.random() * pondNames.length)];
    const organic = Math.floor(Math.random() * 30) + 65;
    const temp = (Math.random() * 5 + 28).toFixed(1);
    
    const types = ['critical', 'warning', 'info'];
    const randomType = types[Math.floor(Math.random() * types.length)];
    
    let message = '';
    let icon = '';
    
    if (randomType === 'critical') {
        message = `HIGH ALERT: Pond ${randomPond} - Organic level ${organic}% Critical! Temperature ${temp}°C`;
        icon = 'exclamation-circle';
    } else if (randomType === 'warning') {
        message = `WARNING: Pond ${randomPond} - Organic level ${organic}% approaching threshold`;
        icon = 'exclamation-triangle';
    } else {
        message = `INFO: Pond ${randomPond} - Routine check recommended`;
        icon = 'info-circle';
    }
    
    const notificationDiv = document.createElement('div');
    notificationDiv.className = `alert-item ${randomType}`;
    notificationDiv.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <strong>Pond ${randomPond}</strong>
                <span style="background: #ef4444; padding: 0.2rem 0.5rem; border-radius: 50px; font-size: 0.7rem; color: white;">NEW</span>
            </div>
            <p style="font-size: 0.9rem;">${message}</p>
            <small style="color: rgba(255,255,255,0.4);">Just now</small>
        </div>
        <button class="notify-btn small" onclick="notifyAdminSimulation(this, '${randomPond}')">
            Notify
        </button>
    `;
    
    const notificationsList = document.getElementById('notificationsList');
    notificationsList.insertBefore(notificationDiv, notificationsList.firstChild);
    
    if (notificationsList.children.length > 5) {
        notificationsList.removeChild(notificationsList.lastChild);
    }
    
    showNotification(message, randomType);
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function notifyAdmin(id) {
    const btn = event.currentTarget;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    setTimeout(() => {
        btn.innerHTML = '<i class="fas fa-check"></i> Notified';
        btn.style.background = '#4ade80';
        
        setTimeout(() => {
            btn.innerHTML = 'Notify';
            btn.style.background = '';
            btn.disabled = false;
        }, 2000);
    }, 1000);
}

function notifyAdminSimulation(btn, pondName) {
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    setTimeout(() => {
        btn.innerHTML = '<i class="fas fa-check"></i> Notified';
        btn.style.background = '#4ade80';
        
        showNotification(`Admin notified for Pond ${pondName}`, 'success');
        
        setTimeout(() => {
            btn.innerHTML = 'Notify';
            btn.style.background = '';
            btn.disabled = false;
        }, 2000);
    }, 1000);
}

function notifyAllAdmin() {
    showNotification('All admins notified successfully', 'success');
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed; top: 80px; right: 20px; 
        background: ${type === 'success' ? '#4ade80' : type === 'warning' ? '#fbbf24' : type === 'critical' ? '#ef4444' : '#3b82f6'};
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
        max-width: 350px;
    `;
    
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    if (type === 'critical') icon = 'exclamation-circle';
    
    notification.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <div style="flex: 1;">${message}</div>
        <small style="opacity: 0.7;">${new Date().toLocaleTimeString()}</small>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

function startTimeUpdates() {
    setInterval(() => {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { 
            timeZone: 'Asia/Manila',
            hour12: true,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        document.getElementById('footerTimestamp').textContent = timeStr;
    }, 1000);
}

// Handle window resize
window.addEventListener('resize', function() {
    if (map) setTimeout(() => map.invalidateSize(), 100);
    if (chart) chart.resize();
});

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes dropIcon {
        0% { transform: translateY(-20px); opacity: 0; }
        100% { transform: translateY(0); opacity: 1; }
    }
    @keyframes pulseRing {
        0% { transform: scale(0.8); opacity: 1; }
        50% { transform: scale(1.5); opacity: 0.5; }
        100% { transform: scale(0.8); opacity: 1; }
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .click-marker { cursor: pointer; transition: all 0.3s ease; }
    .click-marker:hover { transform: scale(1.2); z-index: 1001 !important; }
`;
document.head.appendChild(style);
</script>

</body>
</html>