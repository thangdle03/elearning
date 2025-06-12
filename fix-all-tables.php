<?php

require_once 'includes/config.php';

echo "<h2>🔧 Fixing All Database Tables...</h2>";

try {
    // Fix users table
    echo "<h3>📝 Fixing users table...</h3>";
    
    // Check and add missing columns to users table
    $users_columns = [
        'updated_at' => "ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        'full_name' => "ALTER TABLE users ADD COLUMN full_name VARCHAR(255) NULL AFTER role",
        'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER full_name",
        'bio' => "ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER phone",
        'avatar' => "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER bio"
    ];
    
    foreach ($users_columns as $column => $sql) {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE '$column'");
        if (!$stmt->fetch()) {
            $pdo->exec($sql);
            echo "✅ Added column: $column<br>";
        } else {
            echo "✅ Column exists: $column<br>";
        }
    }
    
    // Fix courses table
    echo "<h3>📚 Fixing courses table...</h3>";
    
    // Check if courses table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'courses'");
    if (!$stmt->fetch()) {
        echo "Creating courses table...<br>";
        $pdo->exec("
            CREATE TABLE courses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                thumbnail VARCHAR(255),
                price DECIMAL(10,2) DEFAULT 0,
                category_id INT,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX(category_id),
                INDEX(status)
            )
        ");
        echo "✅ Courses table created<br>";
    } else {
        // Add missing columns to courses
        $courses_columns = [
            'updated_at' => "ALTER TABLE courses ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
        ];
        
        foreach ($courses_columns as $column => $sql) {
            $stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE '$column'");
            if (!$stmt->fetch()) {
                $pdo->exec($sql);
                echo "✅ Added column to courses: $column<br>";
            } else {
                echo "✅ Courses column exists: $column<br>";
            }
        }
    }
    
    // Fix categories table
    echo "<h3>📂 Fixing categories table...</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
    if (!$stmt->fetch()) {
        echo "Creating categories table...<br>";
        $pdo->exec("
            CREATE TABLE categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert sample categories
        $pdo->exec("
            INSERT INTO categories (name, description) VALUES 
            ('Lập trình Web', 'Các khóa học về phát triển web'),
            ('Thiết kế', 'Khóa học thiết kế đồ họa và UI/UX'),
            ('Marketing', 'Digital marketing và SEO'),
            ('Kinh doanh', 'Quản lý và khởi nghiệp'),
            ('Ngoại ngữ', 'Học tiếng Anh và các ngôn ngữ khác')
        ");
        echo "✅ Categories table created with sample data<br>";
    } else {
        echo "✅ Categories table exists<br>";
    }
    
    // Fix lessons table
    echo "<h3>📖 Fixing lessons table...</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'lessons'");
    if (!$stmt->fetch()) {
        echo "Creating lessons table...<br>";
        $pdo->exec("
            CREATE TABLE lessons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                video_url VARCHAR(500),
                order_index INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
                INDEX(course_id),
                INDEX(order_index)
            )
        ");
        echo "✅ Lessons table created<br>";
    } else {
        echo "✅ Lessons table exists<br>";
    }
    
    // Fix enrollments table
    echo "<h3>👥 Fixing enrollments table...</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'enrollments'");
    if (!$stmt->fetch()) {
        echo "Creating enrollments table...<br>";
        $pdo->exec("
            CREATE TABLE enrollments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                course_id INT NOT NULL,
                enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
                UNIQUE KEY unique_enrollment (user_id, course_id),
                INDEX(user_id),
                INDEX(course_id)
            )
        ");
        echo "✅ Enrollments table created<br>";
    } else {
        echo "✅ Enrollments table exists<br>";
    }
    
    // Fix progress table
    echo "<h3>📊 Fixing progress table...</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'progress'");
    if (!$stmt->fetch()) {
        echo "Creating progress table...<br>";
        $pdo->exec("
            CREATE TABLE progress (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                lesson_id INT NOT NULL,
                completed BOOLEAN DEFAULT FALSE,
                completed_at TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
                UNIQUE KEY unique_progress (user_id, lesson_id),
                INDEX(user_id),
                INDEX(lesson_id)
            )
        ");
        echo "✅ Progress table created<br>";
    } else {
        echo "✅ Progress table exists<br>";
    }
    
    // Create admin and student accounts
    echo "<h3>👤 Creating user accounts...</h3>";
    
    // Delete existing accounts
    $stmt = $pdo->prepare("DELETE FROM users WHERE username IN ('admin', 'student1')");
    $stmt->execute();
    
    // Create new accounts
    $password_hash = password_hash('password', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, status, full_name, created_at) VALUES 
        ('admin', 'admin@elearning.com', ?, 'admin', 'active', 'Quản trị viên', NOW()),
        ('student1', 'student1@elearning.com', ?, 'student', 'active', 'Học viên 1', NOW())
    ");
    $stmt->execute([$password_hash, $password_hash]);
    
    echo "✅ User accounts created successfully!<br>";
    
    // Create sample course
    echo "<h3>📚 Creating sample course...</h3>";
    
    $stmt = $pdo->prepare("
        INSERT INTO courses (title, description, price, category_id, status, created_at) VALUES 
        ('Khóa học PHP cơ bản', 'Học lập trình PHP từ cơ bản đến nâng cao', 500000, 1, 'active', NOW())
    ");
    $stmt->execute();
    $course_id = $pdo->lastInsertId();
    
    // Create sample lessons
    $lessons = [
        'Giới thiệu về PHP',
        'Cú pháp cơ bản PHP',
        'Biến và kiểu dữ liệu',
        'Vòng lặp và điều kiện',
        'Hàm trong PHP'
    ];
    
    foreach ($lessons as $index => $lesson_title) {
        $stmt = $pdo->prepare("
            INSERT INTO lessons (course_id, title, content, order_index, created_at) VALUES 
            (?, ?, 'Nội dung bài học về <?php echo $lesson_title; ?>', ?, NOW())
        ");
        $stmt->execute([$course_id, $lesson_title, $index + 1]);
    }
    
    echo "✅ Sample course and lessons created<br>";
    
    echo "<h2>🎉 Database setup completed successfully!</h2>";
    
    // Display final status
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h4>✅ Setup Summary:</h4>";
    echo "<ul>";
    echo "<li>✅ Users table: Fixed with all required columns</li>";
    echo "<li>✅ Courses table: Created/Updated</li>";
    echo "<li>✅ Categories table: Created with sample data</li>";
    echo "<li>✅ Lessons table: Created</li>";
    echo "<li>✅ Enrollments table: Created</li>";
    echo "<li>✅ Progress table: Created</li>";
    echo "<li>✅ Admin account: admin/password</li>";
    echo "<li>✅ Student account: student1/password</li>";
    echo "<li>✅ Sample course created</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin: 20px;'>";
    echo "<a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Go to Login</a>";
    echo "<a href='admin/courses.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Admin Panel</a>";
    echo "<a href='index.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>Homepage</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString();
}
?>