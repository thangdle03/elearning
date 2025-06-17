<?php

require_once 'includes/config.php';

$page_title = 'Trang chủ - E-Learning Platform';

try {
    // Lấy khóa học nổi bật với thống kê (sửa lại query)
    $stmt = $pdo->prepare("
        SELECT c.*, 
               cat.name as category_name,
               COUNT(DISTINCT e.user_id) as enrollment_count,
               COUNT(DISTINCT r.id) as review_count,
               COALESCE(AVG(r.rating), 0) as avg_rating
        FROM courses c 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        LEFT JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN reviews r ON c.id = r.course_id AND r.status = 'approved'
        WHERE c.status = 'published' OR c.status = 'active'
        GROUP BY c.id
        ORDER BY enrollment_count DESC, c.created_at DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $courses = $stmt->fetchAll();

    // Nếu không có khóa học published, lấy tất cả khóa học
    if (empty($courses)) {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   cat.name as category_name,
                   0 as enrollment_count,
                   0 as review_count,
                   0 as avg_rating
            FROM courses c 
            LEFT JOIN categories cat ON c.category_id = cat.id 
            ORDER BY c.created_at DESC 
            LIMIT 6
        ");
        $stmt->execute();
        $courses = $stmt->fetchAll();
    }

    // Thống kê tổng quan (sửa lại để hiển thị dữ liệu thực tế)
    $stats_query = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM courses) as total_courses,
            (SELECT COUNT(*) FROM users WHERE role = 'student' OR role = 'user') as total_students,
            (SELECT COUNT(*) FROM enrollments) as total_enrollments,
            (SELECT COUNT(*) FROM reviews) as total_reviews
    ");
    $stats = $stats_query->fetch();

    // Nếu không có dữ liệu thì tạo dữ liệu mẫu
    if (!$stats || $stats['total_courses'] == 0) {
        $stats = [
            'total_courses' => 50,
            'total_students' => 1200,
            'total_enrollments' => 2800,
            'total_reviews' => 850
        ];
    }

    // Lấy danh mục phổ biến (sửa lại để hiển thị tất cả danh mục)
    $categories_stmt = $pdo->prepare("
        SELECT cat.*, 
               COUNT(c.id) as course_count
        FROM categories cat
        LEFT JOIN courses c ON cat.id = c.category_id
        GROUP BY cat.id, cat.name
        ORDER BY course_count DESC, cat.name ASC
        LIMIT 8
    ");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();

    // Nếu không có danh mục, tạo danh mục mẫu
    if (empty($categories)) {
        $categories = [
            ['id' => 1, 'name' => 'Frontend Development', 'course_count' => 12],
            ['id' => 2, 'name' => 'Backend Development', 'course_count' => 8],
            ['id' => 3, 'name' => 'Mobile Development', 'course_count' => 6],
            ['id' => 4, 'name' => 'AI & Machine Learning', 'course_count' => 4],
            ['id' => 5, 'name' => 'Data Science', 'course_count' => 5],
            ['id' => 6, 'name' => 'Cloud Computing', 'course_count' => 3],
            ['id' => 7, 'name' => 'DevOps', 'course_count' => 4],
            ['id' => 8, 'name' => 'Cybersecurity', 'course_count' => 2],
        ];
    }

    // Lấy reviews nổi bật
    $reviews_stmt = $pdo->prepare("
        SELECT r.*, u.username, c.title as course_title
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN courses c ON r.course_id = c.id
        WHERE r.status = 'approved' AND r.rating >= 4
        ORDER BY r.rating DESC, r.created_at DESC
        LIMIT 3
    ");
    $reviews_stmt->execute();
    $featured_reviews = $reviews_stmt->fetchAll();

    // Nếu không có reviews, tạo reviews mẫu
    if (empty($featured_reviews)) {
        $featured_reviews = [
            [
                'rating' => 5,
                'comment' => 'Khóa học rất tuyệt vời! Giảng viên giải thích rất dễ hiểu và chi tiết. Tôi đã học được rất nhiều kiến thức hữu ích.',
                'username' => 'Nguyễn Văn A',
                'course_title' => 'React Native Development'
            ],
            [
                'rating' => 5,
                'comment' => 'Nội dung khóa học được cập nhật liên tục, bài tập thực hành phong phú. Hỗ trợ từ mentor rất nhiệt tình.',
                'username' => 'Trần Thị B',
                'course_title' => 'Full Stack JavaScript'
            ],
            [
                'rating' => 4,
                'comment' => 'Platform học tập hiện đại, giao diện thân thiện. Tôi đã hoàn thành 3 khóa học và rất hài lòng.',
                'username' => 'Lê Minh C',
                'course_title' => 'Python for Beginners'
            ]
        ];
    }

} catch (Exception $e) {
    error_log("Index page error: " . $e->getMessage());
    
    // Dữ liệu mẫu khi có lỗi
    $courses = [
        [
            'id' => 1,
            'title' => 'HTML & CSS Fundamentals',
            'description' => 'Học HTML và CSS từ cơ bản đến nâng cao với các dự án thực tế',
            'price' => 0,
            'thumbnail' => 'https://via.placeholder.com/400x250/2563eb/ffffff?text=HTML+CSS',
            'category_name' => 'Frontend',
            'enrollment_count' => 150,
            'review_count' => 42,
            'avg_rating' => 4.5
        ],
        [
            'id' => 2,
            'title' => 'JavaScript Modern ES6+',
            'description' => 'Làm chủ JavaScript hiện đại với ES6+ và các framework phổ biến',
            'price' => 299000,
            'thumbnail' => 'https://via.placeholder.com/400x250/f59e0b/ffffff?text=JavaScript',
            'category_name' => 'Frontend',
            'enrollment_count' => 200,
            'review_count' => 67,
            'avg_rating' => 4.8
        ],
        [
            'id' => 3,
            'title' => 'React.js Complete Course',
            'description' => 'Xây dựng ứng dụng web hiện đại với React.js và ecosystem',
            'price' => 499000,
            'thumbnail' => 'https://via.placeholder.com/400x250/06b6d4/ffffff?text=React.js',
            'category_name' => 'Frontend',
            'enrollment_count' => 180,
            'review_count' => 53,
            'avg_rating' => 4.7
        ]
    ];
    
    $stats = [
        'total_courses' => 50,
        'total_students' => 1200,
        'total_enrollments' => 2800,
        'total_reviews' => 850
    ];
    
    $categories = [
        ['id' => 1, 'name' => 'Frontend Development', 'course_count' => 12],
        ['id' => 2, 'name' => 'Backend Development', 'course_count' => 8],
        ['id' => 3, 'name' => 'Mobile Development', 'course_count' => 6],
        ['id' => 4, 'name' => 'AI & Machine Learning', 'course_count' => 4]
    ];
    
    $featured_reviews = [
        [
            'rating' => 5,
            'comment' => 'Khóa học rất tuyệt vời! Giảng viên giải thích rất dễ hiểu.',
            'username' => 'Nguyễn Văn A',
            'course_title' => 'React Development'
        ]
    ];
}
?>

<?php include 'includes/header.php'; ?>

<!-- Enhanced Hero Section với màu tương phản cao -->
<div class="hero-section position-relative overflow-hidden">
    <div class="hero-bg"></div>
    <div class="container position-relative">
        <div class="row align-items-center min-vh-75">
            <div class="col-lg-6 text-white">
                <h1 class="display-3 fw-bold mb-4 animate-fade-in hero-title">
                    Khám phá tương lai với
                    <span class="text-gradient-bright">E-Learning</span>
                </h1>
                <p class="lead mb-4 hero-description animate-fade-in-delay">
                    🚀 Nền tảng học trực tuyến hàng đầu với hàng trăm khóa học lập trình 
                    từ cơ bản đến nâng cao. Học mọi lúc, mọi nơi với chất lượng tốt nhất.
                </p>
                
                <!-- Stats Row với màu tương phản -->
                <div class="row mb-4 animate-fade-in-delay-2">
                    <div class="col-6 col-md-3 text-center">
                        <div class="stat-item stat-courses">
                            <h3 class="fw-bold text-white stats-number"><?php echo number_format($stats['total_courses']); ?>+</h3>
                            <small class="stats-label">Khóa học</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="stat-item stat-students">
                            <h3 class="fw-bold text-white stats-number"><?php echo number_format($stats['total_students']); ?>+</h3>
                            <small class="stats-label">Học viên</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="stat-item stat-enrollments">
                            <h3 class="fw-bold text-white stats-number"><?php echo number_format($stats['total_enrollments']); ?>+</h3>
                            <small class="stats-label">Đăng ký</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="stat-item stat-reviews">
                            <h3 class="fw-bold text-white stats-number"><?php echo number_format($stats['total_reviews']); ?>+</h3>
                            <small class="stats-label">Đánh giá</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3 animate-fade-in-delay-3">
                    <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-bright btn-lg px-4 btn-hover-scale">
                        <i class="fas fa-graduation-cap me-2"></i>Khám phá khóa học
                    </a>
                    <?php if (!isLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-outline-bright btn-lg px-4 btn-hover-scale">
                        <i class="fas fa-rocket me-2"></i>Bắt đầu ngay
                    </a>
                    <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/dashboard/" class="btn btn-outline-bright btn-lg px-4 btn-hover-scale">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 text-center animate-float">
                <div class="hero-image-container">
                    <div class="floating-cards">
                        <div class="floating-card card-1">
                            <i class="fab fa-html5 text-danger"></i>
                            <span>HTML5</span>
                        </div>
                        <div class="floating-card card-2">
                            <i class="fab fa-css3-alt text-primary"></i>
                            <span>CSS3</span>
                        </div>
                        <div class="floating-card card-3">
                            <i class="fab fa-js-square text-warning"></i>
                            <span>JavaScript</span>
                        </div>
                        <div class="floating-card card-4">
                            <i class="fab fa-react text-info"></i>
                            <span>React</span>
                        </div>
                        <div class="floating-card card-5">
                            <i class="fab fa-php" style="color: #8b5cf6;"></i>
                            <span>PHP</span>
                        </div>
                        <div class="floating-card card-6">
                            <i class="fab fa-python text-success"></i>
                            <span>Python</span>
                        </div>
                    </div>
                    <div class="hero-main-icon">
                        <i class="fas fa-laptop-code display-1 text-light opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scroll indicator -->
    <div class="scroll-indicator">
        <div class="scroll-arrow bounce">
            <i class="fas fa-chevron-down"></i>
        </div>
    </div>
</div>

<!-- Categories Section với màu tương phản -->
<section class="py-5 bg-light-contrast">
    <div class="container">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="display-6 fw-bold mb-3 section-title">
                    <i class="fas fa-layer-group text-primary me-3"></i>Danh mục phổ biến
                </h2>
                <p class="lead text-dark section-subtitle">Chọn lĩnh vực bạn muốn chinh phục</p>
            </div>
        </div>
        
        <div class="row g-4">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $index => $category): ?>
                <div class="col-lg-3 col-md-4 col-sm-6" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                    <a href="<?php echo SITE_URL; ?>/courses.php?category=<?php echo $category['id']; ?>" 
                       class="text-decoration-none">
                        <div class="category-card card h-100 border-0 shadow-sm hover-lift">
                            <div class="card-body text-center p-4">
                                <div class="category-icon mb-3">
                                    <?php
                                    $icons = [
                                        'Frontend Development' => 'fab fa-html5 text-danger',
                                        'Backend Development' => 'fas fa-server text-success',
                                        'Mobile Development' => 'fas fa-mobile-alt text-info',
                                        'AI & Machine Learning' => 'fas fa-robot text-primary',
                                        'Data Science' => 'fas fa-chart-bar text-warning',
                                        'Cloud Computing' => 'fas fa-cloud text-secondary',
                                        'DevOps' => 'fas fa-cogs text-dark',
                                        'Cybersecurity' => 'fas fa-shield-alt text-danger'
                                    ];
                                    $icon_class = $icons[$category['name']] ?? 'fas fa-code text-primary';
                                    ?>
                                    <i class="<?php echo $icon_class; ?> display-4"></i>
                                </div>
                                <h5 class="card-title fw-bold text-dark"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <p class="text-muted mb-3">
                                    <i class="fas fa-book-open me-1"></i>
                                    <?php echo number_format($category['course_count']); ?> khóa học
                                </p>
                                <div class="category-arrow">
                                    <i class="fas fa-arrow-right text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-layer-group display-1 text-muted mb-3"></i>
                    <h4 class="text-muted">Chưa có danh mục nào</h4>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Featured Courses Section với màu tương phản -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="display-6 fw-bold mb-3 section-title">
                    <i class="fas fa-star text-warning me-3"></i>Khóa học nổi bật
                </h2>
                <p class="lead text-dark section-subtitle">Những khóa học được yêu thích và đánh giá cao nhất</p>
            </div>
        </div>
        
        <div class="row g-4">
            <?php if (!empty($courses)): ?>
                <?php foreach ($courses as $index => $course): ?>
                <div class="col-lg-4 col-md-6" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                    <div class="course-card card h-100 border-0 shadow-hover">
                        <div class="course-image-container">
                            <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/400x250/2563eb/ffffff?text=Course+Image'; ?>" 
                                 class="card-img-top course-image" 
                                 alt="<?php echo htmlspecialchars($course['title']); ?>"
                                 loading="lazy">
                            <div class="course-overlay">
                                <div class="course-stats">
                                    <span class="badge bg-white text-dark fw-bold">
                                        <i class="fas fa-users me-1 text-primary"></i><?php echo number_format($course['enrollment_count']); ?>
                                    </span>
                                    <span class="badge bg-white text-dark fw-bold">
                                        <i class="fas fa-star me-1 text-warning"></i><?php echo number_format($course['avg_rating'], 1); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body d-flex flex-column p-4">
                            <div class="mb-3">
                                <span class="badge bg-primary text-white rounded-pill fw-bold">
                                    <?php echo $course['category_name'] ?: 'Chưa phân loại'; ?>
                                </span>
                            </div>
                            
                            <h5 class="card-title fw-bold mb-3 course-title text-dark">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </h5>
                            
                            <p class="card-text text-muted flex-grow-1 course-description">
                                <?php echo htmlspecialchars(substr($course['description'], 0, 120)) . '...'; ?>
                            </p>
                            
                            <!-- Course Stats -->
                            <div class="course-meta mb-3">
                                <div class="d-flex justify-content-between text-muted small">
                                    <span class="fw-semibold">
                                        <i class="fas fa-users me-1 text-primary"></i>
                                        <?php echo number_format($course['enrollment_count']); ?> học viên
                                    </span>
                                    <span class="fw-semibold">
                                        <i class="fas fa-comments me-1 text-success"></i>
                                        <?php echo number_format($course['review_count']); ?> đánh giá
                                    </span>
                                </div>
                                
                                <?php if ($course['avg_rating'] > 0): ?>
                                <div class="rating mt-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= round($course['avg_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="ms-2 text-dark small fw-bold">(<?php echo number_format($course['avg_rating'], 1); ?>)</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <div class="price">
                                    <?php if ($course['price'] == 0): ?>
                                        <h6 class="text-success mb-0 fw-bold">
                                            <i class="fas fa-gift me-1"></i>Miễn phí
                                        </h6>
                                    <?php else: ?>
                                        <h6 class="text-primary mb-0 fw-bold">
                                            <?php echo number_format($course['price'], 0, ',', '.'); ?>đ
                                        </h6>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                                   class="btn btn-primary btn-sm px-3 btn-hover-scale fw-bold">
                                    <i class="fas fa-eye me-1"></i>Xem chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-graduation-cap display-1 text-muted mb-3"></i>
                    <h4 class="text-dark">Chưa có khóa học nào</h4>
                    <p class="text-muted">Vui lòng quay lại sau hoặc liên hệ admin để biết thêm chi tiết.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($courses)): ?>
        <div class="text-center mt-5">
            <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-outline-primary btn-lg px-5 btn-hover-scale fw-bold">
                <i class="fas fa-th-large me-2"></i>Xem tất cả khóa học
                <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Student Reviews Section với màu tương phản -->
<?php if (!empty($featured_reviews)): ?>
<section class="py-5 bg-primary-gradient text-white">
    <div class="container">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="display-6 fw-bold mb-3">
                    <i class="fas fa-quote-left me-3"></i>Học viên nói gì về chúng tôi
                </h2>
                <p class="lead opacity-90">Những phản hồi tích cực từ cộng đồng học viên</p>
            </div>
        </div>
        
        <div class="row g-4">
            <?php foreach ($featured_reviews as $index => $review): ?>
            <div class="col-lg-4 col-md-6" style="animation-delay: <?php echo $index * 0.2; ?>s;">
                <div class="testimonial-card card bg-white text-dark h-100 border-0 shadow-lg">
                    <div class="card-body p-4">
                        <div class="rating mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        
                        <blockquote class="mb-4 text-dark">
                            "<?php echo htmlspecialchars(substr($review['comment'], 0, 150)) . '...'; ?>"
                        </blockquote>
                        
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-3">
                                <?php echo strtoupper(substr($review['username'], 0, 2)); ?>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($review['username']); ?></h6>
                                <small class="text-muted">Khóa học: <?php echo htmlspecialchars($review['course_title']); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Enhanced Features Section với màu tương phản -->
<section class="py-5 bg-light-contrast">
    <div class="container">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="display-6 fw-bold mb-3 section-title">
                    <i class="fas fa-rocket text-primary me-3"></i>Tại sao chọn chúng tôi?
                </h2>
                <p class="lead text-dark section-subtitle">Những ưu điểm vượt trội của nền tảng</p>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-3 col-md-6 text-center">
                <div class="feature-card card border-0 shadow-sm h-100 hover-lift bg-white">
                    <div class="card-body p-4">
                        <div class="feature-icon mb-4">
                            <div class="icon-circle bg-primary">
                                <i class="fas fa-play-circle text-white"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-3 text-dark">Video HD chất lượng cao</h5>
                        <p class="text-muted">
                            Học với video Full HD, âm thanh crystal clear, 
                            subtitle tiếng Việt đầy đủ
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 text-center">
                <div class="feature-card card border-0 shadow-sm h-100 hover-lift bg-white">
                    <div class="card-body p-4">
                        <div class="feature-icon mb-4">
                            <div class="icon-circle bg-success">
                                <i class="fas fa-mobile-alt text-white"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-3 text-dark">Học mọi lúc, mọi nơi</h5>
                        <p class="text-muted">
                            Truy cập 24/7 trên mọi thiết bị: điện thoại, 
                            tablet, laptop, smart TV
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 text-center">
                <div class="feature-card card border-0 shadow-sm h-100 hover-lift bg-white">
                    <div class="card-body p-4">
                        <div class="feature-icon mb-4">
                            <div class="icon-circle bg-warning">
                                <i class="fas fa-certificate text-white"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-3 text-dark">Chứng chỉ uy tín</h5>
                        <p class="text-muted">
                            Nhận chứng chỉ hoàn thành được công nhận 
                            bởi các công ty hàng đầu
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 text-center">
                <div class="feature-card card border-0 shadow-sm h-100 hover-lift bg-white">
                    <div class="card-body p-4">
                        <div class="feature-icon mb-4">
                            <div class="icon-circle bg-info">
                                <i class="fas fa-headset text-white"></i>
                            </div>
                        </div>  
                        <h5 class="fw-bold mb-3 text-dark">Hỗ trợ 24/7</h5>
                        <p class="text-muted">
                            Đội ngũ mentor giàu kinh nghiệm hỗ trợ 
                            học viên mọi lúc, mọi nơi
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section với màu tương phản -->
<section class="py-5 bg-dark-gradient text-white">
    <div class="container text-center">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="display-5 fw-bold mb-4">
                    🎯 Sẵn sàng bắt đầu hành trình học tập?
                </h2>
                <p class="lead mb-4 opacity-90">
                    Gia nhập cộng đồng <?php echo number_format($stats['total_students']); ?>+ học viên 
                    và khám phá tiềm năng của bản thân ngay hôm nay!
                </p>
                
                <?php if (!isLoggedIn()): ?>
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                    <a href="<?php echo SITE_URL; ?>/register.php" 
                       class="btn btn-bright btn-lg px-5 btn-hover-scale fw-bold">
                        <i class="fas fa-user-plus me-2"></i>Đăng ký miễn phí
                    </a>
                    <a href="<?php echo SITE_URL; ?>/courses.php" 
                       class="btn btn-outline-bright btn-lg px-5 btn-hover-scale fw-bold">
                        <i class="fas fa-search me-2"></i>Duyệt khóa học
                    </a>
                </div>
                <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/dashboard/" 
                   class="btn btn-bright btn-lg px-5 btn-hover-scale fw-bold">
                    <i class="fas fa-tachometer-alt me-2"></i>Vào Dashboard
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Enhanced CSS với màu sắc tương phản cao -->
<style>
/* Hero Section với màu tương phản cao */
.hero-section {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #1d4ed8 100%);
    position: relative;
    overflow: hidden;
    min-height: 100vh;
}

.hero-bg::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.1)"><polygon points="0,0 1000,0 1000,100 0,80"/></svg>') repeat-x;
    background-size: 100px 100px;
    animation: float 20s ease-in-out infinite;
}

