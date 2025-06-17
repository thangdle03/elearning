<!-- Modern Footer -->
<footer class="footer bg-dark text-light">
    <!-- Main Footer Content -->
    <div class="footer-main">
        <div class="container">
            <div class="row g-4">
                <!-- Brand & Description -->
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand">
                        <div class="d-flex align-items-center mb-3">
                            <div class="footer-logo me-3">
                                <div class="logo-circle">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                            </div>
                            <div class="brand-text">
                                <h5 class="brand-name text-white mb-0"><?php echo SITE_NAME; ?></h5>
                                <small class="brand-tagline text-muted">E-Learning Platform</small>
                            </div>
                        </div>
                        <p class="footer-description text-muted mb-4">
                            Nền tảng học trực tuyến hàng đầu với các khóa học lập trình chất lượng cao.
                            Học từ những chuyên gia hàng đầu và phát triển kỹ năng công nghệ của bạn.
                        </p>

                        <!-- Social Media Links -->
                        <div class="social-links">
                            <h6 class="text-white mb-3">
                                <i class="fas fa-share-alt me-2"></i>Kết nối với chúng tôi
                            </h6>
                            <div class="d-flex gap-3">
                                <a href="#" class="social-link facebook" title="Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="#" class="social-link twitter" title="Twitter">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" class="social-link youtube" title="YouTube">
                                    <i class="fab fa-youtube"></i>
                                </a>
                                <a href="#" class="social-link linkedin" title="LinkedIn">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <a href="#" class="social-link instagram" title="Instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="#" class="social-link github" title="GitHub">
                                    <i class="fab fa-github"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6">
                    <div class="footer-section">
                        <h6 class="footer-title">
                            <i class="fas fa-link me-2"></i>Liên kết nhanh
                        </h6>
                        <ul class="footer-links">
                            <li><a href="<?php echo SITE_URL; ?>">
                                    <i class="fas fa-home me-2"></i>Trang chủ
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/courses.php">
                                    <i class="fas fa-book-open me-2"></i>Khóa học
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/about.php">
                                    <i class="fas fa-info-circle me-2"></i>Giới thiệu
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/contact.php">
                                    <i class="fas fa-envelope me-2"></i>Liên hệ
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/blog/">
                                    <i class="fas fa-blog me-2"></i>Blog
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/faq.php">
                                    <i class="fas fa-question-circle me-2"></i>FAQ
                                </a></li>
                        </ul>
                    </div>
                </div>

                <!-- Popular Courses -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h6 class="footer-title">
                            <i class="fas fa-fire me-2"></i>Khóa học phổ biến
                        </h6>
                        <ul class="footer-links">
                            <li><a href="<?php echo SITE_URL; ?>/courses.php?category=frontend">
                                    <i class="fab fa-html5 me-2 text-warning"></i>Frontend Development
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/courses.php?category=backend">
                                    <i class="fas fa-server me-2 text-success"></i>Backend Development
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/courses.php?category=mobile">
                                    <i class="fas fa-mobile-alt me-2 text-info"></i>Mobile Development
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/courses.php?category=ai">
                                    <i class="fas fa-robot me-2 text-primary"></i>AI & Machine Learning
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/courses.php?category=data">
                                    <i class="fas fa-chart-bar me-2 text-danger"></i>Data Science
                                </a></li>
                            <li><a href="<?php echo SITE_URL; ?>/courses.php?category=cloud">
                                    <i class="fas fa-cloud me-2 text-secondary"></i>Cloud Computing
                                </a></li>
                        </ul>
                    </div>
                </div>

                <!-- Contact Info & Newsletter -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h6 class="footer-title">
                            <i class="fas fa-envelope me-2"></i>Liên hệ & Đăng ký
                        </h6>

                        <!-- Contact Information -->
                        <div class="contact-info mb-4">
                            <div class="contact-item">
                                <i class="fas fa-map-marker-alt me-3 text-primary"></i>
                                <div>
                                    <strong>Địa chỉ:</strong><br>
                                    <span class="text-muted">Văn Quán, Hà Đông, TP.Hà Nội</span>
                                </div>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-phone me-3 text-success"></i>
                                <div>
                                    <strong>Điện thoại:</strong><br>
                                    <a href="tel:+84123456789" class="text-muted">+84 123 456 789</a>
                                </div>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-envelope me-3 text-warning"></i>
                                <div>
                                    <strong>Email:</strong><br>
                                    <a href="mailto:info@elearning.com" class="text-muted">info@elearning.com</a>
                                </div>
                            </div>
                        </div>

                        <!-- Newsletter Signup -->
                        <div class="newsletter-signup">
                            <h6 class="text-white mb-3">
                                <i class="fas fa-bell me-2"></i>Đăng ký nhận tin
                            </h6>
                            <p class="text-muted small mb-3">
                                Nhận thông báo về khóa học mới và ưu đãi đặc biệt
                            </p>
                            <form class="newsletter-form" action="<?php echo SITE_URL; ?>/newsletter-subscribe.php" method="POST">
                                <div class="input-group">
                                    <input type="email" name="email" class="form-control"
                                        placeholder="Nhập email của bạn..." required>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Stats -->
    <div class="footer-stats">
        <div class="container">
            <div class="row text-center">
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number" data-count="10000">0</div>
                        <div class="stat-label">Học viên</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-number" data-count="500">0</div>
                        <div class="stat-label">Khóa học</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-number" data-count="100">0</div>
                        <div class="stat-label">Giảng viên</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <div class="stat-number" data-count="5000">0</div>
                        <div class="stat-label">Chứng chỉ</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="copyright">
                        <p class="mb-0">
                            &copy; <?php echo date('Y'); ?> <strong><?php echo SITE_NAME; ?></strong>.
                            Tất cả quyền được bảo lưu.
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="footer-legal text-md-end">
                        <a href="<?php echo SITE_URL; ?>/privacy-policy.php">Chính sách bảo mật</a>
                        <span class="separator">|</span>
                        <a href="<?php echo SITE_URL; ?>/terms-of-service.php">Điều khoản sử dụng</a>
                        <span class="separator">|</span>
                        <a href="<?php echo SITE_URL; ?>/sitemap.php">Sitemap</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" title="Về đầu trang">
        <i class="fas fa-chevron-up"></i>
    </button>
