<?php
require_once '../db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT tournament_name, game_type, start_date, end_date, description FROM tournaments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($tournament = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($tournament);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Tournament not found']);
    }
    
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No tournament ID provided']);
}
