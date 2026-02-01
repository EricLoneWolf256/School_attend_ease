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
$attendance_records = [];

try {
    $stmt = $db->prepare(
        "SELECT a.status, a.marked_at, 
                l.title as lecture_title, l.scheduled_date, 
                c.course_code, c.course_name,
                CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
         FROM attendance a
         JOIN lectures l ON a.lecture_id = l.lecture_id
         JOIN courses c ON l.course_id = c.course_id
         JOIN users u ON l.lecturer_id = u.user_id
         WHERE a.student_id = ?
         ORDER BY l.scheduled_date DESC, l.start_time DESC"
    );
    $stmt->execute([$student_id]);
    $attendance_records = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching attendance records: ' . $e->getMessage();
}

$page_title = 'My Attendance';
include 'header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">My Attendance History</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Records</h6>
        </div>
        <div class="card-body">
            <?php if (empty($attendance_records)): ?>
                <div class="alert alert-info">No attendance records found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="attendanceTable" width="100%" cellspacing="0">
                        <thead class="thead-light">
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Lecture</th>
                                <th>Lecturer</th>
                                <th>Status</th>
                                <th>Marked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($record['scheduled_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['course_code'] . ' - ' . $record['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['lecture_title']); ?></td>
                                    <td><?php echo htmlspecialchars($record['lecturer_name']); ?></td>
                                    <td>
                                        <?php 
                                            $status = htmlspecialchars($record['status']);
                                            $badge_class = 'badge-secondary';
                                            if ($status === 'present') {
                                                $badge_class = 'badge-success';
                                            } elseif ($status === 'late') {
                                                $badge_class = 'badge-warning';
                                            } elseif ($status === 'absent') {
                                                $badge_class = 'badge-danger';
                                            }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                    </td>
                                    <td><?php echo date('g:i:s A', strtotime($record['marked_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
