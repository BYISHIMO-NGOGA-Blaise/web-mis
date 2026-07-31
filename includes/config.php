<?php
// School Management System - Configuration

// Session hardening before session_start()
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_trans_sid', 0);

session_start();

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['_last_regeneration'])) {
    $_SESSION['_last_regeneration'] = time();
} elseif (time() - $_SESSION['_last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['_last_regeneration'] = time();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_management');

// Application Settings
define('APP_NAME', 'La Lumiere School');
define('APP_VERSION', '2.0');

define('CREDIT_FEE', 20000);

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Error Reporting (disabled in production)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Prevent caching of sensitive pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * Get Database Connection
 */
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Sanitize Input Data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is HOD (by department assignment).
 * Result is cached in session to avoid repeated DB queries.
 */
function isHOD($userId = null) {
    if ($userId === null) $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) return false;
    
    if (isset($_SESSION['_is_hod_cache'])) {
        return $_SESSION['_is_hod_cache'];
    }
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id FROM departments WHERE hod_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $isHod = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    $conn->close();
    
    $_SESSION['_is_hod_cache'] = $isHod;
    return $isHod;
}

/**
 * Check if user has dual role (lecturer who is also HOD)
 */
function isDualRole() {
    if ($_SESSION['role'] !== 'lecturer') return false;
    return isHOD();
}

/**
 * Get current portal mode: 'hod' or 'lecturer'
 */
function getPortalMode() {
    if (!isDualRole()) {
        return $_SESSION['role'];
    }
    return $_SESSION['portal_mode'] ?? (isHOD() ? 'hod' : 'lecturer');
}

/**
 * Require specific role access.
 * Dual-role users are checked against their current portal mode.
 */
function requireRole($requiredRole) {
    if (!isLoggedIn()) {
        header('Location: ../index.php?error=login_required');
        exit();
    }
    
    if (isDualRole()) {
        $mode = getPortalMode();
        if ($mode === $requiredRole) return;
        // Mismatched: redirect to their current mode's dashboard
        $redirects = [
            'hod' => '../HOD/dashboard.php',
            'admin' => '../admin/dashboard.php',
            'lecturer' => '../lecturer/dashboard.php',
        ];
        header('Location: ' . ($redirects[$mode] ?? '../lecturer/dashboard.php'));
        exit();
    }
    
    // Single-role user
    if ($_SESSION['role'] !== $requiredRole) {
        header('Location: ../index.php?error=unauthorized');
        exit();
    }
}

/**
 * Render the portal switch button (for dual/triple-role users)
 */
function renderPortalSwitch($currentPage) {
    if (!isDualRole()) return;

    $mode = getPortalMode();

    // Build list of available modes
    $modes = ['lecturer' => ['label' => 'Lecturer Portal', 'icon' => 'fa-chalkboard-teacher']];
    if (isHOD()) $modes['hod'] = ['label' => 'HOD Portal', 'icon' => 'fa-user-tie'];

    // Remove current mode from switch options
    $otherModes = array_filter(array_keys($modes), fn($m) => $m !== $mode);

    if (empty($otherModes)) return;

    echo '<div style="padding: 14px 20px; margin: 8px 12px; background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(139,92,246,0.3)); border-radius: 14px; text-align: center; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(4px);">';
    foreach ($otherModes as $m) {
        $info = $modes[$m];
        echo '<a href="../portal-switch.php?mode=' . $m . '&page=' . urlencode($currentPage) . '" style="color: white; text-decoration: none; font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 6px;">';
        echo '<i class="fas ' . $info['icon'] . '"></i> Switch to ' . $info['label'];
        echo '</a>';
    }
    echo '</div>';
}

/**
 * Render sidebar navigation. For dual-role users, shows only the current mode's tabs.
 */
