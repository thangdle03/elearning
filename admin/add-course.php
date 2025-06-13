<?php

require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Thêm khóa học mới';
$current_page = 'courses';

// Handle form submission
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['add_course'])) {
        // Validate required fields - Based on actual DB structure
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $thumbnail = trim($_POST['thumbnail'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Tiêu đề khóa học không được để trống!';
        } elseif (strlen($title) < 5) {
            $errors[] = 'Tiêu đề khóa học phải có ít nhất 5 ký tự!';
        }
        
        if (empty($description)) {
            $errors[] = 'Mô tả khóa học không được để trống!';
        } elseif (strlen($description) < 20) {
            $errors[] = 'Mô tả khóa học phải có ít nhất 20 ký tự!';
        }
        
        if ($category_id <= 0) {
            $errors[] = 'Vui lòng chọn danh mục khóa học!';
        }
        
        if ($price < 0) {
            $errors[] = 'Giá khóa học không được âm!';
        }
        
        // Check if title already exists
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE title = ?");
            $stmt->execute([$title]);
            if ($stmt->fetch()) {
                $errors[] = 'Tiêu đề khóa học đã tồn tại!';
            }
        }
        
        // Insert course if no errors
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO courses (
                        title, description, thumbnail, price, category_id, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                if ($stmt->execute([
                    $title, $description, $thumbnail, $price, $category_id, $status
                ])) {
                    $course_id = $pdo->lastInsertId();
                    $message = "Khóa học '{$title}' đã được thêm thành công! <a href='courses.php' class='alert-link'>Xem danh sách</a>";
                    
                    // Reset form
                    $_POST = [];
                } else {
                    $errors[] = 'Có lỗi khi thêm khóa học vào database!';
                }
            } catch (Exception $e) {
                $errors[] = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        }
    }
}

