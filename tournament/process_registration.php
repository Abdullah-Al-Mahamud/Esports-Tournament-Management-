<?php
session_start();
require_once('../db.php');

function sendJsonResponse($success, $message, $redirect = '') {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect
    ]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'Please login to register', '../auth/login.php');
}

// Simple validation
if (empty($_POST['tournament_id']) || empty($_POST['team_name']) || empty($_POST['players'])) {
    sendJsonResponse(false, 'Please fill all required fields');
}

$tournament_id = $_POST['tournament_id'];
$team_name = $_POST['team_name'];
$manager_email = $_SESSION['user_email'];

try {
    $conn->begin_transaction();
    
    // Check if team exists or create new one
    $team_id = null;
    $team_check = $conn->prepare("SELECT id FROM teams WHERE team_name = ? AND manager_email = ?");
    $team_check->bind_param("ss", $team_name, $manager_email);
    $team_check->execute();
    $result = $team_check->get_result();
    
    if ($result->num_rows > 0) {
        $team = $result->fetch_assoc();
        $team_id = $team['id'];
    } else {
        // Check if team name is taken by another manager
        $name_check = $conn->prepare("SELECT id FROM teams WHERE team_name = ?");
        $name_check->bind_param("s", $team_name);
        $name_check->execute();
        if ($name_check->get_result()->num_rows > 0) {
            throw new Exception('Team name already taken');
        }
        
        // Create new team
        $create_team = $conn->prepare("INSERT INTO teams (team_name, manager_email) VALUES (?, ?)");
        $create_team->bind_param("ss", $team_name, $manager_email);
        $create_team->execute();
        $team_id = $conn->insert_id;
        
        // Update user profile
        $conn->query("UPDATE users SET team_name = '$team_name' WHERE email = '$manager_email' AND team_name IS NULL");
    }
    
    // Check for existing registration
    $reg_check = $conn->prepare("SELECT id FROM team_registrations WHERE tournament_id = ? AND team_id = ?");
    $reg_check->bind_param("ii", $tournament_id, $team_id);
    $reg_check->execute();
    if ($reg_check->get_result()->num_rows > 0) {
        throw new Exception('Team already registered for this tournament');
    }
    
    // Check tournament slots
    $tournament = $conn->query("SELECT slots, (SELECT COUNT(*) FROM team_registrations WHERE tournament_id = $tournament_id) as registered FROM tournaments WHERE id = $tournament_id")->fetch_assoc();
    if ($tournament['registered'] >= $tournament['slots']) {
        throw new Exception('Tournament is full');
    }
    
    // Check for duplicate players
    $players = array_unique(array_filter($_POST['players']));
    if (count($players) !== count($_POST['players'])) {
        throw new Exception('Duplicate player names are not allowed');
    }
    
    // Check if players are already registered
    $player_list = "'" . implode("','", array_map([$conn, 'real_escape_string'], $players)) . "'";
    $existing = $conn->query("
        SELECT DISTINCT player_name FROM team_players tp 
        JOIN team_registrations tr ON tp.team_id = tr.id 
        WHERE tr.tournament_id = $tournament_id 
        AND player_name IN ($player_list)
    ");
    
    if ($existing->num_rows > 0) {
        $taken = [];
        while ($row = $existing->fetch_assoc()) {
            $taken[] = $row['player_name'];
        }
        throw new Exception('Players already registered: ' . implode(', ', $taken));
    }
    
    // Create registration
    $reg = $conn->prepare("INSERT INTO team_registrations (tournament_id, team_id, manager_name, manager_phone, manager_email) VALUES (?, ?, ?, ?, ?)");
    $reg->bind_param("iisss", $tournament_id, $team_id, $_POST['manager_name'], $_POST['manager_phone'], $manager_email);
    $reg->execute();
    $registration_id = $conn->insert_id;
    
    // Add players
    $add_player = $conn->prepare("INSERT INTO team_players (team_id, player_name) VALUES (?, ?)");
    foreach ($players as $player) {
        $add_player->bind_param("is", $team_id, $player);
        $add_player->execute();
    }
    
    $conn->commit();
    sendJsonResponse(true, 'Registration successful', "payment.php?registration_id=$registration_id");
    
} catch (Exception $e) {
    $conn->rollback();
    sendJsonResponse(false, $e->getMessage());
}

$conn->close();
