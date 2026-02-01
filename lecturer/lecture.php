<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is a lecturer
if (!isLoggedIn() || $_SESSION['role'] !== 'lecturer') {
    $_SESSION['error'] = 'Access denied. You must be a lecturer to access this page.';
    redirect('../index.php');
}

// Check if lecture ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid lecture ID.';
    redirect('lectures.php');
}

$lecture_id = (int)$_GET['id'];
$lecturer_id = $_SESSION['user_id'];
$db = getDBConnection();

// Get lecture details
$lecture = getLectureDetails($db, $lecture_id, $lecturer_id);
if (!$lecture) {
    $_SESSION['error'] = 'Lecture not found or access denied.';
    redirect('lectures.php');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_code'])) {
        handleGenerateCode($db, $lecture_id, $lecturer_id);
    } elseif (isset($_POST['stop_attendance'])) {
        handleStopAttendance($db, $lecture_id, $lecturer_id);
    } elseif (isset($_POST['export_attendance'])) {
        handleExportAttendance($db, $lecture_id, $lecturer_id);
    } elseif (isset($_POST['mark_attendance'])) {
        handleManualAttendance($db, $lecture_id, $lecturer_id);
    }
}

// Get attendance statistics
$attendance_stats = getAttendanceStats($db, $lecture_id);

// Get attendance list
$attendance_list = getAttendanceList($db, $lecture_id, $lecture['course_id']);

