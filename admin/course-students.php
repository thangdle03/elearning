<?php
// filepath: d:\Xampp\htdocs\elearning\admin\course-students.php
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

$page_title = 'Quản lý học viên: ' . $course['title'];
$current_page = 'courses';

// Initialize variables
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // REMOVE SINGLE STUDENT
    if (isset($_POST['remove_student'])) {
        try {
            $user_id = (int)$_POST['user_id'];

            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$user_id, $course_id]);

            if ($stmt->rowCount() > 0) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?course_id=' . $course_id . '&success=remove');
                exit;
            } else {
                throw new Exception('Không tìm thấy học viên trong khóa học!');
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // BULK ACTIONS
    elseif (isset($_POST['bulk_action']) && !empty($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selected_students = isset($_POST['selected_students']) ? $_POST['selected_students'] : [];

        if (empty($selected_students) || !is_array($selected_students)) {
            $error = 'Vui lòng chọn ít nhất một học viên để thực hiện thao tác!';
        } elseif (!in_array($action, ['remove', 'export', 'send_message'])) {
            $error = 'Thao tác không hợp lệ!';
        } else {
            // Clean and validate student IDs
            $student_ids = [];
            foreach ($selected_students as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $student_ids[] = $id;
                }
            }

            if (empty($student_ids)) {
                $error = 'Không có học viên hợp lệ được chọn!';
            } else {
                $success_count = 0;
                $failed_count = 0;

                try {
                    $pdo->beginTransaction();

                    if ($action === 'remove') {
                        foreach ($student_ids as $student_id) {
                            try {
                                $stmt = $pdo->prepare("DELETE FROM enrollments WHERE user_id = ? AND course_id = ?");
                                if ($stmt->execute([$student_id, $course_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                }
                            } catch (Exception $e) {
                                $failed_count++;
                            }
                        }

                        $pdo->commit();
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?course_id=' . $course_id . '&success=bulk_remove&count=' . $success_count . '&failed=' . $failed_count);
                        exit;
                    } elseif ($action === 'export') {
                        // Export selected students
                        $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
                        $stmt = $pdo->prepare("
                            SELECT u.username, u.email, e.enrolled_at,
                                   COUNT(DISTINCT l.id) as total_lessons,
                                   COUNT(DISTINCT CASE WHEN p.completed = 1 THEN p.lesson_id END) as completed_lessons,
                                   CASE 
                                       WHEN COUNT(DISTINCT l.id) > 0 
                                       THEN ROUND((COUNT(DISTINCT CASE WHEN p.completed = 1 THEN p.lesson_id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
                                       ELSE 0 
                                   END as progress_percentage
                            FROM users u
                            JOIN enrollments e ON u.id = e.user_id
                            LEFT JOIN lessons l ON l.course_id = e.course_id
                            LEFT JOIN progress p ON p.user_id = u.id AND p.lesson_id = l.id
                            WHERE u.id IN ($placeholders) AND e.course_id = ?
                            GROUP BY u.id, e.id
                        ");
                        $stmt->execute(array_merge($student_ids, [$course_id]));
                        $export_data = $stmt->fetchAll();

                        // Export to CSV
                        header('Content-Type: text/csv; charset=UTF-8');
                        header('Content-Disposition: attachment; filename="hoc-vien-' . $course['id'] . '-' . date('Y-m-d') . '.csv"');
                        header('Pragma: no-cache');
                        header('Expires: 0');

                        // Add BOM for UTF-8
                        echo "\xEF\xBB\xBF";

                        $output = fopen('php://output', 'w');

                        // Headers
                        fputcsv($output, [
                            'Tên đăng nhập',
                            'Email',
                            'Ngày đăng ký',
                            'Tổng bài học',
                            'Bài học hoàn thành',
                            'Tiến độ (%)'
                        ]);

                        // Data
                        foreach ($export_data as $row) {
                            fputcsv($output, [
                                $row['username'],
                                $row['email'],
                                date('d/m/Y H:i', strtotime($row['enrolled_at'])),
                                $row['total_lessons'],
                                $row['completed_lessons'],
                                $row['progress_percentage']
                            ]);
                        }

                        fclose($output);
                        exit;
                    } elseif ($action === 'send_message') {
                        // Redirect to bulk message page
                        $_SESSION['bulk_message_students'] = $student_ids;
                        $_SESSION['bulk_message_course'] = $course_id;
                        header('Location: bulk-message.php');
                        exit;
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
        case 'remove':
            $message = 'Đã xóa học viên khỏi khóa học thành công!';
            break;
        case 'bulk_remove':
            $count = (int)($_GET['count'] ?? 0);
            $failed = (int)($_GET['failed'] ?? 0);
            $message = "Đã xóa thành công {$count} học viên khỏi khóa học";
            if ($failed > 0) {
                $message .= ", {$failed} học viên không thể xóa";
            }
            $message .= "!";
            break;
        case 'bulk_message':
            $count = (int)($_GET['count'] ?? 0);
            $message = "Đã gửi tin nhắn thành công đến {$count} học viên!";
            break;
    }
}

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));

$requested_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$limit = in_array($requested_limit, [5, 10, 20, 50]) ? $requested_limit : 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ["e.course_id = ?"];
$params = [$course_id];

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(e.enrolled_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(e.enrolled_at) <= ?";
    $params[] = $date_to;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Sort options
$order_clause = match ($sort) {
    'oldest' => 'ORDER BY e.enrolled_at ASC',
    'name_asc' => 'ORDER BY u.username ASC',
    'name_desc' => 'ORDER BY u.username DESC',
    'email_asc' => 'ORDER BY u.email ASC',
    'progress_high' => 'ORDER BY progress_percentage DESC, e.enrolled_at DESC',
    'progress_low' => 'ORDER BY progress_percentage ASC, e.enrolled_at DESC',
    default => 'ORDER BY e.enrolled_at DESC'
};

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM enrollments e 
    JOIN users u ON e.user_id = u.id 
    $where_clause
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = (int)$stmt->fetchColumn();
$total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

// Get students
$sql = "
    SELECT e.*, u.username, u.email, u.created_at as user_created,
           COUNT(DISTINCT l.id) as total_lessons,
           COUNT(DISTINCT CASE WHEN p.completed = 1 THEN p.lesson_id END) as completed_lessons,
           CASE 
               WHEN COUNT(DISTINCT l.id) > 0 
               THEN ROUND((COUNT(DISTINCT CASE WHEN p.completed = 1 THEN p.lesson_id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
               ELSE 0 
           END as progress_percentage
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN lessons l ON l.course_id = e.course_id
    LEFT JOIN progress p ON p.user_id = u.id AND p.lesson_id = l.id
    $where_clause
    GROUP BY e.user_id, e.course_id, u.id, e.enrolled_at, u.username, u.email, u.created_at
    $order_clause
    LIMIT $offset, $limit
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get course statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT e.user_id) as total_students,
        COUNT(CASE WHEN DATE(e.enrolled_at) = CURDATE() THEN 1 END) as today_enrollments,
        COUNT(CASE WHEN DATE(e.enrolled_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_enrollments,
        COUNT(CASE WHEN DATE(e.enrolled_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_enrollments,
        COALESCE(AVG(
            CASE 
                WHEN lesson_counts.total_lessons > 0 
                THEN (progress_counts.completed_lessons * 100.0 / lesson_counts.total_lessons)
                ELSE 0 
            END
        ), 0) as avg_progress
    FROM enrollments e
    LEFT JOIN (
        SELECT e2.user_id, e2.course_id, COUNT(DISTINCT l.id) as total_lessons
        FROM enrollments e2
        LEFT JOIN lessons l ON l.course_id = e2.course_id
        WHERE e2.course_id = ?
        GROUP BY e2.user_id, e2.course_id
    ) lesson_counts ON lesson_counts.user_id = e.user_id AND lesson_counts.course_id = e.course_id
    LEFT JOIN (
        SELECT e3.user_id, e3.course_id, COUNT(DISTINCT p.lesson_id) as completed_lessons
        FROM enrollments e3
        LEFT JOIN lessons l ON l.course_id = e3.course_id
        LEFT JOIN progress p ON p.user_id = e3.user_id AND p.lesson_id = l.id AND p.completed = 1
        WHERE e3.course_id = ?
        GROUP BY e3.user_id, e3.course_id
    ) progress_counts ON progress_counts.user_id = e.user_id AND progress_counts.course_id = e.course_id
    WHERE e.course_id = ?
");
$stmt->execute([$course_id, $course_id, $course_id]);
$stats = $stmt->fetch();

// Check if any filter is active
$has_filters = !empty($search) || !empty($date_from) || !empty($date_to) || ($sort !== 'newest');
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
            <li class="breadcrumb-item active">Quản lý học viên</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-users me-2"></i>Quản lý học viên
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
                                Tổng học viên
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_students']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                Hôm nay
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                +<?php echo number_format($stats['today_enrollments']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
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
                                30 ngày qua
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                +<?php echo number_format($stats['month_enrollments']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
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
                                Tiến độ TB
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['avg_progress'], 1); ?>%
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
                            placeholder="Tên đăng nhập hoặc email..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <?php if (!empty($search)): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Từ ngày</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Đến ngày</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Sắp xếp theo</label>
                    <select name="sort" class="form-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Tên A-Z</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Tên Z-A</option>
                        <option value="email_asc" <?php echo $sort === 'email_asc' ? 'selected' : ''; ?>>Email A-Z</option>
                        <option value="progress_high" <?php echo $sort === 'progress_high' ? 'selected' : ''; ?>>Tiến độ cao</option>
                        <option value="progress_low" <?php echo $sort === 'progress_low' ? 'selected' : ''; ?>>Tiến độ thấp</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Tìm kiếm
                    </button>
                    <?php if ($has_filters): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                            <i class="fas fa-refresh"></i>
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

                        <?php if (!empty($date_from)): ?>
                            <span class="badge bg-info d-inline-flex align-items-center">
                                <span>Từ: <?php echo $date_from; ?></span>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('date_from')" style="font-size: 0.6em; width: 12px; height: 12px;"></button>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($date_to)): ?>
                            <span class="badge bg-info d-inline-flex align-items-center">
                                <span>Đến: <?php echo $date_to; ?></span>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('date_to')" style="font-size: 0.6em; width: 12px; height: 12px;"></button>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Students Management -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-users me-2"></i>Danh sách học viên
                <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?></span>
            </h6>

            <div class="d-flex gap-2">
                <!-- Bulk Action Buttons -->

                <button type="button" class="btn btn-sm btn-primary" onclick="performBulkAction('send_message')" id="bulkMessageBtn" style="display: none;">
                    <i class="fas fa-envelope me-1"></i>Gửi tin nhắn
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="performBulkAction('remove')" id="bulkRemoveBtn" style="display: none;">
                    <i class="fas fa-trash me-1"></i>Xóa khỏi khóa học
                </button>
            </div>
        </div>

        <div class="card-body">
            <?php if (!empty($students)): ?>
                <form id="bulkActionForm" method="POST" style="display: none;">
                    <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
                    <div id="selectedStudentsContainer"></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="3%" class="text-center">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th width="25%">Học viên</th>
                                <th width="20%">Email</th>
                                <th width="15%" class="text-center">Ngày đăng ký</th>
                                <th width="20%" class="text-center">Tiến độ học tập</th>
                                <th width="17%" class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input student-checkbox"
                                            value="<?php echo $student['user_id']; ?>">
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary me-3">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($student['username']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Tham gia: <?php echo date('d/m/Y', strtotime($student['user_created'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div>
                                            <i class="fas fa-envelope text-muted me-1"></i>
                                            <small><?php echo htmlspecialchars($student['email']); ?></small>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <div>
                                            <strong><?php echo date('d/m/Y', strtotime($student['enrolled_at'])); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($student['enrolled_at'])); ?>
                                            </small>
                                        </div>
                                        <?php
                                        $days_ago = floor((time() - strtotime($student['enrolled_at'])) / (24 * 60 * 60));
                                        ?>
                                        <small class="badge <?php echo $days_ago <= 7 ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $days_ago == 0 ? 'Hôm nay' : $days_ago . ' ngày trước'; ?>
                                        </small>
                                    </td>

                                    <td class="text-center">
                                        <?php
                                        $progress = $student['progress_percentage'] ?? 0;
                                        $completed = $student['completed_lessons'] ?? 0;
                                        $total = $student['total_lessons'] ?? 0;
                                        $progress_class = $progress >= 80 ? 'bg-success' : ($progress >= 50 ? 'bg-warning' : 'bg-danger');
                                        ?>
                                        <div class="progress mb-1" style="height: 8px;">
                                            <div class="progress-bar <?php echo $progress_class; ?>"
                                                style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <small class="fw-bold">
                                            <?php echo number_format($progress, 1); ?>%
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo $completed; ?>/<?php echo $total; ?> bài học
                                        </small>
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-info"
                                                onclick="viewStudentDetail(<?php echo $student['user_id']; ?>)"
                                                title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </button>



                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="removeStudent(<?php echo $student['user_id']; ?>, '<?php echo addslashes($student['username']); ?>')"
                                                title="Xóa khỏi khóa học">
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
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        <small class="text-muted">
                            Hiển thị <?php echo count($students); ?> học viên
                            (<?php echo number_format($offset + 1); ?> - <?php echo number_format($offset + count($students)); ?>
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
                    <h4 class="text-muted">Không tìm thấy học viên nào</h4>
                    <p class="text-muted mb-4">
                        <?php if ($has_filters): ?>
                            Không có học viên nào phù hợp với bộ lọc hiện tại.
                        <?php else: ?>
                            Khóa học này chưa có học viên nào đăng ký.
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

<!-- Student Detail Modal -->
<div class="modal fade" id="studentDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i>Chi tiết học viên
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="studentDetailContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Send Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-envelope me-2"></i>Gửi tin nhắn
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="messageForm">
                    <input type="hidden" name="recipient_id" id="message_recipient_id">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Gửi đến:</label>
                        <div id="message_recipient_name" class="form-control-plaintext bg-light p-2 rounded"></div>
                    </div>

                    <div class="mb-3">
                        <label for="message_subject" class="form-label fw-bold">Tiêu đề:</label>
                        <input type="text" class="form-control" id="message_subject" name="subject"
                            placeholder="Nhập tiêu đề tin nhắn..." required>
                    </div>

                    <div class="mb-3">
                        <label for="message_content" class="form-label fw-bold">Nội dung:</label>
                        <textarea class="form-control" id="message_content" name="content" rows="4"
                            placeholder="Nhập nội dung tin nhắn..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="sendMessageToStudent()">
                    <i class="fas fa-paper-plane me-2"></i>Gửi tin nhắn
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Remove Confirmation Modal -->
<div class="modal fade" id="removeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa học viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                </div>
                <p class="text-center">
                    Bạn có chắc chắn muốn xóa học viên<br>
                    <strong id="studentName"></strong> khỏi khóa học?
                </p>
                <div class="alert alert-warning">
                    <strong>Cảnh báo:</strong> Học viên sẽ mất quyền truy cập vào khóa học này!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" id="removeForm" class="d-inline">
                    <input type="hidden" name="user_id" id="studentIdInput" value="">
                    <input type="hidden" name="remove_student" value="1">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa khỏi khóa học
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* Copy styles from users.php */
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

    .card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background-color: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
    }

    .btn {
        border-radius: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .progress {
        background-color: #e9ecef;
        border-radius: 1rem;
    }

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
    }

    .pagination .page-item.active .page-link {
        background-color: #6366f1;
        border-color: #6366f1;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ Course students management loaded');

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

        // Get elements
        const selectAllCheckbox = document.getElementById('selectAll');
        const studentCheckboxes = document.querySelectorAll('.student-checkbox');
        const bulkButtons = document.querySelectorAll('[id^="bulk"]');

        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                studentCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkButtons();
            });
        }

        // Individual checkbox change
        studentCheckboxes.forEach(checkbox => {
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

        const studentCheckboxes = document.querySelectorAll('.student-checkbox');
        const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
        const totalCount = studentCheckboxes.length;

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
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
        const bulkButtons = document.querySelectorAll('[id^="bulk"]');

        if (checkedBoxes.length > 0) {
            bulkButtons.forEach(btn => btn.style.display = 'inline-block');
        } else {
            bulkButtons.forEach(btn => btn.style.display = 'none');
        }
    }

    function performBulkAction(action) {
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');

        if (checkedBoxes.length === 0) {
            showAlert('warning', 'Vui lòng chọn ít nhất một học viên!');
            return;
        }

        const messages = {
            'export': `Bạn có muốn xuất dữ liệu ${checkedBoxes.length} học viên đã chọn không?`,
            'send_message': `Bạn có muốn gửi tin nhắn đến ${checkedBoxes.length} học viên đã chọn không?`,
            'remove': `Bạn có chắc muốn XÓA ${checkedBoxes.length} học viên đã chọn khỏi khóa học?\n\nCảnh báo: Họ sẽ mất quyền truy cập vào khóa học này!`
        };

        if (!confirm(messages[action])) {
            return;
        }

        const form = document.getElementById('bulkActionForm');
        const actionInput = document.getElementById('bulkActionInput');
        const container = document.getElementById('selectedStudentsContainer');

        actionInput.value = action;
        container.innerHTML = '';

        checkedBoxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_students[]';
            input.value = checkbox.value;
            container.appendChild(input);
        });

        form.submit();
    }

    // Show student detail modal with loading animation
    function viewStudentDetail(userId) {
        console.log('👁️ Viewing student detail:', userId);

        const modal = new bootstrap.Modal(document.getElementById('studentDetailModal'));
        modal.show();

        // Show loading state
        document.getElementById('studentDetailContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Đang tải...</span>
            </div>
            <p class="mt-3 text-muted">Đang tải thông tin chi tiết học viên...</p>
        </div>
    `;

        // Load student data
        fetch(`get-student-detail.php?user_id=${userId}&course_id=<?php echo $course_id; ?>`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('studentDetailContent').innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('studentDetailContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Không thể tải thông tin học viên!</strong>
                    <br>Vui lòng thử lại sau.
                </div>
            `;
            });
    }

    // Send message to student
    function sendMessage(userId, username) {
        console.log('💌 Preparing to send message to:', userId, username);

        // Reset form
        document.getElementById('message_recipient_id').value = userId;
        document.getElementById('message_recipient_name').innerHTML = `
        <i class="fas fa-user me-2"></i>${username}
    `;
        document.getElementById('message_subject').value = '';
        document.getElementById('message_content').value = '';

        // Focus on subject field when modal opens
        const messageModal = document.getElementById('messageModal');
        messageModal.addEventListener('shown.bs.modal', function() {
            document.getElementById('message_subject').focus();
        }, {
            once: true
        });

        const modal = new bootstrap.Modal(messageModal);
        modal.show();
    }

    // Send message with validation and feedback
    function sendMessageToStudent() {
        const form = document.getElementById('messageForm');
        const formData = new FormData(form);
        const sendBtn = document.querySelector('#messageModal .btn-primary');

        // Validate inputs
        const subject = formData.get('subject').trim();
        const content = formData.get('content').trim();

        if (!subject) {
            showAlert('warning', 'Vui lòng nhập tiêu đề tin nhắn!');
            document.getElementById('message_subject').focus();
            return;
        }

        if (!content) {
            showAlert('warning', 'Vui lòng nhập nội dung tin nhắn!');
            document.getElementById('message_content').focus();
            return;
        }

        // Show loading state
        const originalBtnText = sendBtn.innerHTML;
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang gửi...';

        fetch('send-message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('messageModal')).hide();

                    // Reset form
                    form.reset();
                } else {
                    showAlert('danger', data.message || 'Có lỗi xảy ra khi gửi tin nhắn!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Lỗi kết nối! Vui lòng thử lại sau.');
            })
            .finally(() => {
                // Restore button state
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalBtnText;
            });
    }

    // Remove student from course
    function removeStudent(userId, username) {
        document.getElementById('studentName').textContent = username;
        document.getElementById('studentIdInput').value = userId;
        new bootstrap.Modal(document.getElementById('removeModal')).show();
    }

    // Utility function to show alerts
    function showAlert(type, message) {
        // Remove existing alerts
        document.querySelectorAll('.temp-alert').forEach(alert => alert.remove());

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show temp-alert`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';

        const icon = {
            'success': 'fas fa-check-circle',
            'danger': 'fas fa-exclamation-circle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        } [type] || 'fas fa-info-circle';

        alertDiv.innerHTML = `
        <i class="${icon} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

        document.body.appendChild(alertDiv);

        // Auto hide after 5 seconds
        setTimeout(() => {
            try {
                new bootstrap.Alert(alertDiv).close();
            } catch (e) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Clear search
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