-- Database: edu_repository
CREATE DATABASE IF NOT EXISTS edu_repository;
USE edu_repository;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'lecturer', 'admin') NOT NULL,
    department VARCHAR(100),
    course VARCHAR(100),
    year_of_study INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Courses table
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(200) NOT NULL,
    department VARCHAR(100),
    lecturer_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Resources table
CREATE TABLE resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_name VARCHAR(255),
    file_type ENUM('pdf', 'doc', 'docx', 'ppt', 'pptx', 'video', 'image', 'other') NOT NULL,
    file_size INT,
    course_id INT,
    uploaded_by INT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    access_level ENUM('public', 'course_only', 'private') DEFAULT 'course_only',
    download_count INT DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Student enrollments
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    course_id INT,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id)
);

-- Questions/Queries from students
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    lecturer_id INT,
    course_id INT,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    attachment VARCHAR(255),
    asked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'answered', 'closed') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Answers to questions
CREATE TABLE answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT,
    lecturer_id INT,
    answer TEXT NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default admin (password: admin123)
INSERT INTO users (username, password, email, full_name, role, department) 
VALUES ('admin', '$2y$10$YourHashedPasswordHere', 'admin@edurepository.edu', 'System Administrator', 'admin', 'Administration');

-- Insert sample lecturer (password: lecturer123)
INSERT INTO users (username, password, email, full_name, role, department) 
VALUES ('lecturer1', '$2y$10$YourHashedPasswordHere', 'lecturer@edurepository.edu', 'Dr. John Smith', 'lecturer', 'Computer Science');

-- Insert sample student (password: student123)
INSERT INTO users (username, password, email, full_name, role, department, course, year_of_study) 
VALUES ('student1', '$2y$10$YourHashedPasswordHere', 'student@edurepository.edu', 'Jane Doe', 'student', 'Computer Science', 'BSc Computer Science', 2);