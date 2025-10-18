<?php
session_start();
require_once '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_email']) || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_id']) && isset($_POST['payment_status'])) {
    try {
        $conn->begin_transaction();

        $registration_id = $_POST['registration_id'];
        $new_status = $_POST['payment_status'];

        // Log the update attempt
        error_log("Attempting to update payment status for registration ID: $registration_id to status: $new_status");

        // Get tournament information first
        $tournament_query = "SELECT tr.tournament_id, t.slots, t.status,
            (SELECT COUNT(*) FROM team_registrations 
             WHERE tournament_id = tr.tournament_id 
             AND payment_status = 'confirmed') as confirmed_teams
            FROM team_registrations tr
            JOIN tournaments t ON tr.tournament_id = t.id
            WHERE tr.id = ?
            FOR UPDATE";
        
        $tournament_stmt = $conn->prepare($tournament_query);
        $tournament_stmt->bind_param("i", $registration_id);
        $tournament_stmt->execute();
        $tournament_result = $tournament_stmt->get_result();
        $tournament_data = $tournament_result->fetch_assoc();

        if (!$tournament_data) {
            throw new Exception("Tournament data not found for registration ID: $registration_id");
        }

        error_log("Tournament data retrieved: " . print_r($tournament_data, true));

        // Update payment status
        $update_query = "UPDATE team_registrations SET payment_status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $registration_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update payment status: " . $stmt->error);
        }

        error_log("Payment status updated successfully");

        // Update tournament status based on confirmed registrations
        if ($new_status === 'confirmed') {
            // Recount confirmed teams after the update
            $count_query = "SELECT COUNT(*) as confirmed_count 
                          FROM team_registrations 
                          WHERE tournament_id = ? 
                          AND payment_status = 'confirmed'";
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param("i", $tournament_data['tournament_id']);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_data = $count_result->fetch_assoc();
            $confirmed_count = $count_data['confirmed_count'];

            error_log("Current confirmed count: $confirmed_count, Total slots: {$tournament_data['slots']}");
            
            if ($confirmed_count >= $tournament_data['slots']) {
                // All slots filled with confirmed payments
                $update_tournament = "UPDATE tournaments SET status = 'registration_closed' WHERE id = ?";
                $tournament_stmt = $conn->prepare($update_tournament);
                $tournament_stmt->bind_param("i", $tournament_data['tournament_id']);
                
                if (!$tournament_stmt->execute()) {
                    throw new Exception("Failed to update tournament status: " . $tournament_stmt->error);
                }
                error_log("Tournament status updated to registration_closed");
            }
        }

        $conn->commit();
        error_log("Transaction committed successfully");
        $success_message = "Payment status updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in payment update: " . $e->getMessage());
        $error_message = "Error updating payment status: " . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all registrations with user and tournament details
$query = "SELECT tr.*, t.tournament_name, t.entry_fee, t.slots, t.status,
    u.name as username,
    (SELECT COUNT(*) FROM team_registrations 
     WHERE tournament_id = tr.tournament_id 
     AND payment_status = 'confirmed') as filled_slots
    FROM team_registrations tr
    JOIN tournaments t ON tr.tournament_id = t.id
    JOIN users u ON tr.manager_email = u.email
    ORDER BY tr.created_at DESC";

$result = $conn->query($query);
if (!$result) {
    error_log("Error fetching registrations: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Admin Panel</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .payment-status-pending { color: #ffc107; }
        .payment-status-confirmed { color: #28a745; }
        .payment-status-rejected { color: #dc3545; }
        .payment-status-processing { color: #17a2b8; }
        .tournament-status {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        .status-upcoming { background-color: #007bff; color: white; }
        .status-registration_closed { background-color: #6c757d; color: white; }
        .status-ongoing { background-color: #28a745; color: white; }
        .status-completed { background-color: #dc3545; color: white; }
    </style>
</head>
<body class="bg-dark text-light">
    <div class="container mt-4">
        <h2><i class="fas fa-money-bill"></i> Manage Payments</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-dark">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Tournament</th>
                        <th>Entry Fee</th>
                        <th>Registration Date</th>
                        <th>Tournament Status</th>
                        <th>Slots (Filled/Total)</th>
                        <th>Payment Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['tournament_name']); ?></td>
                            <td>৳<?php echo number_format($row['entry_fee'], 2); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></td>
                            <td>
                                <span class="tournament-status status-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $row['filled_slots']; ?>/<?php echo $row['slots']; ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="registration_id" value="<?php echo $row['id']; ?>">
                                    <select name="payment_status" class="form-control form-control-sm d-inline-block w-auto">
                                        <option value="pending" <?php echo $row['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $row['payment_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="confirmed" <?php echo $row['payment_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="rejected" <?php echo $row['payment_status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                </form>
                            </td>
                            <td>
                                <span class="payment-status-<?php echo $row['payment_status']; ?>">
                                    <i class="fas fa-circle"></i>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <a href="admin.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Admin Panel
        </a>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
