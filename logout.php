<?php
/* logout.php — Destroy session and redirect to login */
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;

