<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: LogIn.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// If user data not found, redirect to login
if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_profile'])) {
        try {
            $conn->begin_transaction();
            
            // Delete user's team registrations and related data
            $reg_stmt = $conn->prepare("DELETE FROM team_registrations WHERE manager_email = ?");
            $reg_stmt->bind_param("s", $_SESSION['user_email']);
            $reg_stmt->execute();
            
            // Delete user's teams
            $team_stmt = $conn->prepare("DELETE FROM teams WHERE manager_email = ?");
            $team_stmt->bind_param("s", $_SESSION['user_email']);
            $team_stmt->execute();
            
            // Delete the user's profile picture if exists
            if (!empty($user['picture']) && file_exists($user['picture'])) {
                unlink($user['picture']);
            }
            
            // Finally, delete the user
            $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $delete_stmt->bind_param("i", $_SESSION['user_id']);
            $delete_stmt->execute();
            
            $conn->commit();
            
            // Clear session and redirect to registration
            session_destroy();
            header("Location: ../auth/register.php?message=profile_deleted");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error deleting profile: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_picture'])) {
        // Delete the physical file if it exists
        if (!empty($user['picture']) && file_exists('../' . $user['picture'])) {
            unlink('../' . $user['picture']);
        }
        
        // Update database to remove picture reference
        $stmt = $conn->prepare("UPDATE users SET picture = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Profile picture deleted successfully!";
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $error_message = "Error deleting profile picture: " . $conn->error;
        }
        $stmt->close();
    } else {
        $name = $_POST['name'];
        $number = $_POST['number'];
        $email = $_POST['email'];
        $team_name = $_POST['team_name'];
        
        // Handle picture upload
        $picture_sql = "";
        $types = "ssss";
        $params = array($name, $number, $email, $team_name);
        
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === 0) {
            // Delete old picture if it exists
            if (!empty($user['picture']) && file_exists('../' . $user['picture'])) {
                unlink('../' . $user['picture']);
            }
            
            $upload_dir = 'uploads/users/';
            if (!file_exists('../' . $upload_dir)) {
                mkdir('../' . $upload_dir, 0777, true);
            }
            
            // Generate a unique filename to prevent overwriting
            $file_extension = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid('profile_') . '.' . $file_extension;
            $relative_path = $upload_dir . $unique_filename;
            $full_path = '../' . $relative_path;
            
            move_uploaded_file($_FILES['picture']['tmp_name'], $full_path);
            $picture_sql = ", picture = ?";
            $types .= "s";
            $params[] = $relative_path; // Store the relative path in the database
        }
        
        // Add user_id to params
        $types .= "i";
        $params[] = $user_id;
        
        $stmt = $conn->prepare("UPDATE users SET name = ?, number = ?, email = ?, team_name = ?" . $picture_sql . " WHERE id = ?");
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-image: url('../assets/images/all_background.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        .profile-container {
            background-color: rgba(42, 42, 42, 0.9);
            border-radius: 15px;
            padding: 30px;
            margin-top: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            color: white;
        }
        .form-control {
            background-color: rgba(51, 51, 51, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        .form-control:focus {
            background-color: rgba(64, 64, 64, 0.9);
            color: white;
            border-color: #2193b0;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 20px;
        }
        .btn-primary {
            background-color: #2193b0;
            border: none;
        }
        .btn-primary:hover {
            background-color: #1c7a8e;
        }
        .picture-controls {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .delete-profile-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .delete-profile-btn:hover {
            background-color: #c82333;
        }
        .default-profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <div class="text-center mb-4">
                <?php if (isset($user['picture']) && $user['picture']): ?>
                    <img src="../<?php echo htmlspecialchars($user['picture']); ?>" alt="Profile Picture" class="profile-picture">
                    <div class="picture-controls">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="delete_picture" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete your profile picture?')">
                                <i class="fas fa-trash"></i> Delete Picture
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <img src="../assets/images/default-avatar.png" alt="Default Profile Picture" class="default-profile-img">
                <?php endif; ?>
                <h2>User Profile</h2>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo isset($user['name']) ? htmlspecialchars($user['name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="number" class="form-control" value="<?php echo isset($user['number']) ? htmlspecialchars($user['number']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Team Name (Optional)</label>
                    <input type="text" name="team_name" class="form-control" value="<?php echo isset($user['team_name']) ? htmlspecialchars($user['team_name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Profile Picture (Optional)</label>
                    <input type="file" name="picture" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Update Profile</button>
            </form>
            <div class="text-center mt-3">
                <a href="../index.php" class="btn btn-secondary mr-2">Back to Home</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
                <form method="POST" style="display: inline-block;" class="mt-3">
                    <button type="submit" name="delete_profile" class="delete-profile-btn" 
                            onclick="return confirm('Are you sure you want to delete your profile? This action cannot be undone.')">
                        <i class="fas fa-user-times"></i> Delete Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Tournament Registration History</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tournament</th>
                                        <th>Team Name</th>
                                        <th>Registration Date</th>
                                        <th>Payment Method</th>
                                        <th>Payment Status</th>
                                        <th>Players</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get user's tournament registrations
                                    $stmt = $conn->prepare("
                                        SELECT tr.*, t.tournament_name, tm.team_name, 
                                        tr.created_at as registration_date
                                        FROM team_registrations tr
                                        JOIN tournaments t ON tr.tournament_id = t.id
                                        JOIN teams tm ON tr.team_id = tm.id
                                        WHERE tr.manager_email = ? 
                                        ORDER BY tr.created_at DESC
                                    ");
                                    $stmt->bind_param("s", $user['email']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    
                                    while ($registration = $result->fetch_assoc()) {
                                        // Get players for this team registration
                                        $player_stmt = $conn->prepare("
                                            SELECT player_name 
                                            FROM team_players 
                                            WHERE team_id = ?
                                        ");
                                        $player_stmt->bind_param("i", $registration['team_id']);
                                        $player_stmt->execute();
                                        $players = $player_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($registration['tournament_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($registration['team_name']) . '</td>';
                                        echo '<td>' . date('Y-m-d H:i', strtotime($registration['registration_date'])) . '</td>';
                                        echo '<td>' . ucfirst(htmlspecialchars($registration['payment_method'])) . '</td>';
                                        echo '<td>' . ucfirst(htmlspecialchars($registration['payment_status'])) . '</td>';
                                        echo '<td>';
                                        foreach ($players as $player) {
                                            echo htmlspecialchars($player['player_name']) . '<br>';
                                        }
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                    
                                    if ($result->num_rows === 0) {
                                        echo '<tr><td colspan="6" class="text-center">No tournament registrations found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
