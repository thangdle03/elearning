<?php
// filepath: d:\Xampp\htdocs\elearning\login.php


require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(SITE_URL . '/admin/dashboard.php');
    } else {
        redirect(SITE_URL . '/my-courses.php');
    }
}

$page_title = 'Đăng nhập';
$error = '';
$success = '';

// Check for registration success message
if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

// Check for password reset success message
if (isset($_SESSION['password_reset_success'])) {
    $success = $_SESSION['password_reset_success'];
    unset($_SESSION['password_reset_success']);
}

// Rate limiting for login attempts
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 minutes

if ($_POST) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    // Check rate limiting
    $ip = $_SERVER['REMOTE_ADDR'];
    $current_time = time();
    
    // In production, store this in database or cache
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    // Clean old attempts
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($attempt) use ($current_time, $lockout_time) {
        return ($current_time - $attempt) < $lockout_time;
    });
    
    // Check if locked out
    if (count($_SESSION['login_attempts']) >= $max_attempts) {
        $error = 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút.';
    } else {
        // Validation
        if (empty($username) || empty($password)) {
            $error = 'Vui lòng nhập đầy đủ thông tin!';
        } else {
            // Check user credentials
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful - clear login attempts
                $_SESSION['login_attempts'] = [];
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_name'] = $user['username']; // Use username since no full_name column
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['login_time'] = time();
                
                // Skip remember me functionality for now (requires new columns)
                /*
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + (86400 * 30); // 30 days
                    
                    setcookie('remember_token', $token, $expires, '/', '', true, true);
                    
                    $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_expires = ? WHERE id = ?");
                    $stmt->execute([$token, date('Y-m-d H:i:s', $expires), $user['id']]);
                }
                */
                
                // Skip last login update for now (requires new column)
                /*
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                */
                
                // Just update the updated_at column that already exists
                $stmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    redirect(SITE_URL . '/admin/dashboard.php');
                } else {
                    $redirect_to = isset($_GET['redirect']) ? $_GET['redirect'] : '/my-courses.php';
                    redirect(SITE_URL . $redirect_to);
                }
            } else {
                // Login failed - record attempt
                $_SESSION['login_attempts'][] = $current_time;
                
                $remaining_attempts = $max_attempts - count($_SESSION['login_attempts']);
                if ($remaining_attempts > 0) {
                    $error = "Tên đăng nhập hoặc mật khẩu không đúng! Còn lại {$remaining_attempts} lần thử.";
                } else {
                    $error = 'Quá nhiều lần đăng nhập thất bại. Tài khoản tạm thời bị khóa 15 phút.';
                }
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <!-- Header -->
                    <div class="text-center mb-4">
                        <div class="mb-3">
                            <div class="login-icon">
                                <i class="bi bi-person-circle display-4 text-primary"></i>
                            </div>
                        </div>
                        <h2 class="fw-bold text-dark">Đăng nhập</h2>
                        <p class="text-muted">Chào mừng trở lại! Vui lòng đăng nhập vào tài khoản của bạn.</p>
                    </div>
                    
                    <!-- Success Message -->
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Error Message -->
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    

                    
                    <!-- Login Form -->
                    <form method="POST" id="loginForm" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                Tên đăng nhập hoặc Email
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Nhập tên đăng nhập hoặc email"
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       autocomplete="username"
                                       required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Mật khẩu
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Nhập mật khẩu"
                                       autocomplete="current-password"
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Ghi nhớ đăng nhập
                                    </label>
                                </div>
                                <a href="#" class="text-decoration-none small" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                                    <i class="bi bi-key me-1"></i>Quên mật khẩu?
                                </a>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                            </button>
                        </div>
                    </form>
                    
                    <!-- Divider -->
                    <div class="divider my-4">
                        <hr class="hr-text" data-content="hoặc">
                    </div>
                    
                    <!-- Register Link -->
                    <div class="text-center">
                        <p class="mb-0">
                            Chưa có tài khoản? 
                            <a href="<?php echo SITE_URL; ?>/register.php" class="text-decoration-none fw-bold">
                                <i class="bi bi-person-plus me-1"></i>Đăng ký ngay
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Back to Home -->
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Về trang chủ
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="forgotPasswordModalLabel">
                    <i class="bi bi-key me-2"></i>Quên mật khẩu
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="forgotPasswordForm">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-envelope-paper display-4 text-primary"></i>
                        <p class="text-muted mt-2">
                            Nhập email của bạn và chúng tôi sẽ gửi liên kết đặt lại mật khẩu.
                        </p>
                    </div>
                    
                    <div id="forgotPasswordAlert"></div>
                    
                    <div class="mb-3">
                        <label for="forgot_email" class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" 
                                   class="form-control" 
                                   id="forgot_email" 
                                   name="forgot_email" 
                                   placeholder="Nhập địa chỉ email"
                                   required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Hủy
                    </button>
                    <button type="submit" class="btn btn-primary" id="sendResetBtn">
                        <i class="bi bi-send me-1"></i>Gửi liên kết
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enhanced CSS -->
<style>
.login-icon {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.demo-accounts {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px;
    border-left: 4px solid #0d6efd;
}

.hr-text {
    position: relative;
    border: none;
    height: 1px;
    background: linear-gradient(to right, transparent, #dee2e6, transparent);
}

.hr-text:before {
    content: attr(data-content);
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 0 15px;
    color: #6c757d;
    font-size: 0.875rem;
}

.card {
    transition: all 0.3s ease;
    border-radius: 15px;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.input-group-text {
    background: #f8f9fa;
    border-right: none;
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(13, 110, 253, 0.3);
}

.modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    border-radius: 15px 15px 0 0;
}

#forgotPasswordAlert {
    min-height: 0;
    transition: all 0.3s ease;
}
</style>

<!-- Enhanced JavaScript -->
<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (password.type === 'password') {
        password.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        password.type = 'password';
        icon.className = 'bi bi-eye';
    }
});

