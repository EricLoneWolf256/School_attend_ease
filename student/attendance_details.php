<?php
require_once '../config.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    $_SESSION['error'] = 'Access denied. You must be a student to access this page.';
    redirect('../index.php');
}

if (!isset($_GET['lecture_id']) || !is_numeric($_GET['lecture_id'])) {
    $_SESSION['error'] = 'Invalid lecture specified.';
    redirect('dashboard.php');
}

$lecture_id = (int)$_GET['lecture_id'];
$student_id = $_SESSION['user_id'];
$db = getDBConnection();

// Get lecture details and attendance record
try {
    $stmt = $db->prepare(
        "SELECT l.*, c.course_code, c.course_name, 
                CONCAT(u.first_name, ' ', u.last_name) as lecturer_name,
                a.status, a.marked_at, a.attendance_code
         FROM lectures l
         JOIN courses c ON l.course_id = c.course_id
         JOIN users u ON l.lecturer_id = u.user_id
         LEFT JOIN attendance a ON l.lecture_id = a.lecture_id AND a.student_id = ?
         WHERE l.lecture_id = ?
         AND l.course_id IN (
             SELECT course_id FROM student_courses WHERE student_id = ?
         )"
    );
    $stmt->execute([$student_id, $lecture_id, $student_id]);
    $lecture = $stmt->fetch();
    
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or you are not enrolled in this course.';
        redirect('dashboard.php');
    }
    
    // Get all students in the course who have marked attendance
    $stmt = $db->prepare(
        "SELECT a.*, 
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.registration_number
         FROM attendance a
         JOIN users s ON a.student_id = s.user_id
         WHERE a.lecture_id = ?
         ORDER BY a.marked_at DESC"
    );
    $stmt->execute([$lecture_id]);
    $attendees = $stmt->fetchAll();
    
    // Get total students in the course
    $stmt = $db->prepare(
        "SELECT COUNT(*) as total_students
         FROM student_courses
         WHERE course_id = ?"
    );
    $stmt->execute([$lecture['course_id']]);
    $total_students = $stmt->fetch()['total_students'];
    
    // Calculate attendance statistics
    $present_count = 0;
    $late_count = 0;
    foreach ($attendees as $attendee) {
        if ($attendee['status'] === 'present') $present_count++;
        if ($attendee['status'] === 'late') $late_count++;
    }
    $absent_count = $total_students - count($attendees);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching lecture details: ' . $e->getMessage();
    redirect('dashboard.php');
}

