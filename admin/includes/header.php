<?php
// Include configuration file
require_once __DIR__ . '/../../config.php';

if (!isset($page_title)) {
    $page_title = 'Admin Panel';
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
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    
    <!-- SB Admin 2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- Custom styles -->
    <link href="../assets/css/admin.css?v=1.1" rel="stylesheet">
    <link href="../assets/css/styles.css?v=1.1" rel="stylesheet">
    <link href="../assets/css/course-dropdown-fix.css?v=1.0" rel="stylesheet">
    
    <style>
        .topbar { z-index: 1040 !important; }
        .dropdown-menu { z-index: 1050 !important; }
        .glass { position: relative; } /* Ensure z-index works on glass elements */
    </style>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Core plugin JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom scripts for all pages-->
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <?php include 'topbar.php'; ?>
