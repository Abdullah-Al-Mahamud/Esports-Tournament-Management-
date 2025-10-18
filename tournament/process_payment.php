<?php
session_start();
require_once('../db.php');

function sendJsonResponse($success, $message, $redirect = '') {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect
    ]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'Please login first', '../auth/login.php');
}

if (!isset($_POST['registration_id']) || !isset($_POST['payment_method'])) {
    sendJsonResponse(false, 'Invalid request', 'tournaments.php');
}

$registration_id = $_POST['registration_id'];
$payment_method = $_POST['payment_method'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Verify registration belongs to user
    $check_stmt = $conn->prepare("SELECT * FROM team_registrations WHERE id = ? AND manager_email = ?");
    $check_stmt->bind_param("is", $registration_id, $_SESSION['user_email']);
    $check_stmt->execute();
    $registration = $check_stmt->get_result()->fetch_assoc();

    if (!$registration) {
        throw new Exception('Invalid registration');
    }

    // Process payment based on method
    $payment_status = 'pending';
    $payment_details = [];
    
    if ($payment_method === 'cash') {
        $payment_status = 'processing';
        $payment_details = ['note' => 'Cash payment to be collected at office'];
    } elseif ($payment_method === 'bkash') {
        if (empty($_POST['bkash_number']) || empty($_POST['bkash_transaction'])) {
            throw new Exception('Please provide bKash number and transaction ID');
        }
        $payment_details = [
            'number' => $_POST['bkash_number'],
            'transaction' => $_POST['bkash_transaction']
        ];
    } elseif ($payment_method === 'nagad') {
        if (empty($_POST['nagad_number']) || empty($_POST['nagad_transaction'])) {
            throw new Exception('Please provide Nagad number and transaction ID');
        }
        $payment_details = [
            'number' => $_POST['nagad_number'],
            'transaction' => $_POST['nagad_transaction']
        ];
    } elseif ($payment_method === 'card') {
        if (empty($_POST['card_number']) || empty($_POST['card_expiry']) || empty($_POST['card_cvv'])) {
            throw new Exception('Please provide all card details');
        }
        $payment_details = [
            'card_number' => substr($_POST['card_number'], -4), // Store only last 4 digits
            'expiry' => $_POST['card_expiry']
        ];
    } else {
        throw new Exception('Invalid payment method');
    }
    
    // Update payment details
    $update_stmt = $conn->prepare("
        UPDATE team_registrations 
        SET payment_method = ?,
            payment_details = ?,
            payment_status = ?,
            payment_date = NOW() 
        WHERE id = ?
    ");
    
    $payment_json = json_encode($payment_details);
    $update_stmt->bind_param("sssi", $payment_method, $payment_json, $payment_status, $registration_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to process payment');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Set success message and return JSON response
    $message = 'Payment processed successfully! ';
    if ($payment_method === 'cash') {
        $message .= 'Please visit our office to complete the cash payment.';
    } else {
        $message .= 'We will verify your payment and update your registration status.';
    }

    $_SESSION['success'] = $message;
    sendJsonResponse(true, $message, 'tournaments.php');
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    sendJsonResponse(false, $e->getMessage());
}

$conn->close();
