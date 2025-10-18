<?php
session_start();

// If already logged in, redirect to admin page
if(isset($_SESSION['admin_email'])) {
    header("Location: admin.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // check the fixed or pre-programmed email & password
    if ($email === 'mahmudmohot@gmail.com' && $password === 'mohot123') {
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit();
    } else {
        $error = "Invalid email or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url('../assets/images/all_background.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: white;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background-color: rgba(42, 42, 42, 0.9);
            padding: 30px;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            backdrop-filter: blur(5px);
        }
        .form-control {
            background-color: rgba(51, 51, 51, 0.8);
            border: 1px solid #444;
            color: white;
        }
        .form-control:focus {
            background-color: rgba(64, 64, 64, 0.9);
            color: white;
            border-color: #007bff;
        }
        .btn-primary {
            width: 100%;
            background-color: rgba(0, 123, 255, 0.9);
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">Admin Login</h2>
        <?php if($error): ?>
            <div class="error-message text-center"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</body>
</html>
