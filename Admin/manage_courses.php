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

// Get filter parameters
$department_filter = $_GET['department'] ?? '';
$lecturer_filter = $_GET['lecturer'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT c.*, u.full_name as lecturer_name, 
                 (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
                 (SELECT COUNT(*) FROM resources WHERE course_id = c.id) as resource_count,
                 (SELECT COUNT(*) FROM questions WHERE course_id = c.id) as question_count
          FROM courses c 
          LEFT JOIN users u ON c.lecturer_id = u.id 
          WHERE 1=1";
$params = [];

if ($department_filter) {
    $query .= " AND c.department = ?";
    $params[] = $department_filter;
}

if ($lecturer_filter) {
    $query .= " AND c.lecturer_id = ?";
    $params[] = $lecturer_filter;
}

if ($search) {
    $query .= " AND (c.course_code LIKE ? OR c.course_name LIKE ? OR c.department LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY c.course_code";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get unique departments
$departments = $pdo->query("SELECT DISTINCT department FROM courses WHERE department IS NOT NULL ORDER BY department")->fetchAll();

// Get lecturers for filter
$lecturers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'lecturer' ORDER BY full_name")->fetchAll();

// Handle course actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_course'])) {
        $course_code = $_POST['course_code'];
        $course_name = $_POST['course_name'];
        $department = $_POST['department'];
        $lecturer_id = $_POST['lecturer_id'];
        $description = $_POST['description'];
        
        // Check if course code exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ?");
        $stmt->execute([$course_code]);
        $exists = $stmt->fetchColumn();
        
        if ($exists > 0) {
            $error = "Course code already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, department, lecturer_id, description) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$course_code, $course_name, $department, $lecturer_id, $description]);
            $success = "Course added successfully!";
            header('Location: manage_courses.php?success=Course added successfully');
            exit();
        }
    }
    
    if (isset($_POST['delete_course'])) {
        $course_id = $_POST['course_id'];
        
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $success = "Course deleted successfully!";
        header('Location: manage_courses.php?success=Course deleted successfully');
        exit();
    }
    
    if (isset($_POST['update_course'])) {
        $course_id = $_POST['course_id'];
        $course_code = $_POST['course_code'];
        $course_name = $_POST['course_name'];
        $department = $_POST['department'];
        $lecturer_id = $_POST['lecturer_id'];
        $description = $_POST['description'];
        
        // Check if course code exists (excluding current course)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ? AND id != ?");
        $stmt->execute([$course_code, $course_id]);
        $exists = $stmt->fetchColumn();
        
        if ($exists > 0) {
            $error = "Course code already exists!";
        } else {
            $stmt = $pdo->prepare("UPDATE courses 
                                  SET course_code = ?, course_name = ?, department = ?, lecturer_id = ?, description = ? 
                                  WHERE id = ?");
            $stmt->execute([$course_code, $course_name, $department, $lecturer_id, $description, $course_id]);
            $success = "Course updated successfully!";
            header('Location: manage_courses.php?success=Course updated successfully');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - EduRepository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
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
        .course-card {
            transition: transform 0.3s;
            border-left: 4px solid #3a86ff;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .course-icon {
            font-size: 2.5rem;
            color: #3a86ff;
        }
        .stat-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        .modal-lg {
            max-width: 800px;
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
                    <a class="nav-link active" href="manage_courses.php">
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
                        <i class="fas fa-chalkboard text-primary"></i> Course Management
                    </h1>
                    <p class="text-muted">Create, edit, and manage courses in the system</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="fas fa-plus"></i> Add New Course
                </button>
            </div>

            <!-- Course Statistics -->
            <div class="row mb-4">
                <?php
                $total_courses = count($courses);
                $total_students = array_sum(array_column($courses, 'student_count'));
                $total_resources = array_sum(array_column($courses, 'resource_count'));
                $total_questions = array_sum(array_column($courses, 'question_count'));
                ?>
                <div class="col-md-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $total_courses; ?></h2>
                            <p class="mb-0">Total Courses</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $total_students; ?></h2>
                            <p class="mb-0">Enrolled Students</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $total_resources; ?></h2>
                            <p class="mb-0">Course Resources</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $total_questions; ?></h2>
                            <p class="mb-0">Course Questions</p>
                        </div>
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
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department']; ?>" 
                                            <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo $dept['department']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Lecturer</label>
                            <select class="form-select" name="lecturer">
                                <option value="">All Lecturers</option>
                                <?php foreach ($lecturers as $lecturer): ?>
                                    <option value="<?php echo $lecturer['id']; ?>" 
                                            <?php echo $lecturer_filter == $lecturer['id'] ? 'selected' : ''; ?>>
                                        <?php echo $lecturer['full_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by course code, name, or department...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="manage_courses.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Courses Grid View -->
            <div class="row mb-4">
                <?php if (empty($courses)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                            <h5>No courses found</h5>
                            <p class="text-muted">
                                <?php echo ($department_filter || $lecturer_filter || $search) ? 
                                       'No courses match your filter criteria.' : 
                                       'No courses have been created yet.'; ?>
                            </p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                                <i class="fas fa-plus"></i> Create First Course
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="col-xl-4 col-lg-6 mb-4">
                            <div class="card course-card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><?php echo $course['course_code']; ?></h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                    
                                    <?php if ($course['description']): ?>
                                        <p class="card-text small text-muted">
                                            <?php echo substr(htmlspecialchars($course['description']), 0, 100); ?>
                                            <?php echo strlen($course['description']) > 100 ? '...' : ''; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">Department:</small>
                                        <span class="badge bg-light text-dark"><?php echo $course['department']; ?></span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">Lecturer:</small>
                                        <p class="mb-0">
                                            <?php if ($course['lecturer_name']): ?>
                                                <i class="fas fa-user-tie text-primary"></i> <?php echo $course['lecturer_name']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-4 text-center">
                                            <div class="border rounded p-2">
                                                <h5 class="mb-0"><?php echo $course['student_count']; ?></h5>
                                                <small class="text-muted">Students</small>
                                            </div>
                                        </div>
                                        <div class="col-4 text-center">
                                            <div class="border rounded p-2">
                                                <h5 class="mb-0"><?php echo $course['resource_count']; ?></h5>
                                                <small class="text-muted">Resources</small>
                                            </div>
                                        </div>
                                        <div class="col-4 text-center">
                                            <div class="border rounded p-2">
                                                <h5 class="mb-0"><?php echo $course['question_count']; ?></h5>
                                                <small class="text-muted">Questions</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="small text-muted">
                                        <i class="fas fa-calendar"></i> Created: <?php echo date('M d, Y', strtotime($course['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#editCourseModal<?php echo $course['id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                data-bs-toggle="modal" data-bs-target="#courseDetailsModal<?php echo $course['id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <form method="POST" action="" onsubmit="return confirm('Delete this course?')">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" name="delete_course" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Course Modal -->
                        <div class="modal fade" id="editCourseModal<?php echo $course['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">Edit Course: <?php echo $course['course_code']; ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Course Code *</label>
                                                    <input type="text" class="form-control" name="course_code" 
                                                           value="<?php echo $course['course_code']; ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Course Name *</label>
                                                    <input type="text" class="form-control" name="course_name" 
                                                           value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Department *</label>
                                                    <input type="text" class="form-control" name="department" 
                                                           value="<?php echo $course['department']; ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Lecturer</label>
                                                    <select class="form-select" name="lecturer_id">
                                                        <option value="">Select Lecturer</option>
                                                        <?php foreach ($lecturers as $lecturer): ?>
                                                            <option value="<?php echo $lecturer['id']; ?>" 
                                                                    <?php echo $course['lecturer_id'] == $lecturer['id'] ? 'selected' : ''; ?>>
                                                                <?php echo $lecturer['full_name']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($course['description']); ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="card bg-light">
                                                        <div class="card-body text-center">
                                                            <h5 class="mb-0"><?php echo $course['student_count']; ?></h5>
                                                            <small class="text-muted">Enrolled Students</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-light">
                                                        <div class="card-body text-center">
                                                            <h5 class="mb-0"><?php echo $course['resource_count']; ?></h5>
                                                            <small class="text-muted">Resources</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-light">
                                                        <div class="card-body text-center">
                                                            <h5 class="mb-0"><?php echo $course['question_count']; ?></h5>
                                                            <small class="text-muted">Questions</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_course" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Course Details Modal -->
                        <div class="modal fade" id="courseDetailsModal<?php echo $course['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-info text-white">
                                        <h5 class="modal-title">Course Details: <?php echo $course['course_code']; ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h4><?php echo htmlspecialchars($course['course_name']); ?></h4>
                                                <p class="text-muted"><?php echo $course['department']; ?></p>
                                                
                                                <?php if ($course['description']): ?>
                                                    <div class="mb-4">
                                                        <h6>Description</h6>
                                                        <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="row mb-4">
                                                    <div class="col-md-4">
                                                        <div class="card border-primary">
                                                            <div class="card-body text-center">
                                                                <h3 class="mb-0"><?php echo $course['student_count']; ?></h3>
                                                                <small class="text-muted">Enrolled Students</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="card border-success">
                                                            <div class="card-body text-center">
                                                                <h3 class="mb-0"><?php echo $course['resource_count']; ?></h3>
                                                                <small class="text-muted">Resources</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="card border-warning">
                                                            <div class="card-body text-center">
                                                                <h3 class="mb-0"><?php echo $course['question_count']; ?></h3>
                                                                <small class="text-muted">Questions</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <h6>Course Information</h6>
                                                        <table class="table table-sm">
                                                            <tr>
                                                                <td><strong>Code:</strong></td>
                                                                <td><?php echo $course['course_code']; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><strong>Lecturer:</strong></td>
                                                                <td>
                                                                    <?php if ($course['lecturer_name']): ?>
                                                                        <?php echo $course['lecturer_name']; ?>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">Not assigned</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><strong>Created:</strong></td>
                                                                <td><?php echo date('F d, Y', strtotime($course['created_at'])); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><strong>Status:</strong></td>
                                                                <td><span class="badge bg-success">Active</span></td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Enrolled Students -->
                                        <?php
                                        $stmt = $pdo->prepare("SELECT u.full_name, u.email, u.department, u.year_of_study 
                                                              FROM enrollments e 
                                                              JOIN users u ON e.student_id = u.id 
                                                              WHERE e.course_id = ? 
                                                              ORDER BY u.full_name 
                                                              LIMIT 5");
                                        $stmt->execute([$course['id']]);
                                        $enrolled_students = $stmt->fetchAll();
                                        ?>
                                        
                                        <h6 class="mt-4">Recently Enrolled Students</h6>
                                        <?php if (empty($enrolled_students)): ?>
                                            <p class="text-muted">No students enrolled yet</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th>Email</th>
                                                            <th>Department</th>
                                                            <th>Year</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($enrolled_students as $student): ?>
                                                            <tr>
                                                                <td><?php echo $student['full_name']; ?></td>
                                                                <td><?php echo $student['email']; ?></td>
                                                                <td><?php echo $student['department']; ?></td>
                                                                <td>Year <?php echo $student['year_of_study']; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php if ($course['student_count'] > 5): ?>
                                                <small class="text-muted">... and <?php echo $course['student_count'] - 5; ?> more students</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-primary" 
                                                data-bs-toggle="modal" data-bs-target="#editCourseModal<?php echo $course['id']; ?>"
                                                data-bs-dismiss="modal">
                                            <i class="fas fa-edit"></i> Edit Course
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Courses Table View -->
            <?php if (!empty($courses)): ?>
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-table"></i> All Courses (<?php echo count($courses); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="coursesTable">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Department</th>
                                        <th>Lecturer</th>
                                        <th>Students</th>
                                        <th>Resources</th>
                                        <th>Questions</th>
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
                                                <?php if ($course['lecturer_name']): ?>
                                                    <?php echo $course['lecturer_name']; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $course['student_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $course['resource_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $course['question_count']; ?></span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($course['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" data-bs-target="#editCourseModal<?php echo $course['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info" 
                                                            data-bs-toggle="modal" data-bs-target="#courseDetailsModal<?php echo $course['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <form method="POST" action="" class="d-inline" 
                                                          onsubmit="return confirm('Delete this course?')">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <button type="submit" name="delete_course" class="btn btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
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

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Course</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Code *</label>
                                <input type="text" class="form-control" name="course_code" required>
                                <small class="text-muted">Unique identifier for the course</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course Name *</label>
                                <input type="text" class="form-control" name="course_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department *</label>
                                <input type="text" class="form-control" name="department" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lecturer</label>
                                <select class="form-select" name="lecturer_id">
                                    <option value="">Select Lecturer</option>
                                    <?php foreach ($lecturers as $lecturer): ?>
                                        <option value="<?php echo $lecturer['id']; ?>"><?php echo $lecturer['full_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Can be assigned later</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" 
                                      placeholder="Enter course description, objectives, or other details..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Course Creation Guidelines:</strong>
                            <ul class="mb-0 small">
                                <li>Course codes must be unique</li>
                                <li>Assign a lecturer if available, or assign later</li>
                                <li>Provide a clear and descriptive course name</li>
                                <li>Include relevant department information</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_course" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Course
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#coursesTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [8] } // Disable sorting for actions column
                ]
            });
        });
    </script>
</body>
</html>