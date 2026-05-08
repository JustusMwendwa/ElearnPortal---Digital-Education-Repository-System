<?php
require_once '../db.php';
requireLogin();

if (getUserRole() !== 'student') {
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get student statistics
$stmt = $pdo->prepare("SELECT 
    COUNT(DISTINCT e.course_id) as enrolled_courses,
    COUNT(DISTINCT r.id) as downloaded_resources,
    COUNT(DISTINCT q.id) as total_questions,
    COUNT(DISTINCT CASE WHEN q.status = 'answered' THEN q.id END) as answered_questions
    FROM users u 
    LEFT JOIN enrollments e ON u.id = e.student_id 
    LEFT JOIN resources r ON 1=1 
    LEFT JOIN questions q ON u.id = q.student_id 
    WHERE u.id = ?");
$stmt->execute([$student_id]);
$stats = $stmt->fetch();

// Get enrolled courses with details
$stmt = $pdo->prepare("SELECT c.*, 
                 (SELECT COUNT(*) FROM resources WHERE course_id = c.id) as resource_count
          FROM courses c 
          JOIN enrollments e ON c.id = e.course_id 
          WHERE e.student_id = ? 
          ORDER BY c.course_code");
$stmt->execute([$student_id]);
$enrolled_courses = $stmt->fetchAll();

// Handle profile update
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $department = $_POST['department'];
        $course = $_POST['course'];
        $year_of_study = $_POST['year_of_study'];
        
        // Update profile
        $stmt = $pdo->prepare("UPDATE users 
                              SET full_name = ?, email = ?, department = ?, course = ?, year_of_study = ? 
                              WHERE id = ?");
        $stmt->execute([$full_name, $email, $department, $course, $year_of_study, $student_id]);
        
        // Update session
        $_SESSION['full_name'] = $full_name;
        
        $success = "Profile updated successfully!";
        
        // Refresh student data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $student['password'])) {
            $error = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long.";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $student_id]);
            $success = "Password changed successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - EduRepository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #3a86ff, #8338ec);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        .profile-header {
            background: linear-gradient(135deg, #3a86ff, #8338ec);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            color: #3a86ff;
            font-size: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 20px;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .tab-content {
            background: white;
            border: 1px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 10px 10px;
            padding: 30px;
        }
        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 15px 30px;
            font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            background: #3a86ff;
            color: white;
            border-color: #3a86ff;
        }
        .course-progress {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
        }
        .course-progress-bar {
            height: 100%;
            border-radius: 4px;
            background: #3a86ff;
        }
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content.active {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header p-4 text-center">
            <h4><i class="fas fa-graduation-cap"></i> EduRepository</h4>
            <p class="mb-0">Student Dashboard</p>
        </div>
        <div class="px-3">
            <div class="user-info text-center p-3 mb-3" style="background: rgba(255,255,255,0.1); border-radius: 10px;">
                <h6><?php echo $student['full_name']; ?></h6>
                <span class="badge bg-success">Student</span>
                <p class="mb-0 small"><?php echo $student['department']; ?></p>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="resources.php">
                        <i class="fas fa-book"></i> Resources
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="courses.php">
                        <i class="fas fa-chalkboard-teacher"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="questions.php">
                        <i class="fas fa-question-circle"></i> My Questions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm rounded mb-4">
            <div class="container-fluid">
                <button class="btn btn-primary" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3">
                        Welcome, <strong><?php echo $student['full_name']; ?></strong>
                    </span>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
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

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <h1 class="display-6 mb-2"><?php echo $student['full_name']; ?></h1>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1">
                                    <i class="fas fa-envelope"></i> <?php echo $student['email']; ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-graduation-cap"></i> <?php echo $student['course']; ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1">
                                    <i class="fas fa-building"></i> <?php echo $student['department']; ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-calendar-alt"></i> Year <?php echo $student['year_of_study']; ?>
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-light text-dark me-2">
                                <i class="fas fa-user"></i> @<?php echo $student['username']; ?>
                            </span>
                            <span class="badge bg-success">
                                <i class="fas fa-calendar"></i> Joined <?php echo date('F Y', strtotime($student['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-chalkboard-teacher stat-icon text-primary"></i>
                        <h3 class="mb-0"><?php echo $stats['enrolled_courses']; ?></h3>
                        <p class="text-muted mb-0">Enrolled Courses</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-book stat-icon text-success"></i>
                        <h3 class="mb-0"><?php echo $stats['downloaded_resources']; ?></h3>
                        <p class="text-muted mb-0">Resources Downloaded</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-question-circle stat-icon text-warning"></i>
                        <h3 class="mb-0"><?php echo $stats['total_questions']; ?></h3>
                        <p class="text-muted mb-0">Questions Asked</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-check-circle stat-icon text-info"></i>
                        <h3 class="mb-0"><?php echo $stats['answered_questions']; ?></h3>
                        <p class="text-muted mb-0">Questions Answered</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="profileTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button">
                        <i class="fas fa-chalkboard"></i> My Courses
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                        <i class="fas fa-chart-line"></i> Activity
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="profileTabContent">
                <!-- Edit Profile Tab -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo $student['email']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department *</label>
                                <input type="text" class="form-control" name="department" 
                                       value="<?php echo htmlspecialchars($student['department']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course/Program *</label>
                                <input type="text" class="form-control" name="course" 
                                       value="<?php echo htmlspecialchars($student['course']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year of Study *</label>
                                <select class="form-select" name="year_of_study" required>
                                    <option value="1" <?php echo $student['year_of_study'] == 1 ? 'selected' : ''; ?>>Year 1</option>
                                    <option value="2" <?php echo $student['year_of_study'] == 2 ? 'selected' : ''; ?>>Year 2</option>
                                    <option value="3" <?php echo $student['year_of_study'] == 3 ? 'selected' : ''; ?>>Year 3</option>
                                    <option value="4" <?php echo $student['year_of_study'] == 4 ? 'selected' : ''; ?>>Year 4</option>
                                    <option value="5" <?php echo $student['year_of_study'] == 5 ? 'selected' : ''; ?>>Year 5</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo $student['username']; ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Member Since</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo date('F d, Y', strtotime($student['created_at'])); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Status</label>
                                <div>
                                    <span class="badge bg-<?php echo $student['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password Tab -->
                <div class="tab-pane fade" id="password" role="tabpanel">
                    <form method="POST" action="">
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="card border-0">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <label class="form-label">Current Password *</label>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">New Password *</label>
                                            <input type="password" class="form-control" name="new_password" required>
                                            <small class="text-muted">Minimum 6 characters</small>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Confirm New Password *</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <strong>Password Requirements:</strong>
                                            <ul class="mb-0 small">
                                                <li>Minimum 6 characters</li>
                                                <li>Use a mix of letters, numbers, and symbols</li>
                                                <li>Avoid common words or patterns</li>
                                                <li>Don't reuse old passwords</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <button type="submit" name="change_password" class="btn btn-warning">
                                                <i class="fas fa-key"></i> Change Password
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- My Courses Tab -->
                <div class="tab-pane fade" id="courses" role="tabpanel">
                    <h5 class="mb-4">Enrolled Courses (<?php echo count($enrolled_courses); ?>)</h5>
                    
                    <?php if (empty($enrolled_courses)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                            <h5>No courses enrolled</h5>
                            <p class="text-muted">You haven't enrolled in any courses yet</p>
                            <a href="courses.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Browse Courses
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Department</th>
                                        <th>Resources</th>
                                        <th>Enrolled Since</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrolled_courses as $course): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $course['course_code']; ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td><?php echo $course['department']; ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $course['resource_count']; ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                // Get enrollment date
                                                $stmt2 = $pdo->prepare("SELECT enrolled_at FROM enrollments 
                                                                      WHERE student_id = ? AND course_id = ?");
                                                $stmt2->execute([$student_id, $course['id']]);
                                                $enrollment = $stmt2->fetch();
                                                ?>
                                                <small><?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="resources.php?course=<?php echo $course['id']; ?>" class="btn btn-outline-info">
                                                        <i class="fas fa-book"></i>
                                                    </a>
                                                    <form method="POST" action="courses.php" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to unenroll from this course?')">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <button type="submit" name="unenroll_course" class="btn btn-outline-danger">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Course Distribution</h6>
                                        <div class="mt-3">
                                            <?php foreach ($enrolled_courses as $course): ?>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <small><?php echo $course['course_code']; ?></small>
                                                        <small><?php echo $course['resource_count']; ?> resources</small>
                                                    </div>
                                                    <div class="course-progress">
                                                        <?php
                                                        $max_resources = max(array_column($enrolled_courses, 'resource_count'));
                                                        $progress = $max_resources > 0 ? ($course['resource_count'] / $max_resources) * 100 : 0;
                                                        ?>
                                                        <div class="course-progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Quick Actions</h6>
                                        <div class="d-grid gap-2 mt-3">
                                            <a href="courses.php" class="btn btn-outline-primary">
                                                <i class="fas fa-plus"></i> Enroll in More Courses
                                            </a>
                                            <a href="resources.php" class="btn btn-outline-success">
                                                <i class="fas fa-book"></i> View All Resources
                                            </a>
                                            <a href="questions.php" class="btn btn-outline-warning">
                                                <i class="fas fa-question-circle"></i> Ask a Question
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Tab -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    <h5 class="mb-4">Recent Activity</h5>
                    
                    <!-- Recent Questions -->
                    <?php
                    $stmt = $pdo->prepare("SELECT q.*, c.course_code 
                                          FROM questions q 
                                          JOIN courses c ON q.course_id = c.id 
                                          WHERE q.student_id = ? 
                                          ORDER BY q.asked_at DESC 
                                          LIMIT 5");
                    $stmt->execute([$student_id]);
                    $recent_questions = $stmt->fetchAll();
                    ?>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-white">
                            <h6 class="mb-0"><i class="fas fa-question-circle"></i> Recent Questions</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_questions)): ?>
                                <p class="text-muted">No questions asked yet</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_questions as $question): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <strong><?php echo htmlspecialchars($question['subject']); ?></strong>
                                                <span class="badge bg-<?php echo $question['status'] == 'pending' ? 'warning' : 'success'; ?>">
                                                    <?php echo ucfirst($question['status']); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted"><?php echo $question['course_code']; ?></small>
                                            <p class="mb-1 small"><?php echo substr($question['message'], 0, 80); ?>...</p>
                                            <small><?php echo date('M d, Y H:i', strtotime($question['asked_at'])); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Downloads -->
                    <?php
                    $stmt = $pdo->prepare("SELECT r.title, r.download_count, c.course_code, r.upload_date 
                                          FROM resources r 
                                          JOIN courses c ON r.course_id = c.id 
                                          JOIN enrollments e ON c.id = e.course_id 
                                          WHERE e.student_id = ? 
                                          ORDER BY r.upload_date DESC 
                                          LIMIT 5");
                    $stmt->execute([$student_id]);
                    $recent_resources = $stmt->fetchAll();
                    ?>
                    
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-download"></i> Recent Resources</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_resources)): ?>
                                <p class="text-muted">No resources downloaded yet</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_resources as $resource): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <strong><?php echo htmlspecialchars($resource['title']); ?></strong>
                                                <span class="badge bg-info"><?php echo $resource['download_count']; ?> downloads</span>
                                            </div>
                                            <small class="text-muted"><?php echo $resource['course_code']; ?></small>
                                            <small><?php echo date('M d, Y', strtotime($resource['upload_date'])); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Activity Statistics -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Monthly Activity</h6>
                                    <div class="mt-3">
                                        <small class="text-muted">This month:</small>
                                        <div class="d-flex justify-content-between mt-2">
                                            <span>Questions Asked</span>
                                            <strong>5</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2">
                                            <span>Resources Downloaded</span>
                                            <strong>12</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2">
                                            <span>Course Engagements</span>
                                            <strong>8</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Study Goals</h6>
                                    <div class="mt-3">
                                        <div class="mb-3">
                                            <small class="text-muted">Course Completion</small>
                                            <div class="course-progress">
                                                <div class="course-progress-bar" style="width: 65%"></div>
                                            </div>
                                            <small class="text-muted">65% complete</small>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted">Resource Usage</small>
                                            <div class="course-progress">
                                                <div class="course-progress-bar" style="width: 80%"></div>
                                            </div>
                                            <small class="text-muted">80% complete</small>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted">Question Resolution</small>
                                            <div class="course-progress">
                                                <div class="course-progress-bar" style="width: 90%"></div>
                                            </div>
                                            <small class="text-muted">90% complete</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('mainContent').classList.toggle('active');
        });

        // Tab activation
        const triggerTabList = [].slice.call(document.querySelectorAll('#profileTab button'));
        triggerTabList.forEach(function (triggerEl) {
            const tabTrigger = new bootstrap.Tab(triggerEl);
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });

        // Password strength indicator
        const newPasswordInput = document.querySelector('input[name="new_password"]');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthBar = document.createElement('div');
                strengthBar.className = 'mt-1';
                
                // Simple password strength check
                let strength = 0;
                if (password.length >= 6) strength++;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                if (password.match(/\d/)) strength++;
                if (password.match(/[^a-zA-Z\d]/)) strength++;
                
                // Update UI
                const existingBar = this.parentElement.querySelector('.password-strength');
                if (existingBar) existingBar.remove();
                
                strengthBar.innerHTML = `
                    <small>Strength: ${strength < 2 ? 'Weak' : strength < 4 ? 'Medium' : 'Strong'}</small>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar bg-${strength < 2 ? 'danger' : strength < 4 ? 'warning' : 'success'}" 
                             style="width: ${strength * 25}%"></div>
                    </div>
                `;
                strengthBar.className = 'password-strength mt-1';
                this.parentElement.appendChild(strengthBar);
            });
        }
    </script>
</body>
</html>