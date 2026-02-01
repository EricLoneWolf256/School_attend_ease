<?php
require_once 'includes/session.php';
require_once 'includes/db_connection.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    header('Location: /ghost/index.php');
    exit();
}

// Get current user's data
$user_id = $_SESSION['user_id'];
$user = fetchOne("SELECT * FROM users WHERE user_id = ?", [$user_id], 'i');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: profile.php');
        exit();
    }
    
    // Get form data
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    $errors = [];
    
    if (empty($first_name) || empty($last_name)) {
        if (empty($first_name) && empty($last_name)) {
            $errors[] = 'First name and last name are required';
        } else if (empty($first_name)) {
            $errors[] = 'First name is required';
        } else if (empty($last_name)) {
            $errors[] = 'Last name is required';
        }
    }
    
    if (empty($email)) {
        $errors[] = 'Email address is required';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Check if email is already taken by another user
    $existing = fetchOne("SELECT user_id FROM users WHERE email = ? AND user_id != ?", [$email, $user_id], 'si');
    if ($existing) {
        $errors[] = 'This email is already registered to another account';
    }
    
    // Check if changing password
    $password_changed = false;
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        
        if (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters long';
        } else if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match';
        } else {
            $password_changed = true;
        }
    }
    
    // Handle profile picture upload
    $profile_pic = $user['profile_pic'] ?? null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_picture']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = __DIR__ . '/uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if it exists
                if ($profile_pic && file_exists($upload_dir . $profile_pic)) {
                    unlink($upload_dir . $profile_pic);
                }
                $profile_pic = $new_filename;
            } else {
                $errors[] = 'Failed to upload profile picture';
            }
        } else {
            $errors[] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
        }
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Update basic info
            $query = "UPDATE users SET 
                     first_name = ?, 
                     last_name = ?, 
                     email = ?,
                     phone = ?,
                     address = ?";
            
            $params = [
                $first_name,
                $last_name,
                $email,
                $phone,
                $address
            ];
            
            // Add profile picture if uploaded
            if ($profile_pic) {
                $query .= ", profile_pic = ?";
                $params[] = $profile_pic;
            }
            
            // Add password to query if changing
            if ($password_changed) {
                $query .= ", password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            $query .= " WHERE user_id = ?";
            $params[] = $user_id;
            
            // Execute the query
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Preparation failed: " . $conn->error);
            }
            
            $types = str_repeat('s', count($params) - 1) . 'i';
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Execution failed: " . $stmt->error);
            }
            
            // Update session data
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            if ($profile_pic) {
                $_SESSION['profile_pic'] = $profile_pic;
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = 'Profile updated successfully';
            header('Location: profile.php');
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Database Error: ' . $e->getMessage();
            error_log('Profile update error: ' . $e->getMessage());
        }
    }
    
    // If we got here, there were errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Set page title
$page_title = 'My Profile';

