<?php
session_start();
require_once '../config/config.php';

// Ensure logged in staff
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){
    header("Location: ../auth/login.php");
    exit();
}

// staff_dashboard.php
$full_name     = $_SESSION['full_name'] ?? 'John Staff';
$assigned_pond = $_SESSION['assigned_pond'] ?? 'A-1';
$pond          = $assigned_pond;
$email         = $_SESSION['email'] ?? 'staff@example.com';
$last_login    = $_SESSION['last_login'] ?? date('Y-m-d H:i:s');

// Set Philippines Time Zone
date_default_timezone_set('Asia/Manila');
$current_time = date('h:i:s A');
$current_date = date('F j, Y');

// SIMPLE SIMULATION DATA - 14 days
$dates = [];
$organic = [];
$temp = [];
$ph = [];

for($i=13; $i>=0; $i--){
    $dates[] = date("M d", strtotime("-".$i." days"));
    
    // Create realistic data with some variations
    $organic[] = round(15 + rand(-80, 80)/10, 1); // 7-23 range
    $temp[] = round(28 + rand(-20, 30)/10, 1);    // 26-31 range
    $ph[] = round(7.2 + rand(-8, 8)/10, 1);       // 6.4-8.0 range
}

// Current readings (last values)
$current_organic = end($organic);
$current_temp = end($temp);
$current_ph = end($ph);

// Determine if safe
$is_safe = ($current_organic <= 25 && $current_temp <= 31 && $current_ph >= 6.5 && $current_ph <= 8.5);
$status_color = $is_safe ? 'safe' : 'unsafe';
$status_text = $is_safe ? 'SAFE' : 'UNSAFE';

// Manolo Fortich Coordinates
$manolo_fortich = [
    'center' => [8.3695, 124.8645],
    'ponds' => [
        'A-1' => [
            'name' => 'Tilapia Pond A-1',
            'center' => [8.3695, 124.8645],
            'bounds' => [
                [8.3692, 124.8642],
                [8.3698, 124.8640],
                [8.3700, 124.8648],
                [8.3696, 124.8650],
                [8.3692, 124.8642]
            ]
        ],
        'B-2' => [
            'name' => 'Tilapia Pond B-2',
            'center' => [8.3688, 124.8652],
            'bounds' => [
                [8.3685, 124.8649],
                [8.3690, 124.8647],
                [8.3693, 124.8654],
                [8.3688, 124.8656],
                [8.3685, 124.8649]
            ]
        ]
    ]
];

