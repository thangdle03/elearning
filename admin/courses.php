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
    elseif (isset($_POST['bulk_action']) && !empty($_POST['selected_courses'])) {
        $action = $_POST['bulk_action'];
        $selected_courses = (array)$_POST['selected_courses'];
        $success_count = 0;
        $failed_count = 0;
        $failed_reasons = [];

        if (!in_array($action, ['activate', 'deactivate', 'delete'])) {
            $error = 'Thao tác không hợp lệ!';
        } else {
            try {
                $pdo->beginTransaction();

                foreach ($selected_courses as $course_id) {
                    $course_id = (int)$course_id;
                    if ($course_id <= 0) continue;

                    // Get course info
                    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch();

                    if (!$course) {
                        $failed_count++;
                        $failed_reasons[] = "Khóa học ID {$course_id} không tồn tại";
                        continue;
                    }

                    try {
                        switch ($action) {
                            case 'activate':
                                $stmt = $pdo->prepare("UPDATE courses SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                                if ($stmt->execute([$course_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                    $failed_reasons[] = "Không thể kích hoạt: {$course['title']}";
                                }
                                break;

                            case 'deactivate':
                                $stmt = $pdo->prepare("UPDATE courses SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                                if ($stmt->execute([$course_id]) && $stmt->rowCount() > 0) {
                                    $success_count++;
                                } else {
                                    $failed_count++;
                                    $failed_reasons[] = "Không thể vô hiệu hóa: {$course['title']}";
                                }
                                break;

                            case 'delete':
                                // Check enrollments
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
                                $stmt->execute([$course_id]);
                                $enrollment_count = $stmt->fetchColumn();

                                if ($enrollment_count > 0) {
                                    $failed_count++;
                                    $failed_reasons[] = "{$course['title']} có {$enrollment_count} học viên";
                                } else {
                                    // Delete related data
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
                                break;
                        }
                    } catch (Exception $e) {
                        $failed_count++;
                        $failed_reasons[] = "Lỗi xử lý {$course['title']}: " . $e->getMessage();
                    }
                }

                $pdo->commit();

                // Build result message
                if ($success_count > 0) {
                    $action_name = match ($action) {
                        'activate' => 'kích hoạt',
                        'deactivate' => 'vô hiệu hóa',
                        'delete' => 'xóa',
                        default => 'cập nhật'
                    };

                    $message = "Đã {$action_name} thành công {$success_count} khóa học";
                    if ($failed_count > 0) {
                        $message .= ", {$failed_count} khóa học không thể thực hiện";
                    }
                    $message .= "!";

                    header('Location: courses.php?success=bulk&action=' . $action . '&count=' . $success_count . '&failed=' . $failed_count);
                    exit;
                } else {
                    $error = 'Không có khóa học nào được cập nhật!';
                    if (!empty($failed_reasons)) {
                        $error .= ' Lý do: ' . implode('; ', array_slice($failed_reasons, 0, 3));
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    } else {
        if (isset($_POST['bulk_action'])) {
            $error = 'Vui lòng chọn ít nhất một khóa học!';
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
            $count = $_GET['count'] ?? 0;
            $failed = $_GET['failed'] ?? 0;
            $action_name = match ($action) {
                'activate' => 'kích hoạt',
                'deactivate' => 'vô hiệu hóa',
                'delete' => 'xóa',
                default => 'cập nhật'
            };
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

$requested_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
$limit = in_array($requested_limit, [5, 10, 20, 50]) ? $requested_limit : 5;

if ($limit <= 0) {
    $limit = 5;
}

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

    if ($total_records < 0) {
        $total_records = 0;
    }

    $total_pages = ($total_records > 0 && $limit > 0) ? ceil($total_records / $limit) : 1;

    if ($total_pages < 1) {
        $total_pages = 1;
    }

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

$total_records = max(0, $total_records);
$total_pages = max(1, $total_pages);
$page = max(1, min($page, $total_pages));
$courses = $courses ?: [];

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

// Build query string helper
function build_query($params = [])
{
    global $search, $category_filter, $status_filter, $sort, $page, $limit;

    $safe_limit = isset($limit) && in_array($limit, [5, 10, 20, 50]) ? $limit : 5;

    $query_params = array_filter([
        'search' => $search ?: null,
        'category' => $category_filter ?: null,
        'status' => $status_filter ?: null,
        'sort' => ($sort != 'newest') ? $sort : null,
        'page' => ($page > 1) ? $page : null,
        'limit' => ($safe_limit != 5) ? $safe_limit : null
    ], function ($value) {
        return $value !== null && $value !== '';
    });

    $final_params = array_merge($query_params, array_filter($params, function ($value) {
        return $value !== null && $value !== '';
    }));

    return http_build_query($final_params);
}
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
            <form method="GET" class="row g-3">
                <input type="hidden" name="limit" value="<?php echo $limit; ?>">

                <div class="col-md-4">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" name="search" class="form-control"
                        placeholder="Nhập tên khóa học..."
                        value="<?php echo htmlspecialchars($search); ?>">
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

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Tìm kiếm
                    </button>
                </div>
            </form>

            <?php if ($search || $category_filter || $status_filter || $sort != 'newest'): ?>
                <div class="mt-2">
                    <a href="courses.php?<?php echo build_query(['search' => null, 'category' => null, 'status' => null, 'sort' => null, 'page' => null]); ?>"
                        class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Xóa bộ lọc
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>Danh sách khóa học
                <span class="badge bg-primary ms-2"><?php echo count($courses); ?></span>
            </h6>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" id="bulkActionDropdown">
                    <i class="fas fa-cog me-1"></i>Thao tác hàng loạt
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="handleBulkAction('activate')">
                            <i class="fas fa-check-circle me-2"></i>Kích hoạt
                        </a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="handleBulkAction('deactivate')">
                            <i class="fas fa-times-circle me-2"></i>Vô hiệu hóa
                        </a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="handleBulkAction('delete')">
                            <i class="fas fa-trash me-2"></i>Xóa
                        </a></li>
                </ul>
            </div>
        </div>

        <div class="card-body">
            <?php if ($courses): ?>
                <form id="bulkForm" method="POST">
                    <input type="hidden" name="bulk_action" id="bulkAction" value="">

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
                                                name="selected_courses[]" value="<?php echo $course['id']; ?>">
                                        </td>
                                        <td class="text-center">
                                            <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/80x60?text=No+Image'; ?>"
                                                alt="<?php echo htmlspecialchars($course['title']); ?>"
                                                class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
                                        </td>
                                        <td>
                                            <div>
                                                <h6 class="mb-1">
                                                    <strong class="text-primary"><?php echo htmlspecialchars($course['title']); ?></strong>
                                                </h6>
                                                <p class="mb-1 text-muted small">
                                                    <?php echo htmlspecialchars(mb_substr($course['description'], 0, 80)); ?>
                                                    <?php if (mb_strlen($course['description']) > 80) echo '...'; ?>
                                                </p>
                                                <small class="text-muted">
                                                    ID: <?php echo $course['id']; ?>
                                                    | <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('d/m/Y', strtotime($course['created_at'])); ?>
                                                </small>
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
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit-course.php?id=<?php echo $course['id']; ?>"
                                                    class="btn btn-outline-primary btn-sm" title="Chỉnh sửa">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo addslashes($course['title']); ?>')"
                                                    title="Xóa" <?php echo $course['enrollment_count'] > 0 ? 'disabled' : ''; ?>>
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

                <!-- Pagination Info -->
                <div class="mt-3">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <small class="text-muted">
                                Hiển thị <?php echo count($courses); ?> khóa học
                                (<?php echo number_format($offset + 1); ?> - <?php echo number_format($offset + count($courses)); ?>
                                trong tổng số <?php echo number_format($total_records); ?>)
                                <?php if ($search): ?>
                                    với từ khóa "<strong><?php echo htmlspecialchars($search); ?></strong>"
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">Trang <?php echo $page; ?> / <?php echo $total_pages; ?></small>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav>
                            <ul class="pagination pagination-sm">
                                <!-- Previous -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <?php if ($page > 1): ?>
                                        <a class="page-link" href="?<?php echo build_query(['page' => $page - 1]); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="page-link"><i class="fas fa-chevron-left"></i></span>
                                    <?php endif; ?>
                                </li>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?' . build_query(['page' => 1]) . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo build_query(['page' => $i]); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor;

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?' . build_query(['page' => $total_pages]) . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <!-- Next -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <?php if ($page < $total_pages): ?>
                                        <a class="page-link" href="?<?php echo build_query(['page' => $page + 1]); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="page-link"><i class="fas fa-chevron-right"></i></span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </nav>
                    </div>

                    <!-- Page Size Selector -->
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Hiển thị
                            <select id="pageSize" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            </select>
                            bản ghi mỗi trang
                        </small>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-book fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">Không tìm thấy khóa học nào</h4>
                    <p class="text-muted mb-4">
                        <?php if ($search || $category_filter || $status_filter): ?>
                            Thử thay đổi bộ lọc hoặc
                            <a href="courses.php?<?php echo build_query(['search' => null, 'category' => null, 'status' => null, 'page' => null]); ?>">xem tất cả khóa học</a>
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

<!-- Bulk Action Modal -->
<div class="modal fade" id="bulkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkModalTitle">Xác nhận thao tác hàng loạt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-question-circle fa-3x text-primary"></i>
                </div>
                <p class="text-center" id="bulkModalMessage"></p>
                <div class="alert alert-info">
                    <span id="bulkModalAlertText"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="confirmBulkAction">
                    <i class="fas fa-check me-2"></i>Xác nhận
                </button>
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

    .pagination .page-item.active .page-link {
        background-color: #4e73df;
        border-color: #4e73df;
    }

    .pagination .page-link {
        color: #5a5c69;
        border: 1px solid #dddfeb;
    }

    .pagination .page-link:hover {
        color: #224abe;
        background-color: #eaecf4;
    }

    .form-check-input {
        margin: 0;
    }

    .btn-group-sm>.btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
</style>

<script>
// Global variables
let selectedCourseIds = [];
let currentBulkAction = '';

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Page loaded');
    
    // Check if all required elements exist
    const requiredElements = {
        selectAll: document.getElementById('selectAll'),
        bulkForm: document.getElementById('bulkForm'),
        bulkAction: document.getElementById('bulkAction'),
        confirmButton: document.getElementById('confirmBulkAction'),
        bulkModal: document.getElementById('bulkModal'),
        courseCheckboxes: document.querySelectorAll('.course-checkbox')
    };
    
    console.log('🔍 Element check:', {
        selectAll: !!requiredElements.selectAll,
        bulkForm: !!requiredElements.bulkForm,
        bulkAction: !!requiredElements.bulkAction,
        confirmButton: !!requiredElements.confirmButton,
        bulkModal: !!requiredElements.bulkModal,
        courseCheckboxes: requiredElements.courseCheckboxes.length
    });

    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert:not(.alert-info)').forEach(alert => {
            new bootstrap.Alert(alert).close();
        });
    }, 5000);

    // Select all functionality
    if (requiredElements.selectAll) {
        requiredElements.selectAll.addEventListener('change', function() {
            console.log('🔄 Select all clicked:', this.checked);
            requiredElements.courseCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedIds();
            console.log('✅ Select all result. Selected:', selectedCourseIds.length, 'IDs:', selectedCourseIds);
        });
    }

    // Individual checkbox change
    requiredElements.courseCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('📝 Individual checkbox changed. ID:', this.value, 'Checked:', this.checked);
            updateSelectAllState();
            updateSelectedIds();
            console.log('📊 Current selection:', selectedCourseIds.length, 'IDs:', selectedCourseIds);
        });
    });

    // Page size selector
    document.getElementById('pageSize')?.addEventListener('change', function() {
        const url = new URL(window.location);
        url.searchParams.set('limit', this.value);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    });

    // Bulk action confirm button - ENHANCED
    if (requiredElements.confirmButton && requiredElements.bulkForm) {
        requiredElements.confirmButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('🔥 CONFIRM BUTTON CLICKED');
            console.log('📊 Current state before confirmation:', {
                selectedCourseIds: selectedCourseIds,
                currentBulkAction: currentBulkAction,
                selectedCount: selectedCourseIds.length
            });

            if (selectedCourseIds.length === 0) {
                alert('Không có khóa học nào được chọn!');
                console.warn('⚠️ No courses selected!');
                return;
            }

            if (!currentBulkAction) {
                alert('Không có thao tác nào được chọn!');
                console.warn('⚠️ No bulk action selected!');
                return;
            }

            // Set bulk action input
            const bulkActionInput = document.getElementById('bulkAction');
            if (bulkActionInput) {
                bulkActionInput.value = currentBulkAction;
                console.log('✅ Set bulk action input to:', currentBulkAction);
            } else {
                console.error('❌ Bulk action input not found!');
                return;
            }

            // RE-CHECK all selected checkboxes to ensure they're checked
            console.log('🔄 Re-checking selected checkboxes...');
            let recheckedCount = 0;
            selectedCourseIds.forEach(id => {
                const checkbox = document.querySelector(`.course-checkbox[value="${id}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    recheckedCount++;
                    console.log(`✅ Re-checked course ID: ${id}`);
                } else {
                    console.warn(`⚠️ Checkbox not found for ID: ${id}`);
                }
            });
            
            console.log(`📋 Re-checked ${recheckedCount} out of ${selectedCourseIds.length} checkboxes`);

            // Final form data verification
            const formData = new FormData(requiredElements.bulkForm);
            console.log('📤 FINAL FORM DATA CHECK:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value}`);
            }

            // Verify selected courses in form data
            const selectedCoursesInForm = formData.getAll('selected_courses[]');
            console.log('🔍 Selected courses in form:', selectedCoursesInForm);
            console.log('🔍 Expected selected courses:', selectedCourseIds);
            
            if (selectedCoursesInForm.length === 0) {
                console.error('❌ NO SELECTED COURSES IN FORM DATA!');
                alert('Lỗi: Không có khóa học nào trong form data. Vui lòng thử lại!');
                return;
            }

            // Hide modal
            const modalElement = document.getElementById('bulkModal');
            if (modalElement) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                    console.log('✅ Modal hidden');
                }
            }

            // Submit form with delay
            setTimeout(() => {
                console.log('🚀 SUBMITTING FORM...');
                console.log('📝 Form action:', requiredElements.bulkForm.action || 'current page');
                console.log('📝 Form method:', requiredElements.bulkForm.method);
                
                // Final check before submit
                const finalFormData = new FormData(requiredElements.bulkForm);
                console.log('🔍 FINAL SUBMIT DATA:');
                for (let [key, value] of finalFormData.entries()) {
                    console.log(`  ${key}: ${value}`);
                }
                
                requiredElements.bulkForm.submit();
            }, 500);
        });
    } else {
        console.error('❌ Required elements missing:', {
            confirmButton: !!requiredElements.confirmButton,
            bulkForm: !!requiredElements.bulkForm
        });
    }

    console.log('✅ Initialization complete');
});