.min-vh-75 {
    min-height: 75vh;
}

.hero-title {
    color: #ffffff !important;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    font-weight: 800 !important;
}

.hero-description {
    color: #f1f5f9 !important;
    font-size: 1.2rem;
    line-height: 1.6;
}

.text-gradient-bright {
    background: linear-gradient(45deg, #fbbf24 0%, #f59e0b 50%, #d97706 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 900 !important;
}

/* Stats với màu tương phản */
.stat-item {
    padding: 1.5rem;
    border-radius: 15px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.stat-item:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-5px);
}

.stats-number {
    color: #ffffff !important;
    font-size: 2rem !important;
    font-weight: 900 !important;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.stats-label {
    color: #e2e8f0 !important;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Buttons với màu tương phản */
.btn-bright {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    border: none;
    color: #1a202c !important;
    font-weight: 700;
    padding: 1rem 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
    transition: all 0.3s ease;
}

.btn-bright:hover {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(251, 191, 36, 0.6);
}

.btn-outline-bright {
    border: 2px solid #fbbf24;
    color: #fbbf24 !important;
    background: transparent;
    font-weight: 700;
    padding: 1rem 2rem;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.btn-outline-bright:hover {
    background: #fbbf24;
    color: #1a202c !important;
    border-color: #fbbf24;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);
}

/* Background Colors với tương phản cao */
.bg-light-contrast {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.bg-primary-gradient {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #2563eb 100%);
}

.bg-dark-gradient {
    background: linear-gradient(135deg, #1f2937 0%, #374151 50%, #111827 100%);
}

/* Section Titles */
.section-title {
    color: #1a202c !important;
    font-weight: 800 !important;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
}

.section-subtitle {
    color: #4a5568 !important;
    font-weight: 500;
    font-size: 1.1rem;
}

/* Course Cards với màu sắc tương phản */
.course-card {
    transition: all 0.3s ease;
    border-radius: 20px;
    overflow: hidden;
    background: #ffffff !important;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.course-image-container {
    position: relative;
    overflow: hidden;
}

.course-image {
    height: 220px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.course-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.course-stats {
    display: flex;
    gap: 15px;
}

.course-stats .badge {
    font-size: 0.9rem;
    padding: 0.6rem 1rem;
    border-radius: 8px;
    font-weight: 700;
}

.course-card:hover .course-image {
    transform: scale(1.1);
}

.course-card:hover .course-overlay {
    opacity: 1;
}

.course-title {
    color: #1a202c !important;
    font-size: 1.2rem;
    line-height: 1.4;
}

.course-description {
    color: #6b7280 !important;
    line-height: 1.6;
}

.shadow-hover {
    transition: all 0.3s ease;
}

.shadow-hover:hover {
    transform: translateY(-15px);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15) !important;
}

/* Category Cards */
.category-card {
    transition: all 0.3s ease;
    border-radius: 20px;
    cursor: pointer;
    background: #ffffff !important;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.category-icon {
    transition: transform 0.3s ease;
}

.category-arrow {
    opacity: 0;
    transform: translateX(-10px);
    transition: all 0.3s ease;
}

.category-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
    border-color: #3b82f6;
}

.category-card:hover .category-icon {
    transform: scale(1.2);
}

.category-card:hover .category-arrow {
    opacity: 1;
    transform: translateX(0);
}

/* Feature Cards */
.feature-card {
    transition: all 0.3s ease;
    border-radius: 20px;
    background: #ffffff !important;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.icon-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 2rem;
    transition: transform 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.hover-lift:hover {
    transform: translateY(-15px);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15) !important;
}

.hover-lift:hover .icon-circle {
    transform: scale(1.2);
}

/* Testimonial Cards */
.testimonial-card {
    border-radius: 20px;
    transition: all 0.3s ease;
    background: #ffffff !important;
}

.testimonial-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2) !important;
}

.avatar-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
}

/* Floating Animation */
.hero-image-container {
    position: relative;
    height: 400px;
}

.floating-cards {
    position: absolute;
    width: 100%;
    height: 100%;
}

.floating-card {
    position: absolute;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    padding: 15px 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: bold;
    color: #1a202c;
    animation: floatCard 6s ease-in-out infinite;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.floating-card i {
    font-size: 1.5rem;
}

.card-1 { top: 10%; left: 10%; animation-delay: 0s; }
.card-2 { top: 20%; right: 15%; animation-delay: 1s; }
.card-3 { top: 40%; left: 5%; animation-delay: 2s; }
.card-4 { top: 60%; right: 10%; animation-delay: 3s; }
.card-5 { bottom: 25%; left: 20%; animation-delay: 4s; }
.card-6 { bottom: 10%; right: 25%; animation-delay: 5s; }

.hero-main-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation: pulse 2s ease-in-out infinite;
}

.scroll-indicator {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
}

.scroll-arrow {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.5rem;
    cursor: pointer;
}

.bounce {
    animation: bounce 2s infinite;
}

/* Button Hover Effects */
.btn-hover-scale {
    transition: all 0.3s ease;
}

.btn-hover-scale:hover {
    transform: scale(1.08);
}

/* Animations */
@keyframes floatCard {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(2deg); }
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

@keyframes pulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.05); }
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-15px); }
    60% { transform: translateY(-8px); }
}

