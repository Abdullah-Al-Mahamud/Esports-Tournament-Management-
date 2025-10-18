<?php
session_start();
require_once('../db.php');

// Create the tournament if it doesn't exist
$check_tournament = $conn->prepare("SELECT id FROM tournaments WHERE tournament_name = 'Summer Championship 2025'");
$check_tournament->execute();
$tournament_exists = $check_tournament->get_result()->num_rows > 0;

if (!$tournament_exists) {
    // Get Valorant game ID
    $game_stmt = $conn->prepare("SELECT game_name FROM games WHERE game_name = 'Valorant'");
    $game_stmt->execute();
    $game_result = $game_stmt->get_result();
    
    if ($game_result->num_rows > 0) {
        $game = $game_result->fetch_assoc();
        $game_name = $game['game_name'];
        
        // Set tournament date to 2 weeks from now
        $tournament_date = date('Y-m-d H:i:s', strtotime('+2 weeks'));
        $registration_deadline = date('Y-m-d H:i:s', strtotime('+10 days'));
        
        // Create the tournament
        $stmt = $conn->prepare("INSERT INTO tournaments (tournament_name, description, game_type, entry_fee, prize_money, tournament_date, registration_deadline, slots, min_teams, max_teams, status, picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $tournament_name = 'Summer Championship 2025';
        $description = 'Join our exciting Summer Championship 2025! This tournament features intense Valorant matches with teams competing for glory and prizes. Register your team now to secure your spot in this prestigious event.';
        $entry_fee = 500.00;
        $prize_money = 10000.00;
        $slots = 16;
        $min_teams = 8;
        $max_teams = 16;
        $status = 'upcoming';
        $picture = '../assets/images/Esports Tournament.jpg';
        
        $stmt->bind_param("sssddssiiiis", $tournament_name, $description, $game_name, $entry_fee, $prize_money, $tournament_date, $registration_deadline, $slots, $min_teams, $max_teams, $status, $picture);
        $stmt->execute();
        
        // Get the tournament ID
        $tournament_id = $conn->insert_id;
    } else {
        // If Valorant game doesn't exist, create it
        $stmt = $conn->prepare("INSERT INTO games (game_name, genre, platform, number_of_players, description) VALUES (?, ?, ?, ?, ?)");
        
        $game_name = 'Valorant';
        $genre = 'Tactical FPS';
        $platform = 'PC';
        $number_of_players = 5;
        $description = 'Valorant is a free-to-play first-person tactical hero shooter developed and published by Riot Games.';
        
        $stmt->bind_param("sssss", $game_name, $genre, $platform, $number_of_players, $description);
        $stmt->execute();
        
        // Now create the tournament
        $stmt = $conn->prepare("INSERT INTO tournaments (tournament_name, description, game_type, entry_fee, prize_money, tournament_date, registration_deadline, slots, min_teams, max_teams, status, picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $tournament_name = 'Summer Championship 2025';
        $description = 'Join our exciting Summer Championship 2025! This tournament features intense Valorant matches with teams competing for glory and prizes. Register your team now to secure your spot in this prestigious event.';
        $entry_fee = 500.00;
        $prize_money = 10000.00;
        $tournament_date = date('Y-m-d H:i:s', strtotime('+2 weeks'));
        $registration_deadline = date('Y-m-d H:i:s', strtotime('+10 days'));
        $slots = 16;
        $min_teams = 8;
        $max_teams = 16;
        $status = 'upcoming';
        $picture = '../assets/images/Esports Tournament.jpg';
        
        $stmt->bind_param("sssddssiiiis", $tournament_name, $description, $game_name, $entry_fee, $prize_money, $tournament_date, $registration_deadline, $slots, $min_teams, $max_teams, $status, $picture);
        $stmt->execute();
        
        // Get the tournament ID
        $tournament_id = $conn->insert_id;
    }
} else {
    // Get the tournament ID if it already exists
    $tournament_id = $check_tournament->get_result()->fetch_assoc()['id'];
}

// Fetch tournament details
$stmt = $conn->prepare("
    SELECT t.*, g.game_name, g.number_of_players,
    (SELECT COUNT(*) FROM team_registrations WHERE tournament_id = t.id AND payment_status = 'confirmed') as confirmed_teams,
    (SELECT COUNT(*) FROM team_registrations WHERE tournament_id = t.id) as total_registrations,
    (SELECT tr.payment_status 
     FROM team_registrations tr 
     WHERE tr.tournament_id = t.id 
     AND tr.manager_email = ? 
     LIMIT 1) as user_payment_status
    FROM tournaments t 
    JOIN games g ON t.game_type = g.game_name 
    WHERE t.id = ?
");

$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$stmt->bind_param("si", $user_email, $tournament_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();

// Check if user has already registered for this tournament
$is_registered = false;
$registration_status = '';

if (isset($_SESSION['user_email'])) {
    $check_reg = $conn->prepare("
        SELECT payment_status 
        FROM team_registrations tr
        JOIN teams t ON tr.team_id = t.id
        WHERE tr.tournament_id = ? AND t.manager_email = ?
    ");
    $check_reg->bind_param("is", $tournament_id, $_SESSION['user_email']);
    $check_reg->execute();
    $reg_result = $check_reg->get_result();
    
    if ($reg_result->num_rows > 0) {
        $is_registered = true;
        $registration_status = $reg_result->fetch_assoc()['payment_status'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tournament['tournament_name']); ?> - Esports Tournament</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/tournament.css">
    <style>
        .tournament-banner {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../assets/images/Esports Tournament.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .tournament-details {
            background-color: rgba(0, 63, 84, 0.75);
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
        }
        
        .tournament-details h2 {
            color: #4FB3BF;
            margin-bottom: 20px;
        }
        
        .detail-item {
            margin-bottom: 15px;
        }
        
        .detail-item strong {
            color: #4FB3BF;
        }
        
        .registration-section {
            background-color: rgba(0, 63, 84, 0.75);
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
        }
        
        .btn-register {
            background-color: #4FB3BF;
            border: none;
            padding: 10px 25px;
            font-weight: bold;
            margin-top: 15px;
        }
        
        .btn-register:hover {
            background-color: #3a8a94;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            margin: 10px 0;
        }
        
        .status-confirmed {
            background-color: #28a745;
            color: white;
        }
        
        .status-processing {
            background-color: #17a2b8;
            color: white;
        }
        
        .status-pending {
            background-color: #ffd700;
            color: black;
        }
        
        .home-button {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            background-color: rgba(79, 179, 191, 0.7);
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .home-button:hover {
            background-color: rgba(79, 179, 191, 1);
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <a href="../index.php" class="home-button">Home</a>
    
    <div class="tournament-banner">
        <h1><?php echo htmlspecialchars($tournament['tournament_name']); ?></h1>
        <p class="lead">Compete with the best teams and win amazing prizes!</p>
    </div>
    
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <div class="tournament-details">
                    <h2>Tournament Details</h2>
                    
                    <div class="detail-item">
                        <strong>Game:</strong> <?php echo htmlspecialchars($tournament['game_name']); ?>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Description:</strong> <?php echo htmlspecialchars($tournament['description']); ?>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Tournament Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($tournament['tournament_date'])); ?>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Registration Deadline:</strong> <?php echo date('F j, Y, g:i a', strtotime($tournament['registration_deadline'])); ?>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Entry Fee:</strong> ৳<?php echo number_format($tournament['entry_fee'], 2); ?>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Prize Pool:</strong> ৳<?php echo number_format($tournament['prize_money'], 2); ?>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Players Per Team:</strong> <?php echo $tournament['number_of_players']; ?>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Status:</strong> <?php echo ucfirst($tournament['status']); ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="registration-section">
                    <h2>Registration</h2>
                    
                    <div class="detail-item">
                        <strong>Available Slots:</strong> <?php echo $tournament['slots'] - $tournament['confirmed_teams']; ?> of <?php echo $tournament['slots']; ?>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Registered Teams:</strong> <?php echo $tournament['total_registrations']; ?>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Confirmed Teams:</strong> <?php echo $tournament['confirmed_teams']; ?>
                    </div>
                    
                    <?php if ($is_registered): ?>
                        <div class="status-badge status-<?php echo $registration_status; ?>">
                            Registration <?php echo ucfirst($registration_status); ?>
                        </div>
                        <p>You have already registered for this tournament.</p>
                        <a href="tournaments.php" class="btn btn-primary">View All Tournaments</a>
                    <?php elseif ($tournament['confirmed_teams'] >= $tournament['slots']): ?>
                        <div class="status-badge status-full">Tournament Full</div>
                        <p>Sorry, all slots for this tournament have been filled.</p>
                        <a href="tournaments.php" class="btn btn-primary">View Other Tournaments</a>
                    <?php else: ?>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="registration.php?tournament_id=<?php echo $tournament_id; ?>" class="btn btn-register btn-lg">Register Now</a>
                        <?php else: ?>
                            <p>You need to be logged in to register for this tournament.</p>
                            <a href="../user/LogIn.php?redirect=tournament_registration&tournament_id=<?php echo $tournament_id; ?>" class="btn btn-register">Login to Register</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="registration-section">
                    <h2>Quick Links</h2>
                    <ul class="list-unstyled">
                        <li><a href="tournaments.php" class="text-white">All Tournaments</a></li>
                        <li><a href="fixtures & Scoreboard.php" class="text-white">Fixtures & Scoreboard</a></li>
                        <li><a href="games.php" class="text-white">Games</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li><a href="../user/profile.php" class="text-white">My Profile</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
