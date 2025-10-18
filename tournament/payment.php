<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['registration_id'])) {
    header('Location: tournaments.php');
    exit();
}

$registration_id = $_GET['registration_id'];

// Get registration details
$stmt = $conn->prepare("
    SELECT tr.*, t.tournament_name, t.entry_fee, tm.team_name 
    FROM team_registrations tr
    JOIN tournaments t ON tr.tournament_id = t.id
    LEFT JOIN teams tm ON tr.team_id = tm.id
    WHERE tr.id = ? AND tr.manager_email = ?
");
$stmt->bind_param("is", $registration_id, $_SESSION['user_email']);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    header('Location: tournaments.php');
    exit();
}

$registration = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Payment</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/registration.css">
    <style>
        .payment-method-selector {
            margin-bottom: 20px;
        }
        .payment-method-selector .form-check {
            margin: 10px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
        }
        .payment-method-selector .form-check:hover {
            background-color: #f8f9fa;
        }
        .payment-method-selector .form-check-input:checked + .form-check-label {
            font-weight: bold;
        }
        .payment-fields {
            margin-top: 20px;
        }
        .registration-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .registration-info p {
            margin-bottom: 8px;
        }
        .home-button {
            position: absolute;
            top: 20px;
            left: 20px;
        }
    </style>
</head>
<body>
    <a href="../index.php" class="btn btn-primary home-button">
        <i class="fas fa-home"></i> Home
    </a>
    
    <div class="registration-container">
        <div class="registration-form">
            <h2>Payment Information</h2>
            
            <div class="registration-info">
                <p><strong>Tournament:</strong> <?php echo htmlspecialchars($registration['tournament_name']); ?></p>
                <p><strong>Team Name:</strong> <?php echo htmlspecialchars($registration['team_name']); ?></p>
                <p><strong>Manager:</strong> <?php echo htmlspecialchars($registration['manager_name']); ?></p>
                <p><strong>Registration ID:</strong> <?php echo $registration['id']; ?></p>
                <p><strong>Tournament ID:</strong> <?php echo $registration['tournament_id']; ?></p>
                <p><strong>Entry Fee:</strong> ৳<?php echo number_format($registration['entry_fee'], 2); ?></p>
            </div>

            <form id="paymentForm" action="process_payment.php" method="POST">
                <input type="hidden" name="registration_id" value="<?php echo $registration_id; ?>">
                
                <div class="payment-method-selector">
                    <div class="form-check">
                        <input type="radio" class="form-check-input" id="cash" name="payment_method" value="cash" checked>
                        <label class="form-check-label" for="cash">
                            <i class="fas fa-money-bill-wave"></i> Cash Payment
                        </label>
                    </div>
                    
                    <div class="form-check">
                        <input type="radio" class="form-check-input" id="bkash" name="payment_method" value="bkash">
                        <label class="form-check-label" for="bkash">
                            <i class="fas fa-mobile-alt"></i> bKash
                        </label>
                    </div>
                    
                    <div class="form-check">
                        <input type="radio" class="form-check-input" id="nagad" name="payment_method" value="nagad">
                        <label class="form-check-label" for="nagad">
                            <i class="fas fa-mobile-alt"></i> Nagad
                        </label>
                    </div>
                    
                    <div class="form-check">
                        <input type="radio" class="form-check-input" id="card" name="payment_method" value="card">
                        <label class="form-check-label" for="card">
                            <i class="fas fa-credit-card"></i> Credit/Debit Card
                        </label>
                    </div>
                </div>

                <div id="cash_fields" class="payment-fields">
                    <div class="alert alert-info">
                        <p><strong>Cash Payment Instructions:</strong></p>
                        <ol>
                            <li>Please bring the exact amount: ৳<?php echo number_format($registration['entry_fee'], 2); ?></li>
                            <li>Visit our office during business hours (10 AM - 6 PM)</li>
                            <li>Present your registration ID: <?php echo $registration_id; ?></li>
                        </ol>
                    </div>
                </div>

                <div id="bkash_fields" class="payment-fields" style="display:none;">
                    <div class="alert alert-info">
                        <p><strong>bKash Payment Instructions:</strong></p>
                        <ol>
                            <li>Go to your bKash Mobile Menu by dialing *247#</li>
                            <li>Choose "Send Money"</li>
                            <li>Enter the bKash Account Number: 01XXXXXXXXX</li>
                            <li>Enter amount: ৳<?php echo number_format($registration['entry_fee'], 2); ?></li>
                            <li>Enter your bKash PIN to confirm</li>
                            <li>Copy the Transaction ID and paste it below</li>
                        </ol>
                    </div>
                    <div class="form-group">
                        <label>bKash Number</label>
                        <input type="text" class="form-control" name="bkash_number" placeholder="01XXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>Transaction ID</label>
                        <input type="text" class="form-control" name="bkash_transaction" placeholder="TR1234567890">
                    </div>
                </div>

                <div id="nagad_fields" class="payment-fields" style="display:none;">
                    <div class="alert alert-info">
                        <p><strong>Nagad Payment Instructions:</strong></p>
                        <ol>
                            <li>Go to your Nagad Mobile Menu by dialing *167#</li>
                            <li>Choose "Send Money"</li>
                            <li>Enter the Nagad Account Number: 01XXXXXXXXX</li>
                            <li>Enter amount: ৳<?php echo number_format($registration['entry_fee'], 2); ?></li>
                            <li>Enter your Nagad PIN to confirm</li>
                            <li>Copy the Transaction ID and paste it below</li>
                        </ol>
                    </div>
                    <div class="form-group">
                        <label>Nagad Number</label>
                        <input type="text" class="form-control" name="nagad_number" placeholder="01XXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>Transaction ID</label>
                        <input type="text" class="form-control" name="nagad_transaction" placeholder="TR1234567890">
                    </div>
                </div>

                <div id="card_fields" class="payment-fields" style="display:none;">
                    <div class="form-group">
                        <label>Card Number</label>
                        <input type="text" class="form-control" name="card_number" placeholder="1234 5678 9012 3456">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Expiry Date</label>
                                <input type="text" class="form-control" name="card_expiry" placeholder="MM/YY">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>CVV</label>
                                <input type="text" class="form-control" name="card_cvv" placeholder="123">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-submit">Pay Now</button>
                    <a href="tournaments.php" class="btn btn-secondary ml-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        // Handle payment method selection
        $('input[name="payment_method"]').change(function() {
            $('.payment-fields').hide();
            $('#' + $(this).val() + '_fields').show();
        });

        // Handle form submission
        $('#paymentForm').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                type: 'POST',
                url: 'process_payment.php',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.redirect;
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });
    });
    </script>
</body>
</html>
