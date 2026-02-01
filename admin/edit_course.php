<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. You must be an admin to access this page.';
    redirect('../index.php');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid course ID.';
    redirect('courses.php');
}

$course_id = (int)$_GET['id'];
$db = getDBConnection();
$error = '';
$success = '';

// Fetch course details
try {
    $stmt = $db->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        $_SESSION['error'] = 'Course not found.';
        redirect('courses.php');
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching course: ' . $e->getMessage();
    redirect('courses.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_course'])) {
        // Update course details
        $course_code = trim($_POST['course_code'] ?? '');
        $course_name = trim($_POST['course_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Basic validation
        if (empty($course_code) || empty($course_name)) {
            $error = 'Course code and name are required.';
        } else {
            try {
                // Check if course code already exists (excluding current course)
                $stmt = $db->prepare(
                    "SELECT course_id FROM courses 
                     WHERE course_code = ? AND course_id != ?"
                );
                $stmt->execute([$course_code, $course_id]);
                
                if ($stmt->rowCount() > 0) {
                    $error = 'A course with this code already exists.';
                } else {
                    // Update course
                    $stmt = $db->prepare(
                        "UPDATE courses 
                         SET course_code = ?, course_name = ?, description = ?
                         WHERE course_id = ?"
                    );
                    
                    if ($stmt->execute([$course_code, $course_name, $description, $course_id])) {
                        $success = 'Course updated successfully!';
                        // Refresh course data
                        $stmt = $db->prepare("SELECT * FROM courses WHERE course_id = ?");
                        $stmt->execute([$course_id]);
                        $course = $stmt->fetch();
                    } else {
                        $error = 'Failed to update course. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['assign_lecturer'])) {
        // Handle lecturer assignment
        $lecturer_id = (int)($_POST['lecturer_id'] ?? 0);
        
        if ($lecturer_id <= 0) {
            $response = ['success' => false, 'message' => 'Please select a valid lecturer.'];
        } else {
            try {
                // Check if assignment already exists
                $stmt = $db->prepare(
                    "SELECT assignment_id FROM course_assignments 
                     WHERE course_id = ? AND lecturer_id = ?"
                );
                $stmt->execute([$course_id, $lecturer_id]);
                
                if ($stmt->rowCount() > 0) {
                    $response = ['success' => false, 'message' => 'This lecturer is already assigned to this course.'];
                } else {
                    // Assign lecturer to course
                    $stmt = $db->prepare(
                        "INSERT INTO course_assignments (course_id, lecturer_id) 
                         VALUES (?, ?)"
                    );
                    
                    if ($stmt->execute([$course_id, $lecturer_id])) {
                        $response = ['success' => true, 'message' => 'Lecturer assigned successfully!'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to assign lecturer. Please try again.'];
                    }
                }
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        }
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } elseif (isset($_POST['remove_lecturer'])) {
        // Handle lecturer removal
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        
        if ($assignment_id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM course_assignments WHERE assignment_id = ?");
                if ($stmt->execute([$assignment_id])) {
                    $success = 'Lecturer removed from course successfully!';
                } else {
                    $error = 'Failed to remove lecturer. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch assigned lecturers
$assigned_lecturers = [];
try {
    $stmt = $db->prepare(
        "SELECT ca.assignment_id, u.user_id, u.first_name, u.last_name, u.email 
         FROM course_assignments ca
         JOIN users u ON ca.lecturer_id = u.user_id
         WHERE ca.course_id = ?
         ORDER BY u.last_name, u.first_name"
    );
    $stmt->execute([$course_id]);
    $assigned_lecturers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching assigned lecturers: ' . $e->getMessage();
}

// Fetch available lecturers (not yet assigned to this course)
$available_lecturers = [];
try {
    $stmt = $db->prepare(
        "SELECT u.user_id, u.first_name, u.last_name, u.email 
         FROM users u
         WHERE u.role = 'lecturer' 
         AND u.user_id NOT IN (
             SELECT lecturer_id FROM course_assignments WHERE course_id = ?
         )
         ORDER BY u.last_name, u.first_name"
    );
    $stmt->execute([$course_id]);
    $available_lecturers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching available lecturers: ' . $e->getMessage();
}

$page_title = 'Edit Course: ' . $course['course_code'];
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <h1 class="mt-4 text-white font-weight-bold">Edit Course</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="courses.php" class="text-white-50">Courses</a></li>
        <li class="breadcrumb-item active text-white">Edit Course</li>
    </ol>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-white-50">Course: <?php echo htmlspecialchars($course['course_code']); ?></h1>
        <a href="courses.php" class="btn btn-outline-light shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back to Courses
        </a>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Course Details -->
        <div class="col-lg-6">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Course Information</h6>
                </div>
                <div class="card-body">
                    <form action="edit_course.php?id=<?php echo $course_id; ?>" method="POST">
                        <input type="hidden" name="update_course" value="1">
                        
                        <div class="form-group">
                            <label for="course_code">Course Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="course_code" name="course_code" 
                                   value="<?php echo htmlspecialchars($course['course_code']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="course_name">Course Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="course_name" name="course_name" 
                                   value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Course
                            </button>
                            <a href="courses.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Assigned Lecturers -->
        <div class="col-lg-6">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Assigned Lecturers</h6>
                    <button type="button" class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#assignLecturerModal" 
                        <?php echo empty($available_lecturers) ? 'disabled title="No available lecturers to assign"' : ''; ?>>
                        <i class="fas fa-plus"></i> Assign Lecturer
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($assigned_lecturers)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No lecturers are currently assigned to this course.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_lecturers as $lecturer): ?>
                                        <tr>
                                            <td class="align-middle">
                                                <i class="fas fa-chalkboard-teacher text-primary mr-2"></i>
                                                <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                                            </td>
                                            <td class="align-middle">
                                                <a href="mailto:<?php echo htmlspecialchars($lecturer['email']); ?>">
                                                    <?php echo htmlspecialchars($lecturer['email']); ?>
                                                </a>
                                            </td>
                                            <td class="align-middle">
                                                <form action="edit_course.php?id=<?php echo $course_id; ?>" method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to remove this lecturer from the course?');">
                                                    <input type="hidden" name="remove_lecturer" value="1">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $lecturer['assignment_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                            title="Remove from course">
                                                        <i class="fas fa-user-minus"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($available_lecturers) && empty($assigned_lecturers)): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle"></i> No lecturers available to assign. 
                            <a href="lecturers.php">Add lecturers</a> first.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Lecturer Modal -->
<div class="modal fade" id="assignLecturerModal" tabindex="-1" role="dialog" aria-labelledby="assignLecturerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content glass">
            <div class="modal-header bg-transparent border-bottom border-secondary">
                <h5 class="modal-title text-white" id="assignLecturerModalLabel">
                    <i class="fas fa-user-plus mr-2" style="color: var(--secondary-color);"></i>Assign Lecturer to <?php echo htmlspecialchars($course['course_code']); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="assignLecturerForm" action="edit_course.php?id=<?php echo $course_id; ?>" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?php echo $course_id; ?>">
                <input type="hidden" name="assign_lecturer" value="1">
                
                <div class="modal-body">
                    <div id="assignLecturerAlert" class="alert" style="display: none;"></div>
                    
                    <?php if (empty($available_lecturers)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            No available lecturers to assign. All lecturers are already assigned to this course.
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="lecturer_id" class="font-weight-bold">Select Lecturer <span class="text-danger">*</span></label>
                            <select class="form-control select2" id="lecturer_id" name="lecturer_id" required>
                                <option value="" disabled selected>-- Select a lecturer --</option>
                                <?php 
                                // Group lecturers by department if available
                                $lecturers_by_dept = [];
                                foreach ($available_lecturers as $lecturer) {
                                    $dept = $lecturer['department'] ?? 'Other';
                                    if (!isset($lecturers_by_dept[$dept])) {
                                        $lecturers_by_dept[$dept] = [];
                                    }
                                    $lecturers_by_dept[$dept][] = $lecturer;
                                }
                                
                                foreach ($lecturers_by_dept as $dept => $dept_lecturers): 
                                    if (count($lecturers_by_dept) > 1): // Only show optgroup if we have multiple departments
                                ?>
                                    <optgroup label="<?php echo htmlspecialchars($dept); ?>">
                                <?php endif; ?>
                                
                                <?php foreach ($dept_lecturers as $lecturer): ?>
                                    <option value="<?php echo $lecturer['user_id']; ?>">
                                        <?php echo htmlspecialchars(sprintf(
                                            '%s %s (%s)', 
                                            $lecturer['first_name'], 
                                            $lecturer['last_name'], 
                                            $lecturer['email']
                                        )); ?>
                                    </option>
                                <?php endforeach; ?>
                                
                                <?php if (count($lecturers_by_dept) > 1): ?>
                                    </optgroup>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a lecturer to assign to this course.
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="font-weight-bold">Available Lecturers</label>
                            <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($available_lecturers as $lecturer): ?>
                                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($lecturer['email']); ?></small>
                                            <?php if (!empty($lecturer['department'])): ?>
                                                <span class="badge badge-info ml-2"><?php echo htmlspecialchars($lecturer['department']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary select-lecturer" 
                                                data-id="<?php echo $lecturer['user_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>">
                                            Select
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <?php if (!empty($available_lecturers)): ?>
                        <button type="submit" class="btn btn-primary" id="submitAssignLecturer">
                            <i class="fas fa-user-plus mr-1"></i> Assign Lecturer
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Handle select lecturer button click
$(document).on('click', '.select-lecturer', function() {
    const lecturerId = $(this).data('id');
    const lecturerName = $(this).data('name');
    
    // Set the selected lecturer in the dropdown
    $('#lecturer_id').val(lecturerId).trigger('change');
    
    // Show a brief visual feedback
    const button = $(this);
    const originalText = button.html();
    button.html('<i class="fas fa-check"></i> Selected').removeClass('btn-outline-primary').addClass('btn-success');
    
    // Reset the button after a short delay
    setTimeout(function() {
        button.html(originalText).removeClass('btn-success').addClass('btn-outline-primary');
    }, 1500);
});
</script>
