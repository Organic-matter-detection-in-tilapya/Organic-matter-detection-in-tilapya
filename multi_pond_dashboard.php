<?php
$conn = new mysqli("localhost","root","","organic_tilapia");
if($conn->connect_error) die("Connection failed: ".$conn->connect_error);

// Last 14 readings for Pond A-1
$sql = "SELECT DATE(detected_at) AS sample_date, organic_mg_l, temperature_c, ph_level
        FROM user_ponds
        WHERE pond_name='A-1'
        ORDER BY detected_at ASC
        LIMIT 14";
$result = $conn->query($sql);

$dates = [];
$organic = [];
$temp = [];
$ph = [];

if($result && $result->num_rows>0){
    while($row = $result->fetch_assoc()){
        $dates[] = date("M d", strtotime($row['sample_date']));
        $organic[] = floatval($row['organic_mg_l']);
        $temp[] = floatval($row['temperature_c']);
        $ph[] = floatval($row['ph_level']);
    }
} else {
    for($i=0;$i<14;$i++){
        $dates[] = date("M d", strtotime("-".(13-$i)." days"));
        $organic[] = 50.0;
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
<title>Pond A-1 Smooth Live Monitor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background:#f0f2f5; font-family:'Segoe UI', sans-serif; }
.dashboard { max-width:750px; margin:40px auto; }
.chart-card { background:#fff; padding:25px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.12); }
.card-title { font-weight:600; font-size:1.3rem; margin-bottom:15px; }
.summary p { margin:0; font-size:0.95rem; }
.badge-live { font-size:0.85rem; }
canvas { width:100% !important; height:400px !important; }
</style>
</head>
<body>

<div class="dashboard">
    <div class="chart-card">
        <h4 class="card-title">Pond A-1 <span id="badge-A1" class="badge bg-success badge-live">Safe</span></h4>
        <canvas id="chart-A1"></canvas>
        <div class="summary mt-3">
            <p>Avg Organic: <strong id="avg-org-A1"><?php echo round(array_sum($organic)/count($organic),1); ?></strong> mg/L</p>
            <p>Avg Temp: <strong id="avg-temp-A1"><?php echo round(array_sum($temp)/count($temp),1); ?></strong> °C</p>
            <p>Avg pH: <strong id="avg-ph-A1"><?php echo round(array_sum($ph)/count($ph),2); ?></strong></p>
        </div>
    </div>
</div>

<script>
let pondA1 = {
    dates: <?php echo json_encode($dates); ?>,
    organic: <?php echo json_encode($organic, JSON_NUMERIC_CHECK); ?>,
    temp: <?php echo json_encode($temp, JSON_NUMERIC_CHECK); ?>,
    ph: <?php echo json_encode($ph, JSON_NUMERIC_CHECK); ?>,
};

// Chart.js with curves
const ctx = document.getElementById('chart-A1').getContext('2d');
const chart = new Chart(ctx, {
    type:'line',
    data:{
        labels: pondA1.dates,
        datasets:[
            { label:'Organic (mg/L)', data: pondA1.organic, borderColor:'blue', backgroundColor:'rgba(54,162,235,0.2)', tension:0.4, fill:true },
            { label:'Temp (°C)', data: pondA1.temp, borderColor:'orange', backgroundColor:'rgba(255,159,64,0.2)', tension:0.4, fill:true },
            { label:'pH', data: pondA1.ph, borderColor:'purple', backgroundColor:'rgba(153,102,255,0.2)', tension:0.4, fill:true }
        ]
    },
    options:{
        responsive:true,
        plugins:{legend:{position:'top'}},
        interaction:{mode:'index',intersect:false},
        animation:{duration:100, easing:'linear'},
        scales:{y:{beginAtZero:false}, x:{title:{display:true,text:'Date'}}}
    }
});

// Smoothest simulation using very small increments
function updateSmoothly(arr, minStep, maxStep){
    let last = arr[arr.length-1];
    let target = last + (Math.random()*(maxStep-minStep)+minStep);
    // interpolate smoothly by a fraction
    return last + (target - last) * 0.2;
}

setInterval(()=>{
    pondA1.organic.push(updateSmoothly(pondA1.organic, -0.5, 0.5));
    pondA1.organic.shift();
    pondA1.temp.push(updateSmoothly(pondA1.temp, -0.1, 0.1));
    pondA1.temp.shift();
    pondA1.ph.push(updateSmoothly(pondA1.ph, -0.02, 0.02));
    pondA1.ph.shift();

    chart.data.datasets[0].data = pondA1.organic;
    chart.data.datasets[1].data = pondA1.temp;
    chart.data.datasets[2].data = pondA1.ph;
    chart.update();

    // update summary
    document.getElementById('avg-org-A1').innerText = (pondA1.organic.reduce((a,b)=>a+b,0)/pondA1.organic.length).toFixed(1);
    document.getElementById('avg-temp-A1').innerText = (pondA1.temp.reduce((a,b)=>a+b,0)/pondA1.temp.length).toFixed(1);
    document.getElementById('avg-ph-A1').innerText = (pondA1.ph.reduce((a,b)=>a+b,0)/pondA1.ph.length).toFixed(2);

    // update badge
    let safe = (pondA1.organic[pondA1.organic.length-1]<=70 &&
                pondA1.temp[pondA1.temp.length-1]>=24 && pondA1.temp[pondA1.temp.length-1]<=32 &&
                pondA1.ph[pondA1.ph.length-1]>=6 && pondA1.ph[pondA1.ph.length-1]<=9);
    let badge = document.getElementById('badge-A1');
    badge.className = 'badge ' + (safe?'bg-success':'bg-danger') + ' badge-live';
    badge.innerText = safe?'Safe':'Alert';

}, 100); // update every 0.1s for ultra-smooth effect
</script>

</body>
</html>