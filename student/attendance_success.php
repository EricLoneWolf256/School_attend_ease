<?php
require_once '../config.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    $_SESSION['error'] = 'Access denied. You must be a student to access this page.';
    redirect('../index.php');
}

// Check if we have a success message in the session
if (!isset($_SESSION['success']) || empty($_SESSION['success'])) {
    redirect('dashboard.php');
}

// Get the success message and clear it from the session
$success_message = $_SESSION['success'];
unset($_SESSION['success']);

// Get lecture details if lecture_id is provided
$lecture = null;
if (isset($_GET['lecture_id']) && is_numeric($_GET['lecture_id'])) {
    $lecture_id = (int)$_GET['lecture_id'];
    $student_id = $_SESSION['user_id'];
    
    try {
        $db = getDBConnection();
        $stmt = $db->prepare(
            "SELECT a.*, l.title as lecture_title, l.scheduled_date, l.start_time, l.end_time,
                    c.course_code, c.course_name,
                    CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
             FROM attendance a
             JOIN lectures l ON a.lecture_id = l.lecture_id
             JOIN courses c ON l.course_id = c.course_id
             JOIN users u ON l.lecturer_id = u.user_id
             WHERE a.lecture_id = ? AND a.student_id = ?"
        );
        $stmt->execute([$lecture_id, $student_id]);
        $lecture = $stmt->fetch();
    } catch (PDOException $e) {
        // Ignore errors, we'll just show the success message
    }
}

$page_title = 'Attendance Confirmation';
include 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg my-5">
                <div class="card-body p-5 text-center">
                    <div class="mb-4">
                        <div class="checkmark-circle">
                            <div class="checkmark">
                                <div class="checkmark-stem"></div>
                                <div class="checkmark-kick"></div>
                            </div>
                        </div>
                    </div>
                    
                    <h2 class="text-success mb-3">Attendance Recorded</h2>
                    
                    <div class="alert alert-success" role="alert">
                        <h5 class="alert-heading"><?php echo htmlspecialchars($success_message); ?></h5>
                        <p class="mb-0">Your attendance has been successfully recorded.</p>
                    </div>
                    
                    <?php if ($lecture): ?>
                        <div class="card border-left-primary shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title text-primary">
                                    <?php echo htmlspecialchars($lecture['course_code'] . ' - ' . $lecture['lecture_title']); ?>
                                </h5>
                                <div class="row text-left">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <i class="fas fa-calendar-alt text-muted"></i> 
                                            <strong>Date:</strong> 
                                            <?php echo date('l, F j, Y', strtotime($lecture['scheduled_date'])); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="far fa-clock text-muted"></i> 
                                            <strong>Time:</strong> 
                                            <?php echo date('g:i A', strtotime($lecture['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($lecture['end_time'])); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <i class="fas fa-chalkboard-teacher text-muted"></i> 
                                            <strong>Lecturer:</strong> 
                                            <?php echo htmlspecialchars($lecture['lecturer_name']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-check-circle text-<?php echo $lecture['status'] === 'present' ? 'success' : 'warning'; ?>"></i> 
                                            <strong>Status:</strong> 
                                            <span class="badge badge-<?php echo $lecture['status'] === 'present' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($lecture['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 small text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Marked at: <?php echo date('g:i A', strtotime($lecture['marked_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="dashboard.php" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                        
                        <?php if ($lecture): ?>
                            <a href="attendance_details.php?lecture_id=<?php echo $lecture['lecture_id']; ?>" 
                               class="btn btn-outline-primary btn-lg px-4">
                                <i class="fas fa-info-circle"></i> View Details
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Next Steps Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-2">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-arrow-alt-circle-right text-primary"></i> What's Next?
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="d-flex">
                                <div class="mr-3 text-primary">
                                    <i class="fas fa-calendar-check fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="font-weight-bold mb-1">Upcoming Lectures</h6>
                                    <p class="small mb-0">Check your upcoming lectures and mark attendance when they're active.</p>
                                    <a href="my_lectures.php" class="btn btn-sm btn-outline-primary mt-2">View Schedule</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="mr-3 text-primary">
                                    <i class="fas fa-chart-bar fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="font-weight-bold mb-1">Attendance History</h6>
                                    <p class="small mb-0">View your attendance records and statistics for all courses.</p>
                                    <a href="attendance.php" class="btn btn-sm btn-outline-primary mt-2">View History</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Checkmark animation */
.checkmark-circle {
    width: 100px;
    height: 100px;
    position: relative;
    display: inline-block;
    vertical-align: top;
    margin-bottom: 20px;
}

.checkmark-circle .checkmark {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: block;
    stroke-width: 4;
    stroke: #4bb71b;
    stroke-miterlimit: 10;
    margin: 10% auto;
    box-shadow: inset 0 0 0 #4bb71b;
    animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
}

.checkmark-circle .checkmark__circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 4;
    stroke-miterlimit: 10;
    stroke: #4bb71b;
    fill: none;
    animation: stroke .6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.checkmark-circle .checkmark__check {
    transform-origin: 50% 50%;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    animation: stroke .3s cubic-bezier(0.65, 0, 0.45, 1) .8s forwards;
}

.checkmark {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: block;
    stroke-width: 6;
    stroke: #fff;
    stroke-miterlimit: 10;
    margin: 10% auto;
    background-color: #4bb71b;
    animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
}

.checkmark-stem {
    position: absolute;
    width: 28px;
    height: 56px;
    background-color: #fff;
    left: 50%;
    top: 30%;
    transform: translateX(-50%) rotate(45deg);
    border-radius: 3px;
    animation: drawStem .3s ease-out 0.7s both;
}

.checkmark-kick {
    position: absolute;
    width: 28px;
    height: 28px;
    background-color: #fff;
    left: 38%;
    top: 60%;
    transform: rotate(-45deg);
    border-radius: 3px;
    animation: drawKick .3s ease-out 1s both;
}

@keyframes drawStem {
    0% {
        height: 0;
    }
    100% {
        height: 56px;
    }
}

@keyframes drawKick {
    0% {
        width: 0;
        left: 50%;
        top: 60%;
    }
    100% {
        width: 28px;
        left: 38%;
        top: 60%;
    }
}

@keyframes stroke {
    100% {
        stroke-dashoffset: 0;
    }
}

@keyframes scale {
    0%, 100% {
        transform: none;
    }
    50% {
        transform: scale3d(1.1, 1.1, 1);
    }
}

@keyframes fill {
    100% {
        box-shadow: inset 0 0 0 100vh #4bb71b;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
