
    <!-- Mobile Sidebar Toggle -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.toggle('show');
        }
        
        // Update current time
        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleString('vi-VN', {
                hour: '2-digit',
                minute: '2-digit',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        // Update time every minute
        setInterval(updateCurrentTime, 60000);
    </script>

    <!-- Your existing footer content -->
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Chart.js for dashboards -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Admin Custom JS -->
    <script>
        // Auto refresh time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('vi-VN');
            const element = document.getElementById('last-login-time');
            if (element) {
                element.textContent = timeString;
            }
        }
        
        // Update time every minute
        setInterval(updateTime, 60000);
        
        // Confirm delete actions
        function confirmDelete(message = 'Bạn có chắc chắn muốn xóa?') {
            return confirm(message);
        }
        
        // Show loading on form submit
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.classList.contains('btn-loading')) {
                        submitBtn.classList.add('btn-loading');
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
                        submitBtn.disabled = true;
                    }
                });
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.classList.remove('show');
                        setTimeout(() => {
                            if (alert.parentNode) {
                                alert.remove();
                            }
                        }, 150);
                    }
                }, 5000);
            });
        });
        
        // AJAX function for quick actions
        function quickAction(url, data, successCallback) {
            $.ajax({
                url: url,
                method: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (successCallback) successCallback(response);
                        showAlert('success', response.message || 'Thành công!');
                    } else {
                        showAlert('danger', response.message || 'Có lỗi xảy ra!');
                    }
                },
                error: function() {
                    showAlert('danger', 'Lỗi kết nối server!');
                }
            });
        }
        
        // Show alert function
        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Find or create alert container
            let container = document.querySelector('.alert-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'alert-container mb-3';
                const pageContent = document.querySelector('.admin-page-content');
                if (pageContent) {
                    pageContent.insertBefore(container, pageContent.firstChild);
                }
            }
            
            container.insertAdjacentHTML('afterbegin', alertHtml);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 150);
                }
            }, 5000);
        }
    </script>
    
    <!-- Custom Scripts for specific admin pages -->
    <?php if (isset($admin_custom_js)): ?>
        <?php echo $admin_custom_js; ?>
    <?php endif; ?>
    
    <!-- Page specific scripts -->
    <?php if (isset($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
</body>
</html>