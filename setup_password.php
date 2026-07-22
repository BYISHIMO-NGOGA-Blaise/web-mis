<?php
require_once 'includes/config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: index.php?error=unauthorized');
    exit();
}

// Generate password hash for "Admin@123"
$password = 'Admin@123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2> Password Hash Generator</h2>";
echo "<p><strong>Password:</strong> Admin@123</p>";
echo "<p><strong>Generated Hash:</strong></p>";
echo "<code style='background: #f0f0f0; padding: 10px; display: block; margin: 10px 0;'>{$hash}</code>";

// Now update the database
$conn = getDBConnection();

// Update all default users
$emails = ['admin@school.edu', 'hod@school.edu', 'lecturer@school.edu', 'student@school.edu'];

foreach ($emails as $email) {
    $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $update->bind_param("ss", $hash, $email);
    $update->execute();
    
    if ($update->affected_rows > 0) {
        echo " Updated: {$email}<br>";
    } else {
        echo " Not found: {$email}<br>";
    }
    $update->close();
}

$conn->close();

echo "<br><a href='index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login</a>";
?>
