<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$host = 'localhost';
$dbname = 'u442411629_fishpond'; 
$username = 'u442411629_dev_fishpond'; 
$password = '7Dv9L:2-n1=C'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set timezone to Philippine Time
    $pdo->exec("SET time_zone = '+08:00'");
    
   
    
} catch(PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}

return $pdo;
?>