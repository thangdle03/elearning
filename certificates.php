<?php
// filepath: d:\Xampp\htdocs\elearning\certificates.php

require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php');
}

$user_id = $_SESSION['user_id'];
$page_title = 'Chứng chỉ của tôi';

// Handle certificate generation for completed courses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_certificate'])) {
    $course_id = (int)$_POST['course_id'];
    
    try {
        // Check if course is completed
        $stmt = $pdo->prepare("
            SELECT c.*, COUNT(l.id) as total_lessons,
                   SUM(CASE WHEN p.completed = 1 THEN 1 ELSE 0 END) as completed_lessons
            FROM courses c
            LEFT JOIN lessons l ON c.id = l.course_id
            LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$user_id, $course_id]);
        $course = $stmt->fetch();
        
        if ($course && $course['total_lessons'] > 0 && $course['completed_lessons'] == $course['total_lessons']) {
            // Check if certificate already exists
            $stmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$user_id, $course_id]);
            
            if (!$stmt->fetch()) {
                // Generate certificate
                $certificate_code = 'CERT-' . strtoupper(uniqid()) . '-' . date('Y');
                $completion_date = date('Y-m-d');
                
                // Calculate course duration (estimate based on lessons)
                $course_duration = max(1, $course['total_lessons'] * 2); // 2 hours per lesson estimate
                
                $stmt = $pdo->prepare("
                    INSERT INTO certificates (user_id, course_id, certificate_code, completion_date, course_duration_hours)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $course_id, $certificate_code, $completion_date, $course_duration]);
                
                $success_message = "🎉 Chứng chỉ đã được tạo thành công!";
            } else {
                $error_message = "⚠️ Chứng chỉ cho khóa học này đã tồn tại!";
            }
        } else {
            $error_message = "❌ Bạn cần hoàn thành tất cả bài học để nhận chứng chỉ!";
        }
    } catch (PDOException $e) {
        $error_message = "💥 Lỗi hệ thống: " . $e->getMessage();
    }
}

