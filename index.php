<?php
require_once 'includes/config.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT u.id, u.email, u.password_hash, u.first_name, u.last_name, r.name as role 
                                FROM users u 
                                JOIN roles r ON u.role_id = r.id 
                                WHERE u.email = ? AND u.is_active = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                
                // Check if lecturer is also assigned as HOD
                $_SESSION['is_hod'] = false;
                $_SESSION['_is_hod_cache'] = false;
                $_SESSION['portal_mode'] = $user['role'];
                if ($user['role'] === 'lecturer') {
                    $hodCheck = $conn->prepare("SELECT id FROM departments WHERE hod_id = ?");
                    $hodCheck->bind_param("i", $user['id']);
                    $hodCheck->execute();
                    $_SESSION['is_hod'] = $hodCheck->get_result()->num_rows > 0;
                    $_SESSION['_is_hod_cache'] = $_SESSION['is_hod'];
                    $hodCheck->close();

                    if ($_SESSION['is_hod']) {
                        $_SESSION['portal_mode'] = 'hod';
                    }
                }
                
                session_regenerate_id(true);
                
                $redirects = [
                    'admin' => 'admin/dashboard.php',
                    'hod' => 'HOD/dashboard.php',
                    'lecturer' => $_SESSION['is_hod'] ? 'HOD/dashboard.php' : 'lecturer/dashboard.php',
                    'student' => 'student/dashboard.php',
                ];
                
                header('Location: ' . ($redirects[$user['role']] ?? 'index.php'));
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-header">
            <h1> <?php echo APP_NAME; ?></h1>
            <p>Login to your account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="login-error"> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
                <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label>Email Address</label>
                <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input class="form-control" type="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block"> Login</button>
        </form>
        
        <div class="login-footer">
            <p>Contact your administrator for login credentials.</p>
            <p style="margin-top: 10px;">Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
    <script src="assets/js/auth.js"></script>
</body>
</html>
