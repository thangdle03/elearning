
<?php
require_once 'includes/config.php';

echo "<h2>🔍 Database Status Check</h2>";

$tables_to_check = [
    'users' => ['id', 'username', 'email', 'password', 'role', 'status', 'full_name', 'phone', 'bio', 'avatar', 'created_at', 'updated_at'],
    'categories' => ['id', 'name', 'description', 'created_at'],
    'courses' => ['id', 'title', 'description', 'thumbnail', 'price', 'category_id', 'status', 'created_at', 'updated_at'],
    'lessons' => ['id', 'course_id', 'title', 'content', 'video_url', 'order_index', 'created_at'],
    'enrollments' => ['id', 'user_id', 'course_id', 'enrolled_at'],
    'progress' => ['id', 'user_id', 'lesson_id', 'completed', 'completed_at']
];

$all_good = true;

foreach ($tables_to_check as $table => $required_columns) {
    echo "<h3>Table: $table</h3>";
    
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if (!$stmt->fetch()) {
            echo "❌ Table $table does NOT exist<br>";
            $all_good = false;
            continue;
        }
        echo "✅ Table $table exists<br>";
        
        // Check columns
        $stmt = $pdo->query("DESCRIBE $table");
        $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<ul>";
        foreach ($required_columns as $column) {
            if (in_array($column, $existing_columns)) {
                echo "<li>✅ $column</li>";
            } else {
                echo "<li>❌ $column (MISSING)</li>";
                $all_good = false;
            }
        }
        echo "</ul>";
        
        // Show row count
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "📊 Rows: $count<br>";
        
    } catch (Exception $e) {
        echo "❌ Error checking $table: " . $e->getMessage() . "<br>";
        $all_good = false;
    }
    
    echo "<br>";
}

if ($all_good) {
    echo "<h2 style='color: green;'>🎉 All tables are properly configured!</h2>";
} else {
    echo "<h2 style='color: red;'>⚠️ Some issues found. Please run fix-all-tables.php</h2>";
    echo "<a href='fix-all-tables.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Fix Database Now</a>";
}
?>