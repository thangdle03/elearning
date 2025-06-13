<?php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý danh mục';
$current_page = 'categories';

// Handle form submissions
$message = '';
$error = '';

// Debug mode
$debug = isset($_GET['debug']);

// Helper function to generate slug
function generateSlug($text) {
    // Convert Vietnamese characters to ASCII
    $vietnamese = [
        'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($debug) {
        error_log("=== POST DEBUG START ===");
        error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("POST count: " . count($_POST));
        error_log("POST keys: " . implode(', ', array_keys($_POST)));
        error_log("POST data: " . print_r($_POST, true));
        error_log("=== POST DEBUG END ===");
    }

    // Check if POST data exists
    if (empty($_POST)) {
        if ($debug) {
            error_log("ERROR: Empty POST data");
        }
        $error = 'Không nhận được dữ liệu form!';
    }
    // DELETE CATEGORY - Check this FIRST
    elseif (isset($_POST['delete_category'])) {
        $category_id = (int)($_POST['category_id'] ?? 0);

        if ($debug) {
            error_log("DELETE CATEGORY: ID=$category_id");
            error_log("POST delete_category value: " . $_POST['delete_category']);
        }

        if ($category_id <= 0) {
            $error = 'ID danh mục không hợp lệ!';
            if ($debug) {
                error_log("DELETE ERROR: Invalid category ID: $category_id");
            }
        } else {
            try {
                // Check if category exists and get info
                $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $existing = $stmt->fetch();

                if ($debug) {
                    error_log("DELETE: Category exists check - " . ($existing ? "Found: " . $existing['name'] : "Not found"));
                }

                if (!$existing) {
                    $error = 'Danh mục không tồn tại!';
                } else {
                    // Check if has courses
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
                    $stmt->execute([$category_id]);
                    $course_count = $stmt->fetchColumn();

                    if ($debug) {
                        error_log("DELETE: Course count check - $course_count courses found");
                    }

                    if ($course_count > 0) {
                        $error = "Không thể xóa danh mục '{$existing['name']}' vì đang có {$course_count} khóa học!";
                    } else {
                        // Delete category
                        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                        $deleteResult = $stmt->execute([$category_id]);
                        $rowsAffected = $stmt->rowCount();
                        
                        if ($debug) {
                            error_log("DELETE: Execute result - " . ($deleteResult ? "SUCCESS" : "FAILED"));
                            error_log("DELETE: Rows affected - $rowsAffected");
                        }

                        if ($deleteResult && $rowsAffected > 0) {
                            if ($debug) {
                                error_log("DELETE SUCCESS: Category '{$existing['name']}' (ID: $category_id) deleted");
                            }
                            $redirect_url = 'categories.php?success=delete';
                            if ($debug) $redirect_url .= '&debug=1';
                            header('Location: ' . $redirect_url);
                            exit;
                        } else {
                            $error = 'Không thể xóa danh mục! Có thể danh mục không tồn tại hoặc đã bị xóa.';
                            if ($debug) {
                                error_log("DELETE ERROR: Delete failed - Execute: $deleteResult, Rows: $rowsAffected");
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Lỗi database: ' . $e->getMessage();
                if ($debug) {
                    error_log("DELETE ERROR: " . $e->getMessage());
                }
            }
        }
    }
    // EDIT CATEGORY - Check this SECOND
    elseif (isset($_POST['category_id']) && (int)$_POST['category_id'] > 0 && isset($_POST['name'])) {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($debug) {
            error_log("EDIT CATEGORY (auto-detected): ID=$category_id, Name='$name', Description='$description'");
        }

        if ($category_id <= 0) {
            $error = 'ID danh mục không hợp lệ!';
        } elseif (empty($name)) {
            $error = 'Tên danh mục không được để trống!';
        } elseif (strlen($name) < 2) {
            $error = 'Tên danh mục phải có ít nhất 2 ký tự!';
        } elseif (strlen($name) > 100) {
            $error = 'Tên danh mục không được quá 100 ký tự!';
        } else {
            try {
                // Check if category exists
                $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    $error = 'Danh mục không tồn tại!';
                } else {
                    // Check duplicate name (except current)
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                    $stmt->execute([$name, $category_id]);
                    if ($stmt->fetch()) {
                        $error = 'Tên danh mục đã tồn tại!';
                    } else {
                        $slug = generateSlug($name);
                        
                        // Check if slug exists (except current), add number if needed
                        $original_slug = $slug;
                        $counter = 1;
                        while (true) {
                            $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
                            $stmt->execute([$slug, $category_id]);
                            if (!$stmt->fetch()) {
                                break;
                            }
                            $slug = $original_slug . '-' . $counter;
                            $counter++;
                        }
                        
                        // Update category
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, slug = ? WHERE id = ?");
                        if ($stmt->execute([$name, $description, $slug, $category_id])) {
                            if ($debug) {
                                error_log("EDIT SUCCESS: Category ID $category_id updated, rows affected = " . $stmt->rowCount());
                            }
                            $redirect_url = 'categories.php?success=edit';
                            if ($debug) $redirect_url .= '&debug=1';
                            header('Location: ' . $redirect_url);
                            exit;
                        } else {
                            $error = 'Có lỗi khi cập nhật danh mục!';
                            if ($debug) {
                                error_log("EDIT ERROR: Update failed");
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Lỗi database: ' . $e->getMessage();
                if ($debug) {
                    error_log("EDIT ERROR: " . $e->getMessage());
                }
            }
        }
    }
    // ADD CATEGORY
    elseif (isset($_POST['add_category']) || (isset($_POST['name']) && !isset($_POST['category_id']))) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($debug) {
            error_log("ADD CATEGORY: Name='$name', Description='$description'");
        }

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
                    
                    // Check if slug exists, add number if needed
                    $original_slug = $slug;
                    $counter = 1;
                    while (true) {
                        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
                        $stmt->execute([$slug]);
                        if (!$stmt->fetch()) {
                            break;
                        }
                        $slug = $original_slug . '-' . $counter;
                        $counter++;
                    }
                    
                    // Insert new category
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description, slug, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
                    if ($stmt->execute([$name, $description, $slug])) {
                        if ($debug) {
                            error_log("ADD SUCCESS: Category added with ID = " . $pdo->lastInsertId());
                        }
                        $redirect_url = 'categories.php?success=add';
                        if ($debug) $redirect_url .= '&debug=1';
                        header('Location: ' . $redirect_url);
                        exit;
                    } else {
                        $error = 'Có lỗi khi thêm danh mục!';
                        if ($debug) {
                            error_log("ADD ERROR: Insert failed");
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = 'Lỗi database: ' . $e->getMessage();
                if ($debug) {
                    error_log("ADD ERROR: " . $e->getMessage());
                }
            }
        }
    }
    // INVALID ACTION OR MISSING DATA
    else {
        if ($debug) {
            error_log("INVALID ACTION OR MISSING DATA");
            error_log("Available POST keys: " . implode(', ', array_keys($_POST)));
            error_log("Has category_id: " . (isset($_POST['category_id']) ? 'YES (' . $_POST['category_id'] . ')' : 'NO'));
            error_log("Has name: " . (isset($_POST['name']) ? 'YES (' . $_POST['name'] . ')' : 'NO'));
            error_log("Has add_category: " . (isset($_POST['add_category']) ? 'YES' : 'NO'));
            error_log("Has edit_category: " . (isset($_POST['edit_category']) ? 'YES' : 'NO'));
            error_log("Has delete_category: " . (isset($_POST['delete_category']) ? 'YES' : 'NO'));
        }
        $error = 'Thao tác không hợp lệ hoặc thiếu dữ liệu!';
    }
}

// Handle success messages from redirects
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

// Get filter parameters
$search = trim($_GET['search'] ?? '');

// Build query with search
$sql = "SELECT c.*, COUNT(co.id) as course_count FROM categories c LEFT JOIN courses co ON c.id = co.category_id";
$params = [];

if ($search) {
    $sql .= " WHERE (c.name LIKE ? OR c.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " GROUP BY c.id ORDER BY c.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $error = 'Lỗi database: ' . $e->getMessage();
}

// Get statistics
try {
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM categories) as total_categories,
            (SELECT COUNT(DISTINCT category_id) FROM courses WHERE category_id IS NOT NULL) as categories_with_courses,
            (SELECT COUNT(*) FROM courses) as total_courses
    ")->fetch();
} catch (Exception $e) {
    $stats = [
        'total_categories' => 0,
        'categories_with_courses' => 0,
        'total_courses' => 0
    ];
}

// Variable for edit mode
$editCategory = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editCategory = $stmt->fetch();
        if (!$editCategory) {
            $error = 'Danh mục không tồn tại!';
        }
    } catch (Exception $e) {
        $error = 'Lỗi khi tải thông tin danh mục: ' . $e->getMessage();
    }
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Debug Mode -->
<?php if ($debug): ?>
    <div class="alert alert-info">
        <h5><i class="fas fa-bug me-2"></i>Debug Mode</h5>
        <p><strong>Request Method:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
        <p><strong>POST Data:</strong></p>
        <pre><?php print_r($_POST); ?></pre>
        <p><strong>GET Data:</strong></p>
        <pre><?php print_r($_GET); ?></pre>
        <p><strong>Edit Category:</strong> <?php echo $editCategory ? json_encode($editCategory) : 'null'; ?></p>
        <p><strong>Message:</strong> <?php echo htmlspecialchars($message); ?></p>
        <p><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
    </div>
<?php endif; ?>

<!-- Success/Error Messages -->
<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
        <?php if ($debug): ?>
            <a href="categories.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-bug-slash me-2"></i>Tắt Debug
            </a>
        <?php else: ?>
            <a href="categories.php?debug=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline-info">
                <i class="fas fa-bug me-2"></i>Debug Mode
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Tổng danh mục
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_categories']); ?>
                        </div>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Có khóa học
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['categories_with_courses']); ?>
                        </div>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Tổng khóa học
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_courses']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-book fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Category Form -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-<?php echo $editCategory ? 'edit' : 'plus'; ?> me-2"></i>
            <?php echo $editCategory ? 'Chỉnh sửa danh mục' : 'Thêm danh mục mới'; ?>
        </h6>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="categoryForm">
            <?php if ($editCategory): ?>
                <input type="hidden" name="category_id" value="<?php echo $editCategory['id']; ?>">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Đang chỉnh sửa danh mục:</strong> <?php echo htmlspecialchars($editCategory['name']); ?>
                    (ID: <?php echo $editCategory['id']; ?>)
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required
                            placeholder="Nhập tên danh mục..." maxlength="100"
                            value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>">
                        <div class="form-text">Tên danh mục phải là duy nhất (2-100 ký tự)</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"
                            placeholder="Nhập mô tả cho danh mục..." maxlength="500"><?php echo $editCategory ? htmlspecialchars($editCategory['description']) : ''; ?></textarea>
                        <div class="form-text">Mô tả ngắn gọn về danh mục này (tối đa 500 ký tự)</div>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <?php if ($editCategory): ?>
                    <a href="categories.php" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-2"></i>Hủy
                    </a>
                    <button type="submit" name="edit_category" value="1" class="btn btn-warning" id="editBtn">
                        <i class="fas fa-save me-2"></i>Cập nhật danh mục
                    </button>
                <?php else: ?>
                    <button type="submit" name="add_category" value="1" class="btn btn-primary" id="addBtn">
                        <i class="fas fa-save me-2"></i>Thêm danh mục
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Search Filter -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-search me-2"></i>Tìm kiếm danh mục
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <?php if ($debug): ?>
                <input type="hidden" name="debug" value="1">
            <?php endif; ?>
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
                <a href="categories.php<?php echo $debug ? '?debug=1' : ''; ?>" class="btn btn-outline-secondary btn-sm">
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
                            <tr <?php if ($editCategory && $editCategory['id'] == $category['id']) echo 'class="table-warning"'; ?>>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div>
                                        <strong class="text-primary"><?php echo htmlspecialchars($category['name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-hashtag me-1"></i>ID: <?php echo $category['id']; ?>
                                            <?php if (!empty($category['slug'])): ?>
                                                | <i class="fas fa-link me-1"></i><?php echo htmlspecialchars($category['slug']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $desc = $category['description'];
                                    if ($desc) {
                                        echo '<p class="mb-0 text-muted">' . htmlspecialchars(mb_substr($desc, 0, 100)) . (mb_strlen($desc) > 100 ? '...' : '') . '</p>';
                                    } else {
                                        echo '<em class="text-muted">Chưa có mô tả</em>';
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($category['course_count'] > 0): ?>
                                        <a href="courses.php?category_id=<?php echo $category['id']; ?>"
                                            class="badge bg-info fs-6 text-decoration-none">
                                            <?php echo number_format($category['course_count']); ?>
                                        </a>
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
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="categories.php?edit=<?php echo $category['id']; ?><?php echo $debug ? '&debug=1' : ''; ?>"
                                            class="btn btn-outline-primary btn-sm" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($category['course_count'] == 0): ?>
                                            <form method="POST" action="categories.php<?php echo $debug ? '?debug=1' : ''; ?>" style="display: inline;" class="delete-form"
                                                data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-category-id="<?php echo $category['id']; ?>">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                <input type="hidden" name="delete_category" value="1">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Xóa danh mục">
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

            <!-- Summary -->
            <div class="mt-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <small class="text-muted">
                            Hiển thị <?php echo count($categories); ?> danh mục
                            <?php if ($search): ?>
                                với từ khóa "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">
                            Tổng cộng: <?php echo number_format($stats['total_categories']); ?> danh mục
                        </small>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-tags fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Không tìm thấy danh mục nào</h4>
                <p class="text-muted mb-4">
                    <?php if ($search): ?>
                        Thử thay đổi từ khóa tìm kiếm hoặc <a href="categories.php<?php echo $debug ? '?debug=1' : ''; ?>" class="text-decoration-none">xem tất cả danh mục</a>
                    <?php else: ?>
                        Hệ thống chưa có danh mục nào. Hãy tạo danh mục đầu tiên!
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
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
        background-color: #f8f9fc !important;
    }
    .btn-group-sm>.btn {
        margin: 0 1px;
    }
    .table tbody tr:hover {
        background-color: #f8f9fc;
    }
    .table-warning {
        background-color: #fff3cd !important;
    }
    .alert {
        border: none;
        border-radius: 0.5rem;
    }
    .card {
        border: none;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
    }
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert:not(.alert-info)').forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form validation - FIX để không chặn submit
    const form = document.getElementById('categoryForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const nameInput = this.querySelector('input[name="name"]');
            const name = nameInput.value.trim();

            // Only prevent if validation fails
            if (!name) {
                e.preventDefault();
                alert('Vui lòng nhập tên danh mục!');
                nameInput.focus();
                return false;
            }

            if (name.length < 2) {
                e.preventDefault();
                alert('Tên danh mục phải có ít nhất 2 ký tự!');
                nameInput.focus();
                return false;
            }

            // Don't prevent default - let form submit naturally
            console.log('📤 Form validation passed, submitting...');
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
            }
            
            // Form will submit naturally
            return true;
        });
    }

    // Delete confirmation
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const categoryName = this.dataset.categoryName;
            const confirmed = confirm(`⚠️ XÁC NHẬN XÓA\n\nBạn có chắc chắn muốn xóa danh mục:\n📁 ${categoryName}\n\n❌ Thao tác này không thể hoàn tác!\n\n🔄 Nhấn OK để xóa, Cancel để hủy.`);
            
            if (confirmed) {
                // Show loading on delete button
                const deleteBtn = this.querySelector('button[name="delete_category"]');
                if (deleteBtn) {
                    deleteBtn.disabled = true;
                    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                }
                
                // Submit form
                this.submit();
            }
        });
    });

    // Search on Enter key
    document.querySelector('input[name="search"]')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.form.submit();
        }
    });

    // Focus on name input if in edit mode
    <?php if ($editCategory): ?>
        const nameInput = document.getElementById('categoryName');
        if (nameInput) {
            nameInput.focus();
            nameInput.select();
        }
    <?php endif; ?>

    console.log('✅ Categories page loaded successfully!');
    console.log('📊 Statistics:', <?php echo json_encode($stats); ?>);
    console.log('📋 Categories loaded:', <?php echo count($categories); ?>);
    <?php if ($editCategory): ?>
        console.log('✏️ Edit mode for category ID:', <?php echo $editCategory['id']; ?>);
    <?php endif; ?>
});
</script>

<?php include 'includes/admin-footer.php'; ?>