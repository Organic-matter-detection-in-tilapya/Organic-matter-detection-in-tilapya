<?php
// install.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Installation Script - Organic Tilapia</h2>";

$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect without database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to MySQL<br>";
    
    // Read SQL file
    $sql_file = __DIR__ . '/database/organic.sql';
    
    if(!file_exists($sql_file)) {
        die("❌ SQL file not found at: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Split SQL by delimiter
    $queries = explode(';', $sql);
    
    $count = 0;
    foreach($queries as $query) {
        $query = trim($query);
        if(!empty($query) && !preg_match('/^--/', $query)) { // Skip comments
            try {
                $pdo->exec($query);
                $count++;
                echo "✅ Executed query " . $count . "<br>";
            } catch(PDOException $e) {
                // Skip errors for DROP DATABASE if it doesn't exist
                if(!strpos($e->getMessage(), "Unknown database")) {
                    echo "⚠️ " . $e->getMessage() . "<br>";
                }
            }
        }
    }
    
    echo "<br><strong style='color:green'>✅ Installation complete! Executed $count queries</strong><br>";
    
    // Test connection to new database
    $pdo->exec("USE organic_tilapia");
    
    // Check users
    $result = $pdo->query("SELECT COUNT(*) as total FROM users");
    $users = $result->fetch();
    echo "✅ Users table created with " . $users['total'] . " records<br>";
    
    // Hash passwords for security
    $stmt = $pdo->query("SELECT user_id, password FROM users");
    $updated = 0;
    while($user = $stmt->fetch()) {
        if(strlen($user['password']) < 60) {
            $hashed = password_hash($user['password'], PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update->execute([$hashed, $user['user_id']]);
            $updated++;
        }
    }
    
    if($updated > 0) {
        echo "✅ Hashed $updated passwords for security<br>";
    }
    
    // Check views
    $views = $pdo->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW'");
    echo "✅ Created " . $views->rowCount() . " views<br>";
    
    echo "<br><a href='auth/login.php' style='padding:10px 20px; background:green; color:white; text-decoration:none; border-radius:5px;'>Go to Login Page</a>";
    
} catch(PDOException $e) {
    echo "<strong style='color:red'>❌ Error: " . $e->getMessage() . "</strong><br>";
}
?>