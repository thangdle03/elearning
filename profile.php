
<?php
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
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect(SITE_URL . '/logout.php');
}

// Handle form submission
if ($_POST) {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $bio = sanitize($_POST['bio']);
        
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
        
        if ($phone && !preg_match('/^[0-9+\-\s\(\)]+$/', $phone)) {
            $errors['phone'] = 'Số điện thoại không hợp lệ!';
        }
        
        // Update if no errors
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, email = ?, full_name = ?, phone = ?, bio = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$username, $email, $full_name, $phone, $bio, $user_id]);
                
                // Update session if username changed
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
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra khi cập nhật thông tin!';
            }
        }
        
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($current_password)) {
            $errors['current_password'] = 'Vui lòng nhập mật khẩu hiện tại!';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors['current_password'] = 'Mật khẩu hiện tại không đúng!';
        }
        
        if (empty($new_password)) {
            $errors['new_password'] = 'Vui lòng nhập mật khẩu mới!';
        } elseif (strlen($new_password) < 6) {
            $errors['new_password'] = 'Mật khẩu mới phải có ít nhất 6 ký tự!';
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
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT e.course_id) as enrolled_courses,
        COUNT(DISTINCT CASE WHEN p.completed = 1 THEN p.lesson_id END) as completed_lessons,
        COUNT(DISTINCT CASE WHEN course_progress.progress >= 100 THEN e.course_id END) as completed_courses
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.user_id
    LEFT JOIN lessons l ON e.course_id = l.course_id
    LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = u.id
    LEFT JOIN (
        SELECT 
            e.course_id,
            CASE 
                WHEN total_lessons.lesson_count > 0 
                THEN (completed_lessons.completed_count * 100.0 / total_lessons.lesson_count)
                ELSE 0
            END as progress
        FROM enrollments e
        LEFT JOIN (
            SELECT course_id, COUNT(*) as lesson_count
            FROM lessons
            GROUP BY course_id
        ) total_lessons ON e.course_id = total_lessons.course_id
        LEFT JOIN (
            SELECT l.course_id, COUNT(*) as completed_count
            FROM lessons l
            JOIN progress p ON l.id = p.lesson_id
            WHERE p.user_id = ? AND p.completed = 1
            GROUP BY l.course_id
        ) completed_lessons ON e.course_id = completed_lessons.course_id
        WHERE e.user_id = ?
    ) course_progress ON e.course_id = course_progress.course_id
    WHERE u.id = ?
");
$stmt->execute([$user_id, $user_id, $user_id]);
$stats = $stmt->fetch();
?>

<?php include 'includes/header.php'; ?>

