<?php
session_start();
require_once '../db.php';

// Get all tournaments
$tournaments_query = "SELECT t.id, t.tournament_name, t.tournament_date, t.status, t.slots,
    (SELECT COUNT(*) FROM team_registrations tr WHERE tr.tournament_id = t.id AND tr.payment_status = 'confirmed') as registered_teams
    FROM tournaments t 
    ORDER BY t.tournament_date DESC";
$tournaments = $conn->query($tournaments_query);

// Get selected tournament
$selected_tournament = isset($_GET['tournament_id']) ? $_GET['tournament_id'] : null;
$tournament_details = null;
$teams = null;

if ($selected_tournament) {
    // Get tournament details
    $tournament_stmt = $conn->prepare("
        SELECT t.*, g.game_name, g.number_of_players
        FROM tournaments t 
        JOIN games g ON t.game_type = g.game_name
        WHERE t.id = ?
    ");
    $tournament_stmt->bind_param("i", $selected_tournament);
    $tournament_stmt->execute();
    $tournament_details = $tournament_stmt->get_result()->fetch_assoc();
    
    // Get teams registered for this tournament with their statistics
    $teams_query = "
        SELECT 
            tr.id as registration_id,
            t.id as team_id,
            t.team_name,
            tr.manager_name,
            tr.manager_email,
            tr.payment_status,
            t.matches_played,
            t.matches_won,
            t.matches_lost,
            t.matches_drawn,
            (
                SELECT COUNT(*) 
                FROM fixtures f 
                WHERE (f.team1_id = tr.id OR f.team2_id = tr.id) 
                AND f.tournament_id = ?
            ) as tournament_matches,
            (
                SELECT COUNT(*) 
                FROM fixtures f 
                WHERE f.winner_id = tr.id 
                AND f.tournament_id = ?
            ) as tournament_wins,
            (
                SELECT COUNT(*) 
                FROM fixtures f 
                WHERE (f.team1_id = tr.id OR f.team2_id = tr.id) 
                AND f.winner_id IS NOT NULL 
                AND f.winner_id != tr.id 
                AND f.tournament_id = ?
            ) as tournament_losses,
            (
                SELECT COUNT(*) 
                FROM fixtures f 
                WHERE (f.team1_id = tr.id OR f.team2_id = tr.id) 
                AND f.team1_score = f.team2_score 
                AND f.team1_score IS NOT NULL 
                AND f.tournament_id = ?
            ) as tournament_draws
        FROM team_registrations tr
        JOIN teams t ON tr.team_id = t.id
        WHERE tr.tournament_id = ? AND tr.payment_status = 'confirmed'
        ORDER BY tournament_wins DESC, tournament_draws DESC, t.team_name ASC
    ";
    
    $teams_stmt = $conn->prepare($teams_query);
    $teams_stmt->bind_param("iiiii", $selected_tournament, $selected_tournament, $selected_tournament, $selected_tournament, $selected_tournament);
    $teams_stmt->execute();
    $teams = $teams_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Statistics</title>
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
        .stats-table {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .stats-table .table {
            color: white;
            margin-bottom: 0;
        }
        .stats-table th {
            background-color: rgba(0, 0, 0, 0.3);
            border-top: none;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }
        .stats-table td {
            border-color: rgba(255, 255, 255, 0.1);
        }
        .stats-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
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
        .tournament-info p {
            margin-bottom: 10px;
        }
        .nav-buttons {
            margin-bottom: 20px;
        }
        .nav-buttons .btn {
            margin-right: 10px;
            background-color: #343a40;
            border: none;
        }
        .nav-buttons .btn:hover {
            background-color: #495057;
        }
        .no-teams {
            text-align: center;
            padding: 40px;
            color: rgba(255, 255, 255, 0.7);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        .team-rank {
            font-weight: bold;
            text-align: center;
            width: 40px;
            height: 40px;
            line-height: 40px;
            border-radius: 50%;
            display: inline-block;
        }
        .rank-1 {
            background-color: gold;
            color: black;
        }
        .rank-2 {
            background-color: silver;
            color: black;
        }
        .rank-3 {
            background-color: #cd7f32; /* bronze */
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-buttons mb-4">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="tournaments.php" class="btn btn-secondary">
                <i class="fas fa-trophy"></i> Tournaments
            </a>
            <a href="fixtures & Scoreboard.php" class="btn btn-secondary">
                <i class="fas fa-calendar-alt"></i> Fixtures
            </a>
        </div>

        <div class="tournament-select">
            <h1 class="mb-4 text-center">Team Statistics</h1>
            <form method="GET" class="form-inline justify-content-center mb-3">
                <select name="tournament_id" class="form-control mr-2" onchange="this.form.submit()">
                    <option value="">Select Tournament</option>
                    <?php while ($tournament = $tournaments->fetch_assoc()): 
                        $selected = ($selected_tournament == $tournament['id']) ? 'selected' : '';
                        
                        // Create status badge
                        $status_class = '';
                        switch ($tournament['status']) {
                            case 'upcoming': $status_class = 'badge-primary'; break;
                            case 'ongoing': $status_class = 'badge-success'; break;
                            case 'completed': $status_class = 'badge-secondary'; break;
                            case 'cancelled': $status_class = 'badge-danger'; break;
                        }
                        $status_badge = '<span class="badge ' . $status_class . ' ml-2">' . ucfirst($tournament['status']) . '</span>';
                    ?>
                        <option value="<?php echo $tournament['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($tournament['tournament_name']) . ' ' . $status_badge; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <?php if ($selected_tournament && $tournament_details): ?>
            <div class="tournament-info">
                <h2><?php echo htmlspecialchars($tournament_details['tournament_name']); ?></h2>
                <p><strong>Game:</strong> <?php echo htmlspecialchars($tournament_details['game_name']); ?></p>
                <p><strong>Players Per Team:</strong> <?php echo $tournament_details['number_of_players']; ?></p>
                <p><strong>Tournament Date:</strong> <?php echo date('F j, Y', strtotime($tournament_details['tournament_date'])); ?></p>
                <p><strong>Status:</strong> <span class="badge badge-<?php 
                    echo ($tournament_details['status'] == 'upcoming') ? 'primary' : 
                         (($tournament_details['status'] == 'ongoing') ? 'success' : 
                         (($tournament_details['status'] == 'completed') ? 'secondary' : 'danger')); 
                ?>"><?php echo ucfirst($tournament_details['status']); ?></span></p>
            </div>

            <?php if ($teams && $teams->num_rows > 0): ?>
                <div class="stats-table">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Team</th>
                                <th>Manager</th>
                                <th>Matches</th>
                                <th>Won</th>
                                <th>Lost</th>
                                <th>Drawn</th>
                                <th>Win Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            while ($team = $teams->fetch_assoc()): 
                                $matches = $team['tournament_matches'];
                                $win_rate = ($matches > 0) ? round(($team['tournament_wins'] / $matches) * 100) : 0;
                                
                                // Determine rank class
                                $rank_class = '';
                                if ($rank == 1) $rank_class = 'rank-1';
                                else if ($rank == 2) $rank_class = 'rank-2';
                                else if ($rank == 3) $rank_class = 'rank-3';
                            ?>
                                <tr>
                                    <td>
                                        <div class="team-rank <?php echo $rank_class; ?>">
                                            <?php echo $rank++; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                                    <td><?php echo htmlspecialchars($team['manager_name']); ?></td>
                                    <td><?php echo $team['tournament_matches']; ?></td>
                                    <td><?php echo $team['tournament_wins']; ?></td>
                                    <td><?php echo $team['tournament_losses']; ?></td>
                                    <td><?php echo $team['tournament_draws']; ?></td>
                                    <td><?php echo $win_rate; ?>%</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-teams">
                    <h3><i class="fas fa-users-slash"></i> No teams registered for this tournament yet</h3>
                    <p class="mt-3">Teams will appear here once they have registered and been confirmed.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
