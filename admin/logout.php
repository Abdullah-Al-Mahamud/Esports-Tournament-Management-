<?php
session_start();
// Clear session data
session_unset();
session_destroy();
// Redirect to index page
header('Location: ../index.php');
exit();
?>
