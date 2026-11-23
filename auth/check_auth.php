
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['staff_id'])) {
    header('Location: auth/login.php');
    exit;
}

