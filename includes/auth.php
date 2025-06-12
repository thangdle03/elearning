
<?php
/**
 * Xử lý authentication
 */

// Kiểm tra login
function checkLogin($username, $password)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }

    return false;
}

// Đăng ký user mới
function registerUser($username, $email, $password)
{
    global $pdo;

    // Check existing
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username hoặc email đã tồn tại'];
    }

    // Insert new user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");

    if ($stmt->execute([$username, $email, $hashedPassword])) {
        return ['success' => true, 'message' => 'Đăng ký thành công'];
    }

    return ['success' => false, 'message' => 'Có lỗi xảy ra'];
}

// Set login session
function setLoginSession($user)
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
}

// Clear session
function logout()
{
    session_start();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Update user profile
function updateProfile($userId, $data)
{
    global $pdo;

    $updates = [];
    $params = [];

    if (isset($data['email'])) {
        $updates[] = "email = ?";
        $params[] = $data['email'];
    }

    if (isset($data['password']) && !empty($data['password'])) {
        $updates[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (empty($updates)) {
        return false;
    }

    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute($params);
}