// Update selected IDs array
function updateSelectedIds() {
    const selected = document.querySelectorAll('.course-checkbox:checked');
    selectedCourseIds = Array.from(selected).map(cb => cb.value);
    console.log('🔄 Updated selectedCourseIds:', selectedCourseIds);
}

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

// Handle bulk actions - ENHANCED
function handleBulkAction(action) {
    console.log('🔧 HANDLE BULK ACTION CALLED:', action);

    // Force update selected IDs
    updateSelectedIds();
    
    console.log('📊 Selection check:', {
        action: action,
        selectedCount: selectedCourseIds.length,
        selectedIds: selectedCourseIds
    });

    if (selectedCourseIds.length === 0) {
        alert('Vui lòng chọn ít nhất một khóa học!');
        console.warn('⚠️ No courses selected for bulk action');
        return false;
    }

    // Store current action
    currentBulkAction = action;
    const count = selectedCourseIds.length;
    let title, message, alertText;

    switch (action) {
        case 'activate':
            title = 'Kích hoạt khóa học';
            message = `Bạn có chắc muốn kích hoạt ${count} khóa học đã chọn?`;
            alertText = 'Các khóa học sẽ được kích hoạt và hiển thị công khai.';
            break;
        case 'deactivate':
            title = 'Vô hiệu hóa khóa học';
            message = `Bạn có chắc muốn vô hiệu hóa ${count} khóa học đã chọn?`;
            alertText = 'Các khóa học sẽ bị ẩn khỏi danh sách công khai.';
            break;
        case 'delete':
            title = 'Xóa khóa học';
            message = `Bạn có chắc chắn muốn xóa ${count} khóa học đã chọn?`;
            alertText = 'Chỉ các khóa học chưa có học viên đăng ký mới được xóa!';
            break;
        default:
            console.error('❌ Invalid action:', action);
            return false;
    }

    // DETAILED MODAL ELEMENTS DEBUG
    const modalElements = {
        title: document.getElementById('bulkModalTitle'),
        message: document.getElementById('bulkModalMessage'),
        alertText: document.getElementById('bulkModalAlertText'),
        modal: document.getElementById('bulkModal')
    };

    console.log('🔍 DETAILED Modal elements check:');
    console.log('  bulkModalTitle:', modalElements.title);
    console.log('  bulkModalMessage:', modalElements.message);
    console.log('  bulkModalAlertText:', modalElements.alertText);
    console.log('  bulkModal:', modalElements.modal);

    // Check if modal HTML exists in DOM
    const allModals = document.querySelectorAll('.modal');
    console.log('🔍 All modals in DOM:', allModals.length);
    allModals.forEach((modal, index) => {
        console.log(`  Modal ${index}:`, modal.id, modal);
    });

    // Try alternative approach if elements are missing
    if (!modalElements.title || !modalElements.message || !modalElements.alertText || !modalElements.modal) {
        console.error('❌ Some modal elements missing!');
        
        // FALLBACK: Use native confirm dialog
        const confirmMessage = `${message}\n\n${alertText}`;
        if (confirm(confirmMessage)) {
            console.log('🔥 User confirmed via native dialog');
            
            // Set bulk action input
            const bulkActionInput = document.getElementById('bulkAction');
            if (bulkActionInput) {
                bulkActionInput.value = currentBulkAction;
                console.log('✅ Set bulk action input to:', currentBulkAction);
            }

            // Re-check all selected checkboxes
            console.log('🔄 Re-checking selected checkboxes...');
            selectedCourseIds.forEach(id => {
                const checkbox = document.querySelector(`.course-checkbox[value="${id}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    console.log(`✅ Re-checked course ID: ${id}`);
                }
            });

            // Submit form directly
            const bulkForm = document.getElementById('bulkForm');
            if (bulkForm) {
                console.log('🚀 SUBMITTING FORM via fallback...');
                bulkForm.submit();
            } else {
                console.error('❌ Bulk form not found!');
                alert('Lỗi: Không tìm thấy form để submit!');
            }
        }
        return false;
    }

    // Set modal content if elements exist
    modalElements.title.textContent = title;
    modalElements.message.textContent = message;
    modalElements.alertText.textContent = alertText;

    console.log('✅ Modal setup complete:', {
        action: action,
        count: count,
        title: title,
        storedAction: currentBulkAction
    });

    // Show modal
    try {
        const modal = new bootstrap.Modal(modalElements.modal);
        modal.show();
        console.log('✅ Modal shown successfully');
    } catch (error) {
        console.error('❌ Error showing modal:', error);
        alert('Lỗi hiển thị modal: ' + error.message);
        return false;
    }

    return true;
}

// Delete course function
function deleteCourse(courseId, courseTitle) {
    console.log('🗑️ Delete course:', courseId, courseTitle);
    document.getElementById('courseTitle').textContent = courseTitle;
    document.getElementById('courseIdInput').value = courseId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Debug function to check current states
function debugCurrentState() {
    console.log('🔍 DEBUG CURRENT STATE:');
    console.log('Selected Course IDs:', selectedCourseIds);
    console.log('Current Bulk Action:', currentBulkAction);
    
    const checkedBoxes = document.querySelectorAll('.course-checkbox:checked');
    console.log('Actually Checked Checkboxes:', checkedBoxes.length);
    console.log('Checked Values:', Array.from(checkedBoxes).map(cb => cb.value));
    
    const bulkActionInput = document.getElementById('bulkAction');
    console.log('Bulk Action Input Value:', bulkActionInput ? bulkActionInput.value : 'NOT FOUND');
    
    const bulkForm = document.getElementById('bulkForm');
    if (bulkForm) {
        const formData = new FormData(bulkForm);
        console.log('Current Form Data:');
        for (let [key, value] of formData.entries()) {
            console.log(`  ${key}: ${value}`);
        }
    }
}

// Make debug function available globally
window.debugCurrentState = debugCurrentState;
</script>

<?php include 'includes/admin-footer.php'; ?>