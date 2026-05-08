<?php
require_once 'db.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$resource_id = $_GET['id'];

// Get resource information
$stmt = $pdo->prepare("SELECT r.*, c.course_code FROM resources r 
                       JOIN courses c ON r.course_id = c.id 
                       WHERE r.id = ?");
$stmt->execute([$resource_id]);
$resource = $stmt->fetch();

if (!$resource) {
    die("Resource not found.");
}

// Check access permissions
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $user_role = getUserRole();
    
    // Admin can download everything
    if ($user_role === 'admin') {
        // Allow download
    }
    // Lecturer can download their own or public resources
    elseif ($user_role === 'lecturer') {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
        $stmt->execute([$resource['course_id'], $user_id]);
        $course = $stmt->fetch();
        
        if (!$course && $resource['access_level'] !== 'public') {
            die("Access denied.");
        }
    }
    // Student can download if enrolled in the course or it's public
    elseif ($user_role === 'student') {
        $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE course_id = ? AND student_id = ?");
        $stmt->execute([$resource['course_id'], $user_id]);
        $enrollment = $stmt->fetch();
        
        if (!$enrollment && $resource['access_level'] !== 'public') {
            die("Access denied.");
        }
    }
} else {
    // Only public resources for non-logged in users
    if ($resource['access_level'] !== 'public') {
        die("Please login to access this resource.");
    }
}

// Update download count
$stmt = $pdo->prepare("UPDATE resources SET download_count = download_count + 1 WHERE id = ?");
$stmt->execute([$resource_id]);

// Serve the file
$file_path = 'uploads/' . $resource['file_name'];

if (file_exists($file_path)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($resource['file_name']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
} else {
    die("File not found.");
}
?>