$page_title = 'Attendance Details: ' . $lecture['title'];
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Attendance Details</h1>
        <div>
            <a href="attendance.php" class="btn btn-secondary btn-icon-split">
                <span class="icon text-white-50">
                    <i class="fas fa-arrow-left"></i>
                </span>
                <span class="text">Back to Attendance</span>
            </a>
            <a href="#" class="btn btn-primary btn-icon-split" onclick="window.print()">
                <span class="icon text-white-50">
                    <i class="fas fa-print"></i>
                </span>
                <span class="text">Print</span>
            </a>
        </div>
    </div>

    <!-- Lecture Details Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Lecture Information</h6>
            <?php if ($lecture['status']): ?>
                <span class="badge badge-<?php echo $lecture['status'] === 'present' ? 'success' : 'warning'; ?> px-3 py-2">
                    <?php echo ucfirst($lecture['status']); ?>
                </span>
            <?php else: ?>
                <span class="badge badge-secondary px-3 py-2">Not Marked</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h4 class="text-primary"><?php echo htmlspecialchars($lecture['course_code'] . ' - ' . $lecture['course_name']); ?></h4>
                    <h2 class="h4 font-weight-bold"><?php echo htmlspecialchars($lecture['title']); ?></h2>
                    <p class="mb-1">
                        <i class="fas fa-chalkboard-teacher text-muted"></i> 
                        <strong>Lecturer:</strong> 
                        <?php echo htmlspecialchars($lecture['lecturer_name']); ?>
                    </p>
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
                    <?php if (!empty($lecture['description'])): ?>
                        <div class="mt-3">
                            <h6 class="font-weight-bold">Description:</h6>
                            <p class="text-justify"><?php echo nl2br(htmlspecialchars($lecture['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="card border-left-primary h-100">
                        <div class="card-body">
                            <h5 class="font-weight-bold text-primary mb-4">Attendance Summary</h5>
                            
                            <div class="row mb-4">
                                <div class="col-6 text-center">
                                    <div class="h1 font-weight-bold text-primary">
                                        <?php echo count($attendees); ?>/<?php echo $total_students; ?>
                                    </div>
                                    <div class="text-muted">Students Present</div>
                                    <div class="small">
                                        (<?php echo $total_students > 0 ? round((count($attendees) / $total_students) * 100) : 0; ?>%)
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>On Time</span>
                                            <span><?php echo $present_count; ?></span>
                                        </div>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo $total_students > 0 ? ($present_count / $total_students) * 100 : 0; ?>%" 
                                                 aria-valuenow="<?php echo $present_count; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?php echo $total_students; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Late</span>
                                            <span><?php echo $late_count; ?></span>
                                        </div>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                 style="width: <?php echo $total_students > 0 ? ($late_count / $total_students) * 100 : 0; ?>%" 
                                                 aria-valuenow="<?php echo $late_count; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?php echo $total_students; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Absent</span>
                                            <span><?php echo $absent_count; ?></span>
                                        </div>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-danger" role="progressbar" 
                                                 style="width: <?php echo $total_students > 0 ? ($absent_count / $total_students) * 100 : 0; ?>%" 
                                                 aria-valuenow="<?php echo $absent_count; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?php echo $total_students; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($lecture['status']): ?>
                                <div class="alert alert-info">
                                    <div class="d-flex align-items-center">
                                        <div class="mr-3">
                                            <i class="fas fa-info-circle fa-2x"></i>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1">Your Attendance</h6>
                                            <p class="mb-0">
                                                You were marked 
                                                <span class="font-weight-bold">
                                                    <?php echo $lecture['status']; ?>
                                                </span> 
                                                at <?php echo date('g:i A', strtotime($lecture['marked_at'])); ?>
                                            </p>
                                            <?php if (!empty($lecture['attendance_code'])): ?>
                                                <p class="mb-0 small">
                                                    Attendance Code: 
                                                    <span class="font-weight-bold">
                                                        <?php echo htmlspecialchars($lecture['attendance_code']); ?>
                                                    </span>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <div class="d-flex align-items-center">
                                        <div class="mr-3">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1">Attendance Not Marked</h6>
                                            <p class="mb-0">
                                                You have not marked your attendance for this lecture.
                                                <?php if ($lecture['is_active'] && !empty($lecture['secret_code'])): ?>
                                                    <a href="mark_attendance.php?lecture_id=<?php echo $lecture['lecture_id']; ?>" 
                                                       class="btn btn-sm btn-primary ml-2">
                                                        Mark Attendance Now
                                                    </a>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendees List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Attendance List</h6>
            <div class="dropdown no-arrow
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" 
                   data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" 
                     aria-labelledby="dropdownMenuLink">
                    <a class="dropdown-item" href="#" onclick="exportToCSV()">
                        <i class="fas fa-file-csv fa-sm fa-fw mr-2 text-gray-400"></i>
                        Export to CSV
                    </a>
                    <a class="dropdown-item" href="#" onclick="window.print()">
                        <i class="fas fa-print fa-sm fa-fw mr-2 text-gray-400"></i>
                        Print List
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($attendees)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users-slash fa-3x text-gray-300 mb-3"></i>
                    <h5>No attendance records found for this lecture.</h5>
                    <p class="text-muted">Students can mark their attendance when the lecture is active.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="attendanceTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Registration #</th>
                                <th>Status</th>
                                <th>Time Marked</th>
                                <th>Attendance Code</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $index => $attendee): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($attendee['student_name']); ?>
                                        <?php if ($attendee['student_id'] == $student_id): ?>
                                            <span class="badge badge-primary ml-1">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($attendee['registration_number']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $attendee['status'] === 'present' ? 'success' : 
                                                 ($attendee['status'] === 'late' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($attendee['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($attendee['marked_at'])); ?></td>
                                    <td>
                                        <code><?php echo htmlspecialchars($attendee['attendance_code']); ?></code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Export Attendance</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="exportFormat">Select Format</label>
                        <select class="form-control" id="exportFormat">
                            <option value="csv">CSV (Comma Separated Values)</option>
                            <option value="pdf">PDF Document</option>
                            <option value="excel">Excel Spreadsheet</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="includeDetails">Include Lecture Details</label>
                        <input type="checkbox" class="form-check-input ml-2" id="includeDetails" checked>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmExport()">
                        <i class="fas fa-download fa-sm fa-fw mr-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to export table data to CSV
function exportToCSV() {
    // Get table data
    const table = document.getElementById('attendanceTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Add headers
    const headers = [];
    table.querySelectorAll('th').forEach(header => {
        headers.push('"' + header.textContent.trim() + '"');
    });
    csv.push(headers.join(','));
    
    // Add rows
    for (let i = 1; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td');
        
        for (let j = 0; j < cols.length; j++) {
            // Remove any HTML tags and extra whitespace
            let text = cols[j].textContent.trim();
            // Escape quotes and wrap in quotes
            row.push('"' + text.replace(/"/g, '""') + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Create CSV file
    const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
    
    // Create download link
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', 'attendance_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $lecture['course_code'] . '_' . $lecture['scheduled_date']); ?>.csv');
    document.body.appendChild(link);
    
    // Download file
    link.click();
    document.body.removeChild(link);
}

// Function to show export options
function showExportOptions() {
    $('#exportModal').modal('show');
}

// Function to handle export confirmation
function confirmExport() {
    const format = document.getElementById('exportFormat').value;
    const includeDetails = document.getElementById('includeDetails').checked;
    
    // For now, we'll just implement CSV export
    // In a real application, you would handle different formats
    if (format === 'csv') {
        exportToCSV();
    } else {
        alert(format.toUpperCase() + ' export will be available soon. Using CSV format instead.');
        exportToCSV();
    }
    
    // Close modal
    $('#exportModal').modal('hide');
}

// Initialize DataTable
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        "pageLength": 10,
        "order": [[4, 'desc']], // Sort by time marked by default
        "columnDefs": [
            { "orderable": false, "targets": [0] } // Disable sorting on the # column
        ],
        "language": {
            "search": "Search students:",
            "lengthMenu": "Show _MENU_ entries per page",
            "zeroRecords": "No matching records found",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "No records available",
            "infoFiltered": "(filtered from _MAX_ total entries)",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        },
        "dom": '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex align-items-center">l>rt<"d-flex justify-content-between align-items-center"ip>',
        "initComplete": function() {
            $('.dataTables_filter input').addClass('form-control form-control-sm');
            $('.dataTables_length select').addClass('form-control form-control-sm');
        }
    });
});
</script>

<style>
/* Print styles */
@media print {
    .no-print, .no-print * {
        display: none !important;
    }
    
    body {
        font-size: 12pt;
        background: white;
        color: black;
    }
    
    .card {
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
    }
}

/* Custom styles */
.attendance-status {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

.attendance-status.present {
    background-color: #1cc88a;
}

.attendance-status.late {
    background-color: #f6c23e;
}

.attendance-status.absent {
    background-color: #e74a3b;
}
</style>

<?php include 'includes/footer.php'; ?>
