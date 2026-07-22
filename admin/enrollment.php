<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

$success = '';
$error = '';

// Handle drop enrollment via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_enrollment'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $enrollmentId = intval($_POST['drop_enrollment']);
        $stmt = $conn->prepare("DELETE FROM course_enrollments WHERE id = ?");
        $stmt->bind_param("i", $enrollmentId);
        $stmt->execute();
        $stmt->close();
        header('Location: enrollment.php');
        exit();
    }
}

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $studentId = intval($_POST['student_id'] ?? 0);
        $courseId = intval($_POST['course_id'] ?? 0);
        
        if ($studentId <= 0 || $courseId <= 0) {
            $error = "Please select both student and course.";
        } else {
            $check = $conn->prepare("SELECT id FROM course_enrollments WHERE student_id = ? AND course_id = ?");
            $check->bind_param("ii", $studentId, $courseId);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                $error = "Student is already enrolled in this course.";
            } else {
                $enroll = $conn->prepare("INSERT INTO course_enrollments (student_id, course_id) VALUES (?, ?)");
                $enroll->bind_param("ii", $studentId, $courseId);
                
                if ($enroll->execute()) {
                    $success = "Student enrolled successfully!";
                } else {
                    $error = "Failed to enroll student.";
                }
                $enroll->close();
            }
            $check->close();
        }
    }
}

// Get all students
$students = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, email
    FROM users
    WHERE role_id = 4
    ORDER BY first_name, last_name
");

// Get all courses
$courses = $conn->query("
    SELECT c.id, c.code, c.name, d.name as dept_name
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    ORDER BY c.code
");

// Get current enrollments
$enrollments = $conn->query("
    SELECT ce.id, CONCAT(u.first_name, ' ', u.last_name) as student_name, u.email,
           c.code, c.name as course_name, ce.enrollment_date
    FROM course_enrollments ce
    JOIN users u ON ce.student_id = u.id
    JOIN courses c ON ce.course_id = c.id
    WHERE ce.status = 'enrolled'
    ORDER BY ce.enrollment_date DESC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Enrollment - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/table-search.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('enrollment'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Student Course Enrollment</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Enroll Student in Course</h3>
                    </div>
                    
                    <div style="padding: 30px;">
                        <?php if ($success): ?>
                            <div class="alert alert-success"> <?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"> <?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" style="max-width: 600px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>Select Student *</label>
                                <select name="student_id" id="studentSelect" class="form-control" required>
                                    <option value="0">-- Choose Student --</option>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>Select Course *</label>
                                <select name="course_id" id="courseSelect" class="form-control" required>
                                    <option value="0">-- Choose Course --</option>
                                    <?php while ($course = $courses->fetch_assoc()): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['code']); ?> - <?php echo htmlspecialchars($course['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                                 Enroll Student
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3> Current Enrollments</h3>
                    </div>
                    <div style="padding: 15px 20px 0;">
                        <input type="text" id="searchEnroll" placeholder="Search by student name, email, or course..." style="width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <?php if ($enrollments->num_rows > 0): ?>
                    <table class="data-table" id="enrollTable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Email</th>
                                <th>Course</th>
                                <th>Enrolled Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($enrollment = $enrollments->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($enrollment['student_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($enrollment['email']); ?></td>
                                <td>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($enrollment['code']); ?></span>
                                    <span style="margin-left: 5px;"><?php echo htmlspecialchars($enrollment['course_name']); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></td>
                                <td>
                                    <form method="POST" action="" style="display:inline; margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="drop_enrollment" value="<?php echo $enrollment['id']; ?>">
                                        <button type="submit" class="badge badge-warning" style="border:none; background:none; cursor:pointer; padding:0;" onclick="return confirm('Drop this student from the course?')"> Drop</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">
                        No enrollments yet.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
<script>
initTableSearch('searchEnroll', 'enrollTable');
initSearchableSelect('studentSelect');
initSearchableSelect('courseSelect');
</script>
</body>
</html>
