<?php
session_start();
session_destroy();
header("Location: /Esports_Tournament/index.php");
exit();
?>
