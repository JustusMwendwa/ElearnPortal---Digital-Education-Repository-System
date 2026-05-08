<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Education Repository System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3a86ff;
            --secondary-color: #8338ec;
            --success-color: #38b000;
            --warning-color: #ffbe0b;
            --danger-color: #e63946;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 10px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: #f5f7fb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        a {
            text-decoration: none;
            color: var(--primary-color);
            transition: var(--transition);
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-icon {
            font-size: 2.2rem;
            background-color: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-text h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .logo-text p {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 30px;
            transition: var(--transition);
        }
        
        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .auth-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background-color: white;
            color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #f0f0f0;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline {
            background-color: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        /* Hero Section */
        .hero {
            padding: 80px 0;
            background-color: white;
            border-bottom: 1px solid #eaeaea;
        }
        
        .hero-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }
        
        .hero-text {
            flex: 1;
        }
        
        .hero-text h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--dark-color);
            line-height: 1.2;
        }
        
        .hero-text p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            color: var(--gray-color);
            max-width: 600px;
        }
        
        .hero-stats {
            display: flex;
            gap: 40px;
            margin-top: 40px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .hero-image {
            flex: 1;
            text-align: center;
        }
        
        .hero-image img {
            max-width: 100%;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        /* Features Section */
        .features {
            padding: 80px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h2 {
            font-size: 2.2rem;
            color: var(--dark-color);
            margin-bottom: 15px;
        }
        
        .section-title p {
            color: var(--gray-color);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            text-align: center;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 20px;
        }
        
        .feature-card h3 {
            font-size: 1.4rem;
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        .feature-card p {
            color: var(--gray-color);
            margin-bottom: 20px;
        }
        
        /* How It Works Section */
        .how-it-works {
            padding: 80px 0;
            background-color: white;
        }
        
        .steps {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 50px;
            flex-wrap: wrap;
        }
        
        .step {
            text-align: center;
            max-width: 250px;
            position: relative;
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 20px;
            position: relative;
            z-index: 2;
        }
        
        .step h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        .step p {
            color: var(--gray-color);
        }
        
        /* Resources Preview */
        .resources-preview {
            padding: 80px 0;
        }
        
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }
        
        .resource-card {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .resource-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .resource-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background-color: rgba(58, 134, 255, 0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .resource-info h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .resource-type {
            font-size: 0.85rem;
            color: var(--gray-color);
        }
        
        .resource-body {
            padding: 20px;
        }
        
        .resource-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .resource-desc {
            font-size: 0.95rem;
            color: var(--dark-color);
            margin-bottom: 20px;
        }
        
        .resource-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 0.85rem;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 60px 0 30px;
            margin-top: auto;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-column h3 {
            font-size: 1.3rem;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-column h3:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: #bbb;
            transition: var(--transition);
        }
        
        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #333;
            color: #aaa;
            font-size: 0.9rem;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }
            
            .hero-text h2 {
                font-size: 2.2rem;
            }
            
            .steps {
                gap: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background-color: var(--primary-color);
                flex-direction: column;
                padding: 20px;
                box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
                z-index: 100;
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .auth-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .hero-stats {
                justify-content: center;
                flex-wrap: wrap;
                gap: 30px;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .section-title h2 {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .hero-text h2 {
                font-size: 1.8rem;
            }
            
            .hero {
                padding: 60px 0;
            }
            
            .features, .how-it-works, .resources-preview {
                padding: 60px 0;
            }
            
            .features-grid, .resources-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="logo-text">
                        <h1>EduRepository</h1>
                        <p>Digital Education Repository System</p>
                    </div>
                </div>
                
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                
                <nav class="nav-links" id="navLinks">
                    <a href="#" class="active"><i class="fas fa-home"></i> Home</a>
                    <a href="student/dashboard.php"><i class="fas fa-book"></i> Resources</a>
                    <a href="student/dashboard.php"><i class="fas fa-search"></i> Search</a>
                                        <a href="#"><i class="fas fa-info-circle"></i> About</a>
                    <a href="#"><i class="fas fa-envelope"></i> Contact</a>
                    
                    <div class="auth-buttons">
                        <a href="login.php" class="btn btn-outline"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</a>
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h2>Centralized Platform for Academic Resources</h2>
                    <p>A secure, user-friendly digital repository for educational materials. Access lecture notes, past papers, research materials, and multimedia content all in one place. Streamlining learning and collaboration for students and lecturers.</p>
                    <a href="courses.php" class="btn btn-primary"><i class="fas fa-search"></i> Explore Resources</a>
                    <a href="#" class="btn btn-outline" style="color: var(--primary-color); border-color: var(--primary-color); margin-left: 15px;"><i class="fas fa-upload"></i>  </a>
                    
                    <div class="hero-stats">
                        <div class="stat">
                            <div class="stat-value">1,250+</div>
                            <div class="stat-label">Resources</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value">350+</div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value">45+</div>
                            <div class="stat-label">Courses</div>
                        </div>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="https://images.unsplash.com/photo-1481627834876-b7833e8f5570?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Digital Education Repository">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-title">
                <h2>Key Features</h2>
                <p>Our platform provides all the tools needed for effective digital learning and collaboration</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h3>Centralized Repository</h3>
                    <p>All educational resources in one secure location, eliminating scattered platforms and improving accessibility.</p>
                </div>
                
                             <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Advanced Search</h3>
                    <p>Powerful search functionality to quickly find resources by category, course, or keywords.</p>
                </div>
                                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Usage Analytics</h3>
                    <p>Track resource downloads, user engagement, and system performance metrics.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-title">
                <h2>How It Works</h2>
                <p>Three simple steps to access and share educational resources</p>
            </div>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Register & Login</h3>
                    <p>Create an account based on your role (student, lecturer, or admin) and login to the system.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Search</h3>
                    <p>search the repository for materials you need.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Download & Collaborate</h3>
                    <p>Download resources for your studies or share your own materials with the community.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Resources Preview -->
    <section class="resources-preview">
        <div class="container">
            <div class="section-title">
                <h2>Recently Added Resources</h2>
                <p>Explore the latest educational materials added to our repository</p>
            </div>
            
            <div class="resources-grid">
                <div class="resource-card">
                    <div class="resource-header">
                        <div class="resource-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="resource-info">
                            <h4>Data Structures Notes</h4>
                            <div class="resource-type">Lecture Notes</div>
                        </div>
                    </div>
                    <div class="resource-body">
                        <div class="resource-meta">
                            <span><i class="far fa-calendar"></i> 2 days ago</span>
                            <span><i class="fas fa-download"></i> 45 downloads</span>
                        </div>
                        <p class="resource-desc">Comprehensive notes on data structures and algorithms for Computer Science students.</p>
                        <div class="resource-actions">
                            <a href="#" class="btn btn-primary btn-small"><i class="fas fa-eye"></i> Preview</a>
                            <a href="#" class="btn btn-outline btn-small" style="color: var(--primary-color); border-color: var(--primary-color);"><i class="fas fa-download"></i> Download</a>
                        </div>
                    </div>
                </div>
                
                <div class="resource-card">
                    <div class="resource-header">
                        <div class="resource-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <div class="resource-info">
                            <h4>Calculus Tutorial</h4>
                            <div class="resource-type">Video Lecture</div>
                        </div>
                    </div>
                    <div class="resource-body">
                        <div class="resource-meta">
                            <span><i class="far fa-calendar"></i> 1 week ago</span>
                            <span><i class="fas fa-download"></i> 89 downloads</span>
                        </div>
                        <p class="resource-desc">Step-by-step video tutorial on differential calculus for engineering students.</p>
                        <div class="resource-actions">
                            <a href="#" class="btn btn-primary btn-small"><i class="fas fa-eye"></i> Preview</a>
                            <a href="#" class="btn btn-outline btn-small" style="color: var(--primary-color); border-color: var(--primary-color);"><i class="fas fa-download"></i> Download</a>
                        </div>
                    </div>
                </div>
                
                <div class="resource-card">
                    <div class="resource-header">
                        <div class="resource-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="resource-info">
                            <h4>2022 Exam Papers</h4>
                            <div class="resource-type">Past Papers</div>
                        </div>
                    </div>
                    <div class="resource-body">
                        <div class="resource-meta">
                            <span><i class="far fa-calendar"></i> 3 weeks ago</span>
                            <span><i class="fas fa-download"></i> 120 downloads</span>
                        </div>
                        <p class="resource-desc">Collection of past examination papers for Business Administration courses.</p>
                        <div class="resource-actions">
                            <a href="#" class="btn btn-primary btn-small"><i class="fas fa-eye"></i> Preview</a>
                            <a href="#" class="btn btn-outline btn-small" style="color: var(--primary-color); border-color: var(--primary-color);"><i class="fas fa-download"></i> Download</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 40px;">
                <a href="courses.php" class="btn btn-primary"><i class="fas fa-book-open"></i> Browse All Resources</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>EduRepository</h3>
                    <p>A centralized platform for storing and sharing educational resources. Promoting e-learning and knowledge accessibility for students and lecturers.</p>
                    <div style="margin-top: 20px;">
                        <a href="#" style="margin-right: 15px; font-size: 1.2rem;"><i class="fab fa-facebook"></i></a>
                        <a href="#" style="margin-right: 15px; font-size: 1.2rem;"><i class="fab fa-twitter"></i></a>
                        <a href="#" style="margin-right: 15px; font-size: 1.2rem;"><i class="fab fa-linkedin"></i></a>
                        <a href="#" style="font-size: 1.2rem;"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Resources</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Upload</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Search</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> About Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Resource Categories</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Lecture Notes</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Past Papers</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Research Materials</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Video Lectures</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Presentation Slides</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt"></i> University Campus, Digital Education Dept.</li>
                        <li><i class="fas fa-phone"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-envelope"></i> info@edurepository.edu</li>
                        <li><i class="fas fa-clock"></i> Mon - Fri: 9:00 AM - 6:00 PM</li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; 2023 Digital Education Repository System. All rights reserved.</p>
                <p>Developed using HTML, CSS, JavaScript, PHP & MySQL</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navLinks = document.getElementById('navLinks');
        
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            
            // Change icon based on menu state
            const icon = mobileMenuBtn.querySelector('i');
            if (navLinks.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
        
        // Close mobile menu when clicking a link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
                const icon = mobileMenuBtn.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            });
        });
        
        // Simple animation on scroll
        window.addEventListener('scroll', () => {
            const elements = document.querySelectorAll('.feature-card, .step, .resource-card');
            
            elements.forEach(element => {
                const elementPosition = element.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.2;
                
                if (elementPosition < screenPosition) {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }
            });
        });
        
        // Initialize animation states
        document.querySelectorAll('.feature-card, .step, .resource-card').forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            element.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        });
        
        // Trigger initial animation
        window.dispatchEvent(new Event('scroll'));
    </script>
</body>
</html>