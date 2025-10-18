<?php
require_once '../db.php';

// Fetch all active games from the database (excluding predefined games)
$stmt = $conn->prepare("SELECT * FROM games WHERE status = 'active' AND game_name NOT IN ('Valorant', 'EA FC24') ORDER BY id DESC");
$stmt->execute();
$games = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esports Games</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-image: url('../assets/images/all_background.jpg');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            min-height: 100vh;
        }

        header {
            background-color: #3edcff5b;
            padding: 20px;
            text-align: center;
            color: white;
            position: relative;
        }

        header h1 {
            margin: 0;
        }
        .home-button {
            position: absolute;
            right: 20px;
            top: 20px;
            color: whitesmoke;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .home-button:hover{
            background: rgb(3, 118, 133);
            color: white;
            border: black;
            box-shadow: paleturquoise;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .game-section {
            display: flex;
            flex-direction: row;
            margin-bottom: 40px;
            background-color: rgba(0, 63, 84, 0.458);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .game-section:nth-child(even) {
            flex-direction: row-reverse;
        }

        .game-image {
            flex: 1;
            min-height: 400px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .game-description {
            flex: 1.5;
            padding: 20px;
        }

        .game-description h2 {
            color: #ffffff;
            margin-bottom: 10px;
        }

        .game-description p {
            color: white;
            font-size: 16px;
            line-height: 1.6;
        }

        .game-description ul {
            color: white;
            padding-left: 20px;
        }

        .game-description li {
            color: white;
            margin-bottom: 5px;
        }

        .coming-soon {
            text-align: center;
            margin: 20px 0;
            font-size: 18px;
            color: white;
            background-color: rgba(0, 63, 84, 0.458);
            padding: 20px;
            border-radius: 8px;
        }

        footer {
            text-align: center;
            padding: 20px;
            background-color: #3edcff5b;
            color: white;
            position: relative;
            bottom: 0;
            width: 100%;
        }

        @media (max-width: 768px) {
            .game-section {
                flex-direction: column !important;
            }

            .game-image {
                min-height: 300px;
            }
        }
    </style>
</head>
<body>

<header>
    <h1>Games to Feature in The Upcoming Tournament</h1>
    <a href="../index.php" class="home-button">Home</a>
</header>

<div class="container">
    <!-- Predefined Game 1: Valorant -->
    <section class="game-section">
        <div class="game-image" style="background-image: url('../assets/images/games-val.jpg');">
        </div>
        <div class="game-description">
            <h2>Valorant</h2>
            <p>
                Valorant is a free-to-play first-person tactical hero shooter developed and published by Riot Games. Released in 2020, 
                it quickly rose in popularity within the esports world. Players assume the control of agents, characters with unique 
                abilities, and battle in teams of five. The game combines precise shooting mechanics with strategic, team-based play 
                and objective control.
            </p>
            <p>
                The competitive mode of Valorant features a ranked system that pushes players to climb tiers, adding a thrilling layer 
                of competition. Valorant esports has massive events like the Valorant Champions Tour (VCT) which gathers the best teams 
                from around the world, and offers millions in prize pools.
            </p>
            <p>
                <strong>Key Features:</strong>
                <ul>
                    <li>5v5 team-based tactical shooting</li>
                    <li>Unique agent abilities</li>
                    <li>Precise gunplay with an emphasis on aim</li>
                    <li>Ranked competitive play</li>
                </ul>
            </p>
        </div>
    </section>

    <!-- Predefined Game 2: EA FC24 -->
    <section class="game-section">
        <div class="game-image" style="background-image: url('../assets/images/games-fifa.jpg');">
        </div>
        <div class="game-description">
            <h2>EA FC24 (FIFA Series)</h2>
            <p>
                EA FC24 (formerly FIFA) is the latest iteration in EA Sports' long-running football simulation series. With EA FC24, 
                players can enjoy a true-to-life soccer experience with real clubs, leagues, and players. The game includes both single-player 
                modes and online multiplayer, with tournaments and leagues dedicated to professional esports players.
            </p>
            <p>
                EA FC24 features iconic modes like Ultimate Team, Career Mode, and Pro Clubs, and introduces new features to enhance 
                gameplay realism. The Esports scene for EA FC24 (previously FIFA) has seen massive tournaments such as the EA Sports FIFA 
                Global Series and the FIFA eWorld Cup, offering players the chance to compete for large prize pools.
            </p>
            <p>
                <strong>Key Features:</strong>
                <ul>
                    <li>Realistic football simulation</li>
                    <li>Ultimate Team with real players and squads</li>
                    <li>Career Mode and Manager Mode</li>
                    <li>Pro Clubs and online multiplayer</li>
                </ul>
            </p>
        </div>
    </section>
    
    <!-- Dynamically added games from database -->
    <?php if ($games->num_rows > 0): ?>
        <?php while ($game = $games->fetch_assoc()): ?>
            <section class="game-section">
                <div class="game-image" style="background-image: url('<?php echo !empty($game['picture']) ? $game['picture'] : '../assets/images/game-default.jpg'; ?>');"></div>
                <div class="game-description">
                    <h2><?php echo htmlspecialchars($game['game_name']); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($game['description'])); ?></p>
                    
                    <?php if (!empty($game['genre']) || !empty($game['platform']) || !empty($game['number_of_players'])): ?>
                    <p>
                        <strong>Key Features:</strong>
                        <ul>
                            <?php if (!empty($game['genre'])): ?>
                                <li><?php echo htmlspecialchars($game['genre']); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($game['platform'])): ?>
                                <li>Platform: <?php echo htmlspecialchars($game['platform']); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($game['number_of_players'])): ?>
                                <li><?php echo htmlspecialchars($game['number_of_players']); ?> players</li>
                            <?php endif; ?>
                            <?php if (!empty($game['release_date'])): ?>
                                <li>Released: <?php echo date('F j, Y', strtotime($game['release_date'])); ?></li>
                            <?php endif; ?>
                        </ul>
                    </p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endwhile; ?>
    <?php endif; ?>
    
    <?php if ($games->num_rows == 0): ?>
        <div class="coming-soon">
            <p>Stay tuned as more featured games will be coming soon!</p>
        </div>
    <?php endif; ?>

</div>

<footer>
    <p>&copy; 2024 Crac Cloud. All rights reserved.</p>
</footer>

</body>
</html>
