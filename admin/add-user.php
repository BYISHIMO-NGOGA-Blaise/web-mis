<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $roleId = intval($_POST['role_id'] ?? 4);
        $password = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $errors = [];
        
        if (empty($firstName)) $errors[] = "First name is required.";
        if (empty($lastName)) $errors[] = "Last name is required.";
        if (empty($email)) $errors[] = "Email is required.";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
        if (empty($password)) $errors[] = "Password is required.";
        elseif (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
        
        // Check if email exists
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        if ($checkEmail->get_result()->num_rows > 0) {
            $errors[] = "Email already exists.";
        }
        $checkEmail->close();
        
        if (empty($errors)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, role_id, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssii", $email, $passwordHash, $firstName, $lastName, $roleId, $isActive);
            
            if ($stmt->execute()) {
                $success = "User created successfully!";
            } else {
                $error = "Failed to create user.";
            }
            $stmt->close();
        } else {
            $error = implode(" ", $errors);
        }
    }
}

$roles = $conn->query("SELECT * FROM roles ORDER BY id");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('users'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Add New User</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Create New User</h3>
                        <a href="users.php" class="btn btn-primary" style="padding: 8px 20px;"> Back to Users</a>
                    </div>
                    
                    <div style="padding: 30px;">
                        <?php if ($success): ?>
                            <div class="alert alert-success"> <?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"> <?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" style="max-width: 500px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Email Address *</label>
                                <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Password *</label>
                                <input type="password" name="password" class="form-control" required minlength="8">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Role *</label>
                                <select name="role_id" class="form-control" required>
                                    <?php while ($role = $roles->fetch_assoc()): ?>
                                        <option value="<?php echo $role['id']; ?>">
                                            <?php echo htmlspecialchars(ucfirst($role['name'])); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" name="is_active" checked> 
                                    <strong>Active User</strong>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                                 Create User
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
