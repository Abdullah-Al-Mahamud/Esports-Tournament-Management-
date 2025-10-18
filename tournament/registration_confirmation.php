<?php
session_start();
require_once('../db_connect.php');

if (!isset($_SESSION['registration_success']) || !isset($_GET['team_id'])) {
    header('Location: tournaments.php');
    exit();
}

$team_id = $_GET['team_id'];

// Get registration details
$stmt = $conn->prepare("
    SELECT tr.*, t.tournament_name, t.entry_fee, t.prize_money, p.payment_method 
    FROM team_registrations tr
    JOIN tournaments t ON tr.tournament_id = t.id
    JOIN payments p ON tr.id = p.team_id
    WHERE tr.id = ?
");
$stmt->bind_param("i", $team_id);
$stmt->execute();
$registration = $stmt->get_result()->fetch_assoc();

// Get players
$stmt = $conn->prepare("SELECT player_name FROM team_players WHERE team_id = ?");
$stmt->bind_param("i", $team_id);
$stmt->execute();
$players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Clear the success message
unset($_SESSION['registration_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .confirmation-container {
            background: rgba(7, 166, 188, 0.1);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            margin: 20px auto;
            max-width: 800px;
        }
        
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .players-list {
            list-style: none;
            padding: 0;
        }
        
        .players-list li {
            padding: 5px 0;
        }
        
        .btn-group {
            margin-top: 30px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        @media print {
            .btn-group {
                display: none;
            }
            body {
                background: white !important;
            }
            .confirmation-container {
                background: white !important;
                color: black !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="confirmation-container">
            <div class="text-center">
                <i class="fas fa-check-circle success-icon"></i>
                <h2 class="mb-4">Registration Successful!</h2>
                <p class="lead mb-4">Your team has been successfully registered for <?php echo htmlspecialchars($registration['tournament_name']); ?></p>
            </div>
            
            <div class="details mt-4">
                <h4>Registration Details</h4>
                
                <div class="detail-row">
                    <span>Team Name:</span>
                    <span><?php echo htmlspecialchars($registration['team_name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span>Manager Name:</span>
                    <span><?php echo htmlspecialchars($registration['manager_name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span>Contact Number:</span>
                    <span><?php echo htmlspecialchars($registration['contact_number']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span>Tournament:</span>
                    <span><?php echo htmlspecialchars($registration['tournament_name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span>Entry Fee Paid:</span>
                    <span>$<?php echo number_format($registration['entry_fee'], 2); ?></span>
                </div>
                
                <div class="detail-row">
                    <span>Payment Method:</span>
                    <span><?php echo ucfirst(htmlspecialchars($registration['payment_method'])); ?></span>
                </div>
                
                <h5 class="mt-4">Team Players</h5>
                <ul class="players-list">
                    <?php foreach ($players as $player): ?>
                        <li><?php echo htmlspecialchars($player['player_name']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="btn-group">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <a href="tournaments.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> View Tournaments
                </a>
                <a href="../index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>
