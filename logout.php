<?php
require_once 'includes/auth.php';

logoutUser();

header('Location: /prakchek_/login.php');
exit;
