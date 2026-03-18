<?php
session_start();
require_once '../config/config.php';

// Ensure logged in staff
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){
    header("Location: ../auth/login.php");
    exit();
}

// staff_dashboard.php
$full_name     = $_SESSION['full_name'] ?? 'N/A';
$assigned_pond = $_SESSION['assigned_pond'] ?? 'N/A';
$pond          = $assigned_pond; // use this in queries
$email         = $_SESSION['email'] ?? 'N/A';
$last_login    = $_SESSION['last_login'] ?? 'N/A';

// --- Fetch readings from database using $pdo ---
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

$dates = [];
$organic = [];
$temp = [];
$ph = [];

if($result && count($result) > 0){
    foreach($result as $row){
        $dates[] = date("M d", strtotime($row['sample_date']));
        $organic[] = floatval($row['organic_mg_l']);
        $temp[] = floatval($row['temperature_c']);
        $ph[] = floatval($row['ph_level']);
    }
} else { 
    // Simulation fallback
    for($i=0;$i<14;$i++){
        $dates[] = date("M d", strtotime("-".(13-$i)." days"));

        if($pond == "A-1"){ 
            // Organic random safe value
            $organic_val = rand(50,250)/10; // 5.0 – 25.0
            if(rand(1,10) == 1){
                $organic_val = rand(260,350)/10; // 26 – 35 mg/L → high
            }
            $organic[] = $organic_val;

            // Temperature
            $temp_val = rand(260,310)/10; // 26 – 31 °C
            if(rand(1,10) == 1){ $temp_val = rand(315,330)/10; }
            $temp[] = $temp_val;

            // pH
            $ph_val = rand(65,85)/10; // 6.5 – 8.5
            if(rand(1,20) == 1){ $ph_val = rand(60,90)/10; }
            $ph[] = $ph_val;

        } else { 
            // other ponds
            $organic_val = rand(60,280)/10;
            if(rand(1,10) == 1){ $organic_val = rand(290,350)/10; }
            $organic[] = $organic_val;

            $temp_val = rand(270,320)/10;
            if(rand(1,10) == 1){ $temp_val = rand(325,340)/10; }
            $temp[] = $temp_val;

            $ph_val = rand(70,85)/10;
            if(rand(1,20) == 1){ $ph_val = rand(60,90)/10; }
            $ph[] = $ph_val;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">      
<title>Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.2.0/countUp.min.js"></script>

<style>
/* --- Global Styles --- */
body { background:#121920; font-family:'Segoe UI', sans-serif; margin:0; padding:0; color:#eee;}
.navbar { background:#1F2A38; }
.navbar-brand { color:#fff; font-weight:700; font-size:1.3rem; }
.navbar-brand:hover { color:#1ABC9C; }

.dashboard-header { margin:25px 0; text-align:center; }
.dashboard-header h2 { font-weight:700; color:#fff; }
.dashboard-header p { color:#aaa; font-size:0.95rem; }

/* --- Cards --- */
.card {
    border-radius:20px; 
    box-shadow:0 15px 40px rgba(0,0,0,0.5);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    background: linear-gradient(145deg, #1F2A38, #28364E);
    color:#eee;
}
.card:hover { transform: translateY(-5px); box-shadow:0 20px 60px rgba(0,0,0,0.6); }
.card-title { font-weight:600; font-size:1.2rem; }
.badge-status { font-size:0.85rem; padding:0.45em 0.7em; transition: all 0.5s ease; }
.list-group-item { font-size:0.95rem; background:transparent; border:none; color:#eee; }
.list-group-item span.fw-bold { font-weight:700; color:#fff; }
.bi-person-circle { color:#1ABC9C; font-size:1.6rem; }

/* --- Summary Cards --- */
.summary-cards { display:flex; gap:1rem; justify-content:center; margin-top:25px; flex-wrap:wrap; }
.summary-card { flex:1 1 150px; padding:20px; border-radius:15px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.5); background:#1F2A38; transition: transform 0.3s ease; }
.summary-card:hover { transform: translateY(-3px); }
.summary-card h5 { font-weight:600; margin-bottom:10px; color:#aaa; }
.summary-card p { font-size:1.5rem; font-weight:700; margin:0; }

/* --- Chart Card --- */
.chart-card { background:#1F2A38; padding:25px; border-radius:20px; box-shadow:0 15px 40px rgba(0,0,0,0.5); margin-top:25px; }

/* --- Pulsing Badge --- */
.pulse {
    animation: pulse 2s infinite;
    box-shadow: 0 0 15px rgba(255,0,0,0.5);
}
@keyframes pulse {
    0% { transform: scale(1); box-shadow:0 0 10px rgba(255,0,0,0.5); }
    50% { transform: scale(1.1); box-shadow:0 0 25px rgba(255,0,0,0.8); }
    100% { transform: scale(1); box-shadow:0 0 10px rgba(255,0,0,0.5); }
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Organic-Matter Detection in Tilapia</a>

    <!-- Right-aligned Logout button -->
    <div class="d-flex ms-auto">
        <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
    </div>
  </div>
</nav>

<!-- Header -->
<div class="dashboard-header">
    <h2>Pond <?php echo $pond; ?> Live Dashboard</h2>
    <p>Enterprise-level water monitoring system</p>
</div>

<div class="container">
<div class="row g-4">

    <!-- Pond Status Card -->
    <div class="col-md-6">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title">Pond <?php echo $assigned_pond; ?> Status</h5>
                <span class="badge bg-success badge-status" id="badge-status">Safe</span>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Organic (mg/L)
                    <span class="fw-bold" id="status-org"><?php echo end($organic); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Temperature (°C)
                    <span class="fw-bold" id="status-temp"><?php echo end($temp); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    pH Level
                    <span class="fw-bold" id="status-ph"><?php echo end($ph); ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Staff Info Card -->
    <div class="col-md-6">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title">Staff Info</h5>
                <i class="bi bi-person-circle"></i>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Name
                    <span class="fw-bold"><?php echo $full_name; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Email
                    <span class="fw-bold"><?php echo $email; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Last Login
                    <span class="fw-bold"><?php echo $last_login; ?></span>
                </li>
            </ul>
        </div>
    </div>

</div>

<!-- Trends Chart -->
<div class="chart-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="card-title">Pond <?php echo $assigned_pond; ?> Trends</h4>
        <span class="badge bg-primary">Live Data</span>
    </div>
    <canvas id="pondChart"></canvas>
</div>

<!-- Summary Cards with Dynamic Icons -->
<div class="summary-cards">
    <div class="summary-card">
        <h5><i class="bi bi-leaf me-2" id="icon-org"></i>Organic (mg/L)</h5>
        <p id="sum-org"><?php echo round(array_sum($organic)/count($organic),1); ?></p>
    </div>
    <div class="summary-card">
        <h5><i class="bi bi-thermometer-half me-2" id="icon-temp"></i>Temperature (°C)</h5>
        <p id="sum-temp"><?php echo round(array_sum($temp)/count($temp),1); ?></p>
    </div>
    <div class="summary-card">
        <h5><i class="bi bi-droplet-half me-2" id="icon-ph"></i>pH Level</h5>
        <p id="sum-ph"><?php echo round(array_sum($ph)/count($ph),1); ?></p>
    </div>
</div>

<script>
// Grab last values
const orgVal = <?php echo end($organic); ?>;
const tempVal = <?php echo end($temp); ?>;
const phVal = <?php echo end($ph); ?>;

// Grab icon elements
const iconOrg = document.getElementById('icon-org');
const iconTemp = document.getElementById('icon-temp');
const iconPH = document.getElementById('icon-ph');

// Set colors based on safe/unsafe
iconOrg.style.color = (orgVal <= 100) ? 'limegreen' : 'red';
iconTemp.style.color = (tempVal <= 32) ? 'limegreen' : 'red';
iconPH.style.color = (phVal >= 6.5 && phVal <= 8.5) ? 'limegreen' : 'red';

// --- Chart.js Multi-Axis Gradient Chart ---
const ctx = document.getElementById('pondChart').getContext('2d');

// Create gradient for Organic line
const gradientOrganic = ctx.createLinearGradient(0,0,0,350);
gradientOrganic.addColorStop(0,'rgba(30,144,255,0.5)');
gradientOrganic.addColorStop(1,'rgba(30,144,255,0.05)');

const pondChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [
            {
                label: 'Organic (mg/L)',
                data: <?php echo json_encode($organic); ?>,
                borderColor: '#1E90FF',
                backgroundColor: gradientOrganic,
                yAxisID: 'yOrganic',
                tension: 0.4,
                fill: true,
                pointRadius:5,
                borderWidth:3
            },
            {
                label: 'Temperature (°C)',
                data: <?php echo json_encode($temp); ?>,
                borderColor: '#FFA500',
                borderDash:[5,5],
                backgroundColor: 'rgba(255,165,0,0.1)',
                yAxisID: 'yTemp',
                tension:0.3,
                fill:false,
                pointRadius:0,
                borderWidth:2
            },
            {
                label: 'pH Level',
                data: <?php echo json_encode($ph); ?>,
                borderColor: '#32CD32',
                borderDash:[5,5],
                backgroundColor: 'rgba(50,205,50,0.1)',
                yAxisID: 'yPH',
                tension:0.3,
                fill:false,
                pointRadius:0,
                borderWidth:2
            }
        ]
    },
    options: {
        responsive:true,
        plugins:{
            legend:{ position:'top', labels:{boxWidth:15, padding:15, font:{weight:'600'}} },
            tooltip:{mode:'index', intersect:false}
        },
        interaction:{mode:'index', intersect:false},
        scales:{
            yOrganic: { type:'linear', position:'left', title:{display:true,text:'Organic (mg/L)'} },
            yTemp: { type:'linear', position:'right', title:{display:true,text:'Temperature (°C)'}, grid:{drawOnChartArea:false}, offset:true },
            yPH: { type:'linear', position:'right', title:{display:true,text:'pH Level'}, grid:{drawOnChartArea:false}, offset:true },
            x: { title:{display:true,text:'Date'} }
        }
    }
});

// --- CountUp Animation for Summary ---
const options = { duration: 1.5, separator: ',' };
const orgAnim = new CountUp('sum-org', <?php echo round(array_sum($organic)/count($organic),1); ?>, options);
const tempAnim = new CountUp('sum-temp', <?php echo round(array_sum($temp)/count($temp),1); ?>, options);
const phAnim = new CountUp('sum-ph', <?php echo round(array_sum($ph)/count($ph),1); ?>, options);
orgAnim.start(); tempAnim.start(); phAnim.start();

// Grab elements
const statusOrg  = document.getElementById('status-org');
const statusTemp = document.getElementById('status-temp');
const statusPh   = document.getElementById('status-ph');
const badge      = document.getElementById('badge-status');

// Function to simulate realistic pond readings
function simulateReading(){
    // Organic (5.0 - 25.0 safe, 26-35 high chance small)
    let organic = Math.random() < 0.1 ? (26 + Math.random()*9) : (5 + Math.random()*20);

    // Temperature (26-31 safe, 31-33 spike small chance)
    let temp = Math.random() < 0.1 ? (31 + Math.random()*2) : (26 + Math.random()*5);

    // pH (6.5-8.5 safe, 6.0-6.5 or 8.5-9.0 rare spike)
    let ph = Math.random() < 0.05 ? (Math.random() < 0.5 ? (6 + Math.random()*0.5) : (8.5 + Math.random()*0.5)) : (6.5 + Math.random()*2);

    return { organic, temp, ph };
}

// Update panel with numbers + badge
function updatePanel(){
    const data = simulateReading();

    // Update numbers
    statusOrg.textContent  = data.organic.toFixed(1);
    statusTemp.textContent = data.temp.toFixed(1);
    statusPh.textContent   = data.ph.toFixed(1);

    // Update badge
    if(data.organic > 25 || data.temp > 31 || data.ph < 6.5 || data.ph > 8.5){
        badge.textContent = 'Unsafe';
        badge.classList.remove('bg-success');
        badge.classList.add('bg-danger','pulse');
    } else {
        badge.textContent = 'Safe';
        badge.classList.remove('bg-danger','pulse');
        badge.classList.add('bg-success');
    }
}

// Run every 5 seconds
updatePanel(); // initial load
setInterval(updatePanel, 5000);
</script>

<footer style="
    width: 100%;
    background: rgba(31, 42, 56, 0.85);
    color: #eee;
    text-align: center;
    padding: 12px 0;
    font-size: 0.9rem;
    font-weight: 500;
    box-shadow: 0 -2px 8px rgba(0,0,0,0.4);
    backdrop-filter: blur(3px);
    margin-top: 30px;
">
    2026 &copy; Organic-Matter Detection in Tilapia
</footer>

</body>
</html>