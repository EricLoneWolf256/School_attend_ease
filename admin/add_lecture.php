<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. You must be an admin to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();
$error = '';

// Fetch all courses
$courses = [];
try {
    $stmt = $db->query("SELECT course_id, course_code, course_name FROM courses ORDER BY course_code");
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching courses: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $lecturer_name = trim($_POST['lecturer_name'] ?? '');
    $lecturer_id = $_SESSION['user_id']; // Default to current user for non-admin
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $generate_code = isset($_POST['generate_code']);
    
    // Basic validation
    if ($course_id <= 0) {
        $error = 'Please select a valid course.';
    } elseif (empty($lecturer_name) && $_SESSION['role'] === 'admin') {
        $error = 'Please enter a lecturer name.';
    } elseif (empty($title)) {
        $error = 'Please enter a title for the lecture.';
    } elseif (empty($scheduled_date) || empty($start_time) || empty($end_time)) {
        $error = 'Please provide date and time details.';
    } else {
        // Convert times to DateTime objects for comparison
        $start_datetime = new DateTime($scheduled_date . ' ' . $start_time);
        $end_datetime = new DateTime($scheduled_date . ' ' . $end_time);
        
        if ($end_datetime <= $start_datetime) {
            $error = 'End time must be after start time.';
        } else {
            try {
                // Check for scheduling conflicts
                $stmt = $db->prepare(
                    "SELECT COUNT(*) as conflict_count 
                     FROM lectures 
                     WHERE lecturer_id = ? 
                     AND scheduled_date = ? 
                     AND (
                         (start_time <= ? AND end_time > ?) OR  -- New lecture starts during existing
                         (start_time < ? AND end_time >= ?) OR  -- New lecture ends during existing
                         (start_time >= ? AND end_time <= ?)     -- New lecture is within existing
                     )"
                );
                
                $stmt->execute([
                    $lecturer_id, 
                    $scheduled_date,
                    $start_time, $start_time,
                    $end_time, $end_time,
                    $start_time, $end_time
                ]);
                
                $conflict = $stmt->fetch();
                
                if ($conflict['conflict_count'] > 0) {
                    $error = 'You already have a lecture scheduled during this time.';
                } else {
                    // Generate secret code if requested
                    $secret_code = $generate_code ? generateSecretCode() : null;
                    
                    // Insert new lecture
                    if ($_SESSION['role'] === 'admin') {
                        $lecturer_name = $lecturer_name;
                    } else {
                        // Get current user's name
                        $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE user_id = ?");
                        $stmt->execute([$lecturer_id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        $lecturer_name = $user['full_name'];
                    }

                    // Insert the lecture
                    $stmt = $db->prepare("INSERT INTO lectures (course_id, lecturer_id, title, description, scheduled_date, start_time, end_time, is_active, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                    
                    if ($stmt->execute([$course_id, $lecturer_id, $title, $description, $scheduled_date, $start_time, $end_time])) {

                        $lecture_id = $db->lastInsertId();
                        $_SESSION['success'] = 'Lecture scheduled successfully!';
                        
                        if ($generate_code) {
                            $_SESSION['success'] .= " Attendance code: <strong>$secret_code</strong>";
                        }
                        
                        redirect('timetable.php');
                    } else {
                        $error = 'Failed to schedule lecture. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Schedule New Lecture';
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <h1 class="mt-4 text-white font-weight-bold">Schedule New Lecture</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="timetable.php" class="text-white-50">Timetable</a></li>
        <li class="breadcrumb-item active text-white">Schedule Lecture</li>
    </ol>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-white-50">Lecture Details</h1>
        <a href="timetable.php" class="btn btn-outline-light shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back to Timetable
        </a>
    </div>

    <!-- Content Row -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Lecture Details</h6>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger bg-danger text-white border-0"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form action="add_lecture.php" method="POST" id="lectureForm">
                        <div class="form-group row">
                            <label for="course_id" class="col-sm-3 col-form-label">Course <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select class="form-control" id="course_id" name="course_id" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>" 
                                            <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="form-group row">
                            <label for="lecturer_name" class="col-sm-3 col-form-label">Lecturer <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="lecturer_name" name="lecturer_name" 
                                       value="<?php echo htmlspecialchars($_POST['lecturer_name'] ?? ''); ?>" required>
                                <small class="form-text text-muted">Enter the name of the lecturer</small>
                            </div>
                        </div>
                        <?php else: ?>
                            <div class="form-group row">
                                <label class="col-sm-3 col-form-label">Lecturer</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>" readonly>
                                    <input type="hidden" name="lecturer_id" value="<?php echo $_SESSION['user_id']; ?>">
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group row">
                            <label for="title" class="col-sm-3 col-form-label">Title <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                <small class="form-text text-muted">e.g., Introduction to Web Development</small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="description" class="col-sm-3 col-form-label">Description</label>
                            <div class="col-sm-9">
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Date & Time <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <div class="form-row">
                                    <div class="col-md-5 mb-3">
                                        <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" 
                                               value="<?php echo htmlspecialchars($_POST['scheduled_date'] ?? date('Y-m-d')); ?>" required>
                                        <small class="form-text text-muted">Date</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <input type="time" class="form-control" id="start_time" name="start_time" 
                                               value="<?php echo htmlspecialchars($_POST['start_time'] ?? '09:00'); ?>" required>
                                        <small class="form-text text-muted">Start Time</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <input type="time" class="form-control" id="end_time" name="end_time" 
                                               value="<?php echo htmlspecialchars($_POST['end_time'] ?? '10:00'); ?>" required>
                                        <small class="form-text text-muted">End Time</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="generate_code" name="generate_code" 
                                           <?php echo (isset($_POST['generate_code']) || !isset($_POST['generate_code'])) ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="generate_code">
                                        Generate attendance code and activate lecture immediately
                                    </label>
                                    <small class="form-text text-muted">
                                        If checked, a random 6-character code will be generated and students can start marking attendance.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Schedule Lecture
                                </button>
                                <a href="timetable.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Quick Tips</h6>
                </div>
                <div class="card-body text-white">
                    <h6>Lecture Scheduling</h6>
                    <p>Schedule lectures in advance or create them on the spot with an attendance code.</p>
                    
                    <h6 class="mt-4">Attendance Codes</h6>
                    <p>When you check "Generate attendance code", a random 6-character code will be created. Share this code with students to mark their attendance.</p>
                    
                    <h6 class="mt-4">Lecture Status</h6>
                    <p><span class="badge badge-success">Active</span> - Students can mark attendance using the code<br>
                    <span class="badge badge-secondary">Completed</span> - Lecture has ended<br>
                    <span class="badge badge-info">Scheduled</span> - Upcoming lecture, no code generated yet</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Set default time to next hour
    var now = new Date();
    var nextHour = new Date(now.getTime() + 60 * 60 * 1000);
    var hours = nextHour.getHours().toString().padStart(2, '0');
    var minutes = '00';
    
    // Set default end time to 1 hour after start time
    $('#start_time').on('change', function() {
        var startTime = $(this).val();
        if (startTime) {
            var timeParts = startTime.split(':');
            var startDate = new Date();
            startDate.setHours(parseInt(timeParts[0]), parseInt(timeParts[1]));
            
            // Add 1 hour
            startDate.setHours(startDate.getHours() + 1);
            
            // Format the time
            var endTime = startDate.getHours().toString().padStart(2, '0') + ':' + 
                          startDate.getMinutes().toString().padStart(2, '0');
            
            $('#end_time').val(endTime);
        }
    });
    
    // Set default date to today if not set
    if (!$('#scheduled_date').val()) {
        $('#scheduled_date').val(new Date().toISOString().split('T')[0]);
    }
    
    // Make title field read-only for non-admin users
    <?php if ($_SESSION['role'] !== 'admin'): ?>
        $('#title').prop('readonly', true);
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>
