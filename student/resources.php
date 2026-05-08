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

// Get filter parameters
$course_filter = $_GET['course'] ?? '';
$type_filter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Get enrolled courses
$stmt = $pdo->prepare("SELECT c.* FROM courses c 
                       JOIN enrollments e ON c.id = e.course_id 
                       WHERE e.student_id = ?");
$stmt->execute([$student_id]);
$enrolled_courses = $stmt->fetchAll();

// Build query for resources
if (!empty($enrolled_courses)) {
    $course_ids = array_column($enrolled_courses, 'id');
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    
    $query = "SELECT r.*, c.course_code, c.course_name, u.full_name as uploaded_by_name 
              FROM resources r 
              JOIN courses c ON r.course_id = c.id 
              JOIN users u ON r.uploaded_by = u.id 
              WHERE (r.course_id IN ($placeholders) OR r.access_level = 'public')";
    $params = $course_ids;
    
    if ($course_filter) {
        $query .= " AND r.course_id = ?";
        $params[] = $course_filter;
    }
    
    if ($type_filter) {
        $query .= " AND r.file_type = ?";
        $params[] = $type_filter;
    }
    
    if ($search) {
        $query .= " AND (r.title LIKE ? OR r.description LIKE ? OR c.course_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Sorting
    switch($sort) {
        case 'newest':
            $query .= " ORDER BY r.upload_date DESC";
            break;
        case 'oldest':
            $query .= " ORDER BY r.upload_date ASC";
            break;
        case 'downloads':
            $query .= " ORDER BY r.download_count DESC";
            break;
        case 'name':
            $query .= " ORDER BY r.title ASC";
            break;
        default:
            $query .= " ORDER BY r.upload_date DESC";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
} else {
    $resources = [];
}

// Get all file types for filter
$file_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'video', 'image', 'other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Resources - EduRepository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
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
        .resource-card {
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }
        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .file-icon-lg {
            font-size: 3rem;
        }
        .badge-pdf { background: #e63946; color: white; }
        .badge-doc { background: #3a86ff; color: white; }
        .badge-ppt { background: #ffbe0b; color: black; }
        .badge-video { background: #38b000; color: white; }
        .badge-image { background: #8338ec; color: white; }
        .badge-other { background: #6c757d; color: white; }
        .view-switch {
            cursor: pointer;
        }
        .view-switch.active {
            color: #3a86ff;
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
                    <a class="nav-link active" href="resources.php">
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
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-book text-primary"></i> Study Resources
                    </h1>
                    <p class="text-muted">Access educational materials from your courses</p>
                </div>
                <div class="d-flex align-items-center">
                    <span class="me-3">View:</span>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary view-switch active" data-view="grid">
                            <i class="fas fa-th-large"></i> Grid
                        </button>
                        <button type="button" class="btn btn-outline-primary view-switch" data-view="list">
                            <i class="fas fa-list"></i> List
                        </button>
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
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo $course['course_code']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">File Type</label>
                            <select class="form-select" name="type">
                                <option value="">All Types</option>
                                <?php foreach ($file_types as $type): ?>
                                    <option value="<?php echo $type; ?>" 
                                            <?php echo ($type_filter == $type) ? 'selected' : ''; ?>>
                                        <?php echo strtoupper($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sort By</label>
                            <select class="form-select" name="sort">
                                <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo ($sort == 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="downloads" <?php echo ($sort == 'downloads') ? 'selected' : ''; ?>>Most Downloads</option>
                                <option value="name" <?php echo ($sort == 'name') ? 'selected' : ''; ?>>Name (A-Z)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search resources...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="resources.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resources Grid View -->
            <div id="gridView" class="view-content">
                <?php if (empty($resources)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <h5>No resources found</h5>
                        <p class="text-muted">
                            <?php echo empty($enrolled_courses) ? 'You are not enrolled in any courses.' : 'No resources match your filter criteria.'; ?>
                        </p>
                        <?php if (empty($enrolled_courses)): ?>
                            <a href="courses.php" class="btn btn-primary">
                                <i class="fas fa-chalkboard-teacher"></i> Browse Courses
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($resources as $resource): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                                <div class="card resource-card h-100">
                                    <div class="card-body">
                                        <div class="text-center mb-3">
                                            <?php
                                            $icon_class = '';
                                            $badge_class = '';
                                            switch($resource['file_type']) {
                                                case 'pdf': 
                                                    $icon_class = 'fa-file-pdf text-danger'; 
                                                    $badge_class = 'badge-pdf';
                                                    break;
                                                case 'doc': case 'docx': 
                                                    $icon_class = 'fa-file-word text-primary'; 
                                                    $badge_class = 'badge-doc';
                                                    break;
                                                case 'ppt': case 'pptx': 
                                                    $icon_class = 'fa-file-powerpoint text-warning'; 
                                                    $badge_class = 'badge-ppt';
                                                    break;
                                                case 'video': 
                                                    $icon_class = 'fa-file-video text-success'; 
                                                    $badge_class = 'badge-video';
                                                    break;
                                                case 'image': 
                                                    $icon_class = 'fa-file-image text-info'; 
                                                    $badge_class = 'badge-image';
                                                    break;
                                                default: 
                                                    $icon_class = 'fa-file text-secondary'; 
                                                    $badge_class = 'badge-other';
                                            }
                                            ?>
                                            <i class="fas <?php echo $icon_class; ?> file-icon-lg mb-2"></i>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo strtoupper($resource['file_type']); ?>
                                            </span>
                                        </div>
                                        
                                        <h6 class="card-title"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                        
                                        <?php if ($resource['description']): ?>
                                            <p class="card-text small text-muted">
                                                <?php echo substr(htmlspecialchars($resource['description']), 0, 80); ?>
                                                <?php echo strlen($resource['description']) > 80 ? '...' : ''; ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted d-block">
                                                <i class="fas fa-chalkboard"></i> <?php echo $resource['course_code']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?php echo $resource['uploaded_by_name']; ?>
                                            </small>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-download"></i> <?php echo $resource['download_count']; ?> downloads
                                            </small>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($resource['upload_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="d-grid">
                                            <a href="../download.php?id=<?php echo $resource['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Resources List View (Hidden by default) -->
            <div id="listView" class="view-content d-none">
                <?php if (!empty($resources)): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="resourcesTable">
                                    <thead>
                                        <tr>
                                            <th>Resource</th>
                                            <th>Course</th>
                                            <th>Type</th>
                                            <th>Uploaded By</th>
                                            <th>Downloads</th>
                                            <th>Uploaded</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resources as $resource): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                        $icon_class = '';
                                                        switch($resource['file_type']) {
                                                            case 'pdf': $icon_class = 'fa-file-pdf text-danger'; break;
                                                            case 'doc': case 'docx': $icon_class = 'fa-file-word text-primary'; break;
                                                            case 'ppt': case 'pptx': $icon_class = 'fa-file-powerpoint text-warning'; break;
                                                            case 'video': $icon_class = 'fa-file-video text-success'; break;
                                                            default: $icon_class = 'fa-file text-secondary';
                                                        }
                                                        ?>
                                                        <i class="fas <?php echo $icon_class; ?> fa-2x me-3"></i>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($resource['title']); ?></strong>
                                                            <?php if ($resource['description']): ?>
                                                                <p class="mb-0 small text-muted">
                                                                    <?php echo substr(htmlspecialchars($resource['description']), 0, 60); ?>
                                                                    <?php echo strlen($resource['description']) > 60 ? '...' : ''; ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo $resource['course_code']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badge_class = '';
                                                    switch($resource['file_type']) {
                                                        case 'pdf': $badge_class = 'badge-pdf'; break;
                                                        case 'doc': case 'docx': $badge_class = 'badge-doc'; break;
                                                        case 'ppt': case 'pptx': $badge_class = 'badge-ppt'; break;
                                                        case 'video': $badge_class = 'badge-video'; break;
                                                        case 'image': $badge_class = 'badge-image'; break;
                                                        default: $badge_class = 'badge-other';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo strtoupper($resource['file_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $resource['uploaded_by_name']; ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $resource['download_count']; ?></span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M d, Y', strtotime($resource['upload_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <a href="../download.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
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

            <!-- Pagination -->
            <?php if (!empty($resources)): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        <p class="mb-0">Showing <?php echo count($resources); ?> resources</p>
                    </div>
                    <nav>
                        <ul class="pagination">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1">Previous</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('mainContent').classList.toggle('active');
        });

        // View switcher
        document.querySelectorAll('.view-switch').forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.view-switch').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Get view type
                const viewType = this.getAttribute('data-view');
                
                // Hide all views
                document.querySelectorAll('.view-content').forEach(view => {
                    view.classList.add('d-none');
                });
                
                // Show selected view
                document.getElementById(viewType + 'View').classList.remove('d-none');
                
                // Initialize DataTables for list view if needed
                if (viewType === 'list' && !$.fn.DataTable.isDataTable('#resourcesTable')) {
                    $('#resourcesTable').DataTable({
                        pageLength: 10,
                        responsive: true,
                        order: [[5, 'desc']]
                    });
                }
            });
        });

        // Initialize DataTables if list view is active
        $(document).ready(function() {
            if ($('#listView').is(':visible')) {
                $('#resourcesTable').DataTable({
                    pageLength: 10,
                    responsive: true,
                    order: [[5, 'desc']]
                });
            }
        });
    </script>
</body>
</html>