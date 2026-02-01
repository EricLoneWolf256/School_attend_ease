        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Footer -->
    <footer class="sticky-footer glass border-0 mt-auto">
        <div class="container my-auto">
            <div class="copyright text-center my-auto text-white">
                <span>Copyright &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</span>
            </div>
        </div>
    </footer>
    <!-- End of Footer -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content glass border-0 shadow-lg" style="background: rgba(255, 255, 255, 0.9) !important; backdrop-filter: blur(20px);">
                <div class="modal-header border-bottom border-secondary border-opacity-25 py-3">
                    <h5 class="modal-title text-dark font-weight-bold" id="exampleModalLabel">
                        <i class="fas fa-sign-out-alt me-2 text-primary"></i>Ready to Leave?
                    </h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" data-toggle="modal" data-target="#logoutModal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 text-dark text-center">
                    <p class="mb-0">Select "Logout" below if you are ready to end your current session.</p>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-10 py-3 d-flex justify-content-center">
                    <button class="btn btn-sm px-4 fw-bold text-muted border-0" type="button" data-bs-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" href="../logout.php" id="confirmLogout">Logout</a>
                </div>
            </div>
        </div>
    </div>


    <!-- Page level plugins -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

    <!-- Custom scripts -->
    <script>
        // Check if Bootstrap is loaded
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap 5 is not loaded!');
            }
        });
    </script>

    <script>
    // Handle lecturer assignment form submission
    $(document).ready(function() {
        // Function to show alert messages
        function showAlert(container, message, type = 'danger') {
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            const alertClass = type === 'success' ? 'bg-success text-white border-0' : 'bg-danger text-white border-0';
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show glass shadow-sm" role="alert">
                    <i class="fas ${icon} mr-2"></i> ${message}
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
            
            container.html(alertHtml).show();
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                container.fadeOut();
            }, 5000);
        }

        // Initialize Select2 if available
        if ($.fn.select2) {
            $('.select2').select2({
                placeholder: 'Search for a lecturer...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#assignLecturerModal')
            });
        }

        // Handle click on select lecturer button
        $('.select-lecturer').on('click', function() {
            const lecturerId = $(this).data('id');
            const lecturerName = $(this).data('name');
            
            // Set the select value
            $('#lecturer_id').val(lecturerId).trigger('change');
            
            // Show success message
            const alert = $('#assignLecturerAlert');
            alert.removeClass('alert-danger').addClass('alert-success')
                 .html(`<i class="fas fa-check-circle"></i> Selected lecturer: <strong>${lecturerName}</strong>`)
                 .fadeIn();
                 
            // Scroll to the select field
            $('html, body').animate({
                scrollTop: $('#lecturer_id').offset().top - 100
            }, 500);
        });

        // Lecturer assignment form submission
        $('body').on('submit', '#assignLecturerForm', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            const modal = $('#assignLecturerModal');
            const alertContainer = $('#assignLecturerAlert');
            
            // Reset and show alert container
            alertContainer.hide().empty();
            
            // Basic client-side validation
            const lecturerId = form.find('[name="lecturer_id"]').val();
            if (!lecturerId) {
                showAlert(alertContainer, 'Please select a lecturer.', 'danger');
                return false;
            }
            
            // Disable submit button and show loading state
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Assigning...');
            
            // Get form data
            const formData = form.serialize();
            const courseId = form.find('input[name="id"]').val();
            
            // Submit form via AJAX
            $.ajax({
                url: 'edit_course.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        // Show success message
                        showAlert(alertContainer, response.message || 'Lecturer assigned successfully!', 'success');
                        
                        // Close modal and reload page after a short delay
                        setTimeout(function() {
                            modal.modal('hide');
                            location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        const errorMsg = (response && response.message) || 'Failed to assign lecturer. Please try again.';
                        showAlert(alertContainer, errorMsg, 'danger');
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    let errorMsg = 'An error occurred while processing your request. Please try again.';
                    
                    try {
                        // Try to parse the response as JSON
                        let response;
                        try {
                            response = JSON.parse(xhr.responseText);
                        } catch (e) {
                            response = { message: xhr.responseText };
                        }
                        
                        if (response && response.message) {
                            errorMsg = response.message;
                        } else if (xhr.status === 0) {
                            errorMsg = 'Unable to connect to the server. Please check your internet connection.';
                        } else if (xhr.status === 500) {
                            errorMsg = 'Server error occurred. Please try again later.';
                        }
                    } catch (e) {
                        console.error('Error parsing error response:', e);
                    }
                    
                    showAlert(alertContainer, errorMsg, 'danger');
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            });
        });
        
        // Reset form when modal is hidden
        $('#assignLecturerModal').on('hidden.bs.modal', function () {
            const form = $(this).find('form')[0];
            if (form) form.reset();
            if ($.fn.select2) {
                $('.select2').val(null).trigger('change');
            }
            $(this).find('.alert').remove();
            $('#assignLecturerAlert').empty().hide();
        });
    });
    </script>

</body>
</html>
