<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/session.php';
require_once '../includes/db_connection.php';

// Ensure user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Manage Lecturers';
include '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4 text-white font-weight-bold">Manage Lecturers</h1>
    <ol class="breadcrumb mb-4 bg-transparent border-0 p-0">
        <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
        <li class="breadcrumb-item active text-white">Lecturers</li>
    </ol>

    <div class="card mb-4 glass border-0">
        <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom border-secondary">
            <div class="text-white">
                <i class="fas fa-chalkboard-teacher me-1" style="color: var(--secondary-color);"></i>
                Lecturers List
            </div>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addLecturerModal">
                <i class="fas fa-plus me-1"></i> Add New Lecturer
            </button>
        </div>
        <div class="card-body">
            <table id="lecturersTable" class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Courses Assigned</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT u.*, COUNT(ca.course_id) as course_count 
                              FROM users u 
                              LEFT JOIN course_assignments ca ON u.user_id = ca.lecturer_id 
                              WHERE u.role = 'lecturer' 
                              GROUP BY u.user_id";
                    $result = mysqli_query($conn, $query);
                    
                    while ($lecturer = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($lecturer['user_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($lecturer['email']) . "</td>";
                        echo "<td>" . htmlspecialchars($lecturer['username']) . "</td>";
                        echo "<td>" . $lecturer['course_count'] . " courses</td>";
                        echo "<td><span class='badge bg-success'>Active</span></td>";
                        echo "<td>";
                        echo "<button class='btn btn-sm btn-info edit-lecturer' data-id='" . $lecturer['user_id'] . "' data-bs-toggle='tooltip' title='Edit'><i class='fas fa-edit'></i></button> ";
                        echo "<button class='btn btn-sm btn-warning assign-course' data-id='" . $lecturer['user_id'] . "' data-bs-toggle='tooltip' title='Assign Course'><i class='fas fa-book'></i></button> ";
                        echo "<button class='btn btn-sm btn-danger delete-lecturer' data-id='" . $lecturer['user_id'] . "' data-bs-toggle='tooltip' title='Delete'><i class='fas fa-trash'></i></button>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Include modals -->
<?php include 'includes/lecturer_modal.php'; ?>

<!-- Page level scripts -->
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#lecturersTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [6] } // Disable sorting on actions column
        ]
    });

    // Edit lecturer button click
    $('.edit-lecturer').click(function() {
        var lecturerId = $(this).data('id');
        // AJAX call to get lecturer details
        $.ajax({
            url: '../ajax/get_lecturer_details.php',
            type: 'GET',
            data: { id: lecturerId },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    $('#editLecturerId').val(response.data.user_id);
                    $('#editFirstName').val(response.data.first_name);
                    $('#editLastName').val(response.data.last_name);
                    $('#editEmail').val(response.data.email);
                    $('#editUsername').val(response.data.username);
                    $('#editLecturerModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    });

    // Delete lecturer button click
    $('.delete-lecturer').click(function() {
        if(confirm('Are you sure you want to delete this lecturer? This action cannot be undone.')) {
            var lecturerId = $(this).data('id');
            // AJAX call to delete lecturer
            $.ajax({
                url: 'includes/lecturer_actions.php',
                type: 'POST',
                data: { 
                    action: 'delete_lecturer',
                    lecturer_id: lecturerId 
                },
                success: function(response) {
                    var result = JSON.parse(response);
                    if(result.status === 'success') {
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                }
            });
        }
    });
});
</script>

<?php include 'lecturer_actions_js.php'; ?>
<?php include '../includes/footer.php'; ?>
