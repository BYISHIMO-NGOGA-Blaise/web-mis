<?php
require_once '../includes/config.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get enrolled courses
$courses = $conn->prepare("
    SELECT c.*, d.name as dept_name, CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
    FROM courses c
    JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    WHERE ce.student_id = ? AND ce.status = 'enrolled'
    ORDER BY c.code
");
$courses->bind_param("i", $studentId);
$courses->execute();
$enrolledCourses = $courses->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Student</title>
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
                <li><a href="courses.php" class="active"> My Courses</a></li>
                <li><a href="register-course.php"> Registration</a></li>
                <li><a href="schedule.php"> My Schedule</a></li>
                <li><a href="grades.php"> Results</a></li>
                <li><a href="financial.php"> Financial Portal</a></li>
                <li><a href="attendance.php"> Attendance</a></li>
                <li><a href="profile.php"> My Profile</a></li>
                <li><a href="../logout.php"> Logout</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>My Enrolled Courses</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Student</div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Course Details</h3>
                    </div>
                    
                    <?php if ($enrolledCourses->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Department</th>
                                <th>Lecturer</th>
                                <th>Credits</th>
                                <th>Semester</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = $enrolledCourses->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($course['name']); ?>
                                    <?php if ($course['description']): ?>
                                        <div style="font-size: 12px; color: #718096; margin-top: 5px;">
                                            <?php echo htmlspecialchars($course['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($course['dept_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($course['lecturer_name']): ?>
                                        <?php echo htmlspecialchars($course['lecturer_name']); ?>
                                    <?php else: ?>
                                        <em style="color: #718096;">Not Assigned</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $course['credits']; ?></td>
                                <td><?php echo $course['semester']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">
                         You are not enrolled in any courses yet.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
