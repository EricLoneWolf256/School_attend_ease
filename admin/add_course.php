<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. You must be an admin to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_code = trim($_POST['course_code'] ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $lecturer_id = (int)($_POST['lecturer_id'] ?? 0);
    
    // Basic validation
    if (empty($course_code) || empty($course_name)) {
        $error = 'Course code and name are required.';
    } elseif ($lecturer_id <= 0) {
        $error = 'Please select a lecturer for this course.';
    } else {
        try {
            // Check if course code already exists
            $stmt = $db->prepare("SELECT course_id FROM courses WHERE course_code = ?");
            $stmt->execute([$course_code]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'A course with this code already exists.';
            } else {
                // Start transaction
                $db->beginTransaction();
                
                try {
                    // Insert new course
                    $stmt = $db->prepare(
                        "INSERT INTO courses (course_code, course_name, description) 
                         VALUES (?, ?, ?)"
                    );
                    
                    if ($stmt->execute([$course_code, $course_name, $description])) {
                        $course_id = $db->lastInsertId();
                        
                        // Assign lecturer to course
                        $stmt = $db->prepare(
                            "INSERT INTO course_assignments (course_id, lecturer_id) 
                             VALUES (?, ?)"
                        );
                        
                        if ($stmt->execute([$course_id, $lecturer_id])) {
                            $db->commit();
                            $_SESSION['success'] = 'Course added and lecturer assigned successfully!';
                            redirect("courses.php");
                        } else {
                            throw new Exception('Failed to assign lecturer to course.');
                        }
                    } else {
                        $db->rollBack();
                        $error = 'Failed to add course. Please try again.';
                    }
                } catch (PDOException $e) {
                    $db->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Add New Course';
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <h1 class="mt-4 text-white font-weight-bold">Add New Course</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="courses.php" class="text-white-50">Courses</a></li>
        <li class="breadcrumb-item active text-white">Add Course</li>
    </ol>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-white-50">Course Details</h1>
        <a href="courses.php" class="btn btn-outline-light shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back to Courses
        </a>
    </div>

    <!-- Content Row -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Course Information</h6>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger bg-danger text-white border-0"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form action="add_course.php" method="POST" id="courseForm">
                        <div class="form-group row">
                            <label for="course_code" class="col-sm-3 col-form-label">Course Code <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="course_code" name="course_code" 
                                       value="<?php echo htmlspecialchars($_POST['course_code'] ?? ''); ?>" required>
                                <small class="form-text text-muted">e.g., CS101, MATH201</small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="course_name" class="col-sm-3 col-form-label">Course Name <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="course_name" name="course_name" 
                                       value="<?php echo htmlspecialchars($_POST['course_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="description" class="col-sm-3 col-form-label">Description</label>
                            <div class="col-sm-9">
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="lecturer_id" class="col-sm-3 col-form-label">Lecturer <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select class="form-control" id="lecturer_id" name="lecturer_id" required>
                                    <option value="">-- Select Lecturer --</option>
                                    <?php
                                    // Fetch all active lecturers
                                    try {
                                        // First, check if the users table exists and has the expected columns
                                        $tableCheck = $db->query("SHOW TABLES LIKE 'users'")->fetch();
                                        if (!$tableCheck) {
                                            throw new Exception('Users table does not exist');
                                        }
                                        
                                        // Try to get column names
                                        $columns = [];
                                        $columnStmt = $db->query("SHOW COLUMNS FROM users");
                                        while ($row = $columnStmt->fetch(PDO::FETCH_ASSOC)) {
                                            $columns[] = $row['Field'];
                                        }
                                        
                                        // Build query based on available columns
                                        $idField = in_array('id', $columns) ? 'id' : 'user_id';
                                        $firstField = in_array('firstname', $columns) ? 'firstname' : 'first_name';
                                        $lastField = in_array('lastname', $columns) ? 'lastname' : 'last_name';
                                        
                                        $query = "SELECT $idField as id, $firstField as firstname, $lastField as lastname 
                                                FROM users 
                                                WHERE role = 'lecturer' 
                                                ORDER BY $lastField, $firstField";
                                        
                                        $lecturerStmt = $db->query($query);
                                        $lecturers = $lecturerStmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (empty($lecturers)) {
                                            // If no lecturers found, show all users for debugging
                                            $allUsers = $db->query("SELECT * FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                                            error_log('Sample users in database: ' . print_r($allUsers, true));
                                            echo '<option value="">-- No lecturers found in database --</option>';
                                            
                                            // Debug: Show available columns
                                            echo '<!-- Available columns: ' . implode(', ', $columns) . ' -->';
                                        } else {
                                            foreach ($lecturers as $lecturer) {
                                                $selected = (isset($_POST['lecturer_id']) && $_POST['lecturer_id'] == $lecturer['id']) ? 'selected' : '';
                                                echo sprintf(
                                                    '<option value="%d" %s>%s %s</option>',
                                                    $lecturer['id'],
                                                    $selected,
                                                    htmlspecialchars($lecturer['firstname'] ?? ''),
                                                    htmlspecialchars($lecturer['lastname'] ?? '')
                                                );
                                            }
                                        }
                                    } catch (Exception $e) {
                                        $errorDetails = $e->getMessage();
                                        error_log('Lecturer dropdown error: ' . $errorDetails);
                                        echo '<option value="">-- Error: ' . htmlspecialchars($errorDetails) . ' --</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Course
                                </button>
                                <a href="courses.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card glass mb-4 border-0">
                <div class="card-header py-3 bg-transparent border-bottom border-secondary">
                    <h6 class="m-0 font-weight-bold" style="color: var(--secondary-color);">Quick Tips</h6>
                </div>
                <div class="card-body text-white">
                    <h6>Course Code</h6>
                    <p>Use a short, unique code to identify the course (e.g., CS101, MATH201). This code will be used throughout the system.</p>
                    
                    <h6 class="mt-4">Course Name</h6>
                    <p>Enter the full name of the course (e.g., Introduction to Computer Science).</p>
                    
                    <h6 class="mt-4">Description</h6>
                    <p>Provide a brief description of the course content, objectives, and any other relevant information.</p>
                    
                    <h6 class="mt-4">Lecturer</h6>
                    <p>Select the lecturer who will be teaching this course. The lecturer must have an active account in the system.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
