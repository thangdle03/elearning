<?php

require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý người dùng';
$current_page = 'users';

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        // Prevent admin from deleting themselves
        if ($user_id == $_SESSION['user_id']) {
            $error = 'Bạn không thể xóa tài khoản của chính mình!';
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Check if user has enrollments
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $enrollment_count = $stmt->fetchColumn();
                    
                    if ($enrollment_count > 0) {
                        $error = "Không thể xóa người dùng này vì đã có {$enrollment_count} đăng ký khóa học!";
                    } else {
                        // Delete user and related data
                        $pdo->beginTransaction();
                        
                        // Delete progress records
                        try {
                            $stmt = $pdo->prepare("DELETE FROM progress WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                        } catch (Exception $e) {
                            // Progress table might not exist
                        }
                        
                        // Delete user
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $pdo->commit();
                        $message = 'Đã xóa người dùng thành công!';
                    }
                } else {
                    $error = 'Người dùng không tồn tại!';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Có lỗi xảy ra khi xóa người dùng: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $user_id = (int)$_POST['user_id'];
        $new_status = $_POST['new_status'] === 'active' ? 'active' : 'inactive';
        
        if ($user_id == $_SESSION['user_id'] && $new_status === 'inactive') {
            $error = 'Bạn không thể vô hiệu hóa tài khoản của chính mình!';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $user_id])) {
                    $message = 'Đã cập nhật trạng thái người dùng!';
                } else {
                    $error = 'Có lỗi khi cập nhật trạng thái!';
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['change_role'])) {
        $user_id = (int)$_POST['user_id'];
        $new_role = $_POST['new_role'];
        
        if ($user_id == $_SESSION['user_id']) {
            $error = 'Bạn không thể thay đổi vai trò của chính mình!';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                if ($stmt->execute([$new_role, $user_id])) {
                    $message = 'Đã cập nhật vai trò người dùng!';
                } else {
                    $error = 'Có lỗi khi cập nhật vai trò!';
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
}

// Get filter parameters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT u.*, COUNT(e.id) as enrollment_count FROM users u 
        LEFT JOIN enrollments e ON u.id = e.user_id";

$where_conditions = [];
$params = [];

if ($role_filter) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($where_conditions) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
    $error = 'Có lỗi khi tải danh sách người dùng: ' . $e->getMessage();
}

// Get statistics
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as total_admins,
            COUNT(CASE WHEN role = 'student' THEN 1 END) as total_students,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_month
        FROM users
    ")->fetch();
} catch (Exception $e) {
    $stats = [
        'total_users' => 0,
        'total_admins' => 0,
        'total_students' => 0,
        'active_users' => 0,
        'inactive_users' => 0,
        'new_users_month' => 0
    ];
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-users me-2"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">Quản lý tất cả người dùng trong hệ thống</p>
    </div>
    <div>
        <a href="add-user.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Thêm người dùng mới
        </a>
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
                            Tổng người dùng
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_users']); ?>
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
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Đang hoạt động
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['active_users']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                            Admin
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_admins']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-shield fa-2x text-gray-300"></i>
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
                            <?php echo number_format($stats['total_students']); ?>
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
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Vô hiệu hóa
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['inactive_users']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-times fa-2x text-gray-300"></i>
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
                            Mới (30 ngày)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['new_users_month']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-plus fa-2x text-gray-300"></i>
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
                <label class="form-label">Vai trò</label>
                <select name="role" class="form-select">
                    <option value="">Tất cả vai trò</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Học viên</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Vô hiệu hóa</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tìm kiếm</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Nhập tên, email hoặc tên đăng nhập..."
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

<!-- Users Table -->
<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-list me-2"></i>Danh sách người dùng 
            <span class="badge bg-primary ms-2"><?php echo count($users); ?></span>
        </h6>
    </div>
    <div class="card-body">
        <?php if ($users): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="usersTable">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="20%">Thông tin cơ bản</th>
                        <th width="15%">Email</th>
                        <th width="10%">Vai trò</th>
                        <th width="10%">Trạng thái</th>
                        <th width="10%">Đăng ký khóa học</th>
                        <th width="15%">Ngày tạo</th>
                        <th width="15%">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $index => $user): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-3">
                                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong>
                                    <br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?> fs-6">
                                <?php echo $user['role'] === 'admin' ? 'Admin' : 'Học viên'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?> fs-6">
                                <?php echo $user['status'] === 'active' ? 'Hoạt động' : 'Vô hiệu hóa'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info fs-6">
                                <?php echo number_format($user['enrollment_count']); ?> khóa học
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view-user.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-info btn-sm" title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-user.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" title="Chỉnh sửa">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <!-- Toggle Status -->
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                    <button type="submit" 
                                            class="btn btn-outline-<?php echo $user['status'] === 'active' ? 'warning' : 'success'; ?> btn-sm" 
                                            title="<?php echo $user['status'] === 'active' ? 'Vô hiệu hóa' : 'Kích hoạt'; ?>">
                                        <i class="fas fa-<?php echo $user['status'] === 'active' ? 'user-times' : 'user-check'; ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <!-- Delete User -->
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                        title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
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
                        Hiển thị <?php echo count($users); ?> người dùng
                        <?php if ($search || $role_filter || $status_filter): ?>
                        - <a href="users.php" class="text-decoration-none">Xem tất cả</a>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportUsers()">
                            <i class="fas fa-download me-2"></i>Xuất Excel
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="sendBulkEmail()">
                            <i class="fas fa-envelope me-2"></i>Gửi email hàng loạt
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-users fa-4x text-muted mb-4"></i>
            <h4 class="text-muted">Không tìm thấy người dùng nào</h4>
            <p class="text-muted mb-4">
                <?php if ($search || $role_filter || $status_filter): ?>
                Thử thay đổi bộ lọc hoặc <a href="users.php" class="text-decoration-none">xem tất cả người dùng</a>
                <?php else: ?>
                Hệ thống chưa có người dùng nào. Hãy thêm người dùng đầu tiên!
                <?php endif; ?>
            </p>
            <a href="add-user.php" class="btn btn-primary btn-lg">
                <i class="fas fa-user-plus me-2"></i>Thêm người dùng mới
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
                    Xác nhận xóa người dùng
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa người dùng:</p>
                <p class="fw-bold text-danger" id="userName"></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Cảnh báo:</strong> Hành động này sẽ xóa:
                    <ul class="mb-0 mt-2">
                        <li>Tài khoản người dùng</li>
                        <li>Tiến độ học tập (nếu có)</li>
                        <li>Tất cả dữ liệu liên quan</li>
                    </ul>
                </div>
                <p class="text-muted">Hành động này không thể hoàn tác!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Hủy bỏ
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="delete_user" value="1">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa người dùng
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

.border-left-danger {
    border-left: 0.25rem solid #e74a3b !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.border-left-secondary {
    border-left: 0.25rem solid #858796 !important;
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

#usersTable tbody tr:hover {
    background-color: #f8f9fc;
}

.table-responsive {
    border-radius: 0.5rem;
}
</style>

<!-- Custom JavaScript -->
<script>
function confirmDelete(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('userName').textContent = userName;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function exportUsers() {
    alert('Tính năng xuất Excel đang được phát triển!');
}

function sendBulkEmail() {
    alert('Tính năng gửi email hàng loạt đang được phát triển!');
}

// Auto-submit form when filters change
document.querySelector('select[name="role"]')?.addEventListener('change', function() {
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
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        bsAlert.close();
    });
}, 5000);

// Confirm status toggle
document.querySelectorAll('form').forEach(form => {
    if (form.querySelector('input[name="toggle_status"]')) {
        form.addEventListener('submit', function(e) {
            const newStatus = form.querySelector('input[name="new_status"]').value;
            const action = newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa';
            
            if (!confirm(`Bạn có chắc chắn muốn ${action} người dùng này?`)) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php include 'includes/admin-footer.php'; ?>