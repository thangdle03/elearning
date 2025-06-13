<?php

require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(SITE_URL . '/admin/');
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

if ($_POST) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    // Validation
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin!';
    } else {
        // Check user credentials
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            
            // Remember me functionality
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
                // In production, save this token to database
            }
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                redirect(SITE_URL . '/admin/');
            } else {
                // Redirect to intended page or my-courses
                $redirect_to = isset($_GET['redirect']) ? $_GET['redirect'] : '/my-courses.php';
                redirect(SITE_URL . $redirect_to);
            }
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng!';
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
                            <i class="bi bi-person-circle display-4 text-primary"></i>
                        </div>
                        <h2 class="fw-bold">Đăng nhập</h2>
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
                    <form method="POST" id="loginForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">Tên đăng nhập hoặc Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Nhập tên đăng nhập hoặc email"
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Mật khẩu</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Nhập mật khẩu" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Ghi nhớ đăng nhập
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                            </button>
                        </div>
                    </form>
                    
                    <!-- Divider -->
                    <hr class="my-4">
                    
                    <!-- Register Link -->
                    <div class="text-center">
                        <p class="mb-0">
                            Chưa có tài khoản? 
                            <a href="<?php echo SITE_URL; ?>/register.php" class="text-decoration-none fw-bold">
                                Đăng ký ngay
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

<!-- JavaScript -->
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
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
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
}

// Auto focus on username field
document.getElementById('username').focus();
</script>

<?php include 'includes/footer.php'; ?>