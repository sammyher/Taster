<?php
session_start();

require_once __DIR__ . '/auth.php';

$_SESSION['target_role'] = $_GET['role'] ?? 'attendee';

$loginUrl = $auth0->login();

header('Location: ' . $loginUrl);
exit;
