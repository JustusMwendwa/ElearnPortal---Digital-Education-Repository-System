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

// Get report parameters
$report_type = $_GET['report'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$department = $_GET['department'] ?? '';

// Get statistics based on report type
$stats = [];

switch ($report_type) {
    case 'overview':
        // User statistics
        $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        $stats['new_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= '$start_date' AND created_at <= '$end_date'")->fetchColumn();
        
        // Course statistics
        $stats['total_courses'] = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $stats['popular_course'] = $pdo->query("SELECT c.course_code, COUNT(e.student_id) as count 
                                               FROM courses c 
                                               LEFT JOIN enrollments e ON c.id = e.course_id 
                                               GROUP BY c.id 
                                               ORDER BY count DESC 
                                               LIMIT 1")->fetch();
        
        // Resource statistics
        $stats['total_resources'] = $pdo->query("SELECT COUNT(*) FROM resources")->fetchColumn();
        $stats['total_downloads'] = $pdo->query("SELECT SUM(download_count) FROM resources")->fetchColumn() ?: 0;
        $stats['popular_resource'] = $pdo->query("SELECT title, download_count FROM resources ORDER BY download_count DESC LIMIT 1")->fetch();
        
        // Question statistics
        $stats['total_questions'] = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
        $stats['answered_questions'] = $pdo->query("SELECT COUNT(*) FROM questions WHERE status = 'answered'")->fetchColumn();
        $stats['response_rate'] = $stats['total_questions'] > 0 ? round(($stats['answered_questions'] / $stats['total_questions']) * 100, 1) : 0;
        break;
        
    case 'users':
        // User growth over time
        $stats['user_growth'] = $pdo->query("SELECT 
            DATE(created_at) as date,
            COUNT(*) as count,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN role = 'lecturer' THEN 1 ELSE 0 END) as lecturers
            FROM users 
            WHERE created_at >= '$start_date' AND created_at <= '$end_date'
            GROUP BY DATE(created_at)
            ORDER BY date")->fetchAll();
        
        // User distribution
        $stats['role_distribution'] = $pdo->query("SELECT 
            role, COUNT(*) as count 
            FROM users 
            GROUP BY role")->fetchAll();
            
        $stats['department_distribution'] = $pdo->query("SELECT 
            department, COUNT(*) as count 
            FROM users 
            WHERE department IS NOT NULL 
            GROUP BY department 
            ORDER BY count DESC 
            LIMIT 10")->fetchAll();
        break;
        
    case 'courses':
        // Course enrollment statistics
        $stats['course_enrollment'] = $pdo->query("SELECT 
            c.course_code, c.course_name, c.department,
            COUNT(e.student_id) as student_count,
            COUNT(DISTINCT r.id) as resource_count,
            COUNT(DISTINCT q.id) as question_count
            FROM courses c 
            LEFT JOIN enrollments e ON c.id = e.course_id 
            LEFT JOIN resources r ON c.id = r.course_id 
            LEFT JOIN questions q ON c.id = q.course_id 
            GROUP BY c.id 
            ORDER BY student_count DESC")->fetchAll();
            
        // Department course statistics
        $stats['department_courses'] = $pdo->query("SELECT 
            department, COUNT(*) as course_count,
            SUM((SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id)) as total_students
            FROM courses c 
            GROUP BY department 
            ORDER BY course_count DESC")->fetchAll();
        break;
        
    case 'resources':
        // Resource upload statistics
        $stats['resource_uploads'] = $pdo->query("SELECT 
            DATE(upload_date) as date,
            COUNT(*) as count,
            SUM(file_size) as total_size
            FROM resources 
            WHERE upload_date >= '$start_date' AND upload_date <= '$end_date'
            GROUP BY DATE(upload_date)
            ORDER BY date")->fetchAll();
            
        // Resource type distribution
        $stats['type_distribution'] = $pdo->query("SELECT 
            file_type, COUNT(*) as count,
            AVG(file_size) as avg_size,
            SUM(download_count) as total_downloads
            FROM resources 
            GROUP BY file_type 
            ORDER BY count DESC")->fetchAll();
            
        // Top resources by downloads
        $stats['top_resources'] = $pdo->query("SELECT 
            r.title, r.file_type, r.download_count, r.upload_date,
            c.course_code, u.full_name as uploaded_by
            FROM resources r 
            JOIN courses c ON r.course_id = c.id 
            JOIN users u ON r.uploaded_by = u.id 
            ORDER BY r.download_count DESC 
            LIMIT 10")->fetchAll();
        break;
        
    case 'questions':
        // Question statistics
        $stats['question_stats'] = $pdo->query("SELECT 
            DATE(asked_at) as date,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'answered' THEN 1 ELSE 0 END) as answered,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM questions 
            WHERE asked_at >= '$start_date' AND asked_at <= '$end_date'
            GROUP BY DATE(asked_at)
            ORDER BY date")->fetchAll();
            
        // Course question statistics
        $stats['course_questions'] = $pdo->query("SELECT 
            c.course_code, c.course_name,
            COUNT(q.id) as total_questions,
            SUM(CASE WHEN q.status = 'answered' THEN 1 ELSE 0 END) as answered_questions,
            AVG(TIMESTAMPDIFF(HOUR, q.asked_at, a.answered_at)) as avg_response_hours
            FROM courses c 
            LEFT JOIN questions q ON c.id = q.course_id 
            LEFT JOIN answers a ON q.id = a.question_id 
            GROUP BY c.id 
            HAVING total_questions > 0 
            ORDER BY total_questions DESC")->fetchAll();
            
        // Lecturer response statistics
        $stats['lecturer_responses'] = $pdo->query("SELECT 
            u.full_name, u.department,
            COUNT(DISTINCT q.id) as total_questions,
            COUNT(DISTINCT a.id) as total_answers,
            AVG(TIMESTAMPDIFF(HOUR, q.asked_at, a.answered_at)) as avg_response_time
            FROM users u 
            LEFT JOIN questions q ON u.id = q.lecturer_id 
            LEFT JOIN answers a ON q.id = a.question_id 
            WHERE u.role = 'lecturer'
            GROUP BY u.id 
            ORDER BY total_answers DESC")->fetchAll();
        break;
}

// Get departments for filter
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department")->fetchAll();

// Export functionality
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $export_type . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch ($export_type) {
        case 'users':
            fputcsv($output, ['Date', 'Total Users', 'Students', 'Lecturers']);
            foreach ($stats['user_growth'] as $row) {
                fputcsv($output, [$row['date'], $row['count'], $row['students'], $row['lecturers']]);
            }
            break;
            
        case 'courses':
            fputcsv($output, ['Course Code', 'Course Name', 'Department', 'Students', 'Resources', 'Questions']);
            foreach ($stats['course_enrollment'] as $row) {
                fputcsv($output, [$row['course_code'], $row['course_name'], $row['department'], 
                                 $row['student_count'], $row['resource_count'], $row['question_count']]);
            }
            break;
            
        case 'resources':
            fputcsv($output, ['Date', 'Uploads', 'Total Size (MB)', 'Avg Size (MB)']);
            foreach ($stats['resource_uploads'] as $row) {
                $total_size_mb = round($row['total_size'] / (1024 * 1024), 2);
                $avg_size_mb = $row['count'] > 0 ? round($row['total_size'] / $row['count'] / (1024 * 1024), 2) : 0;
                fputcsv($output, [$row['date'], $row['count'], $total_size_mb, $avg_size_mb]);
            }
            break;
            
        case 'questions':
            fputcsv($output, ['Date', 'Total Questions', 'Answered', 'Pending', 'Answer Rate']);
            foreach ($stats['question_stats'] as $row) {
                $answer_rate = $row['count'] > 0 ? round(($row['answered'] / $row['count']) * 100, 1) : 0;
                fputcsv($output, [$row['date'], $row['count'], $row['answered'], $row['pending'], $answer_rate . '%']);
            }
            break;
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - EduRepository</title>
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
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            line-height: 1;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .chart-container {
            height: 300px;
            margin-bottom: 20px;
        }
        .nav-pills .nav-link {
            color: #495057;
            border-radius: 20px;
            padding: 8px 20px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #3a86ff, #1d4ed8);
            color: white;
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
            <h4><i class="fas fa-cogs"></i> Admin Panel</h4>
            <p class="mb-0">System Administrator</p>
            <span class="badge bg-danger">Admin</span>
        </div>
        <div class="px-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
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
                    <a class="nav-link active" href="reports.php">
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
                <a href="dashboard.php" class="btn btn-danger">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3">
                        Welcome, <strong><?php echo $admin['full_name']; ?></strong>
                    </span>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-chart-bar text-info"></i> Reports & Analytics
                    </h1>
                    <p class="text-muted">System analytics and performance reports</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download"></i> Export Report
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="?export=users&report=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                            <i class="fas fa-file-csv"></i> Export User Data
                        </a>
                        <a class="dropdown-item" href="?export=courses&report=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                            <i class="fas fa-file-csv"></i> Export Course Data
                        </a>
                        <a class="dropdown-item" href="?export=resources&report=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                            <i class="fas fa-file-csv"></i> Export Resource Data
                        </a>
                        <a class="dropdown-item" href="?export=questions&report=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                            <i class="fas fa-file-csv"></i> Export Question Data
                        </a>
                    </div>
                </div>
            </div>

            <!-- Report Type Selector -->
            <div class="card mb-4">
                <div class="card-body">
                    <ul class="nav nav-pills mb-3">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'overview' ? 'active' : ''; ?>" 
                               href="?report=overview&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                                <i class="fas fa-tachometer-alt"></i> Overview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'users' ? 'active' : ''; ?>" 
                               href="?report=users&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                                <i class="fas fa-users"></i> User Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'courses' ? 'active' : ''; ?>" 
                               href="?report=courses&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                                <i class="fas fa-chalkboard"></i> Course Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'resources' ? 'active' : ''; ?>" 
                               href="?report=resources&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                                <i class="fas fa-book"></i> Resource Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type == 'questions' ? 'active' : ''; ?>" 
                               href="?report=questions&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                                <i class="fas fa-question-circle"></i> Question Analytics
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Date Filter -->
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="report" value="<?php echo $report_type; ?>">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department']; ?>" 
                                            <?php echo $department == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo $dept['department']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-info w-100">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Content -->
            <?php if ($report_type == 'overview'): ?>
                <!-- Overview Report -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="report-card text-center">
                            <div class="stat-number text-primary"><?php echo $stats['total_users']; ?></div>
                            <div class="stat-label">Total Users</div>
                            <small class="text-muted"><?php echo $stats['active_users']; ?> active</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="report-card text-center">
                            <div class="stat-number text-success"><?php echo $stats['total_courses']; ?></div>
                            <div class="stat-label">Total Courses</div>
                            <?php if ($stats['popular_course']): ?>
                                <small class="text-muted">Most popular: <?php echo $stats['popular_course']['course_code']; ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="report-card text-center">
                            <div class="stat-number text-warning"><?php echo $stats['total_resources']; ?></div>
                            <div class="stat-label">Total Resources</div>
                            <small class="text-muted"><?php echo number_format($stats['total_downloads']); ?> downloads</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="report-card text-center">
                            <div class="stat-number text-danger"><?php echo $stats['total_questions']; ?></div>
                            <div class="stat-label">Total Questions</div>
                            <small class="text-muted"><?php echo $stats['response_rate']; ?>% response rate</small>
                        </div>
                    </div>
                </div>

                <!-- System Metrics -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="report-card">
                            <h5><i class="fas fa-chart-line text-primary"></i> System Growth</h5>
                            <div class="chart-container">
                                <canvas id="systemGrowthChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="report-card">
                            <h5><i class="fas fa-chart-pie text-success"></i> Resource Distribution</h5>
                            <div class="chart-container">
                                <canvas id="resourceDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'users'): ?>
                <!-- User Analytics Report -->
                <div class="report-card mb-4">
                    <h5><i class="fas fa-chart-line text-primary"></i> User Growth Timeline</h5>
                    <div class="chart-container">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="report-card">
                            <h5><i class="fas fa-chart-pie text-warning"></i> User Role Distribution</h5>
                            <div class="chart-container">
                                <canvas id="roleDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="report-card">
                            <h5><i class="fas fa-chart-bar text-info"></i> Top Departments</h5>
                            <div class="chart-container">
                                <canvas id="departmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'courses'): ?>
                <!-- Course Analytics Report -->
                <div class="report-card mb-4">
                    <h5><i class="fas fa-table text-success"></i> Course Enrollment Statistics</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Department</th>
                                    <th>Students</th>
                                    <th>Resources</th>
                                    <th>Questions</th>
                                    <th>Engagement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['course_enrollment'] as $course): 
                                    $engagement = $course['student_count'] > 0 ? 
                                        round((($course['resource_count'] + $course['question_count']) / $course['student_count']) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo $course['course_code']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td><?php echo $course['department']; ?></td>
                                        <td><span class="badge bg-success"><?php echo $course['student_count']; ?></span></td>
                                        <td><span class="badge bg-info"><?php echo $course['resource_count']; ?></span></td>
                                        <td><span class="badge bg-warning"><?php echo $course['question_count']; ?></span></td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-primary" style="width: <?php echo min($engagement, 100); ?>%"></div>
                                            </div>
                                            <small><?php echo $engagement; ?>%</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="report-card">
                            <h5><i class="fas fa-chart-bar text-primary"></i> Department Course Distribution</h5>
                            <div class="chart-container">
                                <canvas id="departmentCourseChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="report-card">
                            <h5><i class="fas fa-info-circle text-info"></i> Course Statistics</h5>
                            <div class="mt-3">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Total Courses</small>
                                    <h3 class="mb-0"><?php echo count($stats['course_enrollment']); ?></h3>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Total Students Enrolled</small>
                                    <h3 class="mb-0"><?php echo array_sum(array_column($stats['course_enrollment'], 'student_count')); ?></h3>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Average Students per Course</small>
                                    <h3 class="mb-0"><?php echo count($stats['course_enrollment']) > 0 ? 
                                        round(array_sum(array_column($stats['course_enrollment'], 'student_count')) / count($stats['course_enrollment']), 1) : 0; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'resources'): ?>
                <!-- Resource Analytics Report -->
                <div class="report-card mb-4">
                    <h5><i class="fas fa-chart-line text-success"></i> Resource Upload Timeline</h5>
                    <div class="chart-container">
                        <canvas id="resourceUploadChart"></canvas>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="report-card">
                            <h5><i class="fas fa-chart-pie text-warning"></i> Resource Type Distribution</h5>
                            <div class="chart-container">
                                <canvas id="resourceTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="report-card">
                            <h5><i class="fas fa-trophy text-danger"></i> Top Resources by Downloads</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Resource</th>
                                            <th>Type</th>
                                            <th>Course</th>
                                            <th>Downloads</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['top_resources'] as $resource): ?>
                                            <tr>
                                                <td>
                                                    <small><?php echo htmlspecialchars(substr($resource['title'], 0, 30)); ?>...</small>
                                                </td>
                                                <td><span class="badge bg-light text-dark"><?php echo strtoupper($resource['file_type']); ?></span></td>
                                                <td><small><?php echo $resource['course_code']; ?></small></td>
                                                <td><span class="badge bg-info"><?php echo $resource['download_count']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'questions'): ?>
                <!-- Question Analytics Report -->
                <div class="report-card mb-4">
                    <h5><i class="fas fa-chart-line text-warning"></i> Question Activity Timeline</h5>
                    <div class="chart-container">
                        <canvas id="questionActivityChart"></canvas>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="report-card">
                            <h5><i class="fas fa-table text-info"></i> Course Question Statistics</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Total Questions</th>
                                            <th>Answered</th>
                                            <th>Response Rate</th>
                                            <th>Avg Response Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['course_questions'] as $course): 
                                            $response_rate = $course['total_questions'] > 0 ? 
                                                round(($course['answered_questions'] / $course['total_questions']) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $course['course_code']; ?></strong></td>
                                                <td><span class="badge bg-primary"><?php echo $course['total_questions']; ?></span></td>
                                                <td><span class="badge bg-success"><?php echo $course['answered_questions']; ?></span></td>
                                                <td>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-info" style="width: <?php echo $response_rate; ?>%"></div>
                                                    </div>
                                                    <small><?php echo $response_rate; ?>%</small>
                                                </td>
                                                <td>
                                                    <?php if ($course['avg_response_hours']): ?>
                                                        <span class="badge bg-warning"><?php echo round($course['avg_response_hours'], 1); ?> hours</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="report-card">
                            <h5><i class="fas fa-user-tie text-danger"></i> Lecturer Response Performance</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Lecturer</th>
                                            <th>Questions</th>
                                            <th>Answers</th>
                                            <th>Response Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['lecturer_responses'] as $lecturer): ?>
                                            <tr>
                                                <td><small><?php echo $lecturer['full_name']; ?></small></td>
                                                <td><span class="badge bg-primary"><?php echo $lecturer['total_questions']; ?></span></td>
                                                <td><span class="badge bg-success"><?php echo $lecturer['total_answers']; ?></span></td>
                                                <td>
                                                    <?php if ($lecturer['avg_response_time']): ?>
                                                        <span class="badge bg-warning"><?php echo round($lecturer['avg_response_time'], 1); ?>h</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Initialize charts based on report type
        <?php if ($report_type == 'overview'): ?>
            // System Growth Chart
            const growthCtx = document.getElementById('systemGrowthChart').getContext('2d');
            const growthChart = new Chart(growthCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [
                        {
                            label: 'Users',
                            data: [150, 200, 250, 300, 350, 400, 450, 500, 550, 600, 650, <?php echo $stats['total_users']; ?>],
                            borderColor: '#3a86ff',
                            backgroundColor: 'rgba(58, 134, 255, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Resources',
                            data: [50, 100, 150, 200, 250, 300, 350, 400, 450, 500, 550, <?php echo $stats['total_resources']; ?>],
                            borderColor: '#38b000',
                            backgroundColor: 'rgba(56, 176, 0, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Downloads',
                            data: [1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000, 11000, <?php echo $stats['total_downloads']; ?>],
                            borderColor: '#ff006e',
                            backgroundColor: 'rgba(255, 0, 110, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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

            // Resource Distribution Chart
            const resourceDistCtx = document.getElementById('resourceDistributionChart').getContext('2d');
            const resourceDistChart = new Chart(resourceDistCtx, {
                type: 'doughnut',
                data: {
                    labels: ['PDF', 'DOC/DOCX', 'PPT/PPTX', 'Video', 'Image', 'Other'],
                    datasets: [{
                        data: [40, 25, 15, 10, 5, 5],
                        backgroundColor: [
                            '#e63946',
                            '#3a86ff',
                            '#ffbe0b',
                            '#38b000',
                            '#8338ec',
                            '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });

        <?php elseif ($report_type == 'users'): ?>
            // User Growth Chart
            const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
            const userGrowthChart = new Chart(userGrowthCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($stats['user_growth'], 'date')); ?>,
                    datasets: [
                        {
                            label: 'Total Users',
                            data: <?php echo json_encode(array_column($stats['user_growth'], 'count')); ?>,
                            borderColor: '#3a86ff',
                            backgroundColor: 'rgba(58, 134, 255, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Students',
                            data: <?php echo json_encode(array_column($stats['user_growth'], 'students')); ?>,
                            borderColor: '#38b000',
                            backgroundColor: 'rgba(56, 176, 0, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Lecturers',
                            data: <?php echo json_encode(array_column($stats['user_growth'], 'lecturers')); ?>,
                            borderColor: '#ffbe0b',
                            backgroundColor: 'rgba(255, 190, 11, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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

            // Role Distribution Chart
            const roleDistCtx = document.getElementById('roleDistributionChart').getContext('2d');
            const roleDistChart = new Chart(roleDistCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($stats['role_distribution'], 'role')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($stats['role_distribution'], 'count')); ?>,
                        backgroundColor: ['#38b000', '#ffbe0b', '#e63946']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });

            // Department Chart
            const deptCtx = document.getElementById('departmentChart').getContext('2d');
            const deptChart = new Chart(deptCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_slice(array_column($stats['department_distribution'], 'department'), 0, 5)); ?>,
                    datasets: [{
                        label: 'Users',
                        data: <?php echo json_encode(array_slice(array_column($stats['department_distribution'], 'count'), 0, 5)); ?>,
                        backgroundColor: '#3a86ff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

        <?php elseif ($report_type == 'courses'): ?>
            // Department Course Chart
            const deptCourseCtx = document.getElementById('departmentCourseChart').getContext('2d');
            const deptCourseChart = new Chart(deptCourseCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($stats['department_courses'], 'department')); ?>,
                    datasets: [
                        {
                            label: 'Courses',
                            data: <?php echo json_encode(array_column($stats['department_courses'], 'course_count')); ?>,
                            backgroundColor: '#3a86ff'
                        },
                        {
                            label: 'Students',
                            data: <?php echo json_encode(array_column($stats['department_courses'], 'total_students')); ?>,
                            backgroundColor: '#38b000'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

        <?php elseif ($report_type == 'resources'): ?>
            // Resource Upload Chart
            const resourceUploadCtx = document.getElementById('resourceUploadChart').getContext('2d');
            const resourceUploadChart = new Chart(resourceUploadCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($stats['resource_uploads'], 'date')); ?>,
                    datasets: [{
                        label: 'Uploads',
                        data: <?php echo json_encode(array_column($stats['resource_uploads'], 'count')); ?>,
                        backgroundColor: '#38b000'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Resource Type Chart
            const resourceTypeCtx = document.getElementById('resourceTypeChart').getContext('2d');
            const resourceTypeChart = new Chart(resourceTypeCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($stats['type_distribution'], 'file_type')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($stats['type_distribution'], 'count')); ?>,
                        backgroundColor: ['#e63946', '#3a86ff', '#ffbe0b', '#38b000', '#8338ec', '#6c757d']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });

        <?php elseif ($report_type == 'questions'): ?>
            // Question Activity Chart
            const questionActivityCtx = document.getElementById('questionActivityChart').getContext('2d');
            const questionActivityChart = new Chart(questionActivityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($stats['question_stats'], 'date')); ?>,
                    datasets: [
                        {
                            label: 'Total Questions',
                            data: <?php echo json_encode(array_column($stats['question_stats'], 'count')); ?>,
                            borderColor: '#3a86ff',
                            backgroundColor: 'rgba(58, 134, 255, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Answered',
                            data: <?php echo json_encode(array_column($stats['question_stats'], 'answered')); ?>,
                            borderColor: '#38b000',
                            backgroundColor: 'rgba(56, 176, 0, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Pending',
                            data: <?php echo json_encode(array_column($stats['question_stats'], 'pending')); ?>,
                            borderColor: '#ffbe0b',
                            backgroundColor: 'rgba(255, 190, 11, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
        <?php endif; ?>
    </script>
</body>
</html>