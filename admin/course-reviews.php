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
    if (isset($_POST['action'])) {
        $review_id = (int)$_POST['review_id'];
        $action = $_POST['action'];

        try {
            switch ($action) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved', admin_id = ?, admin_responded_at = NOW() WHERE id = ? AND course_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $review_id, $course_id]);
                    $message = "Đánh giá đã được phê duyệt thành công!";
                    break;

                case 'reject':
                    $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected', admin_id = ?, admin_responded_at = NOW() WHERE id = ? AND course_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $review_id, $course_id]);
                    $message = "Đánh giá đã bị từ chối!";
                    break;

                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND course_id = ?");
                    $stmt->execute([$review_id, $course_id]);
                    $message = "Đánh giá đã được xóa khỏi hệ thống!";
                    break;

                case 'respond':
                    $response = trim($_POST['admin_response']);
                    if ($response) {
                        $stmt = $pdo->prepare("UPDATE reviews SET admin_response = ?, admin_id = ?, admin_responded_at = NOW() WHERE id = ? AND course_id = ?");
                        $stmt->execute([$response, $_SESSION['user_id'], $review_id, $course_id]);
                        $message = "Phản hồi đã được gửi thành công!";
                    } else {
                        $error = "Vui lòng nhập nội dung phản hồi!";
                    }
                    break;

                case 'bulk_approve':
                    if (isset($_POST['selected_reviews']) && is_array($_POST['selected_reviews'])) {
                        $placeholders = str_repeat('?,', count($_POST['selected_reviews']) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved', admin_id = ?, admin_responded_at = NOW() WHERE id IN ($placeholders) AND course_id = ?");
                        $params = array_merge([$_SESSION['user_id']], $_POST['selected_reviews'], [$course_id]);
                        $stmt->execute($params);
                        $message = "Đã phê duyệt " . count($_POST['selected_reviews']) . " đánh giá!";
                    }
                    break;

                case 'bulk_reject':
                    if (isset($_POST['selected_reviews']) && is_array($_POST['selected_reviews'])) {
                        $placeholders = str_repeat('?,', count($_POST['selected_reviews']) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected', admin_id = ?, admin_responded_at = NOW() WHERE id IN ($placeholders) AND course_id = ?");
                        $params = array_merge([$_SESSION['user_id']], $_POST['selected_reviews'], [$course_id]);
                        $stmt->execute($params);
                        $message = "Đã từ chối " . count($_POST['selected_reviews']) . " đánh giá!";
                    }
                    break;

                case 'bulk_delete':
                    if (isset($_POST['selected_reviews']) && is_array($_POST['selected_reviews'])) {
                        $placeholders = str_repeat('?,', count($_POST['selected_reviews']) - 1) . '?';
                        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id IN ($placeholders) AND course_id = ?");
                        $params = array_merge($_POST['selected_reviews'], [$course_id]);
                        $stmt->execute($params);
                        $message = "Đã xóa " . count($_POST['selected_reviews']) . " đánh giá!";
                    }
                    break;

                case 'bulk_export':
                    if (isset($_POST['selected_reviews']) && is_array($_POST['selected_reviews'])) {
                        $placeholders = str_repeat('?,', count($_POST['selected_reviews']) - 1) . '?';
                        $stmt = $pdo->prepare("
                            SELECT r.*, u.username, u.email, admin.username as admin_name
                            FROM reviews r 
                            JOIN users u ON r.user_id = u.id 
                            LEFT JOIN users admin ON r.admin_id = admin.id
                            WHERE r.id IN ($placeholders) AND r.course_id = ?
                            ORDER BY r.created_at DESC
                        ");
                        $params = array_merge($_POST['selected_reviews'], [$course_id]);
                        $stmt->execute($params);
                        $export_data = $stmt->fetchAll();

                        // Export to CSV
                        header('Content-Type: text/csv; charset=UTF-8');
                        header('Content-Disposition: attachment; filename="danh-gia-khoa-hoc-' . $course['id'] . '-' . date('Y-m-d') . '.csv"');
                        header('Pragma: no-cache');
                        header('Expires: 0');

                        echo "\xEF\xBB\xBF";

                        $output = fopen('php://output', 'w');
                        fputcsv($output, [
                            'ID',
                            'Người đánh giá',
                            'Email',
                            'Điểm số',
                            'Nội dung',
                            'Trạng thái',
                            'Ngày tạo',
                            'Admin xử lý',
                            'Phản hồi Admin'
                        ]);

                        foreach ($export_data as $row) {
                            $status_text = [
                                'approved' => 'Đã duyệt',
                                'pending' => 'Chờ duyệt',
                                'rejected' => 'Từ chối'
                            ];

                            fputcsv($output, [
                                $row['id'],
                                $row['username'],
                                $row['email'],
                                $row['rating'] . '/5',
                                $row['comment'],
                                $status_text[$row['status']] ?? $row['status'],
                                date('d/m/Y H:i', strtotime($row['created_at'])),
                                $row['admin_name'] ?? '',
                                $row['admin_response'] ?? ''
                            ]);
                        }

                        fclose($output);
                        exit;
                    }
                    break;
            }

            // Redirect để tránh resubmit
            header('Location: ' . $_SERVER['PHP_SELF'] . '?course_id=' . $course_id . '&success=1');
            exit;
        } catch (PDOException $e) {
            $error = "Có lỗi xảy ra: " . $e->getMessage();
        }
    }
}

// Handle success message
if (isset($_GET['success'])) {
    $message = "Thao tác đã được thực hiện thành công!";
}

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));

