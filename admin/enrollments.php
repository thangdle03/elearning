
<?php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý đăng ký khóa học';
$current_page = 'enrollments';

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['add_enrollment'])) {
        $user_id = (int)$_POST['user_id'];
        $course_id = (int)$_POST['course_id'];
        
        if (empty($user_id) || empty($course_id)) {
            $error = 'Vui lòng chọn học viên và khóa học!';
        } else {
            try {
                // Check if enrollment already exists
                $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
                $stmt->execute([$user_id, $course_id]);
                if ($stmt->fetch()) {
                    $error = 'Học viên đã đăng ký khóa học này rồi!';
                } else {
                    // Insert new enrollment
                    $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, enrolled_at, status) VALUES (?, ?, NOW(), 'active')");
                    if ($stmt->execute([$user_id, $course_id])) {
                        $message = 'Đã thêm đăng ký thành công!';
                    } else {
                        $error = 'Có lỗi khi thêm đăng ký!';
                    }
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $enrollment_id = (int)$_POST['enrollment_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE enrollments SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $enrollment_id])) {
                $message = 'Đã cập nhật trạng thái đăng ký!';
            } else {
                $error = 'Có lỗi khi cập nhật trạng thái!';
            }
        } catch (Exception $e) {
            $error = 'Có lỗi xảy ra: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_enrollment'])) {
        $enrollment_id = (int)$_POST['enrollment_id'];
        
        try {
            // Delete related progress first
            $stmt = $pdo->prepare("DELETE FROM progress WHERE enrollment_id = ?");
            $stmt->execute([$enrollment_id]);
            
            // Delete enrollment
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = ?");
            if ($stmt->execute([$enrollment_id])) {
                $message = 'Đã xóa đăng ký thành công!';
            } else {
                $error = 'Có lỗi khi xóa đăng ký!';
            }
        } catch (Exception $e) {
            $error = 'Có lỗi xảy ra khi xóa đăng ký: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$course_filter = $_GET['course_id'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with safe handling for missing tables
$sql = "
    SELECT e.*, 
           u.username, u.email, u.full_name,
           c.title as course_title, c.description as course_description
    FROM enrollments e
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN courses c ON e.course_id = c.id
";

$where_conditions = [];
$params = [];

if ($course_filter) {
    $where_conditions[] = "e.course_id = ?";
    $params[] = $course_filter;
}

if ($user_filter) {
    $where_conditions[] = "e.user_id = ?";
    $params[] = $user_filter;
}

if ($status_filter) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR c.title LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($where_conditions) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY e.enrolled_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $enrollments = $stmt->fetchAll();
} catch (Exception $e) {
    $enrollments = [];
    $error = 'Có lỗi khi tải danh sách đăng ký: ' . $e->getMessage();
}

// Get statistics
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_enrollments,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_enrollments,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_enrollments,
            COUNT(CASE WHEN enrolled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_enrollments_month,
            COUNT(DISTINCT user_id) as unique_students,
            COUNT(DISTINCT course_id) as enrolled_courses
        FROM enrollments
    ")->fetch();
} catch (Exception $e) {
    $stats = [
        'total_enrollments' => 0,
        'active_enrollments' => 0,
        'inactive_enrollments' => 0,
        'new_enrollments_month' => 0,
        'unique_students' => 0,
        'enrolled_courses' => 0
    ];
}

// Get courses and users for dropdowns
try {
    $courses = $pdo->query("SELECT id, title FROM courses ORDER BY title")->fetchAll();
    $users = $pdo->query("SELECT id, username, email, full_name FROM users WHERE role = 'student' ORDER BY username")->fetchAll();
} catch (Exception $e) {
    $courses = [];
    $users = [];
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Success/Error Messages -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-graduate me-2"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">Quản lý đăng ký khóa học của học viên</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal">
            <i class="fas fa-plus me-2"></i>Thêm đăng ký mới
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Tổng đăng ký
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_enrollments']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Đang hoạt động
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['active_enrollments']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Tạm dừng
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['inactive_enrollments']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-pause-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Học viên
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['unique_students']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                            Khóa học
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['enrolled_courses']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-book fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Mới (30 ngày)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['new_enrollments_month']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-plus fa-2x text-gray-300"></i>
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
            <div class="col-md-3">
                <label class="form-label">Khóa học</label>
                <select name="course_id" class="form-select">
                    <option value="">Tất cả khóa học</option>
                    <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Tạm dừng</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Tìm kiếm</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Nhập tên học viên, email hoặc khóa học..."
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

<!-- Enrollments Table -->
<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-list me-2"></i>Danh sách đăng ký khóa học 
            <span class="badge bg-primary ms-2"><?php echo count($enrollments); ?></span>
        </h6>
    </div>
    <div class="card-body">
        <?php if ($enrollments): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="enrollmentsTable">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="25%">Học viên</th>
                        <th width="25%">Khóa học</th>
                        <th width="10%">Trạng thái</th>
                        <th width="15%">Ngày đăng ký</th>
                        <th width="10%">Tiến độ</th>
                        <th width="10%">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $index => $enrollment): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-3">
                                    <?php echo strtoupper(substr($enrollment['username'] ?? 'N/A', 0, 2)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($enrollment['full_name'] ?: $enrollment['username'] ?: 'N/A'); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-at me-1"></i><?php echo htmlspecialchars($enrollment['email'] ?? 'N/A'); ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong class="text-primary"><?php echo htmlspecialchars($enrollment['course_title'] ?? 'N/A'); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php 
                                    $desc = $enrollment['course_description'] ?? '';
                                    echo $desc ? htmlspecialchars(mb_substr($desc, 0, 50)) . '...' : 'Chưa có mô tả';
                                    ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <?php $status = $enrollment['status'] ?? 'active'; ?>
                            <span class="badge bg-<?php echo $status === 'active' ? 'success' : 'warning'; ?> fs-6">
                                <?php echo $status === 'active' ? 'Hoạt động' : 'Tạm dừng'; ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('d/m/Y H:i', strtotime($enrollment['enrolled_at'])); ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <?php
                            // Get progress for this enrollment
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as completed_lessons FROM progress WHERE enrollment_id = ? AND completed = 1");
                                $stmt->execute([$enrollment['id']]);
                                $progress = $stmt->fetch();
                                
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total_lessons FROM lessons WHERE course_id = ?");
                                $stmt->execute([$enrollment['course_id']]);
                                $total = $stmt->fetch();
                                
                                $completed = $progress['completed_lessons'] ?? 0;
                                $total_lessons = $total['total_lessons'] ?? 0;
                                $percentage = $total_lessons > 0 ? round(($completed / $total_lessons) * 100) : 0;
                            } catch (Exception $e) {
                                $completed = 0;
                                $total_lessons = 0;
                                $percentage = 0;
                            }
                            ?>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-<?php echo $percentage < 50 ? 'danger' : ($percentage < 80 ? 'warning' : 'success'); ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $percentage; ?>%">
                                    <?php echo $percentage; ?>%
                                </div>
                            </div>
                            <small class="text-muted"><?php echo $completed; ?>/<?php echo $total_lessons; ?> bài</small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view-enrollment.php?id=<?php echo $enrollment['id']; ?>" 
                                   class="btn btn-outline-info btn-sm" title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Toggle Status -->
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $status === 'active' ? 'inactive' : 'active'; ?>">
                                    <button type="submit" 
                                            class="btn btn-outline-<?php echo $status === 'active' ? 'warning' : 'success'; ?> btn-sm" 
                                            title="<?php echo $status === 'active' ? 'Tạm dừng' : 'Kích hoạt'; ?>">
                                        <i class="fas fa-<?php echo $status === 'active' ? 'pause' : 'play'; ?>"></i>
                                    </button>
                                </form>
                                
                                <!-- Delete Enrollment -->
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="confirmDelete(<?php echo $enrollment['id']; ?>, '<?php echo htmlspecialchars($enrollment['username'] ?? 'N/A'); ?>', '<?php echo htmlspecialchars($enrollment['course_title'] ?? 'N/A'); ?>')" 
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
                        Hiển thị <?php echo count($enrollments); ?> đăng ký
                        <?php if ($search || $course_filter || $status_filter): ?>
                        - <a href="enrollments.php" class="text-decoration-none">Xem tất cả</a>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportEnrollments()">
                            <i class="fas fa-download me-2"></i>Xuất Excel
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="sendBulkNotification()">
                            <i class="fas fa-bell me-2"></i>Gửi thông báo
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-user-graduate fa-4x text-muted mb-4"></i>
            <h4 class="text-muted">Không tìm thấy đăng ký nào</h4>
            <p class="text-muted mb-4">
                <?php if ($search || $course_filter || $status_filter): ?>
                Thử thay đổi bộ lọc hoặc <a href="enrollments.php" class="text-decoration-none">xem tất cả đăng ký</a>
                <?php else: ?>
                Hệ thống chưa có đăng ký nào. Hãy thêm đăng ký đầu tiên!
                <?php endif; ?>
            </p>
            <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal">
                <i class="fas fa-plus me-2"></i>Thêm đăng ký mới
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Enrollment Modal -->
<div class="modal fade" id="addEnrollmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus text-primary me-2"></i>
                    Thêm đăng ký mới
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="userId" class="form-label">Học viên <span class="text-danger">*</span></label>
                        <select class="form-select" id="userId" name="user_id" required>
                            <option value="">Chọn học viên...</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?> 
                                (<?php echo htmlspecialchars($user['email']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Chọn học viên muốn đăng ký khóa học</div>
                    </div>
                    <div class="mb-3">
                        <label for="courseId" class="form-label">Khóa học <span class="text-danger">*</span></label>
                        <select class="form-select" id="courseId" name="course_id" required>
                            <option value="">Chọn khóa học...</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Chọn khóa học để đăng ký</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Hủy bỏ
                    </button>
                    <button type="submit" name="add_enrollment" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Thêm đăng ký
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
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Xác nhận xóa đăng ký
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa đăng ký:</p>
                <p class="fw-bold text-danger">
                    <span id="studentName"></span> - <span id="courseName"></span>
                </p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Cảnh báo:</strong> Hành động này sẽ xóa:
                    <ul class="mb-0 mt-2">
                        <li>Đăng ký khóa học</li>
                        <li>Tiến độ học tập (nếu có)</li>
                        <li>Tất cả dữ liệu liên quan</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Hủy bỏ
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="delete_enrollment" value="1">
                    <input type="hidden" name="enrollment_id" id="deleteEnrollmentId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa đăng ký
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

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-secondary {
    border-left: 0.25rem solid #858796 !important;
}

.border-left-danger {
    border-left: 0.25rem solid #e74a3b !important;
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
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

#enrollmentsTable tbody tr:hover {
    background-color: #f8f9fc;
}

.table-responsive {
    border-radius: 0.5rem;
}

.progress {
    background-color: #e9ecef;
}
</style>

<!-- Custom JavaScript -->
<script>
function confirmDelete(enrollmentId, studentName, courseName) {
    document.getElementById('deleteEnrollmentId').value = enrollmentId;
    document.getElementById('studentName').textContent = studentName;
    document.getElementById('courseName').textContent = courseName;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function exportEnrollments() {
    alert('Tính năng xuất Excel đang được phát triển!');
}

function sendBulkNotification() {
    alert('Tính năng gửi thông báo hàng loạt đang được phát triển!');
}

// Auto-submit form when filters change
document.querySelector('select[name="course_id"]')?.addEventListener('change', function() {
    this.form.submit();
});

document.querySelector('select[name="status"]')?.addEventListener('change', function() {
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
        if (bootstrap.Alert.getOrCreateInstance) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }
    });
}, 5000);

// Confirm status toggle
document.querySelectorAll('form').forEach(form => {
    if (form.querySelector('input[name="toggle_status"]')) {
        form.addEventListener('submit', function(e) {
            const newStatus = form.querySelector('input[name="new_status"]').value;
            const action = newStatus === 'active' ? 'kích hoạt' : 'tạm dừng';
            
            if (!confirm(`Bạn có chắc chắn muốn ${action} đăng ký này?`)) {
                e.preventDefault();
            }
        });
    }
});

// Reset modal when hidden
document.getElementById('addEnrollmentModal').addEventListener('hidden.bs.modal', function() {
    this.querySelector('form').reset();
});
</script>

<?php include 'includes/admin-footer.php'; ?>