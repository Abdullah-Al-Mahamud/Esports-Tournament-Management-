<?php
session_start();
require_once '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_email']) || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "User deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting user: " . $conn->error;
    }
    header("Location: users.php");
    exit();
}

// Handle user update
if (isset($_POST['update_user'])) {
    $user_id = intval($_POST['user_id']);
    $name = $_POST['name'];
    $email = $_POST['email'];
    $number = $_POST['number'];
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, number = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $email, $number, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "User information updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating user: " . $conn->error;
    }
    header("Location: users.php");
    exit();
}

// Fetch all users
$users_query = "SELECT * FROM users ORDER BY id DESC";
$users_result = $conn->query($users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .nav-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .table th {
            background-color: #07a6bc;
            color: white;
        }
        .btn-action {
            margin: 2px;
        }
        .modal-header {
            background-color: #07a6bc;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-top">
            <h2><i class="fas fa-users"></i> User Management</h2>
            <div>
                <a href="admin.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['number']); ?></td>
                            <td>
                                <button class="btn btn-info btn-sm btn-action" data-toggle="modal" data-target="#viewModal<?php echo $user['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-warning btn-sm btn-action" data-toggle="modal" data-target="#editModal<?php echo $user['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-action" data-toggle="modal" data-target="#deleteModal<?php echo $user['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- View Modal -->
                        <div class="modal fade" id="viewModal<?php echo $user['id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">User Information</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>ID:</strong> <?php echo htmlspecialchars($user['id']); ?></p>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                        <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($user['number']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?php echo $user['id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit User</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Email</label>
                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Phone Number</label>
                                                <input type="text" class="form-control" name="number" value="<?php echo htmlspecialchars($user['number']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            <button type="submit" name="update_user" class="btn btn-primary">Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete User</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete this user?</p>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                    <div class="modal-footer">
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
