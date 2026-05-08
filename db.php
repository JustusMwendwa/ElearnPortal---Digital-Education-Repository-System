<?php
// db.php - Database Connection
$host = 'localhost';
$dbname = 'edu_repository';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check user role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Add this function to db.php

// Function to check if admin registration is allowed
function isAdminRegistrationAllowed() {
    // In production, this should check for invitation codes or other validation
    return false; // Disable admin self-registration by default
}
// Function to redirect based on role
function redirectBasedOnRole() {
    if (isLoggedIn()) {
        $role = getUserRole();
        switch($role) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'lecturer':
                header('Location: lecturer/dashboard.php');
                break;
            case 'student':
                header('Location: student/dashboard.php');
                break;
        }
        exit();
    }
}
?>