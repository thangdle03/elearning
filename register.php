<?php

require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . '/my-courses.php');
}

$page_title = 'Đăng ký';
$error = '';
$errors = [];

if ($_POST) {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($username)) {
        $errors['username'] = 'Vui lòng nhập tên đăng nhập!';
    } elseif (strlen($username) < 3) {
        $errors['username'] = 'Tên đăng nhập phải có ít nhất 3 ký tự!';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới!';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Vui lòng nhập email!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ!';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Vui lòng nhập mật khẩu!';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự!';
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Xác nhận mật khẩu không khớp!';
    }
    
    if (!$terms) {
        $errors['terms'] = 'Bạn phải đồng ý với điều khoản sử dụng!';
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Tên đăng nhập hoặc email đã được sử dụng!';
        }
    }
    
    // Create account if no errors
    if (empty($errors) && empty($error)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, created_at) 
                VALUES (?, ?, ?, 'student', NOW())
            ");
            $stmt->execute([$username, $email, $hashed_password]);
            
            // Set success message and redirect to login
            $_SESSION['register_success'] = 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.';
            redirect(SITE_URL . '/login.php');
            
        } catch (Exception $e) {
            $error = 'Có lỗi xảy ra trong quá trình đăng ký. Vui lòng thử lại!';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <!-- Header -->
                    <div class="text-center mb-4">
                        <div class="mb-3">
                            <i class="bi bi-person-plus-fill display-4 text-success"></i>
                        </div>
                        <h2 class="fw-bold">Đăng ký</h2>
                        <p class="text-muted">Tạo tài khoản mới để bắt đầu học tập</p>
                    </div>
                    
                    <!-- General Error -->
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Registration Form -->
                    <form method="POST" id="registerForm" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" 
                                       class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                       id="username" name="username" 
                                       placeholder="Nhập tên đăng nhập"
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required>
                            </div>
                            <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback d-block">
                                <i class="bi bi-exclamation-circle me-1"></i><?php echo $errors['username']; ?>
                            </div>
                            <?php endif; ?>
                            <small class="text-muted">Chỉ được sử dụng chữ cái, số và dấu gạch dưới</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" 
                                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                       id="email" name="email" 
                                       placeholder="Nhập địa chỉ email"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                       required>
                            </div>
                            <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback d-block">
                                <i class="bi bi-exclamation-circle me-1"></i><?php echo $errors['email']; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                       id="password" name="password" 
                                       placeholder="Nhập mật khẩu" 
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback d-block">
                                <i class="bi bi-exclamation-circle me-1"></i><?php echo $errors['password']; ?>
                            </div>
                            <?php endif; ?>
                            <small class="text-muted">Tối thiểu 6 ký tự</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock-fill"></i>
                                </span>
                                <input type="password" 
                                       class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                       id="confirm_password" name="confirm_password" 
                                       placeholder="Nhập lại mật khẩu" 
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback d-block">
                                <i class="bi bi-exclamation-circle me-1"></i><?php echo $errors['confirm_password']; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" 
                                       class="form-check-input <?php echo isset($errors['terms']) ? 'is-invalid' : ''; ?>" 
                                       id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    Tôi đồng ý với 
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">
                                        điều khoản sử dụng
                                    </a> 
                                    và 
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">
                                        chính sách bảo mật
                                    </a>
                                </label>
                                <?php if (isset($errors['terms'])): ?>
                                <div class="invalid-feedback d-block">
                                    <i class="bi bi-exclamation-circle me-1"></i><?php echo $errors['terms']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="registerBtn">
                                <i class="bi bi-person-plus me-2"></i>Tạo tài khoản
                            </button>
                        </div>
                    </form>
                    
                    <!-- Divider -->
                    <hr class="my-4">
                    
                    <!-- Login Link -->
                    <div class="text-center">
                        <p class="mb-0">
                            Đã có tài khoản? 
                            <a href="<?php echo SITE_URL; ?>/login.php" class="text-decoration-none fw-bold">
                                Đăng nhập ngay
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

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Điều khoản sử dụng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Chấp nhận điều khoản</h6>
                <p>Bằng việc sử dụng dịch vụ của chúng tôi, bạn đồng ý tuân thủ các điều khoản này.</p>
                
                <h6>2. Tài khoản người dùng</h6>
                <p>Bạn có trách nhiệm bảo mật thông tin tài khoản và mật khẩu của mình.</p>
                
                <h6>3. Sử dụng hợp pháp</h6>
                <p>Nghiêm cấm sử dụng dịch vụ cho các mục đích bất hợp pháp hoặc vi phạm quyền của người khác.</p>
                
                <h6>4. Nội dung</h6>
                <p>Tất cả nội dung khóa học được bảo vệ bởi bản quyền. Không được sao chép, phân phối mà không có sự cho phép.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chính sách bảo mật</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Thu thập thông tin</h6>
                <p>Chúng tôi chỉ thu thập thông tin cần thiết để cung cấp dịch vụ tốt nhất.</p>
                
                <h6>Sử dụng thông tin</h6>
                <p>Thông tin của bạn được sử dụng để:</p>
                <ul>
                    <li>Cung cấp và cải thiện dịch vụ</li>
                    <li>Gửi thông báo quan trọng</li>
                    <li>Hỗ trợ khách hàng</li>
                </ul>
                
                <h6>Bảo mật</h6>
                <p>Chúng tôi cam kết bảo vệ thông tin cá nhân của bạn bằng các biện pháp bảo mật tiên tiến.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
// Toggle password visibility
function setupPasswordToggle(passwordId, toggleId) {
    document.getElementById(toggleId).addEventListener('click', function() {
        const password = document.getElementById(passwordId);
        const icon = this.querySelector('i');
        
        if (password.type === 'password') {
            password.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            password.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
}

setupPasswordToggle('password', 'togglePassword');
setupPasswordToggle('confirm_password', 'toggleConfirmPassword');

// Form validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('registerBtn');
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Check password match
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Mật khẩu xác nhận không khớp!');
        return;
    }
    
    // Show loading state
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang tạo tài khoản...';
    btn.disabled = true;
});

// Real-time password confirmation check
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// Auto focus on username field
document.getElementById('username').focus();
</script>

<?php include 'includes/footer.php'; ?>