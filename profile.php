<?php
// filepath: d:\Xampp\htdocs\elearning\profile.php

require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=/profile.php');
}

$page_title = 'Hồ sơ cá nhân';
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$errors = [];

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        redirect(SITE_URL . '/logout.php');
    }
    
} catch (PDOException $e) {
    error_log("User fetch error: " . $e->getMessage());
    redirect(SITE_URL . '/logout.php');
}

// AJAX endpoint for password verification
if (isset($_POST['verify_password']) && isset($_POST['password'])) {
    header('Content-Type: application/json');
    
    $password = $_POST['password'];
    $is_valid = password_verify($password, $user['password']);
    
    echo json_encode(['valid' => $is_valid]);
    exit;
}

// Handle form submission
if ($_POST) {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        
        // Validation
        if (empty($username)) {
            $errors['username'] = 'Vui lòng nhập tên đăng nhập!';
        } elseif (strlen($username) < 3) {
            $errors['username'] = 'Tên đăng nhập phải có ít nhất 3 ký tự!';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors['username'] = 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới!';
        } elseif ($username !== $user['username']) {
            // Check if new username is available
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $errors['username'] = 'Tên đăng nhập đã được sử dụng!';
            }
        }
        
        if (empty($email)) {
            $errors['email'] = 'Vui lòng nhập email!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không hợp lệ!';
        } elseif ($email !== $user['email']) {
            // Check if new email is available
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email đã được sử dụng!';
            }
        }
        
        // Update if no errors
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$username, $email, $user_id]);
                
                // Update session if username or email changed
                if ($username !== $_SESSION['username']) {
                    $_SESSION['username'] = $username;
                }
                if ($email !== $_SESSION['email']) {
                    $_SESSION['email'] = $email;
                }
                
                $success = 'Cập nhật thông tin thành công!';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                error_log("Profile update error: " . $e->getMessage());
                $error = 'Có lỗi xảy ra khi cập nhật thông tin!';
            }
        }
        
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Enhanced password validation (same as register.php)
        if (empty($current_password)) {
            $errors['current_password'] = 'Vui lòng nhập mật khẩu hiện tại!';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors['current_password'] = 'Mật khẩu hiện tại không đúng!';
        }
        
        if (empty($new_password)) {
            $errors['new_password'] = 'Vui lòng nhập mật khẩu mới!';
        } elseif (strlen($new_password) < 8) {
            $errors['new_password'] = 'Mật khẩu phải có ít nhất 8 ký tự!';
        } elseif (strlen($new_password) > 255) {
            $errors['new_password'] = 'Mật khẩu không được vượt quá 255 ký tự!';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $new_password)) {
            $errors['new_password'] = 'Mật khẩu phải chứa ít nhất: 1 chữ thường, 1 chữ hoa, 1 số và 1 ký tự đặc biệt (@$!%*?&)!';
        } elseif (preg_match('/^(password|123456|qwerty|admin|user)/i', $new_password)) {
            $errors['new_password'] = 'Mật khẩu quá đơn giản, vui lòng chọn mật khẩu khác!';
        }
        
        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Xác nhận mật khẩu không khớp!';
        }
        
        // Update password if no errors
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                $success = 'Đổi mật khẩu thành công!';
                
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra khi đổi mật khẩu!';
            }
        }
    }
}

// Get user statistics
try {
    // Get enrolled courses count
    $stmt = $pdo->prepare("SELECT COUNT(*) as enrolled_courses FROM enrollments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $enrolled_result = $stmt->fetch();
    $enrolled_courses = $enrolled_result['enrolled_courses'] ?? 0;
    
    // Get completed lessons count (if progress table exists)
    $completed_lessons = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT p.lesson_id) as completed_lessons
            FROM progress p 
            JOIN lessons l ON p.lesson_id = l.id
            JOIN enrollments e ON l.course_id = e.course_id
            WHERE p.user_id = ? AND p.completed = 1 AND e.user_id = ?
        ");
        $stmt->execute([$user_id, $user_id]);
        $completed_result = $stmt->fetch();
        $completed_lessons = $completed_result['completed_lessons'] ?? 0;
    } catch (PDOException $e) {
        $completed_lessons = 0;
    }
    
    $stats = [
        'enrolled_courses' => $enrolled_courses,
        'completed_lessons' => $completed_lessons
    ];
    
} catch (Exception $e) {
    error_log("Stats calculation error: " . $e->getMessage());
    $stats = [
        'enrolled_courses' => 0,
        'completed_lessons' => 0
    ];
}
?>

<?php include 'includes/header.php'; ?>

