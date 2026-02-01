<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. You must be an admin to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();
$success = '';
$error = '';

// Handle attendance deletion
if (isset($_POST['delete_attendance']) && isset($_POST['attendance_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM attendance WHERE attendance_id = ?");
        $stmt->execute([$_POST['attendance_id']]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'Attendance record deleted successfully.';
        } else {
            $_SESSION['error'] = 'Attendance record not found or already deleted.';
        }
        
        redirect('attendance_report.php');
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error deleting attendance record: ' . $e->getMessage();
        redirect('attendance_report.php');
    }
}

// Get search term if any
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query to fetch attendance records with related data
$query = "SELECT 
            a.attendance_id,
            a.marked_at,
            a.feedback,
            u.user_id as student_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            c.course_code,
            c.course_name,
            l.title as lecture_title,
            l.scheduled_date,
            l.start_time,
            l.end_time,
            TIMESTAMPDIFF(MINUTE, l.start_time, l.end_time) as duration_minutes
          FROM attendance a
          JOIN users u ON a.student_id = u.user_id
          JOIN lectures l ON a.lecture_id = l.lecture_id
          JOIN courses c ON l.course_id = c.course_id
          WHERE u.role = 'student'";

// Add search condition if search term exists
if (!empty($search)) {
    $searchTerm = "%$search%";
    $query .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE :search 
              OR c.course_code LIKE :search 
              OR c.course_name LIKE :search 
              OR l.title LIKE :search)";
}

$query .= " ORDER BY a.marked_at DESC";

try {
    $stmt = $db->prepare($query);
    
    if (!empty($search)) {
        $stmt->bindParam(':search', $searchTerm);
    }
    
    $stmt->execute();
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching attendance records: ' . $e->getMessage();
}

$page_title = 'Attendance Report';
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <h1 class="mt-4 text-white font-weight-bold"><?php echo $page_title; ?></h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item active text-white">Attendance Report</li>
    </ol>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        
        <!-- Search Form -->
        <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
            <div class="input-group glass rounded shadow-sm">
                <input type="text" class="form-control bg-transparent border-0 text-white small" placeholder="Search for..." 
                       name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       aria-label="Search" aria-describedby="basic-addon2">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search fa-sm"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php 
            echo $_SESSION['success']; 
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php 
            echo $_SESSION['error']; 
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Attendance Records Table -->
    <div class="card glass mb-4 border-0">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-transparent border-bottom border-secondary">
            <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Attendance Records</h6>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php elseif (empty($attendance_records)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No attendance records found.
                    <?php if (!empty($search)): ?>
                        <a href="attendance_report.php" class="alert-link">Clear search</a> to see all records.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0" id="attendanceTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student Name</th>
                                <th>Course</th>
                                <th>Lecture</th>
                                <th>Status</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): 
                                $duration = $record['duration_minutes'] ?? 0;
                                $hours = floor($duration / 60);
                                $minutes = $duration % 60;
                                $duration_display = '';
                                
                                if ($hours > 0) {
                                    $duration_display .= $hours . 'h ';
                                }
                                $duration_display .= $minutes . 'm';
                            ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($record['scheduled_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                    <td>
                                        <div class="text-primary"><?php echo htmlspecialchars($record['course_code']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['course_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['lecture_title']); ?></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i> Present
                                        </span>
                                    </td>
                                    <td><?php echo date('g:i A', strtotime($record['start_time'])); ?></td>
                                    <td><?php echo date('g:i A', strtotime($record['end_time'])); ?></td>
                                    <td><?php echo $duration_display; ?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-danger delete-attendance" 
                                           data-id="<?php echo $record['attendance_id']; ?>"
                                           data-name="<?php echo htmlspecialchars($record['student_name'] . ' - ' . $record['course_code']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content glass">
            <div class="modal-header bg-transparent border-bottom border-secondary text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-white">
                Are you sure you want to delete the attendance record for <strong id="recordToDelete"></strong>?
                <p class="text-danger mt-2">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="post" style="display: inline;">
                    <input type="hidden" name="attendance_id" id="attendanceId">
                    <input type="hidden" name="delete_attendance" value="1">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- DataTables Script -->
<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#attendanceTable').DataTable({
            "pageLength": 10,
            "order": [[0, 'desc']], // Sort by date descending by default
            "columnDefs": [
                { "orderable": false, "targets": [8] }, // Disable sorting on actions column
                { "searchable": false, "targets": [8] }  // Disable search on actions column
            ]
        });

        // Handle delete button click
        $('.delete-attendance').click(function(e) {
            e.preventDefault();
            var recordId = $(this).data('id');
            var recordName = $(this).data('name');
            
            $('#recordToDelete').text(recordName);
            $('#attendanceId').val(recordId);
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
