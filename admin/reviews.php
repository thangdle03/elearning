<?php
// filepath: d:\Xampp\htdocs\elearning\admin\reviews.php

require_once '../includes/config.php';

// Check admin authentication
if (!isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

// Set page variables
$current_page = 'reviews';
$page_title = 'Quản lý Đánh giá';
$page_subtitle = 'Xem, duyệt và phản hồi các đánh giá từ học viên';

// Initialize variables
$success_message = '';
$error_message = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$course_filter = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $review_id = (int)$_POST['review_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved', admin_id = ?, admin_responded_at = NOW() WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], $review_id]);
                    $success_message = "Đánh giá đã được phê duyệt thành công!";
                    break;
                    
                case 'reject':
                    $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected', admin_id = ?, admin_responded_at = NOW() WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], $review_id]);
                    $success_message = "Đánh giá đã bị từ chối!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
                    $stmt->execute([$review_id]);
                    $success_message = "Đánh giá đã được xóa khỏi hệ thống!";
                    break;
                    
                case 'respond':
                    $response = trim($_POST['admin_response']);
                    if ($response) {
                        $stmt = $pdo->prepare("UPDATE reviews SET admin_response = ?, admin_id = ?, admin_responded_at = NOW() WHERE id = ?");
                        $stmt->execute([$response, $_SESSION['user_id'], $review_id]);
                        $success_message = "Phản hồi đã được gửi thành công!";
                    } else {
                        $error_message = "Vui lòng nhập nội dung phản hồi!";
                    }
                    break;
                    
                case 'bulk_approve':
                    if (isset($_POST['selected_reviews']) && is_array($_POST['selected_reviews'])) {
                        $placeholders = str_repeat('?,', count($_POST['selected_reviews']) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved', admin_id = ?, admin_responded_at = NOW() WHERE id IN ($placeholders)");
                        $params = array_merge([$_SESSION['user_id']], $_POST['selected_reviews']);
                        $stmt->execute($params);
                        $success_message = "Đã phê duyệt " . count($_POST['selected_reviews']) . " đánh giá!";
                    }
                    break;
                    
                case 'bulk_delete':
                    if (isset($_POST['selected_reviews']) && is_array($_POST['selected_reviews'])) {
                        $placeholders = str_repeat('?,', count($_POST['selected_reviews']) - 1) . '?';
                        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id IN ($placeholders)");
                        $stmt->execute($_POST['selected_reviews']);
                        $success_message = "Đã xóa " . count($_POST['selected_reviews']) . " đánh giá!";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Có lỗi xảy ra: " . $e->getMessage();
        }
    }
}

