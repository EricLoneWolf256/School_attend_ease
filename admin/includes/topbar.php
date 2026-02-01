<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light topbar mb-4 static-top shadow-none bg-transparent">
    <!-- Sidebar Toggle (Topbar) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3 text-white">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Topbar Search -->
    <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
        <div class="input-group glass rounded-pill border-0 px-3 py-1 shadow-sm" style="background: rgba(255, 255, 255, 0.05);">
            <input type="text" class="form-control bg-transparent border-0 text-white small" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2" style="box-shadow: none;">
            <div class="input-group-append">
                <button class="btn btn-transparent text-white-50" type="button">
                    <i class="fas fa-search fa-sm"></i>
                </button>
            </div>
        </div>
    </form>

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ml-auto">
        <!-- Nav Item - User Information -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="mr-3 d-none d-lg-inline text-dark small fw-bold">
                    <?php 
                    if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
                        echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
                    } else {
                        echo 'Administrator';
                    }
                    ?>
                </span>
                <?php 
                $profile_pic = $_SESSION['profile_pic'] ?? '';
                if (empty($profile_pic)) {
                    $name = urlencode(($_SESSION['first_name'] ?? 'A') . ' ' . ($_SESSION['last_name'] ?? ''));
                    $topbar_avatar = "https://ui-avatars.com/api/?name=$name&background=990000&color=FFD700&bold=true";
                } elseif (strpos($profile_pic, 'http') === 0) {
                    $topbar_avatar = $profile_pic;
                } else {
                    // It's a local filename, prepend the correct path
                    $clean_path = ltrim($profile_pic, '/');
                    // If it doesn't already have 'uploads/', prepend it
                    if (strpos($clean_path, 'uploads/') === false) {
                        $topbar_avatar = '../uploads/profile_pictures/' . $clean_path;
                    } else {
                        $topbar_avatar = '../' . $clean_path;
                    }
                }
                ?>
                <img class="img-profile rounded-circle border border-primary shadow-sm" 
                     src="<?php echo $topbar_avatar; ?>" 
                     style="width: 40px; height: 40px; object-fit: cover; border-color: var(--secondary-color) !important; border-width: 2px !important;">
            </a>
            <!-- Dropdown - User Information -->
            <div class="dropdown-menu dropdown-menu-right glass border-secondary shadow-lg animated--grow-in mt-3" aria-labelledby="userDropdown">
                <a class="dropdown-item text-dark py-2" href="../profile.php">
                    <i class="fas fa-user fa-sm fa-fw mr-2 text-muted"></i>
                    Profile
                </a>
                <a class="dropdown-item text-dark py-2" href="settings.php">
                    <i class="fas fa-cogs fa-sm fa-fw mr-3 text-muted"></i>
                    Settings
                </a>
                <div class="dropdown-divider border-secondary opacity-25"></div>
                <a class="dropdown-item text-dark py-2" href="#" data-toggle="modal" data-target="#logoutModal" data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-3 text-muted"></i>
                    Logout
                </a>
            </div>
        </li>
    </ul>
</nav>
<!-- End of Topbar -->
