<?php
require_once '../db.php';

if (isset($_GET['tournament_id'])) {
    $tournament_id = intval($_GET['tournament_id']);
    
    $stmt = $conn->prepare("SELECT id, team_name, manager_name, manager_email as email, manager_phone as contact_number 
                           FROM team_registrations 
                           WHERE tournament_id = ? AND payment_status = 'confirmed'");
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $teams = array();
    while ($team = $result->fetch_assoc()) {
        $teams[] = $team;
    }
    
    header('Content-Type: application/json');
    echo json_encode($teams);
    
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No tournament ID provided']);
}