</footer>

<!-- Enhanced Footer Styles -->
<style>
    /* Footer Base Styles */
    .footer {
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
        color: #e2e8f0;
        margin-top: auto;
        position: relative;
        overflow: hidden;
    }

    .footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent 0%, #4299e1 50%, transparent 100%);
    }

    .footer-main {
        padding: 4rem 0 2rem;
        position: relative;
    }

    /* Footer Logo */
    .footer-logo .logo-circle {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
    }

    .footer .brand-name {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 1.25rem;
    }

    .footer .brand-tagline {
        font-size: 0.8rem;
        color: #a0aec0;
    }

    .footer-description {
        line-height: 1.8;
        font-size: 0.95rem;
    }

    /* Footer Sections */
    .footer-section {
        height: 100%;
    }

    .footer-title {
        color: #f7fafc;
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #4299e1;
        display: inline-block;
    }

    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-links li {
        margin-bottom: 0.8rem;
    }

    .footer-links a {
        color: #cbd5e0;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        padding: 0.3rem 0;
        border-radius: 6px;
    }

    .footer-links a:hover {
        color: #4299e1;
        transform: translateX(8px);
        background-color: rgba(66, 153, 225, 0.1);
        padding-left: 0.5rem;
    }

    .footer-links a i {
        width: 20px;
        text-align: center;
        opacity: 0.8;
    }

    .text-muted {
        color: #a0aec0 !important;
    }

    /* Social Links */
    .social-links {
        margin-top: 2rem;
    }

    .social-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        text-decoration: none;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .social-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .social-link:hover::before {
        left: 100%;
    }

    .social-link.facebook {
        background: linear-gradient(135deg, #1877f2 0%, #0d5aa7 100%);
        color: white;
    }

    .social-link.twitter {
        background: linear-gradient(135deg, #1da1f2 0%, #0d8bd9 100%);
        color: white;
    }

    .social-link.youtube {
        background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
        color: white;
    }

    .social-link.linkedin {
        background: linear-gradient(135deg, #0077b5 0%, #005885 100%);
        color: white;
    }

    .social-link.instagram {
        background: linear-gradient(135deg, #e4405f 0%, #833ab4 50%, #fccc63 100%);
        color: white;
    }

    .social-link.github {
        background: linear-gradient(135deg, #333 0%, #24292e 100%);
        color: white;
    }

    .social-link:hover {
        transform: translateY(-3px) scale(1.1);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }

    /* Contact Information */
    .contact-info {
        font-size: 0.9rem;
    }

    .contact-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 1rem;
        padding: 0.8rem;
        background-color: rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .contact-item:hover {
        background-color: rgba(66, 153, 225, 0.1);
        transform: translateY(-2px);
    }

    .contact-item i {
        margin-top: 0.2rem;
        font-size: 1.1rem;
    }

    .contact-item a {
        color: #cbd5e0;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .contact-item a:hover {
        color: #4299e1;
    }

    /* Newsletter */
    .newsletter-form .input-group {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .newsletter-form .form-control {
        border: none;
        background-color: rgba(255, 255, 255, 0.1);
        color: #f7fafc;
        padding: 1rem;
        font-size: 0.9rem;
        border-radius: 0;
    }

    .newsletter-form .form-control::placeholder {
        color: #a0aec0;
    }

    .newsletter-form .form-control:focus {
        background-color: rgba(255, 255, 255, 0.15);
        color: #f7fafc;
        box-shadow: none;
        border-color: #4299e1;
    }

    .newsletter-form .btn {
        border-radius: 0;
        padding: 1rem 1.5rem;
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        border: none;
        transition: all 0.3s ease;
    }

    .newsletter-form .btn:hover {
        background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
        transform: scale(1.05);
    }

    /* Footer Stats */
    .footer-stats {
        background-color: rgba(0, 0, 0, 0.3);
        padding: 3rem 0;
        border-top: 1px solid rgba(66, 153, 225, 0.2);
        border-bottom: 1px solid rgba(66, 153, 225, 0.2);
    }

    .stat-item {
        padding: 1.5rem;
        transition: all 0.3s ease;
        border-radius: 12px;
    }

    .stat-item:hover {
        background-color: rgba(66, 153, 225, 0.1);
        transform: translateY(-5px);
    }

    .stat-icon {
        font-size: 2.5rem;
        color: #4299e1;
        margin-bottom: 1rem;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        color: #f7fafc;
        margin-bottom: 0.5rem;
        font-family: 'Poppins', sans-serif;
    }

    .stat-label {
        color: #a0aec0;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Footer Bottom */
    .footer-bottom {
        background-color: rgba(0, 0, 0, 0.4);
        padding: 2rem 0;
        border-top: 1px solid rgba(66, 153, 225, 0.2);
    }

    .copyright {
        font-size: 0.9rem;
        color: #a0aec0;
    }

    .footer-legal {
        font-size: 0.9rem;
    }

    .footer-legal a {
        color: #cbd5e0;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
        padding: 0.3rem 0.6rem;
        border-radius: 6px;
    }

    .footer-legal a:hover {
        color: #4299e1;
        background-color: rgba(66, 153, 225, 0.1);
    }

    .footer-legal .separator {
        color: #4a5568;
        margin: 0 0.5rem;
    }

    /* Back to Top Button */
    .back-to-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(20px);
        box-shadow: 0 4px 15px rgba(66, 153, 225, 0.4);
    }

    .back-to-top.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .back-to-top:hover {
        transform: translateY(-5px) scale(1.1);
        box-shadow: 0 8px 25px rgba(66, 153, 225, 0.6);
    }

    .back-to-top:active {
        transform: translateY(-2px) scale(1.05);
    }

    /* Responsive Design */
    @media (max-width: 991.98px) {
        .footer-main {
            padding: 3rem 0 1.5rem;
        }

        .footer-stats {
            padding: 2rem 0;
        }

        .stat-number {
            font-size: 2rem;
        }

        .stat-icon {
            font-size: 2rem;
        }
    }

    @media (max-width: 767.98px) {
        .footer-main {
            padding: 2rem 0 1rem;
        }

        .footer-section {
            margin-bottom: 2rem;
        }

        .footer-title {
            font-size: 1rem;
        }

        .social-link {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        .contact-item {
            padding: 0.6rem;
        }

        .stat-item {
            padding: 1rem;
        }

        .stat-number {
            font-size: 1.8rem;
        }

        .stat-icon {
            font-size: 1.8rem;
        }

        .footer-legal {
            text-align: center !important;
            margin-top: 1rem;
        }

        .back-to-top {
            bottom: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
        }
    }

    /* Animation Classes */
    .fade-in {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.6s ease;
    }

    .fade-in.visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* Dark Theme Support */
    @media (prefers-color-scheme: dark) {
        .footer {
            background: linear-gradient(135deg, #0a0e1a 0%, #1a1f2e 100%);
        }
    }

    /* Print Styles */
    @media print {
        .footer {
            background: none !important;
            color: black !important;
        }

        .back-to-top {
            display: none !important;
        }

        .social-links {
            display: none !important;
        }
    }
</style>

<!-- Footer JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Counter Animation
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number[data-count]');
            const observerOptions = {
                threshold: 0.5,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const counter = entry.target;
                        const target = parseInt(counter.getAttribute('data-count'));
                        const increment = target / 100;
                        let current = 0;

                        const timer = setInterval(() => {
                            current += increment;
                            if (current >= target) {
                                counter.textContent = target.toLocaleString();
                                clearInterval(timer);
                            } else {
                                counter.textContent = Math.floor(current).toLocaleString();
                            }
                        }, 20);

                        observer.unobserve(counter);
                    }
                });
            }, observerOptions);

            counters.forEach(counter => observer.observe(counter));
        }

        // Back to Top Button
        const backToTopBtn = document.getElementById('backToTop');

        function toggleBackToTop() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        }

        window.addEventListener('scroll', toggleBackToTop);

        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Newsletter Form
        const newsletterForm = document.querySelector('.newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const emailInput = this.querySelector('input[name="email"]');
                const submitBtn = this.querySelector('button[type="submit"]');

                // Simple validation
                if (!emailInput.value || !emailInput.value.includes('@')) {
                    emailInput.classList.add('is-invalid');
                    return;
                }

                // Simulate API call
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                submitBtn.disabled = true;

                setTimeout(() => {
                    submitBtn.innerHTML = '<i class="fas fa-check"></i>';
                    emailInput.value = '';

                    setTimeout(() => {
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                        submitBtn.disabled = false;
                    }, 2000);
                }, 1000);
            });
        }

        // Fade in animation for footer sections
        function handleScrollAnimations() {
            const elements = document.querySelectorAll('.footer-section, .stat-item');
            const windowHeight = window.innerHeight;

            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;

                if (elementTop < windowHeight - 100) {
                    element.classList.add('fade-in', 'visible');
                }
            });
        }

        window.addEventListener('scroll', handleScrollAnimations);

        // Social media click tracking
        document.querySelectorAll('.social-link').forEach(link => {
            link.addEventListener('click', function(e) {
                const platform = this.classList[1]; // Get the platform class
                console.log(`Social media click: ${platform}`);

                // Add click animation
                this.style.transform = 'translateY(-3px) scale(1.2)';
                setTimeout(() => {
                    this.style.transform = 'translateY(-3px) scale(1.1)';
                }, 150);
            });
        });

        // Footer link hover effects
        document.querySelectorAll('.footer-links a').forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.paddingLeft = '1rem';
            });

            link.addEventListener('mouseleave', function() {
                this.style.paddingLeft = '0';
            });
        });

        // Initialize animations
        animateCounters();
        handleScrollAnimations();

        console.log('✨ Enhanced footer loaded successfully!');
    });
</script>

</body>

</html>