// Use coordinates based on pond
$current_location = $manolo_fortich['ponds'][$pond] ?? $manolo_fortich['ponds']['A-1'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pond <?php echo $pond; ?> Dashboard - Manolo Fortich</title>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        /* EXACT COLORS from manager dashboard */
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

        .location-badge {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
            display: flex;
            align-items: center;
            gap: 0.4rem;
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

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
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

        /* Status Badge */
        .status-badge {
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-safe {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }

        .badge-unsafe {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            animation: pulseGlow 2s infinite;
        }

        @keyframes pulseGlow {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        /* List Group */
        .list-group-item {
            background: transparent;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #e0e0e0;
            padding: 1rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .list-group-item:last-child {
            border-bottom: none;
        }

        .list-group-item span:last-child {
            background: #1e2f47;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Metrics Grid */
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

        /* Map Container */
        .map-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .map-header {
            background: #1e2f47;
            padding: 10px 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #3b82f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .location-highlight {
            color: #3b82f6;
            font-weight: 600;
        }

        #pondMap {
            height: 450px;
            width: 100%;
            border-radius: 16px;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.1);
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

        .legend-color.safe { background: #4ade80; }
        .legend-color.unsafe { background: #ef4444; }

        /* Chart Container */
        .chart-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Summary Cards */
        .summary-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.07);
            border-color: #3b82f6;
        }

        .summary-card h6 {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.8rem;
        }

        .summary-card .value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #fff;
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

        /* Footer */
        .footer {
            background: rgba(13, 23, 41, 0.98);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 2rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }
            
            #pondMap {
                height: 350px;
            }
            
            .time-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Leaflet Custom Theme */
        .leaflet-container {
            background: #1e2935;
        }

        .leaflet-popup-content-wrapper {
            background: #142138;
            color: #fff;
            border-radius: 16px;
            border-left: 4px solid #3b82f6;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .leaflet-popup-tip {
            background: #142138;
        }

        .leaflet-control-zoom a {
            background: #1e2f47 !important;
            color: #fff !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }

        .leaflet-control-zoom a:hover {
            background: #2a3f5e !important;
            color: #3b82f6 !important;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="logo-area">
        <i class="fas fa-water logo"></i>
        <span style="font-weight: 600;">Pond Monitoring System</span>
        <span class="location-badge">
            <i class="fas fa-map-marker-alt"></i> Manolo Fortich
        </span>
    </div>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="user-badge">
            <i class="fas fa-user"></i>
            <span><?php echo htmlspecialchars($full_name); ?></span>
        </div>
        <a href="../auth/logout.php" class="logout-btn">
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
                    <i class="fas fa-clock"></i> <?php echo $current_time; ?>
                </span>
            </div>
            <div style="color: rgba(255,255,255,0.5);">
                <i class="fas fa-map-marker-alt"></i> Pond <?php echo $pond; ?> - Manolo Fortich
            </div>
        </div>
        <div class="live-indicator">
            <span class="live-dot"></span>
            <span>Live Monitoring</span>
        </div>
    </div>

    <!-- First Row: Status Cards -->
    <div class="grid-2">
        <!-- Pond Status Card -->
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-water" style="color: #3b82f6;"></i> Pond <?php echo $pond; ?> Status</span>
                <span class="status-badge <?php echo $is_safe ? 'badge-safe' : 'badge-unsafe'; ?>" id="statusBadge">
                    <i class="fas fa-circle"></i> <?php echo $status_text; ?>
                </span>
            </div>
            
            <div class="metrics-grid">
                <div class="metric-item">
                    <i class="fas fa-seedling metric-icon organic"></i>
                    <div style="font-size: 1.5rem; font-weight: 600;" id="currentOrg"><?php echo $current_organic; ?></div>
                    <small style="color: rgba(255,255,255,0.5);">mg/L</small>
                </div>
                <div class="metric-item">
                    <i class="fas fa-thermometer-half metric-icon temp"></i>
                    <div style="font-size: 1.5rem; font-weight: 600;" id="currentTemp"><?php echo $current_temp; ?></div>
                    <small style="color: rgba(255,255,255,0.5);">°C</small>
                </div>
                <div class="metric-item">
                    <i class="fas fa-flask metric-icon ph"></i>
                    <div style="font-size: 1.5rem; font-weight: 600;" id="currentPH"><?php echo $current_ph; ?></div>
                    <small style="color: rgba(255,255,255,0.5);">pH</small>
                </div>
            </div>
            
            <div style="margin-top: 1rem; font-size: 0.85rem; color: rgba(255,255,255,0.4);">
                <i class="far fa-clock"></i> Last updated: <span id="updateTime"><?php echo $current_time; ?></span>
            </div>
        </div>

        <!-- Staff Info Card -->
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-user-tie" style="color: #3b82f6;"></i> Staff Information</span>
            </div>
            <div style="padding: 0.5rem 0;">
                <div class="list-group-item">
                    <span><i class="fas fa-user"></i> Name</span>
                    <span><?php echo htmlspecialchars($full_name); ?></span>
                </div>
                <div class="list-group-item">
                    <span><i class="fas fa-envelope"></i> Email</span>
                    <span><?php echo htmlspecialchars($email); ?></span>
                </div>
                <div class="list-group-item">
                    <span><i class="fas fa-clock"></i> Last Login</span>
                    <span><?php echo $last_login; ?></span>
                </div>
                <div class="list-group-item">
                    <span><i class="fas fa-map-pin"></i> Assignment</span>
                    <span>Pond <?php echo $pond; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Section - Manolo Fortich -->
    <div class="map-container">
        <div class="map-header">
            <div>
                <i class="fas fa-map-marker-alt" style="color: #3b82f6;"></i>
                <span class="location-highlight"> Pond <?php echo $pond; ?> - <?php echo $current_location['name']; ?></span>
            </div>
            <div class="map-legend">
                <div class="legend-item">
                    <div class="legend-color safe"></div>
                    <span>Safe</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color unsafe"></div>
                    <span>Unsafe</span>
                </div>
            </div>
        </div>
        <div id="pondMap"></div>
        <div style="margin-top: 0.8rem; text-align: right;">
            <small style="color: rgba(255,255,255,0.4);">
                <i class="fas fa-map-pin"></i> Manolo Fortich, Bukidnon
            </small>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid-3">
        <div class="summary-card">
            <i class="fas fa-seedling" style="color: #4ade80;"></i>
            <h6>Average Organic</h6>
            <div class="value" id="avgOrg"><?php echo round(array_sum($organic)/count($organic), 1); ?></div>
            <small style="color: rgba(255,255,255,0.4);">mg/L (14 days)</small>
        </div>
        <div class="summary-card">
            <i class="fas fa-thermometer-half" style="color: #fbbf24;"></i>
            <h6>Average Temperature</h6>
            <div class="value" id="avgTemp"><?php echo round(array_sum($temp)/count($temp), 1); ?></div>
            <small style="color: rgba(255,255,255,0.4);">°C (14 days)</small>
        </div>
        <div class="summary-card">
            <i class="fas fa-flask" style="color: #a78bfa;"></i>
            <h6>Average pH</h6>
            <div class="value" id="avgPH"><?php echo round(array_sum($ph)/count($ph), 1); ?></div>
            <small style="color: rgba(255,255,255,0.4);">(14 days)</small>
        </div>
    </div>

    <!-- Chart Section - FIXED -->
    <div class="chart-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <span><i class="fas fa-chart-line" style="color: #3b82f6;"></i> 14-Day Water Quality Trends</span>
            <span class="status-badge badge-safe">
                <i class="fas fa-sync-alt fa-spin"></i> Live Updates
            </span>
        </div>
        <div style="height: 350px; width: 100%;">
            <canvas id="qualityChart"></canvas>
        </div>
    </div>

</div>

<!-- Footer -->
<div class="footer">
    <i class="fas fa-water"></i> Pond Monitoring System | Manolo Fortich, Bukidnon
    <br>
    <small>
        <i class="fas fa-clock"></i> PH Time: <span id="footerTime"><?php echo $current_time; ?></span>
    </small>
</div>

<script>
// ============================================
// MANOLO FORTICH DATA
// ============================================
const pondName = '<?php echo $pond; ?>';
const pondData = {
    organic: <?php echo json_encode($organic); ?>,
    temp: <?php echo json_encode($temp); ?>,
    ph: <?php echo json_encode($ph); ?>,
    dates: <?php echo json_encode($dates); ?>,
    center: <?php echo json_encode($current_location['center']); ?>,
    bounds: <?php echo json_encode($current_location['bounds']); ?>,
    locationName: '<?php echo $current_location['name']; ?>'
};

// Current values
let currentOrg = <?php echo $current_organic; ?>;
let currentTemp = <?php echo $current_temp; ?>;
let currentPH = <?php echo $current_ph; ?>;

// ============================================
// INITIALIZE CHART - FIXED
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('qualityChart').getContext('2d');
    
    // Create gradient backgrounds
    const gradientOrganic = ctx.createLinearGradient(0, 0, 0, 350);
    gradientOrganic.addColorStop(0, 'rgba(74, 222, 128, 0.3)');
    gradientOrganic.addColorStop(1, 'rgba(74, 222, 128, 0.0)');
    
    const gradientTemp = ctx.createLinearGradient(0, 0, 0, 350);
    gradientTemp.addColorStop(0, 'rgba(251, 191, 36, 0.3)');
    gradientTemp.addColorStop(1, 'rgba(251, 191, 36, 0.0)');
    
    const gradientPH = ctx.createLinearGradient(0, 0, 0, 350);
    gradientPH.addColorStop(0, 'rgba(167, 139, 250, 0.3)');
    gradientPH.addColorStop(1, 'rgba(167, 139, 250, 0.0)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: pondData.dates,
            datasets: [
                {
                    label: 'Organic (mg/L)',
                    data: pondData.organic,
                    borderColor: '#4ade80',
                    backgroundColor: gradientOrganic,
                    borderWidth: 2,
                    pointBackgroundColor: '#4ade80',
                    pointBorderColor: '#fff',
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Temperature (°C)',
                    data: pondData.temp,
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
                    data: pondData.ph,
                    borderColor: '#a78bfa',
                    backgroundColor: gradientPH,
                    borderWidth: 2,
                    pointBackgroundColor: '#a78bfa',
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
                legend: {
                    labels: {
                        color: '#fff',
                        font: { size: 11 },
                        boxWidth: 12,
                        padding: 10
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#142138',
                    titleColor: '#fff',
                    bodyColor: 'rgba(255,255,255,0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    padding: 10
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(255,255,255,0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#fff'
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(255,255,255,0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#fff'
                    },
                    beginAtZero: false
                }
            },
            elements: {
                line: {
                    tension: 0.3
                }
            }
        }
    });
});

// ============================================
// INITIALIZE MAP
// ============================================
let map, polygon, marker;

function initMap() {
    // Create map centered on pond
    map = L.map('pondMap').setView(pondData.center, 18);
    
    // Add dark tile layer
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    
    // Function to get color based on status
    function getStatusColor() {
        return (currentOrg <= 25 && currentTemp <= 31 && currentPH >= 6.5 && currentPH <= 8.5) 
            ? '#4ade80' : '#ef4444';
    }
    
    // Add pond polygon
    polygon = L.polygon(pondData.bounds, {
        color: getStatusColor(),
        weight: 3,
        opacity: 1,
        fillColor: getStatusColor(),
        fillOpacity: 0.3
    }).addTo(map);
    
    // Add hover effect
    polygon.on('mouseover', function() {
        this.setStyle({ fillOpacity: 0.6, weight: 4 });
    });
    
    polygon.on('mouseout', function() {
        this.setStyle({ fillOpacity: 0.3, weight: 3 });
    });
    
    // Add sensor marker
    const markerIcon = L.divIcon({
        html: `<div style="
            background-color: ${getStatusColor()};
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 15px ${getStatusColor()};
        "></div>`,
        className: 'sensor-marker',
        iconSize: [16, 16]
    });
    
    marker = L.marker(pondData.center, { icon: markerIcon }).addTo(map);
    
    // Create popup content
    function getPopupContent() {
        const isSafe = (currentOrg <= 25 && currentTemp <= 31 && currentPH >= 6.5 && currentPH <= 8.5);
        const status = isSafe ? 'SAFE' : 'UNSAFE';
        const statusColor = isSafe ? '#4ade80' : '#ef4444';
        
        return `
            <div style="min-width: 200px;">
                <h4 style="margin: 0 0 10px 0; color: #fff; font-size: 1rem; border-bottom: 1px solid #333; padding-bottom: 5px;">
                    <i class="fas fa-map-marker-alt" style="color: #3b82f6;"></i> Pond ${pondName}
                </h4>
                
                <div style="margin-bottom: 10px;">
                    <span style="background: ${statusColor}20; color: ${statusColor}; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem;">
                        ${status}
                    </span>
                </div>
                
                <div style="font-size: 0.85rem;">
                    <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                        <span>🌿 Organic:</span>
                        <span style="color: ${currentOrg <= 25 ? '#4ade80' : '#ef4444'}">${currentOrg.toFixed(1)} mg/L</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                        <span>🌡️ Temperature:</span>
                        <span style="color: ${currentTemp <= 31 ? '#4ade80' : '#ef4444'}">${currentTemp.toFixed(1)} °C</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                        <span>💧 pH Level:</span>
                        <span style="color: ${(currentPH >= 6.5 && currentPH <= 8.5) ? '#4ade80' : '#ef4444'}">${currentPH.toFixed(1)}</span>
                    </div>
                </div>
                
                <div style="margin-top: 8px; font-size: 0.7rem; color: #3b82f6;">
                    <i class="fas fa-clock"></i> ${new Date().toLocaleTimeString()}
                </div>
            </div>
        `;
    }
    
    // Bind popups
    polygon.bindPopup(getPopupContent());
    marker.bindPopup(getPopupContent());
    
    // Store update function globally
    window.updateMapStatus = function() {
        const newColor = (currentOrg <= 25 && currentTemp <= 31 && currentPH >= 6.5 && currentPH <= 8.5) 
            ? '#4ade80' : '#ef4444';
        
        polygon.setStyle({
            color: newColor,
            fillColor: newColor
        });
        
        marker.setIcon(L.divIcon({
            html: `<div style="
                background-color: ${newColor};
                width: 16px;
                height: 16px;
                border-radius: 50%;
                border: 3px solid white;
                box-shadow: 0 0 15px ${newColor};
            "></div>`,
            className: 'sensor-marker',
            iconSize: [16, 16]
        }));
        
        polygon.setPopupContent(getPopupContent());
        marker.setPopupContent(getPopupContent());
    };
}

// Initialize map when page loads
window.addEventListener('load', function() {
    initMap();
});

// ============================================
// REAL-TIME SIMULATION
// ============================================
function updateReadings() {
    // Simulate new readings
    currentOrg = Math.random() < 0.2 ? 26 + Math.random() * 8 : 15 + Math.random() * 10;
    currentTemp = Math.random() < 0.2 ? 32 + Math.random() * 2 : 26 + Math.random() * 5;
    currentPH = Math.random() < 0.2 ? 
        (Math.random() < 0.5 ? 6 + Math.random() * 0.4 : 8.5 + Math.random() * 0.4) : 
        6.8 + Math.random() * 1.4;
    
    // Update display
    document.getElementById('currentOrg').textContent = currentOrg.toFixed(1);
    document.getElementById('currentTemp').textContent = currentTemp.toFixed(1);
    document.getElementById('currentPH').textContent = currentPH.toFixed(1);
    
    // Update time
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { 
        hour12: true,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    document.getElementById('updateTime').textContent = timeStr;
    document.getElementById('footerTime').textContent = timeStr;
    
    // Update status
    const isSafe = (currentOrg <= 25 && currentTemp <= 31 && currentPH >= 6.5 && currentPH <= 8.5);
    const statusBadge = document.getElementById('statusBadge');
    statusBadge.innerHTML = `<i class="fas fa-circle"></i> ${isSafe ? 'SAFE' : 'UNSAFE'}`;
    statusBadge.className = `status-badge ${isSafe ? 'badge-safe' : 'badge-unsafe'}`;
    
    // Update map if initialized
    if (window.updateMapStatus) {
        window.updateMapStatus();
    }
    
    // Update summary averages
    document.getElementById('avgOrg').textContent = (15 + Math.random() * 8).toFixed(1);
    document.getElementById('avgTemp').textContent = (27 + Math.random() * 3).toFixed(1);
    document.getElementById('avgPH').textContent = (7.0 + Math.random() * 0.8).toFixed(1);
}

// Run updates every 5 seconds
setInterval(updateReadings, 5000);

// Handle window resize
window.addEventListener('resize', function() {
    if (map) {
        setTimeout(() => map.invalidateSize(), 100);
    }
});
</script>

</body>
</html>