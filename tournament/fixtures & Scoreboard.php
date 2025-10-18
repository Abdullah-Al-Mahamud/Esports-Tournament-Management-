<?php
session_start();
require_once '../db.php';

// Function to generate fixtures for a tournament
function generateFixtures($conn, $tournament_id) {
    // Get confirmed teams for this tournament
    $teams_query = "SELECT tr.id, t.team_name 
                   FROM team_registrations tr 
                   JOIN teams t ON tr.team_id = t.id 
                   WHERE tr.tournament_id = ? AND tr.payment_status = 'confirmed' 
                   ORDER BY RAND()";
    $teams_stmt = $conn->prepare($teams_query);
    $teams_stmt->bind_param("i", $tournament_id);
    $teams_stmt->execute();
    $teams_result = $teams_stmt->get_result();
    
    if ($teams_result->num_rows < 4) {
        return ['success' => false, 'message' => 'Need at least 4 teams to generate fixtures'];
    }
    
    // Get tournament details
    $tournament_query = "SELECT tournament_name, tournament_date FROM tournaments WHERE id = ?";
    $tournament_stmt = $conn->prepare($tournament_query);
    $tournament_stmt->bind_param("i", $tournament_id);
    $tournament_stmt->execute();
    $tournament = $tournament_stmt->get_result()->fetch_assoc();
    
    $teams = [];
    while ($team = $teams_result->fetch_assoc()) {
        $teams[] = $team;
    }
    
    $team_count = count($teams);
    $rounds = [];
    $match_number = 1;
    
    // Determine tournament structure based on team count
    if ($team_count >= 8) {
        $rounds = ['quarter_final', 'semi_final', 'final'];
        if ($team_count >= 16) {
            array_unshift($rounds, 'round_of_16');
        }
    } else {
        $rounds = ['semi_final', 'final'];
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Clear existing fixtures for this tournament
        $clear_stmt = $conn->prepare("DELETE FROM fixtures WHERE tournament_id = ?");
        $clear_stmt->bind_param("i", $tournament_id);
        $clear_stmt->execute();
        
        // Tournament start date
        $tournament_date = new DateTime($tournament['tournament_date']);
        
        // Generate fixtures for each round
        $fixtures_count = 0;
        $current_round = $rounds[0];
        $teams_in_round = $teams;
        
        foreach ($rounds as $round_index => $round) {
            $match_date = clone $tournament_date;
            // Add days based on round
            $match_date->modify("+{$round_index} days");
            
            $matches_in_round = count($teams_in_round) / 2;
            
            // For the first round, use actual teams
            if ($round_index == 0) {
                for ($i = 0; $i < $matches_in_round; $i++) {
                    $team1_id = $teams_in_round[$i * 2]['id'];
                    $team2_id = $teams_in_round[$i * 2 + 1]['id'];
                    
                    // Add 2 hours for each match in the same day
                    $match_time = clone $match_date;
                    $match_time->modify("+{$i} hours");
                    
                    $fixture_stmt = $conn->prepare("INSERT INTO fixtures 
                        (tournament_id, team1_id, team2_id, match_date, round, match_number, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $fixture_stmt->bind_param("iiissi", $tournament_id, $team1_id, $team2_id, 
                                            $match_time->format('Y-m-d H:i:s'), $round, $match_number);
                    $fixture_stmt->execute();
                    $match_number++;
                    $fixtures_count++;
                }
            } else {
                // For subsequent rounds, create placeholder matches
                $previous_round_matches = $matches_in_round * 2;
                for ($i = 0; $i < $previous_round_matches / 2; $i++) {
                    // Add 3 hours for each match in the same day
                    $match_time = clone $match_date;
                    $match_time->modify("+{$i} hours");
                    
                    $fixture_stmt = $conn->prepare("INSERT INTO fixtures 
                        (tournament_id, match_date, round, match_number, status) 
                        VALUES (?, ?, ?, ?, 'pending')");
                    $fixture_stmt->bind_param("issi", $tournament_id, 
                                            $match_time->format('Y-m-d H:i:s'), $round, $match_number);
                    $fixture_stmt->execute();
                    $match_number++;
                    $fixtures_count++;
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        return ['success' => true, 'message' => "Successfully generated {$fixtures_count} fixtures"];
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        return ['success' => false, 'message' => 'Error generating fixtures: ' . $e->getMessage()];
    }
}

// Function to get tournament matches
function getTournamentMatches($conn, $tournament_id) {
    $query = "SELECT 
        f.id as fixture_id,
        f.round,
        f.match_number,
        t.tournament_name,
        tm1.team_name as team1_name,
        tm2.team_name as team2_name,
        f.match_date,
        f.team1_score,
        f.team2_score,
        f.status,
        CASE 
            WHEN f.winner_id = f.team1_id THEN tm1.team_name
            WHEN f.winner_id = f.team2_id THEN tm2.team_name
            ELSE NULL
        END as winner_team
    FROM fixtures f
    JOIN tournaments t ON f.tournament_id = t.id
    LEFT JOIN team_registrations tr1 ON f.team1_id = tr1.id
    LEFT JOIN team_registrations tr2 ON f.team2_id = tr2.id
    LEFT JOIN teams tm1 ON tr1.team_id = tm1.id
    LEFT JOIN teams tm2 ON tr2.team_id = tm2.id
    WHERE f.tournament_id = ?
    ORDER BY f.round DESC, f.match_number ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Handle fixture generation request
if (isset($_POST['generate_fixtures']) && isset($_POST['tournament_id'])) {
    $tournament_id = $_POST['tournament_id'];
    
    // Check if user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        $_SESSION['error_message'] = 'not_authorized';
    } else {
        $result = generateFixtures($conn, $tournament_id);
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: fixtures & Scoreboard.php?tournament_id=" . $tournament_id);
    exit();
}

// Get all tournaments
$tournaments_query = "SELECT t.id, t.tournament_name, t.tournament_date, t.status, t.slots,
    (SELECT COUNT(*) FROM team_registrations tr WHERE tr.tournament_id = t.id AND tr.payment_status = 'confirmed') as registered_teams,
    (SELECT COUNT(*) FROM fixtures f WHERE f.tournament_id = t.id) as has_fixtures
    FROM tournaments t 
    WHERE t.tournament_date >= CURDATE() OR 
          EXISTS (SELECT 1 FROM fixtures WHERE tournament_id = t.id)
    ORDER BY t.tournament_date ASC";
$tournaments = $conn->query($tournaments_query);

// Get matches for selected tournament
$selected_tournament = isset($_GET['tournament_id']) ? $_GET['tournament_id'] : null;

// Get tournament details and matches
if ($selected_tournament) {
    // Get tournament details
    $tournament_stmt = $conn->prepare("
        SELECT t.*, 
            (SELECT COUNT(*) FROM team_registrations WHERE tournament_id = t.id AND payment_status = 'completed') as team_count,
            (SELECT COUNT(*) FROM fixtures WHERE tournament_id = t.id) as fixture_count
        FROM tournaments t 
        WHERE t.id = ?
    ");
    $tournament_stmt->bind_param("i", $selected_tournament);
    $tournament_stmt->execute();
    $tournament_details = $tournament_stmt->get_result()->fetch_assoc();
    
    // Get matches if they exist
    $matches = getTournamentMatches($conn, $selected_tournament);
    
    // Update tournament status
    $update_status = $conn->prepare("
        UPDATE tournaments 
        SET status = CASE 
            WHEN tournament_date >= CURDATE() THEN 'upcoming'
            WHEN EXISTS (SELECT 1 FROM fixtures WHERE tournament_id = tournaments.id AND status != 'completed') THEN 'ongoing'
            ELSE 'completed'
        END 
        WHERE id = ?");
    $update_status->bind_param("i", $selected_tournament);
    $update_status->execute();
}

// Add a button to generate fixtures if tournament is selected and has no fixtures
$show_generate_button = false;
if ($selected_tournament && isset($tournament_details)) {
    $show_generate_button = ($tournament_details['fixture_count'] == 0 && $tournament_details['team_count'] >= 4);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fixtures & Scoreboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #1a1a1a;
            color: white;
            padding-top: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .tournament-select {
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
        }
        .tournament-info {
            background: rgba(0, 63, 84, 0.75);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .tournament-info h2 {
            color: #4FB3BF;
            margin-bottom: 15px;
        }
        .round {
            margin-bottom: 30px;
        }
        .round-title {
            background-color: rgba(0, 0, 0, 0.3);
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            color: #4FB3BF;
            text-transform: uppercase;
            font-weight: bold;
        }
        .match-card {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s;
        }
        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        .match-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .match-number {
            font-weight: bold;
            color: #4FB3BF;
        }
        .match-datetime {
            color: #aaa;
            font-size: 0.9em;
        }
        .vs-indicator {
            text-align: center;
            font-weight: bold;
            margin: 10px 0;
            color: #4FB3BF;
            font-size: 1.2em;
        }
        .team-name {
            padding: 12px 15px;
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .team-name:hover {
            background-color: rgba(0, 0, 0, 0.3);
        }
        .team-score {
            font-weight: bold;
            font-size: 1.2em;
            color: #4FB3BF;
        }
        .winner {
            background-color: rgba(79, 179, 191, 0.2);
            border-left: 4px solid #4FB3BF;
        }
        .match-result {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .winner-name {
            color: #4FB3BF;
            font-weight: bold;
        }
        .match-status {
            position: absolute;
            top: -10px;
            right: 10px;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.8em;
        }
        .status-pending {
            background-color: #007bff;
        }
        .status-live {
            background-color: #28a745;
            animation: pulse 2s infinite;
        }
        .status-completed {
            background-color: #6c757d;
        }
        .status-live {
            background-color: #28a745;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .no-fixtures {
            text-align: center;
            padding: 40px;
            color: rgba(255, 255, 255, 0.7);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-buttons mb-4">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Home
            </a>
        </div>

        <div class="tournament-select">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php
            $error_messages = [
                'not_authorized' => 'You must be an admin to generate fixtures.',
                'no_tournament' => 'No tournament selected.',
                'invalid_tournament' => 'Invalid tournament selected.',
                'fixtures_exist' => 'Fixtures already exist for this tournament.',
                'not_enough_teams' => 'Not enough teams registered (minimum 4 teams required).',
                'generation_failed' => 'Failed to generate fixtures. Please try again.'
            ];

            if (isset($_GET['error']) && isset($error_messages[$_GET['error']])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_messages[$_GET['error']]; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <form method="GET" class="form-inline justify-content-center mb-3">
                <select name="tournament_id" class="form-control mr-2" onchange="this.form.submit()">
                    <option value="">Select Tournament</option>
                    <?php while ($tournament = $tournaments->fetch_assoc()): 
                        $status_badge = '';
                        if ($tournament['has_fixtures'] > 0) {
                            $status_badge = ' <span class="badge badge-info">Fixtures Ready</span>';
                        } elseif ($tournament['registered_teams'] >= 4) {
                            $status_badge = ' <span class="badge badge-success">Ready to Generate</span>';
                        }
                    ?>
                        <option value="<?php echo $tournament['id']; ?>" 
                            <?php echo ($selected_tournament == $tournament['id']) ? 'selected' : ''; ?>>
                            <?php 
                            echo htmlspecialchars($tournament['tournament_name']) . 
                                 ' (' . date('M j, Y', strtotime($tournament['tournament_date'])) . 
                                 ' - ' . $tournament['registered_teams'] . '/' . $tournament['slots'] . ' teams)' .
                                 $status_badge;
                            ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>

            <?php if ($show_generate_button && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                <div class="text-center">
                    <form action="generate_fixtures.php" method="POST" class="d-inline">
                        <input type="hidden" name="tournament_id" value="<?php echo $selected_tournament; ?>">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-sitemap"></i> Generate Fixtures
                        </button>
                    </form>
                </div>
            <?php elseif ($selected_tournament && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                <?php if (isset($tournament_details) && $tournament_details['team_count'] < 4): ?>
                    <div class="alert alert-warning text-center">
                        Need at least 4 teams to generate fixtures. Current teams: <?php echo $tournament_details['team_count']; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($selected_tournament): ?>
            <?php if ($matches && $matches->num_rows > 0): ?>
                <div class="tournament-info mb-4">
                    <h2><?php echo htmlspecialchars($tournament_details['tournament_name']); ?> - Match Schedule</h2>
                    <p>View detailed team statistics and rankings: <a href="team_statistics.php?tournament_id=<?php echo $selected_tournament; ?>" class="btn btn-info btn-sm">Team Statistics</a></p>
                </div>
                
                <div class="tournament-bracket">
                    <?php
                    $rounds = ['final' => [], 'semi_final' => [], 'quarter_final' => [], 'round_of_16' => []];
                    while ($match = $matches->fetch_assoc()) {
                        $rounds[$match['round']][] = $match;
                    }
                    
                    foreach ($rounds as $round_name => $round_matches):
                        if (!empty($round_matches)):
                    ?>
                        <div class="round">
                            <h4 class="round-title">
                                <?php echo ucwords(str_replace('_', ' ', $round_name)); ?>
                            </h4>
                            <?php foreach ($round_matches as $match): ?>
                                <div class="match-card">
                                    <div class="match-status <?php echo 'status-' . $match['status']; ?>">
                                        <?php echo ucfirst($match['status']); ?>
                                    </div>
                                    <div class="match-header">
                                        <div class="match-number">Match #<?php echo $match['match_number']; ?></div>
                                        <div class="match-datetime">
                                            <?php echo date('M j, Y - g:i A', strtotime($match['match_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="team-name <?php echo ($match['winner_team'] == $match['team1_name']) ? 'winner' : ''; ?>">
                                        <?php echo $match['team1_name'] ?? 'TBD'; ?>
                                        <?php if ($match['team1_score'] !== null): ?>
                                            <span class="team-score"><?php echo $match['team1_score']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vs-indicator">VS</div>
                                    <div class="team-name <?php echo ($match['winner_team'] == $match['team2_name']) ? 'winner' : ''; ?>">
                                        <?php echo $match['team2_name'] ?? 'TBD'; ?>
                                        <?php if ($match['team2_score'] !== null): ?>
                                            <span class="team-score"><?php echo $match['team2_score']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($match['winner_team']): ?>
                                    <div class="match-result">
                                        Winner: <span class="winner-name"><?php echo $match['winner_team']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            <?php else: ?>
                <div class="no-fixtures">
                    <h3><i class="fas fa-calendar-times"></i> No fixtures available for this tournament yet</h3>
                    <?php if (isset($tournament_details)): ?>
                        <?php if ($tournament_details['team_count'] < 4): ?>
                            <p class="mt-3">Need at least 4 teams to generate fixtures. Current teams: <?php echo $tournament_details['team_count']; ?></p>
                        <?php elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                            <p class="mt-3">You can generate fixtures now that you have enough teams.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
