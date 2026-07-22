<?php
require_once __DIR__ . '/../includes/config.php';
$conn = getDBConnection();

echo "Running add_admin_flag migration...\n";

// Check if column already exists
$check = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_admin'");
if ($check->num_rows > 0) {
    echo "Column is_admin already exists.\n";
} else {
    echo "Adding is_admin column to users table...\n";
    $conn->query("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
    echo "Column is_admin added.\n";
}

$conn->close();
echo "Migration complete.\n";
