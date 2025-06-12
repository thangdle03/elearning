<?php

require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý bài học';
$current_page = 'lessons';

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['delete_lesson'])) {
        $lesson_id = (int)$_POST['lesson_id'];
        
        try {
            // Check if lesson exists
            $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id = ?");
            $stmt->execute([$lesson_id]);
            $lesson = $stmt->fetch();
            
            if ($lesson) {
                // Delete lesson and related progress
                $pdo->beginTransaction();
                
                // Delete progress records first (if progress table exists)
                try {
                    $stmt = $pdo->prepare("DELETE FROM progress WHERE lesson_id = ?");
                    $stmt->execute([$lesson_id]);
                } catch (Exception $e) {
                    // Progress table might not exist, continue
                }
                
                // Delete the lesson
                $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
                $stmt->execute([$lesson_id]);
                
                $pdo->commit();
                $message = 'Đã xóa bài học thành công!';
            } else {
                $error = 'Bài học không tồn tại!';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Có lỗi xảy ra khi xóa bài học: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_order'])) {
        try {
            $lesson_orders = $_POST['lesson_orders'] ?? [];
            
            foreach ($lesson_orders as $lesson_id => $order_number) {
                $stmt = $pdo->prepare("UPDATE lessons SET order_number = ? WHERE id = ?");
                $stmt->execute([$order_number, $lesson_id]);
            }
            
            $message = 'Đã cập nhật thứ tự bài học!';
        } catch (Exception $e) {
            $error = 'Có lỗi khi cập nhật thứ tự: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$course_id = $_GET['course_id'] ?? null;
$search = $_GET['search'] ?? '';

// Get course info if course_id is provided
$course_info = null;
if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course_info = $stmt->fetch();
}

// Update page title based on course
if ($course_info) {
    $page_title = 'Quản lý bài học - ' . $course_info['title'];
} else {
    $page_title = 'Quản lý bài học';
}

// Build query using correct column names
$sql = "
    SELECT l.*, c.title as course_title, c.id as course_id
    FROM lessons l
    LEFT JOIN courses c ON l.course_id = c.id
";

$where_conditions = [];
$params = [];

if ($course_id) {
    $where_conditions[] = "l.course_id = ?";
    $params[] = $course_id;
}

if ($search) {
    $where_conditions[] = "(l.title LIKE ?)";
    $params[] = "%{$search}%";
}

if ($where_conditions) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY c.title, l.order_number ASC, l.id ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lessons = $stmt->fetchAll();
} catch (Exception $e) {
    $lessons = [];
    $error = 'Có lỗi khi tải danh sách bài học: ' . $e->getMessage();
}

// Get courses for filter
$courses = $pdo->query("SELECT id, title FROM courses ORDER BY title")->fetchAll();

// Get statistics using correct column names
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_lessons,
        COUNT(CASE WHEN youtube_url IS NOT NULL AND youtube_url != '' THEN 1 END) as lessons_with_video,
        COUNT(DISTINCT course_id) as courses_with_lessons
    FROM lessons
")->fetch();
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-play-circle me-2"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">Quản lý tất cả bài học trong hệ thống</p>
    </div>
    <div>
        <a href="add-lesson.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Thêm bài học mới
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Tổng bài học
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_lessons']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-play-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Có video YouTube
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['lessons_with_video']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fab fa-youtube fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Khóa học có bài học
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['courses_with_lessons']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-filter me-2"></i>Bộ lọc
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Khóa học</label>
                <select name="course_id" class="form-select">
                    <option value="">Tất cả khóa học</option>
                    <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" 
                            <?php echo $course_id == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Tìm kiếm</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Nhập tiêu đề bài học..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search me-2"></i>Lọc
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lessons Table -->
<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-list me-2"></i>Danh sách bài học 
            <span class="badge bg-primary ms-2"><?php echo count($lessons); ?></span>
        </h6>
    </div>
    <div class="card-body">
        <?php if ($lessons): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="lessonsTable">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="35%">Tiêu đề bài học</th>
                        <th width="25%">Khóa học</th>
                        <th width="8%">Thứ tự</th>
                        <th width="12%">Video YouTube</th>
                        <th width="15%">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $index => $lesson): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-hashtag me-1"></i>ID: <?php echo $lesson['id']; ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary fs-6">
                                <?php echo htmlspecialchars($lesson['course_title'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info fs-6">
                                <?php echo $lesson['order_number'] ?? 0; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($lesson['youtube_url']): ?>
                                <a href="<?php echo htmlspecialchars($lesson['youtube_url']); ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-danger">
                                    <i class="fab fa-youtube me-1"></i>Xem
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-minus me-1"></i>Không có
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view-lesson.php?id=<?php echo $lesson['id']; ?>" 
                                   class="btn btn-outline-info btn-sm" title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-lesson.php?id=<?php echo $lesson['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" title="Chỉnh sửa">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="confirmDelete(<?php echo $lesson['id']; ?>, '<?php echo htmlspecialchars($lesson['title']); ?>')" 
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
        
        <!-- Summary -->
        <div class="mt-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small class="text-muted">
                        Hiển thị <?php echo count($lessons); ?> bài học
                        <?php if ($search || $course_id): ?>
                        - <a href="lesson.php" class="text-decoration-none">Xem tất cả</a>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportLessons()">
                            <i class="fas fa-download me-2"></i>Xuất Excel
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="showReorderModal()">
                            <i class="fas fa-sort me-2"></i>Sắp xếp thứ tự
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-play-circle fa-4x text-muted mb-4"></i>
            <h4 class="text-muted">Không tìm thấy bài học nào</h4>
            <p class="text-muted mb-4">
                <?php if ($search || $course_id): ?>
                Thử thay đổi bộ lọc hoặc <a href="lesson.php" class="text-decoration-none">xem tất cả bài học</a>
                <?php else: ?>
                Hệ thống chưa có bài học nào. Hãy tạo bài học đầu tiên!
                <?php endif; ?>
            </p>
            <a href="add-lesson.php" class="btn btn-primary btn-lg">
                <i class="fas fa-plus me-2"></i>Thêm bài học mới
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Xác nhận xóa bài học
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa bài học:</p>
                <p class="fw-bold text-danger" id="lessonTitle"></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Cảnh báo:</strong> Hành động này sẽ xóa vĩnh viễn bài học và không thể hoàn tác!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Hủy bỏ
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="delete_lesson" value="1">
                    <input type="hidden" name="lesson_id" id="deleteLessonId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa bài học
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.table th {
    border-top: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    color: #5a5c69;
}

.btn-group-sm > .btn {
    margin: 0 1px;
}

.badge {
    font-size: 0.75em;
}

#lessonsTable tbody tr:hover {
    background-color: #f8f9fc;
}

.table-responsive {
    border-radius: 0.5rem;
}
</style>

<!-- Custom JavaScript -->
<script>
function confirmDelete(lessonId, lessonTitle) {
    document.getElementById('deleteLessonId').value = lessonId;
    document.getElementById('lessonTitle').textContent = lessonTitle;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function showReorderModal() {
    alert('Tính năng sắp xếp lại thứ tự đang được phát triển!');
}

function exportLessons() {
    alert('Tính năng xuất Excel đang được phát triển!');
}

// Auto-submit form when course filter changes
document.querySelector('select[name="course_id"]')?.addEventListener('change', function() {
    this.form.submit();
});

// Search on Enter key
document.querySelector('input[name="search"]')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include 'includes/admin-footer.php'; ?>