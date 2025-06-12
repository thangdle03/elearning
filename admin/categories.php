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

if ($_POST) {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            $error = 'Tên danh mục không được để trống!';
        } else {
            try {
                // Check if category name already exists
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $error = 'Tên danh mục đã tồn tại!';
                } else {
                    // Insert new category
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
                    if ($stmt->execute([$name, $description])) {
                        $message = 'Đã thêm danh mục thành công!';
                    } else {
                        $error = 'Có lỗi khi thêm danh mục!';
                    }
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['edit_category'])) {
        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            $error = 'Tên danh mục không được để trống!';
        } else {
            try {
                // Check if category name already exists (except current category)
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                $stmt->execute([$name, $category_id]);
                if ($stmt->fetch()) {
                    $error = 'Tên danh mục đã tồn tại!';
                } else {
                    // Update category
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                    if ($stmt->execute([$name, $description, $category_id])) {
                        $message = 'Đã cập nhật danh mục thành công!';
                    } else {
                        $error = 'Có lỗi khi cập nhật danh mục!';
                    }
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['delete_category'])) {
        $category_id = (int)$_POST['category_id'];
        
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
        } catch (Exception $e) {
            $error = 'Có lỗi xảy ra khi xóa danh mục: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';

// Build query
$sql = "
    SELECT c.*, COUNT(co.id) as course_count 
    FROM categories c 
    LEFT JOIN courses co ON c.id = co.category_id
";

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($where_conditions) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " GROUP BY c.id ORDER BY c.name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
    $error = 'Có lỗi khi tải danh sách danh mục: ' . $e->getMessage();
}

// Get statistics
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_categories,
            COUNT(CASE WHEN co.id IS NOT NULL THEN 1 END) as categories_with_courses,
            SUM(course_counts.course_count) as total_courses_in_categories
        FROM categories c
        LEFT JOIN (
            SELECT category_id, COUNT(*) as course_count 
            FROM courses 
            GROUP BY category_id
        ) course_counts ON c.id = course_counts.category_id
        LEFT JOIN courses co ON c.id = co.category_id
    ")->fetch();
} catch (Exception $e) {
    $stats = [
        'total_categories' => 0,
        'categories_with_courses' => 0,
        'total_courses_in_categories' => 0
    ];
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
            <i class="fas fa-tags me-2"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">Quản lý danh mục khóa học trong hệ thống</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus me-2"></i>Thêm danh mục mới
        </button>
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
                            <?php echo number_format($stats['total_courses_in_categories'] ?? 0); ?>
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

<!-- Search Filter -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-search me-2"></i>Tìm kiếm danh mục
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" 
                       placeholder="Nhập tên hoặc mô tả danh mục..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search me-2"></i>Tìm kiếm
                </button>
            </div>
        </form>
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
            <table class="table table-bordered table-hover" id="categoriesTable">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="20%">Tên danh mục</th>
                        <th width="40%">Mô tả</th>
                        <th width="10%">Số khóa học</th>
                        <th width="15%">Ngày tạo</th>
                        <th width="10%">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $index => $category): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div>
                                <strong class="text-primary"><?php echo htmlspecialchars($category['name']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-hashtag me-1"></i>ID: <?php echo $category['id']; ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <p class="mb-0 text-muted">
                                <?php 
                                $desc = $category['description'];
                                echo $desc ? htmlspecialchars(mb_substr($desc, 0, 100)) . (mb_strlen($desc) > 100 ? '...' : '') : '<em>Chưa có mô tả</em>';
                                ?>
                            </p>
                        </td>
                        <td class="text-center">
                            <?php if ($category['course_count'] > 0): ?>
                                <a href="courses.php?category_id=<?php echo $category['id']; ?>" 
                                   class="badge bg-info fs-6 text-decoration-none">
                                    <?php echo number_format($category['course_count']); ?> khóa học
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary fs-6">0 khóa học</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('d/m/Y H:i', strtotime($category['created_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                        title="Chỉnh sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="confirmDelete(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['course_count']; ?>)" 
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
                        Hiển thị <?php echo count($categories); ?> danh mục
                        <?php if ($search): ?>
                        - <a href="categories.php" class="text-decoration-none">Xem tất cả</a>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportCategories()">
                            <i class="fas fa-download me-2"></i>Xuất Excel
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="importCategories()">
                            <i class="fas fa-upload me-2"></i>Nhập Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-tags fa-4x text-muted mb-4"></i>
            <h4 class="text-muted">Không tìm thấy danh mục nào</h4>
            <p class="text-muted mb-4">
                <?php if ($search): ?>
                Thử thay đổi từ khóa tìm kiếm hoặc <a href="categories.php" class="text-decoration-none">xem tất cả danh mục</a>
                <?php else: ?>
                Hệ thống chưa có danh mục nào. Hãy tạo danh mục đầu tiên!
                <?php endif; ?>
            </p>
            <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Thêm danh mục mới
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus text-primary me-2"></i>
                    Thêm danh mục mới
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required
                               placeholder="Nhập tên danh mục...">
                        <div class="form-text">Tên danh mục phải là duy nhất</div>
                    </div>
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"
                                  placeholder="Nhập mô tả cho danh mục..."></textarea>
                        <div class="form-text">Mô tả ngắn gọn về danh mục này</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Hủy bỏ
                    </button>
                    <button type="submit" name="add_category" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Thêm danh mục
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit text-warning me-2"></i>
                    Chỉnh sửa danh mục
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="category_id" id="editCategoryId">
                    <div class="mb-3">
                        <label for="editCategoryName" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editCategoryName" name="name" required>
                        <div class="form-text">Tên danh mục phải là duy nhất</div>
                    </div>
                    <div class="mb-3">
                        <label for="editCategoryDescription" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="editCategoryDescription" name="description" rows="3"></textarea>
                        <div class="form-text">Mô tả ngắn gọn về danh mục này</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Hủy bỏ
                    </button>
                    <button type="submit" name="edit_category" class="btn btn-warning">
                        <i class="fas fa-save me-2"></i>Cập nhật
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
                    Xác nhận xóa danh mục
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa danh mục:</p>
                <p class="fw-bold text-danger" id="categoryName"></p>
                <div class="alert alert-warning" id="courseWarning" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Cảnh báo:</strong> Danh mục này đang có <span id="courseCount"></span> khóa học!
                    Bạn cần xóa hoặc chuyển các khóa học trước khi xóa danh mục.
                </div>
                <div class="alert alert-info" id="deleteInfo" style="display: none;">
                    <i class="fas fa-info-circle me-2"></i>
                    Danh mục này không có khóa học nào và có thể xóa an toàn.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Hủy bỏ
                </button>
                <form method="POST" style="display: inline;" id="deleteForm">
                    <input type="hidden" name="delete_category" value="1">
                    <input type="hidden" name="category_id" id="deleteCategoryId">
                    <button type="submit" class="btn btn-danger" id="deleteButton">
                        <i class="fas fa-trash me-2"></i>Xóa danh mục
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

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
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

#categoriesTable tbody tr:hover {
    background-color: #f8f9fc;
}

