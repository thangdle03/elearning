<?php
// filepath: d:\Xampp\htdocs\elearning\admin\courses.php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý khóa học';
$current_page = 'courses';

// Initialize variables
$message = '';
$error = '';
$debug = isset($_GET['debug']);

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // DELETE COURSE
    if (isset($_POST['delete_course'])) {
        $course_id = (int)($_POST['course_id'] ?? 0);

        if ($course_id <= 0) {
            $error = 'ID khóa học không hợp lệ!';
        } else {
            try {
                // Check if course has enrollments
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
                $stmt->execute([$course_id]);
                $enrollment_count = $stmt->fetchColumn();

                if ($enrollment_count > 0) {
                    $error = "Không thể xóa khóa học này vì đã có {$enrollment_count} học viên đăng ký!";
                } else {
                    $pdo->beginTransaction();

                    // Delete related data first
                    try {
                        $pdo->prepare("DELETE FROM lessons WHERE course_id = ?")->execute([$course_id]);
                        $pdo->prepare("DELETE FROM reviews WHERE course_id = ?")->execute([$course_id]);
                    } catch (Exception $e) {
                        // Tables might not exist
                    }

                    // Delete course
                    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                    if ($stmt->execute([$course_id]) && $stmt->rowCount() > 0) {
                        $pdo->commit();
                        header('Location: courses.php?success=delete');
                        exit;
                    } else {
                        $pdo->rollBack();
                        $error = 'Không tìm thấy khóa học để xóa!';
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Có lỗi xảy ra khi xóa khóa học: ' . $e->getMessage();
            }
        }
    }

    // TOGGLE STATUS
    elseif (isset($_POST['toggle_status'])) {
        $course_id = (int)($_POST['course_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';

        if ($course_id <= 0) {
            $error = 'ID khóa học không hợp lệ!';
        } elseif (!in_array($new_status, ['active', 'inactive'])) {
            $error = 'Trạng thái không hợp lệ!';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE courses SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$new_status, $course_id]) && $stmt->rowCount() > 0) {
                    header('Location: courses.php?success=toggle');
                    exit;
                } else {
                    $error = 'Không tìm thấy khóa học để cập nhật!';
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra khi cập nhật trạng thái: ' . $e->getMessage();
            }
        }
    }

    // BULK ACTIONS - FIXED VERSION
    elseif (isset($_POST['bulk_action']) && !empty($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selected_courses = isset($_POST['selected_courses']) ? $_POST['selected_courses'] : [];

        // Debug logging
        if ($debug) {
            error_log("Bulk action: " . $action);
            error_log("Selected courses: " . print_r($selected_courses, true));
        }

        // Validate selected courses
        if (empty($selected_courses) || !is_array($selected_courses)) {
            $error = 'Vui lòng chọn ít nhất một khóa học để thực hiện thao tác!';
        } elseif (!in_array($action, ['activate', 'deactivate', 'delete'])) {
            $error = 'Thao tác không hợp lệ!';
        } else {
            // Clean and validate course IDs
            $course_ids = [];
            foreach ($selected_courses as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $course_ids[] = $id;
                }
            }

            if (empty($course_ids)) {
                $error = 'Không có khóa học hợp lệ được chọn!';
            } else {
                $success_count = 0;
                $failed_count = 0;
                $failed_reasons = [];

                try {
                    $pdo->beginTransaction();

                    foreach ($course_ids as $course_id) {
                        // Get course info first
                        $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
                        $stmt->execute([$course_id]);
                        $course = $stmt->fetch();

                        if (!$course) {
                            $failed_count++;
                            $failed_reasons[] = "Khóa học ID {$course_id} không tồn tại";
                            continue;
                        }

                        try {
                            if ($action === 'activate') {
                                $stmt = $pdo->prepare("UPDATE courses SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                                if ($stmt->execute([$course_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                    $failed_reasons[] = "Không thể kích hoạt: {$course['title']}";
                                }
                            } elseif ($action === 'deactivate') {
                                $stmt = $pdo->prepare("UPDATE courses SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                                if ($stmt->execute([$course_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                    $failed_reasons[] = "Không thể vô hiệu hóa: {$course['title']}";
                                }
                            } elseif ($action === 'delete') {
                                // Check enrollments first
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
                                $stmt->execute([$course_id]);
                                $enrollment_count = $stmt->fetchColumn();

                                if ($enrollment_count > 0) {
                                    $failed_count++;
                                    $failed_reasons[] = "{$course['title']} có {$enrollment_count} học viên";
                                } else {
                                    // Delete related data first
                                    try {
                                        $pdo->prepare("DELETE FROM lessons WHERE course_id = ?")->execute([$course_id]);
                                        $pdo->prepare("DELETE FROM reviews WHERE course_id = ?")->execute([$course_id]);
                                    } catch (Exception $e) {
                                        // Ignore if tables don't exist
                                    }

                                    // Delete course
                                    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                                    if ($stmt->execute([$course_id]) && $stmt->rowCount() > 0) {
                                        $success_count++;
                                    } else {
                                        $failed_count++;
                                        $failed_reasons[] = "Không thể xóa: {$course['title']}";
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            $failed_count++;
                            $failed_reasons[] = "Lỗi xử lý {$course['title']}: " . $e->getMessage();
                        }
                    }

                    $pdo->commit();

                    // Build result message
                    if ($success_count > 0) {
                        $action_names = [
                            'activate' => 'kích hoạt',
                            'deactivate' => 'vô hiệu hóa',
                            'delete' => 'xóa'
                        ];
                        $action_name = $action_names[$action] ?? 'cập nhật';

                        $message = "Đã {$action_name} thành công {$success_count} khóa học";
                        if ($failed_count > 0) {
                            $message .= ", {$failed_count} khóa học không thể thực hiện";
                        }
                        $message .= "!";

                        // Redirect to avoid form resubmission
                        $redirect_url = 'courses.php?success=bulk&action=' . urlencode($action) . '&count=' . $success_count;
                        if ($failed_count > 0) {
                            $redirect_url .= '&failed=' . $failed_count;
                        }
                        header('Location: ' . $redirect_url);
                        exit;
                    } else {
                        $error = 'Không có khóa học nào được cập nhật!';
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
        case 'delete':
            $message = 'Đã xóa khóa học thành công!';
            break;
        case 'toggle':
            $message = 'Đã cập nhật trạng thái khóa học thành công!';
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

            $message = "Đã {$action_name} thành công {$count} khóa học";
            if ($failed > 0) {
                $message .= ", {$failed} khóa học không thể thực hiện";
            }
            $message .= "!";
            break;
    }
}

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
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
    $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($category_filter)) {
    $where_conditions[] = "c.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Sort options
$order_clause = match ($sort) {
    'oldest' => 'ORDER BY c.created_at ASC',
    'title' => 'ORDER BY c.title ASC',
    'price_high' => 'ORDER BY c.price DESC',
    'price_low' => 'ORDER BY c.price ASC',
    'popular' => 'ORDER BY enrollment_count DESC',
    default => 'ORDER BY c.created_at DESC'
};

// Get total count
try {
    $count_sql = "SELECT COUNT(DISTINCT c.id) FROM courses c LEFT JOIN categories cat ON c.category_id = cat.id $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_records = (int)$stmt->fetchColumn();

    $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Get courses
    $sql = "SELECT c.*, cat.name as category_name,
            (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
            FROM courses c 
            LEFT JOIN categories cat ON c.category_id = cat.id 
            $where_clause
            GROUP BY c.id 
            $order_clause
            LIMIT $offset, $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $courses = [];
    $total_records = 0;
    $total_pages = 1;
    $error = 'Lỗi database: ' . $e->getMessage();
}

// Get categories for filter
try {
    $categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Get statistics
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_courses,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_courses,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_courses,
            COUNT(CASE WHEN price = 0 THEN 1 END) as free_courses,
            AVG(price) as avg_price,
            COALESCE((SELECT COUNT(*) FROM enrollments), 0) as total_enrollments
        FROM courses
    ")->fetch();
} catch (Exception $e) {
    $stats = [
        'total_courses' => 0,
        'active_courses' => 0,
        'inactive_courses' => 0,
        'free_courses' => 0,
        'avg_price' => 0,
        'total_enrollments' => 0
    ];
}

// Check if any filter is active
$has_filters = !empty($search) || !empty($category_filter) || !empty($status_filter) || ($sort !== 'newest');
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
                <i class="fas fa-graduation-cap me-2"></i><?php echo $page_title; ?>
            </h1>
            <p class="text-muted mb-0">Quản lý khóa học trong hệ thống</p>
        </div>
        <div class="d-flex gap-2">
            <a href="add-course.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Thêm khóa học
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
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tổng khóa học</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_courses']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['active_courses']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Tổng đăng ký</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_enrollments']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Giá trung bình</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['avg_price'] > 0 ? number_format($stats['avg_price']) . ' VNĐ' : 'Miễn phí'; ?>
                            </div>
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
                            placeholder="Nhập tên khóa học..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <?php if (!empty($search)): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()" title="Xóa tìm kiếm">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Danh mục</label>
                    <select name="category" class="form-select">
                        <option value="">Tất cả</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
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
                        <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Tên A-Z</option>
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Phổ biến nhất</option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Giá cao nhất</option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Giá thấp nhất</option>
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

            <?php if ($has_filters): ?>
                <div class="mt-3">
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!empty($search)): ?>
                            <span class="badge bg-primary">
                                Tìm kiếm: "<?php echo htmlspecialchars($search); ?>"
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('search')" style="font-size: 0.7em;"></button>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($category_filter)): ?>
                            <?php
                            $selected_cat = array_filter($categories, function ($cat) use ($category_filter) {
                                return $cat['id'] == $category_filter;
                            });
                            $cat_name = !empty($selected_cat) ? reset($selected_cat)['name'] : 'Không xác định';
                            ?>
                            <span class="badge bg-info">
                                Danh mục: <?php echo htmlspecialchars($cat_name); ?>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('category')" style="font-size: 0.7em;"></button>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($status_filter)): ?>
                            <span class="badge bg-<?php echo $status_filter === 'active' ? 'success' : 'secondary'; ?>">
                                Trạng thái: <?php echo $status_filter === 'active' ? 'Hoạt động' : 'Ngừng hoạt động'; ?>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('status')" style="font-size: 0.7em;"></button>
                            </span>
                        <?php endif; ?>

                        <?php if ($sort !== 'newest'): ?>
                            <?php
                            $sort_names = [
                                'oldest' => 'Cũ nhất',
                                'title' => 'Tên A-Z',
                                'popular' => 'Phổ biến nhất',
                                'price_high' => 'Giá cao nhất',
                                'price_low' => 'Giá thấp nhất'
                            ];
                            $sort_name = $sort_names[$sort] ?? 'Mới nhất';
                            ?>
                            <span class="badge bg-warning">
                                Sắp xếp: <?php echo $sort_name; ?>
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeFilter('sort')" style="font-size: 0.7em;"></button>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>Danh sách khóa học
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
            <?php if ($courses): ?>
                <form id="bulkActionForm" method="POST" style="display: none;">
                    <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
                    <div id="selectedCoursesContainer"></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="3%" class="text-center">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th width="8%" class="text-center">Hình ảnh</th>
                                <th width="25%" class="text-center">Thông tin khóa học</th>
                                <th width="10%" class="text-center">Danh mục</th>
                                <th width="8%" class="text-center">Giá</th>
                                <th width="6%" class="text-center">Bài học</th>
                                <th width="6%" class="text-center">Học viên</th>
                                <th width="10%" class="text-center">Trạng thái</th>
                                <th width="10%" class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $index => $course): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input course-checkbox"
                                            value="<?php echo $course['id']; ?>">
                                    </td>
                                    <td class="text-center">
                                        <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/80x60?text=No+Image'; ?>"
                                            alt="<?php echo htmlspecialchars($course['title']); ?>"
                                            class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($course['thumbnail'])): ?>
                                                <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>"
                                                    alt="" class="rounded me-3" style="width: 50px; height: 35px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center"
                                                    style="width: 50px; height: 35px;">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <!-- Thêm link vào title -->
                                                <h6 class="mb-0">
                                                    <a href="course-detail.php?id=<?php echo $course['id']; ?>"
                                                        class="text-decoration-none text-primary fw-bold">
                                                        <?php echo htmlspecialchars($course['title']); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($course['category_name'] ?? 'Chưa phân loại'); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">
                                            <?php echo $course['category_name'] ?: 'Chưa phân loại'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <strong class="<?php echo $course['price'] > 0 ? 'text-success' : 'text-primary'; ?>">
                                            <?php echo $course['price'] > 0 ? number_format($course['price']) . ' VNĐ' : 'Miễn phí'; ?>
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info fs-6"><?php echo $course['lesson_count']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning fs-6"><?php echo $course['enrollment_count']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $course['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <input type="hidden" name="toggle_status" value="1">
                                            <button type="submit"
                                                class="btn btn-sm <?php echo $course['status'] === 'active' ? 'btn-success' : 'btn-secondary'; ?>"
                                                onclick="return confirm('Bạn có chắc muốn thay đổi trạng thái?')"
                                                title="<?php echo $course['status'] === 'active' ? 'Đang hoạt động - Click để tạm dừng' : 'Tạm dừng - Click để kích hoạt'; ?>">
                                                <i class="fas fa-<?php echo $course['status'] === 'active' ? 'check' : 'times'; ?>"></i>
                                                <?php echo $course['status'] === 'active' ? 'Hoạt động' : 'Tạm dừng'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group" aria-label="Course actions">
                                            <a href="course-detail.php?id=<?php echo $course['id']; ?>"
                                                class="btn btn-sm btn-outline-info" title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit-course.php?id=<?php echo $course['id']; ?>"
                                                class="btn btn-sm btn-outline-primary" title="Chỉnh sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="deleteCourse(<?php echo $course['id']; ?>)" title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Info và Navigation -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <!-- Left: Record Info -->
                    <div>
                        <small class="text-muted">
                            Hiển thị <?php echo count($courses); ?> danh mục
                            (<?php echo number_format($offset + 1); ?> - <?php echo number_format($offset + count($courses)); ?>
                            trong tổng số <?php echo number_format($total_records); ?>)
                        </small>
                    </div>

                    <!-- Right: Page Info -->
                    <div>
                        <small class="text-muted">
                            Trang <?php echo $page; ?> / <?php echo $total_pages; ?>
                        </small>
                    </div>
                </div>

                <!-- Center: Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-3">
                        <nav aria-label="Phân trang">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous Button -->
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

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next Button -->
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

                <!-- Bottom: Page Size Selector -->
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
                <!-- No Data Message -->
                <div class="text-center py-5">
                    <i class="fas fa-book fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">Không tìm thấy khóa học nào</h4>
                    <p class="text-muted mb-4">
                        <?php if ($has_filters): ?>
                            Thử thay đổi bộ lọc hoặc
                            <a href="courses.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-refresh me-1"></i>Reset bộ lọc
                            </a>
                        <?php else: ?>
                            Hệ thống chưa có khóa học nào. Hãy tạo khóa học đầu tiên!
                        <?php endif; ?>
                    </p>
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
                <h5 class="modal-title">Xác nhận xóa khóa học</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                </div>
                <p class="text-center">
                    Bạn có chắc chắn muốn xóa khóa học<br>
                    <strong id="courseTitle"></strong>?
                </p>
                <div class="alert alert-warning">
                    <strong>Cảnh báo:</strong> Hành động này sẽ xóa vĩnh viễn khóa học và tất cả dữ liệu liên quan!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" id="deleteForm" class="d-inline">
                    <input type="hidden" name="course_id" id="courseIdInput" value="">
                    <input type="hidden" name="delete_course" value="1">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa khóa học
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

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

    .border-left-warning {
        border-left: 0.25rem solid #f6c23e !important;
    }

    .table th {
        border-top: none;
        font-weight: 600;
        font-size: 0.85rem;
        color: #5a5c69;
        vertical-align: middle;
        white-space: nowrap;
        padding: 12px 8px;
    }

    .table tbody tr:hover {
        background-color: #f8f9fc;
    }

    .table td {
        vertical-align: middle;
        padding: 12px 8px;
    }

    .card {
        border: none;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
    }

    /* Fixed Pagination Styles */
    .pagination {
        margin-bottom: 0;
    }

    .pagination .page-item.active .page-link {
        background-color: #4e73df;
        border-color: #4e73df;
        color: white;
        font-weight: 600;
    }

    .pagination .page-link {
        color: #5a5c69;
        border: 1px solid #dddfeb;
        padding: 0.375rem 0.75rem;
        margin: 0;
        transition: all 0.15s ease-in-out;
    }

    .pagination .page-link:hover {
        color: #224abe;
        background-color: #eaecf4;
        border-color: #d1d3e2;
    }

    .pagination .page-item.disabled .page-link {
        color: #858796;
        background-color: #f8f9fc;
        border-color: #e3e6f0;
    }

    .pagination .page-item:first-child .page-link {
        border-top-left-radius: 0.35rem;
        border-bottom-left-radius: 0.35rem;
    }

    .pagination .page-item:last-child .page-link {
        border-top-right-radius: 0.35rem;
        border-bottom-right-radius: 0.35rem;
    }

    /* Fix for Page Size Selector - prevent overlapping */
    .form-select-sm {
        padding: 0.25rem 1.75rem 0.25rem 0.5rem !important;
        font-size: 0.875rem;
        line-height: 1.5;
        min-width: 60px;
        width: auto !important;
        flex-shrink: 0;
    }

    /* Ensure proper spacing for the pagination controls */
    .d-flex.align-items-center {
        white-space: nowrap;
    }

    .d-flex.align-items-center .gap-2>* {
        margin-right: 0.5rem;
    }

    .d-flex.align-items-center .gap-2>*:last-child {
        margin-right: 0;
    }

    /* Alternative: Use flexbox gap if supported */
    .gap-2 {
        gap: 0.5rem !important;
    }

    /* Ensure text doesn't wrap */
    .text-muted {
        white-space: nowrap;
        flex-shrink: 0;
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
        const courseCheckboxes = document.querySelectorAll('.course-checkbox');
        const bulkButtons = document.querySelectorAll('[id^="bulk"]');

        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                courseCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkButtons();
            });
        }

        // Individual checkbox change
        courseCheckboxes.forEach(checkbox => {
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
                url.searchParams.set('page', '1'); // Reset to first page
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

        const courseCheckboxes = document.querySelectorAll('.course-checkbox');
        const checkedCount = document.querySelectorAll('.course-checkbox:checked').length;
        const totalCount = courseCheckboxes.length;

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
        const checkedBoxes = document.querySelectorAll('.course-checkbox:checked');
        const bulkButtons = document.querySelectorAll('[id^="bulk"]');

        if (checkedBoxes.length > 0) {
            bulkButtons.forEach(btn => btn.style.display = 'inline-block');
        } else {
            bulkButtons.forEach(btn => btn.style.display = 'none');
        }
    }

    // Perform bulk action
    function performBulkAction(action) {
        const checkedBoxes = document.querySelectorAll('.course-checkbox:checked');

        if (checkedBoxes.length === 0) {
            alert('Vui lòng chọn ít nhất một khóa học!');
            return;
        }

        // Confirmation messages
        const messages = {
            'activate': `Bạn có chắc muốn kích hoạt ${checkedBoxes.length} khóa học đã chọn?`,
            'deactivate': `Bạn có chắc muốn vô hiệu hóa ${checkedBoxes.length} khóa học đã chọn?`,
            'delete': `Bạn có chắc muốn XÓA ${checkedBoxes.length} khóa học đã chọn?\n\nCảnh báo: Chỉ xóa được khóa học chưa có học viên!`
        };

        if (!confirm(messages[action])) {
            return;
        }

        // Prepare form
        const form = document.getElementById('bulkActionForm');
        const actionInput = document.getElementById('bulkActionInput');
        const container = document.getElementById('selectedCoursesContainer');

        // Set action
        actionInput.value = action;

        // Clear previous inputs
        container.innerHTML = '';

        // Add selected course IDs
        checkedBoxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_courses[]';
            input.value = checkbox.value;
            container.appendChild(input);
        });

        // Submit form
        form.submit();
    }

    // Delete course function
    function deleteCourse(courseId, courseTitle) {
        document.getElementById('courseTitle').textContent = courseTitle;
        document.getElementById('courseIdInput').value = courseId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
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
        window.location.href = 'courses.php?limit=<?php echo $limit; ?>';
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