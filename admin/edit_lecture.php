<?php
require_once '../config.php';
require_once __DIR__ . '/../includes/session.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. You must be an admin to access this page.';
    redirect('../index.php');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid lecture ID.';
    redirect('timetable.php');
}

$lecture_id = (int)$_GET['id'];
$db = getDBConnection();
$error = '';
$success = '';

// Fetch lecture details
try {
    $stmt = $db->prepare(
        "SELECT l.*, c.course_code, c.course_name, 
                u.first_name, u.last_name, u.email as lecturer_email
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         JOIN users u ON l.lecturer_id = u.user_id
         WHERE l.lecture_id = ?"
    );
    $stmt->execute([$lecture_id]);
    $lecture = $stmt->fetch();
    
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found.';
        redirect('timetable.php');
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching lecture: ' . $e->getMessage();
    redirect('timetable.php');
}

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
    if (isset($_POST['update_lecture'])) {
        // Update lecture details
        $course_id = (int)($_POST['course_id'] ?? 0);
        $lecturer_id = (int)($_POST['lecturer_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scheduled_date = $_POST['scheduled_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        
        // Basic validation
        if ($course_id <= 0) {
            $error = 'Please select a valid course.';
        } elseif ($lecturer_id <= 0) {
            $error = 'Please select a valid lecturer.';
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
                    // Check for scheduling conflicts (excluding current lecture)
                    $stmt = $db->prepare(
                        "SELECT COUNT(*) as conflict_count 
                         FROM lectures 
                         WHERE lecturer_id = ? 
                         AND scheduled_date = ? 
                         AND lecture_id != ?
                         AND (
                             (start_time <= ? AND end_time > ?) OR  -- New lecture starts during existing
                             (start_time < ? AND end_time >= ?) OR  -- New lecture ends during existing
                             (start_time >= ? AND end_time <= ?)     -- New lecture is within existing
                         )"
                    );
                    
                    $stmt->execute([
                        $lecturer_id, 
                        $scheduled_date,
                        $lecture_id,
                        $start_time, $start_time,
                        $end_time, $end_time,
                        $start_time, $end_time
                    ]);
                    
                    $conflict = $stmt->fetch();
                    
                    if ($conflict['conflict_count'] > 0) {
                        $error = 'The selected lecturer already has a lecture scheduled during this time.';
                    } else {
                        // Update lecture
                        $stmt = $db->prepare(
                            "UPDATE lectures 
                             SET course_id = ?, lecturer_id = ?, title = ?, description = ?, 
                                 scheduled_date = ?, start_time = ?, end_time = ?
                             WHERE lecture_id = ?"
                        );
                        
                        if ($stmt->execute([
                            $course_id, 
                            $lecturer_id, 
                            $title, 
                            $description, 
                            $scheduled_date, 
                            $start_time, 
                            $end_time,
                            $lecture_id
                        ])) {
                            $success = 'Lecture updated successfully!';
                            
                            // Refresh lecture data
                            $stmt = $db->prepare(
                                "SELECT l.*, c.course_code, c.course_name, 
                                        u.first_name, u.last_name, u.email as lecturer_email
                                 FROM lectures l
                                 JOIN courses c ON l.course_id = c.course_id
                                 JOIN users u ON l.lecturer_id = u.user_id
                                 WHERE l.lecture_id = ?"
                            );
                            $stmt->execute([$lecture_id]);
                            $lecture = $stmt->fetch();
                        } else {
                            $error = 'Failed to update lecture. Please try again.';
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['generate_code'])) {
        // Generate new attendance code
        try {
            $new_code = generateSecretCode();
            
            $stmt = $db->prepare(
                "UPDATE lectures 
                 SET secret_code = ?, is_active = 1
                 WHERE lecture_id = ?"
            );
            
            if ($stmt->execute([$new_code, $lecture_id])) {
                $success = 'New attendance code generated: <strong>' . $new_code . '</strong>';
                $lecture['secret_code'] = $new_code;
                $lecture['is_active'] = 1;
            } else {
                $error = 'Failed to generate new code. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['end_lecture'])) {
        // End the lecture
        try {
            $stmt = $db->prepare(
                "UPDATE lectures 
                 SET is_active = 0
                 WHERE lecture_id = ?"
            );
            
            if ($stmt->execute([$lecture_id])) {
                $success = 'Lecture has been ended. No more attendance can be marked.';
                $lecture['is_active'] = 0;
            } else {
                $error = 'Failed to end lecture. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch lecturers for the current course
$lecturers = [];
try {
    $stmt = $db->prepare(
        "SELECT u.user_id, u.first_name, u.last_name 
         FROM users u
         JOIN course_assignments ca ON u.user_id = ca.lecturer_id
         WHERE ca.course_id = ?
         ORDER BY u.last_name, u.first_name"
    );
    $stmt->execute([$lecture['course_id']]);
    $lecturers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching lecturers: ' . $e->getMessage();
}

$page_title = 'Edit Lecture: ' . $lecture['title'];
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <h1 class="mt-4 text-white font-weight-bold">Edit Lecture</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="timetable.php" class="text-white-50">Timetable</a></li>
        <li class="breadcrumb-item active text-white">Edit Lecture</li>
    </ol>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-white-50">Lecture Details</h1>
        <a href="timetable.php" class="btn btn-outline-light shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back to Timetable
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
        <!-- Lecture Details -->
        <div class="col-lg-8">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Lecture Information</h6>
                </div>
                <div class="card-body">
                    <form action="edit_lecture.php?id=<?php echo $lecture_id; ?>" method="POST" id="lectureForm">
                        <input type="hidden" name="update_lecture" value="1">
                        
                        <div class="form-group row">
                            <label for="course_id" class="col-sm-3 col-form-label">Course <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select class="form-control" id="course_id" name="course_id" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>" 
                                            <?php echo ($lecture['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="lecturer_id" class="col-sm-3 col-form-label">Lecturer <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select class="form-control" id="lecturer_id" name="lecturer_id" required>
                                    <?php if (empty($lecturers)): ?>
                                        <option value="">No lecturers assigned to this course</option>
                                    <?php else: ?>
                                        <?php foreach ($lecturers as $lecturer): ?>
                                            <option value="<?php echo $lecturer['user_id']; ?>" 
                                                <?php echo ($lecture['lecturer_id'] == $lecturer['user_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="title" class="col-sm-3 col-form-label">Title <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($lecture['title']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="description" class="col-sm-3 col-form-label">Description</label>
                            <div class="col-sm-9">
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($lecture['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Date & Time <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <div class="form-row">
                                    <div class="col-md-5 mb-3">
                                        <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" 
                                               value="<?php echo htmlspecialchars($lecture['scheduled_date']); ?>" required>
                                        <small class="form-text text-muted">Date</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <input type="time" class="form-control" id="start_time" name="start_time" 
                                               value="<?php echo htmlspecialchars(substr($lecture['start_time'], 0, 5)); ?>" required>
                                        <small class="form-text text-muted">Start Time</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <input type="time" class="form-control" id="end_time" name="end_time" 
                                               value="<?php echo htmlspecialchars(substr($lecture['end_time'], 0, 5)); ?>" required>
                                        <small class="form-text text-muted">End Time</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
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
        
        <!-- Lecture Actions -->
        <div class="col-lg-4">
            <!-- Status Card -->
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Lecture Status</h6>
                </div>
                <div class="card-body text-center text-white">
                    <?php 
                    $is_past = strtotime($lecture['scheduled_date'] . ' ' . $lecture['end_time']) < time();
                    $is_active = $lecture['is_active'] == 1;
                    $is_today = $lecture['scheduled_date'] == date('Y-m-d');
                    ?>
                    
                    <div class="mb-4">
                        <?php if ($is_active): ?>
                            <span class="badge badge-success p-3" style="font-size: 1.2rem;">
                                <i class="fas fa-check-circle fa-2x d-block mb-2"></i>
                                Active
                            </span>
                            <p class="mt-3 mb-1">Attendance Code:</p>
                            <h3 class="text-primary font-weight-bold"><?php echo htmlspecialchars($lecture['secret_code']); ?></h3>
                            <p class="text-muted small">Share this code with students to mark attendance</p>
                            
                            <form action="edit_lecture.php?id=<?php echo $lecture_id; ?>" method="POST" class="mt-3">
                                <button type="submit" name="end_lecture" class="btn btn-warning btn-block" 
                                        onclick="return confirm('Are you sure you want to end this lecture? This will prevent any further attendance marking.');">
                                    <i class="fas fa-stop-circle"></i> End Lecture
                                </button>
                            </form>
                            
                        <?php elseif ($is_past): ?>
                            <span class="badge badge-secondary p-3" style="font-size: 1.2rem;">
                                <i class="fas fa-check-circle fa-2x d-block mb-2"></i>
                                Completed
                            </span>
                            <p class="mt-3">This lecture has ended.</p>
                            
                        <?php else: ?>
                            <span class="badge badge-info p-3" style="font-size: 1.2rem;">
                                <i class="far fa-calendar-alt fa-2x d-block mb-2"></i>
                                Scheduled
                            </span>
                            <p class="mt-3">This lecture is scheduled for a future date.</p>
                            
                            <form action="edit_lecture.php?id=<?php echo $lecture_id; ?>" method="POST" class="mt-3">
                                <button type="submit" name="generate_code" class="btn btn-primary btn-block">
                                    <i class="fas fa-key"></i> Generate Attendance Code
                                </button>
                                <small class="form-text text-muted">This will activate the lecture for attendance.</small>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($lecture['secret_code'] && !$is_active): ?>
                        <div class="alert alert-info mt-3">
                            <p class="mb-1">Previous attendance code:</p>
                            <p class="h5 font-weight-bold"><?php echo htmlspecialchars($lecture['secret_code']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <a href="attendance.php?lecture_id=<?php echo $lecture_id; ?>" class="btn btn-info btn-block mb-2">
                        <i class="fas fa-clipboard-list"></i> View Attendance
                    </a>
                    <a href="#" class="btn btn-secondary btn-block mb-2" data-toggle="modal" data-target="#emailStudentsModal">
                        <i class="fas fa-envelope"></i> Email Students
                    </a>
                    <a href="#" class="btn btn-success btn-block mb-2" data-toggle="modal" data-target="#duplicateLectureModal">
                        <i class="fas fa-copy"></i> Duplicate Lecture
                    </a>
                    <form action="timetable.php" method="POST" class="d-inline-block w-100" 
                          onsubmit="return confirm('Are you sure you want to delete this lecture? This action cannot be undone.');">
                        <input type="hidden" name="lecture_id" value="<?php echo $lecture_id; ?>">
                        <button type="submit" name="delete_lecture" class="btn btn-danger btn-block">
                            <i class="fas fa-trash"></i> Delete Lecture
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Students Modal -->
<div class="modal fade" id="emailStudentsModal" tabindex="-1" role="dialog" aria-labelledby="emailStudentsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass">
            <div class="modal-header bg-transparent border-bottom border-secondary text-white">
                <h5 class="modal-title" id="emailStudentsModalLabel">Email Students</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="email_students.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="lecture_id" value="<?php echo $lecture_id; ?>">
                    
                    <div class="form-group">
                        <label for="email_subject">Subject</label>
                        <input type="text" class="form-control" id="email_subject" name="subject" 
                               value="Lecture: <?php echo htmlspecialchars($lecture['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_message">Message</label>
                        <textarea class="form-control" id="email_message" name="message" rows="6" required>Dear students,

This is a reminder about the upcoming lecture:

Lecture: <?php echo htmlspecialchars($lecture['title']); ?>
Date: <?php echo date('l, F j, Y', strtotime($lecture['scheduled_date'])); ?>
Time: <?php echo date('g:i A', strtotime($lecture['start_time'])); ?> - <?php echo date('g:i A', strtotime($lecture['end_time'])); ?>

<?php if ($lecture['is_active'] && $lecture['secret_code']): ?>
Attendance Code: <?php echo $lecture['secret_code']; ?>

Please use this code to mark your attendance during the lecture.
<?php endif; ?>

Best regards,
<?php echo $_SESSION['name'] ?? 'Administrator'; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="include_attendance_code" name="include_attendance_code" 
                                   <?php echo ($lecture['is_active'] && $lecture['secret_code']) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="include_attendance_code">
                                Include attendance code (if available)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Duplicate Lecture Modal -->
<div class="modal fade" id="duplicateLectureModal" tabindex="-1" role="dialog" aria-labelledby="duplicateLectureModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass">
            <div class="modal-header bg-transparent border-bottom border-secondary text-white">
                <h5 class="modal-title" id="duplicateLectureModalLabel">Duplicate Lecture</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="duplicate_lecture.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="source_lecture_id" value="<?php echo $lecture_id; ?>">
                    
                    <div class="form-group">
                        <label for="duplicate_date">New Date</label>
                        <input type="date" class="form-control" id="duplicate_date" name="scheduled_date" 
                               value="<?php echo date('Y-m-d', strtotime('+1 week')); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="duplicate_start_time">Start Time</label>
                            <input type="time" class="form-control" id="duplicate_start_time" name="start_time" 
                                   value="<?php echo substr($lecture['start_time'], 0, 5); ?>" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="duplicate_end_time">End Time</label>
                            <input type="time" class="form-control" id="duplicate_end_time" name="end_time" 
                                   value="<?php echo substr($lecture['end_time'], 0, 5); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="generate_new_code" name="generate_code">
                            <label class="custom-control-label" for="generate_new_code">
                                Generate new attendance code
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-copy"></i> Duplicate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// AJAX to load lecturers when course is changed
$(document).ready(function() {
    // Handle course change
    $('#course_id').on('change', function() {
        var courseId = $(this).val();
        var lecturerSelect = $('#lecturer_id');
        
        if (courseId > 0) {
            // Show loading state
            lecturerSelect.html('<option value="">Loading lecturers...</option>');
            
            // Fetch lecturers for selected course
            $.ajax({
                url: '../ajax/get_lecturers.php',
                type: 'GET',
                data: { course_id: courseId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success' && response.data.length > 0) {
                        var options = '<option value="">-- Select Lecturer --</option>';
                        $.each(response.data, function(index, lecturer) {
                            options += '<option value="' + lecturer.user_id + '">' + 
                                      lecturer.first_name + ' ' + lecturer.last_name + 
                                      '</option>';
                        });
                        lecturerSelect.html(options);
                    } else {
                        lecturerSelect.html('<option value="">No lecturers assigned to this course</option>');
                    }
                },
                error: function() {
                    lecturerSelect.html('<option value="">Error loading lecturers</option>');
                }
            });
        } else {
            lecturerSelect.html('<option value="">-- Select Course First --</option>');
        }
    });
    
    // Set default end time when start time changes
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
});
</script>

<?php include 'includes/footer.php'; ?>
