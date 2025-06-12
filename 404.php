<?php
// filepath: d:\Xampp\htdocs\elearning\404.php

require_once 'includes/config.php';

$page_title = 'Trang không tìm thấy - 404';

// Log 404 errors (optional)
error_log("404 Error: " . $_SERVER['REQUEST_URI'] . " - Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'Direct'));

// Set proper HTTP status code
http_response_code(404);

// Get the requested URL for suggestions
$requested_url = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim($requested_url, '/'));

// Simple suggestions based on URL
$suggestions = [];

// Check if it might be a course
if (in_array('course', $path_parts) || in_array('courses', $path_parts)) {
    $suggestions[] = [
        'title' => 'Tất cả khóa học',
        'url' => SITE_URL . '/courses.php',
        'icon' => 'bi-collection'
    ];
}

// Check if it might be learning related
if (in_array('learn', $path_parts) || in_array('lesson', $path_parts)) {
    if (isLoggedIn()) {
        $suggestions[] = [
            'title' => 'Khóa học của tôi',
            'url' => SITE_URL . '/my-courses.php',
            'icon' => 'bi-bookmarks'
        ];
    }
}

// Check if it might be admin related
if (in_array('admin', $path_parts)) {
    if (isLoggedIn() && isAdmin()) {
        $suggestions[] = [
            'title' => 'Admin Panel',
            'url' => SITE_URL . '/admin/',
            'icon' => 'bi-speedometer2'
        ];
    }
}

// Default suggestions
if (empty($suggestions)) {
    $suggestions = [
        [
            'title' => 'Trang chủ',
            'url' => SITE_URL,
            'icon' => 'bi-house'
        ],
        [
            'title' => 'Tất cả khóa học',
            'url' => SITE_URL . '/courses.php',
            'icon' => 'bi-collection'
        ],
        [
            'title' => 'Tìm kiếm',
            'url' => SITE_URL . '/search.php',
            'icon' => 'bi-search'
        ]
    ];
}

// Get recent popular courses for suggestions
try {
    $stmt = $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
        FROM courses c 
        ORDER BY student_count DESC, c.created_at DESC 
        LIMIT 3
    ");
    $popular_courses = $stmt->fetchAll();
} catch (Exception $e) {
    $popular_courses = [];
}
?>

<?php include 'includes/header.php'; ?>

<!-- 404 Error Page -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 text-center">
            <!-- 404 Animation -->
            <div class="error-animation mb-4">
                <div class="error-code">
                    <span class="digit">4</span>
                    <span class="digit bounce">0</span>
                    <span class="digit">4</span>
                </div>
            </div>
            
            <!-- Error Message -->
            <h1 class="display-4 fw-bold text-primary mb-3">Oops! Trang không tìm thấy</h1>
            <p class="lead text-muted mb-4">
                Trang bạn đang tìm kiếm có thể đã được di chuyển, xóa hoặc không tồn tại.
            </p>
            
            <!-- Requested URL -->
            <div class="alert alert-light mb-4">
                <small class="text-muted">
                    <i class="bi bi-link-45deg me-1"></i>
                    URL yêu cầu: <code><?php echo htmlspecialchars($requested_url); ?></code>
                </small>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mb-5">
                <?php foreach ($suggestions as $suggestion): ?>
                <div class="col-md-4 mb-3">
                    <a href="<?php echo $suggestion['url']; ?>" class="btn btn-outline-primary btn-lg w-100">
                        <i class="<?php echo $suggestion['icon']; ?> d-block mb-2" style="font-size: 2rem;"></i>
                        <?php echo $suggestion['title']; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Search Box -->
            <div class="card mb-5">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-search me-2"></i>Tìm kiếm thay thế
                    </h5>
                    <p class="text-muted mb-3">Hãy thử tìm kiếm nội dung bạn cần:</p>
                    <form action="<?php echo SITE_URL; ?>/search.php" method="GET">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control form-control-lg" 
                                   placeholder="Nhập từ khóa tìm kiếm..." autofocus>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search me-2"></i>Tìm kiếm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Popular Courses -->
            <?php if ($popular_courses): ?>
            <div class="mb-5">
                <h4 class="fw-bold mb-4">
                    <i class="bi bi-fire me-2 text-danger"></i>Khóa học phổ biến
                </h4>
                <div class="row">
                    <?php foreach ($popular_courses as $course): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 course-card">
                            <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/300x200?text=' . urlencode($course['title']); ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>"
                                 style="height: 150px; object-fit: cover;">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-people me-1"></i>
                                        <?php echo $course['student_count']; ?> học viên
                                    </small>
                                    <span class="badge bg-success">
                                        <?php echo formatPrice($course['price']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                                   class="btn btn-primary btn-sm w-100">
                                    Xem chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Help Section -->
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-question-circle me-2"></i>Cần hỗ trợ?
                    </h5>
                    <p class="text-muted mb-3">
                        Nếu bạn tin rằng đây là một lỗi hoặc cần hỗ trợ, vui lòng liên hệ với chúng tôi:
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="mailto:support@elearning.com" class="btn btn-outline-primary">
                            <i class="bi bi-envelope me-2"></i>Email hỗ trợ
                        </a>
                        <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-outline-secondary">
                            <i class="bi bi-chat-dots me-2"></i>Liên hệ
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Back Button -->
            <div class="mt-4">
                <button onclick="goBack()" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>Quay lại
                </button>
                <a href="<?php echo SITE_URL; ?>" class="btn btn-primary">
                    <i class="bi bi-house me-2"></i>Về trang chủ
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.error-animation {
    margin: 2rem 0;
}

.error-code {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
}

.digit {
    font-size: 8rem;
    font-weight: bold;
    color: #007bff;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
    line-height: 1;
}

.bounce {
    animation: bounce 2s infinite;
    color: #dc3545;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-30px);
    }
    60% {
        transform: translateY(-15px);
    }
}

