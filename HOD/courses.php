<?php
require_once '../includes/config.php';
requireRole('hod');

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

$success = '';
$error = '';
$editMode = false;
$courseId = 0;
$courseData = [
    'code' => '',
    'name' => '',
    'description' => '',
    'credits' => 3,
    'semester' => 1,
    'lecturer_id' => 0,
    'day' => '',
    'start_time' => '',
    'end_time' => '',
    'location' => ''
];

// Get department
$dept = $conn->prepare("SELECT * FROM departments WHERE hod_id = ?");
$dept->bind_param("i", $userId);
$dept->execute();
$department = $dept->get_result()->fetch_assoc();
$dept->close();

if ($department) {
    $deptId = $department['id'];

        $lecturersStmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, email FROM users WHERE role_id = 3 AND is_active = 1 ORDER BY first_name, last_name");
        $lecturersStmt->execute();
        $lecturers = $lecturersStmt->get_result();
        $lecturersStmt->close();

        $conn->query("CREATE TABLE IF NOT EXISTS course_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            day VARCHAR(20) DEFAULT NULL,
            start_time TIME DEFAULT NULL,
            end_time TIME DEFAULT NULL,
            location VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_course_schedule (course_id),
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (isset($_GET['edit'])) {
        $courseId = intval($_GET['edit']);
        $stmt = $conn->prepare("SELECT c.*, s.day, s.start_time, s.end_time, s.location FROM courses c LEFT JOIN course_schedules s ON c.id = s.course_id WHERE c.id = ? AND c.department_id = ?");
        $stmt->bind_param("ii", $courseId, $deptId);
        $stmt->execute();
        $course = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($course) {
            $editMode = true;
            $courseData = [
                'code' => $course['code'],
                'name' => $course['name'],
                'description' => $course['description'],
                'credits' => $course['credits'],
                'semester' => $course['semester'],
                        'lecturer_id' => $course['lecturer_id'] ?? 0,
                        'day' => $course['day'] ?? '',
                        'start_time' => $course['start_time'] ?? '',
                        'end_time' => $course['end_time'] ?? '',
                        'location' => $course['location'] ?? ''
            ];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request token.';
        } else {
            if (isset($_POST['delete_course'])) {
                $deleteId = intval($_POST['delete_course']);
                $stmt = $conn->prepare("DELETE FROM courses WHERE id = ? AND department_id = ?");
                $stmt->bind_param("ii", $deleteId, $deptId);
                if ($stmt->execute()) {
                    $success = 'Course deleted successfully.';
                } else {
                    $error = 'Unable to delete course. Please try again.';
                }
                $stmt->close();
            } elseif (isset($_POST['save_course'])) {
                $courseId = intval($_POST['course_id'] ?? 0);
                $courseData['code'] = strtoupper(sanitize($_POST['code'] ?? ''));
                $courseData['description'] = sanitize($_POST['description'] ?? '');
                $courseData['credits'] = intval($_POST['credits'] ?? 3);
                $courseData['semester'] = intval($_POST['semester'] ?? 1);
                $courseData['lecturer_id'] = intval($_POST['lecturer_id'] ?? 0);
                $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                $rawDay = sanitize($_POST['day'] ?? '');
                $courseData['day'] = in_array($rawDay, $validDays, true) ? $rawDay : '';
                $courseData['start_time'] = sanitize($_POST['start_time'] ?? '');
                $courseData['end_time'] = sanitize($_POST['end_time'] ?? '');
                $courseData['location'] = sanitize($_POST['location'] ?? '');

                if (empty($courseData['code']) || empty($courseData['name']) || $courseData['credits'] <= 0 || $courseData['lecturer_id'] <= 0) {
                    $error = 'Please fill in all required course fields and select a lecturer.';
                } elseif (!empty($courseData['start_time']) && !empty($courseData['end_time']) && $courseData['start_time'] >= $courseData['end_time']) {
                    $error = 'Start time must be before end time.';
                } else {
                    if ($courseId > 0) {
                        $stmt = $conn->prepare("UPDATE courses SET code = ?, name = ?, description = ?, credits = ?, semester = ?, lecturer_id = ? WHERE id = ? AND department_id = ?");
                        $stmt->bind_param("sssiiiii", $courseData['code'], $courseData['name'], $courseData['description'], $courseData['credits'], $courseData['semester'], $courseData['lecturer_id'], $courseId, $deptId);
                        if ($stmt->execute()) {
                            $success = 'Course updated successfully.';
                            $editMode = true;
                            $scheduleCheck = $conn->prepare("SELECT id FROM course_schedules WHERE course_id = ?");
                            $scheduleCheck->bind_param("i", $courseId);
                            $scheduleCheck->execute();
                            $existingSchedule = $scheduleCheck->get_result()->fetch_assoc();
                            $scheduleCheck->close();

                            if ($existingSchedule) {
                                $scheduleStmt = $conn->prepare("UPDATE course_schedules SET day = ?, start_time = ?, end_time = ?, location = ? WHERE course_id = ?");
                                $scheduleStmt->bind_param("ssssi", $courseData['day'], $courseData['start_time'], $courseData['end_time'], $courseData['location'], $courseId);
                                $scheduleStmt->execute();
                                $scheduleStmt->close();
                            } else {
                                $scheduleStmt = $conn->prepare("INSERT INTO course_schedules (course_id, day, start_time, end_time, location) VALUES (?, ?, ?, ?, ?)");
                                $scheduleStmt->bind_param("issss", $courseId, $courseData['day'], $courseData['start_time'], $courseData['end_time'], $courseData['location']);
                                $scheduleStmt->execute();
                                $scheduleStmt->close();
                            }
                        } else {
                            $error = 'Unable to update course. Please try again.';
                        }
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO courses (code, name, description, credits, semester, department_id, lecturer_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssiiii", $courseData['code'], $courseData['name'], $courseData['description'], $courseData['credits'], $courseData['semester'], $deptId, $courseData['lecturer_id']);
                        if ($stmt->execute()) {
                            $newCourseId = $stmt->insert_id;
                            if (!empty($courseData['day']) || !empty($courseData['start_time']) || !empty($courseData['end_time']) || !empty($courseData['location'])) {
                                $scheduleStmt = $conn->prepare("INSERT INTO course_schedules (course_id, day, start_time, end_time, location) VALUES (?, ?, ?, ?, ?)");
                                $scheduleStmt->bind_param("issss", $newCourseId, $courseData['day'], $courseData['start_time'], $courseData['end_time'], $courseData['location']);
                                $scheduleStmt->execute();
                                $scheduleStmt->close();
                            }
                            $success = 'Course created successfully!';
                            $courseData = [
                                'code' => '',
                                'name' => '',
                                'description' => '',
                                'credits' => 3,
                                'semester' => 1,
                                'lecturer_id' => 0,
                                'day' => '',
                                'start_time' => '',
                                'end_time' => '',
                                'location' => ''
                            ];
                        } else {
                            $error = 'Failed to create course.';
                        }
                        $stmt->close();
                    }
                }
            } elseif (isset($_POST['enroll_student'])) {
                $enrollCourseId = intval($_POST['enroll_course_id'] ?? 0);
                $enrollStudentId = intval($_POST['enroll_student_id'] ?? 0);

                if ($enrollCourseId <= 0 || $enrollStudentId <= 0) {
                    $error = 'Please select a valid course and student to enroll.';
                } else {
                    // verify course belongs to this department
                    $verify = $conn->prepare("SELECT id FROM courses WHERE id = ? AND department_id = ?");
                    $verify->bind_param("ii", $enrollCourseId, $deptId);
                    $verify->execute();
                    $courseExists = $verify->get_result()->fetch_assoc();
                    $verify->close();

                    if (!$courseExists) {
                        $error = 'Selected course is not in your department.';
                    } else {
                        // ensure student exists, is a student and belongs to this department
                        $stu = $conn->prepare("SELECT id, department_id FROM users WHERE id = ? AND role_id = 4 AND is_active = 1");
                        $stu->bind_param("i", $enrollStudentId);
                        $stu->execute();
                        $studentRow = $stu->get_result()->fetch_assoc();
                        $stu->close();

                        if (!$studentRow) {
                            $error = 'Selected user is not an active student.';
                        } elseif (intval($studentRow['department_id']) !== intval($deptId)) {
                            $error = 'Selected student does not belong to your department.';
                        } else {
                            // check existing enrollment
                            $check = $conn->prepare("SELECT id FROM course_enrollments WHERE student_id = ? AND course_id = ?");
                            $check->bind_param("ii", $enrollStudentId, $enrollCourseId);
                            $check->execute();
                            $already = $check->get_result()->fetch_assoc();
                            $check->close();

                            if ($already) {
                                $error = 'Student is already enrolled in that course.';
                            } else {
                                $ins = $conn->prepare("INSERT INTO course_enrollments (student_id, course_id, status) VALUES (?, ?, 'enrolled')");
                                $ins->bind_param("ii", $enrollStudentId, $enrollCourseId);
                                if ($ins->execute()) {
                                    $success = 'Student enrolled successfully.';
                                } else {
                                    $error = 'Unable to enroll student. Please try again.';
                                }
                                $ins->close();
                            }
                        }
                    }
                }
            }
        }
    }

    $lecturerFilter = 0;
    if (isset($_GET['lecturer_id'])) {
        $lecturerFilter = intval($_GET['lecturer_id']);
    }

    // Load active students for enroll form
    $studentsStmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, email FROM users WHERE role_id = 4 AND is_active = 1 ORDER BY first_name, last_name");
    $studentsStmt->execute();
    $students = $studentsStmt->get_result();
    $studentsStmt->close();

    if ($lecturerFilter > 0) {
        $coursesStmt = $conn->prepare("SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
            FROM courses c
            LEFT JOIN users u ON c.lecturer_id = u.id
            WHERE c.department_id = ? AND c.lecturer_id = ?
            ORDER BY c.code");
        $coursesStmt->bind_param("ii", $deptId, $lecturerFilter);
    } else {
        $coursesStmt = $conn->prepare("SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
            FROM courses c
            LEFT JOIN users u ON c.lecturer_id = u.id
            WHERE c.department_id = ?
            ORDER BY c.code");
        $coursesStmt->bind_param("i", $deptId);
    }
    $coursesStmt->execute();
    $courses = $coursesStmt->get_result();
    $coursesStmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Courses - HOD</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/table-search.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php renderSidebar('courses'); ?>
        
        <main class="main-content">
            <nav class="top-nav">
                <h1>Department Courses</h1>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role">Head of Department</div>
                </div>
            </nav>
            
            <div class="page-content">
                <?php if ($department): ?>
                <div class="card">
                    <div class="card-header">
                        <h3> Add New Course</h3>
                    </div>
                    
                    <div style="padding: 20px;">
                        <?php if ($success): ?>
                            <div class="alert alert-success"> <?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"> <?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" style="max-width: 700px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="course_id" value="<?php echo intval($courseId); ?>">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="font-weight: 600; color: #1a365d;">Course Code *</label>
                                    <input type="text" name="code" class="form-control" placeholder="e.g., CSC101" required style="text-transform: uppercase;" value="<?php echo htmlspecialchars($courseData['code']); ?>">
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #1a365d;">Course Name *</label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g., Introduction to Programming" required value="<?php echo htmlspecialchars($courseData['name']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; color: #1a365d;">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Course description..."><?php echo htmlspecialchars($courseData['description']); ?></textarea>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="font-weight: 600; color: #1a365d;">Credits *</label>
                                    <input type="number" name="credits" class="form-control" value="<?php echo htmlspecialchars($courseData['credits']); ?>" min="1" max="6" required>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #1a365d;">Semester *</label>
                                    <select name="semester" class="form-control" required>
                                        <option value="1" <?php echo $courseData['semester'] == 1 ? 'selected' : ''; ?>>Semester 1</option>
                                        <option value="2" <?php echo $courseData['semester'] == 2 ? 'selected' : ''; ?>>Semester 2</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; color: #1a365d;">Assign Lecturer *</label>
                                <select name="lecturer_id" class="form-control" required>
                                    <option value="0">-- Select Lecturer --</option>
                                    <?php 
                                    if ($lecturers) {
                                        $lecturers->data_seek(0);
                                        while ($lec = $lecturers->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $lec['id']; ?>" <?php echo $lec['id'] == $courseData['lecturer_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lec['name']); ?> (<?php echo htmlspecialchars($lec['email']); ?>)
                                        </option>
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="font-weight: 600; color: #1a365d;">Day</label>
                                    <select name="day" class="form-control">
                                        <option value="">-- Select Day --</option>
                                        <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $dayOption): ?>
                                            <option value="<?php echo $dayOption; ?>" <?php echo $courseData['day'] === $dayOption ? 'selected' : ''; ?>><?php echo $dayOption; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #1a365d;">Location</label>
                                    <input type="text" name="location" class="form-control" placeholder="e.g., Room 204" value="<?php echo htmlspecialchars($courseData['location']); ?>">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="font-weight: 600; color: #1a365d;">Start Time</label>
                                    <input type="time" name="start_time" class="form-control" value="<?php echo htmlspecialchars($courseData['start_time']); ?>">
                                </div>
                                <div>
                                    <label style="font-weight: 600; color: #1a365d;">End Time</label>
                                    <input type="time" name="end_time" class="form-control" value="<?php echo htmlspecialchars($courseData['end_time']); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" name="save_course" class="btn btn-primary" style="padding: 12px 30px;">
                                <?php echo $editMode ? ' Update Course' : ' Create Course'; ?>
                            </button>
                            <?php if ($editMode): ?>
                                <a href="courses.php" class="btn btn-secondary" style="margin-left: 10px; padding: 12px 30px;"> Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="card" style="margin-top:20px;">
                    <div class="card-header">
                        <h3> Enroll Student</h3>
                    </div>
                    <div style="padding:20px; max-width:700px;">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                <div>
                                    <label style="font-weight:600; color:#1a365d;">Course *</label>
                                    <select name="enroll_course_id" class="form-control" required>
                                        <option value="0">-- Select Course --</option>
                                        <?php if ($courses && $courses->num_rows > 0): ?>
                                            <?php while ($c = $courses->fetch_assoc()): ?>
                                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code'] . ' - ' . $c['name']); ?></option>
                                            <?php endwhile; ?>
                                            <?php $courses->data_seek(0); // reset result pointer for listing below ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-weight:600; color:#1a365d;">Student *</label>
                                    <select name="enroll_student_id" class="form-control" required>
                                        <option value="0">-- Select Student --</option>
                                        <?php if (isset($students) && $students->num_rows > 0): ?>
                                            <?php while ($s = $students->fetch_assoc()): ?>
                                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name'] . ' (' . $s['email'] . ')'); ?></option>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <option value="0">No active students</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="enroll_student" class="btn btn-primary"> Enroll Student</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3> <?php echo htmlspecialchars($department['name']); ?> - All Courses</h3>
                        <?php if (isset($_GET['lecturer_id']) && intval($_GET['lecturer_id']) > 0): ?>
                            <p style="margin: 10px 0 0; color: #4A5568; font-size: 15px;">
                                Showing courses for selected lecturer. <a href="courses.php">Clear filter</a>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 15px 20px 0;">
                        <input type="text" id="searchCourses" placeholder="Search by code, name, or lecturer..." style="width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <?php if ($courses && $courses->num_rows > 0): ?>
                    <table class="data-table" id="coursesTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Lecturer</th>
                                <th>Credits</th>
                                <th>Semester</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['name']); ?></td>
                                <td>
                                    <?php if ($course['lecturer_name']): ?>
                                        <?php echo htmlspecialchars($course['lecturer_name']); ?>
                                    <?php else: ?>
                                        <em style="color: #718096;">Not Assigned</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $course['credits']; ?></td>
                                <td><?php echo $course['semester']; ?></td>
                                <td>
                                    <a href="?edit=<?php echo $course['id']; ?>" class="badge badge-primary" style="margin-right: 5px;"> Edit</a>
                                    <form method="POST" action="" style="display:inline; margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="delete_course" value="<?php echo $course['id']; ?>">
                                        <button type="submit" class="badge badge-danger" style="border:none; background:none; cursor:pointer; padding:0;" onclick="return confirm('Delete this course?')"> Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p style="padding: 30px; color: #718096; text-align: center;">
                             No courses offered in this department yet. Create your first course above.
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
<script>initTableSearch('searchCourses', 'coursesTable');</script>
</body>
</html>
