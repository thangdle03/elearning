<?php

require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php');
}

// Get parameters
$course_id = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$lesson_id = isset($_GET['lesson']) ? (int)$_GET['lesson'] : 0;

if (!$course_id) {
    redirect(SITE_URL . '/my-courses.php');
}

// Check if user is enrolled
if (!isEnrolled($_SESSION['user_id'], $course_id, $pdo)) {
    redirect(SITE_URL . '/course-detail.php?id=' . $course_id);
}

// Get course details
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name 
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect(SITE_URL . '/my-courses.php');
}

// Get all lessons for this course
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_number ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// If no specific lesson is selected, get the first one
if (!$lesson_id && !empty($lessons)) {
    $lesson_id = $lessons[0]['id'];
}

// Get current lesson
$current_lesson = null;
foreach ($lessons as $lesson) {
    if ($lesson['id'] == $lesson_id) {
        $current_lesson = $lesson;
        break;
    }
}

if (!$current_lesson && !empty($lessons)) {
    $current_lesson = $lessons[0];
    $lesson_id = $current_lesson['id'];
}

$page_title = $course['title'] . ' - ' . ($current_lesson ? $current_lesson['title'] : 'Học tập');

// Get user's progress for this course
$stmt = $pdo->prepare("
    SELECT lesson_id, completed 
    FROM progress 
    WHERE user_id = ? AND lesson_id IN (SELECT id FROM lessons WHERE course_id = ?)
");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$progress = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Mark lesson as completed (AJAX handler)
if ($_POST && isset($_POST['mark_completed'])) {
    $lesson_to_complete = (int)$_POST['lesson_id'];
    
    // Insert or update progress
    $stmt = $pdo->prepare("
        INSERT INTO progress (user_id, lesson_id, completed, completed_at) 
        VALUES (?, ?, 1, NOW()) 
        ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()
    ");
    $stmt->execute([$_SESSION['user_id'], $lesson_to_complete]);
    
    // Return JSON response for AJAX
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Đã đánh dấu hoàn thành!']);
        exit;
    }
    
    $progress[$lesson_to_complete] = 1;
}

// Calculate progress percentage
$total_lessons = count($lessons);
$completed_lessons = array_sum($progress);
$progress_percentage = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;

// Get YouTube video ID
$youtube_id = $current_lesson ? getYoutubeId($current_lesson['youtube_url']) : '';
?>

<?php include 'includes/header.php'; ?>

<div class="learning-container">
    <div class="row g-0">
        <!-- Sidebar - Course Navigation -->
        <div class="col-lg-3 bg-dark text-white" id="sidebar">
            <div class="sidebar-header p-3 border-bottom border-secondary">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-truncate">
                        <i class="bi bi-book me-2"></i>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </h5>
                    <button class="btn btn-sm btn-outline-light d-lg-none" id="toggleSidebar">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                
                <!-- Progress Bar -->
                <div class="mt-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Tiến độ</span>
                        <span><?php echo $completed_lessons; ?>/<?php echo $total_lessons; ?></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $progress_percentage; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo $progress_percentage; ?>% hoàn thành</small>
                </div>
            </div>
            
            <!-- Lessons List -->
            <div class="lessons-list">
                <?php foreach ($lessons as $index => $lesson): ?>
                <div class="lesson-item p-3 border-bottom border-secondary <?php echo $lesson['id'] == $lesson_id ? 'active' : ''; ?>" 
                     style="cursor: pointer;" 
                     onclick="loadLesson(<?php echo $lesson['id']; ?>)">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <?php if (isset($progress[$lesson['id']]) && $progress[$lesson['id']]): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo $index + 1; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 <?php echo $lesson['id'] == $lesson_id ? 'text-warning' : ''; ?>">
                                <?php echo htmlspecialchars($lesson['title']); ?>
                            </h6>
                            <small class="text-muted">
                                <i class="bi bi-play-circle me-1"></i>Video bài học
                            </small>
                        </div>
                        <?php if ($lesson['id'] == $lesson_id): ?>
                        <i class="bi bi-arrow-right text-warning"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Course Actions -->
            <div class="p-3 border-top border-secondary">
                <div class="d-grid gap-2">
                    <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course_id; ?>" 
                       class="btn btn-outline-light btn-sm">
                        <i class="bi bi-info-circle me-2"></i>Thông tin khóa học
                    </a>
                    <a href="<?php echo SITE_URL; ?>/my-courses.php" 
                       class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-2"></i>Khóa học của tôi
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content - Video Player -->
        <div class="col-lg-9">
            <!-- Top Navigation -->
            <div class="learning-header bg-white border-bottom p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-secondary me-3 d-lg-none" id="showSidebar">
                            <i class="bi bi-list"></i>
                        </button>
                        <div>
                            <h4 class="mb-0"><?php echo $current_lesson ? htmlspecialchars($current_lesson['title']) : 'Chọn bài học'; ?></h4>
                            <small class="text-muted">
                                <span class="badge bg-secondary"><?php echo $course['category_name'] ?: 'Khóa học'; ?></span>
                            </small>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2">
                        <!-- Mark as Complete Button -->
                        <?php if ($current_lesson): ?>
                        <button class="btn btn-success btn-sm" id="markCompleteBtn" 
                                <?php echo (isset($progress[$lesson_id]) && $progress[$lesson_id]) ? 'disabled' : ''; ?>>
                            <i class="bi bi-check-circle me-1"></i>
                            <?php echo (isset($progress[$lesson_id]) && $progress[$lesson_id]) ? 'Đã hoàn thành' : 'Đánh dấu hoàn thành'; ?>
                        </button>
                        <?php endif; ?>
                        
                        <!-- Navigation Buttons -->
                        <div class="btn-group">
                            <?php
                            $current_index = 0;
                            foreach ($lessons as $i => $l) {
                                if ($l['id'] == $lesson_id) {
                                    $current_index = $i;
                                    break;
                                }
                            }
                            ?>
                            
                            <?php if ($current_index > 0): ?>
                            <button class="btn btn-outline-primary btn-sm" 
                                    onclick="loadLesson(<?php echo $lessons[$current_index - 1]['id']; ?>)">
                                <i class="bi bi-chevron-left"></i> Trước
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($current_index < count($lessons) - 1): ?>
                            <button class="btn btn-outline-primary btn-sm" 
                                    onclick="loadLesson(<?php echo $lessons[$current_index + 1]['id']; ?>)">
                                Tiếp <i class="bi bi-chevron-right"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Video Player -->
            <div class="video-container p-3">
                <?php if ($current_lesson && $youtube_id): ?>
                <div class="ratio ratio-16x9 mb-3">
                    <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>?rel=0&modestbranding=1" 
                            title="<?php echo htmlspecialchars($current_lesson['title']); ?>"
                            allowfullscreen id="videoPlayer"></iframe>
                </div>
                <?php else: ?>
                <div class="ratio ratio-16x9 mb-3 bg-light d-flex align-items-center justify-content-center">
                    <div class="text-center text-muted">
                        <i class="bi bi-play-circle display-1"></i>
                        <h4 class="mt-3">Chọn bài học để bắt đầu</h4>
                        <p>Vui lòng chọn một bài học từ danh sách bên trái</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Lesson Content -->
                <?php if ($current_lesson): ?>
                <div class="lesson-content">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle me-2"></i>Nội dung bài học
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6><?php echo htmlspecialchars($current_lesson['title']); ?></h6>
                            <p class="text-muted mb-0">
                                Đây là bài học số <?php echo $current_index + 1; ?> trong khóa học 
                                <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.learning-container {
    height: 100vh;
    overflow: hidden;
}

#sidebar {
    height: 100vh;
    overflow-y: auto;
}

