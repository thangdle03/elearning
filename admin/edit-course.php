<?php

require_once '../includes/config.php';

// Đảm bảo PDO ném ngoại lệ khi có lỗi
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Chỉnh sửa khóa học';
$current_page = 'courses';

// Initialize variables
$message = '';
$error = '';
$debug = isset($_GET['debug']);
$course_id = (int)($_GET['id'] ?? 0);

// Check if course exists
if ($course_id <= 0) {
    $_SESSION['error_message'] = 'ID khóa học không hợp lệ!';
    header('Location: courses.php');
    exit();
}

// Get course data
try {
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name as category_name 
        FROM courses c 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        $_SESSION['error_message'] = 'Không tìm thấy khóa học!';
        header('Location: courses.php');
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Lỗi truy vấn database: ' . $e->getMessage();
    header('Location: courses.php');
    exit();
}

// Update page title
$page_title = 'Chỉnh sửa: ' . $course['title'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($debug) {
        error_log("=== EDIT COURSE FORM SUBMISSION ===");
        error_log("Course ID: $course_id");
        error_log("POST data: " . print_r($_POST, true));
    }
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $thumbnail = trim($_POST['thumbnail'] ?? '');
    $price = isset($_POST['price']) && $_POST['price'] !== '' ? (int)$_POST['price'] : 0;
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : 0;
    $status = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';

    if ($debug) {
        error_log("=== PROCESSED UPDATE DATA ===");
        error_log("Title: '$title' (length: " . strlen($title) . ")");
        error_log("Description: '$description' (length: " . strlen($description) . ")");
        error_log("Category ID: $category_id");
        error_log("Price: $price");
        error_log("Status: '$status'");
        error_log("Thumbnail: '$thumbnail'");
    }

    // Validation
    $errors = [];

    if (empty($title)) {
        $errors[] = 'Tiêu đề khóa học không được để trống!';
    } elseif (mb_strlen($title) < 5) {
        $errors[] = 'Tiêu đề khóa học phải có ít nhất 5 ký tự!';
    } elseif (mb_strlen($title) > 255) {
        $errors[] = 'Tiêu đề khóa học không được quá 255 ký tự!';
    }

    if (empty($description)) {
        $errors[] = 'Mô tả khóa học không được để trống!';
    } elseif (mb_strlen($description) < 20) {
        $errors[] = 'Mô tả khóa học phải có ít nhất 20 ký tự!';
    }

    if ($category_id <= 0) {
        $errors[] = 'Vui lòng chọn danh mục khóa học!';
    } else {
        // Check if category exists and is active
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = ? AND status = 'active'");
            $stmt->execute([$category_id]);
            $category_check = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$category_check) {
                $errors[] = 'Danh mục được chọn không tồn tại hoặc không hoạt động!';
                if ($debug) {
                    error_log("Category check failed for ID: $category_id");
                }
            } else if ($debug) {
                error_log("Category check passed: " . $category_check['name']);
            }
        } catch (Exception $e) {
            $errors[] = 'Lỗi kiểm tra danh mục: ' . $e->getMessage();
            if ($debug) error_log("Category check error: " . $e->getMessage());
        }
    }

    if ($price < 0) {
        $errors[] = 'Giá khóa học không được âm!';
    }

    // Validate thumbnail URL if provided
    if (!empty($thumbnail)) {
        if (!filter_var($thumbnail, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL thumbnail không hợp lệ!';
        } elseif (mb_strlen($thumbnail) > 255) {
            $errors[] = 'URL thumbnail không được quá 255 ký tự!';
        }
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = 'Trạng thái không hợp lệ!';
        $status = 'active';
    }

    // Check if title already exists (exclude current course)
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE title = ? AND id != ?");
            $stmt->execute([$title, $course_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Tiêu đề khóa học đã tồn tại cho khóa học khác!';
                if ($debug) {
                    error_log("Title already exists for another course: $title");
                }
            } else if ($debug) {
                error_log("Title uniqueness check passed");
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi kiểm tra trùng lặp: ' . $e->getMessage();
            if ($debug) error_log("Title check error: " . $e->getMessage());
        }
    }

    if ($debug) {
        error_log("=== VALIDATION RESULTS ===");
        error_log("Errors: " . print_r($errors, true));
    }

    // Update course if no errors
    if (empty($errors)) {
        try {
            if ($debug) {
                error_log("=== STARTING DATABASE UPDATE ===");
            }

            // Start transaction
            $pdo->beginTransaction();

            // Prepare update SQL
            $sql = "UPDATE courses SET 
                        title = ?, 
                        description = ?, 
                        thumbnail = ?, 
                        price = ?, 
                        category_id = ?, 
                        status = ?, 
                        updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);

            // Parameters array
            $params = [
                $title,
                $description,
                !empty($thumbnail) ? $thumbnail : null,
                $price,
                $category_id,
                $status,
                $course_id
            ];

            if ($debug) {
                error_log("Update SQL: $sql");
                error_log("Update params: " . print_r($params, true));
            }

            // Execute the update
            $result = $stmt->execute($params);

            if ($debug) {
                error_log("Update result: " . ($result ? 'TRUE' : 'FALSE'));
                error_log("Rows affected: " . $stmt->rowCount());
            }

            if ($result) {
                // Commit transaction
                $pdo->commit();

                // Update course data for display
                $course['title'] = $title;
                $course['description'] = $description;
                $course['thumbnail'] = $thumbnail;
                $course['price'] = $price;
                $course['category_id'] = $category_id;
                $course['status'] = $status;

                if ($debug) {
                    error_log("SUCCESS: Course updated successfully");
                    $message = "✅ <strong>CẬP NHẬT THÀNH CÔNG!</strong><br>Khóa học '<strong>" . htmlspecialchars($title) . "</strong>' đã được cập nhật thành công!";
                } else {
                    // Redirect in normal mode
                    $_SESSION['success_message'] = "Khóa học '" . htmlspecialchars($title) . "' đã được cập nhật thành công!";
                    header('Location: courses.php');
                    exit();
                }
            } else {
                // Rollback on failure
                $pdo->rollback();
                $errors[] = 'Không thể cập nhật khóa học!';
                if ($debug) {
                    error_log("Update failed - no rows affected");
                }
            }
        } catch (PDOException $e) {
            // Rollback on exception
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }

            $errors[] = 'Lỗi PDO: ' . $e->getMessage();
            if ($debug) {
                error_log("PDO Exception during update: " . $e->getMessage());
            }
        } catch (Exception $e) {
            // Rollback on any exception
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }

            $errors[] = 'Lỗi hệ thống: ' . $e->getMessage();
            if ($debug) {
                error_log("General Exception during update: " . $e->getMessage());
            }
        }
    }

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
        if ($debug) {
            error_log("=== FINAL UPDATE ERRORS ===");
            error_log(implode(' | ', $errors));
        }
    }
}