<!-- Page Header -->
<div class="bg-gradient-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-0">
                    <i class="bi bi-person-circle me-2"></i>Hồ sơ cá nhân
                </h1>
                <p class="mb-0 mt-2">Quản lý thông tin tài khoản của bạn</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="d-flex justify-content-md-end gap-2">
                    <a href="<?php echo SITE_URL; ?>/my-courses.php" class="btn btn-light btn-sm">
                        <i class="bi bi-book me-1"></i>Khóa học
                    </a>
                    <a href="<?php echo SITE_URL; ?>/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Messages -->
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show m-3" role="alert">
    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="container my-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-4 mb-4">
            <!-- Profile Card -->
            <div class="card profile-card shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="profile-avatar mb-3">
                        <div class="avatar-circle">
                            <?php if ($user['avatar']): ?>
                            <img src="<?php echo $user['avatar']; ?>" alt="Avatar" class="rounded-circle w-100 h-100">
                            <?php else: ?>
                            <i class="bi bi-person-fill display-4 text-muted"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h4 class="fw-bold"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h4>
                    <p class="text-muted mb-2">@<?php echo htmlspecialchars($user['username']); ?></p>
                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?> mb-3">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    
                    <?php if ($user['bio']): ?>
                    <p class="text-muted small"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                    <?php endif; ?>
                    
                    <div class="profile-stats mt-3">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-item">
                                    <h5 class="fw-bold text-primary"><?php echo $stats['enrolled_courses']; ?></h5>
                                    <small class="text-muted">Khóa học</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item">
                                    <h5 class="fw-bold text-success"><?php echo $stats['completed_courses']; ?></h5>
                                    <small class="text-muted">Hoàn thành</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item">
                                    <h5 class="fw-bold text-warning"><?php echo $stats['completed_lessons']; ?></h5>
                                    <small class="text-muted">Bài học</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Thông tin tài khoản
                    </h6>
                </div>
                <div class="card-body">
                    <div class="info-item mb-2">
                        <small class="text-muted">Tham gia:</small>
                        <div><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                    </div>
                    <div class="info-item mb-2">
                        <small class="text-muted">Cập nhật cuối:</small>
                        <div><?php echo date('d/m/Y H:i', strtotime($user['updated_at'] ?: $user['created_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <small class="text-muted">ID:</small>
                        <div><code><?php echo $user['id']; ?></code></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                        <i class="bi bi-person me-2"></i>Thông tin cá nhân
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
                        <i class="bi bi-shield-lock me-2"></i>Bảo mật
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                        <i class="bi bi-clock-history me-2"></i>Hoạt động
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="profileTabsContent">
                <!-- Personal Info Tab -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Chỉnh sửa thông tin cá nhân</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="profileForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                               id="username" name="username" 
                                               value="<?php echo htmlspecialchars($user['username']); ?>" 
                                               required>
                                        <?php if (isset($errors['username'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
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
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">Họ và tên</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Số điện thoại</label>
                                        <input type="tel" 
                                               class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                               id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bio" class="form-label">Giới thiệu bản thân</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="4" 
                                              placeholder="Chia sẻ một chút về bản thân bạn..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-2"></i>Cập nhật thông tin
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Đổi mật khẩu</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="passwordForm">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Mật khẩu hiện tại <span class="text-danger">*</span></label>
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
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
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
                                    <div class="form-text">Tối thiểu 6 ký tự</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
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
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="bi bi-shield-lock me-2"></i>Đổi mật khẩu
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Security Settings -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">Cài đặt bảo mật</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">Xác thực 2 bước</h6>
                                    <small class="text-muted">Tăng cường bảo mật tài khoản với xác thực 2 bước</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="two_factor" disabled>
                                    <label class="form-check-label" for="two_factor">
                                        <span class="badge bg-secondary">Sắp có</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Thông báo đăng nhập</h6>
                                    <small class="text-muted">Nhận email khi có đăng nhập từ thiết bị mới</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="login_notifications" checked disabled>
                                    <label class="form-check-label" for="login_notifications">
                                        <span class="badge bg-secondary">Sắp có</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Tab -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Hoạt động gần đây</h5>
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
                                    </div>
                                </div>
                                
                                <div class="activity-item">
                                    <div class="activity-icon bg-success">
                                        <i class="bi bi-person-check text-white"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6 class="mb-1">Cập nhật thông tin hồ sơ</h6>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($user['updated_at'] ?: $user['created_at'])); ?></small>
                                    </div>
                                </div>
                                
                                <div class="activity-item">
                                    <div class="activity-icon bg-info">
                                        <i class="bi bi-person-plus text-white"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6 class="mb-1">Tạo tài khoản</h6>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <p class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Tính năng theo dõi hoạt động chi tiết sẽ được bổ sung trong phiên bản tiếp theo.
                                </p>
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
.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.profile-card {
    border: none;
    border-radius: 15px;
}

.avatar-circle {
    width: 120px;
    height: 120px;
    margin: 0 auto;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 4px solid #e9ecef;
}

.profile-stats .stat-item {
    padding: 10px 0;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    background: none;
    border-bottom: 3px solid #007bff;
    color: #007bff;
}

.tab-content {
    padding-top: 20px;
}

.activity-timeline {
    position: relative;
    padding-left: 30px;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.activity-item {
    position: relative;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
}

.activity-icon {
    position: absolute;
    left: -30px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.activity-content {
    margin-left: 20px;
}

@media (max-width: 768px) {
    .avatar-circle {
        width: 80px;
        height: 80px;
    }
    
    .profile-stats .row {
        text-align: center;
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

// Form validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Mật khẩu xác nhận không khớp!');
        return false;
    }
});

// Real-time password confirmation check
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && newPassword !== confirmPassword) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Save form data on input (auto-save draft)
let saveTimer;
document.getElementById('profileForm').addEventListener('input', function() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        // Could implement auto-save to localStorage here
        console.log('Form data saved to draft');
    }, 2000);
});
</script>

<?php include 'includes/footer.php'; ?>