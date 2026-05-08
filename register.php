<?php
require_once 'db.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';
$success = '';

// Departments list (can be moved to database)
$departments = [
    'Computer Science',
    'Information Technology',
    'Software Engineering',
    'Business Administration',
    'Accounting',
    'Economics',
    'Mathematics',
    'Physics',
    'Chemistry',
    'Biology',
    'English Literature',
    'History',
    'Psychology',
    'Education',
    'Medicine',
    'Engineering',
    'Law',
    'Arts'
];

// Courses list (can be moved to database)
$courses = [
    'Computer Science' => [
        'BSc Computer Science',
        'BSc Software Engineering',
        'BSc Information Technology',
        'MSc Computer Science',
        'PhD Computer Science'
    ],
    'Business Administration' => [
        'BBA',
        'MBA',
        'Executive MBA'
    ],
    'Engineering' => [
        'BSc Civil Engineering',
        'BSc Electrical Engineering',
        'BSc Mechanical Engineering',
        'BSc Chemical Engineering'
    ],
    'Medicine' => [
        'MBBS',
        'BDS',
        'BSc Nursing'
    ]
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate inputs
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $department = $_POST['department'] ?? '';
    $course = $_POST['course'] ?? '';
    $year_of_study = $_POST['year_of_study'] ?? '';
    $student_id = trim($_POST['student_id'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');
    
    // Validate inputs
    if (empty($username) || empty($password) || empty($email) || empty($full_name) || empty($role)) {
        $error = 'Please fill all required fields.';
    } elseif (strlen($username) < 4 || strlen($username) > 50) {
        $error = 'Username must be between 4 and 50 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $error = 'Full name must be between 2 and 100 characters.';
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists. Please choose another.';
        } else {
            // Additional validation based on role
            if ($role === 'student') {
                if (empty($department) || empty($course) || empty($year_of_study) || empty($student_id)) {
                    $error = 'Please fill all student information.';
                }
            } elseif ($role === 'lecturer') {
                if (empty($department) || empty($staff_id)) {
                    $error = 'Please fill all lecturer information.';
                }
            }
            
            if (empty($error)) {
                try {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Prepare student ID or staff ID
                    $identification_number = ($role === 'student') ? $student_id : (($role === 'lecturer') ? $staff_id : '');
                    
                    // Insert user into database
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, department, course, year_of_study, identification_number) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $username,
                        $hashed_password,
                        $email,
                        $full_name,
                        $role,
                        $department,
                        $role === 'student' ? $course : null,
                        $role === 'student' ? $year_of_study : null,
                        $identification_number
                    ]);
                    
                    // Get the new user's ID
                    $user_id = $pdo->lastInsertId();
                    
                    // Automatically log in the user
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['department'] = $user['department'];
                        
                        // Redirect to appropriate dashboard
                        redirectBasedOnRole();
                    }
                } catch (PDOException $e) {
                    $error = 'Registration failed. Please try again. Error: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Digital Education Repository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #3a86ff, #8338ec);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-body {
            padding: 30px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .form-section h5 {
            color: #3a86ff;
            border-bottom: 2px solid #3a86ff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 20px;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .step.active .step-circle {
            background: #3a86ff;
            color: white;
        }
        .step-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .step.active .step-label {
            color: #3a86ff;
            font-weight: 500;
        }
        .role-option {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        .role-option:hover {
            border-color: #3a86ff;
            transform: translateY(-2px);
        }
        .role-option.selected {
            border-color: #3a86ff;
            background: rgba(58, 134, 255, 0.1);
        }
        .role-option i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #3a86ff;
        }
        .role-option h6 {
            margin-bottom: 5px;
            font-weight: 600;
        }
        .role-option p {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0;
        }
        .btn-register {
            background: linear-gradient(135deg, #3a86ff, #8338ec);
            border: none;
            color: white;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 30px;
            transition: all 0.3s;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(58, 134, 255, 0.4);
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        .password-strength {
            height: 5px;
            background: #e9ecef;
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        .strength-weak {
            background: #dc3545;
        }
        .strength-fair {
            background: #ffc107;
        }
        .strength-good {
            background: #17a2b8;
        }
        .strength-strong {
            background: #28a745;
        }
        .form-control:focus {
            border-color: #3a86ff;
            box-shadow: 0 0 0 0.2rem rgba(58, 134, 255, 0.25);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h2><i class="fas fa-graduation-cap"></i> EduRepository</h2>
            <p class="mb-0">Create Your Account</p>
        </div>
        
        <div class="register-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step active" id="step1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Select Role</div>
                </div>
                <div class="step" id="step2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Account Info</div>
                </div>
                <div class="step" id="step3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Personal Info</div>
                </div>
            </div>
            
            <form method="POST" action="" id="registrationForm">
                <!-- Step 1: Role Selection -->
                <div class="form-section" id="roleSection">
                    <h5>Select Your Role</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="role-option" data-role="student">
                                <i class="fas fa-user-graduate"></i>
                                <h6>Student</h6>
                                <p>Access course materials, submit assignments, ask questions</p>
                                <input type="radio" name="role" value="student" style="display: none;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="role-option" data-role="lecturer">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <h6>Lecturer</h6>
                                <p>Upload materials, manage courses, answer student questions</p>
                                <input type="radio" name="role" value="lecturer" style="display: none;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="role-option" data-role="admin" style="opacity: 0.5; cursor: not-allowed;">
                                <i class="fas fa-cogs"></i>
                                <h6>Administrator</h6>
                                <p>System management (Only by invitation)</p>
                                <span class="badge bg-danger">By Invitation Only</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-primary" onclick="nextStep()">Next <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
                
                <!-- Step 2: Account Information -->
                <div class="form-section" id="accountSection" style="display: none;">
                    <h5>Account Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="username" required
                                       placeholder="Choose a username" minlength="4" maxlength="50">
                            </div>
                            <small class="text-muted">4-50 characters, letters and numbers only</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" required
                                       placeholder="your.email@example.com">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" id="password" required
                                       placeholder="At least 8 characters" minlength="8">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrength"></div>
                            </div>
                            <small class="text-muted" id="passwordHint"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required
                                       placeholder="Re-enter your password">
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted" id="passwordMatch"></small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-outline-secondary" onclick="prevStep()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Step 3: Personal Information -->
                <div class="form-section" id="personalSection" style="display: none;">
                    <h5>Personal Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" name="full_name" required
                                       placeholder="Your full name">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department *</label>
                            <select class="form-select" name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Student Specific Fields -->
                    <div id="studentFields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Student ID *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                                    <input type="text" class="form-control" name="student_id" 
                                           placeholder="e.g., CS/2021/001">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course/Program *</label>
                                <select class="form-select" name="course">
                                    <option value="">Select Course</option>
                                    <option value="BSc Computer Science">BSc Computer Science</option>
                                    <option value="BSc Software Engineering">BSc Software Engineering</option>
                                    <option value="BSc Information Technology">BSc Information Technology</option>
                                    <option value="BSc Data Science">BSc Data Science</option>
                                    <option value="BSc Cyber Security">BSc Cyber Security</option>
                                    <option value="BBA">Bachelor of Business Administration</option>
                                    <option value="MBA">Master of Business Administration</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year of Study *</label>
                                <select class="form-select" name="year_of_study">
                                    <option value="">Select Year</option>
                                    <option value="1">Year 1</option>
                                    <option value="2">Year 2</option>
                                    <option value="3">Year 3</option>
                                    <option value="4">Year 4</option>
                                    <option value="5">Year 5</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lecturer Specific Fields -->
                    <div id="lecturerFields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Staff ID *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                                    <input type="text" class="form-control" name="staff_id" 
                                           placeholder="e.g., LEC/2021/001">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Terms and Conditions -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> *
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-outline-secondary" onclick="prevStep()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="btn btn-success btn-register">
                            <i class="fas fa-user-plus"></i> Create Account
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php" class="fw-bold">Login here</a>
            </div>
        </div>
    </div>
    
    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Digital Education Repository System - Terms of Use</h6>
                    <p><strong>Last Updated:</strong> <?php echo date('F d, Y'); ?></p>
                    
                    <h6>1. Acceptance of Terms</h6>
                    <p>By registering for an account, you agree to comply with and be bound by these terms and conditions.</p>
                    
                    <h6>2. User Accounts</h6>
                    <ul>
                        <li>You are responsible for maintaining the confidentiality of your account</li>
                        <li>You agree to provide accurate and complete information</li>
                        <li>You must notify us immediately of any unauthorized use of your account</li>
                    </ul>
                    
                    <h6>3. Acceptable Use</h6>
                    <ul>
                        <li>Use the system for educational purposes only</li>
                        <li>Do not upload copyrighted material without permission</li>
                        <li>Do not share your account credentials</li>
                        <li>Respect other users and maintain professional conduct</li>
                    </ul>
                    
                    <h6>4. Intellectual Property</h6>
                    <p>All educational materials remain the property of their respective owners. The system only facilitates sharing.</p>
                    
                    <h6>5. Privacy</h6>
                    <p>We respect your privacy and protect your personal information in accordance with our Privacy Policy.</p>
                    
                    <h6>6. System Availability</h6>
                    <p>We strive to maintain system availability but cannot guarantee uninterrupted access.</p>
                    
                    <p><strong>By creating an account, you acknowledge that you have read, understood, and agree to these terms.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        const totalSteps = 3;
        let selectedRole = '';
        
        // Role selection
        document.querySelectorAll('.role-option[data-role]').forEach(option => {
            if (!option.style.opacity || option.style.opacity !== '0.5') {
                option.addEventListener('click', function() {
                    // Remove selection from all options
                    document.querySelectorAll('.role-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Select this option
                    this.classList.add('selected');
                    selectedRole = this.dataset.role;
                    
                    // Update hidden radio button
                    document.querySelector(`input[name="role"][value="${selectedRole}"]`).checked = true;
                    
                    // Show role-specific fields in step 3
                    updateRoleSpecificFields();
                });
            }
        });
        
        // Step navigation
        function nextStep() {
            if (validateCurrentStep()) {
                if (currentStep < totalSteps) {
                    // Hide current step
                    document.getElementById(getStepSection(currentStep)).style.display = 'none';
                    document.getElementById(`step${currentStep}`).classList.remove('active');
                    
                    // Show next step
                    currentStep++;
                    document.getElementById(getStepSection(currentStep)).style.display = 'block';
                    document.getElementById(`step${currentStep}`).classList.add('active');
                    
                    // Update role-specific fields when moving to step 3
                    if (currentStep === 3) {
                        updateRoleSpecificFields();
                    }
                }
            }
        }
        
        function prevStep() {
            if (currentStep > 1) {
                // Hide current step
                document.getElementById(getStepSection(currentStep)).style.display = 'none';
                document.getElementById(`step${currentStep}`).classList.remove('active');
                
                // Show previous step
                currentStep--;
                document.getElementById(getStepSection(currentStep)).style.display = 'block';
                document.getElementById(`step${currentStep}`).classList.add('active');
            }
        }
        
        function getStepSection(step) {
            switch(step) {
                case 1: return 'roleSection';
                case 2: return 'accountSection';
                case 3: return 'personalSection';
                default: return '';
            }
        }
        
        function validateCurrentStep() {
            if (currentStep === 1) {
                if (!selectedRole) {
                    alert('Please select your role.');
                    return false;
                }
                return true;
            } else if (currentStep === 2) {
                const username = document.querySelector('input[name="username"]').value;
                const email = document.querySelector('input[name="email"]').value;
                const password = document.querySelector('input[name="password"]').value;
                const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
                
                if (!username || username.length < 4) {
                    alert('Username must be at least 4 characters.');
                    return false;
                }
                
                if (!email || !email.includes('@')) {
                    alert('Please enter a valid email address.');
                    return false;
                }
                
                if (!password || password.length < 8) {
                    alert('Password must be at least 8 characters.');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    alert('Passwords do not match.');
                    return false;
                }
                
                return true;
            }
            return true;
        }
        
        function updateRoleSpecificFields() {
            const studentFields = document.getElementById('studentFields');
            const lecturerFields = document.getElementById('lecturerFields');
            
            // Hide all fields first
            studentFields.style.display = 'none';
            lecturerFields.style.display = 'none';
            
            // Clear required fields
            document.querySelectorAll('#studentFields input, #studentFields select').forEach(field => {
                field.required = false;
            });
            document.querySelectorAll('#lecturerFields input').forEach(field => {
                field.required = false;
            });
            
            // Show relevant fields based on role
            if (selectedRole === 'student') {
                studentFields.style.display = 'block';
                document.querySelectorAll('#studentFields input, #studentFields select').forEach(field => {
                    field.required = true;
                });
            } else if (selectedRole === 'lecturer') {
                lecturerFields.style.display = 'block';
                document.querySelectorAll('#lecturerFields input').forEach(field => {
                    field.required = true;
                });
            }
        }
        
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const hint = document.getElementById('passwordHint');
            
            let strength = 0;
            let hintText = '';
            
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.className = 'password-strength-bar strength-weak';
                hintText = 'Weak password. Try adding uppercase letters, numbers, and special characters.';
            } else if (strength < 75) {
                strengthBar.className = 'password-strength-bar strength-fair';
                hintText = 'Fair password. Could be stronger.';
            } else if (strength < 100) {
                strengthBar.className = 'password-strength-bar strength-good';
                hintText = 'Good password!';
            } else {
                strengthBar.className = 'password-strength-bar strength-strong';
                hintText = 'Strong password! Excellent.';
            }
            
            hint.textContent = hintText;
        });
        
        // Password confirmation checker
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm === '') {
                matchText.textContent = '';
                matchText.className = 'text-muted';
            } else if (password === confirm) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'text-success';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'text-danger';
            }
        });
        
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmField = document.getElementById('confirmPassword');
            const icon = this.querySelector('i');
            
            if (confirmField.type === 'password') {
                confirmField.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                confirmField.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        
        // Form submission validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const terms = document.getElementById('terms');
            
            if (!terms.checked) {
                e.preventDefault();
                alert('You must agree to the Terms and Conditions.');
                return false;
            }
            
            // Validate all required fields based on role
            if (selectedRole === 'student') {
                const studentId = document.querySelector('input[name="student_id"]').value;
                const course = document.querySelector('select[name="course"]').value;
                const year = document.querySelector('select[name="year_of_study"]').value;
                
                if (!studentId || !course || !year) {
                    e.preventDefault();
                    alert('Please fill all student information fields.');
                    return false;
                }
            } else if (selectedRole === 'lecturer') {
                const staffId = document.querySelector('input[name="staff_id"]').value;
                
                if (!staffId) {
                    e.preventDefault();
                    alert('Please provide your Staff ID.');
                    return false;
                }
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            submitBtn.disabled = true;
            
            return true;
        });
        
        // Department change handler for course options
        document.querySelector('select[name="department"]').addEventListener('change', function() {
            const courseSelect = document.querySelector('select[name="course"]');
            const department = this.value;
            
            // Reset course options
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            
            // Add department-specific courses
            const coursesByDept = {
                'Computer Science': ['BSc Computer Science', 'BSc Software Engineering', 'BSc Information Technology', 'MSc Computer Science'],
                'Business Administration': ['BBA', 'MBA', 'Executive MBA'],
                'Engineering': ['BSc Civil Engineering', 'BSc Electrical Engineering', 'BSc Mechanical Engineering'],
                'Medicine': ['MBBS', 'BDS', 'BSc Nursing'],
                'Information Technology': ['BSc IT', 'BSc Cyber Security', 'BSc Data Science']
            };
            
            if (coursesByDept[department]) {
                coursesByDept[department].forEach(course => {
                    const option = document.createElement('option');
                    option.value = course;
                    option.textContent = course;
                    courseSelect.appendChild(option);
                });
            }
        });
        
        // Initialize with first step active
        document.getElementById('step1').classList.add('active');
        document.getElementById('roleSection').style.display = 'block';
    </script>
</body>
</html>