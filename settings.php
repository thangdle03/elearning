<?php
// filepath: d:\Xampp\htdocs\elearning\settings.php

require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php');
}

$user_id = $_SESSION['user_id'];
$page_title = 'Cài đặt tài khoản';

// Create user_preferences table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            language VARCHAR(5) DEFAULT 'vi',
            theme VARCHAR(10) DEFAULT 'light',
            email_notifications TINYINT(1) DEFAULT 1,
            course_reminders TINYINT(1) DEFAULT 1,
            marketing_emails TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user (user_id)
        )
    ");
} catch (PDOException $e) {
    // Ignore if table already exists
}

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        redirect(SITE_URL . '/logout.php');
    }
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn dữ liệu: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_email':
                $email = trim($_POST['email'] ?? '');
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Email không hợp lệ!");
                }
                
                // Validate email uniqueness
                if ($email !== $user['email']) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        throw new Exception("Email này đã được sử dụng bởi tài khoản khác!");
                    }
                }
                
                $stmt = $pdo->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$email, $user_id]);
                
                // Update session email if changed
                if ($email !== $user['email']) {
                    $_SESSION['email'] = $email;
                }
                
                $success_message = "✅ Cập nhật email thành công!";
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                // Verify current password
                if (!password_verify($current_password, $user['password'])) {
                    throw new Exception("Mật khẩu hiện tại không đúng!");
                }
                
                if (strlen($new_password) < 6) {
                    throw new Exception("Mật khẩu mới phải có ít nhất 6 ký tự!");
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception("Xác nhận mật khẩu không khớp!");
                }
                
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                $success_message = "🔒 Đổi mật khẩu thành công!";
                break;
                
            case 'update_preferences':
                $language = $_POST['language'] ?? 'vi';
                $theme = $_POST['theme'] ?? 'light';
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $course_reminders = isset($_POST['course_reminders']) ? 1 : 0;
                $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;
                
                // Create or update user preferences
                $stmt = $pdo->prepare("
                    INSERT INTO user_preferences (user_id, language, theme, email_notifications, course_reminders, marketing_emails, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                    language = VALUES(language),
                    theme = VALUES(theme),
                    email_notifications = VALUES(email_notifications),
                    course_reminders = VALUES(course_reminders),
                    marketing_emails = VALUES(marketing_emails),
                    updated_at = NOW()
                ");
                $stmt->execute([$user_id, $language, $theme, $email_notifications, $course_reminders, $marketing_emails]);
                
                $success_message = "⚙️ Cập nhật tùy chọn thành công!";
                break;
                
            case 'delete_account':
                $confirm_password = $_POST['confirm_password'] ?? '';
                $confirm_text = $_POST['confirm_text'] ?? '';
                
                if (!password_verify($confirm_password, $user['password'])) {
                    throw new Exception("Mật khẩu xác nhận không đúng!");
                }
                
                if ($confirm_text !== 'XÓA TÀI KHOẢN') {
                    throw new Exception("Vui lòng nhập đúng text xác nhận!");
                }
                
                // Soft delete account
                $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                
                // Logout and redirect
                session_destroy();
                redirect(SITE_URL . '/index.php?message=account_deleted');
                break;
        }
        
        // Refresh user data after update
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    } catch (PDOException $e) {
        $error_message = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
    }
}

// Get user preferences with fallback
$preferences = [
    'language' => 'vi',
    'theme' => 'light',
    'email_notifications' => 1,
    'course_reminders' => 1,
    'marketing_emails' => 0
];

try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_prefs = $stmt->fetch();
    
    if ($user_prefs) {
        $preferences = array_merge($preferences, $user_prefs);
    }
} catch (PDOException $e) {
    // Use default preferences if table doesn't exist
}

include 'includes/header.php';
?>

