<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

// Get all departments with HOD names and student counts
$departments = $conn->query(
    "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as hod_name, (SELECT COUNT(*) FROM users su WHERE su.role_id = 4 AND su.department_id = d.id) as student_count FROM departments d LEFT JOIN users u ON d.hod_id = u.id ORDER BY d.created_at DESC"
);

// Get all lecturers for HOD selection
$lecturers = $conn->query("
    SELECT id, first_name, last_name, email 
    FROM users 
    WHERE role_id = 2 OR role_id = 3
    ORDER BY first_name, last_name
");

// Handle delete via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_dept'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $deptId = intval($_POST['delete_dept']);
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->bind_param("i", $deptId);
        $stmt->execute();
        $stmt->close();
        header('Location: departments.php');
        exit();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('departments'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Manage Departments</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> All Departments</h3>
                        <a href="add-department.php" class="btn btn-primary" style="padding: 8px 20px;">+ Add Department</a>
                    </div>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>HOD</th>
                                <th>Students</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($dept = $departments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $dept['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($dept['code']); ?></span></td>
                                <td>
                                    <?php if ($dept['hod_name']): ?>
                                        <?php echo htmlspecialchars($dept['hod_name']); ?>
                                    <?php else: ?>
                                        <em style="color: #718096;">Not Assigned</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo intval($dept['student_count'] ?? 0); ?></td>
                                <td><?php echo date('M d, Y', strtotime($dept['created_at'])); ?></td>
                                <td>
                                    <a href="edit-department.php?id=<?php echo $dept['id']; ?>" class="badge badge-info" style="margin-right: 5px;"> Edit</a>
                                    <form method="POST" action="" style="display:inline; margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="delete_dept" value="<?php echo $dept['id']; ?>">
                                        <button type="submit" class="badge badge-warning" style="border:none; background:none; cursor:pointer; padding:0;" onclick="return confirm('Are you sure you want to delete this department?')"> Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
