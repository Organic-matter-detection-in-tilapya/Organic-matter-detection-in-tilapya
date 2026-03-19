<?php
// test_all_users.php - Complete User Password Tester
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>🔐 Complete User Password Test</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #fff; padding: 20px; }
        .success { color: #4ade80; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        .info { color: #3b82f6; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #2a2a2a; }
        .hash { font-size: 0.8em; word-break: break-all; }
    </style>
</head>
<body>";

echo "<h1>🔐 Complete User Password Test</h1>";

// Test local database connection
echo "<h2>📡 Database Connection Test</h2>";

$connection_attempts = [
    'Local (XAMPP)' => ['host' => 'localhost', 'user' => 'root', 'pass' => ''],
    'Live Credentials' => ['host' => 'localhost', 'user' => 'u442411629_dev_fishpond', 'pass' => '7Dv9L:2-n1=C']
];

$successful_connections = [];

foreach ($connection_attempts as $name => $creds) {
    try {
        $pdo = new PDO(
            "mysql:host={$creds['host']};dbname=u442411629_fishpond;charset=utf8mb4",
            $creds['user'],
            $creds['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "<div class='success'>✅ $name: CONNECTED</div>";
        $successful_connections[] = ['name' => $name, 'pdo' => $pdo, 'creds' => $creds];
    } catch(Exception $e) {
        echo "<div class='error'>❌ $name: " . $e->getMessage() . "</div>";
    }
}

if (empty($successful_connections)) {
    die("<div class='error'>❌ No database connection available!</div>");
}

// Use the first successful connection
$conn = $successful_connections[0];
echo "<h2>✅ Using connection: " . $conn['name'] . "</h2>";

// Get all users
$stmt = $conn['pdo']->query("SELECT user_id, full_name, email, role, password FROM users ORDER BY role, full_name");
$users = $stmt->fetchAll();

echo "<h2>📋 All Users in Database</h2>";
echo "<table>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Password Hash</th><th>Status</th></tr>";

// Test passwords for each user
$test_passwords = [
    'admin' => 'admin123',
    'manager' => 'manager123',
    'staff' => 'staff123'
];

foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . $user['user_id'] . "</td>";
    echo "<td>" . $user['full_name'] . "</td>";
    echo "<td>" . $user['email'] . "</td>";
    echo "<td><strong>" . $user['role'] . "</strong></td>";
    echo "<td class='hash'>" . substr($user['password'], 0, 30) . "...</td>";
    
    // Determine expected password based on role
    $expected_pass = null;
    if (strpos($user['email'], 'admin') !== false || $user['role'] == 'admin') {
        $expected_pass = 'admin123';
    } elseif (strpos($user['email'], 'manager') !== false || $user['role'] == 'manager') {
        $expected_pass = 'manager123';
    } elseif (strpos($user['email'], 'staff') !== false || $user['role'] == 'staff') {
        $expected_pass = 'staff123';
    }
    
    if ($expected_pass) {
        $verify_result = password_verify($expected_pass, $user['password']);
        if ($verify_result) {
            echo "<td class='success'>✅ CORRECT</td>";
        } else {
            echo "<td class='error'>❌ INVALID (tried: $expected_pass)</td>";
        }
    } else {
        echo "<td class='warning'>⚠️ No test password</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Detailed analysis for each user
echo "<h2>🔍 Detailed Analysis</h2>";

foreach ($users as $user) {
    echo "<div style='margin: 20px 0; padding: 15px; background: #2a2a2a; border-radius: 5px;'>";
    echo "<h3>👤 " . $user['full_name'] . " (" . $user['role'] . ")</h3>";
    
    $hash = $user['password'];
    
    // Check 1: Hash format
    echo "<h4>Hash Analysis:</h4>";
    echo "Length: " . strlen($hash) . " characters<br>";
    echo "Starts with: " . substr($hash, 0, 7) . "<br>";
    
    if (strlen($hash) == 60 && (substr($hash, 0, 4) == '$2y$' || substr($hash, 0, 4) == '$2a$')) {
        echo "<span class='success'>✅ Valid bcrypt hash format</span><br>";
    } else {
        echo "<span class='error'>❌ Invalid hash format!</span><br>";
    }
    
    // Check 2: Password verification for role-based password
    $test_pass = '';
    if ($user['role'] == 'admin') $test_pass = 'admin123';
    if ($user['role'] == 'manager') $test_pass = 'manager123';
    if ($user['role'] == 'staff') $test_pass = 'staff123';
    
    if ($test_pass) {
        echo "<h4>Password Test (trying: '$test_pass'):</h4>";
        if (password_verify($test_pass, $hash)) {
            echo "<span class='success'>✅ CORRECT! Password matches '$test_pass'</span><br>";
        } else {
            echo "<span class='error'>❌ DOES NOT MATCH '$test_pass'</span><br>";
            
            // Try common variations
            $variations = [
                $test_pass,
                trim($test_pass),
                strtolower($test_pass),
                ucfirst($test_pass),
                $test_pass . ' ',
                ' ' . $test_pass
            ];
            
            foreach ($variations as $var_pass) {
                if (password_verify($var_pass, $hash)) {
                    echo "<span class='warning'>⚠️ But matches variation: '$var_pass'</span><br>";
                    break;
                }
            }
        }
    }
    
    // Check 3: Try rehash
    if ($test_pass) {
        $new_hash = password_hash($test_pass, PASSWORD_DEFAULT);
        echo "<h4>Rehash Test:</h4>";
        echo "New hash would be: " . substr($new_hash, 0, 40) . "...<br>";
        echo "New hash verification: " . (password_verify($test_pass, $new_hash) ? '✅ Works' : '❌ Failed') . "<br>";
    }
    
    // Check 4: SQL to reset if needed
    echo "<h4>🔄 Reset SQL (if needed):</h4>";
    $new_reset_hash = password_hash($test_pass, PASSWORD_DEFAULT);
    echo "<code style='background: #333; padding: 10px; display: block; margin: 10px 0;'>";
    echo "UPDATE users SET password = '$new_reset_hash' WHERE email = '" . $user['email'] . "';";
    echo "</code>";
    
    echo "</div>";
}

// Summary
echo "<h2>📊 Summary</h2>";
$working_users = 0;
foreach ($users as $user) {
    $test_pass = '';
    if ($user['role'] == 'admin') $test_pass = 'admin123';
    if ($user['role'] == 'manager') $test_pass = 'manager123';
    if ($user['role'] == 'staff') $test_pass = 'staff123';
    
    if ($test_pass && password_verify($test_pass, $user['password'])) {
        $working_users++;
    }
}

echo "<p>Total users: " . count($users) . "<br>";
echo "Working with default passwords: $working_users<br>";

if ($working_users < 3) {
    echo "<div class='warning'>⚠️ Some users have incorrect passwords. Use the SQL reset commands above to fix them.</div>";
}

echo "<h2>🚀 Quick Fix All Users</h2>";
echo "<p>Run these SQL commands in phpMyAdmin to reset ALL users to default passwords:</p>";

echo "<pre style='background: #333; padding: 15px;'>";
// Admin
$admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
echo "UPDATE users SET password = '$admin_hash' WHERE role = 'admin' AND email LIKE '%admin%';\n";
// Manager
$manager_hash = password_hash('manager123', PASSWORD_DEFAULT);
echo "UPDATE users SET password = '$manager_hash' WHERE role = 'manager' AND email LIKE '%manager%';\n";
// Staff
$staff_hash = password_hash('staff123', PASSWORD_DEFAULT);
echo "UPDATE users SET password = '$staff_hash' WHERE role = 'staff' AND email = 'staff1@company.com';\n";
echo "</pre>";

echo "</body></html>";
?>