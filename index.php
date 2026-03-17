<?php
// pond_dashboard.php
$conn = new mysqli("localhost", "root", "", "organic_tilapia");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sql = "SELECT DATE(detected_at) AS sample_date, organic_mg_l, temperature_c, ph_level
        FROM user_ponds
        WHERE pond_name = 'A-1'
        ORDER BY detected_at ASC
        LIMIT 14";

$result = $conn->query($sql);

$dates = [];
$organic = [];
$temp = [];
$ph = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dates[] = date("M d", strtotime($row['sample_date']));
        $organic[] = $row['organic_mg_l'];
        $temp[] = $row['temperature_c'];
        $ph[] = $row['ph_level'];
    }
} else {
    for ($i=0;$i<14;$i++){
        $dates[] = date("M d", strtotime("-".(13-$i)." days"));
        $organic[] = 55.0;
        $temp[] = 28.0;
        $ph[] = 7.0;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pond A-1 Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; margin:0; padding:0; }
.container { max-width: 1100px; margin: 0 auto; padding: 20px; }
.dashboard-header { text-align:center; margin-bottom:30px; }
.dashboard-header h1 { font-weight:700; margin-bottom:5px; }
.dashboard-header p { color:#6c757d; font-size:0.95rem; }
.chart-card { background:#fff; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.1); margin-bottom:30px; }
.card-title { font-weight:600; font-size:1.3rem; margin-bottom:15px; }
.summary-cards { display:flex; justify-content:space-between; gap:20px; flex-wrap:wrap; }
.summary-card { background:#fff; flex:1; min-width:150px; padding:20px; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,0.08); text-align:center; }
.summary-card h5 { font-weight:600; margin-bottom:10px; font-size:1rem; color:#555; }
.summary-card p { font-size:1.8rem; font-weight:700; margin:0; }
.text-primary { color:#1E90FF; }
.text-warning { color:#FFA500; }
.text-success { color:#32CD32; }
</style>
</head>
<body>

<div class="container">
    <div class="dashboard-header">
        <h1>Pond A-1 Monitoring Dashboard</h1>
        <p>Last 14 days trends — Organic Matter, Temperature, and pH Level</p>
    </div>

    <!-- Pond A-1 Trends Card -->
    <div class="chart-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h4 class="card-title">Pond A-1 Trends</h4>
            <span style="background:#1E90FF; color:#fff; padding:5px 10px; border-radius:10px; font-size:0.85rem;">Static Data</span>
        </div>
        <canvas id="pondChart"></canvas>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <h5>Organic (mg/L)</h5>
            <p class="text-primary"><?php echo round(array_sum($organic)/count($organic),1); ?></p>
        </div>
        <div class="summary-card">
            <h5>Temperature (°C)</h5>
            <p class="text-warning"><?php echo round(array_sum($temp)/count($temp),1); ?></p>
        </div>
        <div class="summary-card">
            <h5>pH Level</h5>
            <p class="text-success"><?php echo round(array_sum($ph)/count($ph),1); ?></p>
        </div>
    </div>
</div>

<script>
// Static chart
const ctx = document.getElementById('pondChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [
            {
                label: 'Organic (mg/L)',
                data: <?php echo json_encode($organic); ?>,
                borderColor: '#1E90FF',
                backgroundColor: 'rgba(30,144,255,0.2)',
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
</script>

</body>
</html>