// Handle success message from redirect
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get categories for dropdown
try {
    $categories = $pdo->query("
        SELECT id, name 
        FROM categories 
        WHERE status = 'active' 
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if ($debug) {
        error_log("Categories loaded for edit: " . count($categories));
    }
} catch (PDOException $e) {
    $categories = [];
    if (!$error) {
        $error = 'Không thể tải danh sách danh mục: ' . $e->getMessage();
    }
}

// Get course statistics
try {
    $course_stats = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM lessons WHERE course_id = ?) as lesson_count,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = ?) as enrollment_count,
            (SELECT AVG(rating) FROM reviews WHERE course_id = ?) as avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE course_id = ?) as review_count
    ");
    $course_stats->execute([$course_id, $course_id, $course_id, $course_id]);
    $stats = $course_stats->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['lesson_count' => 0, 'enrollment_count' => 0, 'avg_rating' => 0, 'review_count' => 0];
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Debug Info -->
<?php if ($debug): ?>
    <div class="alert alert-info">
        <h5><i class="fas fa-bug"></i> Debug Mode - Chỉnh sửa khóa học</h5>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Request Method:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
                <p><strong>Course ID:</strong> <?php echo $course_id; ?></p>
                <p><strong>Form Submitted:</strong> <?php echo isset($_POST['update_course']) ? 'YES ✅' : 'NO ❌'; ?></p>
                <p><strong>Categories Count:</strong> <?php echo count($categories); ?></p>

                <?php if ($_POST): ?>
                    <p><strong>POST Data:</strong></p>
                    <pre class="small bg-light p-2 rounded"><?php
                                                            $debug_post = $_POST;
                                                            if (isset($debug_post['description']) && strlen($debug_post['description']) > 100) {
                                                                $debug_post['description'] = substr($debug_post['description'], 0, 100) . '... (length: ' . strlen($_POST['description']) . ')';
                                                            }
                                                            print_r($debug_post);
                                                            ?></pre>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <p><strong>Course Info:</strong></p>
                <ul class="small">
                    <li><strong>Title:</strong> <?php echo htmlspecialchars($course['title']); ?></li>
                    <li><strong>Status:</strong> <?php echo $course['status']; ?></li>
                    <li><strong>Category:</strong> <?php echo $course['category_name'] ?: 'Không có'; ?></li>
                    <li><strong>Price:</strong> <?php echo number_format($course['price']) . ' VNĐ'; ?></li>
                    <li><strong>Created:</strong> <?php echo date('d/m/Y H:i', strtotime($course['created_at'])); ?></li>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit me-2"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">
            ID: #<?php echo $course_id; ?> •
            Tạo lúc: <?php echo date('d/m/Y H:i', strtotime($course['created_at'])); ?>
            <?php if ($course['updated_at']): ?>
                • Cập nhật: <?php echo date('d/m/Y H:i', strtotime($course['updated_at'])); ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="btn-group" role="group">
        <a href="courses.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Quay lại
        </a>
        <a href="../course-detail.php?id=<?php echo $course_id; ?>" target="_blank" class="btn btn-outline-primary">
            <i class="fas fa-eye me-2"></i>Xem trước
        </a>
        <a href="lessons.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-info">
            <i class="fas fa-list me-2"></i>Quản lý bài học
        </a>
       
    </div>