<!-- Page Header -->
<div class="hero-section bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-6 fw-bold mb-2">
                    <i class="bi bi-person-circle me-2"></i>Hồ sơ cá nhân
                </h1>
                <p class="lead mb-0">Quản lý thông tin tài khoản của bạn</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="d-flex justify-content-md-end gap-2 mt-3 mt-md-0">
                    <a href="<?php echo SITE_URL; ?>/my-courses.php" class="btn btn-light">
                        <i class="bi bi-book me-1"></i>Khóa học
                    </a>
                    <a href="<?php echo SITE_URL; ?>/logout.php" class="btn btn-outline-light">
                        <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <!-- Messages -->
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-4 mb-4">
            <!-- Profile Card -->
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <div class="profile-avatar mb-3">
                        <div class="avatar-circle mx-auto">
                            <i class="bi bi-person-fill display-4 text-muted"></i>
                        </div>
                    </div>
                    
                    <h4 class="fw-bold mb-1">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </h4>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?> mb-3">
                        <?php echo $user['role'] === 'admin' ? 'Quản trị viên' : 'Học viên'; ?>
                    </span>
                    
                    <div class="profile-stats mt-4 pt-3 border-top">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="stat-item">
                                    <h5 class="fw-bold text-primary mb-1"><?php echo $stats['enrolled_courses']; ?></h5>
                                    <small class="text-muted">Khóa học</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item">
                                    <h5 class="fw-bold text-success mb-1"><?php echo $stats['completed_lessons']; ?></h5>
                                    <small class="text-muted">Bài học</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Info -->
            <div class="card mt-4 shadow-sm border-0">
                <div class="card-header bg-light border-0">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-info-circle me-2"></i>Thông tin tài khoản
                    </h6>
                </div>
                <div class="card-body">
                    <div class="info-item d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <small class="text-muted d-block">Tên đăng nhập</small>
                            <span class="fw-medium"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <i class="bi bi-person text-muted"></i>
                    </div>
                    
                    <div class="info-item d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <small class="text-muted d-block">Email</small>
                            <span class="fw-medium"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <i class="bi bi-envelope text-muted"></i>
                    </div>
                    
                    <div class="info-item d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <small class="text-muted d-block">Vai trò</small>
                            <span class="fw-medium">
                                <?php echo $user['role'] === 'admin' ? 'Quản trị viên' : 'Học viên'; ?>
                            </span>
                        </div>
                        <i class="bi bi-shield-check text-muted"></i>
                    </div>
                    
                    <div class="info-item d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <small class="text-muted d-block">Tham gia</small>
                            <span class="fw-medium"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <i class="bi bi-calendar-plus text-muted"></i>
                    </div>
                    
                    <div class="info-item d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block">Cập nhật cuối</small>
                            <span class="fw-medium">
                                <?php echo date('d/m/Y', strtotime($user['updated_at'] ?? $user['created_at'])); ?>
                            </span>
                        </div>
                        <i class="bi bi-clock-history text-muted"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Tab Navigation -->
            <ul class="nav nav-pills nav-fill mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="info-tab" data-bs-toggle="pill" data-bs-target="#info" type="button">
                        <i class="bi bi-person me-2"></i>Thông tin cơ bản
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button">
                        <i class="bi bi-shield-lock me-2"></i>Bảo mật
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="pill" data-bs-target="#activity" type="button">
                        <i class="bi bi-clock-history me-2"></i>Hoạt động
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="profileTabsContent">
                <!-- Personal Info Tab -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0 fw-bold">Cập nhật thông tin</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="profileForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label fw-bold">
                                            Tên đăng nhập <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                               id="username" name="username" 
                                               value="<?php echo htmlspecialchars($user['username']); ?>" 
                                               required>
                                        <?php if (isset($errors['username'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                                        <?php endif; ?>
                                        <div class="form-text">Chỉ được sử dụng chữ cái, số và dấu gạch dưới</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label fw-bold">
                                            Email <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" 
                                               class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                               id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                                               required>
                                        <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Vai trò</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo $user['role'] === 'admin' ? 'Quản trị viên' : 'Học viên'; ?>" 
                                           readonly>
                                    <div class="form-text">Vai trò không thể thay đổi</div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-2"></i>Cập nhật thông tin
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Đặt lại
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0 fw-bold">Đổi mật khẩu</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="passwordForm">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label fw-bold">
                                        Mật khẩu hiện tại <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                               id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($errors['current_password'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['current_password']; ?></div>
                                    <?php endif; ?>
                                    <div id="current-password-feedback" class="mt-1"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label fw-bold">
                                        Mật khẩu mới <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                               id="new_password" name="new_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($errors['new_password'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['new_password']; ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">
                                        <strong>Yêu cầu mật khẩu:</strong> 8-255 ký tự, có chữ thường, chữ hoa, số và ký tự đặc biệt (@$!%*?&)
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label fw-bold">
                                        Xác nhận mật khẩu mới <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                               id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['confirm_password']; ?></div>
                                    <?php endif; ?>
                                    <div id="password-match-feedback" class="mt-1"></div>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-warning" id="changePasswordBtn" disabled>
                                    <i class="bi bi-shield-lock me-2"></i>Đổi mật khẩu
                                </button>
                                <div class="form-text mt-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Nút đổi mật khẩu sẽ được kích hoạt khi tất cả thông tin hợp lệ
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Security Tips -->
                    <div class="card mt-4 shadow-sm border-0">
                        <div class="card-header bg-light border-0">
                            <h6 class="mb-0 fw-bold">Yêu cầu mật khẩu mạnh</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <i class="bi bi-check-circle text-success fs-5 me-3 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Độ dài: 8-255 ký tự</h6>
                                    <small class="text-muted">Mật khẩu phải có từ 8 đến 255 ký tự</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-start mb-3">
                                <i class="bi bi-check-circle text-success fs-5 me-3 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Kết hợp ký tự</h6>
                                    <small class="text-muted">Phải có chữ thường (a-z), chữ hoa (A-Z), số (0-9) và ký tự đặc biệt (@$!%*?&)</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-start mb-3">
                                <i class="bi bi-x-circle text-danger fs-5 me-3 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Tránh mật khẩu phổ biến</h6>
                                    <small class="text-muted">Không sử dụng password, 123456, qwerty, admin, user...</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-start">
                                <i class="bi bi-shield-lock text-primary fs-5 me-3 mt-1"></i>
                                <div>
                                    <h6 class="mb-1">Bảo mật tài khoản</h6>
                                    <small class="text-muted">Không chia sẻ mật khẩu và thay đổi định kỳ</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Tab -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0 fw-bold">Hoạt động gần đây</h5>
                        </div>
                        <div class="card-body">
                            <div class="activity-timeline">
                                <div class="activity-item">
                                    <div class="activity-icon bg-primary">
                                        <i class="bi bi-box-arrow-in-right text-white"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6 class="mb-1">Đăng nhập thành công</h6>
                                        <small class="text-muted">Hôm nay, <?php echo date('H:i'); ?></small>
                                        <p class="small text-muted mb-0">Đăng nhập từ trình duyệt web</p>
                                    </div>
                                </div>
                                
                                <div class="activity-item">
                                    <div class="activity-icon bg-success">
                                        <i class="bi bi-person-check text-white"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6 class="mb-1">Cập nhật thông tin hồ sơ</h6>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($user['updated_at'] ?? $user['created_at'])); ?>
                                        </small>
                                        <p class="small text-muted mb-0">Thay đổi thông tin cá nhân</p>
                                    </div>
                                </div>
                                
                                <div class="activity-item">
                                    <div class="activity-icon bg-info">
                                        <i class="bi bi-person-plus text-white"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6 class="mb-1">Tạo tài khoản</h6>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></small>
                                        <p class="small text-muted mb-0">Tham gia hệ thống E-Learning</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4 pt-4 border-top">
                                <div class="alert alert-info border-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Thông báo:</strong> Lịch sử hoạt động chi tiết sẽ được cập nhật trong tương lai.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.hero-section {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.avatar-circle {
    width: 100px;
    height: 100px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 4px solid #ffffff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.profile-stats .stat-item {
    padding: 0.5rem 0;
}

.nav-pills .nav-link {
    border-radius: 50px;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.3s ease;
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.card {
    border-radius: 15px;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.activity-timeline {
    position: relative;
    padding-left: 40px;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #007bff, #e9ecef);
}

.activity-item {
    position: relative;
    margin-bottom: 2rem;
    display: flex;
    align-items: flex-start;
}

.activity-icon {
    position: absolute;
    left: -40px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.activity-content {
    margin-left: 15px;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.1);
}

.btn {
    border-radius: 50px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-primary {
    background: linear-gradient(135deg, #007bff, #0056b3);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.info-item {
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #f8f9fa;
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

#password-match-feedback, #current-password-feedback {
    font-size: 0.8rem;
}

.match-success, .password-valid {
    color: #28a745;
}

.match-error, .password-invalid {
    color: #dc3545;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

@media (max-width: 768px) {
    .avatar-circle {
        width: 80px;
        height: 80px;
    }
    
    .hero-section h1 {
        font-size: 1.8rem;
    }
    
    .activity-timeline {
        padding-left: 30px;
    }
    
    .activity-icon {
        left: -30px;
        width: 30px;
        height: 30px;
    }
}
</style>

<!-- JavaScript -->
<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Enhanced password validation (same as register.php)
function validatePassword(password) {
    const requirements = {
        length: password.length >= 8 && password.length <= 255,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        number: /\d/.test(password),
        special: /[@$!%*?&]/.test(password),
        notCommon: !(/^(password|123456|qwerty|admin|user)/i.test(password))
    };
    
    return Object.values(requirements).every(req => req === true);
}

// Verify current password via AJAX
function verifyCurrentPassword(password) {
    return fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'verify_password=1&password=' + encodeURIComponent(password)
    })
    .then(response => response.json())
    .then(data => data.valid)
    .catch(() => false);
}

// Variables to track validation state
let currentPasswordValid = false;
let newPasswordValid = false;
let passwordsMatch = false;

// Update button state
function updateButtonState() {
    const button = document.getElementById('changePasswordBtn');
    const allValid = currentPasswordValid && newPasswordValid && passwordsMatch;
    button.disabled = !allValid;
    
    if (allValid) {
        button.classList.remove('btn-outline-warning');
        button.classList.add('btn-warning');
    } else {
        button.classList.remove('btn-warning');
        button.classList.add('btn-outline-warning');
    }
}

// Real-time current password verification
document.getElementById('current_password').addEventListener('input', async function() {
    const password = this.value;
    const feedback = document.getElementById('current-password-feedback');
    
    if (password.length > 0) {
        // Show loading state
        feedback.innerHTML = '<i class="bi bi-hourglass-split me-1 text-primary"></i><span class="text-primary">Đang kiểm tra...</span>';
        
        try {
            const isValid = await verifyCurrentPassword(password);
            currentPasswordValid = isValid;
            
            if (isValid) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                feedback.innerHTML = '<i class="bi bi-check-circle me-1 text-success"></i><span class="password-valid">Mật khẩu đúng</span>';
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                feedback.innerHTML = '<i class="bi bi-x-circle me-1 text-danger"></i><span class="password-invalid">Mật khẩu hiện tại không đúng!</span>';
            }
        } catch (error) {
            currentPasswordValid = false;
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
            feedback.innerHTML = '<i class="bi bi-exclamation-triangle me-1 text-warning"></i><span class="text-warning">Lỗi kiểm tra mật khẩu</span>';
        }
    } else {
        currentPasswordValid = false;
        this.classList.remove('is-valid', 'is-invalid');
        feedback.innerHTML = '';
    }
    
    updateButtonState();
});

// Real-time new password validation
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    newPasswordValid = validatePassword(password);
    
    if (password.length > 0) {
        if (newPasswordValid) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    } else {
        newPasswordValid = false;
        this.classList.remove('is-valid', 'is-invalid');
    }
    
    // Re-check password match when new password changes
    const confirmPasswordField = document.getElementById('confirm_password');
    if (confirmPasswordField.value) {
        confirmPasswordField.dispatchEvent(new Event('input'));
    }
    
    updateButtonState();
});

// Real-time password confirmation check
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    const feedback = document.getElementById('password-match-feedback');
    
    if (confirmPassword) {
        passwordsMatch = newPassword === confirmPassword;
        
        if (passwordsMatch) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            feedback.innerHTML = '<i class="bi bi-check-circle me-1 text-success"></i><span class="match-success">Mật khẩu khớp</span>';
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
            feedback.innerHTML = '<i class="bi bi-x-circle me-1 text-danger"></i><span class="match-error">Mật khẩu không khớp</span>';
        }
    } else {
        passwordsMatch = false;
        this.classList.remove('is-valid', 'is-invalid');
        feedback.innerHTML = '';
    }
    
    updateButtonState();
});

// Form validation before submit
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    if (!currentPasswordValid) {
        e.preventDefault();
        alert('Vui lòng nhập mật khẩu hiện tại chính xác!');
        document.getElementById('current_password').focus();
        return;
    }
    
    if (!newPasswordValid) {
        e.preventDefault();
        alert('Mật khẩu mới không đáp ứng yêu cầu bảo mật!');
        document.getElementById('new_password').focus();
        return;
    }
    
    if (!passwordsMatch) {
        e.preventDefault();
        alert('Xác nhận mật khẩu không khớp!');
        document.getElementById('confirm_password').focus();
        return;
    }
});

// Username validation
document.getElementById('username').addEventListener('input', function() {
    const username = this.value.trim();
    if (username.length >= 3 && /^[a-zA-Z0-9_]+$/.test(username)) {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else if (username.length > 0) {
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid', 'is-valid');
    }
});

// Email validation
document.getElementById('email').addEventListener('input', function() {
    const email = this.value.trim();
    if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else if (email.length > 0) {
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid', 'is-valid');
    }
});

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });
});
</script>

<?php include 'includes/footer.php'; ?>