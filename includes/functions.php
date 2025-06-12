<?php
// includes/functions.php
/**
 * Hàm tiện ích chung cho hệ thống
 */

// Format thời gian tiếng Việt
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Vừa xong';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' phút trước';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' giờ trước';
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . ' ngày trước';
    } else {
        return date('d/m/Y', $time);
    }
}

// Format số tiền VNĐ
function formatPrice($price)
{
    return number_format($price, 0, ',', '.') . 'đ';
}

// Tạo slug từ tiếng Việt
function createSlug($string)
{
    $vietnameseMap = [
        'à' => 'a',
        'á' => 'a',
        'ạ' => 'a',
        'ả' => 'a',
        'ã' => 'a',
        'ầ' => 'a',
        'ấ' => 'a',
        'ậ' => 'a',
        'ẩ' => 'a',
        'ẫ' => 'a',
        'ằ' => 'a',
        'ắ' => 'a',
        'ặ' => 'a',
        'ẳ' => 'a',
        'ẵ' => 'a',
        'è' => 'e',
        'é' => 'e',
        'ẹ' => 'e',
        'ẻ' => 'e',
        'ẽ' => 'e',
        'ề' => 'e',
        'ế' => 'e',
        'ệ' => 'e',
        'ể' => 'e',
        'ễ' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'ị' => 'i',
        'ỉ' => 'i',
        'ĩ' => 'i',
        'ò' => 'o',
        'ó' => 'o',
        'ọ' => 'o',
        'ỏ' => 'o',
        'õ' => 'o',
        'ồ' => 'o',
        'ố' => 'o',
        'ộ' => 'o',
        'ổ' => 'o',
        'ỗ' => 'o',
        'ờ' => 'o',
        'ớ' => 'o',
        'ợ' => 'o',
        'ở' => 'o',
        'ỡ' => 'o',
        'ù' => 'u',
        'ú' => 'u',
        'ụ' => 'u',
        'ủ' => 'u',
        'ũ' => 'u',
        'ừ' => 'u',
        'ứ' => 'u',
        'ự' => 'u',
        'ử' => 'u',
        'ữ' => 'u',
        'ỳ' => 'y',
        'ý' => 'y',
        'ỵ' => 'y',
        'ỷ' => 'y',
        'ỹ' => 'y',
        'đ' => 'd',
        'Đ' => 'd'
    ];

    $string = strtr($string, $vietnameseMap);
    $string = mb_strtolower($string, 'UTF-8');
    $string = preg_replace('/[^a-z0-9]+/i', '-', $string);
    $string = trim($string, '-');
    return $string;
}

// Upload thumbnail
function uploadThumbnail($file)
{
    $uploadDir = '../assets/uploads/thumbnails/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return 'assets/uploads/thumbnails/' . $filename;
    }

    return false;
}

// Kiểm tra quyền truy cập
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: /');
        exit;
    }
}

// Pagination helper
function paginate($query, $params = [], $perPage = 12)
{
    global $pdo;

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $perPage;

    // Count total records
    $countQuery = "SELECT COUNT(*) FROM ($query) as count_table";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();

    // Get paginated records
    $query .= " LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'records' => $records,
        'total' => $totalRecords,
        'perPage' => $perPage,
        'currentPage' => $page,
        'totalPages' => ceil($totalRecords / $perPage)
    ];
}
