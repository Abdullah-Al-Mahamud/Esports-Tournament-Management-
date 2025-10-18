<?php
session_start();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/CSE370-Project/index.php">CracCloud</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/Esports_Tournament/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/Esports_Tournament/tournament/tournaments.php">Tournaments</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/Esports_Tournament/Tournament/teams.php">Teams</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_email'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']); ?>
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="/Esports_Tournament/user/profile.php">Profile</a>
                            <a class="dropdown-item" href="/Esports_Tournament/user/my_tournaments.php">My Tournaments</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="/Esports_Tournament/user/logout.php">Logout</a>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/Esports_Tournament/user/LogIn.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Esports_Tournament/user/LogIn.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 