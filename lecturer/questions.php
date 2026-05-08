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
$status_filter = $_GET['status'] ?? 'all';
$course_filter = $_GET['course'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for questions
$query = "SELECT q.*, c.course_code, c.course_name, u.full_name as student_name, 
                 u.department as student_department,
                 (SELECT COUNT(*) FROM answers WHERE question_id = q.id) as answer_count
          FROM questions q 
          JOIN courses c ON q.course_id = c.id 
          JOIN users u ON q.student_id = u.id 
          WHERE q.lecturer_id = ?";
$params = [$lecturer_id];

if ($status_filter !== 'all') {
    $query .= " AND q.status = ?";
    $params[] = $status_filter;
}

if ($course_filter) {
    $query .= " AND q.course_id = ?";
    $params[] = $course_filter;
}

if ($search) {
    $query .= " AND (q.subject LIKE ? OR q.message LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY q.asked_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Get lecturer's courses for filter
$stmt = $pdo->prepare("SELECT * FROM courses WHERE lecturer_id = ?");
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll();

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_answer'])) {
    $question_id = $_POST['question_id'];
    $answer = $_POST['answer'];
    
    try {
        $pdo->beginTransaction();
        
        // Insert answer
        $stmt = $pdo->prepare("INSERT INTO answers (question_id, lecturer_id, answer) VALUES (?, ?, ?)");
        $stmt->execute([$question_id, $lecturer_id, $answer]);
        
        // Update question status
        $stmt = $pdo->prepare("UPDATE questions SET status = 'answered' WHERE id = ?");
        $stmt->execute([$question_id]);
        
        $pdo->commit();
        $success = "Answer submitted successfully!";
        
        // Refresh page to show updated status
        header('Location: questions.php?success=Answer submitted successfully');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error submitting answer: " . $e->getMessage();
    }
}

// Handle status change
if (isset($_GET['mark_pending']) || isset($_GET['mark_closed'])) {
    $question_id = isset($_GET['mark_pending']) ? $_GET['mark_pending'] : $_GET['mark_closed'];
    $new_status = isset($_GET['mark_pending']) ? 'pending' : 'closed';
    
    $stmt = $pdo->prepare("UPDATE questions SET status = ? WHERE id = ? AND lecturer_id = ?");
    $stmt->execute([$new_status, $question_id, $lecturer_id]);
    
    header('Location: questions.php?success=Question marked as ' . $new_status);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Questions - EduRepository</title>
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
                    <a class="nav-link active" href="questions.php">
                        <i class="fas fa-question-circle"></i> Questions
                        <?php 
                        $pending_count = array_reduce($questions, function($carry, $q) {
                            return $carry + ($q['status'] == 'pending' ? 1 : 0);
                        }, 0);
                        if ($pending_count > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $pending_count; ?></span>
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
                        <i class="fas fa-question-circle text-warning"></i> Student Questions
                    </h1>
                    <p class="text-muted">Answer questions from your students</p>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-warning dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <?php
                        $status_counts = [
                            'pending' => 0,
                            'answered' => 0,
                            'closed' => 0
                        ];
                        foreach ($questions as $q) {
                            $status_counts[$q['status']]++;
                        }
                        ?>
                        <h6 class="dropdown-header">Question Status</h6>
                        <a class="dropdown-item" href="#">
                            Pending: <span class="badge bg-warning float-end"><?php echo $status_counts['pending']; ?></span>
                        </a>
                        <a class="dropdown-item" href="#">
                            Answered: <span class="badge bg-success float-end"><?php echo $status_counts['answered']; ?></span>
                        </a>
                        <a class="dropdown-item" href="#">
                            Closed: <span class="badge bg-secondary float-end"><?php echo $status_counts['closed']; ?></span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#">
                            Total: <span class="badge bg-primary float-end"><?php echo count($questions); ?></span>
                        </a>
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
                                <?php foreach ($courses as $course): ?>
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
                                   placeholder="Search by student, subject, or question...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Questions List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($questions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>No questions found</h5>
                            <p class="text-muted">No questions match your filter criteria</p>
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
                                                            <i class="fas fa-user"></i> <?php echo $question['student_name']; ?>
                                                        </span>
                                                        <span class="me-3">
                                                            <i class="fas fa-chalkboard"></i> <?php echo $question['course_code']; ?>
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
                                            <!-- Question -->
                                            <div class="mb-4">
                                                <h6>Question:</h6>
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($question['message'])); ?></p>
                                                    </div>
                                                </div>
                                                <div class="mt-2 small text-muted">
                                                    Asked by: <?php echo $question['student_name']; ?> 
                                                    (<?php echo $question['student_department']; ?>)
                                                </div>
                                            </div>

                                            <!-- Previous Answers -->
                                            <?php
                                            $stmt = $pdo->prepare("SELECT a.*, u.full_name 
                                                                  FROM answers a 
                                                                  JOIN users u ON a.lecturer_id = u.id 
                                                                  WHERE a.question_id = ? 
                                                                  ORDER BY a.answered_at DESC");
                                            $stmt->execute([$question['id']]);
                                            $answers = $stmt->fetchAll();
                                            ?>
                                            
                                            <?php if (!empty($answers)): ?>
                                                <div class="mb-4">
                                                    <h6>Previous Answers:</h6>
                                                    <?php foreach ($answers as $answer): ?>
                                                        <div class="card mb-2">
                                                            <div class="card-body">
                                                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($answer['answer'])); ?></p>
                                                                <small class="text-muted">
                                                                    Answered by <?php echo $answer['full_name']; ?> 
                                                                    on <?php echo date('M d, Y H:i', strtotime($answer['answered_at'])); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Answer Form -->
                                            <?php if ($question['status'] !== 'closed'): ?>
                                                <div class="mt-4">
                                                    <h6>Your Answer:</h6>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                        <div class="mb-3">
                                                            <textarea class="form-control" name="answer" rows="4" 
                                                                      placeholder="Type your answer here..." required></textarea>
                                                        </div>
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <button type="submit" name="submit_answer" class="btn btn-warning">
                                                                    <i class="fas fa-reply"></i> Submit Answer
                                                                </button>
                                                                <?php if ($question['status'] == 'answered'): ?>
                                                                    <a href="questions.php?mark_pending=<?php echo $question['id']; ?>" 
                                                                       class="btn btn-outline-warning">
                                                                        <i class="fas fa-clock"></i> Mark as Pending
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                            <a href="questions.php?mark_closed=<?php echo $question['id']; ?>" 
                                                               class="btn btn-outline-secondary"
                                                               onclick="return confirm('Close this question? It cannot be reopened.')">
                                                                <i class="fas fa-lock"></i> Close Question
                                                            </a>
                                                        </div>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-secondary">
                                                    <i class="fas fa-lock"></i> This question is closed and cannot be answered.
                                                </div>
                                            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on answer textarea when accordion is opened
        document.addEventListener('DOMContentLoaded', function() {
            var accordionButtons = document.querySelectorAll('.accordion-button');
            accordionButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    var targetId = this.getAttribute('data-bs-target');
                    var target = document.querySelector(targetId);
                    var textarea = target.querySelector('textarea');
                    if (textarea) {
                        setTimeout(function() {
                            textarea.focus();
                        }, 300);
                    }
                });
            });
        });
    </script>
</body>
</html>