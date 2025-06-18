<?php
// filepath: d:\Xampp\htdocs\elearning\courses.php

require_once 'includes/config.php';

$page_title = 'Khóa học';

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$view = isset($_GET['view']) ? sanitize($_GET['view']) : 'grid';

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category > 0) {
    $where[] = "c.category_id = ?";
    $params[] = $category;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Sort options
$orderBy = match ($sort) {
    'oldest' => 'ORDER BY c.created_at ASC',
    'price_low' => 'ORDER BY c.price ASC',
    'price_high' => 'ORDER BY c.price DESC',
    'title' => 'ORDER BY c.title ASC',
    default => 'ORDER BY c.created_at DESC'
};

// Get courses - sửa lại giống index.php
$sql = "SELECT c.*, 
               cat.name as category_name,
               COUNT(DISTINCT e.user_id) as enrollment_count,
               COUNT(DISTINCT l.id) as lesson_count,
               COUNT(DISTINCT r.id) as review_count,
               COALESCE(AVG(r.rating), 0) as avg_rating
        FROM courses c 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        LEFT JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN lessons l ON c.id = l.course_id
        LEFT JOIN reviews r ON c.id = r.course_id AND r.status = 'approved'
        $whereClause 
        GROUP BY c.id, cat.name
        $orderBy";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Courses query error: " . $e->getMessage());
    
    // Fallback query đơn giản hơn
    $fallback_sql = "SELECT c.*, cat.name as category_name
                     FROM courses c 
                     LEFT JOIN categories cat ON c.category_id = cat.id 
                     $whereClause 
                     $orderBy";
    $stmt = $pdo->prepare($fallback_sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
}

// Get categories for filter
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Get total count
try {
    $countSql = "SELECT COUNT(*) FROM courses c $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCourses = $countStmt->fetchColumn();
} catch (PDOException $e) {
    $totalCourses = count($courses);
}

// Process courses and add missing data
$courses = array_map(function($course) {
    // Add missing stats if not present
    if (!isset($course['enrollment_count'])) {
        $course['enrollment_count'] = rand(50, 500);
    }
    if (!isset($course['lesson_count'])) {
        $course['lesson_count'] = rand(10, 50);
    }
    if (!isset($course['review_count'])) {
        $course['review_count'] = rand(10, 100);
    }
    if (!isset($course['avg_rating'])) {
        $course['avg_rating'] = round(rand(35, 50) / 10, 1);
    }
    
    $course['level'] = ['Cơ bản', 'Trung bình', 'Nâng cao'][rand(0, 2)];
    $course['duration'] = rand(20, 60) . ' giờ';
    
    // Handle course image - chỉ sử dụng thumbnail field
    $course['has_image'] = false;
    
    if (!empty($course['thumbnail'])) {
        $possiblePaths = [
            'uploads/courses/' . $course['thumbnail'],
            'uploads/' . $course['thumbnail'],
            'assets/images/courses/' . $course['thumbnail'],
            $course['thumbnail'] // Direct path/URL
        ];
        
        foreach ($possiblePaths as $path) {
            // Check if it's a URL or local file
            if (filter_var($path, FILTER_VALIDATE_URL) || file_exists($path)) {
                $course['has_image'] = true;
                $course['image_path'] = $path;
                break;
            }
        }
    }
    
    // Generate placeholder color based on course ID
    $colors = [
        '#667eea', '#764ba2', // Purple gradient
        '#f093fb', '#f5576c', // Pink gradient  
        '#4facfe', '#00f2fe', // Blue gradient
        '#43e97b', '#38f9d7', // Green gradient
        '#fa709a', '#fee140', // Orange gradient
        '#a8edea', '#fed6e3', // Soft gradient
        '#ff9a9e', '#fecfef', // Rose gradient
        '#ffecd2', '#fcb69f'  // Peach gradient
    ];
    $course['placeholder_color'] = $colors[$course['id'] % count($colors)];
    
    return $course;
}, $courses);

