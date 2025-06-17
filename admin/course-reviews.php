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
    
    // DELETE SINGLE REVIEW
    if (isset($_POST['delete_review'])) {
        $review_id = (int)($_POST['review_id'] ?? 0);
        
        if ($review_id <= 0) {
            $error = 'ID đánh giá không hợp lệ!';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND course_id = ?");
                if ($stmt->execute([$review_id, $course_id]) && $stmt->rowCount() > 0) {
                    header('Location: course-reviews.php?course_id=' . $course_id . '&success=delete');
                    exit;
                } else {
                    $error = 'Không tìm thấy đánh giá để xóa!';
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra khi xóa đánh giá: ' . $e->getMessage();
            }
        }
    }
    
    // APPROVE SINGLE REVIEW
    elseif (isset($_POST['approve_review'])) {
        $review_id = (int)($_POST['review_id'] ?? 0);
        
        if ($review_id <= 0) {
            $error = 'ID đánh giá không hợp lệ!';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND course_id = ?");
                if ($stmt->execute([$review_id, $course_id]) && $stmt->rowCount() > 0) {
                    header('Location: course-reviews.php?course_id=' . $course_id . '&success=approve');
                    exit;
                } else {
                    $error = 'Không tìm thấy đánh giá để duyệt!';
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra khi duyệt đánh giá: ' . $e->getMessage();
            }
        }
    }
    
    // REJECT SINGLE REVIEW
    elseif (isset($_POST['reject_review'])) {
        $review_id = (int)($_POST['review_id'] ?? 0);
        
        if ($review_id <= 0) {
            $error = 'ID đánh giá không hợp lệ!';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND course_id = ?");
                if ($stmt->execute([$review_id, $course_id]) && $stmt->rowCount() > 0) {
                    header('Location: course-reviews.php?course_id=' . $course_id . '&success=reject');
                    exit;
                } else {
                    $error = 'Không tìm thấy đánh giá để từ chối!';
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra khi từ chối đánh giá: ' . $e->getMessage();
            }
        }
    }
    
    // UPDATE REVIEW (EDIT STATUS AND ADMIN RESPONSE)
    elseif (isset($_POST['update_review'])) {
        $review_id = (int)($_POST['review_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $admin_response = trim($_POST['admin_response'] ?? '');
        
        if ($review_id <= 0) {
            $error = 'ID đánh giá không hợp lệ!';
        } elseif (!in_array($status, ['pending', 'approved', 'rejected'])) {
            $error = 'Trạng thái không hợp lệ!';
        } else {
            try {
                // Check if review exists and belongs to this course
                $stmt = $pdo->prepare("SELECT id FROM reviews WHERE id = ? AND course_id = ?");
                $stmt->execute([$review_id, $course_id]);
                
                if (!$stmt->fetch()) {
                    $error = 'Không tìm thấy đánh giá!';
                } else {
                    // Update review
                    $stmt = $pdo->prepare("
                        UPDATE reviews 
                        SET status = ?, 
                            admin_response = ?, 
                            admin_id = ?, 
                            admin_responded_at = CURRENT_TIMESTAMP,
                            updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ? AND course_id = ?
                    ");
                    
                    $admin_id = !empty($admin_response) ? $_SESSION['user_id'] : null;
                    
                    if ($stmt->execute([$status, $admin_response, $admin_id, $review_id, $course_id]) && $stmt->rowCount() > 0) {
                        header('Location: course-reviews.php?course_id=' . $course_id . '&success=update');
                        exit;
                    } else {
                        $error = 'Không thể cập nhật đánh giá!';
                    }
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra khi cập nhật đánh giá: ' . $e->getMessage();
            }
        }
    }
    
    // BULK ACTIONS - GIỐNG COURSES.PHP
    elseif (isset($_POST['bulk_action']) && !empty($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selected_reviews = isset($_POST['selected_reviews']) ? $_POST['selected_reviews'] : [];
        
        // Debug logging
        $debug = isset($_GET['debug']);
        if ($debug) {
            error_log("Bulk action: " . $action);
            error_log("Selected reviews: " . print_r($selected_reviews, true));
        }
        
        // Validate selected reviews
        if (empty($selected_reviews) || !is_array($selected_reviews)) {
            $error = 'Vui lòng chọn ít nhất một đánh giá để thực hiện thao tác!';
        } elseif (!in_array($action, ['approve', 'reject', 'delete'])) {
            $error = 'Thao tác không hợp lệ!';
        } else {
            // Clean and validate review IDs
            $review_ids = [];
            foreach ($selected_reviews as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $review_ids[] = $id;
                }
            }
            
            if (empty($review_ids)) {
                $error = 'Không có đánh giá hợp lệ được chọn!';
            } else {
                $success_count = 0;
                $failed_count = 0;
                $failed_reasons = [];
                
                try {
                    $pdo->beginTransaction();
                    
                    foreach ($review_ids as $review_id) {
                        // Get review info first
                        $stmt = $pdo->prepare("SELECT r.id, r.comment, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.id = ? AND r.course_id = ?");
                        $stmt->execute([$review_id, $course_id]);
                        $review = $stmt->fetch();
                        
                        if (!$review) {
                            $failed_count++;
                            $failed_reasons[] = "Đánh giá ID {$review_id} không tồn tại";
                            continue;
                        }
                        
                        try {
                            if ($action === 'approve') {
                                $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND course_id = ?");
                                if ($stmt->execute([$review_id, $course_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                    $failed_reasons[] = "Không thể duyệt đánh giá của: {$review['username']}";
                                }
                            } elseif ($action === 'reject') {
                                $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND course_id = ?");
                                if ($stmt->execute([$review_id, $course_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                    $failed_reasons[] = "Không thể từ chối đánh giá của: {$review['username']}";
                                }
                            } elseif ($action === 'delete') {
                                $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND course_id = ?");
                                if ($stmt->execute([$review_id, $course_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                    $failed_reasons[] = "Không thể xóa đánh giá của: {$review['username']}";
                                }
                            }
                        } catch (Exception $e) {
                            $failed_count++;
                            $failed_reasons[] = "Lỗi xử lý đánh giá của {$review['username']}: " . $e->getMessage();
                        }
                    }
                    
                    $pdo->commit();
                    
                    // Build result message
                    if ($success_count > 0) {
                        $action_names = [
                            'approve' => 'duyệt',
                            'reject' => 'từ chối',
                            'delete' => 'xóa'
                        ];
                        $action_name = $action_names[$action] ?? 'cập nhật';
                        
                        // Redirect to avoid form resubmission
                        $redirect_url = 'course-reviews.php?course_id=' . $course_id . '&success=bulk&action=' . urlencode($action) . '&count=' . $success_count;
                        if ($failed_count > 0) {
                            $redirect_url .= '&failed=' . $failed_count;
                        }
                        header('Location: ' . $redirect_url);
                        exit;
                    } else {
                        $error = 'Không có đánh giá nào được cập nhật!';
                        if (!empty($failed_reasons)) {
                            $error .= ' Lý do: ' . implode('; ', array_slice($failed_reasons, 0, 3));
                            if (count($failed_reasons) > 3) {
                                $error .= '...';
                            }
                        }
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Có lỗi xảy ra khi thực hiện thao tác hàng loạt: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'approve':
            $message = 'Đã duyệt đánh giá thành công!';
            break;
        case 'reject':
            $message = 'Đã từ chối đánh giá thành công!';
            break;
        case 'delete':
            $message = 'Đã xóa đánh giá thành công!';
            break;
        case 'update':
            $message = 'Đã cập nhật đánh giá thành công!';
            break;
        case 'bulk':
            $action = $_GET['action'] ?? '';
            $count = (int)($_GET['count'] ?? 0);
            $failed = (int)($_GET['failed'] ?? 0);
            
            $action_names = [
                'approve' => 'duyệt',
                'reject' => 'từ chối',
                'delete' => 'xóa'
            ];
            $action_name = $action_names[$action] ?? 'cập nhật';
            
            $message = "Đã {$action_name} thành công {$count} đánh giá";
            if ($failed > 0) {
                $message .= ", {$failed} đánh giá không thể thực hiện";
            }
            $message .= "!";
            break;
    }
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
    SELECT r.*, u.username, u.email,
           admin.username as admin_username
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
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Chờ duyệt</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Đã duyệt</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Đã từ chối</option>
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
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>Danh sách đánh giá
                <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
            </h6
            
            <div class="d-flex gap-2">
                <!-- Bulk Action Buttons -->
                <button type="button" class="btn btn-sm btn-success" onclick="performBulkAction('approve')" id="bulkApproveBtn" style="display: none;">
                    <i class="fas fa-check me-1"></i>Duyệt
                </button>
                <button type="button" class="btn btn-sm btn-warning" onclick="performBulkAction('reject')" id="bulkRejectBtn" style="display: none;">
                    <i class="fas fa-times me-1"></i>Từ chối
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="performBulkAction('delete')" id="bulkDeleteBtn" style="display: none;">
                    <i class="fas fa-trash me-1"></i>Xóa
                </button>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (!empty($reviews)): ?>
                <form id="bulkActionForm" method="POST" style="display: none;">
                    <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
                    <div id="selectedReviewsContainer"></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="3%" class="text-center">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th width="15%">Người đánh giá</th>
                                <th width="10%" class="text-center">Điểm số</th>
                                <th width="40%">Nội dung</th>
                                <th width="12%" class="text-center">Ngày tạo</th>
                                <th width="10%" class="text-center">Trạng thái</th>
                                <th width="10%" class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input review-checkbox" value="<?php echo $review['id']; ?>">
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

                                    <td>
                                        <div class="review-content">
                                            <?php if (!empty($review['comment'])): ?>
                                                <p class="mb-1">
                                                    <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                                </p>
                                            <?php else: ?>
                                                <em class="text-muted">Không có bình luận</em>
                                            <?php endif; ?>
                                            
                                            <!-- Admin Response -->
                                            <?php if (!empty($review['admin_response'])): ?>
                                                <div class="mt-2 p-2 bg-light border-start border-primary border-3">
                                                    <small class="text-primary fw-bold">
                                                        <i class="fas fa-reply me-1"></i>Phản hồi Admin:
                                                    </small>
                                                    <div class="mt-1 small">
                                                        <?php echo nl2br(htmlspecialchars($review['admin_response'])); ?>
                                                    </div>
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
                                            <!-- VIEW REVIEW BUTTON -->
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewReview(<?php echo $review['id']; ?>, '<?php echo addslashes($review['username']); ?>', '<?php echo addslashes($review['comment']); ?>', <?php echo $review['rating']; ?>, '<?php echo $review['created_at']; ?>')"
                                                    title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($review['status'] === 'pending'): ?>
                                                <!-- APPROVE BUTTON -->
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn duyệt đánh giá này?')">
                                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                    <input type="hidden" name="approve_review" value="1">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Duyệt đánh giá">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                
                                                <!-- REJECT BUTTON -->
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn từ chối đánh giá này?')">
                                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                    <input type="hidden" name="reject_review" value="1">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Từ chối đánh giá">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- EDIT STATUS BUTTON -->
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="editReview(<?php echo $review['id']; ?>, '<?php echo $review['status']; ?>', '<?php echo addslashes($review['admin_response'] ?? ''); ?>')"
                                                        title="Chỉnh sửa trạng thái">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- DELETE BUTTON -->
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteReview(<?php echo $review['id']; ?>, '<?php echo addslashes($review['username']); ?>')"
                                                    title="Xóa đánh giá">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

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

<!-- View Review Modal -->
<div class="modal fade" id="viewReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết đánh giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-user me-2"></i>Người đánh giá:</h6>
                        <p id="modal_username" class="text-primary fw-bold"></p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-calendar me-2"></i>Ngày tạo:</h6>
                        <p id="modal_date"></p>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6><i class="fas fa-star me-2"></i>Điểm đánh giá:</h6>
                    <div id="modal_rating"></div>
                </div>
                
                <div class="mb-3">
                    <h6><i class="fas fa-comment me-2"></i>Nội dung đánh giá:</h6>
                    <div id="modal_comment" class="p-3 bg-light rounded"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Review Modal -->
<div class="modal fade" id="editReviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chỉnh sửa đánh giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editReviewForm">
                <div class="modal-body">
                    <input type="hidden" name="review_id" id="edit_review_id" value="">
                    <input type="hidden" name="update_review" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Trạng thái:</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="pending">Chờ duyệt</option>
                            <option value="approved">Đã duyệt</option>
                            <option value="rejected">Đã từ chối</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phản hồi của Admin:</label>
                        <textarea name="admin_response" id="edit_admin_response" class="form-control" rows="3" 
                                  placeholder="Nhập phản hồi của bạn (tùy chọn)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Review Modal -->
<div class="modal fade" id="deleteReviewModal" tabindex="-1">
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
                    <strong id="delete_username"></strong>?
                </p>
                <div class="alert alert-warning">
                    <strong>Cảnh báo:</strong> Hành động này sẽ xóa vĩnh viễn đánh giá!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" id="deleteReviewForm" class="d-inline">
                    <input type="hidden" name="review_id" id="delete_review_id" value="">
                    <input type="hidden" name="delete_review" value="1">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa đánh giá
                    </button>
                </form>
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
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Course reviews management loaded');
    
    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert:not(.alert-info)').forEach(alert => {
            try {
                new bootstrap.Alert(alert).close();
            } catch (e) {
                console.log('Alert already closed');
            }
        });
    }, 5000);

    // Get elements
    const selectAllCheckbox = document.getElementById('selectAll');
    const reviewCheckboxes = document.querySelectorAll('.review-checkbox');
    const bulkButtons = document.querySelectorAll('[id^="bulk"]');

    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            reviewCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkButtons();
        });
    }

    // Individual checkbox change
    reviewCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateBulkButtons();
        });
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

    // Initial state
    updateBulkButtons();
});

// Update select all checkbox state
function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById('selectAll');
    if (!selectAllCheckbox) return;

    const reviewCheckboxes = document.querySelectorAll('.review-checkbox');
    const checkedCount = document.querySelectorAll('.review-checkbox:checked').length;
    const totalCount = reviewCheckboxes.length;

    if (checkedCount === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedCount === totalCount) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
    }
}

// Update bulk action buttons
function updateBulkButtons() {
    const checkedBoxes = document.querySelectorAll('.review-checkbox:checked');
    const bulkButtons = document.querySelectorAll('[id^="bulk"]');

    if (checkedBoxes.length > 0) {
        bulkButtons.forEach(btn => btn.style.display = 'inline-block');
    } else {
        bulkButtons.forEach(btn => btn.style.display = 'none');
    }
}

// Perform bulk action - GIỐNG COURSES.PHP
function performBulkAction(action) {
    const checkedBoxes = document.querySelectorAll('.review-checkbox:checked');

    if (checkedBoxes.length === 0) {
        alert('Vui lòng chọn ít nhất một đánh giá!');
        return;
    }

    // Confirmation messages
    const messages = {
        'approve': `Bạn có chắc muốn duyệt ${checkedBoxes.length} đánh giá đã chọn?`,
        'reject': `Bạn có chắc muốn từ chối ${checkedBoxes.length} đánh giá đã chọn?`,
        'delete': `Bạn có chắc muốn XÓA ${checkedBoxes.length} đánh giá đã chọn?\n\nCảnh báo: Hành động này không thể hoàn tác!`
    };

    if (!confirm(messages[action])) {
        return;
    }

    // Prepare form
    const form = document.getElementById('bulkActionForm');
    const actionInput = document.getElementById('bulkActionInput');
    const container = document.getElementById('selectedReviewsContainer');

    // Set action
    actionInput.value = action;

    // Clear previous inputs
    container.innerHTML = '';

    // Add selected review IDs
    checkedBoxes.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_reviews[]';
        input.value = checkbox.value;
        container.appendChild(input);
    });

    // Submit form
    form.submit();
}

