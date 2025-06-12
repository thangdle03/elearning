<?php
require_once '../includes/config.php';

// Check admin login
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Dashboard';

// Get stats
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$stats['users'] = $stmt->fetchColumn();

// Total courses
$stmt = $pdo->query("SELECT COUNT(*) FROM courses");
$stats['courses'] = $stmt->fetchColumn();

// Total lessons
$stmt = $pdo->query("SELECT COUNT(*) FROM lessons");
$stats['lessons'] = $stmt->fetchColumn();

// Total enrollments
$stmt = $pdo->query("SELECT COUNT(*) FROM enrollments");
$stats['enrollments'] = $stmt->fetchColumn();

// Recent courses
$stmt = $pdo->query("SELECT c.*, cat.name as category_name 
                     FROM courses c 
                     LEFT JOIN categories cat ON c.category_id = cat.id 
                     ORDER BY c.created_at DESC 
                     LIMIT 5");
$recent_courses = $stmt->fetchAll();

// Recent users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt->fetchAll();
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Người dùng</h6>
                        <h2 class="card-title mb-0"><?php echo number_format($stats['users']); ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-people display-6 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stats-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Khóa học</h6>
                        <h2 class="card-title mb-0"><?php echo number_format($stats['courses']); ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-book display-6 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stats-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Bài học</h6>
                        <h2 class="card-title mb-0"><?php echo number_format($stats['lessons']); ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-play-circle display-6 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stats-card danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Ghi danh</h6>
                        <h2 class="card-title mb-0"><?php echo number_format($stats['enrollments']); ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-person-check display-6 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Content -->
<div class="row">
    <div class="col-lg-8">
        <div class="card admin-card">
            <div class="card-header">
                <h5 class="mb-0">Khóa học mới nhất</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_courses): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tiêu đề</th>
                                <th>Danh mục</th>
                                <th>Giá</th>
                                <th>Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo $course['category_name'] ?: 'Chưa phân loại'; ?>
                                    </span>
                                </td>
                                <td><?php echo formatPrice($course['price']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($course['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">Chưa có khóa học nào.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card admin-card">
            <div class="card-header">
                <h5 class="mb-0">Người dùng mới</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_users): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_users as $user): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h6>
                            <small class="text-muted"><?php echo $user['email']; ?></small>
                        </div>
                        <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">Chưa có người dùng nào.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>