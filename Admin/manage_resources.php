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
$course_filter = $_GET['course'] ?? '';
$type_filter = $_GET['type'] ?? '';
$uploader_filter = $_GET['uploader'] ?? '';
$access_filter = $_GET['access'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT r.*, c.course_code, c.course_name, u.full_name as uploaded_by_name 
          FROM resources r 
          JOIN courses c ON r.course_id = c.id 
          JOIN users u ON r.uploaded_by = u.id 
          WHERE 1=1";
$params = [];

if ($course_filter) {
    $query .= " AND r.course_id = ?";
    $params[] = $course_filter;
}

if ($type_filter) {
    $query .= " AND r.file_type = ?";
    $params[] = $type_filter;
}

if ($uploader_filter) {
    $query .= " AND r.uploaded_by = ?";
    $params[] = $uploader_filter;
}

if ($access_filter) {
    $query .= " AND r.access_level = ?";
    $params[] = $access_filter;
}

if ($search) {
    $query .= " AND (r.title LIKE ? OR r.description LIKE ? OR c.course_name LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY r.upload_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Get courses for filter
$courses = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code")->fetchAll();

// Get uploaders for filter
$uploaders = $pdo->query("SELECT DISTINCT u.id, u.full_name 
                         FROM resources r 
                         JOIN users u ON r.uploaded_by = u.id 
                         ORDER BY u.full_name")->fetchAll();

// Get file types
$file_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'video', 'image', 'other'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_resource'])) {
        $resource_id = $_POST['resource_id'];
        
        // Get file info
        $stmt = $pdo->prepare("SELECT file_name FROM resources WHERE id = ?");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch();
        
        if ($resource) {
            // Delete file
            $file_path = "../uploads/" . $resource['file_name'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ?");
            $stmt->execute([$resource_id]);
            
            header('Location: manage_resources.php?success=Resource deleted successfully');
            exit();
        }
    }
    
    if (isset($_POST['update_resource'])) {
        $resource_id = $_POST['resource_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $course_id = $_POST['course_id'];
        $access_level = $_POST['access_level'];
        
        $stmt = $pdo->prepare("UPDATE resources 
                              SET title = ?, description = ?, course_id = ?, access_level = ? 
                              WHERE id = ?");
        $stmt->execute([$title, $description, $course_id, $access_level, $resource_id]);
        
        header('Location: manage_resources.php?success=Resource updated successfully');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resources - EduRepository</title>
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
        .resource-card {
            transition: transform 0.3s;
            border-left: 4px solid #3a86ff;
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
        .badge-public { background: #38b000; }
        .badge-course { background: #ffbe0b; color: black; }
        .badge-private { background: #6c757d; }
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
                    <a class="nav-link active" href="manage_resources.php">
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

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-book text-success"></i> Resource Management
                    </h1>
                    <p class="text-muted">Manage all educational resources in the system</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download"></i> Export Data
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#"><i class="fas fa-file-csv"></i> Export as CSV</a>
                        <a class="dropdown-item" href="#"><i class="fas fa-file-excel"></i> Export as Excel</a>
                        <a class="dropdown-item" href="#"><i class="fas fa-file-pdf"></i> Export as PDF</a>
                    </div>
                </div>
            </div>

            <!-- Resource Statistics -->
            <div class="row mb-4">
                <?php
                $total_resources = count($resources);
                $total_downloads = array_sum(array_column($resources, 'download_count'));
                $total_size = array_sum(array_column($resources, 'file_size'));
                $avg_size = $total_resources > 0 ? $total_size / $total_resources : 0;
                ?>
                <div class="col-md-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $total_resources; ?></h2>
                            <p class="mb-0">Total Resources</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo number_format($total_downloads); ?></h2>
                            <p class="mb-0">Total Downloads</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo round($total_size / (1024 * 1024 * 1024), 2); ?> GB</h2>
                            <p class="mb-0">Total Storage</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo round($avg_size / (1024 * 1024), 2); ?> MB</h2>
                            <p class="mb-0">Avg File Size</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-filter"></i> Advanced Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Course</label>
                            <select class="form-select" name="course">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
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
                                            <?php echo $type_filter == $type ? 'selected' : ''; ?>>
                                        <?php echo strtoupper($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Uploader</label>
                            <select class="form-select" name="uploader">
                                <option value="">All Uploaders</option>
                                <?php foreach ($uploaders as $uploader): ?>
                                    <option value="<?php echo $uploader['id']; ?>" 
                                            <?php echo $uploader_filter == $uploader['id'] ? 'selected' : ''; ?>>
                                        <?php echo $uploader['full_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Access Level</label>
                            <select class="form-select" name="access">
                                <option value="">All Access</option>
                                <option value="public" <?php echo $access_filter == 'public' ? 'selected' : ''; ?>>Public</option>
                                <option value="course_only" <?php echo $access_filter == 'course_only' ? 'selected' : ''; ?>>Course Only</option>
                                <option value="private" <?php echo $access_filter == 'private' ? 'selected' : ''; ?>>Private</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search resources...">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resources Table -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-table"></i> All Resources (<?php echo $total_resources; ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($resources)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5>No resources found</h5>
                            <p class="text-muted">No resources match your filter criteria</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="resourcesTable">
                                <thead>
                                    <tr>
                                        <th>Resource</th>
                                        <th>Course</th>
                                        <th>Type</th>
                                        <th>Uploader</th>
                                        <th>Access</th>
                                        <th>Downloads</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource): ?>
                                        <tr class="resource-card">
                                            <td>
                                                <div class="d-flex align-items-center">
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
                                                    <i class="fas <?php echo $icon_class; ?> fa-lg me-3"></i>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($resource['title']); ?></strong>
                                                        <?php if ($resource['description']): ?>
                                                            <p class="mb-0 small text-muted">
                                                                <?php echo substr(htmlspecialchars($resource['description']), 0, 50); ?>
                                                                <?php echo strlen($resource['description']) > 50 ? '...' : ''; ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark"><?php echo $resource['course_code']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo strtoupper($resource['file_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo $resource['uploaded_by_name']; ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $access_badge = '';
                                                switch($resource['access_level']) {
                                                    case 'public': $access_badge = 'badge-public'; break;
                                                    case 'course_only': $access_badge = 'badge-course'; break;
                                                    case 'private': $access_badge = 'badge-private'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $access_badge; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $resource['access_level'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $resource['download_count']; ?></span>
                                            </td>
                                            <td>
                                                <small><?php echo round($resource['file_size'] / 1024 / 1024, 2); ?> MB</small>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($resource['upload_date'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../download.php?id=<?php echo $resource['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-warning" 
                                                            data-bs-toggle="modal" data-bs-target="#editResourceModal<?php echo $resource['id']; ?>"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                                        <button type="submit" name="delete_resource" 
                                                                class="btn btn-outline-danger"
                                                                onclick="return confirm('Are you sure you want to delete this resource?')"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Edit Resource Modal -->
                                        <div class="modal fade" id="editResourceModal<?php echo $resource['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-warning text-white">
                                                        <h5 class="modal-title">Edit Resource</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                                            
                                                            <div class="row mb-3">
                                                                <div class="col-md-8">
                                                                    <div class="d-flex align-items-center">
                                                                        <i class="fas <?php echo $icon_class; ?> fa-2x me-3"></i>
                                                                        <div>
                                                                            <h6 class="mb-0"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                                                            <small class="text-muted">
                                                                                <?php echo $resource['course_code']; ?> • 
                                                                                <?php echo strtoupper($resource['file_type']); ?> • 
                                                                                <?php echo round($resource['file_size'] / 1024 / 1024, 2); ?> MB
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4 text-end">
                                                                    <span class="badge bg-info"><?php echo $resource['download_count']; ?> downloads</span>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Title *</label>
                                                                <input type="text" class="form-control" name="title" 
                                                                       value="<?php echo htmlspecialchars($resource['title']); ?>" required>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($resource['description']); ?></textarea>
                                                            </div>
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Course *</label>
                                                                    <select class="form-select" name="course_id" required>
                                                                        <option value="">Select Course</option>
                                                                        <?php foreach ($courses as $course): ?>
                                                                            <option value="<?php echo $course['id']; ?>" 
                                                                                    <?php echo $resource['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                                                                <?php echo $course['course_code']; ?> - <?php echo $course['course_name']; ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Access Level</label>
                                                                    <select class="form-select" name="access_level">
                                                                        <option value="course_only" <?php echo $resource['access_level'] == 'course_only' ? 'selected' : ''; ?>>
                                                                            Course Only (Enrolled students only)
                                                                        </option>
                                                                        <option value="public" <?php echo $resource['access_level'] == 'public' ? 'selected' : ''; ?>>
                                                                            Public (Everyone can access)
                                                                        </option>
                                                                        <option value="private" <?php echo $resource['access_level'] == 'private' ? 'selected' : ''; ?>>
                                                                            Private (Only visible to uploader)
                                                                        </option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="card bg-light">
                                                                <div class="card-body">
                                                                    <h6>File Information</h6>
                                                                    <div class="row">
                                                                        <div class="col-md-4">
                                                                            <small class="text-muted d-block">File Type:</small>
                                                                            <strong><?php echo strtoupper($resource['file_type']); ?></strong>
                                                                        </div>
                                                                        <div class="col-md-4">
                                                                            <small class="text-muted d-block">File Size:</small>
                                                                            <strong><?php echo round($resource['file_size'] / 1024 / 1024, 2); ?> MB</strong>
                                                                        </div>
                                                                        <div class="col-md-4">
                                                                            <small class="text-muted d-block">Uploaded:</small>
                                                                            <strong><?php echo date('M d, Y', strtotime($resource['upload_date'])); ?></strong>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="update_resource" class="btn btn-warning">
                                                                <i class="fas fa-save"></i> Save Changes
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
            $('#resourcesTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[7, 'desc']], // Sort by upload date descending
                columnDefs: [
                    { orderable: false, targets: [8] } // Disable sorting for actions column
                ]
            });
        });
    </script>
</body>
</html>