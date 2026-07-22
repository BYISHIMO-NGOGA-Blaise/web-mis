<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();
$success = '';
$errors = [];
$formData = [
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'role_id' => 0,
];

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $formData['email'] = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $formData['first_name'] = sanitize($_POST['first_name'] ?? '');
    $formData['last_name'] = sanitize($_POST['last_name'] ?? '');
    $formData['role_id'] = intval($_POST['role_id'] ?? 0);
    $formData['phone'] = sanitize($_POST['phone'] ?? '');

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }

    if (empty($errors)) {
        if (empty($formData['email']) || empty($password) || empty($formData['first_name']) || empty($formData['last_name']) || $formData['role_id'] <= 0) {
            $errors[] = 'Please fill in all required fields and choose a valid role.';
        } else {
            if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }

            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            }
        }
    }

    if (empty($errors)) {
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $formData['email']);
        $checkEmail->execute();
        $checkEmail->store_result();

        if ($checkEmail->num_rows > 0) {
            $errors[] = 'A user with that email already exists.';
        }
        $checkEmail->close();
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone, role_id) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->bind_param("sssssi", $formData['email'], $passwordHash, $formData['first_name'], $formData['last_name'], $formData['phone'], $formData['role_id']);

        if ($insert->execute()) {
            $success = 'User created successfully!';
            $formData = [
                'email' => '',
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'role_id' => 0,
            ];
        } else {
            $errors[] = 'Unable to create the user. Please try again.';
        }
        $insert->close();
    }
}

// Handle HOD assignment for lecturers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_hod'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $userId = intval($_POST['toggle_hod']);
        $deptId = intval($_POST['dept_id'] ?? 0);
        if ($userId === intval($_SESSION['user_id'])) {
            $errors[] = 'You cannot change your own HOD status.';
        } else {
            // Check if this lecturer is already HOD of another department
            $check = $conn->prepare("SELECT id, name FROM departments WHERE hod_id = ?");
            $check->bind_param("i", $userId);
            $check->execute();
            $current = $check->get_result()->fetch_assoc();
            $check->close();

            if ($current && $current['id'] != $deptId) {
                // Already HOD of another department - unassign first, then assign new
                $unassign = $conn->prepare("UPDATE departments SET hod_id = NULL WHERE hod_id = ?");
                $unassign->bind_param("i", $userId);
                $unassign->execute();
                $unassign->close();
            }

            if ($current && $current['id'] == $deptId) {
                // Unassigning from current department
                $unassign = $conn->prepare("UPDATE departments SET hod_id = NULL WHERE id = ?");
                $unassign->bind_param("i", $deptId);
                $unassign->execute();
                if ($unassign->affected_rows > 0) {
                    $success = 'HOD status removed.';
                } else {
                    $errors[] = 'Unable to remove HOD status.';
                }
                $unassign->close();
                // Clear HOD cache
                unset($_SESSION['_is_hod_cache']);
            } elseif ($deptId > 0) {
                // Check if department already has an HOD
                $dupCheck = $conn->prepare("SELECT hod_id FROM departments WHERE id = ? AND hod_id IS NOT NULL");
                $dupCheck->bind_param("i", $deptId);
                $dupCheck->execute();
                $dupRow = $dupCheck->get_result()->fetch_assoc();
                $dupCheck->close();
                if ($dupRow) {
                    $errors[] = 'This department already has a HOD. Remove the current HOD first.';
                } else {
                    $assign = $conn->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
                    $assign->bind_param("ii", $userId, $deptId);
                    $assign->execute();
                    if ($assign->affected_rows > 0) {
                        $success = 'Lecturer assigned as HOD.';
                    } else {
                        $errors[] = 'Unable to assign HOD.';
                    }
                    $assign->close();
                    // Clear HOD cache
                    unset($_SESSION['_is_hod_cache']);
                }
            } else {
                $errors[] = 'Please select a department.';
            }
        }
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $userId = intval($_POST['delete_user']);

        if ($userId === intval($_SESSION['user_id'])) {
            $errors[] = 'You cannot delete your own account while logged in.';
        } else {
            $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
            $delete->bind_param("i", $userId);
            if ($delete->execute()) {
                $success = 'User deleted successfully!';
            } else {
                $errors[] = 'Unable to delete user. Please try again.';
            }
            $delete->close();
        }
    }
}

