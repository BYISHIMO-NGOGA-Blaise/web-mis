<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

$stats = [
    'total_students' => $conn->query("SELECT COUNT(*) as t FROM users WHERE role_id = 4")->fetch_assoc()['t'],
    'total_lecturers' => $conn->query("SELECT COUNT(*) as t FROM users WHERE role_id = 3")->fetch_assoc()['t'],
    'total_hods' => $conn->query("SELECT COUNT(*) as t FROM users WHERE role_id = 2")->fetch_assoc()['t'],
    'total_admins' => $conn->query("SELECT COUNT(*) as t FROM users WHERE role_id = 1")->fetch_assoc()['t'],
    'total_departments' => $conn->query("SELECT COUNT(*) as t FROM departments")->fetch_assoc()['t'],
    'total_courses' => $conn->query("SELECT COUNT(*) as t FROM courses")->fetch_assoc()['t'],
    'total_enrollments' => $conn->query("SELECT COUNT(*) as t FROM course_enrollments WHERE status = 'enrolled'")->fetch_assoc()['t'],
    'total_graded' => $conn->query("SELECT COUNT(*) as t FROM grades WHERE total > 0")->fetch_assoc()['t'],
    'total_payments' => $conn->query("SELECT IFNULL(SUM(amount), 0) as t FROM payments")->fetch_assoc()['t'],
];

$deptStudents = $conn->query("SELECT d.name, d.code, COUNT(u.id) as student_count FROM departments d LEFT JOIN users u ON d.id = u.department_id AND u.role_id = 4 GROUP BY d.id, d.name, d.code ORDER BY student_count DESC")->fetch_all(MYSQLI_ASSOC);
$unassignedStudents = $conn->query("SELECT COUNT(*) as t FROM users WHERE role_id = 4 AND (department_id IS NULL OR department_id = 0)")->fetch_assoc()['t'];
if ($unassignedStudents > 0) {
    $deptStudents[] = ['name' => 'Unassigned', 'code' => 'N/A', 'student_count' => $unassignedStudents];
}

$deptCourses = $conn->query("SELECT d.name, d.code, COUNT(c.id) as course_count FROM departments d LEFT JOIN courses c ON d.id = c.department_id GROUP BY d.id, d.name, d.code ORDER BY course_count DESC")->fetch_all(MYSQLI_ASSOC);

$deptEnrollments = $conn->query("SELECT d.name, d.code, COUNT(ce.id) as enrollment_count FROM departments d LEFT JOIN courses c ON d.id = c.department_id LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'enrolled' GROUP BY d.id, d.name, d.code ORDER BY enrollment_count DESC")->fetch_all(MYSQLI_ASSOC);

$deptRevenue = $conn->query("SELECT d.name, d.code, IFNULL(SUM(p.amount), 0) as total_paid FROM departments d LEFT JOIN courses c ON d.id = c.department_id LEFT JOIN course_enrollments ce ON c.id = ce.course_id LEFT JOIN payments p ON ce.student_id = p.student_id GROUP BY d.id, d.name, d.code ORDER BY total_paid DESC")->fetch_all(MYSQLI_ASSOC);

$gradeDist = $conn->query("SELECT g.letter_grade, COUNT(*) as count FROM grades g WHERE g.letter_grade IS NOT NULL AND g.letter_grade != '' GROUP BY g.letter_grade ORDER BY FIELD(g.letter_grade, 'A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F')")->fetch_all(MYSQLI_ASSOC);

$deptAvgGrade = $conn->query("SELECT d.name, d.code, IFNULL(ROUND(AVG(g.total), 2), 0) as avg_grade FROM departments d LEFT JOIN courses c ON d.id = c.department_id LEFT JOIN course_enrollments ce ON c.id = ce.course_id LEFT JOIN grades g ON ce.id = g.enrollment_id AND g.total > 0 GROUP BY d.id, d.name, d.code HAVING avg_grade > 0 ORDER BY avg_grade DESC")->fetch_all(MYSQLI_ASSOC);

$attData = $conn->query("SELECT IFNULL(SUM(CASE WHEN status='present' THEN 1 ELSE 0 END),0) as present, IFNULL(SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END),0) as absent, IFNULL(SUM(CASE WHEN status='late' THEN 1 ELSE 0 END),0) as late, IFNULL(COUNT(*),0) as total FROM attendance")->fetch_assoc();
$attRate = $attData['total'] > 0 ? round(($attData['present'] / $attData['total']) * 100, 1) : 0;

