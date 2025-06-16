<?php
// filepath: d:\Xampp\htdocs\elearning\admin\course-reviews.php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

// Get course ID
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($course_id <= 0) {
    redirect('courses.php');
}

// Get course info
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect('courses.php');
}

$page_title = 'Quản lý đánh giá: ' . $course['title'];
$current_page = 'courses';

// Initialize variables
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selected_reviews = $_POST['selected_reviews'] ?? [];

        if (empty($selected_reviews)) {
            $error = 'Vui lòng chọn ít nhất một đánh giá!';
        } else {
            try {
                $pdo->beginTransaction();

                switch ($action) {
                    case 'approve':
                        $placeholders = str_repeat('?,', count($selected_reviews) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE reviews SET status = 'active' WHERE id IN ($placeholders) AND course_id = ?");
                        $stmt->execute(array_merge($selected_reviews, [$course_id]));

                        $updated_count = $stmt->rowCount();
                        $_SESSION['review_success'] = "✅ Đã duyệt $updated_count đánh giá thành công!";
                        break;

                    case 'reject':
                        $placeholders = str_repeat('?,', count($selected_reviews) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE reviews SET status = 'inactive' WHERE id IN ($placeholders) AND course_id = ?");
                        $stmt->execute(array_merge($selected_reviews, [$course_id]));

                        $updated_count = $stmt->rowCount();
                        $_SESSION['review_success'] = "✅ Đã từ chối $updated_count đánh giá thành công!";
                        break;

                    case 'delete':
                        $placeholders = str_repeat('?,', count($selected_reviews) - 1) . '?';
                        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id IN ($placeholders) AND course_id = ?");
                        $stmt->execute(array_merge($selected_reviews, [$course_id]));

                        $deleted_count = $stmt->rowCount();
                        $_SESSION['review_success'] = "✅ Đã xóa $deleted_count đánh giá thành công!";
                        break;
                }

                $pdo->commit();
                redirect($_SERVER['PHP_SELF'] . '?course_id=' . $course_id);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }

    if (isset($_POST['update_review'])) {
        try {
            $review_id = (int)$_POST['review_id'];
            $status = $_POST['status'];
            $admin_response = trim($_POST['admin_response'] ?? '');

            if (!in_array($status, ['active', 'inactive', 'pending'])) {
                throw new Exception('Trạng thái không hợp lệ!');
            }

            $stmt = $pdo->prepare("
                UPDATE reviews 
                SET status = ?, admin_response = ?, updated_at = NOW() 
                WHERE id = ? AND course_id = ?
            ");
            $stmt->execute([$status, $admin_response, $review_id, $course_id]);

            $_SESSION['review_success'] = '✅ Đã cập nhật đánh giá thành công!';
            redirect($_SERVER['PHP_SELF'] . '?course_id=' . $course_id);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Handle success messages
if (isset($_SESSION['review_success'])) {
    $message = $_SESSION['review_success'];
    unset($_SESSION['review_success']);
}

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$rating_filter = $_GET['rating'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ["course_id = ?"];
$params = [$course_id];

if (!empty($search)) {
    $where_conditions[] = "(comment LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if (!empty($rating_filter)) {
    $where_conditions[] = "r.rating = ?";
    $params[] = (int)$rating_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Sort options
$order_clause = match ($sort) {
    'oldest' => 'ORDER BY r.created_at ASC',
    'rating_high' => 'ORDER BY r.rating DESC, r.created_at DESC',
    'rating_low' => 'ORDER BY r.rating ASC, r.created_at DESC',
    'username' => 'ORDER BY u.username ASC',
    default => 'ORDER BY r.created_at DESC'
};

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    $where_clause
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = (int)$stmt->fetchColumn();
$total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

// Get reviews
$sql = "
    SELECT r.*, u.username, u.email
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    $where_clause
    $order_clause
    LIMIT $offset, $limit
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Get course statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reviews,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_reviews,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reviews,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_reviews,
        COALESCE(AVG(CASE WHEN status = 'active' THEN rating END), 0) as avg_rating,
        COUNT(DISTINCT user_id) as unique_reviewers
    FROM reviews 
    WHERE course_id = ?
");
$stmt->execute([$course_id]);
$stats = $stmt->fetch();

// Get rating distribution
$stmt = $pdo->prepare("
    SELECT rating, COUNT(*) as count 
    FROM reviews 
    WHERE course_id = ? AND status = 'active'
    GROUP BY rating 
    ORDER BY rating DESC
");
$stmt->execute([$course_id]);
$rating_distribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <a href="courses.php"><i class="fas fa-book me-1"></i>Khóa học</a>
            </li>
            <li class="breadcrumb-item">
                <a href="course-detail.php?id=<?php echo $course['id']; ?>">
                    <?php echo htmlspecialchars($course['title']); ?>
                </a>
            </li>
            <li class="breadcrumb-item active">Quản lý đánh giá</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-star me-2"></i>Quản lý đánh giá
            </h1>
            <p class="mb-0 text-muted">
                Khóa học: <strong><?php echo htmlspecialchars($course['title']); ?></strong>
            </p>
        </div>
        <div class="btn-group">
            <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại chi tiết
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewStatsModal">
                <i class="fas fa-chart-bar me-2"></i>Thống kê chi tiết
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Tổng đánh giá
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_reviews']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-comments fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Đã duyệt
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['active_reviews']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Chờ duyệt
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['pending_reviews']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Bị từ chối
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['inactive_reviews']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Điểm TB
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['avg_rating'], 1); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-star fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Người đánh giá
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['unique_reviewers']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter me-2"></i>Bộ lọc và tìm kiếm
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">

                <div class="col-md-3">
                    <label class="form-label">Tìm kiếm</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                            placeholder="Tên người dùng, email, nội dung..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <?php if (!empty($search)): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Đã duyệt</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Chờ duyệt</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Bị từ chối</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Điểm đánh giá</label>
                    <select name="rating" class="form-select">
                        <option value="">Tất cả</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $rating_filter == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> sao
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Sắp xếp theo</label>
                    <select name="sort" class="form-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                        <option value="rating_high" <?php echo $sort === 'rating_high' ? 'selected' : ''; ?>>Điểm cao</option>
                        <option value="rating_low" <?php echo $sort === 'rating_low' ? 'selected' : ''; ?>>Điểm thấp</option>
                        <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Tên A-Z</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Tìm kiếm
                    </button>
                    <a href="?course_id=<?php echo $course_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-refresh me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Reviews Management -->
    <div class="card shadow">
        <div class="card-header py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list me-2"></i>Danh sách đánh giá
                    <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
                </h6>

                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-info" onclick="selectAll()" id="selectAllBtn">
                        <i class="fas fa-check-square me-1"></i>Chọn tất cả
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (!empty($reviews)): ?>
                <form method="POST" id="reviewManagementForm">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">

                    <!-- Bulk Actions -->
                    <div class="bulk-actions p-3 border-bottom bg-light" style="display: none;">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <select name="bulk_action" class="form-select form-select-sm" required>
                                    <option value="">-- Chọn hành động --</option>
                                    <option value="approve">✅ Duyệt các đánh giá</option>
                                    <option value="reject">❌ Từ chối các đánh giá</option>
                                    <option value="delete">🗑️ Xóa các đánh giá</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Bạn có chắc chắn?')">
                                    <i class="fas fa-play me-1"></i>Thực hiện
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="cancelBulkAction()">
                                    <i class="fas fa-times me-1"></i>Hủy
                                </button>
                            </div>
                            <div class="col-md-4 text-end">
                                <small class="text-muted">
                                    <span id="selectedCount">0</span> đánh giá được chọn
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">
                                        <input type="checkbox" id="masterCheckbox" onchange="toggleAllCheckboxes()">
                                    </th>
                                    <th width="15%">Người đánh giá</th>
                                    <th width="10%" class="text-center">Điểm</th>
                                    <th width="35%">Nội dung</th>
                                    <th width="12%" class="text-center">Trạng thái</th>
                                    <th width="13%" class="text-center">Ngày tạo</th>
                                    <th width="10%" class="text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                    <tr>
                                        <td class="align-middle">
                                            <input type="checkbox" name="selected_reviews[]" value="<?php echo $review['id']; ?>"
                                                class="review-checkbox" onchange="updateBulkActions()">
                                        </td>

                                        <td class="align-middle">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2"
                                                    style="width: 35px; height: 35px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($review['username']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($review['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="text-center align-middle">
                                            <div class="rating-display">
                                                <div class="h5 mb-0 text-warning">
                                                    <?php echo $review['rating']; ?>
                                                    <i class="fas fa-star"></i>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $review['rating']) {
                                                            echo '<i class="fas fa-star text-warning"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star text-muted"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="align-middle">
                                            <div class="review-comment">
                                                <p class="mb-2">
                                                    <?php
                                                    $comment = htmlspecialchars($review['comment']);
                                                    echo mb_strlen($comment) > 120 ? mb_substr($comment, 0, 120) . '...' : $comment;
                                                    ?>
                                                </p>

                                                <?php if (!empty($review['admin_response'])): ?>
                                                    <div class="alert alert-info py-2 small mb-0">
                                                        <i class="fas fa-reply me-1"></i>
                                                        <strong>Phản hồi Admin:</strong>
                                                        <?php echo htmlspecialchars($review['admin_response']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td class="text-center align-middle">
                                            <?php
                                            $status_config = [
                                                'active' => ['badge' => 'bg-success', 'icon' => 'check-circle', 'text' => 'Đã duyệt'],
                                                'pending' => ['badge' => 'bg-warning', 'icon' => 'clock', 'text' => 'Chờ duyệt'],
                                                'inactive' => ['badge' => 'bg-danger', 'icon' => 'times-circle', 'text' => 'Bị từ chối']
                                            ];
                                            $status = $status_config[$review['status']] ?? $status_config['pending'];
                                            ?>
                                            <span class="badge <?php echo $status['badge']; ?>">
                                                <i class="fas fa-<?php echo $status['icon']; ?> me-1"></i>
                                                <?php echo $status['text']; ?>
                                            </span>
                                        </td>

                                        <td class="text-center align-middle">
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($review['created_at'])); ?>
                                                <br>
                                                <?php echo date('H:i', strtotime($review['created_at'])); ?>
                                            </small>
                                        </td>

                                        <td class="text-center align-middle">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="showReviewModal(<?php echo htmlspecialchars(json_encode($review)); ?>)"
                                                    title="Xem chi tiết & phản hồi">
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                <?php if ($review['status'] !== 'active'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success"
                                                        onclick="quickAction(<?php echo $review['id']; ?>, 'approve')"
                                                        title="Duyệt">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($review['status'] !== 'inactive'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="quickAction(<?php echo $review['id']; ?>, 'reject')"
                                                        title="Từ chối">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center p-3 border-top">
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?course_id=<?php echo $course_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&rating=<?php echo $rating_filter; ?>&sort=<?php echo $sort; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?course_id=<?php echo $course_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&rating=<?php echo $rating_filter; ?>&sort=<?php echo $sort; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?course_id=<?php echo $course_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&rating=<?php echo $rating_filter; ?>&sort=<?php echo $sort; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-star fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">Không tìm thấy đánh giá nào</h4>
                    <p class="text-muted mb-4">
                        <?php if (!empty($search) || !empty($status_filter) || !empty($rating_filter)): ?>
                            Không có đánh giá nào phù hợp với bộ lọc hiện tại.
                        <?php else: ?>
                            Khóa học này chưa có đánh giá nào từ học viên.
                        <?php endif; ?>
                    </p>
                    <a href="?course_id=<?php echo $course_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-refresh me-2"></i>Làm mới
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Review Detail Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-star me-2"></i>Chi tiết đánh giá
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="reviewUpdateForm">
                    <input type="hidden" name="review_id" id="modal_review_id">
                    <input type="hidden" name="update_review" value="1">

                    <!-- Review Info -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Người đánh giá:</label>
                            <div id="modal_reviewer_info"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Ngày đánh giá:</label>
                            <div id="modal_review_date"></div>
                        </div>
                    </div>

                    <!-- Rating -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Điểm đánh giá:</label>
                        <div id="modal_rating_display"></div>
                    </div>

                    <!-- Comment -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nội dung đánh giá:</label>
                        <div id="modal_comment" class="bg-light p-3 rounded"></div>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="modal_status" class="form-label fw-bold">Trạng thái:</label>
                        <select name="status" id="modal_status" class="form-select">
                            <option value="pending">⏳ Chờ duyệt</option>
                            <option value="active">✅ Đã duyệt</option>
                            <option value="inactive">❌ Bị từ chối</option>
                        </select>
                    </div>

                    <!-- Admin Response -->
                    <div class="mb-3">
                        <label for="modal_admin_response" class="form-label fw-bold">Phản hồi của Admin:</label>
                        <textarea name="admin_response" id="modal_admin_response" class="form-control" rows="3"
                            placeholder="Nhập phản hồi cho học viên (tùy chọn)..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" form="reviewUpdateForm" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Cập nhật
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Modal -->
<div class="modal fade" id="reviewStatsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-chart-bar me-2"></i>Thống kê đánh giá chi tiết
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Rating Distribution -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-3">Phân bố điểm đánh giá</h6>
                    <?php
                    $total_active = array_sum($rating_distribution);
                    for ($i = 5; $i >= 1; $i--):
                        $count = $rating_distribution[$i] ?? 0;
                        $percentage = $total_active > 0 ? ($count / $total_active) * 100 : 0;
                    ?>
                        <div class="row align-items-center mb-2">
                            <div class="col-2">
                                <span class="fw-bold"><?php echo $i; ?> sao</span>
                            </div>
                            <div class="col-8">
                                <div class="progress">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-2 text-end">
                                <small class="text-muted"><?php echo $count; ?> (<?php echo number_format($percentage, 1); ?>%)</small>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Summary Stats -->
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="border rounded p-3">
                            <h3 class="text-primary"><?php echo number_format($stats['avg_rating'], 1); ?></h3>
                            <small class="text-muted">Điểm trung bình</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3">
                            <h3 class="text-success"><?php echo $stats['active_reviews']; ?></h3>
                            <small class="text-muted">Đánh giá hiển thị</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3">
                            <h3 class="text-warning"><?php echo $stats['pending_reviews']; ?></h3>
                            <small class="text-muted">Chờ duyệt</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3">
                            <h3 class="text-info"><?php echo $stats['unique_reviewers']; ?></h3>
                            <small class="text-muted">Người đánh giá</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Copy styles from course-lessons.php for consistency */
    .border-left-primary {
        border-left: 0.375rem solid #6366f1 !important;
    }

    .border-left-success {
        border-left: 0.375rem solid #10b981 !important;
    }

    .border-left-info {
        border-left: 0.375rem solid #06b6d4 !important;
    }

    .border-left-warning {
        border-left: 0.375rem solid #f59e0b !important;
    }

    .border-left-danger {
        border-left: 0.375rem solid #ef4444 !important;
    }

    .border-left-secondary {
        border-left: 0.375rem solid #6b7280 !important;
    }

    .card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    .table tbody tr:hover {
        background-color: #f8fafc;
    }

    .bulk-actions {
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .btn {
        border-radius: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .review-comment {
        max-width: 350px;
    }

    .rating-display .fas.fa-star {
        font-size: 0.75rem;
    }
</style>

<script>
    // Global variables
    let selectedReviews = [];

    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ Course reviews management loaded');

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                try {
                    new bootstrap.Alert(alert).close();
                } catch (e) {
                    console.log('Alert already closed');
                }
            });
        }, 5000);
    });

    // Show review detail modal
    function showReviewModal(review) {
        console.log('📖 Showing review modal:', review);

        // Fill modal data
        document.getElementById('modal_review_id').value = review.id;
        document.getElementById('modal_reviewer_info').innerHTML =
            `<strong>${review.username}</strong><br><small class="text-muted">${review.email}</small>`;
        document.getElementById('modal_review_date').textContent =
            new Date(review.created_at).toLocaleDateString('vi-VN');

        // Rating display
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            starsHtml += i <= review.rating ?
                '<i class="fas fa-star text-warning"></i> ' :
                '<i class="far fa-star text-muted"></i> ';
        }
        document.getElementById('modal_rating_display').innerHTML =
            `<span class="h5 text-warning">${review.rating}/5</span> ${starsHtml}`;

        document.getElementById('modal_comment').textContent = review.comment || 'Không có nội dung';
        document.getElementById('modal_status').value = review.status;
        document.getElementById('modal_admin_response').value = review.admin_response || '';

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
        modal.show();
    }

    // Quick action (approve/reject)
    function quickAction(reviewId, action) {
        const actionText = action === 'approve' ? 'duyệt' : 'từ chối';

        if (!confirm(`Bạn có chắc chắn muốn ${actionText} đánh giá này?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('update_review', '1');
        formData.append('review_id', reviewId);
        formData.append('status', action === 'approve' ? 'active' : 'inactive');

        fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    alert('❌ Có lỗi xảy ra!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Lỗi kết nối!');
            });
    }

    // Select all reviews
    function selectAll() {
        const checkboxes = document.querySelectorAll('.review-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);

        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });

        updateBulkActions();

        const btn = document.getElementById('selectAllBtn');
        if (allChecked) {
            btn.innerHTML = '<i class="fas fa-check-square me-1"></i>Chọn tất cả';
        } else {
            btn.innerHTML = '<i class="fas fa-square me-1"></i>Bỏ chọn tất cả';
        }
    }

    // Toggle all checkboxes
    function toggleAllCheckboxes() {
        const masterCheckbox = document.getElementById('masterCheckbox');
        const checkboxes = document.querySelectorAll('.review-checkbox');

        checkboxes.forEach(cb => {
            cb.checked = masterCheckbox.checked;
        });

        updateBulkActions();
    }

    // Update bulk actions
    function updateBulkActions() {
        const checkboxes = document.querySelectorAll('.review-checkbox:checked');
        const bulkActions = document.querySelector('.bulk-actions');
        const selectedCount = document.getElementById('selectedCount');

        if (checkboxes.length > 0) {
            bulkActions.style.display = 'block';
            selectedCount.textContent = checkboxes.length;
        } else {
            bulkActions.style.display = 'none';
        }
    }

    // Cancel bulk action
    function cancelBulkAction() {
        const checkboxes = document.querySelectorAll('.review-checkbox');
        const masterCheckbox = document.getElementById('masterCheckbox');

        checkboxes.forEach(cb => cb.checked = false);
        masterCheckbox.checked = false;

        updateBulkActions();
    }

    // Clear search
    function clearSearch() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.value = '';
            searchInput.form.submit();
        }
    }
</script>

<?php include 'includes/admin-footer.php'; ?>