<?php
session_start();
require_once "../staff/staff_dashboard.php";


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){
    http_response_code(403);
    exit("Unauthorized");
}

$pond = $_SESSION['assigned_pond'] ?? 'B-2';

$sql = "SELECT organic_mg_l, temperature_c, ph_level 
        FROM user_ponds 
        WHERE pond_name='$pond' 
        ORDER BY detected_at DESC 
        LIMIT 1";

$result = $conn->query($sql);

$data = [];

if($result && $row = $result->fetch_assoc()){
    $data['organic'] = floatval($row['organic_mg_l']);
    $data['temp']    = floatval($row['temperature_c']);
    $data['ph']      = floatval($row['ph_level']);
} else {
    // fallback simulation
    $data['organic'] = rand(50,250)/10;
    $data['temp']    = rand(260,310)/10;
    $data['ph']      = rand(65,85)/10;
}

echo json_encode($data);
$conn->close();