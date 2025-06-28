<?php
require_once 'config.php';

// Redirect based on login status
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit();
?>
