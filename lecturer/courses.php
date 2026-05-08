<?php
require_once '../db.php';
requireLogin();

if (getUserRole() !== 'lecturer') {
    header('Location: ../login.php');
    exit();
}

$lecturer_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$lecturer_id]);
$lecturer = $stmt->fetch();

// Get lecturer's courses
$query = "SELECT c.*, 
                 (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
                 (SELECT COUNT(*) FROM resources WHERE course_id = c.id) as resource_count,
                 (SELECT COUNT(*) FROM questions WHERE course_id = c.id AND status = 'pending') as pending_questions
          FROM courses c 
          WHERE c.lecturer_id = ? 
          ORDER BY c.course_code";
$stmt = $pdo->prepare($query);
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll();

// Get total statistics
$total_students = 0;
$total_resources = 0;
$total_pending_questions = 0;
foreach ($courses as $course) {
    $total_students += $course['student_count'];
    $total_resources += $course['resource_count'];
    $total_pending_questions += $course['pending_questions'];
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
            background: linear-gradient(135deg, #ffbe0b, #fb5607);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
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
            color: #ffbe0b;
        }
        .stat-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        .progress {
            height: 8px;
        }
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header p-4 text-center">
            <h4><i class="fas fa-chalkboard-teacher"></i> Lecturer Panel</h4>
            <p class="mb-0"><?php echo $lecturer['full_name']; ?></p>
            <span class="badge bg-warning">Lecturer</span>
        </div>
        <div class="px-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_resources.php">
                        <i class="fas fa-book"></i> Manage Resources
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="questions.php">
                        <i class="fas fa-question-circle"></i> Questions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="courses.php">
                        <i class="fas fa-chalkboard"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-users"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="upload.php">
                        <i class="fas fa-upload"></i> Upload Resource
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
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm rounded mb-4">
            <div class="container-fluid">
                <a href="dashboard.php" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3">
                        <strong><?php echo $lecturer['full_name']; ?></strong>
                    </span>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-chalkboard-teacher text-warning"></i> My Courses
                    </h1>
                    <p class="text-muted">Manage and view details of your assigned courses</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar"></i> Course Statistics
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Overview</h6>
                        <a class="dropdown-item" href="#">
                            Total Courses: <span class="badge bg-primary float-end"><?php echo count($courses); ?></span>
                        </a>
                        <a class="dropdown-item" href="#">
                            Total Students: <span class="badge bg-success float-end"><?php echo $total_students; ?></span>
                        </a>
                        <a class="dropdown-item" href="#">
                            Total Resources: <span class="badge bg-info float-end"><?php echo $total_resources; ?></span>
                        </a>
                        <a class="dropdown-item" href="#">
                            Pending Questions: <span class="badge bg-warning float-end"><?php echo $total_pending_questions; ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?php echo count($courses); ?></h2>
                                    <p class="mb-0">Courses</p>
                                </div>
                                <i class="fas fa-chalkboard-teacher fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?php echo $total_students; ?></h2>
                                    <p class="mb-0">Students</p>
                                </div>
                                <i class="fas fa-users fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?php echo $total_resources; ?></h2>
                                    <p class="mb-0">Resources</p>
                                </div>
                                <i class="fas fa-book fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?php echo $total_pending_questions; ?></h2>
                                    <p class="mb-0">Pending Qs</p>
                                </div>
                                <i class="fas fa-question-circle fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Courses Grid -->
            <div class="row">
                <?php if (empty($courses)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                            <h5>No courses assigned yet</h5>
                            <p class="text-muted">Contact the administrator to get courses assigned to you</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card course-card h-100">
                                <div class="card-header bg-warning text-white">
                                    <h5 class="mb-0"><?php echo $course['course_code']; ?></h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <i class="fas fa-chalkboard course-icon"></i>
                                    </div>
                                    <h6 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                    <p class="card-text small text-muted">
                                        <?php echo substr(htmlspecialchars($course['description']), 0, 100); ?>
                                        <?php echo strlen($course['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">Department:</small>
                                        <span class="badge bg-light text-dark"><?php echo $course['department']; ?></span>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <h4 class="mb-0"><?php echo $course['student_count']; ?></h4>
                                                <small class="text-muted">Students</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <h4 class="mb-0"><?php echo $course['resource_count']; ?></h4>
                                                <small class="text-muted">Resources</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($course['pending_questions'] > 0): ?>
                                        <div class="alert alert-warning small mb-3">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <?php echo $course['pending_questions']; ?> pending question(s)
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between">
                                        <a href="course_students.php?id=<?php echo $course['id']; ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-users"></i> Students
                                        </a>
                                        <a href="manage_resources.php?course=<?php echo $course['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-book"></i> Resources
                                        </a>
                                        <a href="questions.php?course=<?php echo $course['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-question-circle"></i> Questions
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Detailed Course Information -->
            <?php if (!empty($courses)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-table"></i> Course Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Department</th>
                                        <th>Students</th>
                                        <th>Resources</th>
                                        <th>Pending Qs</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $course['course_code']; ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td><?php echo $course['department']; ?></td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $course['student_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $course['resource_count']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($course['pending_questions'] > 0): ?>
                                                    <span class="badge bg-warning"><?php echo $course['pending_questions']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($course['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="course_students.php?id=<?php echo $course['id']; ?>" 
                                                       class="btn btn-outline-success" title="View Students">
                                                        <i class="fas fa-users"></i>
                                                    </a>
                                                    <a href="manage_resources.php?course=<?php echo $course['id']; ?>" 
                                                       class="btn btn-outline-primary" title="View Resources">
                                                        <i class="fas fa-book"></i>
                                                    </a>
                                                    <a href="questions.php?course=<?php echo $course['id']; ?>" 
                                                       class="btn btn-outline-warning" title="View Questions">
                                                        <i class="fas fa-question-circle"></i>
                                                    </a>
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
</body>
</html>