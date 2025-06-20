<?php
// filepath: d:\Xampp\htdocs\elearning\register.php


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
    
    // Enhanced password validation with max length limit
    if (empty($password)) {
        $errors['password'] = 'Vui lòng nhập mật khẩu!';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 8 ký tự!';
    } elseif (strlen($password) > 255) {
        $errors['password'] = 'Mật khẩu không được vượt quá 255 ký tự!';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
        $errors['password'] = 'Mật khẩu phải chứa ít nhất: 1 chữ thường, 1 chữ hoa, 1 số và 1 ký tự đặc biệt (@$!%*?&)!';
    } elseif (preg_match('/^(password|123456|qwerty|admin|user)/i', $password)) {
        $errors['password'] = 'Mật khẩu quá đơn giản, vui lòng chọn mật khẩu khác!';
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
                            
                            <!-- Password Strength Indicator -->
                            <!-- <div class="mt-2">
                                <div class="password-strength">
                                    <div class="strength-bar">
                                        <div class="strength-fill" id="strengthFill"></div>
                                    </div>
                                    <small class="strength-text" id="strengthText">Độ mạnh mật khẩu</small>
                                </div>
                            </div>
                             -->
                            <!-- Password Requirements -->
                            <!-- <div class="password-requirements mt-2">
                                <small class="text-muted d-block mb-1"><strong>Yêu cầu mật khẩu:</strong></small>
                                <ul class="requirements-list">
                                    <li id="req-length" class="requirement">
                                        <i class="bi bi-x-circle text-danger"></i>
                                        <span>8-255 ký tự</span>
                                    </li>
                                    <li id="req-lowercase" class="requirement">
                                        <i class="bi bi-x-circle text-danger"></i>
                                        <span>Có chữ thường (a-z)</span>
                                    </li>
                                    <li id="req-uppercase" class="requirement">
                                        <i class="bi bi-x-circle text-danger"></i>
                                        <span>Có chữ hoa (A-Z)</span>
                                    </li>
                                    <li id="req-number" class="requirement">
                                        <i class="bi bi-x-circle text-danger"></i>
                                        <span>Có số (0-9)</span>
                                    </li>
                                    <li id="req-special" class="requirement">
                                        <i class="bi bi-x-circle text-danger"></i>
                                        <span>Có ký tự đặc biệt (@$!%*?&)</span>
                                    </li>
                                </ul>
                            </div> -->
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
                            <div id="password-match-feedback" class="mt-1"></div>
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
                            <button type="submit" class="btn btn-success btn-lg" id="registerBtn" disabled>
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

<!-- CSS for Password Strength -->
<style>
.password-strength {
    margin-top: 0.5rem;
}

.strength-bar {
    height: 4px;
    background-color: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 0.25rem;
}

.strength-fill {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.strength-fill.weak {
    background-color: #dc3545;
    width: 25%;
}

.strength-fill.fair {
    background-color: #fd7e14;
    width: 50%;
}

.strength-fill.good {
    background-color: #ffc107;
    width: 75%;
}

.strength-fill.strong {
    background-color: #28a745;
    width: 100%;
}

.strength-text {
    font-size: 0.75rem;
    font-weight: 500;
}

.strength-text.weak {
    color: #dc3545;
}

.strength-text.fair {
    color: #fd7e14;
}

.strength-text.good {
    color: #ffc107;
}

.strength-text.strong {
    color: #28a745;
}

.requirements-list {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.75rem;
}

.requirement {
    display: flex;
    align-items: center;
    margin-bottom: 0.25rem;
}

.requirement i {
    margin-right: 0.5rem;
    font-size: 0.8rem;
}

.requirement.valid i {
    color: #28a745 !important;
}

.requirement.valid i:before {
    content: "\f26b";
}

#password-match-feedback {
    font-size: 0.8rem;
}

.match-success {
    color: #28a745;
}

.match-error {
    color: #dc3545;
}

/* Add this CSS to make button states clearer */
.btn-secondary {
    background-color: #6c757d;
    border-color: #6c757d;
    opacity: 0.6;
}

.btn-success {
    background-color: #198754;
    border-color: #198754;
}

.btn:disabled {
    cursor: not-allowed;
}
</style>

<!-- Simplified Validation JavaScript -->
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

// Validation functions
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const existingError = field.parentNode.parentNode.querySelector('.validation-error');
    
    // Remove existing error
    if (existingError) {
        existingError.remove();
    }
    
    // Add error class and message
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'validation-error text-danger mt-1';
    errorDiv.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>' + message;
    
    field.parentNode.parentNode.appendChild(errorDiv);
}

