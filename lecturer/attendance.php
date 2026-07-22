<?php
require_once '../includes/config.php';
requireRole('lecturer');

$lecturerId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get courses taught by this lecturer
$courses = $conn->prepare("
    SELECT c.id, c.code, c.name, COUNT(ce.id) as student_count
    FROM courses c
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'enrolled'
    WHERE c.lecturer_id = ?
    GROUP BY c.id, c.code, c.name
");
$courses->bind_param("i", $lecturerId);
$courses->execute();
$taughtCourses = $courses->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Lecturer</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('attendance'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Attendance Management</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Lecturer</div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Select Course for Attendance</h3>
                    </div>
                    
                    <?php if ($taughtCourses->num_rows > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                        <?php while ($course = $taughtCourses->fetch_assoc()): ?>
                        <div style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px; background: white;">
                            <h4 style="color: #1a365d; margin-bottom: 10px; font-size: 18px;">
                                <?php echo htmlspecialchars($course['code']); ?>
                            </h4>
                            <p style="color: #2d3748; margin-bottom: 8px; font-weight: 600;">
                                <?php echo htmlspecialchars($course['name']); ?>
                            </p>
                            <p style="color: #718096; font-size: 14px; margin-bottom: 15px;">
                                 <?php echo $course['student_count']; ?> student<?php echo $course['student_count'] != 1 ? 's' : ''; ?> enrolled
                            </p>
                            <a href="take-attendance.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary" style="padding: 8px 20px; display: block; text-align: center;">
                                 Take Attendance
                            </a>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">
                         You are not assigned to any courses yet.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
