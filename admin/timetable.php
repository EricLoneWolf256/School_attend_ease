<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. You must be an admin to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();
$success = '';
$error = '';

// Get filter parameters
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$lecturer_id = isset($_GET['lecturer_id']) ? (int)$_GET['lecturer_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d', strtotime('+1 month'));

// Handle lecture deletion
if (isset($_POST['delete_lecture']) && isset($_POST['lecture_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM lectures WHERE lecture_id = ?");
        $stmt->execute([$_POST['lecture_id']]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'Lecture deleted successfully.';
        } else {
            $_SESSION['error'] = 'Lecture not found or already deleted.';
        }
        
        redirect('timetable.php');
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error deleting lecture: ' . $e->getMessage();
        redirect('timetable.php');
    }
}

// Fetch all courses for filter
$courses = [];
try {
    $stmt = $db->query("SELECT course_id, course_code, course_name FROM courses ORDER BY course_code");
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching courses: ' . $e->getMessage();
}

// Fetch all lecturers for filter
$lecturers = [];
try {
    $stmt = $db->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'lecturer' ORDER BY last_name, first_name");
    $lecturers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching lecturers: ' . $e->getMessage();
}

// Build query for fetching lectures
$query = "SELECT l.*, c.course_code, c.course_name, 
          u.first_name, u.last_name, u.email as lecturer_email
          FROM lectures l
          JOIN courses c ON l.course_id = c.course_id
          JOIN users u ON l.lecturer_id = u.user_id
          WHERE l.scheduled_date BETWEEN :date_from AND :date_to";
          
$params = [
    ':date_from' => $date_from,
    ':date_to' => $date_to
];

if ($course_id > 0) {
    $query .= " AND l.course_id = :course_id";
    $params[':course_id'] = $course_id;
}

if ($lecturer_id > 0) {
    $query .= " AND l.lecturer_id = :lecturer_id";
    $params[':lecturer_id'] = $lecturer_id;
}

$query .= " ORDER BY l.scheduled_date, l.start_time";

// Fetch lectures based on filters
$lectures = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $lectures = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching lectures: ' . $e->getMessage();
}

// Group lectures by date for calendar view
$calendar = [];
foreach ($lectures as $lecture) {
    $date = $lecture['scheduled_date'];
    if (!isset($calendar[$date])) {
        $calendar[$date] = [];
    }
    $calendar[$date][] = $lecture;
}

$page_title = 'Timetable Management';

// Start output buffering for modals
ob_start();

include 'includes/header.php';

