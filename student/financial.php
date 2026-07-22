<?php
require_once '../includes/config.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$conn = getDBConnection();

$studentInfo = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$studentInfo->bind_param("i", $studentId);
$studentInfo->execute();
$student = $studentInfo->get_result()->fetch_assoc();
$studentInfo->close();


$enrolled = [];

$courseQuery = $conn->prepare(" 
    SELECT c.code, c.name, c.credits
    FROM courses c
    JOIN course_enrollments ce ON c.id = ce.course_id
    WHERE ce.student_id = ? AND ce.status = 'enrolled'
    ORDER BY c.code
");
$courseQuery->bind_param("i", $studentId);
$courseQuery->execute();
$enrolledCourses = $courseQuery->get_result();

$totalCredits = 0;
while ($course = $enrolledCourses->fetch_assoc()) {
    $totalCredits += intval($course['credits']);
    $enrolled[] = $course;
}
$totalFee = calculateCoursePrice($totalCredits);
$paymentQuery = $conn->prepare("SELECT IFNULL(SUM(amount), 0) AS total_paid FROM payments WHERE student_id = ?");
$paymentQuery->bind_param("i", $studentId);
$paymentQuery->execute();
$paymentResult = $paymentQuery->get_result()->fetch_assoc();
$paymentQuery->close();

$paidAmount = floatval($paymentResult['total_paid'] ?? 0);
$outstanding = max(0, $totalFee - $paidAmount);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Portal - Student</title>
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
                <li><a href="financial.php" class="active"> Financial Portal</a></li>
                <li><a href="attendance.php"> Attendance</a></li>
                <li><a href="profile.php"> My Profile</a></li>
                <li><a href="../logout.php"> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <nav class="top-nav">
                <h1>Financial Portal</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                    <div class="user-role">Student</div>
                </div>
            </nav>

            <div class="page-content">
                <div class="metrics-grid">
                    <div class="metric-card">
                        <h4>Registered Courses</h4>
                        <div class="metric-value"><?php echo count($enrolled); ?></div>
                        <p>Currently enrolled</p>
                    </div>
                    <div class="metric-card">
                        <h4>Total Credits</h4>
                        <div class="metric-value"><?php echo $totalCredits; ?></div>
                        <p>Credit hours</p>
                    </div>
                    <div class="metric-card">
                        <h4>Total Fees</h4>
                        <div class="metric-value"><?php echo number_format($totalFee); ?> FRW</div>
                        <p>Tuition due</p>
                    </div>
                    <div class="metric-card">
                        <h4>Paid</h4>
                        <div class="metric-value"><?php echo number_format($paidAmount); ?> FRW</div>
                        <p>Payments recorded</p>
                    </div>
                    <div class="metric-card">
                        <h4>Outstanding</h4>
                        <div class="metric-value"><?php echo number_format($outstanding); ?> FRW</div>
                        <p>Amount due</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3> Fee Breakdown</h3>
                    </div>

                    <?php if (!empty($enrolled)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Credits</th>
                                <th>Course Fee</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolled as $course): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['name']); ?></td>
                                <td><?php echo intval($course['credits']); ?></td>
                                <td><?php echo number_format(calculateCoursePrice($course['credits'])); ?> FRW</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">
                         No enrolled courses found. Your fee summary will appear once you register.
                    </p>
                    <?php endif; ?>
                    <div style="padding: 20px; display:flex; gap: 15px; align-items:center; flex-wrap:wrap;">
                        <a href="https://urubutopay.rw/pay-now" target="_blank" class="btn btn-success" style="padding: 12px 30px; text-decoration:none; display:inline-block;"> Pay Now</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

