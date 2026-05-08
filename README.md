ElearnPortal - Digital Education Repository System
https://img.shields.io/badge/PHP-7.4%252B-blue https://img.shields.io/badge/MySQL-8.0%252B-orange https://img.shields.io/badge/Bootstrap-5.3-purple https://img.shields.io/badge/License-MIT-green

📌 Overview
ElearnPortal is a web-based platform that centralizes academic resources (lecture notes, past papers, research documents, videos) for educational institutions. It features role-based access for Students (search/download), Lecturers (upload/manage resources), and Administrators (full control). Built with PHP, MySQL, and Bootstrap.

🚀 Quick Start
bash
git clone https://github.com/yourusername/ElearnPortal.git
cd ElearnPortal
# Import database/ders_db.sql to MySQL
# Update config/config.php with your database credentials
# Visit http://localhost/ElearnPortal
🔧 Installation (3 Steps)
Step	Action
1	Create MySQL database and import database/elearnportal_db.sql
2	Copy config/config.example.php to config/config.php and update database settings
3	Ensure /uploads directory is writable (chmod 755)
👤 Default Accounts
Role	Email	Password
Admin	admin@elearnportal.com	admin123
Lecturer	lecturer@elearnportal.com	lecturer123
Student	student@elearnportal.com	student123
📁 Project Structure
text
ElearnPortal/
├── config/          # Database configuration
├── uploads/         # Uploaded files
├── admin/           # Admin-only pages
├── index.php        # Homepage
├── login.php        # Login page
├── upload.php       # Resource upload
├── search.php       # Advanced search
└── README.md
🔒 Security Features
✅ Password hashing (bcrypt)

✅ PDO prepared statements (SQL injection protection)

✅ XSS protection (output escaping)

✅ Server-side RBAC enforcement

✅ CSRF tokens on forms

🛠️ Tech Stack
Component	Technology
Backend	PHP 7.4+
Database	MySQL 8.0+
Frontend	HTML5, CSS3, JavaScript
CSS Framework	Bootstrap 5
Server	Apache 2.4+
🐛 Troubleshooting
Issue	Solution
"Connection failed"	Check database credentials in config/config.php
Upload fails	Run chmod 755 uploads/ (Linux/Mac)
Blank page after login	Check PHP error logs
📝 License
MIT License - Free for personal and commercial use.

📧 Contact
Developer: Justus Mwendwa
GitHub: github.com/justusmwendwa
Project: github.com/justusmwendwa/ElearnPortal