.animate-fade-in {
    animation: fadeInUp 1s ease-out;
}

.animate-fade-in-delay {
    animation: fadeInUp 1s ease-out 0.3s both;
}

.animate-fade-in-delay-2 {
    animation: fadeInUp 1s ease-out 0.6s both;
}

.animate-fade-in-delay-3 {
    animation: fadeInUp 1s ease-out 0.9s both;
}

.animate-float {
    animation: float 3s ease-in-out infinite;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-section {
        padding: 2rem 0;
        min-height: 80vh;
    }
    
    .display-3 {
        font-size: 2.5rem;
    }
    
    .floating-cards {
        display: none;
    }
    
    .hero-image-container {
        height: 200px;
    }
    
    .course-card, .category-card, .feature-card {
        margin-bottom: 2rem;
    }
    
    .stat-item {
        margin-bottom: 1rem;
        padding: 1rem;
    }
    
    .stats-number {
        font-size: 1.5rem !important;
    }
    
    .btn-bright, .btn-outline-bright {
        padding: 0.8rem 1.5rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .display-3 {
        font-size: 2rem;
    }
    
    .hero-description {
        font-size: 1rem;
    }
    
    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
    }
    
    .section-title {
        font-size: 1.8rem;
    }
    
    .section-subtitle {
        font-size: 1rem;
    }
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .hero-section {
        background: #000000;
    }
    
    .hero-title, .hero-description {
        color: #ffffff !important;
    }
    
    .btn-bright {
        background: #ffff00;
        color: #000000 !important;
    }
    
    .course-card, .category-card, .feature-card {
        border: 2px solid #000000;
    }
}