// Include header
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <h1 class="mt-4 text-white font-weight-bold">My Profile</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item">
            <?php
            $dashboard_link = '/ghost/admin/dashboard.php';
            if (isset($_SESSION['role'])) {
                if ($_SESSION['role'] === 'lecturer') $dashboard_link = '/ghost/lecturer/dashboard.php';
                elseif ($_SESSION['role'] === 'student') $dashboard_link = '/ghost/student/dashboard.php';
            }
            ?>
            <a href="<?php echo $dashboard_link; ?>" class="text-white-50">Dashboard</a>
        </li>
        <li class="breadcrumb-item active text-white">Profile Settings</li>
    </ol>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger glass border-danger mb-4 shadow-sm">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-3 fs-4 text-danger"></i>
                <div class="text-white">
                    <h6 class="alert-heading fw-bold mb-1">Update Failed</h6>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li class="small"><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success glass border-success mb-4">
            <span class="text-white font-weight-bold"><i class="fas fa-check-circle me-2"></i><?php 
                echo htmlspecialchars($_SESSION['success']); 
                unset($_SESSION['success']);
            ?></span>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4 glass border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom border-secondary border-opacity-25 py-3">
                    <h6 class="m-0 font-weight-bold text-white">
                        <i class="fas fa-user-edit me-2" style="color: var(--primary-color);"></i>
                        Personal Information
                    </h6>
                </div>
                <div class="card-body p-4">
                    <form action="profile.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate id="profileForm">
                        <?php echo generateCSRFTokenInput(); ?>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="first_name" class="form-label text-white fw-bold small text-uppercase">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your first name.</div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="last_name" class="form-label text-white fw-bold small text-uppercase">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter your last name.</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="form-label text-white fw-bold small text-uppercase">Email address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="phone" class="form-label text-white fw-bold small text-uppercase">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="(123) 456-7890">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="address" class="form-label text-white fw-bold small text-uppercase">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter your residential address"><?php 
                                echo htmlspecialchars($user['address'] ?? ''); 
                            ?></textarea>
                        </div>
                        
                        <div class="py-4 my-2 border-top border-secondary border-opacity-10">
                            <h5 class="mb-3 text-white font-weight-bold">Security Settings</h5>
                            <p class="text-white-50 small mb-4">Leave the password fields blank if you don't wish to change it.</p>
                            
                            <div class="mb-4">
                                <label for="current_password" class="form-label text-white fw-bold small text-uppercase">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" 
                                       autocomplete="current-password" placeholder="Enter current password to verify">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="new_password" class="form-label text-white fw-bold small text-uppercase">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           autocomplete="new-password" placeholder="Min 8 characters">
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="confirm_password" class="form-label text-white fw-bold small text-uppercase">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           autocomplete="new-password" placeholder="Repeat new password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <button type="submit" class="btn btn-primary px-5 py-2 shadow-sm">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-4 glass border-0 shadow-sm overflow-visible">
                <div class="card-header bg-transparent border-bottom border-secondary border-opacity-25 py-3">
                    <h6 class="m-0 font-weight-bold text-white">
                        <i class="fas fa-image me-2" style="color: var(--secondary-color);"></i>
                        Profile Picture
                    </h6>
                </div>
                <div class="card-body text-center p-4">
                    <div class="text-center">
                        <?php 
                        $profile_pic_display = !empty($user['profile_pic']) ? 
                            'uploads/profile_pictures/' . htmlspecialchars($user['profile_pic']) : 
                            'https://ui-avatars.com/api/?name=' . urlencode(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) . '&background=990000&color=FFD700&size=200';
                        ?>
                        <div class="position-relative d-inline-block mb-4">
                            <div class="profile-pic-wrapper p-2 rounded-circle glass shadow-lg" style="background: rgba(153, 0, 0, 0.05);">
                                <img src="<?php echo $profile_pic_display; ?>" 
                                     class="rounded-circle" 
                                     alt="Profile Picture" 
                                     id="profileImagePreview"
                                     style="width: 180px; height: 180px; object-fit: cover; border: 4px solid #fff;">
                            </div>
                            <label for="profile_picture_input" class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-3 border border-3 border-white shadow-sm hover-lift transition-all cursor-pointer" style="margin-bottom: 10px; margin-right: 10px;">
                                <i class="fas fa-camera text-white"></i>
                            </label>
                            <input form="profileForm" type="file" id="profile_picture_input" name="profile_picture" class="d-none" accept="image/*" onchange="previewAndSubmit(this)">
                        </div>
                        <h4 class="text-white font-weight-bold mb-1"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h4>
                        <p class="text-white-50 small mb-4"><?php echo ucfirst(htmlspecialchars($user['role'] ?? 'User')); ?></p>
                        
                        <div class="bg-secondary bg-opacity-5 rounded-3 p-3 mb-2 text-start">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-envelope text-primary me-2" style="width: 20px;"></i>
                                <span class="text-white small"><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                            </div>
                            <div class="d-flex align-items-center mb-0">
                                <i class="fas fa-calendar-alt text-primary me-2" style="width: 20px;"></i>
                                <span class="text-white small">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                        <p class="text-white-50 x-small mt-3">JPG, PNG or GIF. Max size 2MB.</p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Quick Link -->
            <a href="<?php echo $dashboard_link; ?>" class="card glass border-0 shadow-sm text-decoration-none hover-lift transition-all mb-4">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="fas fa-tachometer-alt text-primary"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-white font-weight-bold">Back to Dashboard</h6>
                        <small class="text-white-50">Return to overview</small>
                    </div>
                    <i class="fas fa-chevron-right ms-auto text-white-50"></i>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
.profile-pic-wrapper img {
    transition: all 0.3s ease;
}
.profile-pic-wrapper:hover img {
    filter: brightness(0.9);
}
.cursor-pointer { cursor: pointer; }
.x-small { font-size: 0.7rem; }
</style>

<script>
function previewAndSubmit(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profileImagePreview').src = e.target.result;
            
            // Show a "Saving..." feedback if possible, or just submit
            if(confirm('Do you want to save this new profile picture?')) {
                document.getElementById('profileForm').submit();
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php include 'includes/footer.php'; ?>