.course-card {
    transition: all 0.3s ease;
    border: 1px solid #e0e0e0;
}

.course-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.btn-outline-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,123,255,0.3);
}

@media (max-width: 768px) {
    .digit {
        font-size: 4rem;
    }
    
    .error-code {
        gap: 0.5rem;
    }
    
    .display-4 {
        font-size: 2rem;
    }
}

/* Loading animation for search */
.searching {
    position: relative;
}

.searching::after {
    content: '';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}
</style>

<!-- JavaScript -->
<script>
// Go back functionality
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '<?php echo SITE_URL; ?>';
    }
}

// Enhanced search with loading state
document.querySelector('form').addEventListener('submit', function(e) {
    const input = this.querySelector('input[name="q"]');
    if (input.value.trim() === '') {
        e.preventDefault();
        input.focus();
        return;
    }
    
    // Add loading state
    input.classList.add('searching');
    this.querySelector('button').disabled = true;
    this.querySelector('button').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang tìm...';
});

// Auto-suggest popular searches
const searchInput = document.querySelector('input[name="q"]');
const popularSearches = ['PHP', 'JavaScript', 'Python', 'Web Design', 'Marketing', 'Photoshop'];

searchInput.addEventListener('focus', function() {
    if (this.value === '') {
        this.placeholder = 'Thử: ' + popularSearches[Math.floor(Math.random() * popularSearches.length)];
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // ESC to focus search
    if (e.key === 'Escape') {
        searchInput.focus();
    }
    
    // Ctrl/Cmd + Home to go to homepage
    if ((e.ctrlKey || e.metaKey) && e.key === 'Home') {
        e.preventDefault();
        window.location.href = '<?php echo SITE_URL; ?>';
    }
});

// Easter egg - Konami code
let konamiCode = [];
const konamiSequence = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; // ↑↑↓↓←→←→BA

document.addEventListener('keydown', function(e) {
    konamiCode.push(e.keyCode);
    if (konamiCode.length > konamiSequence.length) {
        konamiCode.shift();
    }
    
    if (konamiCode.length === konamiSequence.length && 
        konamiCode.every((code, index) => code === konamiSequence[index])) {
        
        // Easter egg: Show secret message
        const secretMsg = document.createElement('div');
        secretMsg.className = 'alert alert-success position-fixed';
        secretMsg.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        secretMsg.innerHTML = '🎉 Chúc mừng! Bạn đã tìm thấy Easter Egg! <br><small>Hãy tiếp tục khám phá các khóa học nhé!</small>';
        document.body.appendChild(secretMsg);
        
        setTimeout(() => secretMsg.remove(), 5000);
        konamiCode = [];
    }
});

// Animate elements on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe all cards
document.querySelectorAll('.card, .btn-outline-primary').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'all 0.6s ease';
    observer.observe(el);
});

// Auto-focus search after animation
setTimeout(() => {
    searchInput.focus();
}, 1000);
</script>

<?php include 'includes/footer.php'; ?>