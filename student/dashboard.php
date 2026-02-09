<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    $_SESSION['error'] = 'Access denied. You must be a student to access this page.';
    if (function_exists('redirect')) {
        redirect('../index.php');
    } else {
        header("Location: ../index.php");
        exit();
    }
}

$db = getDBConnection();
$student_id = $_SESSION['user_id'];

// Get student's enrolled courses
try {
    $stmt = $db->prepare(
        "SELECT c.course_id, c.course_code, c.course_name, 
                CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
         FROM courses c
         JOIN course_assignments ca ON c.course_id = ca.course_id
         JOIN users u ON ca.lecturer_id = u.user_id
         WHERE c.course_id IN (
             SELECT course_id FROM student_courses WHERE student_id = ?
         )
         ORDER BY c.course_code"
    );
    $stmt->execute([$student_id]);
    $enrolled_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching enrolled courses: ' . $e->getMessage();
    $enrolled_courses = [];
}

// Get upcoming lectures (extended to include recent imports)
$upcoming_lectures = [];
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
         AND (l.scheduled_date >= CURDATE() - INTERVAL 1 DAY 
              OR (l.scheduled_date = CURDATE() AND l.end_time > CURTIME()))
         ORDER BY l.scheduled_date, l.start_time
         LIMIT 10"
    );
    $stmt->execute([$student_id]);
    $upcoming_lectures = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching upcoming lectures: ' . $e->getMessage();
}

// Get recent attendance
$recent_attendance = [];
try {
    $stmt = $db->prepare(
        "SELECT a.*, l.title as lecture_title, l.scheduled_date, l.start_time,
                c.course_code, c.course_name,
                CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
         FROM attendance a
         JOIN lectures l ON a.lecture_id = l.lecture_id
         JOIN courses c ON l.course_id = c.course_id
         JOIN users u ON l.lecturer_id = u.user_id
         WHERE a.student_id = ?
         ORDER BY a.marked_at DESC
         LIMIT 5"
    );
    $stmt->execute([$student_id]);
    $recent_attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching attendance records: ' . $e->getMessage();
}

$page_title = 'Student Dashboard';
include 'header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-white font-weight-bold">Student Dashboard</h1>
        <a href="mark_attendance.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-clipboard-check fa-sm text-white-50 mr-2"></i> Mark Attendance
        </a>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Enrolled Courses Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card glass h-100 border-0" style="border-left: 5px solid var(--primary-color) !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color);">
                                Enrolled Courses</div>
                            <div class="h2 mb-0 font-weight-bold text-white"><?php echo count($enrolled_courses); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x" style="color: var(--primary-color);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Attendance Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card glass h-100 border-0" style="border-left: 5px solid var(--secondary-color) !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color);">
                                Attendance Rate</div>
                            <div class="h2 mb-0 font-weight-bold text-white">
                                <?php 
                                try {
                                    $stmt = $db->prepare(
                                        "SELECT 
                                            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0) / 
                                                 NULLIF(COUNT(*), 0), 1) as attendance_rate
                                         FROM attendance a
                                         JOIN lectures l ON a.lecture_id = l.lecture_id
                                         WHERE a.student_id = ?
                                         AND l.scheduled_date <= CURDATE()"
                                    );
                                    $stmt->execute([$student_id]);
                                    $attendance = $stmt->fetch();
                                    echo $attendance && $attendance['attendance_rate'] !== null ? 
                                         htmlspecialchars($attendance['attendance_rate']) . '%' : '0%';
                                } catch (PDOException $e) {
                                    echo 'Error';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percent fa-2x" style="color: var(--secondary-color);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Lectures Card -->
        <div class="col-xl-4 col-md-6 mb-4">
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
                                         FROM lectures l
                                         WHERE l.course_id IN (
                                             SELECT course_id FROM student_courses WHERE student_id = ?
                                         )
                                         AND l.scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
                                    );
                                    $stmt->execute([$student_id]);
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
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Upcoming Lectures -->
        <div class="col-lg-6 mb-4">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Upcoming Lectures</h6>
                    <a href="my_lectures.php" class="btn btn-sm btn-outline-primary text-white">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_lectures)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                            <p class="text-muted">No upcoming lectures found.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcoming_lectures as $lecture): 
                                $lecture_date = new DateTime($lecture['scheduled_date']);
                                $now = new DateTime();
                                $is_today = $lecture_date->format('Y-m-d') === $now->format('Y-m-d');
                                $start_time = new DateTime($lecture['start_time']);
                                $end_time = new DateTime($lecture['end_time']);
                            ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($lecture['course_code'] . ': ' . $lecture['lecture_title']); ?>
                                        </h6>
                                        <small class="text-<?php echo $is_today ? 'success' : 'muted'; ?>">
                                            <?php 
                                            if ($is_today) {
                                                echo 'Today, ' . $start_time->format('g:i A');
                                            } else {
                                                echo $lecture_date->format('D, M j, Y') . ' at ' . $start_time->format('g:i A');
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <p class="mb-1">
                                        <small class="text-muted">
                                            <i class="fas fa-user-tie"></i> 
                                            <?php echo htmlspecialchars($lecture['lecturer_name']); ?>
                                        </small>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="far fa-clock"></i> 
                                            <?php echo $start_time->format('g:i A') . ' - ' . $end_time->format('g:i A'); ?>
                                        </small>
                                        <?php if ($is_today && $lecture['is_active'] && $lecture['secret_code']): ?>
                                            <a href="mark_attendance.php?lecture_id=<?php echo $lecture['lecture_id']; ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-clipboard-check"></i> Mark Attendance
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="col-lg-6 mb-4">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Recent Attendance</h6>
                    <a href="attendance.php" class="btn btn-sm btn-outline-primary text-white">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_attendance)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-white-50 mb-3 opacity-25"></i>
                            <p class="text-white-50">No attendance records found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attendance as $attendance): 
                                        $attendance_date = new DateTime($attendance['scheduled_date']);
                                    ?>
                                        <tr>
                                            <td><?php echo $attendance_date->format('M j, Y'); ?></td>
                                            <td><?php echo htmlspecialchars($attendance['course_code']); ?></td>
                                            <td>
                                                <?php if ($attendance['status'] === 'present'): ?>
                                                    <span class="badge badge-success">Present</span>
                                                <?php elseif ($attendance['status'] === 'late'): ?>
                                                    <span class="badge badge-warning">Late</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Absent</span>
                                                <?php endif; ?>
                                            </td>
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

<?php include 'footer.php'; ?>
