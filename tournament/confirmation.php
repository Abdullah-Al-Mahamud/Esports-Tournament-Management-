<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tournament_id = intval($_POST['tournament_id']);
    $team_name = $_POST['team_name'];
    $manager_name = $_POST['manager_name'];
    $manager_phone = $_POST['manager_phone'];
    $manager_email = $_SESSION['user_email'];
    
    $conn->begin_transaction();
    
    try {
        // First, check if team already exists
        $stmt = $conn->prepare("SELECT id FROM teams WHERE team_name = ?");
        $stmt->bind_param("s", $team_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Team exists, get the team ID
            $team = $result->fetch_assoc();
            $team_id = $team['id'];
            
            // Verify if this user is the team manager
            if ($team['manager_email'] !== $manager_email) {
                throw new Exception("A team with this name already exists and you are not the manager.");
            }
        } else {
            // Create new team
            $stmt = $conn->prepare("INSERT INTO teams (team_name, manager_email) VALUES (?, ?)");
            $stmt->bind_param("ss", $team_name, $manager_email);
            $stmt->execute();
            $team_id = $conn->insert_id;
        }
        
        // Check if team is already registered for this tournament
        $stmt = $conn->prepare("SELECT id FROM team_registrations WHERE tournament_id = ? AND team_id = ?");
        $stmt->bind_param("ii", $tournament_id, $team_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("This team is already registered for this tournament.");
        }
        
        // Register team for tournament
        $stmt = $conn->prepare("INSERT INTO team_registrations (tournament_id, team_id, manager_name, manager_email, manager_phone) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $tournament_id, $team_id, $manager_name, $manager_email, $manager_phone);
        $stmt->execute();
        
        // Update user's team name in profile if not set
        $stmt = $conn->prepare("UPDATE users SET team_name = COALESCE(team_name, ?) WHERE email = ? AND team_name IS NULL");
        $stmt->bind_param("ss", $team_name, $manager_email);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = "Team registration successful! Please proceed with payment to confirm your spot.";
        header("Location: tournament.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Registration failed: " . $e->getMessage();
        header("Location: tournament.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
        }
        .nav-links {
            text-align: center;
            margin-top: 20px;
        }
        .nav-links a {
            margin: 0 10px;
            text-decoration: none;
            color: #53A9A8;
        }
        .back-button {
            display: block;
            text-align: center;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #53A9A8;
            color: white;
            border-radius: 5px;
            text-decoration: none;
        }
        .back-button:hover {
            background-color: #12525c;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Registration Successful</h2>
    <p>Congratulations! Your team has been successfully registered.</p>
    <p><strong>Team Name:</strong> <?php echo htmlspecialchars($team_name); ?></p>
    <p><strong>Tournament ID:</strong> <?php echo htmlspecialchars($tournament_id); ?></p>
    <p><strong>Manager Name:</strong> <?php echo htmlspecialchars($manager_name); ?></p>
    <p><strong>Amount:</strong> $100</p>
    <p><strong>Payment Status:</strong> Due Payment</p>
    
    <div class="nav-links">
        <a href="../tournament.php">Tournaments</a>
        <a href="../tournament/teams.php">Teams</a>
    </div>
    <div style="text-align: center; margin-top: 20px;">
        <a href="../index.php" class="back-button">Go Back to Homepage</a>
    </div>
</div>

</body>
</html>

