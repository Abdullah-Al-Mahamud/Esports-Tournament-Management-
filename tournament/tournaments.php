<?php
session_start();
require_once('../db.php');

// At the top after session_start():
if (isset($_SESSION['user_id']) && !isset($_SESSION['user_email'])) {
    // Fetch user email if not set in session
    $user_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $_SESSION['user_id']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    if ($user = $user_result->fetch_assoc()) {
        $_SESSION['user_email'] = $user['email'];
    }
}

// Fetch all tournaments with registration counts
$query = "
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
    WHERE t.status != 'completed'
    ORDER BY t.tournament_date ASC
";

$stmt = $conn->prepare($query);
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

// Get user's registered tournaments
$user_registrations = [];
if (isset($_SESSION['user_id'])) {
    // Ensure user_email is set
    if (!isset($_SESSION['user_email'])) {
        $user_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $_SESSION['user_id']);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($user = $user_result->fetch_assoc()) {
            $_SESSION['user_email'] = $user['email'];
        }
    }

    if (isset($_SESSION['user_email'])) {
        $reg_query = "SELECT tournament_id, payment_status, created_at FROM team_registrations WHERE manager_email = ?";
        $reg_stmt = $conn->prepare($reg_query);
        $reg_stmt->bind_param("s", $_SESSION['user_email']);
        $reg_stmt->execute();
        $reg_result = $reg_stmt->get_result();
        while ($row = $reg_result->fetch_assoc()) {
            $user_registrations[$row['tournament_id']] = [
                'status' => $row['payment_status'],
                'date' => $row['created_at']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournaments</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/tournament.css">
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
            margin: 5px 0;
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
        .status-rejected {
            background-color: #dc3545;
            color: white;
        }
        .status-full {
            background-color: #6c757d;
            color: white;
        }
        .slots-info {
            font-size: 0.9em;
            margin-top: 5px;
        }
        .slots-info .confirmed {
            color: #28a745;
            font-weight: bold;
        }
        .slots-info .total {
            color: #6c757d;
        }
        .tournament-status {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            margin-bottom: 10px;
            display: inline-block;
        }
        .status-upcoming { background-color: #007bff; color: white; }
        .status-registration_closed { background-color: #6c757d; color: white; }
        .status-ongoing { background-color: #28a745; color: white; }
        .status-completed { background-color: #dc3545; color: white; }
    </style>
</head>
<body>

<div class="nav-buttons mb-4">
    <a href="../index.php" class="btn btn-secondary">
        <i class="fas fa-home"></i> Home
    </a>
    <a href="fixtures & Scoreboard.php" class="btn btn-secondary">
        <i class="fas fa-calendar-alt"></i> Fixtures & Scoreboard
    </a>
    <a href="team_statistics.php" class="btn btn-secondary">
        <i class="fas fa-chart-bar"></i> Team Statistics
    </a>
</div>

<div class="container">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <h1><b>Available Tournaments</b></h1>

    <div class="card-container">
        <?php while ($tournament = $result->fetch_assoc()): ?>
            <div class="card">
                <img src="<?php echo $tournament['picture'] ?? '../assets/images/Esports Tournament.jpg'; ?>" class="card-img-top" alt="Tournament Image">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($tournament['tournament_name']); ?></h5>
                    
                    <span class="tournament-status status-<?php echo $tournament['status']; ?>">
                        <?php echo ucfirst($tournament['status']); ?>
                    </span>
                    
                    <p class="card-text">Game: <?php echo htmlspecialchars($tournament['game_name']); ?></p>
                    <p class="card-text">Entry Fee: ৳<?php echo number_format($tournament['entry_fee'], 2); ?></p>
                    <p class="card-text">Prize Pool: ৳<?php echo number_format($tournament['prize_money'], 2); ?></p>
                    <p class="card-text">Players Per Team: <?php echo $tournament['number_of_players']; ?></p>
                    
                    <div class="slots-info">
                        <span class="confirmed"><?php echo $tournament['confirmed_teams']; ?></span> confirmed / 
                        <span class="total"><?php echo $tournament['total_registrations']; ?></span> registered / 
                        <span class="total"><?php echo $tournament['slots']; ?></span> total slots
                    </div>
                    
                    <?php if ($tournament['user_payment_status']): ?>
                        <div class="status-badge status-<?php echo $tournament['user_payment_status']; ?>">
                            Registration <?php echo ucfirst($tournament['user_payment_status']); ?>
                        </div>
                    <?php elseif (isset($user_registrations[$tournament['id']])): ?>
                        <?php 
                        $status = $user_registrations[$tournament['id']]['status'];
                        $status_class = 'status-' . $status;
                        $status_text = ucfirst($status);
                        ?>
                        <div class="status-badge <?php echo $status_class; ?>">
                            Registration <?php echo $status_text; ?>
                        </div>
                    <?php elseif ($tournament['confirmed_teams'] >= $tournament['slots']): ?>
                        <div class="status-badge status-full">Tournament Full</div>
                    <?php else: ?>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="registration.php?tournament_id=<?php echo $tournament['id']; ?>" 
                               class="btn btn-primary">Register Now</a>
                        <?php else: ?>
                            <a href="../auth/login.php?redirect=tournament_registration&tournament_id=<?php echo $tournament['id']; ?>" 
                               class="btn btn-primary">Login to Register</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="features-card">
        <h2>Fixtures & Scoreboard</h2>
        <p>Check out the latest fixtures and live scoreboard updates for ongoing tournaments!</p>
        <a href="fixtures & Scoreboard.php" class="register-button">View Now</a>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="../assets/js/tournament.js"></script>

</body>
</html>

<?php

mysqli_close($conn);
?>
