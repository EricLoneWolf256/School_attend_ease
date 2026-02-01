<!-- Sidebar -->
<ul class="navbar-nav glass sidebar sidebar-dark accordion" id="accordionSidebar" style="background: rgba(0, 0, 0, 0.4) !important; backdrop-filter: blur(20px); border-right: 1px solid var(--glass-border);">
    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center py-5" href="dashboard.php" style="height: auto !important; min-height: 100px;">
        <div class="sidebar-brand-icon d-flex align-items-center justify-content-center shadow-lg" 
             style="background: rgba(153, 0, 0, 0.4); width: 50px; height: 50px; border-radius: 12px; border: 1px solid rgba(255, 215, 0, 0.3);">
            <i class="fas fa-calendar-check" style="color: var(--secondary-color); font-size: 1.4rem;"></i>
        </div>
        <div class="sidebar-brand-text ms-3" style="color: #fff; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; font-size: 0.9rem;">
            AttendEase
        </div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0 border-secondary opacity-25">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider border-secondary opacity-25">

    <!-- Heading -->
    <div class="sidebar-heading" style="color: var(--secondary-color); opacity: 0.8; font-size: 0.7rem; letter-spacing: 1px;">
        Management
    </div>

    <!-- Nav Item - Courses -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="courses.php">
            <i class="fas fa-fw fa-book"></i>
            <span>Courses</span>
        </a>
    </li>

    <!-- Nav Item - Timetable -->
    <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'timetable.php' || basename($_SERVER['PHP_SELF']) == 'add_lecture.php' || basename($_SERVER['PHP_SELF']) == 'edit_lecture.php') ? 'active' : ''; ?>">
        <a class="nav-link" href="timetable.php">
            <i class="fas fa-fw fa-calendar-alt"></i>
            <span>Timetable</span>
        </a>
    </li>

    <!-- Nav Item - Lecturers -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'lecturers.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="lecturers.php">
            <i class="fas fa-fw fa-chalkboard-teacher"></i>
            <span>Lecturers</span>
        </a>
    </li>

    <!-- Nav Item - Students -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="students.php">
            <i class="fas fa-fw fa-user-graduate"></i>
            <span>Students</span>
        </a>
    </li>

    <!-- Nav Item - Reports -->
    <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
        <a class="nav-link" href="reports.php">
            <i class="fas fa-fw fa-chart-bar"></i>
            <span>Reports</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block border-secondary opacity-25">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0 glass transition-all" id="sidebarToggle" style="background: rgba(255, 255, 255, 0.1); color: #fff;"></button>
    </div>
</ul>
<!-- End of Sidebar -->

<style>
/* Custom Sidebar Styling */
.sidebar {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    width: 260px !important; /* Increased width for breathing room */
}

.sidebar-brand-text {
    display: inline-block !important;
    color: #fff !important;
    font-weight: 800 !important;
    letter-spacing: 1px;
    text-transform: uppercase;
    overflow: visible !important; /* NO TRUNCATION */
    text-overflow: unset !important;
    white-space: nowrap !important;
}

.sidebar .nav-item .nav-link span {
    display: inline-block !important;
    overflow: visible !important;
    text-overflow: unset !important;
    white-space: nowrap !important;
}

/* Force override SB Admin 2 active states */
.sidebar .nav-item.active .nav-link {
    color: var(--secondary-color) !important;
    background: rgba(153, 0, 0, 0.4) !important;
    font-weight: 700 !important;
    border-left: 5px solid var(--secondary-color) !important; /* Gold border! */
}

.sidebar .nav-item.active .nav-link i {
    color: var(--secondary-color) !important;
}

/* Remove default SB Admin 2 blue highlights */
.sidebar .nav-item.active .nav-link::before {
    display: none !important;
}

.sidebar .nav-item .nav-link {
    color: rgba(255, 255, 255, 0.8) !important;
    transition: all 0.2s ease;
    border-radius: 0 8px 8px 0;
    margin: 0.2rem 0;
    padding: 0.75rem 1.5rem !important;
    display: flex;
    align-items: center;
    border-left: 5px solid transparent; /* Prepare for active border */
}

.sidebar .nav-item .nav-link i {
    margin-right: 1rem !important;
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    color: inherit !important;
}

.sidebar .nav-item .nav-link:hover {
    color: var(--secondary-color) !important;
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
    border-left-color: rgba(255, 215, 0, 0.3);
}

/* Improve the brand area visibility */
.sidebar-brand {
    background: rgba(0, 0, 0, 0.2);
    margin-bottom: 2rem !important;
    width: 100% !important;
    height: auto !important;
    min-height: 120px !important;
    padding: 2rem 1rem !important;
}

/* Headings */
.sidebar .sidebar-heading {
    text-transform: uppercase;
    font-weight: 800;
    padding: 1.5rem 1.5rem 0.5rem;
    color: var(--secondary-color) !important;
    opacity: 0.6;
    font-size: 0.7rem;
    letter-spacing: 1px;
}

/* Toggler */
#sidebarToggle {
    width: 2.5rem;
    height: 2.5rem;
    text-align: center;
    margin-top: 1rem;
}

#sidebarToggle::after {
    font-weight: 900;
    content: '\f104';
    font-family: 'Font Awesome 5 Free';
    margin-right: 0.1rem;
}
</style>
