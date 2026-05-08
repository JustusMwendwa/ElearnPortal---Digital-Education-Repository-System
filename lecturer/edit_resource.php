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

// Get resource ID from URL
$resource_id = $_GET['id'] ?? 0;

// Get resource details
$stmt = $pdo->prepare("SELECT r.*, c.course_code, c.course_name 
                       FROM resources r 
                       JOIN courses c ON r.course_id = c.id 
                       WHERE r.id = ? AND r.uploaded_by = ?");
$stmt->execute([$resource_id, $lecturer_id]);
$resource = $stmt->fetch();

if (!$resource) {
    header('Location: manage_resources.php');
    exit();
}

// Get lecturer's courses
$stmt = $pdo->prepare("SELECT * FROM courses WHERE lecturer_id = ? ORDER BY course_code");
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll();

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_resource'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $course_id = $_POST['course_id'];
    $access_level = $_POST['access_level'];
    
    // Validate inputs
    if (empty($title) || empty($course_id)) {
        $error = "Title and course selection are required.";
    } else {
        try {
            // Update resource in database
            $stmt = $pdo->prepare("UPDATE resources 
                                  SET title = ?, description = ?, course_id = ?, access_level = ? 
                                  WHERE id = ? AND uploaded_by = ?");
            $stmt->execute([$title, $description, $course_id, $access_level, $resource_id, $lecturer_id]);
            
            $success = "Resource updated successfully!";
            
            // Refresh resource data
            $stmt = $pdo->prepare("SELECT r.*, c.course_code, c.course_name 
                                   FROM resources r 
                                   JOIN courses c ON r.course_id = c.id 
                                   WHERE r.id = ? AND r.uploaded_by = ?");
            $stmt->execute([$resource_id, $lecturer_id]);
            $resource = $stmt->fetch();
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle file replacement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['replace_file'])) {
    if (isset($_FILES['new_file']) && $_FILES['new_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['new_file'];
        
        // Validate file size (max 50MB)
        $max_size = 50 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            $error = "File size exceeds maximum limit of 50MB.";
        } else {
            // Validate file type
            $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'avi', 'mov', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $error = "File type not allowed. Allowed types: " . implode(', ', $allowed_extensions);
            } else {
                // Map extensions to file types
                $ext_to_type = [
                    'pdf' => 'pdf',
                    'doc' => 'doc', 'docx' => 'docx',
                    'ppt' => 'ppt', 'pptx' => 'pptx',
                    'mp4' => 'video', 'avi' => 'video', 'mov' => 'video',
                    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
                    'txt' => 'other', 'zip' => 'other'
                ];
                
                $file_type_db = $ext_to_type[$file_extension] ?? 'other';
                
                // Generate new filename
                $timestamp = time();
                $random_string = bin2hex(random_bytes(8));
                $sanitized_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
                $new_file_name = $timestamp . '_' . $random_string . '_' . $sanitized_name;
                
                // Delete old file
                $old_file_path = "../uploads/" . $resource['file_name'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
                
                // Upload new file
                $upload_dir = "../uploads/";
                $target_file = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    // Update database
                    $stmt = $pdo->prepare("UPDATE resources 
                                          SET file_name = ?, file_type = ?, file_size = ? 
                                          WHERE id = ? AND uploaded_by = ?");
                    $stmt->execute([$new_file_name, $file_type_db, $file['size'], $resource_id, $lecturer_id]);
                    
                    $success = "File replaced successfully!";
                    
                    // Refresh resource data
                    $stmt = $pdo->prepare("SELECT r.*, c.course_code, c.course_name 
                                           FROM resources r 
                                           JOIN courses c ON r.course_id = c.id 
                                           WHERE r.id = ? AND r.uploaded_by = ?");
                    $stmt->execute([$resource_id, $lecturer_id]);
                    $resource = $stmt->fetch();
                } else {
                    $error = "Failed to upload new file.";
                }
            }
        }
    } else {
        $error = "Please select a new file.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Resource - EduRepository</title>
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
        .file-icon-lg {
            font-size: 4rem;
        }
        .file-info-card {
            border-left: 4px solid #ffbe0b;
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
                <a href="manage_resources.php" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i> Back to Resources
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
                        <i class="fas fa-edit text-warning"></i> Edit Resource
                    </h1>
                    <p class="text-muted">Update resource details and information</p>
                </div>
                <div>
                    <a href="../download.php?id=<?php echo $resource_id; ?>" class="btn btn-success">
                        <i class="fas fa-download"></i> Download
                    </a>
                    <a href="manage_resources.php?delete=<?php echo $resource_id; ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('Are you sure you want to delete this resource?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Left Column - Edit Form -->
                <div class="col-lg-8">
                    <!-- Edit Resource Details -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Resource Details</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Resource Title *</label>
                                    <input type="text" class="form-control" name="title" 
                                           value="<?php echo htmlspecialchars($resource['title']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($resource['description']); ?></textarea>
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
                                                Private (Only visible to you)
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="manage_resources.php" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                    <button type="submit" name="update_resource" class="btn btn-warning">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Replace File -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-exchange-alt"></i> Replace File</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Select New File</label>
                                    <input type="file" class="form-control" name="new_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.jpeg,.png,.gif,.txt,.zip">
                                    <small class="text-muted">Max file size: 50MB. This will replace the current file.</small>
                                </div>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Warning:</strong> Replacing the file will delete the current file and upload the new one. This action cannot be undone.
                                </div>
                                <button type="submit" name="replace_file" class="btn btn-primary" 
                                        onclick="return confirm('Are you sure you want to replace the current file?')">
                                    <i class="fas fa-exchange-alt"></i> Replace File
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Resource Info -->
                <div class="col-lg-4">
                    <!-- Current File Info -->
                    <div class="card mb-4 file-info-card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-file-alt"></i> Current File</h5>
                        </div>
                        <div class="card-body text-center">
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
                                case 'image': 
                                    $icon_class = 'fa-file-image'; 
                                    $icon_color = 'text-info';
                                    break;
                                default: 
                                    $icon_class = 'fa-file'; 
                                    $icon_color = 'text-secondary';
                            }
                            ?>
                            <i class="fas <?php echo $icon_class; ?> <?php echo $icon_color; ?> file-icon-lg mb-3"></i>
                            <h5><?php echo htmlspecialchars($resource['title']); ?></h5>
                            <p class="text-muted"><?php echo $resource['course_code']; ?></p>
                            
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>File Type:</span>
                                    <span class="badge bg-light text-dark"><?php echo strtoupper($resource['file_type']); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>File Size:</span>
                                    <span><?php echo round($resource['file_size'] / 1024 / 1024, 2); ?> MB</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Downloads:</span>
                                    <span class="badge bg-info"><?php echo $resource['download_count']; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Uploaded:</span>
                                    <span><?php echo date('M d, Y', strtotime($resource['upload_date'])); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Access:</span>
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
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="../download.php?id=<?php echo $resource_id; ?>" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-download"></i> Download File
                                </a>
                                <a href="manage_resources.php?delete=<?php echo $resource_id; ?>" 
                                   class="btn btn-outline-danger w-100"
                                   onclick="return confirm('Are you sure you want to delete this resource?')">
                                    <i class="fas fa-trash"></i> Delete Resource
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Resource Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <h4 class="mb-0"><?php echo $resource['download_count']; ?></h4>
                                        <small class="text-muted">Downloads</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <h4 class="mb-0">
                                            <?php echo round($resource['file_size'] / 1024 / 1024, 1); ?> MB
                                        </h4>
                                        <small class="text-muted">File Size</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <h6>Popularity</h6>
                                <div class="progress" style="height: 20px;">
                                    <?php
                                    $popularity = min($resource['download_count'] * 10, 100);
                                    ?>
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo $popularity; ?>%">
                                        <?php echo $popularity; ?>%
                                    </div>
                                </div>
                                <small class="text-muted">Based on download count</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const fileInput = this.querySelector('input[type="file"]');
                    if (fileInput && fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        const maxSize = 50 * 1024 * 1024; // 50MB
                        
                        if (file.size > maxSize) {
                            e.preventDefault();
                            alert('File size exceeds 50MB limit.');
                            return false;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>