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

echo "Starting student fields migration...\n";

$fields = [
    ['reg_number', 'VARCHAR(30) DEFAULT NULL'],
    ['date_of_birth', 'DATE DEFAULT NULL'],
    ['gender', "ENUM('Male', 'Female', 'Other') DEFAULT NULL"],
    ['address', 'TEXT DEFAULT NULL'],
    ['national_id', 'VARCHAR(30) DEFAULT NULL'],
    ['emergency_contact', 'VARCHAR(20) DEFAULT NULL'],
    ['emergency_contact_name', 'VARCHAR(100) DEFAULT NULL'],
];

foreach ($fields as [$name, $type]) {
    if (!column_exists($conn, 'users', $name)) {
        echo "Adding column users.$name...\n";
        $res = $conn->query("ALTER TABLE users ADD COLUMN $name $type");
        if ($res === TRUE) echo "  OK.\n"; else echo "  Failed: " . $conn->error . "\n";
    } else {
        echo "Column users.$name already exists. Skipping.\n";
    }
}

echo "Adding unique index on reg_number...\n";
$check = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'unique_reg_number'");
if ($check->num_rows === 0) {
    $res = $conn->query("ALTER TABLE users ADD UNIQUE KEY unique_reg_number (reg_number)");
    if ($res === TRUE) echo "  Index added.\n"; else echo "  Failed: " . $conn->error . "\n";
} else {
    echo "  Index already exists. Skipping.\n";
}

$conn->close();
echo "\nMigration completed.\n";
