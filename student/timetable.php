<?php
require_once '../config.php';
require_once '../includes/session.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    $_SESSION['error'] = 'Access denied.';
    redirect('../index.php');
}

$db = getDBConnection();
$student_id = $_SESSION['user_id'];
$page_title = 'My Timetable';

// Get the current week's start and end dates
$today = new DateTime();
$day_of_week = $today->format('N'); // 1 (for Monday) through 7 (for Sunday)
$start_of_week = clone $today;
$start_of_week->modify('-' . ($day_of_week - 1) . ' days');
$end_of_week = clone $start_of_week;
$end_of_week->modify('+6 days');

$lectures_by_day = [];
try {
    $stmt = $db->prepare(
        "SELECT l.*, c.course_code, c.course_name
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         WHERE l.course_id IN (SELECT course_id FROM student_courses WHERE student_id = ?)
         AND l.scheduled_date BETWEEN ? AND ?
         ORDER BY l.scheduled_date, l.start_time"
    );
    $stmt->execute([$student_id, $start_of_week->format('Y-m-d'), $end_of_week->format('Y-m-d')]);
    $all_lectures = $stmt->fetchAll();

    // Group lectures by day
    foreach ($all_lectures as $lecture) {
        $day = date('N', strtotime($lecture['scheduled_date'])); // 1 for Monday, 7 for Sunday
        $lectures_by_day[$day][] = $lecture;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching timetable: ' . $e->getMessage();
}

include 'header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">My Weekly Timetable</h1>
    <p class="mb-4">Week of <?php echo $start_of_week->format('F j, Y'); ?> to <?php echo $end_of_week->format('F j, Y'); ?></p>

    <div class="row">
        <?php for ($i = 1; $i <= 5; $i++): // Monday to Friday ?>
            <?php
                $day_name = date('l', strtotime($start_of_week->format('Y-m-d') . ' +' . ($i - 1) . ' days'));
                $day_lectures = $lectures_by_day[$i] ?? [];
            ?>
            <div class="col-lg-12 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h6 class="m-0 font-weight-bold"><?php echo $day_name; ?></h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($day_lectures)): ?>
                            <p class="text-muted text-center">No lectures scheduled.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($day_lectures as $lecture): ?>
                                    <?php
                                        $now = new DateTime();
                                        $lecture_start = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['start_time']);
                                        $lecture_end = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['end_time']);
                                        $is_active = ($now >= $lecture_start && $now <= $lecture_end && $lecture['is_active']);
                                    ?>
                                    <a href="<?php echo $is_active ? 'mark_attendance.php?lecture_id=' . $lecture['lecture_id'] : '#'; ?>"
                                       class="list-group-item list-group-item-action <?php echo $is_active ? 'list-group-item-success' : ($now > $lecture_end ? 'list-group-item-light' : ''); ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($lecture['course_code'] . ' - ' . $lecture['title']); ?></h5>
                                            <small><?php echo date('g:i A', strtotime($lecture['start_time'])) . ' - ' . date('g:i A', strtotime($lecture['end_time'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($lecture['course_name']); ?></p>
                                        <?php if ($is_active): ?>
                                            <span class="badge badge-success">Active - Click to Mark Attendance</span>
                                        <?php elseif ($now > $lecture_end): ?>
                                            <span class="badge badge-secondary">Finished</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Upcoming</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
