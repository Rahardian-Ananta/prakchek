<?php
require_once 'includes/auth.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    header('Location: /prakchek_/login.php');
    exit;
}

// Redirect to dashboard if logged in
header('Location: /prakchek_/dashboard.php');
exit;
