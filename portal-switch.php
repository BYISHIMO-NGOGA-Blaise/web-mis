<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if (!isDualRole()) {
    header('Location: index.php');
    exit();
}

$mode = $_GET['mode'] ?? '';
$page = $_GET['page'] ?? 'dashboard';

if ($mode === 'hod' || $mode === 'lecturer') {
    // Verify the user actually has access to this mode
    if ($mode === 'hod' && !isHOD()) { header('Location: index.php'); exit(); }
    $_SESSION['portal_mode'] = $mode;
}

// Whitelist allowed pages to prevent open redirect
$allowedPages = [
    'dashboard', 'courses', 'lecturers', 'students', 'enrollment',
    'approvals', 'reports', 'grades', 'attendance', 'timetable',
    'enter-grades', 'view-grades', 'take-attendance', 'profile',
    'users', 'departments', 'add-user', 'edit-user', 'view-user',
    'add-department', 'edit-department', 'add-course', 'edit-course',
];

$safePage = basename($page, '.php');
if (!in_array($safePage, $allowedPages)) {
    $safePage = 'dashboard';
}

$folderMap = ['hod' => 'HOD', 'lecturer' => 'lecturer'];
$folder = $folderMap[$mode] ?? 'lecturer';

session_write_close();
header('Location: ' . $folder . '/' . $safePage . '.php');
exit();
