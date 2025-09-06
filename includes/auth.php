<?php
session_start();

function checkAdminAuth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: login.php");
        exit();
    }
}

function logout() {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

