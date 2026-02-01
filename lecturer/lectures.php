<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/db_connection.php';

// Check if user is logged in and has the right role
if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please log in to access this page.';
    if (function_exists('redirect')) {
        redirect('../index.php');
    } else {
        header('Location: ../index.php');
        exit();
    }
}

// Check if user has the right role (either admin or lecturer)
if (!in_array($_SESSION['role'], ['admin', 'lecturer'])) {
    $_SESSION['error'] = 'Access denied. You must be an administrator or lecturer to access this page. Current role: ' . $_SESSION['role'];
    if (function_exists('redirect')) {
        redirect('../index.php');
    } else {
        header('Location: ../index.php');
        exit();
    }
}

$db = getDBConnection();
$lecturer_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Get lecturer's assigned courses
try {
    $stmt = $db->prepare(
        "SELECT c.course_id, c.course_code, c.course_name
         FROM courses c
         JOIN course_assignments ca ON c.course_id = ca.course_id
         WHERE ca.lecturer_id = ?
         ORDER BY c.course_code"
    );
    $stmt->execute([$lecturer_id]);
    $assigned_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching assigned courses: ' . $e->getMessage();
    $assigned_courses = [];
}

// Handle different actions
switch ($action) {
    case 'add':
    case 'edit':
        handleAddEditLecture($db, $lecturer_id, $assigned_courses, $action);
        break;
    case 'delete':
        handleDeleteLecture($db, $lecturer_id);
        break;
    default:
        listLectures($db, $lecturer_id, $filter, $course_filter, $assigned_courses);
        break;
}

/**
 * Handle adding/editing a lecture
 */
function handleAddEditLecture($db, $lecturer_id, $assigned_courses, $action) {
    $lecture = [
        'lecture_id' => 0,
        'course_id' => $assigned_courses[0]['course_id'] ?? 0,
        'title' => '',
        'description' => '',
        'scheduled_date' => date('Y-m-d'),
        'start_time' => date('H:00:00', strtotime('+1 hour')),
        'end_time' => date('H:00:00', strtotime('+2 hours')),
        'is_active' => 0,
        'secret_code' => ''
    ];
    
    $page_title = 'Schedule New Lecture';
    $form_action = 'lecture_action.php?action=add';
    
    // If editing, load the lecture data
    if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
        $lecture_id = (int)$_GET['id'];
        
        try {
            $stmt = $db->prepare(
                "SELECT l.*
                 FROM lectures l
                 JOIN course_assignments ca ON l.course_id = ca.course_id
                 WHERE l.lecture_id = ? AND ca.lecturer_id = ?"
            );
            $stmt->execute([$lecture_id, $lecturer_id]);
            $lecture = $stmt->fetch();
            
            if (!$lecture) {
                $_SESSION['error'] = 'Lecture not found or access denied.';
                redirect('lectures.php');
            }
            
            $page_title = 'Edit Lecture';
            $form_action = 'lecture_action.php?action=edit';
            
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error fetching lecture: ' . $e->getMessage();
            redirect('lectures.php');
        }
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $lecture['course_id'] = (int)($_POST['course_id'] ?? 0);
        $lecture['title'] = trim($_POST['title'] ?? '');
        $lecture['description'] = trim($_POST['description'] ?? '');
        $lecture['scheduled_date'] = $_POST['scheduled_date'] ?? '';
        $lecture['start_time'] = $_POST['start_time'] ?? '';
        $lecture['end_time'] = $_POST['end_time'] ?? '';
        
        $errors = [];
        
        // Validate course
        $valid_course = false;
        foreach ($assigned_courses as $course) {
            if ($course['course_id'] == $lecture['course_id']) {
                $valid_course = true;
                break;
            }
        }
        
        if (!$valid_course) {
            $errors[] = 'Please select a valid course.';
        }
        
        if (empty($lecture['title'])) {
            $errors[] = 'Please enter a title for the lecture.';
        }
        
        if (empty($lecture['scheduled_date']) || empty($lecture['start_time']) || empty($lecture['end_time'])) {
            $errors[] = 'Please provide date and time details.';
        } else {
            $start_datetime = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['start_time']);
            $end_datetime = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['end_time']);
            
            if ($end_datetime <= $start_datetime) {
                $errors[] = 'End time must be after start time.';
            }
            
            // Check for scheduling conflicts (only for new lectures or when date/time changes)
            if ($action === 'add' || 
                ($action === 'edit' && 
                 ($lecture['scheduled_date'] != $lecture['scheduled_date'] || 
                  $lecture['start_time'] != $lecture['start_time'] || 
                  $lecture['end_time'] != $lecture['end_time']))) {
                
                $stmt = $db->prepare(
                    "SELECT COUNT(*) as conflict_count 
                     FROM lectures l
                     JOIN course_assignments ca ON l.course_id = ca.course_id
                     WHERE ca.lecturer_id = ?
                     AND l.lecture_id != ?
                     AND l.scheduled_date = ?
                     AND (
                         (l.start_time <= ? AND l.end_time > ?) OR  -- New lecture starts during existing
                         (l.start_time < ? AND l.end_time >= ?) OR  -- New lecture ends during existing
                         (l.start_time >= ? AND l.end_time <= ?)     -- New lecture is within existing
                     )"
                );
                
                $stmt->execute([
                    $lecturer_id,
                    $lecture['lecture_id'] ?? 0,
                    $lecture['scheduled_date'],
                    $lecture['start_time'], $lecture['start_time'],
                    $lecture['end_time'], $lecture['end_time'],
                    $lecture['start_time'], $lecture['end_time']
                ]);
                
                $conflict = $stmt->fetch();
                
                if ($conflict['conflict_count'] > 0) {
                    $errors[] = 'You already have a lecture scheduled during this time.';
                }
            }
        }
        
        if (empty($errors)) {
            // Save to database in lecture_action.php
            $_SESSION['form_data'] = $lecture;
            
            if ($action === 'add') {
                redirect('lecture_action.php?action=add');
            } else {
                redirect('lecture_action.php?action=edit&id=' . $lecture_id);
            }
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
    }
    
    // Display the form
    displayLectureForm($page_title, $form_action, $lecture, $assigned_courses, $action);
}

