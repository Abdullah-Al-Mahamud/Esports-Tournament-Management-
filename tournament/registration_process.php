<?php
session_start();
require_once('../db.php');

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to register for tournaments.";
    header("Location: ../user/LogIn.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: tournaments.php");
    exit();
}

// Get form data
$tournament_id = $_POST['tournament_id'] ?? null;
$team_name = $_POST['team_name'] ?? null;
$player_names = $_POST['player_names'] ?? [];
$player_emails = $_POST['player_emails'] ?? [];

// Validate input
if (!$tournament_id || !$team_name || empty($player_names)) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: registration.php?tournament_id=" . $tournament_id);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if tournament exists and has available slots
    $tournament_query = "SELECT t.*, g.number_of_players,
                        (SELECT COUNT(*) FROM team_registrations 
                         WHERE tournament_id = t.id AND payment_status = 'confirmed') as registered_teams
                        FROM tournaments t
                        JOIN games g ON t.game_type = g.game_name
                        WHERE t.id = ? AND t.status != 'completed'
                        FOR UPDATE";
    
    $stmt = $conn->prepare($tournament_query);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();

    if (!$tournament) {
        throw new Exception("Tournament not found or already completed.");
    }

    if ($tournament['registered_teams'] >= $tournament['slots']) {
        throw new Exception("Sorry, all slots are filled for this tournament.");
    }

    // Check if team name is unique for this tournament
    $team_check = $conn->prepare("SELECT id FROM team_registrations WHERE tournament_id = ? AND team_name = ?");
    $team_check->bind_param("is", $tournament_id, $team_name);
    $team_check->execute();
    if ($team_check->get_result()->num_rows > 0) {
        throw new Exception("Team name already exists in this tournament.");
    }

    // Insert team registration
    $reg_query = "INSERT INTO team_registrations (tournament_id, manager_id, team_name, payment_status, registration_date)
                  VALUES (?, ?, ?, 'pending', NOW())";
    $reg_stmt = $conn->prepare($reg_query);
    $reg_stmt->bind_param("iis", $tournament_id, $_SESSION['user_id'], $team_name);
    $reg_stmt->execute();
    $team_id = $conn->insert_id;

    // Insert player details
    $player_query = "INSERT INTO team_players (team_id, player_name, player_email)
                    VALUES (?, ?, ?)";
    $player_stmt = $conn->prepare($player_query);
    
    foreach ($player_names as $index => $name) {
        if (!empty($name)) {
            $email = $player_emails[$index] ?? '';
            $player_stmt->bind_param("iss", $team_id, $name, $email);
            $player_stmt->execute();
        }
    }

    // Check if tournament is now full
    if ($tournament['registered_teams'] + 1 >= $tournament['slots']) {
        $update_tournament = $conn->prepare("UPDATE tournaments SET status = 'registration_closed' WHERE id = ?");
        $update_tournament->bind_param("i", $tournament_id);
        $update_tournament->execute();
    }

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Team registration successful! Please complete the payment to confirm your spot.";
    header("Location: tournaments.php");

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    header("Location: registration.php?tournament_id=" . $tournament_id);
} finally {
    $conn->close();
}