// Get categories for dropdown
try {
    $categories = $pdo->query("
        SELECT id, name 
        FROM categories 
        WHERE status = 'active' 
        ORDER BY name ASC
    ")->fetchAll();
} catch (Exception $e) {
    $categories = [];
    $error = 'Không thể tải danh sách danh mục: ' . $e->getMessage();
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-plus-circle me-2"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">Tạo khóa học mới và quản lý nội dung</p>
    </div>
    <div class="btn-group" role="group">
        <a href="courses.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách
        </a>
        <a href="categories.php" class="btn btn-outline-primary">
            <i class="fas fa-tags me-2"></i>Quản lý danh mục
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
                <form method="POST" id="courseForm">
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
                                   placeholder="Nhập tiêu đề khóa học..."
                                   required>
                            <div class="form-text">Tiêu đề khóa học nên ngắn gọn và hấp dẫn (tối thiểu 5 ký tự)</div>
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
                                    Hoạt động
                                </option>
                                <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>
                                    Không hoạt động
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
                                      placeholder="Mô tả chi tiết về khóa học, nội dung, mục tiêu học tập..."
                                      required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-text">Mô tả chi tiết giúp học viên hiểu rõ hơn về khóa học (tối thiểu 20 ký tự)</div>
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
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? '0'); ?>"
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
                                   placeholder="https://example.com/image.jpg">
                            <div class="form-text">
                                Nhập URL của ảnh thumbnail khóa học<br>
                                Ví dụ: https://img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg
                            </div>
                        </div>
                    </div>
                    
                    <!-- Thumbnail Preview -->
                    <div class="row mb-4" id="thumbnail-preview-section" style="display: none;">
                        <div class="col-12">
                            <h5 class="text-primary">
                                <i class="fas fa-image me-2"></i>Xem trước ảnh thumbnail
                            </h5>
                            <hr>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center">
                                <img id="thumbnail-preview" 
                                     src="" 
                                     alt="Thumbnail Preview" 
                                     class="img-fluid rounded shadow" 
                                     style="max-height: 300px; max-width: 100%;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Thông tin ảnh:</h6>
                                <ul class="mb-0">
                                    <li>Kích thước khuyến nghị: <strong>800x600px</strong></li>
                                    <li>Định dạng: <strong>JPG, PNG, WEBP</strong></li>
                                    <li>Chất lượng: <strong>Cao, rõ nét</strong></li>
                                    <li>Nội dung: <strong>Liên quan đến khóa học</strong></li>
                                </ul>
                            </div>
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
                                    <button type="submit" name="add_course" class="btn btn-success me-2" id="submitBtn">
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
                    <small class="text-muted">Sử dụng tiêu đề ngắn gọn, hấp dẫn và mô tả đúng nội dung khóa học</small>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-info"><i class="fas fa-file-alt me-2"></i>Mô tả chi tiết</h6>
                    <small class="text-muted">Mô tả rõ ràng về nội dung, mục tiêu và lợi ích mà học viên sẽ đạt được</small>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-warning"><i class="fas fa-image me-2"></i>Ảnh thumbnail</h6>
                    <small class="text-muted">Sử dụng ảnh chất lượng cao từ YouTube hoặc các nguồn khác</small>
                </div>
                
                <div class="mb-0">
                    <h6 class="text-danger"><i class="fas fa-dollar-sign me-2"></i>Định giá hợp lý</h6>
                    <small class="text-muted">Nghiên cứu thị trường để đặt giá phù hợp với giá trị nội dung</small>
                </div>
            </div>
        </div>
        
        <!-- YouTube Thumbnail Helper -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-danger">
                    <i class="fab fa-youtube me-2"></i>YouTube Thumbnail Helper
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="youtube-url" class="form-label">YouTube URL:</label>
                    <input type="url" 
                           class="form-control form-control-sm" 
                           id="youtube-url" 
                           placeholder="https://www.youtube.com/watch?v=VIDEO_ID">
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="generateYoutubeThumbnail()">
                    <i class="fas fa-magic me-2"></i>Tạo Thumbnail URL
                </button>
                <div class="form-text mt-2">
                    <small>Nhập link YouTube để tự động tạo URL thumbnail</small>
                </div>
            </div>
        </div>
        
        <!-- Recent Courses -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-history me-2"></i>Khóa học gần đây
                </h6>
            </div>
            <div class="card-body">
                <?php
                try {
                    $recent_courses = $pdo->query("
                        SELECT title, status, created_at 
                        FROM courses 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ")->fetchAll();
                    
                    if ($recent_courses):
                        foreach ($recent_courses as $course):
                ?>
                <div class="d-flex align-items-center mb-2">
                    <div class="flex-shrink-0">
                        <span class="badge bg-<?php echo $course['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($course['status']); ?>
                        </span>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fw-bold"><?php echo htmlspecialchars(mb_substr($course['title'], 0, 30)); ?>...</div>
                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($course['created_at'])); ?></small>
                    </div>
                </div>
                <?php 
                        endforeach; 
                    else:
                ?>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-2"></i>Chưa có khóa học nào
                </p>
                <?php endif; ?>
                <?php } catch (Exception $e) { ?>
                <p class="text-danger mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Không thể tải dữ liệu
                </p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.form-label {
    font-weight: 600;
}

.text-danger {
    color: #e74a3b !important;
}

.form-control:focus,
.form-select:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

.btn-success {
    background-color: #1cc88a;
    border-color: #1cc88a;
}

.btn-success:hover {
    background-color: #17a673;
    border-color: #169b6b;
}

.card {
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}

#thumbnail-preview {
    border: 2px solid #e3e6f0;
    border-radius: 0.375rem;
}

.badge {
    font-size: 0.75em;
}

.alert-info {
    border-left: 4px solid #36b9cc;
}
</style>

<!-- Custom JavaScript -->
<script>
// Thumbnail preview from URL
document.getElementById('thumbnail').addEventListener('input', function(e) {
    const url = e.target.value.trim();
    const previewSection = document.getElementById('thumbnail-preview-section');
    const previewImg = document.getElementById('thumbnail-preview');
    
    if (url && isValidImageUrl(url)) {
        previewImg.src = url;
        previewImg.onload = function() {
            previewSection.style.display = 'block';
        };
        previewImg.onerror = function() {
            previewSection.style.display = 'none';
            alert('Không thể tải ảnh từ URL này. Vui lòng kiểm tra lại!');
        };
    } else {
        previewSection.style.display = 'none';
    }
});

// Validate image URL
function isValidImageUrl(url) {
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
    const urlLower = url.toLowerCase();
    return imageExtensions.some(ext => urlLower.includes(ext)) || 
           url.includes('youtube.com') || 
           url.includes('youtu.be') ||
           url.includes('img.youtube.com');
}

// Generate YouTube thumbnail
function generateYoutubeThumbnail() {
    const youtubeUrl = document.getElementById('youtube-url').value.trim();
    const thumbnailInput = document.getElementById('thumbnail');
    
    if (!youtubeUrl) {
        alert('Vui lòng nhập URL YouTube!');
        return;
    }
    
    // Extract video ID from YouTube URL
    const videoId = extractYouTubeVideoId(youtubeUrl);
    
    if (videoId) {
        const thumbnailUrl = `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`;
        thumbnailInput.value = thumbnailUrl;
        
        // Trigger preview
        thumbnailInput.dispatchEvent(new Event('input'));
        
        // Clear YouTube URL field
        document.getElementById('youtube-url').value = '';
        
        // Show success message
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show mt-2';
        alert.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>Đã tạo thumbnail URL thành công!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.card-body').appendChild(alert);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 3000);
    } else {
        alert('URL YouTube không hợp lệ!');
    }
}

// Extract YouTube video ID
function extractYouTubeVideoId(url) {
    const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
    const match = url.match(regExp);
    return (match && match[7].length === 11) ? match[7] : null;
}

// Form validation
document.getElementById('courseForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
    submitBtn.disabled = true;
});

// Reset form function
function resetForm() {
    if (confirm('Bạn có chắc muốn đặt lại form? Tất cả dữ liệu đã nhập sẽ bị mất.')) {
        document.getElementById('courseForm').reset();
        document.getElementById('thumbnail-preview-section').style.display = 'none';
        document.getElementById('youtube-url').value = '';
    }
}

// Form validation feedback
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('courseForm');
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(input => {
        input.addEventListener('invalid', function() {
            this.classList.add('is-invalid');
        });
        
        input.addEventListener('input', function() {
            if (this.checkValidity()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    });
});
</script>

<?php include 'includes/admin-footer.php'; ?>