<div class="container my-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="h2 mb-2">
                            <i class="fas fa-cog me-3"></i>Cài đặt tài khoản
                        </h1>
                        <p class="mb-0 opacity-90">
                            Quản lý tài khoản, bảo mật và tùy chọn hệ thống
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="user-avatar-large">
                            <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Settings Navigation -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="nav nav-pills flex-column" id="settings-tabs" role="tablist">
                        <button class="nav-link active" id="account-tab" data-bs-toggle="pill" data-bs-target="#account" type="button" role="tab">
                            <i class="fas fa-user me-2"></i>Tài khoản
                        </button>
                        <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
                            <i class="fas fa-shield-alt me-2"></i>Bảo mật
                        </button>
                        <button class="nav-link" id="preferences-tab" data-bs-toggle="pill" data-bs-target="#preferences" type="button" role="tab">
                            <i class="fas fa-sliders-h me-2"></i>Tùy chọn
                        </button>
                        <button class="nav-link" id="notifications-tab" data-bs-toggle="pill" data-bs-target="#notifications" type="button" role="tab">
                            <i class="fas fa-bell me-2"></i>Thông báo
                        </button>
                        <button class="nav-link text-danger" id="danger-tab" data-bs-toggle="pill" data-bs-target="#danger" type="button" role="tab">
                            <i class="fas fa-exclamation-triangle me-2"></i>Nguy hiểm
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="col-lg-9">
            <div class="tab-content" id="settings-content">
                
                <!-- Account Tab -->
                <div class="tab-pane fade show active" id="account" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-user me-2 text-primary"></i>Thông tin tài khoản
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <strong>Tên đăng nhập:</strong>
                                </div>
                                <div class="col-md-9">
                                    <span class="text-muted"><?php echo htmlspecialchars($user['username']); ?></span>
                                    <small class="text-muted d-block">Không thể thay đổi</small>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <strong>Loại tài khoản:</strong>
                                </div>
                                <div class="col-md-9">
                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                        <?php echo $user['role'] === 'admin' ? 'Quản trị viên' : 'Học viên'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <strong>Ngày tham gia:</strong>
                                </div>
                                <div class="col-md-9">
                                    <span class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <form method="POST" id="emailForm">
                                <input type="hidden" name="action" value="update_email">
                                
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <small class="text-muted">Email dùng để đăng nhập và nhận thông báo</small>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Cập nhật email
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-shield-alt me-2 text-success"></i>Bảo mật tài khoản
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="passwordForm">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu hiện tại *</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Mật khẩu mới *</label>
                                        <input type="password" class="form-control" name="new_password" 
                                               id="newPassword" minlength="6" required>
                                        <small class="text-muted">Tối thiểu 6 ký tự</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Xác nhận mật khẩu *</label>
                                        <input type="password" class="form-control" name="confirm_password" 
                                               id="confirmPassword" required>
                                    </div>
                                </div>
                                
                                <div class="password-strength mb-3">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" id="passwordStrength" style="width: 0%"></div>
                                    </div>
                                    <small class="text-muted" id="strengthText">Độ mạnh mật khẩu</small>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted small">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Cập nhật lần cuối: <?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-key me-2"></i>Đổi mật khẩu
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div class="tab-pane fade" id="preferences" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-sliders-h me-2 text-info"></i>Tùy chọn giao diện
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="preferencesForm">
                                <input type="hidden" name="action" value="update_preferences">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ngôn ngữ</label>
                                        <select class="form-select" name="language">
                                            <option value="vi" <?php echo $preferences['language'] === 'vi' ? 'selected' : ''; ?>>
                                                🇻🇳 Tiếng Việt
                                            </option>
                                            <option value="en" <?php echo $preferences['language'] === 'en' ? 'selected' : ''; ?>>
                                                🇺🇸 English
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Giao diện</label>
                                        <select class="form-select" name="theme">
                                            <option value="light" <?php echo $preferences['theme'] === 'light' ? 'selected' : ''; ?>>
                                                ☀️ Sáng
                                            </option>
                                            <option value="dark" <?php echo $preferences['theme'] === 'dark' ? 'selected' : ''; ?>>
                                                🌙 Tối
                                            </option>
                                            <option value="auto" <?php echo $preferences['theme'] === 'auto' ? 'selected' : ''; ?>>
                                                🔄 Tự động
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-save me-2"></i>Lưu tùy chọn
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div class="tab-pane fade" id="notifications" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-bell me-2 text-warning"></i>Thông báo
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="notificationsForm">
                                <input type="hidden" name="action" value="update_preferences">
                                <input type="hidden" name="language" value="<?php echo $preferences['language']; ?>">
                                <input type="hidden" name="theme" value="<?php echo $preferences['theme']; ?>">
                                
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="email_notifications" 
                                               id="emailNotif" <?php echo $preferences['email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="emailNotif">
                                            <strong>Thông báo qua Email</strong>
                                            <br><small class="text-muted">Nhận thông báo về khóa học, bài tập mới</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="course_reminders" 
                                               id="courseReminder" <?php echo $preferences['course_reminders'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="courseReminder">
                                            <strong>Nhắc nhở học tập</strong>
                                            <br><small class="text-muted">Nhắc nhở tiếp tục học các khóa học đã đăng ký</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="marketing_emails" 
                                               id="marketingEmail" <?php echo $preferences['marketing_emails'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="marketingEmail">
                                            <strong>Email Marketing</strong>
                                            <br><small class="text-muted">Nhận thông tin về khóa học mới, khuyến mãi</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save me-2"></i>Lưu cài đặt
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone Tab -->
                <div class="tab-pane fade" id="danger" role="tabpanel">
                    <div class="card border-danger shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Vùng nguy hiểm
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-danger">
                                <i class="fas fa-warning me-2"></i>
                                <strong>Cảnh báo:</strong> Các hành động trong phần này không thể hoàn tác!
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="text-danger">Xóa tài khoản vĩnh viễn</h6>
                                <p class="text-muted">
                                    Xóa tài khoản sẽ làm mất tất cả dữ liệu học tập, tiến độ, chứng chỉ và không thể khôi phục.
                                </p>
                                
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-trash me-2"></i>Xóa tài khoản
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Xác nhận xóa tài khoản
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_account">
                    
                    <div class="alert alert-danger">
                        <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!
                        <br>• Tất cả tiến độ học tập sẽ bị mất
                        <br>• Chứng chỉ sẽ không còn hiệu lực
                        <br>• Không thể khôi phục tài khoản
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nhập mật khẩu để xác nhận</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Gõ <code>XÓA TÀI KHOẢN</code> để xác nhận</label>
                        <input type="text" class="form-control" name="confirm_text" 
                               placeholder="XÓA TÀI KHOẢN" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa vĩnh viễn
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.user-avatar-large {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    color: white;
    margin-left: auto;
}

.nav-pills .nav-link {
    border-radius: 0;
    border: none;
    text-align: left;
    padding: 1rem 1.5rem;
    color: #666;
    transition: all 0.3s ease;
}

.nav-pills .nav-link:hover {
    background: #f8f9fa;
    color: #333;
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.nav-pills .nav-link.text-danger:hover {
    background: #fee;
    color: #dc3545;
}

.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
}

.password-strength .progress-bar {
    transition: all 0.3s ease;
}

.strength-weak { background-color: #dc3545; }
.strength-medium { background-color: #ffc107; }
.strength-strong { background-color: #28a745; }

@media (max-width: 768px) {
    .user-avatar-large {
        width: 60px;
        height: 60px;
        font-size: 1.2rem;
        margin: 1rem auto 0;
    }
}
</style>

<script>
// Password strength checker
document.getElementById('newPassword')?.addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('strengthText');
    
    let strength = 0;
    let text = 'Rất yếu';
    let className = 'strength-weak';
    
    if (password.length >= 6) strength += 20;
    if (password.match(/[a-z]/)) strength += 20;
    if (password.match(/[A-Z]/)) strength += 20;
    if (password.match(/[0-9]/)) strength += 20;
    if (password.match(/[^a-zA-Z0-9]/)) strength += 20;
    
    if (strength >= 80) {
        text = 'Rất mạnh';
        className = 'strength-strong';
    } else if (strength >= 60) {
        text = 'Mạnh';
        className = 'strength-strong';
    } else if (strength >= 40) {
        text = 'Trung bình';
        className = 'strength-medium';
    } else if (strength >= 20) {
        text = 'Yếu';
        className = 'strength-medium';
    }
    
    strengthBar.style.width = strength + '%';
    strengthBar.className = 'progress-bar ' + className;
    strengthText.textContent = text;
});

// Confirm password validation
document.getElementById('confirmPassword')?.addEventListener('input', function() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && newPassword !== confirmPassword) {
        this.setCustomValidity('Mật khẩu xác nhận không khớp');
    } else {
        this.setCustomValidity('');
    }
});

// Auto-dismiss alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        if (alert.querySelector('.btn-close')) {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        }
    });
}, 5000);

// Form submission confirmation
document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const actionInput = form.querySelector('input[name="action"]');
        if (actionInput && actionInput.value === 'change_password') {
            if (!confirm('Bạn có chắc muốn đổi mật khẩu không?')) {
                e.preventDefault();
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>