.table-responsive {
    border-radius: 0.5rem;
}

.modal-header {
    border-bottom: 1px solid #e3e6f0;
}

.modal-footer {
    border-top: 1px solid #e3e6f0;
}
</style>

<!-- Custom JavaScript -->
<script>
function editCategory(category) {
    document.getElementById('editCategoryId').value = category.id;
    document.getElementById('editCategoryName').value = category.name;
    document.getElementById('editCategoryDescription').value = category.description || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    modal.show();
}

function confirmDelete(categoryId, categoryName, courseCount) {
    document.getElementById('deleteCategoryId').value = categoryId;
    document.getElementById('categoryName').textContent = categoryName;
    
    const courseWarning = document.getElementById('courseWarning');
    const deleteInfo = document.getElementById('deleteInfo');
    const deleteButton = document.getElementById('deleteButton');
    
    if (courseCount > 0) {
        courseWarning.style.display = 'block';
        deleteInfo.style.display = 'none';
        document.getElementById('courseCount').textContent = courseCount;
        deleteButton.disabled = true;
        deleteButton.innerHTML = '<i class="fas fa-ban me-2"></i>Không thể xóa';
        deleteButton.className = 'btn btn-secondary';
    } else {
        courseWarning.style.display = 'none';
        deleteInfo.style.display = 'block';
        deleteButton.disabled = false;
        deleteButton.innerHTML = '<i class="fas fa-trash me-2"></i>Xóa danh mục';
        deleteButton.className = 'btn btn-danger';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function exportCategories() {
    alert('Tính năng xuất Excel đang được phát triển!');
}

function importCategories() {
    alert('Tính năng nhập Excel đang được phát triển!');
}

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

// Reset modals when hidden
document.getElementById('addCategoryModal').addEventListener('hidden.bs.modal', function() {
    this.querySelector('form').reset();
});

document.getElementById('editCategoryModal').addEventListener('hidden.bs.modal', function() {
    this.querySelector('form').reset();
});
</script>

<?php include 'includes/admin-footer.php'; ?>