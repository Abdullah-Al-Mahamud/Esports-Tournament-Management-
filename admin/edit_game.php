<?php
session_start();
require_once '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Get game ID from URL
$game_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch game details
$stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$game) {
    header("Location: admin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize update fields array
    $updateFields = array();
    $types = "";
    $params = array();

    // Check each field and add to update only if it's not empty
    if (!empty($_POST['game_name'])) {
        $updateFields[] = "game_name = ?";
        $types .= "s";
        $params[] = $_POST['game_name'];
    }
    
    if (!empty($_POST['description'])) {
        $updateFields[] = "description = ?";
        $types .= "s";
        $params[] = $_POST['description'];
    }
    
    if (!empty($_POST['genre'])) {
        $updateFields[] = "genre = ?";
        $types .= "s";
        $params[] = $_POST['genre'];
    }
    
    if (!empty($_POST['platform'])) {
        $updateFields[] = "platform = ?";
        $types .= "s";
        $params[] = $_POST['platform'];
    }
    
    if (!empty($_POST['release_date'])) {
        $updateFields[] = "release_date = ?";
        $types .= "s";
        $params[] = $_POST['release_date'];
    }

    if (!empty($_POST['number_of_players'])) {
        $updateFields[] = "number_of_players = ?";
        $types .= "s";
        $params[] = $_POST['number_of_players'];
    }

    // Only proceed if there are fields to update
    if (!empty($updateFields)) {
        // Add game_id to params array and types
        $types .= "i";
        $params[] = $game_id;
        
        // Create SQL query
        $sql = "UPDATE games SET " . implode(", ", $updateFields) . " WHERE id = ?";
        
        // Prepare and execute statement
        $stmt = $conn->prepare($sql);
        
        // Create args array for bind_param
        $bindParams = array($types);
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        
        if ($stmt->execute()) {
            $success = "Game updated successfully!";
            // Refresh game data
            $stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $game = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Error updating game: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "No fields were provided for update.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Game - Admin Panel</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .edit-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 20px auto;
        }
        .form-group label {
            font-weight: bold;
        }
        .current-value {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="edit-container">
            <h2 class="text-center mb-4">Edit Game</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="game_name">Game Name</label>
                    <input type="text" class="form-control" id="game_name" name="game_name" 
                           value="<?php echo htmlspecialchars($game['game_name']); ?>" 
                           placeholder="Enter new game name">
                    <div class="current-value">Current: <?php echo htmlspecialchars($game['game_name']); ?></div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              placeholder="Enter new description"><?php echo htmlspecialchars($game['description']); ?></textarea>
                    <div class="current-value">Current: <?php echo htmlspecialchars($game['description']); ?></div>
                </div>

                <div class="form-group">
                    <label for="genre">Genre</label>
                    <input type="text" class="form-control" id="genre" name="genre" 
                           value="<?php echo htmlspecialchars($game['genre']); ?>" 
                           placeholder="Enter new genre">
                    <div class="current-value">Current: <?php echo htmlspecialchars($game['genre']); ?></div>
                </div>

                <div class="form-group">
                    <label for="platform">Platform</label>
                    <input type="text" class="form-control" id="platform" name="platform" 
                           value="<?php echo htmlspecialchars($game['platform']); ?>" 
                           placeholder="Enter new platform">
                    <div class="current-value">Current: <?php echo htmlspecialchars($game['platform']); ?></div>
                </div>

                <div class="form-group">
                    <label for="release_date">Release Date</label>
                    <input type="date" class="form-control" id="release_date" name="release_date" 
                           value="<?php echo htmlspecialchars($game['release_date']); ?>">
                    <div class="current-value">Current: <?php echo htmlspecialchars($game['release_date']); ?></div>
                </div>

                <div class="form-group">
                    <label for="number_of_players">Number of Players</label>
                    <input type="text" class="form-control" id="number_of_players" name="number_of_players" 
                           value="<?php echo htmlspecialchars($game['number_of_players']); ?>" 
                           placeholder="Enter number of players">
                    <div class="current-value">Current: <?php echo htmlspecialchars($game['number_of_players']); ?></div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary">Update Game</button>
                    <a href="admin.php" class="btn btn-secondary ml-2">Back to Admin Panel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