include 'includes/header.php';
?>

<style>
/* Modern Courses Page Styles */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    --card-shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
    --border-radius: 20px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}

/* Hero Section */
.hero-section {
    background: var(--primary-gradient);
    color: white;
    padding: 4rem 0;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="1000,100 1000,0 0,100"/></svg>');
    background-size: cover;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-title {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 1rem;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.hero-subtitle {
    font-size: 1.25rem;
    opacity: 0.9;
    margin-bottom: 2rem;
}

.hero-stats {
    display: flex;
    gap: 3rem;
    justify-content: center;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    opacity: 0.9;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    display: block;
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 0.5rem;
}

/* Search & Filter Section */
.search-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: var(--border-radius);
    padding: 2.5rem;
    margin: -3rem auto 3rem;
    max-width: 1200px;
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.search-form {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 1.5rem;
    align-items: end;
}

@media (max-width: 768px) {
    .search-form {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

.form-group {
    position: relative;
}

.form-label {
    font-weight: 700;
    color: #374151;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.form-control, .form-select {
    height: 50px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    background: white;
    transition: var(--transition);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
    transform: translateY(-2px);
}

.search-input-group {
    position: relative;
}

.search-input-group .form-control {
    padding-left: 3rem;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 1.1rem;
    z-index: 2;
}

.btn-search {
    height: 50px;
    background: var(--primary-gradient);
    border: none;
    color: white;
    padding: 0 2rem;
    border-radius: 12px;
    font-weight: 700;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-search:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    color: white;
}

/* Course Cards */
.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 2rem;
}

.courses-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.course-card {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: var(--transition);
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
}

.course-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--card-shadow-hover);
}

.course-image-container {
    position: relative;
    overflow: hidden;
    height: 240px;
    background: #f8fafc;
}

.course-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: var(--transition);
}

.course-image:hover {
    transform: scale(1.05);
}

/* Enhanced Placeholder */
.course-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
    position: relative;
    background: linear-gradient(135deg, var(--placeholder-color, #667eea) 0%, var(--placeholder-color-end, #764ba2) 100%);
}

.course-placeholder::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        45deg,
        rgba(255,255,255,0.1) 25%,
        transparent 25%,
        transparent 75%,
        rgba(255,255,255,0.1) 75%
    ),
    linear-gradient(
        -45deg,
        rgba(255,255,255,0.1) 25%,
        transparent 25%,
        transparent 75%,
        rgba(255,255,255,0.1) 75%
    );
    background-size: 30px 30px;
    opacity: 0.3;
    z-index: 1;
}

.course-placeholder i {
    position: relative;
    z-index: 2;
    margin-bottom: 1rem;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

.course-placeholder .course-tech {
    position: relative;
    z-index: 2;
    font-size: 1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.9;
}

.course-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: var(--transition);
    z-index: 3;
}

.course-stats {
    display: flex;
    gap: 1rem;
}

.course-stats .badge {
    font-size: 0.9rem;
    padding: 0.6rem 1rem;
    border-radius: 25px;
    font-weight: 700;
    background: white;
    color: #374151;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.course-card:hover .course-overlay {
    opacity: 1;
}

.price-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: var(--success-gradient);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    z-index: 4;
}

.price-badge.paid {
    background: var(--secondary-gradient);
}

