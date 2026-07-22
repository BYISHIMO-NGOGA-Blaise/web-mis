<?php
require_once '../includes/config.php';
requireRole('hod');

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

$dept = $conn->prepare("SELECT * FROM departments WHERE hod_id = ?");
$dept->bind_param("i", $userId);
$dept->execute();
$department = $dept->get_result()->fetch_assoc();
$dept->close();

$lecturers = null;
$totalCourses = 0;
$totalStudents = 0;
$registeredStudents = 0;

if ($department) {
    $deptId = $department['id'];
    $coursesResult = $conn->query("SELECT COUNT(*) as total FROM courses WHERE department_id = $deptId");
    $totalCourses = $coursesResult->fetch_assoc()['total'];

    $studentsResult = $conn->query("SELECT COUNT(DISTINCT u.id) as total FROM users u
        JOIN course_enrollments ce ON u.id = ce.student_id
        JOIN courses c ON ce.course_id = c.id
        WHERE c.department_id = $deptId AND u.role_id = 4");
    $totalStudents = $studentsResult->fetch_assoc()['total'];

    $regResult = $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id = 4 AND department_id = $deptId");
    $registeredStudents = $regResult->fetch_assoc()['total'];

    $lecturers = $conn->query("SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email
        FROM users u
        JOIN courses c ON u.id = c.lecturer_id
        WHERE c.department_id = $deptId
        ORDER BY u.first_name, u.last_name");
}

$myTeachStmt = $conn->prepare("
    SELECT c.id, c.code, c.name, d.name as dept_name, COUNT(ce.id) as student_count
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'enrolled'
    WHERE c.lecturer_id = ?
    GROUP BY c.id, c.code, c.name, d.name
    ORDER BY c.code
");
$myTeachStmt->bind_param("i", $userId);
$myTeachStmt->execute();
$myCourses = $myTeachStmt->get_result();
$myTeachStmt->close();

$conn->close();

$greeting = 'Good ' . (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard</title>
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
                <h1>HOD Dashboard</h1>
                <div class="user-info">
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                        <div class="user-role">Head of Department</div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?></div>
                </div>
            </nav>

            <div class="page-content">
                <?php if ($department): ?>
                <div class="welcome-banner">
                    <h2><?php echo $greeting ?>, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
                    <p>Managing <strong><?php echo htmlspecialchars($department['name']); ?></strong> (<?php echo htmlspecialchars($department['code']); ?>)</p>
                    <div class="welcome-time"><i class="far fa-clock"></i> <span id="current-time"></span></div>
                </div>

                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-building"></i></div>
                        <h4>Department</h4>
                        <div class="metric-value" style="font-size: 1.4rem;"><?php echo htmlspecialchars($department['name']); ?></div>
                        <p>Code: <?php echo htmlspecialchars($department['code']); ?></p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-book-open"></i></div>
                        <h4>Total Courses</h4>
                        <div class="metric-value" data-counter="<?php echo $totalCourses; ?>">0</div>
                        <p>Offered this semester</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <h4>Lecturers</h4>
                        <div class="metric-value" data-counter="<?php echo $lecturers ? $lecturers->num_rows : 0; ?>">0</div>
                        <p>Teaching staff</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-user-graduate"></i></div>
                        <h4>Enrolled Students</h4>
                        <div class="metric-value" data-counter="<?php echo $totalStudents; ?>">0</div>
                        <p>Enrolled in department courses</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-clipboard-list"></i></div>
                        <h4>Registered Students</h4>
                        <div class="metric-value" data-counter="<?php echo $registeredStudents; ?>">0</div>
                        <p>Assigned to this department</p>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Department Breakdown</h3>
                        </div>
                        <div class="chart-card" style="height: 300px;">
                            <canvas id="deptChart"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Department Stats</h3>
                        </div>
                        <div class="chart-card" style="height: 300px;">
                            <canvas id="statsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Department Overview</h3>
                    </div>
                    <div style="padding: 1.75rem;">
                        <div class="stat-highlight">
                            <div class="stat-icon" style="background: var(--gradient-primary);"><i class="fas fa-building"></i></div>
                            <div class="stat-details">
                                <div class="stat-label">Department Name</div>
                                <div class="stat-value"><?php echo htmlspecialchars($department['name']); ?> (<?php echo htmlspecialchars($department['code']); ?>)</div>
                            </div>
                        </div>
                        <p style="color: #475569; font-size: 0.95rem; line-height: 1.7; margin-top: 1rem;">
                            <?php echo htmlspecialchars($department['description'] ?? 'No description available'); ?>
                        </p>
                    </div>
                </div>

                <?php if ($lecturers && $lecturers->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chalkboard-teacher"></i> Lecturers in Department</h3>
                        <a href="lecturers.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($lec = $lecturers->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($lec['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($lec['email']); ?></td>
                                    <td>
                                        <a href="lecturers.php" class="badge badge-info"><i class="fas fa-eye"></i> View</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($myCourses && $myCourses->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chalkboard"></i> My Teaching</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Course Name</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($mc = $myCourses->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($mc['code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($mc['name']); ?></td>
                                    <td><span class="badge badge-primary"><?php echo $mc['student_count']; ?> students</span></td>
                                    <td>
                                        <a href="grades.php" class="badge badge-info" style="margin-right:5px;"><i class="fas fa-star"></i> Grades</a>
                                        <a href="attendance.php" class="badge badge-primary"><i class="fas fa-clipboard-check"></i> Attendance</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> No Department Assigned</h3>
                    </div>
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <p>You are not assigned to manage any department. Please contact the admin to assign you a department.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <?php if ($department): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initCharts([
            {
                canvasId: 'deptChart',
                type: 'doughnut',
                data: {
                    labels: ['Courses', 'Lecturers', 'Enrolled Students', 'Registered Students'],
                    datasets: [{
                        data: [<?php echo $totalCourses; ?>, <?php echo $lecturers ? $lecturers->num_rows : 0; ?>, <?php echo $totalStudents; ?>, <?php echo $registeredStudents; ?>],
                        backgroundColor: ['#4f46e5', '#3b82f6', '#22c55e', '#f59e0b'],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: { cutout: '65%', plugins: { legend: { position: 'bottom' } } }
            },
            {
                canvasId: 'statsChart',
                type: 'bar',
                data: {
                    labels: ['Courses', 'Lecturers', 'Enrolled', 'Registered'],
                    datasets: [{
                        label: 'Count',
                        data: [<?php echo $totalCourses; ?>, <?php echo $lecturers ? $lecturers->num_rows : 0; ?>, <?php echo $totalStudents; ?>, <?php echo $registeredStudents; ?>],
                        backgroundColor: ['rgba(79,70,229,0.8)', 'rgba(59,130,246,0.8)', 'rgba(34,197,94,0.8)', 'rgba(245,158,11,0.8)'],
                        borderRadius: 12,
                        borderSkipped: false
                    }]
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(226,232,240,0.4)' } }, x: { grid: { display: false } } } }
            }
        ]);
    });
    </script>
    <?php endif; ?>
</body>
</html>
