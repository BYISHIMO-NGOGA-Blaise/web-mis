<?php
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    $redirects = [
        'admin' => 'admin/dashboard.php',
        'hod' => 'HOD/dashboard.php',
        'lecturer' => 'lecturer/dashboard.php',
        'student' => 'student/dashboard.php',
    ];
    header('Location: ' . ($redirects[$_SESSION['role']] ?? 'index.php'));
    exit();
}

$error = '';
$success = '';

$conn = getDBConnection();
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");
$conn->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please try again.';
    } else {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $regNumber = sanitize($_POST['reg_number'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $dob = sanitize($_POST['date_of_birth'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $nationalId = sanitize($_POST['national_id'] ?? '');
    $emergencyName = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = sanitize($_POST['emergency_contact'] ?? '');
    $departmentId = intval($_POST['department_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $roleId = intval($_POST['role_id'] ?? 4);

    $errors = [];

    if (empty($firstName)) $errors[] = "First name is required.";
    if (empty($lastName)) $errors[] = "Last name is required.";
    if (empty($regNumber)) $errors[] = "Registration number is required.";
    if (empty($email)) $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($dob)) $errors[] = "Date of birth is required.";
    if (empty($gender)) $errors[] = "Gender is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (empty($nationalId)) $errors[] = "National ID is required.";
    if ($departmentId <= 0) $errors[] = "Please select a department.";
    if (empty($password)) $errors[] = "Password is required.";
    elseif (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
    if ($password !== $confirmPassword) $errors[] = "Passwords do not match.";

    $conn = getDBConnection();

    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already registered.";
        }
        $stmt->close();
    }

    if (!empty($regNumber)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE reg_number = ?");
        $stmt->bind_param("s", $regNumber);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Registration number already exists.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name, reg_number, phone, date_of_birth, gender, address, national_id, emergency_contact_name, emergency_contact, department_id, role_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("ssssssssssssii", $email, $passwordHash, $firstName, $lastName, $regNumber, $phone, $dob, $gender, $address, $nationalId, $emergencyName, $emergencyPhone, $departmentId, $roleId);

        if ($stmt->execute()) {
            $success = "Registration successful! You can now login.";
            $_POST = array();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }

        $stmt->close();
    }

    $conn->close();

    if (!empty($errors)) {
        $error = implode(" ", $errors);
    }
    } // end CSRF check
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="reg-body">
    <div class="reg-container">
        <div class="reg-header">
            <h1> <?php echo APP_NAME; ?></h1>
            <p>Student Registration  Fill in your details to create an account</p>
        </div>
        <div class="reg-form">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="role_id" value="4">

                <div class="section-title"> Personal Information</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input class="form-control" type="text" name="first_name" placeholder="e.g. Jean" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input class="form-control" type="text" name="last_name" placeholder="e.g. Ndayisaba" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row three">
                    <div class="form-group">
                        <label>Registration Number *</label>
                        <input class="form-control" type="text" name="reg_number" placeholder="e.g. UBR/2026/001" required value="<?php echo htmlspecialchars($_POST['reg_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth *</label>
                        <input class="form-control" type="date" name="date_of_birth" required value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select class="form-control" name="gender" required>
                            <option value="">-- Select --</option>
                            <option value="Male" <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (($_POST['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="section-title"> Contact Details</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input class="form-control" type="email" name="email" placeholder="e.g. jean@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input class="form-control" type="tel" name="phone" placeholder="e.g. +250 788 123 456" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Address *</label>
                    <textarea class="form-control" name="address" placeholder="e.g. Kigali, Rwanda" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>

                <div class="section-title"> Academic Information</div>
                <div class="form-group">
                    <label>Department *</label>
                    <select class="form-control" name="department_id" required>
                        <option value="">-- Select Department --</option>
                        <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo (intval($_POST['department_id'] ?? 0) === $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="section-title"> Identification</div>
                <div class="form-group">
                    <label>National ID Number *</label>
                    <input class="form-control" type="text" name="national_id" placeholder="e.g. 1199010112345678" required value="<?php echo htmlspecialchars($_POST['national_id'] ?? ''); ?>">
                </div>

                <div class="section-title"> Emergency Contact</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Person Name</label>
                        <input class="form-control" type="text" name="emergency_contact_name" placeholder="e.g. Marie Ndayisaba" value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Person Phone</label>
                        <input class="form-control" type="tel" name="emergency_contact" placeholder="e.g. +250 788 654 321" value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>">
                    </div>
                </div>

                <div class="section-title"> Account Security</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password *</label>
                        <input class="form-control" type="password" name="password" placeholder="Minimum 8 characters" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password *</label>
                        <input class="form-control" type="password" name="confirm_password" placeholder="Re-enter password" required>
                    </div>
                </div>

                <p class="required-note">* Required fields</p>
                <button type="submit" class="btn btn-primary btn-block"> Create Student Account</button>
            </form>

            <div class="footer-link">
                Already have an account? <a href="index.php">Login here</a>
            </div>
        </div>
    </div>
</body>
</html>

