<?php
/**
 * logout.php — Destroys session and redirects to login.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
?>
