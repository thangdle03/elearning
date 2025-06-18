<?php
// filepath: d:\Xampp\htdocs\elearning\course-detail.php

require_once 'includes/config.php';

// Get course ID
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    redirect(SITE_URL . '/courses.php');
}

// Get course details
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect(SITE_URL . '/courses.php');
}

// Process course data - Add missing fields
$course['duration'] = isset($course['duration']) && !empty($course['duration'])
    ? $course['duration']
    : (max(2, $course['lesson_count'] * 1.5) . 'h');

$course['students'] = $course['student_count'] > 0
    ? $course['student_count']
    : rand(50, 1500);

$course['level'] = isset($course['level']) && !empty($course['level'])
    ? $course['level']
    : (function ($price) {
        if ($price == 0) return 'Cơ bản';
        elseif ($price < 500000) return 'Trung cấp';
        else return 'Nâng cao';
    })($course['price']);

$course['rating'] = isset($course['rating']) && !empty($course['rating'])
    ? $course['rating']
    : 4.5;

$course['reviews_count'] = isset($course['reviews_count']) && !empty($course['reviews_count'])
    ? $course['reviews_count']
    : rand(10, 500);

$course['language'] = isset($course['language']) && !empty($course['language'])
    ? $course['language']
    : 'Tiếng Việt';

$course['updated'] = isset($course['updated_at']) && !empty($course['updated_at'])
    ? date('d/m/Y', strtotime($course['updated_at']))
    : date('d/m/Y');

$course['has_image'] = !empty($course['thumbnail']);
$course['image_path'] = $course['thumbnail'] ?? '';

$page_title = $course['title'];

// Get course lessons
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_number ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// Check if user is enrolled
$is_enrolled = false;
if (isLoggedIn()) {
    $is_enrolled = isEnrolled($_SESSION['user_id'], $course_id, $pdo);
}

// Handle enrollment
$enrollment_message = '';
if ($_POST && isset($_POST['enroll']) && isLoggedIn()) {
    if (!$is_enrolled) {
        try {
            $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $course_id]);
            $is_enrolled = true;
            $enrollment_message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Đăng ký khóa học thành công!</div>';
        } catch (Exception $e) {
            $enrollment_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Có lỗi xảy ra. Vui lòng thử lại!</div>';
        }
    }
}

// Get related courses with thumbnail
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE c.category_id = ? AND c.id != ? 
    ORDER BY RAND() 
    LIMIT 6
");
$stmt->execute([$course['category_id'], $course_id]);
$related_courses = $stmt->fetchAll();

// Function to get course thumbnail/image
function getCourseImage($course)
{
    if (!empty($course['thumbnail'])) {
        if (filter_var($course['thumbnail'], FILTER_VALIDATE_URL)) {
            return $course['thumbnail'];
        } else {
            $possiblePaths = [
                'uploads/courses/' . $course['thumbnail'],
                'uploads/' . $course['thumbnail'],
                'assets/images/courses/' . $course['thumbnail'],
                $course['thumbnail']
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    return SITE_URL . '/' . ltrim($path, '/');
                }
            }

            return SITE_URL . '/' . ltrim($course['thumbnail'], '/');
        }
    }

    return null;
}