// Form submission with loading state
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('loginBtn');
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    // Basic validation
    if (!username || !password) {
        e.preventDefault();
        showAlert('Vui lòng nhập đầy đủ thông tin!', 'danger');
        return;
    }
    
    // Show loading state
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang đăng nhập...';
    btn.disabled = true;
});

// Fill demo credentials
function fillDemo(type) {
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    
    if (type === 'admin') {
        username.value = 'admin';
        password.value = 'admin123';
    } else {
        username.value = 'student1';
        password.value = 'student123';
    }
    
    // Add visual feedback
    username.classList.add('border-success');
    password.classList.add('border-success');
    
    setTimeout(() => {
        username.classList.remove('border-success');
        password.classList.remove('border-success');
    }, 2000);
}

// Forgot password form submission
document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const email = document.getElementById('forgot_email').value.trim();
    const btn = document.getElementById('sendResetBtn');
    const alertDiv = document.getElementById('forgotPasswordAlert');
    
    if (!email) {
        showForgotPasswordAlert('Vui lòng nhập địa chỉ email!', 'danger');
        return;
    }
    
    if (!isValidEmail(email)) {
        showForgotPasswordAlert('Địa chỉ email không hợp lệ!', 'danger');
        return;
    }
    
    // Show loading state
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi...';
    btn.disabled = true;
    
    // Simulate API call
    fetch('api/forgot-password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showForgotPasswordAlert(
                'Liên kết đặt lại mật khẩu đã được gửi đến email của bạn. Vui lòng kiểm tra hộp thư!', 
                'success'
            );
            document.getElementById('forgot_email').value = '';
        } else {
            showForgotPasswordAlert(data.message || 'Có lỗi xảy ra. Vui lòng thử lại!', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showForgotPasswordAlert('Có lỗi xảy ra. Vui lòng thử lại!', 'danger');
    })
    .finally(() => {
        // Reset button state
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Gửi liên kết';
        btn.disabled = false;
    });
});

// Utility functions
function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const form = document.getElementById('loginForm');
    form.insertAdjacentHTML('beforebegin', alertHtml);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        }
    }, 5000);
}

function showForgotPasswordAlert(message, type) {
    const alertDiv = document.getElementById('forgotPasswordAlert');
    alertDiv.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Auto focus on username field
document.getElementById('username').focus();

// Add Enter key support
document.getElementById('username').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('password').focus();
    }
});

// Form validation feedback
document.getElementById('username').addEventListener('blur', function() {
    if (this.value.trim() === '') {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    }
});

document.getElementById('password').addEventListener('blur', function() {
    if (this.value === '') {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    }
});

// Reset modal state when closed
document.getElementById('forgotPasswordModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('forgotPasswordForm').reset();
    document.getElementById('forgotPasswordAlert').innerHTML = '';
    document.getElementById('sendResetBtn').innerHTML = '<i class="bi bi-send me-1"></i>Gửi liên kết';
    document.getElementById('sendResetBtn').disabled = false;
});
</script>

<?php include 'includes/footer.php'; ?>