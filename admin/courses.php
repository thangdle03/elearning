
<?php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Quản lý khóa học';
$current_page = 'courses';

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['delete_course'])) {
        $course_id = (int)$_POST['course_id'];
        
        try {
            // Check if course has enrollments
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $enrollment_count = $stmt->fetchColumn();
            
            if ($enrollment_count > 0) {
                $error = "Không thể xóa khóa học này vì đã có {$enrollment_count} học viên đăng ký!";
            } else {
                // Delete course and related data
                $pdo->beginTransaction();
                
                // Delete lessons first
                $stmt = $pdo->prepare("DELETE FROM lessons WHERE course_id = ?");
                $stmt->execute([$course_id]);
                
                // Delete course
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$course_id]);
                
                $pdo->commit();
                $message = 'Đã xóa khóa học thành công!';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Có lỗi xảy ra khi xóa khóa học: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $course_id = (int)$_POST['course_id'];
        $new_status = $_POST['new_status'] === 'active' ? 'active' : 'inactive';
        
        try {
            $stmt = $pdo->prepare("UPDATE courses SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $course_id]);
            $message = 'Đã cập nhật trạng thái khóa học!';
        } catch (Exception $e) {
            $error = 'Có lỗi xảy ra khi cập nhật trạng thái!';
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

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
$order_by = match($sort) {
    'oldest' => 'ORDER BY c.created_at ASC',
    'title' => 'ORDER BY c.title ASC',
    'price_high' => 'ORDER BY c.price DESC',
    'price_low' => 'ORDER BY c.price ASC',
    'popular' => 'ORDER BY enrollment_count DESC',
    default => 'ORDER BY c.created_at DESC'
};

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_courses = $count_stmt->fetchColumn();

// Get courses
$sql = "
    SELECT c.*, 
           cat.name as category_name,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    $where_clause 
    $order_by
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Calculate pagination
$total_pages = ceil($total_courses / $per_page);

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_courses,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_courses,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_courses,
        COUNT(CASE WHEN price = 0 THEN 1 END) as free_courses,
        AVG(price) as avg_price,
        (SELECT COUNT(*) FROM enrollments) as total_enrollments
    FROM courses
")->fetch();
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-graduation-cap me-2"></i>Quản lý khóa học
        </h1>
        <div class="d-flex gap-2">
            <a href="add-course.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-2"></i>Thêm khóa học mới
            </a>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-2"></i>In báo cáo
            </button>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
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

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Đang hoạt động
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['active_courses']); ?>
                            </div>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Tổng đăng ký
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_enrollments']); ?>
                            </div>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Giá trung bình
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatPrice($stats['avg_price']); ?>
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
            <i class="fas fa-filter me-2"></i>Bộ lọc và tìm kiếm
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tìm kiếm</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Tên khóa học..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
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
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>
                            Hoạt động
                        </option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>
                            Ngừng hoạt động
                        </option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Sắp xếp</label>
                    <select name="sort" class="form-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>
                            Mới nhất
                        </option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>
                            Cũ nhất
                        </option>
                        <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>
                            Tên A-Z
                        </option>
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>
                            Phổ biến nhất
                        </option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>
                            Giá cao nhất
                        </option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>
                            Giá thấp nhất
                        </option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>Lọc
                    </button>
                    <a href="courses.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                Danh sách khóa học (<?php echo number_format($total_courses); ?> khóa học)
            </h6>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                        data-bs-toggle="dropdown">
                    <i class="fas fa-cog me-1"></i>Thao tác hàng loạt
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="bulkAction('activate')">
                        <i class="fas fa-check-circle me-2"></i>Kích hoạt
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="bulkAction('deactivate')">
                        <i class="fas fa-times-circle me-2"></i>Vô hiệu hóa
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="bulkAction('delete')">
                        <i class="fas fa-trash me-2"></i>Xóa
                    </a></li>
                </ul>
            </div>
        </div>
        
        <div class="card-body">
            <?php if ($courses): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="coursesTable">
                    <thead>
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th width="80">Hình</th>
                            <th>Thông tin khóa học</th>
                            <th width="120">Danh mục</th>
                            <th width="100">Giá</th>
                            <th width="80">Bài học</th>
                            <th width="80">Học viên</th>
                            <th width="100">Trạng thái</th>
                            <th width="150">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input course-checkbox" 
                                       value="<?php echo $course['id']; ?>">
                            </td>
                            <td>
                                <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/80x60?text=No+Image'; ?>" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                                     class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
                            </td>
                            <td>
                                <div>
                                    <h6 class="mb-1">
                                        <a href="../course-detail.php?id=<?php echo $course['id']; ?>" 
                                           target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($course['title']); ?>
                                            <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                                        </a>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($course['description'], 0, 80)); ?>
                                        <?php echo strlen($course['description']) > 80 ? '...' : ''; ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($course['created_at'])); ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo $course['category_name'] ?: 'Chưa phân loại'; ?>
                                </span>
                            </td>
                            <td>
                                <strong class="<?php echo $course['price'] > 0 ? 'text-success' : 'text-primary'; ?>">
                                    <?php echo formatPrice($course['price']); ?>
                                </strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info">
                                    <?php echo $course['lesson_count']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-warning">
                                    <?php echo $course['enrollment_count']; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <input type="hidden" name="new_status" 
                                           value="<?php echo $course['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                    <button type="submit" name="toggle_status" 
                                            class="btn btn-sm <?php echo $course['status'] === 'active' ? 'btn-success' : 'btn-secondary'; ?>"
                                            title="Click để thay đổi trạng thái">
                                        <i class="fas fa-<?php echo $course['status'] === 'active' ? 'check' : 'times'; ?>"></i>
                                        <?php echo ucfirst($course['status']); ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="edit-course.php?id=<?php echo $course['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm" title="Chỉnh sửa">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="lessons.php?course_id=<?php echo $course['id']; ?>" 
                                       class="btn btn-outline-info btn-sm" title="Quản lý bài học">
                                        <i class="fas fa-list"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo addslashes($course['title']); ?>')"
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

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Courses pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i> Trước
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Sau <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-5">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <h5>Không tìm thấy khóa học nào</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($category_filter) || !empty($status_filter)): ?>
                    Không có khóa học nào phù hợp với bộ lọc hiện tại.
                    <?php else: ?>
                    Chưa có khóa học nào trong hệ thống.
                    <?php endif; ?>
                </p>
                <a href="add-course.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Thêm khóa học đầu tiên
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
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Cảnh báo:</strong> Hành động này sẽ xóa vĩnh viễn khóa học và tất cả bài học liên quan.
                    Không thể hoàn tác!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" id="deleteForm" class="d-inline">
                    <input type="hidden" name="course_id" id="courseIdInput">
                    <button type="submit" name="delete_course" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa khóa học
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

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #5a5c69;
    background-color: #f8f9fc;
}

