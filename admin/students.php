<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

// Ensure user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Manage Students';
include 'includes/header.php';

$error_message = '';
$debug_message = '';
$student_data = [];




// Fetch data logic
try {
    $checkStudents = $conn->query("SELECT COUNT(*) as count FROM students");
    $studentCount = $checkStudents ? $checkStudents->fetch_assoc()['count'] : 0;
    
    // FILTER DISABLED (REVERTED)
    //$query = "SELECT u.*, s.student_id, s.student_number, s.program, s.year_level, s.status 
    //          FROM users u 
    //          JOIN students s ON u.user_id = s.user_id 
    //          WHERE LOWER(u.role) = 'student'
    //          ORDER BY s.student_id DESC";
              
    $query = "SELECT u.*, s.student_id, s.student_number, s.program, s.year_level, s.status 
              FROM users u 
              JOIN students s ON u.user_id = s.user_id 
              ORDER BY s.student_id DESC";
              
    $result = $conn->query($query);
    
    if (!$result) {
        $error_message = "Database Error: " . $conn->error;
    } elseif ($result->num_rows === 0) {
        // Run diagnostics
        $userCheck = $conn->query("SELECT COUNT(*) as c FROM users WHERE LOWER(role) = 'student'");
        $studentCheck = $conn->query("SELECT COUNT(*) as c FROM students");
        
        $uCount = $userCheck ? $userCheck->fetch_assoc()['c'] : 'error';
        $sCount = $studentCheck ? $studentCheck->fetch_assoc()['c'] : 'error';
        
        $debug_message = "<strong>Diagnostic Info:</strong><br>" .
                        "Users with role 'student': $uCount<br>" .
                        "Records in 'students' table: $sCount<br>" .
                        "<em>If Users > 0 and Students > 0, there is a mismatch in User IDs.</em>";
    } else {
        while ($row = $result->fetch_assoc()) {
            $student_data[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message = "System Error: " . $e->getMessage();
}
?>

<div class="container-fluid px-4">
    <!-- Toast Container for Notifications (Z-Index 9999 to sit above Modals) -->
    <div class="toast-container position-absolute top-0 end-0 p-3" style="z-index: 9999;"></div>

    <h1 class="mt-4 text-white font-weight-bold">Manage Students</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item active text-white">Students</li>
    </ol>

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger shadow-sm border-0">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($debug_message)): ?>
    <div class="alert alert-warning shadow-sm border-0">
        <h5 class="alert-heading"><i class="fas fa-lightbulb me-2"></i> No Joined Records Found</h5>
        <hr>
        <p class="mb-0"><?php echo $debug_message; ?></p>
    </div>
    <?php endif; ?>


        <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom border-secondary">
            <div class="text-white">
                <i class="fas fa-user-graduate me-1" style="color: var(--secondary-color);"></i>
                Students List
            </div>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="fas fa-plus me-1"></i> Add New Student
            </button>
        </div>
        <div class="card-body">
            <!-- Add Student Modal was here, moved to bottom -->
            <div class="table-responsive">
                <table id="studentsTable" class="table table-light table-striped table-hover mb-0 text-dark">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Program</th>
                            <th>Year Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student_data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['program']); ?></td>
                            <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                            <td><span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            <td>
                                <div class="d-flex">
                                    <button class="btn btn-sm btn-info edit-student me-1" data-id="<?php echo $row['user_id']; ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-warning toggle-status me-1" data-id="<?php echo $row['user_id']; ?>" data-status="<?php echo $row['status']; ?>" title="<?php echo $row['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>"><i class="fas <?php echo $row['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i></button>
                                    <button class="btn btn-sm btn-danger delete-student" data-id="<?php echo $row['user_id']; ?>" title="Delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content glass">
            <div class="modal-header bg-transparent border-bottom border-secondary">
                <h5 class="modal-title text-white" id="editStudentModalLabel">Edit Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editStudentForm" action="includes/student_actions.php" method="POST">
                <input type="hidden" name="action" value="update_student">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editStudentId" class="form-label">Student ID</label>
                        <input type="text" class="form-control" id="editStudentId" name="student_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="editFirstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editLastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="editLastName" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editProgram" class="form-label">Program</label>
                            <select class="form-select" id="editProgram" name="program" required>
                                <option value="">Select Program</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Software Engineering">Software Engineering</option>
                                <option value="Data Science">Data Science</option>
                                <option value="Cybersecurity">Cybersecurity</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editYearLevel" class="form-label">Year Level</label>
                            <select class="form-select" id="editYearLevel" name="year_level" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editStatus" class="form-label">Status</label>
                        <select class="form-select" id="editStatus" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                            <option value="graduated">Graduated</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                        <div class="form-text">Leave blank to keep current password</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Students Modal -->
<div class="modal fade" id="importStudentsModal" tabindex="-1" aria-labelledby="importStudentsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content glass">
            <div class="modal-header bg-transparent border-bottom border-secondary">
                <h5 class="modal-title text-white" id="importStudentsModalLabel">Import Students</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="importStudentsForm" action="includes/student_actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_students">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="csvFile" class="form-label">CSV File</label>
                        <input class="form-control" type="file" id="csvFile" name="csv_file" accept=".csv" required>
                        <div class="form-text">
                            Download the <a href="templates/student_import_template.csv" download>CSV template</a> for reference.
                            Required columns: student_id, first_name, last_name, email, program, year_level
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="sendEmail" name="send_email">
                        <label class="form-check-label" for="sendEmail">
                            Send welcome email with login credentials
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Import Students</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Page level scripts -->
<script>
$(document).ready(function() {
    // Function to show toast messages
    function showToast(message, type = 'success') {
        var toastClass = type === 'success' ? 'bg-success' : 'bg-danger';
        var toast = $(`
            <div class="toast align-items-center text-white ${toastClass}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `);
        
        $('.toast-container').append(toast);
        var bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        // Remove toast after 5 seconds
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    // Initialize DataTable (DISABLED FOR DEBUGGING)

    
    // RE-ENABLED DataTable
    var table = $('#studentsTable').DataTable({
        responsive: true,
        ordering: true,
        pageLength: 25,
        columnDefs: [
            { targets: 0, visible: false }, // Hide ID column
            { targets: 7, orderable: false, searchable: false } // Disable sorting/search on Actions
        ],
        order: [[1, 'desc']], // Sort by Student ID descending
        language: {
            emptyTable: "No students found in the system.",
            zeroRecords: "No matching students found"
        },
        dom: 'Bfrtip',
        buttons: [
            {
                text: '<i class="fas fa-file-import me-1"></i> Import Students',
                className: 'btn btn-warning btn-sm text-white',
                action: function() {
                    $('#importStudentsModal').modal('show');
                }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel me-1"></i> Export to Excel',
                className: 'btn btn-success btn-sm',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5, 6]
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print me-1"></i> Print',
                className: 'btn btn-info btn-sm',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5, 6]
                }
            }
        ]
    });
    // Remove null placeholder
    // var table = null; 



    // Add student form submission
    $('#addStudentForm').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $('#addStudentSubmit');
        
        // Basic validation (HTML5)
        if (!this.checkValidity()) {
            e.stopPropagation();
            $form.addClass('was-validated');
            return;
        }
        
        var originalBtnText = $btn.html();
        
        // Disable submit button and show loading state
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
        
        var formData = $form.serialize();
        
        $.ajax({
            url: 'includes/student_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                console.log('Add Student Response:', response);
                
                if (response.status === 'success') {
                    $('#addStudentModal').modal('hide');
                    $form[0].reset();
                    $form.removeClass('was-validated');
                    showToast(response.message, 'success');
                    
                    // Dynamic table update
                    console.log('Checking for dynamic update...', {
                        hasStudent: !!response.student,
                        tableExists: typeof table !== 'undefined',
                        tableType: typeof table
                    });
                    
                    if (response.student && typeof table !== 'undefined') {
                        console.log('Adding row to table with data:', response.student);
                        var s = response.student;
                        var statusBadge = `<span class="badge bg-${s.status == 'active' ? 'success' : 'danger'}">${s.status.charAt(0).toUpperCase() + s.status.slice(1)}</span>`;
                        var actions = `
                            <div class="d-flex">
                                <button class="btn btn-sm btn-info edit-student me-1" data-id="${s.user_id}" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-warning toggle-status me-1" data-id="${s.user_id}" data-status="${s.status}" title="${s.status === 'active' ? 'Deactivate' : 'Activate'}"><i class="fas ${s.status === 'active' ? 'fa-user-slash' : 'fa-user-check'}"></i></button>
                                <button class="btn btn-sm btn-danger delete-student" data-id="${s.user_id}" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        `;
                        
                        var newRow = table.row.add([
                            s.user_id,
                            s.student_number,
                            s.first_name + ' ' + s.last_name,
                            s.email,
                            s.program,
                            s.year_level,
                            statusBadge,
                            actions
                        ]).draw(false).node();
                        
                        console.log('Row added successfully:', newRow);
                        
                        // Highlight new row
                        $(newRow).addClass('table-success');
                        setTimeout(() => $(newRow).removeClass('table-success'), 2000);
                    } else {
                        console.warn('Falling back to page reload. Reason:', {
                            noStudent: !response.student,
                            noTable: typeof table === 'undefined'
                        });
                        // Fallback if no student data returned
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showToast('An error occurred. Check console for details.', 'error');
            },
            complete: function() {
                // Re-enable submit button
                $btn.prop('disabled', false).html(originalBtnText);
            }
        });
    });

    // Edit student button click
    $(document).on('click', '.edit-student', function() {
        var userId = $(this).data('id');
        
        // AJAX call to get student details
        $.ajax({
            url: '../ajax/get_student_details.php',
            type: 'GET',
            data: { id: userId },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    // Populate the edit form
                    $('#editUserId').val(response.data.user_id);
                    $('#editStudentId').val(response.data.student_id);
                    $('#editFirstName').val(response.data.first_name);
                    $('#editLastName').val(response.data.last_name);
                    $('#editEmail').val(response.data.email);
                    $('#editProgram').val(response.data.program);
                    $('#editYearLevel').val(response.data.year_level);
                    $('#editStatus').val(response.data.status);
                    
                    // Show the modal
                    $('#editStudentModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while fetching student details.');
            }
        });
    });

    // Toggle student status
    $(document).on('click', '.toggle-status', function() {
        var userId = $(this).data('id');
        var currentStatus = $(this).data('status');
        var newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        
        if (confirm('Are you sure you want to ' + (newStatus === 'active' ? 'activate' : 'deactivate') + ' this student?')) {
            $.ajax({
                url: 'includes/student_actions.php',
                type: 'POST',
                data: {
                    action: 'update_student_status',
                    user_id: userId,
                    status: newStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('An error occurred while updating student status.');
                }
            });
        }
    });

    // Delete student button click
    $(document).on('click', '.delete-student', function() {
        var userId = $(this).data('id');
        
        if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
            $.ajax({
                url: 'includes/student_actions.php',
                type: 'POST',
                data: {
                    action: 'delete_student',
                    user_id: userId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('An error occurred while deleting the student.');
                }
            });
        }
    });

                    var errorMessage = response.message || 'An unknown error occurred';
                    console.error('Error adding student:', errorMessage);
                    
                    var toast = $('<div class="toast align-items-center text-white bg-danger" role="alert" aria-live="assertive" aria-atomic="true">' +
                        '<div class="d-flex">' +
                        '<div class="toast-body">Error: ' + errorMessage + '</div>' +
                        '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
                        '</div></div>');
                    
                    $('.toast-container').append(toast);
                    var bsToast = new bootstrap.Toast(toast[0]);
                    bsToast.show();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                
                var toast = $('<div class="toast align-items-center text-white bg-danger" role="alert" aria-live="assertive" aria-atomic="true">' +
                    '<div class="d-flex">' +
                    '<div class="toast-body">An error occurred while adding the student. Please check the console for details.</div>' +
                    '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
                    '</div></div>');
                
                $('.toast-container').append(toast);
                var bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();
            },
            complete: function() {
                // Re-enable submit button and restore original text
                $submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    });

    // Edit student form submission
    $('#editStudentForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'includes/student_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#editStudentModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while updating the student.');
            }
        });
    });

    // Import students form submission
    $('#importStudentsForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        
        $.ajax({
            url: 'includes/student_actions.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#importStudentsModal').modal('hide');
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while importing students.');
            }
        });
    });
});
</script>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content glass text-dark">
            <div class="modal-header bg-transparent border-bottom border-secondary">
                <h5 class="modal-title" id="addStudentModalLabel">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addStudentForm">
                <input type="hidden" name="action" value="add_student">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Student ID / Reg Number</label>
                        <input type="text" class="form-control" id="student_id" name="student_id" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="program" class="form-label">Program</label>
                            <select class="form-select" id="program" name="program" required>
                                <option value="">Select Program</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Software Engineering">Software Engineering</option>
                                <option value="Data Science">Data Science</option>
                                <option value="Cybersecurity">Cybersecurity</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="year_level" class="form-label">Year Level</label>
                            <select class="form-select" id="year_level" name="year_level" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="position: relative; z-index: 1060; pointer-events: auto;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="addStudentSubmit">Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Page level scripts - Moved AFTER footer to ensure jQuery is loaded -->

