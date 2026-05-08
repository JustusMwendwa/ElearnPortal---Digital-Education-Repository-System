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

// Get course ID from URL
$course_id = $_GET['id'] ?? 0;

// Verify lecturer has access to this course
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
$stmt->execute([$course_id, $lecturer_id]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit();
}

// Get enrolled students for this course
$query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM questions WHERE student_id = u.id AND course_id = ?) as total_questions,
                 (SELECT COUNT(*) FROM questions WHERE student_id = u.id AND course_id = ? AND status = 'answered') as answered_questions,
                 (SELECT MAX(asked_at) FROM questions WHERE student_id = u.id AND course_id = ?) as last_question_date
          FROM users u 
          JOIN enrollments e ON u.id = e.student_id 
          WHERE e.course_id = ? 
          ORDER BY u.full_name";
$stmt = $pdo->prepare($query);
$stmt->execute([$course_id, $course_id, $course_id, $course_id]);
$students = $stmt->fetchAll();

// Get course statistics
$total_students = count($students);
$total_questions = array_sum(array_column($students, 'total_questions'));
$answered_questions = array_sum(array_column($students, 'answered_questions'));
$response_rate = $total_questions > 0 ? round(($answered_questions / $total_questions) * 100, 1) : 0;

// Get recent questions for this course
$stmt = $pdo->prepare("SELECT q.*, u.full_name as student_name 
                      FROM questions q 
                      JOIN users u ON q.student_id = u.id 
                      WHERE q.course_id = ? 
                      ORDER BY q.asked_at DESC 
                      LIMIT 10");
$stmt->execute([$course_id]);
$recent_questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Students - EduRepository</title>
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
        .course-header {
            background: linear-gradient(135deg, #ffbe0b, #fb5607);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .student-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #38b000, #2d7d46);
            color: white;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .performance-badge {
            font-size: 0.7rem;
            padding: 3px 6px;
        }
        .engagement-bar {
            height: 8px;
            width: 100%;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .engagement-fill {
            height: 100%;
            background: #38b000;
            transition: width 0.3s;
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
                    <a class="nav-link active" href="students.php">
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
                <a href="courses.php" class="btn btn-warning">
                    <i class="fas fa-arrow-left"></i> Back to Courses
                </a>
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3">
                        <strong><?php echo $lecturer['full_name']; ?></strong>
                    </span>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <!-- Course Header -->
            <div class="course-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="h3 mb-2"><?php echo $course['course_code']; ?></h1>
                        <h2 class="h5 mb-4"><?php echo htmlspecialchars($course['course_name']); ?></h2>
                        <p class="mb-0"><?php echo $course['department']; ?> • <?php echo $total_students; ?> enrolled students</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <a href="manage_resources.php?course=<?php echo $course_id; ?>" class="btn btn-light">
                                <i class="fas fa-book"></i> Resources
                            </a>
                            <a href="questions.php?course=<?php echo $course_id; ?>" class="btn btn-light">
                                <i class="fas fa-question-circle"></i> Questions
                            </a>
                            <a href="upload.php" class="btn btn-light">
                                <i class="fas fa-upload"></i> Upload
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $total_students; ?></h2>
                            <p class="mb-0">Enrolled Students</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $total_questions; ?></h2>
                            <p class="mb-0">Total Questions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $answered_questions; ?></h2>
                            <p class="mb-0">Answered Questions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?php echo $response_rate; ?>%</h2>
                            <p class="mb-0">Response Rate</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Students List -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-users"></i> Enrolled Students (<?php echo $total_students; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($students)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                    <h5>No students enrolled</h5>
                                    <p class="text-muted">Students will appear here once they enroll in the course</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="studentsTable">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Year</th>
                                                <th>Department</th>
                                                <th>Questions</th>
                                                <th>Engagement</th>
                                                <th>Last Activity</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student): 
                                                $engagement_rate = $student['total_questions'] > 0 
                                                    ? round(($student['answered_questions'] / $student['total_questions']) * 100, 0) 
                                                    : 0;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="student-avatar me-3">
                                                                <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($student['full_name']); ?></strong><br>
                                                                <small class="text-muted">@<?php echo $student['username']; ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">Year <?php echo $student['year_of_study']; ?></span>
                                                    </td>
                                                    <td><?php echo $student['department']; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span class="badge bg-primary me-1"><?php echo $student['total_questions']; ?></span>
                                                            <?php if ($student['total_questions'] > 0): ?>
                                                                <span class="badge bg-success"><?php echo $student['answered_questions']; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="engagement-bar me-2 flex-grow-1">
                                                                <div class="engagement-fill" style="width: <?php echo $engagement_rate; ?>%"></div>
                                                            </div>
                                                            <small><?php echo $engagement_rate; ?>%</small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($student['last_question_date']): ?>
                                                            <small><?php echo date('M d', strtotime($student['last_question_date'])); ?></small>
                                                        <?php else: ?>
                                                            <small class="text-muted">No activity</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="mailto:<?php echo $student['email']; ?>" 
                                                               class="btn btn-outline-primary" title="Send Email">
                                                                <i class="fas fa-envelope"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-warning" 
                                                                    data-bs-toggle="modal" data-bs-target="#studentQuestionsModal<?php echo $student['id']; ?>"
                                                                    title="View Questions">
                                                                <i class="fas fa-question-circle"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>

                                                <!-- Student Questions Modal -->
                                                <div class="modal fade" id="studentQuestionsModal<?php echo $student['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-warning text-white">
                                                                <h5 class="modal-title">Questions from <?php echo htmlspecialchars($student['full_name']); ?></h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <?php
                                                                // Get student's questions for this course
                                                                $stmt2 = $pdo->prepare("SELECT q.*, c.course_code 
                                                                                       FROM questions q 
                                                                                       JOIN courses c ON q.course_id = c.id 
                                                                                       WHERE q.student_id = ? AND q.course_id = ? 
                                                                                       ORDER BY q.asked_at DESC");
                                                                $stmt2->execute([$student['id'], $course_id]);
                                                                $student_questions = $stmt2->fetchAll();
                                                                ?>
                                                                
                                                                <?php if (empty($student_questions)): ?>
                                                                    <div class="text-center py-4">
                                                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                                                        <p>No questions asked in this course</p>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="accordion" id="studentQuestionsAccordion<?php echo $student['id']; ?>">
                                                                        <?php foreach ($student_questions as $index => $question): ?>
                                                                            <div class="accordion-item mb-2">
                                                                                <h2 class="accordion-header">
                                                                                    <button class="accordion-button collapsed" type="button" 
                                                                                            data-bs-toggle="collapse" 
                                                                                            data-bs-target="#collapseQ<?php echo $question['id']; ?>">
                                                                                        <div class="d-flex w-100 justify-content-between">
                                                                                            <div>
                                                                                                <strong><?php echo htmlspecialchars($question['subject']); ?></strong>
                                                                                                <br>
                                                                                                <small class="text-muted">
                                                                                                    <?php echo date('M d, Y H:i', strtotime($question['asked_at'])); ?>
                                                                                                </small>
                                                                                            </div>
                                                                                            <span class="badge bg-<?php echo $question['status'] == 'pending' ? 'warning' : 'success'; ?>">
                                                                                                <?php echo ucfirst($question['status']); ?>
                                                                                            </span>
                                                                                        </div>
                                                                                    </button>
                                                                                </h2>
                                                                                <div id="collapseQ<?php echo $question['id']; ?>" 
                                                                                     class="accordion-collapse collapse" 
                                                                                     data-bs-parent="#studentQuestionsAccordion<?php echo $student['id']; ?>">
                                                                                    <div class="accordion-body">
                                                                                        <p><?php echo nl2br(htmlspecialchars($question['message'])); ?></p>
                                                                                        
                                                                                        <?php
                                                                                        // Get answers for this question
                                                                                        $stmt3 = $pdo->prepare("SELECT a.*, u.full_name 
                                                                                                               FROM answers a 
                                                                                                               JOIN users u ON a.lecturer_id = u.id 
                                                                                                               WHERE a.question_id = ? 
                                                                                                               ORDER BY a.answered_at DESC");
                                                                                        $stmt3->execute([$question['id']]);
                                                                                        $answers = $stmt3->fetchAll();
                                                                                        ?>
                                                                                        
                                                                                        <?php if (!empty($answers)): ?>
                                                                                            <div class="mt-3">
                                                                                                <h6>Answers:</h6>
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
                                                                                        <?php elseif ($question['status'] == 'pending'): ?>
                                                                                            <div class="alert alert-warning mt-3">
                                                                                                <i class="fas fa-clock"></i> Waiting for your response
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <a href="mailto:<?php echo $student['email']; ?>" class="btn btn-warning">
                                                                    <i class="fas fa-envelope"></i> Email Student
                                                                </a>
                                                            </div>
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

                <!-- Recent Questions & Course Info -->
                <div class="col-lg-4">
                    <!-- Recent Questions -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-question-circle"></i> Recent Questions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_questions)): ?>
                                <p class="text-muted">No recent questions</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_questions as $question): ?>
                                        <a href="questions.php" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <strong class="mb-1"><?php echo htmlspecialchars($question['subject']); ?></strong>
                                                <small><?php echo date('M d', strtotime($question['asked_at'])); ?></small>
                                            </div>
                                            <small class="text-muted"><?php echo $question['student_name']; ?></small>
                                            <div class="mt-2">
                                                <span class="badge bg-<?php echo $question['status'] == 'pending' ? 'warning' : 'success'; ?>">
                                                    <?php echo ucfirst($question['status']); ?>
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="questions.php?course=<?php echo $course_id; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-list"></i> View All Questions
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Course Information -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Course Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th>Course Code:</th>
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
                                    <th>Students:</th>
                                    <td><?php echo $total_students; ?> enrolled</td>
                                </tr>
                                <tr>
                                    <th>Created:</th>
                                    <td><?php echo date('F d, Y', strtotime($course['created_at'])); ?></td>
                                </tr>
                            </table>
                            
                            <h6 class="mt-4">Quick Actions</h6>
                            <div class="d-grid gap-2">
                                <a href="manage_resources.php?course=<?php echo $course_id; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-book"></i> Manage Resources
                                </a>
                                <a href="questions.php?course=<?php echo $course_id; ?>" class="btn btn-outline-warning">
                                    <i class="fas fa-question-circle"></i> View Questions
                                </a>
                                <a href="upload.php" class="btn btn-outline-success">
                                    <i class="fas fa-upload"></i> Upload Material
                                </a>
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
        $(document).ready(function() {
            $('#studentsTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [6] } // Disable sorting for actions column
                ]
            });
        });
    </script>
</body>
</html>