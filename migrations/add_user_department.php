<?php
require_once __DIR__ . '/../includes/config.php';
$conn = getDBConnection();

function column_exists($conn, $table, $column) {
    $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

function fk_exists($conn, $table, $column) {
    $sql = "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

echo "Starting users.department_id migration...\n";

if (!column_exists($conn, 'users', 'department_id')) {
    echo "Adding column users.department_id...\n";
    $res = $conn->query("ALTER TABLE users ADD COLUMN department_id INT DEFAULT NULL");
    if ($res === TRUE) echo "Column added.\n"; else echo "Failed to add column: " . $conn->error . "\n";
} else {
    echo "Column users.department_id already exists.\n";
}

if (!fk_exists($conn, 'users', 'department_id')) {
    // ensure no orphans: if any non-null department_id refer to missing dept, set to NULL
    $orphan = $conn->query("SELECT u.id FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.department_id IS NOT NULL AND d.id IS NULL LIMIT 1");
    if ($orphan && $orphan->num_rows > 0) {
        echo "Found user(s) with invalid department_id; setting to NULL...\n";
        $conn->query("UPDATE users u LEFT JOIN departments d ON u.department_id = d.id SET u.department_id = NULL WHERE u.department_id IS NOT NULL AND d.id IS NULL");
    }
    echo "Adding FK users.department_id -> departments.id...\n";
    $res = $conn->query("ALTER TABLE users ADD CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL");
    if ($res === TRUE) echo "FK added.\n"; else echo "Failed to add FK: " . $conn->error . "\n";
} else {
    echo "FK on users.department_id already exists.\n";
}

$conn->close();
echo "Migration complete.\n";
