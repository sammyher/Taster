<?php

declare(strict_types=1);

$hostname = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];

if (!in_array($hostname, ['localhost', '127.0.0.1'], true)) {
    http_response_code(404);
    exit('Not Found');
}

session_start();
session_regenerate_id(true);

$_SESSION['account_id'] = 'dev-account-001';
$_SESSION['display_name'] = 'Development Host';
$_SESSION['is_development_login'] = true;

header('Location: create-event.php');
exit;

