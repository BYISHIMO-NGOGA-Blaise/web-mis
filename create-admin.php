<?php
require_once 'includes/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

$conn = getDBConnection();

// Create fresh admin user
$email = 'admin@school.edu';
$password = 'Admin@123';
$firstName = 'Admin';
$lastName = 'User';
$roleId = 1; // Admin role

// Check if admin exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    // Update existing admin
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password_hash = ?, first_name = ?, last_name = ?, role_id = ?, is_active = 1 WHERE email = ?");
    $update->bind_param("ssiis", $passwordHash, $firstName, $lastName, $roleId, $email);
    $update->execute();
    $update->close();
    echo " Admin user <strong>updated</strong> successfully!";
} else {
    // Create new admin
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role_id, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $insert->bind_param("ssssi", $email, $passwordHash, $firstName, $lastName, $roleId);
    $insert->execute();
    $insert->close();
    echo " Admin user <strong>created</strong> successfully!";
}

$check->close();
$conn->close();

echo "<br><br>";
echo "<strong>Login Credentials:</strong><br>";
echo "Email: <strong>admin@school.edu</strong><br>";
echo "Password: <strong>Admin@123</strong><br><br>";
echo "<a href='index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login</a>";
?>
