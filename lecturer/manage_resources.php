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
$type_filter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT r.*, c.course_code, c.course_name 
          FROM resources r 
          JOIN courses c ON r.course_id = c.id 
          WHERE r.uploaded_by = ?";
$params = [$lecturer_id];

if ($course_filter) {
    $query .= " AND r.course_id = ?";
    $params[] = $course_filter;
}

if ($type_filter) {
    $query .= " AND r.file_type = ?";
    $params[] = $type_filter;
}

if ($search) {
    $query .= " AND (r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY r.upload_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Get lecturer's courses for filter
$stmt = $pdo->prepare("SELECT * FROM courses WHERE lecturer_id = ?");
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll();

// Handle delete action
if (isset($_GET['delete'])) {
    $resource_id = $_GET['delete'];
    
    // Get resource info
    $stmt = $pdo->prepare("SELECT file_name FROM resources WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$resource_id, $lecturer_id]);
    $resource = $stmt->fetch();
    
    if ($resource) {
        // Delete file
        $file_path = "../uploads/" . $resource['file_name'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ? AND uploaded_by = ?");
        $stmt->execute([$resource_id, $lecturer_id]);
        
        header('Location: manage_resources.php?success=Resource deleted successfully');
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
        .file-icon {
            font-size: 1.5rem;
        }
        .resource-card {
            transition: all 0.3s;
        }
        .resource-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .badge-pdf { background: #e63946; color: white; }
        .badge-doc { background: #3a86ff; color: white; }
        .badge-ppt { background: #ffbe0b; color: black; }
        .badge-video { background: #38b000; color: white; }
        .badge-image { background: #8338ec; color: white; }
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
                    <a class="nav-link active" href="manage_resources.php">
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
                        <i class="fas fa-book text-warning"></i> Manage Resources
                    </h1>
                    <p class="text-muted">Manage and organize your educational materials</p>
                </div>
                <a href="upload.php" class="btn btn-warning">
                    <i class="fas fa-plus"></i> Upload New Resource
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-filter"></i> Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
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
                        <div class="col-md-3">
                            <label class="form-label">File Type</label>
                            <select class="form-select" name="type">
                                <option value="">All Types</option>
                                <option value="pdf" <?php echo ($type_filter == 'pdf') ? 'selected' : ''; ?>>PDF</option>
                                <option value="doc" <?php echo ($type_filter == 'doc') ? 'selected' : ''; ?>>DOC/DOCX</option>
                                <option value="ppt" <?php echo ($type_filter == 'ppt') ? 'selected' : ''; ?>>PPT/PPTX</option>
                                <option value="video" <?php echo ($type_filter == 'video') ? 'selected' : ''; ?>>Video</option>
                                <option value="image" <?php echo ($type_filter == 'image') ? 'selected' : ''; ?>>Image</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by title or description...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resources List -->
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-file-alt"></i> Your Resources (<?php echo count($resources); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($resources)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5>No resources found</h5>
                            <p class="text-muted">Upload your first resource using the button above</p>
                            <a href="upload.php" class="btn btn-warning">
                                <i class="fas fa-upload"></i> Upload Resource
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="resourcesTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Course</th>
                                        <th>Type</th>
                                        <th>Access</th>
                                        <th>Downloads</th>
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
                                                    $icon = '';
                                                    $badge_class = '';
                                                    switch($resource['file_type']) {
                                                        case 'pdf': 
                                                            $icon = 'fa-file-pdf'; 
                                                            $badge_class = 'badge-pdf';
                                                            break;
                                                        case 'doc': case 'docx': 
                                                            $icon = 'fa-file-word'; 
                                                            $badge_class = 'badge-doc';
                                                            break;
                                                        case 'ppt': case 'pptx': 
                                                            $icon = 'fa-file-powerpoint'; 
                                                            $badge_class = 'badge-ppt';
                                                            break;
                                                        case 'video': 
                                                            $icon = 'fa-file-video'; 
                                                            $badge_class = 'badge-video';
                                                            break;
                                                        case 'image': 
                                                            $icon = 'fa-file-image'; 
                                                            $badge_class = 'badge-image';
                                                            break;
                                                        default: 
                                                            $icon = 'fa-file'; 
                                                            $badge_class = 'badge-secondary';
                                                    }
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?> fa-lg text-warning me-3"></i>
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
                                                <span class="badge bg-light text-dark">
                                                    <?php echo $resource['course_code']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo strtoupper($resource['file_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $access_badge = '';
                                                switch($resource['access_level']) {
                                                    case 'public': $access_badge = 'badge bg-success'; break;
                                                    case 'course_only': $access_badge = 'badge bg-warning text-dark'; break;
                                                    case 'private': $access_badge = 'badge bg-secondary'; break;
                                                }
                                                ?>
                                                <span class="<?php echo $access_badge; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $resource['access_level'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $resource['download_count']; ?></span>
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
                                                    <a href="edit_resource.php?id=<?php echo $resource['id']; ?>" 
                                                       class="btn btn-outline-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="manage_resources.php?delete=<?php echo $resource['id']; ?>" 
                                                       class="btn btn-outline-danger" title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this resource?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
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
                order: [[5, 'desc']] // Sort by upload date descending
            });
        });
    </script>
</body>
</html>