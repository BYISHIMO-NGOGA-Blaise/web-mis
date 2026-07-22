<?php
require_once __DIR__ . '/../includes/config.php';
$conn = getDBConnection();
$db = DB_NAME;

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

function index_exists($conn, $table, $indexName) {
    $sql = "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $indexName);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

echo "Starting FK migration...\n";

// 1) course_schedules -> courses
if (fk_exists($conn, 'course_schedules', 'course_id')) {
    echo "Foreign key on course_schedules.course_id already exists.\n";
} else {
    // check orphans
    $row = $conn->query("SELECT cs.course_id FROM course_schedules cs LEFT JOIN courses c ON cs.course_id = c.id WHERE c.id IS NULL LIMIT 1");
    if ($row && $row->num_rows > 0) {
        echo "Cannot add FK course_schedules->courses: orphan course_id found. Clean data first.\n";
    } else {
        // add unique index if missing
        if (!index_exists($conn, 'course_schedules', 'uq_course_schedule')) {
            echo "Adding unique index uq_course_schedule...\n";
            $conn->query("ALTER TABLE course_schedules ADD UNIQUE KEY uq_course_schedule (course_id)");
        }
        echo "Adding FK course_schedules.course_id -> courses.id...\n";
        $res = $conn->query("ALTER TABLE course_schedules ADD CONSTRAINT fk_course_schedules_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
        if ($res === TRUE) echo "FK added.\n"; else echo "Failed to add FK: " . $conn->error . "\n";
    }
}

// 2) payments -> users
if (fk_exists($conn, 'payments', 'student_id')) {
    echo "Foreign key on payments.student_id already exists.\n";
} else {
    $row = $conn->query("SELECT p.student_id FROM payments p LEFT JOIN users u ON p.student_id = u.id WHERE u.id IS NULL LIMIT 1");
    if ($row && $row->num_rows > 0) {
        echo "Cannot add FK payments->users: orphan student_id found. Clean data first.\n";
    } else {
        echo "Adding FK payments.student_id -> users.id...\n";
        $res = $conn->query("ALTER TABLE payments ADD CONSTRAINT fk_payments_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
        if ($res === TRUE) echo "FK added.\n"; else echo "Failed to add FK: " . $conn->error . "\n";
    }
}

$conn->close();
echo "Migration complete.\n";
