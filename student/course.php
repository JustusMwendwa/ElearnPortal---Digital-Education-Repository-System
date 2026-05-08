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

// Get course ID from URL
$course_id = $_GET['id'] ?? 0;

// Verify student is enrolled in this course
$stmt = $pdo->prepare("SELECT c.*, u.full_name as lecturer_name 
                      FROM courses c 
                      JOIN enrollments e ON c.id = e.course_id 
                      JOIN users u ON c.lecturer_id = u.id 
                      WHERE c.id = ? AND e.student_id = ?");
$stmt->execute([$course_id, $student_id]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit();
}

// Get course resources
$stmt = $pdo->prepare("SELECT r.*, u.full_name as uploaded_by_name 
                      FROM resources r 
                      JOIN users u ON r.uploaded_by = u.id 
                      WHERE r.course_id = ? 
                      ORDER BY r.upload_date DESC");
$stmt->execute([$course_id]);
$resources = $stmt->fetchAll();

// Get resource count by type
$resource_types = ['pdf', 'doc', 'ppt', 'video', 'image', 'other'];
$type_counts = [];
foreach ($resource_types as $type) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM resources WHERE course_id = ? AND file_type = ?");
    $stmt->execute([$course_id, $type]);
    $result = $stmt->fetch();
    $type_counts[$type] = $result['count'];
}

// Get student's questions for this course
$stmt = $pdo->prepare("SELECT q.*, 
                 (SELECT COUNT(*) FROM answers WHERE question_id = q.id) as answer_count
          FROM questions q 
          WHERE q.course_id = ? AND q.student_id = ? 
          ORDER BY q.asked_at DESC");
$stmt->execute([$course_id, $student_id]);
$course_questions = $stmt->fetchAll();

// Get course statistics
$total_resources = count($resources);
$total_questions = count($course_questions);
$answered_questions = array_reduce($course_questions, function($carry, $q) {
    return $carry + ($q['status'] == 'answered' ? 1 : 0);
}, 0);
$response_rate = $total_questions > 0 ? round(($answered_questions / $total_questions) * 100, 1) : 0;

// Handle new question
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ask_question'])) {
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    $stmt = $pdo->prepare("INSERT INTO questions (student_id, lecturer_id, course_id, subject, message) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$student_id, $course['lecturer_id'], $course_id, $subject, $message]);
    $success = "Your question has been submitted successfully!";
    
    // Refresh questions
    header('Location: course.php?id=' . $course_id . '&success=Question submitted successfully');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $course['course_code']; ?> - Course Details</title>
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
        .course-header {
            background: linear-gradient(135deg, #3a86ff, #8338ec);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .resource-card {
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }
        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .file-icon {
            font-size: 2rem;
        }
        .badge-pdf { background: #e63946; color: white; }
        .badge-doc { background: #3a86ff; color: white; }
        .badge-ppt { background: #ffbe0b; color: black; }
        .badge-video { background: #38b000; color: white; }
        .badge-image { background: #8338ec; color: white; }
        .badge-other { background: #6c757d; color: white; }
        .question-timeline {
            position: relative;
            padding-left: 30px;
        }
        .question-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-dot {
            position: absolute;
            left: 6px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3a86ff;
        }
        .tab-content {
            background: white;
            border: 1px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 10px 10px;
            padding: 30px;
        }
        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 15px 30px;
            font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            background: #3a86ff;
            color: white;
            border-color: #3a86ff;
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
                    <a class="nav-link" href="resources.php">
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
                <a href="courses.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Courses
                </a>
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3">
                        Welcome, <strong><?php echo $student['full_name']; ?></strong>
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

            <!-- Course Header -->
            <div class="course-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-6 mb-2"><?php echo $course['course_code']; ?></h1>
                        <h2 class="h4 mb-4"><?php echo htmlspecialchars($course['course_name']); ?></h2>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <i class="fas fa-user-tie"></i> 
                                    <strong>Lecturer:</strong> <?php echo $course['lecturer_name']; ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-building"></i> 
                                    <strong>Department:</strong> <?php echo $course['department']; ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <i class="fas fa-book"></i> 
                                    <strong>Resources:</strong> <?php echo $total_resources; ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-question-circle"></i> 
                                    <strong>My Questions:</strong> <?php echo $total_questions; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="bg-white text-primary p-4 rounded" style="border-radius: 10px;">
                            <h3 class="mb-0"><?php echo $response_rate; ?>%</h3>
                            <p class="mb-0">Response Rate</p>
                            <small class="text-muted"><?php echo $answered_questions; ?> of <?php echo $total_questions; ?> questions answered</small>
                        </div>
                    </div>
                </div>
                
                <?php if ($course['description']): ?>
                    <div class="mt-4">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
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
                            <h2 class="mb-0"><?php echo $type_counts['pdf']; ?></h2>
                            <p class="mb-0">PDF Files</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $total_questions; ?></h2>
                            <p class="mb-0">My Questions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $answered_questions; ?></h2>
                            <p class="mb-0">Answered</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="courseTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="resources-tab" data-bs-toggle="tab" data-bs-target="#resources" type="button">
                        <i class="fas fa-book"></i> Course Resources
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="questions-tab" data-bs-toggle="tab" data-bs-target="#questions" type="button">
                        <i class="fas fa-question-circle"></i> My Questions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ask-tab" data-bs-toggle="tab" data-bs-target="#ask" type="button">
                        <i class="fas fa-plus"></i> Ask Question
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                        <i class="fas fa-info-circle"></i> Course Info
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="courseTabContent">
                <!-- Resources Tab -->
                <div class="tab-pane fade show active" id="resources" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5>Course Materials (<?php echo $total_resources; ?>)</h5>
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-filter"></i> Filter by Type
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#" data-type="all">All Types</a>
                                <div class="dropdown-divider"></div>
                                <?php foreach ($resource_types as $type): 
                                    if ($type_counts[$type] > 0): ?>
                                        <a class="dropdown-item" href="#" data-type="<?php echo $type; ?>">
                                            <?php echo strtoupper($type); ?> 
                                            <span class="badge bg-primary float-end"><?php echo $type_counts[$type]; ?></span>
                                        </a>
                                    <?php endif; 
                                endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($resources)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5>No resources available</h5>
                            <p class="text-muted">The lecturer hasn't uploaded any materials for this course yet.</p>
                        </div>
                    <?php else: ?>
                        <!-- Resources Grid -->
                        <div class="row" id="resourcesGrid">
                            <?php foreach ($resources as $resource): ?>
                                <div class="col-xl-4 col-lg-6 mb-4 resource-item" data-type="<?php echo $resource['file_type']; ?>">
                                    <div class="card resource-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start mb-3">
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
                                                <i class="fas <?php echo $icon_class; ?> file-icon me-3"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                                    <small class="text-muted">
                                                        Uploaded by <?php echo $resource['uploaded_by_name']; ?>
                                                    </small>
                                                    <div class="mt-2">
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo strtoupper($resource['file_type']); ?>
                                                        </span>
                                                        <?php if ($resource['access_level'] == 'public'): ?>
                                                            <span class="badge bg-success">Public</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($resource['description']): ?>
                                                <p class="card-text small text-muted">
                                                    <?php echo substr(htmlspecialchars($resource['description']), 0, 100); ?>
                                                    <?php echo strlen($resource['description']) > 100 ? '...' : ''; ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-3">
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

                <!-- Questions Tab -->
                <div class="tab-pane fade" id="questions" role="tabpanel">
                    <h5 class="mb-4">My Questions for This Course (<?php echo $total_questions; ?>)</h5>
                    
                    <?php if (empty($course_questions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                            <h5>No questions asked yet</h5>
                            <p class="text-muted">You haven't asked any questions in this course yet.</p>
                            <button type="button" class="btn btn-warning" data-bs-toggle="tab" data-bs-target="#ask">
                                <i class="fas fa-plus"></i> Ask Your First Question
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="question-timeline">
                            <?php foreach ($course_questions as $question): ?>
                                <div class="card mb-4 position-relative">
                                    <div class="timeline-dot"></div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($question['subject']); ?></h6>
                                                <small class="text-muted">
                                                    Asked on <?php echo date('F d, Y \a\t H:i', strtotime($question['asked_at'])); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php echo $question['status'] == 'pending' ? 'warning' : 'success'; ?>">
                                                <?php echo ucfirst($question['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <p class="mb-3"><?php echo nl2br(htmlspecialchars($question['message'])); ?></p>
                                        
                                        <?php if ($question['answer_count'] > 0): ?>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT a.*, u.full_name 
                                                                  FROM answers a 
                                                                  JOIN users u ON a.lecturer_id = u.id 
                                                                  WHERE a.question_id = ? 
                                                                  ORDER BY a.answered_at ASC");
                                            $stmt->execute([$question['id']]);
                                            $answers = $stmt->fetchAll();
                                            ?>
                                            
                                            <div class="mt-4">
                                                <h6 class="border-bottom pb-2">Answers (<?php echo $question['answer_count']; ?>)</h6>
                                                <?php foreach ($answers as $answer): ?>
                                                    <div class="card bg-light mt-3">
                                                        <div class="card-body">
                                                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($answer['answer'])); ?></p>
                                                            <small class="text-muted">
                                                                Answered by <strong><?php echo $answer['full_name']; ?></strong> 
                                                                on <?php echo date('M d, Y \a\t H:i', strtotime($answer['answered_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php elseif ($question['status'] == 'pending'): ?>
                                            <div class="alert alert-warning mt-3">
                                                <i class="fas fa-clock"></i> Waiting for lecturer's response...
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ask Question Tab -->
                <div class="tab-pane fade" id="ask" role="tabpanel">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card border-0">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Ask a Question</h5>
                                    
                                    <form method="POST" action="">
                                        <div class="mb-4">
                                            <label class="form-label">Subject *</label>
                                            <input type="text" class="form-control" name="subject" 
                                                   placeholder="Brief subject of your question" required>
                                            <small class="text-muted">Be specific about what you need help with</small>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Your Question *</label>
                                            <textarea class="form-control" name="message" rows="8" 
                                                      placeholder="Describe your question in detail..." required></textarea>
                                            <small class="text-muted">Provide as much detail as possible for better assistance</small>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6><i class="fas fa-info-circle text-primary"></i> Course Information</h6>
                                                    <p class="mb-1"><strong>Course:</strong> <?php echo $course['course_code']; ?> - <?php echo htmlspecialchars($course['course_name']); ?></p>
                                                    <p class="mb-1"><strong>Lecturer:</strong> <?php echo $course['lecturer_name']; ?></p>
                                                    <p class="mb-0"><strong>Department:</strong> <?php echo $course['department']; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-lightbulb"></i>
                                            <strong>Tips for getting better answers:</strong>
                                            <ul class="mb-0 small">
                                                <li>Mention which lecture, chapter, or topic you're referring to</li>
                                                <li>Include page numbers or slide numbers if applicable</li>
                                                <li>Describe what you've tried or where you're stuck</li>
                                                <li>Be respectful and professional in your language</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="tab" data-bs-target="#resources">
                                                Cancel
                                            </button>
                                            <button type="submit" name="ask_question" class="btn btn-warning">
                                                <i class="fas fa-paper-plane"></i> Submit Question
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Info Tab -->
                <div class="tab-pane fade" id="info" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Course Details</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table">
                                        <tr>
                                            <th style="width: 200px;">Course Code:</th>
                                            <td><?php echo $course['course_code']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Course Name:</th>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Department:</th>
                                            <td><?php echo $course['department']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Lecturer:</th>
                                            <td><?php echo $course['lecturer_name']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Course Created:</th>
                                            <td><?php echo date('F d, Y', strtotime($course['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Your Enrollment:</th>
                                            <td>
                                                <?php
                                                $stmt = $pdo->prepare("SELECT enrolled_at FROM enrollments 
                                                                      WHERE student_id = ? AND course_id = ?");
                                                $stmt->execute([$student_id, $course_id]);
                                                $enrollment = $stmt->fetch();
                                                ?>
                                                <?php echo date('F d, Y', strtotime($enrollment['enrolled_at'])); ?>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <?php if ($course['description']): ?>
                                        <h6 class="mt-4">Course Description</h6>
                                        <div class="border rounded p-3 bg-light">
                                            <?php echo nl2br(htmlspecialchars($course['description'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Resource Statistics -->
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Resource Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php foreach ($resource_types as $type): 
                                            if ($type_counts[$type] > 0): ?>
                                                <div class="col-md-4 mb-3">
                                                    <div class="text-center p-3 border rounded">
                                                        <?php
                                                        $icon_class = '';
                                                        switch($type) {
                                                            case 'pdf': $icon_class = 'fa-file-pdf text-danger'; break;
                                                            case 'doc': $icon_class = 'fa-file-word text-primary'; break;
                                                            case 'ppt': $icon_class = 'fa-file-powerpoint text-warning'; break;
                                                            case 'video': $icon_class = 'fa-file-video text-success'; break;
                                                            case 'image': $icon_class = 'fa-file-image text-info'; break;
                                                            default: $icon_class = 'fa-file text-secondary';
                                                        }
                                                        ?>
                                                        <i class="fas <?php echo $icon_class; ?> fa-2x mb-2"></i>
                                                        <h4 class="mb-0"><?php echo $type_counts[$type]; ?></h4>
                                                        <p class="mb-0"><?php echo strtoupper($type); ?> Files</p>
                                                    </div>
                                                </div>
                                            <?php endif; 
                                        endforeach; ?>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <h6>Resource Distribution</h6>
                                        <div class="progress" style="height: 20px;">
                                            <?php
                                            $total = array_sum($type_counts);
                                            $positions = 0;
                                            $colors = ['bg-danger', 'bg-primary', 'bg-warning', 'bg-success', 'bg-info', 'bg-secondary'];
                                            $i = 0;
                                            foreach ($type_counts as $type => $count):
                                                if ($count > 0):
                                                    $width = ($count / $total) * 100;
                                                    ?>
                                                    <div class="progress-bar <?php echo $colors[$i % count($colors)]; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $width; ?>%"
                                                         title="<?php echo strtoupper($type) . ': ' . $count; ?>">
                                                    </div>
                                                    <?php
                                                    $i++;
                                                endif;
                                            endforeach;
                                            ?>
                                        </div>
                                        <small class="text-muted">Total resources: <?php echo $total_resources; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Quick Actions -->
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-warning" data-bs-toggle="tab" data-bs-target="#ask">
                                            <i class="fas fa-question-circle"></i> Ask a Question
                                        </button>
                                        <a href="resources.php?course=<?php echo $course_id; ?>" class="btn btn-primary">
                                            <i class="fas fa-book"></i> View All Resources
                                        </a>
                                        <a href="questions.php?course=<?php echo $course_id; ?>" class="btn btn-info">
                                            <i class="fas fa-list"></i> View All Questions
                                        </a>
                                        <a href="courses.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left"></i> Back to Courses
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Course Progress -->
                            <div class="card">
                                <div class="card-header bg-warning">
                                    <h5 class="mb-0">Your Progress</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-2">Resource Usage</small>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-primary" 
                                                 style="width: <?php echo min($total_resources * 10, 100); ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $total_resources; ?> resources available</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-2">Question Response Rate</small>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $response_rate; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $response_rate; ?>% answered</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-2">Engagement Level</small>
                                        <?php
                                        $engagement = min(($total_questions * 20) + ($total_resources * 5), 100);
                                        ?>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-info" 
                                                 style="width: <?php echo $engagement; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $engagement; ?>% engaged</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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

        // Tab activation
        const triggerTabList = [].slice.call(document.querySelectorAll('#courseTab button'));
        triggerTabList.forEach(function (triggerEl) {
            const tabTrigger = new bootstrap.Tab(triggerEl);
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });

        // Resource filter
        document.querySelectorAll('.dropdown-item[data-type]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const type = this.getAttribute('data-type');
                const resources = document.querySelectorAll('.resource-item');
                
                resources.forEach(resource => {
                    if (type === 'all' || resource.getAttribute('data-type') === type) {
                        resource.style.display = 'block';
                    } else {
                        resource.style.display = 'none';
                    }
                });
                
                // Update button text
                const dropdownButton = document.querySelector('.dropdown-toggle');
                if (type === 'all') {
                    dropdownButton.innerHTML = '<i class="fas fa-filter"></i> Filter by Type';
                } else {
                    dropdownButton.innerHTML = `<i class="fas fa-filter"></i> ${type.toUpperCase()}`;
                }
            });
        });

        // Form validation for ask question
        const askForm = document.querySelector('#ask form');
        if (askForm) {
            askForm.addEventListener('submit', function(e) {
                const subject = this.querySelector('input[name="subject"]');
                const message = this.querySelector('textarea[name="message"]');
                
                if (!subject.value.trim() || !message.value.trim()) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
                
                if (message.value.trim().length < 10) {
                    e.preventDefault();
                    alert('Please provide a more detailed question (at least 10 characters).');
                    return false;
                }
            });
        }
    </script>
</body>
</html>