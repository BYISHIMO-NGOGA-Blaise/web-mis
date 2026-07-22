<?php
require_once '../includes/config.php';
requireRole('hod');

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

$success = '';
$error = '';
$department = null;
$students = null;

$dept = $conn->prepare("SELECT * FROM departments WHERE hod_id = ?");
$dept->bind_param("i", $userId);
$dept->execute();
$department = $dept->get_result()->fetch_assoc();
$dept->close();

if ($department) {
    $deptId = $department['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student'])) {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request token.';
        } else {
            $studentId = intval($_POST['remove_student']);
            $remove = $conn->prepare(
                "DELETE ce FROM course_enrollments ce
                 JOIN courses c ON ce.course_id = c.id
                 WHERE ce.student_id = ? AND c.department_id = ?"
            );
            $remove->bind_param("ii", $studentId, $deptId);

            if ($remove->execute()) {
                $success = 'Student removed from department courses successfully.';
            } else {
                $error = 'Unable to remove student. Please try again.';
            }
            $remove->close();
        }
    }

    $studentQuery = $conn->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email,
        COUNT(DISTINCT ce.course_id) as course_count
        FROM users u
        JOIN course_enrollments ce ON u.id = ce.student_id
        JOIN courses c ON ce.course_id = c.id
        WHERE c.department_id = ? AND u.role_id = 4
        GROUP BY u.id
        ORDER BY u.first_name, u.last_name");
    $studentQuery->bind_param("i", $deptId);
    $studentQuery->execute();
    $students = $studentQuery->get_result();
    $studentQuery->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Students - HOD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/table-search.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('students'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Department Students</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Head of Department</div>
                </div>
            </nav>

            <div class="page-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($department): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3> Students Enrolled in <?php echo htmlspecialchars($department['name']); ?></h3>
                        </div>
                        <div style="padding: 15px 20px 0;">
                            <input type="text" id="searchStudents" placeholder="Search by name or email..." style="width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:6px; font-size:14px; box-sizing:border-box;">
                        </div>
                        <?php if ($students && $students->num_rows > 0): ?>
                            <table class="data-table" id="studentsTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Courses Enrolled</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td><?php echo $student['course_count']; ?></td>
                                            <td>
                                                <form method="POST" action="" style="display:inline; margin:0;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="remove_student" value="<?php echo $student['id']; ?>">
                                                    <button type="submit" class="badge badge-danger" style="border:none; background:none; cursor:pointer; padding:0;" onclick="return confirm('Remove this student from all department courses?')"> Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="padding: 30px; color: #718096; text-align: center;">
                                 No students currently enrolled in this department's courses.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h3> No Department Assigned</h3>
                        </div>
                        <div style="padding: 30px; color: #718096;">
                            You are not assigned to manage any department.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
<script>initTableSearch('searchStudents', 'studentsTable');</script>
</body>
</html>

