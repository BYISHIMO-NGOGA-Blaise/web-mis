<?php
require_once '../includes/config.php';
requireRole('hod');

$userId = $_SESSION['user_id'];
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

        $errors = [];
        if (empty($firstName)) $errors[] = "First name is required.";
        if (empty($lastName)) $errors[] = "Last name is required.";
        if (empty($phone)) $errors[] = "Phone number is required.";

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, gender = ?, address = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $firstName, $lastName, $phone, $dob, $gender, $address, $userId);
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
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!password_verify($currentPassword, $result['password_hash'])) {
                $error = "Current password is incorrect.";
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->bind_param("si", $newHash, $userId);
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

// Fetch current user data
$stmt = $conn->prepare("SELECT u.*, d.name as dept_name, d.code as dept_code FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get department info if HOD manages one
$deptStmt = $conn->prepare("SELECT * FROM departments WHERE hod_id = ?");
$deptStmt->bind_param("i", $userId);
$deptStmt->execute();
$department = $deptStmt->get_result()->fetch_assoc();
$deptStmt->close();

// Count department stats
$totalCourses = 0;
$totalStudents = 0;
$lecturerCount = 0;
if ($department) {
    $deptId = $department['id'];
    $r1 = $conn->query("SELECT COUNT(*) as t FROM courses WHERE department_id = $deptId");
    $totalCourses = $r1->fetch_assoc()['t'];
    $r2 = $conn->query("SELECT COUNT(DISTINCT ce.student_id) as t FROM course_enrollments ce JOIN courses c ON ce.course_id = c.id WHERE c.department_id = $deptId AND ce.status = 'enrolled'");
    $totalStudents = $r2->fetch_assoc()['t'];
    $r3 = $conn->query("SELECT COUNT(DISTINCT c.lecturer_id) as t FROM courses c WHERE c.department_id = $deptId AND c.lecturer_id IS NOT NULL");
    $lecturerCount = $r3->fetch_assoc()['t'];
}

$conn->close();

$labelStyle = 'font-weight:600; color:#1a365d; display:block; margin-bottom:6px; font-size:13px;';
$disabledStyle = 'background:#f7fafc; cursor:not-allowed;';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - HOD Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('profile'); ?>

        <main class="main-content">
            <nav class="top-nav">
                <h1>My Profile</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
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
                                            <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($user['first_name']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Last Name *</label>
                                            <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($user['last_name']); ?>">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Email Address</label>
                                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="<?php echo $disabledStyle; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">National ID</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['national_id'] ?? 'N/A'); ?>" disabled style="<?php echo $disabledStyle; ?>">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Phone Number *</label>
                                            <input type="tel" name="phone" class="form-control" required value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Date of Birth</label>
                                            <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Gender</label>
                                            <select name="gender" class="form-control">
                                                <option value="">-- Select --</option>
                                                <option value="Male" <?php echo (($user['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo (($user['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                                <option value="Other" <?php echo (($user['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label style="<?php echo $labelStyle; ?>">Department</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(($user['dept_name'] ?? 'None') . ($user['dept_code'] ? ' (' . $user['dept_code'] . ')' : '')); ?>" disabled style="<?php echo $disabledStyle; ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label style="<?php echo $labelStyle; ?>">Address</label>
                                        <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                    </div>

                                    <button type="submit" name="update_profile" class="btn btn-primary" style="margin-top: 5px;"> Save Changes</button>
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
                                        <div class="metric-value"><?php echo $totalCourses; ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Lecturers</h4>
                                        <div class="metric-value"><?php echo $lecturerCount; ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <h4>Students</h4>
                                        <div class="metric-value"><?php echo $totalStudents; ?></div>
                                    </div>
                                </div>
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                        <span style="color:#718096; font-size:13px;">Role</span>
                                        <span style="font-weight:600; font-size:13px;">Head of Department</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                        <span style="color:#718096; font-size:13px;">Status</span>
                                        <span style="font-weight:600; font-size:13px; color: <?php echo $user['is_active'] ? '#48bb78' : '#f56565'; ?>;"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span style="color:#718096; font-size:13px;">Member Since</span>
                                        <span style="font-weight:600; font-size:13px;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
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

