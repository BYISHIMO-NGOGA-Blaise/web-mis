<?php
require_once '../includes/config.php';
requireRole('hod');

$lecturerId = $_SESSION['user_id'];
$conn = getDBConnection();

// Ensure schedule table exists (create if missing) to avoid fatal errors on fresh DBs
$tblCheck = $conn->query("SHOW TABLES LIKE 'course_schedules'");
if (!$tblCheck || $tblCheck->num_rows == 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS course_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        day VARCHAR(20) DEFAULT NULL,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        location VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$stmt = $conn->prepare("SELECT c.code, c.name, d.name AS dept_name, s.day, s.start_time, s.end_time, s.location
    FROM courses c
    LEFT JOIN course_schedules s ON c.id = s.course_id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE c.lecturer_id = ?
    ORDER BY FIELD(s.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time, c.code");
$stmt->bind_param("i", $lecturerId);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable - HOD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('timetable'); ?>
        <main class="main-content">
            <nav class="top-nav">
                <h1>My Teaching - Timetable</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Head of Department</div>
                </div>
            </nav>
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Weekly Timetable</h3>
                    </div>
                    <?php if ($rows && $rows->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Course</th>
                                <th>Department</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = $rows->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['day'] ?? 'TBD'); ?></td>
                                <td>
                                    <?php if (!empty($r['start_time']) || !empty($r['end_time'])): ?>
                                        <?php echo htmlspecialchars($r['start_time'] ?? ''); ?> - <?php echo htmlspecialchars($r['end_time'] ?? ''); ?>
                                    <?php else: ?>
                                        <em style="color:#718096;">Not scheduled</em>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($r['code']); ?></strong> - <?php echo htmlspecialchars($r['name']); ?></td>
                                <td><?php echo htmlspecialchars($r['dept_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($r['location'] ?? 'TBD'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p style="padding: 30px; color: #718096; text-align: center;"> No scheduled classes found. Contact admin to assign schedule.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
