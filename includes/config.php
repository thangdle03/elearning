<?php

// includes/config.php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'elearning_simple';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Site configuration - SỬA ĐỔI CỔNG
define('SITE_URL', 'http://localhost:8080/elearning');
define('SITE_NAME', 'E-Learning Platform');

// Helper functions
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function getYoutubeId($url)
{
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches);
    return $matches[1] ?? '';
}

function redirect($url)
{
    header("Location: $url");
    exit();
}

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatPrice($price)
{
    return $price == 0 ? 'Miễn phí' : number_format($price) . ' ₫';
}

function isEnrolled($user_id, $course_id, $pdo)
{
    $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    return $stmt->fetchColumn() !== false;
}
?>