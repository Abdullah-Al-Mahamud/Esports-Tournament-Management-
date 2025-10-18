<?php
session_start();
include('config.php');


$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = $user_id"; 
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_email = mysqli_real_escape_string($conn, $_POST['email']);
    $new_number = mysqli_real_escape_string($conn, $_POST['number']);

    
    $update_query = "UPDATE users SET email='$new_email', number='$new_number' WHERE id=$user_id";
    mysqli_query($conn, $update_query);

    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Account</title>
</head>
<body>
    <form method="POST">
        <label for="email">Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        <label for="number">Number:</label>
        <input type="text" name="number" value="<?php echo htmlspecialchars($user['number']); ?>" required>
        <button type="submit">Update</button>
    </form>
</body>
</html>
