<?php
// Set default page title if not set
if (!isset($page_title)) {
    $page_title = 'Attendance System';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, minimum-scale=1.0, viewport-fit=cover" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title><?php echo htmlspecialchars($page_title); ?> - Lecture Attendance System</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/ghost/assets/img/favicon.ico" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    
    <!-- Custom styles -->
    <link href="/ghost/assets/css/styles.css" rel="stylesheet" />
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Sidebar Toggle Script -->
    <script src="/ghost/assets/js/sidebar.js"></script>
    
    <!-- Search Script -->
    <script src="/ghost/assets/js/search.js"></script>
    
    <!-- Initialize Logout Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const logoutLink = document.getElementById('logoutLink');
            const logoutForm = document.getElementById('logoutForm');
            
            if (logoutLink && logoutForm) {
                logoutLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    logoutForm.submit();
                });
            }
        });
    </script>
</head>
<body class="sb-nav-fixed">
    <style>
        /* Ensure dropdowns appear above glass elements and other content */
        .dropdown-menu {
            z-index: 1060 !important;
            background: rgba(0, 0, 0, 0.9) !important;
            backdrop-filter: blur(20px) !important;
        }
        .dropdown-item {
            color: rgba(255, 255, 255, 0.9) !important;
            transition: all 0.2s ease;
        }
        .dropdown-item:hover {
            background: rgba(153, 0, 0, 0.2) !important;
            color: var(--secondary-color) !important;
        }
        .dropdown-item.text-danger {
            color: #ff4d4d !important;
        }
        .dropdown-item.text-danger:hover {
            background: rgba(220, 53, 69, 0.1) !important;
        }
    </style>
    <!-- Toast container for notifications -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100"></div>
    
    <nav class="sb-topnav navbar navbar-expand-lg navbar-dark fixed-top">
        <!-- Navbar Brand -->
        <a class="navbar-brand ps-3" href="/ghost/admin/dashboard.php">
            <i class="fas fa-user-clock me-2" style="color: var(--primary-color);"></i>
            <span class="d-none d-md-inline">ATTEND <span style="color: var(--primary-color);">EASE</span></span>
        </a>
        
        <!-- Sidebar Toggle Button -->
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-3 me-lg-0 text-white" id="sidebarToggle" href="#">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Navbar Search -->
        <form id="searchForm" class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0 position-relative">
            <div class="input-group">
                <input 
                    id="searchInput"
                    class="form-control glass" 
                    type="search" 
                    placeholder="Search..." 
                    aria-label="Search" 
                    autocomplete="off"
                    aria-describedby="btnNavbarSearch" 
                />
                <button class="btn btn-primary" id="btnNavbarSearch" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
        
        <!-- Navbar -->
        <div class="dropdown ms-auto me-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php
                // Display profile picture if available, otherwise show UI-Avatar
                $profile_pic = $_SESSION['profile_pic'] ?? '';
                if (!empty($profile_pic) && strpos($profile_pic, 'http') === false) {
                    echo '<img src="/ghost/uploads/profile_pictures/' . htmlspecialchars($profile_pic) . '" class="rounded-circle me-2 border border-secondary" width="32" height="32" alt="Profile" style="object-fit: cover;">';
                } else {
                    $name = urlencode(($_SESSION['first_name'] ?? 'A') . ' ' . ($_SESSION['last_name'] ?? ''));
                    $avatar_url = "https://ui-avatars.com/api/?name=$name&background=990000&color=FFD700&bold=true";
                    echo '<img src="' . $avatar_url . '" class="rounded-circle me-2 border border-secondary" width="32" height="32" alt="Profile">';
                }
                ?>
                <span class="d-none d-md-inline fw-bold">
                    <?php echo isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) : 'Guest'; ?>
                </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow glass border-secondary" aria-labelledby="navbarDropdown">
                <li><a class="dropdown-item text-white" href="/ghost/profile.php"><i class="fas fa-user me-2 text-secondary"></i>Profile</a></li>
                <li><hr class="dropdown-divider m-0 border-secondary opacity-25"></li>
                <li><a class="dropdown-item text-danger" href="/ghost/logout.php" id="logoutLink"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
        
        <!-- Logout Form (hidden) -->
        <form id="logoutForm" action="/ghost/logout.php" method="post" style="display: none;">
            <?php echo generateCSRFTokenInput(); ?>
        </form>
    </nav>
    
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <div class="sb-sidenav-menu-heading">Core</div>
                        <a class="nav-link" href="/ghost/admin/dashboard.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                            Dashboard
                        </a>
                        
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <div class="sb-sidenav-menu-heading">Admin</div>
                        <a class="nav-link" href="/ghost/admin/lecturers.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                            Manage Lecturers
                        </a>
                        <a class="nav-link" href="/ghost/admin/courses.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-book"></i></div>
                            Manage Courses
                        </a>
                        <a class="nav-link" href="/ghost/admin/students.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-user-graduate"></i></div>
                            Manage Students
                        </a>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'lecturer'): ?>
                        <div class="sb-sidenav-menu-heading">Lecturer</div>
                        <a class="nav-link" href="/ghost/lecturer/lectures.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-calendar-alt"></i></div>
                            My Lectures
                        </a>
                        <a class="nav-link" href="/ghost/lecturer/attendance.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-clipboard-check"></i></div>
                            Take Attendance
                        </a>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                        <div class="sb-sidenav-menu-heading">Student</div>
                        <a class="nav-link" href="/ghost/student/attendance.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-user-check"></i></div>
                            My Attendance
                        </a>
                        <a class="nav-link" href="/ghost/student/timetable.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-calendar-week"></i></div>
                            My Timetable
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sb-sidenav-footer">
                    <div class="small">Logged in as:</div>
                    <?php echo isset($_SESSION['role']) ? ucfirst(htmlspecialchars($_SESSION['role'])) : 'Guest'; ?>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                            <?php 
                            echo htmlspecialchars($_SESSION['success']); 
                            unset($_SESSION['success']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                            <?php 
                            echo htmlspecialchars($_SESSION['error']); 
                            unset($_SESSION['error']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
