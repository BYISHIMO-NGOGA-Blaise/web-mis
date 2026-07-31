<?php
require_once '../includes/config.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get enrolled courses
$scheduleQuery = "
    SELECT c.code, c.name, c.credits, c.semester, d.name as dept_name,
           CONCAT(u.first_name, ' ', u.last_name) as lecturer_name,
           cs.day, TIME_FORMAT(cs.start_time, '%H:%i') as start_time, TIME_FORMAT(cs.end_time, '%H:%i') as end_time, cs.location
    FROM courses c
    JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    LEFT JOIN course_schedules cs ON cs.course_id = c.id
    WHERE ce.student_id = ? AND ce.status = 'enrolled'
    ORDER BY c.code, FIELD(cs.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), cs.start_time
";

$stmt = $conn->prepare($scheduleQuery);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$scheduleRows = $result->fetch_all(MYSQLI_ASSOC);

// CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="schedule_' . $studentId . '.csv"');

    $output = fopen('php://output', 'w');

    // Student info
    $studentInfo = $conn->prepare("SELECT first_name, last_name, email, reg_number FROM users WHERE id = ?");
    $studentInfo->bind_param("i", $studentId);
    $studentInfo->execute();
    $sInfo = $studentInfo->get_result()->fetch_assoc();
    $studentInfo->close();

    $semester = $scheduleRows[0]['semester'] ?? 'N/A';

    fputcsv($output, [APP_NAME . ' - Student Course Schedule']);
    fputcsv($output, ['Student', $sInfo['first_name'] . ' ' . $sInfo['last_name']]);
    fputcsv($output, ['Reg Number', $sInfo['reg_number'] ?? 'N/A']);
    fputcsv($output, ['Semester', $semester]);
    fputcsv($output, ['Academic Year', date('Y')]);
    fputcsv($output, ['Generated', date('F d, Y H:i:s')]);
    fputcsv($output, []);

    // Courses
    $totalCredits = 0;
    $coursesMap = [];
    foreach ($scheduleRows as $r) {
        $code = $r['code'];
        if (!isset($coursesMap[$code])) {
            $coursesMap[$code] = $r;
            $totalCredits += intval($r['credits'] ?? 0);
        }
    }

    fputcsv($output, ['ENROLLED COURSES SUMMARY']);
    fputcsv($output, ['Total Courses', count($coursesMap)]);
    fputcsv($output, ['Total Credits', $totalCredits]);
    fputcsv($output, []);

    fputcsv($output, ['COURSE DETAILS']);
    fputcsv($output, ['Code', 'Course Name', 'Department', 'Lecturer', 'Credits', 'Location']);
    foreach ($coursesMap as $c) {
        fputcsv($output, [
            $c['code'],
            $c['name'],
            $c['dept_name'] ?? 'N/A',
            $c['lecturer_name'] ?? 'N/A',
            $c['credits'] ?? '-',
            $c['location'] ?? 'TBD'
        ]);
    }
    fputcsv($output, []);

    fputcsv($output, ['WEEKLY TIMETABLE']);
    fputcsv($output, ['Day', 'Time', 'Course Code', 'Course Name', 'Location']);
    foreach ($scheduleRows as $r) {
        if (!empty($r['day'])) {
            fputcsv($output, [
                $r['day'],
                ($r['start_time'] ?? '') . ' - ' . ($r['end_time'] ?? ''),
                $r['code'],
                $r['name'],
                $r['location'] ?? 'TBD'
            ]);
        }
    }

    fclose($output);
    $stmt->close();
    $conn->close();
    exit();
}

