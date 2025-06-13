<?php

require_once '../includes/config.php';

// Đảm bảo PDO ném ngoại lệ khi có lỗi
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Thêm khóa học mới';
$current_page = 'courses';

// Initialize variables
$message = '';
$error = '';
$debug = isset($_GET['debug']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if ($debug) {
        error_log("=== FORM SUBMISSION START ===");
        error_log("POST data: " . print_r($_POST, true));
    }
    
    // Get form data with better validation
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $thumbnail = trim($_POST['thumbnail'] ?? '');
    $price = isset($_POST['price']) && $_POST['price'] !== '' ? (int)$_POST['price'] : 0;
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : 0;
    $status = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; // Ensure enum value
    
    if ($debug) {
        error_log("=== PROCESSED DATA ===");
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
        $status = 'active'; // Reset to default
    }
    
    if ($debug) {
        error_log("=== VALIDATION RESULTS ===");
        error_log("Errors: " . print_r($errors, true));
    }
    
    // Check if title already exists (only if no validation errors)
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE title = ?");
            $stmt->execute([$title]);
            if ($stmt->fetch()) {
                $errors[] = 'Tiêu đề khóa học đã tồn tại!';
                if ($debug) {
                    error_log("Title already exists: $title");
                }
            } else if ($debug) {
                error_log("Title uniqueness check passed");
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi kiểm tra trùng lặp: ' . $e->getMessage();
            if ($debug) error_log("Title check error: " . $e->getMessage());
        }
    }
    
    // Insert course if no errors
    if (empty($errors)) {
        try {
            if ($debug) {
                error_log("=== STARTING DATABASE INSERT ===");
                
                // Test database connection first
                $test = $pdo->query("SELECT 1 as test")->fetch();
                error_log("Database test: " . ($test ? 'OK' : 'FAILED'));
            }
            
            // Start transaction for safety
            $pdo->beginTransaction();
            
            // Prepare SQL - use simple parameter binding
            $sql = "INSERT INTO courses (title, description, thumbnail, price, category_id, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            
            $stmt = $pdo->prepare($sql);
            
            // Parameters array - handle NULL for optional thumbnail
            $params = [
                $title,
                $description, 
                !empty($thumbnail) ? $thumbnail : null,
                $price,
                $category_id,
                $status
            ];
            
            if ($debug) {
                error_log("SQL: $sql");
                error_log("Params: " . print_r($params, true));
                error_log("Param count: " . count($params));
                error_log("Placeholder count: " . substr_count($sql, '?'));
            }
            
            // Execute the statement
            $result = $stmt->execute($params);
            
            if ($debug) {
                error_log("Execute result: " . ($result ? 'TRUE' : 'FALSE'));
                error_log("Rows affected: " . $stmt->rowCount());
                
                // Check for errors
                $pdo_error = $pdo->errorInfo();
                $stmt_error = $stmt->errorInfo();
                error_log("PDO error info: " . print_r($pdo_error, true));
                error_log("Statement error info: " . print_r($stmt_error, true));
            }
            
            if ($result && $stmt->rowCount() > 0) {
                $course_id = $pdo->lastInsertId();
                
                // Commit transaction
                $pdo->commit();
                
                if ($debug) {
                    error_log("SUCCESS: Course inserted with ID: $course_id");
                    // Show success message on same page in debug mode
                    $message = "✅ <strong>THÀNH CÔNG!</strong><br>Khóa học '<strong>" . htmlspecialchars($title) . "</strong>' đã được thêm thành công!<br><strong>ID:</strong> {$course_id}";
                    
                    // Clear form data in debug mode to prevent resubmission
                    $_POST = [];
                } else {
                    // Redirect in normal mode
                    $_SESSION['success_message'] = "Khóa học '" . htmlspecialchars($title) . "' đã được thêm thành công với ID: {$course_id}!";
                    header('Location: courses.php');
                    exit();
                }
            } else {
                // Rollback on failure
                $pdo->rollback();
                
                $errors[] = 'Không thể thêm khóa học vào database!';
                $stmt_error = $stmt->errorInfo();
                if ($stmt_error[0] !== '00000') {
                    $errors[] = 'SQL Error Code: ' . $stmt_error[0];
                    $errors[] = 'SQL Error Message: ' . $stmt_error[2];
                    if ($debug) {
                        error_log("SQL Error Code: " . $stmt_error[0]);
                        error_log("SQL Error Message: " . $stmt_error[2]);
                    }
                }
                if ($debug) {
                    error_log("Insert failed - no rows affected");
                }
            }
            
        } catch (PDOException $e) {
            // Rollback on exception
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            
            $errors[] = 'Lỗi PDO: ' . $e->getMessage();
            if ($debug) {
                error_log("PDO Exception: " . $e->getMessage());
                error_log("Error code: " . $e->getCode());
                error_log("Error info: " . print_r($e->errorInfo, true));
            }
        } catch (Exception $e) {
            // Rollback on any exception
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            
            $errors[] = 'Lỗi hệ thống: ' . $e->getMessage();
            if ($debug) {
                error_log("General Exception: " . $e->getMessage());
            }
        }
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
        if ($debug) {
            error_log("=== FINAL ERRORS ===");
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
        error_log("Categories loaded: " . count($categories));
    }
} catch (PDOException $e) {
    $categories = [];
    if (!$error) { // Don't override existing error
        $error = 'Không thể tải danh sách danh mục: ' . $e->getMessage();
    }
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Debug Info -->
<?php if ($debug): ?>
    <div class="alert alert-info">
        <h5><i class="fas fa-bug"></i> Debug Mode - Chi tiết</h5>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Request Method:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
                <p><strong>Form Submitted:</strong> <?php echo isset($_POST['add_course']) ? 'YES' : 'NO'; ?></p>
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
                <p><strong>Database Tests:</strong></p>
                <?php
                try {
                    // Test connection
                    $db_test = $pdo->query("SELECT 1 as test")->fetch();
                    echo "<span class='text-success'>✅ Database connection: OK</span><br>";
                    
                    // Test courses table
                    $courses_count = $pdo->query("SELECT COUNT(*) as total FROM courses")->fetch();
                    echo "<span class='text-success'>✅ Courses table: {$courses_count['total']} records</span><br>";
                    
                    // Test categories
                    $cats_count = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE status = 'active'")->fetch();
                    echo "<span class='text-success'>✅ Active categories: {$cats_count['total']}</span><br>";
                    
                } catch (Exception $e) {
                    echo "<span class='text-danger'>❌ Database Error: " . $e->getMessage() . "</span>";
                }
                ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-plus-circle me-2"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">Tạo khóa học mới và quản lý nội dung</p>
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

<!-- Course Form -->
<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-edit me-2"></i>Thông tin khóa học
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="add-course.php<?php echo $debug ? '?debug=1' : ''; ?>" id="courseForm">
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
                                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
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
                                        <?php echo (($_POST['category_id'] ?? 0) == $category['id']) ? 'selected' : ''; ?>>
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
                                <option value="active" <?php echo (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>
                                    🟢 Hoạt động
                                </option>
                                <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>
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
                                minlength="20"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
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
                                    value="<?php echo $_POST['price'] ?? '0'; ?>"
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
                                value="<?php echo htmlspecialchars($_POST['thumbnail'] ?? ''); ?>"
                                placeholder="https://img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg">
                            <div class="form-text">
                                URL ảnh đại diện cho khóa học
                            </div>
                        </div>
                    </div>

                    <!-- Thumbnail Preview -->
                    <div class="row mb-4" id="thumbnail-preview-section" style="display: none;">
                        <div class="col-12">
                            <h5 class="text-primary">
                                <i class="fas fa-image me-2"></i>Xem trước thumbnail
                            </h5>
                            <hr>
                        </div>
                        <div class="col-12 text-center">
                            <img id="thumbnail-preview"
                                src=""
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
                                    <i class="fas fa-undo me-2"></i>Đặt lại
                                </button>
                                <div>
                                    <button type="submit" name="add_course" value="1" class="btn btn-success me-2" id="submitBtn">
                                        <i class="fas fa-save me-2"></i>Thêm khóa học
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
        <!-- Test Data -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-secondary">
                    <i class="fas fa-flask me-2"></i>Dữ liệu test
                </h6>
            </div>
            <div class="card-body">
                <button type="button" class="btn btn-sm btn-primary w-100 mb-2" onclick="fillTestData()">
                    <i class="fas fa-fill me-2"></i>Điền dữ liệu mẫu
                </button>
                <div class="form-text">
                    <small>Điền nhanh form với dữ liệu test để kiểm tra</small>
                </div>
            </div>
        </div>
        
        <!-- Quick Tips -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-lightbulb me-2"></i>Mẹo tạo khóa học
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-success"><i class="fas fa-check-circle me-2"></i>Tiêu đề hay</h6>
                    <small class="text-muted">Sử dụng tiêu đề rõ ràng, dễ hiểu</small>
                </div>

                <div class="mb-3">
                    <h6 class="text-info"><i class="fas fa-file-alt me-2"></i>Mô tả chi tiết</h6>
                    <small class="text-muted">Liệt kê những gì học viên sẽ học được</small>
                </div>

                <div class="mb-3">
                    <h6 class="text-warning"><i class="fas fa-image me-2"></i>Ảnh thumbnail</h6>
                    <small class="text-muted">Sử dụng ảnh chất lượng cao, kích thước 16:9</small>
                </div>

                <div class="mb-0">
                    <h6 class="text-danger"><i class="fas fa-dollar-sign me-2"></i>Định giá hợp lý</h6>
                    <small class="text-muted">Giá phù hợp với nội dung và thị trường</small>
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
    // Character counter for description
    document.getElementById('description').addEventListener('input', function() {
        document.getElementById('descriptionCount').textContent = this.value.length + ' ký tự';
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

            // Show success
            showAlert('success', 'Đã tạo thumbnail thành công!');
        } else {
            alert('Video ID không hợp lệ!');
        }
    }

    // Fill test data
    function fillTestData() {
        const timestamp = Date.now();
        
        document.getElementById('title').value = `Khóa học Test ${timestamp}`;
        document.getElementById('description').value = 'Đây là mô tả test cho khóa học. Nội dung này đủ dài để pass validation và test tính năng thêm khóa học mới vào hệ thống. Khóa học sẽ bao gồm các kiến thức cơ bản và nâng cao về lập trình web với PHP và MySQL.';
        document.getElementById('price').value = '150000';
        document.getElementById('thumbnail').value = 'https://img.youtube.com/vi/ImtZ5yENzgE/maxresdefault.jpg';
        
        // Select first category if available
        const categorySelect = document.getElementById('category_id');
        if (categorySelect.options.length > 1) {
            categorySelect.selectedIndex = 1; // First actual category (not placeholder)
            console.log('Selected category:', categorySelect.value, categorySelect.options[categorySelect.selectedIndex].text);
        } else {
            console.warn('No categories available!');
        }
        
        // Set status to active
        document.getElementById('status').value = 'active';
        
        // Trigger events
        document.getElementById('description').dispatchEvent(new Event('input'));
        document.getElementById('thumbnail').dispatchEvent(new Event('input'));

        showAlert('info', 'Đã điền dữ liệu test!');
        console.log('✅ Test data filled successfully');
    }

    // Reset form
    function resetForm() {
        if (confirm('Bạn có chắc muốn xóa tất cả dữ liệu đã nhập?')) {
            document.getElementById('courseForm').reset();
            document.getElementById('thumbnail-preview-section').style.display = 'none';
            document.getElementById('descriptionCount').textContent = '0 ký tự';
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
        console.log('🚀 Form submission started');
        
        const formData = new FormData(this);
        console.log('Form data:', Object.fromEntries(formData));
        
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
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
        submitBtn.disabled = true;
        
        // Re-enable after timeout (fallback)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 10000);

        console.log('✅ Form validation passed, submitting...');
        return true;
    });

    // Remove validation classes on input
    document.querySelectorAll('.form-control, .form-select').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid', 'is-valid');
        });
    });

    // Initialize description counter on page load
    document.addEventListener('DOMContentLoaded', function() {
        const descField = document.getElementById('description');
        if (descField.value) {
            document.getElementById('descriptionCount').textContent = descField.value.length + ' ký tự';
        }
        
        // Initialize thumbnail preview if URL exists
        const thumbnailField = document.getElementById('thumbnail');
        if (thumbnailField.value) {
            thumbnailField.dispatchEvent(new Event('input'));
        }
        
        console.log('✅ Add Course page loaded - Categories: <?php echo count($categories); ?>');
    });
</script>

<?php include 'includes/admin-footer.php'; ?>