$requested_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($requested_limit, [5, 10, 20, 50]) ? $requested_limit : 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ["r.course_id = ?"];
$params = [$course_id];

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR r.comment LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($rating_filter > 0) {
    $where_conditions[] = "r.rating = ?";
    $params[] = $rating_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(r.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(r.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Sort options
$order_clause = match ($sort) {
    'oldest' => 'ORDER BY r.created_at ASC',
    'rating_high' => 'ORDER BY r.rating DESC, r.created_at DESC',
    'rating_low' => 'ORDER BY r.rating ASC, r.created_at DESC',
    'name_asc' => 'ORDER BY u.username ASC',
    'name_desc' => 'ORDER BY u.username DESC',
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
    SELECT r.*, u.username, u.email, u.full_name, u.avatar,
           admin.username as admin_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users admin ON r.admin_id = admin.id
    $where_clause
    $order_clause
    LIMIT $offset, $limit
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Get course review statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reviews,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        AVG(rating) as avg_rating,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_count
    FROM reviews 
    WHERE course_id = ?
");
$stmt->execute([$course_id]);
$stats = $stmt->fetch();

// Check if any filter is active
$has_filters = !empty($search) || !empty($status_filter) || ($rating_filter > 0) || !empty($date_from) || !empty($date_to) || ($sort !== 'newest');
?>

<?php include 'includes/admin-header.php'; ?>

<style>
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

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

    .rating-stars {
        color: #ffc107;
    }

    .admin-reply {
        background: #f8f9fa;
        border-left: 3px solid #007bff;
        padding: 10px;
        margin-top: 10px;
        border-radius: 4px;
        font-size: 0.9em;
    }
</style>

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
                <i class="fas fa-star text-warning me-2"></i>Quản lý đánh giá
            </h1>
            <p class="mb-0 text-muted">
                Khóa học: <strong><?php echo htmlspecialchars($course['title']); ?></strong>
            </p>
        </div>
        <div class="btn-group">
            <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại chi tiết
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
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
                            <i class="fas fa-star fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Chờ duyệt
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['pending']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Đã duyệt
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['approved']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Điểm TB
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['avg_rating'], 1); ?>/5
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
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
            <form method="GET" class="row g-3" id="filterForm">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <input type="hidden" name="limit" value="<?php echo $limit; ?>">

                <div class="col-md-3">
                    <label class="form-label">Tìm kiếm</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                            placeholder="Tên người dùng, nội dung..."
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
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Chờ duyệt</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Đã duyệt</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Từ chối</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Đánh giá</label>
                    <select name="rating" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="5" <?php echo $rating_filter === 5 ? 'selected' : ''; ?>>5 sao</option>
                        <option value="4" <?php echo $rating_filter === 4 ? 'selected' : ''; ?>>4 sao</option>
                        <option value="3" <?php echo $rating_filter === 3 ? 'selected' : ''; ?>>3 sao</option>
                        <option value="2" <?php echo $rating_filter === 2 ? 'selected' : ''; ?>>2 sao</option>
                        <option value="1" <?php echo $rating_filter === 1 ? 'selected' : ''; ?>>1 sao</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Từ ngày</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Đến ngày</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <?php if ($has_filters): ?>
                <div class="mt-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="text-muted small">Bộ lọc đang áp dụng:</span>

                        <?php if (!empty($search)): ?>
                            <span class="badge bg-primary d-inline-flex align-items-center">
                                <span>Tìm: "<?php echo htmlspecialchars($search); ?>"</span>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('search')" style="font-size: 0.6em; width: 12px; height: 12px;"></button>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($status_filter)): ?>
                            <span class="badge bg-info d-inline-flex align-items-center">
                                <span>Trạng thái: <?php echo $status_filter; ?></span>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('status')" style="font-size: 0.6em; width: 12px; height: 12px;"></button>
                            </span>
                        <?php endif; ?>

                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetFilters()">
                            <i class="fas fa-refresh me-1"></i>Xóa tất cả
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reviews Management -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-star me-2"></i>Danh sách đánh giá
                <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
            </h6>

            <div class="d-flex gap-2">
                <!-- Bulk Action Buttons -->
                <button type="button" class="btn btn-sm btn-success" onclick="performBulkAction('approve')" id="bulkApproveBtn" style="display: none;">
                    <i class="fas fa-check me-1"></i>Duyệt đã chọn
                </button>
                <button type="button" class="btn btn-sm btn-warning" onclick="performBulkAction('reject')" id="bulkRejectBtn" style="display: none;">
                    <i class="fas fa-times me-1"></i>Từ chối đã chọn
                </button>
                
                <button type="button" class="btn btn-sm btn-danger" onclick="performBulkAction('delete')" id="bulkDeleteBtn" style="display: none;">
                    <i class="fas fa-trash me-1"></i>Xóa đã chọn
                </button>
            </div>
        </div>

        <div class="card-body">
            <?php if (!empty($reviews)): ?>
                <form id="bulkActionForm" method="POST" style="display: none;">
                    <input type="hidden" name="action" id="bulkActionInput" value="">
                    <div id="selectedReviewsContainer"></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="3%" class="text-center">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th width="25%">Người đánh giá</th>
                                <th width="15%" class="text-center">Đánh giá</th>
                                <th width="30%">Nội dung</th>
                                <th width="12%" class="text-center">Trạng thái</th>
                                <th width="15%" class="text-center">Ngày tạo</th>
                                <th width="15%" class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input review-checkbox"
                                            value="<?php echo $review['id']; ?>">
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary me-3">
                                                <?php if (!empty($review['avatar']) && file_exists('../uploads/avatars/' . $review['avatar'])): ?>
                                                    <img src="../uploads/avatars/<?php echo htmlspecialchars($review['avatar']); ?>"
                                                        alt="Avatar" class="rounded-circle" width="40" height="40">
                                                <?php else: ?>
                                                    <i class="fas fa-user text-white"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($review['username']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($review['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <div class="rating-stars mb-1">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $review['rating']) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <span class="badge bg-primary"><?php echo $review['rating']; ?>/5</span>
                                    </td>

                                    <td>
                                        <?php if ($review['comment']): ?>
                                            <div class="text-truncate" style="max-width: 250px;"
                                                title="<?php echo htmlspecialchars($review['comment']); ?>">
                                                <?php echo htmlspecialchars(substr($review['comment'], 0, 100)); ?>
                                                <?php if (strlen($review['comment']) > 100): ?>...<?php endif; ?>
                                            </div>

                                            <?php if (!empty($review['admin_response'])): ?>
                                                <div class="admin-reply mt-2">
                                                    <small class="fw-bold text-primary">
                                                        <i class="fas fa-reply me-1"></i>Phản hồi Admin:
                                                    </small>
                                                    <div class="small">
                                                        <?php echo htmlspecialchars(substr($review['admin_response'], 0, 60)); ?>
                                                        <?php if (strlen($review['admin_response']) > 60): ?>...<?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <em class="text-muted">Chỉ đánh giá sao</em>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <?php
                                        $status_config = [
                                            'pending' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Chờ duyệt'],
                                            'approved' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Đã duyệt'],
                                            'rejected' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Từ chối']
                                        ];
                                        $config = $status_config[$review['status']];
                                        ?>
                                        <span class="badge bg-<?php echo $config['class']; ?>">
                                            <i class="fas fa-<?php echo $config['icon']; ?> me-1"></i>
                                            <?php echo $config['text']; ?>
                                        </span>

                                        <?php if ($review['admin_name']): ?>
                                            <br><small class="text-muted mt-1">
                                                <i class="fas fa-user-shield me-1"></i>
                                                <?php echo htmlspecialchars($review['admin_name']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <div>
                                            <strong><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($review['created_at'])); ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <?php if ($review['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success"
                                                    onclick="approveReview(<?php echo $review['id']; ?>)"
                                                    title="Phê duyệt">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                    onclick="rejectReview(<?php echo $review['id']; ?>)"
                                                    title="Từ chối">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="respondReview(<?php echo $review['id']; ?>)"
                                                title="Phản hồi">
                                                <i class="fas fa-reply"></i>
                                            </button>

                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="deleteReview(<?php echo $review['id']; ?>, '<?php echo addslashes($review['username']); ?>')"
                                                title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination - copy từ course-students.php -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        <small class="text-muted">
                            Hiển thị <?php echo count($reviews); ?> đánh giá
                            (<?php echo number_format($offset + 1); ?> - <?php echo number_format($offset + count($reviews)); ?>
                            trong tổng số <?php echo number_format($total_records); ?>)
                        </small>
                    </div>
                    <div>
                        <small class="text-muted">
                            Trang <?php echo $page; ?> / <?php echo $total_pages; ?>
                        </small>
                    </div>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-3">
                        <nav aria-label="Phân trang">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <?php if ($page > 1): ?>
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="page-link">
                                            <i class="fas fa-chevron-left"></i>
                                        </span>
                                    <?php endif; ?>
                                </li>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <?php if ($page < $total_pages): ?>
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="page-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-center mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted">Hiển thị</span>
                        <select id="pageSize" class="form-select form-select-sm" style="width: 80px;">
                            <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        </select>
                        <span class="text-muted">bản ghi mỗi trang</span>
                    </div>
                </div>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-star fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">Không tìm thấy đánh giá nào</h4>
                    <p class="text-muted mb-4">
                        <?php if ($has_filters): ?>
                            Không có đánh giá nào phù hợp với bộ lọc hiện tại.
                        <?php else: ?>
                            Khóa học này chưa có đánh giá nào.
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

<!-- Response Modal -->
<div class="modal fade" id="responseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-reply me-2"></i>Phản hồi Đánh giá
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="review_id" id="responseReviewId">
                    <input type="hidden" name="action" value="respond">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nội dung phản hồi</label>
                        <textarea name="admin_response" class="form-control" rows="5" required
                            placeholder="Viết phản hồi của bạn cho đánh giá này..."></textarea>
                        <div class="form-text">
                            Phản hồi này sẽ được hiển thị công khai cho người dùng và các học viên khác.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Hủy
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i>Gửi phản hồi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa đánh giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                </div>
                <p class="text-center">
                    Bạn có chắc chắn muốn xóa đánh giá của<br>
                    <strong id="reviewUsername"></strong>?
                </p>
                <div class="alert alert-warning">
                    <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" id="deleteForm" class="d-inline">
                    <input type="hidden" name="review_id" id="deleteReviewId" value="">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa đánh giá
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Copy script logic từ course-students.php và điều chỉnh cho reviews
    document.addEventListener('DOMContentLoaded', function() {
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

        // Bulk selection
        const selectAllCheckbox = document.getElementById('selectAll');
        const reviewCheckboxes = document.querySelectorAll('.review-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                reviewCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                toggleBulkButtons();
            });
        }

        reviewCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', toggleBulkButtons);
        });

        // Page size selector
        const pageSizeSelect = document.getElementById('pageSize');
        if (pageSizeSelect) {
            pageSizeSelect.addEventListener('change', function() {
                const url = new URL(window.location);
                url.searchParams.set('limit', this.value);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            });
        }

        function toggleBulkButtons() {
            const selected = document.querySelectorAll('.review-checkbox:checked');
            const bulkButtons = document.querySelectorAll('[id^="bulk"]');

            if (selected.length > 0) {
                bulkButtons.forEach(btn => btn.style.display = 'inline-block');
            } else {
                bulkButtons.forEach(btn => btn.style.display = 'none');
            }
        }
    });

    function performBulkAction(action) {
        const selected = document.querySelectorAll('.review-checkbox:checked');
        if (selected.length === 0) {
            alert('Vui lòng chọn ít nhất một đánh giá!');
            return;
        }

        let message = '';
        if (action === 'approve') {
            message = `Bạn có chắc muốn phê duyệt ${selected.length} đánh giá đã chọn?`;
        } else if (action === 'reject') {
            message = `Bạn có chắc muốn từ chối ${selected.length} đánh giá đã chọn?`;
        } else if (action === 'delete') {
            message = `Bạn có chắc muốn xóa ${selected.length} đánh giá đã chọn? Hành động này không thể hoàn tác!`;
        } else if (action === 'export') {
            message = `Bạn có muốn xuất ${selected.length} đánh giá đã chọn ra file Excel?`;
        }

        if (confirm(message)) {
            document.getElementById('bulkActionInput').value = 'bulk_' + action;

            const container = document.getElementById('selectedReviewsContainer');
            container.innerHTML = '';

            selected.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_reviews[]';
                input.value = checkbox.value;
                container.appendChild(input);
            });

            document.getElementById('bulkActionForm').submit();
        }
    }

    function approveReview(reviewId) {
        if (confirm('Bạn có chắc muốn phê duyệt đánh giá này?')) {
            submitAction(reviewId, 'approve');
        }
    }

    function rejectReview(reviewId) {
        if (confirm('Bạn có chắc muốn từ chối đánh giá này?')) {
            submitAction(reviewId, 'reject');
        }
    }

    function deleteReview(reviewId, username) {
        document.getElementById('reviewUsername').textContent = username;
        document.getElementById('deleteReviewId').value = reviewId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function respondReview(reviewId) {
        document.getElementById('responseReviewId').value = reviewId;
        const modal = new bootstrap.Modal(document.getElementById('responseModal'));
        modal.show();
    }

    function submitAction(reviewId, action) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
        <input type="hidden" name="review_id" value="${reviewId}">
        <input type="hidden" name="action" value="${action}">
    `;
        document.body.appendChild(form);
        form.submit();
    }

    function clearSearch() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.value = '';
            document.getElementById('filterForm').submit();
        }
    }

    function resetFilters() {
        window.location.href = '?course_id=<?php echo $course_id; ?>&limit=<?php echo $limit; ?>';
    }

    function removeFilter(filterName) {
        const url = new URL(window.location);
        url.searchParams.delete(filterName);
        url.searchParams.set('page', '1');
        url.searchParams.set('limit', '<?php echo $limit; ?>');
        window.location.href = url.toString();
    }
</script>

<?php include 'includes/admin-footer.php'; ?>