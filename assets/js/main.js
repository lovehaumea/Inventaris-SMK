
document.addEventListener('DOMContentLoaded', function () {
    // Tutup notifikasi otomatis setelah 3 detik
    window.setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (alertElement) {
            if (
                alertElement.isConnected &&
                typeof bootstrap !== 'undefined' &&
                bootstrap.Alert
            ) {
                bootstrap.Alert.getOrCreateInstance(alertElement).close();
            }
        });
    }, 3000);

    // Elemen sidebar
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !toggle || !overlay) {
        console.error('Sidebar tidak ditemukan:', {
            sidebar: !!sidebar,
            toggle: !!toggle,
            overlay: !!overlay
        });
        return;
    }

    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.classList.add('sidebar-open');

        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Tutup menu');

        const icon = toggle.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-xmark');
        }

        overlay.setAttribute('aria-hidden', 'false');
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.classList.remove('sidebar-open');

        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Buka menu');

        const icon = toggle.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-xmark');
            icon.classList.add('fa-bars');
        }

        overlay.setAttribute('aria-hidden', 'true');
    }

    toggle.addEventListener('click', function (event) {
        event.preventDefault();

        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a.nav-link').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });
});
