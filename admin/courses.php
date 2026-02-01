<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. You must be an admin to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();
$success = '';
$error = '';

// Handle course deletion
if (isset($_POST['delete_course']) && isset($_POST['course_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->execute([$_POST['course_id']]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'Course deleted successfully.';
        } else {
            $_SESSION['error'] = 'Course not found or already deleted.';
        }
        
        redirect('courses.php');
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error deleting course: ' . $e->getMessage();
        redirect('courses.php');
    }
}

// Handle course activation/deactivation
if (isset($_POST['toggle_activation']) && isset($_POST['course_id'])) {
    try {
        // Get current status
        $stmt = $db->prepare("SELECT is_active FROM courses WHERE course_id = ?");
        $stmt->execute([$_POST['course_id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $new_status = $current['is_active'] ? 0 : 1;
            $stmt = $db->prepare("UPDATE courses SET is_active = ? WHERE course_id = ?");
            $stmt->execute([$new_status, $_POST['course_id']]);
            
            if ($new_status) {
                $_SESSION['success'] = 'Course activated successfully.';
            } else {
                $_SESSION['success'] = 'Course deactivated successfully.';
            }
        }
        
        // Return JSON response for AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
        
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Fetch all courses with lecturer count and ensure is_active is set with a default of 1
$courses = [];
try {
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM course_assignments WHERE course_id = c.course_id) as lecturer_count
              FROM courses c
              ORDER BY c.course_code ASC";
    $stmt = $db->query($query);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure is_active is set with a default value of 1 if not present
    $courses = array_map(function($course) {
        $course['is_active'] = $course['is_active'] ?? 1;
        return $course;
    }, $courses);
} catch (PDOException $e) {
    $error = 'Error fetching courses: ' . $e->getMessage();
}

$page_title = 'Manage Courses';
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <h1 class="mt-4 text-white font-weight-bold">Manage Courses</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item active text-white">Courses</li>
    </ol>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-white-50">Course Catalog</h1>
        <a href="add_course.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus mr-2"></i> Add New Course
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success']; 
            unset($_SESSION['success']);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error']; 
            unset($_SESSION['error']);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Courses Table -->
    <div class="card glass mb-4 border-0">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-transparent border-bottom border-secondary">
            <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">All Courses</h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-white-50"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right glass border-secondary shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                    <div class="dropdown-header text-white-50">Export Options:</div>
                    <a class="dropdown-item text-white" href="#"><i class="fas fa-file-excel fa-sm fa-fw mr-2 text-white-50"></i> Export to Excel</a>
                    <a class="dropdown-item text-white" href="#"><i class="fas fa-file-pdf fa-sm fa-fw mr-2 text-white-50"></i> Export to PDF</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php elseif (empty($courses)): ?>
                <div class="alert alert-info">No courses found. <a href="add_course.php">Add your first course</a>.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Lecturers</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td>
                                        <span class="badge" style="background-color: var(--primary-color); color: white;"><?php echo $course['lecturer_count']; ?> Lecturers</span>
                                        <a href="<?php echo htmlspecialchars("edit_course.php?id=" . $course['course_id']); ?>" class="btn btn-sm btn-link text-white-50">
                                            <i class="fas fa-user-edit"></i> Manage
                                        </a>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($course['created_at'])); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="mr-2">
                                                <label class="switch" title="<?php echo (isset($course['is_active']) && $course['is_active'] == 0) ? 'Inactive' : 'Active'; ?>">
                                                    <input type="checkbox" class="activation-toggle" data-id="<?php echo $course['course_id']; ?>" 
                                                        <?php echo ($course['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                                    <span class="slider round"></span>
                                                </label>
                                            </div>
                                            <div class="btn-group ml-2">
                                                <a href="edit_course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="#" class="btn btn-sm btn-info" title="View Details" data-toggle="modal" data-target="#courseModal<?php echo $course['course_id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <form action="courses.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this course? This action cannot be undone.');">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                    <button type="submit" name="delete_course" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Course Details Modal -->
                                <div class="modal fade" id="courseModal<?php echo $course['course_id']; ?>" tabindex="-1" role="dialog" aria-labelledby="courseModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content glass">
                                            <div class="modal-header bg-transparent border-bottom border-secondary text-white">
                                                <h5 class="modal-title" id="courseModalLabel"><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></h5>
                                                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                            </div>
                                            <div class="modal-body text-white">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <h6 style="color: var(--secondary-color);">Course Details</h6>
                                                        <p><?php echo nl2br(htmlspecialchars($course['description'] ?? 'No description available.')); ?></p>
                                                        <hr class="border-secondary">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p class="mb-1"><strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?></p>
                                                                <p class="mb-1"><strong>Created:</strong> <?php echo date('F j, Y', strtotime($course['created_at'])); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p class="mb-1"><strong>Lecturers:</strong> <?php echo $course['lecturer_count']; ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-transparent border-top border-secondary">
                                                <button class="btn btn-outline-light" type="button" data-dismiss="modal">Close</button>
                                                <a class="btn btn-primary shadow-sm" href="edit_course.php?id=<?php echo $course['course_id']; ?>">
                                                    <i class="fas fa-edit mr-1"></i> Edit Course
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Toast container for notifications -->
<div class="toast-container"></div>

<!-- Page level plugins -->
<script src="../assets/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/vendor/datatables/dataTables.bootstrap4.min.js"></script>

<!-- Page level custom scripts -->
<script>
    // Handle activation toggle
$(document).on('change', '.activation-toggle', function() {
    var courseId = $(this).data('id');
    var isChecked = $(this).is(':checked');
    var $switch = $(this);
    
    // Show loading state
    $switch.prop('disabled', true);
    
    // Send AJAX request
    $.ajax({
        url: 'courses.php',
        type: 'POST',
        data: {
            toggle_activation: 1,
            course_id: courseId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update UI based on new state
                var newStatus = isChecked ? 'active' : 'inactive';
                $switch.closest('label').next('span').text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                
                // Show success message
                var toast = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3000">' +
                    '<div class="toast-header">' +
                    '<strong class="mr-auto">Success</strong>' +
                    '<button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">' +
                    '<span aria-hidden="true">&times;</span>' +
                    '</button>' +
                    '</div>' +
                    '<div class="toast-body">' +
                    'Course status updated successfully.' +
                    '</div>' +
                    '</div>');
                
                $('.toast-container').append(toast);
                toast.toast('show');
                
                // Remove toast after it's hidden
                toast.on('hidden.bs.toast', function() {
                    $(this).remove();
                });
            } else {
                // Revert switch on error
                $switch.prop('checked', !isChecked);
                alert(response.error || 'An error occurred. Please try again.');
            }
        },
        error: function() {
            // Revert switch on error
            $switch.prop('checked', !isChecked);
            alert('An error occurred. Please try again.');
        },
        complete: function() {
            $switch.prop('disabled', false);
        }
    });
});

// Initialize DataTable
$(document).ready(function() {
        $('#dataTable').DataTable({
            "order": [[0, "asc"]],
            "pageLength": 10,
            "responsive": true
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
