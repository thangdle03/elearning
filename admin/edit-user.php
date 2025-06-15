<?php
// filepath: d:\Xampp\htdocs\elearning\admin\edit-user.php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Chỉnh sửa người dùng';
$current_page = 'users';

// Initialize variables
$message = '';
$error = '';
$user = null;

// Get user ID
$user_id = (int)($_GET['id'] ?? 0);

if ($user_id <= 0) {
    header('Location: users.php');
    exit;
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: users.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: users.php');
    exit;
}

// Update page title with username
$page_title = 'Chỉnh sửa: ' . htmlspecialchars($user['username']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($username)) {
        $error = 'Vui lòng nhập tên người dùng!';
    } elseif (strlen($username) < 3) {
        $error = 'Tên người dùng phải có ít nhất 3 ký tự!';
    } elseif (empty($email)) {
        $error = 'Vui lòng nhập email!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Định dạng email không hợp lệ!';
    } elseif (!in_array($role, ['admin', 'user'])) {
        $error = 'Vai trò không hợp lệ!';
    } elseif (!in_array($status, ['active', 'inactive'])) {
        $error = 'Trạng thái không hợp lệ!';
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự!';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = 'Xác nhận mật khẩu không khớp!';
    } else {
        try {
            // Check if username already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Tên người dùng đã tồn tại!';
            } else {
                // Check if email already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $error = 'Email đã được sử dụng!';
                } else {
                    // Prevent self-demotion from admin
                    if ($user_id == $_SESSION['user_id'] && $role !== 'admin') {
                        $error = 'Bạn không thể thay đổi vai trò của chính mình!';
                    }
                    // Prevent self-deactivation
                    elseif ($user_id == $_SESSION['user_id'] && $status !== 'active') {
                        $error = 'Bạn không thể vô hiệu hóa tài khoản của chính mình!';
                    } else {
                        // Update user
                        $pdo->beginTransaction();

                        if (!empty($new_password)) {
                            // Update with new password
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("
                                UPDATE users 
                                SET username = ?, email = ?, password = ?, role = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                                WHERE id = ?
                            ");
                            $stmt->execute([$username, $email, $hashed_password, $role, $status, $user_id]);
                        } else {
                            // Update without changing password
                            $stmt = $pdo->prepare("
                                UPDATE users 
                                SET username = ?, email = ?, role = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                                WHERE id = ?
                            ");
                            $stmt->execute([$username, $email, $role, $status, $user_id]);
                        }

                        if ($stmt->rowCount() > 0) {
                            $pdo->commit();

                            // Update session if editing own account
                            if ($user_id == $_SESSION['user_id']) {
                                $_SESSION['username'] = $username;
                                $_SESSION['user_role'] = $role;
                            }

                            $message = 'Đã cập nhật thông tin người dùng thành công!';

                            // Refresh user data
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                            $stmt->execute([$user_id]);
                            $user = $stmt->fetch();
                        } else {
                            $pdo->rollBack();
                            $error = 'Không có thay đổi nào được thực hiện!';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Có lỗi xảy ra: ' . $e->getMessage();
        }
    }
}

// Get user's statistics
try {
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM enrollments WHERE user_id = ?) as total_enrollments,
            (SELECT COUNT(*) FROM progress WHERE user_id = ?) as completed_lessons,
            (SELECT COUNT(DISTINCT course_id) FROM progress p 
             JOIN lessons l ON p.lesson_id = l.id 
             WHERE p.user_id = ?) as courses_with_progress
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = [
        'total_enrollments' => 0,
        'completed_lessons' => 0,
        'courses_with_progress' => 0
    ];
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
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="users.php">Người dùng</a></li>
                    <li class="breadcrumb-item">
                        <a href="user-detail.php?id=<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </a>
                    </li>
                    <li class="breadcrumb-item active">Chỉnh sửa</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-user-edit me-2"></i><?php echo $page_title; ?>
            </h1>
            <p class="text-muted mb-0">Cập nhật thông tin và cài đặt người dùng</p>
        </div>
        <div class="d-flex gap-2">
            <a href="user-detail.php?id=<?php echo $user['id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
        </div>
    </div>

    <div class="row">
        <!-- User Info Sidebar -->
        <div class="col-lg-4 mb-4">
            <!-- Current User Info -->
            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-gradient-primary text-white text-center py-4">
                    <div class="mb-3">
                        <div class="avatar-circle-large bg-white text-primary mx-auto">
                            <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'user-shield' : 'user'; ?> fa-3x"></i>
                        </div>
                    </div>
                    <h4 class="mb-1 fw-bold"><?php echo htmlspecialchars($user['username']); ?></h4>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-envelope me-1"></i>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </p>
                </div>
                <div class="card-body">
                    <div class="user-info">
                        <div class="info-item">
                            <strong>ID:</strong>
                            <span class="badge bg-secondary fs-6"><?php echo $user['id']; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Vai trò hiện tại:</strong>
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'success'; ?> fs-6">
                                <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'shield-alt' : 'user'; ?> me-1"></i>
                                <?php echo $user['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <strong>Trạng thái:</strong>
                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                                <i class="fas fa-<?php echo $user['status'] === 'active' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                <?php echo $user['status'] === 'active' ? 'Hoạt động' : 'Tạm dừng'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <strong>Ngày tham gia:</strong>
                            <span class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Statistics -->
            <div class="card shadow border-0">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar me-2"></i>Thống kê hoạt động
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-12 mb-3">
                            <div class="stat-item">
                                <div class="h4 mb-0 text-primary fw-bold"><?php echo number_format($stats['total_enrollments']); ?></div>
                                <small class="text-muted">Khóa học đã đăng ký</small>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="stat-item">
                                <div class="h4 mb-0 text-success fw-bold"><?php echo number_format($stats['completed_lessons']); ?></div>
                                <small class="text-muted">Bài học đã hoàn thành</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="stat-item">
                                <div class="h4 mb-0 text-info fw-bold"><?php echo number_format($stats['courses_with_progress']); ?></div>
                                <small class="text-muted">Khóa học có tiến độ</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="col-lg-8">
            <div class="card shadow border-0">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-edit me-2"></i>Thông tin người dùng
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="editUserForm" novalidate>
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label fw-bold">
                                    <i class="fas fa-user me-1"></i>Tên người dùng <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="username" name="username"
                                    value="<?php echo htmlspecialchars($user['username']); ?>"
                                    required minlength="3" maxlength="50">
                                <div class="invalid-feedback">
                                    Vui lòng nhập tên người dùng (3-50 ký tự)
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label fw-bold">
                                    <i class="fas fa-envelope me-1"></i>Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?php echo htmlspecialchars($user['email']); ?>"
                                    required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập email hợp lệ
                                </div>
                            </div>

                            <!-- Role and Status -->
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label fw-bold">
                                    <i class="fas fa-shield-alt me-1"></i>Vai trò <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="role" name="role" required
                                    <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>
                                        <i class="fas fa-user"></i> Người dùng
                                    </option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>
                                        <i class="fas fa-user-shield"></i> Quản trị viên
                                    </option>
                                </select>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                                    <small class="text-muted">Không thể thay đổi vai trò của chính mình</small>
                                <?php endif; ?>
                                <div class="invalid-feedback">
                                    Vui lòng chọn vai trò
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label fw-bold">
                                    <i class="fas fa-toggle-on me-1"></i>Trạng thái <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="status" name="status" required
                                    <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>
                                        <i class="fas fa-check-circle"></i> Hoạt động
                                    </option>
                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>
                                        <i class="fas fa-times-circle"></i> Tạm dừng
                                    </option>
                                </select>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <input type="hidden" name="status" value="<?php echo $user['status']; ?>">
                                    <small class="text-muted">Không thể thay đổi trạng thái của chính mình</small>
                                <?php endif; ?>
                                <div class="invalid-feedback">
                                    Vui lòng chọn trạng thái
                                </div>
                            </div>

                            <!-- Password Section -->
                            <div class="col-12">
                                <hr class="my-4">
                                <h6 class="mb-3 text-primary">
                                    <i class="fas fa-key me-2"></i>Thay đổi mật khẩu (tùy chọn)
                                </h6>
                                <p class="text-muted mb-3">Để trống nếu không muốn thay đổi mật khẩu</p>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label fw-bold">
                                    <i class="fas fa-lock me-1"></i>Mật khẩu mới
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password"
                                        minlength="6" maxlength="100">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye" id="new_password_icon"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">
                                    Mật khẩu phải có ít nhất 6 ký tự
                                </div>
                                <small class="text-muted">Tối thiểu 6 ký tự</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label fw-bold">
                                    <i class="fas fa-lock me-1"></i>Xác nhận mật khẩu
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye" id="confirm_password_icon"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">
                                    Xác nhận mật khẩu không khớp
                                </div>
                            </div>
                        </div>

                        <!-- Warning Messages -->
                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Lưu ý:</strong> Bạn đang chỉnh sửa tài khoản của chính mình.
                                Một số thao tác như thay đổi vai trò và trạng thái đã bị vô hiệu hóa để đảm bảo an toàn.
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Các trường có dấu <span class="text-danger">*</span> là bắt buộc
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Đặt lại
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-save me-2"></i>Lưu thay đổi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .avatar-circle-large {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .user-info .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e9ecef;
    }

    .user-info .info-item:last-child {
        border-bottom: none;
    }

    .stat-item {
        padding: 1rem;
        border-radius: 0.5rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        margin-bottom: 0.5rem;
    }

    .form-label {
        margin-bottom: 0.5rem;
        color: #495057;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
        transform: translateY(-1px);
    }

    .card {
        border-radius: 0.75rem;
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-2px);
    }

    .alert {
        border-radius: 0.5rem;
        border: none;
    }

    .breadcrumb-item a {
        color: #667eea;
        text-decoration: none;
    }

    .breadcrumb-item a:hover {
        color: #764ba2;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert:not(.alert-warning)').forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);

        // Form validation
        const form = document.getElementById('editUserForm');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');

        // Password matching validation
        function validatePasswords() {
            if (newPassword.value && confirmPassword.value) {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Xác nhận mật khẩu không khớp');
                    confirmPassword.classList.add('is-invalid');
                    return false;
                } else {
                    confirmPassword.setCustomValidity('');
                    confirmPassword.classList.remove('is-invalid');
                    confirmPassword.classList.add('is-valid');
                    return true;
                }
            }
            return true;
        }

        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);

        // Form submission
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity() || !validatePasswords()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });

        // Real-time validation
        form.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.checkValidity()) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        });
    });

    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '_icon');

        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function resetForm() {
        if (confirm('Bạn có chắc muốn đặt lại form? Tất cả thay đổi sẽ bị mất!')) {
            document.getElementById('editUserForm').reset();
            document.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                el.classList.remove('is-valid', 'is-invalid');
            });
            document.getElementById('editUserForm').classList.remove('was-validated');
        }
    }
</script>

<?php include 'includes/admin-footer.php'; ?>