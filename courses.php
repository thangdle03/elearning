<?php

require_once 'includes/config.php';

$page_title = 'Khóa học';

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category > 0) {
    $where[] = "c.category_id = ?";
    $params[] = $category;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Sort options
$orderBy = match($sort) {
    'oldest' => 'ORDER BY c.created_at ASC',
    'price_low' => 'ORDER BY c.price ASC',
    'price_high' => 'ORDER BY c.price DESC',
    'title' => 'ORDER BY c.title ASC',
    default => 'ORDER BY c.created_at DESC'
};

// Get courses
$sql = "SELECT c.*, cat.name as category_name,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
        FROM courses c 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        $whereClause 
        $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Get total count
$countSql = "SELECT COUNT(*) FROM courses c $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCourses = $countStmt->fetchColumn();
?>

<?php include 'includes/header.php'; ?>

<!-- Page Header -->
<div class="bg-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h2 mb-0">
                    <i class="bi bi-book me-2"></i>Khóa học
                </h1>
                <p class="mb-0 mt-2">Khám phá <?php echo number_format($totalCourses); ?> khóa học chất lượng cao</p>
            </div>
            <div class="col-md-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 justify-content-md-end">
                        <li class="breadcrumb-item">
                            <a href="<?php echo SITE_URL; ?>" class="text-white-50">Trang chủ</a>
                        </li>
                        <li class="breadcrumb-item active text-white">Khóa học</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Section -->
<div class="bg-light py-4">
    <div class="container">
        <form method="GET" class="row g-3">
            <!-- Search Box -->
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" 
                           placeholder="Tìm kiếm khóa học..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <!-- Category Filter -->
            <div class="col-md-3">
                <select class="form-select" name="category">
                    <option value="0">Tất cả danh mục</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" 
                            <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Sort Options -->
            <div class="col-md-3">
                <select class="form-select" name="sort">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                    <option value="title" <?php echo $sort == 'title' ? 'selected' : ''; ?>>Theo tên A-Z</option>
                    <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Giá thấp → cao</option>
                    <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Giá cao → thấp</option>
                </select>
            </div>
            
            <!-- Search Button -->
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Lọc
                </button>
            </div>
        </form>
        
        <!-- Active Filters -->
        <?php if ($search || $category > 0): ?>
        <div class="mt-3">
            <span class="text-muted me-2">Bộ lọc đang áp dụng:</span>
            <?php if ($search): ?>
            <span class="badge bg-primary me-2">
                Tìm kiếm: "<?php echo htmlspecialchars($search); ?>"
                <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" 
                   class="text-white ms-1">×</a>
            </span>
            <?php endif; ?>
            
            <?php if ($category > 0): ?>
            <?php 
            $selectedCat = array_filter($categories, fn($c) => $c['id'] == $category);
            $selectedCat = reset($selectedCat);
            ?>
            <span class="badge bg-secondary me-2">
                Danh mục: <?php echo $selectedCat['name']; ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => '0'])); ?>" 
                   class="text-white ms-1">×</a>
            </span>
            <?php endif; ?>
            
            <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle me-1"></i>Xóa tất cả bộ lọc
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Courses Grid -->
<div class="container my-5">
    <?php if ($courses): ?>
    <!-- Results Info -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="text-muted mb-0">
            Hiển thị <strong><?php echo count($courses); ?></strong> khóa học
            <?php if ($search || $category > 0): ?>
            từ <strong><?php echo number_format($totalCourses); ?></strong> kết quả tìm thấy
            <?php endif; ?>
        </p>
        
        <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="view" id="grid-view" checked>
            <label class="btn btn-outline-secondary" for="grid-view">
                <i class="bi bi-grid"></i>
            </label>
            <input type="radio" class="btn-check" name="view" id="list-view">
            <label class="btn btn-outline-secondary" for="list-view">
                <i class="bi bi-list"></i>
            </label>
        </div>
    </div>
    
    <!-- Courses Grid -->
    <div class="row" id="courses-container">
        <?php foreach ($courses as $course): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card course-card h-100 shadow-sm">
                <div class="position-relative">
                    <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/300x200?text=' . urlencode($course['title']); ?>" 
                         class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>"
                         style="height: 200px; object-fit: cover;">
                    
                    <?php if ($course['price'] == 0): ?>
                    <span class="position-absolute top-0 end-0 badge bg-success m-2">
                        <i class="bi bi-gift me-1"></i>Miễn phí
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body d-flex flex-column">
                    <!-- Category Badge -->
                    <div class="mb-2">
                        <span class="badge bg-secondary">
                            <?php echo $course['category_name'] ?: 'Chưa phân loại'; ?>
                        </span>
                    </div>
                    
                    <!-- Course Title -->
                    <h5 class="card-title">
                        <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                           class="text-decoration-none text-dark">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </a>
                    </h5>
                    
                    <!-- Course Description -->
                    <p class="card-text text-muted flex-grow-1">
                        <?php echo htmlspecialchars(substr($course['description'], 0, 120)) . '...'; ?>
                    </p>
                    
                    <!-- Course Stats -->
                    <div class="d-flex justify-content-between text-muted small mb-3">
                        <span>
                            <i class="bi bi-play-circle me-1"></i>
                            <?php echo $course['lesson_count']; ?> bài học
                        </span>
                        <span>
                            <i class="bi bi-people me-1"></i>
                            <?php echo $course['student_count']; ?> học viên
                        </span>
                    </div>
                    
                    <!-- Price and Action -->
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <h6 class="text-primary mb-0 fw-bold">
                            <?php echo formatPrice($course['price']); ?>
                        </h6>
                        <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                           class="btn btn-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php else: ?>
    <!-- No Results -->
    <div class="text-center py-5">
        <i class="bi bi-search display-1 text-muted"></i>
        <h3 class="mt-3">Không tìm thấy khóa học nào</h3>
        <p class="text-muted">
            <?php if ($search || $category > 0): ?>
            Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc khác.
            <?php else: ?>
            Hiện tại chưa có khóa học nào. Vui lòng quay lại sau.
            <?php endif; ?>
        </p>
        
        <?php if ($search || $category > 0): ?>
        <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-primary">
            <i class="bi bi-arrow-left me-2"></i>Xem tất cả khóa học
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript for view switching -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const gridView = document.getElementById('grid-view');
    const listView = document.getElementById('list-view');
    const container = document.getElementById('courses-container');
    
    listView.addEventListener('change', function() {
        if (this.checked) {
            container.className = 'row';
            const cards = container.querySelectorAll('.col-md-6.col-lg-4');
            cards.forEach(card => {
                card.className = 'col-12 mb-3';
                const cardElement = card.querySelector('.card');
                cardElement.className = 'card h-auto shadow-sm';
                cardElement.innerHTML = cardElement.innerHTML.replace('h-100', 'h-auto');
            });
        }
    });
    
    gridView.addEventListener('change', function() {
        if (this.checked) {
            location.reload(); // Simple reload for grid view
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>