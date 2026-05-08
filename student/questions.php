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
$status_filter = $_GET['status'] ?? 'all';
$course_filter = $_GET['course'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for questions
$query = "SELECT q.*, c.course_code, c.course_name, u.full_name as lecturer_name,
                 (SELECT COUNT(*) FROM answers WHERE question_id = q.id) as answer_count
          FROM questions q 
          JOIN courses c ON q.course_id = c.id 
          JOIN users u ON q.lecturer_id = u.id 
          WHERE q.student_id = ?";
$params = [$student_id];

if ($status_filter !== 'all') {
    $query .= " AND q.status = ?";
    $params[] = $status_filter;
}

if ($course_filter) {
    $query .= " AND q.course_id = ?";
    $params[] = $course_filter;
}

if ($search) {
    $query .= " AND (q.subject LIKE ? OR q.message LIKE ? OR c.course_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY q.asked_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Get enrolled courses for filter
$stmt = $pdo->prepare("SELECT c.* FROM courses c 
                       JOIN enrollments e ON c.id = e.course_id 
                       WHERE e.student_id = ?");
$stmt->execute([$student_id]);
$enrolled_courses = $stmt->fetchAll();

// Handle new question submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ask_question'])) {
    $course_id = $_POST['course_id'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    // Get lecturer for the course
    $stmt = $pdo->prepare("SELECT lecturer_id FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if ($course && $course['lecturer_id']) {
        $stmt = $pdo->prepare("INSERT INTO questions (student_id, lecturer_id, course_id, subject, message) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $course['lecturer_id'], $course_id, $subject, $message]);
        $success = "Your question has been submitted successfully!";
        
        // Refresh page to show new question
        header('Location: questions.php?success=Question submitted successfully');
        exit();
    } else {
        $error = "Unable to submit question. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Questions - EduRepository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .question-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        .question-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .question-card.pending { border-left-color: #ffbe0b; }
        .question-card.answered { border-left-color: #38b000; }
        .question-card.closed { border-left-color: #6c757d; }
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 10px;
        }
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
                    <a class="nav-link active" href="questions.php">
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
                        <i class="fas fa-question-circle text-warning"></i> My Questions
                    </h1>
                    <p class="text-muted">Track and manage your questions to lecturers</p>
                </div>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#askQuestionModal">
                    <i class="fas fa-plus"></i> Ask New Question
                </button>
            </div>

            <!-- Question Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo count($questions); ?></h2>
                            <p class="mb-0">Total Questions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <?php $pending = array_filter($questions, fn($q) => $q['status'] == 'pending'); ?>
                            <h2 class="mb-0"><?php echo count($pending); ?></h2>
                            <p class="mb-0">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <?php $answered = array_filter($questions, fn($q) => $q['status'] == 'answered'); ?>
                            <h2 class="mb-0"><?php echo count($answered); ?></h2>
                            <p class="mb-0">Answered</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-secondary">
                        <div class="card-body text-center">
                            <?php $closed = array_filter($questions, fn($q) => $q['status'] == 'closed'); ?>
                            <h2 class="mb-0"><?php echo count($closed); ?></h2>
                            <p class="mb-0">Closed</p>
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
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="answered" <?php echo ($status_filter == 'answered') ? 'selected' : ''; ?>>Answered</option>
                                <option value="closed" <?php echo ($status_filter == 'closed') ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
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
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by subject or question...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="questions.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Questions List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($questions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                            <h5>No questions found</h5>
                            <p class="text-muted">
                                <?php echo $status_filter !== 'all' ? 'No questions match your filter criteria.' : 'You haven\'t asked any questions yet.'; ?>
                            </p>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#askQuestionModal">
                                <i class="fas fa-plus"></i> Ask Your First Question
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="accordion" id="questionsAccordion">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="accordion-item question-card <?php echo $question['status']; ?> mb-3">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button <?php echo ($index > 0) ? 'collapsed' : ''; ?>" 
                                                type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#collapse<?php echo $question['id']; ?>">
                                            <div class="d-flex w-100 align-items-center">
                                                <div class="me-3">
                                                    <?php
                                                    $status_color = '';
                                                    switch($question['status']) {
                                                        case 'pending': $status_color = 'bg-warning'; break;
                                                        case 'answered': $status_color = 'bg-success'; break;
                                                        case 'closed': $status_color = 'bg-secondary'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_color; ?> status-badge">
                                                        <?php echo ucfirst($question['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <strong><?php echo htmlspecialchars($question['subject']); ?></strong>
                                                    <div class="small text-muted">
                                                        <span class="me-3">
                                                            <i class="fas fa-chalkboard"></i> <?php echo $question['course_code']; ?>
                                                        </span>
                                                        <span class="me-3">
                                                            <i class="fas fa-user-tie"></i> <?php echo $question['lecturer_name']; ?>
                                                        </span>
                                                        <span>
                                                            <i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($question['asked_at'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <?php if ($question['answer_count'] > 0): ?>
                                                    <span class="badge bg-info me-2">
                                                        <?php echo $question['answer_count']; ?> answer(s)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $question['id']; ?>" 
                                         class="accordion-collapse collapse <?php echo ($index == 0) ? 'show' : ''; ?>" 
                                         data-bs-parent="#questionsAccordion">
                                        <div class="accordion-body">
                                            <!-- Question Details -->
                                            <div class="mb-4">
                                                <h6>Your Question:</h6>
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($question['message'])); ?></p>
                                                    </div>
                                                </div>
                                                <div class="mt-2 small text-muted">
                                                    Asked on <?php echo date('F d, Y \a\t H:i', strtotime($question['asked_at'])); ?>
                                                </div>
                                            </div>

                                            <!-- Answers Timeline -->
                                            <?php
                                            $stmt = $pdo->prepare("SELECT a.*, u.full_name 
                                                                  FROM answers a 
                                                                  JOIN users u ON a.lecturer_id = u.id 
                                                                  WHERE a.question_id = ? 
                                                                  ORDER BY a.answered_at ASC");
                                            $stmt->execute([$question['id']]);
                                            $answers = $stmt->fetchAll();
                                            ?>
                                            
                                            <?php if (!empty($answers)): ?>
                                                <div class="mb-4">
                                                    <h6>Answers:</h6>
                                                    <div class="question-timeline">
                                                        <?php foreach ($answers as $answer): ?>
                                                            <div class="card mb-3 position-relative">
                                                                <div class="timeline-dot"></div>
                                                                <div class="card-body">
                                                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($answer['answer'])); ?></p>
                                                                    <small class="text-muted">
                                                                        Answered by <strong><?php echo $answer['full_name']; ?></strong> 
                                                                        on <?php echo date('M d, Y \a\t H:i', strtotime($answer['answered_at'])); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php elseif ($question['status'] == 'pending'): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-clock"></i> Waiting for lecturer's response...
                                                </div>
                                            <?php elseif ($question['status'] == 'closed'): ?>
                                                <div class="alert alert-secondary">
                                                    <i class="fas fa-lock"></i> This question is closed and cannot be answered.
                                                </div>
                                            <?php endif; ?>

                                            <!-- Question Status -->
                                            <div class="d-flex justify-content-between align-items-center mt-4">
                                                <div>
                                                    <small class="text-muted">Question ID: #<?php echo $question['id']; ?></small>
                                                    <br>
                                                    <small class="text-muted">Course: <?php echo $question['course_name']; ?></small>
                                                </div>
                                                <div>
                                                    <?php if ($question['status'] == 'answered'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check"></i> Answered
                                                        </span>
                                                    <?php elseif ($question['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-clock"></i> Pending
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-lock"></i> Closed
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Ask Question Modal -->
    <div class="modal fade" id="askQuestionModal" tabindex="-1" aria-labelledby="askQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="askQuestionModalLabel">
                        <i class="fas fa-question-circle"></i> Ask a New Question
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Select Course *</label>
                            <select class="form-select" name="course_id" required>
                                <option value="">Choose a course...</option>
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($enrolled_courses)): ?>
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> You need to enroll in a course first.
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject *</label>
                            <input type="text" class="form-control" name="subject" 
                                   placeholder="Brief subject of your question" required>
                            <small class="text-muted">Be specific about what you need help with</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Your Question *</label>
                            <textarea class="form-control" name="message" rows="6" 
                                      placeholder="Describe your question in detail..." required></textarea>
                            <small class="text-muted">Provide as much detail as possible for better assistance</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Tips for getting better answers:</strong>
                            <ul class="mb-0 small">
                                <li>Be clear and specific about what you're asking</li>
                                <li>Mention which part of the material you're referring to</li>
                                <li>Include what you've tried so far (if applicable)</li>
                                <li>Be respectful and professional in your language</li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="ask_question" class="btn btn-warning" 
                            onclick="document.querySelector('#askQuestionModal form').submit()">
                        <i class="fas fa-paper-plane"></i> Submit Question
                    </button>
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

        // Auto-focus on subject field when modal opens
        const askQuestionModal = document.getElementById('askQuestionModal');
        askQuestionModal.addEventListener('shown.bs.modal', function () {
            const subjectInput = this.querySelector('input[name="subject"]');
            if (subjectInput) {
                subjectInput.focus();
            }
        });

        // Form validation
        document.querySelector('#askQuestionModal form').addEventListener('submit', function(e) {
            const courseSelect = this.querySelector('select[name="course_id"]');
            const subjectInput = this.querySelector('input[name="subject"]');
            const messageTextarea = this.querySelector('textarea[name="message"]');
            
            if (!courseSelect.value || !subjectInput.value.trim() || !messageTextarea.value.trim()) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            if (messageTextarea.value.trim().length < 10) {
                e.preventDefault();
                alert('Please provide a more detailed question (at least 10 characters).');
                return false;
            }
        });
    </script>
</body>
</html>