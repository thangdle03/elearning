<?php

require_once 'includes/config.php';

$page_title = 'Tìm kiếm';

// Get search parameters
$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$price_range = isset($_GET['price']) ? sanitize($_GET['price']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'relevance';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$results_per_page = 12;
$offset = ($page - 1) * $results_per_page;

// Initialize search results
$courses = [];
$total_results = 0;
$search_time = 0;

// Only search if query is provided
if (!empty($query) || $category > 0 || !empty($price_range)) {
    $start_time = microtime(true);
    
    // Build search query
    $where_conditions = [];
    $params = [];
    
    // Text search
    if (!empty($query)) {
        $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ? OR cat.name LIKE ?)";
        $search_term = "%$query%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Category filter
    if ($category > 0) {
        $where_conditions[] = "c.category_id = ?";
        $params[] = $category;
    }
    
    // Price filter
    switch ($price_range) {
        case 'free':
            $where_conditions[] = "c.price = 0";
            break;
        case 'under_500k':
            $where_conditions[] = "c.price > 0 AND c.price < 500000";
            break;
        case '500k_1m':
            $where_conditions[] = "c.price BETWEEN 500000 AND 1000000";
            break;
        case 'over_1m':
            $where_conditions[] = "c.price > 1000000";
            break;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Sort options
    $order_by = match($sort) {
        'newest' => 'ORDER BY c.created_at DESC',
        'oldest' => 'ORDER BY c.created_at ASC',
        'price_low' => 'ORDER BY c.price ASC',
        'price_high' => 'ORDER BY c.price DESC',
        'title' => 'ORDER BY c.title ASC',
        'popular' => 'ORDER BY student_count DESC',
        default => 'ORDER BY 
                    CASE 
                        WHEN c.title LIKE ? THEN 1
                        WHEN c.description LIKE ? THEN 2
                        WHEN cat.name LIKE ? THEN 3
                        ELSE 4
                    END, c.created_at DESC'
    };
    
    // Add relevance params if sorting by relevance
    if ($sort === 'relevance' && !empty($query)) {
        $relevance_params = [$search_term, $search_term, $search_term];
        $params = array_merge($params, $relevance_params);
    }
    
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM courses c 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        $where_clause
    ";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    
    // Get courses with pagination
    $search_sql = "
        SELECT c.*, 
               cat.name as category_name,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
        FROM courses c 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        $where_clause 
        $order_by
        LIMIT $results_per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($search_sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
    
    $search_time = round((microtime(true) - $start_time) * 1000, 2);
}

// Get all categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Calculate pagination
$total_pages = ceil($total_results / $results_per_page);

// Build search URL for pagination
function buildSearchUrl($params = []) {
    global $query, $category, $price_range, $sort;
    
    $url_params = [
        'q' => $query,
        'category' => $category,
        'price' => $price_range,
        'sort' => $sort
    ];
    
    $url_params = array_merge($url_params, $params);
    $url_params = array_filter($url_params); // Remove empty values
    
    return SITE_URL . '/search.php?' . http_build_query($url_params);
}
?>

<?php include 'includes/header.php'; ?>

<!-- Search Header -->
<div class="bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-2">
                    <i class="bi bi-search me-2"></i>Tìm kiếm khóa học
                </h1>
                <?php if (!empty($query)): ?>
                <p class="text-muted mb-0">
                    Kết quả cho: <strong>"<?php echo htmlspecialchars($query); ?>"</strong>
                    <?php if ($total_results > 0): ?>
                    - <?php echo number_format($total_results); ?> kết quả 
                    (<?php echo $search_time; ?>ms)
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <!-- Quick Search Form -->
                <form method="GET" class="d-flex">
                    <input type="text" name="q" class="form-control" 
                           placeholder="Tìm kiếm khóa học..." 
                           value="<?php echo htmlspecialchars($query); ?>">
                    <button type="submit" class="btn btn-primary ms-2">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container my-4">
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-funnel me-2"></i>Bộ lọc
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" id="filterForm">
                        <!-- Keep search query -->
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                        
                        <!-- Category Filter -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Danh mục</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="category" value="0" 
                                       id="cat_all" <?php echo $category == 0 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cat_all">
                                    Tất cả danh mục
                                </label>
                            </div>
                            <?php foreach ($categories as $cat): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="category" 
                                       value="<?php echo $cat['id']; ?>" 
                                       id="cat_<?php echo $cat['id']; ?>"
                                       <?php echo $category == $cat['id'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cat_<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Price Filter -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Giá khóa học</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="price" value="" 
                                       id="price_all" <?php echo empty($price_range) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="price_all">
                                    Tất cả mức giá
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="price" value="free" 
                                       id="price_free" <?php echo $price_range == 'free' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="price_free">
                                    <i class="bi bi-gift text-success me-1"></i>Miễn phí
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="price" value="under_500k" 
                                       id="price_under_500k" <?php echo $price_range == 'under_500k' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="price_under_500k">
                                    Dưới 500,000đ
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="price" value="500k_1m" 
                                       id="price_500k_1m" <?php echo $price_range == '500k_1m' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="price_500k_1m">
                                    500,000đ - 1,000,000đ
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="price" value="over_1m" 
                                       id="price_over_1m" <?php echo $price_range == 'over_1m' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="price_over_1m">
                                    Trên 1,000,000đ
                                </label>
                            </div>
                        </div>
                        
                        <!-- Filter Actions -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-2"></i>Áp dụng bộ lọc
                            </button>
                            <a href="<?php echo SITE_URL; ?>/search.php?q=<?php echo urlencode($query); ?>" 
                               class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-2"></i>Đặt lại
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Search Tips -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-lightbulb me-2"></i>Mẹo tìm kiếm
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            Sử dụng từ khóa cụ thể
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            Thử các từ đồng nghĩa
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            Kết hợp bộ lọc để tìm chính xác
                        </li>
                        <li class="mb-0">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            Tìm theo tên danh mục
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Sort & Results Info -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <?php if ($total_results > 0): ?>
                    <span class="text-muted">
                        Hiển thị <?php echo number_format($offset + 1); ?>-<?php echo number_format(min($offset + $results_per_page, $total_results)); ?> 
                        trong <?php echo number_format($total_results); ?> kết quả
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <label for="sortSelect" class="form-label mb-0 me-2">Sắp xếp:</label>
                    <select class="form-select form-select-sm" id="sortSelect" style="width: auto;">
                        <option value="relevance" <?php echo $sort == 'relevance' ? 'selected' : ''; ?>>Liên quan nhất</option>
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Phổ biến nhất</option>
                        <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Giá thấp đến cao</option>
                        <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Giá cao đến thấp</option>
                        <option value="title" <?php echo $sort == 'title' ? 'selected' : ''; ?>>Tên A-Z</option>
                    </select>
                </div>
            </div>
            
            <!-- Search Results -->
            <?php if (!empty($query) || $category > 0 || !empty($price_range)): ?>
                <?php if ($courses): ?>
                <!-- Results Grid -->
                <div class="row">
                    <?php foreach ($courses as $course): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card course-card h-100">
                            <div class="position-relative">
                                <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/300x200?text=' . urlencode($course['title']); ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>"
                                     style="height: 200px; object-fit: cover;">
                                
                                <!-- Price Badge -->
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-<?php echo $course['price'] > 0 ? 'warning' : 'success'; ?> fs-6">
                                        <?php echo formatPrice($course['price']); ?>
                                    </span>
                                </div>
                                
                                <!-- Category Badge -->
                                <div class="position-absolute bottom-0 start-0 m-2">
                                    <span class="badge bg-dark bg-opacity-75">
                                        <?php echo $course['category_name'] ?: 'Khóa học'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <h6 class="card-title mb-2">
                                    <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                                       class="text-decoration-none text-dark">
                                        <?php 
                                        $title = htmlspecialchars($course['title']);
                                        // Highlight search terms
                                        if (!empty($query)) {
                                            $title = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', $title);
                                        }
                                        echo $title;
                                        ?>
                                    </a>
                                </h6>
                                
                                <p class="card-text text-muted small mb-3">
                                    <?php 
                                    $description = htmlspecialchars(substr($course['description'], 0, 100));
                                    if (!empty($query)) {
                                        $description = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', $description);
                                    }
                                    echo $description . (strlen($course['description']) > 100 ? '...' : '');
                                    ?>
                                </p>
                                
                                <!-- Course Stats -->
                                <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                                    <span>
                                        <i class="bi bi-play-circle me-1"></i>
                                        <?php echo $course['lesson_count']; ?> bài học
                                    </span>
                                    <span>
                                        <i class="bi bi-people me-1"></i>
                                        <?php echo $course['student_count']; ?> học viên
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-transparent border-0 pt-0">
                                <div class="d-flex gap-2">
                                    <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm flex-grow-1">
                                        <i class="bi bi-eye me-1"></i>Xem chi tiết
                                    </a>
                                    
                                    <?php if (isLoggedIn()): ?>
                                        <?php if (isEnrolled($_SESSION['user_id'], $course['id'], $pdo)): ?>
                                        <a href="<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course['id']; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="bi bi-play-fill"></i>
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-primary btn-sm" 
                                                onclick="enrollCourse(<?php echo $course['id']; ?>)">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Search results pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Previous -->
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildSearchUrl(['page' => $page - 1]); ?>">
                                <i class="bi bi-chevron-left"></i> Trước
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildSearchUrl(['page' => 1]); ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo buildSearchUrl(['page' => $i]); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildSearchUrl(['page' => $total_pages]); ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Next -->
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildSearchUrl(['page' => $page + 1]); ?>">
                                Sau <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <!-- No Results -->
                <div class="text-center py-5">
                    <i class="bi bi-search display-1 text-muted"></i>
                    <h3 class="mt-3">Không tìm thấy kết quả</h3>
                    <p class="text-muted mb-4">
                        <?php if (!empty($query)): ?>
                        Không tìm thấy khóa học nào phù hợp với "<strong><?php echo htmlspecialchars($query); ?></strong>"
                        <?php else: ?>
                        Không tìm thấy khóa học nào với các bộ lọc đã chọn
                        <?php endif; ?>
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="<?php echo SITE_URL; ?>/search.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-clockwise me-2"></i>Tìm kiếm mới
                        </a>
                        <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-primary">
                            <i class="bi bi-grid me-2"></i>Xem tất cả khóa học
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            
            <?php else: ?>
            <!-- Empty Search State -->
            <div class="text-center py-5">
                <i class="bi bi-search display-1 text-muted"></i>
                <h3 class="mt-3">Tìm kiếm khóa học</h3>
                <p class="text-muted mb-4">Nhập từ khóa hoặc chọn danh mục để tìm kiếm khóa học phù hợp</p>
                
                <!-- Popular Search Terms -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3">Tìm kiếm phổ biến:</h6>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="<?php echo SITE_URL; ?>/search.php?q=php" class="badge bg-light text-dark text-decoration-none px-3 py-2">PHP</a>
                        <a href="<?php echo SITE_URL; ?>/search.php?q=javascript" class="badge bg-light text-dark text-decoration-none px-3 py-2">JavaScript</a>
                        <a href="<?php echo SITE_URL; ?>/search.php?q=python" class="badge bg-light text-dark text-decoration-none px-3 py-2">Python</a>
                        <a href="<?php echo SITE_URL; ?>/search.php?q=design" class="badge bg-light text-dark text-decoration-none px-3 py-2">Design</a>
                        <a href="<?php echo SITE_URL; ?>/search.php?q=marketing" class="badge bg-light text-dark text-decoration-none px-3 py-2">Marketing</a>
                    </div>
                </div>
                
                <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-primary">
                    <i class="bi bi-grid me-2"></i>Xem tất cả khóa học
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.course-card {
    transition: all 0.3s ease;
    border: 1px solid #e0e0e0;
}

.course-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateY(-5px);
}

mark {
    background-color: #fff3cd;
    padding: 2px 4px;
    border-radius: 3px;
}

.badge.bg-light {
    border: 1px solid #dee2e6;
}

.badge.bg-light:hover {
    background-color: #e9ecef !important;
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .course-card {
        margin-bottom: 1rem;
    }
}
</style>

<!-- JavaScript -->
<script>
// Auto-submit sort form
document.getElementById('sortSelect').addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('sort', this.value);
    url.searchParams.delete('page'); // Reset to first page
    window.location.href = url.toString();
});

// Auto-submit filter form on change
document.querySelectorAll('#filterForm input[type="radio"]').forEach(input => {
    input.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// Enroll course function
function enrollCourse(courseId) {
    if (!<?php echo isLoggedIn() ? 'true' : 'false'; ?>) {
        window.location.href = '<?php echo SITE_URL; ?>/login.php';
        return;
    }
    
    // Simple enrollment - in production, you'd use AJAX
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo SITE_URL; ?>/course-detail.php?id=' + courseId;
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'enroll';
    input.value = '1';
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Focus search box on Ctrl+K or Cmd+K
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('input[name="q"]').focus();
    }
    
    // Clear search on Escape
    if (e.key === 'Escape') {
        const searchInput = document.querySelector('input[name="q"]');
        if (document.activeElement === searchInput) {
            searchInput.value = '';
        }
    }
});

// Highlight search terms on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add search functionality hints
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput) {
        searchInput.setAttribute('title', 'Nhấn Ctrl+K để focus, Escape để xóa');
    }
});
</script>

<?php include 'includes/footer.php'; ?>