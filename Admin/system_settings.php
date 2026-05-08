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

// Handle settings update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_general'])) {
        // In a real application, you would update settings in a database table
        $success = "General settings updated successfully!";
    }
    
    if (isset($_POST['update_email'])) {
        // Update email settings
        $success = "Email settings updated successfully!";
    }
    
    if (isset($_POST['update_storage'])) {
        // Update storage settings
        $success = "Storage settings updated successfully!";
    }
    
    if (isset($_POST['update_security'])) {
        // Update security settings
        $success = "Security settings updated successfully!";
    }
    
    if (isset($_POST['clear_cache'])) {
        // Clear system cache
        $success = "System cache cleared successfully!";
    }
    
    if (isset($_POST['run_backup'])) {
        // Run system backup
        $success = "System backup completed successfully!";
    }
}

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'database' => 'MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn(),
    'upload_max' => ini_get('upload_max_filesize'),
    'post_max' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution' => ini_get('max_execution_time')
];

// Get storage information
$upload_dir = '../uploads/';
$storage_used = 0;
$file_count = 0;
if (is_dir($upload_dir)) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir));
    foreach ($files as $file) {
        if ($file->isFile()) {
            $storage_used += $file->getSize();
            $file_count++;
        }
    }
}
$storage_used_mb = round($storage_used / (1024 * 1024), 2);
$storage_limit_mb = 1000; // 1GB limit
$storage_percent = min(($storage_used_mb / $storage_limit_mb) * 100, 100);

