<?php
require_once '../includes/config.php';
requireRole('student');

$studentId = $_SESSION['user_id'];
$conn = getDBConnection();

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $dob = sanitize($_POST['date_of_birth'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $emergencyName = sanitize($_POST['emergency_contact_name'] ?? '');
        $emergencyPhone = sanitize($_POST['emergency_contact'] ?? '');
        $departmentId = $student['department_id'];

        $errors = [];
        if (empty($firstName)) $errors[] = "First name is required.";
        if (empty($lastName)) $errors[] = "Last name is required.";
        if (empty($phone)) $errors[] = "Phone number is required.";
        if ($departmentId <= 0) $errors[] = "Please select a department.";

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, gender = ?, address = ?, emergency_contact_name = ?, emergency_contact = ? WHERE id = ?");
            $stmt->bind_param("ssssssssi", $firstName, $lastName, $phone, $dob, $gender, $address, $emergencyName, $emergencyPhone, $studentId);
            if ($stmt->execute()) {
                $success = "Profile updated successfully.";
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
            } else {
                $error = "Failed to update profile. Please try again.";
            }
            $stmt->close();
        } else {
            $error = implode(" ", $errors);
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $errors = [];
        if (empty($currentPassword)) $errors[] = "Current password is required.";
        if (empty($newPassword)) $errors[] = "New password is required.";
        elseif (strlen($newPassword) < 8) $errors[] = "New password must be at least 8 characters.";
        if ($newPassword !== $confirmPassword) $errors[] = "New passwords do not match.";

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->bind_param("i", $studentId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!password_verify($currentPassword, $result['password_hash'])) {
                $error = "Current password is incorrect.";
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->bind_param("si", $newHash, $studentId);
                if ($stmt->execute()) {
                    $success = "Password changed successfully.";
                } else {
                    $error = "Failed to change password. Please try again.";
                }
                $stmt->close();
            }
        } else {
            $error = implode(" ", $errors);
        }
    }
}

// Fetch current student data
$stmt = $conn->prepare("SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch departments
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

// Fetch enrolled courses count
$enrollStmt = $conn->prepare("SELECT COUNT(*) as count FROM course_enrollments WHERE student_id = ? AND status = 'enrolled'");
$enrollStmt->bind_param("i", $studentId);
$enrollStmt->execute();
$enrolledCount = $enrollStmt->get_result()->fetch_assoc()['count'];
$enrollStmt->close();

// Fetch total credits
$creditStmt = $conn->prepare("
    SELECT IFNULL(SUM(c.credits), 0) as total 
    FROM courses c 
    JOIN course_enrollments ce ON c.id = ce.course_id 
    WHERE ce.student_id = ? AND ce.status = 'enrolled'
");
$creditStmt->bind_param("i", $studentId);
$creditStmt->execute();
$totalCredits = $creditStmt->get_result()->fetch_assoc()['total'];
$creditStmt->close();

// Fetch total paid
$payStmt = $conn->prepare("SELECT IFNULL(SUM(amount), 0) as total FROM payments WHERE student_id = ?");
$payStmt->bind_param("i", $studentId);
$payStmt->execute();
$totalPaid = $payStmt->get_result()->fetch_assoc()['total'];
$payStmt->close();

$conn->close();

$labelStyle = 'font-weight:600; color:#1a365d; display:block; margin-bottom:6px; font-size:13px;';
$disabledStyle = 'background:#f7fafc; cursor:not-allowed;';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Portal</title>
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
                <li><a href="grades.php"> Results</a></li>
                <li><a href="financial.php"> Financial Portal</a></li>
                <li><a href="attendance.php"> Attendance</a></li>
                <li><a href="profile.php" class="active"> My Profile</a></li>
                <li><a href="../logout.php"> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <nav class="top-nav">
                <h1>My Profile</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                    <div class="user-role">Student</div>
                </div>
            </nav>

            <div class="page-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"> <?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"> <?php echo $error; ?></div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <div>
                        <div class="card" style="margin-bottom: 20px;">
                            <div class="card-header">
                                <h3> Personal Information</h3>
                            </div>
                            <div style="padding: 20px;">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">First Name *</label>
                                            <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($student['first_name']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Last Name *</label>
                                            <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($student['last_name']); ?>">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Registration Number</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['reg_number'] ?? 'N/A'); ?>" disabled style="<?php echo $disabledStyle; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Email Address</label>
                                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" disabled style="<?php echo $disabledStyle; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">National ID</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['national_id'] ?? 'N/A'); ?>" disabled style="<?php echo $disabledStyle; ?>">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Phone Number *</label>
                                            <input type="tel" name="phone" class="form-control" required value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Date of Birth</label>
                                            <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Gender</label>
                                            <select name="gender" class="form-control">
                                                <option value="">-- Select --</option>
                                                <option value="Male" <?php echo (($student['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo (($student['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                                <option value="Other" <?php echo (($student['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Department</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['dept_name'] ?? 'Unassigned'); ?>" disabled style="<?php echo $disabledStyle; ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label style="<?php echo $labelStyle; ?>">Address</label>
                                        <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                                    </div>

                                    <button type="submit" name="update_profile" class="btn btn-primary" style="margin-top: 5px;"> Save Changes</button>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3> Emergency Contact</h3>
                            </div>
                            <div style="padding: 20px;">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>">
                                    <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>">
                                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Contact Person Name</label>
                                            <input type="text" name="emergency_contact_name" class="form-control" placeholder="e.g. Marie Ndayisaba" value="<?php echo htmlspecialchars($student['emergency_contact_name'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Contact Person Phone</label>
                                            <input type="tel" name="emergency_contact" class="form-control" placeholder="e.g. +250 788 654 321" value="<?php echo htmlspecialchars($student['emergency_contact'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <button type="submit" name="update_profile" class="btn btn-primary" style="margin-top: 5px;"> Update Emergency Contact</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="card" style="margin-bottom: 20px;">
                            <div class="card-header">
                                <h3> Account Summary</h3>
                            </div>
                            <div style="padding: 20px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div class="metric-card">
                                        <h4>Courses</h4>
                                        <div class="metric-value"><?php echo $enrolledCount; ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Credits</h4>
                                        <div class="metric-value"><?php echo $totalCredits; ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Paid</h4>
                                        <div class="metric-value" style="font-size:14px;"><?php echo number_format($totalPaid); ?> FRW</div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Status</h4>
                                        <div class="metric-value" style="color: <?php echo $student['is_active'] ? '#48bb78' : '#f56565'; ?>; font-size:14px;">
                                            <?php echo $student['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                        <span style="color:#718096; font-size:13px;">Role</span>
                                        <span style="font-weight:600; font-size:13px;">Student</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                        <span style="color:#718096; font-size:13px;">Member Since</span>
                                        <span style="font-weight:600; font-size:13px;"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3> Change Password</h3>
                            </div>
                            <div style="padding: 20px;">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                                    <div class="form-group">
                                        <label style="<?php echo $labelStyle; ?>">Current Password *</label>
                                        <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                                    </div>

                                    <div class="form-group">
                                        <label style="<?php echo $labelStyle; ?>">New Password *</label>
                                        <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" required>
                                    </div>

                                    <div class="form-group">
                                        <label style="<?php echo $labelStyle; ?>">Confirm New Password *</label>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                                    </div>

                                    <button type="submit" name="change_password" class="btn btn-primary" style="margin-top: 5px;"> Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