$topStudents = $conn->query("SELECT CONCAT(u.first_name,' ',u.last_name) as name, u.reg_number, ROUND(AVG(g.total),2) as avg_grade, COUNT(g.id) as graded_courses FROM users u JOIN course_enrollments ce ON u.id=ce.student_id JOIN grades g ON ce.id=g.enrollment_id AND g.total>0 WHERE u.role_id=4 GROUP BY u.id,name,u.reg_number HAVING graded_courses>=1 ORDER BY avg_grade DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$lowStudents = $conn->query("SELECT CONCAT(u.first_name,' ',u.last_name) as name, u.reg_number, ROUND(AVG(g.total),2) as avg_grade, COUNT(g.id) as graded_courses FROM users u JOIN course_enrollments ce ON u.id=ce.student_id JOIN grades g ON ce.id=g.enrollment_id AND g.total>0 WHERE u.role_id=4 GROUP BY u.id,name,u.reg_number HAVING graded_courses>=1 ORDER BY avg_grade ASC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$popularCourses = $conn->query("SELECT c.code, c.name, COUNT(ce.id) as enroll_count, d.name as dept_name FROM courses c LEFT JOIN course_enrollments ce ON c.id=ce.course_id AND ce.status='enrolled' LEFT JOIN departments d ON c.department_id=d.id GROUP BY c.id,c.code,c.name,d.name ORDER BY enroll_count DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$recentEnrollments = $conn->query("SELECT CONCAT(u.first_name,' ',u.last_name) as student_name, c.code, c.name as course_name, ce.enrollment_date FROM course_enrollments ce JOIN users u ON ce.student_id=u.id JOIN courses c ON ce.course_id=c.id WHERE ce.status='enrolled' ORDER BY ce.enrollment_date DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$conn->close();

$chartLabels = array_column($deptStudents, 'code');
$chartStudents = array_column($deptStudents, 'student_count');
$chartCourses = array_column($deptCourses, 'course_count');
$chartEnrollments = array_column($deptEnrollments, 'enrollment_count');
$chartRevenue = array_column($deptRevenue, 'total_paid');
$chartDeptNames = array_column($deptStudents, 'name');

$gradeLabels = array_column($gradeDist, 'letter_grade');
$gradeCounts = array_column($gradeDist, 'count');
$gradeColors = ['#48bb78','#48bb78','#68d391','#4299e1','#4299e1','#63b3ed','#ed8936','#ed8936','#f6ad55','#fc8181','#fc8181','#f56565'];
$gradeBgColors = [];
foreach ($gradeLabels as $gl) {
    $idx = array_search($gl, ['A+','A','A-','B+','B','B-','C+','C','C-','D+','D','F']);
    $gradeBgColors[] = $gradeColors[$idx] ?? '#718096';
}

$avgGradeLabels = array_column($deptAvgGrade, 'code');
$avgGradeValues = array_column($deptAvgGrade, 'avg_grade');
$avgGradeColors = array_map(fn($g) => $g >= 60 ? '#48bb78' : ($g >= 40 ? '#ed8936' : '#f56565'), $avgGradeValues);

$popCourseLabels = array_map(fn($c) => $c['code'], $popularCourses);
$popCourseCounts = array_column($popularCourses, 'enroll_count');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Report - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('reports'); ?>

        <main class="main-content">
            <nav class="top-nav">
                <h1>General School Report</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>

            <div class="page-content">
                <div class="metrics-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="metric-card"><h4>Students</h4><div class="metric-value"><?php echo number_format($stats['total_students']); ?></div><p>Registered</p></div>
                    <div class="metric-card"><h4>Lecturers</h4><div class="metric-value"><?php echo number_format($stats['total_lecturers']); ?></div><p>Teaching staff</p></div>
                    <div class="metric-card"><h4>HODs</h4><div class="metric-value"><?php echo number_format($stats['total_hods']); ?></div><p>Department heads</p></div>
                    <div class="metric-card"><h4>Admins</h4><div class="metric-value"><?php echo number_format($stats['total_admins']); ?></div><p>System admins</p></div>
                </div>
                <div class="metrics-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="metric-card"><h4>Departments</h4><div class="metric-value"><?php echo $stats['total_departments']; ?></div><p>Active</p></div>
                    <div class="metric-card"><h4>Courses</h4><div class="metric-value"><?php echo number_format($stats['total_courses']); ?></div><p>Offered</p></div>
                    <div class="metric-card"><h4>Graded</h4><div class="metric-value"><?php echo number_format($stats['total_graded']); ?></div><p>Results</p></div>
                    <div class="metric-card"><h4>Revenue</h4><div class="metric-value" style="font-size:18px;"><?php echo number_format($stats['total_payments']); ?> FRW</div><p>Collected</p></div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div class="card">
                        <div class="card-header"><h3> User Distribution by Role</h3></div>
                        <div class="chart-card" style="display:flex; justify-content:center;"><canvas id="chartUserDist" style="max-width:350px;"></canvas></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3> Students by Department</h3></div>
                        <div class="chart-card"><canvas id="chartStudents"></canvas></div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div class="card">
                        <div class="card-header"><h3> Courses by Department</h3></div>
                        <div class="chart-card"><canvas id="chartCourses"></canvas></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3> Enrollments by Department</h3></div>
                        <div class="chart-card"><canvas id="chartEnrollments"></canvas></div>
                    </div>
                </div>
                    <div class="card">
                        <div class="card-header"><h3> Revenue by Department</h3></div>
                        <div class="chart-card"><canvas id="chartRevenue"></canvas></div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div class="card">
                        <div class="card-header"><h3> Grade Distribution</h3></div>
                        <div class="chart-card" style="display:flex; justify-content:center;"><canvas id="chartGrades" style="max-width:350px;"></canvas></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3> Average Grade by Department</h3></div>
                        <div class="chart-card"><canvas id="chartAvgGrade"></canvas></div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div class="card">
                        <div class="card-header"><h3> Most Popular Courses</h3></div>
                        <div class="chart-card"><canvas id="chartPopular"></canvas></div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3> Attendance Overview</h3></div>
                        <div class="chart-card" style="display:flex; justify-content:center;"><canvas id="chartAttendance" style="max-width:300px;"></canvas></div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div class="card">
                        <div class="card-header"><h3> Top Performing Students</h3></div>
                        <?php if (!empty($topStudents)): ?>
                        <table class="data-table">
                            <thead><tr><th>#</th><th>Name</th><th>Reg No.</th><th>Avg Grade</th><th>Courses</th></tr></thead>
                            <tbody>
                                <?php foreach ($topStudents as $i => $s): ?>
                                <tr>
                                    <td><strong><?php echo $i+1; ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['reg_number'] ?? 'N/A'); ?></td>
                                    <td><span class="badge badge-success"><?php echo $s['avg_grade']; ?></span></td>
                                    <td><?php echo $s['graded_courses']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p style="padding:30px;color:#718096;text-align:center;">No graded students yet.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3> Students Needing Attention</h3></div>
                        <?php if (!empty($lowStudents)): ?>
                        <table class="data-table">
                            <thead><tr><th>#</th><th>Name</th><th>Reg No.</th><th>Avg Grade</th><th>Courses</th></tr></thead>
                            <tbody>
                                <?php foreach ($lowStudents as $i => $s): ?>
                                <tr>
                                    <td><strong><?php echo $i+1; ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['reg_number'] ?? 'N/A'); ?></td>
                                    <td><span class="badge badge-danger"><?php echo $s['avg_grade']; ?></span></td>
                                    <td><?php echo $s['graded_courses']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p style="padding:30px;color:#718096;text-align:center;">No graded students yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3> Recent Enrollments</h3></div>
                    <?php if (!empty($recentEnrollments)): ?>
                    <table class="data-table">
                        <thead><tr><th>Student</th><th>Course</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentEnrollments as $e): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($e['student_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($e['code']); ?> - <?php echo htmlspecialchars($e['course_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($e['enrollment_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding:30px;color:#718096;text-align:center;">No enrollments yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    const fontFamily = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
    Chart.defaults.font.family = fontFamily;
    Chart.defaults.font.size = 13;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;

    const gridColor = 'rgba(0,0,0,0.06)';
    const axisTitleColor = '#4a5568';

    const defaultScales = {
        x: { grid: { color: gridColor }, ticks: { maxRotation: 45 }, title: { display: true, text: 'Department', color: axisTitleColor, font: { weight: 'bold' } } },
        y: { grid: { color: gridColor }, beginAtZero: true, title: { display: true, text: 'Count', color: axisTitleColor, font: { weight: 'bold' } } }
    };
    const horizontalScales = {
        x: { grid: { color: gridColor }, beginAtZero: true, title: { display: true, text: 'Value', color: axisTitleColor, font: { weight: 'bold' } } },
        y: { grid: { display: false }, title: { display: true, text: 'Department', color: axisTitleColor, font: { weight: 'bold' } } }
    };
    const revenueScales = {
        x: { grid: { color: gridColor }, ticks: { maxRotation: 45 }, title: { display: true, text: 'Department', color: axisTitleColor, font: { weight: 'bold' } } },
        y: { grid: { color: gridColor }, beginAtZero: true, title: { display: true, text: 'Amount (FRW)', color: axisTitleColor, font: { weight: 'bold' } } }
    };

    new Chart(document.getElementById('chartUserDist'), {
        type: 'doughnut',
        data: {
            labels: ['Admin', 'HOD', 'Lecturer', 'Student'],
            datasets: [{
                data: [<?php echo $stats['total_admins']; ?>, <?php echo $stats['total_hods']; ?>, <?php echo $stats['total_lecturers']; ?>, <?php echo $stats['total_students']; ?>],
                backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#22c55e'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'right', labels: { padding: 12 } } } }
    });

    new Chart(document.getElementById('chartStudents'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(fn($d) => $d['code'], $deptStudents)); ?>,
            datasets: [{
                label: 'Students',
                data: <?php echo json_encode($chartStudents); ?>,
                backgroundColor: ['#667eea','#764ba2','#48bb78','#ed8936','#4299e1','#fc8181','#ecc94b','#9f7aea','#38b2ac','#e53e3e'],
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: { responsive: true, scales: defaultScales, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('chartCourses'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($deptCourses, 'code')); ?>,
            datasets: [{
                label: 'Courses',
                data: <?php echo json_encode($chartCourses); ?>,
                backgroundColor: ['#48bb78','#38a169','#68d391','#2f855a','#4fd1c5','#2c7a7b','#319795','#285e61','#234e52','#1d4044'],
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: { responsive: true, scales: defaultScales, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('chartEnrollments'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($deptEnrollments, 'code')); ?>,
            datasets: [{
                label: 'Enrollments',
                data: <?php echo json_encode($chartEnrollments); ?>,
                backgroundColor: ['#ed8936','#dd6b20','#f6ad55','#c05621','#b7791f','#d69e2e','#ecc94b','#f6e05e','#faf089','#fefcbf'],
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: { responsive: true, scales: defaultScales, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('chartRevenue'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($deptRevenue, 'code')); ?>,
            datasets: [{
                label: 'Revenue (FRW)',
                data: <?php echo json_encode($chartRevenue); ?>,
                backgroundColor: ['#4299e1','#3182ce','#63b3ed','#2b6cb0','#2c5282','#2a4365','#eb5757','#e53e3e','#c53030','#9b2c2c'],
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: { responsive: true, scales: revenueScales, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('chartGrades'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($gradeLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($gradeCounts); ?>,
                backgroundColor: <?php echo json_encode($gradeBgColors); ?>,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'right', labels: { padding: 12 } } } }
    });

    new Chart(document.getElementById('chartAvgGrade'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($avgGradeLabels); ?>,
            datasets: [{
                label: 'Avg Grade',
                data: <?php echo json_encode($avgGradeValues); ?>,
                backgroundColor: <?php echo json_encode($avgGradeColors); ?>,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: { indexAxis: 'y', responsive: true, scales: { x: { grid: { color: gridColor }, max: 100, beginAtZero: true, title: { display: true, text: 'Grade (%)', color: axisTitleColor, font: { weight: 'bold' } } }, y: { grid: { display: false }, title: { display: true, text: 'Department', color: axisTitleColor, font: { weight: 'bold' } } } }, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('chartPopular'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($popCourseLabels); ?>,
            datasets: [{
                label: 'Enrollments',
                data: <?php echo json_encode($popCourseCounts); ?>,
                backgroundColor: ['#667eea','#764ba2','#48bb78','#ed8936','#4299e1','#fc8181','#ecc94b','#9f7aea','#38b2ac','#e53e3e'],
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: { indexAxis: 'y', responsive: true, scales: { x: { grid: { color: gridColor }, beginAtZero: true, title: { display: true, text: 'Number of Students', color: axisTitleColor, font: { weight: 'bold' } } }, y: { grid: { display: false }, title: { display: true, text: 'Course Code', color: axisTitleColor, font: { weight: 'bold' } } } }, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('chartAttendance'), {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [<?php echo $attData['present']; ?>, <?php echo $attData['late']; ?>, <?php echo $attData['absent']; ?>],
                backgroundColor: ['#48bb78', '#ed8936', '#f56565'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 15 } } } }
    });
    </script>
</body>
</html>