// Get all users with HOD department info
$users = $conn->query("SELECT u.*, r.name as role_name, d.id as hod_dept_id, d.name as hod_dept_name FROM users u JOIN roles r ON u.role_id = r.id LEFT JOIN departments d ON d.hod_id = u.id ORDER BY u.created_at DESC");

// Get roles
$roles = $conn->query("SELECT id, name FROM roles ORDER BY id");

// Get departments for HOD assignment
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/table-search.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('users'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>User Management</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>
            
            <div class="page-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" style="background: #f56565; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3> Add New User</h3>
                    </div>
                    <div style="padding: 20px;">
                        <form method="POST" action="" style="max-width: 800px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="font-weight: 600; color: #1a365d; display: block; margin-bottom: 8px;">First Name *</label>
                                    <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($formData['first_name']); ?>">
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #1a365d; display: block; margin-bottom: 8px;">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($formData['last_name']); ?>">
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="font-weight: 600; color: #1a365d; display: block; margin-bottom: 8px;">Email *</label>
                                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($formData['email']); ?>">
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #1a365d; display: block; margin-bottom: 8px;">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($formData['phone']); ?>">
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="font-weight: 600; color: #1a365d; display: block; margin-bottom: 8px;">Password *</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #1a365d; display: block; margin-bottom: 8px;">Role *</label>
                                    <select name="role_id" class="form-control" required>
                                        <option value="">-- Select Role --</option>
                                        <?php while ($role = $roles->fetch_assoc()): ?>
                                            <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $formData['role_id']) ? 'selected' : ''; ?>><?php echo ucfirst(htmlspecialchars($role['name'])); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" name="add_user" class="btn btn-primary"> Create User</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3> All Users</h3>
                    </div>
                    <div style="padding: 15px 20px 0;">
                        <input type="text" id="searchUsers" placeholder="Search by name, email, phone, or role..." style="width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>HOD</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                                <td>
                                    <?php if ($user['role_id'] == 3): ?>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" action="" style="display:inline; margin:0; align-items:center;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="toggle_hod" value="<?php echo $user['id']; ?>">
                                            <?php if ($user['hod_dept_id']): ?>
                                                <span class="badge badge-success" style="margin-right:5px;"><?php echo htmlspecialchars($user['hod_dept_name']); ?></span>
                                                <button type="submit" name="dept_id" value="<?php echo $user['hod_dept_id']; ?>" class="badge badge-danger" style="border:none; background:none; cursor:pointer; padding:0;" onclick="return confirm('Remove this lecturer as HOD?')">Remove</button>
                                            <?php else: ?>
                                                <select name="dept_id" style="font-size:12px; padding:2px 4px; border:1px solid #e2e8f0; border-radius:4px;" onchange="if(this.value) this.form.submit();">
                                                    <option value="0">-- Assign HOD --</option>
                                                    <?php
                                                    $departments->data_seek(0);
                                                    while ($dept = $departments->fetch_assoc()):
                                                    ?>
                                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            <?php endif; ?>
                                        </form>
                                        <?php else: ?>
                                            <?php if ($user['hod_dept_id']): ?>
                                                <span class="badge badge-success"><?php echo htmlspecialchars($user['hod_dept_name']); ?></span>
                                            <?php else: ?>
                                                <span style="color: #a0aec0;">-</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #a0aec0;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                                <td>
                                    <a href="view-user.php?id=<?php echo $user['id']; ?>" class="badge badge-info" style="margin-right: 8px;"> View</a>
                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="badge badge-primary" style="margin-right: 8px;"> Edit</a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" action="" style="display:inline; margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="delete_user" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="badge badge-danger" style="border:none; background:none; cursor:pointer; padding:0;" onclick="return confirm('Delete this user?')"> Delete</button>
                                    </form>
                                <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
<script>initTableSearch('searchUsers', 'usersTable');</script>
</body>
</html>
