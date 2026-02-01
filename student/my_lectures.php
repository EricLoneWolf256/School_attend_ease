<?php
require_once '../config.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    $_SESSION['error'] = 'Access denied. You must be a student to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();
$student_id = $_SESSION['user_id'];
$lectures = [];

try {
    $stmt = $db->prepare(
        "SELECT l.*, c.course_code, c.course_name, 
                CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         JOIN users u ON l.lecturer_id = u.user_id
         WHERE l.course_id IN (
             SELECT course_id FROM student_courses WHERE student_id = ?
         )
         ORDER BY l.scheduled_date DESC, l.start_time DESC"
    );
    $stmt->execute([$student_id]);
    $lectures = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching lectures: ' . $e->getMessage();
}

$page_title = 'My Lectures';
include 'header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">My Lectures</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Scheduled Lectures</h6>
        </div>
        <div class="card-body">
            <?php if (empty($lectures)): ?>
                <div class="alert alert-info">You are not enrolled in any courses with scheduled lectures.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="lecturesTable" width="100%" cellspacing="0">
                        <thead class="thead-dark">
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Course</th>
                                <th>Lecture Title</th>
                                <th>Lecturer</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lectures as $lecture): ?>
                                <?php
                                    $status = '';
                                    $row_class = '';
                                    $current_time = new DateTime();
                                    $start_datetime = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['start_time']);
                                    $end_datetime = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['end_time']);

                                    if ($current_time > $end_datetime) {
                                        $status = 'Finished';
                                        $row_class = 'table-secondary';
                                    } elseif ($current_time >= $start_datetime && $current_time <= $end_datetime) {
                                        $status = 'In Progress';
                                        $row_class = 'table-success';
                                    } else {
                                        $status = 'Upcoming';
                                        $row_class = 'table-info';
                                    }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo date('Y-m-d', strtotime($lecture['scheduled_date'])); ?></td>
                                    <td><?php echo date('g:i A', strtotime($lecture['start_time'])) . ' - ' . date('g:i A', strtotime($lecture['end_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($lecture['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($lecture['title']); ?></td>
                                    <td><?php echo htmlspecialchars($lecture['lecturer_name']); ?></td>
                                    <td><span class="badge badge-pill badge-dark"><?php echo $status; ?></span></td>
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