function renderSidebar($activePage, $portalOverride = null) {
    $role = $_SESSION['role'];
    $mode = $portalOverride ?? getPortalMode();
    $dual = isDualRole();

    if ($mode === 'admin' || ($role === 'admin' && !$dual)) {
        $portalLabel = 'Admin Portal';
        $portalSub = 'Administrator';
        $portalIcon = 'fa-shield-alt';
        $items = [
            ['dashboard.php', 'Dashboard', 'dashboard', 'fa-th-large'],
            ['users.php', 'Users', 'users', 'fa-users-cog'],
            ['departments.php', 'Departments', 'departments', 'fa-building'],
            ['courses.php', 'Courses', 'courses', 'fa-book-open'],
            ['enrollment.php', 'Enrollment', 'enrollment', 'fa-user-plus'],
            ['reports.php', 'Reports', 'reports', 'fa-chart-bar'],
        ];
    } elseif ($mode === 'hod' || ($role === 'hod' && !$dual)) {
        $portalLabel = 'HOD Portal';
        $portalSub = $dual ? 'HOD Mode' : 'Head of Department';
        $portalIcon = 'fa-user-tie';
        $items = [
            ['dashboard.php', 'Dashboard', 'dashboard', 'fa-th-large'],
            ['courses.php', 'Department Courses', 'courses', 'fa-book-open'],
            ['lecturers.php', 'Lecturers', 'lecturers', 'fa-chalkboard-teacher'],
            ['students.php', 'Students', 'students', 'fa-user-graduate'],
            ['enrollment.php', 'Enrollment', 'enrollment', 'fa-user-plus'],
            ['approvals.php', 'Approvals', 'approvals', 'fa-check-circle'],
            ['reports.php', 'Reports', 'reports', 'fa-chart-bar'],
            ['profile.php', 'My Profile', 'profile', 'fa-user-circle'],
        ];
    } else {
        $portalLabel = 'Lecturer Portal';
        $portalSub = $dual ? 'Lecturer Mode' : 'Lecturer';
        $portalIcon = 'fa-chalkboard-teacher';
        $items = [
            ['dashboard.php', 'Dashboard', 'dashboard', 'fa-th-large'],
            ['timetable.php', 'Timetable', 'timetable', 'fa-calendar-alt'],
            ['grades.php', 'Grades', 'grades', 'fa-star'],
            ['attendance.php', 'Attendance', 'attendance', 'fa-clipboard-check'],
            ['profile.php', 'My Profile', 'profile', 'fa-user-circle'],
        ];
    }

    echo '<aside class="sidebar">';
    echo '<div class="sidebar-header">';
    echo '<div class="sidebar-logo"><i class="fas ' . $portalIcon . '"></i></div>';
    echo '<h2>' . $portalLabel . '</h2>';
    echo '<p class="school-name">' . APP_NAME . '</p>';
    echo '<p>' . htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) . '</p>';
    if ($dual) {
        echo '<p style="font-size:12px; color:#a0aec0; margin-top:2px;">' . $portalSub . '</p>';
    }
    echo '</div>';
    echo '<ul class="sidebar-nav">';
    foreach ($items as $item) {
        $cls = ($item[2] === $activePage) ? ' class="active"' : '';
        echo '<li><a href="' . $item[0] . '"' . $cls . '><i class="fas ' . $item[3] . '"></i> ' . $item[1] . '</a></li>';
    }
    echo '<div class="sidebar-divider"></div>';
    echo '<li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>';
    echo '</ul>';
    renderPortalSwitch($activePage);
    echo '</aside>';
}

/**
 * Get or generate a CSRF token.
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token.
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Calculate course price from credits.
 */
function calculateCoursePrice($credits) {
    return intval($credits) * CREDIT_FEE;
}

/**
 * Display flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        $bgColor = $type === 'success' ? '#48bb78' : ($type === 'error' ? '#f56565' : '#4299e1');
        echo "<div style='background: {$bgColor}; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>" . htmlspecialchars($message) . "</div>";
    }
}

// Ensure auxiliary tables exist at runtime (safe, no foreign keys to avoid ordering issues)
function ensureAuxTables() {
    $conn = getDBConnection();

    $conn->query("CREATE TABLE IF NOT EXISTS course_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        day VARCHAR(20) DEFAULT NULL,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        location VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_course_schedule (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        method VARCHAR(50) DEFAULT 'Online',
        paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->close();
}

// Run automatic auxiliary table creation once per request to avoid separate helper files.
// This is lightweight and safe due to IF NOT EXISTS usage.
ensureAuxTables();
?>
