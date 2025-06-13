<?php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý danh mục';
$current_page = 'categories';

// Initialize variables
$message = '';
$error = '';
$debug = isset($_GET['debug']);

// Helper function to generate slug from Vietnamese text
function generateSlug($text)
{
    $vietnamese = [
        'á' => 'a',
        'à' => 'a',
        'ả' => 'a',
        'ã' => 'a',
        'ạ' => 'a',
        'ă' => 'a',
        'ắ' => 'a',
        'ằ' => 'a',
        'ẳ' => 'a',
        'ẵ' => 'a',
        'ặ' => 'a',
        'â' => 'a',
        'ấ' => 'a',
        'ầ' => 'a',
        'ẩ' => 'a',
        'ẫ' => 'a',
        'ậ' => 'a',
        'é' => 'e',
        'è' => 'e',
        'ẻ' => 'e',
        'ẽ' => 'e',
        'ẹ' => 'e',
        'ê' => 'e',
        'ế' => 'e',
        'ề' => 'e',
        'ể' => 'e',
        'ễ' => 'e',
        'ệ' => 'e',
        'í' => 'i',
        'ì' => 'i',
        'ỉ' => 'i',
        'ĩ' => 'i',
        'ị' => 'i',
        'ó' => 'o',
        'ò' => 'o',
        'ỏ' => 'o',
        'õ' => 'o',
        'ọ' => 'o',
        'ô' => 'o',
        'ố' => 'o',
        'ồ' => 'o',
        'ổ' => 'o',
        'ỗ' => 'o',
        'ộ' => 'o',
        'ơ' => 'o',
        'ớ' => 'o',
        'ờ' => 'o',
        'ở' => 'o',
        'ỡ' => 'o',
        'ợ' => 'o',
        'ú' => 'u',
        'ù' => 'u',
        'ủ' => 'u',
        'ũ' => 'u',
        'ụ' => 'u',
        'ư' => 'u',
        'ứ' => 'u',
        'ừ' => 'u',
        'ử' => 'u',
        'ữ' => 'u',
        'ự' => 'u',
        'ý' => 'y',
        'ỳ' => 'y',
        'ỷ' => 'y',
        'ỹ' => 'y',
        'ỵ' => 'y',
        'đ' => 'd'
    ];

    $text = strtolower($text);
    $text = strtr($text, $vietnamese);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');

    return $text;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($debug) {
        error_log("=== POST DEBUG ===");
        error_log("POST data: " . print_r($_POST, true));
    }

    // DELETE CATEGORY
    if (isset($_POST['delete_category'])) {
        $category_id = (int)($_POST['category_id'] ?? 0);

        if ($category_id <= 0) {
            $error = 'ID danh mục không hợp lệ!';
        } else {
            try {
                // Check if category exists
                $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $category = $stmt->fetch();

                if (!$category) {
                    $error = 'Danh mục không tồn tại!';
                } else {
                    // Check if has courses
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
                    $stmt->execute([$category_id]);
                    $course_count = $stmt->fetchColumn();

                    if ($course_count > 0) {
                        $error = "Không thể xóa danh mục '{$category['name']}' vì đang có {$course_count} khóa học!";
                    } else {
                        // Delete category
                        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                        if ($stmt->execute([$category_id])) {
                            $message = 'Đã xóa danh mục thành công!';
                            header('Location: categories.php?success=delete' . ($debug ? '&debug=1' : ''));
                            exit;
                        } else {
                            $error = 'Có lỗi khi xóa danh mục!';
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Lỗi database: ' . $e->getMessage();
            }
        }
    }
    // EDIT CATEGORY
    elseif (isset($_POST['category_id']) && isset($_POST['name'])) {
        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $error = 'Tên danh mục không được để trống!';
        } elseif (strlen($name) < 2) {
            $error = 'Tên danh mục phải có ít nhất 2 ký tự!';
        } elseif (strlen($name) > 100) {
            $error = 'Tên danh mục không được quá 100 ký tự!';
        } else {
            try {
                // Check duplicate name (except current)
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                $stmt->execute([$name, $category_id]);
                if ($stmt->fetch()) {
                    $error = 'Tên danh mục đã tồn tại!';
                } else {
                    $slug = generateSlug($name);

                    // Ensure unique slug
                    $original_slug = $slug;
                    $counter = 1;
                    while (true) {
                        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
                        $stmt->execute([$slug, $category_id]);
                        if (!$stmt->fetch()) break;
                        $slug = $original_slug . '-' . $counter++;
                    }

                    // Update category
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, slug = ? WHERE id = ?");
                    if ($stmt->execute([$name, $description, $slug, $category_id])) {
                        header('Location: categories.php?success=edit' . ($debug ? '&debug=1' : ''));
                        exit;
                    } else {
                        $error = 'Có lỗi khi cập nhật danh mục!';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Lỗi database: ' . $e->getMessage();
            }
        }
    }
    // ADD CATEGORY
    elseif (isset($_POST['name'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $error = 'Tên danh mục không được để trống!';
        } elseif (strlen($name) < 2) {
            $error = 'Tên danh mục phải có ít nhất 2 ký tự!';
        } elseif (strlen($name) > 100) {
            $error = 'Tên danh mục không được quá 100 ký tự!';
        } else {
            try {
                // Check duplicate name
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $error = 'Tên danh mục đã tồn tại!';
                } else {
                    $slug = generateSlug($name);

                    // Ensure unique slug
                    $original_slug = $slug;
                    $counter = 1;
                    while (true) {
                        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
                        $stmt->execute([$slug]);
                        if (!$stmt->fetch()) break;
                        $slug = $original_slug . '-' . $counter++;
                    }

                    // Insert new category
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description, slug, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
                    if ($stmt->execute([$name, $description, $slug])) {
                        header('Location: categories.php?success=add' . ($debug ? '&debug=1' : ''));
                        exit;
                    } else {
                        $error = 'Có lỗi khi thêm danh mục!';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Lỗi database: ' . $e->getMessage();
            }
        }
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'add':
            $message = 'Đã thêm danh mục thành công!';
            break;
        case 'edit':
            $message = 'Đã cập nhật danh mục thành công!';
            break;
        case 'delete':
            $message = 'Đã xóa danh mục thành công!';
            break;
    }
}

// Get filter parameters - FIX THIS SECTION
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

// Fix limit handling to prevent undefined key and division by zero
$requested_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
$limit = in_array($requested_limit, [5, 10, 20, 50]) ? $requested_limit : 5;

// Ensure limit is never zero or negative
if ($limit <= 0) {
    $limit = 5;
}

$offset = ($page - 1) * $limit;

// Build query for categories with course count
$where_clause = '';
$params = [];

if ($search) {
    $where_clause = "WHERE (c.name LIKE ? OR c.description LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

// Get total count
try {
    $count_sql = "SELECT COUNT(DISTINCT c.id) FROM categories c $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_records = (int)$stmt->fetchColumn();
    
    // Ensure total_records is not negative
    if ($total_records < 0) {
        $total_records = 0;
    }

    // Fix division by zero - ensure both values are positive
    $total_pages = ($total_records > 0 && $limit > 0) ? ceil($total_records / $limit) : 1;
    
    // Ensure total_pages is at least 1
    if ($total_pages < 1) {
        $total_pages = 1;
    }
    
    // Adjust page if it exceeds total pages
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Get categories with course count - FIX FOR MARIADB
    $sql = "SELECT c.*, COUNT(co.id) as course_count 
            FROM categories c 
            LEFT JOIN courses co ON c.id = co.category_id 
            $where_clause
            GROUP BY c.id 
            ORDER BY c.created_at DESC 
            LIMIT $offset, $limit";

    $stmt = $pdo->prepare($sql);
    // Don't pass offset and limit as parameters, they're already in SQL
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
    
    if ($debug) {
        error_log("PAGINATION DEBUG: Search='$search', Page=$page, Limit=$limit, Offset=$offset, Total=$total_records, TotalPages=$total_pages");
        error_log("SQL: $sql");
        error_log("Execute params: " . print_r($params, true));
    }
    
} catch (PDOException $e) {
    $categories = [];
    $total_records = 0;
    $total_pages = 1;
    $error = 'Lỗi database: ' . $e->getMessage();
    
    if ($debug) {
        error_log("DATABASE ERROR: " . $e->getMessage());
        error_log("SQL that failed: " . ($sql ?? 'N/A'));
        error_log("Params that failed: " . print_r($execute_params ?? [], true));
    }
}

// Ensure all values are valid
$total_records = max(0, $total_records);
$total_pages = max(1, $total_pages);
$page = max(1, min($page, $total_pages));
$categories = $categories ?: [];

// Get statistics
try {
    $stats = [
        'total_categories' => $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
        'categories_with_courses' => $pdo->query("SELECT COUNT(DISTINCT category_id) FROM courses WHERE category_id IS NOT NULL")->fetchColumn(),
        'total_courses' => $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['total_categories' => 0, 'categories_with_courses' => 0, 'total_courses' => 0];
}

// Get category for editing
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_category = $stmt->fetch();
    } catch (Exception $e) {
        $error = 'Lỗi khi tải thông tin danh mục!';
    }
}

// Build query string helper - Enhanced with validation
function build_query($params = [])
{
    global $search, $page, $limit, $debug;
    
    // Ensure limit has a valid value
    $safe_limit = isset($limit) && in_array($limit, [5, 10, 20, 50]) ? $limit : 5;
    
    $query_params = array_filter([
        'search' => $search ?: null,
        'page' => ($page > 1) ? $page : null,
        'limit' => ($safe_limit != 5) ? $safe_limit : null, // Only include if not default
        'debug' => $debug ? '1' : null
    ], function($value) {
        return $value !== null && $value !== '';
    });
    
    // Merge with additional params
    $final_params = array_merge($query_params, array_filter($params, function($value) {
        return $value !== null && $value !== '';
    }));
    
    return http_build_query($final_params);
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Debug Information -->
<?php if ($debug): ?>
    <div class="alert alert-info mb-4">
        <h5><i class="fas fa-bug me-2"></i>Debug Mode</h5>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Request:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
                <p><strong>POST Data:</strong></p>
                <pre class="small"><?php print_r($_POST); ?></pre>
            </div>
            <div class="col-md-6">
                <p><strong>Pagination:</strong> Page <?php echo $page; ?>/<?php echo $total_pages; ?></p>
                <p><strong>Records:</strong> <?php echo count($categories); ?>/<?php echo $total_records; ?></p>
                <p><strong>Edit Mode:</strong> <?php echo $edit_category ? 'ID ' . $edit_category['id'] : 'No'; ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

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
            <i class="fas fa-tags me-2"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">Quản lý danh mục khóa học trong hệ thống</p>
    </div>
    <div>
        <a href="categories.php?<?php echo build_query(['debug' => $debug ? null : '1']); ?>"
            class="btn btn-outline-<?php echo $debug ? 'secondary' : 'info'; ?>">
            <i class="fas fa-bug<?php echo $debug ? '-slash' : ''; ?> me-2"></i>
            <?php echo $debug ? 'Tắt' : 'Bật'; ?> Debug
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tổng danh mục</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_categories']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tags fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Có khóa học</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['categories_with_courses']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Tổng khóa học</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_courses']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-book fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Form -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-<?php echo $edit_category ? 'edit' : 'plus'; ?> me-2"></i>
            <?php echo $edit_category ? 'Chỉnh sửa danh mục' : 'Thêm danh mục mới'; ?>
        </h6>
    </div>
    <div class="card-body">
        <form method="POST" id="categoryForm">
            <?php if ($edit_category): ?>
                <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Đang chỉnh sửa:</strong> <?php echo htmlspecialchars($edit_category['name']); ?> (ID: <?php echo $edit_category['id']; ?>)
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="100"
                            placeholder="Nhập tên danh mục..."
                            value="<?php echo $edit_category ? htmlspecialchars($edit_category['name']) : ''; ?>">
                        <div class="form-text">Tên danh mục phải duy nhất (2-100 ký tự)</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="500"
                            placeholder="Nhập mô tả cho danh mục..."><?php echo $edit_category ? htmlspecialchars($edit_category['description']) : ''; ?></textarea>
                        <div class="form-text">Mô tả ngắn gọn (tối đa 500 ký tự)</div>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <?php if ($edit_category): ?>
                    <a href="categories.php?<?php echo build_query(['edit' => null]); ?>" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-2"></i>Hủy
                    </a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-2"></i>Cập nhật
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Thêm danh mục
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Search -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-search me-2"></i>Tìm kiếm danh mục
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <?php if ($debug): ?><input type="hidden" name="debug" value="1"><?php endif; ?>
            <input type="hidden" name="limit" value="<?php echo $limit; ?>">
            <div class="col-md-9">
                <input type="text" name="search" class="form-control"
                    placeholder="Nhập tên hoặc mô tả danh mục..."
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search me-2"></i>Tìm kiếm
                </button>
            </div>
        </form>

        <?php if ($search): ?>
            <div class="mt-2">
                <a href="categories.php?<?php echo build_query(['search' => null, 'page' => null]); ?>"
                    class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times me-1"></i>Xóa bộ lọc
                </a>
                <span class="text-muted ms-2">Tìm kiếm: "<strong><?php echo htmlspecialchars($search); ?></strong>"</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Categories Table -->
<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-list me-2"></i>Danh sách danh mục
            <span class="badge bg-primary ms-2"><?php echo count($categories); ?></span>
        </h6>
    </div>
    <div class="card-body">
        <?php if ($categories): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">Tên danh mục</th>
                            <th width="35%">Mô tả</th>
                            <th width="10%">Khóa học</th>
                            <th width="15%">Ngày tạo</th>
                            <th width="10%">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $index => $category): ?>
                            <tr <?php if ($edit_category && $edit_category['id'] == $category['id']) echo 'class="table-warning"'; ?>>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <strong class="text-primary"><?php echo htmlspecialchars($category['name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        ID: <?php echo $category['id']; ?>
                                        <?php if ($category['slug']): ?>
                                            | <?php echo htmlspecialchars($category['slug']); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($category['description']): ?>
                                        <p class="mb-0 text-muted">
                                            <?php echo htmlspecialchars(mb_substr($category['description'], 0, 100)); ?>
                                            <?php if (mb_strlen($category['description']) > 100) echo '...'; ?>
                                        </p>
                                    <?php else: ?>
                                        <em class="text-muted">Chưa có mô tả</em>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($category['course_count'] > 0): ?>
                                        <span class="badge bg-info fs-6"><?php echo $category['course_count']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary fs-6">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($category['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="categories.php?<?php echo build_query(['edit' => $category['id']]); ?>"
                                            class="btn btn-outline-primary btn-sm" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <?php if ($category['course_count'] == 0): ?>
                                            <form method="POST" style="display: inline;" class="delete-form"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                <input type="hidden" name="delete_category" value="1">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Xóa">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm" disabled
                                                title="Không thể xóa - có <?php echo $category['course_count']; ?> khóa học">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Info -->
            <div class="mt-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <small class="text-muted">
                            Hiển thị <?php echo count($categories); ?> danh mục
                            (<?php echo number_format($offset + 1); ?> - <?php echo number_format($offset + count($categories)); ?>
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

                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            // First page
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?' . build_query(['page' => 1]) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            // Current range
                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo build_query(['page' => $i]); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor;

                            // Last page
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
                <i class="fas fa-tags fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Không tìm thấy danh mục nào</h4>
                <p class="text-muted mb-4">
                    <?php if ($search): ?>
                        Thử thay đổi từ khóa tìm kiếm hoặc
                        <a href="categories.php?<?php echo build_query(['search' => null, 'page' => null]); ?>">xem tất cả danh mục</a>
                    <?php else: ?>
                        Hệ thống chưa có danh mục nào. Hãy tạo danh mục đầu tiên!
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
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

    .table th {
        border-top: none;
        font-weight: 600;
        font-size: 0.85rem;
        color: #5a5c69;
    }

    .table tbody tr:hover {
        background-color: #f8f9fc;
    }

    .table-warning {
        background-color: #fff3cd !important;
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
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert:not(.alert-info)').forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);

        // Form validation
        document.getElementById('categoryForm')?.addEventListener('submit', function(e) {
            const name = this.querySelector('[name="name"]').value.trim();
            if (!name || name.length < 2) {
                e.preventDefault();
                alert('Tên danh mục phải có ít nhất 2 ký tự!');
                return false;
            }

            // Show loading
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
        });

        // Delete confirmation
        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const name = this.dataset.name;
                if (confirm(`⚠️ Xác nhận xóa danh mục "${name}"?\n\nThao tác này không thể hoàn tác!`)) {
                    this.submit();
                }
            });
        });

        // Page size selector
        document.getElementById('pageSize')?.addEventListener('change', function() {
            const url = new URL(window.location);
            url.searchParams.set('limit', this.value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        });

        // Focus edit form
        <?php if ($edit_category): ?>
            document.querySelector('[name="name"]')?.focus();
        <?php endif; ?>

        console.log('✅ Categories page loaded - Total: <?php echo $total_records; ?>, Current: <?php echo count($categories); ?>');
    });
</script>

<?php include 'includes/admin-footer.php'; ?>