// Handle add lecturer form submission
$('#addLecturerForm').on('submit', function(e) {
    e.preventDefault();
    
    // Show loading state
    var submitBtn = $(this).find('button[type="submit"]');
    var originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
    
    $.ajax({
        url: 'includes/lecturer_actions.php',
        type: 'POST',
        data: $(this).serialize(),
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
                // Show error message
                alert('Error: ' + response.message);
                // Re-enable the submit button
                submitBtn.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            // Show error message
            alert('An error occurred: ' + error);
            console.error('Error:', xhr.responseText);
            // Re-enable the submit button
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
});