function showFieldSuccess(fieldId) {
    const field = document.getElementById(fieldId);
    const existingError = field.parentNode.parentNode.querySelector('.validation-error');
    
    // Remove existing error
    if (existingError) {
        existingError.remove();
    }
    
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
}

function clearFieldValidation(fieldId) {
    const field = document.getElementById(fieldId);
    const existingError = field.parentNode.parentNode.querySelector('.validation-error');
    
    if (existingError) {
        existingError.remove();
    }
    
    field.classList.remove('is-invalid', 'is-valid');
}

// Username validation
document.getElementById('username').addEventListener('input', function() {
    const username = this.value.trim();
    
    if (username === '') {
        clearFieldValidation('username');
        return;
    }
    
    if (username.length < 3) {
        showFieldError('username', 'Tên đăng nhập phải có ít nhất 3 ký tự');
        return;
    }
    
    if (username.length > 20) {
        showFieldError('username', 'Tên đăng nhập không được quá 20 ký tự');
        return;
    }
    
    if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        showFieldError('username', 'Chỉ được sử dụng chữ cái, số và dấu gạch dưới');
        return;
    }
    
    // Check reserved usernames
    const reserved = ['admin', 'administrator', 'root', 'user', 'guest', 'support', 'help'];
    if (reserved.includes(username.toLowerCase())) {
        showFieldError('username', 'Tên đăng nhập này đã được hệ thống sử dụng');
        return;
    }
    
    showFieldSuccess('username');
    updateSubmitButton();
});

// Email validation
document.getElementById('email').addEventListener('input', function() {
    const email = this.value.trim();
    
    if (email === '') {
        clearFieldValidation('email');
        return;
    }
    
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showFieldError('email', 'Định dạng email không hợp lệ');
        return;
    }
    
    showFieldSuccess('email');
    updateSubmitButton();
});

// Password validation
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    
    if (password === '') {
        clearFieldValidation('password');
        return;
    }
    
    // Check length
    if (password.length < 8) {
        showFieldError('password', 'Mật khẩu phải có ít nhất 8 ký tự');
        return;
    }
    
    if (password.length > 255) {
        showFieldError('password', 'Mật khẩu không được quá 255 ký tự');
        return;
    }
    
    // Check lowercase
    if (!/[a-z]/.test(password)) {
        showFieldError('password', 'Mật khẩu phải có ít nhất 1 chữ thường (a-z)');
        return;
    }
    
    // Check uppercase
    if (!/[A-Z]/.test(password)) {
        showFieldError('password', 'Mật khẩu phải có ít nhất 1 chữ hoa (A-Z)');
        return;
    }
    
    // Check number
    if (!/\d/.test(password)) {
        showFieldError('password', 'Mật khẩu phải có ít nhất 1 số (0-9)');
        return;
    }
    
    // Check special character
    if (!/[@$!%*?&]/.test(password)) {
        showFieldError('password', 'Mật khẩu phải có ít nhất 1 ký tự đặc biệt (@$!%*?&)');
        return;
    }
    
    // Check common passwords
    const commonPasswords = ['password', '123456', 'qwerty', 'admin', 'user', 'welcome'];
    if (commonPasswords.some(common => password.toLowerCase().includes(common))) {
        showFieldError('password', 'Mật khẩu quá đơn giản, vui lòng chọn mật khẩu khác');
        return;
    }
    
    showFieldSuccess('password');
    
    // Also validate confirm password if it has value
    const confirmPassword = document.getElementById('confirm_password').value;
    if (confirmPassword) {
        document.getElementById('confirm_password').dispatchEvent(new Event('input'));
    }
    
    updateSubmitButton();
});

