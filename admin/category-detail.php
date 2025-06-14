<?php
// filepath: d:\Xampp\htdocs\elearning\admin\category-detail.php
require_once '../includes/config.php';

// Check authentication
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Get category ID
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($category_id <= 0) {
    header('Location: categories.php');
    exit;
}

// Get category info
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch();

if (!$category) {
    header('Location: categories.php');
    exit;
}

// Get courses in this category - không JOIN với users vì không có instructor_id
$stmt = $pdo->prepare("
    SELECT * 
    FROM courses 
    WHERE category_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$category_id]);
$courses = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_courses,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_courses,
        AVG(price) as avg_price
    FROM courses 
    WHERE category_id = ?
");
$stmt->execute([$category_id]);
$stats = $stmt->fetch();

$page_title = 'Chi tiết danh mục: ' . $category['name'];
$current_page = 'categories';
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo htmlspecialchars($category['name']); ?></h1>

    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="categories.php">Danh mục</a></li>
        <li class="breadcrumb-item active"><?php echo htmlspecialchars($category['name']); ?></li>
    </ol>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Tổng khóa học</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_courses']); ?></div>
                        </div>
                        <div class="fa-2x">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Khóa học hoạt động</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['active_courses']); ?></div>
                        </div>
                        <div class="fa-2x">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Giá trung bình</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo $stats['avg_price'] > 0 ? number_format($stats['avg_price']) . ' VNĐ' : 'Miễn phí'; ?>
                            </div>
                        </div>
                        <div class="fa-2x">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Trạng thái</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo $category['status'] == 'active' ? 'Hoạt động' : 'Không hoạt động'; ?>
                            </div>
                        </div>
                        <div class="fa-2x">
                            <i class="fas fa-toggle-<?php echo $category['status'] == 'active' ? 'on' : 'off'; ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Info -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-info-circle me-1"></i>
            Thông tin danh mục
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Tên danh mục:</strong> <?php echo htmlspecialchars($category['name']); ?></p>
                            <p><strong>Slug:</strong> <?php echo htmlspecialchars($category['slug'] ?? 'N/A'); ?></p>
                            <p><strong>Trạng thái:</strong>
                                <span class="badge <?php echo $category['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $category['status'] == 'active' ? 'Hoạt động' : 'Không hoạt động'; ?>
                                </span>
                            </p>
                            <p><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($category['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Mô tả:</strong></p>
                            <div class="border p-3 bg-light rounded">
                                <?php echo !empty($category['description']) ? nl2br(htmlspecialchars($category['description'])) : '<i class="text-muted">Chưa có mô tả</i>'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-grid gap-2">
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
                        </a>
                        <a href="edit-category.php?id=<?php echo $category['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i>Chỉnh sửa danh mục
                        </a>
                        <a href="add-course.php?category=<?php echo $category['id']; ?>" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>Thêm khóa học mới
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Courses List -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-book me-1"></i>
            Danh sách khóa học trong danh mục (<?php echo count($courses); ?>)
        </div>
        <div class="card-body">
            <?php if (count($courses) > 0): ?>
                <div class="row">
                    <?php foreach ($courses as $course): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border-0 shadow-sm course-card">
                                <!-- Thêm link wrapper cho toàn bộ card -->
                                <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="text-decoration-none">
                                    <?php if (!empty($course['thumbnail'])): ?>
                                        <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" 
                                             class="card-img-top" style="height: 200px; object-fit: cover;" alt="">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                             style="height: 200px;">
                                            <i class="fas fa-image fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="course-detail.php?id=<?php echo $course['id']; ?>" 
                                           class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </a>
                                    </h5>
                                    
                                    <?php if (!empty($course['description'])): ?>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(mb_substr($course['description'], 0, 100)); ?>
                                            <?php if (mb_strlen($course['description']) > 100) echo '...'; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge <?php echo $course['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $course['status'] === 'active' ? 'Hoạt động' : 'Tạm dừng'; ?>
                                            </span>
                                        </div>
                                        <div class="btn-group" role="group">
                                            <a href="course-detail.php?id=<?php echo $course['id']; ?>" 
                                               class="btn btn-sm btn-outline-info" title="Xem chi tiết">
                                                <i class="fas fa-eye me-1"></i>Xem
                                            </a>
                                            <a href="edit-course.php?id=<?php echo $course['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Chỉnh sửa">
                                                <i class="fas fa-edit me-1"></i>Sửa
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('d/m/Y', strtotime($course['created_at'])); ?>
                                        </small>
                                        <strong class="text-primary">
                                            <?php echo $course['price'] > 0 ? number_format($course['price']) . 'đ' : 'Miễn phí'; ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-book fa-5x text-muted opacity-50"></i>
                    </div>
                    <h4 class="text-muted mb-3">Chưa có khóa học nào</h4>
                    <p class="text-muted mb-4">
                        Danh mục "<strong><?php echo htmlspecialchars($category['name']); ?></strong>" chưa có khóa học nào.<br>
                        Hãy thêm khóa học đầu tiên để bắt đầu.
                    </p>
                    <a href="add-course.php?category=<?php echo $category['id']; ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus me-2"></i>Thêm khóa học đầu tiên
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .card {
        border: none;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        margin-bottom: 1.5rem;
    }

    .card-header {
        background-color: #f8f9fc;
        border-bottom: 1px solid #e3e6f0;
        font-weight: 600;
        color: #5a5c69;
    }

    .table th {
        border-top: none;
        font-weight: 600;
        color: #5a5c69;
        font-size: 0.9rem;
        background-color: #f8f9fc;
    }

    .table td {
        vertical-align: middle;
        font-size: 0.9rem;
        border-color: #e3e6f0;
    }

    .table tbody tr:hover {
        background-color: #f8f9fc;
    }

    .course-thumbnail img {
        transition: transform 0.2s ease;
        border: 2px solid #e3e6f0;
    }

    .course-thumbnail img:hover {
        transform: scale(1.05);
        border-color: #4e73df;
    }

    .btn-group-vertical .btn {
        border-radius: 0.25rem !important;
        margin-bottom: 0.25rem;
    }

    .btn-group-vertical .btn:last-child {
        margin-bottom: 0;
    }

    .statistics-card {
        transition: transform 0.2s ease;
    }

    .statistics-card:hover {
        transform: translateY(-2px);
    }

    .breadcrumb-item a {
        text-decoration: none;
        color: #6c757d;
    }

    .breadcrumb-item a:hover {
        color: #4e73df;
    }

    .course-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .course-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15) !important;
    }

    .course-card a:hover {
        text-decoration: none !important;
    }
</style>

<?php include 'includes/admin-footer.php'; ?>