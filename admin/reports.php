<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Reports | Attendance System';
include '../includes/header.php';

// Get user role
$role = $_SESSION['role'];
?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <h1 class="mt-4 text-white font-weight-bold">Reports Dashboard</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item active text-white">Reports</li>
    </ol>

    <!-- Statistics Cards -->
    <div class="row">
        <!-- Total Students Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
                                $row = $result->fetch_assoc();
                                echo $row['count'];
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Attendance Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Today's Attendance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $today = date('Y-m-d');
                                $result = $conn->query("
                                    SELECT COUNT(DISTINCT student_id) as count 
                                    FROM attendance 
                                    WHERE DATE(marked_at) = '$today'
                                ");
                                $row = $result->fetch_assoc();
                                echo $row['count'] ?? 0;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Courses Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Courses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM courses");
                                $row = $result->fetch_assoc();
                                echo $row['count'];
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Lecturers Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Lecturers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'lecturer'");
                                $row = $result->fetch_assoc();
                                echo $row['count'];
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Section -->
    <div class="row">
        <div class="col-12">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Generate Reports</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Date Range Report -->
                        <div class="col-md-6 mb-4">
                            <div class="card glass h-100 border-secondary">
                                <div class="card-header bg-transparent border-bottom border-secondary">
                                    <h6 class="m-0 font-weight-bold text-white">Attendance by Date Range</h6>
                                </div>
                                <div class="card-body">
                                    <form action="generate_report.php" method="get" target="_blank">
                                        <input type="hidden" name="type" value="date_range">
                                        <div class="form-group">
                                            <label>Start Date</label>
                                            <input type="date" name="start_date" class="form-control" 
                                                   value="<?php echo date('Y-m-01'); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>End Date</label>
                                            <input type="date" name="end_date" class="form-control"
                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-download fa-sm"></i> Generate Report
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Student Report -->
                        <div class="col-md-6 mb-4">
                            <div class="card glass h-100 border-secondary">
                                <div class="card-header bg-transparent border-bottom border-secondary">
                                    <h6 class="m-0 font-weight-bold text-white">Student Attendance Report</h6>
                                </div>
                                <div class="card-body">
                                    <form action="generate_report.php" method="get" target="_blank">
                                        <input type="hidden" name="type" value="student">
                                        <div class="form-group">
                                            <label>Select Student</label>
                                            <select name="student_id" class="form-control" required>
                                                <option value="">-- Select Student --</option>
                                                <?php
                                                $result = $conn->query("
                                                    SELECT u.user_id, u.first_name, u.last_name, s.student_number 
                                                    FROM users u
                                                    LEFT JOIN students s ON u.user_id = s.user_id
                                                    WHERE u.role = 'student'
                                                    ORDER BY u.last_name, u.first_name
                                                ");
                                                while ($row = $result->fetch_assoc()) {
                                                    $display = "{$row['last_name']}, {$row['first_name']}";
                                                    if (!empty($row['student_number'])) {
                                                        $display .= " ({$row['student_number']})";
                                                    }
                                                    echo "<option value='{$row['user_id']}'>" . htmlspecialchars($display) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Date Range (Optional)</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" name="start_date">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">to</span>
                                                </div>
                                                <input type="date" class="form-control" name="end_date">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-download fa-sm"></i> Generate Report
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Course Report -->
                        <div class="col-md-6 mb-4">
                            <div class="card glass h-100 border-secondary">
                                <div class="card-header bg-transparent border-bottom border-secondary">
                                    <h6 class="m-0 font-weight-bold text-white">Course Attendance Report</h6>
                                </div>
                                <div class="card-body">
                                    <form action="generate_report.php" method="get" target="_blank">
                                        <input type="hidden" name="type" value="course">
                                        <div class="form-group">
                                            <label>Select Course</label>
                                            <select name="course_id" class="form-control" required>
                                                <option value="">-- Select Course --</option>
                                                <?php
                                                $result = $conn->query("
                                                    SELECT course_id, course_code, course_name 
                                                    FROM courses 
                                                    ORDER BY course_code
                                                ");
                                                while ($row = $result->fetch_assoc()) {
                                                    $display = "{$row['course_code']} - {$row['course_name']}";
                                                    echo "<option value='{$row['course_id']}'>" . htmlspecialchars($display) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Date Range (Optional)</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" name="start_date">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">to</span>
                                                </div>
                                                <input type="date" class="form-control" name="end_date">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-download fa-sm"></i> Generate Report
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Lecturer Report -->
                        <div class="col-md-6 mb-4">
                            <div class="card glass h-100 border-secondary">
                                <div class="card-header bg-transparent border-bottom border-secondary">
                                    <h6 class="m-0 font-weight-bold text-white">Lecturer Report</h6>
                                </div>
                                <div class="card-body">
                                    <form action="generate_report.php" method="get" target="_blank">
                                        <input type="hidden" name="type" value="lecturer">
                                        <div class="form-group">
                                            <label>Select Lecturer</label>
                                            <select name="lecturer_id" class="form-control" required>
                                                <option value="">-- Select Lecturer --</option>
                                                <?php
                                                $result = $conn->query("
                                                    SELECT u.user_id, u.first_name, u.last_name 
                                                    FROM users u
                                                    WHERE u.role = 'lecturer'
                                                    ORDER BY u.last_name, u.first_name
                                                ");
                                                while ($row = $result->fetch_assoc()) {
                                                    $display = "{$row['last_name']}, {$row['first_name']}";
                                                    echo "<option value='{$row['user_id']}'>" . htmlspecialchars($display) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Date Range (Optional)</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" name="start_date">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">to</span>
                                                </div>
                                                <input type="date" class="form-control" name="end_date">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-download fa-sm"></i> Generate Report
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