.lesson-item {
    transition: background-color 0.3s ease;
}

.lesson-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.lesson-item.active {
    background-color: rgba(255, 193, 7, 0.2);
    border-left: 4px solid #ffc107;
}

.learning-header {
    position: sticky;
    top: 0;
    z-index: 100;
}

.video-container {
    height: calc(100vh - 80px);
    overflow-y: auto;
}

@media (max-width: 991.98px) {
    #sidebar {
        position: fixed;
        left: -100%;
        top: 0;
        z-index: 1050;
        transition: left 0.3s ease;
        width: 300px;
    }
    
    #sidebar.show {
        left: 0;
    }
    
    .video-container {
        height: calc(100vh - 60px);
    }
}
</style>

<!-- JavaScript -->
<script>
// Load lesson function
function loadLesson(lessonId) {
    const url = new URL(window.location);
    url.searchParams.set('lesson', lessonId);
    window.location.href = url.toString();
}

// Mark lesson as complete
document.getElementById('markCompleteBtn')?.addEventListener('click', function() {
    const btn = this;
    const lessonId = <?php echo $lesson_id; ?>;
    
    // Show loading
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
    btn.disabled = true;
    
    // Send AJAX request
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `mark_completed=1&lesson_id=${lessonId}&ajax=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Đã hoàn thành';
            btn.className = 'btn btn-success btn-sm';
            
            // Update progress in sidebar
            const lessonItem = document.querySelector(`[onclick="loadLesson(${lessonId})"] .me-3`);
            if (lessonItem) {
                lessonItem.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
            }
            
            // Show success message
            showAlert('success', data.message);
            
            // Reload page after 2 seconds to update progress
            setTimeout(() => location.reload(), 2000);
        } else {
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Đánh dấu hoàn thành';
            btn.disabled = false;
            showAlert('danger', 'Có lỗi xảy ra!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Đánh dấu hoàn thành';
        btn.disabled = false;
        showAlert('danger', 'Có lỗi xảy ra!');
    });
});

// Sidebar toggle for mobile
document.getElementById('showSidebar').addEventListener('click', function() {
    document.getElementById('sidebar').classList.add('show');
});

document.getElementById('toggleSidebar').addEventListener('click', function() {
    document.getElementById('sidebar').classList.remove('show');
});

// Show alert function
function showAlert(type, message) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        alert.remove();
    }, 3000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) return;
    
    switch(e.code) {
        case 'ArrowLeft':
            // Previous lesson
            document.querySelector('button[onclick*="loadLesson"]:has(.bi-chevron-left)')?.click();
            break;
        case 'ArrowRight':
            // Next lesson
            document.querySelector('button[onclick*="loadLesson"]:has(.bi-chevron-right)')?.click();
            break;
        case 'Space':
            // Toggle video play/pause (if supported)
            e.preventDefault();
            break;
    }
});

// Auto-mark as complete when video ends (optional)
// This would require YouTube API integration
</script>

<?php 
// Don't include footer for learning page to maximize space
?>
</body>
</html>