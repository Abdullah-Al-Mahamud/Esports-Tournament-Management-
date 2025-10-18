<?php
session_start();
require_once '../db.php';

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle Login Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Secure the query with prepared statements
    // bind variables to the placeholders
    $query = "SELECT * FROM users WHERE email=?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            header("Location: profile.php");
            exit();
        } else {
            $_SESSION['login_error'] = "Invalid password!";
        }
    } else {
        $_SESSION['login_error'] = "User not found!";
    }
}

// Handle Sign Up Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    $name = $_POST['name'];
    $number = $_POST['number'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Check if email already exists

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['signup_error'] = "Email already exists. Please try logging in.";
    } else {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (name, number, email, password) VALUES (?, ?, ?, ?)");
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bind_param("ssss", $name, $number, $email, $hashed_password);

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['user_name'] = $name;
            header("Location: profile.php");
            exit();
        } else {
            $_SESSION['signup_error'] = "Error creating account. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Or Sign Up</title>
    <link rel="stylesheet" href="../assets/css/LogIn_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body>
    <div class="ComeOnBoss">
        <div class="Form-box">
            <div class="button-box">
                <div id="btn"></div>
                <button type="button" class="toggle-btn" onclick="Login()" size="100px"><b>Log in</b></button>
                <button type="button" class="toggle-btn" onclick="SignUp()" size="100px"><b>Sign Up</b></button>
            </div>
            <?php if (isset($_SESSION['login_error'])): ?>
                <p style="color: red; text-align: center;"><?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['signup_error'])): ?>
                <p style="color: red; text-align: center;"><?php echo $_SESSION['signup_error']; unset($_SESSION['signup_error']); ?></p>
            <?php endif; ?>
            <form id="login" class="input-group" method="POST">
                <input type="text" class="input-field" name="email" placeholder="Email" required>
                <input type="password" class="input-field" name="password" placeholder="Enter Password" required>
                <input type="checkbox" class="check-box" size="60px"><span><b>Remember Password</b></span>
                <button type="submit" name="login" class="submit-btn" size="50px"><b>Log in</b></button>
            </form>
            <form id="Sign-Up" class="input-group" method="POST" size="100%">
                <input type="text" class="input-field" name="name" placeholder="Full Name" required>
                <input type="text" class="input-field" name="number" placeholder="Phone Number" required>
                <input type="email" class="input-field" name="email" placeholder="Email" required>
                <input type="password" class="input-field" name="password" placeholder="Enter Password" required>
                <input type="checkbox" class="check-box"><span><b>I agree to the terms & conditions</b></span>
                <button type="submit" name="signup" class="submit-btn"><b>Sign Up</b></button>
            </form>
        </div>
    </div>
    <script src="../assets/js/login.js"></script>
</body>
</html>
