<?php
// filepath: d:\Xampp\htdocs\elearning\admin\users.php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý người dùng';
$current_page = 'users';

// Initialize variables
$message = '';
$error = '';
$debug = isset($_GET['debug']);

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // DELETE USER
    if (isset($_POST['delete_user'])) {
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            $error = 'ID người dùng không hợp lệ!';
        } elseif ($user_id == $_SESSION['user_id']) {
            $error = 'Không thể xóa chính mình!';
        } else {
            try {
                // Check if user has enrollments
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $enrollment_count = $stmt->fetchColumn();

                if ($enrollment_count > 0) {
                    $error = "Không thể xóa người dùng này vì đã đăng ký {$enrollment_count} khóa học!";
                } else {
                    // Delete user and related data
                    $pdo->beginTransaction();

                    // Delete progress first
                    $stmt = $pdo->prepare("DELETE FROM progress WHERE user_id = ?");
                    $stmt->execute([$user_id]);

                    // Delete user
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$user_id]) && $stmt->rowCount() > 0) {
                        $pdo->commit();
                        header('Location: users.php?success=delete');
                        exit;
                    } else {
                        $pdo->rollBack();
                        $error = 'Không tìm thấy người dùng để xóa!';
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Có lỗi xảy ra khi xóa người dùng: ' . $e->getMessage();
            }
        }
    }

    // TOGGLE STATUS
    elseif (isset($_POST['toggle_status'])) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';

        if ($user_id <= 0) {
            $error = 'ID người dùng không hợp lệ!';
        } elseif ($user_id == $_SESSION['user_id']) {
            $error = 'Không thể thay đổi trạng thái của chính mình!';
        } elseif (!in_array($new_status, ['active', 'inactive'])) {
            $error = 'Trạng thái không hợp lệ!';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$new_status, $user_id]) && $stmt->rowCount() > 0) {
                    header('Location: users.php?success=toggle');
                    exit;
                } else {
                    $error = 'Không tìm thấy người dùng để cập nhật!';
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra khi cập nhật trạng thái: ' . $e->getMessage();
            }
        }
    }

    // BULK ACTIONS
    elseif (isset($_POST['bulk_action']) && !empty($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selected_users = isset($_POST['selected_users']) ? $_POST['selected_users'] : [];

        if (empty($selected_users) || !is_array($selected_users)) {
            $error = 'Vui lòng chọn ít nhất một người dùng để thực hiện thao tác!';
        } elseif (!in_array($action, ['activate', 'deactivate', 'delete'])) {
            $error = 'Thao tác không hợp lệ!';
        } else {
            // Clean and validate user IDs
            $user_ids = [];
            foreach ($selected_users as $id) {
                $id = (int)$id;
                if ($id > 0 && $id != $_SESSION['user_id']) { // Don't include current admin
                    $user_ids[] = $id;
                }
            }

            if (empty($user_ids)) {
                $error = 'Không có người dùng hợp lệ được chọn (không thể thao tác với chính mình)!';
            } else {
                $success_count = 0;
                $failed_count = 0;

                try {
                    $pdo->beginTransaction();

                    foreach ($user_ids as $user_id) {
                        try {
                            if ($action === 'activate') {
                                $stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                                if ($stmt->execute([$user_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                }
                            } elseif ($action === 'deactivate') {
                                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                                if ($stmt->execute([$user_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                }
                            } elseif ($action === 'delete') {
                                // Check enrollments first
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
                                $stmt->execute([$user_id]);
                                $enrollment_count = $stmt->fetchColumn();

                                if ($enrollment_count > 0) {
                                    $failed_count++;
                                } else {
                                    // Delete progress first
                                    $stmt = $pdo->prepare("DELETE FROM progress WHERE user_id = ?");
                                    $stmt->execute([$user_id]);

                                    // Delete user
                                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                                    if ($stmt->execute([$user_id]) && $stmt->rowCount() > 0) {
                                        $success_count++;
                                    } else {
                                        $failed_count++;
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            $failed_count++;
                        }
                    }

                    $pdo->commit();

                    if ($success_count > 0) {
                        $action_names = [
                            'activate' => 'kích hoạt',
                            'deactivate' => 'vô hiệu hóa',
                            'delete' => 'xóa'
                        ];
                        $action_name = $action_names[$action] ?? 'cập nhật';

                        header('Location: users.php?success=bulk&action=' . urlencode($action) . '&count=' . $success_count . '&failed=' . $failed_count);
                        exit;
                    } else {
                        $error = 'Không có người dùng nào được cập nhật!';
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
        case 'delete':
            $message = 'Đã xóa người dùng thành công!';
            break;
        case 'toggle':
            $message = 'Đã cập nhật trạng thái người dùng thành công!';
            break;
        case 'bulk':
            $action = $_GET['action'] ?? '';
            $count = (int)($_GET['count'] ?? 0);
            $failed = (int)($_GET['failed'] ?? 0);

            $action_names = [
                'activate' => 'kích hoạt',
                'deactivate' => 'vô hiệu hóa',
                'delete' => 'xóa'
            ];
            $action_name = $action_names[$action] ?? 'cập nhật';

            $message = "Đã {$action_name} thành công {$count} người dùng";
            if ($failed > 0) {
                $message .= ", {$failed} người dùng không thể thực hiện";
            }
            $message .= "!";
            break;
    }
}

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));

$requested_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($requested_limit, [5, 10, 20, 50]) ? $requested_limit : 10;

$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($role_filter)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Sort options
$order_clause = match ($sort) {
    'oldest' => 'ORDER BY u.created_at ASC',
    'username' => 'ORDER BY u.username ASC',
    'email' => 'ORDER BY u.email ASC',
    'role' => 'ORDER BY u.role ASC',
    default => 'ORDER BY u.created_at DESC'
};

// Get total count
try {
    $count_sql = "SELECT COUNT(*) FROM users u $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_records = (int)$stmt->fetchColumn();

    $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Get users with enrollment count
    $sql = "SELECT u.id, u.username, u.email, u.role, u.status, u.created_at, u.updated_at,
            (SELECT COUNT(*) FROM enrollments WHERE user_id = u.id) as enrollment_count,
            (SELECT COUNT(*) FROM progress WHERE user_id = u.id) as progress_count
            FROM users u 
            $where_clause
            $order_clause
            LIMIT $offset, $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $total_records = 0;
    $total_pages = 1;
    $error = 'Lỗi database: ' . $e->getMessage();
}

// Get statistics
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
            COUNT(CASE WHEN role = 'user' THEN 1 END) as user_count,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d
        FROM users
    ")->fetch();
} catch (Exception $e) {
    $stats = [
        'total_users' => 0,
        'admin_count' => 0,
        'user_count' => 0,
        'active_users' => 0,
        'inactive_users' => 0,
        'new_users_30d' => 0
    ];
}

// Check if any filter is active
$has_filters = !empty($search) || !empty($role_filter) || !empty($status_filter) || ($sort !== 'newest');
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <!-- Success/Error Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-users me-2"></i><?php echo $page_title; ?>
            </h1>
            <p class="text-muted mb-0">Quản lý tài khoản người dùng hệ thống</p>
        </div>
        <div class="d-flex gap-2">
            <a href="add-user.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Thêm người dùng
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tổng người dùng</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_users']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Đang hoạt động</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['active_users']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Quản trị viên</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['admin_count']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Thành viên mới (30 ngày)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['new_users_30d']); ?></div>
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
                <i class="fas fa-filter me-2"></i>Bộ lọc và tìm kiếm
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3" id="filterForm">
                <input type="hidden" name="limit" value="<?php echo $limit; ?>">

                <div class="col-md-4">
                    <label class="form-label">Tìm kiếm</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                            placeholder="Nhập tên người dùng hoặc email..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <?php if (!empty($search)): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()" title="Xóa tìm kiếm">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Vai trò</label>
                    <select name="role" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Quản trị viên</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Người dùng</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Sắp xếp</label>
                    <select name="sort" class="form-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                        <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Tên A-Z</option>
                        <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Email A-Z</option>
                        <option value="role" <?php echo $sort === 'role' ? 'selected' : ''; ?>>Theo vai trò</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Tìm kiếm
                    </button>
                    <?php if ($has_filters): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                            <i class="fas fa-refresh me-2"></i>Reset
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($has_filters): ?>
                <div class="mt-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php if (!empty($search)): ?>
                            <span class="badge bg-primary d-inline-flex align-items-center">
                                <span>Tìm kiếm: "<?php echo htmlspecialchars($search); ?>"</span>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('search')" style="font-size: 0.6em; width: 12px; height: 12px;"></button>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($role_filter)): ?>
                            <span class="badge bg-info d-inline-flex align-items-center">
                                <span>Vai trò: <?php echo $role_filter === 'admin' ? 'Quản trị viên' : 'Người dùng'; ?></span>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('role')" style="font-size: 0.6em; width: 12px; height: 12px;"></button>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($status_filter)): ?>
                            <span class="badge bg-<?php echo $status_filter === 'active' ? 'success' : 'secondary'; ?> d-inline-flex align-items-center">
                                <span>Trạng thái: <?php echo $status_filter === 'active' ? 'Hoạt động' : 'Ngừng hoạt động'; ?></span>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('status')" style="font-size: 0.6em; width: 12px; height: 12px;"></button>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>Danh sách người dùng
                <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
            </h6>
            <div class="d-flex gap-2">
                <!-- Bulk Action Buttons -->
                <button type="button" class="btn btn-sm btn-success" onclick="performBulkAction('activate')" id="bulkActivateBtn" style="display: none;">
                    <i class="fas fa-check-circle me-1"></i>Kích hoạt
                </button>
                <button type="button" class="btn btn-sm btn-warning" onclick="performBulkAction('deactivate')" id="bulkDeactivateBtn" style="display: none;">
                    <i class="fas fa-times-circle me-1"></i>Vô hiệu hóa
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="performBulkAction('delete')" id="bulkDeleteBtn" style="display: none;">
                    <i class="fas fa-trash me-1"></i>Xóa
                </button>
            </div>
        </div>

        <div class="card-body">
            <?php if ($users): ?>
                <form id="bulkActionForm" method="POST" style="display: none;">
                    <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
                    <div id="selectedUsersContainer"></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="3%" class="text-center">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th width="5%" class="text-center">ID</th>
                                <th width="30%" class="text-left">Thông tin người dùng</th>
                                <th width="25%" class="text-center">Email</th>
                                <th width="10%" class="text-center">Vai trò</th>
                                <th width="10%" class="text-center">Khóa học</th>
                                <th width="12%" class="text-center">Ngày tham gia</th>
                                <th width="10%" class="text-center">Trạng thái</th>
                                <th width="5%" class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr <?php echo $user['id'] == $_SESSION['user_id'] ? 'class="table-warning"' : ''; ?>>
                                    <td class="text-center">
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <input type="checkbox" class="form-check-input user-checkbox"
                                                value="<?php echo $user['id']; ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $user['id']; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div class="avatar-circle bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'success'; ?>">
                                                    <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'user-shield' : 'user'; ?> text-white"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge bg-info ms-1">Bạn</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    ID: <?php echo $user['id']; ?> |
                                                    <?php echo $user['enrollment_count']; ?> khóa học
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div>
                                            <i class="fas fa-envelope text-muted me-1"></i>
                                            <small><?php echo htmlspecialchars($user['email']); ?></small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'success'; ?> fs-6">
                                            <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'shield-alt' : 'user'; ?> me-1"></i>
                                            <?php echo $user['role'] === 'admin' ? 'Admin' : 'User'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info fs-6"><?php echo $user['enrollment_count']; ?></span>
                                        <?php if ($user['progress_count'] > 0): ?>
                                            <br><small class="text-muted"><?php echo $user['progress_count']; ?> tiến độ</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-info">Tài khoản của bạn</span>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                <input type="hidden" name="toggle_status" value="1">
                                                <button type="submit"
                                                    class="btn btn-sm status-btn <?php echo $user['status'] === 'active' ? 'btn-success' : 'btn-secondary'; ?>"
                                                    onclick="return confirm('Bạn có chắc muốn thay đổi trạng thái?')"
                                                    title="<?php echo $user['status'] === 'active' ? 'Đang hoạt động - Click để tạm dừng' : 'Tạm dừng - Click để kích hoạt'; ?>">
                                                    <i class="fas fa-<?php echo $user['status'] === 'active' ? 'check' : 'times'; ?>"></i>
                                                    <?php echo $user['status'] === 'active' ? 'Hoạt động' : 'Tạm dừng'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <!-- Fixed detail button -->
                                            <a href="user-detail.php?id=<?php echo $user['id']; ?>"
                                                class="btn btn-sm btn-outline-info" 
                                                title="Xem chi tiết"
                                                onclick="console.log('Clicking detail for user ID: <?php echo $user['id']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <a href="edit-user.php?id=<?php echo $user['id']; ?>"
                                                class="btn btn-sm btn-outline-primary" title="Chỉnh sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')" title="Xóa">
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

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        <small class="text-muted">
                            Hiển thị <?php echo count($users); ?> người dùng
                            (<?php echo number_format($offset + 1); ?> - <?php echo number_format($offset + count($users)); ?>
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
                    <i class="fas fa-users fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">Không tìm thấy người dùng nào</h4>
                    <p class="text-muted mb-4">
                        <?php if ($has_filters): ?>
                            Thử thay đổi bộ lọc hoặc
                            <a href="users.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-refresh me-1"></i>Reset bộ lọc
                            </a>
                        <?php else: ?>
                            Hệ thống chưa có người dùng nào. Hãy tạo tài khoản đầu tiên!
                        <?php endif; ?>
                    </p>
                    <a href="add-user.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Thêm người dùng
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa người dùng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                </div>
                <p class="text-center">
                    Bạn có chắc chắn muốn xóa người dùng<br>
                    <strong id="userName"></strong>?
                </p>
                <div class="alert alert-warning">
                    <strong>Cảnh báo:</strong> Hành động này sẽ xóa vĩnh viễn tài khoản và tất cả dữ liệu liên quan!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" id="deleteForm" class="d-inline">
                    <input type="hidden" name="user_id" id="userIdInput" value="">
                    <input type="hidden" name="delete_user" value="1">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa người dùng
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* === AVATAR CIRCLE === */
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    /* === CARD STYLING === */
    .border-left-primary {
        border-left: 0.375rem solid #6366f1 !important;
        background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
    }

    .border-left-success {
        border-left: 0.375rem solid #10b981 !important;
        background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
    }

    .border-left-info {
        border-left: 0.375rem solid #06b6d4 !important;
        background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
    }

    .border-left-warning {
        border-left: 0.375rem solid #f59e0b !important;
        background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);
    }

    /* === TABLE STYLING === */
    .table {
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .table thead th {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border: none;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        padding: 1.25rem 1rem;
        vertical-align: middle !important;
        position: relative;
    }

    .table thead th::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 40%;
        height: 2px;
        background: linear-gradient(90deg, transparent, #6366f1, transparent);
        border-radius: 1px;
    }

    .table thead th.text-left::after {
        left: 1rem;
        transform: none;
        width: 30%;
    }

    .table tbody td {
        border: none;
        border-bottom: 1px solid #f1f5f9;
        padding: 1.25rem 1rem;
        vertical-align: middle;
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background-color: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .table-warning {
        background-color: #fff3cd !important;
    }

    .table-warning:hover {
        background-color: #ffeaa7 !important;
    }

    /* === STATUS BUTTON STYLING === */
    .status-btn {
        white-space: nowrap !important;
        min-width: 90px !important;
        font-size: 0.75rem !important;
        padding: 0.375rem 0.75rem !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
    }

    .status-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    /* === BADGES === */
    .badge {
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        letter-spacing: 0.025em;
    }

    /* === FILTER BADGES FIX === */
    .badge.d-inline-flex {
        align-items: center !important;
        white-space: nowrap !important;
        line-height: 1.2 !important;
        padding: 0.35rem 0.6rem !important;
        font-size: 0.75rem !important;
    }

    .badge .btn-close {
        font-size: 0.6em !important;
        width: 12px !important;
        height: 12px !important;
        margin-left: 0.5rem !important;
        flex-shrink: 0 !important;
    }

    .d-flex.gap-2 {
        flex-wrap: wrap !important;
        align-items: center !important;
    }

    .d-flex.gap-2 .badge {
        flex-shrink: 0 !important;
        margin-right: 0.5rem !important;
        margin-bottom: 0.25rem !important;
    }

    /* === BUTTONS === */
    .btn {
        border-radius: 0.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1.5px solid transparent;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-outline-info:hover {
        background: #06b6d4;
        border-color: #06b6d4;
        color: #fff;
    }

    .btn-outline-primary:hover {
        background: #6366f1;
        border-color: #6366f1;
        color: #fff;
    }

    .btn-outline-danger:hover {
        background: #ef4444;
        border-color: #ef4444;
        color: #fff;
    }

    /* === ICONS === */
    .fa-users {
        color: #6366f1 !important;
    }

    .fa-user-check {
        color: #10b981 !important;
    }

    .fa-user-shield {
        color: #06b6d4 !important;
    }

    .fa-user-plus {
        color: #f59e0b !important;
    }

    /* === CARD EFFECTS === */
    .card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
    }

    /* === PAGINATION === */
    .pagination .page-item.active .page-link {
        background-color: #6366f1;
        border-color: #6366f1;
        color: white;
        font-weight: 600;
    }

    .pagination .page-link {
        color: #5a5c69;
        border: 1px solid #dddfeb;
        transition: all 0.15s ease-in-out;
    }

    .pagination .page-link:hover {
        color: #224abe;
        background-color: #eaecf4;
        border-color: #d1d3e2;
    }

    /* === RESPONSIVE === */
    @media (max-width: 768px) {
        .table thead th {
            padding: 1rem 0.5rem;
            font-size: 0.7rem;
        }

        .table tbody td {
            padding: 1rem 0.5rem;
            font-size: 0.85rem;
        }

        .status-btn {
            font-size: 0.7rem !important;
            padding: 0.25rem 0.5rem !important;
            min-width: 70px !important;
        }

        .avatar-circle {
            width: 35px;
            height: 35px;
            font-size: 1rem;
        }

        .h5 {
            font-size: 1.5rem;
        }

        .fa-2x {
            font-size: 2rem;
        }

        .badge.d-inline-flex {
            font-size: 0.7rem !important;
            padding: 0.25rem 0.5rem !important;
        }

        .badge .btn-close {
            width: 10px !important;
            height: 10px !important;
        }
    }

    /* === ANIMATIONS === */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card {
        animation: fadeInUp 0.6s ease forwards;
    }

    .card:nth-child(1) {
        animation-delay: 0.1s;
    }

    .card:nth-child(2) {
        animation-delay: 0.2s;
    }

    .card:nth-child(3) {
        animation-delay: 0.3s;
    }

    .card:nth-child(4) {
        animation-delay: 0.4s;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert:not(.alert-info)').forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);

        // Get elements
        const selectAllCheckbox = document.getElementById('selectAll');
        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const bulkButtons = document.querySelectorAll('[id^="bulk"]');

        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                userCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkButtons();
            });
        }

        // Individual checkbox change
        userCheckboxes.forEach(checkbox => {
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

        updateBulkButtons();
    });

    function updateSelectAllState() {
        const selectAllCheckbox = document.getElementById('selectAll');
        if (!selectAllCheckbox) return;

        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
        const totalCount = userCheckboxes.length;

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

    function updateBulkButtons() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        const bulkButtons = document.querySelectorAll('[id^="bulk"]');

        if (checkedBoxes.length > 0) {
            bulkButtons.forEach(btn => btn.style.display = 'inline-block');
        } else {
            bulkButtons.forEach(btn => btn.style.display = 'none');
        }
    }

    function performBulkAction(action) {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');

        if (checkedBoxes.length === 0) {
            alert('Vui lòng chọn ít nhất một người dùng!');
            return;
        }

        const messages = {
            'activate': `Bạn có chắc muốn kích hoạt ${checkedBoxes.length} người dùng đã chọn?`,
            'deactivate': `Bạn có chắc muốn vô hiệu hóa ${checkedBoxes.length} người dùng đã chọn?`,
            'delete': `Bạn có chắc muốn XÓA ${checkedBoxes.length} người dùng đã chọn?\n\nCảnh báo: Chỉ xóa được người dùng chưa đăng ký khóa học!`
        };

        if (!confirm(messages[action])) {
            return;
        }

        const form = document.getElementById('bulkActionForm');
        const actionInput = document.getElementById('bulkActionInput');
        const container = document.getElementById('selectedUsersContainer');

        actionInput.value = action;
        container.innerHTML = '';

        checkedBoxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_users[]';
            input.value = checkbox.value;
            container.appendChild(input);
        });

        form.submit();
    }

    function deleteUser(userId, userName) {
        document.getElementById('userName').textContent = userName;
        document.getElementById('userIdInput').value = userId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function clearSearch() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.value = '';
            document.getElementById('filterForm').submit();
        }
    }

    function resetFilters() {
        window.location.href = 'users.php?limit=<?php echo $limit; ?>';
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