<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    $_SESSION['error'] = 'Access denied. You must be a student to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();
$student_id = $_SESSION['user_id'];
$user = null;

// Fetch user data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching profile data: ' . $e->getMessage();
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $target_dir = '../uploads/';
    $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
    $target_file = $target_dir . $file_name;
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES['profile_picture']['tmp_name']);
    if ($check !== false) {
        $uploadOk = 1;
    } else {
        $_SESSION['error'] = 'File is not an image.';
        $uploadOk = 0;
    }

    // Check file size (5MB limit)
    if ($_FILES['profile_picture']['size'] > 5000000) {
        $_SESSION['error'] = 'Sorry, your file is too large.';
        $uploadOk = 0;
    }

    // Allow certain file formats
    if ($imageFileType != 'jpg' && $imageFileType != 'png' && $imageFileType != 'jpeg' && $imageFileType != 'gif') {
        $_SESSION['error'] = 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.';
        $uploadOk = 0;
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        // if everything is ok, try to upload file
    } else {
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
            // Update database with new profile picture path
            try {
                $stmt = $db->prepare('UPDATE users SET profile_picture = ? WHERE user_id = ?');
                $stmt->execute([$file_name, $student_id]);
                $_SESSION['success'] = 'Profile picture updated successfully.';
                redirect('profile.php');
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error updating profile picture: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Sorry, there was an error uploading your file.';
        }
    }
}

$page_title = 'My Profile';
include 'header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">My Profile</h1>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Picture</h6>
                </div>
                <div class="card-body text-center">
                    <img src="../uploads/<?php echo htmlspecialchars($user['profile_picture'] ?? 'default.png'); ?>" alt="Profile Picture" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                    <form action="profile.php" method="POST" enctype="multipart/form-data">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="profile_picture" name="profile_picture" required>
                            <label class="custom-file-label" for="profile_picture">Choose file...</label>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Upload</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Details</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>First Name</th>
                            <td><?php echo htmlspecialchars($user['first_name'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th>Last Name</th>
                            <td><?php echo htmlspecialchars($user['last_name'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th>Role</th>
                            <td><?php echo ucfirst(htmlspecialchars($user['role'] ?? '')); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
