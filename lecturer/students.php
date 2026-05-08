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

// Get filter parameters
$course_filter = $_GET['course'] ?? '';
$department_filter = $_GET['department'] ?? '';
$year_filter = $_GET['year'] ?? '';
$search = $_GET['search'] ?? '';

// Get lecturer's courses for filter
$stmt = $pdo->prepare("SELECT * FROM courses WHERE lecturer_id = ?");
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll();

// Build query for students
$query = "SELECT DISTINCT u.*, 
                 GROUP_CONCAT(DISTINCT c.course_code ORDER BY c.course_code SEPARATOR ', ') as enrolled_courses,
                 COUNT(DISTINCT e.course_id) as course_count
          FROM users u 
          JOIN enrollments e ON u.id = e.student_id 
          JOIN courses c ON e.course_id = c.id 
          WHERE u.role = 'student' 
          AND c.lecturer_id = ?";
$params = [$lecturer_id];

if ($course_filter) {
    $query .= " AND c.id = ?";
    $params[] = $course_filter;
}

if ($department_filter) {
    $query .= " AND u.department = ?";
    $params[] = $department_filter;
}

if ($year_filter) {
    $query .= " AND u.year_of_study = ?";
    $params[] = $year_filter;
}

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " GROUP BY u.id ORDER BY u.full_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get unique departments and years
$stmt = $pdo->prepare("SELECT DISTINCT u.department, u.year_of_study 
                       FROM users u 
                       JOIN enrollments e ON u.id = e.student_id 
                       JOIN courses c ON e.course_id = c.id 
                       WHERE u.role = 'student' 
                       AND c.lecturer_id = ? 
                       ORDER BY u.department, u.year_of_study");
$stmt->execute([$lecturer_id]);
$departments_years = $stmt->fetchAll();

$unique_departments = [];
$unique_years = [];
foreach ($departments_years as $dy) {
    if ($dy['department'] && !in_array($dy['department'], $unique_departments)) {
        $unique_departments[] = $dy['department'];
    }
    if ($dy['year_of_study'] && !in_array($dy['year_of_study'], $unique_years)) {
        $unique_years[] = $dy['year_of_study'];
    }
}
sort($unique_years);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - EduRepository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
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
        .student-card {
            transition: all 0.3s;
            border-left: 4px solid #38b000;
        }
        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .student-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #38b000, #2d7d46);
            color: white;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .course-badge {
            font-size: 0.7rem;
            margin: 2px;
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
                    <a class="nav-link" href="courses.php">
                        <i class="fas fa-chalkboard"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="students.php">
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
                        <i class="fas fa-users text-success"></i> Students
                    </h1>
                    <p class="text-muted">View and manage students in your courses</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar"></i> Student Statistics
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Overview</h6>
                        <a class="dropdown-item" href="#">
                            Total Students: <span class="badge bg-success float-end"><?php echo count($students); ?></span>
                        </a>
                        <a class="dropdown-item" href="#">
                            Departments: <span class="badge bg-info float-end"><?php echo count($unique_departments); ?></span>
                        </a>
                        <?php if (!empty($unique_years)): ?>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header">By Year of Study</h6>
                            <?php foreach ($unique_years as $year): 
                                $year_count = array_reduce($students, function($carry, $s) use ($year) {
                                    return $carry + ($s['year_of_study'] == $year ? 1 : 0);
                                }, 0);
                            ?>
                                <a class="dropdown-item" href="#">
                                    Year <?php echo $year; ?>: <span class="badge bg-secondary float-end"><?php echo $year_count; ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-filter"></i> Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Course</label>
                            <select class="form-select" name="course">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo $course['course_code']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($unique_departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>" 
                                            <?php echo ($department_filter == $dept) ? 'selected' : ''; ?>>
                                        <?php echo $dept; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Year</label>
                            <select class="form-select" name="year">
                                <option value="">All Years</option>
                                <?php foreach ($unique_years as $year): ?>
                                    <option value="<?php echo $year; ?>" 
                                            <?php echo ($year_filter == $year) ? 'selected' : ''; ?>>
                                        Year <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, username, or email...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Students List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                            <h5>No students found</h5>
                            <p class="text-muted">No students match your filter criteria</p>
                        </div>
                    <?php else: ?>
                        <!-- Cards View -->
                        <div class="row mb-4">
                            <?php foreach ($students as $student): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card student-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start mb-3">
                                                <div class="student-avatar me-3">
                                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                                    <small class="text-muted">@<?php echo $student['username']; ?></small>
                                                    <div class="mt-2">
                                                        <span class="badge bg-success">Year <?php echo $student['year_of_study']; ?></span>
                                                        <span class="badge bg-info"><?php echo $student['department']; ?></span>
                                                        <span class="badge bg-warning"><?php echo $student['course_count']; ?> courses</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted d-block mb-1">
                                                    <i class="fas fa-envelope"></i> <?php echo $student['email']; ?>
                                                </small>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> Joined: <?php echo date('M d, Y', strtotime($student['created_at'])); ?>
                                                </small>
                                            </div>
                                            
                                            <?php if ($student['enrolled_courses']): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted d-block mb-1">Enrolled in:</small>
                                                    <div class="d-flex flex-wrap">
                                                        <?php 
                                                        $course_list = explode(', ', $student['enrolled_courses']);
                                                        foreach (array_slice($course_list, 0, 3) as $course_code): ?>
                                                            <span class="badge bg-light text-dark course-badge">
                                                                <?php echo $course_code; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($course_list) > 3): ?>
                                                            <span class="badge bg-secondary course-badge">
                                                                +<?php echo count($course_list) - 3; ?> more
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="d-flex justify-content-between">
                                                <a href="mailto:<?php echo $student['email']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-envelope"></i> Email
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        data-bs-toggle="modal" data-bs-target="#studentModal<?php echo $student['id']; ?>">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Student Details Modal -->
                                <div class="modal fade" id="studentModal<?php echo $student['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">Student Details</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-4 text-center">
                                                        <div class="student-avatar mx-auto mb-3" style="width: 80px; height: 80px;">
                                                            <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                                        </div>
                                                        <h5><?php echo htmlspecialchars($student['full_name']); ?></h5>
                                                        <p class="text-muted">@<?php echo $student['username']; ?></p>
                                                        <div class="mb-3">
                                                            <span class="badge bg-success">Year <?php echo $student['year_of_study']; ?></span>
                                                            <span class="badge bg-info"><?php echo $student['department']; ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <h6>Contact Information</h6>
                                                        <table class="table table-sm">
                                                            <tr>
                                                                <th>Email:</th>
                                                                <td><?php echo $student['email']; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Course:</th>
                                                                <td><?php echo htmlspecialchars($student['course']); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Joined:</th>
                                                                <td><?php echo date('F d, Y', strtotime($student['created_at'])); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Status:</th>
                                                                <td>
                                                                    <span class="badge bg-<?php echo $student['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                                        <?php echo ucfirst($student['status']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        </table>

                                                        <h6 class="mt-4">Enrolled Courses</h6>
                                                        <?php if ($student['enrolled_courses']): ?>
                                                            <div class="d-flex flex-wrap gap-2 mb-3">
                                                                <?php foreach (explode(', ', $student['enrolled_courses']) as $course_code): ?>
                                                                    <span class="badge bg-warning text-dark">
                                                                        <?php echo $course_code; ?>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <p class="text-muted">Not enrolled in any courses</p>
                                                        <?php endif; ?>

                                                        <h6 class="mt-4">Performance</h6>
                                                        <?php
                                                        // Get student's question statistics
                                                        $stmt2 = $pdo->prepare("SELECT 
                                                            COUNT(*) as total_questions,
                                                            SUM(CASE WHEN status = 'answered' THEN 1 ELSE 0 END) as answered_questions,
                                                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_questions
                                                            FROM questions 
                                                            WHERE student_id = ?");
                                                        $stmt2->execute([$student['id']]);
                                                        $question_stats = $stmt2->fetch();
                                                        ?>
                                                        <div class="row text-center">
                                                            <div class="col-4">
                                                                <div class="p-2 border rounded">
                                                                    <h4 class="mb-0"><?php echo $question_stats['total_questions']; ?></h4>
                                                                    <small>Total Questions</small>
                                                                </div>
                                                            </div>
                                                            <div class="col-4">
                                                                <div class="p-2 border rounded">
                                                                    <h4 class="mb-0"><?php echo $question_stats['answered_questions']; ?></h4>
                                                                    <small>Answered</small>
                                                                </div>
                                                            </div>
                                                            <div class="col-4">
                                                                <div class="p-2 border rounded">
                                                                    <h4 class="mb-0"><?php echo $question_stats['pending_questions']; ?></h4>
                                                                    <small>Pending</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <a href="mailto:<?php echo $student['email']; ?>" class="btn btn-success">
                                                    <i class="fas fa-envelope"></i> Send Email
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Table View -->
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-table"></i> All Students (<?php echo count($students); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="studentsTable">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Department</th>
                                                <th>Year</th>
                                                <th>Courses</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="student-avatar me-2" style="width: 40px; height: 40px; font-size: 1rem;">
                                                                <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                                            </div>
                                                            <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td>@<?php echo $student['username']; ?></td>
                                                    <td><?php echo $student['email']; ?></td>
                                                    <td><?php echo $student['department']; ?></td>
                                                    <td>
                                                        <span class="badge bg-success">Year <?php echo $student['year_of_study']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $student['course_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $student['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($student['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-warning" 
                                                                    data-bs-toggle="modal" data-bs-target="#studentModal<?php echo $student['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <a href="mailto:<?php echo $student['email']; ?>" 
                                                               class="btn btn-outline-primary">
                                                                <i class="fas fa-envelope"></i>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#studentsTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[0, 'asc']]
            });
        });
    </script>
</body>
</html>