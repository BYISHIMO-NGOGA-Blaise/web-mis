<?php
require_once '../includes/config.php';
requireRole('lecturer');

$lecturerId = $_SESSION['user_id'];
$conn = getDBConnection();

$success = '';
$error = '';
$course = null;

// Get course ID
$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($courseId > 0) {
    // Verify this course belongs to the lecturer
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

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $courseId > 0) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $studentId = intval($_POST['student_id'] ?? 0);
        $assignment1 = floatval($_POST['assignment1'] ?? 0);
        $assignment2 = floatval($_POST['assignment2'] ?? 0);
        $midterm = floatval($_POST['midterm'] ?? 0);
        $finalExam = floatval($_POST['final_exam'] ?? 0);
        
        $total = $assignment1 + $assignment2 + $midterm + $finalExam;
        
        $letterGrade = '';
        if ($total >= 90) $letterGrade = 'A';
        elseif ($total >= 80) $letterGrade = 'B';
        elseif ($total >= 70) $letterGrade = 'C';
        elseif ($total >= 60) $letterGrade = 'D';
        else $letterGrade = 'F';
        
        // Get enrollment ID
        $enrollCheck = $conn->prepare("SELECT id FROM course_enrollments WHERE student_id = ? AND course_id = ?");
        $enrollCheck->bind_param("ii", $studentId, $courseId);
        $enrollCheck->execute();
        $enrollment = $enrollCheck->get_result()->fetch_assoc();
        $enrollCheck->close();
        
        if ($enrollment) {
            $enrollmentId = $enrollment['id'];
            
            // Check if grade exists
            $gradeCheck = $conn->prepare("SELECT id FROM grades WHERE enrollment_id = ?");
            $gradeCheck->bind_param("i", $enrollmentId);
            $gradeCheck->execute();
            
            if ($gradeCheck->get_result()->num_rows > 0) {
                // Update
                $update = $conn->prepare("UPDATE grades SET assignment1=?, assignment2=?, midterm=?, final_exam=?, total=?, letter_grade=? WHERE enrollment_id=?");
                $update->bind_param("ddddssi", $assignment1, $assignment2, $midterm, $finalExam, $total, $letterGrade, $enrollmentId);
                $update->execute();
                $update->close();
            } else {
                // Insert
                $insert = $conn->prepare("INSERT INTO grades (enrollment_id, assignment1, assignment2, midterm, final_exam, total, letter_grade) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert->bind_param("iddddis", $enrollmentId, $assignment1, $assignment2, $midterm, $finalExam, $total, $letterGrade);
                $insert->execute();
                $insert->close();
            }
            $gradeCheck->close();
            
            $success = "Grades saved for student!";
        }
    }
}

// Get enrolled students
$students = null;
if ($courseId > 0) {
    $stmt = $conn->prepare("
        SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email
        FROM users u
        JOIN course_enrollments ce ON u.id = ce.student_id
        WHERE ce.course_id = ? AND ce.status = 'enrolled'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->bind_param("i", $courseId);
    $stmt->execute();
    $students = $stmt->get_result();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Grades - <?php echo $course ? htmlspecialchars($course['code']) : ''; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('enter-grades'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Enter Grades</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Lecturer</div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> <?php echo $course ? htmlspecialchars($course['code'] . ' - ' . $course['name']) : 'Select Course'; ?></h3>
                        <a href="grades.php" class="btn btn-primary" style="padding: 8px 20px;"> Back</a>
                    </div>
                    
                    <div style="padding: 30px;">
                        <?php if ($success): ?>
                            <div class="alert alert-success"> <?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"> <?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($course): ?>
                        <form method="POST" action="" style="max-width: 600px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Select Student *</label>
                                <select name="student_id" class="form-control" required>
                                    <option value="0">-- Choose Student --</option>
                                    <?php if ($students): while ($student = $students->fetch_assoc()): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['name']); ?>
                                        </option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>Assignment 1 (25 marks)</label>
                                    <input type="number" name="assignment1" class="form-control" min="0" max="25" step="0.1" required>
                                </div>
                                <div class="form-group">
                                    <label>Assignment 2 (25 marks)</label>
                                    <input type="number" name="assignment2" class="form-control" min="0" max="25" step="0.1" required>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>Midterm (20 marks)</label>
                                    <input type="number" name="midterm" class="form-control" min="0" max="20" step="0.1" required>
                                </div>
                                <div class="form-group">
                                    <label>Final Exam (30 marks)</label>
                                    <input type="number" name="final_exam" class="form-control" min="0" max="30" step="0.1" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                                 Save Grades
                            </button>
                        </form>
                        <?php else: ?>
                        <p style="color: #718096; text-align: center; padding: 30px;">
                            No course selected.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
