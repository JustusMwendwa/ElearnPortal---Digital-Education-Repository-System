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
$stmt = $pdo->prepare("SELECT c.* FROM courses c 
                       JOIN enrollments e ON c.id = e.course_id 
                       WHERE e.student_id = ?");
$stmt->execute([$student_id]);
$enrolled_courses = $stmt->fetchAll();

// Get recent resources for enrolled courses
$resources = [];
if (!empty($enrolled_courses)) {
    $course_ids = array_column($enrolled_courses, 'id');
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT r.*, c.course_code, c.course_name, u.full_name as uploaded_by_name 
                          FROM resources r 
                          JOIN courses c ON r.course_id = c.id 
                          JOIN users u ON r.uploaded_by = u.id 
                          WHERE r.course_id IN ($placeholders) 
                          ORDER BY r.upload_date DESC LIMIT 10");
    $stmt->execute($course_ids);
    $resources = $stmt->fetchAll();
}

// Get upcoming assignments (if you have an assignments table)
// For now, we'll just show resources as assignments

// Handle question submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ask_question'])) {
    $course_id = $_POST['course_id'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    // Get lecturer for the course
    $stmt = $pdo->prepare("SELECT lecturer_id FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if ($course && $course['lecturer_id']) {
        $stmt = $pdo->prepare("INSERT INTO questions (student_id, lecturer_id, course_id, subject, message) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $course['lecturer_id'], $course_id, $subject, $message]);
        $success = "Your question has been submitted successfully!";
    } else {
        $error = "Unable to submit question. Please try again.";
    }
}

// Get student's questions
$stmt = $pdo->prepare("SELECT q.*, c.course_code, c.course_name, u.full_name as lecturer_name 
                      FROM questions q 
                      JOIN courses c ON q.course_id = c.id 
                      JOIN users u ON q.lecturer_id = u.id 
                      WHERE q.student_id = ? 
                      ORDER BY q.asked_at DESC");
$stmt->execute([$student_id]);
$questions = $stmt->fetchAll();

// Get recent announcements (if you have an announcements table)
// For now, we'll use course announcements
$recent_announcements = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - EduRepository</title>
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
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 10px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #3a86ff, #8338ec);
            color: white;
        }
        .file-icon {
            font-size: 2rem;
            color: #3a86ff;
        }
        .resource-card {
            cursor: pointer;
        }
        .announcement-card {
            border-left: 4px solid #ffbe0b;
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
                    <a class="nav-link active" href="dashboard.php">
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
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="container-fluid">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?php echo count($enrolled_courses); ?></h2>
                                    <p class="mb-0">Enrolled Courses</p>
                                </div>
                                <i class="fas fa-chalkboard-teacher fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?php echo count($resources); ?></h2>
                                    <p class="mb-0">Available Resources</p>
                                </div>
                                <i class="fas fa-file-alt fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php
                                    $pending = array_filter($questions, fn($q) => $q['status'] == 'pending');
                                    ?>
                                    <h2 class="mb-0"><?php echo count($pending); ?></h2>
                                    <p class="mb-0">Pending Questions</p>
                                </div>
                                <i class="fas fa-question-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php
                                    $answered = array_filter($questions, fn($q) => $q['status'] == 'answered');
                                    ?>
                                    <h2 class="mb-0"><?php echo count($answered); ?></h2>
                                    <p class="mb-0">Answered Questions</p>
                                </div>
                                <i class="fas fa-check-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Recent Resources -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-book"></i> Recent Resources</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($resources)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                    <p>No resources available for your courses</p>
                                    <a href="courses.php" class="btn btn-primary">Browse Courses</a>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach (array_slice($resources, 0, 6) as $resource): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 resource-card" onclick="window.location='../download.php?id=<?php echo $resource['id']; ?>'">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-start">
                                                        <?php
                                                        $icon_class = '';
                                                        $icon_color = '';
                                                        switch($resource['file_type']) {
                                                            case 'pdf': 
                                                                $icon_class = 'fa-file-pdf'; 
                                                                $icon_color = 'text-danger';
                                                                break;
                                                            case 'doc': case 'docx': 
                                                                $icon_class = 'fa-file-word'; 
                                                                $icon_color = 'text-primary';
                                                                break;
                                                            case 'ppt': case 'pptx': 
                                                                $icon_class = 'fa-file-powerpoint'; 
                                                                $icon_color = 'text-warning';
                                                                break;
                                                            case 'video': 
                                                                $icon_class = 'fa-file-video'; 
                                                                $icon_color = 'text-success';
                                                                break;
                                                            default: 
                                                                $icon_class = 'fa-file'; 
                                                                $icon_color = 'text-secondary';
                                                        }
                                                        ?>
                                                        <i class="fas <?php echo $icon_class; ?> <?php echo $icon_color; ?> fa-2x me-3"></i>
                                                        <div>
                                                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                                            <small class="text-muted">
                                                                <?php echo $resource['course_code']; ?> • 
                                                                <?php echo $resource['uploaded_by_name']; ?>
                                                            </small>
                                                            <div class="mt-2">
                                                                <span class="badge bg-light text-dark">
                                                                    <?php echo strtoupper($resource['file_type']); ?>
                                                                </span>
                                                                <small class="text-muted ms-2">
                                                                    <?php echo date('M d', strtotime($resource['upload_date'])); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-transparent">
                                                    <a href="../download.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="resources.php" class="btn btn-outline-primary">
                                        <i class="fas fa-list"></i> View All Resources
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Ask Question Form -->
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0"><i class="fas fa-question-circle"></i> Ask a Question</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Select Course</label>
                                        <select class="form-select" name="course_id" required>
                                            <option value="">Choose a course...</option>
                                            <?php foreach ($enrolled_courses as $course): ?>
                                                <option value="<?php echo $course['id']; ?>">
                                                    <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Subject</label>
                                        <input type="text" class="form-control" name="subject" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Your Question</label>
                                    <textarea class="form-control" name="message" rows="4" required></textarea>
                                </div>
                                <button type="submit" name="ask_question" class="btn btn-warning">
                                    <i class="fas fa-paper-plane"></i> Submit Question
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- My Courses -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> My Courses</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($enrolled_courses)): ?>
                                <p class="text-muted">No courses enrolled yet</p>
                                <a href="courses.php" class="btn btn-info btn-sm">Browse Courses</a>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($enrolled_courses, 0, 5) as $course): ?>
                                        <a href="course.php?id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <strong><?php echo $course['course_code']; ?></strong>
                                                <small><?php echo $course['department']; ?></small>
                                            </div>
                                            <p class="mb-1 small"><?php echo $course['course_name']; ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($enrolled_courses) > 5): ?>
                                    <div class="text-center mt-3">
                                        <a href="courses.php" class="btn btn-outline-info btn-sm">
                                            View All Courses
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Questions -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-comments"></i> Recent Questions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($questions)): ?>
                                <p class="text-muted">No questions asked yet</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($questions, 0, 5) as $question): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <strong class="mb-1"><?php echo htmlspecialchars($question['subject']); ?></strong>
                                                <span class="badge bg-<?php echo $question['status'] == 'pending' ? 'warning' : 'success'; ?>">
                                                    <?php echo ucfirst($question['status']); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted"><?php echo $question['course_code']; ?></small>
                                            <p class="mb-1 small"><?php echo substr($question['message'], 0, 50); ?>...</p>
                                            <small><?php echo date('M d, Y', strtotime($question['asked_at'])); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="questions.php" class="btn btn-outline-success btn-sm">
                                        View All Questions
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-link"></i> Quick Links</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="resources.php" class="btn btn-outline-primary">
                                    <i class="fas fa-book"></i> Study Materials
                                </a>
                                <a href="courses.php" class="btn btn-outline-success">
                                    <i class="fas fa-chalkboard"></i> My Courses
                                </a>
                                <a href="questions.php" class="btn btn-outline-warning">
                                    <i class="fas fa-question-circle"></i> My Questions
                                </a>
                                <a href="profile.php" class="btn btn-outline-info">
                                    <i class="fas fa-user"></i> My Profile
                                </a>
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
    </script>
</body>
</html>