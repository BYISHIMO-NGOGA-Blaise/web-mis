<?php
require_once '../includes/config.php';
requireRole('hod');

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

$department = null;
$courseStats = [];
$lecturerStats = [];
$studentStats = [];

$dept = $conn->prepare("SELECT * FROM departments WHERE hod_id = ?");
$dept->bind_param("i", $userId);
$dept->execute();
$department = $dept->get_result()->fetch_assoc();
$dept->close();

if ($department) {
    $deptId = $department['id'];

    $courseResult = $conn->query("
        SELECT c.code, c.name, COUNT(ce.id) as enrolled_count
        FROM courses c
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'enrolled'
        WHERE c.department_id = $deptId
        GROUP BY c.id, c.code, c.name
        ORDER BY enrolled_count DESC
    ");
    while ($row = $courseResult->fetch_assoc()) {
        $courseStats[] = $row;
    }

    $lecturerResult = $conn->query("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as name, u.email,
               COUNT(DISTINCT c.id) as course_count,
               COUNT(DISTINCT ce.student_id) as student_count
        FROM users u
        JOIN courses c ON u.id = c.lecturer_id
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'enrolled'
        WHERE c.department_id = $deptId
        GROUP BY u.id, u.first_name, u.last_name, u.email
        ORDER BY student_count DESC
    ");
    while ($row = $lecturerResult->fetch_assoc()) {
        $lecturerStats[] = $row;
    }

    $studentResult = $conn->query("
        SELECT 
            COUNT(DISTINCT u.id) as total_students,
            COUNT(DISTINCT CASE WHEN g.total >= 90 THEN u.id END) as grade_a,
            COUNT(DISTINCT CASE WHEN g.total >= 80 AND g.total < 90 THEN u.id END) as grade_b,
            COUNT(DISTINCT CASE WHEN g.total >= 70 AND g.total < 80 THEN u.id END) as grade_c,
            COUNT(DISTINCT CASE WHEN g.total >= 60 AND g.total < 70 THEN u.id END) as grade_d,
            COUNT(DISTINCT CASE WHEN g.total < 60 AND g.total > 0 THEN u.id END) as grade_f,
            ROUND(AVG(g.total), 2) as avg_grade
        FROM users u
        JOIN course_enrollments ce ON u.id = ce.student_id
        JOIN courses c ON ce.course_id = c.id
        LEFT JOIN grades g ON ce.id = g.enrollment_id
        WHERE c.department_id = $deptId AND u.role_id = 4
    ");
    $studentStats = $studentResult->fetch_assoc();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - HOD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('reports'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Department Reports</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Head of Department</div>
                </div>
            </nav>
            
            <div class="page-content">
                <?php if ($department): ?>
                
                <div class="metrics-grid">
                    <div class="metric-card">
                        <h4>Total Students</h4>
                        <div class="metric-value"><?php echo $studentStats['total_students'] ?? 0; ?></div>
                        <p>In department</p>
                    </div>
                    <div class="metric-card">
                        <h4>Average Grade</h4>
                        <div class="metric-value"><?php echo $studentStats['avg_grade'] ?? 'N/A'; ?></div>
                        <p>Department average</p>
                    </div>
                    <div class="metric-card">
                        <h4>Grade A Students</h4>
                        <div class="metric-value"><?php echo $studentStats['grade_a'] ?? 0; ?></div>
                        <p>Score >= 90</p>
                    </div>
                    <div class="metric-card">
                        <h4>Grade F Students</h4>
                        <div class="metric-value"><?php echo $studentStats['grade_f'] ?? 0; ?></div>
                        <p>Score < 60</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3> Course Performance</h3>
                    </div>
                    <?php if (!empty($courseStats)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Enrolled Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courseStats as $cs): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cs['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($cs['name']); ?></td>
                                <td><?php echo $cs['enrolled_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">No courses in this department yet.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3> Lecturer Workload</h3>
                    </div>
                    <?php if (!empty($lecturerStats)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Lecturer</th>
                                <th>Email</th>
                                <th>Courses</th>
                                <th>Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lecturerStats as $ls): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ls['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ls['email']); ?></td>
                                <td><?php echo $ls['course_count']; ?></td>
                                <td><?php echo $ls['student_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">No lecturers assigned to this department yet.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3> Student Grade Distribution</h3>
                    </div>
                    <div style="padding: 30px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                            <div style="text-align:center; padding:15px; background:#c6f6d5; border-radius:8px;">
                                <div style="font-size:28px; font-weight:bold; color:#276749;"><?php echo $studentStats['grade_a'] ?? 0; ?></div>
                                <div style="color:#276749;">Grade A</div>
                            </div>
                            <div style="text-align:center; padding:15px; background:#bee3f8; border-radius:8px;">
                                <div style="font-size:28px; font-weight:bold; color:#2c5282;"><?php echo $studentStats['grade_b'] ?? 0; ?></div>
                                <div style="color:#2c5282;">Grade B</div>
                            </div>
                            <div style="text-align:center; padding:15px; background:#fefcbf; border-radius:8px;">
                                <div style="font-size:28px; font-weight:bold; color:#975a16;"><?php echo $studentStats['grade_c'] ?? 0; ?></div>
                                <div style="color:#975a16;">Grade C</div>
                            </div>
                            <div style="text-align:center; padding:15px; background:#fed7aa; border-radius:8px;">
                                <div style="font-size:28px; font-weight:bold; color:#9c4221;"><?php echo $studentStats['grade_d'] ?? 0; ?></div>
                                <div style="color:#9c4221;">Grade D</div>
                            </div>
                            <div style="text-align:center; padding:15px; background:#fed7d7; border-radius:8px;">
                                <div style="font-size:28px; font-weight:bold; color:#c53030;"><?php echo $studentStats['grade_f'] ?? 0; ?></div>
                                <div style="color:#c53030;">Grade F</div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h3> No Department Assigned</h3>
                    </div>
                    <p style="padding: 30px; color: #718096;">
                        You are not assigned to manage any department.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

