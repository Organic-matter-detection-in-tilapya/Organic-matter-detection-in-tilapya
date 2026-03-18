<?php
session_start();
require_once '../config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering - para sure walang output before headers
ob_start();


$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        try {
            // Get user by email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['assigned_pond'] = $user['assigned_pond'];
                    
                    // Update last login
                    $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $update->execute([$user['user_id']]);
                    
                    // Log activity
                    try {
                        $log = $pdo->prepare("INSERT INTO activities (user_id, action, ip_address, created_at) VALUES (?, 'login', ?, NOW())");
                        $log->execute([$user['user_id'], $_SERVER['REMOTE_ADDR']]);
                    } catch(Exception $e) {
                        // Skip if activities table doesn't exist
                    }
                    
                    // Clear output buffer before redirect
                    ob_end_clean();
                    
                    // Redirect based on role - use absolute path
                    $base_url = "http://localhost/matter-detection-in-tilapya";
                    
                    switch($user['role']) {
                        case 'admin':
                            header("Location: $base_url/admin/admin_dashboard.php");
                            break;
                        case 'manager':
                            header("Location: $base_url/manager/manager_dashboard.php");
                            break;
                        case 'staff':
                            header("Location: $base_url/staff/staff_dashboard.php");
                            break;
                        default:
                            header("Location: $base_url/auth/login.php?error=invalid_role");
                    }
                    exit();
                } else {
                    $error = "Invalid password";
                }
            } else {
                $error = "Email not found";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Organic Tilapia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #142138 0%, #0d1729 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(59,130,246,0.1) 0%, transparent 50%);
            animation: rotate 30s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .login-container {
            background: rgba(20, 33, 56, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo i {
            font-size: 3.5rem;
            color: #3b82f6;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 10px 8px rgba(59,130,246,0.2));
        }
        
        .logo h2 {
            color: white;
            margin-top: 1rem;
            font-size: 1.8rem;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .logo p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.9rem 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(59, 130, 246, 0.3);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .login-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .login-btn:hover::after {
            width: 300px;
            height: 300px;
        }
        
        .error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 0.8rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .demo-credentials {
            margin-top: 2rem;
            padding: 1.2rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .demo-credentials p {
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .demo-credentials span {
            color: #3b82f6;
            font-weight: 500;
            background: rgba(59, 130, 246, 0.1);
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .demo-credentials i {
            margin-right: 0.5rem;
            color: #3b82f6;
            font-size: 0.8rem;
        }
        
        .demo-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .demo-title i {
            color: #3b82f6;
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-fish"></i>
            <h2>Organic Tilapia</h2>
            <p>Matter Detection System</p>
        </div>
        
        <?php if($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label>
                    <i class="fas fa-envelope" style="margin-right: 0.5rem; color: #3b82f6;"></i>
                    Email Address
                </label>
                <input type="email" name="email" required 
                       placeholder="Enter your email"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>
                    <i class="fas fa-lock" style="margin-right: 0.5rem; color: #3b82f6;"></i>
                    Password
                </label>
                <input type="password" name="password" required 
                       placeholder="Enter your password">
            </div>
            
            <button type="submit" class="login-btn" id="loginBtn">
                <span>Login</span>
                <i class="fas fa-arrow-right" style="margin-left: 0.5rem;"></i>
            </button>
        </form>
        
        <div class="demo-credentials">
            <div class="demo-title">
                <i class="fas fa-info-circle"></i>
                <span>Demo Credentials</span>
            </div>
            <p>
                <span><i class="fas fa-user-shield"></i> Admin</span>
                <span>admin@company.com / admin123</span>
            </p>
            <p>
                <span><i class="fas fa-user-tie"></i> Manager</span>
                <span>manager@company.com / manager123</span>
            </p>
            <p>
                <span><i class="fas fa-user"></i> Staff</span>
                <span>staff1@company.com / staff123</span>
            </p>
        </div>
    </div>

    <script>
        // Add loading animation on form submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<span>Logging in...</span><span class="loading"></span>';
            btn.disabled = true;
        });
    </script>
</body>
</html>