/**
 * Handle deleting a lecture
 */
function handleDeleteLecture($db, $lecturer_id) {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['error'] = 'Invalid lecture ID.';
        redirect('lectures.php');
    }
    
    $lecture_id = (int)$_GET['id'];
    
    try {
        // Verify ownership before deleting
        $stmt = $db->prepare(
            "SELECT l.lecture_id 
             FROM lectures l
             JOIN course_assignments ca ON l.course_id = ca.course_id
             WHERE l.lecture_id = ? AND ca.lecturer_id = ?"
        );
        $stmt->execute([$lecture_id, $lecturer_id]);
        $lecture = $stmt->fetch();
        
        if (!$lecture) {
            $_SESSION['error'] = 'Lecture not found or access denied.';
            redirect('lectures.php');
        }
        
        // Start transaction
        $db->beginTransaction();
        
        // Delete attendance records
        $stmt = $db->prepare("DELETE FROM attendance WHERE lecture_id = ?");
        $stmt->execute([$lecture_id]);
        
        // Delete the lecture
        $stmt = $db->prepare("DELETE FROM lectures WHERE lecture_id = ?");
        $stmt->execute([$lecture_id]);
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['success'] = 'Lecture deleted successfully.';
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error deleting lecture: ' . $e->getMessage();
    }
    
    redirect('lectures.php');
}

/**
 * List lectures based on filter
 */
function listLectures($db, $lecturer_id, $filter, $course_filter, $assigned_courses) {
    $where_conditions = ["ca.lecturer_id = ?"];
    $params = [$lecturer_id];
    $page_title = 'My Lectures';
    
    // Apply filters
    switch ($filter) {
        case 'past':
            $where_conditions[] = "(l.scheduled_date < CURDATE() OR (l.scheduled_date = CURDATE() AND l.end_time < CURTIME()))";
            $page_title = 'Past Lectures';
            break;
        case 'today':
            $where_conditions[] = "l.scheduled_date = CURDATE()";
            $page_title = "Today's Lectures";
            break;
        case 'upcoming':
        default:
            $where_conditions[] = "(l.scheduled_date > CURDATE() OR (l.scheduled_date = CURDATE() AND l.end_time >= CURTIME()))";
            $page_title = 'Upcoming Lectures';
            break;
    }
    
    // Apply course filter
    if ($course_filter > 0) {
        $where_conditions[] = "l.course_id = ?";
        $params[] = $course_filter;
        
        // Find the course name for the title
        foreach ($assigned_courses as $course) {
            if ($course['course_id'] == $course_filter) {
                $page_title .= ' - ' . $course['course_code'];
                break;
            }
        }
    }
    
    // Build the query
    $where_clause = implode(' AND ', $where_conditions);
    
    try {
        $stmt = $db->prepare(
            "SELECT l.*, c.course_code, c.course_name,
                    (SELECT COUNT(*) FROM attendance WHERE lecture_id = l.lecture_id) as attendance_count,
                    (SELECT COUNT(*) FROM student_courses WHERE course_id = l.course_id) as total_students
             FROM lectures l
             JOIN courses c ON l.course_id = c.course_id
             JOIN course_assignments ca ON c.course_id = ca.course_id
             WHERE $where_clause
             ORDER BY l.scheduled_date DESC, l.start_time DESC"
        );
        
        $stmt->execute($params);
        $lectures = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error fetching lectures: ' . $e->getMessage();
        $lectures = [];
    }
    
    // Display the list
    displayLecturesList($page_title, $lectures, $filter, $course_filter, $assigned_courses);
}

