<?php
require_once '../includes/config.php';
requireRole('lecturer');

$lecturerId = $_SESSION['user_id'];
$conn = getDBConnection();

if (isset($_GET['download']) && $courseId > 0 && $course) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $course['code'] . '_grades.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student Name', 'Email', 'Assignment 1', 'Assignment 2', 'Midterm', 'Final Exam', 'Total', 'Letter Grade']);
    $dl = $conn->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as student_name, u.email,
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
    while ($row = $dlResult->fetch_assoc()) {
        fputcsv($output, [
            $row['student_name'], $row['email'],
            $row['assignment1'] ?? '', $row['assignment2'] ?? '',
            $row['midterm'] ?? '', $row['final_exam'] ?? '',
            $row['total'] ?? '', $row['letter_grade'] ?? ''
        ]);
    }
    $dl->close();
    fclose($output);
    $conn->close();
    exit();
}

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
    
    if ($course) {
        // Get all grades for this course
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
    } else {
        header('Location: grades.php');
        exit();
    }
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
