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
        $code = sanitize(strtoupper($_POST['code'] ?? ''));
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $credits = intval($_POST['credits'] ?? 3);
        $semester = intval($_POST['semester'] ?? 1);
        $departmentId = intval($_POST['department_id'] ?? 0);
        $lecturerId = intval($_POST['lecturer_id'] ?? 0);
        $academicYear = sanitize($_POST['academic_year'] ?? date('Y'));
        
        $errors = [];
        
        if (empty($code)) $errors[] = "Course code is required.";
        if (empty($name)) $errors[] = "Course name is required.";
        if ($departmentId <= 0) $errors[] = "Please select a department.";
        if ($credits < 1 || $credits > 10) $errors[] = "Credits must be between 1 and 10.";
        if ($semester < 1 || $semester > 8) $errors[] = "Semester must be between 1 and 8.";
        
        // Check if code exists
        $checkCode = $conn->prepare("SELECT id FROM courses WHERE code = ?");
        $checkCode->bind_param("s", $code);
        $checkCode->execute();
        if ($checkCode->get_result()->num_rows > 0) {
            $errors[] = "Course code already exists.";
        }
        $checkCode->close();
        
        if (empty($errors)) {
            $lecturerId = ($lecturerId > 0) ? $lecturerId : null;
            $stmt = $conn->prepare("
                INSERT INTO courses (code, name, description, credits, semester, department_id, lecturer_id, academic_year)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssiiiss", $code, $name, $description, $credits, $semester, $departmentId, $lecturerId, $academicYear);
            
            if ($stmt->execute()) {
                $success = "Course created successfully!";
            } else {
                $error = "Failed to create course.";
            }
            $stmt->close();
        } else {
            $error = implode(" ", $errors);
        }
    }
}

// Get all departments
$departments = $conn->query("SELECT id, name, code FROM departments ORDER BY name");

// Get all lecturers
$lecturers = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, email
    FROM users
    WHERE role_id IN (1, 2, 3)
    ORDER BY first_name, last_name
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Course - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('courses'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Add New Course</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> Create New Course</h3>
                        <a href="courses.php" class="btn btn-primary" style="padding: 8px 20px;"> Back to Courses</a>
                    </div>
                    
                    <div style="padding: 30px;">
                        <?php if ($success): ?>
                            <div class="alert alert-success"> <?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"> <?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" style="max-width: 700px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>Course Code *</label>
                                    <input type="text" name="code" class="form-control" placeholder="e.g., CS101" required maxlength="20" value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>" style="text-transform: uppercase;">
                                </div>
                                <div class="form-group">
                                    <label>Credits *</label>
                                    <input type="number" name="credits" class="form-control" required min="1" max="10" value="<?php echo htmlspecialchars($_POST['credits'] ?? '3'); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Course Name *</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g., Introduction to Programming" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Brief course description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>Department *</label>
                                    <select name="department_id" class="form-control" required>
                                        <option value="0">-- Select Department --</option>
                                        <?php while ($dept = $departments->fetch_assoc()): ?>
                                            <option value="<?php echo $dept['id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?> (<?php echo htmlspecialchars($dept['code']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Semester *</label>
                                    <select name="semester" class="form-control" required>
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo (isset($_POST['semester']) && $_POST['semester'] == $i) ? 'selected' : ''; ?>>
                                                Semester <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>Lecturer (Optional)</label>
                                    <select name="lecturer_id" class="form-control">
                                        <option value="0">-- Select Lecturer --</option>
                                        <?php 
                                        $lecturers->data_seek(0);
                                        while ($lec = $lecturers->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $lec['id']; ?>" <?php echo (isset($_POST['lecturer_id']) && $_POST['lecturer_id'] == $lec['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lec['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Academic Year</label>
                                    <input type="text" name="academic_year" class="form-control" placeholder="e.g., 2025-2026" value="<?php echo htmlspecialchars($_POST['academic_year'] ?? date('Y') . '-' . (date('Y') + 1)); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                                 Create Course
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
