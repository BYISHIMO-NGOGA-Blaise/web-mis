<?php
require_once '../includes/config.php';
requireRole('hod');

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get department
$dept = $conn->prepare("SELECT * FROM departments WHERE hod_id = ?");
$dept->bind_param("i", $userId);
$dept->execute();
$department = $dept->get_result()->fetch_assoc();
$dept->close();

$lecturers = null;
if ($department) {
    $deptId = $department['id'];
    $lecturers = $conn->query("
        SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email
        FROM users u
        JOIN courses c ON u.id = c.lecturer_id
        WHERE c.department_id = $deptId
        ORDER BY u.first_name, u.last_name
    ");
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Lecturers - HOD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('lecturers'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Department Lecturers</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Head of Department</div>
                </div>
            </nav>
            
            <div class="page-content">
                <?php if ($department): ?>
                <div class="card">
                    <div class="card-header">
                        <h3> <?php echo htmlspecialchars($department['name']); ?> - Teaching Staff</h3>
                    </div>
                    
                    <?php if ($lecturers && $lecturers->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Lecturer Name</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($lec = $lecturers->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($lec['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($lec['email']); ?></td>
                                <td>
                                    <a href="courses.php?lecturer_id=<?php echo $lec['id']; ?>" class="badge badge-info" style="margin-right: 5px;"> View Courses</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">
                         No lecturers teaching in this department yet.
                    </p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h3> No Department Assigned</h3>
                    </div>
                    <p style="padding: 30px; color: #718096;">
                        You are not assigned to manage any department.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
