<?php
session_start();
require_once('../db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['tournament_id'])) {
    header('Location: tournaments.php');
    exit();
}

$tournament_id = $_GET['tournament_id'];

// Check if user has already registered for this tournament
$check_registration = $conn->prepare("
    SELECT payment_status 
    FROM team_registrations tr
    JOIN teams t ON tr.team_id = t.id
    WHERE tr.tournament_id = ? AND t.manager_email = ?
");
$check_registration->bind_param("is", $tournament_id, $_SESSION['user_email']);
$check_registration->execute();
$result = $check_registration->get_result();

if ($result->num_rows > 0) {
    $registration = $result->fetch_assoc();
    $_SESSION['error'] = "You have already registered for this tournament. Current status: " . ucfirst($registration['payment_status']);
    header('Location: tournaments.php');
    exit();
}

// Get tournament details
$stmt = $conn->prepare("SELECT t.*, g.number_of_players FROM tournaments t JOIN games g ON t.game_type = g.game_name WHERE t.id = ?");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();
$tournament = $result->fetch_assoc();

if (!$tournament) {
    header('Location: tournaments.php');
    exit();
}

$required_players = $tournament['number_of_players'];

// Get user details and team name if exists
$manager_name = '';
$team_name = '';
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT name, email, number, team_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $manager_name = $user['name'];
    $manager_email = $user['email'];
    $manager_phone = $user['number'];
    $team_name = $user['team_name'];

    // Check if user has a team in the teams table
    if (empty($team_name)) {
        $team_stmt = $conn->prepare("SELECT team_name FROM teams WHERE manager_email = ? LIMIT 1");
        $team_stmt->bind_param("s", $manager_email);
        $team_stmt->execute();
        $team_result = $team_stmt->get_result();
        if ($team_result->num_rows > 0) {
            $team_data = $team_result->fetch_assoc();
            $team_name = $team_data['team_name'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Registration - <?php echo htmlspecialchars($tournament['tournament_name']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/registration.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container">
        <div class="registration-form">
            <h2 class="text-center mb-4">Tournament Registration</h2>
            <h4 class="text-center mb-4"><?php echo htmlspecialchars($tournament['tournament_name']); ?></h4>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="alert alert-warning">Please <a href="../auth/login.php">login</a> to register for the tournament.</div>
            <?php else: ?>
                <form id="registrationForm" action="process_registration.php" method="POST">
                    <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
                    
                    <div class="form-group">
                        <label for="team_name">Team Name</label>
                        <input type="text" class="form-control" id="team_name" name="team_name" value="<?php echo htmlspecialchars($team_name); ?>" required>
                        <small class="form-text text-muted">This will also update your profile team name if not set.</small>
                    </div>

                    <div class="form-group">
                        <label for="manager_name">Manager Name</label>
                        <input type="text" class="form-control" id="manager_name" name="manager_name" value="<?php echo htmlspecialchars($manager_name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="manager_phone">Manager Phone</label>
                        <input type="text" class="form-control" id="manager_phone" name="manager_phone" value="<?php echo htmlspecialchars($manager_phone ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="manager_email">Manager Email</label>
                        <input type="email" class="form-control" id="manager_email" name="manager_email" value="<?php echo htmlspecialchars($manager_email ?? ''); ?>" required readonly>
                    </div>

                    <div id="player_list">
                        <h4>Players (Required: <?php echo $required_players; ?>)</h4>
                        <?php for ($i = 1; $i <= $required_players; $i++): ?>
                            <div class="player-item">
                                <div class="form-group">
                                    <label>Player <?php echo $i; ?> Name</label>
                                    <input type="text" class="form-control player-input" name="players[]" required>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-submit">Proceed to Payment</button>
                        <a href="tournaments.php" class="btn btn-secondary ml-2">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#registrationForm').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    type: 'POST',
                    url: 'process_registration.php',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.redirect;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred. Please try again.'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
