<?php
require_once '../includes/config.php';
requireRole('hod');

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

$success = '';
$error = '';

// Get HOD's department
$deptStmt = $conn->prepare("SELECT * FROM departments WHERE hod_id = ?");
$deptStmt->bind_param("i", $userId);
$deptStmt->execute();
$department = $deptStmt->get_result()->fetch_assoc();
$deptStmt->close();

if (!$department) {
    $error = "You are not assigned to manage any department.";
}

$deptId = $department['id'] ?? 0;

// Handle drop enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_enrollment']) && $deptId > 0) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $enrollmentId = intval($_POST['drop_enrollment']);
        $stmt = $conn->prepare("
            DELETE ce FROM course_enrollments ce
            JOIN courses c ON ce.course_id = c.id
            WHERE ce.id = ? AND c.department_id = ?
        ");
        $stmt->bind_param("ii", $enrollmentId, $deptId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $success = "Enrollment dropped successfully.";
        } else {
            $error = "Unable to drop enrollment.";
        }
        $stmt->close();
    }
}

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student']) && $deptId > 0) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $studentId = intval($_POST['student_id'] ?? 0);
        $courseId = intval($_POST['course_id'] ?? 0);

        if ($studentId <= 0 || $courseId <= 0) {
            $error = "Please select both student and course.";
        } else {
            // Verify course belongs to this department
            $courseCheck = $conn->prepare("SELECT id FROM courses WHERE id = ? AND department_id = ?");
            $courseCheck->bind_param("ii", $courseId, $deptId);
            $courseCheck->execute();
            if ($courseCheck->get_result()->num_rows === 0) {
                $error = "Course does not belong to your department.";
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
            $courseCheck->close();
        }
    }
}

if ($deptId > 0) {
    // Get students in this department
    $students = $conn->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name, email
        FROM users
        WHERE role_id = 4 AND department_id = ?
        ORDER BY first_name, last_name
    ");
    $students->bind_param("i", $deptId);
    $students->execute();
    $studentList = $students->get_result();
    $students->close();

    // Get courses in this department
    $courses = $conn->prepare("
        SELECT c.id, c.code, c.name
        FROM courses c
        WHERE c.department_id = ?
        ORDER BY c.code
    ");
    $courses->bind_param("i", $deptId);
    $courses->execute();
    $courseList = $courses->get_result();
    $courses->close();

    // Get current enrollments for department courses
    $enrollments = $conn->prepare("
        SELECT ce.id, CONCAT(u.first_name, ' ', u.last_name) as student_name, u.email,
               c.code, c.name as course_name, ce.enrollment_date
        FROM course_enrollments ce
        JOIN users u ON ce.student_id = u.id
        JOIN courses c ON ce.course_id = c.id
        WHERE ce.status = 'enrolled' AND c.department_id = ?
        ORDER BY ce.enrollment_date DESC
    ");
    $enrollments->bind_param("i", $deptId);
    $enrollments->execute();
    $enrollmentList = $enrollments->get_result();
    $enrollments->close();
} else {
    $studentList = null;
    $courseList = null;
    $enrollmentList = null;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment - HOD Portal</title>
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
                    <div class="user-role">Head of Department</div>
                </div>
            </nav>

            <div class="page-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"> <?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"> <?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($deptId > 0): ?>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3> Enroll Student in Course (<?php echo htmlspecialchars($department['name']); ?>)</h3>
                    </div>
                    <div style="padding: 30px;">
                        <form method="POST" action="" style="max-width: 600px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>Select Student *</label>
                                <select name="student_id" id="studentSelect" class="form-control" required>
                                    <option value="0">-- Choose Student --</option>
                                    <?php if ($studentList && $studentList->num_rows > 0): ?>
                                        <?php while ($student = $studentList->fetch_assoc()): ?>
                                            <option value="<?php echo $student['id']; ?>">
                                                <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>Select Course *</label>
                                <select name="course_id" id="courseSelect" class="form-control" required>
                                    <option value="0">-- Choose Course --</option>
                                    <?php if ($courseList && $courseList->num_rows > 0): ?>
                                        <?php while ($course = $courseList->fetch_assoc()): ?>
                                            <option value="<?php echo $course['id']; ?>">
                                                <?php echo htmlspecialchars($course['code']); ?> - <?php echo htmlspecialchars($course['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <button type="submit" name="enroll_student" class="btn btn-primary" style="padding: 12px 30px;"> Enroll Student</button>
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
                    <?php if ($enrollmentList && $enrollmentList->num_rows > 0): ?>
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
                            <?php while ($enrollment = $enrollmentList->fetch_assoc()): ?>
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
                    <p style="padding: 30px; color: #718096; text-align: center;">No enrollments in your department yet.</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h3> No Department Assigned</h3>
                    </div>
                    <div style="padding: 30px;">
                        <p style="color: #718096; font-size: 16px;">
                            You are not assigned to manage any department. Please contact the admin.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
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

