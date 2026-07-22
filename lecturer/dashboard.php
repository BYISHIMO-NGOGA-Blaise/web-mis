<?php
require_once '../includes/config.php';
requireRole('lecturer');

$lecturerId = $_SESSION['user_id'];
$conn = getDBConnection();

$courses = $conn->prepare("
    SELECT c.id, c.code, c.name, d.name as dept_name, COUNT(ce.id) as student_count
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'enrolled'
    WHERE c.lecturer_id = ?
    GROUP BY c.id, c.code, c.name, d.name
    ORDER BY c.code
");
$courses->bind_param("i", $lecturerId);
$courses->execute();
$taughtCourses = $courses->get_result();

$studentStmt = $conn->prepare("
    SELECT COUNT(DISTINCT ce.student_id) as total
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    WHERE c.lecturer_id = ? AND ce.status = 'enrolled'
");
$studentStmt->bind_param("i", $lecturerId);
$studentStmt->execute();
$totalStudents = $studentStmt->get_result()->fetch_assoc()['total'];
$studentStmt->close();

$conn->close();

$greeting = 'Good ' . (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('dashboard'); ?>

        <main class="main-content">
            <nav class="top-nav">
                <h1>Lecturer Dashboard</h1>
                <div class="user-info">
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                        <div class="user-role">Lecturer</div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?></div>
                </div>
            </nav>

            <div class="page-content">
                <div class="welcome-banner">
                    <h2><?php echo $greeting ?>, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
                    <p>Manage your courses, grades, and attendance from your dashboard.</p>
                    <div class="welcome-time"><i class="far fa-clock"></i> <span id="current-time"></span></div>
                </div>

                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-book-open"></i></div>
                        <h4>Courses Teaching</h4>
                        <div class="metric-value" data-counter="<?php echo $taughtCourses->num_rows; ?>">0</div>
                        <p>Active courses</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-user-graduate"></i></div>
                        <h4>Total Students</h4>
                        <div class="metric-value" data-counter="<?php echo $totalStudents; ?>">0</div>
                        <p>Across all courses</p>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Students per Course</h3>
                        </div>
                        <div class="chart-card" style="height: 300px;">
                            <canvas id="coursesChart"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-doughnut"></i> Course Distribution</h3>
                        </div>
                        <div class="chart-card" style="height: 300px;">
                            <canvas id="distributionChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chalkboard"></i> Courses I'm Teaching</h3>
                    </div>
                    <?php if ($taughtCourses->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Course Name</th>
                                    <th>Department</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $courseLabels = [];
                                $courseData = [];
                                while ($course = $taughtCourses->fetch_assoc()):
                                    $courseLabels[] = $course['code'];
                                    $courseData[] = $course['student_count'];
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['dept_name'] ?? 'N/A'); ?></td>
                                    <td><span class="badge badge-primary"><?php echo $course['student_count']; ?> students</span></td>
                                    <td>
                                        <a href="enter-grades.php?course_id=<?php echo $course['id']; ?>" class="badge badge-info" style="margin-right: 5px;"><i class="fas fa-star"></i> Grades</a>
                                        <a href="take-attendance.php?course_id=<?php echo $course['id']; ?>" class="badge badge-primary"><i class="fas fa-clipboard-check"></i> Attendance</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <p>You are not assigned to any courses yet. Contact admin to assign courses.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="quick-actions-grid">
                        <a href="grades.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-star"></i></div>
                            <div class="action-label">Manage Grades</div>
                            <div class="action-desc">Enter & review grades</div>
                        </a>
                        <a href="attendance.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-clipboard-check"></i></div>
                            <div class="action-label">Take Attendance</div>
                            <div class="action-desc">Record attendance</div>
                        </a>
                        <a href="timetable.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="action-label">Timetable</div>
                            <div class="action-desc">View your schedule</div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php
        $taughtCourses->data_seek(0);
        $labels = [];
        $data = [];
        while ($c = $taughtCourses->fetch_assoc()) {
            $labels[] = $c['code'];
            $data[] = $c['student_count'];
        }
        ?>
        var labels = <?php echo json_encode($labels); ?>;
        var data = <?php echo json_encode($data); ?>;
        var colors = ['#4f46e5', '#06b6d4', '#22c55e', '#f59e0b', '#ec4899', '#8b5cf6'];

        initCharts([
            {
                canvasId: 'coursesChart',
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Students',
                        data: data,
                        backgroundColor: colors.slice(0, data.length).map(function(c) { return c + 'cc'; }),
                        borderRadius: 12,
                        borderSkipped: false
                    }]
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(226,232,240,0.4)' } }, x: { grid: { display: false } } } }
            },
            {
                canvasId: 'distributionChart',
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.slice(0, data.length),
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