// View review details
function viewReview(reviewId, username, comment, rating, createdAt) {
    // Set modal content
    document.getElementById('modal_username').textContent = username;
    document.getElementById('modal_date').textContent = new Date(createdAt).toLocaleString('vi-VN');
    document.getElementById('modal_comment').innerHTML = comment.replace(/\n/g, '<br>');
    
    // Create rating stars
    let ratingHtml = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            ratingHtml += '<i class="fas fa-star text-warning"></i>';
        } else {
            ratingHtml += '<i class="far fa-star text-muted"></i>';
        }
    }
    ratingHtml += ` <span class="ms-2">(${rating}/5)</span>`;
    document.getElementById('modal_rating').innerHTML = ratingHtml;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('viewReviewModal')).show();
}

// Edit review
function editReview(reviewId, currentStatus, adminResponse) {
    document.getElementById('edit_review_id').value = reviewId;
    document.getElementById('edit_status').value = currentStatus;
    document.getElementById('edit_admin_response').value = adminResponse || '';
    
    new bootstrap.Modal(document.getElementById('editReviewModal')).show();
}

// Delete review function (updated)
function deleteReview(reviewId, username) {
    document.getElementById('delete_username').textContent = username;
    document.getElementById('delete_review_id').value = reviewId;
    new bootstrap.Modal(document.getElementById('deleteReviewModal')).show();
}

// Filter functions
function clearSearch() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.value = '';
        document.getElementById('filterForm').submit();
    }
}

function resetFilters() {
    const url = new URL(window.location);
    url.search = '';
    url.searchParams.set('course_id', '<?php echo $course_id; ?>');
    url.searchParams.set('limit', '<?php echo $limit; ?>');
    window.location.href = url.toString();
}

function removeFilter(filterName) {
    const url = new URL(window.location);
    url.searchParams.delete(filterName);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>

<?php include 'includes/admin-footer.php'; ?>