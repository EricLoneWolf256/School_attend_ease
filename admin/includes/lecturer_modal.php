<!-- Add Lecturer Modal -->
<div class="modal fade" id="addLecturerModal" tabindex="-1" aria-labelledby="addLecturerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLecturerModalLabel">Add New Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addLecturerForm" action="includes/lecturer_actions.php" method="POST">
                <input type="hidden" name="action" value="add_lecturer">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="firstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="firstName" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="lastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="lastName" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Lecturer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Lecturer Modal -->
<div class="modal fade" id="editLecturerModal" tabindex="-1" aria-labelledby="editLecturerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLecturerModalLabel">Edit Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editLecturerForm" action="includes/lecturer_actions.php" method="POST">
                <input type="hidden" name="action" value="update_lecturer">
                <input type="hidden" name="lecturer_id" id="editLecturerId">
                <div class="modal-body">
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
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">Username</label>
                        <input type="text" class="form-control" id="editUsername" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
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

<!-- Assign Course Modal -->
<div class="modal fade" id="assignCourseModal" tabindex="-1" aria-labelledby="assignCourseModalLabel" aria-hidden="true">
    <style>
        #courseSelect, #courseSelect option {
            color: #000000 !important;
            background-color: #ffffff !important;
        }
        #courseSelect option:hover,
        #courseSelect option:focus,
        #courseSelect option:checked {
            background-color: #f0f0f0 !important;
            color: #000000 !important;
        }
    </style>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignCourseModalLabel">Assign Course to Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignCourseForm" action="includes/lecturer_actions.php" method="POST">
                <input type="hidden" name="action" value="assign_course">
                <input type="hidden" name="lecturer_id" id="assignLecturerId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="courseSelect" class="form-label">Select Course</label>
                        <select class="form-select" id="courseSelect" name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php
                            $query = "SELECT course_id, course_code, course_name FROM courses ORDER BY course_code";
                            $result = mysqli_query($conn, $query);
                            while ($course = mysqli_fetch_assoc($result)) {
                                echo "<option value='" . $course['course_id'] . "'>" . 
                                     htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) . 
                                     "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Assign Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Courses Modal -->
<div class="modal fade" id="viewCoursesModal" tabindex="-1" aria-labelledby="viewCoursesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCoursesModalLabel">Assigned Courses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="assignedCoursesList">
                    <!-- Courses will be loaded here via AJAX -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Form Submission Scripts -->
<script>
// Handle add lecturer form submission
$('#addLecturerForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'includes/lecturer_actions.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $('#addLecturerModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('An error occurred. Please try again.');
        }
    });
});

// Handle edit lecturer form submission
$('#editLecturerForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'includes/lecturer_actions.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $('#editLecturerModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('An error occurred. Please try again.');
        }
    });
});

// Handle assign course form submission
$('#assignCourseForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'includes/lecturer_actions.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $('#assignCourseModal').modal('hide');
                loadAssignedCourses($('#assignLecturerId').val());
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('An error occurred. Please try again.');
        }
    });
});

// Handle assign course button click
$('.assign-course').click(function() {
    var lecturerId = $(this).data('id');
    $('#assignLecturerId').val(lecturerId);
    $('#assignCourseModal').modal('show');
});

// Load assigned courses when view courses button is clicked
$('.view-courses').click(function() {
    var lecturerId = $(this).data('id');
    $('#viewCoursesModal').modal('show');
    loadAssignedCourses(lecturerId);
});

// Function to load assigned courses
function loadAssignedCourses(lecturerId) {
    $.ajax({
        url: 'ajax/get_lecturer_courses.php',
        type: 'GET',
        data: { lecturer_id: lecturerId },
        success: function(response) {
            $('#assignedCoursesList').html(response);
        },
        error: function() {
            $('#assignedCoursesList').html('<div class="alert alert-danger">Failed to load courses. Please try again.</div>');
        }
    });
}

// Handle remove course assignment
$(document).on('click', '.remove-assignment', function() {
    if (confirm('Are you sure you want to remove this course assignment?')) {
        var assignmentId = $(this).data('id');
        
        $.ajax({
            url: 'includes/lecturer_actions.php',
            type: 'POST',
            data: {
                action: 'remove_course_assignment',
                assignment_id: assignmentId
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    loadAssignedCourses($('#assignLecturerId').val());
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    }
});
</script>
