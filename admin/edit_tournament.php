<?php
session_start();
require_once '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Get tournament ID from URL
$tournament_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch tournament details
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tournament) {
    header("Location: admin.php");
    exit();
}

// Fetch all games for the dropdown
$games_result = $conn->query("SELECT id, game_name FROM games ORDER BY game_name");
$games = $games_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize update fields array
    $updateFields = array();
    $types = "";
    $params = array();

    // Check each field and add to update only if it's not empty
    if (!empty($_POST['tournament_name'])) {
        $updateFields[] = "tournament_name = ?";
        $types .= "s";
        $params[] = $_POST['tournament_name'];
    }
    
    if (!empty($_POST['description'])) {
        $updateFields[] = "description = ?";
        $types .= "s";
        $params[] = $_POST['description'];
    }
    
    if (!empty($_POST['game_type'])) {
        $updateFields[] = "game_type = ?";
        $types .= "s";
        $params[] = $_POST['game_type'];
    }
    
    if (!empty($_POST['tournament_date'])) {
        $updateFields[] = "tournament_date = ?";
        $types .= "s";
        $params[] = $_POST['tournament_date'];
    }
    
    if (isset($_POST['entry_fee']) && $_POST['entry_fee'] !== '') {
        $updateFields[] = "entry_fee = ?";
        $types .= "d";
        $params[] = floatval($_POST['entry_fee']);
    }
    
    if (isset($_POST['prize_pool']) && $_POST['prize_pool'] !== '') {
        $updateFields[] = "prize_pool = ?";
        $types .= "d";
        $params[] = floatval($_POST['prize_pool']);
    }
    
    if (isset($_POST['slots']) && $_POST['slots'] !== '') {
        $updateFields[] = "slots = ?";
        $types .= "i";
        $params[] = intval($_POST['slots']);
    }
    
    if (isset($_POST['status'])) {
        $updateFields[] = "status = ?";
        $types .= "s";
        $params[] = $_POST['status'];
    }

    // Only proceed if there are fields to update
    if (!empty($updateFields)) {
        // Add tournament_id to params array and types
        $types .= "i";
        $params[] = $tournament_id;
        
        // Create SQL query
        $sql = "UPDATE tournaments SET " . implode(", ", $updateFields) . " WHERE id = ?";
        
        // Prepare and execute statement
        $stmt = $conn->prepare($sql);
        
        // Create args array for bind_param
        $bindParams = array($types);
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        
        if ($stmt->execute()) {
            $success = "Tournament updated successfully!";
            // Refresh tournament data
            $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
            $stmt->bind_param("i", $tournament_id);
            $stmt->execute();
            $tournament = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Error updating tournament: " . $conn->error;
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
    <title>Edit Tournament - Admin Panel</title>
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
            <h2 class="text-center mb-4">Edit Tournament</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="tournament_name">Tournament Name</label>
                    <input type="text" class="form-control" id="tournament_name" name="tournament_name" 
                           value="<?php echo htmlspecialchars($tournament['tournament_name']); ?>" 
                           placeholder="Enter new tournament name">
                    <div class="current-value">Current: <?php echo htmlspecialchars($tournament['tournament_name']); ?></div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              placeholder="Enter new description"><?php echo htmlspecialchars($tournament['description']); ?></textarea>
                    <div class="current-value">Current: <?php echo htmlspecialchars($tournament['description']); ?></div>
                </div>

                <div class="form-group">
                    <label for="game_type">Game</label>
                    <select class="form-control" id="game_type" name="game_type">
                        <option value="">Select a game</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?php echo htmlspecialchars($game['game_name']); ?>" 
                                    <?php echo ($game['game_name'] === $tournament['game_type']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($game['game_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="current-value">Current: <?php echo htmlspecialchars($tournament['game_type']); ?></div>
                </div>

                <div class="form-group">
                    <label for="tournament_date">Tournament Date</label>
                    <input type="datetime-local" class="form-control" id="tournament_date" name="tournament_date" 
                           value="<?php echo date('Y-m-d\TH:i', strtotime($tournament['tournament_date'])); ?>">
                    <div class="current-value">Current: <?php echo htmlspecialchars($tournament['tournament_date']); ?></div>
                </div>

                <div class="form-group">
                    <label for="entry_fee">Entry Fee</label>
                    <input type="number" step="0.01" class="form-control" id="entry_fee" name="entry_fee" 
                           value="<?php echo htmlspecialchars($tournament['entry_fee']); ?>" 
                           placeholder="Enter new entry fee">
                    <div class="current-value">Current: $<?php echo htmlspecialchars($tournament['entry_fee']); ?></div>
                </div>

                <div class="form-group">
                    <label for="prize_pool">Prize Pool</label>
                    <input type="number" step="0.01" class="form-control" id="prize_pool" name="prize_pool" 
                           value="<?php echo htmlspecialchars($tournament['prize_pool']); ?>" 
                           placeholder="Enter new prize pool">
                    <div class="current-value">Current: $<?php echo htmlspecialchars($tournament['prize_pool']); ?></div>
                </div>

                <div class="form-group">
                    <label for="slots">Available Slots</label>
                    <input type="number" class="form-control" id="slots" name="slots" 
                           value="<?php echo htmlspecialchars($tournament['slots']); ?>" 
                           placeholder="Enter new number of slots">
                    <div class="current-value">Current: <?php echo htmlspecialchars($tournament['slots']); ?></div>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="upcoming" <?php echo ($tournament['status'] === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="ongoing" <?php echo ($tournament['status'] === 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo ($tournament['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($tournament['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <div class="current-value">Current: <?php echo htmlspecialchars($tournament['status']); ?></div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary">Update Tournament</button>
                    <a href="admin.php" class="btn btn-secondary ml-2">Back to Admin Panel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