// Process related courses data
foreach ($related_courses as &$related) {
    $related['course_image'] = getCourseImage($related);
    $related['rating'] = $related['rating'] ?? (4.0 + (rand(1, 10) / 10));
    $related['duration'] = $related['duration'] ?? (rand(8, 25) . 'h ' . rand(10, 59) . 'm');
    $related['students'] = $related['student_count'] > 0 ? $related['student_count'] : rand(50, 1500);

    // Set level based on price for related courses
    if (!isset($related['level']) || empty($related['level'])) {
        if ($related['price'] == 0) {
            $related['level'] = 'Cơ bản';
        } elseif ($related['price'] < 500000) {
            $related['level'] = 'Trung cấp';
        } else {
            $related['level'] = 'Nâng cao';
        }
    }
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    /* Reset & Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --primary-color: #667eea;
        --primary-dark: #5a67d8;
        --secondary-color: #48bb78;
        --accent-color: #ed8936;
        --danger-color: #f56565;
        --warning-color: #ed8936;
        --success-color: #48bb78;
        --info-color: #4299e1;
        --dark-color: #1a202c;
        --gray-50: #f7fafc;
        --gray-100: #edf2f7;
        --gray-200: #e2e8f0;
        --gray-300: #cbd5e0;
        --gray-400: #a0aec0;
        --gray-500: #718096;
        --gray-600: #4a5568;
        --gray-700: #2d3748;
        --gray-800: #1a202c;
        --gray-900: #171923;
        --white: #ffffff;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --border-radius: 12px;
        --border-radius-lg: 16px;
        --border-radius-xl: 20px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --header-height: 80px;
        /* Define header height */
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: var(--white);
        min-height: 100vh;
        color: var(--gray-800);
        line-height: 1.7;
        overflow-x: hidden;
        margin: 0;
        padding: 0;
        padding-top: var(--header-height);
        /* Add padding to avoid header overlap */
    }

    /* Page content with proper spacing */
    .page-content {
        margin: 0;
        padding: 0;
    }

    /* Course Header - Account for fixed header */
    .course-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        position: relative;
        overflow: hidden;
        padding: 2rem 0 6rem 0;
        margin: 0;
        margin-top: calc(-1 * var(--header-height));
        /* Pull up to eliminate gap */
        padding-top: calc(var(--header-height) + 2rem);
        /* Add padding to account for header */
    }

    .course-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
    }

    .course-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 100px;
        background: linear-gradient(180deg, transparent 0%, var(--white) 100%);
    }

    .course-header-content {
        position: relative;
        z-index: 2;
        color: white;
    }

    /* Breadcrumb */
    .breadcrumb-wrapper {
        padding: 1rem 0;
        margin-bottom: 1.5rem;
    }

    .custom-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.95rem;
        font-weight: 500;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        padding: 0.8rem 1.2rem;
        border-radius: 50px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        display: inline-flex;
    }

    .custom-breadcrumb a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .custom-breadcrumb a:hover {
        color: white;
    }

    .breadcrumb-separator {
        color: rgba(255, 255, 255, 0.5);
        font-size: 1.1rem;
        margin: 0 0.2rem;
    }

    .breadcrumb-current {
        color: white;
        font-weight: 600;
    }

    /* Course Info Header */
    .course-info-header {
        margin-bottom: 3rem;
    }

    .course-category {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        color: white;
        padding: 0.7rem 1.2rem;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        margin-bottom: 1.5rem;
    }

    .course-title {
        font-size: 3.2rem;
        font-weight: 900;
        line-height: 1.2;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .course-description {
        font-size: 1.2rem;
        line-height: 1.8;
        margin-bottom: 2rem;
        opacity: 0.95;
        max-width: 800px;
    }

    /* Course Stats */
    .course-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-item {
        text-align: center;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: var(--border-radius);
        padding: 1.5rem 1rem;
        transition: var(--transition);
    }

    .stat-item:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }

    .stat-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        display: block;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 800;
        display: block;
        margin-bottom: 0.3rem;
    }

    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
        font-weight: 500;
    }

    /* Rating Section */
    .rating-section {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stars {
        display: flex;
        gap: 0.2rem;
    }

    .star {
        color: #fbbf24;
        font-size: 1.3rem;
        filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.2));
    }

    .rating-info {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .rating-count {
        opacity: 0.8;
        font-weight: 500;
    }

    /* Main Content Area */
    .main-content {
        background: var(--white);
        margin-top: -4rem;
        position: relative;
        z-index: 3;
        border-radius: var(--border-radius-xl) var(--border-radius-xl) 0 0;
        box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.1);
        min-height: calc(100vh - 200px);
    }

    .content-container {
        padding: 4rem 0;
    }

    /* Course Card Sidebar */
    .course-card {
        background: var(--white);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
        position: sticky;
        top: calc(var(--header-height) + 2rem);
        /* Stick below header */
        border: 1px solid var(--gray-200);
        transform: translateY(-6rem);
        z-index: 10;
        max-height: calc(100vh - var(--header-height) - 4rem);
        overflow-y: auto;
    }

    .course-preview {
        position: relative;
        aspect-ratio: 16/9;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        overflow: hidden;
    }

    .course-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .course-preview:hover img {
        transform: scale(1.05);
    }

    .play-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: var(--transition);
    }

    .course-preview:hover .play-overlay {
        opacity: 1;
    }

    .play-button {
        background: rgba(255, 255, 255, 0.9);
        color: var(--primary-color);
        border: none;
        border-radius: 50%;
        width: 70px;
        height: 70px;
        font-size: 1.8rem;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: var(--shadow-lg);
    }

    .play-button:hover {
        background: white;
        transform: scale(1.1);
    }

    /* Price Section */
    .price-section {
        background: linear-gradient(135deg, var(--success-color) 0%, #38a169 100%);
        color: white;
        text-align: center;
        padding: 2rem;
    }

    .price-section.paid {
        background: linear-gradient(135deg,rgb(227, 227, 234) 0%,rgb(126, 111, 152) 100%);
        border-bottom: 1px solid #4338ca;
        color: white;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.1),
            0 4px 20px rgba(79, 70, 229, 0.3);
    }

    .price-amount {
        font-size: 2.5rem;
        font-weight: 900;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(203, 177, 177, 0.2);
    }

    .price-label {
        font-size: 1rem;
        opacity: 0.9;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Course Actions */
    .course-actions {
        padding: 2rem;
        background: var(--gray-50);
    }

    .btn-enroll {
        width: 100%;
        padding: 1.2rem 2rem;
        font-size: 1.1rem;
        font-weight: 700;
        border: none;
        border-radius: var(--border-radius);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.8rem;
        margin-bottom: 1rem;
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: var(--shadow-md);
    }

    .btn-enroll.primary {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        color: white;
        border: 2px solid transparent;
    }

    .btn-enroll.primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        color: white;
    }

    .btn-enroll.success {
        background: linear-gradient(135deg, var(--success-color) 0%, #38a169 100%);
        color: white;
    }

    .btn-enroll.warning {
        background: linear-gradient(135deg, var(--warning-color) 0%, #dd6b20 100%);
        color: white;
    }

    .guarantee-text {
        text-align: center;
        color: var(--gray-600);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-weight: 500;
    }

    /* Content Sections */
    .content-section {
        background: var(--white);
        margin-bottom: 2rem;
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        transition: var(--transition);
    }

    .content-section:hover {
        box-shadow: var(--shadow-lg);
    }

    .section-header {
        background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--gray-200);
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 0.8rem;
        margin: 0;
    }

    .section-title i {
        color: var(--primary-color);
        font-size: 1.3rem;
    }

    .section-badge {
        background: var(--primary-color);
        color: white;
        padding: 0.3rem 0.8rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: auto;
    }

    .section-content {
        padding: 2rem;
    }

    .section-content.no-padding {
        padding: 0;
    }

    /* Description */
    .description-content {
        font-size: 1.1rem;
        line-height: 1.8;
        color: var(--gray-700);
    }

    /* Curriculum */
    .curriculum-list {
        list-style: none;
    }

    .curriculum-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--gray-200);
        transition: var(--transition);
        background: var(--white);
    }

    .curriculum-item:last-child {
        border-bottom: none;
    }

    .curriculum-item:hover {
        background: var(--gray-50);
    }

    .lesson-content {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }

    .lesson-number {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        box-shadow: var(--shadow);
    }

    .lesson-info h6 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 0.3rem;
    }

    .lesson-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: var(--gray-500);
        font-size: 0.9rem;
    }

    .lesson-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-lesson {
        padding: 0.6rem 1.2rem;
        border-radius: var(--border-radius);
        font-size: 0.9rem;
        font-weight: 600;
        text-decoration: none;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid transparent;
    }

    .btn-lesson.primary {
        background: var(--primary-color);
        color: white;
    }

    .btn-lesson.primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        color: white;
    }

    .btn-lesson.disabled {
        background: var(--gray-200);
        color: var(--gray-500);
        cursor: not-allowed;
    }

    /* Features Grid */
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .feature-card {
        background: var(--gray-50);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        display: flex;
        gap: 1rem;
        transition: var(--transition);
        border: 1px solid var(--gray-200);
    }

    .feature-card:hover {
        background: var(--white);
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .feature-icon {
        background: linear-gradient(135deg, var(--success-color) 0%, #38a169 100%);
        color: white;
        width: 50px;
        height: 50px;
        border-radius: var(--border-radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        box-shadow: var(--shadow);
    }

    .feature-content h6 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 0.5rem;
    }

    .feature-content p {
        color: var(--gray-600);
        font-size: 0.9rem;
        line-height: 1.6;
        margin: 0;
    }

    /* Sidebar */
    .sidebar-card {
        background: var(--white);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        margin-bottom: 2rem;
        overflow: hidden;
        border: 1px solid var(--gray-200);
    }

    .sidebar-header {
        background: var(--dark-color);
        color: white;
        padding: 1rem 1.5rem;
        font-weight: 600;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .sidebar-content {
        padding: 1.5rem;
    }

    .info-list {
        list-style: none;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 0;
        border-bottom: 1px solid var(--gray-200);
    }

    .info-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .info-label {
        color: var(--gray-600);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-value {
        font-weight: 600;
        color: var(--gray-900);
    }

    .badge {
        background: var(--primary-color);
        color: white;
        padding: 0.3rem 0.8rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    /* Share Buttons */
    .share-grid {
        display: grid;
        gap: 0.8rem;
    }

    .btn-share {
        padding: 0.8rem 1rem;
        border-radius: var(--border-radius);
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.8rem;
        text-decoration: none;
        font-size: 0.9rem;
    }

    .btn-share.facebook {
        background: #1877f2;
        color: white;
    }

    .btn-share.twitter {
        background: #1da1f2;
        color: white;
    }

    .btn-share.linkedin {
        background: #0077b5;
        color: white;
    }

    .btn-share:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        color: white;
    }

    /* Related Courses */
    .related-section {
        margin-top: 3rem;
        padding: 4rem 0;
        background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
        border-top: 1px solid var(--gray-200);
    }

    .related-title {
        text-align: center;
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--gray-900);
        margin-bottom: 3rem;
        position: relative;
    }

    .related-title::after {
        content: '';
        position: absolute;
        bottom: -1rem;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 4px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        border-radius: 2px;
    }

    .related-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
        max-width: 1400px;
        margin: 0 auto;
    }

    .related-card {
        background: var(--white);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        overflow: hidden;
        transition: var(--transition);
        border: 1px solid var(--gray-200);
        position: relative;
        transform: translateY(0);
    }

    .related-card:hover {
        transform: translateY(-12px);
        box-shadow: var(--shadow-xl);
    }

    .related-image {
        aspect-ratio: 16/9;
        position: relative;
        overflow: hidden;
        background: var(--gray-100);
    }

    .related-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .related-card:hover .related-image img {
        transform: scale(1.08);
    }

    /* Enhanced course placeholder */
    .course-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        color: white;
        text-align: center;
        position: relative;
    }

    .course-placeholder::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
    }

    .course-placeholder i {
        font-size: 3.5rem;
        margin-bottom: 0.8rem;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    .placeholder-text {
        font-size: 1.1rem;
        font-weight: 600;
        opacity: 0.8;
        position: relative;
        z-index: 1;
    }

    /* Course overlay */
    .course-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(180deg, transparent 0%, rgba(0, 0, 0, 0.7) 100%);
        opacity: 0;
        transition: var(--transition);
        display: flex;
        align-items: flex-end;
    }

    .related-card:hover .course-overlay {
        opacity: 1;
    }

    .overlay-content {
        padding: 1.5rem;
        color: white;
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .course-level,
    .course-lessons {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .related-content {
        padding: 1.8rem;
    }

    .related-badge {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 1.2rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .related-title-link {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--gray-900);
        text-decoration: none;
        line-height: 1.4;
        display: block;
        margin-bottom: 0.8rem;
        transition: var(--transition);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .related-title-link:hover {
        color: var(--primary-color);
    }

    .related-description {
        color: var(--gray-600);
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 1.2rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Enhanced stats */
    .related-stats {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        background: var(--gray-50);
        padding: 1rem;
        border-radius: var(--border-radius);
        border: 1px solid var(--gray-200);
    }

    .related-stat {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.3rem;
        color: var(--gray-700);
        font-size: 0.85rem;
        font-weight: 600;
        text-align: center;
    }

    .related-stat i {
        font-size: 1rem;
        color: var(--primary-color);
        margin-bottom: 0.2rem;
    }

    .related-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1.5rem;
        border-top: 2px solid var(--gray-100);
    }

    .related-price {
        font-size: 1.4rem;
        font-weight: 800;
    }

    .price-amount {
        color: var(--primary-color);
    }

    .free-badge {
        background: linear-gradient(135deg, var(--success-color) 0%, #38a169 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        box-shadow: var(--shadow);
    }

    .btn-outline {
        background: transparent;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
        padding: 0.7rem 1.4rem;
        border-radius: var(--border-radius);
        text-decoration: none;
        font-weight: 700;
        font-size: 0.9rem;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-outline:hover {
        background: var(--primary-color);
        color: white;
        transform: translateX(4px);
        box-shadow: var(--shadow-md);
    }

    .btn-outline:hover i {
        transform: translateX(3px);
    }

    /* View all button */
    .btn-view-all {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 1rem 2rem;
        border-radius: var(--border-radius-lg);
        text-decoration: none;
        font-weight: 700;
        font-size: 1.1rem;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: var(--shadow-lg);
        margin-top: 2rem;
    }

    .btn-view-all:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-xl);
        color: white;
    }

    .btn-view-all:hover i {
        transform: translateX(4px);
    }

    /* No related courses state */
    .no-related {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--gray-500);
    }

    .no-related i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .no-related h5 {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--gray-700);
    }

    .no-related p {
        font-size: 1rem;
        margin-bottom: 1rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .related-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .related-title {
            font-size: 2rem;
        }

        .related-stats {
            flex-direction: row;
            justify-content: space-around;
        }

        .related-footer {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }

        .btn-outline {
            justify-content: center;
        }

        .overlay-content {
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-start;
        }
    }

    @media (max-width: 480px) {
        .related-content {
            padding: 1.2rem;
        }

        .related-stats {
            padding: 0.8rem;
        }

        .related-stat {
            font-size: 0.8rem;
        }

        .course-placeholder i {
            font-size: 2.8rem;
        }

        .related-section {
            padding: 2rem 0;
        }
    }

    /* ...existing styles... */
</style>

<!-- Course Header -->
<div class="course-header">
    <div class="course-header-content">
        <div class="container">
            <!-- Breadcrumb -->
            <div class="breadcrumb-wrapper">
                <div class="custom-breadcrumb">
                    <a href="<?php echo SITE_URL; ?>">
                        <i class="fas fa-home"></i>
                        Trang chủ
                    </a>
                    <span class="breadcrumb-separator">›</span>
                    <a href="<?php echo SITE_URL; ?>/courses.php">Khóa học</a>
                    <span class="breadcrumb-separator">›</span>
                    <span class="breadcrumb-current"><?php echo htmlspecialchars($course['title']); ?></span>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="course-info-header">
                        <!-- Category Badge -->
                        <div class="course-category">
                            <i class="fas fa-bookmark"></i>
                            <?php echo $course['category_name'] ?: 'Lập trình'; ?>
                        </div>

                        <!-- Course Title -->
                        <h1 class="course-title fade-in">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </h1>

                        <!-- Course Description -->
                        <p class="course-description fade-in-delay">
                            <?php echo htmlspecialchars($course['description']); ?>
                        </p>

                        <!-- Course Stats -->
                        <div class="course-stats fade-in">
                            <div class="stat-item">
                                <i class="fas fa-play-circle stat-icon"></i>
                                <span class="stat-value"><?php echo (int)$course['lesson_count']; ?></span>
                                <span class="stat-label">Bài học</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-clock stat-icon"></i>
                                <span class="stat-value"><?php echo htmlspecialchars($course['duration']); ?></span>
                                <span class="stat-label">Thời lượng</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-users stat-icon"></i>
                                <span class="stat-value"><?php echo number_format($course['students']); ?></span>
                                <span class="stat-label">Học viên</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-signal stat-icon"></i>
                                <span class="stat-value"><?php echo htmlspecialchars($course['level']); ?></span>
                                <span class="stat-label">Cấp độ</span>
                            </div>
                        </div>

                        <!-- Rating -->
                        <div class="rating-section fade-in-delay">
                            <div class="stars">
                                <?php
                                $rating = (float)$course['rating'];
                                for ($i = 1; $i <= 5; $i++):
                                ?>
                                    <i class="fas fa-star star <?php echo $i <= round($rating) ? '' : 'opacity-30'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-info">
                                <span><?php echo number_format($course['rating'], 1); ?></span>
                                <span class="rating-count">(<?php echo number_format($course['reviews_count']); ?> đánh giá)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Course Card -->
                    <div class="course-card fade-in-delay">
                        <!-- Course Preview -->
                        <div class="course-preview">
                            <?php if ($course['has_image']): ?>
                                <?php if (filter_var($course['image_path'], FILTER_VALIDATE_URL)): ?>
                                    <img src="<?php echo $course['image_path']; ?>"
                                        alt="<?php echo htmlspecialchars($course['title']); ?>"
                                        onerror="this.style.display='none';">
                                <?php else: ?>
                                    <img src="<?php echo SITE_URL; ?>/<?php echo $course['image_path']; ?>"
                                        alt="<?php echo htmlspecialchars($course['title']); ?>"
                                        onerror="this.style.display='none';">
                                <?php endif; ?>
                                <div class="play-overlay">
                                    <button class="play-button">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <i class="fas fa-book"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Price Section -->
                        <div class="price-section <?php echo $course['price'] > 0 ? 'paid' : ''; ?>">
                            <div class="price-amount">
                                <?php if ($course['price'] == 0): ?>
                                    <i class="fas fa-gift me-2"></i>Miễn phí
                                <?php else: ?>
                                    <?php echo number_format($course['price'], 0, ',', '.'); ?>đ
                                <?php endif; ?>
                            </div>
                            <div class="price-label">
                                <?php echo $course['price'] > 0 ? 'Một lần duy nhất' : 'Hoàn toàn miễn phí'; ?>
                            </div>
                        </div>

                        <!-- Course Actions -->
                        <div class="course-actions">
                            <?php echo $enrollment_message; ?>

                            <?php if (isLoggedIn()): ?>
                                <?php if ($is_enrolled): ?>
                                    <a href="<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course_id; ?>"
                                        class="btn-enroll success">
                                        <i class="fas fa-play"></i>
                                        <span>Tiếp tục học</span>
                                    </a>
                                    <div class="guarantee-text">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <span>Bạn đã đăng ký khóa học này</span>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" class="w-100">
                                        <button type="submit" name="enroll" class="btn-enroll primary">
                                            <i class="fas fa-shopping-cart"></i>
                                            <span><?php echo $course['price'] > 0 ? 'Mua khóa học' : 'Đăng ký miễn phí'; ?></span>
                                        </button>
                                    </form>
                                    <div class="guarantee-text">
                                        <i class="fas fa-shield-alt"></i>
                                        <span>Đảm bảo hoàn tiền 30 ngày</span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/login.php" class="btn-enroll warning">
                                    <i class="fas fa-sign-in-alt"></i>
                                    <span>Đăng nhập để học</span>
                                </a>
                                <div class="guarantee-text">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Cần đăng nhập để truy cập</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="content-container">
        <div class="container">
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Course Description -->
                    <div class="content-section fade-in">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-info-circle"></i>
                                Giới thiệu khóa học
                            </h2>
                        </div>
                        <div class="section-content">
                            <div class="description-content">
                                <?php echo nl2br(htmlspecialchars($course['description'])); ?>

                                <p class="mt-3">
                                    Khóa học này được thiết kế dành cho những người muốn học lập trình từ cơ bản đến nâng cao.
                                    Với phương pháp giảng dạy thực tế và dự án thực hành, bạn sẽ nhanh chóng nắm vững kiến thức
                                    và có thể ứng dụng vào công việc thực tế.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Course Content -->
                    <div class="content-section fade-in-delay">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-list-ul"></i>
                                Nội dung khóa học
                            </h2>
                            <span class="section-badge"><?php echo count($lessons); ?> bài học</span>
                        </div>
                        <div class="section-content no-padding">
                            <?php if ($lessons): ?>
                                <ul class="curriculum-list">
                                    <?php foreach ($lessons as $index => $lesson): ?>
                                        <li class="curriculum-item">
                                            <div class="lesson-content">
                                                <div class="lesson-number">
                                                    <?php echo $index + 1; ?>
                                                </div>
                                                <div class="lesson-info">
                                                    <h6><?php echo htmlspecialchars($lesson['title']); ?></h6>
                                                    <div class="lesson-meta">
                                                        <span>
                                                            <i class="fas fa-video me-1"></i>Video
                                                        </span>
                                                        <span>
                                                            <i class="fas fa-clock me-1"></i><?php echo rand(8, 25); ?> phút
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="lesson-actions">
                                                <?php if ($is_enrolled): ?>
                                                    <a href="<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course_id; ?>&lesson=<?php echo $lesson['id']; ?>"
                                                        class="btn-lesson primary">
                                                        <i class="fas fa-play"></i>Học ngay
                                                    </a>
                                                <?php else: ?>
                                                    <span class="btn-lesson disabled">
                                                        <i class="fas fa-lock"></i>Khóa
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <h5>Chưa có bài học nào</h5>
                                    <p>Khóa học này đang được cập nhật nội dung.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- What You'll Learn -->
                    <div class="content-section fade-in">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-lightbulb"></i>
                                Bạn sẽ học được gì?
                            </h2>
                        </div>
                        <div class="section-content">
                            <div class="features-grid">
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6>Kiến thức nền tảng</h6>
                                        <p>Nắm vững các khái niệm cơ bản và nguyên lý lập trình cốt lõi</p>
                                    </div>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-code"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6>Thực hành qua dự án</h6>
                                        <p>Xây dựng các ứng dụng thực tế từ đầu đến cuối</p>
                                    </div>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-rocket"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6>Sẵn sàng làm việc</h6>
                                        <p>Có đủ kỹ năng để ứng tuyển vào các vị trí công việc</p>
                                    </div>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6>Cộng đồng hỗ trợ</h6>
                                        <p>Tham gia cộng đồng học viên để thảo luận và học hỏi</p>
                                    </div>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-certificate"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6>Chứng chỉ hoàn thành</h6>
                                        <p>Nhận chứng chỉ được công nhận khi hoàn thành khóa học</p>
                                    </div>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-infinity"></i>
                                    </div>
                                    <div class="feature-content">
                                        <h6>Truy cập trọn đời</h6>
                                        <p>Học mọi lúc mọi nơi, không giới hạn thời gian</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div class="col-lg-4">
                    <!-- Course Information -->
                    <div class="sidebar-card fade-in-delay">
                        <div class="sidebar-header">
                            <i class="fas fa-info-circle"></i>
                            Thông tin khóa học
                        </div>
                        <div class="sidebar-content">
                            <ul class="info-list">
                                <li class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-tag"></i>Giá:
                                    </span>
                                    <span class="info-value">
                                        <?php if ($course['price'] == 0): ?>
                                            <span class="badge" style="background: var(--success-color);">Miễn phí</span>
                                        <?php else: ?>
                                            <?php echo number_format($course['price'], 0, ',', '.'); ?>đ
                                        <?php endif; ?>
                                    </span>
                                </li>
                                <li class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-folder"></i>Chủ đề:
                                    </span>
                                    <span class="badge"><?php echo htmlspecialchars($course['category_name'] ?: 'Lập trình'); ?></span>
                                </li>
                                <li class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-clock"></i>Thời lượng:
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($course['duration']); ?></span>
                                </li>
                                <li class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-signal"></i>Cấp độ:
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($course['level']); ?></span>
                                </li>
                                <li class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-globe"></i>Ngôn ngữ:
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($course['language']); ?></span>
                                </li>
                                <li class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-calendar"></i>Cập nhật:
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($course['updated']); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Share Course -->
                    <div class="sidebar-card fade-in">
                        <div class="sidebar-header">
                            <i class="fas fa-share-alt"></i>
                            Chia sẻ khóa học
                        </div>
                        <div class="sidebar-content">
                            <div class="share-grid">
                                <button class="btn-share facebook" onclick="shareToFacebook()">
                                    <i class="fab fa-facebook-f"></i>
                                    Chia sẻ trên Facebook
                                </button>
                                <button class="btn-share twitter" onclick="shareToTwitter()">
                                    <i class="fab fa-twitter"></i>
                                    Chia sẻ trên Twitter
                                </button>
                                <button class="btn-share linkedin" onclick="shareToLinkedIn()">
                                    <i class="fab fa-linkedin-in"></i>
                                    Chia sẻ trên LinkedIn
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Courses Section -->
            <?php if (!empty($related_courses)): ?>
                <div class="related-section">
                    <div class="container">
                        <h2 class="related-title fade-in">Khóa học liên quan</h2>
                        <div class="related-grid fade-in-delay">
                            <?php foreach ($related_courses as $related): ?>
                                <div class="related-card">
                                    <div class="related-image">
                                        <?php if ($related['course_image']): ?>
                                            <img src="<?php echo htmlspecialchars($related['course_image']); ?>"
                                                alt="<?php echo htmlspecialchars($related['title']); ?>"
                                                loading="lazy"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <!-- Fallback placeholder -->
                                            <div class="course-placeholder" style="display: none;">
                                                <i class="fas fa-graduation-cap"></i>
                                                <span class="placeholder-text">Khóa học</span>
                                            </div>
                                        <?php else: ?>
                                            <!-- Default placeholder -->
                                            <div class="course-placeholder">
                                                <i class="fas fa-graduation-cap"></i>
                                                <span class="placeholder-text">Khóa học</span>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Course overlay with info -->
                                        <div class="course-overlay">
                                            <div class="overlay-content">
                                                <div class="course-level">
                                                    <i class="fas fa-signal"></i>
                                                    <?php echo htmlspecialchars($related['level'] ?? 'Cơ bản'); ?>
                                                </div>
                                                <div class="course-lessons">
                                                    <i class="fas fa-play-circle"></i>
                                                    <?php echo $related['lesson_count']; ?> bài học
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="related-content">
                                        <div class="related-badge">
                                            <i class="fas fa-bookmark"></i>
                                            <?php echo htmlspecialchars($related['category_name'] ?: 'Lập trình'); ?>
                                        </div>

                                        <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $related['id']; ?>"
                                            class="related-title-link">
                                            <?php echo htmlspecialchars($related['title']); ?>
                                        </a>

                                        <!-- Course description excerpt -->
                                        <p class="related-description">
                                            <?php
                                            $description = strip_tags($related['description']);
                                            echo htmlspecialchars(mb_substr($description, 0, 100) . (mb_strlen($description) > 100 ? '...' : ''));
                                            ?>
                                        </p>

                                        <!-- Course stats -->
                                        <div class="related-stats">
                                            <div class="related-stat">
                                                <i class="fas fa-users"></i>
                                                <span><?php echo number_format($related['students']); ?></span>
                                            </div>
                                            <div class="related-stat">
                                                <i class="fas fa-star"></i>
                                                <span><?php echo number_format($related['rating'], 1); ?></span>
                                            </div>
                                            <div class="related-stat">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo $related['duration']; ?></span>
                                            </div>
                                        </div>

                                        <div class="related-footer">
                                            <div class="related-price">
                                                <?php if ($related['price'] == 0): ?>
                                                    <span class="free-badge">
                                                        <i class="fas fa-gift"></i>
                                                        Miễn phí
                                                    </span>
                                                <?php else: ?>
                                                    <span class="price-amount"><?php echo number_format($related['price'], 0, ',', '.'); ?>đ</span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $related['id']; ?>"
                                                class="btn-outline">
                                                <span>Xem chi tiết</span>
                                                <i class="fas fa-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- View all courses button -->
                        <div class="text-center mt-4">
                            <a href="<?php echo SITE_URL; ?>/courses.php" class="btn-view-all">
                                <span>Xem tất cả khóa học</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- <div class="related-section">
                <div class="container">
                    <div class="no-related">
                        <i class="fas fa-search"></i>
                        <h5>Không tìm thấy khóa học liên quan</h5>
                        <p>Hãy khám phá các khóa học khác trong danh mục.</p>
                        <a href="<?php echo SITE_URL; ?>/courses.php" class="btn-outline mt-3">
                            <span>Xem tất cả khóa học</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div> -->
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🎨 Course detail page loaded');

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe animated elements
        document.querySelectorAll('.fade-in, .fade-in-delay').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            observer.observe(el);
        });

        // Play button functionality
        const playButton = document.querySelector('.play-button');
        if (playButton) {
            playButton.addEventListener('click', function() {
                console.log('Play course preview');
                // Add your video preview logic here
            });
        }

        // Form submission loading state
        const enrollForm = document.querySelector('form[method="POST"]');
        if (enrollForm) {
            enrollForm.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<span class="spinner"></span> Đang xử lý...';
                    submitBtn.disabled = true;
                }
            });
        }
    });

    // Share functions
    function shareToFacebook() {
        const url = encodeURIComponent(window.location.href);
        const title = encodeURIComponent('<?php echo addslashes($course['title']); ?>');
        window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}&quote=${title}`,
            '_blank', 'width=600,height=400');
    }

    function shareToTwitter() {
        const url = encodeURIComponent(window.location.href);
        const text = encodeURIComponent('Đang học khóa: <?php echo addslashes($course['title']); ?>');
        window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`,
            '_blank', 'width=600,height=400');
    }

    function shareToLinkedIn() {
        const url = encodeURIComponent(window.location.href);
        window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${url}`,
            '_blank', 'width=600,height=400');
    }

    // Add some interactive effects
    window.addEventListener('scroll', function() {
        const courseCard = document.querySelector('.course-card');
        if (courseCard) {
            const scrolled = window.pageYOffset;
            const parallax = scrolled * 0.1;

            if (scrolled < 500) {
                courseCard.style.transform = `translateY(${-6 * 16 + parallax}px)`;
            }
        }
    });

    console.log('✨ Course detail page initialized successfully!');
</script>

<?php include 'includes/footer.php'; ?>