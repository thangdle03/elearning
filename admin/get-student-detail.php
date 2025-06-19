<?php
// filepath: d:\Xampp\htdocs\elearning\admin\get-student-detail.php

require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Không có quyền truy cập!</div>';
    exit;
}

// Get parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($user_id <= 0 || $course_id <= 0) {
    echo '<div class="alert alert-danger">Tham số không hợp lệ!</div>';
    exit;
}

try {
    // Get student basic info and enrollment details
    $stmt = $pdo->prepare("
        SELECT u.*, e.enrolled_at,
               c.title as course_title, c.id as course_id,
               DATEDIFF(CURDATE(), u.created_at) as days_since_joined
        FROM users u
        JOIN enrollments e ON u.id = e.user_id
        JOIN courses c ON e.course_id = c.id
        WHERE u.id = ? AND e.course_id = ?
    ");
    $stmt->execute([$user_id, $course_id]);
    $student = $stmt->fetch();

    if (!$student) {
        echo '<div class="alert alert-warning">Không tìm thấy thông tin học viên!</div>';
        exit;
    }

    // Get course progress details (removed duration reference)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT l.id) as total_lessons,
            COUNT(DISTINCT CASE WHEN p.completed = 1 THEN p.lesson_id END) as completed_lessons,
            COUNT(DISTINCT CASE WHEN p.lesson_id IS NOT NULL THEN p.lesson_id END) as started_lessons,
            COUNT(DISTINCT CASE WHEN p.completed = 1 THEN p.lesson_id END) * 5 as total_study_time,
            MAX(p.completed_at) as last_activity
        FROM lessons l
        LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
        WHERE l.course_id = ?
    ");
    $stmt->execute([$user_id, $course_id]);
    $progress_stats = $stmt->fetch();

    // Get recent lesson activities (removed duration reference)
    $stmt = $pdo->prepare("
        SELECT l.title as lesson_title, l.id as lesson_id,
               p.completed_at, p.completed,
               l.created_at as lesson_created_at
        FROM lessons l
        LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
        WHERE l.course_id = ?
        ORDER BY COALESCE(p.completed_at, l.created_at) DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $course_id]);
    $recent_activities = $stmt->fetchAll();

    // Get quiz results if table exists
    $quiz_results = [];
    $stmt = $pdo->query("SHOW TABLES LIKE 'quiz_results'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT q.title as quiz_title, qr.score, qr.total_questions, qr.completed_at,
                   ROUND((qr.score / qr.total_questions) * 100, 1) as percentage
            FROM quiz_results qr
            JOIN quizzes q ON qr.quiz_id = q.id
            WHERE qr.user_id = ? AND q.course_id = ?
            ORDER BY qr.completed_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id, $course_id]);
        $quiz_results = $stmt->fetchAll();
    }

    // Calculate progress percentage
    $progress_percentage = 0;
    if ($progress_stats['total_lessons'] > 0) {
        $progress_percentage = round(($progress_stats['completed_lessons'] / $progress_stats['total_lessons']) * 100, 1);
    }

    // Convert study time to hours and minutes (estimate: 5 minutes per completed lesson)
    $study_hours = floor($progress_stats['total_study_time'] / 60);
    $study_minutes = $progress_stats['total_study_time'] % 60;

    // Determine course status
    $course_status = 'not_started';
    $course_status_text = 'Chưa bắt đầu';
    $course_status_class = 'secondary';

    if ($progress_stats['completed_lessons'] == $progress_stats['total_lessons'] && $progress_stats['total_lessons'] > 0) {
        $course_status = 'completed';
        $course_status_text = 'Hoàn thành';
        $course_status_class = 'success';
    } elseif ($progress_stats['started_lessons'] > 0) {
        $course_status = 'in_progress';
        $course_status_text = 'Đang học';
        $course_status_class = 'primary';
    }

    ?>

    <div class="student-detail-content">
        <!-- Student Header -->
        <div class="row mb-4">
            <div class="col-md-3 text-center">
                <div class="student-avatar mb-3">
                    <?php if (!empty($student['avatar']) && file_exists('../uploads/avatars/' . $student['avatar'])): ?>
                        <img src="../uploads/avatars/<?php echo htmlspecialchars($student['avatar']); ?>" 
                             alt="Avatar" class="rounded-circle" width="100" height="100">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <i class="fas fa-user fa-3x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h5 class="mb-1"><?php echo htmlspecialchars($student['username']); ?></h5>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($student['email']); ?></p>
                <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?>">
                    <?php echo $student['status'] === 'active' ? 'Hoạt động' : 'Không hoạt động'; ?>
                </span>
            </div>
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-item">
                            <i class="fas fa-calendar-plus text-primary me-2"></i>
                            <strong>Tham gia hệ thống:</strong><br>
                            <span class="text-muted"><?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?></span>
                            <small class="text-muted">(<?php echo $student['days_since_joined']; ?> ngày trước)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <i class="fas fa-book-open text-success me-2"></i>
                            <strong>Đăng ký khóa học:</strong><br>
                            <span class="text-muted"><?php echo date('d/m/Y H:i', strtotime($student['enrolled_at'])); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6 mt-3">
                        <div class="info-item">
                            <i class="fas fa-clock text-info me-2"></i>
                            <strong>Hoạt động gần nhất:</strong><br>
                            <span class="text-muted">
                                <?php if ($progress_stats['last_activity']): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($progress_stats['last_activity'])); ?>
                                <?php else: ?>
                                    Chưa có hoạt động
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 mt-3">
                        <div class="info-item">
                            <i class="fas fa-graduation-cap text-warning me-2"></i>
                            <strong>Trạng thái khóa học:</strong><br>
                            <span class="badge bg-<?php echo $course_status_class; ?>"><?php echo $course_status_text; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Overview -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Tổng quan tiến độ</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="progress-circle-container">
                                    <div class="progress-circle" data-progress="<?php echo $progress_percentage; ?>">
                                        <span class="progress-text"><?php echo $progress_percentage; ?>%</span>
                                    </div>
                                    <small class="text-muted">Tiến độ tổng</small>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <div class="stat-box">
                                            <div class="stat-number text-primary"><?php echo $progress_stats['total_lessons']; ?></div>
                                            <div class="stat-label">Tổng bài học</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="stat-box">
                                            <div class="stat-number text-success"><?php echo $progress_stats['completed_lessons']; ?></div>
                                            <div class="stat-label">Đã hoàn thành</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="stat-box">
                                            <div class="stat-number text-warning"><?php echo $progress_stats['started_lessons']; ?></div>
                                            <div class="stat-label">Đã bắt đầu</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="stat-box">
                                            <div class="stat-number text-info">
                                                <?php if ($study_hours > 0 || $study_minutes > 0): ?>
                                                    <?php echo $study_hours; ?>h <?php echo $study_minutes; ?>m
                                                <?php else: ?>
                                                    0h 0m
                                                <?php endif; ?>
                                            </div>
                                            <div class="stat-label">Thời gian học (ước tính)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="progress-bar-wrapper">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Chi tiết tiến độ</small>
                                        <small class="text-muted">
                                            <?php echo $progress_stats['completed_lessons']; ?>/<?php echo $progress_stats['total_lessons']; ?> bài học
                                        </small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo $progress_percentage; ?>%"
                                             aria-valuenow="<?php echo $progress_percentage; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Hoạt động gần đây</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activities)): ?>
                            <div class="activity-timeline">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?php echo $activity['completed'] ? 'completed' : 'not-started'; ?>">
                                            <i class="fas fa-<?php echo $activity['completed'] ? 'check' : 'circle'; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">
                                                <?php echo htmlspecialchars($activity['lesson_title']); ?>
                                            </div>
                                            <div class="activity-details">
                                                <?php if ($activity['completed']): ?>
                                                    <span class="badge bg-success">Hoàn thành</span>
                                                    <span class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($activity['completed_at'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Chưa bắt đầu</span>
                                                    <span class="text-muted">
                                                        Tạo: <?php echo date('d/m/Y', strtotime($activity['lesson_created_at'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <p>Chưa có hoạt động nào</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quiz Results -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Kết quả bài kiểm tra</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($quiz_results)): ?>
                            <div class="quiz-results">
                                <?php foreach ($quiz_results as $result): ?>
                                    <div class="quiz-item mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($result['quiz_title']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($result['completed_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <div class="score-badge <?php echo $result['percentage'] >= 80 ? 'excellent' : ($result['percentage'] >= 60 ? 'good' : 'needs-improvement'); ?>">
                                                    <?php echo $result['percentage']; ?>%
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-question-circle fa-2x mb-2"></i>
                                <p>Chưa có kết quả bài kiểm tra</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Lessons List -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Danh sách bài học</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get all lessons with progress (removed duration reference)
                        $stmt = $pdo->prepare("
                            SELECT l.id, l.title, l.created_at, p.completed, p.completed_at
                            FROM lessons l
                            LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
                            WHERE l.course_id = ?
                            ORDER BY l.created_at ASC
                        ");
                        $stmt->execute([$user_id, $course_id]);
                        $all_lessons = $stmt->fetchAll();
                        ?>
                        
                        <?php if (!empty($all_lessons)): ?>
                            <div class="lessons-list">
                                <?php $lesson_number = 1; ?>
                                <?php foreach ($all_lessons as $lesson): ?>
                                    <div class="lesson-item">
                                        <div class="lesson-status">
                                            <?php if ($lesson['completed']): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php else: ?>
                                                <i class="far fa-circle text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="lesson-info">
                                            <div class="lesson-title">
                                                <span class="lesson-number"><?php echo $lesson_number; ?>.</span>
                                                <?php echo htmlspecialchars($lesson['title']); ?>
                                            </div>
                                            <div class="lesson-meta">
                                                <?php if ($lesson['completed']): ?>
                                                    <span class="badge bg-success">Hoàn thành</span>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($lesson['completed_at'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Chưa hoàn thành</span>
                                                <?php endif; ?>
                                                
                                                <span class="text-muted ms-2">
                                                    <i class="fas fa-calendar"></i> 
                                                    <?php echo date('d/m/Y', strtotime($lesson['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php $lesson_number++; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-book fa-2x mb-2"></i>
                                <p>Khóa học chưa có bài học nào</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Thông tin bổ sung</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label class="fw-bold">Họ tên đầy đủ:</label>
                                    <p class="text-muted"><?php echo htmlspecialchars($student['full_name'] ?? 'Chưa cập nhật'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label class="fw-bold">Số điện thoại:</label>
                                    <p class="text-muted"><?php echo htmlspecialchars($student['phone'] ?? 'Chưa cập nhật'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label class="fw-bold">Khóa học:</label>
                                    <p class="text-muted"><?php echo htmlspecialchars($student['course_title']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label class="fw-bold">Tỷ lệ hoàn thành:</label>
                                    <p class="text-muted">
                                        <span class="fw-bold text-<?php echo $progress_percentage >= 80 ? 'success' : ($progress_percentage >= 50 ? 'warning' : 'danger'); ?>">
                                            <?php echo $progress_percentage; ?>%
                                        </span>
                                        (<?php echo $progress_stats['completed_lessons']; ?>/<?php echo $progress_stats['total_lessons']; ?> bài)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .student-detail-content {
            max-height: 70vh;
            overflow-y: auto;
        }

        .avatar-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            border: 2px solid #e9ecef;
        }

        .info-item {
            padding: 10px;
            margin-bottom: 10px;
        }

        .progress-circle-container {
            text-align: center;
            padding: 20px;
        }

        .progress-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(
                #28a745 0deg,
                #28a745 calc(var(--progress, 0) * 3.6deg),
                #e9ecef calc(var(--progress, 0) * 3.6deg)
            );
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            position: relative;
        }

        .progress-circle::before {
            content: '';
            position: absolute;
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
        }

        .progress-text {
            position: relative;
            z-index: 2;
            font-weight: bold;
            color: #333;
        }

        .stat-box {
            padding: 15px;
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .progress-bar-wrapper {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .activity-timeline {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f3f4;
        }

        .activity-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .activity-icon.completed {
            background: #d4edda;
            color: #28a745;
        }

        .activity-icon.not-started {
            background: #f8f9fa;
            color: #6c757d;
        }

        .activity-title {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .activity-details {
            font-size: 0.8rem;
        }

        .lessons-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .lesson-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .lesson-item:hover {
            background: #f8f9fa;
        }

        .lesson-status {
            margin-right: 15px;
            font-size: 1.2rem;
        }

        .lesson-info {
            flex: 1;
        }

        .lesson-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .lesson-number {
            color: #6c757d;
            margin-right: 8px;
        }

        .lesson-meta {
            font-size: 0.85rem;
        }

        .quiz-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .score-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .score-badge.excellent {
            background: #d4edda;
            color: #28a745;
        }

        .score-badge.good {
            background: #fff3cd;
            color: #ffc107;
        }

        .score-badge.needs-improvement {
            background: #f8d7da;
            color: #dc3545;
        }

        .info-group {
            margin-bottom: 15px;
        }

        .info-group label {
            display: block;
            margin-bottom: 5px;
            color: #495057;
        }

        .info-group p {
            margin: 0;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .card {
            margin-bottom: 15px;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 12px 15px;
        }

        .card-body {
            padding: 15px;
        }

        /* Scrollbar styling */
        .student-detail-content::-webkit-scrollbar,
        .activity-timeline::-webkit-scrollbar,
        .lessons-list::-webkit-scrollbar {
            width: 6px;
        }

        .student-detail-content::-webkit-scrollbar-track,
        .activity-timeline::-webkit-scrollbar-track,
        .lessons-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .student-detail-content::-webkit-scrollbar-thumb,
        .activity-timeline::-webkit-scrollbar-thumb,
        .lessons-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set progress circle
            const progressCircle = document.querySelector('.progress-circle');
            if (progressCircle) {
                const progress = progressCircle.getAttribute('data-progress');
                progressCircle.style.setProperty('--progress', progress);
            }

            console.log('✅ Student detail loaded successfully');
        });
    </script>

    <?php

} catch (Exception $e) {
    error_log("Student detail error: " . $e->getMessage());
    echo '<div class="alert alert-danger">Có lỗi xảy ra khi tải thông tin học viên: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>