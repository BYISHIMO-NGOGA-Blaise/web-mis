<?php
require_once '../includes/config.php';
requireRole('hod');

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

$success = '';
$error = '';

$dept = $conn->prepare("SELECT * FROM departments WHERE hod_id = ?");
$dept->bind_param("i", $userId);
$dept->execute();
$department = $dept->get_result()->fetch_assoc();
$dept->close();

if ($department) {
    $deptId = $department['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request token.';
        } else {
            if (isset($_POST['approve'])) {
                $approvalId = intval($_POST['approve']);
                $stmt = $conn->prepare("UPDATE course_approvals SET status = 'approved', reviewed_by = ? WHERE id = ? AND status = 'pending'");
                $stmt->bind_param("ii", $userId, $approvalId);
                $stmt->execute();
                $stmt->close();

                $detail = $conn->prepare("SELECT student_id, course_id FROM course_approvals WHERE id = ?");
                $detail->bind_param("i", $approvalId);
                $detail->execute();
                $row = $detail->get_result()->fetch_assoc();
                $detail->close();

                if ($row) {
                    $exists = $conn->prepare("SELECT id FROM course_enrollments WHERE student_id = ? AND course_id = ?");
                    $exists->bind_param("ii", $row['student_id'], $row['course_id']);
                    $exists->execute();
                    if ($exists->get_result()->num_rows === 0) {
                        $exists->close();
                        $enroll = $conn->prepare("INSERT INTO course_enrollments (student_id, course_id, status) VALUES (?, ?, 'enrolled')");
                        $enroll->bind_param("ii", $row['student_id'], $row['course_id']);
                        $enroll->execute();
                        $enroll->close();
                    } else {
                        $exists->close();
                    }
                }

                $success = 'Approval request approved. Student has been enrolled.';
            } elseif (isset($_POST['reject'])) {
                $approvalId = intval($_POST['reject']);
                $stmt = $conn->prepare("UPDATE course_approvals SET status = 'rejected', reviewed_by = ? WHERE id = ? AND status = 'pending'");
                $stmt->bind_param("ii", $userId, $approvalId);
                $stmt->execute();
                $stmt->close();
                $success = 'Approval request rejected.';
            }
        }
    }

    $apprStmt = $conn->prepare("
        SELECT ca.*, CONCAT(u.first_name, ' ', u.last_name) as student_name, u.email,
               c.code, c.name as course_name
        FROM course_approvals ca
        JOIN users u ON ca.student_id = u.id
        JOIN courses c ON ca.course_id = c.id
        JOIN courses c2 ON ca.course_id = c2.id AND c2.department_id = ?
        WHERE ca.status = 'pending'
        ORDER BY ca.created_at DESC
    ");
    $apprStmt->bind_param("i", $deptId);
    $apprStmt->execute();
    $approvals = $apprStmt->get_result();
    $apprStmt->close();

    $histStmt = $conn->prepare("
        SELECT ca.*, CONCAT(u.first_name, ' ', u.last_name) as student_name,
               c.code, c.name as course_name,
               CONCAT(r.first_name, ' ', r.last_name) as reviewer_name
        FROM course_approvals ca
        JOIN users u ON ca.student_id = u.id
        JOIN courses c ON ca.course_id = c.id
        JOIN courses c2 ON ca.course_id = c2.id AND c2.department_id = ?
        LEFT JOIN users r ON ca.reviewed_by = r.id
        WHERE ca.status != 'pending'
        ORDER BY ca.updated_at DESC
        LIMIT 20
    ");
    $histStmt->bind_param("i", $deptId);
    $histStmt->execute();
    $history = $histStmt->get_result();
    $histStmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvals - HOD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('approvals'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Pending Approvals</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Head of Department</div>
                </div>
            </nav>
            
            <div class="page-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($department): ?>
                <div class="card">
                    <div class="card-header">
                        <h3> Pending Requests</h3>
                    </div>
                    <?php if ($approvals && $approvals->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Notes</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($a = $approvals->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($a['student_name']); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($a['code']); ?></span> <?php echo htmlspecialchars($a['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($a['notes'] ?? '-'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($a['created_at'])); ?></td>
                                <td>
                                    <form method="POST" action="" style="display:inline; margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="approve" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="badge badge-success" style="border:none; background:none; cursor:pointer; padding:0;"> Approve</button>
                                    </form>
                                    <form method="POST" action="" style="display:inline; margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="reject" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="badge badge-danger" style="border:none; background:none; cursor:pointer; padding:0;" onclick="return confirm('Reject this request?')"> Reject</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="padding: 30px; color: #718096; text-align: center;">
                        No pending approval requests at this time.
                    </p>
                    <?php endif; ?>
                </div>

                <?php if ($history && $history->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3> Recent History</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($h = $history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($h['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($h['code'] . ' - ' . $h['course_name']); ?></td>
                                <td>
                                    <?php if ($h['status'] === 'approved'): ?>
                                        <span class="badge badge-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($h['reviewer_name'] ?? '-'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($h['updated_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

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

