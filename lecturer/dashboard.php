<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is a lecturer
if (!isLoggedIn() || $_SESSION['role'] !== 'lecturer') {
    $_SESSION['error'] = 'Access denied. You must be a lecturer to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();
$lecturer_id = $_SESSION['user_id'];

// Get lecturer's assigned courses
try {
    $stmt = $db->prepare(
        "SELECT c.course_id, c.course_code, c.course_name, 
                (SELECT COUNT(*) FROM student_courses WHERE course_id = c.course_id) as student_count
         FROM courses c
         JOIN course_assignments ca ON c.course_id = ca.course_id
         WHERE ca.lecturer_id = ?
         ORDER BY c.course_code"
    );
    $stmt->execute([$lecturer_id]);
    $assigned_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching assigned courses: ' . $e->getMessage();
    $assigned_courses = [];
}

// Get today's lectures (enhanced to include recent imports)
try {
    $stmt = $db->prepare(
        "SELECT l.*, c.course_code, c.course_name,
                (SELECT COUNT(*) FROM attendance WHERE lecture_id = l.lecture_id) as attendance_count,
                (SELECT COUNT(*) FROM student_courses WHERE course_id = l.course_id) as total_students
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         WHERE l.lecturer_id = ?
         AND (l.scheduled_date = CURDATE() 
              OR l.scheduled_date >= CURDATE() - INTERVAL 1 DAY)
         ORDER BY l.scheduled_date, l.start_time
         LIMIT 10"
    );
    $stmt->execute([$lecturer_id]);
    $todays_lectures = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching today\'s lectures: ' . $e->getMessage();
    $todays_lectures = [];
}

// Get upcoming lectures (next 7 days, enhanced to include recent imports)
try {
    $stmt = $db->prepare(
        "SELECT l.*, c.course_code, c.course_name,
                (SELECT COUNT(*) FROM attendance WHERE lecture_id = l.lecture_id) as attendance_count,
                (SELECT COUNT(*) FROM student_courses WHERE course_id = l.course_id) as total_students
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         WHERE l.lecturer_id = ?
         AND l.scheduled_date >= CURDATE()
         ORDER BY l.scheduled_date, l.start_time
         LIMIT 10"
    );
    $stmt->execute([$lecturer_id]);
    $upcoming_lectures = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching upcoming lectures: ' . $e->getMessage();
    $upcoming_lectures = [];
}

// Get recent attendance activity
try {
    $stmt = $db->prepare(
        "SELECT a.*, l.title as lecture_title, l.scheduled_date, l.start_time,
                c.course_code, c.course_name,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.user_id as registration_number
         FROM attendance a
         JOIN lectures l ON a.lecture_id = l.lecture_id
         JOIN courses c ON l.course_id = c.course_id
         JOIN users s ON a.student_id = s.user_id
         WHERE l.lecturer_id = ?
         ORDER BY a.marked_at DESC
         LIMIT 5"
    );
    $stmt->execute([$lecturer_id]);
    $recent_attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching recent attendance: ' . $e->getMessage();
    $recent_attendance = [];
}

$page_title = 'Lecturer Dashboard';
include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-white font-weight-bold">Lecturer Dashboard</h1>
        <div>
            <a href="lectures.php" class="btn btn-primary shadow-sm">
                <i class="fas fa-calendar-plus fa-sm text-white-50 mr-2"></i> Schedule Lecture
            </a>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Assigned Courses Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card glass h-100 border-0" style="border-left: 5px solid var(--primary-color) !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color);">
                                Assigned Courses</div>
                            <div class="h2 mb-0 font-weight-bold text-white"><?php echo count($assigned_courses); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x" style="color: var(--primary-color);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Lectures Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card glass h-100 border-0" style="border-left: 5px solid var(--secondary-color) !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color);">
                                Today's Lectures</div>
                            <div class="h2 mb-0 font-weight-bold text-white"><?php echo count($todays_lectures); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x" style="color: var(--secondary-color);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Lectures Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card glass h-100 border-0" style="border-left: 5px solid #fff !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color);">
                                Upcoming (7 Days)</div>
                            <div class="h2 mb-0 font-weight-bold text-white">
                                <?php 
                                try {
                                    $stmt = $db->prepare(
                                        "SELECT COUNT(*) as count
                                         FROM lectures
                                         WHERE lecturer_id = ?
                                         AND scheduled_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
                                         AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
                                    );
                                    $stmt->execute([$lecturer_id]);
                                    $upcoming = $stmt->fetch();
                                    echo $upcoming ? $upcoming['count'] : 0;
                                } catch (PDOException $e) {
                                    echo 'Error';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Students Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card glass h-100 border-0" style="border-left: 5px solid var(--primary-color) !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color);">
                                Total Students</div>
                            <div class="h2 mb-0 font-weight-bold text-white">
                                <?php 
                                try {
                                    $stmt = $db->prepare(
                                        "SELECT COUNT(DISTINCT sc.student_id) as total_students
                                         FROM student_courses sc
                                         JOIN course_assignments ca ON sc.course_id = ca.course_id
                                         WHERE ca.lecturer_id = ?"
                                    );
                                    $stmt->execute([$lecturer_id]);
                                    $result = $stmt->fetch();
                                    echo $result ? $result['total_students'] : 0;
                                } catch (PDOException $e) {
                                    echo 'Error';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x" style="color: var(--primary-color);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Today's Lectures -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Today's Lectures</h6>
                    <a href="lectures.php?filter=today" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($todays_lectures)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                            <p class="text-muted">No lectures scheduled for today.</p>
                            <a href="lectures.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Schedule a Lecture
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($todays_lectures as $lecture): 
                                $start_time = new DateTime($lecture['start_time']);
                                $end_time = new DateTime($lecture['end_time']);
                                $now = new DateTime();
                                $is_active = $now >= $start_time && $now <= $end_time;
                                $attendance_rate = $lecture['total_students'] > 0 ? 
                                    round(($lecture['attendance_count'] / $lecture['total_students']) * 100) : 0;
                            ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($lecture['course_code'] . ': ' . $lecture['title']); ?>
                                            <?php if ($is_active && $lecture['is_active']): ?>
                                                <span class="badge badge-success">Active Now</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo $start_time->format('g:i A') . ' - ' . $end_time->format('g:i A'); ?>
                                        </small>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <div class="progress flex-grow-1 mr-3" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $attendance_rate >= 70 ? 'success' : ($attendance_rate >= 40 ? 'warning' : 'danger'); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $attendance_rate; ?>%" 
                                                 aria-valuenow="<?php echo $attendance_rate; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $attendance_rate; ?>%
                                            </div>
                                        </div>
                                        <div class="text-nowrap">
                                            <small class="text-muted">
                                                <?php echo $lecture['attendance_count']; ?>/<?php echo $lecture['total_students']; ?> students
                                            </small>
                                        </div>
                                    </div>
                                    <div class="mt-2 d-flex justify-content-between">
                                        <a href="lecture.php?id=<?php echo $lecture['lecture_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($is_active): ?>
                                            <?php if ($lecture['is_active'] && !empty($lecture['secret_code'])): ?>
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        data-bs-toggle="modal" data-bs-target="#attendanceCodeModal"
                                                        data-code="<?php echo htmlspecialchars($lecture['secret_code']); ?>">
                                                    <i class="fas fa-key"></i> Show Code
                                                </button>
                                            <?php else: ?>
                                                <form action="lecture_action.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="lecture_id" value="<?php echo $lecture['lecture_id']; ?>">
                                                    <button type="submit" name="generate_code" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-key"></i> Start Attendance
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Lectures -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Upcoming Lectures</h6>
                    <a href="lectures.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_lectures)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-plus fa-3x text-gray-300 mb-3"></i>
                            <p class="text-muted">No upcoming lectures in the next 7 days.</p>
                            <a href="lectures.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Schedule a Lecture
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcoming_lectures as $lecture): 
                                $lecture_date = new DateTime($lecture['scheduled_date']);
                                $start_time = new DateTime($lecture['start_time']);
                                $end_time = new DateTime($lecture['end_time']);
                                $now = new DateTime();
                                $is_today = $lecture_date->format('Y-m-d') === $now->format('Y-m-d');
                            ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($lecture['course_code'] . ': ' . $lecture['title']); ?>
                                        </h6>
                                        <small class="text-<?php echo $is_today ? 'primary' : 'muted'; ?>">
                                            <?php 
                                            if ($is_today) {
                                                echo 'Today, ' . $start_time->format('g:i A');
                                            } else {
                                                echo $lecture_date->format('D, M j') . ' at ' . $start_time->format('g:i A');
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <p class="mb-1 small">
                                        <i class="far fa-clock text-muted"></i> 
                                        <?php echo $start_time->format('g:i A') . ' - ' . $end_time->format('g:i A'); ?>
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-users text-muted"></i> 
                                        <?php echo $lecture['total_students']; ?> students
                                    </p>
                                    <div class="mt-2">
                                        <a href="lecture.php?id=<?php echo $lecture['lecture_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="lectures.php?action=edit&id=<?php echo $lecture['lecture_id']; ?>" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Recent Attendance Activity</h6>
                    <a href="attendance.php" class="btn btn-sm btn-outline-primary text-white">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_attendance)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-4x text-white-50 mb-3 opacity-25"></i>
                            <p class="text-white-50">No recent attendance activity.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Lecture</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attendance as $attendance): 
                                        $attendance_date = new DateTime($attendance['scheduled_date']);
                                    ?>
                                        <tr>
                                            <td><?php echo $attendance_date->format('M j, Y'); ?></td>
                                            <td>
                                                <div class="font-weight-bold text-white"><?php echo htmlspecialchars($attendance['student_name']); ?></div>
                                                <div class="small text-white-50"><?php echo htmlspecialchars($attendance['registration_number']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($attendance['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($attendance['lecture_title']); ?></td>
                                            <td>
                                                <?php if ($attendance['status'] === 'present'): ?>
                                                    <span class="badge badge-success">Present</span>
                                                <?php elseif ($attendance['status'] === 'late'): ?>
                                                    <span class="badge badge-warning">Late</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Absent</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('g:i A', strtotime($attendance['marked_at'])); ?></td>
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

<!-- Attendance Code Modal -->
<div class="modal fade" id="attendanceCodeModal" tabindex="-1" role="dialog" aria-labelledby="attendanceCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="attendanceCodeModalLabel">Attendance Code</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="lead">Share this code with your students:</p>
                <div class="display-3 font-weight-bold text-primary mb-4" id="attendanceCodeDisplay">A1B2C3</div>
                <p class="text-muted small">
                    <i class="far fa-clock"></i> 
                    Code will expire when the lecture ends or when you stop attendance.
                </p>
                <button class="btn btn-outline-primary btn-sm" onclick="copyToClipboard()">
                    <i class="fas fa-copy"></i> Copy to Clipboard
                </button>
            </div>
            <div class="modal-footer
                <form action="lecture_action.php" method="POST" class="w-100">
                    <input type="hidden" name="lecture_id" id="stopAttendanceLectureId" value="">
                    <button type="submit" name="stop_attendance" class="btn btn-danger btn-block">
                        <i class="fas fa-stop-circle"></i> Stop Attendance
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Handle attendance code modal
$('#attendanceCodeModal').on('show.bs.modal', function (event) {
    const button = $(event.relatedTarget);
    const code = button.data('code');
    const modal = $(this);
    modal.find('#attendanceCodeDisplay').text(code);
    
    // Set the lecture ID for the stop attendance form
    const lectureId = button.closest('.list-group-item').find('form input[name="lecture_id"]').val();
    modal.find('#stopAttendanceLectureId').val(lectureId);
    
    // Start auto-refresh for the code (in case it changes)
    const refreshInterval = setInterval(function() {
        // In a real app, you might want to check with the server if the code is still valid
        // For now, we'll just keep showing the same code
    }, 30000); // Check every 30 seconds
    
    // Clear the interval when the modal is closed
    modal.on('hidden.bs.modal', function () {
        clearInterval(refreshInterval);
    });
});

// Copy code to clipboard
function copyToClipboard() {
    const code = document.getElementById('attendanceCodeDisplay').textContent;
    navigator.clipboard.writeText(code).then(function() {
        // Show success message
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Copied!';
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success');
        
        // Reset button after 2 seconds
        setTimeout(function() {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
        }, 2000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy code. Please select and copy it manually.');
    });
}

// Initialize tooltips
$(function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
/* Custom styles for the attendance code display */
#attendanceCodeDisplay {
    letter-spacing: 0.5rem;
    text-align: center;
    padding: 1rem;
    background-color: #f8f9fc;
    border-radius: 0.35rem;
    border: 1px dashed #d1d3e2;
    margin: 1rem 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .progress {
        margin-bottom: 0.5rem;
    }
    
    .list-group-item {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
