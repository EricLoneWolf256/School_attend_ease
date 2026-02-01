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
$message = '';
$error = '';
$lecture = null;
$already_marked = false;

// Check if accessing with a direct lecture link
if (isset($_GET['lecture_id']) && is_numeric($_GET['lecture_id'])) {
    $lecture_id = (int)$_GET['lecture_id'];
    
    try {
        // Get lecture details
        $stmt = $db->prepare(
            "SELECT l.*, c.course_code, c.course_name, 
                    CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
             FROM lectures l
             JOIN courses c ON l.course_id = c.course_id
             JOIN users u ON l.lecturer_id = u.user_id
             WHERE l.lecture_id = ?
             AND l.course_id IN (
                 SELECT course_id FROM student_courses WHERE student_id = ?
             )"
        );
        $stmt->execute([$lecture_id, $student_id]);
        $lecture = $stmt->fetch();
        
        if ($lecture) {
            // Check if already marked attendance
            $stmt = $db->prepare(
                "SELECT * FROM attendance 
                 WHERE lecture_id = ? AND student_id = ?"
            );
            $stmt->execute([$lecture_id, $student_id]);
            $attendance = $stmt->fetch();
            
            if ($attendance) {
                $already_marked = true;
                $message = 'You have already marked your attendance for this lecture.';
            } elseif (!$lecture['is_active'] || empty($lecture['secret_code'])) {
                $error = 'This lecture is not currently active for attendance marking.';
            } else {
                // Check if current time is within lecture time
                $current_time = date('H:i:s');
                $current_date = date('Y-m-d');
                $lecture_date = $lecture['scheduled_date'];
                $start_time = $lecture['start_time'];
                $end_time = $lecture['end_time'];
                
                if ($current_date !== $lecture_date) {
                    $error = 'This lecture is not scheduled for today.';
                } elseif ($current_time < $start_time) {
                    $error = 'This lecture has not started yet. Please wait until ' . date('g:i A', strtotime($start_time)) . '.';
                } elseif ($current_time > $end_time) {
                    $error = 'This lecture has already ended.';
                } else {
                    // Pre-fill the attendance code
                    $_POST['attendance_code'] = $lecture['secret_code'];
                }
            }
        } else {
            $error = 'Lecture not found or you are not enrolled in this course.';
        }
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $attendance_code = strtoupper(trim($_POST['attendance_code'] ?? ''));
    
    if (empty($attendance_code)) {
        $error = 'Please enter an attendance code.';
    } else {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Find active lecture with this code
            $stmt = $db->prepare(
                "SELECT l.*, c.course_code, c.course_name, 
                        CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
                 FROM lectures l
                 JOIN courses c ON l.course_id = c.course_id
                 JOIN users u ON l.lecturer_id = u.user_id
                 WHERE l.secret_code = ?
                 AND l.is_active = 1
                 AND l.scheduled_date = CURDATE()
                 AND CURTIME() BETWEEN l.start_time AND l.end_time"
            );
            $stmt->execute([$attendance_code]);
            $lecture = $stmt->fetch();
            
            if (!$lecture) {
                $error = 'Invalid or expired attendance code. Please check the code and try again.';
            } else {
                // Check if student is enrolled in the course
                $stmt = $db->prepare(
                    "SELECT 1 FROM student_courses 
                     WHERE student_id = ? AND course_id = ?"
                );
                $stmt->execute([$student_id, $lecture['course_id']]);
                $is_enrolled = $stmt->fetch();
                
                if (!$is_enrolled) {
                    $error = 'You are not enrolled in this course.';
                } else {
                    // Check if already marked attendance
                    $stmt = $db->prepare(
                        "SELECT * FROM attendance 
                         WHERE lecture_id = ? AND student_id = ?"
                    );
                    $stmt->execute([$lecture['lecture_id'], $student_id]);
                    $attendance = $stmt->fetch();
                    
                    if ($attendance) {
                        $already_marked = true;
                        $message = 'You have already marked your attendance for this lecture.';
                    } else {
                        // Determine if attendance is on time or late
                        $current_time = date('H:i:s');
                        $lecture_start = $lecture['start_time'];
                        $lecture_end = $lecture['end_time'];
                        
                        // Consider attendance late if after 15 minutes from start
                        $late_threshold = date('H:i:s', strtotime($lecture_start . ' +15 minutes'));
                        $status = ($current_time > $late_threshold) ? 'late' : 'present';
                        
                        // Record attendance
                        $stmt = $db->prepare(
                            "INSERT INTO attendance 
                             (lecture_id, student_id, status, marked_at, attendance_code)
                             VALUES (?, ?, ?, NOW(), ?)"
                        );
                        
                        if ($stmt->execute([
                            $lecture['lecture_id'], 
                            $student_id, 
                            $status,
                            $attendance_code
                        ])) {
                            $message = 'Attendance marked successfully! ';
                            $message .= $status === 'late' ? 
                                'You were marked as late.' : 
                                'You were marked as present.';
                            
                            // Commit transaction
                            $db->commit();
                            
                            // Set flag to prevent resubmission
                            $already_marked = true;
                            
                            // Add success message to session for redirect
                            $_SESSION['success'] = $message;
                            
                            // Redirect to prevent form resubmission
                            redirect('attendance_success.php?lecture_id=' . $lecture['lecture_id']);
                        } else {
                            $error = 'Failed to mark attendance. Please try again.';
                        }
                    }
                }
            }
            
            // If we get here, there was an error, so rollback
            if ($error) {
                $db->rollBack();
            }
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Mark Attendance';
include 'header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg my-5">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="m-0">Mark Your Attendance</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
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
                    
                    <?php if ($lecture && !$already_marked): ?>
                        <div class="text-center mb-4">
                            <h5 class="text-primary"><?php echo htmlspecialchars($lecture['course_code'] . ' - ' . $lecture['course_name']); ?></h5>
                            <h4 class="font-weight-bold"><?php echo htmlspecialchars($lecture['title']); ?></h4>
                            <p class="text-muted">
                                <i class="fas fa-calendar-alt"></i> 
                                <?php echo date('l, F j, Y', strtotime($lecture['scheduled_date'])); ?>
                                <br>
                                <i class="far fa-clock"></i> 
                                <?php echo date('g:i A', strtotime($lecture['start_time'])) . ' - ' . date('g:i A', strtotime($lecture['end_time'])); ?>
                                <br>
                                <i class="fas fa-chalkboard-teacher"></i> 
                                <?php echo htmlspecialchars($lecture['lecturer_name']); ?>
                            </p>
                            <hr>
                            <p class="lead">Enter the attendance code provided by your lecturer:</p>
                        </div>
                    <?php elseif (!$already_marked): ?>
                        <div class="text-center mb-4">
                            <i class="fas fa-clipboard-check fa-4x text-primary mb-3"></i>
                            <h4 class="font-weight-bold">Enter Attendance Code</h4>
                            <p class="text-muted">
                                Enter the 6-character code provided by your lecturer to mark your attendance.
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$already_marked): ?>
                        <form action="mark_attendance.php" method="POST" id="attendanceForm">
                            <div class="form-group">
                                <div class="input-group input-group-lg">
                                    <input type="text" 
                                           class="form-control text-center font-weight-bold text-uppercase" 
                                           id="attendance_code" 
                                           name="attendance_code" 
                                           placeholder="e.g., A1B2C3" 
                                           maxlength="6"
                                           value="<?php echo htmlspecialchars($_POST['attendance_code'] ?? ''); ?>"
                                           required
                                           autofocus
                                           autocomplete="off">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary px-4" type="submit" name="submit_attendance">
                                            <i class="fas fa-check"></i> Submit
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    The code is case-insensitive and usually 6 characters long.
                                </small>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <?php if ($already_marked && $lecture): ?>
                            <a href="attendance_details.php?lecture_id=<?php echo $lecture['lecture_id']; ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-info-circle"></i> View Details
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Help Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-2">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-question-circle text-primary"></i> Need Help?
                    </h6>
                </div>
                <div class="card-body small p-3">
                    <ul class="mb-0 pl-3">
                        <li>Make sure you're in the right location if required by your lecturer.</li>
                        <li>Enter the code exactly as shown (it's not case-sensitive).</li>
                        <li>If the code isn't working, ask your lecturer to verify it.</li>
                        <li>You can only mark attendance once per lecture.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-uppercase and limit to 6 characters
document.getElementById('attendance_code').addEventListener('input', function(e) {
    this.value = this.value.toUpperCase().substring(0, 6);
});

// Focus the input field when the page loads
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('attendance_code');
    if (input) {
        input.focus();
    }
});
</script>

<?php include 'footer.php'; ?>
