<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$userId = intval($_GET['id']);

$stmt = $conn->prepare("SELECT u.*, d.name as dept_name, d.code as dept_code, r.name as role_name FROM users u LEFT JOIN departments d ON u.department_id = d.id LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: users.php');
    exit();
}

$stats = ['courses' => 0, 'enrolled' => 0, 'grades' => 0, 'payments' => 0, 'total_paid' => 0, 'attendance_rate' => 'N/A'];

if ($user['role_id'] == 4) {
    // Student stats
    $s1 = $conn->prepare("SELECT COUNT(*) as t FROM course_enrollments WHERE student_id = ? AND status = 'enrolled'");
    $s1->bind_param("i", $userId);
    $s1->execute();
    $stats['enrolled'] = $s1->get_result()->fetch_assoc()['t'];
    $s1->close();

    $s2 = $conn->prepare("SELECT COUNT(*) as t FROM grades g JOIN course_enrollments ce ON g.enrollment_id = ce.id WHERE ce.student_id = ?");
    $s2->bind_param("i", $userId);
    $s2->execute();
    $stats['grades'] = $s2->get_result()->fetch_assoc()['t'];
    $s2->close();

    $s3 = $conn->prepare("SELECT IFNULL(SUM(amount), 0) as t FROM payments WHERE student_id = ?");
    $s3->bind_param("i", $userId);
    $s3->execute();
    $stats['total_paid'] = $s3->get_result()->fetch_assoc()['t'];
    $s3->close();

    $s4 = $conn->prepare("
        SELECT 
            IFNULL(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) as present,
            IFNULL(COUNT(a.id), 0) as total
        FROM attendance a
        JOIN course_enrollments ce ON a.enrollment_id = ce.id
        WHERE ce.student_id = ?
    ");
    $s4->bind_param("i", $userId);
    $s4->execute();
    $att = $s4->get_result()->fetch_assoc();
    $stats['attendance_rate'] = $att['total'] > 0 ? round(($att['present'] / $att['total']) * 100, 1) . '%' : 'N/A';
    $s4->close();

    // Get enrolled courses
    $coursesStmt = $conn->prepare("
        SELECT c.code, c.name, c.credits, g.total, g.letter_grade, ce.status
        FROM course_enrollments ce
        JOIN courses c ON ce.course_id = c.id
        LEFT JOIN grades g ON ce.id = g.enrollment_id
        WHERE ce.student_id = ?
        ORDER BY c.code
    ");
    $coursesStmt->bind_param("i", $userId);
    $coursesStmt->execute();
    $userCourses = $coursesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $coursesStmt->close();
} elseif ($user['role_id'] == 3) {
    // Lecturer stats
    $s1 = $conn->prepare("SELECT COUNT(*) as t FROM courses WHERE lecturer_id = ?");
    $s1->bind_param("i", $userId);
    $s1->execute();
    $stats['courses'] = $s1->get_result()->fetch_assoc()['t'];
    $s1->close();

    $s2 = $conn->prepare("SELECT COUNT(DISTINCT ce.student_id) as t FROM course_enrollments ce JOIN courses c ON ce.course_id = c.id WHERE c.lecturer_id = ? AND ce.status = 'enrolled'");
    $s2->bind_param("i", $userId);
    $s2->execute();
    $stats['enrolled'] = $s2->get_result()->fetch_assoc()['t'];
    $s2->close();

    $coursesStmt = $conn->prepare("SELECT code, name, credits, semester FROM courses WHERE lecturer_id = ? ORDER BY code");
    $coursesStmt->bind_param("i", $userId);
    $coursesStmt->execute();
    $userCourses = $coursesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $coursesStmt->close();
} elseif ($user['role_id'] == 2) {
    // HOD stats
    $dept = $conn->prepare("SELECT * FROM departments WHERE hod_id = ?");
    $dept->bind_param("i", $userId);
    $dept->execute();
    $hodDept = $dept->get_result()->fetch_assoc();
    $dept->close();

    if ($hodDept) {
        $dId = $hodDept['id'];
        $r1 = $conn->prepare("SELECT COUNT(*) as t FROM courses WHERE department_id = ?");
        $r1->bind_param("i", $dId);
        $r1->execute();
        $stats['courses'] = $r1->get_result()->fetch_assoc()['t'];
        $r1->close();
        $r2 = $conn->prepare("SELECT COUNT(DISTINCT ce.student_id) as t FROM course_enrollments ce JOIN courses c ON ce.course_id = c.id WHERE c.department_id = ? AND ce.status = 'enrolled'");
        $r2->bind_param("i", $dId);
        $r2->execute();
        $stats['enrolled'] = $r2->get_result()->fetch_assoc()['t'];
        $r2->close();
    }
    $userCourses = [];
}

$conn->close();

$labelStyle = 'color:#718096; font-size:13px; margin-bottom:4px;';
$valueStyle = 'font-weight:600; color:#1a365d; font-size:14px;';
$disabledStyle = 'background:#f7fafc; cursor:not-allowed;';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('users'); ?>

        <main class="main-content">
            <nav class="top-nav">
                <h1>User Profile</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>

            <div class="page-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <div style="width:60px; height:60px; border-radius:50%; background:linear-gradient(135deg, #667eea, #764ba2); display:flex; align-items:center; justify-content:center; color:white; font-size:24px; font-weight:700;">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h2 style="margin:0; color:#1a365d;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                            <span style="background:<?php echo $user['is_active'] ? '#c6f6d5; color:#276749' : '#fed7d7; color:#c53030'; ?>; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600;">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <span style="background:#e2e8f0; color:#4a5568; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; margin-left:5px;">
                                <?php echo htmlspecialchars(ucfirst($user['role_name'])); ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <a href="edit-user.php?id=<?php echo $userId; ?>" class="btn btn-primary" style="padding: 8px 20px;"> Edit User</a>
                        <a href="users.php" class="btn btn-primary" style="padding: 8px 20px;"> Back to Users</a>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <div>
                        <div class="card" style="margin-bottom:20px;">
                            <div class="card-header">
                                <h3> Personal Information</h3>
                            </div>
                            <div style="padding:20px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap:20px;">
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">First Name</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['first_name']); ?></div>
                                    </div>
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Last Name</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['last_name']); ?></div>
                                    </div>
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Email</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; margin-top:15px;">
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Phone</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Date of Birth</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo $user['date_of_birth'] ? date('M d, Y', strtotime($user['date_of_birth'])) : 'N/A'; ?></div>
                                    </div>
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Gender</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['gender'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; margin-top:15px;">
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Registration No.</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['reg_number'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">National ID</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['national_id'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Address</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <?php if ($user['role_id'] == 4): ?>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:15px;">
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Emergency Contact Name</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['emergency_contact_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Emergency Contact Phone</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['emergency_contact'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; margin-top:15px; padding-top:15px; border-top:1px solid #e2e8f0;">
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Department</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars($user['dept_name'] ?? 'Unassigned'); ?></div>
                                    </div>
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Role</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo htmlspecialchars(ucfirst($user['role_name'])); ?></div>
                                    </div>
                                    <div>
                                        <div style="<?php echo $labelStyle; ?>">Member Since</div>
                                        <div style="<?php echo $valueStyle; ?>"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($userCourses)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3><?php echo ($user['role_id'] == 4) ? ' Enrolled Courses' : (($user['role_id'] == 3) ? ' Courses Teaching' : ' Department Courses'); ?></h3>
                            </div>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Course Name</th>
                                        <th>Credits</th>
                                        <?php if ($user['role_id'] == 4): ?>
                                            <th>Grade</th>
                                            <th>Status</th>
                                        <?php elseif ($user['role_id'] == 3): ?>
                                            <th>Semester</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userCourses as $c): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($c['code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($c['name']); ?></td>
                                        <td><?php echo $c['credits']; ?></td>
                                        <?php if ($user['role_id'] == 4): ?>
                                            <td>
                                                <?php if ($c['letter_grade']): ?>
                                                    <span class="badge badge-success"><?php echo $c['letter_grade']; ?></span>
                                                    <strong style="margin-left:5px;"><?php echo $c['total']; ?></strong>
                                                <?php else: ?>
                                                    <span style="color:#718096;">Not graded</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge badge-<?php echo $c['status'] === 'enrolled' ? 'success' : ($c['status'] === 'dropped' ? 'danger' : 'info'); ?>"><?php echo ucfirst($c['status']); ?></span></td>
                                        <?php elseif ($user['role_id'] == 3): ?>
                                            <td>Semester <?php echo $c['semester'] ?? 'N/A'; ?></td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <?php if ($user['role_id'] == 4): ?>
                        <div class="card" style="margin-bottom:20px;">
                            <div class="card-header">
                                <h3> Student Summary</h3>
                            </div>
                            <div style="padding:20px;">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                    <div class="metric-card">
                                        <h4>Courses</h4>
                                        <div class="metric-value"><?php echo $stats['enrolled']; ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Graded</h4>
                                        <div class="metric-value"><?php echo $stats['grades']; ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Paid</h4>
                                        <div class="metric-value" style="font-size:14px;"><?php echo number_format($stats['total_paid']); ?> FRW</div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Attendance</h4>
                                        <div class="metric-value" style="font-size:14px;"><?php echo $stats['attendance_rate']; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($user['role_id'] == 3): ?>
                        <div class="card" style="margin-bottom:20px;">
                            <div class="card-header">
                                <h3> Lecturer Summary</h3>
                            </div>
                            <div style="padding:20px;">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                    <div class="metric-card">
                                        <h4>Courses</h4>
                                        <div class="metric-value"><?php echo $stats['courses']; ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Students</h4>
                                        <div class="metric-value"><?php echo $stats['enrolled']; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($user['role_id'] == 2): ?>
                        <div class="card" style="margin-bottom:20px;">
                            <div class="card-header">
                                <h3> HOD Summary</h3>
                            </div>
                            <div style="padding:20px;">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                    <div class="metric-card">
                                        <h4>Courses</h4>
                                        <div class="metric-value"><?php echo $stats['courses']; ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Students</h4>
                                        <div class="metric-value"><?php echo $stats['enrolled']; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <h3> Account Details</h3>
                            </div>
                            <div style="padding:20px;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #e2e8f0;">
                                    <span style="<?php echo $labelStyle; ?>">User ID</span>
                                    <span style="<?php echo $valueStyle; ?>">#<?php echo $user['id']; ?></span>
                                </div>
                                <div style="display:flex; justify-content:space-between; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #e2e8f0;">
                                    <span style="<?php echo $labelStyle; ?>">Status</span>
                                    <span style="color:<?php echo $user['is_active'] ? '#48bb78' : '#f56565'; ?>; font-weight:600; font-size:13px;"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                </div>
                                <div style="display:flex; justify-content:space-between; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #e2e8f0;">
                                    <span style="<?php echo $labelStyle; ?>">Created</span>
                                    <span style="<?php echo $valueStyle; ?>"><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></span>
                                </div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="<?php echo $labelStyle; ?>">Last Updated</span>
                                    <span style="<?php echo $valueStyle; ?>"><?php echo date('M d, Y h:i A', strtotime($user['updated_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

