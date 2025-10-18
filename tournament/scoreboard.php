<?php

include('config.php');

$query = "SELECT * FROM matches WHERE status = 'ongoing'"; // Adjust as needed
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Scoreboard</title>
    <link rel="stylesheet" href="style.css">
    <script>
       
        setInterval(function() {
            location.reload();
        }, 5000); 
    </script>
</head>
<body>
    <header>
        <?php include('header.php'); ?>
    </header>
    
    <section id="scoreboard">
        <h2>Ongoing Tournament Scores</h2>
        <table>
            <tr>
                <th>Match</th>
                <th>Team A</th>
                <th>Score</th>
                <th>Team B</th>
            </tr>
            <?php while ($match = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars($match['match_name']); ?></td>
                <td><?php echo htmlspecialchars($match['team_a']); ?></td>
                <td><?php echo htmlspecialchars($match['score_a']) . ' - ' . htmlspecialchars($match['score_b']); ?></td>
                <td><?php echo htmlspecialchars($match['team_b']); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </section>
</body>
</html>