// Get user's certificates
try {
    $stmt = $pdo->prepare("
        SELECT cert.*, c.title as course_title, c.description as course_description,
               cat.name as category_name, u.full_name as user_full_name, u.username
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON cert.user_id = u.id
        WHERE cert.user_id = ?
        ORDER BY cert.issued_date DESC
    ");
    $stmt->execute([$user_id]);
    $certificates = $stmt->fetchAll();

    // Get completed courses that don't have certificates yet
    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(l.id) as total_lessons,
               SUM(CASE WHEN p.completed = 1 THEN 1 ELSE 0 END) as completed_lessons,
               cat.name as category_name
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN lessons l ON c.id = l.course_id
        LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN certificates cert ON c.id = cert.course_id AND cert.user_id = ?
        WHERE e.user_id = ? AND cert.id IS NULL
        GROUP BY c.id
        HAVING total_lessons > 0 AND completed_lessons = total_lessons
        ORDER BY c.title
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $completed_without_cert = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "💥 Lỗi truy vấn dữ liệu: " . $e->getMessage();
    $certificates = [];
    $completed_without_cert = [];
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
                            <i class="fas fa-certificate me-3"></i>Chứng chỉ của tôi
                        </h1>
                        <p class="mb-0 opacity-90">
                            Quản lý và tải xuống các chứng chỉ hoàn thành khóa học
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="stats-badge bg-white bg-opacity-20 rounded-3 p-3 d-inline-block">
                            <div class="h3 mb-0 text-white"><?php echo count($certificates); ?></div>
                            <small class="text-white opacity-90">Chứng chỉ đã có</small>
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
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Completed Courses Without Certificates -->
    <?php if (!empty($completed_without_cert)): ?>
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy me-2"></i>
                        Khóa học đã hoàn thành - Có thể tạo chứng chỉ
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($completed_without_cert as $course): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100 border-success">
                                <div class="card-body">
                                    <h6 class="card-title text-success"><?php echo htmlspecialchars($course['title']); ?></h6>
                                    <p class="card-text text-muted small">
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($course['category_name'] ?: 'Chưa phân loại'); ?>
                                    </p>
                                    <p class="card-text">
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> bài học
                                        </span>
                                    </p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" name="generate_certificate" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-certificate me-1"></i>Tạo chứng chỉ
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Certificates List -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($certificates)): ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-certificate display-1 text-muted"></i>
                    </div>
                    <h4 class="text-muted mb-3">Chưa có chứng chỉ nào</h4>
                    <p class="text-muted mb-4">
                        Hoàn thành các khóa học để nhận chứng chỉ hoàn thành.
                        <?php if (!empty($completed_without_cert)): ?>
                        <br>Bạn có <?php echo count($completed_without_cert); ?> khóa học đã hoàn thành có thể tạo chứng chỉ.
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-primary">
                        <i class="fas fa-book me-2"></i>Khám phá khóa học
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($certificates as $cert): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card certificate-card h-100 border-0 shadow-lg">
                            <div class="certificate-header bg-gradient-primary text-white p-4 text-center">
                                <i class="fas fa-certificate fa-3x mb-3 opacity-90"></i>
                                <h6 class="mb-0 text-uppercase fw-bold">Chứng chỉ hoàn thành</h6>
                            </div>
                            
                            <div class="card-body p-4">
                                <div class="text-center mb-3">
                                    <h5 class="card-title text-primary mb-2">
                                        <?php echo htmlspecialchars($cert['course_title']); ?>
                                    </h5>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo htmlspecialchars($cert['category_name'] ?: 'Chưa phân loại'); ?>
                                    </p>
                                </div>

                                <div class="certificate-details">
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <div class="border-end">
                                                <div class="fw-bold text-success"><?php echo $cert['grade']; ?></div>
                                                <small class="text-muted">Kết quả</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="fw-bold text-info"><?php echo $cert['course_duration_hours']; ?>h</div>
                                            <small class="text-muted">Thời lượng</small>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted d-block">Mã chứng chỉ:</small>
                                        <code class="text-primary fw-bold"><?php echo $cert['certificate_code']; ?></code>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted d-block">Ngày hoàn thành:</small>
                                        <span class="fw-semibold"><?php echo date('d/m/Y', strtotime($cert['completion_date'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-light border-0 p-4">
                                <div class="d-grid gap-2">
   
                                    <a href="<?php echo SITE_URL; ?>/certificate-download.php?id=<?php echo $cert['id']; ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-download me-2"></i>Tải xuống PDF
                                    </a>
                                    <button class="btn btn-outline-secondary btn-sm" 
                                            onclick="copyToClipboard('<?php echo $cert['certificate_code']; ?>')">
                                        <i class="fas fa-copy me-1"></i>Copy mã chứng chỉ
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.certificate-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.certificate-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
}

.certificate-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 0.5rem 0.5rem 0 0;
}

.stats-badge {
    backdrop-filter: blur(10px);
}

.border-end {
    border-right: 1px solid #dee2e6 !important;
}

code {
    background: #f8f9fa;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.9em;
}

.certificate-details small {
    font-size: 0.8rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .certificate-card .card-body {
        padding: 1.5rem;
    }
    
    .stats-badge {
        margin-top: 1rem;
    }
}
</style>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('success', '✅ Đã copy mã chứng chỉ: ' + text);
    }, function(err) {
        showToast('error', '❌ Không thể copy mã chứng chỉ');
    });
}

function showToast(type, message) {
    const toastDiv = document.createElement('div');
    toastDiv.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 position-fixed`;
    toastDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
    toastDiv.setAttribute('role', 'alert');
    toastDiv.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    document.body.appendChild(toastDiv);
    const toast = new bootstrap.Toast(toastDiv);
    toast.show();

    toastDiv.addEventListener('hidden.bs.toast', () => {
        toastDiv.remove();
    });
}
</script>

<?php include 'includes/footer.php'; ?>