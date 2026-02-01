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
$lecture_id = isset($_GET['lecture_id']) ? (int)$_GET['lecture_id'] : 0;

// Get lecture details
$lecture = null;
$attendance_records = [];

try {
    // Get lecture details and verify ownership
    $stmt = $db->prepare(
        "SELECT l.*, c.course_code, c.course_name, 
                CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         JOIN users u ON l.lecturer_id = u.user_id
         WHERE l.lecture_id = ? AND l.lecturer_id = ?"
    );
    $stmt->execute([$lecture_id, $lecturer_id]);
    $lecture = $stmt->fetch();
    
    if ($lecture) {
        // Get attendance records for this lecture
        $stmt = $db->prepare(
            "SELECT a.*, 
                    s.user_id as student_id, 
                    IF(s.registration_number IS NOT NULL, s.registration_number, CONCAT('STU-', s.user_id)) as registration_number,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    s.email as student_email,
                    a.marked_at,
                    a.status
             FROM attendance a
             JOIN users s ON a.student_id = s.user_id
             JOIN user_courses uc ON s.user_id = uc.user_id 
                                 AND uc.course_id = :course_id 
                                 AND uc.role = 'student'
             WHERE a.lecture_id = :lecture_id
             ORDER BY student_name"
        );
        $stmt->execute([
            ':lecture_id' => $lecture_id,
            ':course_id' => $lecture['course_id']
        ]);
        $attendance_records = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    error_log("Error in view_attendance.php: " . $e->getMessage());
}

// Set page title
$page_title = 'View Attendance - ' . ($lecture ? $lecture['title'] : 'Lecture Not Found');
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php echo htmlspecialchars($lecture ? 'Attendance for ' . $lecture['title'] : 'Lecture Not Found'); ?>
        </h1>
        <a href="lectures.php" class="btn btn-secondary btn-icon-split">
            <span class="icon text-white-50">
                <i class="fas fa-arrow-left"></i>
            </span>
            <span class="text">Back to Lectures</span>
        </a>
    </div>

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

    <?php if ($lecture): ?>
        <!-- Lecture Details Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Lecture Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Course:</strong> <?php echo htmlspecialchars($lecture['course_code'] . ' - ' . $lecture['course_name']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($lecture['scheduled_date'])); ?></p>
                        <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($lecture['start_time'])) . ' - ' . date('g:i A', strtotime($lecture['end_time'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Lecturer:</strong> <?php echo htmlspecialchars($lecture['lecturer_name']); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge badge-<?php echo $lecture['is_active'] ? 'success' : 'secondary'; ?>">
                                <?php echo $lecture['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </p>
                        <?php if ($lecture['secret_code']): ?>
                            <p><strong>Attendance Code:</strong> <code><?php echo htmlspecialchars($lecture['secret_code']); ?></code></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Records Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Attendance Records</h6>
                <div>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="export_attendance.php?lecture_id=<?php echo $lecture_id; ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-file-export"></i> Export
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($attendance_records) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Marked At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_records as $index => $record): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($record['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($record['student_email']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $record['status'] === 'present' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst(htmlspecialchars($record['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $record['marked_at'] ? date('M j, Y g:i A', strtotime($record['marked_at'])) : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No attendance records found for this lecture.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            Lecture not found or you don't have permission to view it.
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<!-- Page level plugins -->
<script src="../vendor/datatables/jquery.dataTables.min.js"></script>
<script src="../vendor/datatables/dataTables.bootstrap4.min.js"></script>

<!-- Page level custom scripts -->
<script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#dataTable').DataTable({
            "pageLength": 25,
            "order": [[2, 'asc']] // Sort by student name by default
        });
    });
</script>
