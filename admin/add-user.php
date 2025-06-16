<?php
// filepath: d:\Xampp\htdocs\elearning\admin\add-user.php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Thêm người dùng mới';
$current_page = 'users';

// Initialize variables
$message = '';
$error = '';
$form_data = [
    'username' => '',
    'email' => '',
    'role' => 'student'
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $password = '12345678'; // Default password

    // Store form data for re-display on error
    $form_data = [
        'username' => $username,
        'email' => $email,
        'role' => $role
    ];

    // Validation
    $errors = [];

    if (empty($username)) {
        $errors[] = 'Tên đăng nhập không được để trống';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Tên đăng nhập phải có ít nhất 3 ký tự';
    } elseif (strlen($username) > 50) {
        $errors[] = 'Tên đăng nhập không được quá 50 ký tự';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới';
    }

    if (empty($email)) {
        $errors[] = 'Email không được để trống';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email không được quá 100 ký tự';
    }

    if (!in_array($role, ['admin', 'student'])) {
        $errors[] = 'Vai trò không hợp lệ';
    }

    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_user) {
                if ($existing_user['username'] === $username) {
                    $errors[] = 'Tên đăng nhập đã tồn tại';
                }
                if ($existing_user['email'] === $email) {
                    $errors[] = 'Email đã được sử dụng';
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Lỗi kiểm tra dữ liệu: ' . $e->getMessage();
        }
    }

    // Insert new user if no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([$username, $email, $hashed_password, $role]);

            if ($result) {
                $user_id = $pdo->lastInsertId();

                // Simple activity log without function dependency
                try {
                    // Get current admin ID from session instead of getCurrentUser()
                    $admin_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

                    // Check if activity_logs table exists before inserting
                    $table_check = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
                    if ($table_check->rowCount() > 0) {
                        $log_stmt = $pdo->prepare("
                            INSERT INTO activity_logs (user_id, action, description, created_at) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        $log_stmt->execute([
                            $admin_id,
                            'user_created',
                            "Admin tạo người dùng mới: $username (ID: $user_id)"
                        ]);
                    }
                } catch (Exception $e) {
                    // Log error but don't stop the process
                    error_log("Activity log error: " . $e->getMessage());
                }

                $message = "✅ Đã tạo thành công người dùng mới!\n";
                $message .= "👤 Tên đăng nhập: $username\n";
                $message .= "📧 Email: $email\n";
                $message .= "🔑 Mật khẩu mặc định: $password\n";
                $message .= "👥 Vai trò: " . ucfirst($role) . "\n";
                $message .= "🎯 ID: #$user_id";

                $_SESSION['add_user_success'] = $message;
                redirect('users.php');
            } else {
                $error = 'Không thể tạo người dùng. Vui lòng thử lại!';
            }
        } catch (Exception $e) {
            $error = 'Lỗi tạo người dùng: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get statistics for info display
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
            COUNT(CASE WHEN role = 'student' THEN 1 END) as student_count,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_week
        FROM users
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [
        'total_users' => 0,
        'admin_count' => 0,
        'student_count' => 0,
        'new_users_week' => 0
    ];
}
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <div style="white-space: pre-line;"><?php echo htmlspecialchars($message); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <div><?php echo $error; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-user-plus me-2"></i><?php echo $page_title; ?>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="users.php">Quản lý người dùng</a></li>
                    <li class="breadcrumb-item active">Thêm người dùng</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="users.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Add User Form -->
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-user-plus me-2"></i>Thông tin người dùng mới
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="addUserForm" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-1"></i>Tên đăng nhập <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="username" name="username"
                                    value="<?php echo htmlspecialchars($form_data['username']); ?>"
                                    required autocomplete="off">
                                <div class="form-text">
                                    Chỉ được chứa chữ cái, số và dấu gạch dưới. Độ dài 3-50 ký tự.
                                </div>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-1"></i>Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                    required autocomplete="off">
                                <div class="form-text">
                                    Email phải là địa chỉ hợp lệ và chưa được sử dụng.
                                </div>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="col-md-6">
                                <label for="role" class="form-label">
                                    <i class="fas fa-user-shield me-1"></i>Vai trò <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="student" <?php echo $form_data['role'] === 'student' ? 'selected' : ''; ?>>
                                        👨‍🎓 Student (Sinh viên)
                                    </option>
                                    <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>
                                        🛡️ Admin (Quản trị viên)
                                    </option>
                                </select>
                                <div class="form-text">
                                    Student: Học viên bình thường | Admin: Toàn quyền quản lý
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-key me-1"></i>Mật khẩu mặc định
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control bg-light" value="12345678" readonly>
                                    <button type="button" class="btn btn-outline-secondary" onclick="copyPassword()" title="Copy mật khẩu">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <div class="form-text text-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Người dùng nên đổi mật khẩu sau lần đăng nhập đầu tiên.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Lưu ý quan trọng:</h6>
                                    <ul class="mb-0">
                                        <li>🔑 <strong>Mật khẩu mặc định:</strong> 12345678 (tự động tạo)</li>
                                        <li>📧 <strong>Email:</strong> Phải là địa chỉ email hợp lệ và duy nhất</li>
                                        <li>👤 <strong>Tên đăng nhập:</strong> Phải duy nhất, chỉ chứa a-z, 0-9, _</li>
                                        <li>🛡️ <strong>Admin:</strong> Có toàn quyền | <strong>Student:</strong> Chỉ học tập</li>
                                        <li>✅ <strong>Trạng thái:</strong> Tài khoản sẽ được kích hoạt ngay lập tức</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">
                                <i class="fas fa-undo me-1"></i>Reset form
                            </button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-user-plus me-1"></i>Tạo người dùng
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics & Info -->
        <div class="col-lg-4">
            <!-- Quick Stats -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar me-2"></i>Thống kê người dùng
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <div class="h4 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['total_users']); ?>
                                </div>
                                <div class="text-xs text-gray-600">Tổng người dùng</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-user-plus fa-2x text-success mb-2"></i>
                                <div class="h4 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['new_users_week']); ?>
                                </div>
                                <div class="text-xs text-gray-600">Mới tuần này</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-user-shield fa-2x text-warning mb-2"></i>
                                <div class="h4 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['admin_count']); ?>
                                </div>
                                <div class="text-xs text-gray-600">Admin</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-user-graduate fa-2x text-info mb-2"></i>
                                <div class="h4 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['student_count']); ?>
                                </div>
                                <div class="text-xs text-gray-600">Student</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Username Generator -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-magic me-2"></i>Username Generator
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Tạo tên đăng nhập ngẫu nhiên:</p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="generateUsername('student')">
                            <i class="fas fa-user me-1"></i>Student Username
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="generateUsername('admin')">
                            <i class="fas fa-user-shield me-1"></i>Admin Username
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="generateUsername('random')">
                            <i class="fas fa-random me-1"></i>Random Username
                        </button>
                    </div>
                </div>
            </div>

            <!-- Password Info -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fas fa-shield-alt me-2"></i>Bảo mật mật khẩu
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Mật khẩu mặc định</h6>
                        <p class="mb-2"><strong>12345678</strong></p>
                        <hr>
                        <small class="text-muted">
                            • Nên thông báo cho người dùng đổi mật khẩu<br>
                            • Mật khẩu được mã hóa an toàn<br>
                            • Khuyến khích dùng mật khẩu mạnh
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
    }

    .form-control:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
    }

    .form-select:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
    }

    .btn {
        border-radius: 0.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .alert {
        border-radius: 0.75rem;
        border: none;
    }

    .breadcrumb {
        background: transparent;
        padding: 0;
        margin: 0;
    }

    .breadcrumb-item+.breadcrumb-item::before {
        content: "›";
        color: #6c757d;
    }

    .border {
        border-radius: 0.75rem !important;
        transition: all 0.3s ease;
    }

    .border:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .is-invalid {
        border-color: #dc3545;
    }

    .is-valid {
        border-color: #28a745;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card {
        animation: fadeInUp 0.6s ease forwards;
    }

    .card:nth-child(1) {
        animation-delay: 0.1s;
    }

    .card:nth-child(2) {
        animation-delay: 0.2s;
    }

    .card:nth-child(3) {
        animation-delay: 0.3s;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Add User page loaded');

        const form = document.getElementById('addUserForm');
        const submitBtn = document.getElementById('submitBtn');
        const usernameField = document.getElementById('username');
        const emailField = document.getElementById('email');

        // Real-time validation
        usernameField.addEventListener('input', function() {
            validateUsername(this);
        });

        emailField.addEventListener('input', function() {
            validateEmail(this);
        });

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const isValid = validateForm();

            if (isValid) {
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang tạo...';
                submitBtn.disabled = true;

                // Submit form
                this.submit();
            }
        });

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                try {
                    new bootstrap.Alert(alert).close();
                } catch (e) {
                    console.log('Alert already closed');
                }
            });
        }, 5000);
    });

    function validateUsername(field) {
        const value = field.value.trim();
        const feedback = field.nextElementSibling.nextElementSibling;

        if (value.length === 0) {
            setFieldError(field, 'Tên đăng nhập không được để trống');
            return false;
        }

        if (value.length < 3) {
            setFieldError(field, 'Tên đăng nhập phải có ít nhất 3 ký tự');
            return false;
        }

        if (value.length > 50) {
            setFieldError(field, 'Tên đăng nhập không được quá 50 ký tự');
            return false;
        }

        if (!/^[a-zA-Z0-9_]+$/.test(value)) {
            setFieldError(field, 'Chỉ được chứa chữ cái, số và dấu gạch dưới');
            return false;
        }

        setFieldSuccess(field, 'Tên đăng nhập hợp lệ');
        return true;
    }

    function validateEmail(field) {
        const value = field.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (value.length === 0) {
            setFieldError(field, 'Email không được để trống');
            return false;
        }

        if (!emailRegex.test(value)) {
            setFieldError(field, 'Email không hợp lệ');
            return false;
        }

        if (value.length > 100) {
            setFieldError(field, 'Email không được quá 100 ký tự');
            return false;
        }

        setFieldSuccess(field, 'Email hợp lệ');
        return true;
    }

    function validateForm() {
        const username = document.getElementById('username');
        const email = document.getElementById('email');

        const isUsernameValid = validateUsername(username);
        const isEmailValid = validateEmail(email);

        return isUsernameValid && isEmailValid;
    }

    function setFieldError(field, message) {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        const feedback = field.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = message;
        }
    }

    function setFieldSuccess(field, message) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        const feedback = field.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
        }
    }

    function resetForm() {
        const form = document.getElementById('addUserForm');
        form.reset();

        // Remove validation classes
        document.querySelectorAll('.is-valid, .is-invalid').forEach(field => {
            field.classList.remove('is-valid', 'is-invalid');
        });

        // Reset submit button
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.innerHTML = '<i class="fas fa-user-plus me-1"></i>Tạo người dùng';
        submitBtn.disabled = false;
    }

    function copyPassword() {
        const password = '12345678';
        navigator.clipboard.writeText(password).then(() => {
            // Show success message
            const btn = event.target.closest('button');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check text-success"></i>';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-outline-secondary');

            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-secondary');
            }, 2000);
        });
    }

    function generateUsername(type) {
        const usernameField = document.getElementById('username');
        let username = '';

        const adjectives = ['smart', 'clever', 'bright', 'quick', 'sharp', 'wise', 'cool', 'pro'];
        const nouns = ['student', 'learner', 'user', 'member', 'scholar', 'pupil'];
        const adminWords = ['admin', 'manager', 'chief', 'master', 'boss', 'leader'];
        const randomWords = ['tiger', 'eagle', 'dragon', 'phoenix', 'wolf', 'lion', 'falcon', 'hawk'];

        const randomNum = () => Math.floor(Math.random() * 9999) + 1;
        const randomChoice = (arr) => arr[Math.floor(Math.random() * arr.length)];

        switch (type) {
            case 'student':
                username = randomChoice(adjectives) + '_' + randomChoice(nouns) + randomNum();
                break;
            case 'admin':
                username = randomChoice(adminWords) + '_' + randomChoice(['pro', 'master', 'chief']) + randomNum();
                break;
            case 'random':
                username = randomChoice(randomWords) + '_' + randomChoice(['pro', 'master', 'king']) + randomNum();
                break;
        }

        usernameField.value = username;
        validateUsername(usernameField);

        // Focus on email field
        document.getElementById('email').focus();
    }
</script>

<?php include 'includes/admin-footer.php'; ?>