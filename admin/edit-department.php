<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

$success = '';
$error = '';
$department = null;

// Get department ID
if (isset($_GET['id'])) {
    $deptId = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->bind_param("i", $deptId);
    $stmt->execute();
    $department = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$department) {
        header('Location: departments.php');
        exit();
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $code = sanitize(strtoupper($_POST['code'] ?? ''));
        $description = sanitize($_POST['description'] ?? '');
        $hodId = intval($_POST['hod_id'] ?? 0);
        
        $errors = [];
        
        if (empty($name)) $errors[] = "Department name is required.";
        if (empty($code)) $errors[] = "Department code is required.";
        
        // Check if code exists (for other departments)
        $checkCode = $conn->prepare("SELECT id FROM departments WHERE code = ? AND id != ?");
        $checkCode->bind_param("si", $code, $deptId);
        $checkCode->execute();
        if ($checkCode->get_result()->num_rows > 0) {
            $errors[] = "Department code already exists.";
        }
        $checkCode->close();
        
        if (empty($errors)) {
            $hodId = ($hodId > 0) ? $hodId : null;
            $stmt = $conn->prepare("
                UPDATE departments
                SET name = ?, code = ?, description = ?, hod_id = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssii", $name, $code, $description, $hodId, $deptId);
            
            if ($stmt->execute()) {
                $success = "Department updated successfully!";
                // Refresh department data
                $stmt2 = $conn->prepare("SELECT * FROM departments WHERE id = ?");
                $stmt2->bind_param("i", $deptId);
                $stmt2->execute();
                $department = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            } else {
                $error = "Failed to update department.";
            }
            $stmt->close();
        } else {
            $error = implode(" ", $errors);
        }
    }
}

// Get all lecturers for HOD selection
$lecturers = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, email
    FROM users
    WHERE role_id IN (2, 3)
    ORDER BY first_name, last_name
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Department - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('departments'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Edit Department</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Edit: <?php echo htmlspecialchars($department['name']); ?></h3>
                        <a href="departments.php" class="btn btn-primary" style="padding: 8px 20px;"> Back to Departments</a>
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
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Department Name *</label>
                                <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($department['name']); ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Department Code *</label>
                                <input type="text" name="code" class="form-control" required maxlength="20" value="<?php echo htmlspecialchars($department['code']); ?>" style="text-transform: uppercase;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($department['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>Head of Department (HOD)</label>
                                <select name="hod_id" class="form-control">
                                    <option value="0" <?php echo empty($department['hod_id']) ? 'selected' : ''; ?>>-- No HOD --</option>
                                    <?php 
                                    $lecturers->data_seek(0);
                                    while ($lec = $lecturers->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $lec['id']; ?>" <?php echo ($lec['id'] == $department['hod_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lec['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                                 Update Department
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
