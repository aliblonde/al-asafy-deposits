<?php // includes/footer.php ?>
<footer class="text-center py-3 mt-auto"
    style="border-top:1px solid var(--border);font-size:0.75rem;color:var(--text-muted)">
    نظام إدارة الودائع الاستثمارية &copy;
    <?= date('Y') ?> — العسافي للاستثمارات &nbsp;|&nbsp; الإصدار 1.0
</footer>
</div><!-- /.main-wrapper -->
</div><!-- /.layout-wrapper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.querySelector('.layout-wrapper');
        const sidebarToggleBtn = document.getElementById('sidebarToggle');
        const themeToggleBtn = document.getElementById('themeToggle');

        // Sidebar Toggle Logic
        if (sidebarToggleBtn) {
            sidebarToggleBtn.addEventListener('click', () => {
                document.documentElement.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', document.documentElement.classList.contains('sidebar-collapsed'));
            });
        }

        // Theme Toggle Logic
        if (themeToggleBtn) {
            // Update icon on load
            updateThemeIcon(document.documentElement.getAttribute('data-theme'));

            themeToggleBtn.addEventListener('click', () => {
                let currentTheme = document.documentElement.getAttribute('data-theme');
                let newTheme = currentTheme === 'light' ? 'dark' : 'light';

                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);

                updateThemeIcon(newTheme);
            });
        }

        function updateThemeIcon(theme) {
            if (!themeToggleBtn) return;
            const icon = themeToggleBtn.querySelector('i');
            if (theme === 'light') {
                icon.className = 'bi bi-moon-fill fs-5 text-dark';
            } else {
                icon.className = 'bi bi-brightness-high fs-5 text-warning';
            }
        }
    });

    // Notifications API
    window.markRead = function(id) {
        let fd = new FormData();
        fd.append('action', 'mark_read');
        fd.append('id', id);
        fetch('notifications_api.php', { method: 'POST', body: fd });
    };

    window.markAllRead = function(e) {
        e.preventDefault();
        e.stopPropagation();
        let fd = new FormData();
        fd.append('action', 'mark_all_read');
        fetch('notifications_api.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
            if (data.success) {
                location.reload();
            }
        });
    };
</script>
<?php if (!empty($extraScript))
    echo $extraScript; ?>
</body>

</html>