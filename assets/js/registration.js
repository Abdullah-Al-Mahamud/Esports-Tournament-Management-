$(document).ready(function() {
    // Form validation
    $('#registrationForm').on('submit', function(e) {
        e.preventDefault();
        
        let isValid = true;
        const requiredFields = ['team_name', 'manager_name', 'manager_phone', 'manager_email'];
        
        // Basic validation
        requiredFields.forEach(field => {
            if (!$(`#${field}`).val()) {
                isValid = false;
                $(`#${field}`).addClass('is-invalid');
            } else {
                $(`#${field}`).removeClass('is-invalid');
            }
        });
        
        // Validate players
        const requiredPlayers = parseInt($('input[name="required_players"]').val());
        let playerCount = 0;
        $('.player-input').each(function() {
            if ($(this).val()) playerCount++;
        });
        
        if (playerCount !== requiredPlayers) {
            isValid = false;
            Swal.fire({
                title: 'Error!',
                text: `You must add exactly ${requiredPlayers} players.`,
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        if (isValid) {
            const formData = new FormData(this);
            
            $.ajax({
                url: 'process_registration.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: result.message,
                                icon: 'success',
                                confirmButtonText: 'Proceed to Payment'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = result.redirect;
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: result.message || 'Registration failed. Please try again.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        Swal.fire({
                            title: 'Error!',
                            text: 'An unexpected error occurred. Please try again.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while processing your request. Please try again later.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
    });
    
    // Payment method toggle
    $('input[name="payment_method"]').change(function() {
        $('.payment-fields').hide();
        $(`#${$(this).val()}_fields`).show();
    });
    
    // Dynamic player fields
    let playerCount = 1;
    $('#add_player').click(function() {
        const requiredPlayers = parseInt($('input[name="required_players"]').val());
        if (playerCount < requiredPlayers) {
            const newPlayer = `
                <div class="player-item">
                    <div class="form-group">
                        <label>Player ${playerCount + 1} Name</label>
                        <input type="text" class="form-control player-input" name="players[]" required>
                    </div>
                </div>
            `;
            $('#player_list').append(newPlayer);
            playerCount++;
        }
        
        if (playerCount >= requiredPlayers) {
            $(this).prop('disabled', true);
        }
    });
});
