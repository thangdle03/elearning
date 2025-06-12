
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">
                        <i class="bi bi-mortarboard-fill me-2"></i>
                        <?php echo SITE_NAME; ?>
                    </h5>
                    <p class="text-light-emphasis">
                        Nền tảng học trực tuyến hàng đầu về lập trình và công nghệ. 
                        Học mọi lúc, mọi nơi với chất lượng cao nhất.
                    </p>
                    <div class="social-links">
                        <a href="#" class="text-light me-3"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-light me-3"><i class="bi bi-youtube"></i></a>
                        <a href="#" class="text-light me-3"><i class="bi bi-telegram"></i></a>
                        <a href="#" class="text-light"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <h6 class="mb-3">Liên kết nhanh</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>" class="text-light-emphasis text-decoration-none">
                                <i class="bi bi-house me-2"></i>Trang chủ
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/courses.php" class="text-light-emphasis text-decoration-none">
                                <i class="bi bi-book me-2"></i>Khóa học
                            </a>
                        </li>
                        <?php if (isLoggedIn()): ?>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/my-courses.php" class="text-light-emphasis text-decoration-none">
                                <i class="bi bi-person-check me-2"></i>Khóa học của tôi
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/search.php" class="text-light-emphasis text-decoration-none">
                                <i class="bi bi-search me-2"></i>Tìm kiếm
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="col-md-3">
                    <h6 class="mb-3">Liên hệ</h6>
                    <div class="contact-info">
                        <p class="mb-2 text-light-emphasis">
                            <i class="bi bi-envelope me-2"></i>
                            <a href="mailto:info@elearning.com" class="text-light-emphasis text-decoration-none">
                                info@elearning.com
                            </a>
                        </p>
                        <p class="mb-2 text-light-emphasis">
                            <i class="bi bi-phone me-2"></i>
                            <a href="tel:0123456789" class="text-light-emphasis text-decoration-none">
                                0123 456 789
                            </a>
                        </p>
                        <p class="mb-2 text-light-emphasis">
                            <i class="bi bi-geo-alt me-2"></i>
                            Hà Nội, Việt Nam
                        </p>
                        <p class="mb-0 text-light-emphasis">
                            <i class="bi bi-clock me-2"></i>
                            24/7 Hỗ trợ online
                        </p>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-light-emphasis">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. 
                        Tất cả quyền được bảo lưu.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-light-emphasis">
                        Được phát triển với ❤️ bởi <strong>PHP & Bootstrap</strong>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <!-- Custom Scripts for specific pages -->
    <?php if (isset($custom_js)): ?>
        <?php echo $custom_js; ?>
    <?php endif; ?>
</body>
</html>