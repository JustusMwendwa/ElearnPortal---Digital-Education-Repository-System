<?php
require_once '../db.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Get statistics
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_lecturers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'lecturer'")->fetchColumn();
$total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$total_resources = $pdo->query("SELECT COUNT(*) FROM resources")->fetchColumn();
$total_downloads = $pdo->query("SELECT SUM(download_count) FROM resources")->fetchColumn() ?: 0;
$total_questions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$answered_questions = $pdo->query("SELECT COUNT(*) FROM questions WHERE status = 'answered'")->fetchColumn();

// Get recent activities
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recent_resources = $pdo->query("SELECT r.*, c.course_code, u.full_name as uploaded_by_name 
                                 FROM resources r 
                                 JOIN courses c ON r.course_id = c.id 
                                 JOIN users u ON r.uploaded_by = u.id 
                                 ORDER BY r.upload_date DESC LIMIT 5")->fetchAll();
$recent_questions = $pdo->query("SELECT q.*, c.course_code, u.full_name as student_name 
                                 FROM questions q 
                                 JOIN courses c ON q.course_id = c.id 
                                 JOIN users u ON q.student_id = u.id 
                                 ORDER BY q.asked_at DESC LIMIT 5")->fetchAll();

// Get system information
$total_storage = $pdo->query("SELECT SUM(file_size) FROM resources")->fetchColumn() ?: 0;
$active_sessions = 0; // You can implement session tracking

// Get department statistics
$departments = $pdo->query("SELECT department, COUNT(*) as count 
                           FROM users 
                           WHERE department IS NOT NULL 
                           GROUP BY department 
                           ORDER BY count DESC 
                           LIMIT 5")->fetchAll();

// Get course enrollment statistics
$enrollment_stats = $pdo->query("SELECT c.course_code, COUNT(e.student_id) as student_count 
                                FROM courses c 
                                LEFT JOIN enrollments e ON c.id = e.course_id 
                                GROUP BY c.id 
                                ORDER BY student_count DESC 
                                LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EduRepository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #e63946, #d90429);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .stat-card {
            border-radius: 10px;
            color: white;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card.students { background: linear-gradient(135deg, #38b000, #2d7d46); }
        .stat-card.lecturers { background: linear-gradient(135deg, #ffbe0b, #fb5607); }
        .stat-card.courses { background: linear-gradient(135deg, #3a86ff, #1d4ed8); }
        .stat-card.resources { background: linear-gradient(135deg, #8338ec, #5a189a); }
        .stat-card.downloads { background: linear-gradient(135deg, #06d6a0, #0a9396); }
        .stat-card.questions { background: linear-gradient(135deg, #ff006e, #9d0208); }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .activity-card {
            transition: transform 0.3s;
        }
        .activity-card:hover {
            transform: translateY(-2px);
        }
        .badge-admin { background: #e63946; }
        .badge-lecturer { background: #ffbe0b; color: black; }
        .badge-student { background: #38b000; }
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
            <h4><i class="fas fa-cogs"></i> Admin Panel</h4>
            <p class="mb-0">System Administrator</p>
            <span class="badge bg-danger">Admin</span>
        </div>
        <div class="px-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_users.php">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_courses.php">
                        <i class="fas fa-chalkboard"></i> Manage Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_resources.php">
                        <i class="fas fa-book"></i> Manage Resources
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i> Reports & Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="system_settings.php">
                        <i class="fas fa-cog"></i> System Settings
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-warning" href="../logout.php">
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
                <div class="navbar-brand">
                    <h4 class="mb-0"><i class="fas fa-tachometer-alt text-danger"></i> Admin Dashboard</h4>
                </div>
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3">
                        Welcome, <strong><?php echo $admin['full_name']; ?></strong>
                    </span>
                    <span class="badge bg-danger">Administrator</span>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <!-- System Stats -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card students">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0"><?php echo $total_students; ?></h2>
                                <p class="mb-0">Students</p>
                            </div>
                            <i class="fas fa-user-graduate fa-2x opacity-75"></i>
                        </div>
                        <small>Total registered students</small>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card lecturers">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0"><?php echo $total_lecturers; ?></h2>
                                <p class="mb-0">Lecturers</p>
                            </div>
                            <i class="fas fa-chalkboard-teacher fa-2x opacity-75"></i>
                        </div>
                        <small>Teaching staff</small>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card courses">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0"><?php echo $total_courses; ?></h2>
                                <p class="mb-0">Courses</p>
                            </div>
                            <i class="fas fa-book fa-2x opacity-75"></i>
                        </div>
                        <small>Available courses</small>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card resources">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0"><?php echo $total_resources; ?></h2>
                                <p class="mb-0">Resources</p>
                            </div>
                            <i class="fas fa-file-alt fa-2x opacity-75"></i>
                        </div>
                        <small>Study materials</small>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card downloads">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0"><?php echo number_format($total_downloads); ?></h2>
                                <p class="mb-0">Downloads</p>
                            </div>
                            <i class="fas fa-download fa-2x opacity-75"></i>
                        </div>
                        <small>Total downloads</small>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card questions">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-0"><?php echo $total_questions; ?></h2>
                                <p class="mb-0">Questions</p>
                            </div>
                            <i class="fas fa-question-circle fa-2x opacity-75"></i>
                        </div>
                        <small>Student queries</small>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="chart-container">
                        <h5><i class="fas fa-chart-line text-primary"></i> System Overview</h5>
                        <canvas id="systemChart" height="100"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="chart-container">
                        <h5><i class="fas fa-users text-success"></i> User Distribution</h5>
                        <canvas id="userChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Activities & Top Departments -->
            <div class="row mb-4">
                <div class="col-lg-4">
                    <div class="card activity-card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-building"></i> Top Departments</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($departments)): ?>
                                <p class="text-muted">No department data</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($departments as $dept): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo $dept['department']; ?>
                                            <span class="badge bg-primary rounded-pill"><?php echo $dept['count']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card activity-card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Popular Courses</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($enrollment_stats)): ?>
                                <p class="text-muted">No enrollment data</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($enrollment_stats as $course): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo $course['course_code']; ?>
                                            <span class="badge bg-success rounded-pill"><?php echo $course['student_count']; ?> students</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card activity-card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-chart-pie"></i> System Storage</h6>
                        </div>
                        <div class="card-body text-center">
                            <?php
                            $storage_gb = round($total_storage / (1024 * 1024 * 1024), 2);
                            $storage_percentage = min(($storage_gb / 100) * 100, 100); // Assuming 100GB max
                            ?>
                            <div class="mb-3">
                                <h2><?php echo $storage_gb; ?> GB</h2>
                                <small class="text-muted">Total Storage Used</small>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-info progress-bar-striped progress-bar-animated" 
                                     style="width: <?php echo $storage_percentage; ?>%">
                                    <?php echo round($storage_percentage, 1); ?>%
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">Disk Usage</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities Tables -->
            <div class="row">
                <!-- Recent Users -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-warning">
                            <h6 class="mb-0"><i class="fas fa-user-plus"></i> Recent Users</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-2" style="width: 30px; height: 30px; background: #e9ecef; border-radius: 50%; 
                                                              display: flex; align-items: center; justify-content: center;">
                                                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <small class="d-block"><?php echo htmlspecialchars($user['full_name']); ?></small>
                                                            <small class="text-muted">@<?php echo $user['username']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M d', strtotime($user['created_at'])); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="manage_users.php" class="btn btn-sm btn-outline-warning w-100">
                                View All Users
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Resources -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-file-upload"></i> Recent Uploads</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Resource</th>
                                            <th>Course</th>
                                            <th>Uploader</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_resources as $resource): ?>
                                            <tr>
                                                <td>
                                                    <small class="d-block"><?php echo htmlspecialchars($resource['title']); ?></small>
                                                    <small class="text-muted">
                                                        <?php echo strtoupper($resource['file_type']); ?> • 
                                                        <?php echo round($resource['file_size'] / 1024 / 1024, 2); ?>MB
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?php echo $resource['course_code']; ?></span>
                                                </td>
                                                <td>
                                                    <small><?php echo $resource['uploaded_by_name']; ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="manage_resources.php" class="btn btn-sm btn-outline-success w-100">
                                View All Resources
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Questions -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-question-circle"></i> Recent Questions</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Question</th>
                                            <th>Course</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_questions as $question): ?>
                                            <tr>
                                                <td>
                                                    <small class="d-block"><?php echo htmlspecialchars($question['subject']); ?></small>
                                                    <small class="text-muted">by <?php echo $question['student_name']; ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?php echo $question['course_code']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $question['status'] == 'pending' ? 'warning' : 'success'; ?>">
                                                        <?php echo ucfirst($question['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="reports.php" class="btn btn-sm btn-outline-info w-100">
                                View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> System Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="mb-3">
                                <i class="fas fa-database fa-2x text-primary mb-2"></i>
                                <h5><?php echo $total_users; ?></h5>
                                <small class="text-muted">Total Users</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="mb-3">
                                <i class="fas fa-hdd fa-2x text-success mb-2"></i>
                                <h5><?php echo number_format($total_storage / (1024 * 1024 * 1024), 2); ?> GB</h5>
                                <small class="text-muted">Storage Used</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="mb-3">
                                <i class="fas fa-check-circle fa-2x text-warning mb-2"></i>
                                <h5><?php echo $answered_questions; ?>/<?php echo $total_questions; ?></h5>
                                <small class="text-muted">Questions Answered</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="mb-3">
                                <i class="fas fa-server fa-2x text-info mb-2"></i>
                                <h5>Active</h5>
                                <small class="text-muted">System Status</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // User Distribution Chart
        const userCtx = document.getElementById('userChart').getContext('2d');
        const userChart = new Chart(userCtx, {
            type: 'doughnut',
            data: {
                labels: ['Students', 'Lecturers', 'Admins'],
                datasets: [{
                    data: [<?php echo $total_students; ?>, <?php echo $total_lecturers; ?>, 1],
                    backgroundColor: [
                        '#38b000',
                        '#ffbe0b',
                        '#e63946'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // System Overview Chart
        const systemCtx = document.getElementById('systemChart').getContext('2d');
        const systemChart = new Chart(systemCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [
                    {
                        label: 'Users',
                        data: [<?php echo $total_students * 0.3; ?>, <?php echo $total_students * 0.4; ?>, <?php echo $total_students * 0.5; ?>, 
                               <?php echo $total_students * 0.6; ?>, <?php echo $total_students * 0.7; ?>, <?php echo $total_students * 0.8; ?>,
                               <?php echo $total_students * 0.9; ?>, <?php echo $total_students; ?>, <?php echo $total_students * 1.1; ?>,
                               <?php echo $total_students * 1.2; ?>, <?php echo $total_students * 1.3; ?>, <?php echo $total_students * 1.4; ?>],
                        borderColor: '#38b000',
                        backgroundColor: 'rgba(56, 176, 0, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Resources',
                        data: [<?php echo $total_resources * 0.2; ?>, <?php echo $total_resources * 0.3; ?>, <?php echo $total_resources * 0.4; ?>, 
                               <?php echo $total_resources * 0.5; ?>, <?php echo $total_resources * 0.6; ?>, <?php echo $total_resources * 0.7; ?>,
                               <?php echo $total_resources * 0.8; ?>, <?php echo $total_resources * 0.9; ?>, <?php echo $total_resources; ?>,
                               <?php echo $total_resources * 1.1; ?>, <?php echo $total_resources * 1.2; ?>, <?php echo $total_resources * 1.3; ?>],
                        borderColor: '#3a86ff',
                        backgroundColor: 'rgba(58, 134, 255, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Downloads',
                        data: [<?php echo $total_downloads * 0.1; ?>, <?php echo $total_downloads * 0.2; ?>, <?php echo $total_downloads * 0.3; ?>, 
                               <?php echo $total_downloads * 0.4; ?>, <?php echo $total_downloads * 0.5; ?>, <?php echo $total_downloads * 0.6; ?>,
                               <?php echo $total_downloads * 0.7; ?>, <?php echo $total_downloads * 0.8; ?>, <?php echo $total_downloads * 0.9; ?>,
                               <?php echo $total_downloads; ?>, <?php echo $total_downloads * 1.1; ?>, <?php echo $total_downloads * 1.2; ?>],
                        borderColor: '#ff006e',
                        backgroundColor: 'rgba(255, 0, 110, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>