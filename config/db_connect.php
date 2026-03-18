<?php
// config/db_connect.php
$host = 'localhost';
$dbname = 'organic_tilapia'; // <-- ITO ANG DAPAT! Hindi organic_db
$username = 'root'; 
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set timezone to Philippine Time
    $pdo->exec("SET time_zone = '+08:00'");
    
    // For debugging - comment out pag live na
    // echo "✅ Connected to $dbname successfully!";
    
} catch(PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}

return $pdo;
?>