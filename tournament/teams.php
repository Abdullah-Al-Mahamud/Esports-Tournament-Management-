<?php
require_once '../db.php';

// Get all teams with their records
$teams_query = "SELECT 
    t.*,
    COUNT(DISTINCT tr.tournament_id) as tournaments_registered,
    COUNT(DISTINCT CASE WHEN tr.payment_status = 'confirmed' THEN tr.tournament_id END) as tournaments_played
FROM teams t
LEFT JOIN team_registrations tr ON t.id = tr.team_id
GROUP BY t.id
ORDER BY t.matches_won DESC, t.matches_played DESC";

$teams_result = $conn->query($teams_query);

// Get tournament registrations for each team
$registrations_query = "SELECT 
    tr.team_id,
    t.tournament_name,
    tr.payment_status,
    t.status as tournament_status
FROM team_registrations tr
JOIN tournaments t ON tr.tournament_id = t.id
WHERE tr.status = 'active'
ORDER BY t.created_at DESC";

$registrations_result = $conn->query($registrations_query);
$team_registrations = [];

while ($reg = $registrations_result->fetch_assoc()) {
    if (!isset($team_registrations[$reg['team_id']])) {
        $team_registrations[$reg['team_id']] = [];
    }
    $team_registrations[$reg['team_id']][] = $reg;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Records - CracCloud</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .table {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .table thead th {
            background-color: rgba(0, 0, 0, 0.3);
            color: #ffd700;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.05);
        }
        .table-striped tbody tr:nth-of-type(even) {
            background-color: rgba(255, 255, 255, 0.02);
        }
        .table td, .table th {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .badge {
            padding: 5px 10px;
            border-radius: 15px;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .badge-info {
            background-color: #17a2b8;
        }
        .badge-success {
            background-color: #28a745;
        }
        .badge-secondary {
            background-color: #6c757d;
        }
        h2 {
            color: #ffd700;
            margin-bottom: 30px;
        }
        .nav-buttons {
            margin-bottom: 20px;
        }
        .btn-secondary {
            background-color: rgba(255, 255, 255, 0.1);
            border: none;
        }
        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="nav-buttons">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Home
            </a>
        </div>

        <h2><i class="fas fa-trophy"></i> Team Records</h2>
        
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Team Name</th>
                        <th>Tournaments</th>
                        <th>Matches Played</th>
                        <th>Won</th>
                        <th>Lost</th>
                        <th>Drawn</th>
                        <th>Win Rate</th>
                        <th>Recent Tournaments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($team = $teams_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                            <td>
                                <?php 
                                echo $team['tournaments_registered'] . " registered<br>";
                                echo $team['tournaments_played'] . " played";
                                ?>
                            </td>
                            <td><?php echo $team['matches_played']; ?></td>
                            <td><?php echo $team['matches_won']; ?></td>
                            <td><?php echo $team['matches_lost']; ?></td>
                            <td><?php echo $team['matches_drawn']; ?></td>
                            <td>
                                <?php
                                if ($team['matches_played'] > 0) {
                                    $win_rate = ($team['matches_won'] / $team['matches_played']) * 100;
                                    echo number_format($win_rate, 1) . '%';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (isset($team_registrations[$team['id']])): ?>
                                    <ul class="list-unstyled">
                                        <?php foreach ($team_registrations[$team['id']] as $reg): ?>
                                            <li>
                                                <?php 
                                                echo htmlspecialchars($reg['tournament_name']);
                                                if ($reg['payment_status'] === 'pending') {
                                                    echo ' <span class="badge badge-warning">Payment Pending</span>';
                                                } elseif ($reg['tournament_status'] === 'upcoming') {
                                                    echo ' <span class="badge badge-info">Upcoming</span>';
                                                } elseif ($reg['tournament_status'] === 'ongoing') {
                                                    echo ' <span class="badge badge-success">Ongoing</span>';
                                                } elseif ($reg['tournament_status'] === 'completed') {
                                                    echo ' <span class="badge badge-secondary">Completed</span>';
                                                }
                                                ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="text-muted">No tournaments</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
