<?php
require_once '../includes/config.php';
requireRole('hod');

$lecturerId = $_SESSION['user_id'];
$conn = getDBConnection();

$success = '';
$error = '';
$course = null;

// Get course ID
$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($courseId > 0) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
    $stmt->bind_param("ii", $courseId, $lecturerId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$course) {
        header('Location: attendance.php');
        exit();
    }
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $date = sanitize($_POST['attendance_date'] ?? date('Y-m-d'));
        $statusList = isset($_POST['status']) ? $_POST['status'] : [];
        
        if (!empty($statusList)) {
            foreach ($statusList as $enrollmentId => $status) {
                $enrollmentId = intval($enrollmentId);
                $status = sanitize($status);
                
                // Check if attendance already exists for this student on this date
                $check = $conn->prepare("SELECT id FROM attendance WHERE enrollment_id = ? AND date = ?");
                $check->bind_param("is", $enrollmentId, $date);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing attendance
                    $update = $conn->prepare("UPDATE attendance SET status = ? WHERE enrollment_id = ? AND date = ?");
                    $update->bind_param("sis", $status, $enrollmentId, $date);
                    $update->execute();
                    $update->close();
                } else {
                    // Insert new attendance
                    $insert = $conn->prepare("INSERT INTO attendance (enrollment_id, date, status) VALUES (?, ?, ?)");
                    $insert->bind_param("iss", $enrollmentId, $date, $status);
                    $insert->execute();
                    $insert->close();
                }
                $check->close();
            }
            $success = "Attendance saved successfully!";
        }
    }
}

// Get enrolled students
$students = null;
if ($courseId > 0) {
    $stmt = $conn->prepare("
        SELECT ce.id as enrollment_id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email
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
    <title>Take Attendance - <?php echo $course ? htmlspecialchars($course['code']) : ''; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/table-search.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('take-attendance'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Take Attendance</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Head of Department</div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> <?php echo $course ? htmlspecialchars($course['code'] . ' - ' . $course['name']) : 'Select Course'; ?></h3>
                        <a href="attendance.php" class="btn btn-primary" style="padding: 8px 20px;"> Back</a>
                    </div>
                    
                    <div style="padding: 20px;">
                        <?php if ($success): ?>
                            <div class="alert alert-success"> <?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"> <?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($course && $students && $students->num_rows > 0): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div style="margin-bottom: 20px;">
                                <label style="font-weight: 600; color: #1a365d;">Date:</label>
                                <input type="date" name="attendance_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="max-width: 200px; display: inline-block;">
                            </div>
                            
                            <div style="padding: 15px 20px 0;">
                                <input type="text" id="searchAttendance" placeholder="Search by student name or email..." style="width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:6px; font-size:14px; box-sizing:border-box;">
                            </div>
                            <table class="data-table" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <label style="margin-right: 15px;">
                                                <input type="radio" name="status[<?php echo $student['enrollment_id']; ?>]" value="present" checked style="margin-right: 5px;">
                                                Present
                                            </label>
                                            <label style="margin-right: 15px;">
                                                <input type="radio" name="status[<?php echo $student['enrollment_id']; ?>]" value="absent" style="margin-right: 5px;">
                                                Absent
                                            </label>
                                            <label>
                                                <input type="radio" name="status[<?php echo $student['enrollment_id']; ?>]" value="late" style="margin-right: 5px;">
                                                Late
                                            </label>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            
                            <div style="margin-top: 20px;">
                                <button type="submit" name="submit_attendance" class="btn btn-primary" style="padding: 12px 30px;">
                                     Save Attendance
                                </button>
                            </div>
                        </form>
                        <?php elseif ($course): ?>
                        <p style="padding: 30px; color: #718096; text-align: center;">
                             No students enrolled in this course yet.
                        </p>
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
<script>initTableSearch('searchAttendance', 'attendanceTable');</script>
</body>
</html>

