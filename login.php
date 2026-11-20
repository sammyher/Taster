<?php
session_start();

require_once __DIR__ . '/config/auth.php';

$_SESSION['target_role'] = $_GET['role'] ?? 'attendee';

// echo '<pre>';
// echo "LOGIN SESSION ID: " . session_id() . "\n";
// var_dump($_SESSION);
// echo '</pre>';
// exit;

$loginUrl = $auth0->login();

header('Location: ' . $loginUrl);
exit;
