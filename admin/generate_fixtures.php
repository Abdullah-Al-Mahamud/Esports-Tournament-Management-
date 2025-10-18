<?php
session_start();
require_once '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Function to determine tournament rounds based on team count
function determineRounds($teamCount) {
    $rounds = [];
    if ($teamCount >= 8) {
        $rounds[] = 'quarter_final';
        $rounds[] = 'semi_final';
        $rounds[] = 'final';
    } else if ($teamCount >= 4) {
        $rounds[] = 'semi_final';
        $rounds[] = 'final';
    } else if ($teamCount >= 2) {
        $rounds[] = 'final';
    }
    return $rounds;
}

// Function to generate fixtures
function generateFixtures($conn, $tournament_id) {
    // Get tournament details and registered teams
    $query = "SELECT t.*, 
              COUNT(CASE WHEN tr.payment_status = 'confirmed' THEN 1 END) as confirmed_teams,
              COUNT(tr.id) as total_teams
              FROM tournaments t 
              LEFT JOIN team_registrations tr ON t.id = tr.tournament_id 
              WHERE t.id = ?
              GROUP BY t.id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $tournament = $stmt->get_result()->fetch_assoc();
    
    if (!$tournament) {
        return ["error" => "Tournament not found"];
    }

    // Check if slots are fulfilled with confirmed teams
    if ($tournament['confirmed_teams'] < 2) {
        return ["error" => "Not enough confirmed teams. Minimum 2 teams required."];
    }

    if ($tournament['confirmed_teams'] < $tournament['slots']) {
        return ["error" => "Cannot generate fixtures. Tournament slots are not filled completely. " . 
                          "Current confirmed teams: " . $tournament['confirmed_teams'] . 
                          ", Required: " . $tournament['slots']];
    }
    
    // Get only confirmed teams
    $teams_query = "SELECT id, team_name 
                    FROM team_registrations 
                    WHERE tournament_id = ? 
                    AND payment_status = 'confirmed'
                    AND status = 'active'
                    ORDER BY RAND()"; // Randomize team order
    
    $teams_stmt = $conn->prepare($teams_query);
    $teams_stmt->bind_param("i", $tournament_id);
    $teams_stmt->execute();
    $teams_result = $teams_stmt->get_result();
    $teams = [];
    while ($team = $teams_result->fetch_assoc()) {
        $teams[] = $team;
    }
    
    $teamCount = count($teams);
    if ($teamCount < 2) {
        return ["error" => "Not enough confirmed teams for tournament"];
    }
    
    try {
        $conn->begin_transaction();
        
        // Delete existing fixtures
        $delete_stmt = $conn->prepare("DELETE FROM fixtures WHERE tournament_id = ?");
        $delete_stmt->bind_param("i", $tournament_id);
        $delete_stmt->execute();
        
        // Determine rounds based on team count
        $rounds = determineRounds($teamCount);
        $matchNumber = 1;
        
        // Generate fixtures for each round
        foreach ($rounds as $round) {
            $teamsInRound = count($teams);
            $matchesInRound = $teamsInRound / 2;
            
            for ($i = 0; $i < $matchesInRound; $i++) {
                $team1 = array_shift($teams);
                $team2 = array_shift($teams);
                
                // Insert fixture
                $fixture_stmt = $conn->prepare("INSERT INTO fixtures (tournament_id, team1_id, team2_id, round, match_number, match_date, status) 
                                              VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), 'pending')");
                
                $daysToAdd = array_search($round, $rounds) * 7 + 1; // Add 7 days between rounds
                $fixture_stmt->bind_param("iiisis", $tournament_id, $team1['id'], $team2['id'], $round, $matchNumber, $daysToAdd);
                $fixture_stmt->execute();
                
                $matchNumber++;
            }
            
            // For next round, winners will be determined after matches
            $teams = array_fill(0, $matchesInRound, ['id' => null, 'team_name' => 'TBD']);
        }

        // Update tournament status to ongoing
        $update_stmt = $conn->prepare("UPDATE tournaments SET status = 'ongoing' WHERE id = ?");
        $update_stmt->bind_param("i", $tournament_id);
        $update_stmt->execute();
        
        $conn->commit();
        return ["success" => "Fixtures generated successfully"];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ["error" => "Error generating fixtures: " . $e->getMessage()];
    }
}

// Handle fixture generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tournament_id'])) {
    $tournament_id = intval($_POST['tournament_id']);
    $result = generateFixtures($conn, $tournament_id);
    
    if (isset($result['error'])) {
        $_SESSION['error'] = $result['error'];
    } else {
        $_SESSION['success'] = $result['success'];
    }
    
    header("Location: admin.php#generateFixturesSection");
    exit();
}

// If accessed directly without POST, redirect to admin page
header("Location: admin.php");
exit(); 