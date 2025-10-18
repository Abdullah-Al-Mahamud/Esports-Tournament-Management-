<?php
require_once '../db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT game_name, number_of_players, description FROM games WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($game = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($game);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
    }
    
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No game ID provided']);
}
