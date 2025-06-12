# E-Learning Platform 📚

Hệ thống học trực tuyến xây dựng bằng PHP và MySQL, cho phép xem video khóa học từ YouTube với theo dõi tiến độ học tập.


## 📋 Tính năng

### Người dùng
- ✅ Đăng ký/Đăng nhập tài khoản
- ✅ Xem danh sách khóa học theo danh mục
- ✅ Tìm kiếm và lọc khóa học
- ✅ Đăng ký khóa học miễn phí
- ✅ Xem video bài học từ YouTube
- ✅ Theo dõi tiến độ học tập
- ✅ Quản lý hồ sơ cá nhân

### Quản trị viên
- ✅ Dashboard thống kê
- ✅ Quản lý khóa học (CRUD)
- ✅ Thêm khóa học từ danh sách YouTube URLs
- ✅ Quản lý danh mục
- ✅ Quản lý người dùng
- ✅ Quản lý bài học

## 🚀 Cài đặt

### Yêu cầu hệ thống
- XAMPP/WAMP/LAMP với:
  - PHP >= 7.4
  - MySQL >= 5.7
  - Apache với mod_rewrite

### Các bước cài đặt

1. **Clone repository**
```bash
git clone https://github.com/yourusername/elearning-platform.git
cd elearning-platform
```

2. **Copy vào thư mục web server**
```bash
# Windows (XAMPP)
Copy toàn bộ code vào C:\xampp\htdocs\elearning

# Linux/Mac
sudo cp -r * /var/www/html/elearning
```

3. **Tạo database**
- Mở phpMyAdmin: http://localhost/phpmyadmin
- Tạo database mới tên: `elearning_simple`
- Import file `database/schema.sql`

4. **Cấu hình database**
- Mở file `includes/config.php`
- Cập nhật thông tin database nếu cần:
```php
$host = 'localhost';
$dbname = 'elearning_simple';
$username = 'root';
$password = '';
```

5. **Truy cập website**
- http://localhost/elearning

## 🔑 Tài khoản demo

### Admin
- Username: `admin`
- Password: `admin123`

### Học viên
- Username: `student1`
- Password: `student123`

## 📁 Cấu trúc project

```
elearning/
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── uploads/
│       └── thumbnails/
├── includes/
│   ├── config.php
│   ├── functions.php
│   ├── auth.php
│   ├── header.php
│   └── footer.php
├── admin/
│   ├── includes/
│   ├── index.php
│   ├── courses.php
│   ├── add-course.php
│   └── ...
├── api/
│   ├── enroll.php
│   ├── progress.php
│   └── search.php
├── database/
│   └── schema.sql
├── index.php
├── login.php
├── register.php
├── courses.php
├── course-detail.php
├── learn.php
├── my-courses.php
├── profile.php
├── search.php
├── .htaccess
└── README.md
```

## 💻 Công nghệ sử dụng

- **Backend**: PHP 7.4+ (Vanilla PHP)
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **CSS Framework**: Bootstrap 5.3
- **JavaScript Library**: jQuery 3.6
- **Icons**: Bootstrap Icons
- **Video Player**: YouTube Embed API




## 🔧 Tính năng nâng cao (có thể phát triển thêm)

- [ ] Thanh toán online (VNPay, Stripe)
- [ ] Chứng chỉ hoàn thành khóa học
- [ ] Forum thảo luận
- [ ] Live chat support
- [ ] Quiz và bài tập
- [ ] Rating và review khóa học
- [ ] Multi-language support
- [ ] Mobile app

## 🤝 Đóng góp

Mọi đóng góp đều được chào đón! Vui lòng:

1. Fork project
2. Tạo feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Mở Pull Request

## 📝 License

Distributed under the MIT License. See `LICENSE` for more information.

## 👥 Tác giả

- **Your Name** - [GitHub Profile](https://github.com/yourusername)

## 🙏 Cảm ơn

- Bootstrap team cho UI framework tuyệt vời
- YouTube API cho video hosting
- Tất cả contributors đã đóng góp cho project

---