// Build query with filters
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(u.username LIKE ? OR c.title LIKE ? OR r.comment LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($course_filter) {
    $where_conditions[] = "r.course_id = ?";
    $params[] = $course_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM reviews r 
              JOIN users u ON r.user_id = u.id 
              JOIN courses c ON r.course_id = c.id 
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_reviews = $count_stmt->fetchColumn();
$total_pages = ceil($total_reviews / $per_page);

// Get reviews with pagination
$sql = "SELECT r.*, u.username, u.email, u.full_name, c.title as course_title,
               admin.username as admin_name
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        JOIN courses c ON r.course_id = c.id 
        LEFT JOIN users admin ON r.admin_id = admin.id
        $where_clause
        ORDER BY r.created_at DESC 
        LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Get courses for filter
$courses_stmt = $pdo->prepare("SELECT id, title FROM courses ORDER BY title");
$courses_stmt->execute();
$courses = $courses_stmt->fetchAll();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    AVG(rating) as avg_rating,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_count
    FROM reviews";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Include header
include 'includes/admin-header.php';
?>

<style>
.avatar-sm {
    width: 35px;
    height: 35px;
    font-size: 14px;
}

.avatar-lg {
    width: 60px;
    height: 60px;
    font-size: 1.5rem;
}

.timeline-item {
    position: relative;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 19px;
    top: 40px;
    width: 2px;
    height: 20px;
    background: #dee2e6;
}

.dropdown-toggle::after {
    display: none;
}

.btn-action {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* ✅ THÊM CSS CHO MODAL */
#reviewModal .modal-dialog {
    max-width: 1200px;
}

#reviewModal .modal-body {
    padding: 0;
    max-height: 80vh;
    overflow-y: auto;
}

.review-detail-content {
    background: #f8f9fa;
    min-height: 400px;
}
</style>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 fw-bold text-primary mb-1"><?php echo number_format($stats['total']); ?></h2>
                        <p class="text-muted mb-0 small">Tổng đánh giá</p>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                        <i class="fas fa-comments text-primary fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 fw-bold text-warning mb-1"><?php echo number_format($stats['pending']); ?></h2>
                        <p class="text-muted mb-0 small">Chờ duyệt</p>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded-3">
                        <i class="fas fa-clock text-warning fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 fw-bold text-success mb-1"><?php echo number_format($stats['approved']); ?></h2>
                        <p class="text-muted mb-0 small">Đã duyệt</p>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded-3">
                        <i class="fas fa-check-circle text-success fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 fw-bold text-info mb-1"><?php echo number_format($stats['avg_rating'], 1); ?></h2>
                        <p class="text-muted mb-0 small">Điểm trung bình</p>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded-3">
                        <i class="fas fa-star text-info fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Messages -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filters & Search -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Bộ lọc & Tìm kiếm
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Tìm kiếm</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Tên người dùng, khóa học, nội dung...">
                </div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-semibold">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Chờ duyệt</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Đã duyệt</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Từ chối</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-semibold">Khóa học</label>
                <select name="course" class="form-select">
                    <option value="">Tất cả khóa học</option>
                    <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Tìm kiếm
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reviews Management -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>Danh sách Đánh giá 
                <span class="badge bg-primary ms-2"><?php echo number_format($total_reviews); ?></span>
            </h5>
            
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-success btn-sm" onclick="bulkAction('approve')" 
                        id="bulkApproveBtn" style="display: none;">
                    <i class="fas fa-check me-1"></i>Duyệt đã chọn
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="bulkAction('delete')" 
                        id="bulkDeleteBtn" style="display: none;">
                    <i class="fas fa-trash me-1"></i>Xóa đã chọn
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <?php if (empty($reviews)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="fas fa-inbox text-muted" style="font-size: 4rem;"></i>
            </div>
            <h5 class="text-muted">Không có đánh giá nào</h5>
            <p class="text-muted mb-0">Chưa có đánh giá nào phù hợp với bộ lọc hiện tại.</p>
        </div>
        <?php else: ?>
        
        <form id="bulkForm" method="POST">
            <input type="hidden" name="action" id="bulkAction">
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                </div>
                            </th>
                            <th>ID</th>
                            <th>Người dùng</th>
                            <th>Khóa học</th>
                            <th>Đánh giá</th>
                            <th>Nội dung</th>
                            <th>Trạng thái</th>
                            <th width="120">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input review-checkbox" type="checkbox" 
                                           name="selected_reviews[]" value="<?php echo $review['id']; ?>">
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">#<?php echo $review['id']; ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center">
                                        <?php echo strtoupper(substr($review['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($review['username']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($review['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-medium text-truncate" style="max-width: 200px;" 
                                     title="<?php echo htmlspecialchars($review['course_title']); ?>">
                                    <?php echo htmlspecialchars($review['course_title']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="text-warning me-2">
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
                                    <span class="badge bg-primary"><?php echo $review['rating']; ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 250px;" 
                                     title="<?php echo htmlspecialchars($review['comment']); ?>">
                                    <?php if ($review['comment']): ?>
                                        <?php echo htmlspecialchars(substr($review['comment'], 0, 80)); ?>
                                        <?php if (strlen($review['comment']) > 80): ?>...<?php endif; ?>
                                    <?php else: ?>
                                        <em class="text-muted">Không có nhận xét</em>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
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
                                <br><small class="text-muted">
                                    <i class="fas fa-user-shield me-1"></i>
                                    <?php echo htmlspecialchars($review['admin_name']); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- View Button -->
                                    <button type="button" class="btn btn-outline-info btn-action" 
                                            onclick="viewReview(<?php echo $review['id']; ?>)" 
                                            title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <!-- Approve/Reject Buttons -->
                                    <?php if ($review['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-outline-success btn-action" 
                                            onclick="approveReview(<?php echo $review['id']; ?>)" 
                                            title="Phê duyệt">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-action" 
                                            onclick="rejectReview(<?php echo $review['id']; ?>)" 
                                            title="Từ chối">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <!-- Response Button -->
                                    <button type="button" class="btn btn-outline-primary btn-action" 
                                            onclick="respondReview(<?php echo $review['id']; ?>)" 
                                            title="Phản hồi">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                    
                                    <!-- Delete Button -->
                                    <button type="button" class="btn btn-outline-danger btn-action" 
                                            onclick="deleteReview(<?php echo $review['id']; ?>)" 
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
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&course=<?php echo $course_filter; ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
        </li>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&course=<?php echo $course_filter; ?>">
                <?php echo $i; ?>
            </a>
        </li>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&course=<?php echo $course_filter; ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Review Detail Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Chi tiết Đánh giá
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reviewModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                </div>
            </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
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
    
    function toggleBulkButtons() {
        const selected = document.querySelectorAll('.review-checkbox:checked');
        const bulkApproveBtn = document.getElementById('bulkApproveBtn');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        
        if (selected.length > 0) {
            if (bulkApproveBtn) bulkApproveBtn.style.display = 'inline-block';
            if (bulkDeleteBtn) bulkDeleteBtn.style.display = 'inline-block';
        } else {
            if (bulkApproveBtn) bulkApproveBtn.style.display = 'none';
            if (bulkDeleteBtn) bulkDeleteBtn.style.display = 'none';
        }
    }
});

// Bulk actions
function bulkAction(action) {
    const selected = document.querySelectorAll('.review-checkbox:checked');
    if (selected.length === 0) {
        alert('Vui lòng chọn ít nhất một đánh giá!');
        return;
    }
    
    let message = '';
    if (action === 'approve') {
        message = `Bạn có chắc muốn phê duyệt ${selected.length} đánh giá đã chọn?`;
    } else if (action === 'delete') {
        message = `Bạn có chắc muốn xóa ${selected.length} đánh giá đã chọn? Hành động này không thể hoàn tác!`;
    }
    
    if (confirm(message)) {
        document.getElementById('bulkAction').value = 'bulk_' + action;
        document.getElementById('bulkForm').submit();
    }
}

// View review details
function viewReview(reviewId) {
    const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
    modal.show();
    
    fetch(`get_review.php?id=${reviewId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('reviewModalBody').innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('reviewModalBody').innerHTML = 
                '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Có lỗi xảy ra khi tải thông tin đánh giá!</div>';
        });
}

// Quick actions
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

function deleteReview(reviewId) {
    if (confirm('Bạn có chắc muốn xóa đánh giá này? Hành động này không thể hoàn tác!')) {
        submitAction(reviewId, 'delete');
    }
}

function respondReview(reviewId) {
    document.getElementById('responseReviewId').value = reviewId;
    const modal = new bootstrap.Modal(document.getElementById('responseModal'));
    modal.show();
}

// Submit individual actions
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
</script>

<?php include 'includes/admin-footer.php'; ?>