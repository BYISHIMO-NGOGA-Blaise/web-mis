<?php
require_once '../includes/config.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$conn = getDBConnection();

$success = '';
$error = '';

// Handle course approval request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $courseId = intval($_POST['course_id']);
        
        if ($courseId > 0) {
            $already = $conn->prepare("SELECT id FROM course_enrollments WHERE student_id = ? AND course_id = ? AND status = 'enrolled'");
            $already->bind_param("ii", $studentId, $courseId);
            $already->execute();
            $isEnrolled = $already->get_result()->num_rows > 0;
            $already->close();

            $pending = $conn->prepare("SELECT id FROM course_approvals WHERE student_id = ? AND course_id = ? AND status = 'pending'");
            $pending->bind_param("ii", $studentId, $courseId);
            $pending->execute();
            $hasPending = $pending->get_result()->num_rows > 0;
            $pending->close();

            if ($isEnrolled) {
                $error = "You are already enrolled in this course.";
            } elseif ($hasPending) {
                $error = "You already have a pending request for this course.";
            } else {
                $req = $conn->prepare("INSERT INTO course_approvals (student_id, course_id, requested_by, status) VALUES (?, ?, ?, 'pending')");
                $req->bind_param("iii", $studentId, $courseId, $studentId);
                if ($req->execute()) {
                    $success = "Enrollment request submitted. Waiting for HOD approval.";
                } else {
                    $error = "Failed to submit request.";
                }
                $req->close();
            }
        }
    }
}

// Get all available courses (excluding already enrolled or pending)
$courses = $conn->prepare("
    SELECT c.*, d.name as dept_name, CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    WHERE c.id NOT IN (
        SELECT course_id FROM course_enrollments WHERE student_id = ? AND status = 'enrolled'
        UNION
        SELECT course_id FROM course_approvals WHERE student_id = ? AND status = 'pending'
    )
    ORDER BY c.code
");
$courses->bind_param("ii", $studentId, $studentId);
$courses->execute();
$availableCourses = $courses->get_result();
$courses->close();

// Get currently enrolled courses
$enrolled = $conn->prepare("
    SELECT c.id, c.code, c.name, c.credits, d.name as dept_name, CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
    FROM courses c
    JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    WHERE ce.student_id = ? AND ce.status = 'enrolled'
    ORDER BY c.code
");
$enrolled->bind_param("i", $studentId);
$enrolled->execute();
$myCourses = $enrolled->get_result();
$enrolled->close();

// Get my approval requests
$requests = $conn->prepare("
    SELECT ca.*, c.code, c.name as course_name, c.credits, d.name as dept_name,
           CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
    FROM course_approvals ca
    JOIN courses c ON ca.course_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users u ON ca.reviewed_by = u.id
    WHERE ca.student_id = ?
    ORDER BY ca.created_at DESC
");
$requests->bind_param("i", $studentId);
$requests->execute();
$myRequests = $requests->get_result();
$requests->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Courses - Student</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/table-search.js"></script>
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
                <li><a href="register-course.php" class="active"> Register Courses</a></li>
                <li><a href="schedule.php"> My Schedule</a></li>
                <li><a href="grades.php"> Results</a></li>
                <li><a href="financial.php"> Financial Portal</a></li>
                <li><a href="attendance.php"> Attendance</a></li>
                <li><a href="profile.php"> My Profile</a></li>
                <li><a href="../logout.php"> Logout</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Course Registration</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Student</div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Register for New Courses</h3>
                    </div>
                    
                    <div style="padding: 20px;">
                        <?php if ($success): ?>
                            <div class="alert alert-success"> <?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"> <?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <div style="padding: 15px 20px 0;">
                            <input type="text" id="searchRegister" placeholder="Search by code, name, or department..." style="width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:6px; font-size:14px; box-sizing:border-box;">
                        </div>
                        <?php if ($availableCourses->num_rows > 0): ?>
                        <table class="data-table" id="registerTable">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Course Name</th>
                                    <th>Department</th>
                                    <th>Lecturer</th>
                                    <th>Credits</th>
                                    <th>Fee</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($course = $availableCourses->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['dept_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($course['lecturer_name']): ?>
                                            <?php echo htmlspecialchars($course['lecturer_name']); ?>
                                        <?php else: ?>
                                            <em style="color: #718096;">Not Assigned</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $course['credits']; ?></td>
                                    <td><?php echo number_format(calculateCoursePrice($course['credits'])); ?> FRW</td>
                                    <td>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" name="register" class="badge badge-primary" style="border: none; cursor: pointer;"> Request Enrollment</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p style="padding: 30px; color: #718096; text-align: center;">
                             No courses available for registration.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3> My Enrollment Requests</h3>
                    </div>
                    <?php if ($myRequests->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Department</th>
                                <th>Credits</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($req = $myRequests->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($req['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($req['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($req['dept_name'] ?? 'N/A'); ?></td>
                                <td><?php echo $req['credits']; ?></td>
                                <td>
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <span class="badge badge-warning" style="background: #ed8936;"> Pending</span>
                                    <?php elseif ($req['status'] === 'approved'): ?>
                                        <span class="badge badge-success" style="background: #48bb78;"> Approved</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="background: #f56565;"> Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($req['reviewer_name'] ?? '-'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">
                         You haven't submitted any enrollment requests yet.
                    </p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3> My Enrolled Courses</h3>
                    </div>
                    <?php if ($myCourses->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Department</th>
                                <th>Lecturer</th>
                                <th>Credits</th>
                                <th>Fee</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = $myCourses->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['name']); ?></td>
                                <td><?php echo htmlspecialchars($course['dept_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($course['lecturer_name']): ?>
                                        <?php echo htmlspecialchars($course['lecturer_name']); ?>
                                    <?php else: ?>
                                        <em style="color: #718096;">Not Assigned</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $course['credits']; ?></td>
                                <td><?php echo number_format(calculateCoursePrice($course['credits'])); ?> FRW</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">
                         You haven't enrolled in any courses yet.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
<script>initTableSearch('searchRegister', 'registerTable');</script>
</body>
</html>
