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

// Get enrolled courses
$stmt = $pdo->prepare("SELECT c.*, 
                 (SELECT COUNT(*) FROM resources WHERE course_id = c.id) as resource_count,
                 (SELECT COUNT(*) FROM questions WHERE course_id = c.id AND student_id = ?) as my_questions,
                 (SELECT COUNT(*) FROM questions WHERE course_id = c.id AND student_id = ? AND status = 'answered') as answered_questions
          FROM courses c 
          JOIN enrollments e ON c.id = e.course_id 
          WHERE e.student_id = ? 
          ORDER BY c.course_code");
$stmt->execute([$student_id, $student_id, $student_id]);
$enrolled_courses = $stmt->fetchAll();

// Get available courses (not enrolled)
$enrolled_ids = array_column($enrolled_courses, 'id');
$available_courses = [];
if (!empty($enrolled_ids)) {
    $placeholders = str_repeat('?,', count($enrolled_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT c.*, u.full_name as lecturer_name 
                          FROM courses c 
                          JOIN users u ON c.lecturer_id = u.id 
                          WHERE c.id NOT IN ($placeholders) 
                          AND c.department = ? 
                          ORDER BY c.course_code");
    $params = array_merge($enrolled_ids, [$student['department']]);
    $stmt->execute($params);
    $available_courses = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT c.*, u.full_name as lecturer_name 
                          FROM courses c 
                          JOIN users u ON c.lecturer_id = u.id 
                          WHERE c.department = ? 
                          ORDER BY c.course_code");
    $stmt->execute([$student['department']]);
    $available_courses = $stmt->fetchAll();
}

// Handle course enrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll_course'])) {
    $course_id = $_POST['course_id'];
    
    // Check if already enrolled
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
        $stmt->execute([$student_id, $course_id]);
        $success = "Successfully enrolled in the course!";
        
        // Refresh page to show updated enrollment
        header('Location: courses.php?success=Successfully enrolled in the course');
        exit();
    } else {
        $error = "You are already enrolled in this course.";
    }
}

// Handle course unenrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unenroll_course'])) {
    $course_id = $_POST['course_id'];
    
    $stmt = $pdo->prepare("DELETE FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    $success = "Successfully unenrolled from the course.";
    
    // Refresh page to show updated enrollment
    header('Location: courses.php?success=Successfully unenrolled from the course');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - EduRepository</title>
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
        .course-card {
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .course-icon {
            font-size: 2.5rem;
            color: #3a86ff;
        }
        .enrolled-badge {
            background: linear-gradient(135deg, #38b000, #2d7d46);
            color: white;
        }
        .available-badge {
            background: linear-gradient(135deg, #ffbe0b, #fb5607);
            color: white;
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
                <p class="mb-0 small">Year <?php echo $student['year_of_study']; ?></p>
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
                    <a class="nav-link active" href="courses.php">
                        <i class="fas fa-chalkboard-teacher"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="questions.php">
                        <i class="fas fa-question-circle"></i> My Questions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
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
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-chalkboard-teacher text-primary"></i> My Courses
                    </h1>
                    <p class="text-muted">Manage your course enrollments and access course materials</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Course Overview</h6>
                        <a class="dropdown-item" href="#">
                            Enrolled: <span class="badge bg-success float-end"><?php echo count($enrolled_courses); ?></span>
                        </a>
                        <a class="dropdown-item" href="#">
                            Available: <span class="badge bg-warning float-end"><?php echo count($available_courses); ?></span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <h6 class="dropdown-header">Department</h6>
                        <a class="dropdown-item" href="#">
                            <?php echo $student['department']; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Enrolled Courses -->
            <div class="card mb-5">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bookmark"></i> Enrolled Courses (<?php echo count($enrolled_courses); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($enrolled_courses)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                            <h5>No courses enrolled yet</h5>
                            <p class="text-muted">Browse available courses below and enroll to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($enrolled_courses as $course): 
                                $progress = $course['my_questions'] > 0 
                                    ? round(($course['answered_questions'] / $course['my_questions']) * 100, 0) 
                                    : 0;
                            ?>
                                <div class="col-xl-4 col-lg-6 mb-4">
                                    <div class="card course-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <span class="badge enrolled-badge">Enrolled</span>
                                                </div>
                                                <i class="fas fa-chalkboard course-icon"></i>
                                            </div>
                                            
                                            <h5 class="card-title"><?php echo $course['course_code']; ?></h5>
                                            <h6 class="card-subtitle mb-3 text-muted"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                            
                                            <p class="card-text small text-muted">
                                                <?php echo substr(htmlspecialchars($course['description']), 0, 100); ?>
                                                <?php echo strlen($course['description']) > 100 ? '...' : ''; ?>
                                            </p>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Department:</small>
                                                <span class="badge bg-light text-dark"><?php echo $course['department']; ?></span>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <h4 class="mb-0"><?php echo $course['resource_count']; ?></h4>
                                                        <small class="text-muted">Resources</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <h4 class="mb-0"><?php echo $course['my_questions']; ?></h4>
                                                        <small class="text-muted">My Qs</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted d-block mb-1">Question Response Rate</small>
                                                <div class="course-progress">
                                                    <div class="course-progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-1">
                                                    <small><?php echo $progress; ?>%</small>
                                                    <small><?php echo $course['answered_questions']; ?> of <?php echo $course['my_questions']; ?> answered</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="d-grid gap-2">
                                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-eye"></i> View Course
                                                </a>
                                                <div class="btn-group" role="group">
                                                    <a href="resources.php?course=<?php echo $course['id']; ?>" class="btn btn-outline-primary">
                                                        <i class="fas fa-book"></i> Resources
                                                    </a>
                                                    <a href="questions.php" class="btn btn-outline-warning">
                                                        <i class="fas fa-question-circle"></i> Ask
                                                    </a>
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to unenroll from this course?')">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <button type="submit" name="unenroll_course" class="btn btn-outline-danger">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Courses -->
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle"></i> Available Courses (<?php echo count($available_courses); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($available_courses)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>No available courses</h5>
                            <p class="text-muted">You are enrolled in all courses from your department</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($available_courses as $course): ?>
                                <div class="col-xl-4 col-lg-6 mb-4">
                                    <div class="card course-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <span class="badge available-badge">Available</span>
                                                </div>
                                                <i class="fas fa-chalkboard course-icon text-warning"></i>
                                            </div>
                                            
                                            <h5 class="card-title"><?php echo $course['course_code']; ?></h5>
                                            <h6 class="card-subtitle mb-3 text-muted"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                            
                                            <p class="card-text small text-muted">
                                                <?php echo substr(htmlspecialchars($course['description']), 0, 100); ?>
                                                <?php echo strlen($course['description']) > 100 ? '...' : ''; ?>
                                            </p>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Department:</small>
                                                <span class="badge bg-light text-dark"><?php echo $course['department']; ?></span>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Lecturer:</small>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-tie text-warning me-2"></i>
                                                    <span><?php echo $course['lecturer_name']; ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <?php
                                                // Get course statistics
                                                $stmt2 = $pdo->prepare("SELECT 
                                                    COUNT(*) as enrolled_students,
                                                    COUNT(DISTINCT r.id) as resource_count
                                                    FROM enrollments e 
                                                    LEFT JOIN resources r ON e.course_id = r.course_id 
                                                    WHERE e.course_id = ?");
                                                $stmt2->execute([$course['id']]);
                                                $stats = $stmt2->fetch();
                                                ?>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="text-center">
                                                            <h4 class="mb-0"><?php echo $stats['enrolled_students']; ?></h4>
                                                            <small class="text-muted">Students</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="text-center">
                                                            <h4 class="mb-0"><?php echo $stats['resource_count']; ?></h4>
                                                            <small class="text-muted">Resources</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <form method="POST" action="">
                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                <button type="submit" name="enroll_course" class="btn btn-warning w-100">
                                                    <i class="fas fa-plus"></i> Enroll Now
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Course Statistics -->
            <?php if (!empty($enrolled_courses)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Course Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Resources</th>
                                        <th>My Questions</th>
                                        <th>Answered</th>
                                        <th>Response Rate</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrolled_courses as $course): 
                                        $progress = $course['my_questions'] > 0 
                                            ? round(($course['answered_questions'] / $course['my_questions']) * 100, 0) 
                                            : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $course['course_code']; ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $course['resource_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $course['my_questions']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $course['answered_questions']; ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="course-progress me-2 flex-grow-1">
                                                        <div class="course-progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                    <small><?php echo $progress; ?>%</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge enrolled-badge">Enrolled</span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="resources.php?course=<?php echo $course['id']; ?>" class="btn btn-outline-info">
                                                        <i class="fas fa-book"></i>
                                                    </a>
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to unenroll from this course?')">
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
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('mainContent').classList.toggle('active');
        });
    </script>
</body>
</html>