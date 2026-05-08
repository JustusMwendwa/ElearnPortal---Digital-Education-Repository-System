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
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$department_filter = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($role_filter !== 'all') {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if ($department_filter) {
    $query .= " AND department = ?";
    $params[] = $department_filter;
}

if ($search) {
    $query .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get unique departments
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department")->fetchAll();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        // Prevent deleting yourself
        if ($user_id != $admin_id) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = "User deleted successfully!";
            header('Location: manage_users.php?success=User deleted successfully');
            exit();
        } else {
            $error = "You cannot delete your own account!";
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $user_id = $_POST['user_id'];
        
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        $new_status = $user['status'] == 'active' ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        header('Location: manage_users.php?success=User status updated');
        exit();
    }
    
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $role = $_POST['role'];
        $department = $_POST['department'];
        $course = $_POST['course'] ?? null;
        $year_of_study = $_POST['year_of_study'] ?? null;
        
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $exists = $stmt->fetchColumn();
        
        if ($exists > 0) {
            $error = "Username or email already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, department, course, year_of_study) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $email, $full_name, $role, $department, $course, $year_of_study]);
            $success = "User added successfully!";
            header('Location: manage_users.php?success=User added successfully');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - EduRepository</title>
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
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3a86ff, #8338ec);
            color: white;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .badge-admin { background: #e63946; }
        .badge-lecturer { background: #ffbe0b; color: black; }
        .badge-student { background: #38b000; }
        .user-card {
            transition: transform 0.3s;
            border-left: 4px solid;
        }
        .user-card.admin { border-left-color: #e63946; }
        .user-card.lecturer { border-left-color: #ffbe0b; }
        .user-card.student { border-left-color: #38b000; }
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
                    <a class="nav-link active" href="manage_users.php">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_courses.php">
                        <i class="fas fa-chalkboard"></i> Manage Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_resources.php">
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
                        <i class="fas fa-users text-danger"></i> User Management
                    </h1>
                    <p class="text-muted">Manage system users, roles, and permissions</p>
                </div>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
            </div>

            <!-- User Statistics -->
            <div class="row mb-4">
                <?php
                $total_users_count = count($users);
                $students_count = array_reduce($users, fn($carry, $u) => $carry + ($u['role'] == 'student' ? 1 : 0), 0);
                $lecturers_count = array_reduce($users, fn($carry, $u) => $carry + ($u['role'] == 'lecturer' ? 1 : 0), 0);
                $admins_count = array_reduce($users, fn($carry, $u) => $carry + ($u['role'] == 'admin' ? 1 : 0), 0);
                $active_count = array_reduce($users, fn($carry, $u) => $carry + ($u['status'] == 'active' ? 1 : 0), 0);
                ?>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-primary">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-0"><?php echo $total_users_count; ?></h3>
                            <small class="text-muted">Total Users</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-success">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-0"><?php echo $students_count; ?></h3>
                            <small class="text-muted">Students</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-warning">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-0"><?php echo $lecturers_count; ?></h3>
                            <small class="text-muted">Lecturers</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-danger">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-0"><?php echo $admins_count; ?></h3>
                            <small class="text-muted">Admins</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-info">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-0"><?php echo $active_count; ?></h3>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-secondary">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-0"><?php echo $total_users_count - $active_count; ?></h3>
                            <small class="text-muted">Inactive</small>
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
                        <div class="col-md-2">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role">
                                <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="lecturer" <?php echo $role_filter == 'lecturer' ? 'selected' : ''; ?>>Lecturer</option>
                                <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department']; ?>" 
                                            <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo $dept['department']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, username, or email...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-danger w-100 me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="manage_users.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-table"></i> Users List (<?php echo count($users); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                            <h5>No users found</h5>
                            <p class="text-muted">No users match your filter criteria</p>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-user-plus"></i> Add First User
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="user-card <?php echo $user['role']; ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                        <?php if ($user['course']): ?>
                                                            <small class="d-block text-muted"><?php echo $user['course']; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>@<?php echo $user['username']; ?></td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role']; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['department']): ?>
                                                    <span class="badge bg-light text-dark"><?php echo $user['department']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="toggle_status" 
                                                                class="btn btn-outline-<?php echo $user['status'] == 'active' ? 'danger' : 'success'; ?>"
                                                                title="<?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                            <i class="fas fa-<?php echo $user['status'] == 'active' ? 'times' : 'check'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <?php if ($user['id'] != $admin_id): ?>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" name="delete_user" 
                                                                    class="btn btn-outline-danger"
                                                                    onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')"
                                                                    title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
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

            <!-- User Cards View (Hidden by default) -->
            <div class="row d-none" id="usersGridView">
                <?php foreach ($users as $user): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                        <div class="card user-card <?php echo $user['role']; ?> h-100">
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="user-avatar mx-auto" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                    <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                                    <p class="text-muted mb-1">@<?php echo $user['username']; ?></p>
                                    <div class="mb-3">
                                        <span class="badge badge-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">
                                        <i class="fas fa-envelope"></i> <?php echo $user['email']; ?>
                                    </small>
                                    <?php if ($user['department']): ?>
                                        <small class="text-muted d-block mb-1">
                                            <i class="fas fa-building"></i> <?php echo $user['department']; ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($user['course']): ?>
                                        <small class="text-muted d-block mb-1">
                                            <i class="fas fa-graduation-cap"></i> <?php echo $user['course']; ?>
                                        </small>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="toggle_status" 
                                                class="btn btn-sm btn-outline-<?php echo $user['status'] == 'active' ? 'danger' : 'success'; ?>">
                                            <i class="fas fa-<?php echo $user['status'] == 'active' ? 'times' : 'check'; ?>"></i>
                                        </button>
                                    </form>
                                    <?php if ($user['id'] != $admin_id): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" 
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Delete this user?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                                <small class="text-muted">Used for login</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" id="roleSelect" required>
                                    <option value="">Select Role</option>
                                    <option value="student">Student</option>
                                    <option value="lecturer">Lecturer</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department *</label>
                                <input type="text" class="form-control" name="department" required>
                            </div>
                        </div>
                        
                        <div id="studentFields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Course/Program</label>
                                    <input type="text" class="form-control" name="course">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Year of Study</label>
                                    <select class="form-select" name="year_of_study">
                                        <option value="">Select Year</option>
                                        <option value="1">Year 1</option>
                                        <option value="2">Year 2</option>
                                        <option value="3">Year 3</option>
                                        <option value="4">Year 4</option>
                                        <option value="5">Year 5</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-danger">
                            <i class="fas fa-save"></i> Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#usersTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [7] } // Disable sorting for actions column
                ]
            });
        });

        // Show/hide student fields based on role selection
        document.getElementById('roleSelect').addEventListener('change', function() {
            const studentFields = document.getElementById('studentFields');
            if (this.value === 'student') {
                studentFields.style.display = 'block';
            } else {
                studentFields.style.display = 'none';
            }
        });

        // View switcher
        function toggleView(view) {
            const tableView = document.getElementById('usersTable').parentElement.parentElement;
            const gridView = document.getElementById('usersGridView');
            
            if (view === 'grid') {
                tableView.classList.add('d-none');
                gridView.classList.remove('d-none');
            } else {
                tableView.classList.remove('d-none');
                gridView.classList.add('d-none');
            }
        }
    </script>
</body>
</html>