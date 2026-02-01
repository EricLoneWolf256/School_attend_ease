<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. You must be an admin to access this page.';
    redirect('../index.php');
}

// Get statistics
$db = getDBConnection();
$stats = [];

// Get total counts
try {
    // Total lecturers
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'lecturer'");
    $stats['total_lecturers'] = $stmt->fetch()['count'];
    
    // Total students
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $stats['total_students'] = $stmt->fetch()['count'];
    
    // Total courses
    $stmt = $db->query("SELECT COUNT(*) as count FROM courses");
    $stats['total_courses'] = $stmt->fetch()['count'];
    
    // Today's lectures
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lectures WHERE scheduled_date = CURDATE()");
    $stmt->execute();
    $stats['today_lectures'] = $stmt->fetch()['count'];
    
    // Recent lectures
    $stmt = $db->prepare(
        "SELECT l.*, c.course_name, c.course_code, u.first_name, u.last_name 
         FROM lectures l 
         JOIN courses c ON l.course_id = c.course_id 
         JOIN users u ON l.lecturer_id = u.user_id 
         ORDER BY l.scheduled_date DESC, l.start_time DESC 
         LIMIT 5"
    );
    $stmt->execute();
    $recent_lectures = $stmt->fetchAll();
    
    // Get attendance statistics
    $stmt = $db->query("SELECT 
        COUNT(*) as total_lectures,
        SUM(attendance_count) as total_attendance,
        ROUND(AVG(attendance_count) / COUNT(DISTINCT l.lecture_id) * 100, 1) as avg_attendance_rate
        FROM (
            SELECT lecture_id, COUNT(*) as attendance_count 
            FROM attendance 
            GROUP BY lecture_id
        ) a
        JOIN lectures l ON a.lecture_id = l.lecture_id");
    $attendance_stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching statistics: ' . $e->getMessage();
}

$page_title = 'Dashboard';
include 'includes/header.php';
?>

<!-- Begin Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <!-- Title is set via $page_title in header -->
    </div>

    <!-- Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-2"></i>
                <div>
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div>
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Total Students Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card glass h-100 border-0 shadow-sm transition-all hover-lift" style="border-left: 4px solid var(--primary-color) !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color); letter-spacing: 0.5px;">
                                Total Students</div>
                            <div class="h2 mb-0 font-weight-bold text-dark"><?php echo number_format($stats['total_students']); ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="bg-primary p-3 rounded-circle" style="box-shadow: 0 4px 15px rgba(153, 0, 0, 0.3);">
                                <i class="fas fa-user-graduate fa-2x text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                    <a href="students.php" class="text-xs text-muted text-decoration-none d-flex align-items-center hover-primary">
                        View Student Records <i class="fas fa-arrow-right ms-2" style="font-size: 0.7rem;"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Total Lecturers Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card glass h-100 border-0 shadow-sm transition-all hover-lift" style="border-left: 4px solid var(--secondary-color) !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color); letter-spacing: 0.5px;">
                                Total Lecturers</div>
                            <div class="h2 mb-0 font-weight-bold text-dark"><?php echo number_format($stats['total_lecturers']); ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="bg-warning p-3 rounded-circle" style="box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);">
                                <i class="fas fa-chalkboard-teacher fa-2x text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                    <a href="lecturers.php" class="text-xs text-muted text-decoration-none d-flex align-items-center hover-secondary">
                        View Faculty Members <i class="fas fa-arrow-right ms-2" style="font-size: 0.7rem;"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Total Courses Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card glass h-100 border-0 shadow-sm transition-all hover-lift" style="border-left: 4px solid #fff !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color); letter-spacing: 0.5px;">
                                Total Courses</div>
                            <div class="h2 mb-0 font-weight-bold text-dark"><?php echo number_format($stats['total_courses']); ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="bg-white p-3 rounded-circle shadow-sm">
                                <i class="fas fa-book fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                    <a href="courses.php" class="text-xs text-muted text-decoration-none d-flex align-items-center hover-white">
                        View Course Catalog <i class="fas fa-arrow-right ms-2" style="font-size: 0.7rem;"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Today's Lectures Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card glass h-100 border-0 shadow-sm transition-all hover-lift" style="border-left: 4px solid var(--primary-color) !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--secondary-color); letter-spacing: 0.5px;">
                                Today's Lectures</div>
                            <div class="h2 mb-0 font-weight-bold text-dark"><?php echo number_format($stats['today_lectures']); ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="bg-primary p-3 rounded-circle" style="box-shadow: 0 4px 15px rgba(153, 0, 0, 0.3);">
                                <i class="fas fa-calendar-day fa-2x text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                    <a href="timetable.php" class="text-xs text-muted text-decoration-none d-flex align-items-center hover-primary">
                        View Schedule <i class="fas fa-arrow-right ms-2" style="font-size: 0.7rem;"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Upcoming Lectures -->
        <div class="col-lg-8 mb-4">
            <div class="card glass mb-4 border-0 shadow-sm">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-transparent border-bottom border-secondary border-opacity-25">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Upcoming Lectures</h6>
                    <a href="timetable.php" class="btn btn-sm btn-primary shadow-sm px-3">
                        <i class="fas fa-calendar-plus fa-sm me-2"></i> Schedule New
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_lectures)): ?>
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle" width="100%" cellspacing="0">
                                <thead class="text-secondary opacity-75" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">
                                    <tr>
                                        <th class="border-0">Course</th>
                                        <th class="border-0">Lecturer</th>
                                        <th class="border-0 text-center">Date & Time</th>
                                        <th class="border-0 text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_lectures as $lecture): ?>
                                        <tr class="border-bottom border-secondary border-opacity-10">
                                            <td class="py-3">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($lecture['course_code']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($lecture['course_name']); ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.7rem; color: var(--secondary-color);">
                                                        <?php echo strtoupper(substr($lecture['first_name'], 0, 1) . substr($lecture['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <span class="text-muted"><?php echo htmlspecialchars($lecture['first_name'] . ' ' . $lecture['last_name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="text-dark"><?php echo date('M d, Y', strtotime($lecture['scheduled_date'])); ?></div>
                                                <small class="text-secondary" style="font-size: 0.7rem;">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo date('h:i A', strtotime($lecture['start_time'])) . ' - ' . date('h:i A', strtotime($lecture['end_time'])); ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <a href="edit_lecture.php?id=<?php echo $lecture['lecture_id']; ?>" class="btn btn-sm glass border-secondary border-opacity-25 text-dark hover-primary" data-bs-toggle="tooltip" title="View & Edit Details">
                                                    <i class="fas fa-eye fa-sm"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="bg-white bg-opacity-5 rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                                <i class="fas fa-calendar-alt fa-3x text-muted opacity-25"></i>
                            </div>
                            <h5 class="text-dark mb-2">No Upcoming Lectures</h5>
                            <p class="text-muted small mb-4">Your teaching schedule is currently clear. Enjoy the break!</p>
                            <a href="timetable.php" class="btn btn-primary px-4">
                                <i class="fas fa-plus me-2"></i> Schedule a Lecture
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4">
            <!-- Attendance Overview -->
            <div class="card glass mb-4 border-0 shadow-sm">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary border-opacity-25">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Attendance Overview</h6>
                </div>
                <div class="card-body">
                    <div class="text-center position-relative mb-4">
                        <div class="mb-3 d-flex justify-content-center align-items-center" style="height: 200px;">
                            <svg viewBox="0 0 36 36" class="circular-chart" style="width: 180px;">
                                <path class="circle-bg"
                                    d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none"
                                    stroke="rgba(255,255,255,0.05)"
                                    stroke-width="2.5"
                                />
                                <path class="circle transition-all"
                                    stroke-dasharray="<?php echo ($attendance_stats['avg_attendance_rate'] ?? 0); ?>, 100"
                                    d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none"
                                    stroke="var(--primary-color)"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                />
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle text-center" style="margin-top: 10px;">
                                <h1 class="mb-0 fw-bold text-dark"><?php echo $attendance_stats['avg_attendance_rate'] ?? '0'; ?>%</h1>
                                <small class="text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Overall Rate</small>
                            </div>
                        </div>
                        <div class="row text-center mt-2 g-0">
                            <div class="col-6 border-end border-secondary border-opacity-25">
                                <h4 class="mb-0 text-dark font-weight-bold"><?php echo number_format($attendance_stats['total_lectures'] ?? 0); ?></h4>
                                <small class="text-muted" style="font-size: 0.75rem;">Total Lectures</small>
                            </div>
                            <div class="col-6">
                                <h4 class="mb-0 text-dark font-weight-bold"><?php echo number_format($attendance_stats['total_attendance'] ?? 0); ?></h4>
                                <small class="text-muted" style="font-size: 0.75rem;">Presences</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card glass mb-4 border-0 shadow-sm">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary border-opacity-25">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="timetable.php?action=add" class="d-block p-3 text-center glass rounded-3 text-decoration-none hover-lift transition-all border border-transparent hover-border-primary" style="background: rgba(153,0,0,0.1);">
                                <div class="bg-primary p-3 rounded-circle mb-2 mx-auto d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                                    <i class="fas fa-calendar-plus text-white"></i>
                                </div>
                                <h6 class="mb-0 text-white" style="font-size: 0.75rem; font-weight: 600;">Schedule</h6>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="add_course.php" class="d-block p-3 text-center glass rounded-3 text-decoration-none hover-lift transition-all border border-transparent hover-border-white" style="background: rgba(255,255,255,0.05);">
                                <div class="bg-white p-3 rounded-circle mb-2 mx-auto d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                                    <i class="fas fa-book text-primary"></i>
                                </div>
                                <h6 class="mb-0 text-white" style="font-size: 0.75rem; font-weight: 600;">Add Course</h6>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="lecturers.php?action=add" class="d-block p-3 text-center glass rounded-3 text-decoration-none hover-lift transition-all border border-transparent hover-border-secondary" style="background: rgba(255,215,0,0.05);">
                                <div class="bg-warning p-3 rounded-circle mb-2 mx-auto d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                                    <i class="fas fa-chalkboard-teacher text-white"></i>
                                </div>
                                <h6 class="mb-0 text-white" style="font-size: 0.75rem; font-weight: 600;">Add Faculty</h6>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="students.php?action=add" class="d-block p-3 text-center glass rounded-3 text-decoration-none hover-lift transition-all border border-transparent hover-border-primary" style="background: rgba(153,0,0,0.1);">
                                <div class="bg-primary p-3 rounded-circle mb-2 mx-auto d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                                    <i class="fas fa-user-plus text-white"></i>
                                </div>
                                <h6 class="mb-0 text-white" style="font-size: 0.75rem; font-weight: 600;">Add Student</h6>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom Scripts -->
<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
