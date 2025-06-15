<?php
// filepath: d:\Xampp\htdocs\elearning\admin\enrollments.php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý đăng ký khóa học';
$current_page = 'enrollments';

// Initialize variables
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD NEW ENROLLMENT - CHECK BOTH POSSIBLE SUBMIT NAMES
    if (isset($_POST['add_enrollment']) || isset($_POST['submit_enrollment']) || count($_POST) >= 2) {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
        $allow_duplicate = isset($_POST['allow_duplicate']) ? (bool)$_POST['allow_duplicate'] : false;

        if ($user_id <= 0) {
            $error = 'Vui lòng chọn người dùng hợp lệ!';
        } elseif ($course_id <= 0) {
            $error = 'Vui lòng chọn khóa học hợp lệ!';
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $error = 'Người dùng không tồn tại!';
                } else {
                    // Check if course exists
                    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$course) {
                        $error = 'Khóa học không tồn tại!';
                    } else {
                        // **CHECK IF ALREADY ENROLLED**
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count, MIN(enrolled_at) as first_enrolled FROM enrollments WHERE user_id = ? AND course_id = ?");
                        $stmt->execute([$user_id, $course_id]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        $existing_count = (int)$existing['count'];
                        $first_enrolled = $existing['first_enrolled'];

                        if ($existing_count > 0 && !$allow_duplicate) {
                            // **ENROLLMENT ALREADY EXISTS - SHOW WARNING**
                            $first_date = date('d/m/Y H:i', strtotime($first_enrolled));
                            $error = "⚠️ Người dùng '{$user['username']}' đã được đăng ký vào khóa '{$course['title']}' từ trước!\n";
                            $error .= "📅 Lần đầu đăng ký: $first_date\n";
                            $error .= "🔢 Số lần đã đăng ký: $existing_count lần\n\n";
                            $error .= "Bạn có muốn đăng ký thêm lần nữa không? (Admin có thể cho phép đăng ký trùng lặp)";

                            // Store data for reshow in form
                            $_SESSION['duplicate_data'] = [
                                'user_id' => $user_id,
                                'course_id' => $course_id,
                                'user_name' => $user['username'],
                                'course_title' => $course['title'],
                                'existing_count' => $existing_count,
                                'first_enrolled' => $first_date
                            ];
                        } else {
                            // **PROCEED WITH ENROLLMENT**
                            $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, enrolled_at) VALUES (?, ?, NOW())");
                            $result = $stmt->execute([$user_id, $course_id]);

                            if ($result) {
                                if ($existing_count > 0) {
                                    $message = "✅ Đã thêm đăng ký trùng lặp thành công!\n";
                                    $message .= "👤 Người dùng: '{$user['username']}'\n";
                                    $message .= "📚 Khóa học: '{$course['title']}'\n";
                                    $message .= "🔢 Tổng số lần đăng ký: " . ($existing_count + 1) . " lần";
                                } else {
                                    $message = "✅ Đã thêm đăng ký mới thành công!\n";
                                    $message .= "👤 Người dùng: '{$user['username']}'\n";
                                    $message .= "📚 Khóa học: '{$course['title']}'\n";
                                    $message .= "🎉 Đăng ký lần đầu tiên!";
                                }

                                // Clear duplicate data and redirect
                                unset($_SESSION['duplicate_data']);
                                $_SESSION['enrollment_success'] = $message;
                                header('Location: ' . $_SERVER['PHP_SELF']);
                                exit;
                            } else {
                                $error = 'Không thể thêm đăng ký vào database!';
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
}

// Handle success messages from session
if (isset($_SESSION['enrollment_success'])) {
    $message = $_SESSION['enrollment_success'];
    unset($_SESSION['enrollment_success']);
}

// Get duplicate data from session
$duplicate_data = $_SESSION['duplicate_data'] ?? null;

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$course_filter = (int)($_GET['course'] ?? 0);
$user_filter = (int)($_GET['user'] ?? 0);
$role_filter = $_GET['role'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));

// Page limit like users.php
$requested_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($requested_limit, [5, 10, 20, 50]) ? $requested_limit : 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR c.title LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($course_filter > 0) {
    $where_conditions[] = "e.course_id = ?";
    $params[] = $course_filter;
}

if ($user_filter > 0) {
    $where_conditions[] = "e.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($role_filter)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Sort options
$order_clause = match ($sort) {
    'oldest' => 'ORDER BY e.enrolled_at ASC',
    'username' => 'ORDER BY u.username ASC',
    'course' => 'ORDER BY c.title ASC',
    'progress' => 'ORDER BY progress_percentage DESC',
    'role' => 'ORDER BY u.role ASC',
    default => 'ORDER BY e.enrolled_at DESC'
};

// Get total count
try {
    $count_sql = "
        SELECT COUNT(*) 
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_records = (int)$stmt->fetchColumn();

    $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }
} catch (Exception $e) {
    $total_records = 0;
    $total_pages = 1;
    $error = 'Lỗi đếm dữ liệu: ' . $e->getMessage();
}

// Get enrollments data
try {
    $sql = "
        SELECT 
            e.user_id,
            e.course_id,
            e.enrolled_at,
            u.username,
            u.email,
            u.role as user_role,
            c.title as course_title,
            c.price as course_price,
            COALESCE(
                (SELECT COUNT(*) FROM lessons l WHERE l.course_id = e.course_id), 0
            ) as total_lessons,
            COALESCE(
                (SELECT COUNT(*) FROM progress p 
                 JOIN lessons l ON p.lesson_id = l.id 
                 WHERE p.user_id = e.user_id AND l.course_id = e.course_id AND p.completed = 1), 0
            ) as completed_lessons,
            CASE 
                WHEN COALESCE((SELECT COUNT(*) FROM lessons l WHERE l.course_id = e.course_id), 0) > 0 
                THEN ROUND(
                    (COALESCE((SELECT COUNT(*) FROM progress p JOIN lessons l ON p.lesson_id = l.id 
                             WHERE p.user_id = e.user_id AND l.course_id = e.course_id AND p.completed = 1), 0) * 100.0) / 
                    COALESCE((SELECT COUNT(*) FROM lessons l WHERE l.course_id = e.course_id), 1), 2
                )
                ELSE 0 
            END as progress_percentage
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        $where_clause
        $order_clause
        LIMIT $offset, $limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $enrollments = $stmt->fetchAll();
} catch (Exception $e) {
    $enrollments = [];
    $error = 'Lỗi truy vấn dữ liệu: ' . $e->getMessage();
}

// Get courses for filter and add enrollment
try {
    $stmt = $pdo->query("SELECT id, title, price FROM courses ORDER BY title");
    $courses = $stmt->fetchAll();
} catch (Exception $e) {
    $courses = [];
}

// Get ALL users for add enrollment
try {
    $stmt = $pdo->query("SELECT id, username, email, role FROM users ORDER BY role DESC, username ASC");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}

// Get statistics
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_enrollments,
            COUNT(DISTINCT e.user_id) as total_users_enrolled,
            COUNT(DISTINCT e.course_id) as enrolled_courses,
            COALESCE(SUM(c.price), 0) as total_value,
            COUNT(CASE WHEN u.role = 'admin' THEN 1 END) as admin_enrollments,
            COUNT(CASE WHEN u.role = 'student' THEN 1 END) as student_enrollments
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN users u ON e.user_id = u.id
    ");
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = [
        'total_enrollments' => 0,
        'total_users_enrolled' => 0,
        'enrolled_courses' => 0,
        'total_value' => 0,
        'admin_enrollments' => 0,
        'student_enrollments' => 0
    ];
}

// Check if any filter is active
$has_filters = !empty($search) || $course_filter > 0 || $user_filter > 0 || !empty($role_filter) || ($sort !== 'newest');
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <div style="white-space: pre-line;"><?php echo htmlspecialchars($message); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <div style="white-space: pre-line;"><?php echo htmlspecialchars($error); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Duplicate Warning Modal -->
    <?php if ($duplicate_data): ?>
        <div class="modal fade show" id="duplicateWarningModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title text-dark">
                            <i class="fas fa-exclamation-triangle me-2"></i>Phát hiện đăng ký trùng lặp
                        </h5>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-info-circle me-2"></i>Thông tin trùng lặp:</h6>
                            <ul class="mb-0">
                                <li><strong>👤 Người dùng:</strong> <?php echo htmlspecialchars($duplicate_data['user_name']); ?></li>
                                <li><strong>📚 Khóa học:</strong> <?php echo htmlspecialchars($duplicate_data['course_title']); ?></li>
                                <li><strong>📅 Lần đầu đăng ký:</strong> <?php echo $duplicate_data['first_enrolled']; ?></li>
                                <li><strong>🔢 Số lần đã đăng ký:</strong> <?php echo $duplicate_data['existing_count']; ?> lần</li>
                            </ul>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-question-circle me-2"></i>Bạn muốn làm gì?</h6>
                            <p class="mb-0">Với quyền Admin, bạn có thể:</p>
                            <ul class="mb-0 mt-2">
                                <li>✅ <strong>Đăng ký thêm lần nữa</strong> (cho phép trùng lặp)</li>
                                <li>❌ <strong>Hủy bỏ</strong> để chọn người dùng/khóa học khác</li>
                            </ul>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?php echo $duplicate_data['user_id']; ?>">
                                    <input type="hidden" name="course_id" value="<?php echo $duplicate_data['course_id']; ?>">
                                    <input type="hidden" name="allow_duplicate" value="1">
                                    <button type="submit" name="add_enrollment" class="btn btn-warning w-100">
                                        <i class="fas fa-plus me-2"></i>Đăng ký thêm lần nữa
                                        <br><small>(Lần thứ <?php echo $duplicate_data['existing_count'] + 1; ?>)</small>
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <button type="button" class="btn btn-secondary w-100" onclick="closeDuplicateModal()">
                                    <i class="fas fa-times me-2"></i>Hủy bỏ
                                    <br><small>Chọn khóa học khác</small>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-graduation-cap me-2"></i><?php echo $page_title; ?>
            </h1>
            <p class="mb-0 text-muted">Admin có thể thêm đăng ký không giới hạn, kể cả trùng lặp</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal">
                <i class="fas fa-plus me-2"></i>Thêm đăng ký mới
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tổng đăng ký</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_enrollments']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book-open fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Người dùng</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_users_enrolled']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Admin/Student</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['admin_enrollments'] . '/' . $stats['student_enrollments']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Tổng giá trị</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_value']); ?>₫</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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

                <div class="col-md-3">
                    <label class="form-label">Tìm kiếm</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                            placeholder="Tên user, email, khóa học..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <?php if (!empty($search)): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()" title="Xóa tìm kiếm">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Khóa học</label>
                    <select class="form-select" name="course">
                        <option value="">Tất cả</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"
                                <?php echo $course_filter === $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Vai trò</label>
                    <select name="role" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Sắp xếp</label>
                    <select name="sort" class="form-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                        <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Tên A-Z</option>
                        <option value="course" <?php echo $sort === 'course' ? 'selected' : ''; ?>>Khóa học</option>
                        <option value="role" <?php echo $sort === 'role' ? 'selected' : ''; ?>>Vai trò</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
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
        </div>
    </div>

    <!-- Enrollments Table -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>Danh sách đăng ký
                <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
            </h6>
        </div>
        <div class="card-body">
            <?php if (!empty($enrollments)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="25%">Người dùng</th>
                                <th width="30%">Khóa học</th>
                                <th width="20%" class="text-center">Tiến độ</th>
                                <th width="25%" class="text-center">Ngày đăng ký</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <div class="avatar-circle bg-<?php echo $enrollment['user_role'] === 'admin' ? 'primary' : 'success'; ?>">
                                                    <i class="fas fa-<?php echo $enrollment['user_role'] === 'admin' ? 'user-shield' : 'user'; ?> text-white"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($enrollment['username']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($enrollment['email']); ?></small>
                                                <br>
                                                <span class="badge bg-<?php echo $enrollment['user_role'] === 'admin' ? 'primary' : 'success'; ?> badge-sm">
                                                    <?php echo ucfirst($enrollment['user_role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($enrollment['course_title']); ?></div>
                                            <small class="text-muted">
                                                <i class="fas fa-tag me-1"></i>
                                                <?php if ($enrollment['course_price'] > 0): ?>
                                                    Giá: <?php echo number_format($enrollment['course_price']); ?>₫
                                                <?php else: ?>
                                                    <span class="text-success">Miễn phí</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="progress mb-2" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar"
                                                style="width: <?php echo $enrollment['progress_percentage']; ?>%">
                                                <?php echo round($enrollment['progress_percentage']); ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $enrollment['completed_lessons']; ?>/<?php echo $enrollment['total_lessons']; ?> bài
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold"><?php echo date('d/m/Y', strtotime($enrollment['enrolled_at'])); ?></div>
                                        <small class="text-muted">
                                            <?php echo date('H:i', strtotime($enrollment['enrolled_at'])); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Info -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        <small class="text-muted">
                            Hiển thị <?php echo count($enrollments); ?> đăng ký
                            (<?php echo number_format($offset + 1); ?> - <?php echo number_format($offset + count($enrollments)); ?>
                            trong tổng số <?php echo number_format($total_records); ?>)
                        </small>
                    </div>
                    <div>
                        <small class="text-muted">
                            Trang <?php echo $page; ?> / <?php echo $total_pages; ?>
                        </small>
                    </div>
                </div>

                <!-- Pagination -->
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

                <!-- Page Size Selector -->
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
                    <i class="fas fa-graduation-cap fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">Không tìm thấy đăng ký nào</h4>
                    <p class="text-muted mb-4">
                        <?php if ($has_filters): ?>
                            Thử thay đổi bộ lọc hoặc
                            <a href="enrollments.php?limit=<?php echo $limit; ?>" class="btn btn-outline-primary btn-sm">Reset bộ lọc</a>
                        <?php else: ?>
                            Hệ thống chưa có đăng ký nào.
                        <?php endif; ?>
                    </p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal">
                        <i class="fas fa-plus me-2"></i>Thêm đăng ký đầu tiên
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Enrollment Modal -->
<div class="modal fade" id="addEnrollmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Thêm đăng ký mới
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>🔓 Quyền Admin:</strong> Thêm đăng ký cho bất kỳ ai. Nếu đã có đăng ký, hệ thống sẽ hỏi trước khi cho phép trùng lặp.
                </div>

                <!-- FIXED FORM with proper method and action -->
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="addEnrollmentForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-user me-1"></i>Chọn người dùng <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="user_id" id="userSelect" required>
                                <option value="">-- Chọn người dùng --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" data-role="<?php echo $user['role']; ?>"
                                        <?php echo ($duplicate_data && $duplicate_data['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ') - ' . ucfirst($user['role'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Tổng: <?php echo count($users); ?> người dùng</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-book me-1"></i>Chọn khóa học <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="course_id" id="courseSelect" required>
                                <option value="">-- Chọn khóa học --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" data-price="<?php echo $course['price']; ?>"
                                        <?php echo ($duplicate_data && $duplicate_data['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['title']); ?>
                                        <?php if ($course['price'] > 0): ?>
                                            (<?php echo number_format($course['price']); ?>₫)
                                        <?php else: ?>
                                            (Miễn phí)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Tổng: <?php echo count($courses); ?> khóa học</div>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-success">
                                <i class="fas fa-shield-alt me-2"></i>
                                <strong>🛡️ Quy trình kiểm tra:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>✅ Hệ thống sẽ kiểm tra xem người dùng đã đăng ký khóa học này chưa</li>
                                    <li>⚠️ Nếu đã có, sẽ hiển thị cảnh báo và hỏi ý kiến Admin</li>
                                    <li>🔄 Admin có thể chọn đăng ký thêm lần nữa hoặc hủy bỏ</li>
                                    <li>🎯 Đăng ký trùng lặp sẽ được theo dõi và đếm số lần</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Hủy
                        </button>
                        <!-- FIXED SUBMIT BUTTON with proper name -->
                        <button type="submit" name="add_enrollment" value="1" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-plus me-1"></i>Kiểm tra và thêm đăng ký
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* === COPY STYLES FROM USERS.PHP === */
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

    .progress-bar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .badge-sm {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .modal-header.bg-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%) !important;
        color: white !important;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

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

    /* === BADGES === */
    .badge {
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        letter-spacing: 0.025em;
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
        console.log('🚀 Page loaded, initializing...');

        // Form elements
        const addForm = document.getElementById('addEnrollmentForm');
        const submitBtn = document.getElementById('submitBtn');
        const userSelect = document.getElementById('userSelect');
        const courseSelect = document.getElementById('courseSelect');

        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                console.log('📝 Form submit event triggered');

                if (!userSelect.value || userSelect.value === '') {
                    e.preventDefault();
                    alert('❌ Vui lòng chọn người dùng!');
                    userSelect.focus();
                    return false;
                }

                if (!courseSelect.value || courseSelect.value === '') {
                    e.preventDefault();
                    alert('❌ Vui lòng chọn khóa học!');
                    courseSelect.focus();
                    return false;
                }

                console.log('✅ Validation passed, submitting...');

                // Show loading state
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang kiểm tra...';
                    submitBtn.disabled = true;
                }

                return true;
            });
        }

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

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert.alert-success, .alert.alert-danger').forEach(alert => {
                try {
                    new bootstrap.Alert(alert).close();
                } catch (e) {
                    console.log('Error closing alert:', e);
                }
            });
        }, 6000);

        // Reset modal on close
        const modal = document.getElementById('addEnrollmentModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function() {
                if (addForm) {
                    // Don't reset if duplicate_data exists
                    <?php if (!$duplicate_data): ?>
                        addForm.reset();
                    <?php endif; ?>
                }
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-plus me-1"></i>Kiểm tra và thêm đăng ký';
                    submitBtn.disabled = false;
                }
            });
        }
    });

    // Close duplicate modal
    function closeDuplicateModal() {
        <?php if ($duplicate_data): ?>
            window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?clear_duplicate=1';
        <?php endif; ?>
    }

    // Helper functions
    function clearSearch() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.value = '';
            document.getElementById('filterForm').submit();
        }
    }

    function resetFilters() {
        window.location.href = 'enrollments.php?limit=<?php echo $limit; ?>';
    }
</script>

<?php
// Clear duplicate data if requested
if (isset($_GET['clear_duplicate'])) {
    unset($_SESSION['duplicate_data']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>

<?php include 'includes/admin-footer.php'; ?>