// Confirm password validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword === '') {
        clearFieldValidation('confirm_password');
        return;
    }
    
    if (password !== confirmPassword) {
        showFieldError('confirm_password', 'Mật khẩu xác nhận không khớp');
        return;
    }
    
    showFieldSuccess('confirm_password');
    updateSubmitButton();
});

// Terms checkbox validation
document.getElementById('terms').addEventListener('change', function() {
    const termsError = document.querySelector('.terms-error');
    if (termsError) {
        termsError.remove();
    }
    
    if (!this.checked) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'terms-error text-danger mt-1';
        errorDiv.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Bạn phải đồng ý với điều khoản sử dụng';
        
        this.parentNode.parentNode.appendChild(errorDiv);
    }
    
    updateSubmitButton();
});

// Update submit button state
function updateSubmitButton() {
    const username = document.getElementById('username');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const terms = document.getElementById('terms');
    const submitBtn = document.getElementById('registerBtn');
    
    // Check if all fields are valid
    const isValid = username.classList.contains('is-valid') &&
                   email.classList.contains('is-valid') &&
                   password.classList.contains('is-valid') &&
                   confirmPassword.classList.contains('is-valid') &&
                   terms.checked;
    
    if (isValid) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn-secondary');
        submitBtn.classList.add('btn-success');
    } else {
        submitBtn.disabled = true;
        submitBtn.classList.remove('btn-success');
        submitBtn.classList.add('btn-secondary');
    }
}

// Form submission validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('registerBtn');
    
    // Final validation before submit
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const terms = document.getElementById('terms').checked;
    
    let hasErrors = false;
    
    // Validate all fields one more time
    if (!username || username.length < 3 || !/^[a-zA-Z0-9_]+$/.test(username)) {
        document.getElementById('username').dispatchEvent(new Event('input'));
        hasErrors = true;
    }
    
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('email').dispatchEvent(new Event('input'));
        hasErrors = true;
    }
    
    if (!password || password.length < 8 || !/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/.test(password)) {
        document.getElementById('password').dispatchEvent(new Event('input'));
        hasErrors = true;
    }
    
    if (password !== confirmPassword) {
        document.getElementById('confirm_password').dispatchEvent(new Event('input'));
        hasErrors = true;
    }
    
    if (!terms) {
        document.getElementById('terms').dispatchEvent(new Event('change'));
        hasErrors = true;
    }
    
    if (hasErrors) {
        e.preventDefault();
        
        // Show general error message
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>
            Vui lòng sửa các lỗi được đánh dấu bên dưới
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const form = document.getElementById('registerForm');
        form.insertAdjacentElement('beforebegin', alertDiv);
        
        // Scroll to first error
        const firstError = document.querySelector('.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        return;
    }
    
    // Show loading state
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang tạo tài khoản...';
    btn.disabled = true;
});

// Auto focus on first field
document.getElementById('username').focus();

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateSubmitButton();
});
</script>

<!-- Additional CSS for validation styling -->
<style>
.validation-error {
    font-size: 0.875rem;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-control.is-valid {
    border-color: #198754;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.94-.94 2.94 2.94L8.5 6.4l-.94-.94L5.23 7.8 2.3 6.73z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-control.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m9 3-6 6m0-6 6 6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.btn:disabled {
    cursor: not-allowed;
}

.btn-secondary {
    opacity: 0.6;
}
</style>

<?php include 'includes/footer.php'; ?>