/**
 * Display the lectures list
 */
function displayLecturesList($page_title, $lectures, $current_filter, $current_course, $courses) {
    include dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="container-fluid">
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?php echo $page_title; ?></h1>
            <a href="lectures.php?action=add" class="btn btn-primary btn-icon-split">
                <span class="icon text-white-50">
                    <i class="fas fa-plus"></i>
                </span>
                <span class="text">Schedule Lecture</span>
            </a>
        </div>

        <!-- Filters -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline">
                    <input type="hidden" name="filter" value="<?php echo $current_filter; ?>">
                    
                    <div class="form-group mr-3 mb-2">
                        <label for="course_filter" class="mr-2">Course:</label>
                        <select class="form-control form-control-sm" id="course_filter" name="course_id" onchange="this.form.submit()">
                            <option value="0">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" 
                                    <?php echo $current_course == $course['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-sm btn-outline-primary <?php echo $current_filter === 'upcoming' ? 'active' : ''; ?>">
                            <input type="radio" name="filter" value="upcoming" 
                                   onchange="this.form.submit()" <?php echo $current_filter === 'upcoming' ? 'checked' : ''; ?>>
                            Upcoming
                        </label>
                        <label class="btn btn-sm btn-outline-primary <?php echo $current_filter === 'today' ? 'active' : ''; ?>">
                            <input type="radio" name="filter" value="today" 
                                   onchange="this.form.submit()" <?php echo $current_filter === 'today' ? 'checked' : ''; ?>>
                            Today
                        </label>
                        <label class="btn btn-sm btn-outline-primary <?php echo $current_filter === 'past' ? 'active' : ''; ?>">
                            <input type="radio" name="filter" value="past" 
                                   onchange="this.form.submit()" <?php echo $current_filter === 'past' ? 'checked' : ''; ?>>
                            Past
                        </label>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-link ml-auto" onclick="resetFilters()">
                        <i class="fas fa-sync-alt"></i> Reset Filters
                    </button>
                </form>
            </div>
        </div>

        <!-- Lectures List -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <?php if (empty($lectures)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                        <h5>No lectures found</h5>
                        <p class="text-muted">
                            <?php if ($current_filter === 'today'): ?>
                                No lectures are scheduled for today.
                            <?php elseif ($current_filter === 'past'): ?>
                                No past lectures found.
                            <?php else: ?>
                                No upcoming lectures found.
                            <?php endif; ?>
                        </p>
                        <a href="lectures.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Schedule a Lecture
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="lecturesTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Course</th>
                                    <th>Title</th>
                                    <th>Time</th>
                                    <th>Attendance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lectures as $lecture): 
                                    $lecture_date = new DateTime($lecture['scheduled_date']);
                                    $start_time = new DateTime($lecture['start_time']);
                                    $end_time = new DateTime($lecture['end_time']);
                                    $now = new DateTime();
                                    $is_today = $lecture_date->format('Y-m-d') === $now->format('Y-m-d');
                                    $is_past = $lecture_date < $now || ($is_today && $end_time < $now);
                                    $is_active = $lecture['is_active'] == 1;
                                    $attendance_rate = $lecture['total_students'] > 0 ? 
                                        round(($lecture['attendance_count'] / $lecture['total_students']) * 100) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($is_today): ?>
                                                <span class="badge badge-primary">Today</span>
                                            <?php else: ?>
                                                <?php echo $lecture_date->format('M j, Y'); ?>
                                            <?php endif; ?>
                                            <div class="small text-muted"><?php echo $lecture_date->format('l'); ?></div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($lecture['course_code']); ?></strong>
                                            <div class="small text-muted"><?php echo htmlspecialchars($lecture['course_name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($lecture['title']); ?></td>
                                        <td>
                                            <?php echo $start_time->format('g:i A') . ' - ' . $end_time->format('g:i A'); ?>
                                            <div class="small text-muted">
                                                <?php echo ($end_time->getTimestamp() - $start_time->getTimestamp()) / 60; ?> mins
                                            </div>
                                        </td>
                                        <td>
                                            <div class="progress mb-1" style="height: 20px;">
                                                <div class="progress-bar bg-<?php 
                                                    echo $attendance_rate >= 70 ? 'success' : 
                                                         ($attendance_rate >= 40 ? 'warning' : 'danger'); 
                                                ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $attendance_rate; ?>%" 
                                                     aria-valuenow="<?php echo $attendance_rate; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?php echo $attendance_rate; ?>%
                                                </div>
                                            </div>
                                            <div class="small text-muted text-center">
                                                <?php echo $lecture['attendance_count']; ?>/<?php echo $lecture['total_students']; ?> students
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($is_active): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php elseif ($is_past): ?>
                                                <span class="badge badge-secondary">Completed</span>
                                            <?php else: ?>
                                                <span class="badge badge-info">Scheduled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="lecture.php?id=<?php echo $lecture['lecture_id']; ?>" 
                                                   class="btn btn-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="lectures.php?action=edit&id=<?php echo $lecture['lecture_id']; ?>" 
                                                   class="btn btn-info" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if (!$is_past): ?>
                                                    <?php if ($is_active): ?>
                                                        <a href="stop_attendance.php?lecture_id=<?php echo $lecture['lecture_id']; ?>" 
                                                           class="btn btn-warning" 
                                                           title="Stop Attendance"
                                                           onclick="return confirm('Are you sure you want to stop attendance for this lecture?')">
                                                            <i class="fas fa-stop-circle"></i>
                                                        </a>
                                                        <span class="btn btn-success" 
                                                              style="cursor: default;"
                                                              title="Active Code: <?php echo htmlspecialchars($lecture['secret_code']); ?>">
                                                            <i class="fas fa-qrcode"></i> <?php echo htmlspecialchars($lecture['secret_code']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <a href="generate_code.php?lecture_id=<?php echo $lecture['lecture_id']; ?>" 
                                                           class="btn btn-success" 
                                                           title="Generate Attendance Code"
                                                           onclick="return confirm('Generate a new attendance code? This will invalidate any existing code.')">
                                                            <i class="fas fa-qrcode"></i> Generate Code
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <a href="#" 
                                                   class="btn btn-danger delete-lecture" 
                                                   data-id="<?php echo $lecture['lecture_id']; ?>"
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
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
    <div class="modal fade" id="deleteLectureModal" tabindex="-1" role="dialog" aria-labelledby="deleteLectureModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteLectureModalLabel">Confirm Deletion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this lecture? This action cannot be undone.</p>
                    <p class="text-danger"><strong>Warning:</strong> This will also delete all attendance records for this lecture.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Lecture
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Initialize DataTable
    $(document).ready(function() {
        $('#lecturesTable').DataTable({
            "order": [[0, "desc"], [3, "asc"]], // Sort by date (desc) then time (asc)
            "pageLength": 10,
            "responsive": true,
            "columnDefs": [
                { "orderable": false, "targets": [4, 5, 6] } // Disable sorting on attendance, status, and actions columns
            ],
            "language": {
                "search": "Filter:",
                "lengthMenu": "Show _MENU_ entries per page",
                "zeroRecords": "No matching lectures found",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoEmpty": "No lectures available",
                "infoFiltered": "(filtered from _MAX_ total entries)",
                "paginate": {
                    "first": "First",
                    "last": "Last",
                    "next": "Next",
                    "previous": "Previous"
                }
            },
            "dom": '<"d-flex justify-content-between align-items-center mb-3"f<"d-flex align-items-center"><l>>rt<"d-flex justify-content-between align-items-center"ip>'
        });
        
        // Handle delete button clicks
        $('.delete-lecture').on('click', function(e) {
            e.preventDefault();
            const lectureId = $(this).data('id');
            $('#confirmDelete').attr('href', 'lectures.php?action=delete&id=' + lectureId);
            $('#deleteLectureModal').modal('show');
        });
    });
    
    // Reset filters
    function resetFilters() {
        window.location.href = 'lectures.php';
    }
    </script>
    
    <style>
    /* Custom styles for the lectures table */
    .progress {
        min-width: 100px;
    }
    
    .btn-group-sm > .btn, .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        line-height: 1.5;
        border-radius: 0.2rem;
    }
    
    /* Make the table more compact */
    #lecturesTable td, #lecturesTable th {
        padding: 0.5rem;
        vertical-align: middle;
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
    }
    </style>
    
    <?php
    include dirname(__DIR__) . '/includes/footer.php';
}

/**
 * Display the lecture form (add/edit)
 */
function displayLectureForm($page_title, $form_action, $lecture, $courses, $action) {
    include dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="container-fluid">
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?php echo $page_title; ?></h1>
            <a href="lectures.php" class="btn btn-secondary btn-icon-split">
                <span class="icon text-white-50">
                    <i class="fas fa-arrow-left"></i>
                </span>
                <span class="text">Back to Lectures</span>
            </a>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Lecture Form -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Lecture Details</h6>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo $form_action; ?>" method="POST" id="lectureForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div class="form-group">
                                <label for="course_id">Course <span class="text-danger">*</span></label>
                                <select class="form-control" id="course_id" name="course_id" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>" 
                                            <?php echo $lecture['course_id'] == $course['course_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="title">Lecture Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($lecture['title']); ?>" required>
                                <small class="form-text text-muted">e.g., Introduction to Web Development, Chapter 1 Review</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                    echo htmlspecialchars($lecture['description']); 
                                ?></textarea>
                                <small class="form-text text-muted">Optional: Add any additional details about this lecture.</small>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="scheduled_date">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" 
                                           value="<?php echo $lecture['scheduled_date']; ?>" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="start_time">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" 
                                           value="<?php echo substr($lecture['start_time'], 0, 5); ?>" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="end_time">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" 
                                           value="<?php echo substr($lecture['end_time'], 0, 5); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Schedule Lecture' : 'Save Changes'; ?>
                                </button>
                                <a href="lectures.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Help Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-question-circle"></i> Help & Tips
                        </h6>
                    </div>
                    <div class="card-body">
                        <h6>Scheduling Lectures</h6>
                        <ul class="small pl-3">
                            <li>Select the course this lecture belongs to.</li>
                            <li>Provide a clear, descriptive title for the lecture.</li>
                            <li>Set the date and time when the lecture will take place.</li>
                            <li>Double-check for scheduling conflicts before saving.</li>
                        </ul>
                        
                        <h6 class="mt-4">Attendance Codes</h6>
                        <p class="small">
                            After saving, you can generate an attendance code when the lecture starts. 
                            Students will use this code to mark their attendance.
                        </p>
                        
                        <h6 class="mt-4">Best Practices</h6>
                        <ul class="small pl-3">
                            <li>Schedule lectures at least 24 hours in advance.</li>
                            <li>Be mindful of other scheduled lectures to avoid conflicts.</li>
                            <li>Generate attendance codes only when the lecture begins.</li>
                            <li>End attendance collection when the lecture is over.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Auto-set end time when start time changes
    document.getElementById('start_time').addEventListener('change', function() {
        const startTime = this.value;
        if (startTime) {
            const [hours, minutes] = startTime.split(':');
            const startDate = new Date();
            startDate.setHours(parseInt(hours, 10), parseInt(minutes, 10));
            
            // Add 1 hour to start time for end time
            startDate.setHours(startDate.getHours() + 1);
            
            // Format the time as HH:MM
            const endTime = startDate.toTimeString().substring(0, 5);
            document.getElementById('end_time').value = endTime;
        }
    });
    
    // Form validation
    document.getElementById('lectureForm').addEventListener('submit', function(e) {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        
        if (startTime && endTime) {
            if (startTime >= endTime) {
                e.preventDefault();
                alert('End time must be after start time.');
                return false;
            }
        }
        
        return true;
    });
    
    // Set minimum date to today
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('scheduled_date').min = today;
        
        // If editing, don't restrict past dates
        <?php if ($action === 'edit'): ?>
        document.getElementById('scheduled_date').removeAttribute('min');
        <?php endif; ?>
    });
    </script>
    
    <style>
    /* Custom styles for the form */
    .form-control:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }
    
    /* Make form labels bolder */
    .form-group label {
        font-weight: 600;
        color: #5a5c69;
    }
    
    /* Add some spacing between form groups */
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    /* Style the help card */
    .card .card-header {
        border-bottom: 1px solid #e3e6f0;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .form-row {
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
    }
    </style>
    
    <?php
    include dirname(__DIR__) . '/includes/footer.php';
    exit();
}
?>
