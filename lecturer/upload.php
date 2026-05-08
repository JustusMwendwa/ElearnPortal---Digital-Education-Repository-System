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
$stmt = $pdo->prepare("SELECT * FROM courses WHERE lecturer_id = ? ORDER BY course_code");
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll();

// Handle file upload
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_resource'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $course_id = $_POST['course_id'];
    $access_level = $_POST['access_level'];
    
    // Validate inputs
    if (empty($title) || empty($course_id)) {
        $error = "Title and course selection are required.";
    } elseif (!isset($_FILES['resource_file']) || $_FILES['resource_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = "Please select a file to upload.";
    } else {
        $file = $_FILES['resource_file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "File upload error: " . $file['error'];
        } else {
            // Validate file size (max 50MB)
            $max_size = 50 * 1024 * 1024; // 50MB in bytes
            if ($file['size'] > $max_size) {
                $error = "File size exceeds maximum limit of 50MB.";
            } else {
                // Validate file type
                $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'avi', 'mov', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error = "File type not allowed. Allowed types: " . implode(', ', $allowed_extensions);
                } else {
                    // Map extensions to file types for database
                    $ext_to_type = [
                        'pdf' => 'pdf',
                        'doc' => 'doc', 'docx' => 'docx',
                        'ppt' => 'ppt', 'pptx' => 'pptx',
                        'mp4' => 'video', 'avi' => 'video', 'mov' => 'video',
                        'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
                        'txt' => 'other', 'zip' => 'other'
                    ];
                    
                    $file_type_db = $ext_to_type[$file_extension] ?? 'other';
                    
                    // Generate unique filename
                    $timestamp = time();
                    $random_string = bin2hex(random_bytes(8));
                    $sanitized_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
                    $file_name = $timestamp . '_' . $random_string . '_' . $sanitized_name;
                    
                    // Upload directory
                    $upload_dir = "../uploads/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $target_file = $upload_dir . $file_name;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        // Insert into database
                        try {
                            $stmt = $pdo->prepare("INSERT INTO resources (title, description, file_name, file_type, file_size, 
                                                  course_id, uploaded_by, access_level) 
                                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $title, 
                                $description, 
                                $file_name, 
                                $file_type_db, 
                                $file['size'], 
                                $course_id, 
                                $lecturer_id, 
                                $access_level
                            ]);
                            
                            $success = "Resource uploaded successfully!";
                            
                            // Clear form
                            $_POST = [];
                        } catch (Exception $e) {
                            $error = "Database error: " . $e->getMessage();
                            // Remove uploaded file if database insert failed
                            if (file_exists($target_file)) {
                                unlink($target_file);
                            }
                        }
                    } else {
                        $error = "Failed to move uploaded file.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Resource - EduRepository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css">
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
        .upload-area {
            border: 3px dashed #ccc;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #ffbe0b;
            background: #fff8e1;
        }
        .file-preview {
            max-width: 100px;
            max-height: 100px;
        }
        .file-icon {
            font-size: 3rem;
            color: #ffbe0b;
        }
        .progress {
            height: 20px;
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
                    <a class="nav-link active" href="upload.php">
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
                        <i class="fas fa-upload text-warning"></i> Upload Resource
                    </h1>
                    <p class="text-muted">Upload educational materials for your courses</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-info-circle"></i> Upload Guidelines
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">File Requirements</h6>
                        <small class="dropdown-item-text">
                            <i class="fas fa-check text-success"></i> Max file size: 50MB
                        </small>
                        <small class="dropdown-item-text">
                            <i class="fas fa-check text-success"></i> Allowed formats: PDF, DOC, PPT, Videos, Images
                        </small>
                        <small class="dropdown-item-text">
                            <i class="fas fa-check text-success"></i> Use descriptive titles for easy searching
                        </small>
                        <div class="dropdown-divider"></div>
                        <h6 class="dropdown-header">Access Levels</h6>
                        <small class="dropdown-item-text">
                            <span class="badge bg-success">Public</span> - Accessible to everyone
                        </small>
                        <small class="dropdown-item-text">
                            <span class="badge bg-warning">Course Only</span> - Only for enrolled students
                        </small>
                        <small class="dropdown-item-text">
                            <span class="badge bg-secondary">Private</span> - Only visible to you
                        </small>
                    </div>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-file-upload"></i> Resource Details</h5>
                        </div>
                        <div class="card-body">
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

                            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                                <!-- File Upload -->
                                <div class="mb-4">
                                    <label class="form-label">Select File</label>
                                    <div class="upload-area" id="dropZone">
                                        <input type="file" class="form-control d-none" id="resource_file" name="resource_file" 
                                               accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.jpeg,.png,.gif,.txt,.zip">
                                        <div id="uploadContent">
                                            <i class="fas fa-cloud-upload-alt file-icon mb-3"></i>
                                            <h5>Drag & drop your file here</h5>
                                            <p class="text-muted">or click to browse</p>
                                            <small class="text-muted">Max file size: 50MB</small>
                                        </div>
                                        <div id="fileInfo" class="d-none">
                                            <div class="d-flex align-items-center justify-content-center mb-3">
                                                <i class="fas fa-file-alt fa-2x text-warning me-3"></i>
                                                <div class="text-start">
                                                    <h6 id="fileName" class="mb-0"></h6>
                                                    <small id="fileSize" class="text-muted"></small>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearFile()">
                                                <i class="fas fa-times"></i> Remove File
                                            </button>
                                        </div>
                                    </div>
                                    <div class="progress mt-2 d-none" id="uploadProgress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                             role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>

                                <!-- Resource Details -->
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Resource Title *</label>
                                        <input type="text" class="form-control" name="title" 
                                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                               placeholder="e.g., Introduction to Database Systems - Lecture 1" required>
                                        <small class="text-muted">Use a descriptive title for easy searching</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Course *</label>
                                        <?php if (empty($courses)): ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                No courses assigned. Contact administrator.
                                            </div>
                                        <?php else: ?>
                                            <select class="form-select" name="course_id" required>
                                                <option value="">Select Course</option>
                                                <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo $course['id']; ?>" 
                                                            <?php echo ($_POST['course_id'] ?? '') == $course['id'] ? 'selected' : ''; ?>>
                                                        <?php echo $course['course_code']; ?> - <?php echo $course['course_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" 
                                              placeholder="Brief description of the resource content..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <small class="text-muted">Optional: Add notes, keywords, or context</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Access Level</label>
                                        <select class="form-select" name="access_level">
                                            <option value="course_only" 
                                                    <?php echo ($_POST['access_level'] ?? 'course_only') == 'course_only' ? 'selected' : ''; ?>>
                                                Course Only (Enrolled students only)
                                            </option>
                                            <option value="public" 
                                                    <?php echo ($_POST['access_level'] ?? '') == 'public' ? 'selected' : ''; ?>>
                                                Public (Everyone can access)
                                            </option>
                                            <option value="private" 
                                                    <?php echo ($_POST['access_level'] ?? '') == 'private' ? 'selected' : ''; ?>>
                                                Private (Only visible to you)
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tags</label>
                                        <input type="text" class="form-control" 
                                               placeholder="e.g., lecture, slides, assignment, solution">
                                        <small class="text-muted">Separate tags with commas (optional)</small>
                                    </div>
                                </div>

                                <!-- File Type Preview -->
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-file-alt"></i> File Type Detection</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-3">
                                                <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                                <p class="small mb-0">PDF</p>
                                            </div>
                                            <div class="col-3">
                                                <i class="fas fa-file-word fa-2x text-primary"></i>
                                                <p class="small mb-0">DOC</p>
                                            </div>
                                            <div class="col-3">
                                                <i class="fas fa-file-powerpoint fa-2x text-warning"></i>
                                                <p class="small mb-0">PPT</p>
                                            </div>
                                            <div class="col-3">
                                                <i class="fas fa-file-video fa-2x text-success"></i>
                                                <p class="small mb-0">Video</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo"></i> Reset Form
                                    </button>
                                    <button type="submit" name="upload_resource" class="btn btn-warning" id="submitBtn">
                                        <i class="fas fa-upload"></i> Upload Resource
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar - Help & Recent Uploads -->
                <div class="col-lg-4">
                    <!-- Quick Stats -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Upload Statistics</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get upload statistics
                            $stmt = $pdo->prepare("SELECT 
                                COUNT(*) as total_uploads,
                                SUM(file_size) as total_size,
                                AVG(file_size) as avg_size
                                FROM resources 
                                WHERE uploaded_by = ?");
                            $stmt->execute([$lecturer_id]);
                            $stats = $stmt->fetch();
                            
                            // Format file sizes
                            function formatSize($bytes) {
                                if ($bytes == 0) return '0 Bytes';
                                $k = 1024;
                                $sizes = ['Bytes', 'KB', 'MB', 'GB'];
                                $i = floor(log($bytes) / log($k));
                                return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
                            }
                            ?>
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Total Uploads
                                    <span class="badge bg-primary rounded-pill"><?php echo $stats['total_uploads']; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Total Storage Used
                                    <span class="badge bg-info rounded-pill"><?php echo formatSize($stats['total_size']); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Average File Size
                                    <span class="badge bg-success rounded-pill"><?php echo formatSize($stats['avg_size']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Uploads -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-history"></i> Recent Uploads</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $stmt = $pdo->prepare("SELECT r.title, r.file_type, r.upload_date, c.course_code 
                                                  FROM resources r 
                                                  JOIN courses c ON r.course_id = c.id 
                                                  WHERE r.uploaded_by = ? 
                                                  ORDER BY r.upload_date DESC 
                                                  LIMIT 5");
                            $stmt->execute([$lecturer_id]);
                            $recent_uploads = $stmt->fetchAll();
                            ?>
                            
                            <?php if (empty($recent_uploads)): ?>
                                <p class="text-muted">No recent uploads</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_uploads as $upload): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <div>
                                                    <?php
                                                    $icon = '';
                                                    switch($upload['file_type']) {
                                                        case 'pdf': $icon = 'fa-file-pdf text-danger'; break;
                                                        case 'doc': case 'docx': $icon = 'fa-file-word text-primary'; break;
                                                        case 'ppt': case 'pptx': $icon = 'fa-file-powerpoint text-warning'; break;
                                                        case 'video': $icon = 'fa-file-video text-success'; break;
                                                        default: $icon = 'fa-file text-secondary';
                                                    }
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?> me-2"></i>
                                                    <strong class="mb-1"><?php echo substr($upload['title'], 0, 20); ?>...</strong>
                                                </div>
                                                <small><?php echo date('M d', strtotime($upload['upload_date'])); ?></small>
                                            </div>
                                            <small class="text-muted"><?php echo $upload['course_code']; ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="manage_resources.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-list"></i> View All Uploads
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Drag and drop file upload
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('resource_file');
        const uploadContent = document.getElementById('uploadContent');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressBar = uploadProgress.querySelector('.progress-bar');
        const submitBtn = document.getElementById('submitBtn');

        // Click to select file
        dropZone.addEventListener('click', () => fileInput.click());

        // File selection
        fileInput.addEventListener('change', handleFileSelect);

        // Drag and drop events
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect();
            }
        });

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                uploadContent.classList.add('d-none');
                fileInfo.classList.remove('d-none');
                
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                
                // Simulate upload progress (for demo purposes)
                simulateUploadProgress();
            }
        }

        function clearFile() {
            fileInput.value = '';
            uploadContent.classList.remove('d-none');
            fileInfo.classList.add('d-none');
            uploadProgress.classList.add('d-none');
            progressBar.style.width = '0%';
            progressBar.textContent = '';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
        }

        function simulateUploadProgress() {
            uploadProgress.classList.remove('d-none');
            let width = 0;
            const interval = setInterval(() => {
                if (width >= 100) {
                    clearInterval(interval);
                    progressBar.textContent = 'Ready to upload!';
                    submitBtn.disabled = false;
                } else {
                    width += 10;
                    progressBar.style.width = width + '%';
                    progressBar.textContent = width + '%';
                }
            }, 100);
        }

        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const file = fileInput.files[0];
            if (!file) {
                e.preventDefault();
                alert('Please select a file to upload.');
                return;
            }

            // Check file size (50MB limit)
            if (file.size > 50 * 1024 * 1024) {
                e.preventDefault();
                alert('File size exceeds 50MB limit.');
                return;
            }

            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>