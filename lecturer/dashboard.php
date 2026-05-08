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
$stmt = $pdo->prepare("SELECT * FROM courses WHERE lecturer_id = ?");
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll();

// Get pending questions
$stmt = $pdo->prepare("SELECT q.*, c.course_code, c.course_name, u.full_name as student_name 
                      FROM questions q 
                      JOIN courses c ON q.course_id = c.id 
                      JOIN users u ON q.student_id = u.id 
                      WHERE q.lecturer_id = ? AND q.status = 'pending' 
                      ORDER BY q.asked_at DESC");
$stmt->execute([$lecturer_id]);
$pending_questions = $stmt->fetchAll();

// Get lecturer's resources
$stmt = $pdo->prepare("SELECT r.*, c.course_code, c.course_name 
                      FROM resources r 
                      JOIN courses c ON r.course_id = c.id 
                      WHERE r.uploaded_by = ? 
                      ORDER BY r.upload_date DESC LIMIT 10");
$stmt->execute([$lecturer_id]);
$resources = $stmt->fetchAll();

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_answer'])) {
    $question_id = $_POST['question_id'];
    $answer = $_POST['answer'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO answers (question_id, lecturer_id, answer) VALUES (?, ?, ?)");
        $stmt->execute([$question_id, $lecturer_id, $answer]);
        
        $stmt = $pdo->prepare("UPDATE questions SET status = 'answered' WHERE id = ?");
        $stmt->execute([$question_id]);
        
        $pdo->commit();
        $success = "Answer submitted successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error submitting answer: " . $e->getMessage();
    }
}

// Handle resource upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_resource'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $course_id = $_POST['course_id'];
    $access_level = $_POST['access_level'];
    
    // Handle file upload
    if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['resource_file'];
        $file_name = time() . '_' . basename($file['name']);
        $file_type = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_size = $file['size'];
        
        // Map file extensions to file types
        $ext_mapping = [
            'pdf' => 'pdf',
            'doc' => 'doc', 'docx' => 'docx',
            'ppt' => 'ppt', 'pptx' => 'pptx',
            'mp4' => 'video', 'avi' => 'video', 'mov' => 'video',
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image'
        ];
        
        $file_type_db = $ext_mapping[strtolower($file_type)] ?? 'other';
        
        // Upload directory
        $upload_dir = "../uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $stmt = $pdo->prepare("INSERT INTO resources (title, description, file_name, file_type, file_size, 
                                  course_id, uploaded_by, access_level) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $file_name, $file_type_db, $file_size, 
                          $course_id, $lecturer_id, $access_level]);
            $success = "Resource uploaded successfully!";
        } else {
            $error = "Error uploading file.";
        }
    } else {
        $error = "Please select a file to upload.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - EduRepository</title>
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
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 10px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
        }
        .stat-card {
            background: linear-gradient(135deg, #ffbe0b, #fb5607);
            color: white;
        }
        .question-card {
            border-left: 4px solid #ffbe0b;
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
            <h4><i class="fas fa-chalkboard-teacher"></i> Lecturer Panel</h4>
            <p class="mb-0"><?php echo $lecturer['full_name']; ?></p>
            <span class="badge bg-warning">Lecturer</span>
        </div>
        <div class="px-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
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
                        <?php if (count($pending_questions) > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo count($pending_questions); ?></span>
                        <?php endif; ?>
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
    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm rounded mb-4">
            <div class="container-fluid">
                <button class="btn btn-warning" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3">
                        <strong>Dr. <?php echo $lecturer['full_name']; ?></strong> | <?php echo $lecturer['department']; ?>
                    </span>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?php echo count($courses); ?></h2>
                                    <p class="mb-0">Courses</p>
                                </div>
                                <i class="fas fa-chalkboard-teacher fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?php echo count($pending_questions); ?></h2>
                                    <p class="mb-0">Pending Questions</p>
                                </div>
                                <i class="fas fa-question-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?php echo count($resources); ?></h2>
                                    <p class="mb-0">Resources</p>
                                </div>
                                <i class="fas fa-file-alt fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php
                                    $total_downloads = array_sum(array_column($resources, 'download_count'));
                                    ?>
                                    <h2 class="mb-0"><?php echo $total_downloads; ?></h2>
                                    <p class="mb-0">Total Downloads</p>
                                </div>
                                <i class="fas fa-download fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Pending Questions -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0"><i class="fas fa-question-circle"></i> Pending Questions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pending_questions)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <p>No pending questions</p>
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="questionsAccordion">
                                    <?php foreach ($pending_questions as $index => $question): ?>
                                        <div class="accordion-item question-card">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed" type="button" 
                                                        data-bs-toggle="collapse" 
                                                        data-bs-target="#collapse<?php echo $question['id']; ?>">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($question['subject']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo $question['student_name']; ?> | <?php echo $question['course_code']; ?>
                                                            </small>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo date('M d', strtotime($question['asked_at'])); ?>
                                                        </small>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $question['id']; ?>" 
                                                 class="accordion-collapse collapse" 
                                                 data-bs-parent="#questionsAccordion">
                                                <div class="accordion-body">
                                                    <p><?php echo nl2br(htmlspecialchars($question['message'])); ?></p>
                                                    
                                                    <form method="POST" action="" class="mt-3">
                                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Your Answer</label>
                                                            <textarea class="form-control" name="answer" rows="3" required></textarea>
                                                        </div>
                                                        <button type="submit" name="submit_answer" class="btn btn-warning btn-sm">
                                                            <i class="fas fa-reply"></i> Submit Answer
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upload Resource -->
                    <div class="card mt-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-upload"></i> Quick Upload</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Resource Title</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="2"></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Course</label>
                                        <select class="form-select" name="course_id" required>
                                            <option value="">Select Course</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo $course['id']; ?>">
                                                    <?php echo $course['course_code']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Access Level</label>
                                        <select class="form-select" name="access_level">
                                            <option value="course_only">Course Only</option>
                                            <option value="public">Public</option>
                                            <option value="private">Private</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">File</label>
                                    <input type="file" class="form-control" name="resource_file" required>
                                    <small class="text-muted">Max size: 50MB</small>
                                </div>
                                <button type="submit" name="upload_resource" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload Resource
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Recent Resources & Courses -->
                <div class="col-md-6">
                    <!-- Recent Resources -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-file-alt"></i> Recent Resources</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($resources)): ?>
                                <p class="text-muted">No resources uploaded yet</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($resources, 0, 5) as $resource): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($resource['title']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $resource['course_code']; ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo strtoupper($resource['file_type']); ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $resource['download_count']; ?> downloads
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <a href="edit_resource.php?id=<?php echo $resource['id']; ?>" 
                                                   class="btn btn-sm btn-outline-warning">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="delete_resource.php?id=<?php echo $resource['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Delete this resource?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- My Courses -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-chalkboard"></i> My Courses</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($courses)): ?>
                                <p class="text-muted">No courses assigned yet</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($courses as $course): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo $course['course_code']; ?></h6>
                                                    <p class="card-text small"><?php echo $course['course_name']; ?></p>
                                                    <small class="text-muted"><?php echo $course['department']; ?></small>
                                                    <div class="mt-3">
                                                        <a href="course_students.php?id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-users"></i> Students
                                                        </a>
                                                        <a href="course_resources.php?id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-book"></i> Resources
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('mainContent').classList.toggle('active');
        });
    </script>
</body>
</html>