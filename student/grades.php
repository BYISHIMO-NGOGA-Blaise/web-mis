<?php
require_once '../includes/config.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$conn = getDBConnection();

if (isset($_GET['download'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="grades_report_' . $studentId . '.csv"');

    $output = fopen('php://output', 'w');

    $dl = $conn->prepare("
        SELECT c.code, c.name, c.credits, c.semester, d.name as dept_name,
               CONCAT(u.first_name, ' ', u.last_name) as lecturer_name,
               g.assignment1, g.assignment2, g.midterm, g.final_exam, g.total, g.letter_grade
        FROM grades g
        JOIN course_enrollments ce ON g.enrollment_id = ce.id
        JOIN courses c ON ce.course_id = c.id
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN users u ON c.lecturer_id = u.id
        WHERE ce.student_id = ?
        ORDER BY c.code
    ");
    $dl->bind_param("i", $studentId);
    $dl->execute();
    $dlResult = $dl->get_result();

    $studentInfo = $conn->prepare("SELECT first_name, last_name, email, reg_number, phone FROM users WHERE id = ?");
    $studentInfo->bind_param("i", $studentId);
    $studentInfo->execute();
    $sInfo = $studentInfo->get_result()->fetch_assoc();

    $totalCourses = 0;
    $totalCredits = 0;
    $totalGradeSum = 0;
    $gradedCount = 0;
    $highestGrade = 0;
    $lowestGrade = 101;
    $gradesData = [];
    while ($row = $dlResult->fetch_assoc()) {
        $gradesData[] = $row;
        $totalCourses++;
        $totalCredits += intval($row['credits'] ?? 0);
        if ($row['total'] !== null) {
            $totalGradeSum += floatval($row['total']);
            $gradedCount++;
            if (floatval($row['total']) > $highestGrade) $highestGrade = floatval($row['total']);
            if (floatval($row['total']) < $lowestGrade) $lowestGrade = floatval($row['total']);
        }
    }
    $averageGrade = $gradedCount > 0 ? round($totalGradeSum / $gradedCount, 2) : 'N/A';

    fputcsv($output, [APP_NAME . ' - Academic Report Card']);
    fputcsv($output, ['Student Name', $sInfo['first_name'] . ' ' . $sInfo['last_name']]);
    fputcsv($output, ['Registration No', $sInfo['reg_number'] ?? 'N/A']);
    fputcsv($output, ['Email', $sInfo['email'] ?? 'N/A']);
    fputcsv($output, ['Phone', $sInfo['phone'] ?? 'N/A']);
    fputcsv($output, ['Generated', date('F d, Y H:i:s')]);
    fputcsv($output, []);

    fputcsv($output, ['PERFORMANCE SUMMARY']);
    fputcsv($output, ['Total Courses', $totalCourses]);
    fputcsv($output, ['Total Credits', $totalCredits]);
    fputcsv($output, ['Average Grade', is_numeric($averageGrade) ? $averageGrade . '%' : $averageGrade]);
    fputcsv($output, ['Graded Courses', $gradedCount]);
    if ($gradedCount > 0) {
        fputcsv($output, ['Highest', $highestGrade . '%']);
        fputcsv($output, ['Lowest', $lowestGrade . '%']);
    }
    fputcsv($output, []);

    fputcsv($output, ['DETAILED GRADES BREAKDOWN']);
    fputcsv($output, ['Code', 'Course Name', 'Assignment 1 (25)', 'Assignment 2 (25)', 'Midterm (20)', 'Final (30)', 'Total', 'Grade', 'Credits', 'Semester']);
    foreach ($gradesData as $g) {
        fputcsv($output, [
            $g['code'],
            $g['name'],
            $g['assignment1'] ?? '-',
            $g['assignment2'] ?? '-',
            $g['midterm'] ?? '-',
            $g['final_exam'] ?? '-',
            $g['total'] ?? '-',
            $g['letter_grade'] ?? '-',
            $g['credits'] ?? '-',
            $g['semester'] ?? '-'
        ]);
    }
    fputcsv($output, []);

    fputcsv($output, ['GRADING SCALE REFERENCE']);
    fputcsv($output, ['Grade', 'Score Range', 'Description']);
    fputcsv($output, ['A', '80 - 100', 'Excellent']);
    fputcsv($output, ['B', '70 - 79', 'Very Good']);
    fputcsv($output, ['C', '60 - 69', 'Good']);
    fputcsv($output, ['D', '50 - 59', 'Average']);
    fputcsv($output, ['F', '0 - 49', 'Fail']);

    fclose($output);
    $dl->close();
    $studentInfo->close();
    $conn->close();
    exit();
}

// Get all grades
$grades = $conn->prepare("
    SELECT c.code, c.name, d.name as dept_name,
           g.assignment1, g.assignment2, g.midterm, g.final_exam, g.total, g.letter_grade,
           u.first_name as lecturer_first, u.last_name as lecturer_last
    FROM grades g
    JOIN course_enrollments ce ON g.enrollment_id = ce.id
    JOIN courses c ON ce.course_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    WHERE ce.student_id = ?
    ORDER BY c.code
");
$grades->bind_param("i", $studentId);
$grades->execute();
$allGrades = $grades->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - Student</title>
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
                <li><a href="schedule.php"> My Schedule</a></li>
                <li><a href="grades.php" class="active"> Results</a></li>
                <li><a href="financial.php"> Financial Portal</a></li>
                <li><a href="attendance.php"> Attendance</a></li>
                <li><a href="profile.php"> My Profile</a></li>
                <li><a href="../logout.php"> Logout</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>My Grades</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Student</div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> All My Grades</h3>
                        <a href="?download=1" class="btn btn-primary" style="padding: 8px 20px;"><i class="fas fa-download"></i> Download CSV</a>
                    </div>
                    
                    <?php if ($allGrades->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Asst 1 (25)</th>
                                <th>Asst 2 (25)</th>
                                <th>Midterm (20)</th>
                                <th>Final (30)</th>
                                <th>Total</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($grade = $allGrades->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($grade['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($grade['name']); ?></td>
                                <td><?php echo $grade['assignment1'] ?? '-'; ?></td>
                                <td><?php echo $grade['assignment2'] ?? '-'; ?></td>
                                <td><?php echo $grade['midterm'] ?? '-'; ?></td>
                                <td><?php echo $grade['final_exam'] ?? '-'; ?></td>
                                <td><strong><?php echo $grade['total'] ?? '-'; ?></strong></td>
                                <td>
                                    <?php if ($grade['letter_grade']): ?>
                                        <span class="badge badge-success"><?php echo $grade['letter_grade']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #718096;">--</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">
                         No grades available yet. Your lecturers haven't posted grades.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
