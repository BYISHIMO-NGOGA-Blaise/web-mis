<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

// Get all courses with details
$courses = $conn->query("
    SELECT c.*, d.name as dept_name, CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users u ON c.lecturer_id = u.id
    ORDER BY c.code
");

// Handle delete via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $courseId = intval($_POST['delete_course']);
        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->bind_param("i", $courseId);
        $stmt->execute();
        $stmt->close();
        header('Location: courses.php');
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
    <title>Manage Courses - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/table-search.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('courses'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Manage Courses</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                </div>
            </nav>
            
            <div class="page-content">
                <div class="card">
                    <div class="card-header">
                        <h3> All Courses</h3>
                        <a href="add-course.php" class="btn btn-primary" style="padding: 8px 20px;">+ Add Course</a>
                    </div>
                    <div style="padding: 15px 20px 0;">
                        <input type="text" id="searchCourses" placeholder="Search by code, name, department, or lecturer..." style="width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <table class="data-table" id="coursesTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Department</th>
                                <th>Lecturer</th>
                                <th>Credits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                            <tr>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($course['code']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($course['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['dept_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($course['lecturer_name'] ?? 'Not Assigned'); ?></td>
                                <td><?php echo $course['credits']; ?></td>
                                <td>
                                    <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="badge badge-info" style="margin-right: 5px;"> Edit</a>
                                    <form method="POST" action="" style="display:inline; margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="delete_course" value="<?php echo $course['id']; ?>">
                                        <button type="submit" class="badge badge-warning" style="border:none; background:none; cursor:pointer; padding:0;" onclick="return confirm('Are you sure you want to delete this course?')"> Delete</button>
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
<script>initTableSearch('searchCourses', 'coursesTable');</script>
</body>
</html>
