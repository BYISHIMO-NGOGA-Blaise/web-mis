<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

$success = '';
$error = '';
$user = null;

if (isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header('Location: users.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $regNumber = sanitize($_POST['reg_number'] ?? '');
    $dob = sanitize($_POST['date_of_birth'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $nationalId = sanitize($_POST['national_id'] ?? '');
    $emergencyName = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = sanitize($_POST['emergency_contact'] ?? '');
    $roleId = intval($_POST['role_id'] ?? 4);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $departmentId = intval($_POST['department_id'] ?? 0);

    $errors = [];

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }

    if (empty($firstName)) $errors[] = "First name is required.";
    if (empty($lastName)) $errors[] = "Last name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";

    if (!empty($password) && strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkEmail->bind_param("si", $email, $userId);
    $checkEmail->execute();
    if ($checkEmail->get_result()->num_rows > 0) {
        $errors[] = "Email already exists.";
    }
    $checkEmail->close();

    if (!empty($regNumber)) {
        $checkReg = $conn->prepare("SELECT id FROM users WHERE reg_number = ? AND id != ?");
        $checkReg->bind_param("si", $regNumber, $userId);
        $checkReg->execute();
        if ($checkReg->get_result()->num_rows > 0) {
            $errors[] = "Registration number already exists.";
        }
        $checkReg->close();
    }

    if (empty($errors)) {
        $deptValue = $departmentId > 0 ? $departmentId : null;

        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE users SET email=?, password_hash=?, first_name=?, last_name=?, phone=?, reg_number=?, date_of_birth=?, gender=?, address=?, national_id=?, emergency_contact_name=?, emergency_contact=?, role_id=?, is_active=?, department_id=? WHERE id=?"
            );
            $stmt->bind_param("ssssssssssssiisi", $email, $passwordHash, $firstName, $lastName, $phone, $regNumber, $dob, $gender, $address, $nationalId, $emergencyName, $emergencyPhone, $roleId, $isActive, $deptValue, $userId);
        } else {
            $stmt = $conn->prepare(
                "UPDATE users SET email=?, first_name=?, last_name=?, phone=?, reg_number=?, date_of_birth=?, gender=?, address=?, national_id=?, emergency_contact_name=?, emergency_contact=?, role_id=?, is_active=?, department_id=? WHERE id=?"
            );
            $stmt->bind_param("sssssssssssiisi", $email, $firstName, $lastName, $phone, $regNumber, $dob, $gender, $address, $nationalId, $emergencyName, $emergencyPhone, $roleId, $isActive, $deptValue, $userId);
        }

        if ($stmt->execute()) {
            $success = "User updated successfully!";
            $stmt2 = $conn->prepare("SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
            $stmt2->bind_param("i", $userId);
            $stmt2->execute();
            $user = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        } else {
            $error = "Failed to update user.";
        }
        $stmt->close();
    } else {
        $error = implode(" ", $errors);
    }
}

$roles = $conn->query("SELECT * FROM roles ORDER BY id");
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('users'); ?>

        <main class="main-content">
            <nav class="top-nav">
                <h1>Edit User</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>

            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Edit User: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <div>
                            <a href="view-user.php?id=<?php echo $userId; ?>" class="btn btn-primary" style="padding: 8px 20px;"> View Profile</a>
                            <a href="users.php" class="btn btn-primary" style="padding: 8px 20px;"> Back to Users</a>
                        </div>
                    </div>

                    <div style="padding: 30px;">
                        <?php if ($success): ?>
                            <div class="alert alert-success"> <?php echo $success; ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"> <?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($user['first_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($user['last_name']); ?>">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>Email Address *</label>
                                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($user['email']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Registration Number</label>
                                    <input type="text" name="reg_number" class="form-control" value="<?php echo htmlspecialchars($user['reg_number'] ?? ''); ?>">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gender" class="form-control">
                                        <option value="">-- Select --</option>
                                        <option value="Male" <?php echo (($user['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (($user['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (($user['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>National ID</label>
                                    <input type="text" name="national_id" class="form-control" value="<?php echo htmlspecialchars($user['national_id'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Address</label>
                                <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>Emergency Contact Name</label>
                                    <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Emergency Contact Phone</label>
                                    <input type="tel" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($user['emergency_contact'] ?? ''); ?>">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>New Password (leave empty to keep)</label>
                                    <input type="password" name="password" class="form-control" minlength="8" placeholder="Enter new password">
                                </div>
                                <div class="form-group">
                                    <label>Role *</label>
                                    <select name="role_id" class="form-control" required>
                                        <?php $roles->data_seek(0); while ($role = $roles->fetch_assoc()): ?>
                                            <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $user['role_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucfirst($role['name'])); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Department</label>
                                    <select name="department_id" class="form-control">
                                        <option value="0">-- None --</option>
                                        <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo ($user['department_id'] == $d['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($d['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                    <strong>Active User</strong>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;"> Update User</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

