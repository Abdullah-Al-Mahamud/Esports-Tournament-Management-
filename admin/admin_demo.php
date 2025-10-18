<?php
session_start();
require_once '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_email']) || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Handle game deletion
if (isset($_POST['delete_game_id'])) {
    $game_id = intval($_POST['delete_game_id']);
    
    // First check if game is used in any tournaments
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM tournaments WHERE game_type IN (SELECT game_name FROM games WHERE id = ?)");
    $check_stmt->bind_param("i", $game_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $game_error = "Cannot delete game as it is being used in tournaments.";
    } else {
        $stmt = $conn->prepare("DELETE FROM games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        if ($stmt->execute()) {
            $game_success = "Game deleted successfully!";
        } else {
            $game_error = "Error deleting game: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle tournament deletion
if (isset($_POST['delete_tournament'])) {
    $tournament_id = intval($_POST['tournament_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get tournament details and registered users before deletion
        $stmt = $conn->prepare("SELECT t.tournament_name, tr.manager_email 
                              FROM tournaments t 
                              LEFT JOIN team_registrations tr ON t.id = tr.tournament_id 
                              WHERE t.id = ?");
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $registered_users = [];
        $tournament_name = "";
        
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['manager_email'])) {
                $registered_users[] = $row['manager_email'];
            }
            if (empty($tournament_name)) {
                $tournament_name = $row['tournament_name'];
            }
        }
        $stmt->close();
        
        // Delete team registrations (will cascade delete team_players)
        $stmt = $conn->prepare("DELETE FROM team_registrations WHERE tournament_id = ?");
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete fixtures
        $stmt = $conn->prepare("DELETE FROM fixtures WHERE tournament_id = ?");
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete tournament
        $stmt = $conn->prepare("DELETE FROM tournaments WHERE id = ?");
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $stmt->close();
        
        // Send notifications to registered users
        if (!empty($registered_users)) {
            $message = "Tournament '" . $tournament_name . "' has been cancelled by the administrator. Your registration has been removed.";
            $stmt = $conn->prepare("INSERT INTO notifications (user_email, message) VALUES (?, ?)");
            
            foreach ($registered_users as $email) {
                $stmt->bind_param("ss", $email, $message);
                $stmt->execute();
            }
            $stmt->close();
        }
        
        $conn->commit();
        $success_message = "Tournament deleted successfully! " . 
                         (!empty($registered_users) ? count($registered_users) . " registered teams have been notified." : "");
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error deleting tournament: " . $e->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_game'])) {
        $game_name = $_POST['game_name'];
        $number_of_players = intval($_POST['number_of_players']);
        $description = $_POST['description'];
        
        // Handle picture upload
        $picture = null;
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === 0) {
            $upload_dir = '../uploads/games/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $picture = $upload_dir . basename($_FILES['picture']['name']);
            move_uploaded_file($_FILES['picture']['tmp_name'], $picture);
        }
        
        $stmt = $conn->prepare("INSERT INTO games (game_name, number_of_players, picture, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siss", $game_name, $number_of_players, $picture, $description);
        
        if ($stmt->execute()) {
            $success_message = "Game added successfully!";
        } else {
            $error_message = "Error adding game: " . $conn->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['add_tournament'])) {
        $tournament_name = $_POST['tournament_name'];
        $game_id = intval($_POST['game_id']);
        $entry_fee = floatval($_POST['entry_fee']);
        $prize_money = floatval($_POST['prize_money']);
        $slots = intval($_POST['slots']);
        $tournament_date = $_POST['tournament_date'];
        $registration_deadline = $_POST['registration_deadline'];
        
        // Handle picture upload
        $picture = null;
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === 0) {
            $upload_dir = '../uploads/tournaments/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $picture = $upload_dir . basename($_FILES['picture']['name']);
            move_uploaded_file($_FILES['picture']['tmp_name'], $picture);
        }
        
        // Get game_name for the selected game_id
        $stmt = $conn->prepare("SELECT game_name FROM games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $game = $result->fetch_assoc();
        $game_type = $game['game_name'];
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO tournaments (tournament_name, game_type, entry_fee, prize_money, slots, picture, status, tournament_date, registration_deadline) 
                               VALUES (?, ?, ?, ?, ?, ?, 'upcoming', ?, ?)");
        $stmt->bind_param("ssddssss", $tournament_name, $game_type, $entry_fee, $prize_money, $slots, $picture, $tournament_date, $registration_deadline);
        
        if ($stmt->execute()) {
            $success_message = "Tournament added successfully!";
        } else {
            $error_message = "Error adding tournament: " . $conn->error;
        }
        $stmt->close();
    }

    if (isset($_POST['generate_fixtures'])) {
        $tournament_id = intval($_POST['tournament_id']);
        
        // Check if tournament exists and slots are filled
        $stmt = $conn->prepare("SELECT t.*, COUNT(r.id) as registered_teams 
                              FROM tournaments t 
                              LEFT JOIN registrations r ON t.id = r.tournament_id 
                              WHERE t.id = ? 
                              GROUP BY t.id");
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $tournament = $result->fetch_assoc();
        
        if ($tournament && $tournament['registered_teams'] == $tournament['slots']) {
            // Get all registered teams
            $teams_query = "SELECT r.id as registration_id, t.id as team_id, t.team_name 
                          FROM registrations r 
                          JOIN teams t ON r.team_id = t.id 
                          WHERE r.tournament_id = ?";
            $stmt = $conn->prepare($teams_query);
            $stmt->bind_param("i", $tournament_id);
            $stmt->execute();
            $teams_result = $stmt->get_result();
            $teams = [];
            while ($team = $teams_result->fetch_assoc()) {
                $teams[] = $team;
            }
            
            // Shuffle teams randomly
            shuffle($teams);
            
            // Generate fixtures
            $conn->begin_transaction();
            try {
                // Delete existing fixtures
                $conn->query("DELETE FROM fixtures WHERE tournament_id = $tournament_id");
                
                // Create new fixtures
                $round = "Round 1";
                for ($i = 0; $i < count($teams); $i += 2) {
                    if (isset($teams[$i]) && isset($teams[$i + 1])) {
                        $team1_id = $teams[$i]['team_id'];
                        $team2_id = $teams[$i + 1]['team_id'];
                        
                        $stmt = $conn->prepare("INSERT INTO fixtures (tournament_id, team1_id, team2_id, round) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iiis", $tournament_id, $team1_id, $team2_id, $round);
                        $stmt->execute();
                    }
                }
                
                $conn->commit();
                $success_message = "Fixtures generated successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error generating fixtures: " . $e->getMessage();
            }
        } else {
            $error_message = "Cannot generate fixtures. Tournament slots are not filled completely.";
        }
    }

    if (isset($_POST['edit_game'])) {
        $game_id = intval($_POST['game_id']);
        $game_name = $_POST['game_name'];
        $number_of_players = intval($_POST['number_of_players']);
        $description = $_POST['description'];
        
        // Handle picture upload
        $picture = null;
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === 0) {
            $upload_dir = '../uploads/games/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $picture = $upload_dir . basename($_FILES['picture']['name']);
            move_uploaded_file($_FILES['picture']['tmp_name'], $picture);
        } else {
            $picture = $_POST['old_picture'];
        }
        
        $stmt = $conn->prepare("UPDATE games SET game_name = ?, number_of_players = ?, picture = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sissi", $game_name, $number_of_players, $picture, $description, $game_id);
        
        if ($stmt->execute()) {
            $success_message = "Game updated successfully!";
        } else {
            $error_message = "Error updating game: " . $conn->error;
        }
        $stmt->close();
    }

    if (isset($_POST['edit_tournament'])) {
        $tournament_id = intval($_POST['tournament_id']);
        $tournament_name = $_POST['tournament_name'];
        $game_id = intval($_POST['game_id']);
        $entry_fee = floatval($_POST['entry_fee']);
        $prize_money = floatval($_POST['prize_money']);
        $slots = intval($_POST['slots']);
        $tournament_date = $_POST['tournament_date'];
        $registration_deadline = $_POST['registration_deadline'];
        
        // Get game_name for the selected game_id
        $stmt = $conn->prepare("SELECT game_name FROM games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $game = $result->fetch_assoc();
        $game_type = $game['game_name'];
        $stmt->close();
        
        // Handle picture upload if a new one is provided
        $picture_sql = "";
        $picture_value = null;
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === 0) {
            $upload_dir = '../uploads/tournaments/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $picture = $upload_dir . basename($_FILES['picture']['name']);
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $picture)) {
                $picture_sql = ", picture = ?";
                $picture_value = $picture;
            }
        }
        
        // Build the update query based on whether a new picture was uploaded
        $sql = "UPDATE tournaments SET tournament_name = ?, game_type = ?, entry_fee = ?, 
                prize_money = ?, slots = ?, tournament_date = ?, registration_deadline = ?";
        if ($picture_sql) {
            $sql .= $picture_sql;
            $stmt = $conn->prepare($sql . " WHERE id = ?");
            $stmt->bind_param("ssddsssi", $tournament_name, $game_type, $entry_fee, $prize_money, 
                            $slots, $tournament_date, $registration_deadline, $picture_value, $tournament_id);
        } else {
            $sql .= " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssddsssi", $tournament_name, $game_type, $entry_fee, $prize_money, 
                            $slots, $tournament_date, $registration_deadline, $tournament_id);
        }
        
        if ($stmt->execute()) {
            $success_message = "Tournament updated successfully!";
        } else {
            $error_message = "Error updating tournament: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch games for tournament form
$games_query = "SELECT id, game_name FROM games";
$games_result = $conn->query($games_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CracCloud</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/CSE370-Project/assets/css/admin.css">
</head>
<body>
    <div class="container">
        <!-- Navigation -->
        <div class="nav-top">
            <h2><i class="fas fa-gamepad"></i> Admin Dashboard</h2>
            <div class="nav-buttons">
                <div class="system-buttons">
                    <a href="/CSE370-Project/index.php" class="btn btn-secondary"><i class="fas fa-home"></i> Home</a>
                    <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
                <div class="main-buttons">
                    <button onclick="showSection('addGame')" class="btn btn-primary"><i class="fas fa-plus"></i> Add Game</button>
                    <button onclick="showSection('addTournament')" class="btn btn-primary"><i class="fas fa-trophy"></i> Add Tournament</button>
                    <button onclick="showSection('generateFixtures')" class="btn btn-primary"><i class="fas fa-sitemap"></i> Generate Fixtures</button>
                    <button onclick="showSection('viewGames')" class="btn btn-primary"><i class="fas fa-list"></i> View Games</button>
                    <button onclick="showSection('viewTournaments')" class="btn btn-primary"><i class="fas fa-list"></i> View Tournaments</button>
                    <button onclick="showSection('manageUsers')" class="btn btn-primary"><i class="fas fa-users"></i> Manage Users</button>
                    <button onclick="showSection('managePayments')" class="btn btn-primary"><i class="fas fa-money-bill"></i> Manage Payments</button>
                </div>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="content-sections">
            <!-- Add Game Form -->
            <div id="addGameSection" class="form-container" style="display: none;">
                <h3><i class="fas fa-gamepad"></i> Add New Game</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Game Name</label>
                        <input type="text" name="game_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Number of Players</label>
                        <input type="number" name="number_of_players" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Picture</label>
                        <input type="file" name="picture" class="form-control">
                    </div>
                    <button type="submit" name="add_game" class="btn btn-primary">Add Game</button>
                </form>
            </div>

            <!-- Add Tournament Form -->
            <div id="addTournamentSection" class="form-container" style="display: none;">
                <h3><i class="fas fa-trophy"></i> Add Tournament</h3>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="tournament_name">Tournament Name</label>
                        <input type="text" class="form-control" id="tournament_name" name="tournament_name" required>
                    </div>
                    <div class="form-group">
                        <label for="game_id">Select Game</label>
                        <select class="form-control" id="game_id" name="game_id" required>
                            <?php
                            $games_result = $conn->query("SELECT * FROM games");
                            while($game = $games_result->fetch_assoc()) {
                                echo "<option value='" . $game['id'] . "'>" . htmlspecialchars($game['game_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tournament_date">Tournament Date</label>
                        <input type="date" class="form-control" id="tournament_date" name="tournament_date" required>
                    </div>
                    <div class="form-group">
                        <label for="registration_deadline">Registration Deadline</label>
                        <input type="date" class="form-control" id="registration_deadline" name="registration_deadline" required>
                    </div>
                    <div class="form-group">
                        <label for="entry_fee">Entry Fee</label>
                        <input type="number" class="form-control" id="entry_fee" name="entry_fee" required>
                    </div>
                    <div class="form-group">
                        <label for="prize_money">Prize Money</label>
                        <input type="number" class="form-control" id="prize_money" name="prize_money" required>
                    </div>
                    <div class="form-group">
                        <label for="slots">Number of Slots</label>
                        <input type="number" class="form-control" id="slots" name="slots" min="2" required>
                    </div>
                    <div class="form-group">
                        <label for="picture">Tournament Banner (Optional)</label>
                        <input type="file" class="form-control" id="picture" name="picture">
                    </div>
                    <button type="submit" name="add_tournament" class="btn btn-primary">Add Tournament</button>
                </form>
            </div>

            <!-- Generate Fixtures Section -->
            <div id="generateFixturesSection" class="form-container" style="display: none;">
                <h3><i class="fas fa-sitemap"></i> Generate Tournament Fixtures</h3>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <form action="generate_fixtures.php" method="POST" class="tournament-form">
                    <div class="form-group">
                        <label>Select Tournament</label>
                        <select name="tournament_id" class="form-control" required>
                            <option value="">Select Tournament</option>
                            <?php
                            // Show all upcoming tournaments with their registration status
                            $tournaments_query = "SELECT 
                                t.id, 
                                t.tournament_name, 
                                t.slots,
                                t.status,
                                t.tournament_date,
                                COUNT(CASE WHEN tr.payment_status = 'confirmed' THEN 1 END) as confirmed_teams
                                FROM tournaments t 
                                LEFT JOIN team_registrations tr ON t.id = tr.tournament_id 
                                WHERE t.status = 'upcoming' 
                                AND t.tournament_date > NOW()
                                GROUP BY t.id
                                ORDER BY t.tournament_date ASC";
                            
                            $tournaments_result = $conn->query($tournaments_query);
                            if ($tournaments_result && $tournaments_result->num_rows > 0) {
                                while ($tournament = $tournaments_result->fetch_assoc()) {
                                    // Check if tournament is ready for fixture generation
                                    $isReady = ($tournament['confirmed_teams'] >= 2 && 
                                              $tournament['confirmed_teams'] == $tournament['slots']);
                                    
                                    $status_text = '';
                                    if ($tournament['confirmed_teams'] == 0) {
                                        $status_text = ' - Waiting for registrations';
                                    } elseif ($tournament['confirmed_teams'] < $tournament['slots']) {
                                        $status_text = ' - Slots not filled (' . $tournament['confirmed_teams'] . '/' . $tournament['slots'] . ' teams)';
                                    } elseif ($isReady) {
                                        $status_text = ' - Ready for fixture generation!';
                                    }
                                    
                                    echo "<option value='" . $tournament['id'] . "'" . (!$isReady ? ' disabled' : '') . ">" 
                                        . htmlspecialchars($tournament['tournament_name']) 
                                        . $status_text
                                        . "</option>";
                                }
                            }
                            
                            // Also show ongoing tournaments but mark them as disabled
                            $ongoing_query = "SELECT 
                                t.id, 
                                t.tournament_name,
                                t.slots,
                                COUNT(CASE WHEN tr.payment_status = 'confirmed' THEN 1 END) as confirmed_teams
                                FROM tournaments t 
                                LEFT JOIN team_registrations tr ON t.id = tr.tournament_id 
                                WHERE t.status = 'ongoing'
                                GROUP BY t.id
                                ORDER BY t.created_at DESC";
                                
                            $ongoing_result = $conn->query($ongoing_query);
                            if ($ongoing_result && $ongoing_result->num_rows > 0) {
                                echo "<optgroup label='Ongoing Tournaments'>";
                                while ($tournament = $ongoing_result->fetch_assoc()) {
                                    echo "<option value='" . $tournament['id'] . "' disabled>" 
                                        . htmlspecialchars($tournament['tournament_name']) 
                                        . " - Fixtures already generated"
                                        . "</option>";
                                }
                                echo "</optgroup>";
                            }
                            
                            if ($tournaments_result->num_rows == 0 && $ongoing_result->num_rows == 0) {
                                echo "<option disabled>No tournaments available</option>";
                            }
                            ?>
                        </select>
                        <small class="form-text text-muted">
                            Tournament status guide:
                            <ul>
                                <li>Waiting for registrations: No teams have registered yet</li>
                                <li>Slots not filled: Tournament needs more confirmed teams</li>
                                <li>Ready for fixture generation: All slots are filled with confirmed teams</li>
                                <li>Fixtures already generated: Tournament is ongoing</li>
                            </ul>
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">Generate Fixtures</button>
                </form>
            </div>

            <!-- View Games Section -->
            <div id="viewGamesSection" class="form-container" style="display: none;">
                <h3><i class="fas fa-list"></i> Games List</h3>
                <div id="editGameForm" style="display: none;" class="edit-form-container">
                    <h4>Edit Game</h4>
                    <form method="POST" enctype="multipart/form-data" class="edit-form">
                        <input type="hidden" name="game_id" id="edit_game_id">
                        <input type="hidden" name="old_picture" id="edit_game_old_picture">
                        <div class="form-group">
                            <label>Game Name</label>
                            <input type="text" name="game_name" id="edit_game_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Number of Players</label>
                            <input type="number" name="number_of_players" id="edit_number_of_players" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Picture (Optional)</label>
                            <input type="file" name="picture" class="form-control">
                            <small class="form-text text-muted">Leave empty to keep current picture</small>
                        </div>
                        <button type="submit" name="edit_game" class="btn btn-primary">Update Game</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditForm('Game')">Cancel</button>
                    </form>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Game Name</th>
                            <th>Players</th>
                            <th>Description</th>
                            <th>Picture</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $games_result = $conn->query("SELECT * FROM games ORDER BY id DESC");
                        while($game = $games_result->fetch_assoc()):
                        ?>
                        <tr data-game-id="<?php echo $game['id']; ?>">
                            <td><?php echo $game['id']; ?></td>
                            <td id="game_name_<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['game_name']); ?></td>
                            <td id="players_<?php echo $game['id']; ?>"><?php echo $game['number_of_players']; ?></td>
                            <td id="description_<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['description']); ?></td>
                            <td>
                                <?php if($game['picture']): ?>
                                    <img src="<?php echo $game['picture']; ?>" alt="Game Picture" style="max-width: 100px;">
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm btn-action" onclick="showEditForm('Game', <?php echo $game['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="delete_game_id" value="<?php echo $game['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm btn-action" onclick="return confirm('Are you sure you want to delete this game?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- View Tournaments Section -->
            <div id="viewTournamentsSection" class="form-container" style="display: none;">
                <h3><i class="fas fa-trophy"></i> Tournaments List</h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tournament Name</th>
                                <th>Game Type</th>
                                <th>Entry Fee</th>
                                <th>Prize Money</th>
                                <th>Slots</th>
                                <th>Tournament Date</th>
                                <th>Registration Deadline</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tournaments_result = $conn->query("SELECT * FROM tournaments ORDER BY created_at DESC");
                            while($tournament = $tournaments_result->fetch_assoc()) {
                                echo "<tr data-tournament-id='" . $tournament['id'] . "'>";
                                echo "<td id='tournament_name_" . $tournament['id'] . "'>" . htmlspecialchars($tournament['tournament_name']) . "</td>";
                                echo "<td id='game_type_" . $tournament['id'] . "'>" . htmlspecialchars($tournament['game_type']) . "</td>";
                                echo "<td id='entry_fee_" . $tournament['id'] . "'>" . $tournament['entry_fee'] . "</td>";
                                echo "<td id='prize_money_" . $tournament['id'] . "'>" . $tournament['prize_money'] . "</td>";
                                echo "<td id='slots_" . $tournament['id'] . "'>" . $tournament['slots'] . "</td>";
                                echo "<td id='tournament_date_" . $tournament['id'] . "'>" . $tournament['tournament_date'] . "</td>";
                                echo "<td id='registration_deadline_" . $tournament['id'] . "'>" . $tournament['registration_deadline'] . "</td>";
                                echo "<td>" . ucfirst($tournament['status']) . "</td>";
                                echo "<td>";
                                echo "<button class='btn btn-sm btn-primary mr-1' onclick='showEditForm(\"Tournament\", " . $tournament['id'] . ")'><i class='fas fa-edit'></i></button>";
                                echo "<form method='POST' style='display: inline;' onsubmit='return confirm(\"Are you sure you want to delete this tournament?\")'>";
                                echo "<input type='hidden' name='tournament_id' value='" . $tournament['id'] . "'>";
                                echo "<button type='submit' name='delete_tournament' class='btn btn-sm btn-danger'><i class='fas fa-trash'></i></button>";
                                echo "</form>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Edit Tournament Form -->
                <div id="editTournamentForm" class="edit-form-container" style="display: none;">
                    <h4>Edit Tournament</h4>
                    <form method="POST" enctype="multipart/form-data" class="edit-form">
                        <input type="hidden" name="tournament_id" id="edit_tournament_id">
                        <div class="form-group">
                            <label>Tournament Name</label>
                            <input type="text" name="tournament_name" id="edit_tournament_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Game</label>
                            <select name="game_id" id="edit_game_id" class="form-control" required>
                                <?php
                                $games_result = $conn->query("SELECT * FROM games");
                                while($game = $games_result->fetch_assoc()) {
                                    echo "<option value='" . $game['id'] . "'>" . htmlspecialchars($game['game_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tournament Date</label>
                            <input type="date" name="tournament_date" id="edit_tournament_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Registration Deadline</label>
                            <input type="date" name="registration_deadline" id="edit_registration_deadline" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Entry Fee</label>
                            <input type="number" name="entry_fee" id="edit_entry_fee" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Prize Money</label>
                            <input type="number" name="prize_money" id="edit_prize_money" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Number of Slots</label>
                            <input type="number" name="slots" id="edit_slots" class="form-control" min="2" required>
                        </div>
                        <div class="form-group">
                            <label>Tournament Banner (Optional)</label>
                            <input type="file" name="picture" class="form-control">
                        </div>
                        <button type="submit" name="edit_tournament" class="btn btn-primary">Update Tournament</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditForm('Tournament')">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Function to show/hide sections
        function showSection(sectionName) {
            // First hide all sections
            const sections = document.querySelectorAll('.form-container');
            sections.forEach(section => {
                section.style.display = 'none';
            });

            // Hide any open edit forms
            const editForms = document.querySelectorAll('.edit-form-container');
            editForms.forEach(form => {
                form.style.display = 'none';
            });

            // Then handle the section to show
            if (sectionName === 'managePayments') {
                window.location.href = 'manage_payments.php';
            } else if (sectionName === 'manageUsers') {
                window.location.href = 'users.php';
            } else {
                const sectionToShow = document.getElementById(sectionName + 'Section');
                if (sectionToShow) {
                    sectionToShow.style.display = 'block';
                }
            }
        }

        // Function to show edit forms
        function showEditForm(type, id) {
            const editForm = document.getElementById('edit' + type + 'Form');
            if (editForm) {
                editForm.style.display = 'block';
                
                if (type === 'Game') {
                    document.getElementById('edit_game_id').value = id;
                    document.getElementById('edit_game_name').value = document.getElementById(`game_name_${id}`).textContent;
                    document.getElementById('edit_number_of_players').value = document.getElementById(`players_${id}`).textContent;
                    document.getElementById('edit_description').value = document.getElementById(`description_${id}`).textContent;
                } else if (type === 'Tournament') {
                    const row = document.querySelector(`tr[data-tournament-id="${id}"]`);
                    document.getElementById('edit_tournament_id').value = id;
                    document.getElementById('edit_tournament_name').value = document.getElementById(`tournament_name_${id}`).textContent;
                    document.getElementById('edit_entry_fee').value = document.getElementById(`entry_fee_${id}`).textContent;
                    document.getElementById('edit_prize_money').value = document.getElementById(`prize_money_${id}`).textContent;
                    document.getElementById('edit_slots').value = document.getElementById(`slots_${id}`).textContent;
                    document.getElementById('edit_tournament_date').value = document.getElementById(`tournament_date_${id}`).textContent;
                    document.getElementById('edit_registration_deadline').value = document.getElementById(`registration_deadline_${id}`).textContent;
                    
                    // Set the game type in dropdown
                    const gameType = document.getElementById(`game_type_${id}`).textContent;
                    const gameSelect = document.getElementById('edit_game_id');
                    Array.from(gameSelect.options).forEach(option => {
                        if (option.text === gameType) {
                            option.selected = true;
                        }
                    });
                }
            }
        }

        // Function to close edit forms
        function closeEditForm(type) {
            const editForm = document.getElementById('edit' + type + 'Form');
            if (editForm) {
                editForm.style.display = 'none';
            }
        }

        // Auto-remove alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>