.btn-group .btn {
    border-radius: 0.25rem;
    margin-right: 2px;
}

.course-checkbox:checked {
    background-color: #4e73df;
    border-color: #4e73df;
}

@media print {
    .btn, .card-header .dropdown, .pagination {
        display: none !important;
    }
}
</style>

<!-- JavaScript -->
<script>
// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.course-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Delete course function
function deleteCourse(courseId, courseTitle) {
    document.getElementById('courseTitle').textContent = courseTitle;
    document.getElementById('courseIdInput').value = courseId;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Bulk actions
function bulkAction(action) {
    const selectedCourses = document.querySelectorAll('.course-checkbox:checked');
    
    if (selectedCourses.length === 0) {
        alert('Vui lòng chọn ít nhất một khóa học!');
        return;
    }
    
    const courseIds = Array.from(selectedCourses).map(cb => cb.value);
    
    if (action === 'delete') {
        if (confirm(`Bạn có chắc chắn muốn xóa ${courseIds.length} khóa học đã chọn?`)) {
            // Implement bulk delete
            console.log('Bulk delete:', courseIds);
        }
    } else if (action === 'activate') {
        if (confirm(`Kích hoạt ${courseIds.length} khóa học đã chọn?`)) {
            // Implement bulk activate
            console.log('Bulk activate:', courseIds);
        }
    } else if (action === 'deactivate') {
        if (confirm(`Vô hiệu hóa ${courseIds.length} khóa học đã chọn?`)) {
            // Implement bulk deactivate
            console.log('Bulk deactivate:', courseIds);
        }
    }
}

// Auto-submit form when filter changes
document.querySelectorAll('select[name="category"], select[name="status"], select[name="sort"]').forEach(select => {
    select.addEventListener('change', function() {
        this.form.submit();
    });
});

// Table row hover effect
document.querySelectorAll('#coursesTable tbody tr').forEach(row => {
    row.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#f8f9fc';
    });
    
    row.addEventListener('mouseleave', function() {
        this.style.backgroundColor = '';
    });
});

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include 'includes/admin-footer.php'; ?>