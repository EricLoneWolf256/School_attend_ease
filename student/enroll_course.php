<?php
// Set the base directory
$base_dir = dirname(__DIR__);

// Include required files
require_once $base_dir . '/config.php';
require_once $base_dir . '/includes/session.php';
require_once $base_dir . '/includes/db_connection.php';
require_once $base_dir . '/includes/attendance_functions.php';

// Ensure user is logged in as a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    $_SESSION['error'] = 'Access denied. You must be a student to access this page.';
    if (function_exists('redirect')) {
        redirect('../index.php');
    } else {
        header("Location: ../index.php");
        exit();
    }
}

$db = getDBConnection();
$student_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $course_id = (int)$_POST['course_id'];
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    try {
        // Check if already enrolled
        $stmt = $db->prepare("
            SELECT * FROM student_courses 
            WHERE student_id = ? AND course_id = ?
        ");
        $stmt->execute([$student_id, $course_id]);
        
        if ($stmt->rowCount() > 0) {
            $message = 'You are already enrolled in this course.';
            $success = false;
        } else {
            // Enroll the student
            $stmt = $db->prepare("
                INSERT INTO student_courses (student_id, course_id, enrollment_date) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$student_id, $course_id]);
            
            // Log the enrollment
            logAction($db, $student_id, 'course', 'enroll', 'Student enrolled in course ID: ' . $course_id, $course_id);
            
            $message = 'Successfully enrolled in the course!';
            $success = true;
        }
    } catch (PDOException $e) {
        $message = 'Error enrolling in course: ' . $e->getMessage();
        $success = false;
    }
    
    // Handle AJAX response
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'course_id' => $course_id
        ]);
        exit();
    } 
    // Handle regular form submission (non-AJAX)
    else {
        if ($success) {
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = $message;
        }
        
        // Redirect back to the enrollment page
        if (function_exists('redirect')) {
            redirect('enroll_course.php');
        } else {
            header("Location: enroll_course.php");
            exit();
        }
    }
}

// Get available courses (not already enrolled in)
$available_courses = [];
try {
    $stmt = $db->prepare("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
        FROM courses c
        LEFT JOIN course_assignments ca ON c.course_id = ca.course_id
        LEFT JOIN users u ON ca.lecturer_id = u.user_id
        WHERE c.course_id NOT IN (
            SELECT course_id 
            FROM student_courses 
            WHERE student_id = ?
        )
        ORDER BY c.course_code
    ");
    $stmt->execute([$student_id]);
    $available_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching available courses: ' . $e->getMessage();
}

// Get enrolled courses
$enrolled_courses = [];
try {
    $stmt = $db->prepare("
        SELECT c.*, sc.enrollment_date
        FROM courses c
        JOIN student_courses sc ON c.course_id = sc.course_id
        WHERE sc.student_id = ?
        ORDER BY sc.enrollment_date DESC
    ");
    $stmt->execute([$student_id]);
    $enrolled_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching enrolled courses: ' . $e->getMessage();
}

$page_title = 'Course Enrollment';
include 'header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Course Enrollment</h1>
    </div>


    <div class="row">
        <!-- Available Courses -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Available Courses</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($available_courses)): ?>
                        <p>No courses available for enrollment at this time.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Course Name</th>
                                        <th>Lecturer</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($available_courses as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars($course['lecturer_name'] ?? 'TBA'); ?></td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                    <button type="button" 
                                                            class="btn btn-sm btn-primary enroll-btn" 
                                                            data-course-id="<?php echo $course['course_id']; ?>"
                                                            onclick="enrollInCourse(this, <?php echo $course['course_id']; ?>)">
                                                        Enroll
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Enrolled Courses -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">My Enrolled Courses</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($enrolled_courses)): ?>
                        <p>You are not enrolled in any courses yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Course Name</th>
                                        <th>Enrolled On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrolled_courses as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($course['enrollment_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include footer
include 'footer.php'; 
?>

<script>
function showAlert(message, type = 'success') {
    // Remove any existing alerts
    const existingAlerts = document.querySelectorAll('.alert-dismissible');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    
    // Insert at the top of the content
    const content = document.querySelector('.container-fluid');
    if (content) {
        content.insertBefore(alertDiv, content.firstChild);
    } else {
        document.body.prepend(alertDiv);
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
        if (alert) alert.close();
    }, 5000);
}

function enrollInCourse(button, courseId) {
    if (!confirm('Are you sure you want to enroll in this course?')) {
        return false;
    }
    
    // Disable the button and show loading state
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enrolling...';
    
    // Create form data
    const formData = new FormData();
    formData.append('course_id', courseId);
    
    // Add X-Requested-With header for AJAX detection
    const headers = new Headers();
    headers.append('X-Requested-With', 'XMLHttpRequest');
    
    // Send AJAX request
    fetch('enroll_course.php', {
        method: 'POST',
        headers: headers,
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        // Show success or error message
        if (data.success) {
            showAlert(data.message, 'success');
            
            // Update the button
            button.innerHTML = 'Enrolled';
            button.classList.remove('btn-primary');
            button.classList.add('btn-success');
            
            // Reload the page after a short delay to show updated lists
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message, 'danger');
            
            // Re-enable the button if there was an error
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while processing your request.', 'danger');
        
        // Re-enable the button
        button.disabled = false;
        button.innerHTML = originalText;
    });
}
</script>
