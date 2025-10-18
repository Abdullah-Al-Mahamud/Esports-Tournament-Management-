<?php
session_start();
require_once '../db.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    $_SESSION['error_message'] = "You must be an admin to generate fixtures.";
    header('Location: fixtures & Scoreboard.php?error=not_authorized');
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: fixtures & Scoreboard.php?error=invalid_request');
    exit();
}

// Get tournament ID from POST
$tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : null;

if (!$tournament_id) {
    $_SESSION['error_message'] = "No tournament selected.";
    header('Location: fixtures & Scoreboard.php?error=no_tournament');
    exit();
}

// Get tournament details and number of registered teams
$stmt = $conn->prepare("
    SELECT t.*, 
    (SELECT COUNT(*) FROM team_registrations WHERE tournament_id = t.id AND payment_status = 'completed') as team_count
    FROM tournaments t 
    WHERE t.id = ?
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();

if (!$tournament) {
    $_SESSION['error_message'] = "Invalid tournament selected.";
    header('Location: fixtures & Scoreboard.php?error=invalid_tournament');
    exit();
}

// Check if fixtures already exist
$check_fixtures = $conn->prepare("SELECT COUNT(*) as fixture_count FROM fixtures WHERE tournament_id = ?");
$check_fixtures->bind_param("i", $tournament_id);
$check_fixtures->execute();
$fixture_result = $check_fixtures->get_result()->fetch_assoc();

if ($fixture_result['fixture_count'] > 0) {
    $_SESSION['error_message'] = "Fixtures already exist for this tournament.";
    header('Location: fixtures & Scoreboard.php?error=fixtures_exist');
    exit();
}

// Get registered teams
$stmt = $conn->prepare("
    SELECT tr.id as registration_id, tr.team_id, tm.team_name
    FROM team_registrations tr
    JOIN teams tm ON tr.team_id = tm.id
    WHERE tr.tournament_id = ? AND tr.payment_status = 'completed'
    ORDER BY RAND()
");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if we have enough teams
$team_count = count($teams);
if ($team_count < 4) {
    $_SESSION['error_message'] = "Not enough teams registered (minimum 4 teams required). Current count: " . $team_count;
    header('Location: fixtures & Scoreboard.php?error=not_enough_teams');
    exit();
}

// Function to generate fixtures based on number of teams
function generateFixtures($teams, $tournament_id) {
    $num_teams = count($teams);
    $fixtures = [];
    $match_date = new DateTime();
    
    // Determine tournament structure based on team count
    if ($num_teams == 4) {
        // Semi-finals and Final
        $rounds = ['semi_final', 'final'];
        $matches_per_round = [2, 1];
    } elseif ($num_teams == 8) {
        // Quarter-finals, Semi-finals, and Final
        $rounds = ['quarter_final', 'semi_final', 'final'];
        $matches_per_round = [4, 2, 1];
    } elseif ($num_teams == 16) {
        // Round of 16, Quarter-finals, Semi-finals, and Final
        $rounds = ['round_of_16', 'quarter_final', 'semi_final', 'final'];
        $matches_per_round = [8, 4, 2, 1];
    } else {
        // For other numbers (5-7, 9-15), start with quarter-finals
        $rounds = ['quarter_final', 'semi_final', 'final'];
        $matches_per_round = [4, 2, 1];
        
        // Fill remaining slots with byes if needed
        while (count($teams) < 8) {
            $teams[] = ['registration_id' => null, 'team_id' => null, 'team_name' => 'BYE'];
        }
    }

    // Generate matches for each round
    $team_index = 0;
    foreach ($rounds as $round_index => $round) {
        $num_matches = $matches_per_round[$round_index];
        
        for ($i = 0; $i < $num_matches; $i++) {
            if ($round === 'round_of_16' || $round === 'quarter_final') {
                $team1 = $teams[$team_index++];
                $team2 = $teams[$team_index++];
            } else {
                // For later rounds, leave teams as TBD
                $team1 = ['registration_id' => null, 'team_name' => 'TBD'];
                $team2 = ['registration_id' => null, 'team_name' => 'TBD'];
            }

            $match_date->modify('+3 days'); // Space matches 3 days apart
            
            $fixtures[] = [
                'tournament_id' => $tournament_id,
                'round' => $round,
                'match_number' => $i + 1,
                'team1_id' => $team1['registration_id'],
                'team2_id' => $team2['registration_id'],
                'match_date' => $match_date->format('Y-m-d H:i:s'),
                'status' => 'pending'
            ];
        }
    }
    
    return $fixtures;
}

try {
    $conn->begin_transaction();

    // Generate fixtures
    $fixtures = generateFixtures($teams, $tournament_id);

    // Insert fixtures into database
    $stmt = $conn->prepare("
        INSERT INTO fixtures (tournament_id, round, match_number, team1_id, team2_id, match_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($fixtures as $fixture) {
        $stmt->bind_param(
            "isiisss",
            $fixture['tournament_id'],
            $fixture['round'],
            $fixture['match_number'],
            $fixture['team1_id'],
            $fixture['team2_id'],
            $fixture['match_date'],
            $fixture['status']
        );
        $stmt->execute();
    }

    // Update tournament status to 'ongoing'
    $update_stmt = $conn->prepare("UPDATE tournaments SET status = 'ongoing' WHERE id = ?");
    $update_stmt->bind_param("i", $tournament_id);
    $update_stmt->execute();

    $conn->commit();
    
    $_SESSION['success_message'] = "Fixtures have been successfully generated!";
    header("Location: fixtures & Scoreboard.php?tournament_id=" . $tournament_id);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Failed to generate fixtures: " . $e->getMessage();
    header("Location: fixtures & Scoreboard.php?error=generation_failed");
    exit();
} 