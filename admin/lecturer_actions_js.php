<!-- Lecturer Actions JavaScript -->
<script>
// Function to show toast notifications
function showToast(type, message) {
    // Create toast container if it doesn't exist
    if ($('#toast-container').length === 0) {
        $('body').append(`
            <div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 1100;">
                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="5000">
                    <div class="toast-header">
                        <strong class="mr-auto">Notification</strong>
                        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="toast-body"></div>
                </div>
            </div>
        `);
    }
    
    // Set toast content and show
    const $toast = $('#toast-container .toast');
    $toast.removeClass('bg-success bg-danger')
          .addClass(type === 'success' ? 'bg-success text-white' : 'bg-danger text-white');
    $toast.find('.toast-body').html(message);
    $toast.toast('show');
}
// Handle add lecturer form submission
$('#addLecturerForm').on('submit', function(e) {
    e.preventDefault();
    
    // Show loading state
    var submitBtn = $(this).find('button[type="submit"]');
    var originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
    
    // Get form data and add action parameter
    var formData = $(this).serialize();
    formData += '&action=add_lecturer';
    
    $.ajax({
        url: 'includes/lecturer_actions.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Show success message
                alert('Lecturer added successfully!');
                // Close the modal
                $('#addLecturerModal').modal('hide');
                // Reload the page to show the new lecturer
                location.reload();
            } else {
                // Show error message in a more user-friendly way
                showToast('error', response.message || 'An error occurred. Please try again.');
                // Re-enable the submit button
                submitBtn.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            // Show error message in a more user-friendly way
            let errorMessage = 'An error occurred. Please try again.';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                console.error('Error parsing response:', e);
            }
            showToast('error', errorMessage);
            console.error('Error:', xhr.responseText);
            // Re-enable the submit button
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
});
</script>