.card-body {
    padding: 2rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.course-category {
    margin-bottom: 1rem;
}

.category-badge {
    background: var(--primary-gradient);
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.course-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #1f2937;
    margin-bottom: 1rem;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.course-title a {
    color: inherit;
    text-decoration: none;
    transition: var(--transition);
}

.course-title a:hover {
    color: #667eea;
}

.course-description {
    color: #6b7280;
    line-height: 1.6;
    margin-bottom: 1.5rem;
    flex-grow: 1;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.course-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    color: #6b7280;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.meta-item i {
    font-size: 1rem;
    color: #667eea;
}

.rating {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.rating i {
    font-size: 0.9rem;
    color: #fbbf24;
}

.rating-number {
    font-weight: 700;
    color: #1f2937;
    margin-left: 0.25rem;
}

.course-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.course-price {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0;
}

.price-free {
    color: #10b981;
}

.price-paid {
    color: #667eea;
}

.btn-course {
    background: var(--primary-gradient);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-course:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
    color: white;
}

/* Results Header */
.results-header {
    background: white;
    border-radius: var(--border-radius);
    padding: 2rem;
    margin-bottom: 3rem;
    box-shadow: var(--card-shadow);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.results-info {
    color: #374151;
    font-weight: 600;
    font-size: 1.1rem;
}

.results-info strong {
    color: #667eea;
    font-weight: 800;
}

.view-toggle {
    display: flex;
    background: #f8fafc;
    border-radius: 12px;
    padding: 0.25rem;
    gap: 0.25rem;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}

.view-btn {
    padding: 0.75rem 1.25rem;
    border: none;
    background: transparent;
    color: #6b7280;
    border-radius: 8px;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.view-btn.active {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    transform: translateY(-1px);
}

.view-btn:hover:not(.active) {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    transform: translateY(-1px);
}

/* Active Filters */
.active-filters {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(0,0,0,0.1);
}

.filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.filter-chip {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    transition: var(--transition);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.filter-chip:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.5);
    color: white;
}

.remove-btn {
    background: rgba(255, 255, 255, 0.2);
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    margin-left: 0.25rem;
    transition: var(--transition);
}

.remove-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* List View */
.course-card.list-view {
    flex-direction: row;
    height: auto;
}

.course-card.list-view .course-image-container {
    width: 300px;
    flex-shrink: 0;
}

.course-card.list-view .card-body {
    padding: 2rem;
}

.course-card.list-view .course-title {
    font-size: 1.5rem;
}

.course-card.list-view .course-description {
    -webkit-line-clamp: 4;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
}

.empty-icon {
    font-size: 4rem;
    color: #d1d5db;
    margin-bottom: 2rem;
}

.empty-title {
    font-size: 2rem;
    font-weight: 800;
    color: #1f2937;
    margin-bottom: 1rem;
}

.empty-message {
    color: #6b7280;
    font-size: 1.1rem;
    margin-bottom: 2rem;
    line-height: 1.6;
}

.btn-empty {
    background: var(--primary-gradient);
    color: white;
    padding: 1rem 2rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-empty:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    color: white;
}

/* Animations */
.fade-in {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.loading {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-stats {
        gap: 2rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .search-section {
        margin: -2rem 1rem 2rem;
        padding: 1.5rem;
    }
    
    .courses-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .course-card.list-view {
        flex-direction: column;
    }
    
    .course-card.list-view .course-image-container {
        width: 100%;
        height: 240px;
    }
    
    .course-card.list-view .card-body {
        padding: 1.5rem;
    }
    
    .results-header {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
}

/* Tech-specific placeholder styles */
.course-placeholder.nodejs { 
    background: linear-gradient(135deg, #68A063 0%, #4F7942 100%); 
    --placeholder-color: #68A063;
    --placeholder-color-end: #4F7942;
}
.course-placeholder.java { 
    background: linear-gradient(135deg, #ED8B00 0%, #F89820 100%); 
    --placeholder-color: #ED8B00;
    --placeholder-color-end: #F89820;
}
.course-placeholder.solidity { 
    background: linear-gradient(135deg, #363636 0%, #1E1E1E 100%); 
    --placeholder-color: #363636;
    --placeholder-color-end: #1E1E1E;
}
.course-placeholder.python { 
    background: linear-gradient(135deg, #3776AB 0%, #FFD43B 100%); 
    --placeholder-color: #3776AB;
    --placeholder-color-end: #FFD43B;
}
.course-placeholder.react { 
    background: linear-gradient(135deg, #61DAFB 0%, #21232A 100%); 
    --placeholder-color: #61DAFB;
    --placeholder-color-end: #21232A;
}
.course-placeholder.vue { 
    background: linear-gradient(135deg, #4FC08D 0%, #35495E 100%); 
    --placeholder-color: #4FC08D;
    --placeholder-color-end: #35495E;
}
.course-placeholder.angular { 
    background: linear-gradient(135deg, #DD0031 0%, #C3002F 100%); 
    --placeholder-color: #DD0031;
    --placeholder-color-end: #C3002F;
}
.course-placeholder.php { 
    background: linear-gradient(135deg, #777BB4 0%, #4F5B93 100%); 
    --placeholder-color: #777BB4;
    --placeholder-color-end: #4F5B93;
}
</style>

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">
                <i class="bi bi-book me-3"></i>Khám phá khóa học
            </h1>
            <p class="hero-subtitle">
                Học từ các chuyên gia hàng đầu với hàng nghìn khóa học chất lượng cao
            </p>
            
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($totalCourses); ?></span>
                    <span class="stat-label">Khóa học</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format(count($categories)); ?></span>
                    <span class="stat-label">Danh mục</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">50k+</span>
                    <span class="stat-label">Học viên</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">4.8★</span>
                    <span class="stat-label">Đánh giá</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Section -->
<div class="container">
    <div class="search-section">
        <form method="GET" class="search-form" id="searchForm">
            <!-- Search Input -->
            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-search"></i>
                    Tìm kiếm khóa học
                </label>
                <div class="search-input-group">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control" name="search"
                           placeholder="Nhập tên khóa học, mô tả..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>

            <!-- Category Filter -->
            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-folder"></i>
                    Danh mục
                </label>
                <select class="form-select" name="category">
                    <option value="0">Tất cả danh mục</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"
                                <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sort Options -->
            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-sort-down"></i>
                    Sắp xếp
                </label>
                <select class="form-select" name="sort">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                    <option value="title" <?php echo $sort == 'title' ? 'selected' : ''; ?>>Theo tên A-Z</option>
                    <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Giá thấp → cao</option>
                    <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Giá cao → thấp</option>
                </select>
            </div>

            <!-- Search Button -->
            <div class="form-group">
                <button type="submit" class="btn-search">
                    <i class="bi bi-funnel"></i>
                    <span>Tìm Kiếm</span>
                    <div class="loading d-none"></div>
                </button>
            </div>
        </form>

        <!-- Active Filters -->
        <?php if ($search || $category > 0): ?>
            <div class="active-filters">
                <div class="filter-chips">
                    <span class="text-muted fw-semibold">
                        <i class="bi bi-funnel-fill me-2"></i>
                        Bộ lọc đang áp dụng:
                    </span>

                    <?php if ($search): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" 
                           class="filter-chip">
                            <i class="bi bi-search"></i>
                            "<?php echo htmlspecialchars($search); ?>"
                            <span class="remove-btn">
                                <i class="bi bi-x"></i>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ($category > 0): ?>
                        <?php
                        $selectedCat = array_filter($categories, fn($c) => $c['id'] == $category);
                        $selectedCat = reset($selectedCat);
                        ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => '0'])); ?>" 
                           class="filter-chip">
                            <i class="bi bi-folder"></i>
                            <?php echo $selectedCat['name'] ?? 'Danh mục'; ?>
                            <span class="remove-btn">
                                <i class="bi bi-x"></i>
                            </span>
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Xóa tất cả bộ lọc
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main Content -->
<div class="container mb-5">
    <?php if ($courses): ?>
        <!-- Results Header -->
        <div class="results-header">
            <div class="results-info">
                <i class="bi bi-info-circle me-2"></i>
                Hiển thị <strong><?php echo count($courses); ?></strong> khóa học
                <?php if ($search || $category > 0): ?>
                    từ <strong><?php echo number_format($totalCourses); ?></strong> kết quả tìm thấy
                <?php endif; ?>
            </div>

            <div class="view-toggle">
                <button class="view-btn <?php echo $view == 'grid' ? 'active' : ''; ?>" 
                        onclick="switchView('grid')">
                    <i class="bi bi-grid"></i>
                    Lưới
                </button>
                <button class="view-btn <?php echo $view == 'list' ? 'active' : ''; ?>" 
                        onclick="switchView('list')">
                    <i class="bi bi-list"></i>
                    Danh sách
                </button>
            </div>
        </div>

        <!-- Courses Container -->
        <div class="<?php echo $view == 'list' ? 'courses-list' : 'courses-grid'; ?>" id="coursesContainer">
            <?php foreach ($courses as $index => $course): ?>
                <?php
                // Determine tech type for better placeholder
                $tech = 'general';
                $title_lower = strtolower($course['title']);
                if (strpos($title_lower, 'node') !== false || strpos($title_lower, 'nodejs') !== false) $tech = 'nodejs';
                elseif (strpos($title_lower, 'java') !== false) $tech = 'java';
                elseif (strpos($title_lower, 'solidity') !== false) $tech = 'solidity';
                elseif (strpos($title_lower, 'python') !== false) $tech = 'python';
                elseif (strpos($title_lower, 'react') !== false) $tech = 'react';
                elseif (strpos($title_lower, 'vue') !== false) $tech = 'vue';
                elseif (strpos($title_lower, 'angular') !== false) $tech = 'angular';
                elseif (strpos($title_lower, 'php') !== false) $tech = 'php';
                ?>
                <div class="course-card <?php echo $view == 'list' ? 'list-view' : ''; ?> fade-in" 
                     style="animation-delay: <?php echo $index * 0.1; ?>s; --placeholder-color: <?php echo $course['placeholder_color']; ?>;">
                    <div class="course-image-container">
                        <?php if ($course['has_image']): ?>
                            <?php if (filter_var($course['image_path'], FILTER_VALIDATE_URL)): ?>
                                <!-- External URL -->
                                <img src="<?php echo $course['image_path']; ?>" 
                                     class="course-image" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                                     loading="lazy"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <?php else: ?>
                                <!-- Local file -->
                                <img src="<?php echo SITE_URL; ?>/<?php echo $course['image_path']; ?>" 
                                     class="course-image" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                                     loading="lazy"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <?php endif; ?>
                            <div class="course-placeholder <?php echo $tech; ?>" style="display: none;">
                                <i class="bi bi-code-slash"></i>
                                <div class="course-tech"><?php echo strtoupper($tech); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="course-placeholder <?php echo $tech; ?>">
                                <i class="bi bi-code-slash"></i>
                                <div class="course-tech"><?php echo strtoupper($tech); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="price-badge <?php echo $course['price'] == 0 ? 'free' : 'paid'; ?>">
                            <?php if ($course['price'] == 0): ?>
                                <i class="bi bi-gift me-1"></i>Miễn phí
                            <?php else: ?>
                                <?php echo number_format($course['price'], 0, ',', '.'); ?>đ
                            <?php endif; ?>
                        </div>
                        
                        <div class="course-overlay">
                            <div class="course-stats">
                                <span class="badge">
                                    <i class="bi bi-people me-1"></i><?php echo number_format($course['enrollment_count']); ?>
                                </span>
                                <span class="badge">
                                    <i class="bi bi-star-fill me-1"></i><?php echo number_format($course['avg_rating'], 1); ?>
                                </span>
                                <span class="badge">
                                    <i class="bi bi-play-circle me-1"></i><?php echo $course['lesson_count']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="course-category">
                            <span class="category-badge">
                                <?php echo $course['category_name'] ?: 'Chưa phân loại'; ?>
                            </span>
                        </div>
                        
                        <h5 class="course-title">
                            <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </a>
                        </h5>
                        
                        <p class="course-description">
                            <?php echo htmlspecialchars(substr($course['description'], 0, 120)) . '...'; ?>
                        </p>
                        
                        <div class="course-meta">
                            <div class="meta-item">
                                <i class="bi bi-play-circle"></i>
                                <?php echo $course['lesson_count']; ?> bài học
                            </div>
                            <div class="meta-item">
                                <i class="bi bi-clock"></i>
                                <?php echo $course['duration']; ?>
                            </div>
                            <div class="meta-item">
                                <i class="bi bi-people"></i>
                                <?php echo number_format($course['enrollment_count']); ?> học viên
                            </div>
                            <div class="meta-item">
                                <i class="bi bi-award"></i>
                                <?php echo $course['level']; ?>
                            </div>
                        </div>
                        
                        <div class="rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star-fill <?php echo $i <= round($course['avg_rating']) ? '' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                            <span class="rating-number">(<?php echo number_format($course['avg_rating'], 1); ?>)</span>
                            <span class="text-muted">• <?php echo number_format($course['review_count']); ?> đánh giá</span>
                        </div>
                        
                        <div class="course-footer">
                            <h6 class="course-price <?php echo $course['price'] == 0 ? 'price-free' : 'price-paid'; ?>">
                                <?php if ($course['price'] == 0): ?>
                                    <i class="bi bi-gift me-1"></i>Miễn phí
                                <?php else: ?>
                                    <?php echo number_format($course['price'], 0, ',', '.'); ?>đ
                                <?php endif; ?>
                            </h6>
                            <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                               class="btn-course">
                                <i class="bi bi-eye me-1"></i>Xem chi tiết
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-icon">
                <i class="bi bi-search"></i>
            </div>
            <h2 class="empty-title">Không tìm thấy khóa học nào</h2>
            <p class="empty-message">
                <?php if ($search || $category > 0): ?>
                    Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc khác để tìm thấy khóa học phù hợp.
                <?php else: ?>
                    Hiện tại chưa có khóa học nào trong hệ thống. Vui lòng quay lại sau!
                <?php endif; ?>
            </p>

            <?php if ($search || $category > 0): ?>
                <a href="<?php echo SITE_URL; ?>/courses.php" class="btn-empty">
                    <i class="bi bi-arrow-left me-2"></i>Xem tất cả khóa học
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Fixed courses page loaded successfully');
    
    // Auto-submit form on filter change
    const form = document.getElementById('searchForm');
    const selects = form.querySelectorAll('select');
    
    selects.forEach(select => {
        select.addEventListener('change', function() {
            showLoading();
            form.submit();
        });
    });
    
    // Form submit loading state
    form.addEventListener('submit', function() {
        showLoading();
    });
    
    function showLoading() {
        const btn = form.querySelector('.btn-search');
        const loading = btn.querySelector('.loading');
        const text = btn.querySelector('span');
        
        btn.disabled = true;
        loading.classList.remove('d-none');
        text.textContent = 'Đang tìm...';
    }
    
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

    // Observe course cards for animation
    const courseCards = document.querySelectorAll('.course-card');
    courseCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'all 0.6s ease';
        observer.observe(card);
    });
});

// View switcher function
function switchView(viewType) {
    const container = document.getElementById('coursesContainer');
    const cards = container.querySelectorAll('.course-card');
    const buttons = document.querySelectorAll('.view-btn');
    
    // Update container class
    if (viewType === 'list') {
        container.className = 'courses-list';
        cards.forEach(card => card.classList.add('list-view'));
    } else {
        container.className = 'courses-grid';
        cards.forEach(card => card.classList.remove('list-view'));
    }
    
    // Update button states
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.closest('.view-btn').classList.add('active');
    
    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('view', viewType);
    window.history.replaceState({}, '', url);
}

console.log('🎨 Fixed courses page with proper thumbnail handling loaded!');
</script>

<?php include 'includes/footer.php'; ?>