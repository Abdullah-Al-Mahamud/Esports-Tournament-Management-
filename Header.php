<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Header</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .navbar {
            background-color: rgba(0, 0, 0, 0.8);
        }
        .navbar-brand, .nav-link {
            color: white !important;
        }
        .nav-link:hover {
            color: #2193b0 !important;
        }
        .user-profile {
            display: flex;
            align-items: center;
            color: white;
        }
        .user-profile img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .dropdown-menu {
            background-color: rgba(0, 0, 0, 0.9);
        }
        .dropdown-item {
            color: white;
        }
        .dropdown-item:hover {
            background-color: rgba(33, 147, 176, 0.3);
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="/Esports_Tournament/index.php">GAMEON</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/Esports_Tournament/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Esports_Tournament/tournament/tournaments.php">Tournaments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Esports_Tournament/news.php">News</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/Esports_Tournament/user/profile.php"><?php echo htmlspecialchars($_SESSION['user_name']); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/Esports_Tournament/user/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/Esports_Tournament/user/LogIn.php">Login/Signup</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>