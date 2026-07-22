<?php
require_once '../includes/config.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get enrolled courses with attendance stats
$courses = $conn->prepare("
    SELECT c.id, c.code, c.name,
           COUNT(a.id) as total_days,
           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
           SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
    FROM courses c
    JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN attendance a ON ce.id = a.enrollment_id
    WHERE ce.student_id = ? AND ce.status = 'enrolled'
    GROUP BY c.id, c.code, c.name
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
    <title>Attendance - Student</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2> Student Portal</h2>
                <p><?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"> Dashboard</a></li>
                <li><a href="courses.php"> My Courses</a></li>
                <li><a href="register-course.php"> Registration</a></li>
                <li><a href="schedule.php"> My Schedule</a></li>
                <li><a href="grades.php"> Results</a></li>
                <li><a href="financial.php"> Financial Portal</a></li>
                <li><a href="attendance.php" class="active"> Attendance</a></li>
                <li><a href="profile.php"> My Profile</a></li>
                <li><a href="../logout.php"> Logout</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>My Attendance</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Student</div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Course Attendance Records</h3>
                    </div>
                    
                    <?php if ($enrolledCourses->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Total Days</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = $enrolledCourses->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['name']); ?></td>
                                <td><?php echo $course['total_days']; ?></td>
                                <td style="color: #48bb78; font-weight: bold;"><?php echo $course['present_count']; ?></td>
                                <td style="color: #f56565; font-weight: bold;"><?php echo $course['absent_count']; ?></td>
                                <td style="color: #ed8936; font-weight: bold;"><?php echo $course['late_count']; ?></td>
                                <td>
                                    <?php 
                                    $effectivePresent = $course['present_count'] + ($course['late_count'] * 0.5);
                                    $percentage = $course['total_days'] > 0 ? round(($effectivePresent / $course['total_days']) * 100, 1) : 0;
                                    $color = $percentage >= 80 ? '#48bb78' : ($percentage >= 60 ? '#ed8936' : '#f56565');
                                    ?>
                                    <strong style="color: <?php echo $color; ?>;"><?php echo $percentage; ?>%</strong>
                                </td>
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
