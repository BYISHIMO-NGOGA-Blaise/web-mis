<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

$success = '';
$error = '';

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
        elseif (strlen($code) > 20) $errors[] = "Code must be 20 characters or less.";
        
        // Check if code exists
        $checkCode = $conn->prepare("SELECT id FROM departments WHERE code = ?");
        $checkCode->bind_param("s", $code);
        $checkCode->execute();
        if ($checkCode->get_result()->num_rows > 0) {
            $errors[] = "Department code already exists.";
        }
        $checkCode->close();
        
        if (empty($errors)) {
            $hodId = ($hodId > 0) ? $hodId : null;
            $stmt = $conn->prepare("
                INSERT INTO departments (name, code, description, hod_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("sssi", $name, $code, $description, $hodId);
            
            if ($stmt->execute()) {
                $success = "Department created successfully!";
            } else {
                $error = "Failed to create department.";
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
    <title>Add Department - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('departments'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Add New Department</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Create New Department</h3>
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
                                <input type="text" name="name" class="form-control" placeholder="e.g., Computer Science" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Department Code *</label>
                                <input type="text" name="code" class="form-control" placeholder="e.g., CS" required maxlength="20" value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>" style="text-transform: uppercase;">
                                <small style="color: #718096;">Short code (2-5 characters, will be converted to uppercase)</small>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the department"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label>Head of Department (HOD)</label>
                                <select name="hod_id" class="form-control">
                                    <option value="0">-- Select HOD (Optional) --</option>
                                    <?php while ($lec = $lecturers->fetch_assoc()): ?>
                                        <option value="<?php echo $lec['id']; ?>">
                                            <?php echo htmlspecialchars($lec['name']); ?> (<?php echo htmlspecialchars($lec['email']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <small style="color: #718096;">Select a lecturer or HOD to assign as department head</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                                 Create Department
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
