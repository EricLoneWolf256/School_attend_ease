<?php
// Include configuration file
require_once __DIR__ . '/../config.php';

if (!isset($page_title)) {
    $page_title = 'Student Portal';
}

// Fetch user data for profile picture
$db_header = getDBConnection();
$user_id_header = $_SESSION['user_id'] ?? null;
$user_header = null;
if ($user_id_header) {
    try {
        $stmt_header = $db_header->prepare('SELECT * FROM users WHERE user_id = ?');
        $stmt_header->execute([$user_id_header]);
        $user_header = $stmt_header->fetch();
    } catch (PDOException $e) {
        // Handle error silently
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME . ' - ' . $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="../assets/img/favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Custom fonts for this template-->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    
    <!-- Custom styles for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Global AttendEase Styles -->
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-dark accordion glass m-2" id="accordionSidebar" style="background: rgba(0, 0, 0, 0.4) !important; border-radius: 15px; height: calc(100vh - 20px);">
            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboard.php">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-user-clock" style="color: var(--primary-color);"></i>
                </div>
                <div class="sidebar-brand-text mx-3">ATTEND <span style="color: var(--primary-color);">EASE</span></div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item active">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Attendance
            </div>

            <!-- Nav Item - Attendance -->
            <li class="nav-item">
                <a class="nav-link" href="attendance.php">
                    <i class="fas fa-fw fa-clipboard-list"></i>
                    <span>My Attendance</span>
                </a>
            </li>

            <!-- Nav Item - Mark Attendance -->
            <li class="nav-item">
                <a class="nav-link" href="mark_attendance.php">
                    <i class="fas fa-fw fa-clipboard-check"></i>
                    <span>Mark Attendance</span>
                </a>
            </li>

            <!-- Nav Item - Course Enrollment -->
            <li class="nav-item">
                <a class="nav-link" href="enroll_course.php">
                    <i class="fas fa-fw fa-book-open"></i>
                    <span>Course Enrollment</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Timetable
            </div>

            <!-- Nav Item - Timetable -->
            <li class="nav-item">
                <a class="nav-link" href="timetable.php">
                    <i class="fas fa-fw fa-calendar-alt"></i>
                    <span>My Timetable</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Nav Item - Profile -->
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-fw fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-dark topbar mb-4 static-top glass m-3" style="background: rgba(0, 0, 0, 0.4) !important; border-radius: 15px;">
                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3 text-white">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo htmlspecialchars($_SESSION['name'] ?? 'Student'); ?>
                                </span>
                                <img class="img-profile rounded-circle"
                                    src="../uploads/<?php echo htmlspecialchars($user_header['profile_picture'] ?? 'default.png'); ?>">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
