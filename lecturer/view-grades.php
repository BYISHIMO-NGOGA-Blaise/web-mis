<?php
require_once '../includes/config.php';
requireRole('lecturer');

$lecturerId = $_SESSION['user_id'];
$conn = getDBConnection();

$course = null;
$grades = null;

// Get course ID
$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($courseId > 0) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
    $stmt->bind_param("ii", $courseId, $lecturerId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$course) {
        header('Location: grades.php');
        exit();
    }
}

if (isset($_GET['download']) && $courseId > 0 && $course) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($course['code'] ?? 'grades') . '_report.csv"');

    $output = fopen('php://output', 'w');

    $dl = $conn->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as student_name, u.email, u.reg_number,
               g.assignment1, g.assignment2, g.midterm, g.final_exam, g.total, g.letter_grade
        FROM grades g
        JOIN course_enrollments ce ON g.enrollment_id = ce.id
        JOIN users u ON ce.student_id = u.id
        WHERE ce.course_id = ?
        ORDER BY g.total DESC
    ");
    $dl->bind_param("i", $courseId);
    $dl->execute();
    $dlResult = $dl->get_result();

    $gradesData = [];
    $totalStudents = 0;
    $totalGradeSum = 0;
    $gradedCount = 0;
    $highestGrade = 0;
    $lowestGrade = 101;
    $passCount = 0;
    while ($row = $dlResult->fetch_assoc()) {
        $gradesData[] = $row;
        $totalStudents++;
        if ($row['total'] !== null) {
            $totalGradeSum += floatval($row['total']);
            $gradedCount++;
            if (floatval($row['total']) > $highestGrade) $highestGrade = floatval($row['total']);
            if (floatval($row['total']) < $lowestGrade) $lowestGrade = floatval($row['total']);
            if (floatval($row['total']) >= 50) $passCount++;
        }
    }
    $averageGrade = $gradedCount > 0 ? round($totalGradeSum / $gradedCount, 2) : 'N/A';
    $passRate = $gradedCount > 0 ? round(($passCount / $gradedCount) * 100, 1) : 'N/A';

    fputcsv($output, [APP_NAME . ' - Course Grades Report']);
    fputcsv($output, ['Course Code', $course['code'] ?? 'N/A']);
    fputcsv($output, ['Course Name', $course['name'] ?? 'N/A']);
    fputcsv($output, ['Credits', $course['credits'] ?? 'N/A']);
    fputcsv($output, ['Semester', $course['semester'] ?? 'N/A']);
    fputcsv($output, ['Generated', date('F d, Y H:i:s')]);
    fputcsv($output, []);

    fputcsv($output, ['PERFORMANCE SUMMARY']);
    fputcsv($output, ['Total Students', $totalStudents]);
    fputcsv($output, ['Graded', $gradedCount]);
    fputcsv($output, ['Average', is_numeric($averageGrade) ? $averageGrade . '%' : $averageGrade]);
    fputcsv($output, ['Pass Rate', is_numeric($passRate) ? $passRate . '%' : $passRate]);
    fputcsv($output, ['Highest', $highestGrade > 0 ? $highestGrade . '%' : 'N/A']);
    fputcsv($output, ['Lowest', $lowestGrade < 101 ? $lowestGrade . '%' : 'N/A']);
    fputcsv($output, []);

    fputcsv($output, ['DETAILED STUDENT GRADES']);
    fputcsv($output, ['Student Name', 'Email', 'Reg Number', 'Assignment 1 (25)', 'Assignment 2 (25)', 'Midterm (20)', 'Final (30)', 'Total', 'Grade', 'Status']);
    foreach ($gradesData as $g) {
        $status = 'Pending';
        if ($g['total'] !== null) {
            $status = floatval($g['total']) >= 50 ? 'Pass' : 'Fail';
        }
        fputcsv($output, [
            $g['student_name'],
            $g['email'],
            $g['reg_number'] ?? '-',
            $g['assignment1'] ?? '-',
            $g['assignment2'] ?? '-',
            $g['midterm'] ?? '-',
            $g['final_exam'] ?? '-',
            $g['total'] ?? '-',
            $g['letter_grade'] ?? '-',
            $status
        ]);
    }
    fputcsv($output, []);

    if ($gradedCount > 0) {
        fputcsv($output, ['GRADE DISTRIBUTION']);
        fputcsv($output, ['Grade', 'Count', 'Percentage']);
        $gradeRanges = ['A' => [80, 100], 'B' => [70, 79], 'C' => [60, 69], 'D' => [50, 59], 'F' => [0, 49]];
        foreach ($gradeRanges as $letter => $range) {
            $count = 0;
            foreach ($gradesData as $g) {
                if ($g['total'] !== null && floatval($g['total']) >= $range[0] && floatval($g['total']) <= $range[1]) {
                    $count++;
                }
            }
            $pct = $gradedCount > 0 ? round(($count / $gradedCount) * 100, 1) . '%' : '0%';
            fputcsv($output, [$letter, $count, $pct]);
        }
    }

    fclose($output);
    $dl->close();
    $conn->close();
    exit();
}

if ($course) {
    $grades = $conn->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as student_name, u.email,
               g.assignment1, g.assignment2, g.midterm, g.final_exam, g.total, g.letter_grade
        FROM grades g
        JOIN course_enrollments ce ON g.enrollment_id = ce.id
        JOIN users u ON ce.student_id = u.id
        WHERE ce.course_id = ?
        ORDER BY g.total DESC
    ");
    $grades->bind_param("i", $courseId);
    $grades->execute();
    $grades = $grades->get_result();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Grades - <?php echo $course ? htmlspecialchars($course['code']) : ''; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/table-search.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('view-grades'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>View Student Grades</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Lecturer</div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> <?php echo $course ? htmlspecialchars($course['code'] . ' - ' . $course['name']) : 'Course Grades'; ?></h3>
                        <div style="display: flex; gap: 10px;">
                            <a href="?course_id=<?php echo $courseId; ?>&download=1" class="btn btn-primary" style="padding: 8px 20px;"><i class="fas fa-download"></i> Download CSV</a>
                            <a href="grades.php" class="btn btn-info" style="padding: 8px 20px;">Back</a>
                        </div>
                    </div>
                    
                    <div style="padding: 20px;">
                        <div style="padding: 15px 20px 0;">
                            <input type="text" id="searchGrades" placeholder="Search by student name or email..." style="width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:6px; font-size:14px; box-sizing:border-box;">
                        </div>
                        <?php if ($grades && $grades->num_rows > 0): ?>
                        <table class="data-table" id="gradesTable">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Asst 1 (25)</th>
                                    <th>Asst 2 (25)</th>
                                    <th>Midterm (20)</th>
                                    <th>Final (30)</th>
                                    <th>Total</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($grade = $grades->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($grade['student_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($grade['email']); ?></td>
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
                             No grades entered yet for this course.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
<script>initTableSearch('searchGrades', 'gradesTable');</script>
</body>
</html>
