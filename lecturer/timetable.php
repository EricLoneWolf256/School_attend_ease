<?php
require_once '../config.php';
require_once '../includes/session.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'lecturer') {
    $_SESSION['error'] = 'Access denied.';
    redirect('../index.php');
}

$db = getDBConnection();
$lecturer_id = $_SESSION['user_id'];
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
        "SELECT l.*, c.course_code, c.course_name,
                (SELECT COUNT(*) FROM student_courses WHERE course_id = l.course_id) as enrolled_students,
                (SELECT COUNT(*) FROM attendance WHERE lecture_id = l.lecture_id) as attendance_count
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         WHERE l.lecturer_id = ?
         AND l.scheduled_date BETWEEN ? AND ?
         ORDER BY l.scheduled_date, l.start_time"
    );
    $stmt->execute([$lecturer_id, $start_of_week->format('Y-m-d'), $end_of_week->format('Y-m-d')]);
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

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <?php 
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $day_colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'];
        
        foreach ($days as $index => $day): 
            $day_num = $index + 1;
            $current_date = clone $start_of_week;
            $current_date->modify('+' . $index . ' days');
            $is_today = $current_date->format('Y-m-d') === $today->format('Y-m-d');
        ?>
            <div class="col-lg-12 mb-4">
                <div class="card shadow" style="border-left: 4px solid <?php echo $day_colors[$index]; ?>;">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color: <?php echo $day_colors[$index]; ?>;">
                        <h6 class="m-0 font-weight-bold text-white">
                            <?php echo $day; ?> 
                            <?php if ($is_today): ?>
                                <span class="badge badge-warning">Today</span>
                            <?php endif; ?>
                        </h6>
                        <small class="text-white"><?php echo $current_date->format('M j, Y'); ?></small>
                    </div>
                    <div class="card-body">
                        <?php if (isset($lectures_by_day[$day_num]) && !empty($lectures_by_day[$day_num])): ?>
                            <?php foreach ($lectures_by_day[$day_num] as $lecture): ?>
                                <div class="card mb-3 border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    <?php echo htmlspecialchars($lecture['course_code']); ?>
                                                </div>
                                                <div class="h6 mb-0 font-weight-bold text-gray-800">
                                                    <?php echo htmlspecialchars($lecture['title']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <i class="fas fa-clock fa-sm text-gray-400"></i>
                                                    <?php echo date('g:i A', strtotime($lecture['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($lecture['end_time'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <i class="fas fa-users fa-sm text-gray-400"></i>
                                                    <?php echo $lecture['enrolled_students']; ?> enrolled
                                                    <?php if ($lecture['attendance_count'] > 0): ?>
                                                        | <?php echo $lecture['attendance_count']; ?> attended
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <?php if ($lecture['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                    <div class="mt-1">
                                                        <small class="text-muted">Code: <strong><?php echo htmlspecialchars($lecture['secret_code']); ?></strong></small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge badge-info">Scheduled</span>
                                                <?php endif; ?>
                                                
                                                <div class="mt-2">
                                                    <a href="lecture.php?id=<?php echo $lecture['lecture_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_lecture.php?id=<?php echo $lecture['lecture_id']; ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No lectures scheduled for <?php echo $day; ?>.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
