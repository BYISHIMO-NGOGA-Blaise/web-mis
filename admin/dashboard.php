<?php
require_once '../includes/config.php';
requireRole('admin');

$conn = getDBConnection();

$stats = [
    'users' => $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id != 1")->fetch_assoc()['total'],
    'departments' => $conn->query("SELECT COUNT(*) as total FROM departments")->fetch_assoc()['total'],
    'courses' => $conn->query("SELECT COUNT(*) as total FROM courses")->fetch_assoc()['total'],
    'students' => $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id = 4")->fetch_assoc()['total'],
    'admins' => $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id = 1")->fetch_assoc()['total'],
    'hods' => $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id = 2")->fetch_assoc()['total'],
    'lecturers' => $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id = 3")->fetch_assoc()['total'],
];

$recentUsers = $conn->query("SELECT first_name, last_name, email, role_id, created_at FROM users WHERE role_id != 1 ORDER BY created_at DESC LIMIT 5");

$conn->close();

$greeting = 'Good ' . (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('dashboard'); ?>

        <main class="main-content">
            <nav class="top-nav">
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                        <div class="user-role"><?php echo ucfirst(getPortalMode()); ?></div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?></div>
                </div>
            </nav>

            <div class="page-content">
                <div class="welcome-banner">
                    <h2><?php echo $greeting ?>, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
                    <p>Welcome to the La Lumiere School admin panel. Here's your overview.</p>
                    <div class="welcome-time"><i class="far fa-clock"></i> <span id="current-time"></span></div>
                </div>

                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-users"></i></div>
                        <h4>Total Users</h4>
                        <div class="metric-value" data-counter="<?php echo $stats['users']; ?>">0</div>
                        <p>All registered users</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-building"></i></div>
                        <h4>Departments</h4>
                        <div class="metric-value" data-counter="<?php echo $stats['departments']; ?>">0</div>
                        <p>Active departments</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-book-open"></i></div>
                        <h4>Courses</h4>
                        <div class="metric-value" data-counter="<?php echo $stats['courses']; ?>">0</div>
                        <p>Offered courses</p>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fas fa-user-graduate"></i></div>
                        <h4>Students</h4>
                        <div class="metric-value" data-counter="<?php echo $stats['students']; ?>">0</div>
                        <p>Enrolled students</p>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> User Distribution</h3>
                        </div>
                        <div class="chart-card" style="height: 300px;">
                            <canvas id="usersChart"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> System Overview</h3>
                        </div>
                        <div class="chart-card" style="height: 300px;">
                            <canvas id="overviewChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="quick-actions-grid">
                        <a href="users.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-users-cog"></i></div>
                            <div class="action-label">Manage Users</div>
                            <div class="action-desc">Add, edit, remove users</div>
                        </a>
                        <a href="departments.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-building"></i></div>
                            <div class="action-label">Departments</div>
                            <div class="action-desc">Manage departments</div>
                        </a>
                        <a href="courses.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-book-open"></i></div>
                            <div class="action-label">Courses</div>
                            <div class="action-desc">Manage course catalog</div>
                        </a>
                        <a href="enrollment.php" class="quick-action-item">
                            <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                            <div class="action-label">Enrollment</div>
                            <div class="action-desc">Manage enrollments</div>
                        </a>
                    </div>
                </div>

                <?php if ($recentUsers && $recentUsers->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-clock"></i> Recent Users</h3>
                        <a href="users.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($u = $recentUsers->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php
                                        $roleNames = [1 => 'Admin', 2 => 'HOD', 3 => 'Lecturer', 4 => 'Student'];
                                        $roleBadge = [1 => 'badge-danger', 2 => 'badge-warning', 3 => 'badge-info', 4 => 'badge-success'];
                                        $rid = $u['role_id'];
                                        ?>
                                        <span class="badge <?php echo $roleBadge[$rid] ?? 'badge-primary'; ?>"><?php echo $roleNames[$rid] ?? 'Unknown'; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    var roleNames = ['Admin', 'HOD', 'Lecturer', 'Student'];
    var roleCounts = [<?php echo $stats['admins']; ?>, <?php echo $stats['hods']; ?>, <?php echo $stats['lecturers']; ?>, <?php echo $stats['students']; ?>];

        initCharts([
            {
                canvasId: 'usersChart',
                type: 'doughnut',
                data: {
                    labels: roleNames,
                    datasets: [{
                        data: roleCounts,
                        backgroundColor: ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6'],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: { cutout: '65%', plugins: { legend: { position: 'bottom' } } }
            },
            {
                canvasId: 'overviewChart',
                type: 'bar',
                data: {
                    labels: ['Users', 'Departments', 'Courses', 'Students'],
                    datasets: [{
                        label: 'Count',
                        data: [<?php echo $stats['users']; ?>, <?php echo $stats['departments']; ?>, <?php echo $stats['courses']; ?>, <?php echo $stats['students']; ?>],
                        backgroundColor: ['rgba(79,70,229,0.8)', 'rgba(6,182,212,0.8)', 'rgba(34,197,94,0.8)', 'rgba(245,158,11,0.8)'],
                        borderRadius: 12,
                        borderSkipped: false
                    }]
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(226,232,240,0.4)' } }, x: { grid: { display: false } } } }
            }
        ]);
    });
    </script>
</body>
</html>
