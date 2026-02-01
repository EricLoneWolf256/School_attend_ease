<?php
// This file contains the HTML for attendance-related modals
// It should be included in the lecture.php file
?>

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
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="mark_attendance" value="1">
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Feedback</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $students = getCourseStudents($db, $lecture['course_id']);
                                foreach ($students as $student): 
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td>
                                            <select name="attendance[<?php echo $student['user_id']; ?>]" class="form-control form-control-sm">
                                                <option value="present">Present</option>
                                                <option value="late">Late</option>
                                                <option value="absent" selected>Absent</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="feedback[<?php echo $student['user_id']; ?>]" class="form-control form-control-sm" placeholder="Optional feedback">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Attendance Modal -->
<div class="modal fade" id="importAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="importAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importAttendanceModalLabel">Import Attendance</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="import_file">CSV File</label>
                        <input type="file" class="form-control-file" id="import_file" name="import_file" accept=".csv" required>
                        <small class="form-text text-muted">
                            Upload a CSV file with columns: Student ID, Status, Feedback (optional)
                        </small>
                    </div>
                    <div class="mt-3">
                        <a href="#" id="downloadTemplate" class="btn btn-sm btn-outline-secondary">Download Template</a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="import_attendance" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" role="dialog" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrCodeModalLabel">Attendance Code</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <div id="qrCodeContainer" class="mb-3">
                    <!-- QR Code will be generated here by JavaScript -->
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <input type="text" class="form-control text-center font-weight-bold" id="attendanceCode" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="copyCodeBtn" data-toggle="tooltip" title="Copy to clipboard">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">Share this code with students</small>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This code will expire in <span id="codeExpiry">60:00</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="stopAttendanceBtn">
                    <i class="fas fa-stop"></i> Stop Attendance
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for attendance functionality -->
<script>
// Generate and download CSV template
document.getElementById('downloadTemplate').addEventListener('click', function(e) {
    e.preventDefault();
    
    // CSV header
    let csvContent = "Student ID,Status,Feedback\n";
    csvContent += "12345,present,On time\n";
    csvContent += "67890,late,Arrived 10 minutes late\n";
    
    // Create download link
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', 'attendance_template.csv');
    link.style.visibility = 'hidden';
    
    // Add to page, click and remove
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});

// Initialize tooltips
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});

// Copy code to clipboard
document.getElementById('copyCodeBtn').addEventListener('click', function() {
    const codeInput = document.getElementById('attendanceCode');
    codeInput.select();
    document.execCommand('copy');
    
    // Show copied tooltip
    $(this).tooltip('hide')
        .attr('data-original-title', 'Copied!')
        .tooltip('show');
    
    // Reset tooltip after 2 seconds
    setTimeout(() => {
        $(this).attr('data-original-title', 'Copy to clipboard')
            .tooltip('hide');
    }, 2000);
});

// Stop attendance button
let attendanceInterval;
function startAttendanceTimer(expiryTime) {
    const expiryDate = new Date(expiryTime).getTime();
    
    // Update the countdown every second
    attendanceInterval = setInterval(function() {
        const now = new Date().getTime();
        const distance = expiryDate - now;
        
        // If the countdown is over, stop it
        if (distance < 0) {
            clearInterval(attendanceInterval);
            document.getElementById('codeExpiry').textContent = 'Expired';
            document.getElementById('stopAttendanceBtn').textContent = 'Close';
            return;
        }
        
        // Calculate minutes and seconds
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        // Display the result
        document.getElementById('codeExpiry').textContent = 
            (minutes < 10 ? '0' : '') + minutes + ':' + 
            (seconds < 10 ? '0' : '') + seconds;
    }, 1000);
}

// Initialize QR code when modal is shown
$('#qrCodeModal').on('show.bs.modal', function (event) {
    const button = $(event.relatedTarget);
    const code = button.data('code');
    const expiresAt = button.data('expires');
    
    // Set the code in the input
    document.getElementById('attendanceCode').value = code;
    
    // Start the countdown timer
    startAttendanceTimer(expiresAt);
    
    // Generate QR code
    const qrContainer = document.getElementById('qrCodeContainer');
    qrContainer.innerHTML = ''; // Clear loading spinner
    
    // In a real implementation, you would use a QR code library like qrcode.js
    // For example: new QRCode(qrContainer, code);
    
    // For now, we'll just show the code in a styled div
    const qrCodeDiv = document.createElement('div');
    qrCodeDiv.className = 'p-3 bg-light rounded d-inline-block';
    qrCodeDiv.innerHTML = `
        <div class="text-center">
            <div class="mb-2">
                <i class="fas fa-qrcode fa-5x text-muted"></i>
            </div>
            <div class="font-weight-bold">${code}</div>
        </div>
    `;
    qrContainer.appendChild(qrCodeDiv);
});

// Clean up interval when modal is closed
$('#qrCodeModal').on('hidden.bs.modal', function () {
    clearInterval(attendanceInterval);
});

// Handle stop attendance button
$('#stopAttendanceBtn').on('click', function() {
    // In a real implementation, you would make an AJAX call to stop attendance
    // For now, we'll just close the modal
    $('#qrCodeModal').modal('hide');
    
    // Show a success message
    const toast = `
        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="5000" style="position: fixed; top: 20px; right: 20px;">
            <div class="toast-header">
                <strong class="mr-auto">Success</strong>
                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="toast-body">
                <i class="fas fa-check-circle text-success mr-2"></i>
                Attendance has been stopped successfully.
            </div>
        </div>
    `;
    
    $('body').append(toast);
    $('.toast').toast('show');
    
    // Remove toast after it's hidden
    $('.toast').on('hidden.bs.toast', function () {
        $(this).remove();
    });
});
</script>
