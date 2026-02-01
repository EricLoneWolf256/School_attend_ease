<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/db_connection.php';

// Check if user is logged in and has the right role
if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'lecturer'])) {
    $_SESSION['error'] = 'Access denied. You must be a lecturer to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();
$lecturer_id = $_SESSION['user_id'];
$lectures = [];
$attendance_records = [];
$selected_lecture = null;

// Get lecturer's assigned courses and lectures
try {
    // Get all lectures for this lecturer
    $stmt = $db->prepare(
        "SELECT l.lecture_id, l.title, l.scheduled_date, l.start_time, l.end_time,
                c.course_code, c.course_name, c.course_id
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         WHERE l.lecturer_id = ?
         ORDER BY l.scheduled_date DESC, l.start_time DESC"
    );
    $stmt->execute([$lecturer_id]);
    $lectures = $stmt->fetchAll();
    
    // Get attendance for a specific lecture if selected
    if (isset($_GET['lecture_id']) && is_numeric($_GET['lecture_id'])) {
        $lecture_id = (int)$_GET['lecture_id'];
        
        // Get lecture details
        $stmt = $db->prepare(
            "SELECT l.*, c.course_code, c.course_name, 
                    CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
             FROM lectures l
             JOIN courses c ON l.course_id = c.course_id
             JOIN users u ON l.lecturer_id = u.user_id
             WHERE l.lecture_id = ? AND l.lecturer_id = ?"
        );
        $stmt->execute([$lecture_id, $lecturer_id]);
        $selected_lecture = $stmt->fetch();
        
        if ($selected_lecture) {
            // Get attendance records for this lecture
            $stmt = $db->prepare(
                "SELECT a.*, 
                        s.user_id as student_id, 
                        IF(s.registration_number IS NOT NULL, s.registration_number, CONCAT('STU-', s.user_id)) as registration_number,
                        CONCAT(s.first_name, ' ', s.last_name) as student_name,
                        s.email as student_email
                 FROM attendance a
                 JOIN users s ON a.student_id = s.user_id
                 JOIN user_courses uc ON s.user_id = uc.user_id AND uc.course_id = :course_id AND uc.role = 'student'
                 WHERE a.lecture_id = :lecture_id
                 ORDER BY IF(s.registration_number IS NOT NULL, s.registration_number, CONCAT('STU-', s.user_id))"
            );
            $stmt->execute([
                ':lecture_id' => $lecture_id,
                ':course_id' => $selected_lecture['course_id']
            ]);
            $attendance_records = $stmt->fetchAll();
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

$page_title = 'Manage Attendance';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Attendance</h1>
        <?php if ($selected_lecture): ?>
            <a href="attendance.php" class="btn btn-secondary btn-icon-split">
                <span class="icon text-white-50">
                    <i class="fas fa-arrow-left"></i>
                </span>
                <span class="text">Back to All Lectures</span>
            </a>
        <?php endif; ?>
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

    <?php if ($selected_lecture): ?>
        <!-- Attendance for a specific lecture -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <?php echo htmlspecialchars($selected_lecture['title']); ?>
                </h6>
                <div>
                    <span class="badge badge-info">
                        <?php echo htmlspecialchars($selected_lecture['course_code'] . ' - ' . $selected_lecture['course_name']); ?>
                    </span>
                    <span class="badge badge-secondary ml-2">
                        <?php echo date('F j, Y', strtotime($selected_lecture['scheduled_date'])); ?>
                    </span>
                    <span class="badge badge-secondary ml-2">
                        <?php echo date('g:i A', strtotime($selected_lecture['start_time'])) . ' - ' . date('g:i A', strtotime($selected_lecture['end_time'])); ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($attendance_records)): ?>
                    <div class="alert alert-info">No students are enrolled in this course.</div>
                <?php else: ?>
                    <form method="post" action="update_attendance.php" id="attendanceForm">
                        <input type="hidden" name="lecture_id" value="<?php echo $selected_lecture['lecture_id']; ?>">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="attendanceTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Status</th>
                                        <th>Marked At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $index => $record): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($record['registration_number']); ?></td>
                                            <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $record['status'] === 'present' ? 'badge-success' : 
                                                         ($record['status'] === 'late' ? 'badge-warning' : 'badge-danger'); 
                                                ?>">
                                                    <?php echo ucfirst($record['status'] ?? 'absent'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $record['marked_at'] ? date('M j, Y g:i A', strtotime($record['marked_at'])) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-attendance" 
                                                        data-student-id="<?php echo $record['student_id']; ?>"
                                                        data-lecture-id="<?php echo $selected_lecture['lecture_id']; ?>"
                                                        data-status="<?php echo $record['status'] ?? 'absent'; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- List of all lectures -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">My Lectures</h6>
            </div>
            <div class="card-body">
                <?php if (empty($lectures)): ?>
                    <div class="alert alert-info">No lectures found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="lecturesTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Course</th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lectures as $index => $lecture): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($lecture['course_code']); ?></strong>
                                            <div class="text-muted small"><?php echo htmlspecialchars($lecture['course_name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($lecture['title']); ?></td>
                                        <td><?php echo date('F j, Y', strtotime($lecture['scheduled_date'])); ?></td>
                                        <td>
                                            <?php echo date('g:i A', strtotime($lecture['start_time'])) . ' - ' . date('g:i A', strtotime($lecture['end_time'])); ?>
                                        </td>
                                        <td>
                                            <a href="attendance.php?lecture_id=<?php echo $lecture['lecture_id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-clipboard-check"></i> Take Attendance
                                            </a>
                                            <a href="view_attendance.php?lecture_id=<?php echo $lecture['lecture_id']; ?>" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="editAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAttendanceModalLabel">Update Attendance</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="updateAttendanceForm" action="update_attendance.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="student_id" id="editStudentId">
                    <input type="hidden" name="lecture_id" id="editLectureId">
                    
                    <div class="form-group">
                        <label for="attendanceStatus">Status</label>
                        <select class="form-control" id="attendanceStatus" name="status" required>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                            <option value="excused">Excused</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any notes about this attendance record"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    if ($.fn.DataTable.isDataTable('#lecturesTable')) {
        $('#lecturesTable').DataTable().destroy();
    }
    
    $('#lecturesTable').DataTable({
        "order": [[3, "desc"], [4, "desc"]], // Sort by date and time
        "pageLength": 10,
        "responsive": true
    });
    
    if ($.fn.DataTable.isDataTable('#attendanceTable')) {
        $('#attendanceTable').DataTable().destroy();
    }
    
    $('#attendanceTable').DataTable({
        "order": [[1, "asc"]], // Sort by student ID
        "pageLength": 25,
        "responsive": true
    });
    
    // Handle edit attendance button click
    $('.edit-attendance').on('click', function() {
        var studentId = $(this).data('student-id');
        var lectureId = $(this).data('lecture-id');
        var status = $(this).data('status');
        
        $('#editStudentId').val(studentId);
        $('#editLectureId').val(lectureId);
        $('#attendanceStatus').val(status);
        
        $('#editAttendanceModal').modal('show');
    });
    
    // Handle form submission
    $('#updateAttendanceForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'update_attendance.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    var alertHtml = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                                  response.message +
                                  '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                                  '<span aria-hidden="true">&times;</span></button></div>';
                    $('.container-fluid').prepend(alertHtml);
                    
                    // Close the modal
                    $('#editAttendanceModal').modal('hide');
                    
                    // Reload the page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Show error message
                    alert('Error: ' + (response.message || 'Failed to update attendance.'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('An error occurred while updating attendance. Please try again.');
            }
        });
    });
});
</script>