// Get system logs (simulated)
$system_logs = [
    ['time' => 'Today 10:30', 'type' => 'info', 'message' => 'System backup completed successfully'],
    ['time' => 'Today 09:15', 'type' => 'warning', 'message' => 'Storage usage exceeds 80%'],
    ['time' => 'Yesterday 22:00', 'type' => 'info', 'message' => 'Nightly maintenance completed'],
    ['time' => 'Yesterday 14:30', 'type' => 'error', 'message' => 'Failed login attempt detected'],
    ['time' => 'Yesterday 10:00', 'type' => 'info', 'message' => 'New user registration: John Doe'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - EduRepository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .settings-card {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .settings-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .settings-body {
            padding: 20px;
        }
        .system-status {
            padding: 15px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
        }
        .system-status.healthy { background: linear-gradient(135deg, #38b000, #2d7d46); }
        .system-status.warning { background: linear-gradient(135deg, #ffbe0b, #fb5607); }
        .system-status.critical { background: linear-gradient(135deg, #e63946, #d90429); }
        .log-entry {
            padding: 10px;
            border-left: 4px solid;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        .log-entry.info { border-left-color: #3a86ff; }
        .log-entry.warning { border-left-color: #ffbe0b; }
        .log-entry.error { border-left-color: #e63946; }
        .log-entry.success { border-left-color: #38b000; }
        .storage-bar {
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        .storage-fill {
            height: 100%;
            background: linear-gradient(135deg, #e63946, #d90429);
        }
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-item:last-child {
            border-bottom: none;
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
                    <a class="nav-link" href="manage_users.php">
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
                    <a class="nav-link active" href="system_settings.php">
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
                        <strong><?php echo $admin['full_name']; ?></strong>
                    </span>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-cog text-danger"></i> System Settings
                    </h1>
                    <p class="text-muted">Configure and manage system settings</p>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#backupModal">
                        <i class="fas fa-database"></i> Backup Now
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="clearSystemCache()">
                        <i class="fas fa-broom"></i> Clear Cache
                    </button>
                </div>
            </div>

            <!-- System Status -->
            <div class="system-status <?php echo $storage_percent > 80 ? 'warning' : 'healthy'; ?>">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">
                            <i class="fas fa-server"></i> System Status: 
                            <span class="text-uppercase"><?php echo $storage_percent > 80 ? 'WARNING' : 'HEALTHY'; ?></span>
                        </h5>
                        <p class="mb-0">
                            <?php if ($storage_percent > 80): ?>
                                <i class="fas fa-exclamation-triangle"></i> 
                                Storage usage is high (<?php echo round($storage_percent, 1); ?>%)
                            <?php else: ?>
                                <i class="fas fa-check-circle"></i> 
                                All systems operational
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <small>Uptime: 99.8%</small>
                        <br>
                        <small>Last updated: <?php echo date('H:i:s'); ?></small>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- General Settings -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0">
                                <i class="fas fa-sliders-h text-danger"></i> General Settings
                            </h5>
                        </div>
                        <div class="settings-body">
                            <form method="POST" action="">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">System Name</label>
                                        <input type="text" class="form-control" value="EduRepository" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">System Version</label>
                                        <input type="text" class="form-control" value="2.1.0" readonly>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Default Timezone</label>
                                        <select class="form-select">
                                            <option selected>UTC</option>
                                            <option>America/New_York</option>
                                            <option>Europe/London</option>
                                            <option>Asia/Tokyo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date Format</label>
                                        <select class="form-select">
                                            <option selected>Y-m-d (2024-01-15)</option>
                                            <option>d/m/Y (15/01/2024)</option>
                                            <option>m/d/Y (01/15/2024)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" value="5" min="1" max="10">
                                        <small class="text-muted">Number of failed login attempts before lockout</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Session Timeout (minutes)</label>
                                        <input type="number" class="form-control" value="30" min="5" max="1440">
                                        <small class="text-muted">User session timeout in minutes</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="maintenanceMode" checked>
                                        <label class="form-check-label" for="maintenanceMode">
                                            Enable Maintenance Mode
                                        </label>
                                    </div>
                                    <small class="text-muted">When enabled, only administrators can access the system</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="userRegistration" checked>
                                        <label class="form-check-label" for="userRegistration">
                                            Allow User Registration
                                        </label>
                                    </div>
                                    <small class="text-muted">Allow new users to register accounts</small>
                                </div>
                                
                                <button type="submit" name="update_general" class="btn btn-danger">
                                    <i class="fas fa-save"></i> Save General Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0">
                                <i class="fas fa-envelope text-danger"></i> Email Settings
                            </h5>
                        </div>
                        <div class="settings-body">
                            <form method="POST" action="">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" value="smtp.gmail.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" value="587">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" value="noreply@edurepository.edu">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" value="********">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Sender Name</label>
                                        <input type="text" class="form-control" value="EduRepository System">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Sender Email</label>
                                        <input type="email" class="form-control" value="noreply@edurepository.edu">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                        <label class="form-check-label" for="emailNotifications">
                                            Enable Email Notifications
                                        </label>
                                    </div>
                                    <small class="text-muted">Send email notifications for system events</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="welcomeEmail" checked>
                                        <label class="form-check-label" for="welcomeEmail">
                                            Send Welcome Emails
                                        </label>
                                    </div>
                                    <small class="text-muted">Send welcome email to new users</small>
                                </div>
                                
                                <button type="submit" name="update_email" class="btn btn-danger">
                                    <i class="fas fa-save"></i> Save Email Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Storage Settings -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0">
                                <i class="fas fa-hdd text-danger"></i> Storage Settings
                            </h5>
                        </div>
                        <div class="settings-body">
                            <form method="POST" action="">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <h5 class="mb-0">Storage Usage</h5>
                                            <small class="text-muted"><?php echo $storage_used_mb; ?> MB of <?php echo $storage_limit_mb; ?> MB used</small>
                                        </div>
                                        <div class="text-end">
                                            <h5 class="mb-0"><?php echo round($storage_percent, 1); ?>%</h5>
                                            <small class="text-muted">Used</small>
                                        </div>
                                    </div>
                                    <div class="storage-bar">
                                        <div class="storage-fill" style="width: <?php echo $storage_percent; ?>%"></div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <?php echo $file_count; ?> files in storage
                                            <?php if ($storage_percent > 80): ?>
                                                • <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Storage almost full</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Max File Upload Size</label>
                                        <select class="form-select">
                                            <option>10 MB</option>
                                            <option selected>50 MB</option>
                                            <option>100 MB</option>
                                            <option>200 MB</option>
                                        </select>
                                        <small class="text-muted">Maximum size for individual file uploads</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Storage Limit</label>
                                        <select class="form-select">
                                            <option>500 MB</option>
                                            <option selected>1 GB</option>
                                            <option>2 GB</option>
                                            <option>5 GB</option>
                                        </select>
                                        <small class="text-muted">Total storage allocation</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Allowed File Types</label>
                                    <div class="border rounded p-3 bg-light">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="pdfFiles" checked>
                                                    <label class="form-check-label" for="pdfFiles">
                                                        PDF Documents (.pdf)
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="docFiles" checked>
                                                    <label class="form-check-label" for="docFiles">
                                                        Word Documents (.doc, .docx)
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="pptFiles" checked>
                                                    <label class="form-check-label" for="pptFiles">
                                                        Presentations (.ppt, .pptx)
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="videoFiles" checked>
                                                    <label class="form-check-label" for="videoFiles">
                                                        Videos (.mp4, .avi, .mov)
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="imageFiles" checked>
                                                    <label class="form-check-label" for="imageFiles">
                                                        Images (.jpg, .png, .gif)
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="otherFiles">
                                                    <label class="form-check-label" for="otherFiles">
                                                        Other Files (.zip, .txt)
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="autoCleanup">
                                        <label class="form-check-label" for="autoCleanup">
                                            Enable Auto Cleanup
                                        </label>
                                    </div>
                                    <small class="text-muted">Automatically delete old unused files</small>
                                </div>
                                
                                <button type="submit" name="update_storage" class="btn btn-danger">
                                    <i class="fas fa-save"></i> Save Storage Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- System Information -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle text-danger"></i> System Information
                            </h5>
                        </div>
                        <div class="settings-body">
                            <div class="info-item">
                                <small class="text-muted d-block">PHP Version</small>
                                <strong><?php echo $system_info['php_version']; ?></strong>
                            </div>
                            <div class="info-item">
                                <small class="text-muted d-block">Server Software</small>
                                <strong><?php echo $system_info['server_software']; ?></strong>
                            </div>
                            <div class="info-item">
                                <small class="text-muted d-block">Database</small>
                                <strong><?php echo $system_info['database']; ?></strong>
                            </div>
                            <div class="info-item">
                                <small class="text-muted d-block">Upload Max Filesize</small>
                                <strong><?php echo $system_info['upload_max']; ?></strong>
                            </div>
                            <div class="info-item">
                                <small class="text-muted d-block">Post Max Size</small>
                                <strong><?php echo $system_info['post_max']; ?></strong>
                            </div>
                            <div class="info-item">
                                <small class="text-muted d-block">Memory Limit</small>
                                <strong><?php echo $system_info['memory_limit']; ?></strong>
                            </div>
                            <div class="info-item">
                                <small class="text-muted d-block">Max Execution Time</small>
                                <strong><?php echo $system_info['max_execution']; ?> seconds</strong>
                            </div>
                            <div class="info-item">
                                <small class="text-muted d-block">System Uptime</small>
                                <strong>99.8% (Last 30 days)</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0">
                                <i class="fas fa-shield-alt text-danger"></i> Security Settings
                            </h5>
                        </div>
                        <div class="settings-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="forceSSL" checked>
                                        <label class="form-check-label" for="forceSSL">
                                            Force SSL/HTTPS
                                        </label>
                                    </div>
                                    <small class="text-muted">Redirect all traffic to HTTPS</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="passwordPolicy" checked>
                                        <label class="form-check-label" for="passwordPolicy">
                                            Enforce Password Policy
                                        </label>
                                    </div>
                                    <small class="text-muted">Require strong passwords</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="twoFactorAuth">
                                        <label class="form-check-label" for="twoFactorAuth">
                                            Enable Two-Factor Authentication
                                        </label>
                                    </div>
                                    <small class="text-muted">Require 2FA for admin accounts</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="ipWhitelist">
                                        <label class="form-check-label" for="ipWhitelist">
                                            Enable IP Whitelist
                                        </label>
                                    </div>
                                    <small class="text-muted">Restrict access to specific IP addresses</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="loginLogging" checked>
                                        <label class="form-check-label" for="loginLogging">
                                            Enable Login Logging
                                        </label>
                                    </div>
                                    <small class="text-muted">Log all login attempts</small>
                                </div>
                                
                                <button type="submit" name="update_security" class="btn btn-danger w-100">
                                    <i class="fas fa-save"></i> Save Security Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- System Logs -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-list text-danger"></i> Recent System Logs
                            </h5>
                        </div>
                        <div class="settings-body">
                            <?php foreach ($system_logs as $log): ?>
                                <div class="log-entry <?php echo $log['type']; ?>">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted"><?php echo $log['time']; ?></small>
                                        <span class="badge bg-<?php echo $log['type'] == 'info' ? 'primary' : ($log['type'] == 'warning' ? 'warning' : 'danger'); ?>">
                                            <?php echo strtoupper($log['type']); ?>
                                        </span>
                                    </div>
                                    <p class="mb-0 small"><?php echo $log['message']; ?></p>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="text-center mt-3">
                                <a href="#" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-list"></i> View All Logs
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0">
                                <i class="fas fa-bolt text-danger"></i> Quick Actions
                            </h5>
                        </div>
                        <div class="settings-body">
                            <div class="d-grid gap-2">
                                <form method="POST" action="" class="d-grid">
                                    <button type="submit" name="clear_cache" class="btn btn-outline-warning">
                                        <i class="fas fa-broom"></i> Clear System Cache
                                    </button>
                                </form>
                                <form method="POST" action="" class="d-grid">
                                    <button type="submit" name="run_backup" class="btn btn-outline-success">
                                        <i class="fas fa-database"></i> Run System Backup
                                    </button>
                                </form>
                                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#updateModal">
                                    <i class="fas fa-sync"></i> Check for Updates
                                </button>
                                <a href="../logout.php?all=1" class="btn btn-outline-danger" 
                                   onclick="return confirm('Log out all users? This will invalidate all active sessions.')">
                                    <i class="fas fa-sign-out-alt"></i> Logout All Users
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup Modal -->
    <div class="modal fade" id="backupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-database"></i> System Backup
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Creating a system backup will:</p>
                    <ul>
                        <li>Backup database to SQL file</li>
                        <li>Compress and backup uploaded files</li>
                        <li>Create backup logs</li>
                        <li>Store backup in secure location</li>
                    </ul>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This process may take several minutes depending on system size.
                    </div>
                    <form method="POST" action="" id="backupForm">
                        <div class="mb-3">
                            <label class="form-label">Backup Type</label>
                            <select class="form-select" name="backup_type">
                                <option value="full">Full Backup (Database + Files)</option>
                                <option value="database">Database Only</option>
                                <option value="files">Files Only</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Backup Description</label>
                            <input type="text" class="form-control" name="backup_desc" placeholder="Optional description...">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" onclick="runBackup()">
                        <i class="fas fa-play"></i> Start Backup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-sync"></i> System Updates
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5>System is up to date</h5>
                        <p class="text-muted">Current version: 2.1.0</p>
                        <p class="text-muted">Latest version: 2.1.0</p>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Last checked:</strong> Today at 10:30 AM
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="checkForUpdates()">
                        <i class="fas fa-redo"></i> Check Again
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearSystemCache() {
            if (confirm('Are you sure you want to clear the system cache?')) {
                // In a real application, you would make an AJAX call to clear cache
                alert('System cache cleared successfully!');
                location.reload();
            }
        }

        function runBackup() {
            const form = document.getElementById('backupForm');
            const backupType = form.querySelector('[name="backup_type"]').value;
            const backupDesc = form.querySelector('[name="backup_desc"]').value;
            
            // Show loading state
            const submitBtn = document.querySelector('#backupModal .btn-danger');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Backing up...';
            submitBtn.disabled = true;
            
            // Simulate backup process
            setTimeout(() => {
                alert(`Backup completed successfully!\nType: ${backupType}\nDescription: ${backupDesc || 'None'}`);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                bootstrap.Modal.getInstance(document.getElementById('backupModal')).hide();
            }, 2000);
        }

        function checkForUpdates() {
            const checkBtn = document.querySelector('#updateModal .btn-primary');
            const originalText = checkBtn.innerHTML;
            checkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            
            setTimeout(() => {
                alert('System is up to date!');
                checkBtn.innerHTML = originalText;
            }, 1500);
        }

        // Auto-save form data
        document.querySelectorAll('form').forEach(form => {
            const formId = form.id || 'form_' + Math.random().toString(36).substr(2, 9);
            form.id = formId;
            
            // Load saved data
            const savedData = localStorage.getItem(formId);
            if (savedData) {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(key => {
                    const element = form.querySelector(`[name="${key}"]`);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = data[key];
                        } else {
                            element.value = data[key];
                        }
                    }
                });
            }
            
            // Save on change
            form.addEventListener('change', function() {
                const formData = {};
                new FormData(form).forEach((value, key) => {
                    formData[key] = value;
                });
                localStorage.setItem(formId, JSON.stringify(formData));
            });
        });
    </script>
</body>
</html>