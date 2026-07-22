<?php
require_once '../includes/config.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$conn = getDBConnection();

$courses = $conn->prepare("
    SELECT c.code, c.name, c.credits, d.name as dept_name,
           CONCAT(u.first_name, ' ', u.last_name) as lecturer_name,
           g.total, g.letter_grade
    FROM courses c
    JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    LEFT JOIN grades g ON ce.id = g.enrollment_id
    WHERE ce.student_id = ? AND ce.status = 'enrolled'
    ORDER BY c.code
");
$courses->bind_param("i", $studentId);
$courses->execute();
$courseResult = $courses->get_result();
$enrolledCourses = $courseResult->fetch_all(MYSQLI_ASSOC);
$totalCredits = 0;
foreach ($enrolledCourses as $course) {
    $totalCredits += intval($course['credits']);
}
$totalFee = calculateCoursePrice($totalCredits);
$enrolledCount = count($enrolledCourses);
$courses->close();

$gpaResult = $conn->prepare("
    SELECT AVG(g.total) as avg_grade
    FROM grades g
    JOIN course_enrollments ce ON g.enrollment_id = ce.id
    WHERE ce.student_id = ? AND g.total IS NOT NULL
");
$gpaResult->bind_param("i", $studentId);
$gpaResult->execute();
$gpaResultData = $gpaResult->get_result()->fetch_assoc();
$gpa = $gpaResultData['avg_grade'] ? round($gpaResultData['avg_grade'], 2) : 'N/A';
$gpaResult->close();

$deptStmt = $conn->prepare("SELECT d.name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
$deptStmt->bind_param("i", $studentId);
$deptStmt->execute();
$deptRes = $deptStmt->get_result()->fetch_assoc();
$studentDeptName = $deptRes['name'] ?? 'Unassigned';
$deptStmt->close();

$conn->close();

$greeting = 'Good ' . (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><i class="fas fa-user-graduate"></i></div>
                <h2>Student Portal</h2>
                <p><?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="courses.php"><i class="fas fa-book-open"></i> My Courses</a></li>
                <li><a href="register-course.php"><i class="fas fa-user-plus"></i> Registration</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
                <li><a href="grades.php"><i class="fas fa-star"></i> Results</a></li>
                <li><a href="financial.php"><i class="fas fa-wallet"></i> Financial Portal</a></li>
                <li><a href="attendance.php"><i class="fas fa-clipboard-check"></i> Attendance</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <div class="sidebar-divider"></div>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <nav class="top-nav">
                <h1>Student Dashboard</h1>
                <div class="user-info">
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                        <div class="user-role">Student</div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?></div>
                </div>
            </nav>

            <div class="page-content">
                <div class="welcome-banner">
                    <h2><?php echo $greeting ?>, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
                    <p>Welcome to your student portal. Keep track of your courses, grades, and finances.</p>
                    <div class="welcome-time"><i class="far fa-clock"></i> <span id="current-time"></span></div>
                </div>

                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-building"></i></div>
                        <h4>Department</h4>
                        <div class="metric-value" style="font-size: 1.4rem;"><?php echo htmlspecialchars($studentDeptName); ?></div>
                        <p>Your assigned department</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-book-open"></i></div>
                        <h4>Enrolled Courses</h4>
                        <div class="metric-value" data-counter="<?php echo $enrolledCount; ?>">0</div>
                        <p>This semester</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-graduation-cap"></i></div>
                        <h4>Total Credits</h4>
                        <div class="metric-value" data-counter="<?php echo $totalCredits; ?>">0</div>
                        <p>Currently registered</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-wallet"></i></div>
                        <h4>Total Fees</h4>
                        <div class="metric-value" style="font-size: 1.6rem;"><?php echo number_format($totalFee); ?> FRW</div>
                        <p>Tuition estimate</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-star"></i></div>
                        <h4>Average Grade</h4>
                        <div class="metric-value"><?php echo $gpa; ?></div>
                        <p>Overall average</p>
                    </div>
                </div>

                <div class="chart-row-wide">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Course Grades</h3>
                        </div>
                        <div class="chart-card" style="height: 300px;">
                            <canvas id="gradesChart"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Credits Distribution</h3>
                        </div>
                        <div class="chart-card" style="height: 300px;">
                            <canvas id="creditsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-book-open"></i> My Enrolled Courses</h3>
                    </div>
                    <?php if ($enrolledCount > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Course Name</th>
                                    <th>Department</th>
                                    <th>Lecturer</th>
                                    <th>Credits</th>
                                    <th>Fee</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledCourses as $course): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['dept_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($course['lecturer_name']): ?>
                                            <?php echo htmlspecialchars($course['lecturer_name']); ?>
                                        <?php else: ?>
                                            <em style="color: #94a3b8;">Not Assigned</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo intval($course['credits']); ?></td>
                                    <td><?php echo number_format(calculateCoursePrice($course['credits'])); ?> FRW</td>
                                    <td>
                                        <?php if ($course['letter_grade']): ?>
                                            <span class="badge badge-success"><?php echo $course['letter_grade']; ?></span>
                                            <strong style="color: #1a365d; margin-left: 5px;"><?php echo $course['total']; ?></strong>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 0.85rem;">Not graded</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <p>You are not enrolled in any courses yet. Contact admin for enrollment.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Access</h3>
                    </div>
                    <div class="quick-actions-grid">
                        <a href="courses.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-book-open"></i></div>
                            <div class="action-label">My Courses</div>
                            <div class="action-desc">View enrolled courses</div>
                        </a>
                        <a href="register-course.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                            <div class="action-label">Registration</div>
                            <div class="action-desc">Register new courses</div>
                        </a>
                        <a href="schedule.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="action-label">Schedule</div>
                            <div class="action-desc">View your timetable</div>
                        </a>
                        <a href="financial.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-wallet"></i></div>
                            <div class="action-label">Financial Portal</div>
                            <div class="action-desc">View fees & payments</div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var courseLabels = <?php echo json_encode(array_column($enrolledCourses, 'code')); ?>;
        var courseGrades = <?php echo json_encode(array_map(function($c) { return $c['total'] ?? 0; }, $enrolledCourses)); ?>;
        var courseCredits = <?php echo json_encode(array_map(function($c) { return intval($c['credits']); }, $enrolledCourses)); ?>;
        var colors = ['#4f46e5', '#06b6d4', '#22c55e', '#f59e0b', '#ec4899', '#8b5cf6', '#ef4444'];

        initCharts([
            {
                canvasId: 'gradesChart',
                type: 'bar',
                data: {
                    labels: courseLabels,
                    datasets: [{
                        label: 'Grade',
                        data: courseGrades,
                        backgroundColor: courseGrades.map(function(g) {
                            if (g >= 80) return 'rgba(34,197,94,0.8)';
                            if (g >= 60) return 'rgba(59,130,246,0.8)';
                            if (g >= 40) return 'rgba(245,158,11,0.8)';
                            return 'rgba(239,68,68,0.8)';
                        }),
                        borderRadius: 12,
                        borderSkipped: false
                    }]
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, grid: { color: 'rgba(226,232,240,0.4)' } }, x: { grid: { display: false } } } }
            },
            {
                canvasId: 'creditsChart',
                type: 'doughnut',
                data: {
                    labels: courseLabels,
                    datasets: [{
                        data: courseCredits,
                        backgroundColor: colors.slice(0, courseLabels.length),
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: { cutout: '65%', plugins: { legend: { position: 'bottom' } } }
            }
        ]);
    });
    </script>
</body>
</html>