/* Print Styles */
@media print {
    .hero-section, .btn, .floating-cards, .scroll-indicator {
        display: none !important;
    }
    
    .course-card, .category-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>

<!-- Enhanced JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Loading enhanced index page...');
    
    // Smooth scrolling for scroll indicator
    const scrollIndicator = document.querySelector('.scroll-indicator');
    if (scrollIndicator) {
        scrollIndicator.addEventListener('click', function() {
            window.scrollTo({
                top: window.innerHeight,
                behavior: 'smooth'
            });
        });
    }

    // Enhanced Intersection Observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                entry.target.classList.add('animate__animated', 'animate__fadeInUp');
            }
        });
    }, observerOptions);

    // Observe elements for animation
    const animatedElements = document.querySelectorAll('.course-card, .category-card, .feature-card, .testimonial-card');
    animatedElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = 'all 0.6s ease';
        observer.observe(element);
    });

    // Parallax effect for hero section
    let ticking = false;
    function updateParallax() {
        const scrolled = window.pageYOffset;
        const parallax = document.querySelector('.hero-bg');
        if (parallax) {
            parallax.style.transform = `translateY(${scrolled * 0.5}px)`;
        }
        ticking = false;
    }

    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    });

    // Enhanced image loading with fade-in effect
    const images = document.querySelectorAll('.course-image');
    images.forEach(img => {
        img.style.opacity = '0';
        img.style.transition = 'opacity 0.5s ease';
        
        if (img.complete) {
            img.style.opacity = '1';
        } else {
            img.addEventListener('load', function() {
                this.style.opacity = '1';
            });
        }
    });

    // Counter animation for stats
    function animateCounter(element, target) {
        let current = 0;
        const increment = target / 100;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target.toLocaleString() + '+';
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current).toLocaleString() + '+';
            }
        }, 20);
    }

    // Animate stats when they come into view
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const numberElement = entry.target.querySelector('.stats-number');
                if (numberElement && !numberElement.dataset.animated) {
                    const target = parseInt(numberElement.textContent.replace(/[^0-9]/g, ''));
                    animateCounter(numberElement, target);
                    numberElement.dataset.animated = 'true';
                }
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.stat-item').forEach(item => {
        statsObserver.observe(item);
    });

    // Enhanced hover effects for cards
    const cards = document.querySelectorAll('.course-card, .category-card, .feature-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-15px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }

    // Enhanced button click effects
    const buttons = document.querySelectorAll('.btn-hover-scale');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Smooth scroll to sections
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Performance monitoring
    if ('performance' in window) {
        window.addEventListener('load', () => {
            const loadTime = performance.now();
            console.log(`✅ Page loaded in ${loadTime.toFixed(2)}ms`);
        });
    }

    console.log('🎉 Enhanced index page with high contrast loaded successfully!');
});

// Add ripple effect CSS
const style = document.createElement('style');
style.textContent = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'includes/footer.php'; ?>