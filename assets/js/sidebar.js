document.addEventListener('DOMContentLoaded', function () {
    // Toggle the side navigation
    const sidebarToggle = document.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        // Add event listener for sidebar toggle
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');

            // Save preference to localStorage
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });

        // Initialize the sidebar state from localStorage
        if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
            document.body.classList.add('sb-sidenav-toggled');
        }

        // Add click outside to close sidebar on mobile
        const sidebar = document.querySelector('.sb-sidenav');
        if (sidebar) {
            document.addEventListener('click', function (event) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnToggle = sidebarToggle.contains(event.target);
                const isMobile = window.innerWidth < 768; // Bootstrap's md breakpoint

                if (!isClickInsideSidebar && !isClickOnToggle && isMobile && !document.body.classList.contains('sb-sidenav-toggled')) {
                    document.body.classList.add('sb-sidenav-toggled');
                }
            });
        }
    }

    // Handle window resize
    const debounce = (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    // Handle responsive behavior
    const handleResize = debounce(function () {
        if (window.innerWidth >= 768) { // Bootstrap's md breakpoint
            document.body.classList.remove('sb-sidenav-toggled');
        }
    }, 250);

    window.addEventListener('resize', handleResize);
});