// Build grid for weekly timetable (Mon-Sun)
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$boundaries = [];
$coursesSet = [];
$starts = [];
$skipMap = [];
foreach ($scheduleRows as $r) {
    if (empty($r['start_time']) || empty($r['end_time'])) continue;
    $boundaries[$r['start_time']] = $r['start_time'];
    $boundaries[$r['end_time']] = $r['end_time'];
    $coursesSet[$r['code']] = true;
}
ksort($boundaries);
$times = array_values($boundaries);
foreach ($scheduleRows as $r) {
    if (empty($r['start_time']) || empty($r['end_time'])) continue;
    $day = $r['day'] ?? 'Monday';
    $startIndex = array_search($r['start_time'], $times, true);
    $endIndex = array_search($r['end_time'], $times, true);
    if ($startIndex === false || $endIndex === false || $endIndex <= $startIndex) continue;
    $rowSpan = $endIndex - $startIndex;
    if (!isset($starts[$day][$r['start_time']])) {
        $starts[$day][$r['start_time']] = [];
    }
    $starts[$day][$r['start_time']][] = array_merge($r, ['rowspan' => $rowSpan]);
    for ($offset = 1; $offset < $rowSpan; $offset++) {
        $skipMap[$day][$times[$startIndex + $offset]] = true;
    }
}
$coursesCount = count($coursesSet);

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Student</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2> Student Portal</h2>
                <p class="school-name"><?php echo APP_NAME; ?></p>
                <p><?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"> Dashboard</a></li>
                <li><a href="courses.php"> My Courses</a></li>
                <li><a href="register-course.php"> Registration</a></li>
                <li><a href="schedule.php" class="active"> My Schedule</a></li>
                <li><a href="grades.php"> Results</a></li>
                <li><a href="financial.php"> Financial Portal</a></li>
                <li><a href="attendance.php"> Attendance</a></li>
                <li><a href="profile.php"> My Profile</a></li>
                <li><a href="../logout.php"> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <nav class="top-nav">
                <h1>My Schedule</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Student</div>
                </div>
            </nav>

            <div class="page-content">
                <div class="card">
                    <div class="card-header schedule-header">
                        <h3> Weekly Schedule</h3>
                        <div class="schedule-actions">
                            <span class="schedule-info-badge"><?php echo $coursesCount; ?> courses</span>
                            <a href="schedule.php?download=csv" class="btn btn-outline schedule-action-btn"> Download CSV</a>
                            <a href="#" onclick="window.print(); return false;" class="btn btn-outline schedule-action-btn"> Print</a>
                        </div>
                    </div>

                    <?php if (!empty($times)): ?>
                    <div class="schedule-body">
                        <div class="schedule-meta">SEMESTER <?php echo htmlspecialchars($scheduleRows[0]['semester'] ?? ''); ?>  <?php echo date('Y'); ?></div>
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th class="schedule-time-col">TIME</th>
                                    <?php foreach (['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY'] as $d): ?>
                                        <th><?php echo $d; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($times as $t): ?>
                                <tr class="schedule-row">
                                    <td class="schedule-time-col"><?php echo date('H:i', strtotime($t)); ?></td>
                                    <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                                        <?php if (!empty($skipMap[$d][$t])): continue; endif; ?>
                                        <td class="schedule-slot" <?php if (!empty($starts[$d][$t])): ?>rowspan="<?php echo max(array_column($starts[$d][$t], 'rowspan')); ?>"<?php endif; ?>>
                                            <?php if (!empty($starts[$d][$t])): 
                                                foreach ($starts[$d][$t] as $idx => $c): 
                                                    $alt = ($idx % 2 == 0);
                                            ?>
                                                <div class="schedule-entry">
                                                    <div class="schedule-badge <?php echo $alt ? 'schedule-badge-alt' : ''; ?>"><?php echo htmlspecialchars($c['code']); ?></div>
                                                    <div class="schedule-entry-text">
                                                        <div class="schedule-entry-title"><?php echo htmlspecialchars($c['name']); ?></div>
                                                        <div class="schedule-entry-subtitle"><?php echo htmlspecialchars(($c['start_time'] ?? '') . ($c['end_time'] ? ' - ' . $c['end_time'] : '')) . '  ' . htmlspecialchars($c['location'] ?? ''); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; else: ?>
                                                <span class="schedule-empty"></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="schedule-empty-message">
                         You are not enrolled in any courses with schedules yet.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

