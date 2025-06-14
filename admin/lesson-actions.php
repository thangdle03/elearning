<?php
// filepath: d:\Xampp\htdocs\elearning\admin\lesson-actions.php
// Turn off error display
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Set JSON headers immediately
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Include config silently
    ob_start();
    require_once '../includes/config.php';
    ob_end_clean();
    
    // Authentication check
    if (!function_exists('isLoggedIn') || !isLoggedIn() || !isAdmin()) {
        http_response_code(403);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        exit;
    }
    
    // Method check
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
        exit;
    }
    
    // Get request data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $action = '';
    $data = [];
    
    if (strpos($contentType, 'application/json') !== false) {
        // JSON request
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Dữ liệu JSON không hợp lệ']);
            exit;
        }
        
        $action = $data['action'] ?? '';
    } else {
        // Form data
        $action = $_POST['action'] ?? 'add_lesson';
        $data = $_POST;
    }
    
    // Clear any previous output
    ob_clean();
    
    // Route to appropriate handler
    switch ($action) {
        case 'add_lesson':
            handleAddLesson($data);
            break;
        case 'edit_lesson':
            handleEditLesson($data);
            break;
        case 'delete_lesson':
            handleDeleteLesson($data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ: ' . $action]);
    }
    
} catch (Exception $e) {
    // Log error to file instead of displaying
    error_log('Lesson action error: ' . $e->getMessage());
    
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Lỗi server. Vui lòng thử lại.']);
}

// Clean exit
ob_end_flush();
exit;

function handleAddLesson($data) {
    global $pdo;
    
    $course_id = (int)($data['course_id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $youtube_url = trim($data['youtube_url'] ?? '');
    $order_number = (int)($data['order_number'] ?? 0);
    
    // Validation
    if ($course_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID khóa học không hợp lệ']);
        return;
    }
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Tiêu đề bài học không được để trống']);
        return;
    }
    
    if ($order_number <= 0) {
        echo json_encode(['success' => false, 'message' => 'Thứ tự bài học phải lớn hơn 0']);
        return;
    }
    
    try {
        // Check if course exists
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Khóa học không tồn tại']);
            return;
        }
        
        // Check order conflict
        $stmt = $pdo->prepare("SELECT id FROM lessons WHERE course_id = ? AND order_number = ?");
        $stmt->execute([$course_id, $order_number]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Thứ tự bài học đã tồn tại']);
            return;
        }
        
        // Insert lesson
        $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, youtube_url, order_number, created_at) VALUES (?, ?, ?, ?, NOW())");
        $result = $stmt->execute([$course_id, $title, $youtube_url, $order_number]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Thêm bài học thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể thêm bài học']);
        }
        
    } catch (PDOException $e) {
        error_log('Add lesson DB error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    }
}

function handleEditLesson($data) {
    global $pdo;
    
    $lesson_id = (int)($data['lesson_id'] ?? 0);
    $course_id = (int)($data['course_id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $youtube_url = trim($data['youtube_url'] ?? '');
    $order_number = (int)($data['order_number'] ?? 0);
    
    // Validation
    if ($lesson_id <= 0 || $course_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        return;
    }
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Tiêu đề bài học không được để trống']);
        return;
    }
    
    try {
        // Check if lesson exists
        $stmt = $pdo->prepare("SELECT id FROM lessons WHERE id = ? AND course_id = ?");
        $stmt->execute([$lesson_id, $course_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Bài học không tồn tại']);
            return;
        }
        
        // Update lesson
        $stmt = $pdo->prepare("UPDATE lessons SET title = ?, youtube_url = ?, order_number = ?, updated_at = NOW() WHERE id = ? AND course_id = ?");
        $result = $stmt->execute([$title, $youtube_url, $order_number, $lesson_id, $course_id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật bài học thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể cập nhật bài học']);
        }
        
    } catch (PDOException $e) {
        error_log('Edit lesson DB error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    }
}

function handleDeleteLesson($data) {
    global $pdo;
    
    $lesson_id = (int)($data['lesson_id'] ?? 0);
    $course_id = (int)($data['course_id'] ?? 0);
    
    // Validation
    if ($lesson_id <= 0 || $course_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        return;
    }
    
    try {
        // Check if lesson exists and get title
        $stmt = $pdo->prepare("SELECT title FROM lessons WHERE id = ? AND course_id = ?");
        $stmt->execute([$lesson_id, $course_id]);
        $lesson = $stmt->fetch();
        
        if (!$lesson) {
            echo json_encode(['success' => false, 'message' => 'Bài học không tồn tại']);
            return;
        }
        
        // Check if course has enrollments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $enrollments = $stmt->fetchColumn();
        
        if ($enrollments > 0) {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa bài học khi khóa học đã có học viên']);
            return;
        }
        
        // Delete lesson
        $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
        $result = $stmt->execute([$lesson_id, $course_id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Xóa bài học "' . $lesson['title'] . '" thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa bài học']);
        }
        
    } catch (PDOException $e) {
        error_log('Delete lesson DB error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    }
}
?>