// Set page title
$page_title = 'Lecture: ' . htmlspecialchars($lecture['title']);
include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php echo htmlspecialchars($lecture['course_code'] . ': ' . $lecture['title']); ?>
            <?php if ($lecture['is_active']): ?>
                <span class="badge badge-success">Active</span>
            <?php elseif (isLectureInProgress($lecture)): ?>
                <span class="badge badge-warning">In Progress</span>
            <?php elseif (isLectureComplete($lecture)): ?>
                <span class="badge badge-secondary">Completed</span>
            <?php else: ?>
                <span class="badge badge-info">Scheduled</span>
            <?php endif; ?>
        </h1>
        <div>
            <a href="lectures.php" class="btn btn-secondary btn-icon-split">
                <span class="icon text-white-50">
                    <i class="fas fa-arrow-left"></i>
                </span>
                <span class="text">Back to Lectures</span>
            </a>
            <a href="lectures.php?action=edit&id=<?php echo $lecture_id; ?>" 
               class="btn btn-primary btn-icon-split">
                <span class="icon text-white-50">
                    <i class="fas fa-edit"></i>
                </span>
                <span class="text">Edit</span>
            </a>
        </div>
    </div>

    <!-- Lecture Details -->
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Lecture Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Lecture Information</h6>
                    <div class="dropdown no-arrow
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" 
                           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" 
                             aria-labelledby="dropdownMenuLink">
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#duplicateLectureModal">
                                <i class="fas fa-copy fa-sm fa-fw mr-2 text-gray-400"></i>
                                Duplicate Lecture
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#deleteLectureModal">
                                <i class="fas fa-trash-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                Delete Lecture
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="font-weight-bold">Course</h5>
                            <p>
                                <?php echo htmlspecialchars($lecture['course_code'] . ' - ' . $lecture['course_name']); ?>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-chalkboard-teacher"></i> 
                                    <?php echo htmlspecialchars($lecture['lecturer_name']); ?>
                                </small>
                            </p>
                            
                            <h5 class="font-weight-bold mt-4">Date & Time</h5>
                            <p>
                                <i class="far fa-calendar-alt text-primary"></i> 
                                <?php echo date('l, F j, Y', strtotime($lecture['scheduled_date'])); ?>
                                <br>
                                <i class="far fa-clock text-primary"></i> 
                                <?php echo date('g:i A', strtotime($lecture['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($lecture['end_time'])); ?>
                                <span class="text-muted">
                                    (<?php echo getDuration($lecture['start_time'], $lecture['end_time']); ?>)
                                </span>
                            </p>
                            
                            <?php if (!empty($lecture['location'])): ?>
                                <h5 class="font-weight-bold mt-4">Location</h5>
                                <p>
                                    <i class="fas fa-map-marker-alt text-primary"></i> 
                                    <?php echo htmlspecialchars($lecture['location']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h5 class="font-weight-bold">Description</h5>
                            <?php if (!empty($lecture['description'])): ?>
                                <p class="text-justify"><?php echo nl2br(htmlspecialchars($lecture['description'])); ?></p>
                            <?php else: ?>
                                <p class="text-muted font-italic">No description provided.</p>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <h5 class="font-weight-bold">Attendance Code</h5>
                                <?php if ($lecture['is_active'] && !empty($lecture['secret_code'])): ?>
                                    <div class="alert alert-success">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Active Code:</strong>
                                                <span class="h4 ml-2"><?php echo $lecture['secret_code']; ?></span>
                                                <div class="small text-muted mt-1">
                                                    Generated at <?php echo date('g:i A', strtotime($lecture['code_generated_at'])); ?>
                                                </div>
                                            </div>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="stop_attendance" value="1">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-stop-circle"></i> Stop
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <p class="small text-muted">
                                            <i class="fas fa-info-circle"></i> 
                                            Share this code with students to mark their attendance.
                                        </p>
                                        <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?php echo $lecture['secret_code']; ?>')">
                                            <i class="fas fa-copy"></i> Copy Code
                                        </button>
                                        <a href="#" class="btn btn-sm btn-outline-success" data-toggle="modal" data-target="#qrCodeModal">
                                            <i class="fas fa-qrcode"></i> Show QR Code
                                        </a>
                                    </div>
                                <?php elseif (isLectureInProgress($lecture)): ?>
                                    <form method="post" class="mt-2">
                                        <input type="hidden" name="generate_code" value="1">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-key"></i> Generate Attendance Code
                                        </button>
                                        <p class="small text-muted mt-2">
                                            <i class="fas fa-info-circle"></i> 
                                            Generate a code for students to mark their attendance.
                                        </p>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-secondary">
                                        <i class="fas fa-info-circle"></i> 
                                        <?php if (isLectureComplete($lecture)): ?>
                                            This lecture has ended. No attendance code can be generated.
                                        <?php else: ?>
                                            Attendance code will be available when the lecture starts.
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Attendance List</h6>
                    <div>
                        <form method="post" class="d-inline" id="exportForm">
                            <input type="hidden" name="export_attendance" value="1">
                            <input type="hidden" name="format" id="exportFormat" value="csv">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-primary dropdown-toggle" 
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-download"></i> Export
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <button class="dropdown-item" type="submit" onclick="setExportFormat('csv')">
                                        <i class="fas fa-file-csv text-primary mr-2"></i>Export as CSV
                                    </button>
                                    <button class="dropdown-item" type="submit" onclick="setExportFormat('pdf')">
                                        <i class="fas fa-file-pdf text-danger mr-2"></i>Export as PDF
                                    </button>
                                    <button class="dropdown-item" type="submit" onclick="setExportFormat('excel')">
                                        <i class="fas fa-file-excel text-success mr-2"></i>Export as Excel
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <?php if (isLectureInProgress($lecture) || $lecture['is_active']): ?>
                            <button class="btn btn-sm btn-success ml-2" data-toggle="modal" data-target="#manualAttendanceModal">
                                <i class="fas fa-user-edit"></i> Mark Attendance
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="attendanceTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attendance_list)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-user-slash fa-3x text-gray-300 mb-3"></i>
                                            <p class="text-muted">No attendance records found.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($attendance_list as $index => $record): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($record['student_name']); ?>
                                                <?php if ($record['is_late']): ?>
                                                    <span class="badge badge-warning ml-1">Late</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['student_number']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $record['status'] === 'present' ? 'success' : 'secondary'; 
                                                ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($record['marked_at']): ?>
                                                    <?php echo date('M j, g:i A', strtotime($record['marked_at'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['attendance_code']): ?>
                                                    <code><?php echo $record['attendance_code']; ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($record['status'] === 'present'): ?>
                                                        <form method="post" class="d-inline" 
                                                              onsubmit="return confirm('Mark this student as absent? This cannot be undone.');">
                                                            <input type="hidden" name="mark_attendance" value="1">
                                                            <input type="hidden" name="student_id" value="<?php echo $record['student_id']; ?>">
                                                            <input type="hidden" name="status" value="absent">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Mark as Absent">
                                                                <i class="fas fa-user-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" class="d-inline"
                                                              onsubmit="return confirm('Mark this student as present?');">
                                                            <input type="hidden" name="mark_attendance" value="1">
                                                            <input type="hidden" name="student_id" value="<?php echo $record['student_id']; ?>">
                                                            <input type="hidden" name="status" value="present">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as Present">
                                                                <i class="fas fa-user-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <a href="student_attendance.php?student_id=<?php echo $record['student_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View Student History">
                                                        <i class="fas fa-history"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Attendance Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Attendance Summary</h6>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Present</span>
                            <span class="font-weight-bold">
                                <?php echo $attendance_stats['present_count']; ?> / <?php echo $attendance_stats['total_students']; ?>
                                (<?php echo $attendance_stats['present_percentage']; ?>%)
                            </span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $attendance_stats['present_percentage']; ?>%" 
                                 aria-valuenow="<?php echo $attendance_stats['present_percentage']; ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Late</span>
                            <span class="font-weight-bold">
                                <?php echo $attendance_stats['late_count']; ?> / <?php echo $attendance_stats['total_students']; ?>
                                (<?php echo $attendance_stats['late_percentage']; ?>%)
                            </span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" role="progressbar" 
                                 style="width: <?php echo $attendance_stats['late_percentage']; ?>%" 
                                 aria-valuenow="<?php echo $attendance_stats['late_percentage']; ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Absent</span>
                            <span class="font-weight-bold">
                                <?php echo $attendance_stats['absent_count']; ?> / <?php echo $attendance_stats['total_students']; ?>
                                (<?php echo $attendance_stats['absent_percentage']; ?>%)
                            </span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-danger" role="progressbar" 
                                 style="width: <?php echo $attendance_stats['absent_percentage']; ?>%" 
                                 aria-valuenow="<?php echo $attendance_stats['absent_percentage']; ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-top pt-3">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h5 font-weight-bold text-success">
                                    <?php echo $attendance_stats['present_count']; ?>
                                </div>
                                <div class="text-muted small">Present</div>
                            </div>
                            <div class="col-4">
                                <div class="h5 font-weight-bold text-warning">
                                    <?php echo $attendance_stats['late_count']; ?>
                                </div>
                                <div class="text-muted small">Late</div>
                            </div>
                            <div class="col-4">
                                <div class="h5 font-weight-bold text-danger">
                                    <?php echo $attendance_stats['absent_count']; ?>
                                </div>
                                <div class="text-muted small">Absent</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Lecture Status -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Lecture Status</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-calendar-day text-primary mr-2"></i>
                                <span>Date</span>
                            </div>
                            <span class="font-weight-bold">
                                <?php echo date('M j, Y', strtotime($lecture['scheduled_date'])); ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="far fa-clock text-primary mr-2"></i>
                                <span>Time</span>
                            </div>
                            <span class="font-weight-bold">
                                <?php echo date('g:i A', strtotime($lecture['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($lecture['end_time'])); ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-users text-primary mr-2"></i>
                                <span>Enrolled Students</span>
                            </div>
                            <span class="font-weight-bold">
                                <?php echo $attendance_stats['total_students']; ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-user-check text-success mr-2"></i>
                                <span>Attendance Taken</span>
                            </div>
                            <span class="font-weight-bold">
                                <?php echo $attendance_stats['attendance_taken'] ? 'Yes' : 'No'; ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-chart-line text-primary mr-2"></i>
                                <span>Attendance Rate</span>
                            </div>
                            <span class="font-weight-bold">
                                <?php echo $attendance_stats['attendance_rate']; ?>%
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($lecture['is_active'] && !empty($lecture['secret_code'])): ?>
                            <form method="post" class="d-grid">
                                <input type="hidden" name="stop_attendance" value="1">
                                <button type="submit" class="btn btn-danger btn-block mb-2">
                                    <i class="fas fa-stop-circle"></i> Stop Attendance
                                </button>
                            </form>
                            
                            <button class="btn btn-primary btn-block mb-2" 
                                    onclick="copyToClipboard('<?php echo $lecture['secret_code']; ?>')">
                                <i class="fas fa-copy"></i> Copy Code
                            </button>
                            
                            <button class="btn btn-info btn-block mb-2" data-toggle="modal" data-target="#qrCodeModal">
                                <i class="fas fa-qrcode"></i> Show QR Code
                            </button>
                            
                        <?php elseif (isLectureInProgress($lecture)): ?>
                            <form method="post" class="d-grid">
                                <input type="hidden" name="generate_code" value="1">
                                <button type="submit" class="btn btn-success btn-block mb-2">
                                    <i class="fas fa-key"></i> Generate Code
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <a href="lectures.php?action=edit&id=<?php echo $lecture_id; ?>" 
                           class="btn btn-primary btn-block mb-2">
                            <i class="fas fa-edit"></i> Edit Lecture
                        </a>
                        
                        <button class="btn btn-warning btn-block mb-2" data-toggle="modal" data-target="#duplicateLectureModal">
                            <i class="fas fa-copy"></i> Duplicate Lecture
                        </button>
                        
                        <button class="btn btn-outline-danger btn-block" data-toggle="modal" data-target="#deleteLectureModal">
                            <i class="fas fa-trash-alt"></i> Delete Lecture
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <?php 
                    $recent_activity = getRecentActivity($db, $lecture_id, 5);
                    if (empty($recent_activity)): 
                    ?>
                        <div class="text-center py-3">
                            <i class="fas fa-history fa-2x text-gray-300 mb-2"></i>
                            <p class="text-muted small mb-0">No recent activity</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="list-group-item px-0 py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="mr-3">
                                            <div class="icon-circle bg-<?php echo getActivityIconColor($activity['action']); ?>">
                                                <i class="fas fa-<?php echo getActivityIcon($activity['action']); ?> text-white"></i>
                                            </div>
                                        </div>
                                        <div class="small">
                                            <div class="font-weight-bold"><?php echo htmlspecialchars($activity['details']); ?></div>
                                            <div class="text-muted">
                                                <?php echo timeAgo($activity['created_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-2">
                            <a href="activity_log.php?lecture_id=<?php echo $lecture_id; ?>" class="small">
                                View All Activity
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" role="dialog" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrCodeModalLabel">Attendance QR Code</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <?php if ($lecture['is_active'] && !empty($lecture['secret_code'])): ?>
                    <div id="qrcode" class="mb-3"></div>
                    <p class="text-muted">
                        Students can scan this QR code to mark their attendance.
                    </p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control text-center font-weight-bold" 
                               value="<?php echo $lecture['secret_code']; ?>" id="qrCodeText" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" 
                                    onclick="copyToClipboard('<?php echo $lecture['secret_code']; ?>')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-info-circle"></i> 
                        This code will expire when the lecture ends or when you stop attendance.
                    </div>
                <?php else: ?>
                    <div class="py-4">
                        <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                        <p>No active attendance code found.</p>
                        <p class="small text-muted">Generate an attendance code to display the QR code.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <?php if (!$lecture['is_active'] && isLectureInProgress($lecture)): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="generate_code" value="1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Generate Code
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Manual Attendance Modal -->
<div class="modal fade" id="manualAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="manualAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualAttendanceModalLabel">Mark Attendance Manually</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" id="manualAttendanceForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="studentSelect">Select Student</label>
                        <select class="form-control select2" id="studentSelect" name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach ($attendance_list as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>">
                                    <?php echo htmlspecialchars($student['registration_number'] . ' - ' . $student['student_name']); ?>
                                    <?php if ($student['status'] === 'present'): ?>
                                        (Already Marked: Present)
                                    <?php elseif ($student['status'] === 'absent'): ?>
                                        (Marked: Absent)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="attendanceStatus">Status</label>
                        <select class="form-control" id="attendanceStatus" name="status" required>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="attendanceNotes">Notes (Optional)</label>
                        <textarea class="form-control" id="attendanceNotes" name="notes" rows="2" 
                                  placeholder="Add any notes about this attendance record"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="mark_attendance" value="1">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Lecture Modal -->
<div class="modal fade" id="deleteLectureModal" tabindex="-1" role="dialog" aria-labelledby="deleteLectureModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteLectureModalLabel">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this lecture? This action cannot be undone.</p>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Warning:</strong> This will also delete all attendance records for this lecture.
                </div>
                <p class="mb-0">Lecture: <strong><?php echo htmlspecialchars($lecture['title']); ?></strong></p>
                <p class="mb-0">Date: <strong><?php echo date('M j, Y', strtotime($lecture['scheduled_date'])); ?></strong></p>
                <p>Time: <strong><?php echo date('g:i A', strtotime($lecture['start_time'])); ?> - <?php echo date('g:i A', strtotime($lecture['end_time'])); ?></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <a href="lecture_action.php?action=delete&id=<?php echo $lecture_id; ?>" 
                   class="btn btn-danger" id="confirmDelete">
                    <i class="fas fa-trash-alt"></i> Delete Lecture
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Duplicate Lecture Modal -->
<div class="modal fade" id="duplicateLectureModal" tabindex="-1" role="dialog" aria-labelledby="duplicateLectureModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="duplicateLectureModalLabel">
                    <i class="fas fa-copy"></i> Duplicate Lecture
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="lecture_action.php" method="post">
                <div class="modal-body">
                    <p>Create a copy of this lecture for a different date and time.</p>
                    
                    <div class="form-group">
                        <label for="duplicateDate">New Date</label>
                        <input type="date" class="form-control" id="duplicateDate" name="scheduled_date" 
                               value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="duplicateStartTime">Start Time</label>
                            <input type="time" class="form-control" id="duplicateStartTime" 
                                   name="start_time" value="<?php echo $lecture['start_time']; ?>" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="duplicateEndTime">End Time</label>
                            <input type="time" class="form-control" id="duplicateEndTime" 
                                   name="end_time" value="<?php echo $lecture['end_time']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="copyMaterials" name="copy_materials" value="1" checked>
                            <label class="form-check-label" for="copyMaterials">
                                Copy lecture materials and description
                            </label>
                        </div>
                    </div>
                    
                    <input type="hidden" name="action" value="duplicate">
                    <input type="hidden" name="lecture_id" value="<?php echo $lecture_id; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-copy"></i> Duplicate Lecture
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Notification Modal -->
<div class="modal fade" id="sendNotificationModal" tabindex="-1" role="dialog" aria-labelledby="sendNotificationModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sendNotificationModalLabel">
                    <i class="fas fa-bell"></i> Send Notification
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="lecture_action.php" method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="notificationType">Notification Type</label>
                        <select class="form-control" id="notificationType" name="notification_type" required>
                            <option value="reminder">Lecture Reminder</option>
                            <option value="cancellation">Lecture Cancellation</option>
                            <option value="reschedule">Reschedule Notice</option>
                            <option value="custom">Custom Message</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notificationRecipients">Recipients</label>
                        <select class="form-control select2" id="notificationRecipients" name="recipients[]" multiple required>
                            <option value="all" selected>All Enrolled Students</option>
                            <option value="present">Students Marked Present</option>
                            <option value="absent">Students Marked Absent</option>
                            <option value="late">Students Marked Late</option>
                            <option value="custom">Custom Selection</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notificationSubject">Subject</label>
                        <input type="text" class="form-control" id="notificationSubject" name="subject" 
                               value="Lecture Notification: <?php echo htmlspecialchars($lecture['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notificationMessage">Message</label>
                        <textarea class="form-control" id="notificationMessage" name="message" rows="5" required>Dear students,

This is a notification regarding the lecture "<?php echo htmlspecialchars($lecture['title']); ?>" 
scheduled for <?php echo date('l, F j, Y', strtotime($lecture['scheduled_date'])); ?> 
from <?php echo date('g:i A', strtotime($lecture['start_time'])); ?> to <?php echo date('g:i A', strtotime($lecture['end_time'])); ?>.

Best regards,
<?php echo htmlspecialchars($_SESSION['name']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sendEmail" name="send_email" value="1" checked>
                            <label class="form-check-label" for="sendEmail">
                                Send as email
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sendSMS" name="send_sms" value="1">
                            <label class="form-check-label" for="sendSMS">
                                Send as SMS (if available)
                            </label>
                        </div>
                    </div>
                    
                    <input type="hidden" name="action" value="send_notification">
                    <input type="hidden" name="lecture_id" value="<?php echo $lecture_id; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include QR Code Library -->
<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>

<script>
// Initialize DataTable
$(document).ready(function() {
    // Initialize DataTable
    $('#attendanceTable').DataTable({
        "order": [[0, "asc"]],
        "pageLength": 10,
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": [6] } // Disable sorting on actions column
        ],
        "language": {
            "search": "Search students:",
            "lengthMenu": "Show _MENU_ entries per page",
            "zeroRecords": "No matching students found",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "No students available",
            "infoFiltered": "(filtered from _MAX_ total entries)",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        },
        "dom": '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex align-items-center">l>rt<"d-flex justify-content-between align-items-center"ip>'
    });
    
    // Initialize Select2 for student dropdown
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Select a student',
        allowClear: true
    });
    
    // Initialize QR Code if modal is shown
    $('#qrCodeModal').on('shown.bs.modal', function () {
        const code = '<?php echo $lecture['is_active'] ? $lecture['secret_code'] : ''; ?>';
        if (code) {
            // Clear previous QR code
            document.getElementById('qrcode').innerHTML = '';
            
            // Generate new QR code
            new QRCode(document.getElementById("qrcode"), {
                text: code,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    });
    
    // Set current date as minimum for duplicate date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('duplicateDate').min = today;
    
    // Auto-set end time when start time changes in duplicate form
    $('#duplicateStartTime').on('change', function() {
        const startTime = $(this).val();
        if (startTime) {
            const [hours, minutes] = startTime.split(':');
            const startDate = new Date();
            startDate.setHours(parseInt(hours, 10), parseInt(minutes, 10));
            
            // Add 1 hour to start time for end time
            startDate.setHours(startDate.getHours() + 1);
            
            // Format the time as HH:MM
            const endTime = startDate.toTimeString().substring(0, 5);
            $('#duplicateEndTime').val(endTime);
        }
    });
});

// Copy text to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const originalBtnText = event.target.innerHTML;
        event.target.innerHTML = '<i class="fas fa-check"></i> Copied!';
        event.target.classList.remove('btn-outline-primary');
        event.target.classList.add('btn-success');
        
        // Reset button after 2 seconds
        setTimeout(function() {
            event.target.innerHTML = originalBtnText;
            event.target.classList.remove('btn-success');
            event.target.classList.add('btn-outline-primary');
        }, 2000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy text. Please try again.');
    });
}

// Set export format
function setExportFormat(format) {
    document.getElementById('exportFormat').value = format;
}

// Format date as time ago
function timeAgo(dateString) {
    const date = new Date(dateString);
    const seconds = Math.floor((new Date() - date) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) {
        return interval + ' year' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) {
        return interval + ' month' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    interval = Math.floor(seconds / 86400);
    if (interval >= 1) {
        return interval + ' day' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) {
        return interval + ' hour' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) {
        return interval + ' minute' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    return 'just now';
}

// Update all time-ago elements on the page
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.time-ago').forEach(function(element) {
        if (element.dataset.timestamp) {
            element.textContent = timeAgo(element.dataset.timestamp);
        }
    });
});
</script>

<style>
/* Custom styles for the lecture page */
.icon-circle {
    height: 2.5rem;
    width: 2.5rem;
    border-radius: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-circle i {
    font-size: 1rem;
}

/* Progress bar styling */
.progress {
    height: 0.8rem;
    border-radius: 0.2rem;
}

/* Card header with gradient */
.card-header {
    background: linear-gradient(45deg, #4e73df, #224abe);
    color: white;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}

/* Print styles */
@media print {
    .no-print, .no-print * {
        display: none !important;
    }
    
    body {
        font-size: 10pt;
        background: white;
        color: black;
    }
    
    .card, .table {
        border: 1px solid #ddd;
        box-shadow: none;
    }
    
    .table th {
        background-color: #f8f9fc !important;
        color: #5a5c69 !important;
    }
    
    .badge {
        border: 1px solid #ddd;
        color: black !important;
        background-color: white !important;
    }
    
    .progress {
        height: 10px !important;
        -webkit-print-color-adjust: exact;
    }
    
    @page {
        size: A4;
        margin: 1cm;
    }
}
</style>

<?php
include '../includes/footer.php';

/**
 * Get lecture details
 */
function getLectureDetails($db, $lecture_id, $lecturer_id) {
    $stmt = $db->prepare(
        "SELECT l.*, c.course_code, c.course_name, 
                CONCAT(u.first_name, ' ', u.last_name) as lecturer_name,
                (SELECT COUNT(*) FROM attendance WHERE lecture_id = l.lecture_id) as attendance_count,
                (SELECT COUNT(*) FROM student_courses WHERE course_id = l.course_id) as total_students
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         JOIN users u ON l.lecturer_id = u.user_id
         JOIN course_assignments ca ON c.course_id = ca.course_id
         WHERE l.lecture_id = ? AND ca.lecturer_id = ?"
    );
    $stmt->execute([$lecture_id, $lecturer_id]);
    return $stmt->fetch();
}

/**
 * Get attendance statistics for a lecture
 */
function getAttendanceStats($db, $lecture_id) {
    $stats = [
        'total_students' => 0,
        'present_count' => 0,
        'late_count' => 0,
        'absent_count' => 0,
        'present_percentage' => 0,
        'late_percentage' => 0,
        'absent_percentage' => 0,
        'attendance_rate' => 0,
        'attendance_taken' => false
    ];
    
    // Get total students enrolled in the course
    $stmt = $db->prepare(
        "SELECT COUNT(*) as count 
         FROM student_courses sc
         JOIN lectures l ON sc.course_id = l.course_id
         WHERE l.lecture_id = ?"
    );
    $stmt->execute([$lecture_id]);
    $result = $stmt->fetch();
    $stats['total_students'] = $result ? (int)$result['count'] : 0;
    
    if ($stats['total_students'] > 0) {
        // Get present count
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
             FROM attendance 
             WHERE lecture_id = ? AND status = 'present' AND is_late = 0"
        );
        $stmt->execute([$lecture_id]);
        $result = $stmt->fetch();
        $stats['present_count'] = $result ? (int)$result['count'] : 0;
        
        // Get late count
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
             FROM attendance 
             WHERE lecture_id = ? AND status = 'present' AND is_late = 1"
        );
        $stmt->execute([$lecture_id]);
        $result = $stmt->fetch();
        $stats['late_count'] = $result ? (int)$result['count'] : 0;
        
        // Calculate absent count
        $stats['absent_count'] = $stats['total_students'] - $stats['present_count'] - $stats['late_count'];
        
        // Calculate percentages
        $stats['present_percentage'] = round(($stats['present_count'] / $stats['total_students']) * 100);
        $stats['late_percentage'] = round(($stats['late_count'] / $stats['total_students']) * 100);
        $stats['absent_percentage'] = 100 - $stats['present_percentage'] - $stats['late_percentage'];
        
        // Calculate overall attendance rate (present + late)
        $stats['attendance_rate'] = $stats['present_percentage'] + $stats['late_percentage'];
        $stats['attendance_taken'] = ($stats['present_count'] + $stats['late_count']) > 0;
    }
    
    return $stats;
}

/**
 * Get attendance list for a lecture
 */
function getAttendanceList($db, $lecture_id, $course_id) {
    // Debug: Log the parameters
    error_log("getAttendanceList called with lecture_id: $lecture_id, course_id: $course_id");
    
    // First, check if there are any students enrolled in this course
    $checkStudents = $db->prepare(
        "SELECT COUNT(*) as count FROM student_courses WHERE course_id = ?"
    );
    $checkStudents->execute([$course_id]);
    $studentCount = $checkStudents->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Number of students enrolled in course $course_id: $studentCount");
    
    // Check if there are any attendance records for this lecture
    $checkAttendance = $db->prepare(
        "SELECT COUNT(*) as count FROM attendance WHERE lecture_id = ?"
    );
    $checkAttendance->execute([$lecture_id]);
    $attendanceCount = $checkAttendance->fetch(PDO::FETCH_ASSOC)['count'];
    error_log("Number of attendance records for lecture $lecture_id: $attendanceCount");
    
    $sql = "SELECT s.user_id as student_id, 
                   st.student_number, 
                   CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   IFNULL(a.status, 'absent') as status, 
                   a.marked_at,
                   a.feedback,
                   a.attendance_code,
                   CASE 
                       WHEN a.marked_at IS NOT NULL AND a.status = 'present' AND a.marked_at > ? 
                       THEN 1 
                       ELSE 0 
                   END as is_late
            FROM users s
            JOIN students st ON s.user_id = st.user_id
            JOIN student_courses sc ON s.user_id = sc.student_id
            LEFT JOIN attendance a ON s.user_id = a.student_id AND a.lecture_id = ?
            WHERE sc.course_id = ?
            ORDER BY a.status DESC, s.last_name, s.first_name";
    
    $stmt = $db->prepare($sql);
    
    // Get lecture start time for late calculation
    $lectureStmt = $db->prepare("SELECT start_time FROM lectures WHERE lecture_id = ?");
    $lectureStmt->execute([$lecture_id]);
    $lecture = $lectureStmt->fetch(PDO::FETCH_ASSOC);
    $lectureStartTime = $lecture ? $lecture['start_time'] : date('Y-m-d H:i:s');
    
    $stmt->execute([$lectureStartTime, $lecture_id, $course_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the number of results
    error_log("getAttendanceList returning " . count($results) . " records");
    if (count($results) > 0) {
        error_log("First record: " . print_r($results[0], true));
    }
    
    return $results;
}

/**
 * Get recent activity for a lecture
 */
function getRecentActivity($db, $lecture_id, $limit = 5) {
    $stmt = $db->prepare(
        "SELECT * FROM activity_log 
         WHERE (entity_type = 'lecture' AND entity_id = ?)
         OR (entity_type = 'attendance' AND entity_id IN 
             (SELECT attendance_id FROM attendance WHERE lecture_id = ?))
         ORDER BY created_at DESC
         LIMIT ?"
    );
    $stmt->execute([$lecture_id, $lecture_id, $limit]);
    return $stmt->fetchAll();
}

/**
 * Check if lecture is in progress
 */
function isLectureInProgress($lecture) {
    $now = new DateTime();
    $start = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['start_time']);
    $end = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['end_time']);
    
    return $now >= $start && $now <= $end;
}

/**
 * Check if lecture is complete
 */
function isLectureComplete($lecture) {
    $now = new DateTime();
    $end = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['end_time']);
    
    return $now > $end;
}

/**
 * Get duration between two times
 */
function getDuration($start_time, $end_time) {
    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $interval = $start->diff($end);
    
    $hours = $interval->h;
    $minutes = $interval->i;
    
    if ($hours > 0 && $minutes > 0) {
        return "{$hours}h {$minutes}m";
    } elseif ($hours > 0) {
        return "{$hours} hour" . ($hours > 1 ? 's' : '');
    } else {
        return "{$minutes} minute" . ($minutes != 1 ? 's' : '');
    }
}

/**
 * Get icon for activity type
 */
function getActivityIcon($action) {
    switch ($action) {
        case 'create': return 'plus';
        case 'update': return 'edit';
        case 'delete': return 'trash';
        case 'generate_code': return 'key';
        case 'mark_present': return 'user-check';
        case 'mark_absent': return 'user-times';
        default: return 'history';
    }
}

/**
 * Get icon color for activity type
 */
function getActivityIconColor($action) {
    switch ($action) {
        case 'create': return 'success';
        case 'update': return 'primary';
        case 'delete': return 'danger';
        case 'generate_code': return 'warning';
        case 'mark_present': return 'success';
        case 'mark_absent': return 'danger';
        default: return 'secondary';
    }
}

/**
 * Handle generating attendance code
 */
function handleGenerateCode($db, $lecture_id, $lecturer_id) {
    // Generate a random 6-character code
    $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    
    try {
        $db->beginTransaction();
        
        // First, deactivate any other active codes for this lecturer
        $stmt = $db->prepare(
            "UPDATE lectures l 
             JOIN course_assignments ca ON l.course_id = ca.course_id
             SET l.is_active = 0, l.updated_at = NOW() 
             WHERE ca.lecturer_id = ? AND l.is_active = 1 AND l.lecture_id != ?"
        );
        $stmt->execute([$lecturer_id, $lecture_id]);
        
        // Then activate the current lecture
        $stmt = $db->prepare(
            "UPDATE lectures 
             SET is_active = 1, secret_code = ?, code_generated_at = NOW(), updated_at = NOW() 
             WHERE lecture_id = ?"
        );
        $stmt->execute([$code, $lecture_id]);
        
        // Log the action
        logAction($db, $lecturer_id, 'attendance', 'generate_code', 
                 "Generated attendance code for lecture ID: $lecture_id");
        
        $db->commit();
        
        $_SESSION['success'] = 'Attendance code generated successfully!';
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error generating attendance code: ' . $e->getMessage();
    }
    
    redirect('lecture.php?id=' . $lecture_id);
}

/**
 * Handle stopping attendance
 */
function handleStopAttendance($db, $lecture_id, $lecturer_id) {
    try {
        $db->beginTransaction();
        
        // Deactivate the lecture
        $stmt = $db->prepare(
            "UPDATE lectures 
             SET is_active = 0, secret_code = NULL, code_generated_at = NULL, updated_at = NOW() 
             WHERE lecture_id = ?"
        );
        $stmt->execute([$lecture_id]);
        
        // Log the action
        logAction($db, $lecturer_id, 'attendance', 'stop', 
                 "Stopped attendance collection for lecture ID: $lecture_id");
        
        $db->commit();
        
        $_SESSION['success'] = 'Attendance collection stopped successfully.';
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error stopping attendance: ' . $e->getMessage();
    }
    
    redirect('lecture.php?id=' . $lecture_id);
}

/**
 * Handle exporting attendance
 */
function handleExportAttendance($db, $lecture_id, $lecturer_id) {
    // Verify lecture belongs to lecturer
    $lecture = getLectureDetails($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        redirect('lectures.php');
    }
    
    // Get attendance list
    $attendance_list = getAttendanceList($db, $lecture_id, $lecture['course_id']);
    
    // Get format (default to CSV)
    $format = strtolower($_POST['format'] ?? 'csv');
    
    // Generate filename
    $filename = 'attendance_' . preg_replace('/[^a-z0-9]/i', '_', strtolower($lecture['course_code'] . '_' . $lecture['title'])) . '_' . date('Y-m-d');
    
    if ($format === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, [
            'Student ID', 
            'Name', 
            'Status', 
            'Marked At', 
            'Attendance Code',
            'Late'
        ]);
        
        // Add data rows
        foreach ($attendance_list as $record) {
            fputcsv($output, [
                $record['registration_number'],
                $record['student_name'],
                ucfirst($record['status'] ?: 'absent'),
                $record['marked_at'] ?: 'Not marked',
                $record['attendance_code'] ?: 'N/A',
                $record['is_late'] ? 'Yes' : 'No'
            ]);
        }
        
        fclose($output);
        exit;
        
    } elseif ($format === 'pdf') {
        // PDF export would require a library like TCPDF or mPDF
        // This is a simplified example
        $_SESSION['error'] = 'PDF export is not yet implemented.';
        redirect('lecture.php?id=' . $lecture_id);
        
    } elseif ($format === 'excel') {
        // Excel export would require a library like PhpSpreadsheet
        // This is a simplified example
        $_SESSION['error'] = 'Excel export is not yet implemented.';
        redirect('lecture.php?id=' . $lecture_id);
        
    } else {
        $_SESSION['error'] = 'Invalid export format.';
        redirect('lecture.php?id=' . $lecture_id);
    }
}

/**
 * Handle manual attendance marking
 */
function handleManualAttendance($db, $lecture_id, $lecturer_id) {
    // Verify lecture belongs to lecturer
    $lecture = getLectureDetails($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        redirect('lectures.php');
    }
    
    // Get form data
    $student_id = (int)($_POST['student_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate input
    if ($student_id <= 0 || !in_array($status, ['present', 'absent', 'late'])) {
        $_SESSION['error'] = 'Invalid input data.';
        redirect('lecture.php?id=' . $lecture_id);
    }
    
    // Check if student is enrolled in the course
    $stmt = $db->prepare(
        "SELECT COUNT(*) as count 
         FROM student_courses 
         WHERE student_id = ? AND course_id = ?"
    );
    $stmt->execute([$student_id, $lecture['course_id']]);
    $result = $stmt->fetch();
    
    if (!$result || $result['count'] == 0) {
        $_SESSION['error'] = 'Student is not enrolled in this course.';
        redirect('lecture.php?id=' . $lecture_id);
    }
    
    try {
        $db->beginTransaction();
        
        // Check if attendance record already exists
        $stmt = $db->prepare(
            "SELECT attendance_id FROM attendance 
             WHERE lecture_id = ? AND student_id = ?"
        );
        $stmt->execute([$lecture_id, $student_id]);
        $existing = $stmt->fetch();
        
        $now = date('Y-m-d H:i:s');
        $is_late = ($status === 'present' && strtotime($now) > strtotime($lecture['scheduled_date'] . ' ' . $lecture['start_time']) + 900) ? 1 : 0;
        
        if ($existing) {
            // Update existing record
            $stmt = $db->prepare(
                "UPDATE attendance 
                 SET status = ?, is_late = ?, notes = ?, updated_at = NOW() 
                 WHERE attendance_id = ?"
            );
            $stmt->execute([$status, $is_late, $notes, $existing['attendance_id']]);
            
            $action = 'update_attendance';
            $details = "Updated attendance for student ID: $student_id to $status";
            
        } else {
            // Insert new record
            $stmt = $db->prepare(
                "INSERT INTO attendance 
                 (lecture_id, student_id, status, is_late, attendance_code, notes, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([
                $lecture_id, 
                $student_id, 
                $status, 
                $is_late,
                $lecture['secret_code'] ?? null,
                $notes
            ]);
            
            $action = 'mark_' . $status;
            $details = "Marked student ID: $student_id as $status";
        }
        
        // Log the action
        logAction($db, $lecturer_id, 'attendance', $action, 
                 $details . " for lecture ID: $lecture_id");
        
        $db->commit();
        
        $_SESSION['success'] = 'Attendance marked successfully!';
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error marking attendance: ' . $e->getMessage();
    }
    
    redirect('lecture.php?id=' . $lecture_id);
}

/**
 * Log an action to the activity log
 */
function logAction($db, $user_id, $entity_type, $action, $details) {
    try {
        $stmt = $db->prepare(
            "INSERT INTO activity_log 
             (user_id, entity_type, entity_id, action, details, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $entity_id = $entity_type === 'lecture' ? $GLOBALS['lecture_id'] : null;
        
        $stmt->execute([
            $user_id,
            $entity_type,
            $entity_id,
            $action,
            $details,
            $ip_address,
            $user_agent
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log('Failed to log action: ' . $e->getMessage());
        return false;
    }
}
?>
