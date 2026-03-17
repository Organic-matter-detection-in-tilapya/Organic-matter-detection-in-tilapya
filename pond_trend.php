<?php
// pond_trend.php

// 1️⃣ PHP: fetch data from MySQL
$conn = new mysqli("localhost", "root", "", "organic_tilapia");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$sql = "SELECT detected_at, organic_mg_l, temperature_c, ph_level 
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
        $dates[] = date("m/d", strtotime($row['detected_at']));
        $organic[] = $row['organic_mg_l'];
        $temp[] = $row['temperature_c'];
        $ph[] = $row['ph_level'];
    }
} else {
    // dummy data if table empty
    for ($i=0;$i<14;$i++){
        $dates[] = date("m/d", strtotime("-".(13-$i)." days"));
        $organic[] = rand(30,70);
        $temp[] = rand(26,30);
        $ph[] = rand(6,8);
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pond A-1 Trend Analysis</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.chart-container { width: 90%; max-width: 900px; margin: 40px auto; }
</style>
</head>
<body>

<h2 style="text-align:center;">Pond A-1 - 14 Day Trend</h2>

<!-- 2️⃣ HTML: Chart container -->
<div class="chart-container">
    <canvas id="pondChart"></canvas>
</div>

<!-- 3️⃣ JS: Chart.js script -->
<script>
const ctx = document.getElementById('pondChart').getContext('2d');
const pondChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [
            { label: 'Organic Matter (mg/L)', data: <?php echo json_encode($organic); ?>, borderColor: 'blue', yAxisID: 'yOrganic', tension:0.3 },
            { label: 'Temperature (°C)', data: <?php echo json_encode($temp); ?>, borderColor: 'orange', yAxisID: 'yTemp', tension:0.3 },
            { label: 'pH Level', data: <?php echo json_encode($ph); ?>, borderColor: 'purple', yAxisID: 'yPH', tension:0.3 }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        stacked: false,
        plugins: { legend: { display: true, position: 'top' } },
        scales: {
            yOrganic: { type: 'linear', position: 'left', title: { display: true, text: 'Organic (mg/L)' } },
            yTemp: { type: 'linear', position: 'right', title: { display: true, text: 'Temperature (°C)' }, grid: { drawOnChartArea: false } },
            yPH: { type: 'linear', position: 'right', title: { display: true, text: 'pH Level' }, grid: { drawOnChartArea: false }, offset: true },
            x: { title: { display: true, text: 'Date' } }
        }
    }
});
</script>

</body>
</html>