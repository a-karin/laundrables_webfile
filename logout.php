<?php
require_once __DIR__ . '/config/db.php';

unset($_SESSION['user_id'], $_SESSION['role']);
$_SESSION['logged_out'] = true;

header('Location: login.php');
exit;
