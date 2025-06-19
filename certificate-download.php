<?php
// filepath: d:\Xampp\htdocs\elearning\certificate-download.php

require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php');
}

$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$certificate_id) {
    redirect(SITE_URL . '/certificates.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT cert.*, c.title as course_title, c.description as course_description,
               cat.name as category_name, u.full_name as user_full_name, u.username
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON cert.user_id = u.id
        WHERE cert.id = ? AND cert.user_id = ?
    ");
    $stmt->execute([$certificate_id, $_SESSION['user_id']]);
    $certificate = $stmt->fetch();

    if (!$certificate) {
        redirect(SITE_URL . '/certificates.php');
    }
} catch (PDOException $e) {
    redirect(SITE_URL . '/certificates.php');
}

// Set headers for HTML download (browser will handle PDF conversion)
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="chung-chi-' . $certificate['certificate_code'] . '.html"');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chứng chỉ - <?php echo htmlspecialchars($certificate['course_title']); ?></title>
    <style>
        @page { 
            margin: 30px; 
            size: A4 landscape;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Times New Roman", serif;
            background: white;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .certificate {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 80px 60px;
            border: 15px solid #667eea;
            border-radius: 20px;
            text-align: center;
            position: relative;
            min-height: 700px;
            background: white;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }
        
        .certificate::before {
            content: "";
            position: absolute;
            top: 25px;
            left: 25px;
            right: 25px;
            bottom: 25px;
            border: 3px solid #764ba2;
            border-radius: 10px;
            pointer-events: none;
        }
        
        .header {
            margin-bottom: 50px;
            position: relative;
        }
        
        .title {
            font-size: 4rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            letter-spacing: 12px;
            text-transform: uppercase;
        }
        
        .subtitle {
            font-size: 1.8rem;
            color: #666;
            margin-bottom: 30px;
            font-style: italic;
            font-weight: 300;
        }
        
        .ornament {
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            margin: 20px auto;
            border-radius: 2px;
        }
        
        .content {
            margin: 50px 0;
        }
        
        .presentation-text {
            font-size: 1.5rem;
            margin-bottom: 30px;
            color: #555;
        }
        
        .recipient-name {
            font-size: 3.5rem;
            font-weight: bold;
            color: #333;
            border-bottom: 5px solid #667eea;
            display: inline-block;
            padding: 15px 50px;
            margin: 30px 0;
            position: relative;
        }
        
        .recipient-name::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: #764ba2;
        }
        
        .completion-text {
            font-size: 1.5rem;
            margin: 40px 0;
            color: #555;
        }
        
        .course-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: #764ba2;
            margin: 40px 0;
            font-style: italic;
            line-height: 1.3;
        }
        
        .course-info {
            font-size: 1.2rem;
            color: #666;
            margin: 30px 0;
        }
        
        .details {
            display: flex;
            justify-content: space-around;
            margin: 60px 0;
            padding: 30px 0;
            border-top: 2px solid #eee;
            border-bottom: 2px solid #eee;
        }
        
        .detail-item {
            text-align: center;
            flex: 1;
        }
        
        .detail-label {
            font-weight: bold;
            color: #333;
            display: block;
            margin-bottom: 10px;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .detail-value {
            color: #667eea;
            font-weight: bold;
            font-size: 1.4rem;
        }
        
        .footer {
            margin-top: 80px;
            display: flex;
            justify-content: space-between;
            align-items: end;
        }
        
        .signature-section {
            text-align: center;
            width: 250px;
        }
        
        .signature-line {
            border-bottom: 2px solid #333;
            margin-bottom: 15px;
            height: 60px;
        }
        
        .signature-title {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .signature-subtitle {
            font-size: 0.9rem;
            color: #666;
        }
        
        .logo-section {
            text-align: center;
            flex: 1;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .platform-name {
            font-weight: bold;
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 5px;
        }
        
        .platform-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 3px;
        }
        
        .verification {
            text-align: center;
            margin-top: 50px;
            font-size: 0.9rem;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 30px;
            line-height: 1.8;
        }
        
        .certificate-code {
            font-weight: bold;
            color: #667eea;
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
            margin: 0 5px;
        }
        
        .seal {
            position: absolute;
            top: 100px;
            right: 100px;
            width: 120px;
            height: 120px;
            border: 10px solid #667eea;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #667eea;
            font-weight: bold;
        }
        
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        @media print {
            .print-controls {
                display: none !important;
            }
            
            body {
                padding: 0;
            }
            
            .certificate {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                max-width: none;
                min-height: auto;
            }
        }
        
        @media (max-width: 768px) {
            .certificate {
                padding: 40px 30px;
                border-width: 10px;
            }
            
            .title {
                font-size: 2.5rem;
                letter-spacing: 6px;
            }
            
            .recipient-name {
                font-size: 2.2rem;
                padding: 10px 30px;
            }
            
            .course-title {
                font-size: 1.8rem;
            }
            
            .details {
                flex-direction: column;
                gap: 20px;
            }
            
            .footer {
                flex-direction: column;
                gap: 40px;
                text-align: center;
            }
            
            .signature-section {
                width: auto;
            }
            
            .seal {
                width: 80px;
                height: 80px;
                top: 60px;
                right: 60px;
                font-size: 1.5rem;
                border-width: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="print-controls">
        <button class="btn btn-success" onclick="window.print()">
            🖨️ In ngay
        </button>
        <button class="btn btn-primary" onclick="savePDF()">
            📄 Lưu PDF
        </button>
        <a href="<?php echo SITE_URL; ?>/certificates.php" class="btn btn-secondary">
            ← Quay lại
        </a>
    </div>
    
    <div class="certificate">
        <div class="seal">★</div>
        
        <div class="header">
            <div class="title">Chứng Chỉ</div>
            <div class="ornament"></div>
            <div class="subtitle">Certificate of Completion</div>
        </div>
        
        <div class="content">
            <div class="presentation-text">Chứng nhận rằng</div>
            
            <div class="recipient-name"><?php echo htmlspecialchars($certificate['user_full_name'] ?: $certificate['username']); ?></div>
            
            <div class="completion-text">đã hoàn thành xuất sắc khóa học</div>
            
            <div class="course-title">"<?php echo htmlspecialchars($certificate['course_title']); ?>"</div>

            <?php if ($certificate['category_name']): ?>
            <div class="course-info">Thuộc lĩnh vực: <strong><?php echo htmlspecialchars($certificate['category_name']); ?></strong></div>
            <?php endif; ?>
            
            <div class="details">
                <div class="detail-item">
                    <span class="detail-label">Thời lượng khóa học</span>
                    <span class="detail-value"><?php echo $certificate['course_duration_hours']; ?> giờ</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Kết quả đánh giá</span>
                    <span class="detail-value"><?php echo $certificate['grade']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Ngày hoàn thành</span>
                    <span class="detail-value"><?php echo date('d/m/Y', strtotime($certificate['completion_date'])); ?></span>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-title">Giám đốc Đào tạo</div>
                <div class="signature-subtitle">E-Learning Platform</div>
            </div>
            
            <div class="logo-section">
                <div class="logo">EL</div>
                <div class="platform-name">E-Learning Platform</div>
                <div class="platform-subtitle">Nền tảng học trực tuyến hàng đầu</div>
                <div class="platform-subtitle">www.elearning.com</div>
            </div>
            
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-title">Ban Giám đốc</div>
                <div class="signature-subtitle"><?php echo date('d/m/Y', strtotime($certificate['issued_date'])); ?></div>
            </div>
        </div>
        
        <div class="verification">
            <strong>Mã chứng chỉ:</strong> <span class="certificate-code"><?php echo $certificate['certificate_code']; ?></span><br>
            <strong>Xác thực trực tuyến tại:</strong> <?php echo SITE_URL; ?>/verify?code=<?php echo $certificate['certificate_code']; ?><br>
            <em>Chứng chỉ này được cấp bởi E-Learning Platform và có giá trị pháp lý đầy đủ.</em>
        </div>
    </div>

    <script>
        // Auto show print dialog
        window.onload = function() {
            setTimeout(function() {
                if (confirm('🎓 Chứng chỉ đã sẵn sàng!\n\n📄 Bạn có muốn in hoặc lưu PDF ngay không?')) {
                    window.print();
                }
            }, 1000);
        }
        
        function savePDF() {
            // Sử dụng print dialog của browser để lưu PDF
            alert('💡 Hướng dẫn lưu PDF:\n\n1. Click "In ngay" hoặc Ctrl+P\n2. Chọn "Save as PDF" trong máy in\n3. Chọn vị trí lưu file\n4. Click "Save"');
            window.print();
        }
        
        // Copy certificate code when clicked
        document.querySelector('.certificate-code').addEventListener('click', function() {
            navigator.clipboard.writeText(this.textContent).then(function() {
                alert('✅ Đã copy mã chứng chỉ: ' + document.querySelector('.certificate-code').textContent);
            });
        });
    </script>
</body>
</html>