?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <h1 class="mt-4 text-white font-weight-bold">Timetable Management</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item active text-white">Timetable</li>
    </ol>
        <a href="add_lecture.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-calendar-plus mr-2"></i> Schedule Lecture
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success']; 
            unset($_SESSION['success']);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error']; 
            unset($_SESSION['error']);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="card glass mb-4 border-0">
        <div class="card-header py-3 bg-transparent border-bottom border-secondary">
            <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Filter Timetable</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="form-inline">
                <div class="form-group mb-2 mr-3">
                    <label for="course_id" class="mr-2">Course:</label>
                    <select class="form-control" id="course_id" name="course_id">
                        <option value="0">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>" 
                                <?php echo $course_id == $course['course_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group mb-2 mr-3">
                    <label for="lecturer_id" class="mr-2">Lecturer:</label>
                    <select class="form-control" id="lecturer_id" name="lecturer_id">
                        <option value="0">All Lecturers</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?php echo $lecturer['user_id']; ?>" 
                                <?php echo $lecturer_id == $lecturer['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group mb-2 mr-3">
                    <label for="date_from" class="mr-2">From:</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group mb-2 mr-3">
                    <label for="date_to" class="mr-2">To:</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary mb-2">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="timetable.php" class="btn btn-secondary mb-2 ml-2">
                    <i class="fas fa-sync-alt"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Calendar View -->
    <div class="card glass mb-4 border-0">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-transparent border-bottom border-secondary">
            <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Scheduled Lectures</h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-white-50"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right glass border-secondary shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                    <div class="dropdown-header text-white-50">Export Options:</div>
                    <a class="dropdown-item text-white" href="#"><i class="fas fa-file-excel fa-sm fa-fw mr-2 text-white-50"></i> Export to Excel</a>
                    <a class="dropdown-item text-white" href="#"><i class="fas fa-file-pdf fa-sm fa-fw mr-2 text-white-50"></i> Export to PDF</a>
                    <div class="dropdown-divider border-secondary"></div>
                    <a class="dropdown-item text-white" href="#"><i class="fas fa-print fa-sm fa-fw mr-2 text-white-50"></i> Print</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($lectures)): ?>
                <div class="alert alert-info">No lectures found matching the selected filters.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0" id="lecturesTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Course</th>
                                <th>Title</th>
                                <th>Lecturer</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($lectures as $lecture): 
                                $is_past = strtotime($lecture['scheduled_date'] . ' ' . $lecture['end_time']) < time();
                                $is_active = $lecture['is_active'] == 1;
                                $is_today = $lecture['scheduled_date'] == date('Y-m-d');
                            ?>
                                <tr class="<?php echo $is_past ? 'table-light' : ''; ?>">
                                    <td>
                                        <?php echo date('D, M j, Y', strtotime($lecture['scheduled_date'])); ?>
                                        <?php if ($is_today): ?>
                                            <span class="badge badge-primary">Today</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('g:i A', strtotime($lecture['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($lecture['end_time'])); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($lecture['course_code']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($lecture['course_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($lecture['title']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($lecture['first_name'] . ' ' . $lecture['last_name']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($lecture['lecturer_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($is_active): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php elseif ($is_past): ?>
                                            <span class="badge badge-secondary">Completed</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Scheduled</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($lecture['secret_code']): ?>
                                            <div class="mt-1">
                                                <small class="text-muted">
                                                    Code: <span class="font-weight-bold"><?php echo htmlspecialchars($lecture['secret_code']); ?></span>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit_lecture.php?id=<?php echo $lecture['lecture_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($is_active): ?>
                                            <a href="end_lecture.php?id=<?php echo $lecture['lecture_id']; ?>" class="btn btn-sm btn-warning" title="End Lecture"
                                               onclick="return confirm('Are you sure you want to end this lecture? This will prevent any further attendance marking.');">
                                                <i class="fas fa-stop-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-info" title="View Details" data-toggle="modal" data-target="#lectureModal<?php echo $lecture['lecture_id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form action="timetable.php" method="POST" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this lecture? This action cannot be undone.');">
                                            <input type="hidden" name="lecture_id" value="<?php echo $lecture['lecture_id']; ?>">
                                            <button type="submit" name="delete_lecture" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php 
                    // Get the modals HTML and clean the buffer
                    $modals = ob_get_clean();
                    ?>
                    
                    <?php 
                    // Output all modals after the table
                    echo $modals; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Required JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>

<!-- Page level plugins -->
<script src="../assets/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/vendor/datatables/dataTables.bootstrap4.min.js"></script>

<!-- Page level custom scripts -->
<script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#lecturesTable').DataTable({
            "order": [[0, "asc"], [1, "asc"]],
            "pageLength": 25,
            "responsive": true,
            "columnDefs": [
                { "orderable": false, "targets": [5, 6] } // Disable sorting on Actions column
            ]
        });
        
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
        
        // Make sure modals work
        $('.modal').on('shown.bs.modal', function () {
            // Focus on the first input when modal is shown
            $(this).find('input[type="text"], input[type="password"], textarea, select').first().focus();
        });
    });
</script>

<?php 
$modals = '';
ob_start();
foreach ($lectures as $lecture): 
?>
<!-- Lecture Details Modal -->
<div class="modal fade" id="lectureModal<?php echo $lecture['lecture_id']; ?>" tabindex="-1" role="dialog" aria-labelledby="lectureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lectureModalLabel">
                    <?php echo htmlspecialchars($lecture['title']); ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Lecture Details</h6>
                        <p><strong>Course:</strong> <?php echo htmlspecialchars($lecture['course_code'] . ' - ' . $lecture['course_name']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('l, F j, Y', strtotime($lecture['scheduled_date'])); ?></p>
                        <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($lecture['start_time'])) . ' - ' . date('g:i A', strtotime($lecture['end_time'])); ?></p>
                        <p><strong>Status:</strong> 
                            <?php if ($lecture['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactive</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Lecturer</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($lecture['first_name'] . ' ' . $lecture['last_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($lecture['lecturer_email']); ?></p>
                        
                        <h6 class="mt-4">Description</h6>
                        <p><?php echo nl2br(htmlspecialchars($lecture['description'] ?? 'No description provided.')); ?></p>
                    </div>
                </div>
                
                <?php if ($lecture['is_active']): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-info-circle"></i> This lecture is currently active. Students can mark attendance using the code: 
                        <strong><?php echo htmlspecialchars($lecture['secret_code'] ?? ''); ?></strong>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Close</button>
                <a class="btn btn-primary" href="edit_lecture.php?id=<?php echo $lecture['lecture_id']; ?>">
                    <i class="fas fa-edit"></i> Edit Lecture
                </a>
                <?php if ($lecture['is_active']): ?>
                    <a href="end_lecture.php?id=<?php echo $lecture['lecture_id']; ?>" class="btn btn-warning" 
                       onclick="return confirm('Are you sure you want to end this lecture? This will prevent any further attendance marking.');">
                        <i class="fas fa-stop-circle"></i> End Lecture
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php 
endforeach;
$modals = ob_get_clean();
?>

<?php include 'includes/footer.php'; ?>

<!-- Required JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>

<!-- Output all modals after the table -->
<?php echo $modals; ?>

<script>
// Initialize DataTable and modals
$(document).ready(function() {
    // Initialize DataTable
    $('#lecturesTable').DataTable({
        "order": [[0, "asc"], [1, "asc"]],
        "pageLength": 25,
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": [5, 6] } // Disable sorting on Actions column
        ]
    });
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
