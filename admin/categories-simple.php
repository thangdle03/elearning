<?php

require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý danh mục (Simple)';
$current_page = 'categories';

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add category
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $error = 'Tên danh mục không được để trống!';
        } elseif (strlen($name) < 2) {
            $error = 'Tên danh mục phải có ít nhất 2 ký tự!';
        } elseif (strlen($name) > 100) {
            $error = 'Tên danh mục không được quá 100 ký tự!';
        } else {
            try {
                // Check if category name already exists
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $error = 'Tên danh mục đã tồn tại!';
                } else {
                    // Generate slug
                    function generateSlug($text) {
                        $text = strtolower($text);
                        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
                        $text = preg_replace('/[\s-]+/', '-', $text);
                        return trim($text, '-');
                    }
                    
                    $slug = generateSlug($name);
                    
                    // Insert new category
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description, slug, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
                    if ($stmt->execute([$name, $description, $slug])) {
                        $message = 'Đã thêm danh mục thành công!';
                        // Clear form data
                        $_POST = array();
                    } else {
                        $error = 'Có lỗi khi thêm danh mục!';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Lỗi database: ' . $e->getMessage();
            }
        }
    }

    // Delete category
    elseif (isset($_POST['delete_category'])) {
        $category_id = (int)($_POST['category_id'] ?? 0);

        if ($category_id <= 0) {
            $error = 'ID danh mục không hợp lệ!';
        } else {
            try {
                // Check if category has courses
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
                $stmt->execute([$category_id]);
                $course_count = $stmt->fetchColumn();

                if ($course_count > 0) {
                    $error = "Không thể xóa danh mục này vì đang có {$course_count} khóa học!";
                } else {
                    // Delete category
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    if ($stmt->execute([$category_id])) {
                        $message = 'Đã xóa danh mục thành công!';
                    } else {
                        $error = 'Có lỗi khi xóa danh mục!';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Lỗi database: ' . $e->getMessage();
            }
        }
    }
}

// Get categories
try {
    $categories = $pdo->query("SELECT c.*, COUNT(co.id) as course_count FROM categories c LEFT JOIN courses co ON c.id = co.category_id GROUP BY c.id ORDER BY c.created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $error = 'Lỗi database: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { max-width: 1200px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
        .btn { border-radius: 5px; }
        .form-card { background: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
        .table-card { background: white; padding: 20px; border-radius: 10px; }
        .header-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 0; margin-bottom: 30px; }
    </style>
</head>
<body>

<div class="header-section">
    <div class="container">
        <h1 class="h3 mb-0"><i class="fas fa-tags me-2"></i><?php echo $page_title; ?></h1>
        <p class="mb-0 opacity-75">Quản lý danh mục khóa học - Version đơn giản</p>
    </div>
</div>

<div class="container">
    <!-- Messages -->
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

    <!-- Add Category Form -->
    <div class="form-card">
        <h4 class="mb-4"><i class="fas fa-plus text-primary me-2"></i>Thêm danh mục mới</h4>
        
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required
                            placeholder="Nhập tên danh mục..." maxlength="100"
                            value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        <div class="form-text">Tên danh mục phải là duy nhất (2-100 ký tự)</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Nhập mô tả cho danh mục..." maxlength="500"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <div class="form-text">Mô tả ngắn gọn về danh mục này</div>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" name="add_category" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Thêm danh mục
                </button>
            </div>
        </form>
    </div>

    <!-- Categories List -->
    <div class="table-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">
                <i class="fas fa-list text-primary me-2"></i>Danh sách danh mục
                <span class="badge bg-primary ms-2"><?php echo count($categories); ?></span>
            </h4>
            <a href="categories.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Về trang chính
            </a>
        </div>

        <?php if ($categories): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">Tên danh mục</th>
                            <th width="40%">Mô tả</th>
                            <th width="10%">Khóa học</th>
                            <th width="15%">Ngày tạo</th>
                            <th width="5%">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $index => $category): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong class="text-primary"><?php echo htmlspecialchars($category['name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        ID: <?php echo $category['id']; ?>
                                        <?php if (!empty($category['slug'])): ?>
                                            | <?php echo htmlspecialchars($category['slug']); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $desc = $category['description'];
                                    if ($desc) {
                                        echo '<p class="mb-0 text-muted">' . htmlspecialchars(mb_substr($desc, 0, 150)) . (mb_strlen($desc) > 150 ? '...' : '') . '</p>';
                                    } else {
                                        echo '<em class="text-muted">Chưa có mô tả</em>';
                                    }
                                    ?>
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
                                    <?php if ($category['course_count'] == 0): ?>
                                        <form method="POST" action="" style="display: inline;"
                                            onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục \'<?php echo addslashes($category['name']); ?>\'?')">
                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" name="delete_category" value="1" 
                                                class="btn btn-outline-danger btn-sm" title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled title="Không thể xóa - có khóa học">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-tags fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Chưa có danh mục nào</h4>
                <p class="text-muted">Hãy thêm danh mục đầu tiên bằng form ở trên!</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Debug Information -->
    <div class="card mt-4" style="border-left: 4px solid #17a2b8;">
        <div class="card-body">
            <h6 class="card-title"><i class="fas fa-info-circle text-info me-2"></i>Thông tin Debug</h6>
            <p class="mb-1"><strong>Request Method:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
            <p class="mb-1"><strong>POST Data:</strong> <?php echo $_POST ? 'Yes (' . count($_POST) . ' items)' : 'No'; ?></p>
            <p class="mb-1"><strong>Categories Count:</strong> <?php echo count($categories); ?></p>
            <p class="mb-0"><strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            
            <?php if ($_POST): ?>
                <details class="mt-2">
                    <summary>Chi tiết POST Data</summary>
                    <pre class="mt-2"><?php print_r($_POST); ?></pre>
                </details>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation -->
    <div class="text-center mt-4 mb-4">
        <a href="../admin/" class="btn btn-secondary me-2">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
        <a href="categories.php" class="btn btn-primary me-2">
            <i class="fas fa-list me-2"></i>Categories (Full)
        </a>
        <a href="add-course.php" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>Add Course
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const nameInput = this.querySelector('input[name="name"]');
    const name = nameInput.value.trim();
    
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
    
    console.log('Form submitted with data:', {
        name: name,
        description: this.querySelector('textarea[name="description"]').value
    });
});

console.log('Categories Simple page loaded successfully!');
</script>

</body>
</html>