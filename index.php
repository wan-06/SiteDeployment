<?php
/* index.php — Entry point: redirect to dashboard or login */
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;