</div>

<!-- Success Message -->
<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Error Message -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Main Content -->
<div class="row">
    <!-- Edit Form -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-edit me-2"></i>Chỉnh sửa thông tin khóa học
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="edit-course.php?id=<?php echo $course_id; ?><?php echo $debug ? '&debug=1' : ''; ?>" id="courseForm">
                    <!-- Basic Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary">
                                <i class="fas fa-info-circle me-2"></i>Thông tin cơ bản
                            </h5>
                            <hr>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">
                                Tiêu đề khóa học <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                class="form-control"
                                id="title"
                                name="title"
                                value="<?php echo htmlspecialchars($course['title']); ?>"
                                placeholder="Ví dụ: Khóa học PHP MySQL cơ bản"
                                required
                                minlength="5"
                                maxlength="255">
                            <div class="form-text">Tiêu đề ngắn gọn và súc tích (5-255 ký tự)</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">
                                Danh mục <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"
                                        <?php echo ($course['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($categories)): ?>
                                <div class="form-text text-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Chưa có danh mục nào. <a href="categories.php">Tạo danh mục mới</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Trạng thái</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo ($course['status'] === 'active') ? 'selected' : ''; ?>>
                                    🟢 Hoạt động
                                </option>
                                <option value="inactive" <?php echo ($course['status'] === 'inactive') ? 'selected' : ''; ?>>
                                    🔴 Không hoạt động
                                </option>
                            </select>
                            <div class="form-text">Chỉ khóa học "Hoạt động" mới hiển thị công khai</div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">
                                Mô tả khóa học <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control"
                                id="description"
                                name="description"
                                rows="6"
                                placeholder="Mô tả chi tiết về khóa học, nội dung sẽ học được..."
                                required
                                minlength="20"><?php echo htmlspecialchars($course['description']); ?></textarea>
                            <div class="form-text">
                                Mô tả chi tiết giúp học viên hiểu rõ về khóa học (tối thiểu 20 ký tự)
                                <span class="float-end" id="descriptionCount">0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Course Details -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary">
                                <i class="fas fa-cogs me-2"></i>Chi tiết khóa học
                            </h5>
                            <hr>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Giá khóa học (VNĐ)</label>
                            <div class="input-group">
                                <input type="number"
                                    class="form-control"
                                    id="price"
                                    name="price"
                                    value="<?php echo $course['price']; ?>"
                                    min="0"
                                    step="1000"
                                    placeholder="0">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                            <div class="form-text">Nhập 0 nếu khóa học miễn phí</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="thumbnail" class="form-label">Link ảnh thumbnail</label>
                            <input type="url"
                                class="form-control"
                                id="thumbnail"
                                name="thumbnail"
                                value="<?php echo htmlspecialchars($course['thumbnail'] ?? ''); ?>"
                                placeholder="https://img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg">
                            <div class="form-text">
                                URL ảnh đại diện cho khóa học
                            </div>
                        </div>
                    </div>

                    <!-- Thumbnail Preview -->
                    <div class="row mb-4" id="thumbnail-preview-section" style="<?php echo !empty($course['thumbnail']) ? 'display: block;' : 'display: none;'; ?>">
                        <div class="col-12">
                            <h5 class="text-primary">
                                <i class="fas fa-image me-2"></i>Xem trước thumbnail
                            </h5>
                            <hr>
                        </div>
                        <div class="col-12 text-center">
                            <img id="thumbnail-preview"
                                src="<?php echo htmlspecialchars($course['thumbnail'] ?? ''); ?>"
                                alt="Thumbnail Preview"
                                class="img-fluid rounded shadow"
                                style="max-height: 300px;">
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Hoàn tác
                                </button>
                                <div>
                                    <button type="submit" name="update_course" value="1" class="btn btn-success me-2" id="submitBtn">
                                        <i class="fas fa-save me-2"></i>Cập nhật khóa học
                                    </button>
                                    <a href="courses.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Hủy
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Course Statistics -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info">
                    <i class="fas fa-chart-bar me-2"></i>Thống kê khóa học
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 text-center">
                        <div class="text-primary">
                            <i class="fas fa-list fa-2x mb-2"></i>
                            <div class="h4 mb-0"><?php echo $stats['lesson_count']; ?></div>
                            <small>Bài học</small>
                        </div>
                    </div>
                    <div class="col-6 text-center">
                        <div class="text-success">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <div class="h4 mb-0"><?php echo $stats['enrollment_count']; ?></div>
                            <small>Học viên</small>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6 text-center">
                        <div class="text-warning">
                            <i class="fas fa-star fa-2x mb-2"></i>
                            <div class="h4 mb-0"><?php echo number_format($stats['avg_rating'], 1); ?></div>
                            <small>Đánh giá TB</small>
                        </div>
                    </div>
                    <div class="col-6 text-center">
                        <div class="text-info">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <div class="h4 mb-0"><?php echo $stats['review_count']; ?></div>
                            <small>Bình luận</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-tools me-2"></i>Thao tác nhanh
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="lessons.php?course_id=<?php echo $course_id; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-list me-2"></i>Quản lý bài học
                    </a>
                    <a href="../course-detail.php?id=<?php echo $course_id; ?>" target="_blank" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye me-2"></i>Xem khóa học
                    </a>
                    <button type="button" class="btn btn-warning btn-sm" onclick="duplicateCourse()">
                        <i class="fas fa-copy me-2"></i>Sao chép khóa học
                    </button>
                    <hr class="my-2">
                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteCourse()">
                        <i class="fas fa-trash me-2"></i>Xóa khóa học
                    </button>
                </div>
            </div>
        </div>

        <!-- YouTube Helper -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-danger text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fab fa-youtube me-2"></i>YouTube Thumbnail
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <input type="text"
                        class="form-control form-control-sm"
                        id="youtube-url"
                        placeholder="YouTube URL hoặc Video ID">
                </div>
                <button type="button" class="btn btn-sm btn-danger w-100" onclick="generateYoutubeThumbnail()">
                    <i class="fas fa-magic me-2"></i>Tạo Thumbnail
                </button>
                <div class="form-text mt-2">
                    <small>Dán link YouTube để lấy thumbnail tự động</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
    .form-label {
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }

    .card {
        border: none;
        transition: all 0.3s;
    }

    .card:hover {
        transform: translateY(-2px);
    }

    #thumbnail-preview {
        border: 3px solid #e3e6f0;
        max-width: 100%;
    }

    .is-invalid {
        border-color: #e74a3b;
    }

    .is-valid {
        border-color: #1cc88a;
    }
</style>

<!-- JavaScript -->
<script>
    // Store original values for reset
    const originalValues = {
        title: '<?php echo addslashes($course['title']); ?>',
        description: `<?php echo addslashes($course['description']); ?>`,
        thumbnail: '<?php echo addslashes($course['thumbnail'] ?? ''); ?>',
        price: '<?php echo $course['price']; ?>',
        category_id: '<?php echo $course['category_id']; ?>',
        status: '<?php echo $course['status']; ?>'
    };

    // Character counter for description
    document.getElementById('description').addEventListener('input', function() {
        document.getElementById('descriptionCount').textContent = this.value.length + ' ký tự';
    });

    // Initialize description counter
    document.addEventListener('DOMContentLoaded', function() {
        const descField = document.getElementById('description');
        document.getElementById('descriptionCount').textContent = descField.value.length + ' ký tự';

        // Initialize thumbnail preview if URL exists
        const thumbnailField = document.getElementById('thumbnail');
        if (thumbnailField.value) {
            thumbnailField.dispatchEvent(new Event('input'));
        }
    });

    // Thumbnail preview
    document.getElementById('thumbnail').addEventListener('input', function(e) {
        const url = e.target.value.trim();
        const previewSection = document.getElementById('thumbnail-preview-section');
        const previewImg = document.getElementById('thumbnail-preview');

        if (url) {
            previewImg.src = url;
            previewImg.onload = function() {
                previewSection.style.display = 'block';
            };
            previewImg.onerror = function() {
                previewSection.style.display = 'none';
            };
        } else {
            previewSection.style.display = 'none';
        }
    });

    // Generate YouTube thumbnail
    function generateYoutubeThumbnail() {
        const input = document.getElementById('youtube-url').value.trim();
        const thumbnailInput = document.getElementById('thumbnail');

        if (!input) {
            alert('Vui lòng nhập YouTube URL hoặc Video ID!');
            return;
        }

        let videoId = input;

        // Extract video ID if full URL
        if (input.includes('youtube.com') || input.includes('youtu.be')) {
            const match = input.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
            if (match) {
                videoId = match[1];
            }
        }

        // Set thumbnail URL
        if (videoId.length === 11) {
            const thumbnailUrl = `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`;
            thumbnailInput.value = thumbnailUrl;
            thumbnailInput.dispatchEvent(new Event('input'));
            document.getElementById('youtube-url').value = '';

            showAlert('success', 'Đã cập nhật thumbnail thành công!');
        } else {
            alert('Video ID không hợp lệ!');
        }
    }

    // Reset form to original values
    function resetForm() {
        if (confirm('Bạn có chắc muốn hoàn tác tất cả thay đổi về giá trị ban đầu?')) {
            document.getElementById('title').value = originalValues.title;
            document.getElementById('description').value = originalValues.description;
            document.getElementById('thumbnail').value = originalValues.thumbnail;
            document.getElementById('price').value = originalValues.price;
            document.getElementById('category_id').value = originalValues.category_id;
            document.getElementById('status').value = originalValues.status;

            // Update counters and previews
            document.getElementById('description').dispatchEvent(new Event('input'));
            document.getElementById('thumbnail').dispatchEvent(new Event('input'));

            showAlert('info', 'Đã hoàn tác về giá trị ban đầu!');
        }
    }

    // Delete course
    function deleteCourse() {
        if (confirm('Bạn có chắc chắn muốn xóa khóa học này?\n\nHành động này không thể hoàn tác!')) {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'courses.php';

            const courseIdInput = document.createElement('input');
            courseIdInput.type = 'hidden';
            courseIdInput.name = 'course_id';
            courseIdInput.value = '<?php echo $course_id; ?>';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'delete_course';
            actionInput.value = '1';

            form.appendChild(courseIdInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Duplicate course
    function duplicateCourse() {
        if (confirm('Tạo bản sao của khóa học này?')) {
            showAlert('info', 'Chức năng sao chép đang được phát triển!');
        }
    }

    // Show alert helper
    function showAlert(type, message) {
        const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

        const container = document.querySelector('.col-lg-8');
        container.insertAdjacentHTML('afterbegin', alertHtml);

        // Auto remove after 3 seconds
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) alert.remove();
        }, 3000);
    }

    // Enhanced form validation
    document.getElementById('courseForm').addEventListener('submit', function(e) {
        console.log('🚀 Form update submission started');

        const inputs = this.querySelectorAll('input[required], select[required], textarea[required]');
        let valid = true;
        let emptyFields = [];

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                emptyFields.push(input.name || input.id);
                valid = false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            }
        });

        if (!valid) {
            e.preventDefault();
            console.log('❌ Validation failed for fields:', emptyFields);
            alert('Vui lòng điền đầy đủ các trường bắt buộc: ' + emptyFields.join(', '));
            return false;
        }

        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang cập nhật...';
        submitBtn.disabled = true;

        // Re-enable after timeout (fallback)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 10000);

        console.log('✅ Form validation passed, updating...');
        return true;
    });

    // Remove validation classes on input
    document.querySelectorAll('.form-control, .form-select').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid', 'is-valid');
        });
    });

    console.log('✅ Edit Course page loaded - Course ID: <?php echo $course_id; ?>');
</script>

<?php include 'includes/admin-footer.php'; ?>